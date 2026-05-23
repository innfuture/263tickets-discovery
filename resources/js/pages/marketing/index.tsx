import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Cable,
    Mail,
    Megaphone,
    Share2,
} from 'lucide-react';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Permissions = {
    email: boolean;
    social: boolean;
    facebookEvent: boolean;
    paidAds: boolean;
};

export default function MarketingIndex({
    permissions,
}: {
    permissions: Permissions;
}) {
    const page = usePage<{
        currentOrganization?: { slug: string } | null;
    }>();
    const base = page.props.currentOrganization
        ? `/${page.props.currentOrganization.slug}/marketing`
        : '/marketing';

    const cards = [
        {
            key: 'email',
            visible: permissions.email,
            icon: Mail,
            title: 'Email campaigns',
            href: `${base}/email-campaigns`,
            blurb: 'Connect with your audience through custom newsletters and event announcements. Re-engage past attendees with targeted sends.',
        },
        {
            key: 'social',
            visible: permissions.social || permissions.facebookEvent,
            icon: Share2,
            title: 'Social media',
            href: `${base}/social`,
            blurb: 'Share to TikTok, LinkedIn, Instagram, and your Facebook page in a few clicks. Sell more tickets to a wider audience.',
        },
        {
            key: 'paid-ads',
            visible: permissions.paidAds,
            icon: Megaphone,
            title: 'Paid Social Ads',
            href: `${base}/paid-ads`,
            blurb: 'Spin up Facebook + Instagram ads that put your events in front of warm audiences. Pause, sync, and measure from here.',
        },
        {
            key: 'integrations',
            visible: true,
            icon: Cable,
            title: 'Integrations',
            href: `${base}/integrations`,
            blurb: 'Connect TikTok, Instagram, LinkedIn, Facebook, and Mailchimp. Marketing surfaces above only light up once their integration is connected.',
        },
    ].filter((c) => c.visible);

    return (
        <>
            <Head title="Marketing" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Marketing"
                    description="Tools for filling your events — email campaigns, organic social posts, paid social ads, and the integrations behind them."
                />

                <div className="grid gap-3 md:grid-cols-2">
                    {cards.map((c) => (
                        <Link
                            key={c.key}
                            href={c.href}
                            className="group block rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <Card className="h-full transition-[transform,box-shadow,border-color] duration-150 group-hover:-translate-y-0.5 group-hover:border-primary/40 group-hover:shadow-md">
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex size-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                                <c.icon className="size-4" />
                                            </div>
                                            <CardTitle className="text-base">
                                                {c.title}
                                            </CardTitle>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                    </div>
                                    <CardDescription className="mt-2">
                                        {c.blurb}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

MarketingIndex.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Marketing',
            href: props.currentOrganization
                ? `/${props.currentOrganization.slug}/marketing`
                : '/marketing',
        },
    ],
});
