import {
    BarChart3,
    Globe2,
    MousePointerClick,
    RefreshCw,
    TrendingUp,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type DailyView = { date: string; views: number };

type AnalyticsData = {
    period_days: number;
    daily_views: DailyView[];
    totals: {
        page_views: number;
        unique_sessions: number;
        conversions: number;
        clicks: number;
    };
    top_referrers: { source: string; visits: number }[];
    top_countries: { country_code: string; views: number }[];
};

function StatCard({
    icon,
    label,
    value,
}: {
    icon: React.ReactNode;
    label: string;
    value: number;
}) {
    return (
        <div className="flex items-center gap-3 rounded-lg border bg-card p-3 transition hover:bg-muted/30">
            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                {icon}
            </div>
            <div className="min-w-0">
                <div className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </div>
                <div className="text-base font-semibold tabular-nums">
                    {value.toLocaleString()}
                </div>
            </div>
        </div>
    );
}

/**
 * Fills missing days in the [periodDays] window with zero-view entries so the
 * chart shows a continuous timeline rather than collapsing on sparse data.
 */
function densifyDailyViews(
    data: DailyView[],
    periodDays: number,
): DailyView[] {
    const byDate = new Map(data.map((d) => [d.date, d.views]));
    const result: DailyView[] = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (let i = periodDays - 1; i >= 0; i--) {
        const d = new Date(today);
        d.setDate(today.getDate() - i);
        const iso = d.toISOString().slice(0, 10);
        result.push({ date: iso, views: byDate.get(iso) ?? 0 });
    }

    return result;
}

function shortDate(iso: string): string {
    const d = new Date(iso);

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
    }).format(d);
}

function BarChart({
    data,
    isEmpty,
}: {
    data: DailyView[];
    isEmpty: boolean;
}) {
    const max = Math.max(...data.map((d) => d.views), 1);
    const totalDays = data.length;
    const firstLabel = data[0] ? shortDate(data[0].date) : '';
    const lastLabel = data[totalDays - 1]
        ? shortDate(data[totalDays - 1].date)
        : '';

    return (
        <div
            className={cn(
                'relative space-y-1.5',
                isEmpty && 'opacity-60',
            )}
            aria-label={
                isEmpty
                    ? 'No analytics data — placeholder graph'
                    : 'Daily page views'
            }
        >
            <div className="relative flex h-28 items-end gap-px rounded-md border bg-linear-to-b from-muted/10 to-transparent p-2">
                {data.map((d) => {
                    const pct = isEmpty
                        ? // Render a soft sine-wave silhouette so the empty
                          // chart feels like an intentional placeholder.
                          15 +
                          12 *
                              Math.sin(
                                  (parseInt(d.date.slice(-2), 10) / 30) *
                                      Math.PI *
                                      2,
                              )
                        : Math.max(2, Math.round((d.views / max) * 100));

                    return (
                        <div
                            key={d.date}
                            title={
                                isEmpty
                                    ? `${shortDate(d.date)}: no data`
                                    : `${shortDate(d.date)}: ${d.views} views`
                            }
                            className="group flex h-full flex-1 cursor-default flex-col items-center justify-end"
                        >
                            <div
                                className={cn(
                                    'w-full rounded-t transition-all',
                                    isEmpty
                                        ? 'bg-muted-foreground/20'
                                        : 'bg-linear-to-t from-primary/70 to-primary group-hover:from-primary group-hover:to-primary',
                                )}
                                style={{ height: `${Math.min(100, pct)}%` }}
                            />
                        </div>
                    );
                })}

                {isEmpty ? (
                    <div className="absolute inset-0 flex items-center justify-center">
                        <span className="rounded-full border border-dashed border-muted-foreground/40 bg-background/80 px-3 py-1 text-[11px] font-medium text-muted-foreground backdrop-blur-sm">
                            No views in the last {totalDays} days
                        </span>
                    </div>
                ) : null}
            </div>
            <div className="flex justify-between px-1 text-[10px] text-muted-foreground tabular-nums">
                <span>{firstLabel}</span>
                <span>{lastLabel}</span>
            </div>
        </div>
    );
}

export function EventAnalyticsDashboard({
    teamSlug,
    eventSlug,
}: {
    teamSlug: string;
    eventSlug: string;
}) {
    const [data, setData] = useState<AnalyticsData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);

    const url = `/${teamSlug}/events/${eventSlug}/analytics`;

    const fetchData = useCallback(
        (signal?: AbortSignal) => {
            return fetch(url, {
                signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then((r) => {
                    if (!r.ok) {
                        throw new Error(`HTTP ${r.status}`);
                    }

                    return r.json();
                })
                .then((json: AnalyticsData) => {
                    setData(json);
                    setError(false);
                    setLoading(false);
                })
                .catch((e: unknown) => {
                    if (e instanceof DOMException && e.name === 'AbortError') {
                        return;
                    }

                    setError(true);
                    setLoading(false);
                });
        },
        [url],
    );

    useEffect(() => {
        const controller = new AbortController();
        fetchData(controller.signal);

        return () => controller.abort();
    }, [fetchData]);

    const refresh = () => {
        setLoading(true);
        fetchData();
    };

    const periodDays = data?.period_days ?? 30;
    const densified = useMemo(
        () => densifyDailyViews(data?.daily_views ?? [], periodDays),
        [data?.daily_views, periodDays],
    );
    const hasViews = (data?.totals.page_views ?? 0) > 0;

    if (loading) {
        return (
            <Card>
                <CardHeader>
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <BarChart3 className="size-4" />
                        Analytics
                    </h2>
                </CardHeader>
                <CardContent className="space-y-3">
                    <Skeleton className="h-28 w-full" />
                    <div className="grid grid-cols-2 gap-2">
                        <Skeleton className="h-14 w-full" />
                        <Skeleton className="h-14 w-full" />
                        <Skeleton className="h-14 w-full" />
                        <Skeleton className="h-14 w-full" />
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (error || !data) {
        return (
            <Card>
                <CardHeader>
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <BarChart3 className="size-4" />
                        Analytics
                    </h2>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        tone="muted"
                        icon={<BarChart3 className="size-6" />}
                        title="Couldn't load analytics"
                        description="The metrics service didn't respond. Try refreshing in a moment."
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={refresh}
                            >
                                <RefreshCw className="size-3.5" />
                                Retry
                            </Button>
                        }
                    />
                </CardContent>
            </Card>
        );
    }

    const maxReferrer = Math.max(
        ...(data.top_referrers?.map((r) => r.visits) ?? [1]),
        1,
    );

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-2">
                    <h2 className="flex items-center gap-2 text-sm font-semibold">
                        <BarChart3 className="size-4 text-primary" />
                        Analytics
                        <span className="text-xs font-normal text-muted-foreground">
                            · last {data.period_days} days
                        </span>
                    </h2>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        title="Refresh"
                        onClick={refresh}
                    >
                        <RefreshCw className="size-3.5" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <BarChart data={densified} isEmpty={!hasViews} />

                <div className="grid grid-cols-2 gap-2">
                    <StatCard
                        icon={<TrendingUp className="size-4" />}
                        label="Page views"
                        value={data.totals.page_views}
                    />
                    <StatCard
                        icon={<Users className="size-4" />}
                        label="Unique visitors"
                        value={data.totals.unique_sessions}
                    />
                    <StatCard
                        icon={<MousePointerClick className="size-4" />}
                        label="Clicks"
                        value={data.totals.clicks}
                    />
                    <StatCard
                        icon={<Globe2 className="size-4" />}
                        label="Conversions"
                        value={data.totals.conversions}
                    />
                </div>

                {data.top_referrers.length > 0 ? (
                    <>
                        <Separator />
                        <div className="space-y-2">
                            <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Top referrers
                            </p>
                            {data.top_referrers.map((r) => (
                                <ReferrerRow
                                    key={r.source}
                                    label={r.source || 'Direct / unknown'}
                                    count={r.visits}
                                    max={maxReferrer}
                                />
                            ))}
                        </div>
                    </>
                ) : null}

                {data.top_countries.length > 0 ? (
                    <>
                        <Separator />
                        <div className="space-y-2">
                            <p className="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Top countries
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {data.top_countries.map((c) => (
                                    <span
                                        key={c.country_code}
                                        className="inline-flex items-center gap-1 rounded-full border bg-muted/40 px-2 py-0.5 text-xs"
                                    >
                                        <Globe2 className="size-3 text-muted-foreground" />
                                        {c.country_code || 'Unknown'}{' '}
                                        <span className="font-semibold tabular-nums">
                                            {c.views}
                                        </span>
                                    </span>
                                ))}
                            </div>
                        </div>
                    </>
                ) : null}
            </CardContent>
        </Card>
    );
}

function ReferrerRow({
    label,
    count,
    max,
}: {
    label: string;
    count: number;
    max: number;
}) {
    const pct = max > 0 ? Math.round((count / max) * 100) : 0;

    return (
        <div className="space-y-1">
            <div className="flex items-center justify-between gap-2 text-xs">
                <span className="truncate text-muted-foreground">{label}</span>
                <span className="shrink-0 font-medium tabular-nums">
                    {count}
                </span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className="h-full bg-linear-to-r from-primary/60 to-primary transition-all"
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}
