import {
    Cloud,
    CloudDrizzle,
    CloudRain,
    CloudSnow,
    CloudSun,
    Snowflake,
    Sun,
    Thermometer,
    Wind,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type Forecast = {
    date: string;
    code: number;
    maxC: number;
    minC: number;
    precipPct: number;
};

const WMO_LABELS: Record<number, string> = {
    0: 'Clear',
    1: 'Mostly clear',
    2: 'Partly cloudy',
    3: 'Overcast',
    45: 'Foggy',
    48: 'Freezing fog',
    51: 'Light drizzle',
    53: 'Drizzle',
    55: 'Heavy drizzle',
    61: 'Light rain',
    63: 'Rain',
    65: 'Heavy rain',
    71: 'Light snow',
    73: 'Snow',
    75: 'Heavy snow',
    80: 'Showers',
    81: 'Heavy showers',
    82: 'Violent showers',
    95: 'Thunderstorm',
    96: 'Thunderstorm + hail',
    99: 'Severe thunderstorm',
};

function iconForCode(code: number) {
    if (code === 0 || code === 1) {
        return <Sun className="size-6 text-yellow-500" />;
    }
    if (code === 2) {
        return <CloudSun className="size-6 text-yellow-500/80" />;
    }
    if (code === 3 || code === 45 || code === 48) {
        return <Cloud className="size-6 text-gray-400" />;
    }
    if (code >= 51 && code <= 55) {
        return <CloudDrizzle className="size-6 text-blue-400" />;
    }
    if (code >= 61 && code <= 82) {
        return <CloudRain className="size-6 text-blue-500" />;
    }
    if (code >= 71 && code <= 75) {
        return <CloudSnow className="size-6 text-blue-300" />;
    }
    if (code >= 95) {
        return <Zap className="size-6 text-yellow-500" />;
    }
    return <Snowflake className="size-6 text-blue-300" />;
}

function isoDateOnly(iso: string, timezone: string): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(iso));
}

export function EventWeatherPanel({
    lat,
    lng,
    startsAtIso,
    endsAtIso,
    timezone,
}: {
    lat: number | null;
    lng: number | null;
    startsAtIso: string | null;
    endsAtIso: string | null;
    timezone: string;
}) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [forecasts, setForecasts] = useState<Forecast[]>([]);
    const [tooFar, setTooFar] = useState(false);

    useEffect(() => {
        if (lat == null || lng == null || !startsAtIso) {
            return;
        }

        const startDate = isoDateOnly(startsAtIso, timezone);
        const endDate = endsAtIso
            ? isoDateOnly(endsAtIso, timezone)
            : startDate;

        // Open-Meteo forecast horizon is 16 days from today.
        const today = new Date();
        const eventDate = new Date(startDate + 'T00:00:00');
        const daysAhead = Math.round(
            (eventDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
        );

        if (daysAhead > 16) {
            setTooFar(true);
            return;
        }

        if (daysAhead < -1) {
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setError(null);

        const url = new URL('https://api.open-meteo.com/v1/forecast');
        url.searchParams.set('latitude', String(lat));
        url.searchParams.set('longitude', String(lng));
        url.searchParams.set(
            'daily',
            'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
        );
        url.searchParams.set('timezone', timezone);
        url.searchParams.set('start_date', startDate);
        url.searchParams.set('end_date', endDate);

        fetch(url.toString(), { signal: controller.signal })
            .then((r) => {
                if (!r.ok) {
                    throw new Error(`Weather API ${r.status}`);
                }
                return r.json();
            })
            .then(
                (data: {
                    daily: {
                        time: string[];
                        weather_code: number[];
                        temperature_2m_max: number[];
                        temperature_2m_min: number[];
                        precipitation_probability_max: number[];
                    };
                }) => {
                    const items: Forecast[] = data.daily.time.map(
                        (date, i) => ({
                            date,
                            code: data.daily.weather_code[i],
                            maxC: data.daily.temperature_2m_max[i],
                            minC: data.daily.temperature_2m_min[i],
                            precipPct:
                                data.daily.precipitation_probability_max[i] ??
                                0,
                        }),
                    );
                    setForecasts(items);
                    setLoading(false);
                },
            )
            .catch((e: unknown) => {
                if (e instanceof DOMException && e.name === 'AbortError') {
                    return;
                }
                setError('Could not load forecast');
                setLoading(false);
            });

        return () => controller.abort();
    }, [lat, lng, startsAtIso, endsAtIso, timezone]);

    if (lat == null || lng == null || !startsAtIso) {
        return null;
    }

    if (tooFar) {
        return (
            <Card>
                <CardContent className="space-y-2 px-6">
                    <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        <Thermometer className="size-4" />
                        Weather forecast
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Forecast available 16 days before the event.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardContent className="space-y-3 px-6">
                <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <Thermometer className="size-4" />
                    Weather forecast
                </div>

                {loading ? (
                    <div className="space-y-2">
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-3 w-24" />
                    </div>
                ) : error ? (
                    <p className="text-sm text-muted-foreground">{error}</p>
                ) : forecasts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No forecast data available.
                    </p>
                ) : (
                    <div className="space-y-3">
                        {forecasts.map((f) => (
                            <div
                                key={f.date}
                                className="flex items-center gap-3"
                            >
                                {iconForCode(f.code)}
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-medium">
                                        {new Intl.DateTimeFormat(undefined, {
                                            weekday: 'short',
                                            month: 'short',
                                            day: 'numeric',
                                        }).format(new Date(f.date + 'T12:00'))}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {WMO_LABELS[f.code] ?? `Code ${f.code}`}
                                    </div>
                                </div>
                                <div className="text-right">
                                    <div className="text-sm font-medium tabular-nums">
                                        {Math.round(f.maxC)}° /{' '}
                                        <span className="text-muted-foreground">
                                            {Math.round(f.minC)}°
                                        </span>
                                    </div>
                                    {f.precipPct > 0 ? (
                                        <div className="flex items-center justify-end gap-1 text-xs text-muted-foreground">
                                            <Wind className="size-3" />
                                            {f.precipPct}%
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                        <p className="pt-1 text-[10px] text-muted-foreground italic">
                            Powered by Open-Meteo
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
