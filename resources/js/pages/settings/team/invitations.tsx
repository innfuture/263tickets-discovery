import { Head, router, usePage } from '@inertiajs/react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import EmailIcon from '@atlaskit/icon/core/email';
import SendIcon from '@atlaskit/icon/core/send';
import CrossIcon from '@atlaskit/icon/core/cross';
import Lozenge from '@atlaskit/lozenge';
import { Button, Card, CardContent, CardHeader, CardTitle, IconButton, PageHeading, useConfirm } from '@ads';

type Breadcrumb = { title: string; href: string };
type Invitation = {
    id: number;
    email: string;
    role: string;
    invited_by: string | null;
    sent_at: string;
    expires_at: string | null;
    is_expired: boolean;
};

export default function InvitationsPage({ invitations }: { invitations: Invitation[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const currentOrg = usePage<{ currentOrganization?: { slug: string } | null }>().props.currentOrganization;

    const cancel = async (id: number) => {
        if (!currentOrg) return;
        if (!(await confirm({ title: 'Cancel invitation?', tone: 'destructive' }))) return;
        router.delete(`/settings/organizations/${currentOrg.slug}/invitations/${id}`);
    };

    return (
        <>
            <Head title="Invitations — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Invitations"
                        description="Pending invitations to join this organization."
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle size="small">
                                <Inline space="space.100" alignBlock="center">
                                    <EmailIcon label="" />
                                    <Text weight="semibold">Pending ({invitations.length})</Text>
                                </Inline>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {invitations.length === 0 ? (
                                <Text color="color.text.subtle">No invitations.</Text>
                            ) : (
                                <Stack space="space.100">
                                    {invitations.map((i) => (
                                        <Box
                                            key={i.id}
                                            padding="space.150"
                                            style={{
                                                borderRadius: 'var(--ds-border-radius-200)',
                                                border: '1px solid var(--ds-border)',
                                            }}
                                        >
                                            <Inline spread="space-between" alignBlock="center" space="space.100">
                                                <Stack space="space.025">
                                                    <Inline space="space.100" alignBlock="center">
                                                        <Text weight="semibold">{i.email}</Text>
                                                        {i.is_expired ? (
                                                            <Lozenge appearance="removed" isBold>
                                                                expired
                                                            </Lozenge>
                                                        ) : null}
                                                    </Inline>
                                                    <Text size="small" color="color.text.subtle">
                                                        Role: {i.role} · Invited by {i.invited_by ?? '—'} ·{' '}
                                                        {new Date(i.sent_at).toLocaleDateString()}
                                                    </Text>
                                                </Stack>
                                                <Inline space="space.050">
                                                    <Button
                                                        size="compact"
                                                        variant="subtle"
                                                        iconBefore={<SendIcon label="" />}
                                                        onClick={() => router.post(`/settings/team/invitations/${i.id}/resend`)}
                                                    >
                                                        Resend
                                                    </Button>
                                                    <IconButton
                                                        size="compact"
                                                        icon={<CrossIcon label="" />}
                                                        label={`Cancel invitation to ${i.email}`}
                                                        onClick={() => cancel(i.id)}
                                                    />
                                                </Inline>
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

InvitationsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
