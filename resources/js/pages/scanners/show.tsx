import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Copy, KeyRound, MapPin, Plus, Radio, Smartphone, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useScannerStream, type LiveScan } from '@/hooks/use-scanner-stream';

type Breadcrumb = { title: string; href: string };
type EventOption = { value: number; label: string };

type Profile = {
    uuid: string;
    name: string;
    description: string | null;
    status: string;
    capabilities: string[];
    allowed_event_ids: number[];
    ip_allowlist: string[];
    max_scans_per_minute: number;
    duplicate_window_seconds: number;
    webhook_url: string | null;
    has_webhook_secret: boolean;
};

type Device = {
    uuid: string;
    label: string;
    platform: string;
    app_version: string | null;
    token_prefix: string;
    last_seen_at: string | null;
    last_known_ip: string | null;
    last_known_lat: string | null;
    last_known_lng: string | null;
    status: string;
    revoked_at: string | null;
    revoked_reason: string | null;
};

type PendingCode = { code: string; expires_at: string | null; hint_label: string | null };

type RecentScan = {
    uuid: string;
    payload: string;
    verdict: string;
    reason_code: string | null;
    was_admitted: boolean;
    created_at: string | null;
    flags: Array<{ rule: string; outcome: string; message: string | null }>;
};

type Props = {
    org_uuid: string;
    profile: Profile;
    devices: Device[];
    pending_codes: PendingCode[];
    recent_scans: RecentScan[];
    event_options: EventOption[];
    capability_options: string[];
    breadcrumbs: Breadcrumb[];
};

const VERDICT_COLOR: Record<string, string> = {
    allow: 'bg-emerald-100 text-emerald-800',
    warn: 'bg-amber-100 text-amber-800',
    deny: 'bg-rose-100 text-rose-800',
};

export default function ScannerShow({ org_uuid, profile, devices, pending_codes, recent_scans }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [hintLabel, setHintLabel] = useState('');
    const { scans: liveScans, connected: liveConnected } = useScannerStream(org_uuid, 30);

    // Merge live + initial scans, de-duped by uuid. Live entries
    // override the static ones (newer state wins). The static list
    // becomes the "before the page loaded" history.
    const mergedScans = useMemo(() => {
        const seen = new Set<string>();
        const out: RecentScan[] = [];
        // Live first — they're newer.
        for (const s of liveScans) {
            if (s.profile_uuid && s.profile_uuid !== profile.uuid) continue;
            if (seen.has(s.scan_uuid)) continue;
            seen.add(s.scan_uuid);
            out.push(liveScanToRow(s));
        }
        for (const s of recent_scans) {
            if (seen.has(s.uuid)) continue;
            seen.add(s.uuid);
            out.push(s);
        }
        return out.slice(0, 50);
    }, [liveScans, recent_scans, profile.uuid]);

    const issueCode = () => {
        router.post(
            `${orgBase}/scanners/${profile.uuid}/pairing-codes`,
            { hint_label: hintLabel || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Pairing code generated.');
                    setHintLabel('');
                },
            },
        );
    };

    const revoke = (deviceUuid: string, label: string) => {
        const reason = window.prompt(`Revoke "${label}"? Enter an optional reason (lost / stolen / replaced):`, '');
        if (reason === null) return;
        router.post(
            `${orgBase}/scanners/${profile.uuid}/devices/${deviceUuid}/revoke`,
            { reason },
            { preserveScroll: true, onSuccess: () => toast.success('Device revoked.') },
        );
    };

    const copy = (text: string) => {
        navigator.clipboard.writeText(text).then(() => toast.success('Copied.'));
    };

    return (
        <>
            <Head title={`Scanner · ${profile.name}`} />
            <div className="space-y-4 p-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`${orgBase}/scanners`}>
                        <ArrowLeft className="mr-1 size-4" /> Back to scanners
                    </Link>
                </Button>

                <div className="flex items-start justify-between gap-2">
                    <Heading
                        variant="small"
                        title={profile.name}
                        description={profile.description ?? 'Scanner profile + paired devices.'}
                    />
                    <span
                        className={`rounded px-2 py-0.5 text-xs ${
                            profile.status === 'active'
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-slate-100 text-slate-700'
                        }`}
                    >
                        {profile.status}
                    </span>
                </div>

                {/* Policy summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Policy</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-4 text-sm">
                        <KV label="Capabilities">
                            <div className="flex flex-wrap gap-1">
                                {profile.capabilities.map((c) => (
                                    <span key={c} className="rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase">
                                        {c}
                                    </span>
                                ))}
                            </div>
                        </KV>
                        <KV label="Rate cap">{profile.max_scans_per_minute}/min</KV>
                        <KV label="Duplicate window">{profile.duplicate_window_seconds}s</KV>
                        <KV label="Webhook">
                            {profile.webhook_url ? (
                                <span className="inline-flex items-center gap-1 text-xs">
                                    <KeyRound className="size-3" />
                                    {profile.has_webhook_secret ? 'signed' : 'unsigned'}
                                </span>
                            ) : (
                                <span className="text-xs text-muted-foreground">—</span>
                            )}
                        </KV>
                        <KV label="Allowed events">
                            {profile.allowed_event_ids.length === 0
                                ? 'all events'
                                : `${profile.allowed_event_ids.length} explicit`}
                        </KV>
                        <KV label="IP allowlist">
                            {profile.ip_allowlist.length === 0
                                ? 'any IP'
                                : profile.ip_allowlist.join(', ')}
                        </KV>
                    </CardContent>
                </Card>

                {/* Pairing codes */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <KeyRound className="size-4" /> Pairing codes
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="grid gap-1">
                                <label className="text-xs text-muted-foreground">Label (optional)</label>
                                <Input
                                    value={hintLabel}
                                    onChange={(e) => setHintLabel(e.target.value)}
                                    placeholder="John's iPhone"
                                    className="w-56"
                                />
                            </div>
                            <Button size="sm" onClick={issueCode}>
                                <Plus className="mr-1 size-4" /> Generate code
                            </Button>
                        </div>

                        {pending_codes.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No unused codes. Codes expire automatically; once paired, the device receives a long-lived
                                token instead.
                            </p>
                        ) : (
                            <ul className="space-y-1">
                                {pending_codes.map((c) => (
                                    <li
                                        key={c.code}
                                        className="flex items-center justify-between rounded border bg-card p-2 text-sm"
                                    >
                                        <div>
                                            <code className="text-base font-mono font-semibold tracking-widest">
                                                {c.code}
                                            </code>
                                            {c.hint_label && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    for {c.hint_label}
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <span>expires {c.expires_at}</span>
                                            <Button size="sm" variant="ghost" onClick={() => copy(c.code)}>
                                                <Copy className="size-3.5" />
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {/* Paired devices */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Smartphone className="size-4" /> Paired devices
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        {devices.length === 0 ? (
                            <p className="px-6 pb-6 text-sm text-muted-foreground">No devices paired yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Device</th>
                                        <th className="p-3">Platform</th>
                                        <th className="p-3">Token</th>
                                        <th className="p-3">Last seen</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {devices.map((d) => (
                                        <tr key={d.uuid} className="border-t hover:bg-muted/40">
                                            <td className="p-3">
                                                <p className="font-medium">{d.label}</p>
                                                {d.app_version && (
                                                    <p className="text-xs text-muted-foreground">v{d.app_version}</p>
                                                )}
                                            </td>
                                            <td className="p-3 text-xs">{d.platform}</td>
                                            <td className="p-3 font-mono text-xs">{d.token_prefix}…</td>
                                            <td className="p-3 text-xs text-muted-foreground">
                                                <div>{d.last_seen_at ?? '—'}</div>
                                                {d.last_known_ip && (
                                                    <div className="flex items-center gap-1">
                                                        <MapPin className="size-3" />
                                                        {d.last_known_ip}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        d.status === 'active'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : 'bg-rose-100 text-rose-800'
                                                    }`}
                                                >
                                                    {d.status}
                                                </span>
                                            </td>
                                            <td className="p-3 text-right">
                                                {d.status === 'active' && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => revoke(d.uuid, d.label)}
                                                        title="Revoke device"
                                                    >
                                                        <X className="size-3.5" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                {/* Recent scans — fed by the static page payload + the live WS stream */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="text-base">Recent scans</CardTitle>
                        <span
                            className={`inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] uppercase tracking-wide ${
                                liveConnected
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : 'bg-slate-100 text-slate-600'
                            }`}
                            title={
                                liveConnected
                                    ? 'Live: receiving scans over WebSocket'
                                    : 'Static: configure BROADCAST_CONNECTION + Echo to stream live'
                            }
                        >
                            <Radio
                                className={`size-3 ${liveConnected ? 'animate-pulse' : ''}`}
                            />
                            {liveConnected ? 'Live' : 'Static'}
                        </span>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        {mergedScans.length === 0 ? (
                            <p className="px-6 pb-6 text-sm text-muted-foreground">No scans yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">When</th>
                                        <th className="p-3">Payload</th>
                                        <th className="p-3">Verdict</th>
                                        <th className="p-3">Reason</th>
                                        <th className="p-3">Flags</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {mergedScans.map((s) => (
                                        <tr key={s.uuid} className="border-t">
                                            <td className="p-3 text-xs text-muted-foreground">{s.created_at}</td>
                                            <td className="p-3 font-mono text-xs">{s.payload}</td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        VERDICT_COLOR[s.verdict] ?? 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {s.verdict}
                                                </span>
                                            </td>
                                            <td className="p-3 text-xs">{s.reason_code ?? '—'}</td>
                                            <td className="p-3 text-xs">
                                                {s.flags.length === 0 ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : (
                                                    <div className="flex flex-wrap gap-1">
                                                        {s.flags.map((f, i) => (
                                                            <span
                                                                key={`${s.uuid}-${i}`}
                                                                className="rounded bg-muted px-1.5 py-0.5 text-[10px]"
                                                                title={f.message ?? ''}
                                                            >
                                                                {f.rule}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function liveScanToRow(s: LiveScan): RecentScan {
    return {
        uuid: s.scan_uuid,
        payload: s.payload,
        verdict: s.verdict,
        reason_code: s.reason_code,
        was_admitted: s.was_admitted,
        created_at: s.created_at,
        flags: (s.flags ?? []).map((f) => ({
            rule: f.rule,
            outcome: f.outcome,
            message: f.message,
        })),
    };
}

function KV({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <div className="mt-0.5">{children}</div>
        </div>
    );
}

ScannerShow.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
