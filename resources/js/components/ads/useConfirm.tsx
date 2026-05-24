import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';
import { ConfirmDialog } from './ConfirmDialog';

interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: 'default' | 'destructive';
}

type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>;

const ConfirmContext = createContext<ConfirmFn | null>(null);

interface PendingPrompt {
    options: ConfirmOptions;
    resolve: (decision: boolean) => void;
}

/**
 * Imperative confirm() hook. Drop <ConfirmProvider> near the app
 * root; call useConfirm() anywhere to get a promise-returning
 * confirm function:
 *
 *   const confirm = useConfirm();
 *   if (await confirm({ title: 'Delete?', tone: 'destructive' })) {
 *       deleteIt();
 *   }
 */
export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [pending, setPending] = useState<PendingPrompt | null>(null);
    const resolveRef = useRef<((d: boolean) => void) | null>(null);

    const confirm = useCallback<ConfirmFn>((options) => {
        return new Promise<boolean>((resolve) => {
            resolveRef.current = resolve;
            setPending({ options, resolve });
        });
    }, []);

    const close = useCallback((decision: boolean) => {
        const resolve = resolveRef.current;
        resolveRef.current = null;
        setPending(null);
        resolve?.(decision);
    }, []);

    const value = useMemo<ConfirmFn>(() => confirm, [confirm]);

    return (
        <ConfirmContext.Provider value={value}>
            {children}
            {pending ? (
                <ConfirmDialog
                    isOpen={true}
                    onClose={() => close(false)}
                    onConfirm={() => close(true)}
                    title={pending.options.title}
                    message={pending.options.description ?? ''}
                    confirmLabel={pending.options.confirmLabel}
                    cancelLabel={pending.options.cancelLabel}
                    isDangerous={pending.options.tone === 'destructive'}
                />
            ) : null}
        </ConfirmContext.Provider>
    );
}

export function useConfirm(): ConfirmFn {
    const ctx = useContext(ConfirmContext);
    if (!ctx) throw new Error('useConfirm must be called inside <ConfirmProvider>.');
    return ctx;
}
