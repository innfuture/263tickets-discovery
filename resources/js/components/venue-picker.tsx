import { Loader2, MapPin, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { VenueMap } from '@/components/venue-map';
import { cn } from '@/lib/utils';

export type VenueSelection = {
    venueName: string;
    addressLine1: string;
    city: string;
    countryCode: string;
    latitude: number | null;
    longitude: number | null;
    displayName: string;
};

type NominatimAddress = {
    name?: string;
    amenity?: string;
    tourism?: string;
    leisure?: string;
    building?: string;
    shop?: string;
    house_number?: string;
    road?: string;
    pedestrian?: string;
    suburb?: string;
    neighbourhood?: string;
    city?: string;
    town?: string;
    village?: string;
    county?: string;
    state?: string;
    postcode?: string;
    country?: string;
    country_code?: string;
};

type NominatimResult = {
    place_id: number;
    display_name: string;
    lat: string;
    lon: string;
    type: string;
    address?: NominatimAddress;
};

function parseResult(r: NominatimResult): VenueSelection {
    const addr = r.address ?? {};

    const venueName =
        addr.name ??
        addr.amenity ??
        addr.tourism ??
        addr.leisure ??
        addr.building ??
        addr.shop ??
        r.display_name.split(',')[0] ??
        '';

    const street = [addr.house_number, addr.road ?? addr.pedestrian]
        .filter(Boolean)
        .join(' ');

    return {
        venueName,
        addressLine1: street,
        city: addr.city ?? addr.town ?? addr.village ?? addr.county ?? '',
        countryCode: (addr.country_code ?? '').toUpperCase(),
        latitude: r.lat ? parseFloat(r.lat) : null,
        longitude: r.lon ? parseFloat(r.lon) : null,
        displayName: r.display_name,
    };
}

export function VenuePicker({
    id,
    value,
    onTextChange,
    onSelect,
    onClear,
    latitude,
    longitude,
    hasError = false,
    placeholder = 'Search a venue, address, or landmark',
    searchUrl = '/geocode/search',
}: {
    id?: string;
    value: string;
    onTextChange: (value: string) => void;
    onSelect: (venue: VenueSelection) => void;
    onClear?: () => void;
    latitude: number | null;
    longitude: number | null;
    hasError?: boolean;
    placeholder?: string;
    /** Backend proxy URL. Defaults to /geocode/search. */
    searchUrl?: string;
}) {
    const [results, setResults] = useState<NominatimResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [open, setOpen] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);

    // Close dropdown on outside click
    useEffect(() => {
        const onDown = (e: MouseEvent) => {
            if (!wrapperRef.current?.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        window.addEventListener('mousedown', onDown);

        return () => window.removeEventListener('mousedown', onDown);
    }, []);

    // Debounced search (only runs when query is meaningful)
    const trimmedQuery = value.trim();
    const shouldSearch = trimmedQuery.length >= 3;

    useEffect(() => {
        if (!shouldSearch) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            setLoading(true);

            const url = new URL(searchUrl, window.location.origin);
            url.searchParams.set('q', trimmedQuery);

            fetch(url.toString(), {
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
                .then((data: NominatimResult[]) => {
                    setResults(data);
                    setOpen(true);
                    setLoading(false);
                })
                .catch((e: unknown) => {
                    if (e instanceof DOMException && e.name === 'AbortError') {
                        return;
                    }

                    setLoading(false);
                });
        }, 500);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [trimmedQuery, shouldSearch, searchUrl]);

    const visibleResults = shouldSearch ? results : [];

    const handlePick = (r: NominatimResult) => {
        const parsed = parseResult(r);
        onSelect(parsed);
        setOpen(false);
    };

    const handleClear = () => {
        onClear?.();
    };

    const hasCoords = latitude != null && longitude != null;

    return (
        <div className="space-y-2" ref={wrapperRef}>
            <div className="relative">
                <Input
                    id={id}
                    value={value}
                    onChange={(e) => onTextChange(e.target.value)}
                    onFocus={() => visibleResults.length > 0 && setOpen(true)}
                    placeholder={placeholder}
                    aria-invalid={hasError}
                    autoComplete="off"
                    className="pr-9"
                />

                <div className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2">
                    {loading ? (
                        <Loader2 className="size-4 animate-spin text-muted-foreground" />
                    ) : hasCoords ? (
                        <MapPin className="size-4 text-green-600" />
                    ) : (
                        <MapPin className="size-4 text-muted-foreground opacity-50" />
                    )}
                </div>

                {open && visibleResults.length > 0 ? (
                    <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-md border bg-popover shadow-md">
                        {visibleResults.map((r) => (
                            <button
                                key={r.place_id}
                                type="button"
                                onClick={() => handlePick(r)}
                                className="flex w-full items-start gap-2 px-3 py-2 text-left text-sm outline-none hover:bg-accent focus:bg-accent"
                            >
                                <MapPin className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-medium">
                                        {parseResult(r).venueName ||
                                            r.display_name}
                                    </div>
                                    <div className="line-clamp-1 text-xs text-muted-foreground">
                                        {r.display_name}
                                    </div>
                                </div>
                            </button>
                        ))}
                    </div>
                ) : null}
            </div>

            {hasCoords ? (
                <div
                    className={cn(
                        'space-y-2 rounded-md border p-2',
                        hasError && 'border-destructive',
                    )}
                >
                    <VenueMap
                        lat={latitude}
                        lng={longitude}
                        title={value}
                        className="aspect-2/1"
                    />
                    <div className="flex items-center justify-between gap-2 px-1 text-xs">
                        <span className="text-muted-foreground tabular-nums">
                            {latitude.toFixed(5)}, {longitude.toFixed(5)}
                        </span>
                        {onClear ? (
                            <button
                                type="button"
                                onClick={handleClear}
                                className="flex items-center gap-1 text-destructive hover:underline"
                            >
                                <X className="size-3" />
                                Clear venue
                            </button>
                        ) : null}
                    </div>
                </div>
            ) : value.length >= 3 ? (
                <p className="text-xs text-muted-foreground">
                    Pick a result above to drop a pin on the map and
                    auto-populate city / country.
                </p>
            ) : null}
        </div>
    );
}
