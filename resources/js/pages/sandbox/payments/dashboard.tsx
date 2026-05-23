import { Form, Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    CircleDollarSign,
    Clock,
    CreditCard,
    Loader2,
    Play,
    RotateCw,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };
type Merchant = { slug: string; name: string };
type Stats = {
    volume_today_minor: number;
    authorized_today: number;
    captured_today: number;
    failed_today: number;
    disputed_total: number;
    pending_webhooks: number;
};
type Tx = {
    uuid: string;
    reference: string | null;
    emulate: string;
    method: string;
    state: string;
    scenario: string;
    reason_code: string | null;
    amount: string;
    currency: string;
    created_at: string | null;
};
type WebhookRow = {
    uuid: string;
    event_id: string;
    type: string;
    emulate: string;
    status: string;
    attempts: number;
    scheduled_for: string | null;
    response_status: number | null;
};
type MagicCard = { pan: string; scenario: string; brand: string };
type MagicMsisdn = { suffix: string; scenario: string };
type MagicAch = { account: string; scenario: string };

type Props = {
    merchant: Merchant & { emulate_default: string; default_currency: string; webhook_endpoint: string | null };
    merchants: Merchant[];
    clock: { virtual_time: string; wall_time: string };
    balance: { pending: number; available: number; total: number; currency: string };
    stats: Stats;
    transactions: Tx[];
    webhooks: WebhookRow[];
    scenarios: string[];
    magic: { cards: MagicCard[]; msisdns: MagicMsisdn[]; ach: MagicAch[] };
    breadcrumbs: Breadcrumb[];
};

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

function fmtMoney(minor: number, currency: string) {
    return `${(minor / 100).toFixed(2)} ${currency}`;
}

export default function SandboxDashboard({
    merchant,
    merchants,
    clock,
    balance,
    stats,
    transactions,
    webhooks,
    scenarios,
    magic,
}: Props) {
    const [emulate, setEmulate] = useState(merchant.emulate_default);
    const [method, setMethod] = useState('card');
    const [currency, setCurrency] = useState(merchant.default_currency);
    const [scenario, setScenario] = useState<string>('success');
    const [advanceSeconds, setAdvanceSeconds] = useState('60');

    const switchMerchant = (slug: string) => {
        router.get(window.location.pathname, { merchant: slug }, { preserveState: false });
    };

    const advanceClock = () => {
        router.post(`/sandbox/payments/clock/${merchant.slug}/advance`, { seconds: Number(advanceSeconds) }, {
            preserveScroll: true,
            onSuccess: () => toast.success(`Clock advanced ${advanceSeconds}s`),
            onError: () => toast.error('Clock advance failed.'),
        });
    };

    const flushHooks = () => {
        router.post(`/sandbox/payments/webhooks/${merchant.slug}/flush`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Webhooks flushed.'),
        });
    };

    const replay = (uuid: string) => {
        router.post(`/sandbox/payments/webhooks/${uuid}/replay`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Webhook replayed.'),
        });
    };

    const drop = (uuid: string) => {
        router.post(`/sandbox/payments/webhooks/${uuid}/drop`, {}, {
            preserveScroll: true,
            onSuccess: () => toast('Dropped.'),
        });
    };

    const pickMagicCard = (pan: string) => {
        const el = document.getElementById('field-pan') as HTMLInputElement | null;
        if (el) el.value = pan;
    };

    const groupedScenarios = useMemo(() => {
        const groups: Record<string, string[]> = {};
        for (const s of scenarios) {
            const head = s.includes('.') ? s.split('.')[0] : 'other';
            (groups[head] ||= []).push(s);
        }
        return groups;
    }, [scenarios]);

    return (
        <>
            <Head title="Sandbox payments" />
            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="Sandbox payments"
                        description="Trigger scenarios, inspect transactions, watch webhooks fly. No real funds move."
                    />
                    <Select value={merchant.slug} onValueChange={switchMerchant}>
                        <SelectTrigger className="w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {merchants.map((m) => (
                                <SelectItem key={m.slug} value={m.slug}>
                                    {m.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Stats row */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                    <StatTile
                        icon={<CircleDollarSign className="size-4" />}
                        label="Volume today"
                        value={fmtMoney(stats.volume_today_minor, merchant.default_currency)}
                    />
                    <StatTile icon={<CreditCard className="size-4" />} label="Authorized" value={stats.authorized_today} />
                    <StatTile icon={<CheckCircle2 className="size-4" />} label="Captured" value={stats.captured_today} />
                    <StatTile icon={<AlertCircle className="size-4" />} label="Failed" value={stats.failed_today} />
                    <StatTile icon={<Activity className="size-4" />} label="Disputed" value={stats.disputed_total} />
                    <StatTile
                        icon={<Clock className="size-4" />}
                        label="Pending webhooks"
                        value={stats.pending_webhooks}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Scenario panel */}
                    <Card className="lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="text-base">Trigger scenario</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form action="/sandbox/payments/charge" method="post">
                                {({ processing }) => (
                                    <div className="space-y-3 text-sm">
                                        <input type="hidden" name="merchant" value={merchant.slug} />
                                        <input type="hidden" name="emulate" value={emulate} />
                                        <input type="hidden" name="method" value={method} />
                                        <input type="hidden" name="currency" value={currency} />
                                        <input type="hidden" name="scenario" value={scenario} />

                                        <div className="grid gap-2">
                                            <FieldLabel>Emulate</FieldLabel>
                                            <Select value={emulate} onValueChange={setEmulate}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {['stripe', 'adyen', 'paynow', 'pesepay', 'ecocash', 'zimswitch'].map((e) => (
                                                        <SelectItem key={e} value={e}>
                                                            {e}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel>Method</FieldLabel>
                                            <Select value={method} onValueChange={setMethod}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {['card', 'wallet', 'ach', 'sepa', 'bnpl', 'mobile'].map((m) => (
                                                        <SelectItem key={m} value={m}>
                                                            {m}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="grid gap-2">
                                                <FieldLabel htmlFor="amount">Amount</FieldLabel>
                                                <Input id="amount" name="amount" type="number" step="0.01" defaultValue="19.99" />
                                            </div>
                                            <div className="grid gap-2">
                                                <FieldLabel>Currency</FieldLabel>
                                                <Select value={currency} onValueChange={setCurrency}>
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {['USD', 'EUR', 'GBP', 'ZAR', 'ZWL'].map((c) => (
                                                            <SelectItem key={c} value={c}>
                                                                {c}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel>Scenario</FieldLabel>
                                            <Select value={scenario} onValueChange={setScenario}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(groupedScenarios).map(([group, items]) => (
                                                        <div key={group} className="px-1 py-1">
                                                            <div className="px-2 text-[10px] font-semibold uppercase text-muted-foreground">
                                                                {group}
                                                            </div>
                                                            {items.map((s) => (
                                                                <SelectItem key={s} value={s}>
                                                                    {s}
                                                                </SelectItem>
                                                            ))}
                                                        </div>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="grid gap-2">
                                            <FieldLabel htmlFor="field-pan">PAN (overrides scenario)</FieldLabel>
                                            <Input id="field-pan" name="pan" placeholder="4242 4242 4242 4242" />
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="grid gap-2">
                                                <FieldLabel htmlFor="field-msisdn">MSISDN</FieldLabel>
                                                <Input id="field-msisdn" name="msisdn" placeholder="263772222516" />
                                            </div>
                                            <div className="grid gap-2">
                                                <FieldLabel htmlFor="field-email">Email</FieldLabel>
                                                <Input id="field-email" name="email" type="email" />
                                            </div>
                                        </div>

                                        <Button type="submit" disabled={processing} className="w-full">
                                            {processing ? <Loader2 className="size-4 animate-spin" /> : <><Play className="mr-1 size-4" /> Run</>}
                                        </Button>
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    {/* Right column: balance + clock + magic values */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Merchant balance &amp; clock</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p className="text-xs text-muted-foreground">Available</p>
                                        <p className="text-lg font-semibold">{fmtMoney(balance.available, balance.currency)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Pending</p>
                                        <p className="text-lg font-semibold">{fmtMoney(balance.pending, balance.currency)}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Total</p>
                                        <p className="text-lg font-semibold">{fmtMoney(balance.total, balance.currency)}</p>
                                    </div>
                                </div>
                                <div className="mt-4 flex items-center gap-2 border-t pt-4 text-xs text-muted-foreground">
                                    <Clock className="size-4" />
                                    <span>
                                        virtual <span className="font-mono">{clock.virtual_time}</span> · wall{' '}
                                        <span className="font-mono">{clock.wall_time}</span>
                                    </span>
                                </div>
                                <div className="mt-3 flex gap-2">
                                    <Input
                                        className="w-32"
                                        type="number"
                                        min={1}
                                        value={advanceSeconds}
                                        onChange={(e) => setAdvanceSeconds(e.target.value)}
                                    />
                                    <Button size="sm" variant="secondary" onClick={advanceClock}>
                                        Advance seconds
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={flushHooks}>
                                        Flush webhooks
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Magic values</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <p className="mb-1 font-semibold uppercase tracking-wide text-muted-foreground">
                                            Cards
                                        </p>
                                        <ul className="space-y-1">
                                            {magic.cards.slice(0, 8).map((c) => (
                                                <li key={c.pan}>
                                                    <button
                                                        type="button"
                                                        onClick={() => pickMagicCard(c.pan)}
                                                        className="font-mono text-left hover:underline"
                                                    >
                                                        {c.pan}
                                                    </button>{' '}
                                                    <span className="text-muted-foreground">
                                                        — {c.brand} · {c.scenario}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                    <div>
                                        <p className="mb-1 font-semibold uppercase tracking-wide text-muted-foreground">
                                            MSISDN suffixes
                                        </p>
                                        <ul className="space-y-1">
                                            {magic.msisdns.map((m) => (
                                                <li key={m.suffix}>
                                                    <span className="font-mono">…{m.suffix}</span>{' '}
                                                    <span className="text-muted-foreground">— {m.scenario}</span>
                                                </li>
                                            ))}
                                        </ul>
                                        <p className="mb-1 mt-3 font-semibold uppercase tracking-wide text-muted-foreground">
                                            ACH accounts
                                        </p>
                                        <ul className="space-y-1">
                                            {magic.ach.map((a) => (
                                                <li key={a.account}>
                                                    <span className="font-mono">{a.account}</span>{' '}
                                                    <span className="text-muted-foreground">— {a.scenario}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Transactions */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Recent transactions</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="p-3">Ref</th>
                                    <th className="p-3">Emulate</th>
                                    <th className="p-3">Method</th>
                                    <th className="p-3">Scenario</th>
                                    <th className="p-3">State</th>
                                    <th className="p-3">Amount</th>
                                    <th className="p-3">Reason</th>
                                    <th className="p-3">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                {transactions.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="p-4 text-center text-muted-foreground">
                                            No transactions yet — trigger one from the panel above.
                                        </td>
                                    </tr>
                                )}
                                {transactions.map((t) => (
                                    <tr key={t.uuid} className="border-t hover:bg-muted/40">
                                        <td className="p-3 font-mono text-xs">
                                            <Link
                                                href={`/sandbox/payments/transactions/${t.uuid}/inspect`}
                                                className="hover:underline"
                                            >
                                                {t.reference ?? t.uuid.slice(0, 8)}
                                            </Link>
                                        </td>
                                        <td className="p-3">{t.emulate}</td>
                                        <td className="p-3">{t.method}</td>
                                        <td className="p-3 text-xs">{t.scenario}</td>
                                        <td className="p-3">
                                            <span className={`rounded px-2 py-0.5 text-xs ${STATE_CLASS[t.state] ?? ''}`}>
                                                {t.state}
                                            </span>
                                        </td>
                                        <td className="p-3 font-mono text-xs">
                                            {t.amount} {t.currency}
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">{t.reason_code ?? '—'}</td>
                                        <td className="p-3 text-xs text-muted-foreground">{t.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {/* Webhook console */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Webhook console</CardTitle>
                    </CardHeader>
                    <CardContent className="overflow-x-auto p-0">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Type</th>
                                    <th className="p-3">Emulate</th>
                                    <th className="p-3">Attempts</th>
                                    <th className="p-3">Scheduled</th>
                                    <th className="p-3">Resp</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {webhooks.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="p-4 text-center text-muted-foreground">
                                            No webhook events.
                                        </td>
                                    </tr>
                                )}
                                {webhooks.map((w) => (
                                    <tr key={w.uuid} className="border-t hover:bg-muted/40">
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
                                        <td className="p-3 text-xs">{w.emulate}</td>
                                        <td className="p-3 text-xs">{w.attempts}</td>
                                        <td className="p-3 text-xs text-muted-foreground">{w.scheduled_for}</td>
                                        <td className="p-3 text-xs">{w.response_status ?? '—'}</td>
                                        <td className="p-3 text-right">
                                            <Button size="sm" variant="ghost" onClick={() => replay(w.uuid)} title="Replay">
                                                <RotateCw className="size-3.5" />
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => drop(w.uuid)} title="Drop">
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function StatTile({ icon, label, value }: { icon: React.ReactNode; label: string; value: string | number }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-4">
                <div className="rounded-md bg-muted p-2 text-muted-foreground">{icon}</div>
                <div>
                    <p className="text-xs text-muted-foreground">{label}</p>
                    <p className="text-lg font-semibold">{value}</p>
                </div>
            </CardContent>
        </Card>
    );
}

SandboxDashboard.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
