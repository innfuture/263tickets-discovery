import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Globe2,
    MapPin,
    Plus,
    Search,
    Star,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import CreateEventModal from '@/components/create-event-modal';
import { DatePicker } from '@/components/date-picker';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { thumbnailUrl } from '@/lib/image-url';
import { cn } from '@/lib/utils';

type EnumOption = { value: string; label: string };

type EventCard = {
    event_id: string;
    slug: string;
    name: string;
    status: string;
    status_label: string;
    visibility: string;
    visibility_label: string;
    is_featured: boolean;
    starts_at: string | null;
    ends_at: string | null;
    timezone: string;
    city: string | null;
    country_code: string | null;
    is_online: boolean;
    banner_image_url: string | null;
    capacity: number | null;
    tickets_sold_count: number;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    name: string;
    status: string;
    visibility: string;
    from: string;
    to: string;
    featured: boolean;
};

type CategoryOption = { id: number; name: string; slug: string };

type Props = {
    events: Paginator<EventCard>;
    filters: Filters;
    statuses: EnumOption[];
    visibilities: EnumOption[];
    categories: CategoryOption[];
};

const ALL_VALUE = '__all__';
const SEARCH_DEBOUNCE_MS = 350;

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'published':
            return 'default';
        case 'sold_out':
        case 'cancelled':
            return 'destructive';
        case 'draft':
            return 'secondary';
        case 'postponed':
        case 'ended':
        default:
            return 'outline';
    }
}

function compactDate(
    starts: string | null,
    ends: string | null,
    timezone: string,
): string {
    if (!starts) {
        return '';
    }

    const startDate = new Date(starts);
    const endDate = ends ? new Date(ends) : null;

    const dayFmt = new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        timeZone: timezone,
    });
    const timeFmt = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        timeZone: timezone,
    });

    if (!endDate || dayFmt.format(startDate) === dayFmt.format(endDate)) {
        return `${dayFmt.format(startDate)} · ${timeFmt.format(startDate)}`;
    }

    return `${dayFmt.format(startDate)} – ${dayFmt.format(endDate)}`;
}

function compactLocation(event: EventCard): string {
    if (event.is_online) {
        return 'Online';
    }

    return event.city ?? event.country_code ?? 'Location TBD';
}

function cleanParams(filters: Filters): Record<string, string> {
    const params: Record<string, string> = {};

    if (filters.name) {
        params.name = filters.name;
    }

    if (filters.status) {
        params.status = filters.status;
    }

    if (filters.visibility) {
        params.visibility = filters.visibility;
    }

    if (filters.from) {
        params.from = filters.from;
    }

    if (filters.to) {
        params.to = filters.to;
    }

    if (filters.featured) {
        params.featured = '1';
    }

    return params;
}

export default function EventsIndex({
    events,
    filters,
    statuses,
    visibilities,
    categories,
}: Props) {
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const slug = page.props.currentTeam?.slug ?? '';
    const eventsUrl = `/${slug}/events`;

    const [local, setLocal] = useState<Filters>(filters);
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            router.get(eventsUrl, cleanParams(local), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, SEARCH_DEBOUNCE_MS);

        return () => clearTimeout(timer);
    }, [local, eventsUrl]);

    const clearFilters = () => {
        setLocal({
            name: '',
            status: '',
            visibility: '',
            from: '',
            to: '',
            featured: false,
        });
    };

    const hasFilters =
        local.name !== '' ||
        local.status !== '' ||
        local.visibility !== '' ||
        local.from !== '' ||
        local.to !== '' ||
        local.featured;

    return (
        <>
            <Head title="My Events" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="My Events"
                        description="Browse, filter, and create events for this organisation."
                    />

                    <CreateEventModal
                        visibilities={visibilities}
                        categories={categories}
                    >
                        <Button>
                            <Plus /> New event
                        </Button>
                    </CreateEventModal>
                </div>

                <Card>
                    <CardContent className="space-y-4 px-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5">
                            <div className="grid gap-2">
                                <Label htmlFor="filter-name">Search</Label>
                                <div className="relative">
                                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="filter-name"
                                        value={local.name}
                                        onChange={(e) =>
                                            setLocal({
                                                ...local,
                                                name: e.target.value,
                                            })
                                        }
                                        placeholder="Search event name..."
                                        className="pl-9"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-status">Status</Label>
                                <Select
                                    value={local.status || ALL_VALUE}
                                    onValueChange={(v) =>
                                        setLocal({
                                            ...local,
                                            status: v === ALL_VALUE ? '' : v,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="filter-status"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_VALUE}>
                                            All statuses
                                        </SelectItem>
                                        {statuses.map((s) => (
                                            <SelectItem
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-visibility">
                                    Visibility
                                </Label>
                                <Select
                                    value={local.visibility || ALL_VALUE}
                                    onValueChange={(v) =>
                                        setLocal({
                                            ...local,
                                            visibility:
                                                v === ALL_VALUE ? '' : v,
                                        })
                                    }
                                >
                                    <SelectTrigger
                                        id="filter-visibility"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="All visibility" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ALL_VALUE}>
                                            All visibility
                                        </SelectItem>
                                        {visibilities.map((v) => (
                                            <SelectItem
                                                key={v.value}
                                                value={v.value}
                                            >
                                                {v.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-from">From</Label>
                                <DatePicker
                                    id="filter-from"
                                    value={local.from}
                                    placeholder="From"
                                    onChange={(v) =>
                                        setLocal({ ...local, from: v })
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="filter-to">To</Label>
                                <DatePicker
                                    id="filter-to"
                                    value={local.to}
                                    placeholder="To"
                                    onChange={(v) =>
                                        setLocal({ ...local, to: v })
                                    }
                                />
                            </div>
                        </div>

                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="filter-featured"
                                    checked={local.featured}
                                    onCheckedChange={(v) =>
                                        setLocal({
                                            ...local,
                                            featured: v === true,
                                        })
                                    }
                                />
                                <Label
                                    htmlFor="filter-featured"
                                    className="flex items-center gap-1 font-normal"
                                >
                                    <Star className="size-3.5 fill-yellow-400 text-yellow-400" />
                                    Featured only
                                </Label>
                            </div>

                            {hasFilters ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearFilters}
                                >
                                    <X /> Clear filters
                                </Button>
                            ) : null}
                        </div>
                    </CardContent>
                </Card>

                {events.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center gap-3 py-16 text-center">
                            <CalendarDays className="size-10 text-muted-foreground" />
                            <div>
                                <p className="font-medium">No events found</p>
                                <p className="text-sm text-muted-foreground">
                                    {hasFilters
                                        ? 'Try adjusting your filters, or create a new event.'
                                        : 'Get started by creating your first event.'}
                                </p>
                            </div>
                            <CreateEventModal
                                visibilities={visibilities}
                                categories={categories}
                            >
                                <Button variant="secondary">
                                    <Plus /> New event
                                </Button>
                            </CreateEventModal>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                            {events.data.map((event) => (
                                <Link
                                    key={event.event_id}
                                    href={`${eventsUrl}/${event.slug}`}
                                    className="block focus:outline-none"
                                >
                                    <Card
                                        className={cn(
                                            'h-full gap-2 overflow-hidden pt-0 pb-3 transition hover:shadow-md',
                                            event.is_featured &&
                                                'ring-1 ring-yellow-400/40',
                                        )}
                                    >
                                        <div className="relative aspect-video w-full overflow-hidden bg-muted">
                                            {event.banner_image_url ? (
                                                <img
                                                    src={
                                                        thumbnailUrl(
                                                            event.banner_image_url,
                                                            'small',
                                                        ) ??
                                                        event.banner_image_url
                                                    }
                                                    srcSet={[
                                                        `${thumbnailUrl(event.banner_image_url, 'small') ?? event.banner_image_url} 400w`,
                                                        `${thumbnailUrl(event.banner_image_url, 'medium') ?? event.banner_image_url} 800w`,
                                                    ].join(', ')}
                                                    sizes="(min-width: 1280px) 20vw, (min-width: 768px) 33vw, 50vw"
                                                    loading="lazy"
                                                    alt=""
                                                    className="size-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.style.display =
                                                            'none';
                                                    }}
                                                />
                                            ) : (
                                                <div className="size-full bg-linear-to-br from-muted to-muted-foreground/10" />
                                            )}
                                            <Badge
                                                variant={statusVariant(
                                                    event.status,
                                                )}
                                                className="absolute top-1.5 left-1.5 px-1.5 py-0 text-[10px]"
                                            >
                                                {event.status_label}
                                            </Badge>
                                            {event.is_featured ? (
                                                <div className="absolute top-1.5 right-1.5 rounded-full bg-black/30 p-1 backdrop-blur-sm">
                                                    <Star className="size-3 fill-yellow-400 text-yellow-400" />
                                                </div>
                                            ) : null}
                                        </div>

                                        <CapacityBar
                                            sold={event.tickets_sold_count}
                                            capacity={event.capacity}
                                        />

                                        <div className="space-y-1 px-3">
                                            <h3 className="line-clamp-1 text-sm leading-tight font-semibold">
                                                {event.name}
                                            </h3>
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <CalendarDays className="size-3 shrink-0" />
                                                <span className="truncate">
                                                    {compactDate(
                                                        event.starts_at,
                                                        event.ends_at,
                                                        event.timezone,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                {event.is_online ? (
                                                    <Globe2 className="size-3 shrink-0" />
                                                ) : (
                                                    <MapPin className="size-3 shrink-0" />
                                                )}
                                                <span className="truncate">
                                                    {compactLocation(event)}
                                                </span>
                                            </div>
                                        </div>
                                    </Card>
                                </Link>
                            ))}
                        </div>

                        {events.last_page > 1 ? (
                            <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
                                <p className="text-sm text-muted-foreground">
                                    Showing {events.from ?? 0}–{events.to ?? 0}{' '}
                                    of {events.total} events
                                </p>
                                <div className="flex gap-2">
                                    {events.prev_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.visit(
                                                    events.prev_page_url!,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <ChevronLeft /> Previous
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            <ChevronLeft /> Previous
                                        </Button>
                                    )}
                                    <span className="flex items-center px-3 text-sm text-muted-foreground">
                                        Page {events.current_page} of{' '}
                                        {events.last_page}
                                    </span>
                                    {events.next_page_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.visit(
                                                    events.next_page_url!,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Next <ChevronRight />
                                        </Button>
                                    ) : (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            Next <ChevronRight />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ) : null}
                    </>
                )}
            </div>
        </>
    );
}

function CapacityBar({
    sold,
    capacity,
}: {
    sold: number;
    capacity: number | null;
}) {
    const pct =
        capacity != null && capacity > 0
            ? Math.min(100, Math.round((sold / capacity) * 100))
            : 0;
    const fill =
        capacity == null
            ? 'bg-muted-foreground/30'
            : pct >= 100
              ? 'bg-destructive'
              : pct >= 80
                ? 'bg-amber-500'
                : 'bg-primary';

    return (
        <div className="mx-3">
            <div className="h-1 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={cn('h-full transition-all', fill)}
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

EventsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'My Events',
            href: props.currentTeam ? `/${props.currentTeam.slug}/events` : '/',
        },
    ],
});
