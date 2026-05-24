import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    CalendarDays,
    ChevronRight,
    CircleDollarSign,
    ClipboardList,
    FolderGit2,
    LayoutGrid,
    LifeBuoy,
    Megaphone,
    Receipt,
    ScanLine,
    Smartphone,
    ShieldCheck,
    Tag,
    Users,
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
 * Sidebar layout — four operational buckets (Plan / Sell / Engage /
 * Operate). Every row is permission-gated against the active org's
 * `auth.can` map; rows that gate to false simply disappear, and an
 * empty bucket folds away entirely. Marketing keeps its collapsible
 * sub-list because it has four sibling sub-pages.
 */
type SubNavItem = {
    title: string;
    href: string;
    perm?: string;
};

type GroupItem = {
    title: string;
    href: string;
    icon: typeof LayoutGrid;
    perm?: string;
};

export function AppSidebar() {
    const page = usePage<{
        auth?: { can?: Record<string, boolean> };
        currentOrganization?: { slug: string } | null;
    }>();
    const can = page.props.auth?.can ?? {};
    const orgSlug = page.props.currentOrganization?.slug;
    const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

    const base = orgSlug ? `/${orgSlug}` : '';
    const dashboardUrl = orgSlug ? `${base}/dashboard` : '/';
    const marketingBase = `${base}/marketing`;

    // ── Always-shown top nav ─────────────────────────────────────────
    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid },
    ];

    // ── Plan ──────────────────────────────────────────────────────────
    const planItems: GroupItem[] = [
        { title: 'Calendar', href: `${base}/calendar`, icon: CalendarDays, perm: 'event.view' },
        { title: 'Events', href: `${base}/events`, icon: ClipboardList, perm: 'event.view' },
    ];

    // ── Sell ──────────────────────────────────────────────────────────
    const sellItems: GroupItem[] = [
        { title: 'Orders', href: `${base}/orders`, icon: Receipt, perm: 'order.view' },
        { title: 'Discounts', href: `${base}/discounts`, icon: Tag, perm: 'ticket_category.manage-discounts' },
        { title: 'Reports', href: `${base}/reports`, icon: BarChart3, perm: 'analytics.view-org' },
        { title: 'Finance', href: `${base}/finance`, icon: CircleDollarSign, perm: 'finance.view-revenue' },
    ];

    // ── Engage ────────────────────────────────────────────────────────
    const engageItems: GroupItem[] = [
        { title: 'Attendees', href: `${base}/attendees`, icon: Users, perm: 'attendee.view' },
    ];

    // Marketing is a collapsible group — own bucket, same sub-pages.
    const marketingItems: SubNavItem[] = [
        { title: 'Email campaigns', href: `${marketingBase}/email-campaigns`, perm: 'marketing.email.manage' },
        { title: 'Social media', href: `${marketingBase}/social`, perm: 'marketing.social.publish' },
        { title: 'Paid Social Ads', href: `${marketingBase}/paid-ads`, perm: 'marketing.paid-ads.manage' },
        { title: 'Integrations', href: `${marketingBase}/integrations` },
    ];

    // ── Operate ───────────────────────────────────────────────────────
    const operateItems: GroupItem[] = [
        { title: 'Check-in', href: `${base}/check-in`, icon: ScanLine, perm: 'ticket.scan' },
        { title: 'Scanners', href: `${base}/scanners`, icon: Smartphone, perm: 'ticket.scan' },
        { title: 'Notifications', href: `${base}/notifications`, icon: Bell },
        { title: 'Audit log', href: '/settings/data/audit-log', icon: ShieldCheck, perm: 'audit_log.view' },
    ];

    const visibleMarketing = marketingItems.filter((item) => !item.perm || can[item.perm]);
    const showMarketing = visibleMarketing.length > 0;
    const visiblePlan = planItems.filter((i) => !i.perm || can[i.perm]);
    const visibleSell = sellItems.filter((i) => !i.perm || can[i.perm]);
    const visibleEngage = engageItems.filter((i) => !i.perm || can[i.perm]);
    const visibleOperate = operateItems.filter((i) => !i.perm || can[i.perm]);

    const marketingActive = useMemo(
        () => isCurrentOrParentUrl(marketingBase),
        [isCurrentOrParentUrl, marketingBase],
    );
    const [marketingOpen, setMarketingOpen] = useState<boolean>(marketingActive);

    const footerNavItems: NavItem[] = [
        { title: 'Help & support', href: '/settings/help', icon: LifeBuoy },
        { title: 'Repository', href: 'https://github.com/laravel/react-starter-kit', icon: FolderGit2 },
        { title: 'Documentation', href: 'https://laravel.com/docs/starter-kits#react', icon: BookOpen },
    ];

    const renderGroup = (label: string, items: GroupItem[]) => {
        if (items.length === 0) return null;
        return (
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel>{label}</SidebarGroupLabel>
                <SidebarMenu>
                    {items.map((item) => {
                        const Icon = item.icon;
                        return (
                            <SidebarMenuItem key={item.href}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isCurrentOrParentUrl(item.href)}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link href={item.href} prefetch>
                                        <Icon />
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        );
                    })}
                </SidebarMenu>
            </SidebarGroup>
        );
    };

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

                {renderGroup('Plan', visiblePlan)}
                {renderGroup('Sell', visibleSell)}

                {(visibleEngage.length > 0 || showMarketing) && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Engage</SidebarGroupLabel>
                        <SidebarMenu>
                            {visibleEngage.map((item) => {
                                const Icon = item.icon;
                                return (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={isCurrentOrParentUrl(item.href)}
                                            tooltip={{ children: item.title }}
                                        >
                                            <Link href={item.href} prefetch>
                                                <Icon />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}

                            {showMarketing && (
                                <Collapsible
                                    asChild
                                    open={marketingOpen}
                                    onOpenChange={setMarketingOpen}
                                    className="group/collapsible"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton
                                                tooltip={{ children: 'Marketing' }}
                                                isActive={marketingActive}
                                            >
                                                <Megaphone />
                                                <span>Marketing</span>
                                                <ChevronRight className="ml-auto size-4 transition-transform duration-150 group-data-[state=open]/collapsible:rotate-90" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <SidebarMenuSub>
                                                {visibleMarketing.map((item) => (
                                                    <SidebarMenuSubItem key={item.href}>
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={isCurrentUrl(item.href)}
                                                        >
                                                            <Link href={item.href} prefetch>
                                                                <span>{item.title}</span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                ))}
                                            </SidebarMenuSub>
                                        </CollapsibleContent>
                                    </SidebarMenuItem>
                                </Collapsible>
                            )}
                        </SidebarMenu>
                    </SidebarGroup>
                )}

                {renderGroup('Operate', visibleOperate)}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
