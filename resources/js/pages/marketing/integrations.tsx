import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Plug } from 'lucide-react';
import { toast } from 'sonner';
import { BrandIcon } from '@/components/brand-icon';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Breadcrumb = { title: string; href: string };

type Integration = {
    key: string;
    label: string;
    category: string;
    description: string;
    connected: boolean;
    account_label: string | null;
    connected_at: string | null;
};

type Props = { integrations: Integration[]; breadcrumbs: Breadcrumb[] };

export default function MarketingIntegrations({ integrations }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');

    const connect = (i: Integration) => {
        const label = window.prompt(
            `Account label for ${i.label} (e.g. "@yourbrand" or admin email):`,
            i.account_label ?? '',
        );
        if (label === null) return;
        router.post(`${orgBase}/marketing/integrations/${i.key}/connect`, { account_label: label }, {
            preserveScroll: true,
            onSuccess: () => toast.success(`${i.label} connected.`),
        });
    };

    const disconnect = (i: Integration) => {
        if (!confirm(`Disconnect ${i.label}? Live posts queued under this account will fail.`)) return;
        router.post(`${orgBase}/marketing/integrations/${i.key}/disconnect`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success(`${i.label} disconnected.`),
        });
    };

    return (
        <>
            <Head title="Marketing integrations" />
            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Marketing integrations"
                    description="Connect the channels that feed the rest of the marketing surface. Each integration unlocks specific features — social sharing, paid ads, or email."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {integrations.map((i) => (
                        <Card key={i.key} className={cn('transition-colors', i.connected ? 'border-primary/30' : 'border-dashed')}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-12 items-center justify-center rounded-md border bg-background">
                                            <BrandIcon provider={i.key} size={30} />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base">{i.label}</CardTitle>
                                            <p className="text-xs text-muted-foreground">{i.category}</p>
                                        </div>
                                    </div>
                                    {i.connected && (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                            <CheckCircle2 className="size-3" />
                                            Connected
                                        </span>
                                    )}
                                </div>
                                <CardDescription className="mt-2">{i.description}</CardDescription>
                                {i.connected && i.account_label && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Account: <code className="font-mono">{i.account_label}</code>
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent>
                                {i.connected ? (
                                    <Button variant="ghost" size="sm" onClick={() => disconnect(i)}>
                                        Disconnect
                                    </Button>
                                ) : (
                                    <Button variant="outline" size="sm" onClick={() => connect(i)}>
                                        <Plug className="mr-1 size-3.5" />
                                        Connect
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <p className="text-xs text-muted-foreground">
                    Sandbox connection: connecting here records the account in the integrations table without launching a
                    real OAuth flow. Per-vendor OAuth handshakes wire in once each provider's app credentials are set in
                    the platform's services config.
                </p>
            </div>
        </>
    );
}

MarketingIntegrations.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
