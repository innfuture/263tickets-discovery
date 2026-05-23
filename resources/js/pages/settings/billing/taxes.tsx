import { Form, Head } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type TaxId = { country_code: string; label: string; value: string };
type Rate = { region: string; rate_percent: number };
type Taxes = { tax_ids: TaxId[]; regional_rates: Rate[]; fees_paid_by: 'attendee' | 'organizer' | 'split'; tax_inclusive_pricing: boolean };

export default function TaxesPage({ taxes }: { taxes: Taxes; breadcrumbs: Breadcrumb[] }) {
    const [taxIds, setTaxIds] = useState<TaxId[]>(taxes.tax_ids);
    const [rates, setRates] = useState<Rate[]>(taxes.regional_rates);
    const [feesPaidBy, setFeesPaidBy] = useState(taxes.fees_paid_by);
    const [inclusive, setInclusive] = useState(taxes.tax_inclusive_pricing);

    return (
        <>
            <Head title="Taxes — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Taxes" description="Tax IDs, regional rates, and fee-passthrough defaults." />
                <Form action="/settings/billing/taxes" method="post" onError={() => toast.error('Check fields.')}>
                    {({ processing }) => (
                        <>
                            {taxIds.map((t, idx) => (
                                <span key={idx} className="hidden">
                                    <input type="hidden" name={`tax_ids[${idx}][country_code]`} value={t.country_code} />
                                    <input type="hidden" name={`tax_ids[${idx}][label]`} value={t.label} />
                                    <input type="hidden" name={`tax_ids[${idx}][value]`} value={t.value} />
                                </span>
                            ))}
                            {rates.map((r, idx) => (
                                <span key={idx} className="hidden">
                                    <input type="hidden" name={`regional_rates[${idx}][region]`} value={r.region} />
                                    <input type="hidden" name={`regional_rates[${idx}][rate_percent]`} value={r.rate_percent} />
                                </span>
                            ))}
                            <input type="hidden" name="fees_paid_by" value={feesPaidBy} />
                            <input type="hidden" name="tax_inclusive_pricing" value={inclusive ? '1' : '0'} />

                            <Card>
                                <CardHeader><CardTitle className="text-base">Tax IDs</CardTitle></CardHeader>
                                <CardContent className="space-y-2">
                                    {taxIds.map((t, idx) => (
                                        <div key={idx} className="grid grid-cols-12 gap-2">
                                            <Input className="col-span-2" value={t.country_code} maxLength={2} onChange={(e) => setTaxIds((p) => p.map((x, i) => i === idx ? { ...x, country_code: e.target.value.toUpperCase() } : x))} placeholder="US" />
                                            <Input className="col-span-3" value={t.label} onChange={(e) => setTaxIds((p) => p.map((x, i) => i === idx ? { ...x, label: e.target.value } : x))} placeholder="EIN" />
                                            <Input className="col-span-6" value={t.value} onChange={(e) => setTaxIds((p) => p.map((x, i) => i === idx ? { ...x, value: e.target.value } : x))} placeholder="12-3456789" />
                                            <Button type="button" variant="ghost" size="sm" className="col-span-1" onClick={() => setTaxIds((p) => p.filter((_, i) => i !== idx))}><Trash2 className="size-3.5" /></Button>
                                        </div>
                                    ))}
                                    <Button type="button" variant="outline" size="sm" onClick={() => setTaxIds((p) => [...p, { country_code: '', label: '', value: '' }])}><Plus className="size-3.5" /> Add tax ID</Button>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader><CardTitle className="text-base">Regional rates</CardTitle></CardHeader>
                                <CardContent className="space-y-2">
                                    {rates.map((r, idx) => (
                                        <div key={idx} className="grid grid-cols-12 gap-2">
                                            <Input className="col-span-7" value={r.region} onChange={(e) => setRates((p) => p.map((x, i) => i === idx ? { ...x, region: e.target.value } : x))} placeholder="CA-ON" />
                                            <Input className="col-span-4" type="number" step="0.01" value={r.rate_percent} onChange={(e) => setRates((p) => p.map((x, i) => i === idx ? { ...x, rate_percent: Number(e.target.value) } : x))} placeholder="13" />
                                            <Button type="button" variant="ghost" size="sm" className="col-span-1" onClick={() => setRates((p) => p.filter((_, i) => i !== idx))}><Trash2 className="size-3.5" /></Button>
                                        </div>
                                    ))}
                                    <Button type="button" variant="outline" size="sm" onClick={() => setRates((p) => [...p, { region: '', rate_percent: 0 }])}><Plus className="size-3.5" /> Add region</Button>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader><CardTitle className="text-base">Defaults</CardTitle></CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2"><FieldLabel>Who pays platform fees?</FieldLabel><Select value={feesPaidBy} onValueChange={(v) => setFeesPaidBy(v as Taxes['fees_paid_by'])}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="attendee">Attendee</SelectItem><SelectItem value="organizer">Organizer</SelectItem><SelectItem value="split">Split 50/50</SelectItem></SelectContent></Select></div>
                                    <label className="flex items-center gap-2"><Checkbox checked={inclusive} onCheckedChange={(v) => setInclusive(Boolean(v))} /><span className="text-sm">Display tax-inclusive prices</span></label>
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save tax settings'}</Button>
                                </CardContent>
                            </Card>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

TaxesPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
