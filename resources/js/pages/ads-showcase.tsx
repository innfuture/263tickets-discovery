import { Head } from '@inertiajs/react';
import { Box, Inline, Stack, Text } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';
import {
    Banner,
    Button,
    DataTable,
    DropdownMenu,
    EmptyState,
    IconButton,
    SectionMessage,
    Spinner,
    StatusBadge,
    Tag,
    TagGroup,
    Tooltip,
    type Column,
} from '@ads';
import { AppShell } from '@/layouts/ads/AppShell';
import { PageContent } from '@/layouts/ads/PageContent';
import MoreIcon from '@atlaskit/icon/core/show-more-horizontal';

/**
 * Worked example demonstrating the new ADS surface end-to-end:
 *   AppShell (top nav + side nav + main)
 *   PageContent (breadcrumbs + page header + actions)
 *   Box / Stack / Inline / Text primitives instead of Tailwind
 *   StatusBadge + DropdownMenu + DataTable + EmptyState wrappers
 *
 * Use this as the canonical pattern when migrating pages.
 */

interface ShowcaseEvent {
    id: number;
    name: string;
    venue: string;
    status: 'available' | 'sold_out' | 'reserved' | 'cancelled' | 'pending' | 'published' | 'draft';
    sold: number;
    capacity: number;
    revenueCents: number;
}

const fixtures: ShowcaseEvent[] = [
    { id: 1, name: 'Harare Jazz Night', venue: '7 Arts Theatre', status: 'available', sold: 142, capacity: 300, revenueCents: 1_420_000 },
    { id: 2, name: 'Vic Falls Marathon', venue: 'Victoria Falls', status: 'published', sold: 870, capacity: 1000, revenueCents: 8_700_000 },
    { id: 3, name: 'Bulawayo Comedy Hour', venue: 'Bulawayo Theatre', status: 'sold_out', sold: 250, capacity: 250, revenueCents: 2_500_000 },
    { id: 4, name: 'Mutare Food Festival', venue: 'Sakubva Stadium', status: 'pending', sold: 18, capacity: 2000, revenueCents: 180_000 },
    { id: 5, name: 'Gweru Tech Conf', venue: 'Midlands Hotel', status: 'draft', sold: 0, capacity: 400, revenueCents: 0 },
];

function formatMoney(cents: number, currency = 'USD') {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
}

const columns: Column<ShowcaseEvent>[] = [
    {
        key: 'name',
        header: 'Event',
        isSortable: true,
        width: 35,
        render: (row) => (
            <Stack space="space.025">
                <Text weight="semibold">{row.name}</Text>
                <Text size="small" color="color.text.subtle">
                    {row.venue}
                </Text>
            </Stack>
        ),
    },
    {
        key: 'status',
        header: 'Status',
        isSortable: true,
        width: 15,
        render: (row) => <StatusBadge status={row.status} />,
    },
    {
        key: 'sold',
        header: 'Tickets',
        isSortable: true,
        width: 15,
        render: (row) => (
            <Inline space="space.050" alignBlock="baseline">
                <Text weight="medium">{row.sold}</Text>
                <Text color="color.text.subtlest">/ {row.capacity}</Text>
            </Inline>
        ),
    },
    {
        key: 'revenue',
        header: 'Revenue',
        isSortable: true,
        width: 20,
        render: (row) => <Text weight="semibold">{formatMoney(row.revenueCents)}</Text>,
    },
    {
        key: 'actions',
        header: '',
        width: 5,
        render: (row) => (
            <DropdownMenu
                trigger={<IconButton icon={<MoreIcon label="" />} label={`Actions for ${row.name}`} />}
                items={[
                    { label: 'View', onClick: () => console.log('view', row.id) },
                    { label: 'Edit', onClick: () => console.log('edit', row.id) },
                    { type: 'separator' },
                    { label: 'Archive', onClick: () => console.log('archive', row.id), isDestructive: true },
                ]}
            />
        ),
    },
];

export default function AdsShowcase() {
    return (
        <>
            <Head title="ADS Showcase" />
            <PageContent
                title="ADS Migration Showcase"
                breadcrumbs={[
                    { text: 'Internal', href: '/dashboard' },
                    { text: 'ADS Showcase' },
                ]}
                actions={
                    <Inline space="space.100">
                        <Tooltip content="Coming soon">
                            <Button variant="secondary">Export</Button>
                        </Tooltip>
                        <Button variant="primary">Create event</Button>
                    </Inline>
                }
            >
                <Stack space="space.300">
                    <Banner appearance="announcement">
                        This page demonstrates the new Atlaskit-based UI surface. Use it as the reference when migrating
                        existing pages.
                    </Banner>

                    <SectionMessage
                        title="Migration in progress"
                        appearance="information"
                        actions={[{ text: 'Read the plan', href: '/docs/ads-migration' }]}
                    >
                        <Text>
                            Pages migrate incrementally. Legacy pages keep using the existing layout + shadcn components
                            until they are flipped to <code>AppShell</code> + <code>@ads</code>.
                        </Text>
                    </SectionMessage>

                    <Box backgroundColor="elevation.surface" padding="space.300" style={{ borderRadius: 8 }}>
                        <Stack space="space.200">
                            <Heading size="medium" as="h2">
                                Filters
                            </Heading>
                            <TagGroup>
                                <Tag text="All events" color="standard" />
                                <Tag text="Published" color="green" />
                                <Tag text="Draft" color="yellow" />
                                <Tag text="Sold out" color="red" />
                            </TagGroup>
                        </Stack>
                    </Box>

                    <Box backgroundColor="elevation.surface" padding="space.300" style={{ borderRadius: 8 }}>
                        <DataTable columns={columns} rows={fixtures} rowsPerPage={10} testId="showcase-events-table" />
                    </Box>

                    <Box backgroundColor="elevation.surface" padding="space.300" style={{ borderRadius: 8 }}>
                        <Stack space="space.200">
                            <Heading size="medium" as="h2">
                                Empty state
                            </Heading>
                            <EmptyState
                                heading="No archived events"
                                description="When you archive an event it'll show up here. Archiving is reversible."
                                primaryAction={<Button variant="primary">View active events</Button>}
                            />
                        </Stack>
                    </Box>

                    <Box backgroundColor="elevation.surface" padding="space.300" style={{ borderRadius: 8 }}>
                        <Inline space="space.200" alignBlock="center">
                            <Spinner size="small" label="Loading" />
                            <Text color="color.text.subtle">Fetching latest sales numbers…</Text>
                        </Inline>
                    </Box>
                </Stack>
            </PageContent>
        </>
    );
}

AdsShowcase.layout = (page: React.ReactNode) => <AppShell>{page}</AppShell>;
