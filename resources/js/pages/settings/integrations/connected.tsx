import { Head, router } from '@inertiajs/react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import PlugIcon from '@atlaskit/icon/core/link';
import DeleteIcon from '@atlaskit/icon/core/delete';
import Lozenge from '@atlaskit/lozenge';
import { BrandIcon } from '@/components/brand-icon';
import { Card, CardContent, CardHeader, CardTitle, IconButton, PageHeading, Tag, TagGroup, useConfirm } from '@ads';

type Breadcrumb = { title: string; href: string };
type Connection = {
    id: number;
    provider: string;
    provider_name: string;
    category: string;
    account_label: string | null;
    scopes: string[];
    connected_by: string | null;
    connected_at: string | null;
};

export default function ConnectedPage({ connections }: { connections: Connection[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    return (
        <>
            <Head title="Connected apps — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Connected apps"
                        description="Apps already connected to this organization."
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle size="small">
                                <Inline space="space.100" alignBlock="center">
                                    <PlugIcon label="" />
                                    <Text weight="semibold">Connections ({connections.length})</Text>
                                </Inline>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {connections.length === 0 ? (
                                <Text color="color.text.subtle">No connected apps.</Text>
                            ) : (
                                <Stack space="space.100">
                                    {connections.map((c) => (
                                        <Box
                                            key={c.id}
                                            padding="space.150"
                                            style={{
                                                borderRadius: 'var(--ds-border-radius-200)',
                                                border: '1px solid var(--ds-border)',
                                            }}
                                        >
                                            <Inline spread="space-between" alignBlock="start" space="space.100">
                                                <Inline space="space.150" alignBlock="start">
                                                    <Box
                                                        style={{
                                                            display: 'inline-flex',
                                                            width: 36,
                                                            height: 36,
                                                            alignItems: 'center',
                                                            justifyContent: 'center',
                                                            border: '1px solid var(--ds-border)',
                                                            borderRadius: 'var(--ds-border-radius-200)',
                                                            backgroundColor: 'var(--ds-background-default)',
                                                            flexShrink: 0,
                                                        }}
                                                    >
                                                        <BrandIcon provider={c.provider} size={20} />
                                                    </Box>
                                                    <Stack space="space.050">
                                                        <Inline space="space.100" alignBlock="center">
                                                            <Text weight="semibold">{c.provider_name}</Text>
                                                            <Lozenge>{c.category}</Lozenge>
                                                        </Inline>
                                                        <Text size="small" color="color.text.subtle">
                                                            {c.account_label ?? '—'} · Connected by {c.connected_by ?? '—'}
                                                            {c.connected_at
                                                                ? ` on ${new Date(c.connected_at).toLocaleDateString()}`
                                                                : ''}
                                                        </Text>
                                                        {c.scopes.length > 0 ? (
                                                            <TagGroup>
                                                                {c.scopes.map((s) => (
                                                                    <Tag key={s} text={s} color="standard" />
                                                                ))}
                                                            </TagGroup>
                                                        ) : null}
                                                    </Stack>
                                                </Inline>
                                                <IconButton
                                                    icon={<DeleteIcon label="" />}
                                                    label={`Disconnect ${c.provider_name}`}
                                                    size="compact"
                                                    onClick={async () => {
                                                        if (
                                                            await confirm({
                                                                title: `Disconnect ${c.provider_name}?`,
                                                                tone: 'destructive',
                                                            })
                                                        )
                                                            router.delete(`/settings/integrations/connected/${c.id}`);
                                                    }}
                                                />
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

ConnectedPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
