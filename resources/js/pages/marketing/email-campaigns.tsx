import { Head } from '@inertiajs/react';
import { Mail, Plus, Send, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Campaign = {
    id: number;
    name: string;
    audience: string;
    status: string;
    sentAt: string | null;
    recipientCount: number;
};

type Audience = { key: string; label: string; count: number };

export default function EmailCampaigns({
    campaigns,
    audiences,
    mailchimpConnected,
}: {
    campaigns: Campaign[];
    audiences: Audience[];
    mailchimpConnected: boolean;
}) {
    return (
        <>
            <Head title="Email campaigns" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Email campaigns"
                        description="Send newsletters and event announcements to your audience. Re-engage past attendees with targeted, customisable campaigns."
                    />
                    <Button size="sm" disabled={!mailchimpConnected}>
                        <Plus className="size-4" />
                        New campaign
                    </Button>
                </div>

                {!mailchimpConnected ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-start gap-3 py-6">
                            <div className="flex items-center gap-2">
                                <Mail className="size-4 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    Connect Mailchimp to start sending
                                </p>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Email campaigns ride on top of your
                                Mailchimp account so deliverability,
                                unsubscribe handling, and audience
                                management stay in one place.
                            </p>
                            <Button asChild variant="outline" size="sm">
                                <a href="../marketing/integrations">
                                    Go to integrations
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-3 sm:grid-cols-3">
                    {audiences.map((a) => (
                        <Card key={a.key}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Users className="size-3.5 text-muted-foreground" />
                                    {a.label}
                                </CardTitle>
                                <CardDescription>
                                    {a.count.toLocaleString()} subscribers
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent campaigns
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {campaigns.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-10 text-center">
                                <Send className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    No campaigns yet
                                </p>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Your sent and scheduled email
                                    campaigns will appear here with open
                                    rate, click-through, and ticket-sale
                                    attribution.
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
                                                {c.audience} ·{' '}
                                                {c.recipientCount.toLocaleString()}{' '}
                                                recipients
                                            </p>
                                        </div>
                                        <div className="text-right text-xs text-muted-foreground">
                                            <p>{c.status}</p>
                                            {c.sentAt ? (
                                                <p>{c.sentAt}</p>
                                            ) : null}
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

EmailCampaigns.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => {
    const base = props.currentOrganization
        ? `/${props.currentOrganization.slug}/marketing`
        : '/marketing';

    return {
        breadcrumbs: [
            { title: 'Marketing', href: base },
            { title: 'Email campaigns', href: `${base}/email-campaigns` },
        ],
    };
};
