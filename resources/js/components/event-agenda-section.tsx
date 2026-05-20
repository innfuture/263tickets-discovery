import { Calendar, Clock, User } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { SectionTitle } from '@/components/ui/section-title';

export type AgendaEntry = {
    id: number;
    starts_at: string | null;
    ends_at: string | null;
    title: string;
    description: string | null;
    host_name: string | null;
    host_role: string | null;
};

function formatTime(iso: string | null, timezone: string): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: timezone,
    }).format(new Date(iso));
}

function formatDate(iso: string | null, timezone: string): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        timeZone: timezone,
    }).format(new Date(iso));
}

function dayKey(iso: string | null, timezone: string): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(iso));
}

export function EventAgendaSection({
    entries,
    timezone,
}: {
    entries: AgendaEntry[];
    timezone: string;
}) {
    if (entries.length === 0) {
        return null;
    }

    // Group entries by day
    const grouped = entries.reduce<Record<string, AgendaEntry[]>>(
        (acc, entry) => {
            const key = dayKey(entry.starts_at, timezone);
            if (!acc[key]) acc[key] = [];
            acc[key].push(entry);
            return acc;
        },
        {},
    );

    const days = Object.keys(grouped).sort();

    return (
        <Card className="animate-in duration-500 fade-in slide-in-from-bottom-2">
            <CardHeader>
                <SectionTitle
                    icon={<Calendar className="size-4" />}
                    count={entries.length}
                >
                    Agenda
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {days.map((day) => {
                    const first = grouped[day][0];
                    return (
                        <div key={day} className="space-y-3">
                            {days.length > 1 ? (
                                <h3 className="text-sm font-semibold">
                                    {formatDate(first.starts_at, timezone)}
                                </h3>
                            ) : null}
                            <ol className="space-y-4 border-l-2 border-border pl-4">
                                {grouped[day].map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="relative space-y-1.5"
                                    >
                                        <div className="absolute top-1.5 left-[-1.4rem] size-3 rounded-full border-2 border-background bg-primary" />
                                        <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                            <Clock className="size-3" />
                                            {formatTime(
                                                entry.starts_at,
                                                timezone,
                                            )}
                                            {entry.ends_at ? (
                                                <>
                                                    {' — '}
                                                    {formatTime(
                                                        entry.ends_at,
                                                        timezone,
                                                    )}
                                                </>
                                            ) : null}
                                        </div>
                                        <p className="font-semibold">
                                            {entry.title}
                                        </p>
                                        {entry.description ? (
                                            <p className="text-sm leading-relaxed text-muted-foreground">
                                                {entry.description}
                                            </p>
                                        ) : null}
                                        {entry.host_name ? (
                                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <User className="size-3" />
                                                <span className="font-medium text-foreground">
                                                    {entry.host_name}
                                                </span>
                                                {entry.host_role ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="font-normal"
                                                    >
                                                        {entry.host_role}
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        ) : null}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}
