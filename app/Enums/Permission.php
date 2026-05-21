<?php

namespace App\Enums;

/**
 * Canonical permission catalogue for the platform. Every permission a
 * policy checks must appear here — the seeder reads this enum to
 * materialise rows in the `permissions` table per org.
 *
 * Naming convention: `resource.action` (e.g. `event.publish`). When a
 * new feature lands:
 *   1. Add a case here in the right resource bucket.
 *   2. Add the human label in `label()`.
 *   3. Run `php artisan rbac:sync` (or re-run the seeder) to materialise
 *      it for existing orgs.
 *   4. Reference it from your Policy via $user->can(Permission::Foo->value).
 *
 * The seeder respects `group()` to bucket permissions on the role
 * editor UI.
 */
enum Permission: string
{
    // ── Organization ────────────────────────────────────────────────────
    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';
    case OrganizationDelete = 'organization.delete';
    case OrganizationTransferOwnership = 'organization.transfer-ownership';
    case OrganizationManageBilling = 'organization.manage-billing';

    // ── Organization members ────────────────────────────────────────────
    case OrganizationMemberView = 'organization_member.view';
    case OrganizationMemberInvite = 'organization_member.invite';
    case OrganizationMemberUpdateRole = 'organization_member.update-role';
    case OrganizationMemberRemove = 'organization_member.remove';

    // ── Sub-teams ───────────────────────────────────────────────────────
    case TeamCreate = 'team.create';
    case TeamView = 'team.view';
    case TeamUpdate = 'team.update';
    case TeamDelete = 'team.delete';
    case TeamManageMembers = 'team.manage-members';

    // ── Roles (custom-role administration) ──────────────────────────────
    case RoleView = 'role.view';
    case RoleManage = 'role.manage';

    // ── Events ──────────────────────────────────────────────────────────
    case EventCreate = 'event.create';
    case EventView = 'event.view';
    case EventUpdate = 'event.update';
    case EventDelete = 'event.delete';
    case EventPublish = 'event.publish';
    case EventUnpublish = 'event.unpublish';
    case EventDuplicate = 'event.duplicate';
    case EventTransfer = 'event.transfer';
    case EventMediaManage = 'event_media.manage';
    case EventSponsorsManage = 'event_sponsors.manage';
    case EventLineupManage = 'event_lineup.manage';

    // ── Ticket categories (inventory) ───────────────────────────────────
    case TicketCategoryCreate = 'ticket_category.create';
    case TicketCategoryUpdate = 'ticket_category.update';
    case TicketCategoryDelete = 'ticket_category.delete';
    case TicketCategoryAdjustInventory = 'ticket_category.adjust-inventory';
    case TicketCategoryManageDiscounts = 'ticket_category.manage-discounts';
    case TicketCategoryManagePromoCodes = 'ticket_category.manage-promo-codes';

    // ── Tickets (per-attendee) ──────────────────────────────────────────
    case TicketView = 'ticket.view';
    case TicketScan = 'ticket.scan';
    case TicketRefund = 'ticket.refund';
    case TicketVoid = 'ticket.void';
    case TicketTransfer = 'ticket.transfer';
    case TicketResend = 'ticket.resend';

    // ── Attendees ───────────────────────────────────────────────────────
    case AttendeeView = 'attendee.view';
    case AttendeeContact = 'attendee.contact';
    case AttendeeExport = 'attendee.export';

    // ── Ad campaigns ────────────────────────────────────────────────────
    case AdCampaignCreate = 'ad_campaign.create';
    case AdCampaignView = 'ad_campaign.view';
    case AdCampaignUpdate = 'ad_campaign.update';
    case AdCampaignPause = 'ad_campaign.pause';
    case AdCampaignDelete = 'ad_campaign.delete';
    case AdCampaignViewMetrics = 'ad_campaign.view-metrics';

    // ── Analytics & Finance ─────────────────────────────────────────────
    case AnalyticsViewEvent = 'analytics.view-event';
    case AnalyticsViewOrg = 'analytics.view-org';
    case AnalyticsExport = 'analytics.export';
    case FinanceViewRevenue = 'finance.view-revenue';
    case FinanceProcessRefund = 'finance.process-refund';
    case FinanceExportReports = 'finance.export-reports';

    // ── API + Webhooks ──────────────────────────────────────────────────
    case ApiManageKeys = 'api.manage-keys';
    case WebhookManage = 'webhook.manage';

    /**
     * Resource bucket used by the role-editor UI to group checkboxes.
     */
    public function group(): string
    {
        return [
            'organization' => 'Organization',
            'organization_member' => 'Members',
            'team' => 'Teams',
            'role' => 'Roles',
            'event' => 'Events',
            'event_media' => 'Events',
            'event_sponsors' => 'Events',
            'event_lineup' => 'Events',
            'ticket_category' => 'Ticket inventory',
            'ticket' => 'Tickets',
            'attendee' => 'Attendees',
            'ad_campaign' => 'Marketing',
            'analytics' => 'Analytics',
            'finance' => 'Finance',
            'api' => 'Developer',
            'webhook' => 'Developer',
        ][explode('.', $this->value)[0]] ?? 'Other';
    }

    /**
     * Human label for the checkbox list. Defaults to the verb after
     * the dot ("event.publish" → "Publish") if no explicit override.
     */
    public function label(): string
    {
        return match ($this) {
            self::OrganizationView => 'View organization',
            self::OrganizationUpdate => 'Update organization profile',
            self::OrganizationDelete => 'Delete organization',
            self::OrganizationTransferOwnership => 'Transfer ownership',
            self::OrganizationManageBilling => 'Manage billing',
            self::OrganizationMemberView => 'View members',
            self::OrganizationMemberInvite => 'Invite members',
            self::OrganizationMemberUpdateRole => 'Change member roles',
            self::OrganizationMemberRemove => 'Remove members',
            self::TeamCreate => 'Create teams',
            self::TeamView => 'View teams',
            self::TeamUpdate => 'Update team details',
            self::TeamDelete => 'Delete teams',
            self::TeamManageMembers => 'Manage team members',
            self::RoleView => 'View roles',
            self::RoleManage => 'Create &amp; edit custom roles',
            self::EventCreate => 'Create events',
            self::EventView => 'View events',
            self::EventUpdate => 'Edit events',
            self::EventDelete => 'Delete events',
            self::EventPublish => 'Publish events',
            self::EventUnpublish => 'Unpublish events',
            self::EventDuplicate => 'Duplicate events',
            self::EventTransfer => 'Transfer events between teams',
            self::EventMediaManage => 'Manage event media',
            self::EventSponsorsManage => 'Manage event sponsors',
            self::EventLineupManage => 'Manage event lineup',
            self::TicketCategoryCreate => 'Create ticket categories',
            self::TicketCategoryUpdate => 'Update ticket categories',
            self::TicketCategoryDelete => 'Delete ticket categories',
            self::TicketCategoryAdjustInventory => 'Adjust ticket inventory',
            self::TicketCategoryManageDiscounts => 'Manage discounts',
            self::TicketCategoryManagePromoCodes => 'Manage promo codes',
            self::TicketView => 'View tickets',
            self::TicketScan => 'Scan tickets at the door',
            self::TicketRefund => 'Refund tickets',
            self::TicketVoid => 'Void tickets',
            self::TicketTransfer => 'Transfer tickets',
            self::TicketResend => 'Resend tickets',
            self::AttendeeView => 'View attendees',
            self::AttendeeContact => 'Contact attendees',
            self::AttendeeExport => 'Export attendee data',
            self::AdCampaignCreate => 'Create ad campaigns',
            self::AdCampaignView => 'View ad campaigns',
            self::AdCampaignUpdate => 'Update ad campaigns',
            self::AdCampaignPause => 'Pause ad campaigns',
            self::AdCampaignDelete => 'Delete ad campaigns',
            self::AdCampaignViewMetrics => 'View ad campaign metrics',
            self::AnalyticsViewEvent => 'View event analytics',
            self::AnalyticsViewOrg => 'View organization analytics',
            self::AnalyticsExport => 'Export analytics',
            self::FinanceViewRevenue => 'View revenue',
            self::FinanceProcessRefund => 'Process refunds',
            self::FinanceExportReports => 'Export financial reports',
            self::ApiManageKeys => 'Manage API keys',
            self::WebhookManage => 'Manage webhooks',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
