import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ExternalLink, Loader2, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

type Breadcrumb = { title: string; href: string };

type TimelineEntry = {
    from_state: string | null;
    to_state: string;
    reason: string | null;
    actor: string;
    occurred_at: string | null;
    virtual_time_at: string | null;
    context: Record<string, unknown> | null;
};

type WebhookEntry = {
    uuid: string;
    event_id: string;
    type: string;
    emulate: string;
    status: string;
    attempts: number;
    scheduled_for: string | null;
    last_attempt_at: string | null;
    response_status: number | null;
    signature_present: boolean;
};

type Refund = {
    uuid: string;
    amount_minor: number;
    state: string;
    reason: string | null;
    created_at: string | null;
};

type Dispute = {
    uuid: string;
    reason_code: string;
    status: string;
    evidence_due_at: string | null;
    resolved_at: string | null;
};

type Transaction = {
    uuid: string;
    reference: string | null;
    provider_reference: string | null;
    emulate: string;
    method: string;
    state: string;
    scenario: string;
    reason_code: string | null;
    amount: string;
    amount_captured: string;
    amount_refunded: string;
    currency: string;
    redirect_url: string | null;
    authorized_at: string | null;
    captured_at: string | null;
    failed_at: string | null;
    settlement_at: string | null;
    expires_at: string | null;
    request_body: Record<string, unknown> | null;
    response_body: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
};

type Props = {
    transaction: Transaction;
    merchant: { slug: string | null; name: string | null };
    timeline: TimelineEntry[];
    webhooks: WebhookEntry[];
    refunds: Refund[];
    disputes: Dispute[];
    has_recording: boolean;
    breadcrumbs: Breadcrumb[];
};

type Tab = 'timeline' | 'request' | 'response' | 'webhooks' | 'refunds' | 'diff';

type DiffEntry = { path: string; recorded: unknown; sandbox: unknown };

const STATE_CLASS: Record<string, string> = {
    initiated: 'bg-slate-100 text-slate-700',
    auth_pending: 'bg-amber-100 text-amber-800',
    authorized: 'bg-blue-100 text-blue-800',
    captured: 'bg-emerald-100 text-emerald-800',
    part_refunded: 'bg-cyan-100 text-cyan-800',
    refunded: 'bg-cyan-100 text-cyan-800',
    voided: 'bg-slate-100 text-slate-700',
    failed: 'bg-rose-100 text-rose-800',
    disputed: 'bg-purple-100 text-purple-800',
};

function asJsonPretty(v: unknown): string {
    return JSON.stringify(v ?? {}, null, 2);
}

export default function SandboxInspector({
    transaction,
    merchant,
    timeline,
    webhooks,
    refunds,
    disputes,
    has_recording,
}: Props) {
    const [tab, setTab] = useState<Tab>('timeline');
    const [diff, setDiff] = useState<DiffEntry[] | null>(null);
    const [diffLoading, setDiffLoading] = useState(false);
    const [refundAmount, setRefundAmount] = useState('');

    useEffect(() => {
        if (tab !== 'diff' || diff !== null) return;
        setDiffLoading(true);
        fetch(`/sandbox/payments/transactions/${transaction.uuid}/diff`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((body: { has_recording: boolean; diff: DiffEntry[] }) => {
                setDiff(body.diff);
            })
            .finally(() => setDiffLoading(false));
    }, [tab, diff, transaction.uuid]);

    const capture = () => {
        router.post(`/sandbox/payments/transactions/${transaction.uuid}/capture`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Captured.'),
        });
    };

    const voidTx = () => {
        router.post(`/sandbox/payments/transactions/${transaction.uuid}/void`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Voided.'),
        });
    };

    const openDispute = () => {
        router.post(`/sandbox/payments/disputes/${transaction.uuid}/open`, { reason_code: '10.4' }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Dispute opened.'),
        });
    };

    return (
        <>
            <Head title={`Sandbox · ${transaction.reference ?? transaction.uuid.slice(0, 8)}`} />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <Link
                            href="/sandbox/payments/dashboard"
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft className="mr-1 inline size-3.5" /> Dashboard
                        </Link>
                        <Heading
                            variant="small"
                            title={transaction.reference ?? transaction.uuid}
                            description={`${transaction.emulate} · ${transaction.method} · scenario ${transaction.scenario}`}
                        />
                    </div>
                    <div className="flex gap-2">
                        {transaction.state === 'authorized' && (
                            <>
                                <Button size="sm" onClick={capture}>
                                    Capture
                                </Button>
                                <Button size="sm" variant="secondary" onClick={voidTx}>
                                    Void
                                </Button>
                            </>
                        )}
                        {transaction.state === 'captured' && (
                            <Button size="sm" variant="secondary" onClick={openDispute}>
                                Open dispute
                            </Button>
                        )}
                    </div>
                </div>

                {/* Header card */}
                <Card>
                    <CardContent className="grid grid-cols-2 gap-4 p-6 md:grid-cols-4">
                        <Field label="State">
                            <span className={`rounded px-2 py-0.5 text-xs ${STATE_CLASS[transaction.state] ?? ''}`}>
                                {transaction.state}
                            </span>
                        </Field>
                        <Field label="Amount">
                            <span className="font-mono">{transaction.amount} {transaction.currency}</span>
                        </Field>
                        <Field label="Captured">
                            <span className="font-mono">{transaction.amount_captured}</span>
                        </Field>
                        <Field label="Refunded">
                            <span className="font-mono">{transaction.amount_refunded}</span>
                        </Field>
                        <Field label="Provider ref">
                            <span className="font-mono text-xs">{transaction.provider_reference ?? '—'}</span>
                        </Field>
                        <Field label="Merchant">
                            <span>{merchant.name ?? merchant.slug}</span>
                        </Field>
                        <Field label="Reason code">
                            <span>{transaction.reason_code ?? '—'}</span>
                        </Field>
                        <Field label="Expires (auth)">
                            <span className="text-xs text-muted-foreground">{transaction.expires_at ?? '—'}</span>
                        </Field>
                    </CardContent>
                </Card>

                {transaction.redirect_url && (
                    <Card>
                        <CardContent className="flex items-center justify-between p-4 text-sm">
                            <span className="text-muted-foreground">
                                Payer should be redirected to this URL (3DS or BNPL hosted checkout).
                            </span>
                            <a
                                href={transaction.redirect_url}
                                target="_blank"
                                rel="noreferrer"
                                className="text-primary hover:underline"
                            >
                                Open <ExternalLink className="ml-1 inline size-3" />
                            </a>
                        </CardContent>
                    </Card>
                )}

                {/* Tabs */}
                <div className="flex gap-1 border-b">
                    {(['timeline', 'request', 'response', 'webhooks', 'refunds', 'diff'] as Tab[]).map((t) => (
                        <button
                            key={t}
                            type="button"
                            onClick={() => setTab(t)}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm capitalize ${
                                tab === t
                                    ? 'border-primary font-semibold'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {t === 'diff' ? `diff vs prod${has_recording ? '' : ' (no fixture)'}` : t}
                            {t === 'webhooks' && webhooks.length > 0 && (
                                <span className="ml-1 rounded bg-muted px-1 text-xs">{webhooks.length}</span>
                            )}
                            {t === 'refunds' && (refunds.length > 0 || disputes.length > 0) && (
                                <span className="ml-1 rounded bg-muted px-1 text-xs">
                                    {refunds.length + disputes.length}
                                </span>
                            )}
                        </button>
                    ))}
                </div>

                {/* Tab content */}
                {tab === 'timeline' && (
                    <Card>
                        <CardContent className="p-4">
                            {timeline.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No state log entries.</p>
                            ) : (
                                <ol className="space-y-3">
                                    {timeline.map((e, i) => (
                                        <li key={i} className="flex gap-3">
                                            <div className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                            <div className="text-sm">
                                                <div>
                                                    <span className="text-muted-foreground">{e.from_state ?? '∅'}</span>{' '}
                                                    →{' '}
                                                    <span
                                                        className={`rounded px-2 py-0.5 text-xs ${
                                                            STATE_CLASS[e.to_state] ?? ''
                                                        }`}
                                                    >
                                                        {e.to_state}
                                                    </span>
                                                    <span className="ml-2 text-xs text-muted-foreground">
                                                        by {e.actor}
                                                        {e.reason && ` · ${e.reason}`}
                                                    </span>
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    virtual {e.virtual_time_at} · wall {e.occurred_at}
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                )}

                {tab === 'request' && (
                    <Card>
                        <CardContent className="p-0">
                            <pre className="overflow-auto p-4 text-xs">{asJsonPretty(transaction.request_body)}</pre>
                        </CardContent>
                    </Card>
                )}

                {tab === 'response' && (
                    <Card>
                        <CardContent className="p-0">
                            <pre className="overflow-auto p-4 text-xs">{asJsonPretty(transaction.response_body)}</pre>
                        </CardContent>
                    </Card>
                )}

                {tab === 'webhooks' && (
                    <Card>
                        <CardContent className="p-0">
                            <table className="w-full text-sm">
                                <thead className="text-left text-xs uppercase text-muted-foreground">
                                    <tr>
                                        <th className="p-3">Status</th>
                                        <th className="p-3">Type</th>
                                        <th className="p-3">Attempts</th>
                                        <th className="p-3">Scheduled</th>
                                        <th className="p-3">Last try</th>
                                        <th className="p-3">Resp</th>
                                        <th className="p-3">Signed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {webhooks.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="p-4 text-center text-muted-foreground">
                                                No webhook deliveries for this transaction.
                                            </td>
                                        </tr>
                                    )}
                                    {webhooks.map((w) => (
                                        <tr key={w.uuid} className="border-t">
                                            <td className="p-3">
                                                <span
                                                    className={`rounded px-2 py-0.5 text-xs ${
                                                        w.status === 'delivered'
                                                            ? 'bg-emerald-100 text-emerald-800'
                                                            : w.status === 'failed' || w.status === 'dropped'
                                                              ? 'bg-rose-100 text-rose-800'
                                                              : 'bg-amber-100 text-amber-800'
                                                    }`}
                                                >
                                                    {w.status}
                                                </span>
                                            </td>
                                            <td className="p-3 font-mono text-xs">{w.type}</td>
                                            <td className="p-3 text-xs">{w.attempts}</td>
                                            <td className="p-3 text-xs text-muted-foreground">{w.scheduled_for}</td>
                                            <td className="p-3 text-xs text-muted-foreground">{w.last_attempt_at}</td>
                                            <td className="p-3 text-xs">{w.response_status ?? '—'}</td>
                                            <td className="p-3">
                                                {w.signature_present ? (
                                                    <CheckCircle2 className="size-4 text-emerald-500" />
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                {tab === 'refunds' && (
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Refunds</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {refunds.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No refunds.</p>
                                ) : (
                                    <ul className="space-y-1">
                                        {refunds.map((r) => (
                                            <li key={r.uuid} className="flex items-center justify-between text-sm">
                                                <span className="font-mono text-xs">{r.uuid.slice(0, 12)}</span>
                                                <span>
                                                    {(r.amount_minor / 100).toFixed(2)} {transaction.currency} —{' '}
                                                    {r.state}
                                                </span>
                                                <span className="text-xs text-muted-foreground">{r.created_at}</span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {transaction.state === 'captured' && (
                                    <div className="flex gap-2 border-t pt-3">
                                        <Input
                                            placeholder="Amount (minor, blank = full)"
                                            value={refundAmount}
                                            onChange={(e) => setRefundAmount(e.target.value)}
                                            className="max-w-xs"
                                        />
                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                toast(
                                                    'Refund via the kernel API; UI button stub. See README for snippet.',
                                                );
                                            }}
                                        >
                                            Refund
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-sm">Disputes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {disputes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No disputes.</p>
                                ) : (
                                    <ul className="space-y-1">
                                        {disputes.map((d) => (
                                            <li key={d.uuid} className="flex items-center justify-between text-sm">
                                                <span className="font-mono text-xs">{d.uuid.slice(0, 12)}</span>
                                                <span>
                                                    {d.reason_code} — {d.status}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    due {d.evidence_due_at}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                )}

                {tab === 'diff' && (
                    <Card>
                        <CardContent className="p-0">
                            {!has_recording && (
                                <div className="flex items-center gap-2 p-4 text-sm text-amber-700">
                                    <ShieldAlert className="size-4" />
                                    <span>
                                        No production recording found for{' '}
                                        <code>{transaction.emulate}/charge/{transaction.scenario}</code>. Import one
                                        with <code>php artisan sandbox:record</code>.
                                    </span>
                                </div>
                            )}
                            {has_recording && diffLoading && (
                                <div className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" /> Computing diff…
                                </div>
                            )}
                            {has_recording && !diffLoading && diff !== null && (
                                <>
                                    {diff.length === 0 ? (
                                        <div className="flex items-center gap-2 p-4 text-sm text-emerald-700">
                                            <CheckCircle2 className="size-4" />
                                            Sandbox response matches the recorded production fixture byte-for-byte.
                                        </div>
                                    ) : (
                                        <table className="w-full text-xs">
                                            <thead className="text-left uppercase text-muted-foreground">
                                                <tr>
                                                    <th className="p-3">Path</th>
                                                    <th className="p-3">Recorded (prod)</th>
                                                    <th className="p-3">Sandbox</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {diff.map((d) => (
                                                    <tr key={d.path} className="border-t align-top">
                                                        <td className="p-3 font-mono">{d.path}</td>
                                                        <td className="p-3 font-mono text-amber-700">
                                                            {JSON.stringify(d.recorded)}
                                                        </td>
                                                        <td className="p-3 font-mono text-blue-700">
                                                            {JSON.stringify(d.sandbox)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm">{children}</p>
        </div>
    );
}

SandboxInspector.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
