import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };
type Invoice = { id: number; number: string; issued_on: string; due_on: string | null; amount_cents: number; amount_formatted: string; currency: string; status: string; paid_at: string | null; line_items: Array<{ label: string; amount_cents: number }> };

export default function InvoicesPage({ invoices }: { invoices: Invoice[]; breadcrumbs: Breadcrumb[] }) {
    const variant = (s: string): 'default' | 'destructive' | 'secondary' => s === 'paid' ? 'default' : s === 'overdue' ? 'destructive' : 'secondary';

    return (
        <>
            <Head title="Invoices — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Invoices" description="Historical platform invoices with downloadable PDFs." />

                <Card>
                    <CardHeader><CardTitle className="text-base">All invoices ({invoices.length})</CardTitle></CardHeader>
                    <CardContent>
                        {invoices.length === 0 ? <p className="text-sm text-muted-foreground">No invoices yet.</p> : (
                            <table className="w-full text-sm">
                                <thead><tr className="text-left text-xs text-muted-foreground"><th className="p-2">Number</th><th>Issued</th><th>Due</th><th className="text-right">Amount</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    {invoices.map((i) => (
                                        <tr key={i.id} className="border-t">
                                            <td className="p-2 font-mono text-xs">{i.number}</td>
                                            <td>{new Date(i.issued_on).toLocaleDateString()}</td>
                                            <td>{i.due_on ? new Date(i.due_on).toLocaleDateString() : '—'}</td>
                                            <td className="text-right font-medium">{i.currency} {i.amount_formatted}</td>
                                            <td><Badge variant={variant(i.status)}>{i.status}</Badge></td>
                                            <td className="text-right"><Button asChild size="sm" variant="ghost"><a href={`/settings/billing/invoices/${i.id}/download`}><Download className="size-3.5" /></a></Button></td>
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

InvoicesPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
