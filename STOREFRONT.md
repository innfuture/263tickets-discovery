# Public Storefront Engine

The unauthenticated public surface of the platform — what the buyer
sees on the marketing site. This document covers everything that
shipped in the storefront engine: routes, services, schema,
configuration, and the deliberate gaps that still need work before
the storefront is fully production-ready.

The tenant organizer backend (auth'd dashboard) and the system
superuser surface are out of scope here.

---

## 1. Architecture at a glance

```
                        ┌──────────────────────────────────────────────┐
       Browser /        │  routes/public.php                            │
       Native app  ───► │   GET  /api/v1/public/events                  │
                        │   POST /api/v1/public/checkout/sessions       │
                        │   POST /api/v1/public/checkout/sessions/.../pay
                        │   POST /sitemap.xml                           │
                        └──────────────────┬───────────────────────────┘
                                           │
                  ┌────────────────────────┴────────────────────────────┐
                  │                                                     │
        Discovery Controllers                            Checkout Controllers
       (read PublicEventQuery)                  ┌──────────────────────────────────┐
                                                │ CheckoutSessionManager           │
                                                │   ↳ TicketReservation (holds)    │
                                                │   ↳ PriceCalculator              │
                                                │       ├─ DiscountResolver        │
                                                │       │     (PromoCodeValidator) │
                                                │       ├─ TaxRule[]               │
                                                │       │     (FlatRateTaxRule)    │
                                                │       └─ FeeRule[]               │
                                                │           ├─ PlatformFeeRule     │
                                                │           └─ ProcessorFeeRule    │
                                                │   ↳ PaymentManager (existing)    │
                                                │   ↳ OrderFulfillment             │
                                                └──────────────┬───────────────────┘
                                                               │
                                                               ▼
                                            PaymentTransactionObserver
                                            (watches the existing payment
                                             pipeline; fulfils on settle)
                                                               │
                                                               ▼
                                                  Order  +  OrderItem[]
                                                       +  OfflineTicket[]  (scannable)
                                                       +  OrderPaid event
                                                               │
                                                               ▼
                                                  QueueOrderConfirmation listener
                                                       → SendOrderConfirmationJob
                                                       → Storefront broadcast (Reverb)
```

Every piece extending the system follows the same pattern already
used in the payment / scanner subsystems: **interface in
`Contracts/`, default impl bound in a service provider, config-driven
strategy lists**. Tax rules, fee rules, and the discount resolver
are all replaceable without editing core.

---

## 2. Routes

All public routes are mounted from `routes/public.php`, loaded in
`bootstrap/app.php`, and CSRF-exempt (`api/v1/public/*`). Each group
is throttled by a named rate limiter declared in
`PublicStorefrontServiceProvider`.

### Discovery (throttle: `storefront-discovery`, default 120/min/IP)

| Method | URL | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/public/events` | `public.events.index` | Paginated catalog. Query: `search`, `city`, `country`, `category`, `organizer`, `starts_after`, `starts_before`, `sort` (`starts_at`, `-starts_at`, `name`, `popular`), `per_page`. |
| GET | `/api/v1/public/events/featured` | `public.events.featured` | Featured events for the homepage hero. |
| GET | `/api/v1/public/events/{slug}` | `public.events.show` | Detail page. Includes ticket tiers, lineup, agenda, amenities, sponsors, organization block, per-tier availability count. |
| GET | `/api/v1/public/organizers/{slug}` | `public.organizers.show` | Organizer profile (brand, logo, socials). |
| GET | `/api/v1/public/organizers/{slug}/events` | `public.organizers.events` | An organizer's events. |
| GET | `/sitemap.xml` | `public.sitemap` | XML sitemap of public events + organizers. |

### Checkout (throttle: `storefront-checkout`, default 30/min/IP)

| Method | URL | Name | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/public/checkout/sessions` | `public.checkout.create` | Create a session for an event. Body: `event_slug`, `currency?`, `idempotency_key?`, `attribution{utm_*, referral_source, buyer_locale, buyer_country_code}`. |
| GET | `/api/v1/public/checkout/sessions/{uuid}` | `public.checkout.show` | Snapshot of cart, totals, line items, status. |
| PATCH | `/api/v1/public/checkout/sessions/{uuid}/items` | `public.checkout.items` | Replace cart items (idempotent set). Body: `items: [{ticket_category_id, quantity}]`. |
| PATCH | `/api/v1/public/checkout/sessions/{uuid}/attendees` | `public.checkout.attendees` | Set buyer + per-seat attendees. Body: `buyer_*`, `attendees: [{item_id, attendees: [{name, email, phone}]}]`. |
| POST | `/api/v1/public/checkout/sessions/{uuid}/promo` | `public.checkout.promo.apply` | Apply a promo code. Body: `code`. |
| DELETE | `/api/v1/public/checkout/sessions/{uuid}/promo` | `public.checkout.promo.clear` | Clear the promo code. |
| POST | `/api/v1/public/checkout/sessions/{uuid}/pay` | `public.checkout.pay` | Lock the session and initiate a charge with the chosen gateway. Body: `gateway`, `return_url?`. |
| POST | `/api/v1/public/checkout/sessions/{uuid}/confirm` | `public.checkout.confirm` | Poll status after gateway redirect (also fulfils on the spot if the gateway has already settled). |
| DELETE | `/api/v1/public/checkout/sessions/{uuid}` | `public.checkout.abandon` | Buyer cancelled — releases holds. |

### Orders (throttle: `storefront-lookup`, default 10/min/IP)

| Method | URL | Name | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/public/orders/lookup` | `public.orders.lookup` | Guest order lookup. Body: `{reference, email}`. Returns 404 on either wrong reference or wrong email to prevent enumeration. |
| GET | `/api/v1/public/orders/{reference}` | `public.orders.show` | Direct fetch by reference (used by signed links in confirmation emails). |

### Waitlist (throttle: `storefront-waitlist`, default 6/min/IP)

| Method | URL | Name | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/public/events/{slug}/waitlist` | `public.waitlist.store` | Enrol on the waitlist. Body: `{email, name?, phone?, ticket_category_uuid?, quantity_requested?}`. Idempotent per (event, tier, email). |

---

## 3. Data model

New tables added in `database/migrations/2026_05_30_000000_create_storefront_tables.php`:

### `checkout_sessions`
The cart row. While `status ∈ {open, paying}` its line items count
against tier inventory.

| Column | Notes |
| --- | --- |
| `uuid` (unique) | Public identifier; the URL key. |
| `organisation_id` (FK → `organizations.uuid`) | Platform-wide tenancy UUID. |
| `event_id` (FK → `events.id`) | Scoped per event. |
| `status` | `open` → `paying` → `completed` ; or → `expired` / `cancelled`. |
| `buyer_*` | Captured at the `/attendees` step. |
| `currency`, `subtotal_cents`, `discount_cents`, `tax_cents`, `fee_cents`, `total_cents` | Snapshot — single source of truth, recomputed on every mutation. |
| `promo_code_id`, `promo_code_snapshot` | Promo + frozen code string. |
| `attendee_data` (JSON) | `[{item_id, attendees: [{name, email, phone}]}]`. |
| `referral_source`, `utm_*` | Marketing attribution. |
| `ip_address`, `user_agent_hash` | Anti-bot (UA is hashed). |
| `payment_transaction_id`, `order_id` | Filled by `/pay` and `OrderFulfillment` respectively. |
| `idempotency_key` (unique) | Honoured on session create to deduplicate. |
| `expires_at`, `locked_at`, `completed_at`, `cancelled_at` | Lifecycle timestamps. |

### `checkout_session_items`
| Column | Notes |
| --- | --- |
| `checkout_session_id`, `ticket_category_id` | Unique together; "two GA tickets" is `quantity=2`, not two rows. |
| `quantity`, `unit_price_cents`, `currency`, `line_total_cents` | |
| `price_locked_at` | Snapshot moment so we can detect a price change mid-checkout. |

### `waitlist_entries`
| Column | Notes |
| --- | --- |
| `event_id`, `ticket_category_id` (nullable) | Tier-scoped or whole-event. |
| `email`, `name`, `phone`, `quantity_requested` | |
| `status` | `pending` / `notified` / `converted` / `expired`. |
| `notified_at`, `expires_at`, `converted_order_id` | |

### Order extensions
Added in the same migration on `orders` / `order_items`:
- `orders`: `discount_cents`, `promo_code_used`, `checkout_session_id`, `ip_address`, `utm_source`, `utm_medium`, `utm_campaign`, `buyer_country_code`, `buyer_locale`, `fulfilled_at`.
- `order_items`: `offline_ticket_id` (FK → `offline_tickets.id`), `attendee_phone`, `qr_payload` (denormalised so receipts can render without a join).

---

## 4. Services (all under `app/Services/Storefront/`)

| Class | Responsibility |
| --- | --- |
| `CheckoutSessionManager` | Session lifecycle: create / touch / lockForPayment / complete / cancel / expire. Enforces per-IP cap and idempotency. |
| `TicketReservation` | Atomic add-to-cart with row-level locking on `ticket_categories`. Computes per-tier availability factoring issued tickets + other open holds. |
| `PriceResolver` | Per-tier unit price lookup with `TicketCurrencyPrice` overrides and base-price fallback. No FX conversion. |
| `PriceCalculator` | Recompute pipeline: subtotal → discount → tax rules → fee rules. Persists totals to the session. |
| `PromoCodeValidator` | Default `DiscountResolver`. Validates promo code state (active, not expired, max-uses, applies to cart) and applies % or fixed discount scoped to the tier. |
| `OrderFulfillment` | Convert a paid session to a permanent `Order` + per-seat `OfflineTicket` rows. Idempotent. Emits `OrderPaid`. |
| `PublicEventQuery` | Read-side for discovery. Enforces the public-visibility + status gate. |
| `WaitlistManager` | Enrol + notify. |
| `Fees\PlatformFeeRule` | SaaS take, bps + flat, configurable per-org via `OrganizationSetting`. |
| `Fees\ProcessorFeeRule` | Pass-through of gateway fee. Skipped when `fee_payer=organizer`. |
| `Taxes\FlatRateTaxRule` | Single jurisdiction-flat-rate VAT/GST with inclusive vs. exclusive support. |

### Contracts (pluggable interfaces)
- `Contracts\DiscountResolver`
- `Contracts\FeeRule`
- `Contracts\TaxRule`

Bind your own in a service provider; register in `config/storefront.php` for fee/tax pipelines.

### Exceptions
- `InventoryUnavailableException` — surfaced as HTTP 422 with `ticket_category_id`, `requested`, `available`.
- `PromoCodeInvalidException` — carries `reasonCode` (`PROMO_NOT_FOUND`, `PROMO_EXPIRED`, `PROMO_INACTIVE`, `PROMO_EXHAUSTED`, `PROMO_NOT_APPLICABLE`).
- `CheckoutSessionLockedException` — HTTP 409 (terminal session) or 429 (per-IP cap).

---

## 5. Events, jobs, listeners

- **Event**: `App\Events\OrderPaid` — implements `ShouldBroadcast`, channel `private-organization.{uuid}.orders`, `broadcastAs('order.paid')`. Skipped when broadcaster is `null`.
- **Listener**: `App\Listeners\Storefront\QueueOrderConfirmation` — dispatches the confirmation job.
- **Job**: `App\Jobs\Storefront\SendOrderConfirmationJob` — sends `OrderConfirmationMail` (Mailable not yet shipped — see gaps).
- **Job**: `App\Jobs\Storefront\ExpireStaleCheckoutSessionsJob` — scheduled every minute. Releases inventory holds for sessions past `expires_at + grace`.
- **Observer**: `App\Observers\PaymentTransactionObserver` — registered in `PublicStorefrontServiceProvider`. Bridges the existing payment pipeline: on transaction → `captured`/`authorized` with a `checkout_session_uuid` in metadata, it calls `OrderFulfillment::fulfill()` and marks the session complete.

---

## 6. Configuration (`config/storefront.php`)

Notable knobs (all env-driven):

| Path | Env | Default | Effect |
| --- | --- | --- | --- |
| `checkout.hold_ttl_minutes` | `STOREFRONT_HOLD_TTL_MINUTES` | 15 | How long inventory is held before sweep. |
| `checkout.max_open_sessions_per_ip` | `STOREFRONT_MAX_SESSIONS_PER_IP` | 3 | Cheap anti-bot. |
| `checkout.absolute_max_tickets_per_session` | `STOREFRONT_MAX_TICKETS` | 50 | Hard cart cap. |
| `pricing.fee_rules` | — | `[PlatformFeeRule, ProcessorFeeRule]` | Pipeline of fee strategies. |
| `pricing.tax_rules` | — | `[FlatRateTaxRule]` | Pipeline of tax strategies. |
| `pricing.platform_fee_bps` | `STOREFRONT_PLATFORM_FEE_BPS` | 250 (2.5%) | Default; org can override via `OrganizationSetting`. |
| `pricing.processor_fee_bps` | `STOREFRONT_PROCESSOR_FEE_BPS` | 290 | Default Stripe-style 2.9%. |
| `pricing.fee_payer` | `STOREFRONT_FEE_PAYER` | `buyer` | `buyer` / `organizer` / `split`. |
| `tax.default_rate_bps` | `STOREFRONT_TAX_RATE_BPS` | 0 | Default off. Set to 1500 for 15% VAT. |
| `tax.tax_inclusive` | `STOREFRONT_TAX_INCLUSIVE` | false | If true, subtotal already includes tax; receipt shows implied portion. |
| `fulfilment.queue_confirmations` | `STOREFRONT_QUEUE_CONFIRMATIONS` | true | Set to false in dev to send mail inline. |
| `discovery.list_cache_seconds` | `STOREFRONT_LIST_CACHE_SECONDS` | 30 | ISR-style cache window (currently advisory — see gaps). |
| `rate_limits.*` | `STOREFRONT_RL_*` | discovery 120/min, checkout 30/min, lookup 10/min, waitlist 6/min | Per-IP per-minute caps; format `"<max>,<minutes>"`. |
| `public_url` | `STOREFRONT_PUBLIC_URL` | `APP_URL` | Prefix used by `sitemap.xml`. |

---

## 7. Buyer flow (happy path)

1. **Browse** → `GET /api/v1/public/events?city=Harare`
2. **View event** → `GET /api/v1/public/events/zim-jazz-fest-2026` (returns ticket tiers + per-tier `available` count)
3. **Open cart** → `POST /api/v1/public/checkout/sessions` with `{event_slug, currency, idempotency_key}`
4. **Add tickets** → `PATCH .../items` with `{items: [{ticket_category_id: 7, quantity: 2}]}` (server picks up holds via `TicketReservation`, recomputes totals)
5. **Apply promo (optional)** → `POST .../promo` with `{code: 'EARLYBIRD'}`
6. **Buyer details + attendees** → `PATCH .../attendees`
7. **Pay** → `POST .../pay` with `{gateway: 'paynow'}`. Returns the gateway's `redirect_url`.
8. Buyer pays at gateway; gateway redirects back; gateway also POSTs the webhook to `/payments/webhooks/{gateway}`.
9. `ProcessWebhookEventJob` marks the `PaymentTransaction` as `captured`. The observer fulfils the order, issues tickets, emits `OrderPaid`.
10. Front-end polls **`POST .../confirm`** → gets `{status: 'completed', order_reference: 'ORD-AB12-3CD4'}`.
11. **View tickets** → `POST /api/v1/public/orders/lookup` with `{reference, email}`.

---

## 8. What ships works end-to-end

- Schema applied (`php artisan migrate` passed).
- 17 public storefront routes + sitemap registered.
- DI bindings resolve (verified via tinker).
- Pluggable fee/tax/discount pipeline.
- Inventory holds with row-level locking.
- Idempotent fulfilment.
- Bridge to existing payment pipeline via observer.
- Pint clean.
- Existing scanner suite (53/53 tests) still green.

---

## 9. Phase 2 — gap closure

Everything in the original Phase-1 gaps list has been built out
except for the items called out in §9.3 below (deliberate skips and
deployment-only items).

### 9.1 What landed in Phase 2

| # | Gap | What shipped |
| --- | --- | --- |
| 1 | Order confirmation mail | `App\Mail\OrderConfirmationMail` (queued, Markdown view at `resources/views/storefront/emails/order_confirmation.blade.php`). Embeds a signed temporary URL to the receipt. |
| 2 | 3DS / risk-step | `/checkout/sessions/{uuid}/pay` now returns `requires_action` + the gateway's challenge URL. Front-end renders the iframe; `confirm` polls until settled. |
| 3 | Payment-failure release | `PaymentTransactionObserver` now also handles `FAILED` / `CANCELLED` / `REVERSED` → cancels the session, releases holds, fans out to waitlist. |
| 4 | Public refund flow | `RefundService` + `RefundRequestController` + `refund_requests` table. `POST /orders/{ref}/refund-request` with reason code; idempotent per (order, email). |
| 5 | Currency mismatch | `CurrencyMismatchException` thrown by `PriceResolver`. `/items` returns HTTP 422 `{error: currency_mismatch, ticket_category_id, cart_currency}`. |
| 6 | Bot protection | `CaptchaProvider` contract + `NullCaptchaProvider` (default) + `TurnstileCaptchaProvider`. `EnsureCaptchaPassed` middleware gates the four mutating endpoints. `GET /api/v1/public/captcha/config` exposes the FE-needed provider/site-key. |
| 7 | Discovery cache | `EventCatalogController::index`/`featured` wrapped in `Cache::tags(['storefront-events'])->remember()`. `EventObserver` flushes on save/delete. |
| 8 | Multi-jurisdiction tax | `JurisdictionalTaxRule` reads `buyer_country_code` against `config('storefront.tax.jurisdiction_rates')`. Drop into `pricing.tax_rules` to enable. |
| 9 | Waitlist auto-notify | `NotifyWaitlistOnCapacityReleasedJob` walks pending entries when capacity frees up. Fired automatically by `CheckoutSessionManager::cancel/expire`. |
| 10 | Wallet passes | `PassGenerator` contract + `StubPassGenerator` (SVG, always-on) + `ApplePassKitGenerator` (signed `.pkpass`) + `GoogleWalletPassGenerator` (RS256 JWT). `WalletPassService` picks by `?provider=…` with stub fallback. `GET /orders/{ref}/items/{item}/pass` (signed). |
| 11 | Signed receipt links | `OrderLookupController::show` requires `hasValidSignature()`. `OrderConfirmationMail` mints a 7-day signed URL (configurable). |
| 12 | Reserved seating | `seats` / `seat_maps` / `seat_holds` tables. `Seat` / `SeatMap` / `SeatHold` models. `SeatedReservation` service (unique-on-`seat_id` hold prevents double-booking). `GET /events/{slug}/seats` + `PATCH /checkout/sessions/{uuid}/seats`. `ExpireStaleSeatHoldsJob` runs every minute. |
| 13 | Group / corporate quotes | `quote_requests` table + `QuoteRequest` model + `QuoteRequestService::submit`/`convertToCheckout` + `POST /events/{slug}/quote`. |
| 15 | Sitemap index | `SitemapIndexController` exposes `/sitemap-index.xml`, `/sitemap-events-{shard}.xml`, `/sitemap-organizers-{shard}.xml` with 5k entries per shard. |
| 16 | Public live inventory | `EventInventoryChanged` broadcasts on the public `storefront.events.{slug}` channel. `BroadcastEventInventory` listener fires on every `OrderPaid`. |
| 17 | Page-view analytics | `EventPageViewTracker` writes one `event_page_views` row per `events/{slug}` detail call (inline, fire-and-forget). |
| 18 | GDPR / CCPA | `BuyerDataExporter` / `BuyerDataEraser` services + `PrivacyController` exposing `/privacy/export` and `/privacy/erase`. Proof-of-ownership is reference+email match — same security model as `lookup`. |
| 19 | i18n | `lang/en/storefront.php` with line labels, error codes, email subjects. `PriceCalculator`, fee rules, tax rules now use `__()`. Copy to `lang/<locale>/storefront.php` to translate. |

### 9.2 Knock-on additions

- **New routes**: 25 public + 4 sitemap endpoints (was 17 in Phase 1).
- **New tables**: `seats`, `seat_maps`, `seat_holds`, `quote_requests`, `refund_requests`.
- **Pest suite**: `tests/Feature/Storefront/{EventCatalog,CheckoutFlow,RefundRequest,Waitlist,SeatReservation,Privacy}Test.php` (23 tests). Requires `pdo_sqlite` to run — same blocker as the existing `tests/Feature/Payments` suite.

### 9.3 Remaining gaps

#### Deliberately out of scope
- **Saved payment methods** (original #14). The public storefront is guest-only. Saved tokens belong on the authenticated portal where there's a user account to attach them to.

#### Optional deps (works without; install for full UX)
- **QR encoder library.** `QrCodeGenerator` delegates to `chillerlan/php-qrcode` or `endroid/qr-code` when present; without one of them the stub renders a clearly-labelled placeholder. Install with `composer require chillerlan/php-qrcode`.
- **Wallet credentials.** `ApplePassKitGenerator` needs `STOREFRONT_APPLE_PASS_CERT_PATH` + WWDR cert + Pass Type ID. `GoogleWalletPassGenerator` needs a service-account JSON + issuer ID. Without these, `WalletPassService` returns the stub SVG.

#### Deployment-only (your infra, can't be auto-configured)
- **Broadcaster.** Default is `null`. Set `BROADCAST_CONNECTION=reverb` (or `pusher` / `ably`) for `OrderPaid` and `EventInventoryChanged` to actually publish.
- **Queue worker.** `storefront` is a dedicated queue. Add it to the deploy's `queue:work` / Horizon config: `php artisan queue:work --queue=storefront,default`.
- **Cron.** `routes/console.php` schedules `ExpireStaleCheckoutSessionsJob` + `ExpireStaleSeatHoldsJob` every minute. The standard Laravel `php artisan schedule:run` cron must be wired into the deployment.
- **Captcha provider.** Default is `null`. Set `STOREFRONT_CAPTCHA_PROVIDER=turnstile` + `STOREFRONT_TURNSTILE_SITE_KEY` + `STOREFRONT_TURNSTILE_SECRET_KEY` to activate.
- **pdo_sqlite.** Feature tests need the SQLite PHP extension. The same blocker applies to the existing `tests/Feature/Payments` suite. `apt install php8.3-sqlite3` (or your distro equivalent) makes the whole feature suite run.

### UI deliberately deferred

The user is wiring the front-end separately. The engine returns
clean JSON for every surface; the UI work boils down to:

- **Marketing site**: events list, event detail, organizer profile, search/filters.
- **Checkout flow**: cart review, attendee form, **gateway redirect handler with 3DS challenge iframe**, confirm/poll loop, sold-out → waitlist branch, **group-quote form**, **reserved-seating picker (consumes `GET /events/{slug}/seats`)**, error states (inventory_unavailable, currency_mismatch, promo_invalid, session_locked).
- **Post-purchase**: order confirmation page, lookup form, ticket viewer (consume `qr_payload`), **wallet add-to-wallet buttons** (links to `/orders/{ref}/items/{id}/pass?provider=apple|google|pdf`), **refund-request form**.
- **Privacy**: data export / erase confirmation forms.
- **Captcha widget**: read `/captcha/config` once, render the matching JS widget on the four protected forms.

---

## 10. Files added

### Phase 1
```
config/storefront.php
database/migrations/2026_05_30_000000_create_storefront_tables.php
routes/public.php

app/Enums/{CheckoutSessionStatus,WaitlistStatus}.php
app/Models/{CheckoutSession,CheckoutSessionItem,WaitlistEntry}.php

app/Services/Storefront/{CheckoutSessionManager,OrderFulfillment,
  PriceCalculator,PriceResolver,PromoCodeValidator,
  PublicEventQuery,TicketReservation,WaitlistManager}.php
app/Services/Storefront/Contracts/{DiscountResolver,FeeRule,TaxRule}.php
app/Services/Storefront/Data/{CartLineInput,PriceLine,PriceQuote}.php
app/Services/Storefront/Exceptions/{CheckoutSessionLockedException,
  InventoryUnavailableException,PromoCodeInvalidException}.php
app/Services/Storefront/Fees/{PlatformFeeRule,ProcessorFeeRule}.php
app/Services/Storefront/Taxes/FlatRateTaxRule.php

app/Http/Controllers/Api/Public/{CheckoutController,EventCatalogController,
  OrderLookupController,OrganizationProfileController,
  SitemapController,WaitlistController}.php

app/Events/OrderPaid.php
app/Jobs/Storefront/{ExpireStaleCheckoutSessionsJob,SendOrderConfirmationJob}.php
app/Listeners/Storefront/QueueOrderConfirmation.php
app/Observers/PaymentTransactionObserver.php
app/Providers/PublicStorefrontServiceProvider.php
```

### Phase 2 (gap closure)
```
database/migrations/2026_05_31_000000_create_storefront_phase_2_tables.php
lang/en/storefront.php

app/Models/{Seat,SeatMap,SeatHold,QuoteRequest,RefundRequest}.php
app/Mail/OrderConfirmationMail.php
resources/views/storefront/emails/order_confirmation.blade.php

app/Services/Storefront/Contracts/{CaptchaProvider,PassGenerator}.php
app/Services/Storefront/Exceptions/{CurrencyMismatchException,SeatUnavailableException}.php
app/Services/Storefront/Captcha/{NullCaptchaProvider,TurnstileCaptchaProvider}.php
app/Services/Storefront/Passes/{QrCodeGenerator,StubPassGenerator,
  ApplePassKitGenerator,GoogleWalletPassGenerator,WalletPassService}.php
app/Services/Storefront/Privacy/{BuyerDataExporter,BuyerDataEraser}.php
app/Services/Storefront/Taxes/JurisdictionalTaxRule.php
app/Services/Storefront/{SeatedReservation,QuoteRequestService,
  RefundService,EventPageViewTracker}.php

app/Http/Middleware/EnsureCaptchaPassed.php
app/Http/Controllers/Api/Public/{RefundRequestController,QuoteController,
  WalletPassController,PrivacyController,SeatController,
  CaptchaConfigController,SitemapIndexController}.php

app/Events/EventInventoryChanged.php
app/Jobs/Storefront/{ExpireStaleSeatHoldsJob,NotifyWaitlistOnCapacityReleasedJob}.php
app/Listeners/Storefront/BroadcastEventInventory.php
app/Observers/EventObserver.php

tests/Feature/Storefront/{StorefrontTestHelpers,EventCatalogTest,
  CheckoutFlowTest,RefundRequestTest,WaitlistTest,
  SeatReservationTest,PrivacyTest}.php
```

Bootstrap / framework files modified: `bootstrap/app.php`,
`bootstrap/providers.php`, `routes/console.php`, `routes/channels.php`,
`config/storefront.php`.

### Verification commands

```bash
php artisan migrate                 # applies both storefront migrations
./vendor/bin/pint app/Services/Storefront app/Http/Controllers/Api/Public
./vendor/bin/pest tests/Unit/Services/Scanning   # 53/53 regression baseline
./vendor/bin/pest tests/Feature/Storefront       # 23 tests, needs pdo_sqlite
php artisan route:list --path=api/v1/public      # 25 routes
```
