import { Content, LeftSidebarWithoutResize, Main, PageLayout, TopNavigation } from '@atlaskit/page-layout';
import type { ReactNode } from 'react';
import { SideNav } from '@/components/ads-navigation/SideNav';
import { TopNav } from '@/components/ads-navigation/TopNav';

/**
 * Jira-shape app shell: fixed 56px top bar, fixed 240px left sidebar,
 * scrollable main content. Pages opt into this by assigning
 * `Page.layout = AppShell` in their Inertia component.
 *
 * Coexists with the legacy <AppLayout>; pages don't have to migrate
 * all at once. Switch a page by setting its layout to AppShell + use
 * components from @ads instead of @/components/ui.
 */
export function AppShell({ children }: { children: ReactNode }) {
    return (
        <div className="ads-page" data-ads-surface>
            <PageLayout>
                <TopNavigation isFixed height={56} skipLinkTitle="Skip to main content" id="topNav" testId="ads-top-nav">
                    <TopNav />
                </TopNavigation>

                <Content testId="ads-page-content">
                    <LeftSidebarWithoutResize
                        isFixed
                        width={240}
                        id="leftSidebar"
                        testId="ads-left-sidebar"
                    >
                        <SideNav />
                    </LeftSidebarWithoutResize>

                    <Main id="main-content" skipLinkTitle="Main content" testId="ads-main">
                        {children}
                    </Main>
                </Content>
            </PageLayout>
        </div>
    );
}

export default AppShell;
