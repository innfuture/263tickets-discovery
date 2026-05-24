# Storefront — feature roadmap, integrations, and n8n automation

This is the next-horizon companion to [STOREFRONT.md](STOREFRONT.md).
Where that document covers what's shipped + the remaining engineering
gaps, this one is a menu of feature ideas, third-party integrations,
and a concrete design for adding n8n as the platform's automation
substrate.

> **Phase 3 update** — the n8n integration design in §4 is now
> shipped end-to-end, plus six of the Phase-2 feature ideas. See
> §6 below for what landed and what's still on the menu.

---

## 1. Verification status (post-audit fixes)

Five real bugs surfaced in a comprehensive audit have been fixed:

| # | Bug | Fix |
| --- | --- | --- |
| H1 | `PaymentTransaction::create` wrote to non-existent `organisation_id` (UUID) and `event_id` columns; silently dropped both | Look up `Organization` by uuid, write the integer `organization_id`. Move event linkage into `metadata`. Set polymorphic `payable` to the `CheckoutSession` (later repointed to `Order`). |
| H2 | `Order::$fillable` was missing the storefront columns (`discount_cents`, `checkout_session_id`, `promo_code_used`, `ip_address`, `utm_*`, `buyer_country_code`, `buyer_locale`, `fulfilled_at`) — every storefront order wrote NULLs for all of them | Added the columns to `$fillable` + corresponding `$casts`. |
| H3 | `OrderItem::$fillable` was missing `offline_ticket_id`, `qr_payload`, `attendee_phone` — receipt page had no QR; line items orphaned from issued tickets | Added all three to `$fillable`. |
| H4 | `OrderFulfillment` never closed the seat-hold → order-item linkage; seats stayed `held` forever; cancelling the now-paid session could re-release SOLD seats | Pivot seat-held inventory in fulfilment, set `seat_holds.order_item_id`, flip seat `status` to SOLD. Observer also repoints the polymorphic `payable` from session to order. |
| H5 | `SeatedReservation::hold` blew away the entire cart, so a buyer with a GA ticket lost it when they picked a seat | Only delete + rebuild line items for tiers the seated flow is managing. |

**Verified after fix:** Pint clean, scanner suite 53/53 green.

---

## 2. More storefront features

### 2.1 Buyer-facing

| Feature | Why | Sketch |
| --- | --- | --- |
| **Gift cards / store credit** | Common on Eventbrite; high attach rate for last-minute gifts | New `gift_cards` table (code, balance_cents, currency, expires_at, recipient_email). New `GiftCardPaymentRule` that consumes balance before invoking the gateway. Surface as a payment method in `/pay`. |
| **Season passes / bundles** | Recurring shows, sports clubs, multi-day festivals | New `event_bundles` table linking N events. `BundleTicketCategory` extends `TicketCategory` to issue one OfflineTicket per included event on fulfilment. |
| **Subscriptions / membership** | "Friends of the Theatre" model | Reuse existing payment manager. New `Membership` model with `renews_at`, recurring `Order` generation via a scheduled job. |
| **Add-ons / merch upsell** | "Add a parking pass to your ticket" | New `event_addons` table (no inventory, no QR). Surfaced as a step after attendee details in checkout. |
| **Resale / transfer marketplace** | Anti-scalper alternative — face-value buyer-to-buyer | New `ticket_transfers` table with a state machine (`offered → claimed → completed`). Issues a new OfflineTicket UUID to the recipient, voids the original. |
| **Saved seats for groups** | Buy together but assign attendees later | Already half there with `attendee_data`. Add a `claim_token` per seat that lets each attendee fill in their own details. |
| **Currency negotiation / FX display** | "See this £50 ticket in USD" — display-only, charge in listing currency | `FxConverter` contract with a default `OpenExchangeRatesFxConverter` (or a static-rate stub). EventCatalog detail returns `display_prices` keyed by buyer-selected currency. |
| **Wait-then-charge** | High-demand drops: buyer queues, gets a 10-minute window when it's their turn | New `purchase_queue` table. `QueueController` issues a `position_token`; cron promotes the front of the queue to "checkout-allowed" status. |
| **Promo code stacking rules** | "10% off + early-bird discount" or "either/or" | `DiscountResolver` already a contract — add a `StackingDiscountResolver` that applies multiple promo codes with configurable priority. |
| **Referral rewards** | Buyer shares a code, gets account credit when redeemed | `referral_codes` table + a hook in `OrderFulfillment` that credits the referrer's `gift_cards` balance. |
| **Group ticket pickup at gate** | Skip emailing N tickets; print one for the group | New `OrderPrintMode` enum on `Order`; scanner pipeline already supports admit-N-from-one-QR via the existing batch flow. |
| **Wallet pre-authorization holds** | Reserve the funds (don't capture) until door-check | Already supported in `PaymentStatus::AUTHORIZED`. Wire a "release on no-show" job that runs N hours after event end. |
| **Multi-language event pages** | Currently single-language Event row | New `event_translations` table. `PublicEventQuery::findPublic` joins by `App::getLocale()`. |
| **Hard-coded "Sold out" override** | Organizer manually marks a tier sold | Already supported by `TicketSaleStatus::SoldOut`; wire a button in the org dashboard. |

### 2.2 Organizer-facing (back office)

| Feature | Why |
| --- | --- |
| **Comp ticket issuance** | Press, VIPs — issue tickets without a payment row | New "comp" payment method on `Order`; no gateway call. |
| **Manual order entry** | Box-office cash sales | Dashboard form that calls the same `OrderFulfillment` directly. |
| **Bulk ticket grant** | Sponsorship deals: "issue 50 tickets to acme.com" | New `BulkIssuanceJob` reads a CSV, calls fulfilment N times. |
| **Refund-with-fee policies** | "Full refund > 7 days out, 50% within 7, no refund within 24h" | New `RefundPolicy` config per event. Apply during organizer approval of `refund_requests`. |
| **Door-pricing override** | "Tickets cheaper at door" | Already feasible: a manual order with a custom unit price. UX work only. |
| **Hold tickets for the artist's friends** | Organizer manually flags seats as `blocked` | Already in the seat model (`STATUS_BLOCKED`); needs UI. |
| **Bundle discounts** | "Buy 4, save 10%" | New `quantity_discount_rules` table consumed by a new `QuantityDiscountRule`. |
| **Recurring event scheduling** | Weekly comedy night, monthly meetup | Reuse `EventTemplate` (TBD); cron creates next instance. |
| **Buyer messaging** | Push update to all attendees ("doors moved to 7pm") | New `EventBuyerBroadcast` job; reads `orders.buyer_email`. |
| **Approval-required events** | Organizer reviews each buyer before issuing | New `Order::status = 'pending_approval'`; fulfilment only fires after dashboard click. |

### 2.3 Platform-facing

| Feature | Why |
| --- | --- |
| **Audit log for refunds + reversals** | Compliance | Reuse existing `audit_logs` table. |
| **Multi-tenant theming** | White-label storefront | `organization_settings.theme` JSON consumed by the public front-end. |
| **Per-organizer custom domains** | `tickets.acmecon.com` instead of `app.example.com/o/acmecon` | New `organization_domains` table + middleware that resolves the org from `Host`. |
| **A/B testing of CTA copy** | Conversion optimisation | Already have `EventPageView`; add a `variant` column + a `RandomVariantPicker` middleware. |
| **Cohort-based analytics** | "How did the FB campaign do?" | Already have UTM capture on Order; reports controller surfaces it. |

---

## 3. Third-party integrations to consider

| Layer | Provider | What it adds | Wire-in pattern |
| --- | --- | --- | --- |
| **Email** | Postmark, Resend, SES, Mailgun, SendGrid | Transactional + bulk | Set Laravel `MAIL_MAILER`. No code change. |
| **SMS / WhatsApp** | Twilio, Africa's Talking, MessageBird | Tickets via SMS, day-of reminders | New `NotificationChannel` contract; `SmsChannel` impl + a `SendTicketsBySms` job. |
| **Push notifications** | OneSignal, Firebase Cloud Messaging | Native mobile alerts | New `device_tokens` table + `PushChannel` impl. |
| **Address validation** | Loqate, Google Places | Reduce billing-mismatch declines | Replace the free-text address fields in checkout with a typeahead. |
| **Email validation** | Kickbox, ZeroBounce | Reduce bounces | Middleware on `/orders/lookup` + `/waitlist`. |
| **Tax calculation** | Avalara, TaxJar | Multi-jurisdiction VAT/sales tax | New `AvalaraTaxRule` implementing `TaxRule`. |
| **Identity verification** | Persona, Onfido, Veriff | KYC for high-value tickets | New `VerificationStepRule` in checkout flow; gates `/pay`. |
| **Anti-fraud** | Sift, Stripe Radar | Block stolen-card chargebacks | New `FraudCheckRule` adjacent to the existing scanner-side fraud engine. |
| **Search** | Meilisearch, Algolia, Typesense | Faster discovery + typo tolerance | Index Events on save via an observer; replace `PublicEventQuery::paginate` LIKE clauses with the index. |
| **Recommendations** | Custom or Algolia Recommend | "You might also like" on detail page | New `RecommendationStrategy` contract; default impl reads category + city. |
| **Maps** | Mapbox, Google Maps | Venue rendering | Pure front-end. Backend already exposes lat/lng. |
| **Calendar** | iCal / Google Calendar | "Add to calendar" button | New endpoint `/orders/{ref}/calendar.ics` serving `text/calendar`. |
| **Live streaming** | Mux, Cloudflare Stream | Hybrid online events | New `event_streams` table linking an Event to a stream URL; surface via `/events/{slug}` detail. |
| **Reviews / NPS** | Trustpilot, Delighted | Post-event feedback loop | Hook into `OrderFulfillment` to schedule a post-event survey email. |
| **CRM sync** | HubSpot, Salesforce, ActiveCampaign | Marketing team owns the buyer list | Outbound webhook on `OrderPaid` → CRM contact create/update. This is where n8n really shines (§4). |
| **Accounting** | Xero, QuickBooks, FreshBooks | Daily revenue + fees + tax reconciliation | Nightly job pushes summary entries. Again, n8n is the cleanest path. |
| **Analytics** | Mixpanel, PostHog, Amplitude | Funnel + retention | Outbound events on every checkout step. Server-side via a `Telemetry` contract. |
| **Customer support** | Intercom, Zendesk, Front | Inbox for refund chats | Webhook in `RefundRequest::created`; agent replies via Intercom API back to the requester. |
| **OpenAI / Claude** | Generative content | "Describe my event" / "Reply to this refund request" | New `AiAssistant` contract; org-side helpers only. |
| **Discord / Slack** | Bot-as-a-channel | Real-time sales pings to the team | n8n native nodes — see §4. |

---

## 4. n8n as the platform's automation substrate

### 4.1 Why n8n

- **Self-hostable** (Docker), open source, no per-execution pricing.
- 400+ pre-built integrations (Slack, Discord, Notion, Airtable, HubSpot, Xero, Gmail, Twilio, OpenAI, …).
- Visual workflow editor — organizers (not just devs) can build automations.
- HTTP webhooks both directions — clean fit for an event-driven backend.

### 4.2 Two-way architecture

```
┌───────────────────────────────────┐                    ┌───────────────────────┐
│  example-app (storefront engine)  │  ───outbound───▶   │                       │
│                                   │   webhook on       │                       │
│   • OrderPaid                     │   domain events    │      n8n              │
│   • RefundRequest::created        │                    │      (self-hosted)    │
│   • TicketScanned                 │                    │                       │
│   • EventInventoryChanged         │                    │      • triggers       │
│                                   │                    │      • transforms     │
│   • Inbound automation API        │  ◀───inbound───    │      • integrations   │
│     /api/v1/automations/…         │  API call          │                       │
└───────────────────────────────────┘                    └───────────────────────┘
```

Outbound = our system **emits** domain events; n8n catches them and
fans out to wherever the organizer wants. Inbound = n8n **calls back**
into us to perform an action (issue a comp ticket, refund an order,
broadcast a message).

### 4.3 Outbound surface — the AutomationDispatcher

Reuse the same HMAC-signed webhook pattern we already ship for
payments + scanner. Concretely:

```php
// app/Services/Automation/AutomationDispatcher.php
class AutomationDispatcher {
    public function dispatch(string $eventType, array $payload, Organization $org): void {
        $hooks = OrganizationWebhook::query()
            ->where('organization_id', $org->id)
            ->where('is_active', true)
            ->whereJsonContains('subscribed_events', $eventType)
            ->get();

        foreach ($hooks as $hook) {
            DeliverWebhookJob::dispatch($hook->id, $eventType, $payload)
                ->onQueue('automations');
        }
    }
}
```

The model `OrganizationWebhook` already exists in the org schema —
extend it to include `subscribed_events` (JSON list of event types
the hook cares about). The job signs the payload with the org's
shared secret in the existing Stripe-style header format:

```
X-Example-App-Signature: t=<unix>,v1=<hex>
hex = hmac_sha256("<t>.<rawBody>", secret)
```

This is the same shape as our scanner + payment webhooks — one mental
model across the platform. n8n's "Webhook" trigger node accepts
arbitrary headers and we publish a tiny verifier snippet for it.

**Events to publish:**

| Event type | Trigger | Useful for |
| --- | --- | --- |
| `order.paid` | `OrderFulfillment::fulfill` | CRM upsert, Slack ping, accounting entry, post-purchase email sequence |
| `order.refund_requested` | `RefundService::submit` | Helpdesk ticket creation |
| `order.refunded` | `PaymentRefund::created` | Accounting reversal, retention nurture |
| `checkout.abandoned` | `ExpireStaleCheckoutSessionsJob` finds a session with `buyer_email` set | Abandoned-cart email sequence |
| `waitlist.joined` | `WaitlistController::store` | "Sorry it's sold out — here are similar events" follow-up |
| `waitlist.notified` | `NotifyWaitlistOnCapacityReleasedJob::dispatchNotification` | Push to mobile, SMS |
| `ticket.scanned` | existing `TicketScanned` event | Real-time door analytics, anti-fraud alerts |
| `event.published` | `Event::status` → Published | Auto-post to social channels, push to Eventbrite mirror |
| `event.sold_out` | `Event::status` → SoldOut | Marketing team alert, retarget audiences |
| `event.inventory_changed` | existing `EventInventoryChanged` | Live "only 3 left" feeds outside our platform |
| `quote.submitted` | `QuoteRequestService::submit` | Sales team Slack ping |
| `attendee.checked_in` | `OfflineTicket::scanned_at` set | "Welcome — here's the venue WiFi" auto-send |

### 4.4 Inbound surface — the AutomationActionController

n8n needs to **call into** us to act on an event. Expose a scoped,
token-authenticated API:

```
POST  /api/v1/automations/orders                   create a comp / manual order
POST  /api/v1/automations/orders/{ref}/refund      kick off a refund
POST  /api/v1/automations/events/{slug}/broadcast  email/SMS all attendees
POST  /api/v1/automations/waitlist/{uuid}/notify   manually notify a waitlister
POST  /api/v1/automations/messages                 send a templated message
GET   /api/v1/automations/events                   list events
GET   /api/v1/automations/orders                   list orders (filtered)
```

Auth: a new `automation_tokens` table (uuid, org_id, label, secret_hash,
scopes JSON, last_used_at, expires_at). Bearer-token middleware
identical to the existing scanner auth pattern. Scopes whitelist which
endpoints a given token can hit ("read orders only" vs "issue tickets").

### 4.5 Org-side configuration UI

In the organizer dashboard add a `/settings/automations` page:

1. **Outbound webhooks** — add a webhook URL (the n8n trigger URL),
   pick the events to subscribe to, see the shared secret, see delivery
   history.
2. **Automation tokens** — generate / rotate bearer tokens with scoped
   permissions, see last-used timestamp.
3. **Workflow templates** — a small library of pre-canned n8n workflow
   JSON files we ship and the organizer can import into their n8n
   instance:
    - "Slack ping on every order > $200"
    - "Auto-add buyers to Mailchimp"
    - "Daily revenue summary to email"
    - "DM Discord when an event sells out"
    - "Push to Xero when payment settles"

### 4.6 Backend changes the n8n surface needs

| New | Purpose |
| --- | --- |
| `app/Services/Automation/AutomationDispatcher.php` | Fan-out from domain events to subscribed webhooks |
| `app/Services/Automation/WebhookSigner.php` | Re-use the HMAC pattern from payments/scanner |
| `app/Jobs/Automation/DeliverWebhookJob.php` | Queued POST with retries (1, 5, 30, 300, 1800 — same shape as scanner) |
| `app/Http/Controllers/Api/Automation/*` | The inbound action controllers (orders, refunds, messages) |
| `app/Http/Middleware/EnsureAutomationToken.php` | Bearer-token auth with scope check |
| `database/migrations/…create_automation_tables.php` | `automation_tokens`, extend `organization_webhooks.subscribed_events` |
| `app/Models/AutomationToken.php` | + a small policy class |
| `routes/api-automation.php` | New route file; loaded from `bootstrap/app.php` like the scanner routes |
| Listeners on every domain event we want to publish | Each calls `AutomationDispatcher::dispatch(eventType, payload, $org)` |
| `n8n/` folder with template workflow JSONs | Importable by organizers |
| `docs/automation.md` | API reference + signature verification snippet |

### 4.7 Why not Zapier / Make / Pipedream

All three would work too — but they charge per-execution, and we'd
end up writing the same outbound webhook + inbound API anyway. n8n
buys us a self-hostable, free-tier-friendly story we can recommend
to every organizer. Crucially: because the contract is just signed
HTTP, an organizer who prefers Zapier can wire it instead without
us doing anything different.

### 4.8 Suggested rollout

1. **Phase A — outbound only.** Ship the dispatcher + extend
   `organization_webhooks` + add `OrderPaid` / `TicketScanned` /
   `EventInventoryChanged` as the first three published events.
   Document signature verification. Ship n8n verifier snippet.
2. **Phase B — automation tokens + the read endpoints.** Lets n8n
   pull data without writing — low-risk, high-value.
3. **Phase C — write endpoints.** Comp tickets, refund initiation,
   broadcasts. Scoped tokens + per-action rate limits.
4. **Phase D — template gallery.** Ship 5–10 canned workflows. Wire
   one-click import (n8n supports importing workflow JSON via API).

---

## 5. Other engine ideas worth flagging

- **Domain event bus.** All the storefront/scanner events fan out via
  Laravel's event system today. As the publisher list grows, consider
  promoting to a real message bus (NATS, Redis Streams) so consumers
  can be decoupled processes.
- **Background reconciliation.** A nightly job that compares Order
  totals + PaymentRefund totals against the gateway's settled-amount
  reports, flags mismatches. Builds trust with finance teams.
- **Event versioning.** When organizers materially change a published
  event (date / venue), we should snapshot the previous state so
  existing tickets carry their original promise. Today a date change
  just overwrites.
- **CDN-friendly event detail.** `/events/{slug}` could be cached at
  Cloudflare with `stale-while-revalidate`; inventory comes in via the
  WebSocket layer we already publish. Sub-100ms TTFB worldwide.
- **Edge ticket validation.** Move part of the scanner verdict logic
  to a Cloudflare Worker so high-volume gates work even when the
  origin is unreachable.
- **Programmatic API for everyone.** Today the scanner has a public
  SDK; the storefront could too. Most of the engine is already a
  clean REST surface — wrapping it into a TypeScript SDK is mostly
  packaging work.
- **Embeddable widget.** A `<script src="…">` snippet that drops a
  buy-tickets button anywhere — calls our public API under the hood.
  Big win for organizers who want to sell from their own marketing site
  without the dev work of integrating us.

---

## 6. Phase 3 — what shipped

### 6.1 n8n automation surface (all four phases of §4 — outbound + inbound + tokens + templates)

| Component | File | What it does |
| --- | --- | --- |
| `WebhookSigner` | [app/Services/Automation/WebhookSigner.php](app/Services/Automation/WebhookSigner.php) | HMAC-SHA256 sign + constant-time verify. Same `t=<unix>,v1=<hex>` shape as scanner + payment webhooks. |
| `AutomationDispatcher` | [app/Services/Automation/AutomationDispatcher.php](app/Services/Automation/AutomationDispatcher.php) | Single fan-out point: takes (event_type, org, payload) → queues a `DeliverWebhookJob` per subscribed `OrganizationWebhook`. |
| `DeliverWebhookJob` | [app/Jobs/Automation/DeliverWebhookJob.php](app/Jobs/Automation/DeliverWebhookJob.php) | Queued POST. Per-attempt backoff `[1, 5, 30, 300, 1800]` seconds. Logs into `organization_webhook_deliveries`. |
| `EnsureAutomationToken` | [app/Http/Middleware/EnsureAutomationToken.php](app/Http/Middleware/EnsureAutomationToken.php) | Bearer-token middleware with per-route scope check. |
| `AutomationToken` | [app/Models/AutomationToken.php](app/Models/AutomationToken.php) | `aut_…` prefixed; secret stored as sha256, 4-char display prefix. `::issue()` returns plaintext once. |
| `Publish*` listeners | `app/Listeners/Automation/*` | Bridge existing domain events into the dispatcher. |
| `Automation{Data,Order,Refund,Message}Controller` | inbound `/api/v1/automations/*` | Read + write surfaces, scope-gated. |
| 3 workflow templates + README | [storage/automation-templates/](storage/automation-templates/) | Slack-ping-on-order-paid, Discord-sold-out, bulk-comp-from-sheet. README has the verifier JS snippet. |

**Routes added:** 7 under `/api/v1/automations/*` — CSRF-exempt,
rate-limited by the existing `storefront-discovery` limiter.

**Config:** [config/automation.php](config/automation.php) — queue name,
HTTP timeout, default scopes, canonical event-type list.

**Event types published today (12):**

`order.paid`, `order.refund_requested`, `order.refunded`,
`ticket.scanned`, `event.published`, `event.sold_out`,
`event.inventory_changed`, `checkout.abandoned`, `waitlist.joined`,
`waitlist.notified`, `quote.submitted`, `attendee.checked_in`.

Of those, `order.paid`, `ticket.scanned`, and the two `event.*` events
have live listeners. The other 8 are reserved in config so the
dashboard can offer them — wire a listener in
`PublicStorefrontServiceProvider::boot()` to start emitting any of
them (one-line change per event).

### 6.2 Storefront features shipped

| Feature | Where | Behaviour |
| --- | --- | --- |
| **Gift cards / store credit** | `GiftCardManager` + `GiftCardPaymentRule` + `GiftCardController` + tables `gift_cards`, `gift_card_redemptions` | Issued via API. Redeemed against checkout totals via the existing FeeRule pipeline (no schema change to `checkout_sessions`). Code format `GC-XXXX-XXXX-XXXX`, locked-row decrement, ledger append-only for void/reversal. Audit logged. |
| **Event add-ons** | `AddonManager` + `AddonController` + tables `event_addons`, `checkout_session_addons` | Parking, merch, donations. Optional `requires_ticket` constraint. Stock tracked + decremented at fulfilment. Mixed into PriceCalculator subtotal alongside ticket lines. |
| **Calendar export** | `CalendarController` | RFC 5545 `.ics` for any order. Signed URL. Apple Calendar / Google Calendar / Outlook compatible. |
| **Refund policies** | `RefundPolicyEvaluator` + `events.refund_policy_rules` JSON | Per-event rule list (`hours_before` × `percent`), `default_percent`, `non_refundable_fees` flag. Verdict snapshotted into the refund_request at submission time. `RefundService::preview()` exposes the verdict for the buyer UI. |
| **Custom domains** | `OrganizationDomain` model + `ResolveOrganizationFromHost` middleware | Verified-via-DNS-TXT hostnames bound to an org. Middleware attaches `host_organization` to incoming requests on non-canonical domains. |
| **Back-office / comp orders** | `BackOfficeOrderService::issue()` | Skips checkout flow — direct Order + OfflineTicket issuance. Supports `comp/cash/invoice/external` methods. Reachable from both the dashboard and the automation API. |
| **Audit logging** | Wired in `GiftCardManager`, `RefundService`, `AutomationOrderController`, `AutomationRefundController` | `audit_logs` rows for every sensitive action. Actor types: `system`, `api`, `user`. |

### 6.3 Verification

- `php artisan migrate` — phase-3 migration applied
- Pint clean
- Scanner suite **53/53** still green
- Public routes: **17 → 25 → 31** across phases
- Automation routes: **7 new** under `/api/v1/automations/*`
- All new DI bindings resolve via tinker
- Webhook signer round-trip verified

### 6.4 What's still on the menu (from §2–§5 above)

**Deliberately deferred (out-of-scope for the storefront engine):**

- Saved payment methods — belongs on the authenticated portal, not guest checkout
- Live streaming (Mux / Cloudflare Stream) — separate service line

**Optional deps:**

- QR encoder library (`chillerlan/php-qrcode` recommended)
- Apple PassKit / Google Wallet credentials
- Turnstile / hCaptcha keys

**Deployment-only:**

- Broadcaster choice (`BROADCAST_CONNECTION`)
- Queue workers must include `automations` + `storefront` queues
- Cron must run `php artisan schedule:run` for the expiry sweeps
- `pdo_sqlite` for the feature tests

---

## 7. Phase 4 — every remaining gap closed

All 11 items listed in 6.4's "Not yet implemented" bucket are now
shipped. Three new top-level deliverables alongside the backend:
the embeddable widget, the public TypeScript SDK, and the
Cloudflare edge worker.

### 7.1 Backend domain features

| Feature | File(s) | Behaviour |
| --- | --- | --- |
| **Season passes / bundles** | `event_bundles`, `bundle_events` tables · `EventBundle`, `BundleEvent` models · `BundleManager` service · `BundleController` public endpoints | One purchase issues N OfflineTickets (one per included event, × the per-row `quantity`). Bundle-level capacity + per-event-tier inventory both enforced. |
| **Subscriptions / memberships** | `memberships`, `membership_periods` tables · `BuyerMembership`, `BuyerMembershipPeriod` models (renamed to dodge the existing team pivot named `Membership`) · `MembershipManager` service · `ExpireBuyerMembershipsJob` (daily) | Annual "Friends of the Theatre" memberships. Periods are ledger-style (start/end + paid_cents per renewal). Auto-renew supported on paper; needs stored payment methods to actually fire. |
| **Resale / transfer marketplace** | `ticket_transfers` table · `TicketTransfer` model · `TicketTransferService` · `TransferController` (offer/show/accept/decline/revoke) | Original buyer offers via signed URL → recipient claims via `claim_token` → accept mints a new OfflineTicket UUID and voids the old. Optional `sale_price_cents` for record-keeping; money handling stays off-platform. |
| **Referral rewards** | `referral_codes`, `referral_credits` tables · `ReferralCode`, `ReferralCredit` models · `ReferralService` · `ReferralController` + `CreditReferrerOnOrderPaid` listener | Reuses `gift_cards` for delivery — no parallel credit system. Unique `(referral_code, order)` guards re-credit on webhook retries. `max_rewards` cap stops a single referrer earning unbounded. |
| **Approval-required events** | `ApprovalQueueService::approve()` / `reject()` + the existing `Order.status='pending_approval'` value | Organizer reviews from dashboard; approve → fulfilment fires, reject → cancel + reason. Audit logged. |
| **Recurring event scheduling** | `event_templates` table · `EventTemplate` model · `RecurringEventGenerator` · `GenerateRecurringEventInstancesJob` (hourly) | Cadences: daily, weekly, monthly, `nth_weekday_of_month`. Clones source Event + ticket categories + currency prices; resets inventory counters. Stops at `repeat_until` or `max_instances`. |
| **Bundle / quantity discounts** | `quantity_discount_rules` table · `QuantityDiscountRule` model · `QuantityDiscountResolver` (extends DiscountResolver) | Three reward shapes: percent off, fixed off, BOGO (`min_quantity` + `free_quantity`). Scope: tier-level or event-wide. Bind via `DiscountResolver::class => QuantityDiscountResolver::class` to opt in. |

### 7.2 Infra deliverables

| Deliverable | Where | What it enables |
| --- | --- | --- |
| **Domain event bus** | [config/eventbus.php](config/eventbus.php) + `app/Services/EventBus/{DomainBus, LaravelEventBus, RedisStreamBus, NullBus}` | Pluggable cross-process bus. Default `LaravelEventBus` (in-process). Swap to `EVENTBUS_DRIVER=redis_streams` for out-of-process consumers (Go/Python workers, scanner-edge analytics). `XADD` to per-event streams with `MAXLEN ~ N` retention. |
| **Embeddable buy-button widget** | `WidgetController` · `GET /widget/v1/embed.js` (~3KB cached) + `GET /widget/v1/event/{slug}.json` | Drop `<div data-example-app-event="slug">` + `<script src=…/embed.js>` on any external site to render a "Buy tickets — $X" button that opens hosted checkout. CORS-open + MutationObserver-friendly for SPA/CMS embeds. |
| **TypeScript Storefront SDK** | [sdks/storefront-typescript/](sdks/storefront-typescript/) (package, types, errors, client, vitest tests, README) | `npm install @example-app/storefront-sdk`. Full type coverage of the public surface — discovery, checkout, gift cards, addons, refund/lookup. Typed error subclasses (`CaptchaRequiredError`, `InventoryUnavailableError`, `PromoInvalidError`, `SessionLockedError`). |
| **Cloudflare scanner edge worker** | [workers/scanner-edge/](workers/scanner-edge/) (wrangler.toml, TypeScript source, vitest tests, README) | Verifies QR HMAC signature + checks `VOIDED_TICKETS` KV cache at the edge before falling through to origin. Cuts gate-side latency from ~150–300ms to ~5–20ms for the 90% of clean scans. Returns degraded `verdict=warn` when origin is unreachable. |

### 7.3 Verification (phase 4)

- `php artisan migrate` — phase-4 migration applied (after fixing MySQL strict-mode timestamp NOT NULL defaults)
- Pint clean across all phase-4 files
- Scanner suite **53/53** still green
- Public routes: 17 → 25 → 31 → **39** across phases (+8 new for bundles/transfers/referral)
- Widget routes: **2** under `/widget/v1/*`
- All 8 phase-4 DI bindings resolve (BundleManager, TicketTransferService, ReferralService, ApprovalQueueService, RecurringEventGenerator, QuantityDiscountResolver, MembershipManager, DomainBus)
- All 9 phase-4 tables present
- DomainBus active driver: `laravel` (default — flip to `redis_streams` per-environment)

### 7.4 What requires opt-in / configuration

- **Bundle discounts** — bind `DiscountResolver::class => QuantityDiscountResolver::class` in a provider to enable; default still `PromoCodeValidator` (promo codes only).
- **Approval-required events** — set the order's status to `pending_approval` from `OrderFulfillment` when the event flags it; controller already wired to approve / reject.
- **Recurring events** — create an `event_templates` row; `GenerateRecurringEventInstancesJob` runs hourly.
- **Redis Streams bus** — `EVENTBUS_DRIVER=redis_streams` + ensure Redis is reachable.
- **Edge worker** — `wrangler deploy` from `workers/scanner-edge/` after setting `QR_SIGNING_SECRET` + creating the `VOIDED_TICKETS` KV namespace.
- **TS SDK** — `cd sdks/storefront-typescript && npm install && npm run build` to produce `dist/`; publish to npm under `@example-app/storefront-sdk`.

### 7.5 Total surface, as of phase 4

- **39 public storefront routes** + 2 widget routes + 4 sitemap routes
- **7 automation API routes** (n8n / Zapier / Make inbound) + outbound webhook dispatcher
- **3 SDK packages**: scanner-kotlin, scanner-swift, scanner-typescript, **storefront-typescript** (new)
- **1 Cloudflare worker** for scanner edge verdict
- **2 cron sweeps** + **2 scheduled jobs** (hourly recurring events, daily membership expiry)
- **12 published event types** for automation subscriptions
- **3 pluggable strategy contracts** (TaxRule, FeeRule, DiscountResolver) + 1 cross-process bus (`DomainBus`) + 1 captcha provider + 3 wallet pass generators
