import AKEmptyState from '@atlaskit/empty-state';
import type { ReactNode } from 'react';

export interface EmptyStateProps {
    heading: string;
    description?: ReactNode;
    primaryAction?: ReactNode;
    secondaryAction?: ReactNode;
    imageUrl?: string;
    imageHeight?: number;
    imageWidth?: number;
    isLoading?: boolean;
    maxImageHeight?: number;
    maxImageWidth?: number;
    size?: 'wide' | 'narrow';
    testId?: string;
}

/**
 * Empty list placeholder. Every list/table that can have zero items
 * should render an EmptyState — never collapse to bare whitespace.
 * The optional imageUrl is for a friendly illustration; the default
 * blank state still reads cleanly.
 */
export function EmptyState({ heading, primaryAction, secondaryAction, size, ...rest }: EmptyStateProps) {
    return (
        <AKEmptyState
            // @atlaskit/empty-state renames `heading` → `header` in
            // recent versions; we keep our prop name stable for callers.
            header={heading}
            width={size}
            primaryAction={primaryAction}
            secondaryAction={secondaryAction}
            {...rest}
        />
    );
}
