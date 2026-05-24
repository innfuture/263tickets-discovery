import { FlagsProvider, useFlags } from '@atlaskit/flag';
import SuccessIcon from '@atlaskit/icon/core/check-circle';
import ErrorIcon from '@atlaskit/icon/core/error';
import WarningIcon from '@atlaskit/icon/core/warning';
import InfoIcon from '@atlaskit/icon/core/information';
import { token } from '@atlaskit/tokens';
import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastAction {
    content: string;
    onClick: () => void;
}

export interface ToastOptions {
    title: string;
    description?: string;
    type?: ToastType;
    /** Milliseconds; 0 = persistent until dismissed. Default 5000. */
    duration?: number;
    actions?: ToastAction[];
}

interface ToastContextValue {
    toast: (options: ToastOptions) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

/**
 * Inner consumer of useFlags — must live inside FlagsProvider, and
 * itself provides our `toast(...)` API to the rest of the tree.
 */
function ToastBridge({ children }: { children: ReactNode }) {
    const { showFlag } = useFlags();

    const toast = useCallback(
        (options: ToastOptions) => {
            const iconMap = {
                success: <SuccessIcon label="Success" color={token('color.icon.success', '#22A06B')} />,
                error: <ErrorIcon label="Error" color={token('color.icon.danger', '#C9372C')} />,
                warning: <WarningIcon label="Warning" color={token('color.icon.warning', '#E2B203')} />,
                info: <InfoIcon label="Info" color={token('color.icon.information', '#1D7AFC')} />,
            } as const;

            const duration = options.duration ?? 5000;

            showFlag({
                icon: iconMap[options.type ?? 'info'],
                title: options.title,
                description: options.description,
                actions: options.actions,
                isAutoDismiss: duration > 0,
            });
        },
        [showFlag],
    );

    const value = useMemo<ToastContextValue>(() => ({ toast }), [toast]);

    return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>;
}

/**
 * Wrap the app root with ToastProvider; call useToast() anywhere to
 * push a Flag. Persistent and auto-dismissing variants both supported.
 *
 * Independent of the existing shadcn Toaster (sonner); they render in
 * different portals and don't collide. Migrated pages use this;
 * legacy pages keep using Toaster.
 */
export function ToastProvider({ children }: { children: ReactNode }) {
    return (
        <FlagsProvider>
            <ToastBridge>{children}</ToastBridge>
        </FlagsProvider>
    );
}

export function useToast(): ToastContextValue {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast must be called inside <ToastProvider>.');
    return ctx;
}
