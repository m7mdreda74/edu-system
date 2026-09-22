import crypto from 'node:crypto';
import { Readable } from 'node:stream';
import { get } from '@vercel/blob';

const MAX_TOKEN_LENGTH = 4096;
const MAX_DOWNLOAD_SECONDS = 15 * 60;

function decodeToken(token) {
    const [encodedPayload, suppliedSignature, extra] = String(token ?? '').split('.');
    const signingKey = process.env.APP_KEY;

    if (
        !signingKey
        || !encodedPayload
        || !suppliedSignature
        || extra !== undefined
        || String(token).length > MAX_TOKEN_LENGTH
    ) {
        throw new Error('Invalid download token.');
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
        throw new Error('Invalid download token.');
    }

    const payload = JSON.parse(
        Buffer.from(encodedPayload, 'base64url').toString('utf8'),
    );
    const pathname = String(payload?.pathname ?? '');
    const expiresAt = Number(payload?.expires_at_ms ?? 0);

    if (
        !pathname.startsWith('curriculum/')
        || pathname.includes('//')
        || pathname.includes('..')
        || pathname.includes('\\')
        || !Number.isInteger(expiresAt)
        || expiresAt <= Date.now()
        || expiresAt > Date.now() + (MAX_DOWNLOAD_SECONDS * 1000) + 5000
    ) {
        throw new Error('Expired or invalid download token.');
    }

    return { pathname, expiresAt };
}

export default async function handler(request, response) {
    response.setHeader('X-Content-Type-Options', 'nosniff');
    response.setHeader('X-Frame-Options', 'DENY');
    response.setHeader('Referrer-Policy', 'no-referrer');
    response.setHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

    if (request.method !== 'GET') {
        response.setHeader('Allow', 'GET');
        return response.status(405).json({ error: 'طريقة الطلب غير مسموحة.' });
    }

    try {
        const { pathname } = decodeToken(request.query?.token);
        const token = process.env.BLOB_READ_WRITE_TOKEN;
        const storeId = process.env.BLOB_STORE_ID;

        const result = await get(pathname, {
            ...(token ? { token } : {}),
            ...(storeId ? { storeId } : {}),
            access: 'private',
            useCache: false,
            headers: request.headers.range
                ? { Range: request.headers.range }
                : undefined,
        });

        if (!result || ![200, 206].includes(result.statusCode)) {
            return response.status(404).json({ error: 'الملف المطلوب غير موجود.' });
        }

        response.statusCode = result.statusCode;
        response.setHeader('Content-Type', result.blob.contentType || 'application/octet-stream');
        const isVideo = (result.blob.contentType || '').startsWith('video/');
        response.setHeader(
            'Content-Disposition',
            isVideo ? 'inline' : (result.blob.contentDisposition || 'attachment'),
        );
        response.setHeader('Cache-Control', 'private, no-store');
        response.setHeader('X-Content-Type-Options', 'nosniff');

        for (const header of ['accept-ranges', 'content-length', 'content-range', 'etag', 'last-modified']) {
            const value = result.headers.get(header);
            if (value) {
                response.setHeader(header, value);
            }
        }

        Readable.fromWeb(result.stream).pipe(response);
    } catch (error) {
        console.error(
            'Private Blob download failed.',
            error instanceof Error ? error.message : 'Unknown error',
        );

        return response.status(404).json({ error: 'الملف المطلوب غير موجود.' });
    }
}
