import { createHmac, timingSafeEqual } from 'node:crypto';

/**
 * Verifies an inbound scan webhook signature.
 *
 * Header shape (sent by the platform): `X-Scanner-Signature: t=<unix>,v1=<hex>`
 * where `hex = hmac_sha256("<t>.<rawBody>", secret)`.
 *
 * Returns true on a valid signature within the tolerance window (default
 * 300s — same as Stripe). Throws on a missing or malformed header so
 * misconfiguration surfaces loudly instead of silently allowing forgeries.
 *
 * Webhook bodies must be passed as the *raw* string (the receiver should
 * read the request body before any JSON.parse to avoid normalising).
 */
export function verifyWebhookSignature(
    rawBody: string,
    header: string | undefined,
    secret: string,
    toleranceSeconds: number = 300,
): boolean {
    if (!header) throw new Error('Missing X-Scanner-Signature header.');

    const parts = Object.fromEntries(
        header.split(',').map((p) => {
            const [k, v] = p.split('=');
            return [k?.trim(), v?.trim()];
        }),
    );

    const t = parts['t'];
    const v1 = parts['v1'];
    if (!t || !v1) {
        throw new Error('Malformed X-Scanner-Signature header.');
    }

    const ts = Number.parseInt(t, 10);
    if (!Number.isFinite(ts)) {
        throw new Error('Signature timestamp is not numeric.');
    }

    const now = Math.floor(Date.now() / 1000);
    if (Math.abs(now - ts) > toleranceSeconds) {
        return false;
    }

    const expected = createHmac('sha256', secret).update(`${ts}.${rawBody}`).digest('hex');
    const a = Buffer.from(expected, 'hex');
    const b = Buffer.from(v1, 'hex');
    if (a.length !== b.length) return false;

    return timingSafeEqual(a, b);
}
