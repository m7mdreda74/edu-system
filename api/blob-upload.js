import crypto from 'node:crypto';
import { issueSignedToken, presignUrl } from '@vercel/blob';

const ALLOWED_KINDS = new Set(['booklet', 'homework', 'exam']);
const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;
const ALLOWED_CONTENT_TYPES = new Set([
    'application/msword',
    'application/pdf',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip',
    'image/jpeg',
    'image/png',
]);

function parseBody(body) {
    if (typeof body === 'string') {
        return JSON.parse(body);
    }

    if (Buffer.isBuffer(body)) {
        return JSON.parse(body.toString('utf8'));
    }

    return body;
}

function verifyAuthorization(token, pathname) {
    const signingKey = process.env.APP_KEY;
    const [encodedPayload, suppliedSignature, extra] = String(token ?? '').split('.');

    if (!signingKey || !encodedPayload || !suppliedSignature || extra !== undefined) {
        throw new Error('Missing upload authorization.');
    }

    const expectedSignature = crypto
        .createHmac('sha256', signingKey)
        .update(encodedPayload)
        .digest('hex');

    const supplied = Buffer.from(suppliedSignature, 'hex');
    const expected = Buffer.from(expectedSignature, 'hex');

    if (
        supplied.length !== expected.length
        || !crypto.timingSafeEqual(supplied, expected)
    ) {
        throw new Error('Invalid upload authorization.');
    }

    const payload = JSON.parse(
        Buffer.from(encodedPayload, 'base64url').toString('utf8'),
    );

    if (
        payload.pathname !== pathname
        || !ALLOWED_KINDS.has(payload.kind)
        || !Number.isInteger(payload.teacher_id)
        || !Number.isInteger(payload.target_id)
        || !Number.isInteger(payload.max_bytes)
        || payload.max_bytes < 1
        || payload.max_bytes > MAX_UPLOAD_BYTES
        || !Array.isArray(payload.allowed_content_types)
        || payload.allowed_content_types.some((contentType) => !ALLOWED_CONTENT_TYPES.has(contentType))
        || !Number.isInteger(payload.expires_at_ms)
        || payload.expires_at_ms <= Date.now()
    ) {
        throw new Error('Expired or mismatched upload authorization.');
    }

    const prefix = `curriculum/${payload.teacher_id}/${payload.kind}/${payload.target_id}/`;

    if (!pathname.startsWith(prefix) || pathname.length > 950 || pathname.includes('//')) {
        throw new Error('Invalid upload pathname.');
    }

    return payload;
}

export default async function handler(request, response) {
    response.setHeader('X-Content-Type-Options', 'nosniff');
    response.setHeader('X-Frame-Options', 'DENY');
    response.setHeader('Referrer-Policy', 'no-referrer');
    response.setHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

    if (request.method !== 'POST') {
        response.setHeader('Allow', 'POST');
        return response.status(405).json({ error: 'Method not allowed.' });
    }

    try {
        const body = parseBody(request.body);
        const pathname = String(body?.pathname ?? '');
        const authorization = verifyAuthorization(
            body?.authorization,
            pathname,
        );

        // issueSignedToken uses Vercel's rotating OIDC credentials when the
        // project is connected to a Blob store, and falls back to the legacy
        // BLOB_READ_WRITE_TOKEN when one is explicitly configured.
        const signedToken = await issueSignedToken({
            pathname,
            operations: ['put'],
            maximumSizeInBytes: authorization.max_bytes,
            allowedContentTypes: authorization.allowed_content_types,
            validUntil: authorization.expires_at_ms,
        });
        const { presignedUrl } = await presignUrl(signedToken, {
            operation: 'put',
            pathname,
            access: 'private',
            allowedContentTypes: authorization.allowed_content_types,
            addRandomSuffix: true,
            maximumSizeInBytes: authorization.max_bytes,
            validUntil: authorization.expires_at_ms,
        });

        return response.status(200).json({
            upload_url: presignedUrl,
        });
    } catch (error) {
        console.error(
            'Curriculum Blob upload authorization failed.',
            error instanceof Error ? error.message : 'Unknown error',
        );

        return response.status(400).json({
            error: 'Unable to authorize this upload.',
        });
    }
}
