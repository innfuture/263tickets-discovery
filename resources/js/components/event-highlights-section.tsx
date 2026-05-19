import { CarFront, DoorOpen, ShieldAlert } from 'lucide-react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

export type EventHighlights = {
    doors_open_at: string | null;
    timezone: string;
    minimum_age: number | null;
    age_requirement_details: string | null;
    parking_info: string | null;
};

function formatTime(iso: string, timezone: string): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(iso));
}

export function EventHighlightsSection({ event }: { event: EventHighlights }) {
    const hasDoors = !!event.doors_open_at;
    const hasAge = !!event.minimum_age || !!event.age_requirement_details;
    const hasParking = !!event.parking_info;

    if (!hasDoors && !hasAge && !hasParking) {
        return null;
    }

    return (
        <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
            <CardHeader>
                <h2 className="font-semibold">Good to know</h2>
            </CardHeader>
            <CardContent>
                <div className="grid gap-4 sm:grid-cols-3">
                    {hasDoors ? (
                        <HighlightTile
                            icon={<DoorOpen className="size-5" />}
                            label="Doors open"
                            value={formatTime(
                                event.doors_open_at!,
                                event.timezone,
                            )}
                        />
                    ) : null}

                    {hasAge ? (
                        <HighlightTile
                            icon={<ShieldAlert className="size-5" />}
                            label="Age requirement"
                            value={
                                event.minimum_age
                                    ? `${event.minimum_age}+`
                                    : 'See details'
                            }
                            detail={event.age_requirement_details ?? undefined}
                        />
                    ) : null}

                    {hasParking ? (
                        <HighlightTile
                            icon={<CarFront className="size-5" />}
                            label="Parking"
                            value="Available"
                            detail={event.parking_info ?? undefined}
                        />
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

function HighlightTile({
    icon,
    label,
    value,
    detail,
}: {
    icon: React.ReactNode;
    label: string;
    value: string;
    detail?: string;
}) {
    return (
        <div className="flex gap-3 rounded-md border bg-muted/40 p-3">
            <div className="mt-0.5 shrink-0 text-primary">{icon}</div>
            <div className="min-w-0 space-y-1">
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className="text-sm font-semibold">{value}</p>
                {detail ? (
                    <p className="line-clamp-3 text-xs leading-relaxed text-muted-foreground">
                        {detail}
                    </p>
                ) : null}
            </div>
        </div>
    );
}
