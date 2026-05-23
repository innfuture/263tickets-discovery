import { Head } from '@inertiajs/react';
import { CheckCircle2, Sparkles } from 'lucide-react';
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
    connected: boolean;
};

export default function MarketingSocial({
    integrations,
    permissions,
}: {
    integrations: Integration[];
    permissions: { publish: boolean; facebookEvent: boolean };
}) {
    return (
        <>
            <Head title="Social media" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Social media"
                    description="Get your events in front of your existing followers on TikTok, LinkedIn, Instagram, and Facebook. Use AI to draft compelling posts and share across multiple platforms at once."
                />

                {permissions.publish ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Sparkles className="size-4 text-primary" />
                                Compose a post
                            </CardTitle>
                            <CardDescription>
                                Use AI to create compelling content that
                                speaks to your audience. Pick the channels,
                                edit the suggested copy, and share in a few
                                clicks.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex flex-wrap gap-2">
                                {integrations.map((i) => (
                                    <span
                                        key={i.key}
                                        className={cn(
                                            'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                                            i.connected
                                                ? 'border-primary/40 bg-primary/5 text-foreground'
                                                : 'border-dashed text-muted-foreground',
                                        )}
                                    >
                                        <BrandIcon
                                            provider={i.key}
                                            size={30}
                                        />
                                        {i.label}
                                        {i.connected ? (
                                            <CheckCircle2 className="size-3.5 text-primary" />
                                        ) : null}
                                    </span>
                                ))}
                            </div>
                            <Button disabled className="w-full sm:w-auto">
                                <Sparkles className="size-4" />
                                Draft with AI
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                Connect at least one social channel from
                                the Integrations page to start composing.
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                {permissions.facebookEvent ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-3 text-base">
                                <BrandIcon provider="facebook" size={30} />
                                Facebook Event
                            </CardTitle>
                            <CardDescription>
                                Add your event to Facebook (for free!) and
                                sell more tickets to a wider audience.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" disabled>
                                Create Facebook Event
                            </Button>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Connect your Facebook page from
                                Integrations to enable this.
                            </p>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

MarketingSocial.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => {
    const base = props.currentOrganization
        ? `/${props.currentOrganization.slug}/marketing`
        : '/marketing';

    return {
        breadcrumbs: [
            { title: 'Marketing', href: base },
            { title: 'Social media', href: `${base}/social` },
        ],
    };
};
