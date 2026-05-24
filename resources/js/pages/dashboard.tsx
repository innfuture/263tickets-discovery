import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    CircleDollarSign,
    Eye,
    Plus,
    ScanLine,
    Ticket,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Stats = {
    events_total: number;
    events_published: number;
    events_draft: number;
    events_upcoming: number;
    tickets_issued: number;
    tickets_scanned: number;
    tickets_voided: number;
    views_30d: number;
    capacity: number;
    sold: number;
};

type Upcoming = {
    slug: string;
    name: string;
    starts_at: string | null;
    starts_relative: string;
    venue: string | null;
    status: string;
};

type ActivityRow = {
    ticket_number: string;
    event_id: number;
    kind: 'scanned' | 'voided';
    at: string | null;
    relative: string | null;
};

type TopEvent = { slug: string; name: string; views: number };

type ViewsTrend = { date: string; views: number };

type Breadcrumb = { title: string; href: string };

type Props = {
    stats: Stats;
    views_trend: ViewsTrend[];
    upcoming: Upcoming[];
    recent_activity: ActivityRow[];
    top_events: TopEvent[];
    permissions: {
        can_view_finance: boolean;
        can_view_analytics: boolean;
        can_create_event: boolean;
    };
    breadcrumbs: Breadcrumb[];
};

function fmtInt(n: number): string {
    return new Intl.NumberFormat().format(n);
}

function pct(part: number, whole: number): number {
    if (whole <= 0) return 0;
    return Math.round((part / whole) * 100);
}

export default function Dashboard({
    stats,
    views_trend,
    upcoming,
    recent_activity,
    top_events,
    permissions,
}: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const sellThrough = pct(stats.sold, stats.capacity);
    const scanRate = pct(stats.tickets_scanned, stats.tickets_issued);
    const maxViews = Math.max(1, ...views_trend.map((v) => v.views));

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                {/* KPI tiles */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiTile
                        icon={<CalendarDays className="size-4" />}
                        label="Upcoming events"
                        value={fmtInt(stats.events_upcoming)}
                        sub={`${fmtInt(stats.events_published)} published · ${fmtInt(stats.events_draft)} draft`}
                    />
                    <KpiTile
                        icon={<Ticket className="size-4" />}
                        label="Tickets issued"
                        value={fmtInt(stats.tickets_issued)}
                        sub={`${fmtInt(stats.tickets_voided)} voided`}
                    />
                    <KpiTile
                        icon={<CheckCircle2 className="size-4" />}
                        label="Sell-through"
                        value={`${sellThrough}%`}
                        sub={`${fmtInt(stats.sold)} of ${fmtInt(stats.capacity)} capacity`}
                    />
                    <KpiTile
                        icon={<Eye className="size-4" />}
                        label="Views (30d)"
                        value={fmtInt(stats.views_30d)}
                        sub={`Check-in rate ${scanRate}%`}
                    />
                </div>

                {/* Quick actions + views trend */}
                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="text-base">Quick actions</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            {permissions.can_create_event && (
                                <QuickAction
                                    href={`${orgBase}/events`}
                                    icon={<Plus className="size-4" />}
                                    label="Create event"
                                />
                            )}
                            <QuickAction
                                href={`${orgBase}/calendar`}
                                icon={<CalendarDays className="size-4" />}
                                label="Event calendar"
                            />
                            <QuickAction
                                href={`${orgBase}/check-in`}
                                icon={<ScanLine className="size-4" />}
                                label="Open check-in"
                            />
                            <QuickAction
                                href={`${orgBase}/attendees`}
                                icon={<Activity className="size-4" />}
                                label="Attendees"
                            />
                            {permissions.can_view_finance && (
                                <QuickAction
                                    href={`${orgBase}/finance`}
                                    icon={<CircleDollarSign className="size-4" />}
                                    label="Finance"
                                />
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Page views — last 14 days</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {views_trend.length === 0 ? (
                                <p className="py-12 text-center text-sm text-muted-foreground">
                                    No view activity yet — publish an event to start tracking.
                                </p>
                            ) : (
                                <div className="flex h-40 items-end gap-1.5">
                                    {views_trend.map((v) => (
                                        <div
                                            key={v.date}
                                            className="flex flex-1 flex-col items-center gap-1"
                                            title={`${v.date}: ${v.views} views`}
                                        >
                                            <div
                                                className="w-full rounded-t bg-primary/80"
                                                style={{ height: `${Math.max(4, (v.views / maxViews) * 140)}px` }}
                                            />
                                            <span className="text-[10px] text-muted-foreground">{v.date.slice(5)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Upcoming + activity */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Upcoming events</CardTitle>
                            <Link href={`${orgBase}/calendar`} className="text-xs text-primary hover:underline">
                                View calendar <ArrowRight className="ml-0.5 inline size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent className="p-0">
                            {upcoming.length === 0 ? (
                                <p className="px-6 pb-6 text-sm text-muted-foreground">No upcoming events.</p>
                            ) : (
                                <ul className="divide-y">
                                    {upcoming.map((e) => (
                                        <li
                                            key={e.slug}
                                            className="flex items-center justify-between p-4 text-sm hover:bg-muted/40"
                                        >
                                            <div>
                                                <Link
                                                    href={`${orgBase}/events/${e.slug}`}
                                                    className="font-medium hover:underline"
                                                >
                                                    {e.name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {e.starts_relative}
                                                    {e.venue && ` · ${e.venue}`}
                                                </p>
                                            </div>
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs ${
                                                    e.status === 'published'
                                                        ? 'bg-emerald-100 text-emerald-800'
                                                        : 'bg-slate-100 text-slate-700'
                                                }`}
                                            >
                                                {e.status}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle className="text-base">Recent ticket activity</CardTitle>
                            <Link href={`${orgBase}/check-in`} className="text-xs text-primary hover:underline">
                                Open check-in <ArrowRight className="ml-0.5 inline size-3" />
                            </Link>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recent_activity.length === 0 ? (
                                <p className="px-6 pb-6 text-sm text-muted-foreground">No recent scans or voids.</p>
                            ) : (
                                <ul className="divide-y">
                                    {recent_activity.map((a, i) => (
                                        <li
                                            key={`${a.ticket_number}-${i}`}
                                            className="flex items-center justify-between p-3 text-sm"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span
                                                    className={`size-2 rounded-full ${
                                                        a.kind === 'voided' ? 'bg-rose-500' : 'bg-emerald-500'
                                                    }`}
                                                />
                                                <code className="text-xs">{a.ticket_number}</code>
                                                <span className="text-xs text-muted-foreground">{a.kind}</span>
                                            </div>
                                            <span className="text-xs text-muted-foreground">{a.relative}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Top events */}
                {permissions.can_view_analytics && top_events.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Top events by views — 30 days</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {top_events.map((e) => {
                                const max = Math.max(...top_events.map((t) => t.views));
                                const width = `${Math.max(8, (e.views / max) * 100)}%`;
                                return (
                                    <div key={e.slug} className="flex items-center gap-3 text-sm">
                                        <Link
                                            href={`${orgBase}/events/${e.slug}`}
                                            className="w-48 truncate hover:underline"
                                        >
                                            {e.name}
                                        </Link>
                                        <div className="h-2 flex-1 overflow-hidden rounded bg-muted">
                                            <div className="h-full rounded bg-primary/70" style={{ width }} />
                                        </div>
                                        <span className="w-16 text-right font-mono text-xs text-muted-foreground">
                                            {fmtInt(e.views)}
                                        </span>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function KpiTile({
    icon,
    label,
    value,
    sub,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    sub?: string;
}) {
    return (
        <Card>
            <CardContent className="flex items-start gap-3 p-4">
                <div className="rounded-md bg-muted p-2 text-muted-foreground">{icon}</div>
                <div className="flex-1">
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-xl font-semibold">{value}</p>
                    {sub && <p className="mt-0.5 text-xs text-muted-foreground">{sub}</p>}
                </div>
            </CardContent>
        </Card>
    );
}

function QuickAction({
    href,
    icon,
    label,
}: {
    href: string;
    icon: React.ReactNode;
    label: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-md border bg-card p-2 text-sm hover:bg-muted/60"
        >
            <span className="rounded bg-muted p-1.5 text-muted-foreground">{icon}</span>
            <span className="flex-1">{label}</span>
            <ArrowRight className="size-3.5 text-muted-foreground" />
        </Link>
    );
}

Dashboard.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
