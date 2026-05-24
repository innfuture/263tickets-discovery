import AKBreadcrumbs, { BreadcrumbsItem } from '@atlaskit/breadcrumbs';
import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';

export interface BreadcrumbItem {
    text: string;
    href?: string;
}

export interface BreadcrumbsProps {
    items: BreadcrumbItem[];
    maxItems?: number;
    testId?: string;
}

/**
 * Breadcrumbs intercept the link click to dispatch through Inertia's
 * router instead of triggering a full page load. Items without an
 * href render as plain text (the current page).
 */
export function Breadcrumbs({ items, maxItems = 8, testId }: BreadcrumbsProps) {
    return (
        <AKBreadcrumbs maxItems={maxItems} testId={testId}>
            {items.map((item, i) => (
                <BreadcrumbsItem
                    key={`${item.text}-${i}`}
                    text={item.text}
                    href={item.href ?? '#'}
                    onClick={
                        item.href
                            ? (e: MouseEvent) => {
                                  e.preventDefault();
                                  router.visit(item.href!);
                              }
                            : undefined
                    }
                />
            ))}
        </AKBreadcrumbs>
    );
}
