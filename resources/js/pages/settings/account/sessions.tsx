import { Head, router } from '@inertiajs/react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import MonitorIcon from '@atlaskit/icon/core/devices';
import { Button, Card, CardContent, CardHeader, CardTitle, PageHeading, useConfirm } from '@ads';
import Lozenge from '@atlaskit/lozenge';

type Breadcrumb = { title: string; href: string };
type Session = {
    id: string;
    is_current: boolean;
    device_label: string | null;
    ip_address: string | null;
    location: string | null;
    user_agent: string | null;
    started_at: string | null;
    last_active_at: string | null;
};

export default function SessionsPage({ sessions }: { sessions: Session[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();

    return (
        <>
            <Head title="Sessions — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Active sessions"
                        description="Browsers and devices currently signed in to your account."
                    />
                    <Card>
                        <CardHeader
                            actions={
                                <Button
                                    variant="secondary"
                                    size="compact"
                                    onClick={async () => {
                                        if (
                                            await confirm({
                                                title: 'Revoke all other sessions?',
                                                description: "You'll stay signed in on this one.",
                                            })
                                        )
                                            router.delete('/settings/sessions');
                                    }}
                                >
                                    Revoke all others
                                </Button>
                            }
                        >
                            <CardTitle size="small">Sessions ({sessions.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {sessions.length === 0 ? (
                                <Text color="color.text.subtle">No active sessions.</Text>
                            ) : (
                                <Stack space="space.100">
                                    {sessions.map((s) => (
                                        <Box
                                            key={s.id}
                                            padding="space.150"
                                            style={{
                                                borderRadius: 'var(--ds-border-radius-200)',
                                                border: '1px solid var(--ds-border)',
                                            }}
                                        >
                                            <Inline spread="space-between" alignBlock="start" space="space.100">
                                                <Inline space="space.100" alignBlock="start">
                                                    <Box paddingBlockStart="space.025">
                                                        <MonitorIcon label="" color="var(--ds-icon-subtle)" />
                                                    </Box>
                                                    <Stack space="space.025">
                                                        <Inline space="space.100" alignBlock="center">
                                                            <Text weight="semibold">{s.device_label || 'Unknown device'}</Text>
                                                            {s.is_current ? (
                                                                <Lozenge appearance="success" isBold>
                                                                    this device
                                                                </Lozenge>
                                                            ) : null}
                                                        </Inline>
                                                        <Text size="small" color="color.text.subtle">
                                                            {s.ip_address || '—'} · {s.location || 'Location unavailable'}
                                                        </Text>
                                                        <Text size="small" color="color.text.subtle">
                                                            Last active{' '}
                                                            {s.last_active_at
                                                                ? new Date(s.last_active_at).toLocaleString()
                                                                : '—'}
                                                        </Text>
                                                    </Stack>
                                                </Inline>
                                                {!s.is_current ? (
                                                    <Button
                                                        variant="subtle"
                                                        size="compact"
                                                        onClick={async () => {
                                                            if (
                                                                await confirm({
                                                                    title: 'Revoke session?',
                                                                    tone: 'destructive',
                                                                })
                                                            )
                                                                router.delete(
                                                                    `/settings/sessions/${encodeURIComponent(s.id)}`,
                                                                );
                                                        }}
                                                    >
                                                        Revoke
                                                    </Button>
                                                ) : null}
                                            </Inline>
                                        </Box>
                                    ))}
                                </Stack>
                            )}
                        </CardContent>
                    </Card>
                </Stack>
            </Box>
        </>
    );
}

SessionsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
