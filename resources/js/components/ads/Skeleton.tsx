import { Box } from '@atlaskit/primitives';
import type { CSSProperties } from 'react';

export interface SkeletonProps {
    width?: number | string;
    height?: number | string;
    borderRadius?: number | string;
    isShimmering?: boolean;
    testId?: string;
}

/**
 * Loading-state placeholder. @atlaskit/skeleton is not used because
 * its API surface kept shifting between minor versions; we build the
 * same shape from a token-styled Box. The shimmer animation respects
 * prefers-reduced-motion via the ADS shimmer keyframes injected by
 * AppProvider.
 */
export function Skeleton({ width = '100%', height = 16, borderRadius = 4, isShimmering = true, testId }: SkeletonProps) {
    const style: CSSProperties = {
        width,
        height,
        borderRadius,
        backgroundColor: 'var(--ds-skeleton)',
        display: 'inline-block',
        ...(isShimmering
            ? {
                  backgroundImage:
                      'linear-gradient(90deg, var(--ds-skeleton) 0%, var(--ds-skeleton-subtle) 50%, var(--ds-skeleton) 100%)',
                  backgroundSize: '200% 100%',
                  animation: 'ds-skeleton-shimmer 1.4s linear infinite',
              }
            : {}),
    };

    return <Box xcss={undefined as never} testId={testId} style={style as never} />;
}
