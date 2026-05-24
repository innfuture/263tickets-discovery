# `@example-app/storefront-sdk`

Official TypeScript SDK for the example-app public storefront API.

## Install

```bash
npm install @example-app/storefront-sdk
```

Node 18+ (uses the global `fetch`).

## Discover events

```ts
import { StorefrontClient } from '@example-app/storefront-sdk';

const sdk = new StorefrontClient({ baseUrl: 'https://app.example.com' });

const { data: events } = await sdk.listEvents({ city: 'Harare', sort: 'popular' });
const { data: event } = await sdk.event('zim-jazz-fest-2026');
```

## Run a checkout

```ts
import {
  CaptchaRequiredError,
  InventoryUnavailableError,
  PromoInvalidError,
  SessionLockedError,
} from '@example-app/storefront-sdk';

try {
  const { data: session } = await sdk.createSession({
    eventSlug: event.slug,
    currency: 'USD',
    idempotencyKey: crypto.randomUUID(),
  });

  await sdk.setItems(session.uuid, [{ ticket_category_id: event.tickets[0].id, quantity: 2 }]);
  await sdk.setAttendees(session.uuid, {
    buyer_name: 'Ada Lovelace',
    buyer_email: 'ada@example.com',
  });

  const { data } = await sdk.pay(session.uuid, 'paynow', 'https://my-site/return');
  if (data.payment.requires_action) window.location.href = data.payment.redirect_url!;
} catch (e) {
  if (e instanceof CaptchaRequiredError) renderCaptcha(e.provider, e.siteKey);
  else if (e instanceof InventoryUnavailableError) toast(`Only ${e.available} left.`);
  else if (e instanceof PromoInvalidError) toast(e.message);
  else if (e instanceof SessionLockedError) location.reload();
  else throw e;
}
```

## Lookup an order + render tickets

```ts
const { data: order } = await sdk.lookupOrder('ORD-AB12-3CD4', 'ada@example.com');
for (const item of order.items) {
  renderQr(item.qr_payload);
  if (item.pass_urls) showAddToWalletButtons(item.pass_urls);
}
```

## Build + test

```bash
npm install
npm test    # vitest
npm run build
```
