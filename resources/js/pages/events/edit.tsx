import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye } from 'lucide-react';
import { useState } from 'react';
import { EventAgendaManager } from '@/components/event-agenda-manager';
import { EventLineupManager } from '@/components/event-lineup-manager';
import { EventMediaManager } from '@/components/event-media-manager';
import { FieldError } from '@/components/field-error';
import Heading from '@/components/heading';
import ImageDropzone from '@/components/image-dropzone';
import { RichTextEditor } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';

type EnumOption = { value: string; label: string };
type CategoryOption = { id: number; name: string; slug: string };

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
    starts_at: string | null;
    ends_at: string | null;
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
    capacity: number | null;
    minimum_age: number | null;
    doors_open_at: string | null;
    parking_info: string | null;
    age_requirement_details: string | null;
    refund_policy: string | null;
    terms: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    category: { id: number; name: string; slug: string } | null;
    lineup: Array<{
        id: number;
        name: string;
        role: string | null;
        bio: string | null;
        image_path: string | null;
        social_url: string | null;
        is_headliner: boolean;
    }>;
    agenda: Array<{
        id: number;
        starts_at: string | null;
        ends_at: string | null;
        title: string;
        description: string | null;
        host_name: string | null;
        host_role: string | null;
    }>;
    media: Array<{
        id: number;
        type: string;
        url: string | null;
        caption: string | null;
        is_primary: boolean;
    }>;
};

type Props = {
    event: EventDetail;
    visibilities: EnumOption[];
    categories: CategoryOption[];
    statuses: EnumOption[];
};

const TIMEZONE_OPTIONS: string[] = [
    'UTC',
    'Africa/Harare',
    'Africa/Johannesburg',
    'Africa/Lagos',
    'Africa/Nairobi',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
    'America/Los_Angeles',
    'America/Sao_Paulo',
    'Asia/Dubai',
    'Asia/Tokyo',
    'Asia/Singapore',
    'Australia/Sydney',
];

const NONE_VALUE = '__none__';

function isoToLocalInput(iso: string | null, timezone: string): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);

    const part = (type: string) =>
        parts.find((p) => p.type === type)?.value ?? '00';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

function FieldLabel({
    htmlFor,
    error,
    children,
    optional,
}: {
    htmlFor?: string;
    error?: string | null;
    children: React.ReactNode;
    optional?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <Label htmlFor={htmlFor}>
                {children}
                {optional ? (
                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                        (optional)
                    </span>
                ) : null}
            </Label>
            <FieldError message={error} />
        </div>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <h2 className="text-base font-semibold">{title}</h2>
                {description ? (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">{children}</CardContent>
        </Card>
    );
}

export default function EventEdit({ event, visibilities, categories }: Props) {
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const teamSlug = page.props.currentTeam?.slug ?? '';
    const eventsUrl = `/${teamSlug}/events`;
    const action = `${eventsUrl}/${event.slug}`;
    const showUrl = `${eventsUrl}/${event.slug}`;

    const [name, setName] = useState<string>(event.name);
    const [shortDescription, setShortDescription] = useState<string>(
        event.short_description ?? '',
    );
    const [startsAt, setStartsAt] = useState<string>(
        isoToLocalInput(event.starts_at, event.timezone),
    );
    const [endsAt, setEndsAt] = useState<string>(
        isoToLocalInput(event.ends_at, event.timezone),
    );
    const [salesStartsAt, setSalesStartsAt] = useState<string>(
        isoToLocalInput(event.sales_start_at, event.timezone),
    );
    const [salesEndsAt, setSalesEndsAt] = useState<string>(
        isoToLocalInput(event.sales_end_at, event.timezone),
    );
    const [doorsOpenAt, setDoorsOpenAt] = useState<string>(
        isoToLocalInput(event.doors_open_at, event.timezone),
    );
    const [timezone, setTimezone] = useState<string>(event.timezone);
    const [visibility, setVisibility] = useState<string>(
        event.visibility.value,
    );
    const [categoryId, setCategoryId] = useState<string>(
        event.category ? String(event.category.id) : '',
    );
    const [isOnline, setIsOnline] = useState<boolean>(event.is_online);
    const [isFeatured, setIsFeatured] = useState<boolean>(event.is_featured);
    const [bannerClientError, setBannerClientError] = useState<string | null>(
        null,
    );

    const handleStartsAtChange = (value: string) => {
        setStartsAt(value);
        if (endsAt && value && endsAt < value) {
            setEndsAt('');
        }
    };

    return (
        <>
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex animate-in flex-wrap items-center justify-between gap-2 duration-500 fade-in slide-in-from-top-2">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={showUrl}>
                                <ArrowLeft className="size-4" />
                                Back
                            </Link>
                        </Button>
                        <Heading
                            title={`Edit · ${event.name}`}
                            description="Update event details. Changes save when you click Save."
                        />
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={showUrl}>
                            <Eye className="size-4" />
                            Preview
                        </Link>
                    </Button>
                </div>

                <Form action={action} method="patch" className="space-y-6">
                    {({ errors, processing }) => (
                        <>
                            <div className="grid animate-in gap-6 duration-500 fade-in slide-in-from-bottom-2 lg:grid-cols-3">
                                <div className="space-y-6 lg:col-span-2">
                                    <Section
                                        title="Basics"
                                        description="The essentials people see first."
                                    >
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-name"
                                                error={errors.name}
                                            >
                                                Event name
                                            </FieldLabel>
                                            <Input
                                                id="event-name"
                                                name="name"
                                                value={name}
                                                onChange={(e) =>
                                                    setName(e.target.value)
                                                }
                                                required
                                                maxLength={126}
                                                aria-invalid={!!errors.name}
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-category"
                                                error={errors.category_id}
                                                optional
                                            >
                                                Category
                                            </FieldLabel>
                                            {categoryId ? (
                                                <input
                                                    type="hidden"
                                                    name="category_id"
                                                    value={categoryId}
                                                />
                                            ) : null}
                                            <Select
                                                value={categoryId || NONE_VALUE}
                                                onValueChange={(v) =>
                                                    setCategoryId(
                                                        v === NONE_VALUE
                                                            ? ''
                                                            : v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="event-category"
                                                    className="w-full"
                                                    aria-invalid={
                                                        !!errors.category_id
                                                    }
                                                >
                                                    <SelectValue placeholder="Select a category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={NONE_VALUE}
                                                    >
                                                        Uncategorized
                                                    </SelectItem>
                                                    {categories.map((c) => (
                                                        <SelectItem
                                                            key={c.id}
                                                            value={String(c.id)}
                                                        >
                                                            {c.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-short-description"
                                                error={errors.short_description}
                                                optional
                                            >
                                                Short description
                                            </FieldLabel>
                                            <Textarea
                                                id="event-short-description"
                                                name="short_description"
                                                value={shortDescription}
                                                onChange={(e) =>
                                                    setShortDescription(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="One or two lines that appear on cards and previews"
                                                maxLength={280}
                                                aria-invalid={
                                                    !!errors.short_description
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                error={
                                                    errors.banner_image ??
                                                    bannerClientError
                                                }
                                                optional
                                            >
                                                Banner image
                                            </FieldLabel>
                                            {event.banner_image_url ? (
                                                <div className="relative aspect-video w-full overflow-hidden rounded-md border bg-muted">
                                                    <img
                                                        src={
                                                            event.banner_image_url
                                                        }
                                                        alt="Current banner"
                                                        className="size-full object-cover"
                                                        onError={(e) => {
                                                            e.currentTarget.style.display =
                                                                'none';
                                                        }}
                                                    />
                                                    <div className="absolute bottom-2 left-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">
                                                        Current banner
                                                    </div>
                                                </div>
                                            ) : null}
                                            <ImageDropzone
                                                name="banner_image"
                                                hasError={
                                                    !!(
                                                        errors.banner_image ??
                                                        bannerClientError
                                                    )
                                                }
                                                onValidationError={
                                                    setBannerClientError
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Upload a new image to replace
                                                the current one.
                                            </p>
                                        </div>
                                    </Section>

                                    <Section
                                        title="About"
                                        description="The full pitch. Format with headings, lists, and links."
                                    >
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                error={errors.description}
                                                optional
                                            >
                                                Long description
                                            </FieldLabel>
                                            <RichTextEditor
                                                name="description"
                                                initialHtml={
                                                    event.description ?? ''
                                                }
                                                hasError={!!errors.description}
                                            />
                                        </div>
                                    </Section>

                                    <Section
                                        title="Schedule"
                                        description="When the event happens and when tickets are on sale."
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-starts-at"
                                                    error={errors.starts_at}
                                                >
                                                    Starts at
                                                </FieldLabel>
                                                <Input
                                                    id="event-starts-at"
                                                    name="starts_at"
                                                    type="datetime-local"
                                                    required
                                                    value={startsAt}
                                                    onChange={(e) =>
                                                        handleStartsAtChange(
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-invalid={
                                                        !!errors.starts_at
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-ends-at"
                                                    error={errors.ends_at}
                                                >
                                                    Ends at
                                                </FieldLabel>
                                                <Input
                                                    id="event-ends-at"
                                                    name="ends_at"
                                                    type="datetime-local"
                                                    required
                                                    value={endsAt}
                                                    onChange={(e) =>
                                                        setEndsAt(
                                                            e.target.value,
                                                        )
                                                    }
                                                    min={startsAt || undefined}
                                                    aria-invalid={
                                                        !!errors.ends_at
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-timezone"
                                                error={errors.timezone}
                                            >
                                                Timezone
                                            </FieldLabel>
                                            <input
                                                type="hidden"
                                                name="timezone"
                                                value={timezone}
                                            />
                                            <Select
                                                value={timezone}
                                                onValueChange={setTimezone}
                                            >
                                                <SelectTrigger
                                                    id="event-timezone"
                                                    className="w-full"
                                                    aria-invalid={
                                                        !!errors.timezone
                                                    }
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {TIMEZONE_OPTIONS.includes(
                                                        timezone,
                                                    ) ? null : (
                                                        <SelectItem
                                                            value={timezone}
                                                        >
                                                            {timezone}
                                                        </SelectItem>
                                                    )}
                                                    {TIMEZONE_OPTIONS.map(
                                                        (tz) => (
                                                            <SelectItem
                                                                key={tz}
                                                                value={tz}
                                                            >
                                                                {tz}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-sales-start"
                                                    error={
                                                        errors.sales_start_at
                                                    }
                                                    optional
                                                >
                                                    Sales open
                                                </FieldLabel>
                                                <Input
                                                    id="event-sales-start"
                                                    name="sales_start_at"
                                                    type="datetime-local"
                                                    value={salesStartsAt}
                                                    onChange={(e) =>
                                                        setSalesStartsAt(
                                                            e.target.value,
                                                        )
                                                    }
                                                    aria-invalid={
                                                        !!errors.sales_start_at
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-sales-end"
                                                    error={errors.sales_end_at}
                                                    optional
                                                >
                                                    Sales close
                                                </FieldLabel>
                                                <Input
                                                    id="event-sales-end"
                                                    name="sales_end_at"
                                                    type="datetime-local"
                                                    value={salesEndsAt}
                                                    onChange={(e) =>
                                                        setSalesEndsAt(
                                                            e.target.value,
                                                        )
                                                    }
                                                    min={
                                                        salesStartsAt ||
                                                        undefined
                                                    }
                                                    aria-invalid={
                                                        !!errors.sales_end_at
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </Section>

                                    <Section
                                        title="Location"
                                        description="Where it happens — or how to join if online."
                                    >
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="event-is-online"
                                                checked={isOnline}
                                                onCheckedChange={(v) =>
                                                    setIsOnline(v === true)
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="is_online"
                                                value={isOnline ? '1' : '0'}
                                            />
                                            <Label
                                                htmlFor="event-is-online"
                                                className="font-normal"
                                            >
                                                This is an online event
                                            </Label>
                                        </div>

                                        {isOnline ? (
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-online-url"
                                                    error={errors.online_url}
                                                    optional
                                                >
                                                    Join URL
                                                </FieldLabel>
                                                <Input
                                                    id="event-online-url"
                                                    name="online_url"
                                                    type="url"
                                                    defaultValue={
                                                        event.online_url ?? ''
                                                    }
                                                    placeholder="https://zoom.us/j/..."
                                                    aria-invalid={
                                                        !!errors.online_url
                                                    }
                                                />
                                            </div>
                                        ) : (
                                            <>
                                                <div className="grid gap-4 sm:grid-cols-3">
                                                    <div className="grid gap-2 sm:col-span-2">
                                                        <FieldLabel
                                                            htmlFor="event-venue-name"
                                                            error={
                                                                errors.venue_name
                                                            }
                                                            optional
                                                        >
                                                            Venue name
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-venue-name"
                                                            name="venue_name"
                                                            defaultValue={
                                                                event.venue_name ??
                                                                ''
                                                            }
                                                            aria-invalid={
                                                                !!errors.venue_name
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <FieldLabel
                                                            htmlFor="event-postal"
                                                            error={
                                                                errors.postal_code
                                                            }
                                                            optional
                                                        >
                                                            Postal code
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-postal"
                                                            name="postal_code"
                                                            defaultValue={
                                                                event.postal_code ??
                                                                ''
                                                            }
                                                            aria-invalid={
                                                                !!errors.postal_code
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid gap-2">
                                                    <FieldLabel
                                                        htmlFor="event-address-1"
                                                        error={
                                                            errors.address_line_1
                                                        }
                                                        optional
                                                    >
                                                        Address line 1
                                                    </FieldLabel>
                                                    <Input
                                                        id="event-address-1"
                                                        name="address_line_1"
                                                        defaultValue={
                                                            event.address_line_1 ??
                                                            ''
                                                        }
                                                        aria-invalid={
                                                            !!errors.address_line_1
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <FieldLabel
                                                        htmlFor="event-address-2"
                                                        error={
                                                            errors.address_line_2
                                                        }
                                                        optional
                                                    >
                                                        Address line 2
                                                    </FieldLabel>
                                                    <Input
                                                        id="event-address-2"
                                                        name="address_line_2"
                                                        defaultValue={
                                                            event.address_line_2 ??
                                                            ''
                                                        }
                                                        aria-invalid={
                                                            !!errors.address_line_2
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-4 sm:grid-cols-3">
                                                    <div className="grid gap-2">
                                                        <FieldLabel
                                                            htmlFor="event-city"
                                                            error={errors.city}
                                                            optional
                                                        >
                                                            City
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-city"
                                                            name="city"
                                                            defaultValue={
                                                                event.city ?? ''
                                                            }
                                                            aria-invalid={
                                                                !!errors.city
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <FieldLabel
                                                            htmlFor="event-region"
                                                            error={
                                                                errors.region
                                                            }
                                                            optional
                                                        >
                                                            Region
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-region"
                                                            name="region"
                                                            defaultValue={
                                                                event.region ??
                                                                ''
                                                            }
                                                            aria-invalid={
                                                                !!errors.region
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <FieldLabel
                                                            htmlFor="event-country"
                                                            error={
                                                                errors.country_code
                                                            }
                                                            optional
                                                        >
                                                            Country (ISO-2)
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-country"
                                                            name="country_code"
                                                            maxLength={2}
                                                            defaultValue={
                                                                event.country_code ??
                                                                ''
                                                            }
                                                            className="uppercase"
                                                            aria-invalid={
                                                                !!errors.country_code
                                                            }
                                                        />
                                                    </div>
                                                </div>

                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="grid gap-2">
                                                        <FieldLabel
                                                            htmlFor="event-lat"
                                                            error={
                                                                errors.latitude
                                                            }
                                                            optional
                                                        >
                                                            Latitude
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-lat"
                                                            name="latitude"
                                                            type="number"
                                                            step="0.0000001"
                                                            min={-90}
                                                            max={90}
                                                            defaultValue={
                                                                event.latitude ??
                                                                ''
                                                            }
                                                            placeholder="-17.8252"
                                                            aria-invalid={
                                                                !!errors.latitude
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <FieldLabel
                                                            htmlFor="event-lng"
                                                            error={
                                                                errors.longitude
                                                            }
                                                            optional
                                                        >
                                                            Longitude
                                                        </FieldLabel>
                                                        <Input
                                                            id="event-lng"
                                                            name="longitude"
                                                            type="number"
                                                            step="0.0000001"
                                                            min={-180}
                                                            max={180}
                                                            defaultValue={
                                                                event.longitude ??
                                                                ''
                                                            }
                                                            placeholder="31.0335"
                                                            aria-invalid={
                                                                !!errors.longitude
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </Section>

                                    <Section
                                        title="Capacity & policies"
                                        description="Optional limits and the rules attendees agree to."
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-capacity"
                                                    error={errors.capacity}
                                                    optional
                                                >
                                                    Capacity
                                                </FieldLabel>
                                                <Input
                                                    id="event-capacity"
                                                    name="capacity"
                                                    type="number"
                                                    min={1}
                                                    defaultValue={
                                                        event.capacity ?? ''
                                                    }
                                                    placeholder="Unlimited"
                                                    aria-invalid={
                                                        !!errors.capacity
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-min-age"
                                                    error={errors.minimum_age}
                                                    optional
                                                >
                                                    Minimum age
                                                </FieldLabel>
                                                <Input
                                                    id="event-min-age"
                                                    name="minimum_age"
                                                    type="number"
                                                    min={0}
                                                    max={120}
                                                    defaultValue={
                                                        event.minimum_age ?? ''
                                                    }
                                                    aria-invalid={
                                                        !!errors.minimum_age
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-refund"
                                                error={errors.refund_policy}
                                                optional
                                            >
                                                Refund policy
                                            </FieldLabel>
                                            <Textarea
                                                id="event-refund"
                                                name="refund_policy"
                                                defaultValue={
                                                    event.refund_policy ?? ''
                                                }
                                                rows={4}
                                                aria-invalid={
                                                    !!errors.refund_policy
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-terms"
                                                error={errors.terms}
                                                optional
                                            >
                                                Terms
                                            </FieldLabel>
                                            <Textarea
                                                id="event-terms"
                                                name="terms"
                                                defaultValue={event.terms ?? ''}
                                                rows={4}
                                                aria-invalid={!!errors.terms}
                                            />
                                        </div>
                                    </Section>

                                    <Section
                                        title="Highlights"
                                        description="Eventbrite-style logistical details that attendees expect to see at a glance."
                                    >
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-doors-open"
                                                error={errors.doors_open_at}
                                                optional
                                            >
                                                Doors open at
                                            </FieldLabel>
                                            <Input
                                                id="event-doors-open"
                                                name="doors_open_at"
                                                type="datetime-local"
                                                value={doorsOpenAt}
                                                onChange={(e) =>
                                                    setDoorsOpenAt(
                                                        e.target.value,
                                                    )
                                                }
                                                aria-invalid={
                                                    !!errors.doors_open_at
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-age-details"
                                                error={
                                                    errors.age_requirement_details
                                                }
                                                optional
                                            >
                                                Age requirement details
                                            </FieldLabel>
                                            <Textarea
                                                id="event-age-details"
                                                name="age_requirement_details"
                                                defaultValue={
                                                    event.age_requirement_details ??
                                                    ''
                                                }
                                                rows={3}
                                                placeholder="e.g. 18+ only. Valid government-issued ID required at entry."
                                                aria-invalid={
                                                    !!errors.age_requirement_details
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-parking"
                                                error={errors.parking_info}
                                                optional
                                            >
                                                Parking information
                                            </FieldLabel>
                                            <Textarea
                                                id="event-parking"
                                                name="parking_info"
                                                defaultValue={
                                                    event.parking_info ?? ''
                                                }
                                                rows={3}
                                                placeholder="e.g. Free on-site parking. Disabled bays near main entrance. Overflow parking on Smith St."
                                                aria-invalid={
                                                    !!errors.parking_info
                                                }
                                            />
                                        </div>
                                    </Section>

                                    <Section
                                        title="Lineup"
                                        description="Special guests, performers, or speakers. Mark prominent names as headliners."
                                    >
                                        <EventLineupManager
                                            initial={event.lineup}
                                        />
                                    </Section>

                                    <Section
                                        title="Agenda"
                                        description="Build a schedule with timestamps, descriptions, and hosts."
                                    >
                                        <EventAgendaManager
                                            initial={event.agenda}
                                            timezone={event.timezone}
                                        />
                                    </Section>
                                </div>

                                {/* Sidebar: visibility / contact / save */}
                                <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                                    <Section title="Visibility">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-visibility"
                                                error={errors.visibility}
                                            >
                                                Audience
                                            </FieldLabel>
                                            <input
                                                type="hidden"
                                                name="visibility"
                                                value={visibility}
                                            />
                                            <Select
                                                value={visibility}
                                                onValueChange={setVisibility}
                                            >
                                                <SelectTrigger
                                                    id="event-visibility"
                                                    className="w-full"
                                                    aria-invalid={
                                                        !!errors.visibility
                                                    }
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
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

                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="event-is-featured"
                                                checked={isFeatured}
                                                onCheckedChange={(v) =>
                                                    setIsFeatured(v === true)
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="is_featured"
                                                value={isFeatured ? '1' : '0'}
                                            />
                                            <Label
                                                htmlFor="event-is-featured"
                                                className="font-normal"
                                            >
                                                Feature this event
                                            </Label>
                                        </div>
                                    </Section>

                                    <Section title="Contact">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-contact-email"
                                                error={errors.contact_email}
                                                optional
                                            >
                                                Email
                                            </FieldLabel>
                                            <Input
                                                id="event-contact-email"
                                                name="contact_email"
                                                type="email"
                                                defaultValue={
                                                    event.contact_email ?? ''
                                                }
                                                aria-invalid={
                                                    !!errors.contact_email
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-contact-phone"
                                                error={errors.contact_phone}
                                                optional
                                            >
                                                Phone
                                            </FieldLabel>
                                            <Input
                                                id="event-contact-phone"
                                                name="contact_phone"
                                                type="tel"
                                                defaultValue={
                                                    event.contact_phone ?? ''
                                                }
                                                aria-invalid={
                                                    !!errors.contact_phone
                                                }
                                            />
                                        </div>
                                    </Section>

                                    <Card>
                                        <CardContent className="flex flex-col gap-2 px-6">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                {processing
                                                    ? 'Saving...'
                                                    : 'Save changes'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={showUrl}>
                                                    Cancel
                                                </Link>
                                            </Button>
                                        </CardContent>
                                    </Card>
                                </aside>
                            </div>
                        </>
                    )}
                </Form>

                {/* Media gallery — separate upload endpoint, not part of main form */}
                <Section
                    title="Media gallery"
                    description="Additional images shown as a carousel in the event hero. Uploads save immediately."
                >
                    <EventMediaManager
                        eventSlug={event.slug}
                        items={event.media}
                    />
                </Section>
            </div>
        </>
    );
}

EventEdit.layout = (props: {
    event?: EventDetail;
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'My Events',
            href: props.currentTeam ? `/${props.currentTeam.slug}/events` : '/',
        },
        {
            title: props.event?.name ?? 'Event',
            href:
                props.currentTeam && props.event
                    ? `/${props.currentTeam.slug}/events/${props.event.slug}`
                    : '#',
        },
        { title: 'Edit', href: '#' },
    ],
});
