import crypto from 'node:crypto';
import { Readable } from 'node:stream';
import { get } from '@vercel/blob';

const MAX_TOKEN_LENGTH = 4096;
const MAX_DOWNLOAD_SECONDS = 15 * 60;

function blobAuthOptions() {
    const oidcToken = process.env.VERCEL_OIDC_TOKEN;
    const storeId = process.env.BLOB_STORE_ID;

    if (oidcToken && storeId) {
        return { oidcToken, storeId };
    }

    const token = process.env.BLOB_READ_WRITE_TOKEN;

    return {
        ...(token ? { token } : {}),
        ...(storeId ? { storeId } : {}),
    };
}

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
    const blobUrl = payload?.blob_url ? String(payload.blob_url) : null;
    const expiresAt = Number(payload?.expires_at_ms ?? 0);

    if (
        (!pathname.startsWith('curriculum/') && !pathname.startsWith('payments/receipts/'))
        || pathname.includes('//')
        || pathname.includes('..')
        || pathname.includes('\\')
        || !Number.isInteger(expiresAt)
        || expiresAt <= Date.now()
        || expiresAt > Date.now() + (MAX_DOWNLOAD_SECONDS * 1000) + 5000
    ) {
        throw new Error('Expired or invalid download token.');
    }

    if (blobUrl) {
        const parsedUrl = new URL(blobUrl);
        const blobPathname = decodeURIComponent(parsedUrl.pathname.replace(/^\//, ''));

        if (
            parsedUrl.protocol !== 'https:'
            || !parsedUrl.hostname.endsWith('.blob.vercel-storage.com')
            || parsedUrl.username
            || parsedUrl.password
            || parsedUrl.port
            || blobPathname !== pathname
        ) {
            throw new Error('Invalid Blob URL in download token.');
        }
    }

    return { pathname, blobUrl, expiresAt };
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
        const { pathname, blobUrl } = decodeToken(request.query?.token);
        const isReceipt = pathname.startsWith('payments/receipts/');

        if (isReceipt) {
            response.setHeader('X-Frame-Options', 'SAMEORIGIN');
            response.setHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'self'");
        }

        const result = await get(blobUrl || pathname, {
            ...blobAuthOptions(),
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
            isVideo || isReceipt ? 'inline' : (result.blob.contentDisposition || 'attachment'),
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
