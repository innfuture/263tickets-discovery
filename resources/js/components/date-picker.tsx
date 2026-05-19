import { format, parseISO } from 'date-fns';
import { CalendarIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export function DatePicker({
    id,
    value,
    onChange,
    placeholder = 'Pick a date',
}: {
    id?: string;
    /** ISO date string (yyyy-MM-dd) or empty string for no value. */
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
}) {
    const [open, setOpen] = useState(false);
    const date = value ? parseISO(value) : undefined;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className={cn(
                        'w-full justify-between font-normal',
                        !value && 'text-muted-foreground',
                    )}
                >
                    <span className="truncate">
                        {date ? format(date, 'PP') : placeholder}
                    </span>
                    <CalendarIcon className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-auto p-0" align="start">
                <Calendar
                    mode="single"
                    selected={date}
                    onSelect={(d) => {
                        onChange(d ? format(d, 'yyyy-MM-dd') : '');
                        setOpen(false);
                    }}
                    autoFocus
                />
            </PopoverContent>
        </Popover>
    );
}
