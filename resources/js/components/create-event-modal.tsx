import { Form, usePage } from '@inertiajs/react';
import type { PropsWithChildren, ReactNode } from 'react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import ImageDropzone from '@/components/image-dropzone';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { VenuePicker } from '@/components/venue-picker';
import type { VenueSelection } from '@/components/venue-picker';

type EnumOption = { value: string; label: string };

const ALL_VALUE = '__none__';

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

function browserTimezone(): string {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
    } catch {
        return 'UTC';
    }
}

function FieldLabel({
    htmlFor,
    error,
    children,
}: {
    htmlFor?: string;
    error?: string | null;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <Label htmlFor={htmlFor}>{children}</Label>
            <FieldError message={error} />
        </div>
    );
}

type CategoryOption = { id: number; name: string; slug: string };

export default function CreateEventModal({
    visibilities,
    categories,
    children,
}: PropsWithChildren<{
    visibilities: EnumOption[];
    categories: CategoryOption[];
}>) {
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const slug = page.props.currentTeam?.slug ?? '';
    const action = `/${slug}/events`;

    const [open, setOpen] = useState(false);
    const [name, setName] = useState<string>('');
    const [shortDescription, setShortDescription] = useState<string>('');
    const [timezone, setTimezone] = useState<string>(browserTimezone());
    const [visibility, setVisibility] = useState<string>(
        visibilities[0]?.value ?? 'public',
    );
    const [isOnline, setIsOnline] = useState(false);
    const [startsAt, setStartsAt] = useState<string>('');
    const [endsAt, setEndsAt] = useState<string>('');
    const [categoryId, setCategoryId] = useState<string>('');
    const [venueName, setVenueName] = useState<string>('');
    const [city, setCity] = useState<string>('');
    const [countryCode, setCountryCode] = useState<string>('');
    const [addressLine1, setAddressLine1] = useState<string>('');
    const [latitude, setLatitude] = useState<number | null>(null);
    const [longitude, setLongitude] = useState<number | null>(null);
    const [bannerClientError, setBannerClientError] = useState<string | null>(
        null,
    );

    // Minimum allowed datetime for starts_at (now, in local time)
    const minStartsAt = (() => {
        const now = new Date();
        const tzOffset = now.getTimezoneOffset() * 60000;

        return new Date(now.getTime() - tzOffset).toISOString().slice(0, 16);
    })();

    const trimmedShortDescription = shortDescription.trim();

    const isValid =
        name.trim().length >= 6 &&
        name.length <= 120 &&
        (trimmedShortDescription.length === 0 ||
            (trimmedShortDescription.length >= 32 &&
                trimmedShortDescription.length <= 180)) &&
        startsAt !== '' &&
        startsAt >= minStartsAt &&
        endsAt !== '' &&
        endsAt > startsAt &&
        timezone !== '' &&
        visibility !== '' &&
        !bannerClientError;

    const handleVenueSelect = (v: VenueSelection) => {
        setVenueName(v.venueName);

        if (v.city) {
            setCity(v.city);
        }

        if (v.countryCode) {
            setCountryCode(v.countryCode);
        }

        if (v.addressLine1) {
            setAddressLine1(v.addressLine1);
        }

        setLatitude(v.latitude);
        setLongitude(v.longitude);
    };

    const handleVenueClear = () => {
        setLatitude(null);
        setLongitude(null);
        setAddressLine1('');
    };

    const handleOpenChange = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setName('');
            setShortDescription('');
            setVenueName('');
            setCity('');
            setCountryCode('');
            setAddressLine1('');
            setLatitude(null);
            setLongitude(null);
            setStartsAt('');
            setEndsAt('');
            setCategoryId('');
            setBannerClientError(null);
        }
    };

    const handleStartsAtChange = (value: string) => {
        setStartsAt(value);

        if (endsAt && value && endsAt < value) {
            setEndsAt('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="flex max-h-[90dvh] flex-col gap-0 p-0 sm:max-w-2xl">
                <Form
                    key={String(open)}
                    action={action}
                    method="post"
                    className="flex min-h-0 flex-1 flex-col"
                    onSuccess={() => {
                        setOpen(false);
                        setBannerClientError(null);
                    }}
                >
                    {({ errors, processing }) => {
                        const bannerError =
                            errors.banner_image ?? bannerClientError;

                        return (
                            <>
                                <DialogHeader className="shrink-0 px-6 pt-6 pb-4">
                                    <DialogTitle>
                                        Create a new event
                                    </DialogTitle>
                                    <DialogDescription>
                                        Set up the basics — you can flesh out
                                        the rest after saving.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="min-h-0 flex-1 overflow-y-auto px-6 pb-4">
                                    <div className="grid gap-4">
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
                                                placeholder="Summer Music Festival"
                                                required
                                                minLength={6}
                                                maxLength={120}
                                                autoFocus
                                                value={name}
                                                onChange={(e) =>
                                                    setName(e.target.value)
                                                }
                                                aria-invalid={!!errors.name}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {name.length}/120 characters · 6
                                                minimum
                                            </p>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-short-description"
                                                error={errors.short_description}
                                            >
                                                Short description
                                            </FieldLabel>
                                            <Textarea
                                                id="event-short-description"
                                                name="short_description"
                                                placeholder="A short blurb that appears on cards and previews (32 chars minimum if provided)"
                                                minLength={32}
                                                maxLength={180}
                                                value={shortDescription}
                                                onChange={(e) =>
                                                    setShortDescription(
                                                        e.target.value,
                                                    )
                                                }
                                                aria-invalid={
                                                    !!errors.short_description
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                {shortDescription.length}/180
                                                characters
                                                {shortDescription.length > 0 &&
                                                shortDescription.length < 32
                                                    ? ` · ${32 - shortDescription.length} more to reach the minimum`
                                                    : ''}
                                            </p>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-category"
                                                error={errors.category_id}
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
                                                value={categoryId || ALL_VALUE}
                                                onValueChange={(v) =>
                                                    setCategoryId(
                                                        v === ALL_VALUE
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
                                                    <SelectValue placeholder="Select a category (optional)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={ALL_VALUE}
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
                                            <FieldLabel error={bannerError}>
                                                Banner image
                                            </FieldLabel>
                                            <ImageDropzone
                                                name="banner_image"
                                                hasError={!!bannerError}
                                                onValidationError={
                                                    setBannerClientError
                                                }
                                            />
                                        </div>

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
                                                    min={minStartsAt}
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

                                        <div className="grid gap-4 sm:grid-cols-2">
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
                                                        <SelectValue placeholder="Select timezone" />
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

                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-visibility"
                                                    error={errors.visibility}
                                                >
                                                    Visibility
                                                </FieldLabel>
                                                <input
                                                    type="hidden"
                                                    name="visibility"
                                                    value={visibility}
                                                />
                                                <Select
                                                    value={visibility}
                                                    onValueChange={
                                                        setVisibility
                                                    }
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
                                                        {visibilities.map(
                                                            (v) => (
                                                                <SelectItem
                                                                    key={
                                                                        v.value
                                                                    }
                                                                    value={
                                                                        v.value
                                                                    }
                                                                >
                                                                    {v.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="event-venue-name"
                                                error={errors.venue_name}
                                            >
                                                Venue
                                            </FieldLabel>
                                            <input
                                                type="hidden"
                                                name="venue_name"
                                                value={venueName}
                                            />
                                            <input
                                                type="hidden"
                                                name="address_line_1"
                                                value={addressLine1}
                                            />
                                            <input
                                                type="hidden"
                                                name="latitude"
                                                value={
                                                    latitude !== null
                                                        ? String(latitude)
                                                        : ''
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="longitude"
                                                value={
                                                    longitude !== null
                                                        ? String(longitude)
                                                        : ''
                                                }
                                            />
                                            <VenuePicker
                                                id="event-venue-name"
                                                value={venueName}
                                                onTextChange={setVenueName}
                                                onSelect={handleVenueSelect}
                                                onClear={handleVenueClear}
                                                latitude={latitude}
                                                longitude={longitude}
                                                hasError={!!errors.venue_name}
                                            />
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="grid gap-2 sm:col-span-2">
                                                <FieldLabel
                                                    htmlFor="event-city"
                                                    error={errors.city}
                                                >
                                                    City
                                                </FieldLabel>
                                                <Input
                                                    id="event-city"
                                                    name="city"
                                                    placeholder="Harare"
                                                    value={city}
                                                    onChange={(e) =>
                                                        setCity(e.target.value)
                                                    }
                                                    aria-invalid={!!errors.city}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <FieldLabel
                                                    htmlFor="event-capacity"
                                                    error={errors.capacity}
                                                >
                                                    Capacity
                                                </FieldLabel>
                                                <Input
                                                    id="event-capacity"
                                                    name="capacity"
                                                    type="number"
                                                    min={1}
                                                    max={500000}
                                                    placeholder="Unlimited"
                                                    aria-invalid={
                                                        !!errors.capacity
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <div className="grid gap-2 sm:col-span-2">
                                                <FieldLabel
                                                    htmlFor="event-country"
                                                    error={errors.country_code}
                                                >
                                                    Country (ISO-2)
                                                </FieldLabel>
                                                <Input
                                                    id="event-country"
                                                    name="country_code"
                                                    maxLength={2}
                                                    placeholder="ZW"
                                                    className="uppercase"
                                                    value={countryCode}
                                                    onChange={(e) =>
                                                        setCountryCode(
                                                            e.target.value.toUpperCase(),
                                                        )
                                                    }
                                                    aria-invalid={
                                                        !!errors.country_code
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="event-is-online"
                                                checked={isOnline}
                                                onCheckedChange={(v) =>
                                                    setIsOnline(v === true)
                                                }
                                                aria-invalid={
                                                    !!errors.is_online
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
                                            <FieldError
                                                message={errors.is_online}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter className="shrink-0 gap-2 border-t bg-background px-6 py-4">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            type="button"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>

                                    <Button
                                        type="submit"
                                        disabled={processing || !isValid}
                                    >
                                        Create event
                                    </Button>
                                </DialogFooter>
                            </>
                        );
                    }}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
