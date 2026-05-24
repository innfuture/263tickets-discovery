import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { useToast } from './Toast';

interface FlashPayload {
    type?: 'success' | 'error' | 'warning' | 'info';
    message?: string;
    title?: string;
    description?: string;
}

/**
 * Mounts inside ToastProvider; subscribes to Inertia's flash event
 * and forwards `flash.toast` payloads to the ADS toast surface. Pair
 * with controllers that emit `Inertia::flash('toast', [...])` after
 * mutating actions.
 *
 * Replaces use-flash-toast.ts (sonner-backed) for ADS-only pages.
 */
export function FlashToastBridge() {
    const { toast } = useToast();

    useEffect(() => {
        return router.on('flash', (event) => {
            const payload = (event as CustomEvent).detail?.flash?.toast as FlashPayload | undefined;
            if (!payload?.message && !payload?.title) return;

            toast({
                type: payload.type ?? 'info',
                title: payload.title ?? payload.message ?? '',
                description: payload.description ?? (payload.title ? payload.message : undefined),
            });
        });
    }, [toast]);

    return null;
}
