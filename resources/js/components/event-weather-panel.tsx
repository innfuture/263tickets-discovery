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
import { Card, CardContent } from '@/components/ui/card';

export type WeatherForecast = {
    date: string;
    code: number;
    max_c: number;
    min_c: number;
    precip_pct: number;
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

    if (code >= 71 && code <= 75) {
        return <CloudSnow className="size-6 text-blue-300" />;
    }

    if (code >= 61 && code <= 82) {
        return <CloudRain className="size-6 text-blue-500" />;
    }

    if (code >= 95) {
        return <Zap className="size-6 text-yellow-500" />;
    }

    return <Snowflake className="size-6 text-blue-300" />;
}

export function EventWeatherPanel({
    forecasts,
}: {
    forecasts: WeatherForecast[] | null;
}) {
    if (!forecasts) {
        return null;
    }

    if (forecasts.length === 0) {
        return (
            <Card>
                <CardContent className="space-y-2 px-6">
                    <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        <Thermometer className="size-4" />
                        Weather forecast
                    </div>
                    <p className="text-sm text-muted-foreground">
                        No forecast data available for the event dates.
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

                <div className="space-y-3">
                    {forecasts.map((f) => (
                        <div key={f.date} className="flex items-center gap-3">
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
                                    {Math.round(f.max_c)}° /{' '}
                                    <span className="text-muted-foreground">
                                        {Math.round(f.min_c)}°
                                    </span>
                                </div>
                                {f.precip_pct > 0 ? (
                                    <div className="flex items-center justify-end gap-1 text-xs text-muted-foreground">
                                        <Wind className="size-3" />
                                        {f.precip_pct}%
                                    </div>
                                ) : null}
                            </div>
                        </div>
                    ))}
                    <p className="pt-1 text-[10px] text-muted-foreground italic">
                        Cached forecast · Powered by Open-Meteo
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
