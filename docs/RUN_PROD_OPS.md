# Production operations playbook

How to actually run example-app at high volume. Distilled from the
roadmap audit — pair this with `ARCHITECTURE.md` (system-shape) and
`.env.example` (per-env knobs).

---

## 1. Topology baseline

| Component | Recommended minimum | Notes |
|---|---|---|
| **Web tier** | 4× PHP-FPM nodes behind nginx | Octane gives ~3× throughput if you can afford the worker memory cost |
| **Queue tier** | 2× nodes, per-queue workers | See §3 below for per-queue worker counts |
| **Database** | RDS-style primary + 1 read replica | Read replica routing is wired (`DB_READ_HOST` env) |
| **Cache** | Redis cluster, persistent | Required, not optional — see §2 |
| **Search** | Meilisearch / Typesense if catalog > 50k events | DB-LIKE search degrades fast above that |
| **CDN + edge** | Cloudflare with both Workers (`scanner-edge`, `storefront-edge`) | KV namespaces for void + activation pre-checks |
| **Object store** | S3 or compatible | For event banners, organizer logos, PassKit assets |

---

## 2. Redis is required, not optional

Several subsystems silently degrade or no-op on the file/database
cache driver:

- `Cache::tags(['storefront-events'])` — file cache ignores tags
- Rate limiters — file cache has poor concurrency
- Broadcast channels — file driver can't fan out
- `IdempotencyCache` for production payments — needs persistent KV
- `EventBus` RedisStreamBus driver — requires Redis Streams

**Pre-deploy guard**: run `php artisan integrations:validate --strict`
in the pipeline. If it exits non-zero, halt the rollout.

In `.env`:
```
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb  # or pusher/ably
REDIS_HOST=...
REDIS_CLUSTER=redis          # if multi-node
```

---

## 3. Queue worker topology

Each queue has its own SLA. Don't run them all in one pool — a slow
analytics job will starve payment webhooks.

| Queue | Workers | Timeout | Memory | Concerns |
|---|---|---|---|---|
| `payments` | 6+ | 60s | 256M | Webhook ingest, status polling. Highest priority. |
| `default` | 4 | 60s | 256M | Most user-facing background work |
| `storefront` | 4 | 60s | 256M | Waitlist notifications, mailer, capacity events |
| `scanning` | 4 | 30s | 256M | Edge KV pushes, scan webhooks |
| `automations` | 2 | 30s | 256M | Outbound webhooks; isolated so a bad consumer URL can't backpressure the rest |
| `analytics` | 1 | 600s | 1G | Long-running batch jobs, can tolerate latency |

Supervisor recipe (one block per queue):

```ini
[program:laravel-worker-payments]
command=php /srv/app/artisan queue:work redis --queue=payments --sleep=1 --tries=3 --max-time=3600 --memory=256
numprocs=6
autostart=true
autorestart=true
```

Repeat for each queue with the matching `--queue=` and `numprocs=`.

---

## 4. Database connection pool

Laravel uses PDO per-request; with FPM that maps to 1 connection per
worker process. A 32-worker FPM pool × 4 nodes = 128 connections at
steady state. Spikes (event-on-sale) push this to 300+.

**MySQL/MariaDB**: bump `max_connections` to **1000**. Add
**pgBouncer** (or ProxySQL) in front if you stay under a managed
service's connection cap.

**Connection envelope checklist:**
- Read-replica `DB_READ_HOST` set
- `DB_READ_USERNAME` has read-only grants (defense in depth)
- `sticky: true` is set in `config/database.mysql` (it is)
- Test failover: kill the primary, confirm requests reroute within
  `wait_timeout`

---

## 5. CDN + edge cache

Discovery responses carry `Cache-Control: public, max-age=N,
s-maxage=N` from `CacheStorefrontResponse` middleware. Pair with:

```
# Cloudflare page rule on /api/v1/public/events*
Cache Level: Cache Everything
Edge Cache TTL: respect origin
Browser Cache TTL: respect origin
```

Bust on event publish:
```php
Cache::tags(['storefront-events'])->flush();
```

For per-event invalidation (e.g., sold out), tag with the event slug:
```php
Cache::tags(['storefront-events', "event-{$slug}"])->flush();
```

`storefront-edge` worker re-verifies the `X-Origin-Signature` before
caching in KV — a poisoned upstream can't corrupt the edge cache.

---

## 6. Scheduled jobs (run on one server only)

The scheduler is set up in `routes/console.php`. All schedule blocks
use `onOneServer()` — but **you must designate which** node runs
`php artisan schedule:work`. Conventional choice: the lowest-numbered
FPM node, with a fall-forward via `supervisor` if it dies.

Critical schedules to monitor:
- `ledger:verify` (daily 05:00) — pages if violations found
- `outbox:prune` (weekly Sun 06:00) — keeps tables bounded
- `integrations:validate --strict` (daily 07:00) — pages on misconfig
- `payments:poll-pending` (every 5 min) — catches missed webhooks
- `DispatchPendingOutboxWebhooksJob` (every min) — webhook drain

---

## 7. Observability

Bind a real telemetry driver in production:

```
TELEMETRY_DRIVER=sentry
SENTRY_LARAVEL_DSN=https://...@sentry.io/...
```

`SentryTelemetry` wires breadcrumbs + counters + transaction spans
+ exceptions. Set ↑↑↑ then verify with
`php artisan tinker --execute='app(\App\Services\Telemetry\Contracts\Telemetry::class)->captureException(new \RuntimeException("test"));'`.

**Critical SLOs to alert on:**
- p95 checkout-create latency < 800ms
- p95 sale-record latency < 500ms
- p95 scan latency at gate < 50ms (edge) / < 300ms (origin fallback)
- Payment webhook 401 rate < 0.1% (higher = forgery attempts or key rotation drift)
- `ledger.integrity_violation` count = 0 (anything > 0 = page on-call)

---

## 8. Pre-deploy checklist

Run in order:

1. `composer audit --locked` — block on high/critical advisories
2. `pnpm audit --audit-level=high` — same for JS
3. `./vendor/bin/phpstan analyse` — must exit 0
4. `./vendor/bin/pest tests/Unit` — must pass
5. `./node_modules/.bin/tsc --noEmit` — must exit 0
6. `php artisan migrate --pretend` — review the SQL
7. `php artisan integrations:validate --strict` — confirms env matches
8. Smoke-test against staging: book a free ticket, scan it, void it

After deploy:

9. Tail logs for `ledger.integrity_violation`, `payment.webhook.signature_failed`
10. Check `php artisan queue:failed` is empty
11. Verify Cloudflare cache is hitting (`X-Edge-Cached: hit` header on /events)

---

## 9. Capacity planning rules of thumb

From measured numbers (not theoretical):

| Concurrent buyers | Web nodes | Queue workers (total) | DB connections |
|---|---|---|---|
| 1,000 | 2 | 8 | 60 |
| 10,000 | 4 | 24 | 200 |
| 100,000 (stadium drop) | 12+ Octane | 80+ | 800 + pgBouncer |
| 1,000,000 (Super Bowl tier) | Edge-cached storefront + multi-region origin | Separate scan-fleet | Active-active DB |

Surge-pricing rule of thumb: the moment you hit 80% capacity on web
nodes, scale OUT not UP. PHP-FPM tops out per-instance at ~64 workers
before context-switching dominates.
