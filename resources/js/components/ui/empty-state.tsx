import { cn } from '@/lib/utils';

/**
 * Standardised empty state used across Event Details cards.
 *
 * Visual contract:
 *  - A theme-coloured (rounded) icon medallion sits centred above the copy.
 *  - The whole block is wrapped in a subtle dashed border + tinted background
 *    so empty regions feel intentional rather than broken / unfinished.
 */
export function EmptyState({
    icon,
    title,
    description,
    action,
    tone = 'primary',
    className,
}: {
    icon: React.ReactNode;
    title: string;
    description?: string;
    action?: React.ReactNode;
    tone?: 'primary' | 'muted' | 'accent';
    className?: string;
}) {
    const toneClasses = {
        primary: {
            wrapper: 'border-primary/20 bg-primary/3',
            icon: 'bg-primary/10 text-primary ring-primary/20',
        },
        muted: {
            wrapper: 'border-muted-foreground/20 bg-muted/30',
            icon: 'bg-muted text-muted-foreground ring-muted-foreground/10',
        },
        accent: {
            wrapper: 'border-amber-400/30 bg-amber-50 dark:bg-amber-950/20',
            icon: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-700/40',
        },
    }[tone];

    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-4 py-8 text-center',
                toneClasses.wrapper,
                className,
            )}
        >
            <div
                className={cn(
                    'flex size-14 items-center justify-center rounded-full ring-4',
                    toneClasses.icon,
                )}
            >
                {icon}
            </div>
            <div className="space-y-1">
                <p className="text-sm font-semibold">{title}</p>
                {description ? (
                    <p className="max-w-xs text-xs leading-relaxed text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {action ? <div className="pt-1">{action}</div> : null}
        </div>
    );
}
