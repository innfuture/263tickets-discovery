import { AlertTriangle, Loader2 } from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * App-wide confirmation modal — single instance mounted at the Inertia root,
 * driven imperatively by `useConfirm()`. Designed to replace every
 * `window.confirm()` call site so confirmations get consistent styling,
 * keyboard handling, async-aware "in flight" states, and are testable.
 *
 * Usage from anywhere inside the React tree:
 *
 *   const confirm = useConfirm();
 *   const ok = await confirm({
 *       title: 'Delete category?',
 *       description: 'All tickets in this category will be removed.',
 *       confirmLabel: 'Delete',
 *       tone: 'destructive',
 *   });
 *   if (ok) router.delete(url);
 *
 * Supports `onConfirm` as an async function — when supplied, the dialog
 * stays open until the promise resolves (button shows a spinner), so
 * network errors can be caught and the user can retry without re-opening.
 */

export type ConfirmTone = 'default' | 'destructive' | 'warning';

export type ConfirmOptions = {
    title: string;
    description?: React.ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: ConfirmTone;
    /**
     * Optional async work performed when the user confirms. The dialog
     * shows a spinner + disables the buttons while the promise is pending,
     * then closes on resolve. If the promise rejects the dialog stays open
     * so the caller can show an inline error.
     */
    onConfirm?: () => Promise<void> | void;
};

type ConfirmRequest = ConfirmOptions & {
    resolve: (confirmed: boolean) => void;
};

const ConfirmationContext = React.createContext<
    ((options: ConfirmOptions) => Promise<boolean>) | null
>(null);

export function ConfirmationDialogProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const [request, setRequest] = React.useState<ConfirmRequest | null>(null);
    const [pending, setPending] = React.useState(false);
    const [error, setError] = React.useState<string | null>(null);

    const confirm = React.useCallback<
        (options: ConfirmOptions) => Promise<boolean>
    >((options) => {
        setError(null);

        return new Promise<boolean>((resolve) => {
            setRequest({ ...options, resolve });
        });
    }, []);

    const close = (confirmed: boolean) => {
        if (request) {
            request.resolve(confirmed);
        }
        setRequest(null);
        setPending(false);
        setError(null);
    };

    const handleConfirm = async () => {
        if (!request) return;

        // Synchronous confirm — close immediately + let the caller act.
        if (!request.onConfirm) {
            close(true);
            return;
        }

        setPending(true);
        setError(null);

        try {
            await request.onConfirm();
            close(true);
        } catch (e) {
            // Keep dialog open so the user can retry / cancel.
            setError(e instanceof Error ? e.message : 'Action failed.');
            setPending(false);
        }
    };

    return (
        <ConfirmationContext.Provider value={confirm}>
            {children}
            <Dialog
                open={request !== null}
                onOpenChange={(open) => {
                    if (!open && !pending) {
                        close(false);
                    }
                }}
            >
                {request ? (
                    <DialogContent className="max-w-sm">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <ToneIcon tone={request.tone ?? 'default'} />
                                {request.title}
                            </DialogTitle>
                            {request.description ? (
                                <DialogDescription>
                                    {request.description}
                                </DialogDescription>
                            ) : null}
                        </DialogHeader>

                        {error ? (
                            <p className="rounded border border-destructive/40 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                                {error}
                            </p>
                        ) : null}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => close(false)}
                                disabled={pending}
                            >
                                {request.cancelLabel ?? 'Cancel'}
                            </Button>
                            <Button
                                type="button"
                                variant={confirmVariant(request.tone)}
                                onClick={handleConfirm}
                                disabled={pending}
                                autoFocus
                            >
                                {pending ? (
                                    <Loader2 className="size-4 animate-spin" />
                                ) : null}
                                {request.confirmLabel ?? 'Confirm'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                ) : null}
            </Dialog>
        </ConfirmationContext.Provider>
    );
}

/**
 * Imperative confirm — returns a Promise<boolean>. False on cancel /
 * escape / backdrop click; true after the user confirms (and after the
 * optional `onConfirm` async work resolves).
 */
export function useConfirm(): (options: ConfirmOptions) => Promise<boolean> {
    const ctx = React.useContext(ConfirmationContext);

    if (!ctx) {
        throw new Error(
            'useConfirm() must be called inside <ConfirmationDialogProvider>. '
            +'Mount it once at the Inertia root (see resources/js/app.tsx).',
        );
    }

    return ctx;
}

function ToneIcon({ tone }: { tone: ConfirmTone }) {
    if (tone === 'default') return null;

    return (
        <AlertTriangle
            className={cn(
                'size-5',
                tone === 'destructive' && 'text-destructive',
                tone === 'warning' && 'text-amber-500',
            )}
        />
    );
}

function confirmVariant(
    tone: ConfirmTone | undefined,
): React.ComponentProps<typeof Button>['variant'] {
    if (tone === 'destructive') return 'destructive';

    return 'default';
}
