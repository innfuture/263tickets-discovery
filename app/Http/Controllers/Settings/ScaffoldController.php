<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single entry-point for every settings page that's permission-gated
 * and routed but doesn't have a real backing implementation yet.
 *
 * Each route in routes/settings.php invokes `show($page)` with a slug
 * keying into `CATALOGUE`. The entry declares:
 *
 *   - permission: Spatie permission string asserted before render.
 *   - component:  Inertia page name (under resources/js/pages/).
 *   - title / description / futureFields: scaffold copy.
 *
 * When a page graduates to a real form, swap its route to a dedicated
 * controller method — the catalogue entry can be deleted without
 * touching anything else.
 *
 * This keeps the 27 stub pages in one auditable place rather than 27
 * tiny controller files.
 */
class ScaffoldController extends Controller
{
    /**
     * @var array<string, array{permission: string, component: string, title: string, description: string, futureFields: array<int, string>, breadcrumbs: array<int, array{title: string, href: string}>}>
     */
    private const CATALOGUE = [
        // ── Organization ───────────────────────────────────────────────
        'organization.brand' => [
            'permission' => 'organization.manage-brand',
            'component' => 'settings/organization/brand',
            'title' => 'Brand kit',
            'description' => 'Logo variants, palette, fonts and email theming that propagate to every event page, attendee email, and PDF ticket your organization produces.',
            'futureFields' => [
                'Logo variants (light, dark, email)',
                'Primary &amp; accent colors',
                'Heading + body font choice',
                'Email template preview',
                'PDF ticket header',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Brand kit', 'href' => '/settings/organization/brand'],
            ],
        ],
        'organization.domain' => [
            'permission' => 'organization.manage-domain',
            'component' => 'settings/organization/domain',
            'title' => 'Custom domain',
            'description' => 'Host your event pages on a domain you own (e.g. events.your-org.com). SSL is provisioned automatically once the DNS record is verified.',
            'futureFields' => [
                'Custom domain name',
                'CNAME setup instructions',
                'DNS verification status',
                'SSL certificate status',
                'Apex / subdomain mode',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Domain', 'href' => '/settings/organization/domain'],
            ],
        ],
        'organization.public' => [
            'permission' => 'organization.update',
            'component' => 'settings/organization/public',
            'title' => 'Public profile page',
            'description' => 'Controls for the Eventbrite-style /o/{slug} profile attendees land on when they tap your organizer name.',
            'futureFields' => [
                'Public-page visibility toggle',
                'Featured events override',
                'Follower-prompt copy',
                'Hide past events',
                'Embed snippet',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Public page', 'href' => '/settings/organization/public'],
            ],
        ],

        // ── Account (personal — no org perm gate) ──────────────────────
        'account.security' => [
            'permission' => '*', // sentinel: no gate (account-level)
            'component' => 'settings/account/security',
            'title' => 'Security',
            'description' => 'Two-factor authentication, recovery codes, and IP allowlisting for your account.',
            'futureFields' => [
                'Two-factor enrolment',
                'Recovery codes',
                'Trusted IP allowlist',
                'Last-login location map',
                'Password policy (non-SSO)',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Security', 'href' => '/settings/security'],
            ],
        ],
        'account.api-tokens' => [
            'permission' => '*',
            'component' => 'settings/account/api-tokens',
            'title' => 'Personal API tokens',
            'description' => 'Tokens you use to call the platform API as yourself. Scoped to your effective permissions, revocable at any time.',
            'futureFields' => [
                'Token name',
                'Permission scopes',
                'Expiry date',
                'Last-used timestamp',
                'Rotate / revoke',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'API tokens', 'href' => '/settings/api-tokens'],
            ],
        ],
        'account.sessions' => [
            'permission' => '*',
            'component' => 'settings/account/sessions',
            'title' => 'Active sessions',
            'description' => 'Browsers and devices currently signed in to your account. Revoke any that you no longer recognise.',
            'futureFields' => [
                'Device + browser',
                'IP &amp; location',
                'Started at',
                'Last active',
                'Revoke session',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Sessions', 'href' => '/settings/sessions'],
            ],
        ],

        // ── Operations ─────────────────────────────────────────────────
        'operations.ticket-templates' => [
            'permission' => 'ticket_category.update',
            'component' => 'settings/operations/ticket-templates',
            'title' => 'Ticket templates',
            'description' => 'Design the PDF + Apple Wallet artwork attendees receive after purchase. Templates inherit your brand kit by default.',
            'futureFields' => [
                'PDF layout',
                'Apple Wallet template',
                'Language fallback',
                'Logo placement',
                'Terms footer',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Ticket templates', 'href' => '/settings/operations/ticket-templates'],
            ],
        ],
        'operations.email-identity' => [
            'permission' => 'organization.update',
            'component' => 'settings/operations/email-identity',
            'title' => 'Email sender identity',
            'description' => 'Verify a domain so attendee emails arrive from "tickets@your-org.com" with DKIM and SPF passing, instead of through our shared sender.',
            'futureFields' => [
                'Verified sending domain',
                'DKIM / SPF DNS records',
                'From-name &amp; reply-to',
                'Per-event sender override',
                'Bounce rate (last 30 days)',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Email sender identity', 'href' => '/settings/operations/email-identity'],
            ],
        ],
        'operations.webhooks' => [
            'permission' => 'webhook.manage',
            'component' => 'settings/operations/webhooks',
            'title' => 'Operational webhooks',
            'description' => 'Receive HTTP callbacks when key events happen — ticket sales, refunds, low inventory. Sign requests with a shared secret and retry on failure.',
            'futureFields' => [
                'Endpoint URL',
                'Subscribed events',
                'Signing secret',
                'Retry policy',
                'Recent deliveries log',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Webhooks', 'href' => '/settings/operations/webhooks'],
            ],
        ],

        // ── Plan & Billing ─────────────────────────────────────────────
        'billing.plan' => [
            'permission' => 'organization.manage-billing',
            'component' => 'settings/billing/plan',
            'title' => 'Plan &amp; usage',
            'description' => 'Your current plan, monthly limits, and what you have used so far this cycle.',
            'futureFields' => [
                'Current plan tier',
                'Events created (month)',
                'Tickets sold (month)',
                'Unique attendees (month)',
                'Storage used',
                'Upgrade / downgrade',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Plan &amp; usage', 'href' => '/settings/billing/plan'],
            ],
        ],
        'billing.methods' => [
            'permission' => 'organization.manage-billing',
            'component' => 'settings/billing/methods',
            'title' => 'Payment methods',
            'description' => 'Cards on file used to pay for your subscription and any platform usage fees.',
            'futureFields' => [
                'Cards on file',
                'Default payment method',
                'Add new card',
                'Billing email',
                'Billing address',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Payment methods', 'href' => '/settings/billing/methods'],
            ],
        ],
        'billing.invoices' => [
            'permission' => 'organization.manage-billing',
            'component' => 'settings/billing/invoices',
            'title' => 'Invoices',
            'description' => 'Historical platform invoices with downloadable PDFs.',
            'futureFields' => [
                'Invoice number',
                'Issue date',
                'Amount',
                'Status (paid / due / overdue)',
                'Download PDF',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Invoices', 'href' => '/settings/billing/invoices'],
            ],
        ],
        'billing.payouts' => [
            'permission' => 'finance.view-revenue',
            'component' => 'settings/billing/payouts',
            'title' => 'Payouts',
            'description' => 'Connect a payout destination (Stripe Connect) and review the schedule and history of money paid out to your organization.',
            'futureFields' => [
                'Stripe Connect status',
                'Bank account / debit card',
                'Payout schedule',
                'Withholding rules',
                'Payout history',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Payouts', 'href' => '/settings/billing/payouts'],
            ],
        ],
        'billing.taxes' => [
            'permission' => 'finance.export-reports',
            'component' => 'settings/billing/taxes',
            'title' => 'Taxes',
            'description' => 'Tax IDs, regional VAT / GST / sales-tax rates, and who-pays-fees defaults for newly created events.',
            'futureFields' => [
                'Org tax ID(s)',
                'Default tax rate per region',
                'Who-pays-fees toggle',
                'Tax-inclusive pricing',
                'Tax export (CSV)',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Taxes', 'href' => '/settings/billing/taxes'],
            ],
        ],
        'billing.refunds' => [
            'permission' => 'finance.process-refund',
            'component' => 'settings/billing/refunds',
            'title' => 'Refund policies',
            'description' => 'Named refund policies you can attach to any ticket type. Attendees see the policy text at checkout.',
            'futureFields' => [
                'Policy templates',
                'Cutoff window',
                'Pro-rated refunds',
                'Restocking fee',
                'Per-event override',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Refund policies', 'href' => '/settings/billing/refunds'],
            ],
        ],

        // ── Team (org-level views; sub-teams + roles live on existing routes) ──
        'team.members' => [
            'permission' => 'organization_member.view',
            'component' => 'settings/team/members',
            'title' => 'Organization members',
            'description' => 'Every person with access to this organization, regardless of which sub-team they are on. Inspect roles, last-seen, and which teams they belong to.',
            'futureFields' => [
                'Member list',
                'Role',
                'Sub-team membership',
                'Last seen',
                'Bulk role change',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Members', 'href' => '/settings/team/members'],
            ],
        ],
        'team.invitations' => [
            'permission' => 'organization_member.invite',
            'component' => 'settings/team/invitations',
            'title' => 'Invitations',
            'description' => 'Pending invitations to join this organization. Cancel or resend any that have not been accepted yet.',
            'futureFields' => [
                'Invitee email',
                'Role offered',
                'Invited by',
                'Sent at',
                'Resend / cancel',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Invitations', 'href' => '/settings/team/invitations'],
            ],
        ],

        // ── Integrations ───────────────────────────────────────────────
        'integrations.index' => [
            'permission' => 'integration.manage',
            'component' => 'settings/integrations/index',
            'title' => 'All integrations',
            'description' => 'Connect third-party services to extend the platform — payment processors, marketing automation, video conferencing, analytics, and CRM.',
            'futureFields' => [
                'Stripe',
                'Mailchimp',
                'Zapier',
                'Slack',
                'Zoom / Teams',
                'Google Calendar',
                'GA4 + Meta Pixel',
                'HubSpot / Salesforce',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Integrations', 'href' => '/settings/integrations'],
            ],
        ],
        'integrations.connected' => [
            'permission' => 'integration.manage',
            'component' => 'settings/integrations/connected',
            'title' => 'Connected apps',
            'description' => 'Apps already connected to this organization, with the scopes each one holds. Disconnect any you no longer use.',
            'futureFields' => [
                'App name',
                'Connected by',
                'Connected at',
                'Granted scopes',
                'Disconnect',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Connected apps', 'href' => '/settings/integrations/connected'],
            ],
        ],
        'integrations.oauth' => [
            'permission' => 'oauth_app.manage',
            'component' => 'settings/integrations/oauth',
            'title' => 'OAuth apps',
            'description' => 'Apps you have published that other developers can OAuth their users into. Useful for building partner integrations on top of the platform API.',
            'futureFields' => [
                'App name',
                'Client ID',
                'Client secret',
                'Redirect URIs',
                'Scopes',
                'Authorized users',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'OAuth apps', 'href' => '/settings/integrations/oauth'],
            ],
        ],

        // ── Data &amp; Compliance ───────────────────────────────────────
        'data.audit-log' => [
            'permission' => 'audit_log.view',
            'component' => 'settings/data/audit-log',
            'title' => 'Audit log',
            'description' => 'Every change made inside this organization — who, what, when, from where. Useful for debugging, dispute resolution, and compliance.',
            'futureFields' => [
                'Actor (user, API, system)',
                'Action',
                'Resource',
                'Before &rarr; after diff',
                'IP address',
                'Timestamp',
                'Filter &amp; export',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Audit log', 'href' => '/settings/data/audit-log'],
            ],
        ],
        'data.exports' => [
            'permission' => 'data.export-request',
            'component' => 'settings/data/exports',
            'title' => 'Data exports',
            'description' => 'Schedule async dumps of events, attendees, tickets, or financials. Exports email a download link when ready.',
            'futureFields' => [
                'Export type',
                'Date range',
                'Format (CSV / JSON)',
                'Delivery (email / S3)',
                'Schedule',
                'History',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Data exports', 'href' => '/settings/data/exports'],
            ],
        ],
        'data.gdpr' => [
            'permission' => 'data.gdpr-process',
            'component' => 'settings/data/gdpr',
            'title' => 'GDPR requests',
            'description' => 'Right-to-access and right-to-be-forgotten requests from attendees. Each request runs through a documented queue with deletion confirmation.',
            'futureFields' => [
                'Requester email',
                'Request type (access / erase)',
                'Status',
                'Submitted at',
                'Resolved at',
                'Resolver',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'GDPR requests', 'href' => '/settings/data/gdpr'],
            ],
        ],
        'data.retention' => [
            'permission' => 'organization.manage-billing',
            'component' => 'settings/data/retention',
            'title' => 'Data retention',
            'description' => 'How long each resource is kept before automatic deletion. Default windows are based on legal recommendations but can be tightened per org policy.',
            'futureFields' => [
                'Events retention',
                'Attendee PII retention',
                'Ticket records retention',
                'Audit log retention',
                'Backup retention',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Retention', 'href' => '/settings/data/retention'],
            ],
        ],

        // ── Developer ──────────────────────────────────────────────────
        'developer.api-keys' => [
            'permission' => 'api.manage-keys',
            'component' => 'settings/developer/api-keys',
            'title' => 'Org API keys',
            'description' => 'Server-to-server API keys scoped to this organization. Use these in your backend integrations and CI pipelines.',
            'futureFields' => [
                'Key name',
                'Permission scopes',
                'IP allowlist',
                'Last-used timestamp',
                'Rotate / revoke',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'API keys', 'href' => '/settings/developer/api-keys'],
            ],
        ],
        'developer.webhooks' => [
            'permission' => 'webhook.manage',
            'component' => 'settings/developer/webhooks',
            'title' => 'Developer webhooks',
            'description' => 'Like the operational webhooks, but with a deeper delivery log and replay tools for debugging your integration.',
            'futureFields' => [
                'Endpoint URL',
                'Subscribed events',
                'Signing secret',
                'Delivery log (last 1000)',
                'Retry / replay',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Webhooks', 'href' => '/settings/developer/webhooks'],
            ],
        ],
        'developer.logs' => [
            'permission' => 'api.manage-keys',
            'component' => 'settings/developer/logs',
            'title' => 'API logs',
            'description' => 'Recent API calls against this organization, including status code, latency, and payload preview. Helpful for debugging client integrations.',
            'futureFields' => [
                'Endpoint',
                'Method',
                'Status code',
                'Latency',
                'Request &amp; response preview',
                'Filter by key / IP',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'API logs', 'href' => '/settings/developer/logs'],
            ],
        ],

        // ── Appearance (Locale is real-form; Dates is scaffold) ────────
        'appearance.dates' => [
            'permission' => 'organization.update',
            'component' => 'settings/appearance/dates',
            'title' => 'Date &amp; number formats',
            'description' => 'How dates, times, and numbers display in the dashboard and on attendee-facing surfaces.',
            'futureFields' => [
                'Date format',
                'Time format (12h / 24h)',
                'Week starts on',
                'Number grouping',
                'First day of fiscal year',
            ],
            'breadcrumbs' => [
                ['title' => 'Settings', 'href' => '/settings'],
                ['title' => 'Date formats', 'href' => '/settings/appearance/dates'],
            ],
        ],
    ];

    public function show(Request $request, string $page): Response
    {
        abort_unless(isset(self::CATALOGUE[$page]), 404);
        $entry = self::CATALOGUE[$page];

        // Account-level pages use the '*' sentinel and have no perm
        // gate beyond being logged in. Everything else requires the
        // declared Spatie permission in the active org.
        if ($entry['permission'] !== '*') {
            abort_unless($request->user()->can($entry['permission']), 403);
        }

        return Inertia::render($entry['component'], [
            'scaffold' => [
                'title' => $entry['title'],
                'description' => $entry['description'],
                'futureFields' => $entry['futureFields'],
                'cta' => null,
            ],
            'breadcrumbs' => $entry['breadcrumbs'],
        ]);
    }
}
