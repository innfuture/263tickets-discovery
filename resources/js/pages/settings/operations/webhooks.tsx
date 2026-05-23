import { Form, Head, router } from '@inertiajs/react';
import { Copy, Loader2, Plus, RotateCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };
type Webhook = { id: number; label: string; url: string; events: string[]; signing_secret: string; is_active: boolean; retry_limit: number; deliveries_count: number; last_delivered_at: string | null };
type Delivery = { id: number; webhook_id: number; event: string; status_code: number | null; duration_ms: number | null; error: string | null; attempt: number; created_at: string };

export default function WebhooksPage({ webhooks, recentDeliveries, eventTypes }: { webhooks: Webhook[]; recentDeliveries: Delivery[]; eventTypes: string[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const [events, setEvents] = useState<string[]>([eventTypes[0]]);
    const toggle = (e: string) => setEvents((p) => p.includes(e) ? p.filter((x) => x !== e) : [...p, e]);

    return (
        <>
            <Head title="Webhooks — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Operational webhooks" description="HTTP callbacks signed with HMAC-SHA256 on the request body." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" /> Create endpoint</CardTitle></CardHeader>
                    <CardContent>
                        <Form action="/settings/operations/webhooks" method="post" onError={() => toast.error('Check fields.')}>
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    {events.map((e) => <input key={e} type="hidden" name="events[]" value={e} />)}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2"><FieldLabel htmlFor="label" error={errors.label}>Label</FieldLabel><Input id="label" name="label" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="url" error={errors.url}>URL</FieldLabel><Input id="url" name="url" type="url" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="retry_limit" error={errors.retry_limit}>Retry limit</FieldLabel><Input id="retry_limit" name="retry_limit" type="number" defaultValue={5} min={0} max={10} /></div>
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel error={errors.events}>Subscribed events</FieldLabel>
                                        <div className="grid gap-1 sm:grid-cols-2">
                                            {eventTypes.map((e) => (
                                                <label key={e} className="flex items-center gap-2 rounded-md border p-2 text-sm">
                                                    <Checkbox checked={events.includes(e)} onCheckedChange={() => toggle(e)} />
                                                    <code className="font-mono text-xs">{e}</code>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Create'}</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Endpoints ({webhooks.length})</CardTitle></CardHeader>
                    <CardContent>
                        {webhooks.length === 0 ? <p className="text-sm text-muted-foreground">No endpoints yet.</p> : (
                            <ul className="space-y-3">
                                {webhooks.map((w) => (
                                    <li key={w.id} className="rounded-md border p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex-1">
                                                <p className="text-sm font-medium">{w.label} {!w.is_active && <Badge variant="destructive" className="ml-2">paused</Badge>}</p>
                                                <p className="font-mono text-xs text-muted-foreground">{w.url}</p>
                                                <div className="mt-1 flex flex-wrap gap-1">{w.events.map((e) => <Badge key={e} variant="secondary" className="text-[10px]">{e}</Badge>)}</div>
                                                <div className="mt-2 flex items-center gap-2 rounded-md bg-muted/30 p-2 text-xs">
                                                    <span className="text-muted-foreground">secret:</span>
                                                    <code className="flex-1 truncate font-mono">{w.signing_secret}</code>
                                                    <Button size="sm" variant="ghost" onClick={() => navigator.clipboard.writeText(w.signing_secret).then(() => toast.success('Copied.'))}><Copy className="size-3.5" /></Button>
                                                </div>
                                                <p className="mt-1 text-xs text-muted-foreground">{w.deliveries_count} deliveries · {w.last_delivered_at ? `last ${new Date(w.last_delivered_at).toLocaleString()}` : 'no deliveries yet'}</p>
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <Button size="sm" variant="ghost" onClick={() => router.post(`/settings/operations/webhooks/${w.id}/rotate`)}><RotateCw className="size-3.5" /> Rotate</Button>
                                                <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: 'Delete webhook?', tone: 'destructive' })) router.delete(`/settings/operations/webhooks/${w.id}`); }}><Trash2 className="size-3.5" /> Delete</Button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Recent deliveries</CardTitle></CardHeader>
                    <CardContent>
                        {recentDeliveries.length === 0 ? <p className="text-sm text-muted-foreground">No deliveries yet.</p> : (
                            <table className="w-full text-xs">
                                <thead><tr className="text-left text-muted-foreground"><th className="p-1.5">Event</th><th>Status</th><th>Latency</th><th>Attempt</th><th>When</th></tr></thead>
                                <tbody>
                                    {recentDeliveries.map((d) => (
                                        <tr key={d.id} className="border-t">
                                            <td className="p-1.5 font-mono">{d.event}</td>
                                            <td>{d.status_code ?? d.error ?? '—'}</td>
                                            <td>{d.duration_ms ? `${d.duration_ms}ms` : '—'}</td>
                                            <td>{d.attempt}</td>
                                            <td className="text-muted-foreground">{new Date(d.created_at).toLocaleString()}</td>
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

WebhooksPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
