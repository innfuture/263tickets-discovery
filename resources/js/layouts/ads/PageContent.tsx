import { Box, Stack } from '@atlaskit/primitives';
import PageHeader from '@atlaskit/page-header';
import type { ReactElement, ReactNode } from 'react';
import { Breadcrumbs, type BreadcrumbItem } from '@ads';

export interface PageContentProps {
    title: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    /** Right-aligned page actions. Pass a single element (use Inline for multiple). */
    actions?: ReactElement;
    bottomBar?: ReactElement;
    children: ReactNode;
    /** Constrain inner content width — defaults to no cap. */
    maxWidth?: number | string;
    testId?: string;
}

/**
 * Page-level wrapper that puts a consistent header (breadcrumbs +
 * title + CTAs) above the page body. All pages inside AppShell render
 * their content through this so vertical rhythm stays consistent
 * across the app.
 */
export function PageContent({ title, breadcrumbs, actions, bottomBar, children, maxWidth, testId }: PageContentProps) {
    return (
        <Box
            padding="space.400"
            style={{
                minHeight: '100vh',
                backgroundColor: 'var(--ds-background-default)',
            }}
            testId={testId}
        >
            <Stack space="space.300">
                <PageHeader
                    breadcrumbs={
                        breadcrumbs && breadcrumbs.length > 0 ? <Breadcrumbs items={breadcrumbs} /> : undefined
                    }
                    actions={actions}
                    bottomBar={bottomBar}
                >
                    {title}
                </PageHeader>

                <Box style={maxWidth ? { maxWidth, width: '100%' } : undefined}>{children}</Box>
            </Stack>
        </Box>
    );
}
