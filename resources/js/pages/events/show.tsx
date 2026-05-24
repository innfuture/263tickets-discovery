import { Head, Link, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import {
    AlertCircle,
    ArrowRight,
    BookText,
    CalendarDays,
    CircleDollarSign,
    Clock,
    FileText,
    Globe2,
    Info,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Receipt,
    ScanLine,
    Settings2,
    ShieldCheck,
    Star,
    Tag,
    Ticket,
    Timer,
    Users,
} from 'lucide-react';
import { AddToCalendarButton } from '@/components/add-to-calendar-button';
import { CountdownTimer } from '@/components/countdown-timer';
import { EventAdManagementPanel } from '@/components/event-ad-management-panel';
import { EventAgendaSection } from '@/components/event-agenda-section';
import type { AgendaEntry } from '@/components/event-agenda-section';
import { EventAmenitiesSection } from '@/components/event-amenities-section';
import type { AmenityView } from '@/components/event-amenities-section';
import { EventAnalyticsDashboard } from '@/components/event-analytics-dashboard';
import { EventHighlightsSection } from '@/components/event-highlights-section';
import { EventLineupSection } from '@/components/event-lineup-section';
import type { LineupArtist } from '@/components/event-lineup-section';
import { EventMediaCarousel } from '@/components/event-media-carousel';
import type { MediaItem } from '@/components/event-media-carousel';
import { EventSeoModal } from '@/components/event-seo-modal';
import type { EventSeo } from '@/components/event-seo-modal';
import { EventSponsorsSection } from '@/components/event-sponsors-section';
import type { SponsorView } from '@/components/event-sponsors-section';
import { EventTicketManager } from '@/components/event-ticket-manager';
import { EventWeatherPanel } from '@/components/event-weather-panel';
import type { WeatherForecast } from '@/components/event-weather-panel';
import { RichTextContent } from '@/components/rich-text-content';
import { ShareMenu } from '@/components/share-menu';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { EmptyState } from '@/components/ui/empty-state';
import { SectionTitle } from '@/components/ui/section-title';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { VenueMap } from '@/components/venue-map';
import { cn } from '@/lib/utils';

type EnumPair = { value: string; label: string };

type EventDetail = {
    id: number;
    event_id: string;
    slug: string;
    name: string;
    description: string | null;
    short_description: string | null;
    status: EnumPair;
    visibility: EnumPair;
    is_featured: boolean;
    published_at: string | null;
    starts_at: string | null;
    ends_at: string | null;
    doors_open_at: string | null;
    timezone: string;
    sales_start_at: string | null;
    sales_end_at: string | null;
    venue_name: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    region: string | null;
    country_code: string | null;
    postal_code: string | null;
    latitude: number | null;
    longitude: number | null;
    is_online: boolean;
    online_url: string | null;
    banner_image_url: string | null;
    tags: string[] | null;
    capacity: number | null;
    tickets_sold_count: number;
    minimum_age: number | null;
    parking_info: string | null;
    age_requirement_details: string | null;
    refund_policy: string | null;
    terms: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    category: { id: number; name: string; slug: string } | null;
    seo: EventSeo;
    lineup: LineupArtist[];
    agenda: AgendaEntry[];
    media: MediaItem[];
    sponsors: SponsorView[];
    amenities: AmenityView[];
    ticket_categories: TicketCategory[];
    ad_campaigns: AdCampaign[];
};

type TicketCategory = {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    offline_quantity: number;
    online_quantity: number;
    generation_status: 'pending' | 'processing' | 'completed' | 'failed' | null;
    generation_progress: number | null;
    sale_status: { value: string; label: string };
    admission_type: { value: string; label: string } | null;
    pass_type: { value: string; label: string } | null;
    currency: string;
    sort_order: number;
};

type AdCampaign = {
    id: number;
    uuid: string;
    name: string;
    platform: { value: string; label: string };
    campaign_status: { value: string; label: string };
    budget_daily: number | null;
    budget_total: number | null;
    budget_currency: string;
    runs_from: string | null;
    runs_until: string | null;
    metrics: Record<string, unknown> | null;
};

type Props = {
    event: EventDetail;
    weather: WeatherForecast[] | null;
};

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
        default:
            return 'outline';
    }
}

function formatFullDateTime(iso: string | null, timezone: string): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(iso));
}

function formatRange(
    starts: string | null,
    ends: string | null,
    timezone: string,
): string {
    if (!starts) {
        return '';
    }

    if (!ends) {
        return formatFullDateTime(starts, timezone);
    }

    const dayFmt = new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: timezone,
    });
    const timeFmt = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    });

    const startDate = new Date(starts);
    const endDate = new Date(ends);

    if (dayFmt.format(startDate) === dayFmt.format(endDate)) {
        return `${dayFmt.format(startDate)} · ${timeFmt.format(startDate)} – ${timeFmt.format(endDate)}`;
    }

    return `${dayFmt.format(startDate)} ${timeFmt.format(startDate)} → ${dayFmt.format(endDate)} ${timeFmt.format(endDate)}`;
}

function formatDuration(
    startsIso: string | null,
    endsIso: string | null,
): string | null {
    if (!startsIso || !endsIso) {
        return null;
    }

    const ms = new Date(endsIso).getTime() - new Date(startsIso).getTime();

    if (ms <= 0) {
        return null;
    }

    const minutes = Math.round(ms / 60000);

    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const remMinutes = minutes % 60;

    if (hours < 24) {
        return remMinutes
            ? `${hours}h ${remMinutes}m`
            : `${hours} hour${hours === 1 ? '' : 's'}`;
    }

    const days = Math.floor(hours / 24);
    const remHours = hours % 24;

    return remHours
        ? `${days}d ${remHours}h`
        : `${days} day${days === 1 ? '' : 's'}`;
}

function fullAddress(event: EventDetail): string {
    return [
        event.venue_name,
        event.address_line_1,
        event.address_line_2,
        [event.city, event.region, event.postal_code]
            .filter(Boolean)
            .join(', '),
        event.country_code,
    ]
        .filter(Boolean)
        .join(', ');
}

function locationLines(event: EventDetail): string[] {
    if (event.is_online) {
        return ['Online event'];
    }

    const lines: string[] = [];

    if (event.venue_name) {
        lines.push(event.venue_name);
    }

    const street = [event.address_line_1, event.address_line_2]
        .filter(Boolean)
        .join(', ');

    if (street) {
        lines.push(street);
    }

    const cityLine = [event.city, event.region, event.postal_code]
        .filter(Boolean)
        .join(', ');

    if (cityLine) {
        lines.push(cityLine);
    }

    if (event.country_code) {
        lines.push(event.country_code);
    }

    return lines.length > 0 ? lines : ['Location TBD'];
}

function StatusBanner({ event }: { event: EventDetail }) {
    const v = event.status.value;

    if (v === 'cancelled') {
        return (
            <BannerBox tone="destructive">
                This event has been cancelled.
            </BannerBox>
        );
    }

    if (v === 'postponed') {
        return (
            <BannerBox tone="warning">
                This event has been postponed. A new date will be announced.
            </BannerBox>
        );
    }

    if (v === 'sold_out') {
        return (
            <BannerBox tone="info">
                Sold out — no more tickets available.
            </BannerBox>
        );
    }

    if (v === 'ended') {
        return <BannerBox tone="muted">This event has ended.</BannerBox>;
    }

    if (v === 'draft') {
        return (
            <BannerBox tone="muted">
                This event is in draft mode — only visible to your team.
            </BannerBox>
        );
    }

    return null;
}

function BannerBox({
    tone,
    children,
}: {
    tone: 'destructive' | 'warning' | 'info' | 'muted';
    children: React.ReactNode;
}) {
    const toneCls = {
        destructive: 'border-destructive/30 bg-destructive/10 text-destructive',
        warning:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
        info: 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400',
        muted: 'border-border bg-muted text-muted-foreground',
    }[tone];

    return (
        <div
            className={cn(
                'flex items-center gap-2 rounded-md border px-4 py-3 text-sm font-medium',
                toneCls,
            )}
        >
            <AlertCircle className="size-4 shrink-0" />
            {children}
        </div>
    );
}

export default function EventShow({ event, weather }: Props) {
    const page = usePage<{
        currentOrganization?: {
            slug: string;
            name?: string;
            logoUrl?: string | null;
            isVerified?: boolean | null;
        } | null;
    }>();
    const teamSlug = page.props.currentOrganization?.slug ?? '';
    const eventsUrl = `/${teamSlug}/events`;
    const editUrl = `${eventsUrl}/${event.slug}/edit`;
    const organizerProfileUrl = teamSlug ? `/o/${teamSlug}` : null;
    const publicUrl = typeof window !== 'undefined' ? window.location.href : '';

    const pct =
        event.capacity != null
            ? Math.min(
                  100,
                  Math.round((event.tickets_sold_count / event.capacity) * 100),
              )
            : null;
    const fill =
        pct == null
            ? 'bg-muted-foreground/30'
            : pct >= 100
              ? 'bg-destructive'
              : pct >= 80
                ? 'bg-amber-500'
                : 'bg-primary';

    const duration = formatDuration(event.starts_at, event.ends_at);

    return (
        <>
            <Head title={event.seo.meta_title ?? event.name}>
                {event.seo.meta_description ? (
                    <meta
                        name="description"
                        content={event.seo.meta_description}
                    />
                ) : null}
                {event.seo.canonical_url ? (
                    <link rel="canonical" href={event.seo.canonical_url} />
                ) : null}
                <meta
                    property="og:title"
                    content={
                        event.seo.og_title ?? event.seo.meta_title ?? event.name
                    }
                />
                {(event.seo.og_description ?? event.seo.meta_description) ? (
                    <meta
                        property="og:description"
                        content={
                            event.seo.og_description ??
                            event.seo.meta_description ??
                            ''
                        }
                    />
                ) : null}
                {event.banner_image_url || event.seo.og_image_path ? (
                    <meta
                        property="og:image"
                        content={
                            event.seo.og_image_path ??
                            event.banner_image_url ??
                            ''
                        }
                    />
                ) : null}
                <meta property="og:type" content="event" />
                {publicUrl ? (
                    <meta property="og:url" content={publicUrl} />
                ) : null}
                <meta
                    name="twitter:card"
                    content={event.seo.twitter_card ?? 'summary_large_image'}
                />
                {event.seo.twitter_creator ? (
                    <meta
                        name="twitter:creator"
                        content={event.seo.twitter_creator}
                    />
                ) : null}
            </Head>

            <div className="flex flex-1 flex-col gap-6 p-4">
                {/* Two-column layout: 8/4 */}
                <div className="grid gap-6 lg:grid-cols-12">
                    {/* Left column (8) — hero + status + tabs */}
                    <div className="animate-in space-y-6 duration-500 fade-in slide-in-from-bottom-4 lg:col-span-8">
                        {/* Hero / media carousel */}
                        <EventMediaCarousel
                            items={event.media}
                            fallbackUrl={event.banner_image_url}
                            overlay={
                                <>
                                    {event.is_featured ? (
                                        <div className="absolute top-4 right-4 z-10 flex animate-in items-center gap-1.5 rounded-full bg-yellow-400 px-3 py-1 text-xs font-semibold text-yellow-950 shadow-lg delay-300 duration-500 fade-in slide-in-from-top-2">
                                            <Star className="size-3 fill-current" />
                                            Featured
                                        </div>
                                    ) : null}
                                    <div className="absolute right-0 bottom-0 left-0 z-10 animate-in space-y-3 p-5 text-white delay-200 duration-700 fade-in slide-in-from-bottom-4 sm:p-6">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge
                                                variant={statusVariant(
                                                    event.status.value,
                                                )}
                                                className="text-xs"
                                            >
                                                {event.status.label}
                                            </Badge>
                                            {event.visibility.value !==
                                            'public' ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-white/40 bg-white/10 text-xs text-white"
                                                >
                                                    {event.visibility.label}
                                                </Badge>
                                            ) : null}
                                            {event.category ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-white/40 bg-white/10 text-xs text-white"
                                                >
                                                    {event.category.name}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <h1 className="line-clamp-2 text-2xl font-bold tracking-tight drop-shadow-md sm:text-3xl">
                                            {event.name}
                                        </h1>
                                        {event.short_description ? (
                                            <p className="line-clamp-2 text-sm text-white/90">
                                                {event.short_description}
                                            </p>
                                        ) : null}
                                    </div>
                                </>
                            }
                        />

                        <StatusBanner event={event} />

                        <EventHighlightsSection event={event} />

                        <EventAmenitiesSection
                            amenities={event.amenities}
                            canEdit
                        />

                        <Tabs defaultValue="about" className="gap-4">
                            <TabsList>
                                <TabsTrigger value="about">About</TabsTrigger>
                                {event.lineup.length > 0 ? (
                                    <TabsTrigger value="lineup">
                                        Lineup
                                    </TabsTrigger>
                                ) : null}
                                {event.agenda.length > 0 ? (
                                    <TabsTrigger value="agenda">
                                        Agenda
                                    </TabsTrigger>
                                ) : null}
                                <TabsTrigger value="venue">
                                    {event.is_online ? 'Online' : 'Venue'}
                                </TabsTrigger>
                                <TabsTrigger value="tickets">
                                    Tickets
                                </TabsTrigger>
                                <TabsTrigger value="policies">
                                    Policies
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="about" className="mt-0">
                                <Card>
                                    <CardHeader>
                                        <SectionTitle
                                            icon={<BookText className="size-4" />}
                                        >
                                            About this event
                                        </SectionTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {event.description ? (
                                            <RichTextContent
                                                html={event.description}
                                            />
                                        ) : (
                                            <EmptyState
                                                tone="muted"
                                                icon={<BookText className="size-6" />}
                                                title="No description yet"
                                                description="Add a longer description to give attendees the full pitch."
                                            />
                                        )}

                                        {event.tags && event.tags.length > 0 ? (
                                            <>
                                                <Separator />
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <Tag className="size-3.5 text-muted-foreground" />
                                                    {event.tags.map((tag) => (
                                                        <Badge
                                                            key={tag}
                                                            variant="secondary"
                                                            className="font-normal"
                                                        >
                                                            {tag}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {event.lineup.length > 0 ? (
                                <TabsContent value="lineup" className="mt-0">
                                    <EventLineupSection
                                        artists={event.lineup}
                                    />
                                </TabsContent>
                            ) : null}

                            {event.agenda.length > 0 ? (
                                <TabsContent value="agenda" className="mt-0">
                                    <EventAgendaSection
                                        entries={event.agenda}
                                        timezone={event.timezone}
                                    />
                                </TabsContent>
                            ) : null}

                            <TabsContent value="venue" className="mt-0">
                                <Card>
                                    <CardHeader>
                                        <SectionTitle
                                            icon={
                                                event.is_online ? (
                                                    <Globe2 className="size-4" />
                                                ) : (
                                                    <MapPin className="size-4" />
                                                )
                                            }
                                        >
                                            {event.is_online
                                                ? 'How to join'
                                                : 'Where it happens'}
                                        </SectionTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {event.is_online ? (
                                            <div className="space-y-2 text-sm">
                                                <p>
                                                    This is an online event.
                                                    Ticket holders will receive
                                                    the join link before the
                                                    event starts.
                                                </p>
                                                {event.online_url ? (
                                                    <a
                                                        href={event.online_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="inline-block break-all text-primary underline"
                                                    >
                                                        {event.online_url}
                                                    </a>
                                                ) : null}
                                            </div>
                                        ) : (
                                            <>
                                                <div className="space-y-1 text-sm">
                                                    {locationLines(event).map(
                                                        (line, i) => (
                                                            <div key={i}>
                                                                {line}
                                                            </div>
                                                        ),
                                                    )}
                                                </div>

                                                <VenueMap
                                                    lat={event.latitude}
                                                    lng={event.longitude}
                                                    title={
                                                        event.venue_name ??
                                                        event.name
                                                    }
                                                    className="aspect-video"
                                                />

                                                {event.latitude != null &&
                                                event.longitude != null ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`https://www.google.com/maps/search/?api=1&query=${event.latitude},${event.longitude}`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        >
                                                            <MapPin className="size-4" />
                                                            Open in Google Maps
                                                        </a>
                                                    </Button>
                                                ) : null}
                                            </>
                                        )}
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent
                                value="policies"
                                className="mt-0 space-y-4"
                            >
                                {event.minimum_age ? (
                                    <Card>
                                        <CardHeader>
                                            <SectionTitle
                                                icon={
                                                    <ShieldCheck className="size-4" />
                                                }
                                            >
                                                Age requirement
                                            </SectionTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-sm">
                                                Attendees must be{' '}
                                                <strong>
                                                    {event.minimum_age}+
                                                </strong>{' '}
                                                years old. Government-issued ID
                                                will be required at entry.
                                            </p>
                                        </CardContent>
                                    </Card>
                                ) : null}

                                {event.refund_policy ? (
                                    <Card>
                                        <CardHeader>
                                            <SectionTitle
                                                icon={<Info className="size-4" />}
                                            >
                                                Refund policy
                                            </SectionTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 text-sm leading-relaxed whitespace-pre-line">
                                            {event.refund_policy}
                                        </CardContent>
                                    </Card>
                                ) : null}

                                {event.terms ? (
                                    <Card>
                                        <CardHeader>
                                            <SectionTitle
                                                icon={
                                                    <FileText className="size-4" />
                                                }
                                            >
                                                Terms
                                            </SectionTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 text-sm leading-relaxed whitespace-pre-line">
                                            {event.terms}
                                        </CardContent>
                                    </Card>
                                ) : null}

                                {!event.minimum_age &&
                                !event.refund_policy &&
                                !event.terms ? (
                                    <Card>
                                        <CardContent className="px-4 py-4">
                                            <EmptyState
                                                tone="muted"
                                                icon={
                                                    <FileText className="size-6" />
                                                }
                                                title="No policies yet"
                                                description="Add a refund policy or terms so attendees know what to expect."
                                            />
                                        </CardContent>
                                    </Card>
                                ) : null}
                            </TabsContent>

                            <TabsContent value="tickets" className="mt-0">
                                <EventTicketManager
                                    // TODO: two TicketCategory types diverged across files; unify and drop this cast.
                                    categories={event.ticket_categories as unknown as ComponentProps<typeof EventTicketManager>['categories']}
                                    teamSlug={teamSlug}
                                    eventSlug={event.slug}
                                />
                            </TabsContent>
                        </Tabs>

                        <EventSponsorsSection
                            sponsors={event.sponsors}
                            canEdit
                        />
                    </div>

                    {/* Sticky sidebar (col-span-4) */}
                    <aside className="animate-in space-y-4 delay-100 duration-500 fade-in slide-in-from-right-4 lg:sticky lg:top-4 lg:col-span-4 lg:self-start">
                        {/* Hosted-by card linking to the public organizer
                            profile (Eventbrite-style attribution + jump). */}
                        {organizerProfileUrl && page.props.currentOrganization ? (
                            <Link
                                href={organizerProfileUrl}
                                className="group flex items-center gap-3 rounded-lg border bg-card p-3 transition hover:border-primary/40 hover:shadow-sm"
                            >
                                <div className="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted">
                                    {page.props.currentOrganization.logoUrl ? (
                                        <img
                                            src={page.props.currentOrganization.logoUrl}
                                            alt=""
                                            className="size-full object-contain p-0.5"
                                        />
                                    ) : (
                                        <span className="text-xs font-semibold text-primary">
                                            {(page.props.currentOrganization.name ?? teamSlug)
                                                .split(/\s+/)
                                                .slice(0, 2)
                                                .map((w) => w[0]?.toUpperCase() ?? '')
                                                .join('')}
                                        </span>
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        Hosted by
                                    </p>
                                    <p className="line-clamp-1 text-sm font-semibold group-hover:text-primary">
                                        {page.props.currentOrganization.name ?? teamSlug}
                                        {page.props.currentOrganization.isVerified ? (
                                            <span
                                                title="Verified organizer"
                                                className="ml-1 text-emerald-600 dark:text-emerald-400"
                                            >
                                                ✓
                                            </span>
                                        ) : null}
                                    </p>
                                </div>
                            </Link>
                        ) : null}

                        {/* Action buttons */}
                        <div className="flex flex-wrap items-center gap-2">
                            <Button size="sm" asChild className="flex-1">
                                <Link href={editUrl}>
                                    <Pencil className="size-4" />
                                    Edit event
                                </Link>
                            </Button>
                            <ShareMenu url={publicUrl} title={event.name} />
                            {event.starts_at && event.ends_at ? (
                                <AddToCalendarButton
                                    uid={event.event_id}
                                    title={event.name}
                                    description={
                                        event.short_description ?? undefined
                                    }
                                    location={fullAddress(event)}
                                    startIso={event.starts_at}
                                    endIso={event.ends_at}
                                />
                            ) : null}
                            <EventSeoModal
                                seo={event.seo}
                                eventName={event.name}
                                eventDescription={event.description}
                                eventShortDescription={event.short_description}
                                eventBannerUrl={event.banner_image_url}
                                eventSlug={event.slug}
                            >
                                <Button variant="outline" size="sm">
                                    <Settings2 className="size-4" />
                                    SEO
                                </Button>
                            </EventSeoModal>
                        </div>

                        {/* Quick facts strip */}
                        <Card>
                            <CardContent className="grid grid-cols-3 gap-2 px-4 text-center text-xs">
                                {event.starts_at ? (
                                    <div className="space-y-1">
                                        <CalendarDays className="mx-auto size-4 text-muted-foreground" />
                                        <div className="font-medium">
                                            {new Intl.DateTimeFormat(
                                                undefined,
                                                {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    timeZone: event.timezone,
                                                },
                                            ).format(new Date(event.starts_at))}
                                        </div>
                                    </div>
                                ) : null}
                                {duration ? (
                                    <div className="space-y-1">
                                        <Clock className="mx-auto size-4 text-muted-foreground" />
                                        <div className="font-medium">
                                            {duration}
                                        </div>
                                    </div>
                                ) : null}
                                <div className="space-y-1">
                                    {event.is_online ? (
                                        <Globe2 className="mx-auto size-4 text-muted-foreground" />
                                    ) : (
                                        <MapPin className="mx-auto size-4 text-muted-foreground" />
                                    )}
                                    <div className="truncate font-medium">
                                        {event.is_online
                                            ? 'Online'
                                            : (event.city ?? 'TBD')}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {event.starts_at &&
                        event.status.value !== 'cancelled' &&
                        event.status.value !== 'ended' ? (
                            <Card>
                                <CardContent className="space-y-3 px-6">
                                    <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <Timer className="size-4" />
                                        Time until event
                                    </div>
                                    <CountdownTimer
                                        targetIso={event.starts_at}
                                        pastLabel="Event is happening now"
                                    />
                                </CardContent>
                            </Card>
                        ) : null}

                        <Card>
                            <CardContent className="space-y-4 px-6 text-sm">
                                <SidebarRow
                                    icon={<CalendarDays className="size-4" />}
                                    label="Date & time"
                                >
                                    <div>
                                        {formatRange(
                                            event.starts_at,
                                            event.ends_at,
                                            event.timezone,
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {event.timezone}
                                        {duration ? ` · ${duration}` : ''}
                                    </div>
                                </SidebarRow>

                                <Separator />

                                <SidebarRow
                                    icon={
                                        event.is_online ? (
                                            <Globe2 className="size-4" />
                                        ) : (
                                            <MapPin className="size-4" />
                                        )
                                    }
                                    label={
                                        event.is_online ? 'Online' : 'Location'
                                    }
                                >
                                    {locationLines(event).map((line, i) => (
                                        <div key={i}>{line}</div>
                                    ))}
                                </SidebarRow>

                                <Separator />

                                <SidebarRow
                                    icon={
                                        event.capacity != null ? (
                                            <Users className="size-4" />
                                        ) : (
                                            <Ticket className="size-4" />
                                        )
                                    }
                                    label="Tickets"
                                >
                                    {event.capacity != null ? (
                                        <>
                                            <div>
                                                {event.tickets_sold_count.toLocaleString()}{' '}
                                                /{' '}
                                                {event.capacity.toLocaleString()}{' '}
                                                sold ({pct}%)
                                            </div>
                                            <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className={cn(
                                                        'h-full transition-all duration-500',
                                                        fill,
                                                    )}
                                                    style={{
                                                        width: `${pct}%`,
                                                    }}
                                                />
                                            </div>
                                        </>
                                    ) : (
                                        <div>
                                            {event.tickets_sold_count.toLocaleString()}{' '}
                                            sold · unlimited capacity
                                        </div>
                                    )}
                                </SidebarRow>

                                {event.sales_start_at || event.sales_end_at ? (
                                    <>
                                        <Separator />
                                        <SidebarRow
                                            icon={<Ticket className="size-4" />}
                                            label="Sales window"
                                        >
                                            {event.sales_start_at ? (
                                                <div>
                                                    Opens{' '}
                                                    {formatFullDateTime(
                                                        event.sales_start_at,
                                                        event.timezone,
                                                    )}
                                                </div>
                                            ) : null}
                                            {event.sales_end_at ? (
                                                <div>
                                                    Closes{' '}
                                                    {formatFullDateTime(
                                                        event.sales_end_at,
                                                        event.timezone,
                                                    )}
                                                </div>
                                            ) : null}
                                        </SidebarRow>
                                    </>
                                ) : null}

                                {event.contact_email || event.contact_phone ? (
                                    <>
                                        <Separator />
                                        <SidebarRow
                                            icon={<Mail className="size-4" />}
                                            label="Contact organiser"
                                        >
                                            {event.contact_email ? (
                                                <a
                                                    href={`mailto:${event.contact_email}`}
                                                    className="flex items-center gap-1.5 hover:underline"
                                                >
                                                    <Mail className="size-3 text-muted-foreground" />
                                                    {event.contact_email}
                                                </a>
                                            ) : null}
                                            {event.contact_phone ? (
                                                <div className="flex items-center gap-1.5">
                                                    <Phone className="size-3 text-muted-foreground" />
                                                    {event.contact_phone}
                                                </div>
                                            ) : null}
                                        </SidebarRow>
                                    </>
                                ) : null}
                            </CardContent>
                        </Card>

                        <EventWeatherPanel forecasts={weather} />

                        {/* Operations — quick jumps into the cross-event
                            surfaces, pre-filtered by this event so the
                            organizer doesn't have to re-select it. */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Operations</CardTitle>
                                <CardDescription>
                                    Day-to-day surfaces, scoped to this event.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2 text-sm">
                                <OpsLink
                                    href={`/${teamSlug}/attendees?event=${event.slug}`}
                                    icon={<Users className="size-4" />}
                                    label="Attendees"
                                    sub="Roster, contact, CSV export"
                                />
                                <OpsLink
                                    href={`/${teamSlug}/check-in`}
                                    icon={<ScanLine className="size-4" />}
                                    label="Check-in"
                                    sub="Scan tickets at the gate"
                                />
                                <OpsLink
                                    href={`/${teamSlug}/orders?event=${event.slug}`}
                                    icon={<Receipt className="size-4" />}
                                    label="Orders"
                                    sub="Filtered to this event"
                                />
                                <OpsLink
                                    href={`/${teamSlug}/discounts`}
                                    icon={<Tag className="size-4" />}
                                    label="Discounts"
                                    sub="Add promos to this event's tickets"
                                />
                                <OpsLink
                                    href={`/${teamSlug}/finance?event=${event.slug}`}
                                    icon={<CircleDollarSign className="size-4" />}
                                    label="Finance"
                                    sub="Revenue for this event + refunds queue"
                                />
                            </CardContent>
                        </Card>

                        <EventAnalyticsDashboard
                            teamSlug={teamSlug}
                            eventSlug={event.slug}
                        />

                        <EventAdManagementPanel
                            campaigns={event.ad_campaigns}
                            teamSlug={teamSlug}
                            eventSlug={event.slug}
                            eventName={event.name}
                            eventUrl={publicUrl}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

function OpsLink({
    href,
    icon,
    label,
    sub,
}: {
    href: string;
    icon: React.ReactNode;
    label: string;
    sub: string;
}) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-md border bg-card p-2 transition hover:bg-muted/60"
        >
            <span className="rounded bg-muted p-1.5 text-muted-foreground">{icon}</span>
            <span className="flex-1">
                <span className="block font-medium">{label}</span>
                <span className="block text-xs text-muted-foreground">{sub}</span>
            </span>
            <ArrowRight className="size-3.5 text-muted-foreground" />
        </Link>
    );
}

function SidebarRow({
    icon,
    label,
    children,
}: {
    icon: React.ReactNode;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {icon}
                {label}
            </div>
            <div className="text-sm">{children}</div>
        </div>
    );
}

EventShow.layout = (props: {
    event?: EventDetail;
    currentOrganization?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'My Events',
            href: props.currentOrganization ? `/${props.currentOrganization.slug}/events` : '/',
        },
        {
            title: props.event?.name ?? 'Event',
            href: '#',
        },
    ],
});
