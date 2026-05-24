import type { CSSProperties } from 'react';

export interface SeparatorProps {
    orientation?: 'horizontal' | 'vertical';
    /** Token-driven spacing around the separator. */
    spacing?: 'space.0' | 'space.100' | 'space.200' | 'space.300';
    testId?: string;
}

/**
 * Token-driven divider. Use sparingly — most layouts get enough
 * separation from Stack space + Card surface contrast.
 */
export function Separator({ orientation = 'horizontal', spacing = 'space.100', testId }: SeparatorProps) {
    const isVertical = orientation === 'vertical';

    const style: CSSProperties = isVertical
        ? {
              width: 'var(--ds-border-width)',
              backgroundColor: 'var(--ds-border)',
              alignSelf: 'stretch',
              marginInline: spacing === 'space.0' ? 0 : `var(--ds-${spacing.replace('.', '-')})`,
          }
        : {
              height: 'var(--ds-border-width)',
              width: '100%',
              backgroundColor: 'var(--ds-border)',
              marginBlock: spacing === 'space.0' ? 0 : `var(--ds-${spacing.replace('.', '-')})`,
          };

    return <div role="separator" aria-orientation={orientation} data-testid={testId} style={style} />;
}
