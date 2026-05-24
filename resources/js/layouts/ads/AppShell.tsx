import { Content, LeftSidebarWithoutResize, Main, PageLayout, TopNavigation } from '@atlaskit/page-layout';
import type { ReactNode } from 'react';
import { SideNav } from '@/components/ads-navigation/SideNav';
import { TopNav } from '@/components/ads-navigation/TopNav';
import { Breadcrumbs, type BreadcrumbItem } from '@ads';

/**
 * Legacy breadcrumb shape used by existing pages — `{ title, href }`
 * with `title` instead of ADS's `text`. AppShell accepts both shapes
 * so pages can opt in to either without rewriting metadata yet.
 */
export interface LegacyBreadcrumb {
    title: string;
    href?: string;
}

export interface AppShellProps {
    children: ReactNode;
    /**
     * Optional breadcrumbs (legacy `{title, href}` or ADS `{text, href}`).
     * When present, they render in a thin bar above the main content so
     * pages that previously set `Page.layout = {breadcrumbs}` keep the
     * same UX without modification.
     */
    breadcrumbs?: (BreadcrumbItem | LegacyBreadcrumb)[];
}

/**
 * Jira-shape app shell: fixed 56px top bar, fixed 240px left sidebar,
 * scrollable main content. Accepts the legacy `breadcrumbs` prop so
 * `Page.layout = { breadcrumbs: [...] }` continues to work.
 *
 * For new pages, use `Page.layout = (page) => <AppShell>{page}</AppShell>`
 * + wrap the body in <PageContent title="..." breadcrumbs={...}> for
 * full title/header treatment.
 */
export function AppShell({ children, breadcrumbs }: AppShellProps) {
    const normalisedCrumbs: BreadcrumbItem[] | undefined = breadcrumbs?.map((c) =>
        'text' in c ? c : { text: c.title, href: c.href },
    );

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
                        {normalisedCrumbs && normalisedCrumbs.length > 0 ? (
                            <div
                                style={{
                                    paddingInline: 'var(--ds-space-400)',
                                    paddingTop: 'var(--ds-space-200)',
                                }}
                            >
                                <Breadcrumbs items={normalisedCrumbs} />
                            </div>
                        ) : null}
                        {children}
                    </Main>
                </Content>
            </PageLayout>
        </div>
    );
}

export default AppShell;
