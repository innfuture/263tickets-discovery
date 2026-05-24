import { Head } from '@inertiajs/react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import DownloadIcon from '@atlaskit/icon/core/download';
import { Card, CardContent, CardHeader, CardTitle, IconButton, PageHeading, StatusBadge, type TicketStatus } from '@ads';

type Breadcrumb = { title: string; href: string };
type Invoice = {
    id: number;
    number: string;
    issued_on: string;
    due_on: string | null;
    amount_cents: number;
    amount_formatted: string;
    currency: string;
    status: string;
    paid_at: string | null;
    line_items: Array<{ label: string; amount_cents: number }>;
};

// Map billing-invoice status strings → the shared StatusBadge vocabulary
// so the colour treatment stays consistent with the rest of the app.
function asStatus(s: string): TicketStatus {
    switch (s) {
        case 'paid':
            return 'completed';
        case 'overdue':
            return 'cancelled';
        case 'refunded':
            return 'refunded';
        default:
            return 'pending';
    }
}

export default function InvoicesPage({ invoices }: { invoices: Invoice[]; breadcrumbs: Breadcrumb[] }) {
    return (
        <>
            <Head title="Invoices — Settings" />
            <Box padding="space.400">
                <Stack space="space.300">
                    <PageHeading
                        variant="small"
                        title="Invoices"
                        description="Historical platform invoices with downloadable PDFs."
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle size="small">All invoices ({invoices.length})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {invoices.length === 0 ? (
                                <Text color="color.text.subtle">No invoices yet.</Text>
                            ) : (
                                <Stack space="space.0">
                                    <Box style={{ borderBottom: '1px solid var(--ds-border)' }} paddingBlock="space.100">
                                        <Inline space="space.200" alignBlock="center">
                                            <Box style={{ width: 140 }}>
                                                <Text size="small" color="color.text.subtle" weight="semibold">
                                                    Number
                                                </Text>
                                            </Box>
                                            <Box style={{ width: 120 }}>
                                                <Text size="small" color="color.text.subtle" weight="semibold">
                                                    Issued
                                                </Text>
                                            </Box>
                                            <Box style={{ width: 120 }}>
                                                <Text size="small" color="color.text.subtle" weight="semibold">
                                                    Due
                                                </Text>
                                            </Box>
                                            <Box style={{ flex: 1, textAlign: 'right' }}>
                                                <Text size="small" color="color.text.subtle" weight="semibold">
                                                    Amount
                                                </Text>
                                            </Box>
                                            <Box style={{ width: 120 }}>
                                                <Text size="small" color="color.text.subtle" weight="semibold">
                                                    Status
                                                </Text>
                                            </Box>
                                            <Box style={{ width: 48 }} />
                                        </Inline>
                                    </Box>
                                    {invoices.map((invoice) => (
                                        <Box
                                            key={invoice.id}
                                            paddingBlock="space.150"
                                            style={{ borderBottom: '1px solid var(--ds-border)' }}
                                        >
                                            <Inline space="space.200" alignBlock="center">
                                                <Box style={{ width: 140 }}>
                                                    <Text size="small" color="color.text">
                                                        <code style={{ fontFamily: 'var(--ds-font-family-code)' }}>
                                                            {invoice.number}
                                                        </code>
                                                    </Text>
                                                </Box>
                                                <Box style={{ width: 120 }}>
                                                    <Text size="small">{new Date(invoice.issued_on).toLocaleDateString()}</Text>
                                                </Box>
                                                <Box style={{ width: 120 }}>
                                                    <Text size="small">
                                                        {invoice.due_on ? new Date(invoice.due_on).toLocaleDateString() : '—'}
                                                    </Text>
                                                </Box>
                                                <Box style={{ flex: 1, textAlign: 'right' }}>
                                                    <Text weight="semibold">
                                                        {invoice.currency} {invoice.amount_formatted}
                                                    </Text>
                                                </Box>
                                                <Box style={{ width: 120 }}>
                                                    <StatusBadge status={asStatus(invoice.status)} customLabel={invoice.status} />
                                                </Box>
                                                <Box style={{ width: 48 }}>
                                                    <a href={`/settings/billing/invoices/${invoice.id}/download`}>
                                                        <IconButton
                                                            icon={<DownloadIcon label="" />}
                                                            label={`Download invoice ${invoice.number}`}
                                                            size="compact"
                                                        />
                                                    </a>
                                                </Box>
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

InvoicesPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
