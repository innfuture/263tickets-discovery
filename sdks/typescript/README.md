# `@example-app/scanner-sdk`

Official TypeScript SDK for the example-app scanner API.

## Install

```bash
npm install @example-app/scanner-sdk
```

Requires Node 18+ (uses the global `fetch`).

## Pair a device

```ts
import { ScannerClient } from '@example-app/scanner-sdk';

const sdk = new ScannerClient({ baseUrl: 'https://app.example.com' });

// Organizer issues a code from the web UI; the operator types it into the app.
const { token, profile } = await sdk.pair({
    code: 'ABC23XYZ',
    device_label: "John's iPhone",
    platform: 'ios',
    app_version: '1.0.0',
});

// Persist the token in the OS keychain — never log it.
await keychain.set('scanner-token', token);
```

## Scan a ticket

```ts
const sdk = new ScannerClient({
    baseUrl: 'https://app.example.com',
    token: await keychain.get('scanner-token'),
});

try {
    const result = await sdk.scan({
        payload: scannedQrPayload,
        client_lat: location.coords.latitude,
        client_lng: location.coords.longitude,
        device_meta: { battery: 0.41, signal: '4g' },
    });

    if (result.was_admitted) admitGuest(result.ticket);
    else if (result.verdict === 'warn') showWarning(result.flags);
} catch (e) {
    if (e instanceof ScanDeniedError) showDenialReason(e.reasonCode, e.flags);
    else throw e;
}
```

## Offline-first batch

```ts
const queuedScans = await db.queue.all();
const { results } = await sdk.batchScan(queuedScans);
for (const [i, r] of results.entries()) {
    if (r.verdict !== 'deny' || !('reason_code' in r && r.reason_code === 'processing_error')) {
        await db.queue.delete(queuedScans[i].id);
    }
}
```

## Webhook verification

If your backend receives scan webhooks from the platform, verify the
signature before trusting the payload:

```ts
import { verifyWebhookSignature } from '@example-app/scanner-sdk';

// In your express / fastify handler:
const rawBody = await getRawBody(req);
const sig = req.headers['x-scanner-signature'] as string;

if (!verifyWebhookSignature(rawBody, sig, process.env.SCANNER_SECRET!)) {
    return res.status(401).send('invalid signature');
}

const event = JSON.parse(rawBody);
// process event.scan …
```

## Error types

- `ScanDeniedError` — verdict came back `deny`. Carries `reasonCode` + `flags`.
- `ScannerApiError` — HTTP non-2xx response. Carries the server's `error` code and `message`.
- `ScannerSDKError` — base class (network errors, missing token, validation).

## Build + test

```bash
npm install
npm test          # vitest
npm run build     # emits dist/
```
