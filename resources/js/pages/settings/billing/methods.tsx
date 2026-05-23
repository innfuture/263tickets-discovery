import { Form, Head, router } from '@inertiajs/react';
import { CreditCard, Loader2, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type Method = { id: number; brand: string; last4: string; exp_month: number; exp_year: number; holder_name: string | null; is_default: boolean; created_at: string };

const BRANDS = ['visa', 'mastercard', 'amex', 'discover', 'jcb', 'unionpay'];

export default function MethodsPage({ methods, billing }: { methods: Method[]; billing: { email: string | null; address: string | null }; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const [brand, setBrand] = useState<string>(BRANDS[0]);
    const [isDefault, setIsDefault] = useState(methods.length === 0);

    return (
        <>
            <Head title="Payment methods — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Payment methods" description="Cards on file for your subscription + usage fees." />

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Plus className="size-4" /> Add card</CardTitle></CardHeader>
                    <CardContent>
                        <Form action="/settings/billing/methods" method="post" onError={() => toast.error('Check fields.')}>
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    <input type="hidden" name="brand" value={brand} />
                                    <input type="hidden" name="is_default" value={isDefault ? '1' : '0'} />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <FieldLabel error={errors.brand}>Brand</FieldLabel>
                                            <Select value={brand} onValueChange={setBrand}>
                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                <SelectContent>{BRANDS.map((b) => <SelectItem key={b} value={b}>{b}</SelectItem>)}</SelectContent>
                                            </Select>
                                        </div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="last4" error={errors.last4}>Last 4</FieldLabel><Input id="last4" name="last4" maxLength={4} inputMode="numeric" /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="exp_month" error={errors.exp_month}>Exp month</FieldLabel><Input id="exp_month" name="exp_month" type="number" min={1} max={12} /></div>
                                        <div className="grid gap-2"><FieldLabel htmlFor="exp_year" error={errors.exp_year}>Exp year</FieldLabel><Input id="exp_year" name="exp_year" type="number" min={new Date().getFullYear()} /></div>
                                        <div className="grid gap-2 sm:col-span-2"><FieldLabel htmlFor="holder_name" error={errors.holder_name}>Cardholder name</FieldLabel><Input id="holder_name" name="holder_name" /></div>
                                    </div>
                                    <label className="flex items-center gap-2"><Checkbox checked={isDefault} onCheckedChange={(v) => setIsDefault(Boolean(v))} /> <span className="text-sm">Make default</span></label>
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Add card'}</Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Cards on file</CardTitle></CardHeader>
                    <CardContent>
                        {methods.length === 0 ? <p className="text-sm text-muted-foreground">No cards yet.</p> : (
                            <ul className="space-y-2">
                                {methods.map((m) => (
                                    <li key={m.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div className="flex items-center gap-3">
                                            <CreditCard className="size-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">{m.brand} •••• {m.last4} {m.is_default && <span className="ml-2 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] text-primary">DEFAULT</span>}</p>
                                                <p className="text-xs text-muted-foreground">Exp {String(m.exp_month).padStart(2, '0')}/{m.exp_year} · {m.holder_name}</p>
                                            </div>
                                        </div>
                                        <div className="flex gap-1">
                                            {!m.is_default && <Button size="sm" variant="ghost" onClick={() => router.post(`/settings/billing/methods/${m.id}/default`)}>Make default</Button>}
                                            <Button size="sm" variant="ghost" onClick={async () => { if (await confirm({ title: 'Remove card?', tone: 'destructive' })) router.delete(`/settings/billing/methods/${m.id}`); }}><Trash2 className="size-3.5" /></Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Form action="/settings/billing/contact" method="post" onError={() => toast.error("Couldn't save.")}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">Billing contact</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2"><FieldLabel htmlFor="email" error={errors.email}>Billing email</FieldLabel><Input id="email" name="email" type="email" defaultValue={billing.email ?? ''} /></div>
                                <div className="grid gap-2"><FieldLabel htmlFor="address" error={errors.address}>Billing address</FieldLabel><Textarea id="address" name="address" defaultValue={billing.address ?? ''} rows={3} /></div>
                                <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save'}</Button>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

MethodsPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
