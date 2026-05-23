import { Head } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';
import { BrandIcon } from '@/components/brand-icon';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Platform = { key: string; label: string; connected: boolean };
type Campaign = {
    id: number;
    name: string;
    platform: string;
    status: string;
    spendToDate: string;
    runsFrom: string | null;
    runsUntil: string | null;
};

export default function PaidAds({
    platforms,
    campaigns,
}: {
    platforms: Platform[];
    campaigns: Campaign[];
}) {
    const anyConnected = platforms.some((p) => p.connected);

    return (
        <>
            <Head title="Paid Social Ads" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Paid Social Ads"
                        description="Quickly create ads that get your event in front of more people on Facebook, Instagram, TikTok, and Google."
                    />
                    <Button size="sm" disabled={!anyConnected}>
                        <Plus className="size-4" />
                        New campaign
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Ad platforms
                        </CardTitle>
                        <CardDescription>
                            Connect at least one ad platform from
                            Integrations before launching a campaign.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-3">
                        {platforms.map((p) => (
                            <div
                                key={p.key}
                                className="flex items-center justify-between rounded-md border p-3 text-sm"
                            >
                                <span className="inline-flex items-center gap-2 font-medium">
                                    <BrandIcon
                                        provider={
                                            // Map our platform key to a
                                            // BrandIcon registry key.
                                            p.key === 'meta'
                                                ? 'meta'
                                                : p.key === 'google'
                                                  ? 'google_ads'
                                                  : p.key
                                        }
                                        size={18}
                                    />
                                    {p.label}
                                </span>
                                <span
                                    className={
                                        p.connected
                                            ? 'rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary'
                                            : 'rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground'
                                    }
                                >
                                    {p.connected
                                        ? 'Connected'
                                        : 'Not connected'}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Active &amp; recent campaigns
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {campaigns.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-10 text-center">
                                <Megaphone className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    No paid campaigns yet
                                </p>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Existing event-level ads (from the
                                    event editor's Ads tab) also surface
                                    here, so you can manage every paid
                                    campaign across every event from one
                                    place.
                                </p>
                            </div>
                        ) : (
                            <ul className="divide-y">
                                {campaigns.map((c) => (
                                    <li
                                        key={c.id}
                                        className="flex items-center justify-between py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {c.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {c.platform} ·{' '}
                                                {c.runsFrom ?? '—'}
                                                {c.runsUntil
                                                    ? ` → ${c.runsUntil}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="text-right text-xs">
                                            <p className="font-medium">
                                                {c.spendToDate}
                                            </p>
                                            <p className="text-muted-foreground">
                                                {c.status}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PaidAds.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => {
    const base = props.currentOrganization
        ? `/${props.currentOrganization.slug}/marketing`
        : '/marketing';

    return {
        breadcrumbs: [
            { title: 'Marketing', href: base },
            { title: 'Paid Social Ads', href: `${base}/paid-ads` },
        ],
    };
};
