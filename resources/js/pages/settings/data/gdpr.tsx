import { Form, Head, router } from '@inertiajs/react';
import { Loader2, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type Request = { id: number; requester_email: string; type: 'access' | 'erase'; status: string; notes: string | null; resolver: string | null; submitted_at: string; resolved_at: string | null };

export default function GdprPage({ requests, statuses }: { requests: Request[]; statuses: string[]; breadcrumbs: Breadcrumb[] }) {
    const [type, setType] = useState<'access' | 'erase'>('access');

    const updateStatus = (id: number, status: string, notes: string | null) => router.patch(`/settings/data/gdpr/${id}`, { status, notes });

    const variant = (s: string): 'default' | 'destructive' | 'secondary' => s === 'resolved' ? 'default' : s === 'rejected' ? 'destructive' : 'secondary';

    return (
        <>
            <Head title="GDPR — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="GDPR requests" description="Right-to-access and right-to-be-forgotten requests from attendees." />

                <Form action="/settings/data/gdpr" method="post" onError={() => toast.error('Check fields.')}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">Log new request</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <input type="hidden" name="type" value={type} />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2"><FieldLabel htmlFor="requester_email" error={errors.requester_email}>Requester email</FieldLabel><Input id="requester_email" name="requester_email" type="email" /></div>
                                    <div className="grid gap-2"><FieldLabel error={errors.type}>Type</FieldLabel><Select value={type} onValueChange={(v) => setType(v as 'access' | 'erase')}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="access">Right to access</SelectItem><SelectItem value="erase">Right to be forgotten</SelectItem></SelectContent></Select></div>
                                </div>
                                <div className="grid gap-2"><FieldLabel htmlFor="notes" error={errors.notes}>Notes</FieldLabel><Textarea id="notes" name="notes" rows={3} /></div>
                                <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Log request'}</Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><ShieldAlert className="size-4" /> Open + recent ({requests.length})</CardTitle></CardHeader>
                    <CardContent>
                        {requests.length === 0 ? <p className="text-sm text-muted-foreground">No GDPR requests.</p> : (
                            <ul className="space-y-2">
                                {requests.map((r) => (
                                    <li key={r.id} className="rounded-md border p-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-sm font-medium">{r.requester_email} <Badge variant="outline" className="ml-2 text-[10px]">{r.type}</Badge></p>
                                                <p className="text-xs text-muted-foreground">Submitted {new Date(r.submitted_at).toLocaleDateString()}{r.resolved_at && ` · Resolved ${new Date(r.resolved_at).toLocaleDateString()} by ${r.resolver}`}</p>
                                                {r.notes && <p className="mt-1 text-xs">{r.notes}</p>}
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge variant={variant(r.status)}>{r.status}</Badge>
                                                <Select value={r.status} onValueChange={(v) => updateStatus(r.id, v, r.notes)}>
                                                    <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                                                    <SelectContent>{statuses.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}</SelectContent>
                                                </Select>
                                            </div>
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

GdprPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
