import { Form, Head, router } from '@inertiajs/react';
import { Mail, Plus, Send, Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };

type Campaign = {
    id: number;
    name: string;
    subject: string;
    audience: string;
    status: string;
    recipient_count: number;
    opened_count: number;
    clicked_count: number;
    scheduled_for: string | null;
    sent_at: string | null;
};

type Audience = { key: string; label: string; count: number };

type Props = {
    campaigns: Campaign[];
    audiences: Audience[];
    mailchimpConnected: boolean;
    permissions: { can_send: boolean };
    breadcrumbs: Breadcrumb[];
};

const STATUS_COLOR: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    scheduled: 'bg-amber-100 text-amber-800',
    sent: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
};

export default function EmailCampaigns({ campaigns, audiences, mailchimpConnected, permissions }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [audience, setAudience] = useState('all');
    const [composerOpen, setComposerOpen] = useState(false);

    const send = (id: number) => {
        if (!confirm('Mark this campaign as sent? Recipients won\'t be re-mailed if it\'s already been delivered upstream.')) return;
        router.post(`${orgBase}/marketing/email-campaigns/${id}/send`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Campaign marked sent.'),
        });
    };

    return (
        <>
            <Head title="Email campaigns" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Email campaigns"
                        description="Send newsletters and event announcements to your audience. Re-engage past attendees with targeted, customisable campaigns."
                    />
                    <Button size="sm" onClick={() => setComposerOpen((v) => !v)}>
                        <Plus className="mr-1 size-4" /> {composerOpen ? 'Close composer' : 'New campaign'}
                    </Button>
                </div>

                {!mailchimpConnected && (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-start gap-3 py-6">
                            <div className="flex items-center gap-2">
                                <Mail className="size-4 text-muted-foreground" />
                                <p className="text-sm font-medium">Connect Mailchimp to send beyond drafts</p>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Drafts are stored locally. Hooking up Mailchimp lets the platform handle deliverability,
                                unsubscribe management, and bounce processing for real sends.
                            </p>
                            <Button asChild variant="outline" size="sm">
                                <a href={`${orgBase}/marketing/integrations`}>Go to integrations</a>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {composerOpen && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Compose campaign</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`${orgBase}/marketing/email-campaigns`}
                                method="post"
                                onError={() => toast.error('Check the fields below.')}
                                onSuccess={() => {
                                    toast.success('Draft saved.');
                                    setComposerOpen(false);
                                }}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <input type="hidden" name="audience_key" value={audience} />
                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="ec-name" error={errors.name}>
                                                Campaign name
                                            </FieldLabel>
                                            <Input id="ec-name" name="name" placeholder="June announcements" />
                                        </div>
                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="ec-subject" error={errors.subject}>
                                                Subject line
                                            </FieldLabel>
                                            <Input id="ec-subject" name="subject" placeholder="See what's new" />
                                        </div>
                                        <div className="grid gap-1">
                                            <FieldLabel>Audience</FieldLabel>
                                            <Select value={audience} onValueChange={setAudience}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {audiences.map((a) => (
                                                        <SelectItem key={a.key} value={a.key}>
                                                            {a.label} ({a.count.toLocaleString()})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-1 sm:col-span-2">
                                            <FieldLabel htmlFor="ec-body">Body (HTML)</FieldLabel>
                                            <Textarea id="ec-body" name="body_html" rows={6} placeholder="<p>Hello…</p>" />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <Button type="submit" disabled={processing}>
                                                {processing ? 'Saving…' : 'Save draft'}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-3 sm:grid-cols-3">
                    {audiences.map((a) => (
                        <Card key={a.key}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Users className="size-3.5 text-muted-foreground" />
                                    {a.label}
                                </CardTitle>
                                <CardDescription>{a.count.toLocaleString()} addressable</CardDescription>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent campaigns</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {campaigns.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 py-10 text-center">
                                <Send className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">No campaigns yet</p>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Your sent and scheduled campaigns will appear here with open rate, click-through,
                                    and ticket-sale attribution.
                                </p>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Name</th>
                                        <th className="p-3">Audience</th>
                                        <th className="p-3 text-right">Recipients</th>
                                        <th className="p-3 text-right">Open / Click</th>
                                        <th className="p-3">Status</th>
                                        <th className="p-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {campaigns.map((c) => (
                                        <tr key={c.id} className="border-t hover:bg-muted/40">
                                            <td className="p-3">
                                                <p className="font-medium">{c.name}</p>
                                                <p className="text-xs text-muted-foreground">{c.subject}</p>
                                            </td>
                                            <td className="p-3 text-xs">{c.audience}</td>
                                            <td className="p-3 text-right font-mono text-xs">
                                                {c.recipient_count.toLocaleString()}
                                            </td>
                                            <td className="p-3 text-right font-mono text-xs text-muted-foreground">
                                                {c.opened_count}/{c.clicked_count}
                                            </td>
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        STATUS_COLOR[c.status] ?? 'bg-slate-100 text-slate-700'
                                                    }`}
                                                >
                                                    {c.status}
                                                </span>
                                            </td>
                                            <td className="p-3 text-right">
                                                {permissions.can_send && c.status === 'draft' && (
                                                    <Button size="sm" variant="ghost" onClick={() => send(c.id)}>
                                                        <Send className="size-3.5" />
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
            </div>
        </>
    );
}

EmailCampaigns.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
