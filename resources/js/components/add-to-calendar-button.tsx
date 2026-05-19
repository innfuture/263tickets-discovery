import { CalendarPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';

function formatIcsDate(iso: string): string {
    return new Date(iso)
        .toISOString()
        .replace(/[-:]/g, '')
        .replace(/\.\d{3}/, '');
}

function escapeIcsText(text: string): string {
    return text
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\n/g, '\\n');
}

export function AddToCalendarButton({
    title,
    description,
    location,
    startIso,
    endIso,
    uid,
}: {
    title: string;
    description?: string;
    location?: string;
    startIso: string;
    endIso: string;
    uid: string;
}) {
    const handleDownload = () => {
        const ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Discovery//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            `UID:${uid}@discovery`,
            `DTSTAMP:${formatIcsDate(new Date().toISOString())}`,
            `DTSTART:${formatIcsDate(startIso)}`,
            `DTEND:${formatIcsDate(endIso)}`,
            `SUMMARY:${escapeIcsText(title)}`,
            description ? `DESCRIPTION:${escapeIcsText(description)}` : '',
            location ? `LOCATION:${escapeIcsText(location)}` : '',
            'END:VEVENT',
            'END:VCALENDAR',
        ]
            .filter(Boolean)
            .join('\r\n');

        const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${uid}.ics`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return (
        <Button variant="outline" size="sm" onClick={handleDownload}>
            <CalendarPlus className="size-4" />
            Add to calendar
        </Button>
    );
}
