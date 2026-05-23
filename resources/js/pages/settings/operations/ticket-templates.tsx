import { Form, Head } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };
type Template = { pdf_layout: string; wallet_template: string; logo_placement: string; language_fallback: string; terms_footer: string | null; show_seat_info: boolean; show_qr_label: boolean };

export default function TicketTemplatesPage({ template, options }: { template: Template; options: { pdf_layouts: string[]; wallet_templates: string[]; logo_placements: string[]; languages: string[] }; breadcrumbs: Breadcrumb[] }) {
    const [pdfLayout, setPdfLayout] = useState(template.pdf_layout);
    const [walletTemplate, setWalletTemplate] = useState(template.wallet_template);
    const [logoPlacement, setLogoPlacement] = useState(template.logo_placement);
    const [language, setLanguage] = useState(template.language_fallback);
    const [showSeat, setShowSeat] = useState(template.show_seat_info);
    const [showQrLabel, setShowQrLabel] = useState(template.show_qr_label);

    return (
        <>
            <Head title="Ticket templates — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Ticket templates" description="PDF + Apple Wallet artwork inheriting your brand kit." />
                <Form action="/settings/operations/ticket-templates" method="post" onError={() => toast.error("Couldn't save.")}>
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader><CardTitle className="text-base">Template</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <input type="hidden" name="pdf_layout" value={pdfLayout} />
                                <input type="hidden" name="wallet_template" value={walletTemplate} />
                                <input type="hidden" name="logo_placement" value={logoPlacement} />
                                <input type="hidden" name="language_fallback" value={language} />
                                <input type="hidden" name="show_seat_info" value={showSeat ? '1' : '0'} />
                                <input type="hidden" name="show_qr_label" value={showQrLabel ? '1' : '0'} />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Choice label="PDF layout" value={pdfLayout} onChange={setPdfLayout} options={options.pdf_layouts} error={errors.pdf_layout} />
                                    <Choice label="Wallet template" value={walletTemplate} onChange={setWalletTemplate} options={options.wallet_templates} error={errors.wallet_template} />
                                    <Choice label="Logo placement" value={logoPlacement} onChange={setLogoPlacement} options={options.logo_placements} error={errors.logo_placement} />
                                    <Choice label="Language fallback" value={language} onChange={setLanguage} options={options.languages} error={errors.language_fallback} />
                                </div>

                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="terms_footer" error={errors.terms_footer}>Terms footer</FieldLabel>
                                    <Textarea id="terms_footer" name="terms_footer" defaultValue={template.terms_footer ?? ''} rows={3} maxLength={500} />
                                </div>

                                <label className="flex items-start gap-3 rounded-md border p-3">
                                    <Checkbox checked={showSeat} onCheckedChange={(v) => setShowSeat(Boolean(v))} />
                                    <span className="text-sm">Show seat info on tickets</span>
                                </label>
                                <label className="flex items-start gap-3 rounded-md border p-3">
                                    <Checkbox checked={showQrLabel} onCheckedChange={(v) => setShowQrLabel(Boolean(v))} />
                                    <span className="text-sm">Show "Scan at door" label under the QR</span>
                                </label>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>{processing ? <Loader2 className="size-4 animate-spin" /> : 'Save'}</Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

function Choice({ label, value, onChange, options, error }: { label: string; value: string; onChange: (v: string) => void; options: string[]; error?: string }) {
    return (
        <div className="grid gap-2">
            <FieldLabel error={error}>{label}</FieldLabel>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{options.map((o) => <SelectItem key={o} value={o}>{o}</SelectItem>)}</SelectContent>
            </Select>
        </div>
    );
}

TicketTemplatesPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
