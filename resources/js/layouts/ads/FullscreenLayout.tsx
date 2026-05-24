import type { ReactNode } from 'react';

interface FullscreenLayoutProps {
    children: ReactNode;
}

/**
 * Chromeless shell — no top bar, no side nav. Use for full-bleed
 * flows like ticket purchase (where the buyer should focus on the
 * payment funnel) or the on-stand QR-scan view (gate operator UX).
 */
export function FullscreenLayout({ children }: FullscreenLayoutProps) {
    return (
        <div className="ads-page" data-ads-surface style={{ minHeight: '100vh' }}>
            {children}
        </div>
    );
}
