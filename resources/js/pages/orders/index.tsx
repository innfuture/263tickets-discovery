import { Head, Link, router } from '@inertiajs/react';
import { Download, Filter, Receipt, Search, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };

type OrderRow = {
    reference: string;
    buyer_name: string;
    buyer_email: string;
    event: string | null;
    event_slug: string | null;
    status: 'paid' | 'pending' | 'refunded' | 'partially_refunded' | 'voided' | string;
    currency: string;
    total: string;
    payment_method: string | null;
    placed_at: string | null;
};

type EventOption = { value: string; label: string };

type Props = {
    filters: { q: string; status: string; eventSlug: string; from: string; to: string };
    event_options: EventOption[];
    totals: { total: number; paid: number; refunded: number; pending: number; revenue_formatted: string };
    orders: {
        data: OrderRow[];
        meta: { current_page: number; last_page: number; per_page: number; total: number };
    };
    permissions: { manage: boolean; refund: boolean; resend: boolean };
    breadcrumbs: Breadcrumb[];
};

const STATUS_OPTIONS = [
    { value: 'all', label: 'All statuses' },
    { value: 'paid', label: 'Paid' },
    { value: 'pending', label: 'Pending' },
    { value: 'refunded', label: 'Refunded' },
    { value: 'partially_refunded', label: 'Partially refunded' },
    { value: 'voided', label: 'Voided' },
];

const STATUS_COLOR: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    refunded: 'bg-cyan-100 text-cyan-800',
    partially_refunded: 'bg-cyan-100 text-cyan-800',
    voided: 'bg-slate-100 text-slate-700',
};

const fmt = (n: number) => new Intl.NumberFormat().format(n);

export default function OrdersIndex({ filters, event_options, totals, orders }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [q, setQ] = useState(filters.q ?? '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [eventSlug, setEventSlug] = useState(filters.eventSlug || 'all');

    const apply = () => {
        router.get(window.location.pathname, {
            q: q.trim() || undefined,
            status: status === 'all' ? undefined : status,
            event: eventSlug === 'all' ? undefined : eventSlug,
        }, { preserveScroll: true, replace: true });
    };

    const clear = () => {
        setQ('');
        setStatus('all');
        setEventSlug('all');
        router.get(window.location.pathname, {}, { preserveScroll: true, replace: true });
    };

    const hasFilter = (filters.q && filters.q.length > 0) || filters.status || filters.eventSlug;

    return (
        <>
            <Head title="Orders" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Orders"
                        description="Manage every order across the organization — refund, resend, deep-link to the buyer's event."
                    />
                    <Button variant="outline" size="sm" disabled>
                        <Download className="size-4" />
                        Export CSV
                    </Button>
                </div>

                {/* Totals */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <SummaryTile label="Total orders" value={fmt(totals.total)} />
                    <SummaryTile label="Paid" value={fmt(totals.paid)} accent="text-emerald-600" />
                    <SummaryTile label="Refunded" value={fmt(totals.refunded)} accent="text-cyan-700" />
                    <SummaryTile label="Pending" value={fmt(totals.pending)} accent="text-amber-600" />
                    <SummaryTile label="Paid revenue" value={totals.revenue_formatted} />
                </div>

                <Card>
                    <CardHeader className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="size-4 text-muted-foreground" />
                            Search & filter
                        </CardTitle>
                        <CardDescription>
                            Look up an order by reference, buyer name, or email. Combine with event and status to narrow down.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-[1fr_200px_200px_auto]">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                onKeyDown={(e) => (e.key === 'Enter' ? apply() : null)}
                                placeholder="Order ref, buyer name, or email…"
                                className="pl-9"
                            />
                        </div>
                        <Select value={eventSlug} onValueChange={setEventSlug}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All events</SelectItem>
                                {event_options.map((o) => (
                                    <SelectItem key={o.value} value={o.value}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUS_OPTIONS.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="flex items-center gap-2">
                            <Button type="button" onClick={apply}>Search</Button>
                            {hasFilter ? (
                                <Button type="button" variant="ghost" onClick={clear} aria-label="Clear filters">
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
                                {hasFilter ? 'No orders match your filters' : 'No orders yet'}
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                {hasFilter
                                    ? 'Try widening the search, picking a different status, or clearing the filters.'
                                    : 'Once attendees start buying tickets across your events, every order will land here with full search, refund, and resend tools.'}
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th className="p-3">Reference</th>
                                            <th className="p-3">Buyer</th>
                                            <th className="p-3">Event</th>
                                            <th className="p-3 text-right">Total</th>
                                            <th className="p-3">Status</th>
                                            <th className="p-3">Method</th>
                                            <th className="p-3">Placed</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {orders.data.map((o) => (
                                            <tr key={o.reference} className="border-t hover:bg-muted/40">
                                                <td className="p-3 font-mono text-xs">
                                                    <Link href={`${orgBase}/orders/${o.reference}`} className="hover:underline">
                                                        {o.reference}
                                                    </Link>
                                                </td>
                                                <td className="p-3">
                                                    <p className="font-medium">{o.buyer_name}</p>
                                                    <p className="text-xs text-muted-foreground">{o.buyer_email}</p>
                                                </td>
                                                <td className="p-3 text-xs">
                                                    {o.event_slug ? (
                                                        <Link
                                                            href={`${orgBase}/events/${o.event_slug}`}
                                                            className="hover:underline"
                                                        >
                                                            {o.event}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </td>
                                                <td className="p-3 text-right font-mono text-xs">
                                                    {o.total} {o.currency}
                                                </td>
                                                <td className="p-3">
                                                    <span
                                                        className={`rounded px-2 py-0.5 text-xs ${
                                                            STATUS_COLOR[o.status] ?? 'bg-slate-100 text-slate-700'
                                                        }`}
                                                    >
                                                        {o.status}
                                                    </span>
                                                </td>
                                                <td className="p-3 text-xs text-muted-foreground">
                                                    {o.payment_method ?? '—'}
                                                </td>
                                                <td className="p-3 text-xs text-muted-foreground">{o.placed_at ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>

                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>
                                Page {orders.meta.current_page} of {orders.meta.last_page} · {orders.meta.total} total
                            </span>
                            <div className="flex gap-1">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    disabled={orders.meta.current_page <= 1}
                                    onClick={() =>
                                        router.get(
                                            window.location.pathname,
                                            { ...filters, page: orders.meta.current_page - 1 },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Prev
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    disabled={orders.meta.current_page >= orders.meta.last_page}
                                    onClick={() =>
                                        router.get(
                                            window.location.pathname,
                                            { ...filters, page: orders.meta.current_page + 1 },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

function SummaryTile({ label, value, accent }: { label: string; value: string; accent?: string }) {
    return (
        <Card>
            <CardContent className="p-3">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className={`text-lg font-semibold ${accent ?? ''}`}>{value}</p>
            </CardContent>
        </Card>
    );
}

OrdersIndex.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
