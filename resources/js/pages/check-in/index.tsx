import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ScanLine, Users, XCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };

type RecentRow = {
    ticket_number: string;
    event: string | null;
    category: string | null;
    scanned_at: string | null;
    relative: string | null;
    scan_count: number;
    is_voided: boolean;
};

type ScanResult = {
    status: 'ok' | 'duplicate' | 'voided' | 'not_found';
    message: string;
    ticket?: {
        ticket_number: string;
        event: string | null;
        event_slug: string | null;
        category: string | null;
        admission: string | null;
        pass: string | null;
        scanned_at: string | null;
        scan_count: number;
        is_voided: boolean;
    };
};

type Props = {
    recent: RecentRow[];
    today: { scanned: number; voided: number };
    events: { value: string; label: string }[];
    scan_endpoint: string;
    breadcrumbs: Breadcrumb[];
};

const DEVICE_ID = (() => {
    if (typeof window === 'undefined') return '';
    const k = 'sandbox.checkin.deviceId';
    let id = window.localStorage.getItem(k);
    if (!id) {
        id = 'dev_' + Math.random().toString(36).slice(2, 14);
        window.localStorage.setItem(k, id);
    }
    return id;
})();

export default function CheckIn({ recent, today, events, scan_endpoint }: Props) {
    const orgBase = typeof window !== 'undefined'
        ? window.location.pathname.split('/').slice(0, 2).join('/')
        : '';
    const [payload, setPayload] = useState('');
    const [result, setResult] = useState<ScanResult | null>(null);
    const [busy, setBusy] = useState(false);
    const [recentRows, setRecentRows] = useState(recent);

    const submitScan = async (raw?: string) => {
        const value = (raw ?? payload).trim();
        if (!value) return;
        setBusy(true);
        try {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            const res = await fetch(scan_endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ payload: value, device_id: DEVICE_ID }),
            });
            const body: ScanResult = await res.json().catch(() => ({
                status: 'not_found' as const,
                message: 'Bad response',
            }));
            setResult(body);
            setPayload('');

            if (body.status === 'ok') {
                toast.success(`Admitted ${body.ticket?.ticket_number}`);
                if (body.ticket) {
                    setRecentRows((prev) => [
                        {
                            ticket_number: body.ticket!.ticket_number,
                            event: body.ticket!.event,
                            category: body.ticket!.category,
                            scanned_at: body.ticket!.scanned_at,
                            relative: 'just now',
                            scan_count: body.ticket!.scan_count,
                            is_voided: body.ticket!.is_voided,
                        },
                        ...prev.slice(0, 19),
                    ]);
                }
            } else if (body.status === 'duplicate') {
                toast(`Already scanned: ${body.ticket?.ticket_number}`, { icon: '⚠️' });
            } else if (body.status === 'voided') {
                toast.error(`Voided ticket: ${body.ticket?.ticket_number}`);
            } else {
                toast.error('Not found.');
            }
        } catch {
            toast.error('Network error.');
        } finally {
            setBusy(false);
        }
    };

    const onKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') void submitScan();
    };

    return (
        <>
            <Head title="Check-in" />
            <div className="space-y-4 p-4">
                <Heading
                    variant="small"
                    title="Check-in"
                    description={`Scan or paste a ticket payload. Device: ${DEVICE_ID}`}
                />

                <div className="grid gap-3 sm:grid-cols-3">
                    <SummaryTile label="Scanned today" value={today.scanned} accent="text-emerald-600" />
                    <SummaryTile label="Voided today" value={today.voided} accent="text-rose-600" />
                    <SummaryTile label="Active events" value={events.length} accent="text-blue-600" />
                </div>

                {/* Active events — quick jump into the full attendee
                    roster for any event happening today. Operators use
                    this to reconcile no-shows against expected check-ins. */}
                {events.length > 0 && (
                    <Card>
                        <CardContent className="flex flex-wrap items-center gap-2 p-3">
                            <span className="text-xs font-medium uppercase text-muted-foreground">
                                Today's events
                            </span>
                            {events.map((e) => (
                                <Link
                                    key={e.value}
                                    href={`${orgBase}/attendees?event=${e.value}`}
                                    className="inline-flex items-center gap-1 rounded-full border bg-card px-2.5 py-1 text-xs hover:bg-muted/60"
                                    title="View attendee roster"
                                >
                                    <Users className="size-3" />
                                    {e.label}
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ScanLine className="size-4" /> Scan / lookup
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2">
                                <Input
                                    autoFocus
                                    value={payload}
                                    onChange={(e) => setPayload(e.target.value)}
                                    onKeyDown={onKey}
                                    placeholder="Paste QR payload or ticket number, then Enter"
                                    className="font-mono"
                                />
                                <Button onClick={() => submitScan()} disabled={busy || !payload.trim()}>
                                    {busy ? 'Scanning…' : 'Scan'}
                                </Button>
                            </div>

                            {result && <ResultPanel result={result} />}

                            <p className="text-xs text-muted-foreground">
                                Camera input integrates here when a wedge scanner or QR-capture library is mounted —
                                the same endpoint accepts both modes.
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Recently scanned</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recentRows.length === 0 ? (
                                <p className="px-6 pb-6 text-sm text-muted-foreground">No scans yet today.</p>
                            ) : (
                                <ul className="divide-y">
                                    {recentRows.map((r, i) => (
                                        <li
                                            key={`${r.ticket_number}-${i}`}
                                            className="flex items-center justify-between p-3 text-sm"
                                        >
                                            <div>
                                                <code className="text-xs">{r.ticket_number}</code>
                                                <p className="text-xs text-muted-foreground">
                                                    {r.event} · {r.category}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-xs text-muted-foreground">{r.relative}</p>
                                                {r.scan_count > 1 && (
                                                    <p className="text-[10px] text-amber-600">×{r.scan_count} scans</p>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function SummaryTile({ label, value, accent }: { label: string; value: number; accent: string }) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className={`text-2xl font-semibold ${accent}`}>{new Intl.NumberFormat().format(value)}</p>
            </CardContent>
        </Card>
    );
}

function ResultPanel({ result }: { result: ScanResult }) {
    const palette =
        result.status === 'ok'
            ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
            : result.status === 'duplicate'
              ? 'bg-amber-50 border-amber-200 text-amber-900'
              : 'bg-rose-50 border-rose-200 text-rose-900';
    const Icon = result.status === 'ok' ? CheckCircle2 : result.status === 'duplicate' ? AlertTriangle : XCircle;

    return (
        <div className={`rounded-md border p-3 text-sm ${palette}`}>
            <div className="flex items-center gap-2 font-medium">
                <Icon className="size-4" />
                <span>{result.message}</span>
            </div>
            {result.ticket && (
                <dl className="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    <dt className="text-muted-foreground">Ticket</dt>
                    <dd className="font-mono">{result.ticket.ticket_number}</dd>
                    <dt className="text-muted-foreground">Event</dt>
                    <dd>{result.ticket.event ?? '—'}</dd>
                    <dt className="text-muted-foreground">Category</dt>
                    <dd>{result.ticket.category ?? '—'}</dd>
                    <dt className="text-muted-foreground">Admission</dt>
                    <dd>{result.ticket.admission ?? '—'}</dd>
                    <dt className="text-muted-foreground">Scans</dt>
                    <dd>{result.ticket.scan_count}</dd>
                </dl>
            )}
        </div>
    );
}

CheckIn.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
