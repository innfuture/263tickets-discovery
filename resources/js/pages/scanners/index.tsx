import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowRight, KeyRound, Loader2, Plus, Smartphone, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type EventOption = { value: number; label: string };

type Profile = {
    uuid: string;
    name: string;
    description: string | null;
    status: string;
    capabilities: string[];
    allowed_event_ids: number[];
    max_scans_per_minute: number;
    duplicate_window_seconds: number;
    webhook_url: string | null;
    has_webhook_secret: boolean;
    devices_count: number;
    scans_count: number;
    created_at: string | null;
};

type Props = {
    profiles: Profile[];
    event_options: EventOption[];
    capability_options: string[];
    default_capabilities: string[];
    breadcrumbs: Breadcrumb[];
};

export default function ScannersIndex({ profiles, event_options, capability_options, default_capabilities }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [composerOpen, setComposerOpen] = useState(false);
    const [selectedCaps, setSelectedCaps] = useState<string[]>(default_capabilities);
    const [selectedEvents, setSelectedEvents] = useState<number[]>([]);

    const destroy = (uuid: string, name: string) => {
        if (!confirm(`Delete scanner profile "${name}"? Paired devices will lose access immediately.`)) return;
        router.delete(`${orgBase}/scanners/${uuid}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Profile deleted.'),
        });
    };

    return (
        <>
            <Head title="Scanners" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Scanners"
                        description="Pair mobile / kiosk ticket scanners. Each profile is a policy bundle — capabilities, event allowlist, fraud thresholds — that you generate pairing codes for."
                    />
                    <Button size="sm" onClick={() => setComposerOpen((v) => !v)}>
                        <Plus className="mr-1 size-4" /> {composerOpen ? 'Close' : 'New profile'}
                    </Button>
                </div>

                {composerOpen && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">New scanner profile</CardTitle>
                            <CardDescription>
                                Create one profile per logical scanning role (e.g. "Main entrance — Saturday"). Pair as
                                many physical devices to it as you need.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`${orgBase}/scanners`}
                                method="post"
                                onError={() => toast.error('Check the fields below.')}
                                onSuccess={() => {
                                    toast.success('Profile created.');
                                    setComposerOpen(false);
                                }}
                                resetOnSuccess
                            >
                                {({ processing, errors }) => (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {selectedCaps.map((c) => (
                                            <input key={c} type="hidden" name="capabilities[]" value={c} />
                                        ))}
                                        {selectedEvents.map((e) => (
                                            <input key={e} type="hidden" name="allowed_event_ids[]" value={e} />
                                        ))}

                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="sp-name" error={errors.name}>Name</FieldLabel>
                                            <Input id="sp-name" name="name" placeholder="Main entrance — Saturday" />
                                        </div>
                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="sp-webhook">Webhook URL (optional)</FieldLabel>
                                            <Input id="sp-webhook" name="webhook_url" type="url" placeholder="https://your.app/hooks/scans" />
                                        </div>
                                        <div className="grid gap-1 sm:col-span-2">
                                            <FieldLabel htmlFor="sp-desc">Description</FieldLabel>
                                            <Textarea id="sp-desc" name="description" rows={2} placeholder="What this scanner is used for." />
                                        </div>

                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="sp-max">Max scans / minute</FieldLabel>
                                            <Input id="sp-max" name="max_scans_per_minute" type="number" defaultValue={60} />
                                        </div>
                                        <div className="grid gap-1">
                                            <FieldLabel htmlFor="sp-dup">Duplicate window (s)</FieldLabel>
                                            <Input id="sp-dup" name="duplicate_window_seconds" type="number" defaultValue={10} />
                                        </div>

                                        <div className="sm:col-span-2">
                                            <FieldLabel>Capabilities</FieldLabel>
                                            <div className="mt-1 flex flex-wrap gap-2">
                                                {capability_options.map((c) => {
                                                    const on = selectedCaps.includes(c);
                                                    return (
                                                        <label
                                                            key={c}
                                                            className={`flex items-center gap-2 rounded border px-2 py-1 text-xs ${
                                                                on ? 'border-primary/40 bg-primary/5' : 'border-dashed'
                                                            }`}
                                                        >
                                                            <Checkbox
                                                                checked={on}
                                                                onCheckedChange={(v) => {
                                                                    setSelectedCaps((prev) =>
                                                                        v ? [...prev, c] : prev.filter((x) => x !== c),
                                                                    );
                                                                }}
                                                            />
                                                            {c}
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </div>

                                        {event_options.length > 0 && (
                                            <div className="sm:col-span-2">
                                                <FieldLabel>Event allowlist (empty = all events)</FieldLabel>
                                                <div className="mt-1 flex max-h-40 flex-wrap gap-2 overflow-y-auto">
                                                    {event_options.map((e) => {
                                                        const on = selectedEvents.includes(e.value);
                                                        return (
                                                            <label
                                                                key={e.value}
                                                                className={`flex items-center gap-2 rounded border px-2 py-1 text-xs ${
                                                                    on ? 'border-primary/40 bg-primary/5' : 'border-dashed'
                                                                }`}
                                                            >
                                                                <Checkbox
                                                                    checked={on}
                                                                    onCheckedChange={(v) => {
                                                                        setSelectedEvents((prev) =>
                                                                            v
                                                                                ? [...prev, e.value]
                                                                                : prev.filter((x) => x !== e.value),
                                                                        );
                                                                    }}
                                                                />
                                                                {e.label}
                                                            </label>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}

                                        <div className="sm:col-span-2">
                                            <Button type="submit" disabled={processing || selectedCaps.length === 0}>
                                                {processing ? <Loader2 className="mr-1 size-4 animate-spin" /> : null}
                                                Create profile
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {profiles.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <Smartphone className="size-8 text-muted-foreground" />
                            <p className="text-sm font-medium">No scanner profiles yet</p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                Create a profile to bundle capabilities + event scope + fraud thresholds, then pair as
                                many mobile / kiosk devices to it as you need.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {profiles.map((p) => (
                            <Card key={p.uuid} className="transition hover:border-primary/40">
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Smartphone className="size-4" />
                                                <Link
                                                    href={`${orgBase}/scanners/${p.uuid}`}
                                                    className="hover:underline"
                                                >
                                                    {p.name}
                                                </Link>
                                            </CardTitle>
                                            {p.description && (
                                                <CardDescription>{p.description}</CardDescription>
                                            )}
                                        </div>
                                        <span
                                            className={`rounded px-2 py-0.5 text-xs ${
                                                p.status === 'active'
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-slate-100 text-slate-700'
                                            }`}
                                        >
                                            {p.status}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Devices</p>
                                        <p className="text-lg font-semibold">{p.devices_count}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Scans logged</p>
                                        <p className="text-lg font-semibold">{p.scans_count.toLocaleString()}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Rate cap</p>
                                        <p className="text-lg font-semibold">{p.max_scans_per_minute}/min</p>
                                    </div>
                                    <div className="col-span-3 flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                                        <div className="flex flex-wrap gap-1">
                                            {p.capabilities.map((c) => (
                                                <span
                                                    key={c}
                                                    className="rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase text-muted-foreground"
                                                >
                                                    {c}
                                                </span>
                                            ))}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            {p.webhook_url && (
                                                <span
                                                    className="inline-flex items-center gap-1 text-xs text-muted-foreground"
                                                    title="Webhook delivery enabled"
                                                >
                                                    <KeyRound className="size-3" /> webhook
                                                </span>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => destroy(p.uuid, p.name)}
                                                aria-label="Delete"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                            <Button asChild size="sm" variant="ghost">
                                                <Link href={`${orgBase}/scanners/${p.uuid}`}>
                                                    Open <ArrowRight className="ml-1 size-3.5" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

ScannersIndex.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
