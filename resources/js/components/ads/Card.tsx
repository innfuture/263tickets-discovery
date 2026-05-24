import { Box, Inline, Stack } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';
import type { CSSProperties, ReactNode } from 'react';

/**
 * Card primitive — wraps a Box in elevation.surface with the standard
 * Jira card padding + border radius. Exposes a CardHeader / CardTitle
 * / CardDescription / CardContent / CardFooter family so existing
 * shadcn-shaped call sites can swap imports with minimal edits.
 */

export interface CardProps {
    children: ReactNode;
    /** Override default elevation.surface — use elevation.raised for floating cards. */
    surface?: 'surface' | 'raised' | 'overlay' | 'sunken';
    /** Override default space.300 padding. Use 0 for fully custom content. */
    padding?: 'space.0' | 'space.100' | 'space.200' | 'space.300' | 'space.400';
    style?: CSSProperties;
    testId?: string;
}

const surfaceColor = {
    surface: 'var(--ds-elevation-surface)',
    raised: 'var(--ds-elevation-surface-raised)',
    overlay: 'var(--ds-elevation-surface-overlay)',
    sunken: 'var(--ds-elevation-surface-sunken)',
} as const;

const surfaceShadow = {
    surface: undefined,
    raised: 'var(--ds-shadow-raised)',
    overlay: 'var(--ds-shadow-overlay)',
    sunken: undefined,
} as const;

export function Card({ children, surface = 'surface', padding = 'space.300', style, testId }: CardProps) {
    return (
        <Box
            padding={padding}
            style={{
                backgroundColor: surfaceColor[surface],
                borderRadius: 'var(--ds-border-radius-200)',
                boxShadow: surfaceShadow[surface],
                border: surface === 'surface' ? '1px solid var(--ds-border)' : undefined,
                ...style,
            }}
            testId={testId}
        >
            {children}
        </Box>
    );
}

export interface CardHeaderProps {
    children: ReactNode;
    actions?: ReactNode;
    testId?: string;
}

export function CardHeader({ children, actions, testId }: CardHeaderProps) {
    if (actions) {
        return (
            <Inline spread="space-between" alignBlock="start" testId={testId}>
                <Stack space="space.050">{children}</Stack>
                <div>{actions}</div>
            </Inline>
        );
    }
    return (
        <Stack space="space.050" testId={testId}>
            {children}
        </Stack>
    );
}

export interface CardTitleProps {
    children: ReactNode;
    size?: 'small' | 'medium' | 'large';
    as?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
    testId?: string;
}

export function CardTitle({ children, size = 'medium', as = 'h3', testId }: CardTitleProps) {
    return (
        <Heading size={size} as={as} testId={testId}>
            {children}
        </Heading>
    );
}

export interface CardDescriptionProps {
    children: ReactNode;
    testId?: string;
}

export function CardDescription({ children, testId }: CardDescriptionProps) {
    return (
        <div style={{ color: 'var(--ds-text-subtle)', fontSize: 'var(--ds-font-size-100)' }} data-testid={testId}>
            {children}
        </div>
    );
}

export interface CardContentProps {
    children: ReactNode;
    testId?: string;
}

export function CardContent({ children, testId }: CardContentProps) {
    return (
        <Stack space="space.200" testId={testId}>
            {children}
        </Stack>
    );
}

export interface CardFooterProps {
    children: ReactNode;
    align?: 'start' | 'end' | 'space-between';
    testId?: string;
}

export function CardFooter({ children, align = 'end', testId }: CardFooterProps) {
    return (
        <Inline
            space="space.100"
            spread={align === 'space-between' ? 'space-between' : undefined}
            alignInline={align === 'start' ? 'start' : align === 'end' ? 'end' : undefined}
            testId={testId}
        >
            {children}
        </Inline>
    );
}
