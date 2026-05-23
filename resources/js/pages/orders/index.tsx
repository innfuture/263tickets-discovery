import { Head, router } from '@inertiajs/react';
import { Download, Filter, Receipt, Search, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type OrderRow = {
    id: number;
    reference: string;
    buyerName: string;
    buyerEmail: string;
    event: string;
    ticketCount: number;
    total: string;
    status: 'paid' | 'pending' | 'refunded' | 'voided';
    placedAt: string;
};

type OrdersPayload = {
    data: OrderRow[];
    meta: { current_page: number; last_page: number; total: number };
};

type Filters = {
    q?: string;
    status?: string;
    event?: string;
    from?: string;
    to?: string;
};

const STATUS_OPTIONS = [
    { value: '__all__', label: 'All statuses' },
    { value: 'paid', label: 'Paid' },
    { value: 'pending', label: 'Pending' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'voided', label: 'Voided' },
];

export default function OrdersIndex({
    orders,
    filters,
}: {
    orders: OrdersPayload;
    filters: Filters;
    permissions: { manage: boolean; refund: boolean; resend: boolean };
}) {
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status ?? '__all__');

    const apply = () => {
        router.get(
            '/orders',
            {
                q: q.trim() || undefined,
                status: status === '__all__' ? undefined : status,
            },
            { preserveState: true, replace: true },
        );
    };

    const clear = () => {
        setQ('');
        setStatus('__all__');
        router.get(
            '/orders',
            {},
            { preserveState: true, replace: true },
        );
    };

    const hasFilter = (filters.q && filters.q.length > 0) || (filters.status && filters.status.length > 0);

    return (
        <>
            <Head title="Orders" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Orders"
                        description="Manage all orders across every event in this organization. Edit buyer info, resend tickets, and process refunds."
                    />
                    <Button variant="outline" size="sm" disabled>
                        <Download className="size-4" />
                        Export CSV
                    </Button>
                </div>

                <Card>
                    <CardHeader className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="size-4 text-muted-foreground" />
                            Search &amp; filter
                        </CardTitle>
                        <CardDescription>
                            Look up an order by reference, buyer name, or
                            email. Combine with status to narrow down.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-[1fr_180px_auto]">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' ? apply() : null
                                }
                                placeholder="Order ref, buyer name, or email…"
                                className="pl-9"
                            />
                        </div>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((s) => (
                                    <SelectItem
                                        key={s.value}
                                        value={s.value}
                                    >
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="flex items-center gap-2">
                            <Button type="button" onClick={apply}>
                                Search
                            </Button>
                            {hasFilter ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={clear}
                                    aria-label="Clear filters"
                                >
                                    <X className="size-4" />
                                </Button>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                {orders.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Receipt className="size-8 text-muted-foreground" />
                            <p className="text-sm font-medium">
                                {hasFilter
                                    ? 'No orders match your filters'
                                    : 'No orders yet'}
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {hasFilter
                                    ? 'Try widening the search, picking a different status, or clearing the filters.'
                                    : 'Once attendees start buying tickets across your events, every order will land here with full search, refund, and resend tools.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <div className="divide-y">
                            <div className="grid grid-cols-[140px_1fr_1fr_90px_110px_110px_140px] gap-3 px-4 py-2 text-xs font-medium text-muted-foreground">
                                <span>Reference</span>
                                <span>Buyer</span>
                                <span>Event</span>
                                <span className="text-right">Tickets</span>
                                <span className="text-right">Total</span>
                                <span>Status</span>
                                <span>Placed</span>
                            </div>
                            {orders.data.map((o) => (
                                <a
                                    key={o.id}
                                    href={`/orders/${o.reference}`}
                                    className="grid grid-cols-[140px_1fr_1fr_90px_110px_110px_140px] items-center gap-3 px-4 py-3 text-sm transition-colors hover:bg-muted"
                                >
                                    <span className="truncate font-mono text-xs">
                                        {o.reference}
                                    </span>
                                    <span className="truncate">
                                        <span className="block truncate font-medium">
                                            {o.buyerName}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {o.buyerEmail}
                                        </span>
                                    </span>
                                    <span className="truncate">{o.event}</span>
                                    <span className="text-right">
                                        {o.ticketCount}
                                    </span>
                                    <span className="text-right font-medium">
                                        {o.total}
                                    </span>
                                    <span>
                                        <span className="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
                                            {o.status}
                                        </span>
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {o.placedAt}
                                    </span>
                                </a>
                            ))}
                        </div>
                    </Card>
                )}
            </div>
        </>
    );
}

OrdersIndex.layout = (props: { currentOrganization?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Orders',
            href: props.currentOrganization
                ? `/${props.currentOrganization.slug}/orders`
                : '/orders',
        },
    ],
});
