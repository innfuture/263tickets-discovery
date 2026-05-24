import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftRight, Banknote, CircleDollarSign, Filter, Receipt, Wallet, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };

type RevByCurrency = { currency: string; revenue: number; units_sold: number; revenue_formatted: string };

type Refund = {
    uuid: string;
    amount_minor: number;
    currency: string;
    status: string;
    reason: string | null;
    created_at: string | null;
    reference: string | null;
    gateway: string | null;
};

type Payout = {
    id: number;
    amount_minor: number;
    currency: string;
    status: string;
    destination_label: string | null;
    arrival_on: string | null;
    created_at: string | null;
};

type EventOption = { value: string; label: string };

type Props = {
    range_days: number;
    primary_currency: string;
    revenue_by_currency: RevByCurrency[];
    totals: {
        units_sold: number;
        refunds_open: number;
        refunds_processed_30d: number;
        payouts_completed: number;
    };
    refunds: Refund[];
    payouts: Payout[];
    event_options: EventOption[];
    event_scope: { slug: string; name: string } | null;
    permissions: { can_process_refund: boolean; can_export: boolean };
    breadcrumbs: Breadcrumb[];
};

const fmtMinor = (n: number, c: string) => `${(n / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${c}`;
const fmtMajor = (n: number, c: string) => `${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${c}`;
const fmt = (n: number) => new Intl.NumberFormat().format(n);

export default function Finance({
    range_days,
    revenue_by_currency,
    totals,
    refunds,
    payouts,
    event_options,
    event_scope,
}: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [eventSel, setEventSel] = useState<string>(event_scope?.slug ?? 'all');

    const setRange = (r: number) =>
        router.get(
            window.location.pathname,
            { range: r, event: event_scope?.slug },
            { preserveScroll: true },
        );

    const setEvent = (slug: string) => {
        setEventSel(slug);
        router.get(
            window.location.pathname,
            { range: range_days, event: slug === 'all' ? undefined : slug },
            { preserveScroll: true },
        );
    };

    const primaryRev = revenue_by_currency[0];

    return (
        <>
            <Head title={event_scope ? `Finance · ${event_scope.name}` : 'Finance'} />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Finance"
                        description={
                            event_scope
                                ? `Revenue for ${event_scope.name} — last ${range_days} days. Refunds + payouts shown org-wide.`
                                : `Revenue, refunds, and payouts — last ${range_days} days.`
                        }
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex items-center gap-1">
                            <Filter className="size-3.5 text-muted-foreground" />
                            <Select value={eventSel} onValueChange={setEvent}>
                                <SelectTrigger className="w-56">
                                    <SelectValue placeholder="All events" />
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
                            {event_scope && (
                                <Button size="sm" variant="ghost" onClick={() => setEvent('all')} aria-label="Clear event filter">
                                    <X className="size-3.5" />
                                </Button>
                            )}
                        </div>
                        <div className="flex gap-1">
                            {[7, 30, 90, 365].map((r) => (
                                <Button
                                    key={r}
                                    size="sm"
                                    variant={range_days === r ? 'secondary' : 'ghost'}
                                    onClick={() => setRange(r)}
                                >
                                    {r === 365 ? '1y' : `${r}d`}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Top KPI row */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        icon={<CircleDollarSign className="size-4" />}
                        label={`Revenue (${primaryRev?.currency ?? '—'})`}
                        value={primaryRev ? fmtMajor(primaryRev.revenue, primaryRev.currency) : '—'}
                    />
                    <KpiTile icon={<Receipt className="size-4" />} label="Units sold" value={fmt(totals.units_sold)} />
                    <KpiTile
                        icon={<ArrowLeftRight className="size-4" />}
                        label="Refunds in queue"
                        value={fmt(totals.refunds_open)}
                        sub={`${fmt(totals.refunds_processed_30d)} processed in window`}
                    />
                    <KpiTile
                        icon={<Banknote className="size-4" />}
                        label="Payouts completed"
                        value={fmt(totals.payouts_completed)}
                    />
                </div>

                {/* Revenue per currency */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Revenue by currency</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {revenue_by_currency.length === 0 ? (
                            <p className="p-6 text-sm text-muted-foreground">No sales recorded yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Currency</th>
                                        <th className="p-3 text-right">Units sold</th>
                                        <th className="p-3 text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {revenue_by_currency.map((r) => (
                                        <tr key={r.currency} className="border-t">
                                            <td className="p-3">{r.currency}</td>
                                            <td className="p-3 text-right font-mono text-xs">{fmt(r.units_sold)}</td>
                                            <td className="p-3 text-right font-mono">{fmtMajor(r.revenue, r.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        <p className="border-t p-3 text-xs text-muted-foreground">
                            Gross revenue from sold ticket categories. Settled bank revenue is shown in Payouts below.
                        </p>
                    </CardContent>
                </Card>

                {/* Refunds + payouts */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Recent refunds</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Reference</th>
                                        <th className="p-3">Gateway</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {refunds.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                                No refunds in window.
                                            </td>
                                        </tr>
                                    )}
                                    {refunds.map((r) => (
                                        <tr key={r.uuid} className="border-t hover:bg-muted/40">
                                            <td className="p-3 font-mono text-xs">
                                                <Link
                                                    href={`${orgBase}/finance/refunds/${r.uuid}`}
                                                    className="hover:underline"
                                                >
                                                    {r.reference ?? r.uuid.slice(0, 8)}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-xs">{r.gateway ?? '—'}</td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        r.status === 'succeeded'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : r.status === 'failed'
                                                              ? 'bg-rose-100 text-rose-800'
                                                              : 'bg-amber-100 text-amber-800'
                                                    }`}
                                                >
                                                    {r.status}
                                                </span>
                                            </td>
                                            <td className="p-3 text-right font-mono text-xs">
                                                {fmtMinor(r.amount_minor, r.currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Wallet className="size-4" /> Payouts
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Destination</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Arrival</th>
                                        <th className="p-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payouts.length === 0 && (
                                        <tr>
                                            <td colSpan={4} className="p-6 text-center text-muted-foreground">
                                                No payouts on record.
                                            </td>
                                        </tr>
                                    )}
                                    {payouts.map((p) => (
                                        <tr key={p.id} className="border-t">
                                            <td className="p-3">{p.destination_label ?? '—'}</td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        p.status === 'completed'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : p.status === 'failed'
                                                              ? 'bg-rose-100 text-rose-800'
                                                              : 'bg-amber-100 text-amber-800'
                                                    }`}
                                                >
                                                    {p.status}
                                                </span>
                                            </td>
                                            <td className="p-3 text-xs text-muted-foreground">{p.arrival_on ?? '—'}</td>
                                            <td className="p-3 text-right font-mono text-xs">
                                                {fmtMinor(p.amount_minor, p.currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function KpiTile({ icon, label, value, sub }: { icon?: React.ReactNode; label: string; value: string; sub?: string }) {
    return (
        <Card>
            <CardContent className="flex items-start gap-3 p-4">
                {icon && <div className="rounded-md bg-muted p-2 text-muted-foreground">{icon}</div>}
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-xl font-semibold">{value}</p>
                    {sub && <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p>}
                </div>
            </CardContent>
        </Card>
    );
}

Finance.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
