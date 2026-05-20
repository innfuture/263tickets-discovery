import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Standardised typographic header used across Event Details cards.
 *
 * Contract:
 *  - `text-sm font-semibold` size (consistent across every panel)
 *  - Optional leading icon, rendered in primary tint
 *  - Optional count badge (e.g. "Tickets · 3")
 *  - Optional right-aligned actions slot
 *  - Optional muted subtitle on the right (e.g. "last 30 days")
 */
export function SectionTitle({
    icon,
    children,
    count,
    subtitle,
    actions,
    className,
}: {
    icon?: React.ReactNode;
    children: React.ReactNode;
    count?: number;
    subtitle?: string;
    actions?: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-between gap-2',
                className,
            )}
        >
            <h2 className="flex min-w-0 items-center gap-2 text-sm font-semibold">
                {icon ? (
                    <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                        {icon}
                    </span>
                ) : null}
                <span className="truncate">{children}</span>
                {typeof count === 'number' && count > 0 ? (
                    <Badge variant="secondary" className="text-[10px]">
                        {count}
                    </Badge>
                ) : null}
                {subtitle ? (
                    <span className="text-xs font-normal text-muted-foreground">
                        · {subtitle}
                    </span>
                ) : null}
            </h2>
            {actions ? (
                <div className="flex shrink-0 items-center gap-1">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
