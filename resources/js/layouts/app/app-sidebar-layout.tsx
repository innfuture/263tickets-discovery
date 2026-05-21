import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    // Bridges server-side `Inertia::flash('toast', ...)` payloads into
    // sonner notifications — every controller already emits these
    // after writes, but they had nowhere to land until now.
    useFlashToast();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            {/*
              Use `overflow-x-clip` (not `hidden`) so we still prevent horizontal
              overflow without turning AppContent into a scroll container.
              `overflow: hidden` makes the element a scroll container, which
              hijacks `position: sticky` descendants (they bind to AppContent's
              non-existent scroll instead of the document's), breaking sticky
              sidebars like the one on the Event Edit page.
            */}
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
