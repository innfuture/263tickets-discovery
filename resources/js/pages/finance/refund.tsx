import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, RotateCcw, ShieldOff, XCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };

type Refund = {
    uuid: string;
    reference: string;
    gateway_reference: string | null;
    amount_minor: number;
    currency: string;
    status: string;
    reason: string | null;
    response_body: Record<string, unknown> | null;
    created_at: string | null;
    updated_at: string | null;
    initiator: { name: string; email: string } | null;
};

type Transaction = {
    uuid: string;
    reference: string;
    gateway: string;
    gateway_reference: string | null;
    status: string;
    amount_minor: number;
    currency: string;
    customer_email: string | null;
    customer_msisdn: string | null;
    customer_name: string | null;
    created_at: string | null;
    settled_at: string | null;
};

type Props = {
    refund: Refund;
    transaction: Transaction;
    permissions: { can_process: boolean };
    breadcrumbs: Breadcrumb[];
};

const STATUS_COLOR: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    succeeded: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-rose-100 text-rose-800',
};

const fmtMinor = (n: number, c: string) =>
    `${(n / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${c}`;

export default function FinanceRefund({ refund, transaction, permissions }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [busy, setBusy] = useState<'process' | 'deny' | null>(null);

    const process = () => {
        if (!confirm('Mark this refund as succeeded? This is irreversible.')) return;
        setBusy('process');
        router.post(`${orgBase}/finance/refunds/${refund.uuid}/process`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Refund processed.'),
            onError: () => toast.error('Could not process refund.'),
            onFinish: () => setBusy(null),
        });
    };

    const deny = () => {
        if (!confirm('Deny this refund? The funds will stay with the merchant.')) return;
        setBusy('deny');
        router.post(`${orgBase}/finance/refunds/${refund.uuid}/deny`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Refund denied.'),
            onError: () => toast.error('Could not deny refund.'),
            onFinish: () => setBusy(null),
        });
    };

    return (
        <>
            <Head title={`Refund ${refund.reference}`} />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`${orgBase}/finance`}>
                                <ArrowLeft className="mr-1 size-4" /> Back to Finance
                            </Link>
                        </Button>
                        <Heading
                            variant="small"
                            title={`Refund ${refund.reference}`}
                            description={`Against transaction ${transaction.reference} · gateway ${transaction.gateway}`}
                        />
                    </div>
                    {permissions.can_process && refund.status === 'pending' && (
                        <div className="flex gap-2">
                            <Button onClick={process} disabled={busy !== null}>
                                <CheckCircle2 className="mr-1 size-4" /> Mark succeeded
                            </Button>
                            <Button variant="secondary" onClick={deny} disabled={busy !== null}>
                                <ShieldOff className="mr-1 size-4" /> Deny
                            </Button>
                        </div>
                    )}
                </div>

                {/* Status banner */}
                <Card>
                    <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div className="flex items-center gap-3">
                            {refund.status === 'succeeded' ? (
                                <CheckCircle2 className="size-6 text-emerald-600" />
                            ) : refund.status === 'failed' ? (
                                <XCircle className="size-6 text-rose-600" />
                            ) : (
                                <RotateCcw className="size-6 text-amber-600" />
                            )}
                            <div>
                                <p className="text-xs text-muted-foreground">Refund status</p>
                                <p>
                                    <span
                                        className={`rounded px-2 py-0.5 text-xs ${
                                            STATUS_COLOR[refund.status] ?? 'bg-slate-100 text-slate-700'
                                        }`}
                                    >
                                        {refund.status}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-muted-foreground">Amount</p>
                            <p className="text-2xl font-semibold">{fmtMinor(refund.amount_minor, refund.currency)}</p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Refund detail */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Refund details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="Reference" value={<code className="font-mono text-xs">{refund.reference}</code>} />
                            <Row
                                label="Gateway reference"
                                value={refund.gateway_reference ? (
                                    <code className="font-mono text-xs">{refund.gateway_reference}</code>
                                ) : '—'}
                            />
                            <Row label="Reason" value={refund.reason ?? '—'} />
                            <Row label="Initiated by" value={refund.initiator?.email ?? 'system'} />
                            <Row label="Requested" value={refund.created_at ?? '—'} />
                            <Row label="Last updated" value={refund.updated_at ?? '—'} />
                            {refund.response_body && (
                                <div className="border-t pt-3">
                                    <p className="mb-2 text-xs uppercase text-muted-foreground">Gateway response</p>
                                    <pre className="overflow-x-auto rounded bg-muted/40 p-2 text-xs">
                                        {JSON.stringify(refund.response_body, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Parent transaction */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Original transaction</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="Reference" value={<code className="font-mono text-xs">{transaction.reference}</code>} />
                            <Row label="Gateway" value={transaction.gateway} />
                            <Row label="Status" value={transaction.status} />
                            <Row
                                label="Amount"
                                value={fmtMinor(transaction.amount_minor, transaction.currency)}
                            />
                            <Row label="Customer" value={transaction.customer_email ?? transaction.customer_name ?? '—'} />
                            <Row label="Captured" value={transaction.settled_at ?? '—'} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right">{value}</span>
        </div>
    );
}

FinanceRefund.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
