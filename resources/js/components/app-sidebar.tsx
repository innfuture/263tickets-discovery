import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    ChevronRight,
    FolderGit2,
    LayoutGrid,
    Megaphone,
    Receipt,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/**
 * Sub-item used inside a collapsible menu group (e.g. Marketing).
 * Each row declares a permission string — if the viewer doesn't hold
 * that permission in the active org's `auth.can` map, the row is
 * hidden. If every sub-row hides, the whole group folds away.
 */
type SubNavItem = {
    title: string;
    href: string;
    perm?: string;
};

export function AppSidebar() {
    const page = usePage<{ auth?: { can?: Record<string, boolean> } }>();
    const can = page.props.auth?.can ?? {};
    const orgSlug = page.props.currentOrganization?.slug;
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

    const dashboardUrl = orgSlug ? `/${orgSlug}/dashboard` : '/';
    const eventsUrl = orgSlug ? `/${orgSlug}/events` : '/';
    const ordersUrl = orgSlug ? `/${orgSlug}/orders` : '/';
    const marketingBase = orgSlug ? `/${orgSlug}/marketing` : '/';

    // ── Always-shown top nav ─────────────────────────────────────────
    // "Organization" deliberately omitted — it lives under the user
    // dropdown → Settings → Organization.
    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid },
        { title: 'My Events', href: eventsUrl, icon: CalendarDays },
    ];

    // Orders is a single flat link, permission-gated.
    const showOrders = can['order.view'] ?? false;

    // Marketing is a collapsible group. The order matches the spec:
    // Email campaigns → Social → Paid Social Ads → Integrations.
    const marketingItems: SubNavItem[] = [
        {
            title: 'Email campaigns',
            href: `${marketingBase}/email-campaigns`,
            perm: 'marketing.email.manage',
        },
        {
            title: 'Social media',
            href: `${marketingBase}/social`,
            perm: 'marketing.social.publish',
        },
        {
            title: 'Paid Social Ads',
            href: `${marketingBase}/paid-ads`,
            perm: 'marketing.paid-ads.manage',
        },
        {
            title: 'Integrations',
            href: `${marketingBase}/integrations`,
            // Surfaced when the viewer can manage anything marketing —
            // mirrors the OR in MarketingController::index/integrations.
        },
    ];

    const visibleMarketing = marketingItems.filter(
        (item) => !item.perm || can[item.perm],
    );
    const showMarketing = visibleMarketing.length > 0;

    // Auto-expand the Marketing group if the user is currently on any
    // marketing sub-page; otherwise start collapsed to keep the nav
    // tight.
    const marketingActive = useMemo(
        () => isCurrentOrParentUrl(marketingBase),
        [isCurrentOrParentUrl, marketingBase],
    );
    const [marketingOpen, setMarketingOpen] = useState<boolean>(marketingActive);

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {/* Orders + Marketing live in their own group so the
                    "Platform" header from NavMain doesn't end up
                    swallowing operational surfaces. */}
                {showOrders || showMarketing ? (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Manage</SidebarGroupLabel>
                        <SidebarMenu>
                            {showOrders ? (
                                <SidebarMenuItem>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentOrParentUrl(
                                            ordersUrl,
                                        )}
                                        tooltip={{ children: 'Orders' }}
                                    >
                                        <Link href={ordersUrl} prefetch>
                                            <Receipt />
                                            <span>Orders</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ) : null}

                            {showMarketing ? (
                                <Collapsible
                                    asChild
                                    open={marketingOpen}
                                    onOpenChange={setMarketingOpen}
                                    className="group/collapsible"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton
                                                tooltip={{
                                                    children: 'Marketing',
                                                }}
                                                isActive={marketingActive}
                                            >
                                                <Megaphone />
                                                <span>Marketing</span>
                                                <ChevronRight className="ml-auto size-4 transition-transform duration-150 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {visibleMarketing.map(
                                                    (item) => (
                                                        <SidebarMenuSubItem
                                                            key={item.href}
                                                        >
                                                            <SidebarMenuSubButton
                                                                asChild
                                                                isActive={isCurrentUrl(
                                                                    item.href,
                                                                )}
                                                            >
                                                                <Link
                                                                    href={
                                                                        item.href
                                                                    }
                                                                    prefetch
                                                                >
                                                                    <span>
                                                                        {item.title}
                                                                    </span>
                                                                </Link>
                                                            </SidebarMenuSubButton>
                                                        </SidebarMenuSubItem>
                                                    ),
                                                )}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            ) : null}
                        </SidebarMenu>
                    </SidebarGroup>
                ) : null}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
