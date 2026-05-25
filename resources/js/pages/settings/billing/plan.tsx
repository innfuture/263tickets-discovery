import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/ui/confirmation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';

type Breadcrumb = { title: string; href: string };
type Plan = { tier: string; name: string; events_per_month: number | null; tickets_per_month: number | null; storage_gb: number | null; price_cents: number | null };
type Usage = { events_this_month: number; tickets_this_month: number; unique_attendees: number; storage_mb: number; period_start: string; period_end: string };

export default function PlanPage({ current, plans, usage }: { current: Plan; plans: Plan[]; usage: Usage; breadcrumbs: Breadcrumb[] }) {
    const confirm = useConfirm();
    const fmtPrice = (cents: number | null) => cents === null ? 'Custom' : cents === 0 ? 'Free' : `$${(cents / 100).toFixed(0)}/mo`;

    return (
        <>
            <Head title="Plan & usage — Settings" />
            <div className="space-y-6">
                <Heading variant="small" title="Plan & usage" description="Your current plan and consumption this cycle." />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between text-base"><span>Current plan: {current.name}</span><Badge>{fmtPrice(current.price_cents)}</Badge></CardTitle>
                        <CardDescription>Cycle: {new Date(usage.period_start).toLocaleDateString()} – {new Date(usage.period_end).toLocaleDateString()}</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <Meter label="Events" used={usage.events_this_month} limit={current.events_per_month} />
                        <Meter label="Tickets sold" used={usage.tickets_this_month} limit={current.tickets_per_month} />
                        <Meter label="Storage (MB)" used={usage.storage_mb} limit={current.storage_gb !== null ? current.storage_gb * 1024 : null} />
                        <div className="rounded-md border p-3">
                            <p className="text-xs text-muted-foreground">Unique attendees</p>
                            <p className="text-2xl font-semibold">{usage.unique_attendees.toLocaleString()}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle className="text-base">Switch plans</CardTitle></CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {plans.map((p) => (
                            <div key={p.tier} className={`rounded-md border p-3 ${p.tier === current.tier ? 'border-primary ring-1 ring-primary' : ''}`}>
                                <p className="text-sm font-semibold">{p.name}</p>
                                <p className="text-xs text-muted-foreground">{fmtPrice(p.price_cents)}</p>
                                <ul className="my-3 space-y-1 text-xs">
                                    <li className="flex items-center gap-1"><Check className="size-3" /> {p.events_per_month ?? '∞'} events/mo</li>
                                    <li className="flex items-center gap-1"><Check className="size-3" /> {p.tickets_per_month?.toLocaleString() ?? '∞'} tickets/mo</li>
                                    <li className="flex items-center gap-1"><Check className="size-3" /> {p.storage_gb ?? '∞'} GB storage</li>
                                </ul>
                                {p.tier !== current.tier ? (
                                    <Button size="sm" className="w-full" onClick={async () => { if (await confirm({ title: `Switch to ${p.name}?` })) router.post('/settings/billing/plan', { tier: p.tier }); }}>Choose</Button>
                                ) : <p className="text-center text-xs text-muted-foreground">Current</p>}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Meter({ label, used, limit }: { label: string; used: number; limit: number | null }) {
    const pct = limit ? Math.min(100, (used / limit) * 100) : 0;
    return (
        <div className="rounded-md border p-3">
            <div className="flex items-center justify-between text-xs"><span className="text-muted-foreground">{label}</span><span>{used.toLocaleString()} / {limit ?? '∞'}</span></div>
            {limit && <Progress value={pct} className="mt-2 h-2" />}
        </div>
    );
}

PlanPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
