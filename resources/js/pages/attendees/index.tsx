import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Download, Filter, Users, XCircle } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type EventOption = { value: string; label: string };
type Row = {
    id: number;
    ticket_number: string;
    event_slug: string | null;
    event_name: string | null;
    category: string | null;
    admission: string;
    pass: string;
    scanned_at: string | null;
    is_voided: boolean;
    voided_at: string | null;
    scan_count: number;
};
type Totals = { total: number; scanned: number; voided: number; unscanned: number };

type Props = {
    filters: { event: string; status: string; q: string };
    event_options: EventOption[];
    totals: Totals;
    rows: Row[];
    meta: { current_page: number; last_page: number; total: number };
    permissions: { can_export: boolean; can_contact: boolean };
    breadcrumbs: Breadcrumb[];
};

export default function Attendees({ filters, event_options, totals, rows, meta, permissions }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [event, setEvent] = useState(filters.event || 'all');
    const [status, setStatus] = useState(filters.status || 'all');
    const [q, setQ] = useState(filters.q);

    const apply = () => {
        router.get(window.location.pathname, {
            event: event === 'all' ? '' : event,
            status: status === 'all' ? '' : status,
            q,
        }, { preserveScroll: true });
    };

    const exportCsv = () => {
        const params = new URLSearchParams({
            event: event === 'all' ? '' : event,
            status: status === 'all' ? '' : status,
            q,
        });
        window.open(`${orgBase}/attendees/export?${params.toString()}`, '_blank');
    };

    return (
        <>
            <Head title="Attendees" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading variant="small" title="Attendees" description="Cross-event roster, filter, contact, export." />
                    {permissions.can_export && (
                        <Button size="sm" variant="secondary" onClick={exportCsv}>
                            <Download className="mr-1 size-4" /> Export CSV
                        </Button>
                    )}
                </div>

                {/* Totals */}
                <div className="grid gap-3 sm:grid-cols-4">
                    <SummaryTile icon={<Users className="size-4" />} label="Total" value={totals.total} />
                    <SummaryTile icon={<CheckCircle2 className="size-4 text-emerald-600" />} label="Scanned" value={totals.scanned} />
                    <SummaryTile icon={<Users className="size-4 text-amber-600" />} label="Unscanned" value={totals.unscanned} />
                    <SummaryTile icon={<XCircle className="size-4 text-rose-600" />} label="Voided" value={totals.voided} />
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="flex flex-wrap items-end gap-3 p-4">
                        <div className="flex-1 min-w-48">
                            <label className="mb-1 block text-xs text-muted-foreground">Search ticket #</label>
                            <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="TKT-…" />
                        </div>
                        <div className="min-w-48">
                            <label className="mb-1 block text-xs text-muted-foreground">Event</label>
                            <Select value={event} onValueChange={setEvent}>
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
                        </div>
                        <div className="min-w-40">
                            <label className="mb-1 block text-xs text-muted-foreground">Status</label>
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Any status</SelectItem>
                                    <SelectItem value="scanned">Scanned</SelectItem>
                                    <SelectItem value="unscanned">Unscanned</SelectItem>
                                    <SelectItem value="voided">Voided</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={apply} size="sm">
                            <Filter className="mr-1 size-3.5" /> Apply
                        </Button>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="p-3">Ticket #</th>
                                    <th className="p-3">Event</th>
                                    <th className="p-3">Category</th>
                                    <th className="p-3">Admission</th>
                                    <th className="p-3">Pass</th>
                                    <th className="p-3">State</th>
                                    <th className="p-3">Scans</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                            No attendees match the current filters.
                                        </td>
                                    </tr>
                                )}
                                {rows.map((r) => (
                                    <tr key={r.id} className="border-t hover:bg-muted/40">
                                        <td className="p-3 font-mono text-xs">{r.ticket_number}</td>
                                        <td className="p-3">
                                            {r.event_slug ? (
                                                <Link
                                                    href={`${orgBase}/events/${r.event_slug}`}
                                                    className="hover:underline"
                                                >
                                                    {r.event_name}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </td>
                                        <td className="p-3 text-xs">{r.category ?? '—'}</td>
                                        <td className="p-3 text-xs">{r.admission}</td>
                                        <td className="p-3 text-xs">{r.pass}</td>
                                        <td className="p-3">
                                            {r.is_voided ? (
                                                <span className="rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-800">voided</span>
                                            ) : r.scanned_at ? (
                                                <span className="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">scanned</span>
                                            ) : (
                                                <span className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">unscanned</span>
                                            )}
                                        </td>
                                        <td className="p-3 text-xs">{r.scan_count}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* Pagination */}
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {meta.current_page} of {meta.last_page} · {meta.total} total
                    </span>
                    <div className="flex gap-1">
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={meta.current_page <= 1}
                            onClick={() => router.get(window.location.pathname, { ...filters, page: meta.current_page - 1 }, { preserveScroll: true })}
                        >
                            Prev
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={meta.current_page >= meta.last_page}
                            onClick={() => router.get(window.location.pathname, { ...filters, page: meta.current_page + 1 }, { preserveScroll: true })}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

function SummaryTile({ icon, label, value }: { icon: React.ReactNode; label: string; value: number }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-3">
                {icon}
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-lg font-semibold">{new Intl.NumberFormat().format(value)}</p>
                </div>
            </CardContent>
        </Card>
    );
}

Attendees.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
