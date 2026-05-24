import { useEffect, useRef, useState } from 'react';

/**
 * Subscribes to the per-organisation scan stream when Laravel Echo is
 * mounted on `window.Echo`. Returns the most-recent scans in reverse
 * chronological order (capped at `limit`).
 *
 * Echo is loaded by the host app's bootstrap; without it, this hook
 * no-ops and `connected` stays false. That means the UI can render
 * an empty live-feed gracefully on a stock dev install (the default
 * BROADCAST_CONNECTION=null path) and only light up when an operator
 * wires up Reverb/Pusher/Ably + Echo.
 *
 * The event payload mirrors `TicketScanned::broadcastWith()`.
 */
export type LiveScan = {
    scan_uuid: string;
    verdict: 'allow' | 'warn' | 'deny';
    reason_code: string | null;
    was_admitted: boolean;
    was_duplicate: boolean;
    was_voided: boolean;
    payload: string;
    event_id: number | null;
    profile_uuid: string | null;
    device_uuid: string | null;
    flags: Array<{ rule: string; outcome: string; message: string | null }> | null;
    created_at: string | null;
};

type EchoLike = {
    private: (channel: string) => {
        listen: (event: string, cb: (payload: LiveScan) => void) => unknown;
        stopListening: (event: string) => unknown;
    };
    leave: (channel: string) => void;
};

declare global {
    interface Window {
        Echo?: EchoLike;
    }
}

export function useScannerStream(orgUuid: string | null | undefined, limit: number = 20) {
    const [scans, setScans] = useState<LiveScan[]>([]);
    const [connected, setConnected] = useState(false);
    const channelRef = useRef<string | null>(null);

    useEffect(() => {
        if (!orgUuid) return;
        const echo = typeof window !== 'undefined' ? window.Echo : undefined;
        if (!echo) {
            // Broadcasting infra absent — render a static UI cleanly.
            setConnected(false);
            return;
        }

        const channel = `organization.${orgUuid}.scans`;
        channelRef.current = channel;
        const ch = echo.private(channel);

        ch.listen('.ticket.scanned', (payload: LiveScan) => {
            setScans((prev) => [payload, ...prev].slice(0, limit));
        });

        setConnected(true);

        return () => {
            if (channelRef.current) {
                echo.leave(channelRef.current);
                channelRef.current = null;
            }
            setConnected(false);
        };
    }, [orgUuid, limit]);

    return { scans, connected };
}
