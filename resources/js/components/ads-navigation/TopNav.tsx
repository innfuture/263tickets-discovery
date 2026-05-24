import {
    AtlassianNavigation,
    Create,
    Help,
    PrimaryButton,
    ProductHome,
    Profile,
    Search,
    Settings,
} from '@atlaskit/atlassian-navigation';
import { router, usePage } from '@inertiajs/react';
import { ThemeToggle } from '@/components/ads/ThemeToggle';
import { AtlassianLogo } from '@atlaskit/logo';

interface PageProps {
    auth?: {
        user?: {
            name?: string;
            email?: string;
            avatar?: string;
        };
    };
    [key: string]: unknown;
}

/**
 * Top navigation bar — product home + primary nav links + global
 * actions (search, create, help, settings, profile). All link clicks
 * go through Inertia's router so navigation stays SPA-fast.
 */
export function TopNav() {
    const { props } = usePage<PageProps>();
    const user = props.auth?.user;

    return (
        <AtlassianNavigation
            label="263Tickets"
            renderProductHome={() => (
                <ProductHome
                    onClick={() => router.visit('/dashboard')}
                    siteTitle="263Tickets"
                    logoMaxWidth={140}
                    icon={AtlassianLogo}
                    logo={AtlassianLogo}
                />
            )}
            renderCreate={() => (
                <Create
                    onClick={() => router.visit('/events/create')}
                    buttonTooltip="Create event"
                    iconButtonTooltip="Create"
                    text="Create"
                />
            )}
            renderSearch={() => (
                <Search
                    onClick={() => {
                        /* TODO: open search drawer */
                    }}
                    placeholder="Search events, tickets…"
                    tooltip="Search"
                    label="Search"
                />
            )}
            renderSettings={() => (
                <Settings onClick={() => router.visit('/settings')} tooltip="Settings" />
            )}
            renderHelp={() => (
                <Help
                    onClick={() => {
                        /* TODO: open help drawer */
                    }}
                    tooltip="Help"
                />
            )}
            renderProfile={() => (
                <Profile
                    icon={user?.avatar ? <img src={user.avatar} alt={user?.name ?? 'Profile'} /> : undefined}
                    tooltip={user?.name ?? 'Profile'}
                    onClick={() => router.visit('/settings/profile')}
                />
            )}
            primaryItems={[
                <PrimaryButton key="events" onClick={() => router.visit('/events')}>
                    Events
                </PrimaryButton>,
                <PrimaryButton key="tickets" onClick={() => router.visit('/tickets')}>
                    Tickets
                </PrimaryButton>,
                <PrimaryButton key="reports" onClick={() => router.visit('/reports')}>
                    Reports
                </PrimaryButton>,
                <PrimaryButton key="theme">
                    <ThemeToggle />
                </PrimaryButton>,
            ]}
        />
    );
}
