import { LinkItem, NavigationContent, NavigationFooter, Section, SideNavigation } from '@atlaskit/side-navigation';
import { router, usePage } from '@inertiajs/react';
import DashboardIcon from '@atlaskit/icon/core/dashboard';
import CalendarIcon from '@atlaskit/icon/core/calendar';
import PeopleGroupIcon from '@atlaskit/icon/core/people-group';
import ChartBarIcon from '@atlaskit/icon/core/chart-bar';
import SettingsIcon from '@atlaskit/icon/core/settings';
import ListBulletedIcon from '@atlaskit/icon/core/list-bulleted';
import MarketplaceIcon from '@atlaskit/icon/core/marketplace';
import type { ReactNode, SyntheticEvent } from 'react';

interface NavLeaf {
    id: string;
    label: string;
    href: string;
    icon: ReactNode;
}

const primary: NavLeaf[] = [
    { id: 'dashboard', label: 'Dashboard', href: '/dashboard', icon: <DashboardIcon label="" /> },
    { id: 'events', label: 'Events', href: '/events', icon: <CalendarIcon label="" /> },
    { id: 'tickets', label: 'Tickets', href: '/tickets', icon: <ListBulletedIcon label="" /> },
    { id: 'attendees', label: 'Attendees', href: '/attendees', icon: <PeopleGroupIcon label="" /> },
    { id: 'reports', label: 'Reports', href: '/reports', icon: <ChartBarIcon label="" /> },
    { id: 'marketplace', label: 'Marketplace', href: '/marketplace', icon: <MarketplaceIcon label="" /> },
];

const footer: NavLeaf[] = [
    { id: 'settings', label: 'Settings', href: '/settings', icon: <SettingsIcon label="" /> },
];

function intercept(href: string) {
    return (e: SyntheticEvent) => {
        e.preventDefault();
        router.visit(href);
    };
}

export function SideNav() {
    const { url } = usePage();

    const renderLeaf = (item: NavLeaf) => (
        <LinkItem
            key={item.id}
            href={item.href}
            iconBefore={item.icon}
            isSelected={url === item.href || url.startsWith(`${item.href}/`)}
            onClick={intercept(item.href)}
        >
            {item.label}
        </LinkItem>
    );

    return (
        <SideNavigation label="Project navigation" testId="ads-side-nav">
            <NavigationContent>
                <Section>{primary.map(renderLeaf)}</Section>
            </NavigationContent>

            <NavigationFooter>
                <Section>{footer.map(renderLeaf)}</Section>
            </NavigationFooter>
        </SideNavigation>
    );
}
