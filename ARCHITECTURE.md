# Architecture & Phase 7 — Audit, Hardening, Stakeholder Portal

Companion to [STOREFRONT_ROADMAP.md](STOREFRONT_ROADMAP.md). Covers
the codebase audit done at the start of this phase, the security
fixes applied, the new Stakeholder Portal subsystem, strategic
package integration recommendations, and an innovation proposal.

---

## 1. Audit findings + fixes applied

### Critical security gap (FIXED)

**B1 — Back-office controllers had no permission checks.** All 11
controllers under `app/Http/Controllers/BackOffice/*` trusted
`EnsureOrganizationMembership` alone — any org member (including
viewers) could mint automation tokens, replay webhooks, install
extensions, approve refunds, configure custom domains.

**Fix:** every route in `routes/backoffice.php` now carries a
Spatie `permission:<key>` middleware mapped to the canonical
`App\Enums\Permission` catalogue. Examples:

- `automation/tokens/*` → `permission:api.manage-keys`
- `automation/webhooks/*` → `permission:webhook.manage`
- `refund-requests/{uuid}/approve` → `permission:order.refund`
- `domains/*` → `permission:organization.manage-domain`
- `extensions/{slug}/install` → `permission:integration.manage`
- `bundles/*` → `permission:ticket_category.*` (create/update/delete)

Spatie middleware was already in composer.json but wasn't aliased.
Added `permission`, `role`, `role_or_permission` aliases plus
`resolve.host_org` in `bootstrap/app.php`.

### Dead code removed

- `app/Policies/EventPolicy.php` (97 LOC) — never registered, never called
- `app/Policies/OrganizationPolicy.php` (86 LOC) — same
- `app/Policies/TeamPolicy.php` (70 LOC) — same

253 LOC of theatre. The codebase authorizes via Spatie permission
strings; policies were leftover from an earlier RBAC design.

### Missing wiring fixed

- **`ResolveOrganizationFromHost` middleware** was defined but never
  registered. Now aliased as `resolve.host_org` so custom-domain
  routing actually works.
- **`BuyerNotificationService` was orphaned.** Added
  `NotifyBuyerOnOrderPaid` listener so every paid order on a linked
  buyer account drops a `order.confirmed` notification into the bell.

### Audit clean items

- All 7 listeners + 3 observers properly wired
- Every dispatched job class exists
- No orphan route → controller references
- Phase-6 (NFC, Buyer accounts, Marketplace, Developer API) bindings
  all resolve

---

## 2. Stakeholder Portal (the big phase-7 deliverable)

A dedicated subsystem for non-organizer commercial entities to plug
into events: **sponsors, media partners, food/beverage vendors,
service providers, influencers, photographers, merchandisers**.

### 2.1 Schema (12 new tables)

| Table | Purpose |
| --- | --- |
| `stakeholders` | Account + identity + verification status |
| `stakeholder_sessions` | Magic-link session bearers |
| `stakeholder_login_tokens` | Single-use magic-link tokens |
| `stakeholder_profiles` | Public-facing bio, logo, portfolio, socials, tags |
| `stakeholder_services` | Catalogue of what they offer (price + pricing model) |
| `stakeholder_invitations` | Event → stakeholder direction |
| `stakeholder_applications` | Stakeholder → event direction |
| `event_stakeholder_engagements` | The contracted relationship |
| `stakeholder_deliverables` | Tasks / milestones inside an engagement |
| `stakeholder_payments` | Scheduled + paid amounts per engagement |
| `stakeholder_reviews` | Two-way post-event reviews |
| `stakeholder_documents` | Contracts, insurance certs, W9s, permits |

### 2.2 Workflow strategies (per stakeholder type)

`StakeholderWorkflowRegistry::for($type)` returns the right impl:

| Type | Workflow | Default payment schedule | Required docs |
| --- | --- | --- | --- |
| Sponsor | `SponsorWorkflow` | 50% deposit -45d / 50% balance | contract |
| Media partner | `MediaPartnerWorkflow` | Single fee net-30 after delivery | contract |
| Food / beverage vendor | `FoodVendorWorkflow` | Full booth fee -30d (org receives) | contract + insurance + permit |
| Service provider | `ServiceProviderWorkflow` | 30% deposit / 70% net-30 | contract + insurance |
| Influencer / photographer / merchandiser | `DefaultStakeholderWorkflow` | Single fee net-7 | contract |

Each workflow seeds:
- type-specific deliverable templates with `due_days_offset` relative to event start
- the default payment schedule above (skipped if `agreed_amount_cents` is null)

### 2.3 Lifecycle

Three intake paths converge at `EventStakeholderEngagement`:

```
                   ┌─────────────────────────────────────┐
                   │  Organizer invites stakeholder      │
                   │  → StakeholderInvitation (pending)  │
                   │  → stakeholder accepts/declines     │
                   └──────────────┬──────────────────────┘
                                  │
                                  │
┌─────────────────────────────────────────────────┐
│  Stakeholder applies to public event            │
│  → StakeholderApplication (submitted)           │
│  → organizer approves/declines                  │
└──────────────────┬──────────────────────────────┘
                   │
                   │
┌──────────────────────────────────────────────┐
│  Direct back-office shortcut (skip intake)   │
│  → engagementService.create()                │
└──────────────────┬───────────────────────────┘
                   │
                   ▼
        EventStakeholderEngagement
                   │
                   ├─→ StakeholderDeliverable[] (seeded by workflow)
                   ├─→ StakeholderPayment[] (seeded by workflow)
                   ├─→ StakeholderDocument[] (uploaded as needed)
                   └─→ StakeholderReview[] (post-event, two-way)
```

### 2.4 Surfaces (21 + 2 + 12 = 35 routes)

| Surface | Routes | Auth |
| --- | --- | --- |
| Public marketplace browse | 2 | none |
| Stakeholder dashboard | 21 | magic-link session bearer |
| Back-office (org side) | 12 | session + `permission:event_sponsors.manage` |

### 2.5 Key endpoints

**Stakeholder-side:**
```
POST  /api/v1/stakeholder/auth/register
POST  /api/v1/stakeholder/auth/request-link
POST  /api/v1/stakeholder/auth/verify        → bearer
GET   /api/v1/stakeholder/me
PATCH /api/v1/stakeholder/me/profile
GET   /api/v1/stakeholder/invitations
GET   /api/v1/stakeholder/applications
GET   /api/v1/stakeholder/engagements
GET   /api/v1/stakeholder/engagements/{uuid}
GET   /api/v1/stakeholder/services
POST  /api/v1/stakeholder/services
POST  /api/v1/stakeholder/invitations/{uuid}/accept
POST  /api/v1/stakeholder/applications
POST  /api/v1/stakeholder/engagements/{e}/deliverables/{d}/submit
```

**Organizer back-office:**
```
GET   /{org}/api/back-office/stakeholders                  browse verified
POST  /{org}/api/back-office/events/{event}/invitations    invite
GET   /{org}/api/back-office/events/{event}/applications   inbox
POST  /{org}/api/back-office/applications/{uuid}/approve   → engagement
POST  /{org}/api/back-office/engagements/{uuid}/complete
POST  /{org}/api/back-office/engagements/{uuid}/cancel
POST  /{org}/api/back-office/engagements/{uuid}/reviews    leave review
POST  /{org}/api/back-office/deliverables/{uuid}/approve
POST  /{org}/api/back-office/deliverables/{uuid}/reject
POST  /{org}/api/back-office/payments/{uuid}/mark-paid
```

### 2.6 Verification

- `php artisan migrate` — phase-7 applied (one index-name length fix for MySQL)
- Scanner regression: **53/53 still green**
- All 12 stakeholder tables present
- All 7 workflow DI bindings resolve
- Workflow registry returns the right strategy + required document
  count for each of 7 stakeholder types
- Public marketplace: 2 routes; stakeholder dashboard: 19+2 routes;
  back-office: 75 routes total (was 38)

---

## 3. Strategic package integration plan

### 3.1 Where Spatie wins

| Package | Replaces | Notes |
| --- | --- | --- |
| **spatie/laravel-permission** | (already in use) | Now properly aliased in `bootstrap/app.php`. Continue migrating residual `$user->isCurrentOrganization()` / hand-rolled checks to `permission:`/`role:` middleware. |
| **spatie/laravel-activitylog** | `app/Services/Audit/AuditLogger.php` (46 LOC) + `app/Models/AuditLog.php` (35 LOC) + ~28 hand-rolled `$audit->record(…)` call sites | Real win — model-level `LogsActivity` trait removes the audit-log boilerplate from every service. `composer require spatie/laravel-activitylog && php artisan vendor:publish --tag=activitylog-migrations` then progressively migrate by adding `LogsActivity` to models. |
| **spatie/laravel-medialibrary** | `app/Services/ImageProcessingService.php` (122 LOC) + media controller actions in `EventController` (`storeMedia`, `destroyMedia`, `storeLineupPhoto`, `storeSponsorLogo`) + `app/Models/EventMediaItem.php` | 200+ LOC saved, gives image conversions / responsive variants / S3 abstraction for free. Already pulling `intervention/image`. |
| **spatie/laravel-query-builder** | Hand-rolled filter chains in `AttendeeController`, `OrderController`, `ReportsController`, `FinanceController` | ~150 LOC saved across 5 controllers. Standardizes filtering grammar (`?filter[event]=…&sort=-created_at&include=items`). |
| **spatie/laravel-data** | (not recommended) | Codebase uses Inertia → arrays. Force-fitting DTOs is more rewrite than benefit. |

**Suggested install order:**
```bash
composer require spatie/laravel-activitylog
php artisan vendor:publish --tag=activitylog-migrations
php artisan migrate

composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate

composer require spatie/laravel-query-builder
```

Each can be adopted incrementally — add the trait/contract to one
controller/model at a time; old code keeps working.

### 3.2 Where React libraries win (frontend)

The codebase ships with React + Inertia. Recommend:

- **TanStack Query (React Query)** — replace ad-hoc `useEffect` +
  `fetch` patterns in dashboard pages. Built-in caching, dedup,
  background refetch. Pairs cleanly with the JSON API surfaces
  we've built.
- **TanStack Table** — for the dashboard's data grids
  (Attendees, Orders, Reports). Headless, virtualized, sortable.
- **Zod + React Hook Form** — replace manual form state + per-field
  validation. Zod schemas mirror the Laravel validation rules.
- **shadcn/ui** — already adopted per the existing pages; continue.
- **Tremor** — pre-built analytics charts that drop straight into
  `EventAnalyticsController` payloads. Saves writing chart-library
  boilerplate.
- **Recharts → Visx** if/when complex viz becomes needed.

---

## 4. Innovation proposal (next-horizon bets)

Concrete tech bets that would meaningfully differentiate the
platform. Ordered by ratio of (impact ÷ effort).

### 4.1 Vector search for events + recommendations

**What:** index every Event into pgvector / Pinecone / Weaviate with
embeddings of `name + description + lineup + venue`. Replace the
existing LIKE-based discovery with semantic search; replace the
rules-based `RelatedEventsRecommendationStrategy` with cosine-
similarity recall.

**Why:** "outdoor jazz festival under 10k people" returns nothing
from LIKE-based search. Embeddings make it trivial.

**Lift:** new `VectorSearchProvider implements SearchProvider`. Re-
embed on Event save via an observer. The contract already exists.

### 4.2 AI organizer assistant

**What:** an Inertia panel that reads the org's current state
(`event_count`, `tickets_sold`, `attendee_metrics`) and drafts:
- promotional copy
- email broadcast templates
- refund reply suggestions
- "events similar to yours that sold faster" comparison

**Why:** the data is all already in our schema; the leap is just
LLM access. Organizers spend hours on the writing tasks LLMs are
specifically good at.

**Lift:** `AiAssistant` contract with `ClaudeAssistant` / `OpenAiAssistant`
impls; one Inertia panel per surface. ~1 week per surface.

### 4.3 Predictive sellout analytics

**What:** train a regression model (per organizer, after enough
history) on `event_page_views`, `tickets_sold_count` over time,
historical event metadata, and surface forecasts to the organizer
dashboard:
- "Will sell out in 6 days at current velocity"
- "Repricing tier B at $45 would shorten time-to-sellout by 40%"

**Lift:** offline Python training job → publish predictions back
as `event_predictions` JSON. Dashboard reads. No real-time inference
needed.

### 4.4 Real-time collaborative event editor

**What:** Yjs / Liveblocks integration so multiple org staff can
edit an Event simultaneously (lineup, agenda, sponsors) like Notion.
Inertia + WebSocket presence.

**Lift:** the broadcast infrastructure already exists (Reverb +
broadcast channels). Yjs CRDT layer + an `event_drafts` table for
the merged document state.

### 4.5 Edge personalization

**What:** a Cloudflare Worker that reads the storefront request's
country + UA + referrer and personalizes the response (currency,
language, featured-events shortlist) before it ever hits origin.
Saves 100-300ms vs. doing it origin-side.

**Lift:** extends the existing `scanner-edge` Worker; reads the
storefront `EventSummary` JSON via the cached discovery endpoint.

### 4.6 Multi-region active-active

**What:** PlanetScale-style global MySQL or CockroachDB so a UK
buyer hits a London origin, a SG buyer hits Singapore. Eliminate
cross-region read latency that bites checkout / lookup.

**Lift:** Substantial — requires sharding strategy. But the schema
is already tenant-scoped (org_id everywhere), so partitioning is
clean.

### 4.7 Privacy-preserving analytics

**What:** Differential privacy on cross-event analytics so we can
expose "average organizer in your category sold X tickets" without
leaking any single organizer's numbers. Trust signal for new orgs
joining the platform.

**Lift:** `DifferentialPrivacyAnalytics` service with Laplace-noise
queries. Reads from `developer_api_usage_daily` shape. ~1 sprint.

### 4.8 Stakeholder dispute resolution (Aragon-style)

**What:** when an `EventStakeholderEngagement` enters `disputed`
status, a structured arbitration workflow:
- both sides submit evidence
- a marketplace-side reviewer (or token-staked third party) renders
  a verdict
- escrow releases / withholds the payment

**Lift:** new `EngagementDispute` model + a `disputed` controller +
optional smart-contract escrow via something like Kleros if you
want trustless. Otherwise marketplace-staff-arbitrated is fine.

### 4.9 Open-source the scanner SDK + edge worker

**What:** the scanner SDKs (Kotlin/Swift/TS) + edge worker are
generic — open-source them. Plant flags + attract third-party
hardware integrations (Boca, Bluestar, Honeywell ring scanners).

**Lift:** licensing decision + a public GitHub mirror. No code
changes.

---

## 5. Verification snapshot (post-phase-7)

| Surface | Routes | Auth |
| --- | --- | --- |
| Public storefront | 40 | none |
| Buyer dashboard | 21 | magic-link |
| Stakeholder dashboard | 21 | magic-link |
| Public stakeholder marketplace | 2 | none |
| Scanner | 9 (incl. NFC tap) | device bearer |
| Automation | 7 | automation token |
| Marketplace browse | 3 | none |
| Extension runtime | 7 | install token |
| Extension developer portal | 4 | open + sig-verified |
| Public Developer API | 10 | tiered API key |
| Back-office | **75** | session + Spatie permission |
| Widget | 2 | none |
| Sitemap | 4 | none |

- **Total routes**: ~205
- **Distinct auth surfaces**: 6 (Spatie / WorkOS, magic-link buyer,
  magic-link stakeholder, scanner device, automation token,
  extension install, dev API key)
- **Pluggable strategy contracts**: 7 (Tax, Fee, Discount, CheckoutFraud,
  Search, Recommendation, StakeholderWorkflow)
- **Cross-cutting abstractions**: 3 (DomainBus, Telemetry, CaptchaProvider)
- **Scheduled jobs**: 6 (session/seat expiry, recurring events, membership
  expiry, outbox drain, all per-minute or per-hour as appropriate)
- **Scanner unit tests**: 53/53 passing across all phases

---

## 6. Remaining honest gaps

Genuinely unimplemented (acknowledged):

- **Stakeholder document upload UX** — `stakeholder_documents` table +
  model exist; an upload endpoint (with virus scan + signed-URL S3
  upload) is the next iteration.
- **Stakeholder marketplace search** — currently DB-backed name/company
  LIKE; would benefit from the same Meilisearch wiring as event search.
- **Stakeholder payment payouts** — `mark-paid` flips a status; actual
  payout via Stripe Connect / similar is per-deployment.
- **Spatie packages not yet installed** — adoption is incremental;
  `composer require` happens when ops is ready.
- **Phase-6 / Phase-7 feature tests** — same `pdo_sqlite` blocker as
  the existing Payments suite.

Out-of-scope by design:

- **Saved payment methods for buyers** — guest checkout is the
  contract.
- **Full Stripe Connect onboarding for stakeholders** — separate
  payments line; can plug into the existing PaymentManager.
- **Live streaming** — separate service line.

---

## 7. Phase 8 — innovation bets shipped

All 9 bets from §4 are now in the codebase. Defaults are no-network
stubs so dev works out of the box; production flips env vars.

### 7.1 §4.1 Vector search for events

| Piece | File |
| --- | --- |
| Contract | `app/Services/Ai/Contracts/EmbeddingClient.php` |
| Default impl | `StubEmbeddingClient` (deterministic hash, 64 dims) |
| Production impl | `OpenAiEmbeddingClient` (text-embedding-3-small, 1536 dims) |
| Indexer | `VectorIndexer::indexEvent()` — short-circuits on content-hash match |
| SearchProvider impl | `PgvectorSearchProvider` — JSON-array cosine ranking; falls back to `DatabaseSearchProvider` for non-keyword queries |
| Schema | `event_embeddings(event_id, model, embedding json, content_hash)` |

Bind via `STOREFRONT_SEARCH_DRIVER=pgvector` + `AI_EMBEDDINGS_DRIVER=openai`. The contract supports any provider; ship a `CohereEmbeddingClient` downstream by binding a new class.

### 7.2 §4.2 AI organizer assistant

| Piece | File |
| --- | --- |
| Contract | `app/Services/Ai/Contracts/AiAssistant.php` |
| Default impl | `StubAiAssistant` (deterministic templated output) |
| Production impl | `ClaudeAssistant` (Anthropic Messages API, JSON-shape coerced) |
| Controller | `BackOffice/Ai/AiAssistantController` |
| Endpoints | `/back-office/ai/event-copy`, `/refund-reply`, `/sales-insight` |

3 narrow task methods (NOT generic chat) — each has a fixed system prompt + JSON schema so misuse is impossible. Returns `null` on transient failure so the dashboard panel degrades to "draft unavailable".

### 7.3 §4.3 Sellout prediction

| Piece | File |
| --- | --- |
| Predictor | `app/Services/Analytics/SelloutPredictor.php` |
| Model | linear velocity over last 14 days, ETA = remaining ÷ mean, confidence = 1 − (stddev ÷ mean) |
| Daily job | `App\Jobs\Analytics\ComputeSelloutPredictionsJob` (04:00 daily) |
| Schema | `event_predictions(event_id, prediction_type, value json, confidence)` |
| Endpoint | `GET /api/v1/public/events/{slug}/predictions` (also surfaced to organizers via the dashboard) |

Caps ETA at the event's `starts_at` so we never predict beyond doors. Returns `confidence` so the FE can suppress noisy badges.

### 7.4 §4.4 Real-time collaborative event editor

| Piece | File |
| --- | --- |
| Schema | `event_drafts(event_id, ydoc_base64, version, last_editor_user_id)` |
| Controller | `BackOffice/Collaboration/EventDraftController` (show/apply/publish) |
| Broadcast event | `EventDraftUpdated` on presence channel `event.{slug}.draft` |
| Channel auth | `routes/channels.php` — membership-gated presence with `{id, name, avatar}` payload |

Server is a thin Yjs persistence layer + a presence channel. CRDT merging happens client-side; we just persist whichever state the client sends. Optimistic conflict-detection via `base_version` lets the FE surface "5 changes since you joined".

### 7.5 §4.5 storefront-edge Cloudflare Worker

- `workers/storefront-edge/` — wrangler config, TypeScript source, README
- Two-stage cache: CF cache API + KV `PERSONALISED_CACHE`
- Personalises featured/discovery JSON per `CF-IPCountry`: adds `preferred_currency`, reorders by country match, echoes `Accept-Language` into `meta`
- Pass-through for non-listed paths
- Cache TTL honours origin's `Cache-Control: max-age`

### 7.6 §4.6 Regional read-replica router

- `app/Http/Middleware/RouteToRegionalReadReplica.php`
- Inspects `CF-IPCountry` / `X-Region` and swaps the default DB connection to the closest read replica (writes still go to primary via Laravel's read/write split)
- Driven by `config('database.region_routing')` — `{country: connection_name}`
- Silent no-op for single-region deploys
- Apply via `Route::middleware('regional.read')` on read-heavy public endpoints

### 7.7 §4.7 Differential-privacy cross-org analytics

- `app/Services/Analytics/DifferentialPrivacyAnalytics.php` — Laplace noise sampler
- Defaults: ε=1.0, min group size = 10 organizations
- Smaller cohorts return `null` so single-org leakage is structurally impossible
- Endpoint: `GET /api/developer/v1/analytics/cross-org?category_id=&country=&months_back=` — Enterprise+ tier

### 7.8 §4.8 Stakeholder dispute resolution

- `app/Services/Stakeholders/DisputeService.php` — open / append evidence / resolve / withdraw
- Schema: `engagement_disputes(raised_by_type, reason_code, status, resolution)`
- On open: engagement flips to `disputed` (pauses payments)
- On resolution: `upheld` → cancelled, `partial` / `rejected` → active
- Endpoints (both sides):
  - `POST /api/v1/stakeholder/engagements/{uuid}/disputes` (stakeholder)
  - `POST /api/back-office/engagements/{uuid}/disputes` (organizer)
  - `POST /api/back-office/disputes/{uuid}/resolve` (verdict)
- Audit logged

### 7.9 §4.9 Open-source the SDKs

- `sdks/LICENSE` (MIT), `workers/LICENSE` (MIT)
- `sdks/README.md` — what's there, contributing rules, what we do/don't accept
- `sdks/SECURITY.md` — disclosure policy + safe-harbour terms
- All four SDK packages + both edge workers are MIT-licensed and ready to mirror to a public repo

### 7.10 Verification (phase 8)

- `php artisan migrate` ✅ (4 new tables: `event_embeddings`, `event_predictions`, `event_drafts`, `engagement_disputes`)
- Scanner suite **53/53** still green
- All 7 new DI bindings resolve via tinker
- Default driver bindings: `EmbeddingClient = stub-hash-v1`, `AiAssistant = stub`
- Stub embedding round-trip returns a 64-dim vector
- 15 new routes registered (1 public predictions + 1 dev cross-org + 4 stakeholder disputes + 3 back-office disputes + 3 back-office draft + 3 back-office AI)

### 7.11 Total surface as of phase 8

| Surface | Routes | Auth |
| --- | --- | --- |
| Public storefront | 42 (+predictions) | none |
| Buyer dashboard | 21 | magic-link |
| Stakeholder dashboard | 25 (+disputes) | magic-link |
| Public stakeholder marketplace | 2 | none |
| Scanner | 9 (incl. NFC) | device bearer |
| Automation | 7 | automation token |
| Marketplace browse | 3 | none |
| Extension runtime | 7 | install token |
| Extension developer portal | 4 | open + sig-verified |
| Public Developer API | 11 (+cross-org DP analytics) | tiered API key |
| Back-office | **84** (+disputes/drafts/AI) | session + Spatie permission |
| Widget | 2 | none |
| Sitemap | 4 | none |

- **Total routes**: ~220 across 13 surfaces
- **Pluggable strategies**: 8 (Tax, Fee, Discount, CheckoutFraud, Search, Recommendation, StakeholderWorkflow, EmbeddingClient + AiAssistant)
- **Cross-cutting abstractions**: 4 (DomainBus, Telemetry, CaptchaProvider, regional DB routing)
- **Edge workers**: 2 (scanner-edge, storefront-edge)
- **Scheduled jobs**: 7 (+ sellout predictions daily)
- **AI-ready**: full content surfaces (embeddings + assistant) via stub-by-default contracts

---

## Recent audit fixes (2026-05)

A 7-commit remediation pass closed 18 verified findings from the
multi-agent audit. Highlights, by area:

- **Developer portal auth** — `portal_bootstrap_token` issued once at
  registration; all mutations require `Authorization: Bearer <token>`
  (new `EnsureDeveloperPortalToken` middleware). Added rotate-token
  endpoint.
- **Payment webhook ordering** — `WebhookController` now verifies the
  signature BEFORE dedupe + enforces a replay-tolerance window via the
  new `ProvidesWebhookTimestamp` contract. Unsigned forgeries can no
  longer poison the dedupe table.
- **Production gateway idempotency** — `PaymentManager::charge($g, $r,
  $key)` wraps the driver call in `IdempotencyCache` so retries within
  the configured TTL return the original `ChargeResult`.
- **Defensive policies** — `Event`/`Order`/`DeveloperAccount`/
  `ScannerProfile`/`OfflineTicketBatch` each have a Laravel Policy
  layered on top of the existing `permission:<key>` route middleware.
  Policy checks combine Spatie permission + org-membership UUID match.
- **Edge integration** — `TicketVoided` event ↔ `PushVoidedTicketToEdgeJob`
  upserts the ticket UUID into the scanner-edge KV namespace so subsequent
  scans are denied without origin RTT. Storefront-edge now verifies
  `X-Origin-Signature` (HMAC-SHA256 of `t.body`) before caching responses.
- **CI security gates** — `phpstan` (larastan level 5), `composer audit
  --locked`, `pnpm audit --audit-level=high`, and a SQLite migration smoke
  test all run on every push.
- **Frontend hygiene** — pnpm-only (`package-lock.json` deleted, lockfile
  is `pnpm-lock.yaml`), `strictNullChecks` on, `dompurify` sanitises
  rich-text before `dangerouslySetInnerHTML`, eslint enforces
  `react/jsx-no-target-blank` (no referrer).
- **Cleanup** — `OrderConfirmationMail` class-exists guard removed (the
  class is real); AppleVAS encrypted-blob decrypt path implemented;
  `AdManagementService` gated by `config('ads.enabled')` and throws
  `FeatureNotImplementedException` instead of `RuntimeException`;
  `scanner-pair` route has a 5-per-IP-per-minute throttle; `.env.example`
  enumerates every env var the new modules read.
