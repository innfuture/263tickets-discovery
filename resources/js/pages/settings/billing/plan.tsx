import { Head, router } from '@inertiajs/react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import CheckIcon from '@atlaskit/icon/core/check-mark';
import Lozenge from '@atlaskit/lozenge';
import {
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    PageHeading,
    ProgressBar,
    useConfirm,
} from '@ads';

type Breadcrumb = { title: string; href: string };
type Plan = {
    tier: string;
    name: string;
    events_per_month: number | null;
    tickets_per_month: number | null;
    storage_gb: number | null;
    price_cents: number | null;
};
type Usage = {
    events_this_month: number;
    tickets_this_month: number;
    unique_attendees: number;
    storage_mb: number;
    period_start: string;
    period_end: string;
};

export default function PlanPage({
    current,
    plans,
    usage,
}: {
    current: Plan;
    plans: Plan[];
    usage: Usage;
    breadcrumbs: Breadcrumb[];
}) {
    const confirm = useConfirm();
    const fmtPrice = (cents: number | null) =>
        cents === null ? 'Custom' : cents === 0 ? 'Free' : `$${(cents / 100).toFixed(0)}/mo`;

    return (
        <>
            <Head title="Plan & usage — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Plan & usage"
                        description="Your current plan and consumption this cycle."
                    />

                    <Card>
                        <CardHeader
                            actions={<Lozenge appearance="new">{fmtPrice(current.price_cents)}</Lozenge>}
                        >
                            <CardTitle size="small">Current plan: {current.name}</CardTitle>
                            <CardDescription>
                                Cycle: {new Date(usage.period_start).toLocaleDateString()} –{' '}
                                {new Date(usage.period_end).toLocaleDateString()}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                                    gap: 'var(--ds-space-200)',
                                }}
                            >
                                <Meter label="Events" used={usage.events_this_month} limit={current.events_per_month} />
                                <Meter
                                    label="Tickets sold"
                                    used={usage.tickets_this_month}
                                    limit={current.tickets_per_month}
                                />
                                <Meter
                                    label="Storage (MB)"
                                    used={usage.storage_mb}
                                    limit={current.storage_gb !== null ? current.storage_gb * 1024 : null}
                                />
                                <Box
                                    padding="space.150"
                                    style={{
                                        borderRadius: 'var(--ds-border-radius-200)',
                                        border: '1px solid var(--ds-border)',
                                    }}
                                >
                                    <Stack space="space.050">
                                        <Text size="small" color="color.text.subtle">
                                            Unique attendees
                                        </Text>
                                        <Text size="large" weight="bold">
                                            {usage.unique_attendees.toLocaleString()}
                                        </Text>
                                    </Stack>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle size="small">Switch plans</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box
                                style={{
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                                    gap: 'var(--ds-space-150)',
                                }}
                            >
                                {plans.map((p) => {
                                    const isCurrent = p.tier === current.tier;
                                    return (
                                        <Box
                                            key={p.tier}
                                            padding="space.150"
                                            style={{
                                                borderRadius: 'var(--ds-border-radius-200)',
                                                border: isCurrent
                                                    ? '2px solid var(--ds-border-brand)'
                                                    : '1px solid var(--ds-border)',
                                            }}
                                        >
                                            <Stack space="space.100">
                                                <Stack space="space.025">
                                                    <Text weight="semibold">{p.name}</Text>
                                                    <Text size="small" color="color.text.subtle">
                                                        {fmtPrice(p.price_cents)}
                                                    </Text>
                                                </Stack>
                                                <Stack space="space.050">
                                                    <Inline space="space.050" alignBlock="center">
                                                        <CheckIcon label="" color="var(--ds-icon-success)" />
                                                        <Text size="small">
                                                            {p.events_per_month ?? '∞'} events/mo
                                                        </Text>
                                                    </Inline>
                                                    <Inline space="space.050" alignBlock="center">
                                                        <CheckIcon label="" color="var(--ds-icon-success)" />
                                                        <Text size="small">
                                                            {p.tickets_per_month?.toLocaleString() ?? '∞'} tickets/mo
                                                        </Text>
                                                    </Inline>
                                                    <Inline space="space.050" alignBlock="center">
                                                        <CheckIcon label="" color="var(--ds-icon-success)" />
                                                        <Text size="small">
                                                            {p.storage_gb ?? '∞'} GB storage
                                                        </Text>
                                                    </Inline>
                                                </Stack>
                                                {isCurrent ? (
                                                    <Text size="small" color="color.text.subtle">
                                                        Current
                                                    </Text>
                                                ) : (
                                                    <Button
                                                        variant="primary"
                                                        size="compact"
                                                        fullWidth
                                                        onClick={async () => {
                                                            if (await confirm({ title: `Switch to ${p.name}?` })) {
                                                                router.post('/settings/billing/plan', { tier: p.tier });
                                                            }
                                                        }}
                                                    >
                                                        Choose
                                                    </Button>
                                                )}
                                            </Stack>
                                        </Box>
                                    );
                                })}
                            </Box>
                        </CardContent>
                    </Card>
                </Stack>
            </Box>
        </>
    );
}

function Meter({ label, used, limit }: { label: string; used: number; limit: number | null }) {
    const pct = limit ? Math.min(1, used / limit) : 0;
    return (
        <Box
            padding="space.150"
            style={{ borderRadius: 'var(--ds-border-radius-200)', border: '1px solid var(--ds-border)' }}
        >
            <Stack space="space.100">
                <Inline spread="space-between" alignBlock="center">
                    <Text size="small" color="color.text.subtle">
                        {label}
                    </Text>
                    <Text size="small">
                        {used.toLocaleString()} / {limit ?? '∞'}
                    </Text>
                </Inline>
                {limit ? <ProgressBar value={pct} ariaLabel={`${label} usage`} /> : null}
            </Stack>
        </Box>
    );
}

PlanPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
