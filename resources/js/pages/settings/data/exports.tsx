import { Form, Head } from '@inertiajs/react';
import { Download, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type Export = { id: number; type: string; format: string; delivery: string; date_from: string | null; date_to: string | null; status: string; error: string | null; requested_by: string | null; created_at: string; completed_at: string | null; has_download: boolean };

export default function ExportsPage({ exports, types }: { exports: Export[]; types: string[]; breadcrumbs: Breadcrumb[] }) {
    const [type, setType] = useState(types[0]);
    const [format, setFormat] = useState('csv');
    const [delivery, setDelivery] = useState('email');

    const variant = (s: string): 'default' | 'destructive' | 'secondary' => s === 'ready' ? 'default' : s === 'failed' ? 'destructive' : 'secondary';

    return (
        <>
            <Head title="Data exports — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Data exports" description="Schedule async dumps of events, attendees, tickets, or financials." />

                <Form action="/settings/data/exports" method="post" onError={() => toast.error('Check fields.')}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">New export</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <input type="hidden" name="type" value={type} />
                                <input type="hidden" name="format" value={format} />
                                <input type="hidden" name="delivery" value={delivery} />
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <div className="grid gap-2"><FieldLabel error={errors.type}>Type</FieldLabel><Select value={type} onValueChange={setType}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{types.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}</SelectContent></Select></div>
                                    <div className="grid gap-2"><FieldLabel error={errors.format}>Format</FieldLabel><Select value={format} onValueChange={setFormat}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="csv">CSV</SelectItem><SelectItem value="json">JSON</SelectItem></SelectContent></Select></div>
                                    <div className="grid gap-2"><FieldLabel error={errors.delivery}>Delivery</FieldLabel><Select value={delivery} onValueChange={setDelivery}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="email">Email link</SelectItem><SelectItem value="s3">S3 upload</SelectItem></SelectContent></Select></div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2"><FieldLabel htmlFor="date_from">From</FieldLabel><Input id="date_from" name="date_from" type="date" /></div>
                                    <div className="grid gap-2"><FieldLabel htmlFor="date_to">To</FieldLabel><Input id="date_to" name="date_to" type="date" /></div>
                                </div>
                                <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Queue export'}</Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                <Card>
                    <CardHeader><CardTitle className="text-base">History ({exports.length})</CardTitle></CardHeader>
                    <CardContent>
                        {exports.length === 0 ? <p className="text-sm text-muted-foreground">No exports yet.</p> : (
                            <table className="w-full text-sm">
                                <thead><tr className="text-left text-xs text-muted-foreground"><th className="p-2">Type</th><th>Format</th><th>Status</th><th>Requested</th><th>By</th><th></th></tr></thead>
                                <tbody>
                                    {exports.map((e) => (
                                        <tr key={e.id} className="border-t">
                                            <td className="p-2">{e.type}</td>
                                            <td className="text-xs">{e.format}</td>
                                            <td><Badge variant={variant(e.status)}>{e.status}</Badge>{e.error && <p className="text-xs text-red-500">{e.error}</p>}</td>
                                            <td className="text-xs">{new Date(e.created_at).toLocaleString()}</td>
                                            <td className="text-xs">{e.requested_by ?? '—'}</td>
                                            <td className="text-right">{e.has_download && <Button asChild size="sm" variant="ghost"><a href={`/settings/data/exports/${e.id}/download`}><Download className="size-3.5" /></a></Button>}</td>
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

ExportsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
