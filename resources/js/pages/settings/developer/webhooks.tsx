import { Form, Head, router } from '@inertiajs/react';
import { Copy, Loader2, Plus, RotateCw, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
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
type Delivery = { id: number; webhook_id: number; event: string; status_code: number | null; duration_ms: number | null; attempt: number; error: string | null; response_body: string | null; payload: Record<string, unknown>; created_at: string };

export default function DeveloperWebhooksPage({ webhooks, deliveries, eventTypes }: { webhooks: Webhook[]; deliveries: Delivery[]; eventTypes: string[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const [events, setEvents] = useState<string[]>([eventTypes[0]]);
    const [expanded, setExpanded] = useState<number | null>(null);
    const [filter, setFilter] = useState<number | null>(null);
    const toggle = (e: string) => setEvents((p) => p.includes(e) ? p.filter((x) => x !== e) : [...p, e]);
    const filteredDeliveries = filter ? deliveries.filter((d) => d.webhook_id === filter) : deliveries;

    return (
        <>
            <Head title="Developer webhooks — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Developer webhooks" description="Same plumbing as operational webhooks but with full delivery log and replay." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" /> Create endpoint</CardTitle></CardHeader>
                    <CardContent>
                        <Form action="/settings/developer/webhooks" method="post" onError={() => toast.error('Check fields.')}>
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    {events.map((e) => <input key={e} type="hidden" name="events[]" value={e} />)}
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2"><FieldLabel htmlFor="label" error={errors.label}>Label</FieldLabel><Input id="label" name="label" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="url" error={errors.url}>URL</FieldLabel><Input id="url" name="url" type="url" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="retry_limit" error={errors.retry_limit}>Retry limit</FieldLabel><Input id="retry_limit" name="retry_limit" type="number" defaultValue={5} min={0} max={10} /></div>
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel error={errors.events}>Events</FieldLabel>
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
                            <ul className="space-y-2">
                                {webhooks.map((w) => (
                                    <li key={w.id} className="rounded-md border p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium">{w.label}</p>
                                                <p className="font-mono text-xs text-muted-foreground">{w.url}</p>
                                                <div className="mt-1 flex flex-wrap gap-1">{w.events.map((e) => <Badge key={e} variant="secondary" className="text-[10px]">{e}</Badge>)}</div>
                                                <p className="mt-1 text-xs text-muted-foreground">{w.deliveries_count} deliveries</p>
                                            </div>
                                            <div className="flex flex-col gap-1">
                                                <Button size="sm" variant="ghost" onClick={() => setFilter(filter === w.id ? null : w.id)}>{filter === w.id ? 'Show all' : 'View log'}</Button>
                                                <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: 'Delete webhook?', tone: 'destructive' })) router.delete(`/settings/developer/webhooks/${w.id}`); }}><Trash2 className="size-3.5" /></Button>
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Delivery log ({filteredDeliveries.length}{filter ? ' filtered' : ''})</CardTitle></CardHeader>
                    <CardContent>
                        <table className="w-full text-xs">
                            <thead><tr className="text-left text-muted-foreground"><th className="p-2">When</th><th>Event</th><th>Status</th><th>Lat.</th><th>#</th><th></th></tr></thead>
                            <tbody>
                                {filteredDeliveries.map((d) => (
                                    <Fragment key={d.id}>
                                        <tr className="border-t">
                                            <td className="p-2">{new Date(d.created_at).toLocaleString()}</td>
                                            <td className="font-mono">{d.event}</td>
                                            <td>{d.status_code ?? <span className="text-red-500">{d.error || 'err'}</span>}</td>
                                            <td>{d.duration_ms ? `${d.duration_ms}ms` : '—'}</td>
                                            <td>{d.attempt}</td>
                                            <td className="flex gap-1">
                                                <Button size="sm" variant="ghost" onClick={() => setExpanded(expanded === d.id ? null : d.id)}>{expanded === d.id ? 'Hide' : 'View'}</Button>
                                                <Button size="sm" variant="ghost" onClick={() => router.post(`/settings/developer/webhooks/deliveries/${d.id}/replay`)}><RotateCw className="size-3" /></Button>
                                            </td>
                                        </tr>
                                        {expanded === d.id && (
                                            <tr><td colSpan={6} className="bg-muted/30 p-3">
                                                <p className="mb-1 text-[10px] font-medium">Payload</p>
                                                <pre className="overflow-x-auto rounded bg-background p-2 text-[10px]">{JSON.stringify(d.payload, null, 2)}</pre>
                                                {d.response_body && (<><p className="mt-2 mb-1 text-[10px] font-medium">Response</p><pre className="overflow-x-auto rounded bg-background p-2 text-[10px]">{d.response_body}</pre></>)}
                                            </td></tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DeveloperWebhooksPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
