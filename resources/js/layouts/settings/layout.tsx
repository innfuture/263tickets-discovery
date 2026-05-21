import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useMemo, useState, type PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';

/**
 * Settings sidebar layout.
 *
 * The sidebar is organised into 9 named categories. Each item declares
 * a `perm` (Spatie permission string) — if the viewer holds that perm
 * in the active org's `auth.can` map shipped via HandleInertiaRequests,
 * the link renders; otherwise it is hidden. Items without a `perm` are
 * always visible (personal / account-level surfaces).
 *
 * Categories whose every item is hidden drop out entirely so the
 * sidebar doesn't carry empty section headers for non-privileged
 * viewers.
 */
type NavItem = {
    title: string;
    href: string;
    /** Permission required to see this link. Omit for always-visible. */
    perm?: string;
};

type NavSection = {
    title: string;
    items: NavItem[];
};

const sections: NavSection[] = [
    {
        title: 'Organization',
        items: [
            { title: 'Profile', href: '/settings/organization', perm: 'organization.view' },
            { title: 'Brand kit', href: '/settings/organization/brand', perm: 'organization.manage-brand' },
            { title: 'Domain', href: '/settings/organization/domain', perm: 'organization.manage-domain' },
            { title: 'Public page', href: '/settings/organization/public', perm: 'organization.update' },
        ],
    },
    {
        title: 'Account',
        items: [
            { title: 'Profile', href: '/settings/profile' },
            { title: 'Notifications', href: '/settings/notifications' },
            { title: 'Security', href: '/settings/security' },
            { title: 'API tokens', href: '/settings/api-tokens' },
            { title: 'Sessions', href: '/settings/sessions' },
        ],
    },
    {
        title: 'Operations',
        items: [
            { title: 'Check-in & scanning', href: '/settings/operations/check-in', perm: 'ticket.scan' },
            { title: 'Ticket templates', href: '/settings/operations/ticket-templates', perm: 'ticket_category.update' },
            { title: 'Email sender identity', href: '/settings/operations/email-identity', perm: 'organization.update' },
            { title: 'Webhooks', href: '/settings/operations/webhooks', perm: 'webhook.manage' },
        ],
    },
    {
        title: 'Plan & Billing',
        items: [
            { title: 'Plan & usage', href: '/settings/billing/plan', perm: 'organization.manage-billing' },
            { title: 'Payment methods', href: '/settings/billing/methods', perm: 'organization.manage-billing' },
            { title: 'Invoices', href: '/settings/billing/invoices', perm: 'organization.manage-billing' },
            { title: 'Payouts', href: '/settings/billing/payouts', perm: 'finance.view-revenue' },
            { title: 'Taxes', href: '/settings/billing/taxes', perm: 'finance.export-reports' },
            { title: 'Refund policies', href: '/settings/billing/refunds', perm: 'finance.process-refund' },
        ],
    },
    {
        title: 'Team',
        items: [
            { title: 'Members', href: '/settings/team/members', perm: 'organization_member.view' },
            { title: 'Roles', href: '/settings/roles', perm: 'role.view' },
            { title: 'Teams', href: '/settings/teams', perm: 'team.view' },
            { title: 'Invitations', href: '/settings/team/invitations', perm: 'organization_member.invite' },
        ],
    },
    {
        title: 'Integrations',
        items: [
            { title: 'All integrations', href: '/settings/integrations', perm: 'integration.manage' },
            { title: 'Connected apps', href: '/settings/integrations/connected', perm: 'integration.manage' },
            { title: 'OAuth apps', href: '/settings/integrations/oauth', perm: 'oauth_app.manage' },
        ],
    },
    {
        title: 'Data & Compliance',
        items: [
            { title: 'Audit log', href: '/settings/data/audit-log', perm: 'audit_log.view' },
            { title: 'Data exports', href: '/settings/data/exports', perm: 'data.export-request' },
            { title: 'GDPR requests', href: '/settings/data/gdpr', perm: 'data.gdpr-process' },
            { title: 'Retention', href: '/settings/data/retention', perm: 'organization.manage-billing' },
        ],
    },
    {
        title: 'Developer',
        items: [
            { title: 'API keys', href: '/settings/developer/api-keys', perm: 'api.manage-keys' },
            { title: 'Webhooks', href: '/settings/developer/webhooks', perm: 'webhook.manage' },
            { title: 'API logs', href: '/settings/developer/logs', perm: 'api.manage-keys' },
        ],
    },
    {
        title: 'Appearance',
        items: [
            { title: 'Theme', href: '/settings/appearance' },
            { title: 'Locale & timezone', href: '/settings/appearance/locale', perm: 'organization.update' },
            { title: 'Date formats', href: '/settings/appearance/dates', perm: 'organization.update' },
        ],
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const page = usePage<{ auth?: { can?: Record<string, boolean> } }>();
    const can = page.props.auth?.can ?? {};

    const visibleSections: NavSection[] = sections
        .map((s) => ({
            ...s,
            items: s.items.filter((i) => !i.perm || can[i.perm]),
        }))
        .filter((s) => s.items.length > 0);

    // Which section is the user currently inside? We default-open that
    // one so they always see the surrounding context for the page they
    // landed on. Everything else starts collapsed to keep the rail
    // short.
    const initialActive = useMemo(() => {
        for (const s of visibleSections) {
            if (s.items.some((i) => isCurrentOrParentUrl(i.href))) {
                return s.title;
            }
        }
        return visibleSections[0]?.title ?? null;
    }, [visibleSections, isCurrentOrParentUrl]);

    // Single-open accordion: at most one section expanded at a time.
    // Opening a new one collapses whichever was open; clicking the
    // already-open section's trigger toggles it closed (null state),
    // so users can have everything collapsed if they want.
    const [openSection, setOpenSection] = useState<string | null>(
        initialActive,
    );

    const toggleSection = (title: string, next: boolean) => {
        setOpenSection(next ? title : null);
    };

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your organization, teams, profile, and account preferences"
            />

            <div className="mt-8 flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-60">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {visibleSections.map((section) => {
                            const open = openSection === section.title;
                            const containsActive = section.items.some((i) =>
                                isCurrentOrParentUrl(i.href),
                            );

                            return (
                                <Collapsible
                                    key={section.title}
                                    open={open}
                                    onOpenChange={(next) =>
                                        toggleSection(section.title, next)
                                    }
                                >
                                    <CollapsibleTrigger
                                        className={cn(
                                            'group flex w-full items-center justify-between gap-2 rounded-md px-3 py-1.5 text-left text-[11px] font-semibold tracking-wider uppercase transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1',
                                            containsActive
                                                ? 'text-foreground'
                                                : 'text-muted-foreground/80 hover:text-foreground',
                                        )}
                                    >
                                        <span>{section.title}</span>
                                        <ChevronRight
                                            className={cn(
                                                'size-3.5 shrink-0 transition-transform duration-150',
                                                open && 'rotate-90',
                                            )}
                                            aria-hidden
                                        />
                                    </CollapsibleTrigger>
                                    <CollapsibleContent className="overflow-hidden data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0">
                                        <div className="mt-1 mb-2 space-y-0.5 pl-1">
                                            {section.items.map(
                                                (item, index) => (
                                                    <Button
                                                        key={`${toUrl(item.href)}-${index}`}
                                                        size="sm"
                                                        variant="ghost"
                                                        asChild
                                                        className={cn(
                                                            'w-full justify-start',
                                                            {
                                                                'bg-muted':
                                                                    isCurrentOrParentUrl(
                                                                        item.href,
                                                                    ),
                                                            },
                                                        )}
                                                    >
                                                        <Link
                                                            href={item.href}
                                                            prefetch
                                                        >
                                                            {item.title}
                                                        </Link>
                                                    </Button>
                                                ),
                                            )}
                                        </div>
                                    </CollapsibleContent>
                                </Collapsible>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-4xl">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
