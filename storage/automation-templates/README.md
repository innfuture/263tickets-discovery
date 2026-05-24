# Automation workflow templates

Ready-to-import n8n workflows that consume the example-app
automation surface. Drop a JSON file into n8n via
**Workflows → Import from File** then set the required env vars.

## Outbound (example-app → n8n)

Pre-register the n8n webhook URL in your organizer dashboard under
**Settings → Automations**, select the events to subscribe to, and
copy the displayed signing secret into the n8n environment as
`EXAMPLE_APP_WEBHOOK_SECRET`.

| File | Subscribes to | What it does |
| --- | --- | --- |
| `slack-ping-on-order-paid.json` | `order.paid` | Sends a Slack message on every paid order |
| `discord-sold-out-alert.json` | `event.sold_out`, `event.inventory_changed` | Discord ping when an event sells out |

## Inbound (n8n → example-app)

Mint an automation token in the dashboard with the scopes the
workflow needs, then set `EXAMPLE_APP_AUTOMATION_TOKEN` in n8n.

| File | Required scope | What it does |
| --- | --- | --- |
| `issue-bulk-comps-from-google-sheet.json` | `orders.write` | Polls a sheet of sponsor allocations, issues comp tickets via the API |

## Webhook signature verification (snippet)

Every outbound webhook carries `X-Example-App-Signature: t=<unix>,v1=<hex>`
where `hex = HMAC-SHA256("<t>.<rawBody>", signing_secret)`. The
templates above include a Code node that verifies this; pasted here
for re-use in custom flows:

```javascript
const crypto = require('crypto');
const header = $input.first().json.headers['x-example-app-signature'];
const raw = JSON.stringify($input.first().json.body);
const secret = $env.EXAMPLE_APP_WEBHOOK_SECRET;

const parts = Object.fromEntries(
  header.split(',').map(p => p.trim().split('=', 2))
);
const t = parseInt(parts.t || 0, 10);
const v1 = parts.v1 || '';

if (!t || !v1) throw new Error('Bad signature header');
if (Math.abs(Date.now() / 1000 - t) > 300) throw new Error('Signature too old');

const expected = crypto
  .createHmac('sha256', secret)
  .update(`${t}.${raw}`)
  .digest('hex');

if (expected.length !== v1.length || !crypto.timingSafeEqual(
  Buffer.from(expected), Buffer.from(v1)
)) throw new Error('Signature mismatch');

return $input.first().json;
```
