import { useEffect, useState } from 'react';

type TimeLeft = {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
    isPast: boolean;
};

function compute(targetIso: string): TimeLeft {
    const target = new Date(targetIso).getTime();
    const now = Date.now();
    const diff = target - now;

    if (diff <= 0) {
        return { days: 0, hours: 0, minutes: 0, seconds: 0, isPast: true };
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
    const minutes = Math.floor((diff / (1000 * 60)) % 60);
    const seconds = Math.floor((diff / 1000) % 60);

    return { days, hours, minutes, seconds, isPast: false };
}

export function CountdownTimer({
    targetIso,
    pastLabel = 'Event started',
}: {
    targetIso: string;
    pastLabel?: string;
}) {
    const [tl, setTl] = useState<TimeLeft>(() => compute(targetIso));

    useEffect(() => {
        setTl(compute(targetIso));

        const id = window.setInterval(() => {
            setTl(compute(targetIso));
        }, 1000);

        return () => window.clearInterval(id);
    }, [targetIso]);

    if (tl.isPast) {
        return (
            <div className="text-sm font-medium text-muted-foreground">
                {pastLabel}
            </div>
        );
    }

    return (
        <div className="grid grid-cols-4 gap-2">
            <Unit value={tl.days} label="days" />
            <Unit value={tl.hours} label="hrs" />
            <Unit value={tl.minutes} label="min" />
            <Unit value={tl.seconds} label="sec" />
        </div>
    );
}

function Unit({ value, label }: { value: number; label: string }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-md bg-muted py-2">
            <span className="text-2xl font-bold tabular-nums">
                {String(value).padStart(2, '0')}
            </span>
            <span className="text-[10px] tracking-wider text-muted-foreground uppercase">
                {label}
            </span>
        </div>
    );
}
