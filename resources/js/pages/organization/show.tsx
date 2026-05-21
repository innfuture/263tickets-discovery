import { Head, Link } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    CheckCircle2,
    ExternalLink,
    Facebook,
    Globe2,
    Globe as GlobeIcon,
    Instagram,
    Linkedin,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Twitter,
    Users,
    Youtube,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { SectionTitle } from '@/components/ui/section-title';

type Organization = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    organizer_type: { value: string; label: string } | null;
    logo_url: string | null;
    banner_url: string | null;
    contact_email: string | null;
    support_email: string | null;
    contact_phone: string | null;
    website_url: string | null;
    social_links: Record<string, string>;
    address: {
        line_1: string | null;
        line_2: string | null;
        city: string | null;
        region: string | null;
        country_code: string | null;
        postal_code: string | null;
    };
    founded_year: number | null;
    is_verified: boolean;
    followers_count: number;
};

type EventCard = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    starts_at: string | null;
    city: string | null;
    country_code: string | null;
    is_online: boolean;
    banner_image_url: string | null;
};

const SOCIAL_ICONS: Record<
    string,
    React.ComponentType<{ className?: string }>
> = {
    twitter: Twitter,
    instagram: Instagram,
    facebook: Facebook,
    linkedin: Linkedin,
    tiktok: ExternalLink,
    youtube: Youtube,
    website: GlobeIcon,
};

/**
 * Public organizer profile — the page attendees land on when they tap an
 * organizer's name on an event card, or open a /o/{slug} URL shared in
 * a Discord/WhatsApp thread.
 *
 * Three vertical blocks:
 *   1. Banner + identity card (logo, name, verified badge, tagline, type)
 *   2. About + contact + social grid in the right rail
 *   3. Upcoming events + Past events
 */
export default function OrganizationShow({
    organization,
    upcomingEvents,
    pastEvents,
    viewerCanEdit,
}: {
    organization: Organization;
    upcomingEvents: EventCard[];
    pastEvents: EventCard[];
    viewerCanEdit: boolean;
}) {
    const title = `${organization.name} — events organizer`;

    return (
        <>
            <Head title={title}>
                {organization.tagline ? (
                    <meta
                        name="description"
                        content={organization.tagline}
                    />
                ) : null}
                <meta property="og:title" content={organization.name} />
                {organization.tagline ? (
                    <meta property="og:description" content={organization.tagline} />
                ) : null}
                {organization.banner_url ? (
                    <meta property="og:image" content={organization.banner_url} />
                ) : null}
            </Head>

            <div className="mx-auto flex max-w-5xl flex-1 flex-col gap-6 p-4">
                <BannerHero org={organization} viewerCanEdit={viewerCanEdit} />

                <div className="grid gap-6 lg:grid-cols-12">
                    <div className="space-y-6 lg:col-span-8">
                        <Card>
                            <CardHeader>
                                <SectionTitle icon={<Building2 className="size-4" />}>
                                    About
                                </SectionTitle>
                            </CardHeader>
                            <CardContent>
                                {organization.description ? (
                                    <p className="text-sm leading-relaxed whitespace-pre-line">
                                        {organization.description}
                                    </p>
                                ) : organization.tagline ? (
                                    <p className="text-sm leading-relaxed text-muted-foreground italic">
                                        {organization.tagline}
                                    </p>
                                ) : (
                                    <EmptyState
                                        tone="muted"
                                        icon={<Building2 className="size-6" />}
                                        title="No description yet"
                                        description="This organizer hasn't written an about section."
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <SectionTitle
                                    icon={<CalendarDays className="size-4" />}
                                    count={upcomingEvents.length}
                                >
                                    Upcoming events
                                </SectionTitle>
                            </CardHeader>
                            <CardContent>
                                {upcomingEvents.length === 0 ? (
                                    <EmptyState
                                        tone="muted"
                                        icon={<CalendarDays className="size-6" />}
                                        title="No upcoming events"
                                        description="Check back later — or follow this organizer to get notified when they publish next."
                                    />
                                ) : (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {upcomingEvents.map((e) => (
                                            <EventCardTile key={e.id} event={e} />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {pastEvents.length > 0 ? (
                            <Card>
                                <CardHeader>
                                    <SectionTitle
                                        icon={<CalendarDays className="size-4" />}
                                        count={pastEvents.length}
                                    >
                                        Past events
                                    </SectionTitle>
                                </CardHeader>
                                <CardContent>
                                    <ul className="divide-y">
                                        {pastEvents.map((e) => (
                                            <li
                                                key={e.id}
                                                className="flex items-center justify-between gap-2 py-2"
                                            >
                                                <div className="min-w-0">
                                                    <Link
                                                        href={`/events/${e.slug}`}
                                                        className="line-clamp-1 text-sm font-medium hover:text-primary hover:underline"
                                                    >
                                                        {e.name}
                                                    </Link>
                                                    <p className="text-xs text-muted-foreground">
                                                        {e.starts_at
                                                            ? new Intl.DateTimeFormat(undefined, {
                                                                  month: 'short',
                                                                  day: 'numeric',
                                                                  year: 'numeric',
                                                              }).format(new Date(e.starts_at))
                                                            : '—'}
                                                        {e.city ? ` · ${e.city}` : ''}
                                                    </p>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>

                    <aside className="space-y-4 lg:col-span-4">
                        <ContactCard org={organization} />
                        {Object.keys(organization.social_links).length > 0 ? (
                            <SocialCard
                                links={organization.social_links}
                                websiteUrl={organization.website_url}
                            />
                        ) : null}
                        {organization.address.city ||
                        organization.address.country_code ? (
                            <LocationCard address={organization.address} />
                        ) : null}
                    </aside>
                </div>
            </div>
        </>
    );
}

function BannerHero({
    org,
    viewerCanEdit,
}: {
    org: Organization;
    viewerCanEdit: boolean;
}) {
    return (
        <Card className="overflow-hidden">
            <div className="relative aspect-5/2 w-full overflow-hidden bg-linear-to-br from-primary/30 via-primary/10 to-transparent">
                {org.banner_url ? (
                    <img
                        src={org.banner_url}
                        alt=""
                        className="size-full object-cover"
                    />
                ) : null}
            </div>
            <CardContent className="relative px-6">
                <div className="-mt-12 flex flex-col items-start gap-4 sm:flex-row sm:items-end">
                    <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border-4 border-background bg-card shadow-sm">
                        {org.logo_url ? (
                            <img
                                src={org.logo_url}
                                alt={`${org.name} logo`}
                                className="size-full object-contain p-2"
                            />
                        ) : (
                            <span className="text-2xl font-bold text-primary">
                                {org.name
                                    .split(/\s+/)
                                    .slice(0, 2)
                                    .map((w) => w[0]?.toUpperCase() ?? '')
                                    .join('')}
                            </span>
                        )}
                    </div>

                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {org.name}
                            </h1>
                            {org.is_verified ? (
                                <Badge
                                    variant="outline"
                                    className="border-emerald-400/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                                    title="Verified by Discovery"
                                >
                                    <CheckCircle2 className="size-3.5" />
                                    Verified
                                </Badge>
                            ) : null}
                            {org.organizer_type ? (
                                <Badge variant="secondary" className="text-xs">
                                    {org.organizer_type.label}
                                </Badge>
                            ) : null}
                        </div>
                        {org.tagline ? (
                            <p className="text-sm leading-relaxed text-muted-foreground">
                                {org.tagline}
                            </p>
                        ) : null}
                        <div className="flex flex-wrap items-center gap-3 pt-1 text-xs text-muted-foreground">
                            <span className="inline-flex items-center gap-1">
                                <Users className="size-3.5" />
                                {org.followers_count.toLocaleString()} followers
                            </span>
                            {org.founded_year ? (
                                <span>Founded {org.founded_year}</span>
                            ) : null}
                        </div>
                    </div>

                    {viewerCanEdit ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/${org.slug}/organization/edit`}>
                                <Pencil className="size-4" />
                                Edit profile
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

function ContactCard({ org }: { org: Organization }) {
    const items: { icon: React.ReactNode; label: string; href?: string }[] = [];

    if (org.contact_email) {
        items.push({
            icon: <Mail className="size-4" />,
            label: org.contact_email,
            href: `mailto:${org.contact_email}`,
        });
    }
    if (org.support_email && org.support_email !== org.contact_email) {
        items.push({
            icon: <Mail className="size-4" />,
            label: `${org.support_email} (attendee support)`,
            href: `mailto:${org.support_email}`,
        });
    }
    if (org.contact_phone) {
        items.push({
            icon: <Phone className="size-4" />,
            label: org.contact_phone,
            href: `tel:${org.contact_phone.replace(/\s+/g, '')}`,
        });
    }

    if (items.length === 0) return null;

    return (
        <Card>
            <CardHeader>
                <SectionTitle icon={<Mail className="size-4" />}>
                    Contact
                </SectionTitle>
            </CardHeader>
            <CardContent>
                <ul className="space-y-2 text-sm">
                    {items.map((it, i) => (
                        <li
                            key={i}
                            className="flex items-center gap-2 text-muted-foreground"
                        >
                            {it.icon}
                            {it.href ? (
                                <a
                                    href={it.href}
                                    className="text-foreground hover:underline"
                                >
                                    {it.label}
                                </a>
                            ) : (
                                <span>{it.label}</span>
                            )}
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

function SocialCard({
    links,
    websiteUrl,
}: {
    links: Record<string, string>;
    websiteUrl: string | null;
}) {
    // Build a single, ordered list — website first if it exists, then
    // platforms in the standard ordering. Skips entries without a URL.
    const order = [
        'website',
        'twitter',
        'instagram',
        'facebook',
        'youtube',
        'tiktok',
        'linkedin',
    ];
    const entries = order
        .map((k) => ({ key: k, url: k === 'website' ? websiteUrl : links[k] }))
        .filter((e): e is { key: string; url: string } => !!e.url);

    if (entries.length === 0) return null;

    return (
        <Card>
            <CardHeader>
                <SectionTitle icon={<Globe2 className="size-4" />}>
                    Follow
                </SectionTitle>
            </CardHeader>
            <CardContent>
                <div className="flex flex-wrap gap-2">
                    {entries.map(({ key, url }) => {
                        const Icon = SOCIAL_ICONS[key] ?? ExternalLink;

                        return (
                            <a
                                key={key}
                                href={url}
                                target="_blank"
                                rel="noreferrer noopener"
                                className="inline-flex size-9 items-center justify-center rounded-full border bg-card text-muted-foreground transition hover:border-primary/40 hover:text-primary"
                                title={key}
                                aria-label={key}
                            >
                                <Icon className="size-4" />
                            </a>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}

function LocationCard({ address }: { address: Organization['address'] }) {
    const lines = [
        address.line_1,
        address.line_2,
        [address.city, address.region, address.postal_code]
            .filter(Boolean)
            .join(', '),
        address.country_code,
    ].filter(Boolean);

    return (
        <Card>
            <CardHeader>
                <SectionTitle icon={<MapPin className="size-4" />}>
                    Address
                </SectionTitle>
            </CardHeader>
            <CardContent>
                <address className="space-y-0.5 text-sm not-italic text-muted-foreground">
                    {lines.map((l, i) => (
                        <div key={i}>{l}</div>
                    ))}
                </address>
            </CardContent>
        </Card>
    );
}

function EventCardTile({ event }: { event: EventCard }) {
    return (
        <Link
            href={`/events/${event.slug}`}
            className="group flex flex-col overflow-hidden rounded-lg border bg-card transition hover:border-primary/40 hover:shadow-sm"
        >
            <div className="relative aspect-video w-full overflow-hidden bg-muted">
                {event.banner_image_url ? (
                    <img
                        src={event.banner_image_url}
                        alt=""
                        className="size-full object-cover transition group-hover:scale-105"
                        loading="lazy"
                    />
                ) : (
                    <div className="size-full bg-linear-to-br from-primary/30 via-primary/10 to-transparent" />
                )}
            </div>
            <div className="space-y-1 p-3">
                <p className="line-clamp-2 text-sm font-semibold">
                    {event.name}
                </p>
                {event.short_description ? (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {event.short_description}
                    </p>
                ) : null}
                <div className="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-muted-foreground">
                    {event.starts_at ? (
                        <span className="inline-flex items-center gap-1">
                            <CalendarDays className="size-3" />
                            {new Intl.DateTimeFormat(undefined, {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                            }).format(new Date(event.starts_at))}
                        </span>
                    ) : null}
                    <span className="inline-flex items-center gap-1">
                        <MapPin className="size-3" />
                        {event.is_online
                            ? 'Online'
                            : (event.city ?? 'TBD')}
                    </span>
                </div>
            </div>
        </Link>
    );
}

// The public page intentionally uses no layout — visitors don't need the
// authenticated sidebar/header for a profile they may have arrived at
// from a shared link without an account.
OrganizationShow.layout = null;
