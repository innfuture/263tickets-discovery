import { Form, Head } from '@inertiajs/react';
import { CheckCircle2, Send, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { BrandIcon } from '@/components/brand-icon';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Breadcrumb = { title: string; href: string };

type Integration = { key: string; label: string; connected: boolean };
type Post = {
    id: number;
    event_id: number | null;
    body: string;
    channels: string[];
    status: string;
    scheduled_for: string | null;
    published_at: string | null;
};
type EventOption = { value: number; label: string };

type Props = {
    integrations: Integration[];
    posts: Post[];
    event_options: EventOption[];
    permissions: { publish: boolean; facebookEvent: boolean };
    breadcrumbs: Breadcrumb[];
};

const STATUS_COLOR: Record<string, string> = {
    draft: 'bg-slate-100 text-slate-700',
    scheduled: 'bg-amber-100 text-amber-800',
    published: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
};

export default function MarketingSocial({ integrations, posts, event_options, permissions }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const connectedChannels = integrations.filter((i) => i.connected).map((i) => i.key);
    const [selected, setSelected] = useState<string[]>(connectedChannels);
    const [eventId, setEventId] = useState<string>('none');

    const toggle = (key: string) => {
        setSelected((prev) => (prev.includes(key) ? prev.filter((c) => c !== key) : [...prev, key]));
    };

    return (
        <>
            <Head title="Social media" />
            <div className="space-y-6 p-4">
                <Heading
                    variant="small"
                    title="Social media"
                    description="Compose, schedule, and track posts across TikTok, LinkedIn, Instagram, and Facebook."
                />

                {permissions.publish && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Sparkles className="size-4 text-primary" /> Compose a post
                            </CardTitle>
                            <CardDescription>
                                Pick the channels, attach an event, and queue the post. Live delivery requires the
                                provider connection on the Integrations page.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`${orgBase}/marketing/social`}
                                method="post"
                                onError={() => toast.error('Check the fields below.')}
                                onSuccess={() => {
                                    toast.success('Post queued.');
                                    setSelected(connectedChannels);
                                }}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <div className="space-y-3">
                                        {selected.map((c) => (
                                            <input key={c} type="hidden" name="channels[]" value={c} />
                                        ))}
                                        <input type="hidden" name="event_id" value={eventId === 'none' ? '' : eventId} />

                                        <div className="flex flex-wrap gap-2">
                                            {integrations.map((i) => (
                                                <button
                                                    type="button"
                                                    key={i.key}
                                                    onClick={() => toggle(i.key)}
                                                    disabled={!i.connected}
                                                    className={cn(
                                                        'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition',
                                                        selected.includes(i.key)
                                                            ? 'border-primary/40 bg-primary/5 text-foreground'
                                                            : 'border-dashed text-muted-foreground',
                                                        !i.connected && 'cursor-not-allowed opacity-50',
                                                    )}
                                                >
                                                    <BrandIcon provider={i.key} size={20} />
                                                    {i.label}
                                                    {selected.includes(i.key) && (
                                                        <CheckCircle2 className="size-3.5 text-primary" />
                                                    )}
                                                </button>
                                            ))}
                                        </div>
                                        {errors.channels && (
                                            <p className="text-xs text-rose-600">{errors.channels}</p>
                                        )}

                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div className="grid gap-1">
                                                <FieldLabel>Attach event (optional)</FieldLabel>
                                                <Select value={eventId} onValueChange={setEventId}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="No event" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">No event</SelectItem>
                                                        {event_options.map((e) => (
                                                            <SelectItem key={e.value} value={String(e.value)}>
                                                                {e.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grid gap-1">
                                                <FieldLabel htmlFor="sp-scheduled">Schedule (optional)</FieldLabel>
                                                <Input
                                                    id="sp-scheduled"
                                                    name="scheduled_for"
                                                    type="datetime-local"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="sp-body" error={errors.body}>
                                                Post body
                                            </FieldLabel>
                                            <Textarea id="sp-body" name="body" rows={4} placeholder="What's happening?" />
                                        </div>

                                        <Button type="submit" disabled={processing || selected.length === 0}>
                                            <Send className="mr-1 size-4" />
                                            {processing ? 'Queuing…' : 'Queue post'}
                                        </Button>
                                        {connectedChannels.length === 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                Connect at least one social channel from{' '}
                                                <a href={`${orgBase}/marketing/integrations`} className="underline">
                                                    Integrations
                                                </a>{' '}
                                                to publish.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent posts</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {posts.length === 0 ? (
                            <p className="px-6 pb-6 text-sm text-muted-foreground">
                                No posts queued yet. Compose your first above.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {posts.map((p) => (
                                    <li key={p.id} className="p-4">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex-1">
                                                <p className="text-sm">{p.body}</p>
                                                <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                                    {p.channels.map((c) => (
                                                        <BrandIcon key={c} provider={c} size={14} />
                                                    ))}
                                                    <span>
                                                        {p.scheduled_for
                                                            ? `Scheduled ${p.scheduled_for}`
                                                            : p.published_at
                                                              ? `Published ${p.published_at}`
                                                              : 'Draft'}
                                                    </span>
                                                </div>
                                            </div>
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs ${
                                                    STATUS_COLOR[p.status] ?? 'bg-slate-100 text-slate-700'
                                                }`}
                                            >
                                                {p.status}
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {permissions.facebookEvent && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-3 text-base">
                                <BrandIcon provider="facebook" size={30} />
                                Facebook Event
                            </CardTitle>
                            <CardDescription>
                                Add your event to Facebook (for free!) and sell more tickets to a wider audience.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button variant="outline" disabled>
                                Create Facebook Event
                            </Button>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Connect your Facebook page from Integrations to enable this.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

MarketingSocial.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
