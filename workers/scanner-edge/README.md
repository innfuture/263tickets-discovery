# scanner-edge

Cloudflare Worker fronting `/api/v1/scanning/scan`. Verifies the QR
HMAC signature + checks a void-cache at the edge before falling
through to the origin.

## Why

- Reduces door-side latency from ~150–300ms to ~5–20ms for the 90%
  of scans that pre-check cleanly.
- Survives origin outages: returns `verdict=warn` (admit with manual
  review) instead of a hard 5xx when origin is unreachable.
- Cheap DDoS shield — malformed / unsigned scans never reach origin.

## Setup

1. **Match the QR secret with origin.** Set the worker secret to
   the same value the Laravel app uses to sign QR payloads
   (`config('app.key')` decoded — see `OrderFulfillment::issueOfflineTicket`):

   ```bash
   wrangler secret put QR_SIGNING_SECRET
   ```

2. **Create the void cache.**

   ```bash
   wrangler kv:namespace create voided-tickets
   ```

   Paste the returned namespace id into `wrangler.toml`'s
   `kv_namespaces[0].id`.

3. **Wire the origin to populate the cache.** On the Laravel side,
   add an Observer on `OfflineTicket` that does:

   ```php
   if ($ticket->is_voided) {
       app('cloudflare-kv')->put(
           namespace: 'voided-tickets',
           key: $ticket->uuid,
           value: $ticket->void_reason ?? 'voided',
       );
   }
   ```

   (Wrap the Cloudflare KV REST API in a thin service — out of
   scope for the worker repo itself.)

4. **Deploy + DNS.**

   ```bash
   wrangler deploy
   ```

   Then add a Cloudflare route: `scan.app.example.com/*` → this
   worker. Update the scanner SDK's `baseUrl` to that hostname.

## Tests

```bash
npm install
npm test
```

Five vitest cases: malformed payload, bad signature, KV-cached void,
happy-path forward to origin, origin-unreachable degraded verdict.
