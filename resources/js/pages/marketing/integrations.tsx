import { Head } from '@inertiajs/react';
import { CheckCircle2, ExternalLink } from 'lucide-react';
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
import { cn } from '@/lib/utils';

type Integration = {
    key: string;
    label: string;
    category: string;
    connected: boolean;
    description: string;
};

export default function MarketingIntegrations({
    integrations,
}: {
    integrations: Integration[];
}) {
    return (
        <>
            <Head title="Marketing integrations" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Marketing integrations"
                    description="Connect the channels that feed the rest of the marketing surface. Each integration unlocks specific features — social sharing, paid ads, or email."
                />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {integrations.map((i) => (
                        <Card
                            key={i.key}
                            className={cn(
                                'transition-colors',
                                i.connected
                                    ? 'border-primary/30'
                                    : 'border-dashed',
                            )}
                        >
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-12 items-center justify-center rounded-md border bg-background">
                                            <BrandIcon
                                                provider={i.key}
                                                size={30}
                                            />
                                        </div>
                                        <div>
                                            <CardTitle className="text-base">
                                                {i.label}
                                            </CardTitle>
                                            <p className="text-xs text-muted-foreground">
                                                {i.category}
                                            </p>
                                        </div>
                                    </div>
                                    {i.connected ? (
                                        <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                            <CheckCircle2 className="size-3" />
                                            Connected
                                        </span>
                                    ) : null}
                                </div>
                                <CardDescription className="mt-2">
                                    {i.description}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    type="button"
                                    variant={
                                        i.connected ? 'ghost' : 'outline'
                                    }
                                    size="sm"
                                    disabled
                                >
                                    {i.connected
                                        ? 'Disconnect'
                                        : 'Connect'}
                                    <ExternalLink className="size-3.5" />
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

MarketingIntegrations.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => {
    const base = props.currentOrganization
        ? `/${props.currentOrganization.slug}/marketing`
        : '/marketing';

    return {
        breadcrumbs: [
            { title: 'Marketing', href: base },
            { title: 'Integrations', href: `${base}/integrations` },
        ],
    };
};
