import { Head, Link, router } from '@inertiajs/react';
import { Eye, Globe, MousePointerClick, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

type Breadcrumb = { title: string; href: string };

type Kpi = { views: number; sessions: number; clicks: number; conversions: number; conv_rate: number };
type Source = { source: string; hits: number };
type Leader = {
    slug: string;
    name: string;
    views: number;
    capacity: number;
    sold: number;
    sell_through: number;
    issued: number;
    scanned: number;
    scan_rate: number;
};
type TrendPoint = { date: string; views: number };

type Props = {
    range_days: number;
    kpis: Kpi;
    trend: TrendPoint[];
    sources: Source[];
    leaderboard: Leader[];
    permissions: { can_export: boolean };
    breadcrumbs: Breadcrumb[];
};

const fmt = (n: number) => new Intl.NumberFormat().format(n);

export default function Reports({ range_days, kpis, trend, sources, leaderboard }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const setRange = (r: number) => router.get(window.location.pathname, { range: r }, { preserveScroll: true });
    const maxTrend = Math.max(1, ...trend.map((t) => t.views));
    const maxSource = Math.max(1, ...sources.map((s) => s.hits));

    return (
        <>
            <Head title="Reports" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Reports"
                        description={`Org-wide analytics rollup — last ${range_days} days.`}
                    />
                    <div className="flex gap-1">
                        {[7, 30, 90].map((r) => (
                            <Button
                                key={r}
                                size="sm"
                                variant={range_days === r ? 'secondary' : 'ghost'}
                                onClick={() => setRange(r)}
                            >
                                {r}d
                            </Button>
                        ))}
                    </div>
                </div>

                {/* KPI strip */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <KpiTile icon={<Eye className="size-4" />} label="Views" value={fmt(kpis.views)} />
                    <KpiTile icon={<Users className="size-4" />} label="Sessions" value={fmt(kpis.sessions)} />
                    <KpiTile icon={<MousePointerClick className="size-4" />} label="Clicks" value={fmt(kpis.clicks)} />
                    <KpiTile icon={<Globe className="size-4" />} label="Conversions" value={fmt(kpis.conversions)} />
                    <KpiTile label="Conv. rate" value={`${kpis.conv_rate}%`} />
                </div>

                {/* Trend */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Daily views</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {trend.length === 0 ? (
                            <p className="py-10 text-center text-sm text-muted-foreground">No views in window.</p>
                        ) : (
                            <div className="flex h-48 items-end gap-1.5">
                                {trend.map((t) => (
                                    <div key={t.date} className="flex flex-1 flex-col items-center gap-1">
                                        <div
                                            className="w-full rounded-t bg-primary/80"
                                            style={{ height: `${Math.max(4, (t.views / maxTrend) * 175)}px` }}
                                            title={`${t.date}: ${t.views}`}
                                        />
                                        <span className="text-[10px] text-muted-foreground">{t.date.slice(5)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Sources */}
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="text-base">Top traffic sources</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {sources.length === 0 && (
                                <p className="text-sm text-muted-foreground">No source data.</p>
                            )}
                            {sources.map((s) => (
                                <div key={s.source} className="flex items-center gap-3 text-sm">
                                    <span className="w-28 truncate">{s.source}</span>
                                    <div className="h-2 flex-1 overflow-hidden rounded bg-muted">
                                        <div
                                            className="h-full rounded bg-primary/70"
                                            style={{ width: `${Math.max(6, (s.hits / maxSource) * 100)}%` }}
                                        />
                                    </div>
                                    <span className="w-12 text-right font-mono text-xs text-muted-foreground">
                                        {fmt(s.hits)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    {/* Leaderboard */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Events leaderboard</CardTitle>
                        </CardHeader>
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Event</th>
                                        <th className="p-3 text-right">Views</th>
                                        <th className="p-3 text-right">Sell-through</th>
                                        <th className="p-3 text-right">Issued</th>
                                        <th className="p-3 text-right">Scan rate</th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {leaderboard.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="p-6 text-center text-muted-foreground">
                                                No events have activity in the selected window.
                                            </td>
                                        </tr>
                                    )}
                                    {leaderboard.map((row) => (
                                        <tr key={row.slug} className="border-t hover:bg-muted/40">
                                            <td className="p-3">
                                                <Link
                                                    href={`${orgBase}/events/${row.slug}`}
                                                    className="hover:underline"
                                                >
                                                    {row.name}
                                                </Link>
                                            </td>
                                            <td className="p-3 text-right font-mono text-xs">{fmt(row.views)}</td>
                                            <td className="p-3 text-right font-mono text-xs">
                                                {row.sell_through}% ({fmt(row.sold)}/{fmt(row.capacity)})
                                            </td>
                                            <td className="p-3 text-right font-mono text-xs">{fmt(row.issued)}</td>
                                            <td className="p-3 text-right font-mono text-xs">{row.scan_rate}%</td>
                                            <td className="p-3 text-right text-xs">
                                                <Link
                                                    href={`${orgBase}/attendees?event=${row.slug}`}
                                                    className="text-primary hover:underline"
                                                    title="Attendees for this event"
                                                >
                                                    Attendees →
                                                </Link>
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

function KpiTile({ icon, label, value }: { icon?: React.ReactNode; label: string; value: string }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-4">
                {icon && <div className="rounded-md bg-muted p-2 text-muted-foreground">{icon}</div>}
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-lg font-semibold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

Reports.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
