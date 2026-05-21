import { router } from '@inertiajs/react';
import {
    Hash,
    Loader2,
    Minus,
    Package,
    PackageMinus,
    PackagePlus,
    Plus,
} from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Modal launcher for an inventory-adjustment batch. Submits to
 * POST .../tickets/{uuid}/adjust with a signed delta + reason.
 *
 * Two-step UI: organizer picks a direction (Add / Remove) then types a
 * count. The dialog shows the current balance vs voidable balance so the
 * organizer can't intuit themselves into a clamp error.
 */
export function TicketInventoryAdjustButton({
    endpoint,
    categoryName,
    currentTotal,
    voidableCount,
}: {
    endpoint: string;
    categoryName: string;
    currentTotal: number;
    voidableCount: number;
}) {
    const [open, setOpen] = React.useState(false);
    const [direction, setDirection] = React.useState<'add' | 'remove'>('add');
    const [count, setCount] = React.useState('');
    const [reason, setReason] = React.useState('');
    const [submitting, setSubmitting] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const parsedCount = Number.parseInt(count, 10);
    const validCount = Number.isFinite(parsedCount) && parsedCount > 0;
    const upperBound = direction === 'remove' ? voidableCount : 100_000;
    const overLimit = validCount && parsedCount > upperBound;

    const reset = () => {
        setCount('');
        setReason('');
        setError(null);
        setDirection('add');
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!validCount || overLimit) return;

        const delta = direction === 'add' ? parsedCount : -parsedCount;
        setSubmitting(true);
        setError(null);

        router.post(
            endpoint,
            { delta, reason: reason || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    reset();
                },
                onError: (errs) => {
                    setError(Object.values(errs)[0] ?? 'Adjustment failed.');
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (!next) reset();
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="h-7">
                    <Package className="size-3.5" />
                    Adjust inventory
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Package className="size-5 text-primary" />
                        Adjust "{categoryName}" inventory
                    </DialogTitle>
                    <DialogDescription>
                        Every change is recorded as a numbered batch with the
                        actor, reason, and timestamp. Adding mints new
                        tickets; removing voids the most-recently-minted
                        unsold ones (LIFO).
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3 rounded-md border bg-muted/30 p-3 text-sm">
                        <div>
                            <p className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Active inventory
                            </p>
                            <p className="text-lg font-semibold tabular-nums">
                                {currentTotal.toLocaleString()}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Removable now
                            </p>
                            <p className="text-lg font-semibold tabular-nums">
                                {voidableCount.toLocaleString()}
                            </p>
                            <p className="text-[10px] text-muted-foreground">
                                Unsold + unscanned
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <DirectionButton
                            active={direction === 'add'}
                            onClick={() => setDirection('add')}
                            icon={<PackagePlus className="size-4" />}
                            label="Add tickets"
                        />
                        <DirectionButton
                            active={direction === 'remove'}
                            onClick={() => setDirection('remove')}
                            icon={<PackageMinus className="size-4" />}
                            label="Remove tickets"
                            disabled={voidableCount === 0}
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="adjust-count"
                            className="flex items-center gap-1.5 text-xs"
                        >
                            <Hash className="size-3.5" />
                            How many to{' '}
                            {direction === 'add' ? 'add' : 'remove'}?
                        </Label>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-2 flex items-center text-muted-foreground">
                                {direction === 'add' ? (
                                    <Plus className="size-4" />
                                ) : (
                                    <Minus className="size-4" />
                                )}
                            </span>
                            <Input
                                id="adjust-count"
                                type="number"
                                min={1}
                                max={upperBound}
                                step={1}
                                value={count}
                                onChange={(e) => setCount(e.target.value)}
                                className="pl-7 tabular-nums"
                                autoFocus
                                required
                            />
                        </div>
                        {overLimit ? (
                            <p className="text-xs text-destructive">
                                Only {voidableCount.toLocaleString()} tickets
                                are unsold and unscanned right now.
                            </p>
                        ) : null}
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="adjust-reason" className="text-xs">
                            Reason{' '}
                            <span className="text-muted-foreground">
                                (optional — appears in the audit timeline)
                            </span>
                        </Label>
                        <Textarea
                            id="adjust-reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={2}
                            maxLength={280}
                            placeholder="e.g. Added 50 more for the late-release window"
                            className="resize-none"
                        />
                    </div>

                    {error ? (
                        <p className="text-xs text-destructive">{error}</p>
                    ) : null}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                !validCount || overLimit || submitting
                            }
                        >
                            {submitting ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : direction === 'add' ? (
                                <PackagePlus className="size-4" />
                            ) : (
                                <PackageMinus className="size-4" />
                            )}
                            {direction === 'add'
                                ? `Add ${validCount ? parsedCount.toLocaleString() : ''}`
                                : `Remove ${validCount ? parsedCount.toLocaleString() : ''}`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DirectionButton({
    active,
    onClick,
    icon,
    label,
    disabled,
}: {
    active: boolean;
    onClick: () => void;
    icon: React.ReactNode;
    label: string;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={cn(
                'flex items-center justify-center gap-2 rounded-md border p-3 text-sm font-medium transition',
                active
                    ? 'border-primary bg-primary/5 text-primary'
                    : 'border-input text-muted-foreground hover:border-input/80 hover:text-foreground',
                disabled && 'cursor-not-allowed opacity-50',
            )}
        >
            {icon}
            {label}
        </button>
    );
}
