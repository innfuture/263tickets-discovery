import AKBadge from '@atlaskit/badge';

export type BadgeAppearance = 'default' | 'added' | 'important' | 'primary' | 'primaryInverted' | 'removed';

export interface BadgeProps {
    children: number;
    max?: number;
    appearance?: BadgeAppearance;
    testId?: string;
}

/**
 * Numeric counter — nav badge, notification count, unread indicator.
 * For state labels use StatusBadge (Lozenge), for tags use Tag.
 */
export function Badge({ children, max, appearance = 'default', testId }: BadgeProps) {
    return (
        <AKBadge appearance={appearance} max={max} testId={testId}>
            {children}
        </AKBadge>
    );
}
