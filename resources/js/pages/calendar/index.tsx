import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };

type Day = { date: string; day: number; is_current_month: boolean; is_today: boolean };

type Event = {
    slug: string;
    name: string;
    starts_at: string | null;
    date: string | null;
    time: string | null;
    status: string;
    venue: string | null;
};

type Props = {
    cursor: string;
    cursor_label: string;
    prev_month: string;
    next_month: string;
    today_month: string;
    days: Day[];
    events: Event[];
    breadcrumbs: Breadcrumb[];
};

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const STATUS_COLOR: Record<string, string> = {
    published: 'bg-emerald-500',
    sold_out: 'bg-amber-500',
    draft: 'bg-slate-400',
    cancelled: 'bg-rose-500',
    postponed: 'bg-orange-500',
    ended: 'bg-slate-300',
};

export default function Calendar({
    cursor,
    cursor_label,
    prev_month,
    next_month,
    today_month,
    days,
    events,
}: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');

    // Index events by date for O(1) lookup per cell.
    const byDate = useMemo(() => {
        const m: Record<string, Event[]> = {};
        for (const e of events) {
            if (!e.date) continue;
            (m[e.date] ||= []).push(e);
        }
        return m;
    }, [events]);

    // Split days into 6 rows of 7 columns.
    const rows = useMemo(() => {
        const r: Day[][] = [];
        for (let i = 0; i < days.length; i += 7) r.push(days.slice(i, i + 7));
        return r;
    }, [days]);

    const go = (m: string) => router.get(`${window.location.pathname}?month=${m}`, undefined, { preserveScroll: true });

    return (
        <>
            <Head title={`Calendar — ${cursor_label}`} />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <Heading variant="small" title="Calendar" description="Every event in this organisation, at a glance." />
                    <div className="flex items-center gap-1">
                        <Button size="sm" variant="ghost" onClick={() => go(prev_month)} aria-label="Previous month">
                            <ChevronLeft className="size-4" />
                        </Button>
                        <span className="min-w-32 text-center text-sm font-medium">{cursor_label}</span>
                        <Button size="sm" variant="ghost" onClick={() => go(next_month)} aria-label="Next month">
                            <ChevronRight className="size-4" />
                        </Button>
                        {cursor !== today_month && (
                            <Button size="sm" variant="secondary" onClick={() => go(today_month)} className="ml-2">
                                Today
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <div className="grid grid-cols-7 border-b text-xs font-medium text-muted-foreground">
                            {WEEKDAYS.map((d) => (
                                <div key={d} className="p-2 text-center">
                                    {d}
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7">
                            {rows.flat().map((day, i) => {
                                const dayEvents = byDate[day.date] ?? [];
                                const isWeekend = i % 7 === 0 || i % 7 === 6;
                                return (
                                    <div
                                        key={day.date}
                                        className={`min-h-28 border-b border-r p-1.5 text-xs ${
                                            !day.is_current_month ? 'bg-muted/30 text-muted-foreground' : ''
                                        } ${isWeekend && day.is_current_month ? 'bg-muted/10' : ''}`}
                                    >
                                        <div
                                            className={`mb-1 flex size-6 items-center justify-center rounded-full text-xs ${
                                                day.is_today ? 'bg-primary font-semibold text-primary-foreground' : ''
                                            }`}
                                        >
                                            {day.day}
                                        </div>
                                        <div className="space-y-1">
                                            {dayEvents.slice(0, 3).map((e) => (
                                                <Link
                                                    key={e.slug}
                                                    href={`${orgBase}/events/${e.slug}`}
                                                    className="block rounded bg-card px-1.5 py-1 text-[11px] shadow-sm ring-1 ring-border hover:bg-muted/60"
                                                    title={`${e.name} · ${e.time ?? ''} · ${e.venue ?? ''}`}
                                                >
                                                    <div className="flex items-center gap-1">
                                                        <span
                                                            className={`size-1.5 shrink-0 rounded-full ${
                                                                STATUS_COLOR[e.status] ?? 'bg-slate-400'
                                                            }`}
                                                        />
                                                        <span className="truncate font-medium">{e.name}</span>
                                                    </div>
                                                    {e.time && (
                                                        <span className="text-[10px] text-muted-foreground">{e.time}</span>
                                                    )}
                                                </Link>
                                            ))}
                                            {dayEvents.length > 3 && (
                                                <span className="text-[10px] text-muted-foreground">
                                                    +{dayEvents.length - 3} more
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {events.length === 0 && (
                    <div className="flex flex-col items-center gap-2 py-12 text-sm text-muted-foreground">
                        <CalendarDays className="size-8 opacity-40" />
                        <p>No events in {cursor_label}.</p>
                    </div>
                )}
            </div>
        </>
    );
}

Calendar.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
