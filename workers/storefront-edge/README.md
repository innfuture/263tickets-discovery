# storefront-edge

Cloudflare Worker that personalises the public storefront JSON at
the edge — before any request hits origin.

## What it does

For configured paths (default: `/api/v1/public/events/featured`,
`/api/v1/public/events`):

- Adds `preferred_currency` to each event summary based on
  `CF-IPCountry`.
- Reorders the response so country-match events come first.
- Echoes `locale` from `Accept-Language` into `meta`.
- Caches the personalised JSON in KV per `(path, country)` for the
  origin's `Cache-Control: max-age` window (default 30s).

Non-listed paths pass through unchanged.

## Setup

```bash
cd workers/storefront-edge
npm install
wrangler kv:namespace create personalised-cache  # paste id into wrangler.toml
wrangler deploy
```

Then add a Cloudflare route mapping your storefront hostname →
this worker.

## Why edge

Origin: ~150ms for a personalised featured list (DB hit + serialisation).
Edge cache hit: ~5ms. Cache miss with personalisation: ~80ms (origin
fetch dominates) + 10ms for the reshape.

90%+ of requests in a tight burst land on the cache.
