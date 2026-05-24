import { Head, Link } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { BrandIcon } from '@/components/brand-icon';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };
type Platform = { key: string; label: string; connected: boolean };
type Campaign = {
    id: number;
    name: string;
    platform: string | null;
    status: string | null;
    budget_daily: string | null;
    budget_total: string | null;
    budget_currency: string | null;
    runs_from: string | null;
    runs_until: string | null;
    event: { slug: string; name: string } | null;
    metrics: Record<string, unknown> | null;
    metrics_synced_at: string | null;
};

type Props = { platforms: Platform[]; campaigns: Campaign[]; breadcrumbs: Breadcrumb[] };

const STATUS_COLOR: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-800',
    paused: 'bg-amber-100 text-amber-800',
    draft: 'bg-slate-100 text-slate-700',
    archived: 'bg-slate-100 text-slate-700',
    failed: 'bg-rose-100 text-rose-800',
};

function platformIconKey(k: string | null): string {
    if (!k) return 'meta';
    if (k === 'meta' || k === 'facebook' || k === 'instagram') return 'meta';
    if (k === 'google' || k === 'google_ads') return 'google_ads';
    return k;
}

export default function PaidAds({ platforms, campaigns }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');

    return (
        <>
            <Head title="Paid Social Ads" />
            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Paid Social Ads"
                    description="Org-wide view of every paid campaign. Per-event ad management still lives on the event page."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Ad platforms</CardTitle>
                        <CardDescription>
                            Connect at least one ad platform from{' '}
                            <Link href={`${orgBase}/marketing/integrations`} className="underline">
                                Integrations
                            </Link>{' '}
                            before launching a new campaign.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-3">
                        {platforms.map((p) => (
                            <div
                                key={p.key}
                                className="flex items-center justify-between rounded-md border p-3 text-sm"
                            >
                                <span className="inline-flex items-center gap-2 font-medium">
                                    <BrandIcon provider={platformIconKey(p.key)} size={18} />
                                    {p.label}
                                </span>
                                <span
                                    className={
                                        p.connected
                                            ? 'rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary'
                                            : 'rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground'
                                    }
                                >
                                    {p.connected ? 'Connected' : 'Not connected'}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">All campaigns</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        {campaigns.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-10 text-center">
                                <Megaphone className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">No paid campaigns yet</p>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Create campaigns from any event's Ads tab. They surface here aggregated across the
                                    organisation.
                                </p>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Campaign</th>
                                        <th className="p-3">Event</th>
                                        <th className="p-3">Platform</th>
                                        <th className="p-3">Budget</th>
                                        <th className="p-3">Window</th>
                                        <th className="p-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaigns.map((c) => (
                                        <tr key={c.id} className="border-t hover:bg-muted/40">
                                            <td className="p-3">{c.name}</td>
                                            <td className="p-3 text-xs">
                                                {c.event ? (
                                                    <Link
                                                        href={`${orgBase}/events/${c.event.slug}/ads`}
                                                        className="hover:underline"
                                                    >
                                                        {c.event.name}
                                                    </Link>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </td>
                                            <td className="p-3 text-xs">
                                                <span className="inline-flex items-center gap-1">
                                                    {c.platform && <BrandIcon provider={platformIconKey(c.platform)} size={14} />}
                                                    {c.platform ?? '—'}
                                                </span>
                                            </td>
                                            <td className="p-3 font-mono text-xs">
                                                {c.budget_total
                                                    ? `${c.budget_total} ${c.budget_currency ?? ''} total`
                                                    : c.budget_daily
                                                      ? `${c.budget_daily} ${c.budget_currency ?? ''}/day`
                                                      : '—'}
                                            </td>
                                            <td className="p-3 text-xs text-muted-foreground">
                                                {c.runs_from?.slice(0, 10) ?? '—'}
                                                {c.runs_until && ` → ${c.runs_until.slice(0, 10)}`}
                                            </td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        STATUS_COLOR[c.status ?? ''] ?? 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {c.status ?? 'unknown'}
                                                </span>
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

PaidAds.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
