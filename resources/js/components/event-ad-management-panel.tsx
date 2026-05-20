import { router } from '@inertiajs/react';
import {
    Eye,
    Facebook,
    Loader2,
    Megaphone,
    MousePointerClick,
    Pause,
    PlusCircle,
    RefreshCw,
    Trash2,
    Youtube,
} from 'lucide-react';
import { useState } from 'react';
import { AdCampaignDialog } from '@/components/ad-campaign-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { SectionTitle } from '@/components/ui/section-title';
import { formatPrice } from '@/lib/currencies';
import { cn } from '@/lib/utils';

type Platform = { value: string; label: string };
type CampaignStatus = { value: string; label: string };

type CampaignMetrics = {
    impressions?: number;
    clicks?: number;
    conversions?: number;
    spend?: number;
    ctr?: number;
    cpc?: number;
};

type Campaign = {
    id: number;
    uuid: string;
    name: string;
    platform: Platform;
    campaign_status: CampaignStatus;
    budget_daily: number | null;
    budget_total: number | null;
    budget_currency: string;
    runs_from: string | null;
    runs_until: string | null;
    metrics: CampaignMetrics | null;
    metrics_synced_at?: string | null;
};

const STATUS_COLORS: Record<string, string> = {
    active: 'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-400',
    draft: 'bg-muted text-muted-foreground',
    paused: 'bg-amber-500/15 text-amber-700 border-amber-500/30 dark:text-amber-400',
    ended: 'bg-muted text-muted-foreground',
    failed: 'bg-destructive/15 text-destructive border-destructive/30',
};

function PlatformIcon({ platform }: { platform: string }) {
    if (platform === 'meta_ads') {
        return <Facebook className="size-3.5 text-blue-600" />;
    }

    if (platform === 'youtube') {
        return <Youtube className="size-3.5 text-red-600" />;
    }

    return <Megaphone className="size-3.5 text-amber-600" />;
}

function formatNumber(n: number | undefined | null): string {
    if (n == null) {
        return '—';
    }

    if (n >= 1_000_000) {
        return `${(n / 1_000_000).toFixed(1)}M`;
    }

    if (n >= 1_000) {
        return `${(n / 1_000).toFixed(1)}k`;
    }

    return n.toLocaleString();
}

function CampaignRow({
    campaign,
    teamSlug,
    eventSlug,
}: {
    campaign: Campaign;
    teamSlug: string;
    eventSlug: string;
}) {
    const base = `/${teamSlug}/events/${eventSlug}/ads/${campaign.uuid}`;
    const [syncing, setSyncing] = useState(false);

    const handleSync = () => {
        setSyncing(true);
        router.post(
            `${base}/sync`,
            {},
            {
                onFinish: () => setSyncing(false),
                preserveScroll: true,
            },
        );
    };

    const handleDelete = () => {
        if (
            !window.confirm(
                `Stop and delete campaign "${campaign.name}"? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(base, { preserveScroll: true });
    };

    const metrics = campaign.metrics ?? {};

    return (
        <div className="space-y-2 rounded-md border bg-card p-3">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1 space-y-1">
                    <div className="flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <PlatformIcon platform={campaign.platform.value} />
                        <span className="truncate">{campaign.name}</span>
                        <Badge
                            className={cn(
                                'shrink-0 rounded-full border text-xs font-normal',
                                STATUS_COLORS[campaign.campaign_status.value] ??
                                    'bg-muted',
                            )}
                        >
                            {campaign.campaign_status.label}
                        </Badge>
                    </div>
                    <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        <span>{campaign.platform.label}</span>
                        {campaign.budget_daily ? (
                            <span>
                                {formatPrice(
                                    campaign.budget_daily,
                                    campaign.budget_currency,
                                )}
                                /day
                            </span>
                        ) : null}
                        {campaign.budget_total ? (
                            <span>
                                {formatPrice(
                                    campaign.budget_total,
                                    campaign.budget_currency,
                                )}{' '}
                                cap
                            </span>
                        ) : null}
                        {campaign.runs_from ? (
                            <span>
                                {new Date(
                                    campaign.runs_from,
                                ).toLocaleDateString()}
                                {campaign.runs_until
                                    ? ` → ${new Date(campaign.runs_until).toLocaleDateString()}`
                                    : ''}
                            </span>
                        ) : null}
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        title="Sync metrics"
                        disabled={syncing}
                        onClick={handleSync}
                    >
                        {syncing ? (
                            <Loader2 className="size-3.5 animate-spin" />
                        ) : (
                            <RefreshCw className="size-3.5" />
                        )}
                    </Button>
                    {campaign.campaign_status.value === 'active' ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            title="Pause campaign"
                            onClick={() =>
                                router.post(
                                    `${base}/pause`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Pause className="size-3.5" />
                        </Button>
                    ) : null}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-destructive hover:text-destructive"
                        title="Delete campaign"
                        onClick={handleDelete}
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            </div>

            {campaign.metrics ? (
                <div className="grid grid-cols-4 gap-2 rounded-sm bg-muted/40 p-2 text-center">
                    <MetricTile
                        icon={<Eye className="size-3" />}
                        label="Impressions"
                        value={formatNumber(metrics.impressions)}
                    />
                    <MetricTile
                        icon={<MousePointerClick className="size-3" />}
                        label="Clicks"
                        value={formatNumber(metrics.clicks)}
                    />
                    <MetricTile
                        label="CTR"
                        value={
                            metrics.ctr != null
                                ? `${(metrics.ctr * 100).toFixed(2)}%`
                                : '—'
                        }
                    />
                    <MetricTile
                        label="Spend"
                        value={
                            metrics.spend != null
                                ? formatPrice(
                                      metrics.spend,
                                      campaign.budget_currency,
                                  )
                                : '—'
                        }
                    />
                </div>
            ) : null}

            {campaign.metrics_synced_at ? (
                <p className="text-[10px] text-muted-foreground italic">
                    Metrics last synced{' '}
                    {new Date(campaign.metrics_synced_at).toLocaleString()}
                </p>
            ) : null}
        </div>
    );
}

function MetricTile({
    icon,
    label,
    value,
}: {
    icon?: React.ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="space-y-0.5">
            <div className="flex items-center justify-center gap-1 text-[10px] text-muted-foreground uppercase">
                {icon}
                {label}
            </div>
            <p className="text-sm font-semibold tabular-nums">{value}</p>
        </div>
    );
}

export function EventAdManagementPanel({
    campaigns: initialCampaigns,
    teamSlug,
    eventSlug,
    eventName,
    eventUrl,
}: {
    campaigns: Campaign[];
    teamSlug: string;
    eventSlug: string;
    eventName?: string;
    eventUrl?: string;
}) {
    const createAction = `/${teamSlug}/events/${eventSlug}/ads`;

    return (
        <Card>
            <CardHeader>
                <SectionTitle
                    icon={<Megaphone className="size-4" />}
                    count={initialCampaigns.length}
                >
                    Ad Campaigns
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {initialCampaigns.length === 0 ? (
                    <EmptyState
                        tone="primary"
                        icon={<Megaphone className="size-6" />}
                        title="No campaigns yet"
                        description="Promote this event via Google Ads, Meta, or YouTube."
                    />
                ) : (
                    initialCampaigns.map((c) => (
                        <CampaignRow
                            key={c.uuid}
                            campaign={c}
                            teamSlug={teamSlug}
                            eventSlug={eventSlug}
                        />
                    ))
                )}

                <AdCampaignDialog
                    action={createAction}
                    defaultName={
                        eventName ? `${eventName} — promotion` : undefined
                    }
                    defaultDestinationUrl={eventUrl}
                >
                    <Button variant="outline" size="sm" className="w-full">
                        <PlusCircle className="size-4" />
                        New campaign
                    </Button>
                </AdCampaignDialog>
            </CardContent>
        </Card>
    );
}
