import { Form, Head, router } from '@inertiajs/react';
import { Banknote, Loader2, Plug, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type Connect = { status: 'unconfigured' | 'active'; destination_label: string | null; schedule: string; withholding_percent: number; connected_at: string | null };
type Payout = { id: number; amount_cents: number; amount_formatted: string; currency: string; status: string; destination_label: string | null; arrival_on: string | null; created_at: string };

export default function PayoutsPage({ connect, payouts, scheduleOptions }: { connect: Connect; payouts: Payout[]; scheduleOptions: string[]; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const [schedule, setSchedule] = useState(connect.schedule);

    return (
        <>
            <Head title="Payouts — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Payouts" description="Where ticket sales land and how often they pay out." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plug className="size-4" /> Destination</CardTitle></CardHeader>
                    <CardContent>
                        {connect.status === 'active' ? (
                            <div className="space-y-3">
                                <div className="flex items-center gap-3"><Badge>{connect.status}</Badge><span className="text-sm">{connect.destination_label}</span></div>
                                <p className="text-xs text-muted-foreground">Connected {connect.connected_at ? new Date(connect.connected_at).toLocaleString() : ''}</p>
                                <Form action="/settings/billing/payouts/schedule" method="post">
                                    {({ processing }) => (
                                        <div className="space-y-3">
                                            <input type="hidden" name="schedule" value={schedule} />
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2"><FieldLabel>Schedule</FieldLabel><Select value={schedule} onValueChange={setSchedule}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{scheduleOptions.map((s) => <SelectItem key={s} value={s}>{s}</SelectItem>)}</SelectContent></Select></div>
                                                <div className="grid gap-2"><FieldLabel htmlFor="withholding_percent">Withholding %</FieldLabel><Input id="withholding_percent" name="withholding_percent" type="number" defaultValue={connect.withholding_percent} min={0} max={100} /></div>
                                            </div>
                                            <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save schedule'}</Button>
                                        </div>
                                    )}
                                </Form>
                                <Button variant="ghost" size="sm" onClick={async () => { if (await confirm({ title: 'Disconnect payout destination?', tone: 'destructive' })) router.delete('/settings/billing/payouts/connect'); }}><Trash2 className="size-3.5" /> Disconnect</Button>
                            </div>
                        ) : (
                            <Form action="/settings/billing/payouts/connect" method="post" onError={() => toast.error('Check fields.')}>
                                {({ processing, errors }) => (
                                    <div className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2"><FieldLabel htmlFor="destination_label" error={errors.destination_label}>Destination label</FieldLabel><Input id="destination_label" name="destination_label" placeholder="Org main account" /></div>
                                            <div className="grid gap-2"><FieldLabel htmlFor="account_holder" error={errors.account_holder}>Account holder</FieldLabel><Input id="account_holder" name="account_holder" /></div>
                                            <div className="grid gap-2"><FieldLabel htmlFor="account_last4" error={errors.account_last4}>Account last 4</FieldLabel><Input id="account_last4" name="account_last4" maxLength={4} /></div>
                                            <div className="grid gap-2"><FieldLabel htmlFor="routing_last4" error={errors.routing_last4}>Routing last 4 (US)</FieldLabel><Input id="routing_last4" name="routing_last4" maxLength={4} /></div>
                                            <div className="grid gap-2"><FieldLabel htmlFor="country_code" error={errors.country_code}>Country (2 letters)</FieldLabel><Input id="country_code" name="country_code" maxLength={2} className="uppercase" /></div>
                                        </div>
                                        <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Connect destination'}</Button>
                                    </div>
                                )}
                            </Form>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Banknote className="size-4" /> History</CardTitle></CardHeader>
                    <CardContent>
                        {payouts.length === 0 ? <p className="text-sm text-muted-foreground">No payouts yet.</p> : (
                            <table className="w-full text-sm">
                                <thead><tr className="text-left text-xs text-muted-foreground"><th className="p-2">Created</th><th>Destination</th><th className="text-right">Amount</th><th>Status</th><th>Arrived</th></tr></thead>
                                <tbody>
                                    {payouts.map((p) => (
                                        <tr key={p.id} className="border-t">
                                            <td className="p-2">{new Date(p.created_at).toLocaleDateString()}</td>
                                            <td>{p.destination_label || '—'}</td>
                                            <td className="text-right font-medium">{p.currency} {p.amount_formatted}</td>
                                            <td><Badge variant={p.status === 'paid' ? 'default' : 'secondary'}>{p.status}</Badge></td>
                                            <td>{p.arrival_on ? new Date(p.arrival_on).toLocaleDateString() : '—'}</td>
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

PayoutsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
