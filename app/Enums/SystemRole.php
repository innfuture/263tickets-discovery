<?php

namespace App\Enums;

/**
 * System roles shipped by the platform. These are recreated per-org by
 * the role seeder and cannot be edited or deleted from the UI
 * (orgs add their own *custom* roles instead).
 *
 * `level` defines the hierarchy: a user with a higher-level role can
 * act on users with strictly lower levels (e.g. an Admin can change
 * an Event Manager's role but not another Admin's).
 */
enum SystemRole: string
{
    case Owner = 'Owner';
    case Admin = 'Admin';
    case EventManager = 'Event Manager';
    case BoxOfficeManager = 'Box Office Manager';
    case MarketingManager = 'Marketing Manager';
    case FinanceManager = 'Finance Manager';
    case SupportAgent = 'Support Agent';
    case DoorStaff = 'Door Staff';
    case Analyst = 'Analyst';
    case Member = 'Member';

    public function level(): int
    {
        return match ($this) {
            self::Owner => 100,
            self::Admin => 80,
            self::EventManager,
            self::BoxOfficeManager => 60,
            self::MarketingManager,
            self::FinanceManager => 50,
            self::SupportAgent => 40,
            self::DoorStaff,
            self::Analyst => 20,
            self::Member => 10,
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full control of the organization, including billing and ownership transfer. Cannot be removed.',
            self::Admin => 'Everything an owner can do except billing and ownership transfer.',
            self::EventManager => 'Creates, edits and publishes events. Manages ticket categories and lineup.',
            self::BoxOfficeManager => 'Owns ticket inventory, refunds, transfers and door-scanner operations.',
            self::MarketingManager => 'Runs ad campaigns and curates the public profile, sponsors and event marketing.',
            self::FinanceManager => 'Views revenue, processes refunds and exports financial reports.',
            self::SupportAgent => 'Handles attendee questions, resends tickets and processes policy-bounded refunds.',
            self::DoorStaff => 'Scans tickets at the venue. No editing privileges.',
            self::Analyst => 'Read-only access to event and org analytics and reports.',
            self::Member => 'Baseline org visibility. Cannot edit anything by default.',
        };
    }

    /**
     * Default permission set granted when the seeder materialises this
     * role for an organization. Custom roles can be created with any
     * subset.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::all(), // every permission

            self::Admin => array_values(array_filter(
                Permission::all(),
                fn (Permission $p) => ! in_array($p, [
                    Permission::OrganizationTransferOwnership,
                    Permission::OrganizationManageBilling,
                    Permission::OrganizationDelete,
                ], true),
            )),

            self::EventManager => [
                Permission::OrganizationView,
                Permission::OrganizationMemberView,
                Permission::TeamView,
                Permission::RoleView,
                Permission::EventCreate, Permission::EventView, Permission::EventUpdate,
                Permission::EventPublish, Permission::EventUnpublish, Permission::EventDuplicate,
                Permission::EventMediaManage, Permission::EventSponsorsManage, Permission::EventLineupManage,
                Permission::TicketCategoryCreate, Permission::TicketCategoryUpdate,
                Permission::TicketCategoryAdjustInventory,
                Permission::TicketCategoryManageDiscounts, Permission::TicketCategoryManagePromoCodes,
                Permission::TicketView,
                Permission::AttendeeView, Permission::AttendeeContact,
                Permission::AnalyticsViewEvent,
                Permission::AuditLogView,
            ],

            self::BoxOfficeManager => [
                Permission::OrganizationView,
                Permission::TeamView,
                Permission::EventView,
                Permission::TicketCategoryUpdate, Permission::TicketCategoryAdjustInventory,
                Permission::TicketCategoryManageDiscounts, Permission::TicketCategoryManagePromoCodes,
                Permission::TicketView, Permission::TicketScan,
                Permission::TicketRefund, Permission::TicketVoid,
                Permission::TicketTransfer, Permission::TicketResend,
                Permission::AttendeeView, Permission::AttendeeContact,
                Permission::AnalyticsViewEvent,
                Permission::OrderView, Permission::OrderManage,
                Permission::OrderRefund, Permission::OrderResend,
            ],

            self::MarketingManager => [
                Permission::OrganizationView, Permission::OrganizationUpdate,
                Permission::OrganizationManageBrand, Permission::OrganizationManageDomain,
                Permission::TeamView,
                Permission::EventView,
                Permission::EventMediaManage, Permission::EventSponsorsManage, Permission::EventLineupManage,
                Permission::AdCampaignCreate, Permission::AdCampaignView, Permission::AdCampaignUpdate,
                Permission::AdCampaignPause, Permission::AdCampaignDelete, Permission::AdCampaignViewMetrics,
                Permission::AnalyticsViewEvent, Permission::AnalyticsViewOrg,
                Permission::IntegrationManage,
                Permission::MarketingEmailManage,
                Permission::MarketingSocialPublish,
                Permission::MarketingFacebookEventManage,
                Permission::MarketingPaidAdsManage,
            ],

            self::FinanceManager => [
                Permission::OrganizationView,
                Permission::TeamView,
                Permission::EventView,
                Permission::TicketView,
                Permission::TicketRefund,
                Permission::AttendeeView, Permission::AttendeeExport,
                Permission::AnalyticsViewEvent, Permission::AnalyticsViewOrg, Permission::AnalyticsExport,
                Permission::FinanceViewRevenue, Permission::FinanceProcessRefund,
                Permission::FinanceExportReports,
                Permission::AuditLogView, Permission::AuditLogExport,
                Permission::DataExportRequest,
            ],

            self::SupportAgent => [
                Permission::OrganizationView,
                Permission::EventView,
                Permission::TicketView,
                Permission::TicketRefund, Permission::TicketResend, Permission::TicketTransfer,
                Permission::AttendeeView, Permission::AttendeeContact,
                Permission::OrderView, Permission::OrderResend,
            ],

            self::DoorStaff => [
                Permission::EventView,
                Permission::TicketView, Permission::TicketScan,
            ],

            self::Analyst => [
                Permission::OrganizationView,
                Permission::EventView,
                Permission::AnalyticsViewEvent, Permission::AnalyticsViewOrg, Permission::AnalyticsExport,
                Permission::FinanceViewRevenue,
            ],

            self::Member => [
                Permission::OrganizationView,
                Permission::EventView,
            ],
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
