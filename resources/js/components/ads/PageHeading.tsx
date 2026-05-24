import { Stack } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';
import type { ReactNode } from 'react';

export interface PageHeadingProps {
    title: ReactNode;
    description?: ReactNode;
    /** "default" = page-level h1; "small" = section heading inside a page. */
    variant?: 'default' | 'small';
    testId?: string;
}

/**
 * Drop-in for the legacy `@/components/heading` component. Pages
 * mid-migration can switch the import without changing the JSX.
 *
 * `default` variant renders as h1 with large display type; `small`
 * variant renders as h3 with medium type — the same hierarchy the
 * legacy component provides.
 */
export function PageHeading({ title, description, variant = 'default', testId }: PageHeadingProps) {
    return (
        <Stack space={variant === 'small' ? 'space.025' : 'space.075'} testId={testId}>
            {variant === 'small' ? (
                <Heading size="medium" as="h2">
                    {title}
                </Heading>
            ) : (
                <Heading size="xlarge" as="h1">
                    {title}
                </Heading>
            )}
            {description ? (
                <div style={{ color: 'var(--ds-text-subtle)', fontSize: 'var(--ds-font-size-100)' }}>{description}</div>
            ) : null}
        </Stack>
    );
}
