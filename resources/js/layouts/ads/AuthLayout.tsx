import { Box, Stack } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';
import type { ReactNode } from 'react';

interface AuthLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

/**
 * Centred-card auth scaffold for login / register / forgot-password.
 * No top nav, no side nav — a quiet shell so the form is the focus.
 */
export function AuthLayout({ title, description, children }: AuthLayoutProps) {
    return (
        <div className="ads-page" data-ads-surface>
            <Box
                style={{
                    minHeight: '100vh',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    padding: 'var(--ds-space-400)',
                    backgroundColor: 'var(--ds-background-sunken)',
                }}
            >
                <Box
                    backgroundColor="elevation.surface"
                    padding="space.400"
                    style={{
                        width: '100%',
                        maxWidth: 420,
                        borderRadius: 'var(--ds-border-radius-200)',
                        boxShadow: 'var(--ds-shadow-overlay)',
                    }}
                >
                    <Stack space="space.300">
                        <Stack space="space.100">
                            <Heading size="large" as="h1">
                                {title}
                            </Heading>
                            {description ? (
                                <Box style={{ color: 'var(--ds-text-subtle)' }}>{description}</Box>
                            ) : null}
                        </Stack>
                        {children}
                    </Stack>
                </Box>
            </Box>
        </div>
    );
}
