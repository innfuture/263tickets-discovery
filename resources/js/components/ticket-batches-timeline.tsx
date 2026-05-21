import {
    Check,
    ChevronDown,
    ChevronUp,
    Clock,
    Loader2,
    Minus,
    PackagePlus,
    Plus,
    XCircle,
} from 'lucide-react';
import * as React from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type TicketBatchView = {
    id: number;
    batch_number: number;
    operation: { value: string; label: string; sign: number };
    quantity: number;
    actual_quantity: number;
    status: { value: string; label: string };
    progress: number | null;
    reason: string | null;
    actor_name: string | null;
    created_at: string | null;
    completed_at: string | null;
};

/**
 * Collapsible batches panel — one row per OfflineTicketBatch, oldest at
 * top. Single source of truth for "where did this category's tickets
 * come from?": initial creation, every increase, every decrease.
 *
 * Visual convention:
 *   - +N green pill for create/increase
 *   - -N amber pill for decrease
 *   - Status icon: spinner (pending/processing), check (completed),
 *     red X (failed)
 *   - Reason + actor + relative time below the headline
 */
export function TicketBatchesTimeline({
    batches,
}: {
    batches: TicketBatchView[];
}) {
    const [open, setOpen] = React.useState(false);

    if (batches.length === 0) {
        return null;
    }

    return (
        <div className="rounded-md border bg-muted/20">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center justify-between px-3 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase transition hover:bg-muted/40"
            >
                <span className="flex items-center gap-1.5">
                    <PackagePlus className="size-3.5" />
                    Inventory batches
                    <Badge variant="secondary" className="text-[10px]">
                        {batches.length}
                    </Badge>
                </span>
                {open ? (
                    <ChevronUp className="size-3.5" />
                ) : (
                    <ChevronDown className="size-3.5" />
                )}
            </button>

            {open ? (
                <ol className="divide-y border-t">
                    {batches.map((b) => (
                        <BatchRow key={b.id} batch={b} />
                    ))}
                </ol>
            ) : null}
        </div>
    );
}

function BatchRow({ batch }: { batch: TicketBatchView }) {
    const sign = batch.operation.sign;
    const isIncrease = sign === 1;
    const status = batch.status.value;

    return (
        <li className="flex items-start gap-3 px-3 py-2 text-xs">
            <span className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-muted font-semibold tabular-nums">
                #{batch.batch_number}
            </span>

            <div className="min-w-0 flex-1 space-y-0.5">
                <div className="flex items-center gap-1.5">
                    <span
                        className={cn(
                            'inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                            isIncrease
                                ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                : 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
                        )}
                    >
                        {isIncrease ? (
                            <Plus className="size-2.5" />
                        ) : (
                            <Minus className="size-2.5" />
                        )}
                        {batch.quantity.toLocaleString()}
                    </span>
                    <span className="text-muted-foreground">
                        {batch.operation.label}
                    </span>
                    <StatusIcon status={status} />
                </div>

                {batch.reason ? (
                    <p className="text-[11px] text-muted-foreground italic">
                        "{batch.reason}"
                    </p>
                ) : null}

                <p className="text-[10px] text-muted-foreground">
                    {batch.actor_name ? `${batch.actor_name} · ` : ''}
                    {formatRelative(batch.completed_at ?? batch.created_at)}
                    {status === 'completed' &&
                    batch.actual_quantity !== batch.quantity ? (
                        <span className="ml-1 text-amber-600">
                            (actually {batch.actual_quantity})
                        </span>
                    ) : null}
                </p>

                {batch.progress !== null &&
                (status === 'pending' || status === 'processing') ? (
                    <div className="mt-1 h-1 w-full overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full bg-primary transition-all"
                            style={{ width: `${batch.progress}%` }}
                        />
                    </div>
                ) : null}
            </div>
        </li>
    );
}

function StatusIcon({ status }: { status: string }) {
    if (status === 'completed') {
        return (
            <Check
                className="size-3 text-emerald-600 dark:text-emerald-400"
                aria-label="Completed"
            />
        );
    }
    if (status === 'failed') {
        return (
            <XCircle
                className="size-3 text-destructive"
                aria-label="Failed"
            />
        );
    }
    if (status === 'processing') {
        return (
            <Loader2
                className="size-3 animate-spin text-primary"
                aria-label="Processing"
            />
        );
    }
    return (
        <Clock
            className="size-3 text-muted-foreground"
            aria-label="Pending"
        />
    );
}

function formatRelative(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const diffSec = Math.round((Date.now() - date.getTime()) / 1000);

    if (diffSec < 60) return 'just now';
    if (diffSec < 3600) return `${Math.round(diffSec / 60)}m ago`;
    if (diffSec < 86400) return `${Math.round(diffSec / 3600)}h ago`;
    if (diffSec < 86400 * 7) return `${Math.round(diffSec / 86400)}d ago`;

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
