import AKTagGroup from '@atlaskit/tag-group';
import type { ReactNode } from 'react';

export interface TagGroupProps {
    children: ReactNode;
    alignment?: 'start' | 'end';
    label?: string;
}

/**
 * Wraps a collection of Tag children with the standard horizontal
 * layout + wrap-on-overflow behaviour. @atlaskit/tag-group dropped
 * the testId prop in 12.x; pages that need test hooks should add a
 * wrapping div with a data-testid attribute.
 */
export function TagGroup({ children, alignment = 'start', label }: TagGroupProps) {
    return (
        <AKTagGroup alignment={alignment} label={label}>
            {children}
        </AKTagGroup>
    );
}
