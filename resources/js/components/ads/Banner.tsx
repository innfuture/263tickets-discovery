import AKBanner from '@atlaskit/banner';
import type { ReactElement, ReactNode } from 'react';

export type BannerAppearance = 'announcement' | 'warning' | 'error';

export interface BannerProps {
    appearance?: BannerAppearance;
    /** Single icon element. */
    icon?: ReactElement;
    children: ReactNode;
    testId?: string;
}

/**
 * Top-of-page system banner — maintenance windows, deprecation
 * notices, billing-overdue alerts. Consumers control visibility by
 * mounting/unmounting; no isOpen prop in the modern API.
 */
export function Banner({ appearance = 'announcement', icon, children, testId }: BannerProps) {
    return (
        <AKBanner appearance={appearance} icon={icon} testId={testId}>
            {children}
        </AKBanner>
    );
}
