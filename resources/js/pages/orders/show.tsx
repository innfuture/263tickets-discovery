import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Loader2, Mail, RefreshCw, Send } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Breadcrumb = { title: string; href: string };

type OrderItem = {
    attendee_name: string;
    attendee_email: string | null;
    ticket_type: string;
    unit_price: string;
    currency: string;
    category: string | null;
    checked_in_at: string | null;
};

type OrderDetail = {
    reference: string;
    status: string;
    currency: string;
    subtotal: string;
    tax: string;
    fee: string;
    total: string;
    buyer_name: string;
    buyer_email: string;
    buyer_phone: string | null;
    event: { slug: string; name: string; starts_at: string | null } | null;
    payment_method: string | null;
    payment_reference: string | null;
    placed_at: string | null;
    metadata: Record<string, unknown> | null;
    items: OrderItem[];
};

type Props = {
    order: OrderDetail | null;
    orderRef: string;
    permissions: { manage: boolean; refund: boolean; resend: boolean };
    breadcrumbs: Breadcrumb[];
};

const STATUS_COLOR: Record<string, string> = {
    paid: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-amber-100 text-amber-800',
    refunded: 'bg-cyan-100 text-cyan-800',
    partially_refunded: 'bg-cyan-100 text-cyan-800',
    voided: 'bg-slate-100 text-slate-700',
};

export default function OrderShow({ order, orderRef, permissions }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const ordersUrl = `${orgBase}/orders`;
    const [busy, setBusy] = useState<'resend' | 'refund' | null>(null);

    const resend = () => {
        if (!order) return;
        setBusy('resend');
        router.post(`${orgBase}/orders/${order.reference}/resend`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Tickets resent.'),
            onError: () => toast.error('Resend failed.'),
            onFinish: () => setBusy(null),
        });
    };

    const refund = () => {
        if (!order) return;
        if (!confirm(`Refund ${order.total} ${order.currency} to ${order.buyer_email}?`)) return;
        setBusy('refund');
        router.post(`${orgBase}/orders/${order.reference}/refund`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Order refunded.'),
            onError: () => toast.error('Refund failed.'),
            onFinish: () => setBusy(null),
        });
    };

    if (!order) {
        return (
            <>
                <Head title={`Order ${orderRef}`} />
                <div className="space-y-6 p-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={ordersUrl}>
                            <ArrowLeft className="mr-1 size-4" /> Back to orders
                        </Link>
                    </Button>
                    <Card className="border-dashed">
                        <CardContent className="py-12 text-center">
                            <p className="text-sm font-medium">Order {orderRef} not found</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Either the reference is wrong, the order belongs to another organization, or it has not
                                been placed yet.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Order ${order.reference}`} />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={ordersUrl}>
                                <ArrowLeft className="mr-1 size-4" /> Back to orders
                            </Link>
                        </Button>
                        <Heading
                            variant="small"
                            title={`Order ${order.reference}`}
                            description={
                                order.event
                                    ? `${order.event.name} · placed ${order.placed_at ?? '—'}`
                                    : `Placed ${order.placed_at ?? '—'}`
                            }
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {permissions.resend && (
                            <Button variant="outline" size="sm" onClick={resend} disabled={busy !== null}>
                                {busy === 'resend' ? (
                                    <Loader2 className="mr-1 size-4 animate-spin" />
                                ) : (
                                    <Send className="mr-1 size-4" />
                                )}
                                Resend tickets
                            </Button>
                        )}
                        {permissions.refund && (
                            <Button variant="outline" size="sm" onClick={refund} disabled={busy !== null}>
                                {busy === 'refund' ? (
                                    <Loader2 className="mr-1 size-4 animate-spin" />
                                ) : (
                                    <RefreshCw className="mr-1 size-4" />
                                )}
                                Refund
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Items */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Items</CardTitle>
                            <CardDescription>
                                Each ticket / line item on the order. Use the action menu to edit names, transfer, or
                                contact individual attendees.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            {order.items.length === 0 ? (
                                <p className="px-6 pb-6 text-sm text-muted-foreground">No items recorded.</p>
                            ) : (
                                <ul className="divide-y">
                                    {order.items.map((i, idx) => (
                                        <li
                                            key={`${i.attendee_name}-${idx}`}
                                            className="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                                        >
                                            <div>
                                                <p className="font-medium">{i.attendee_name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {i.attendee_email ?? 'no email'} · {i.ticket_type}
                                                    {i.category && ` · ${i.category}`}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-mono text-xs">
                                                    {i.unit_price} {i.currency}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {i.checked_in_at ? `Checked in ${i.checked_in_at}` : 'Not checked in'}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    {/* Sidebar */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Buyer</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p className="font-medium">{order.buyer_name}</p>
                                <p className="flex items-center gap-2 text-muted-foreground">
                                    <Mail className="size-3.5" />
                                    {order.buyer_email}
                                </p>
                                {order.buyer_phone && (
                                    <p className="text-muted-foreground">{order.buyer_phone}</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Payment</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <Row label="Subtotal" value={`${order.subtotal} ${order.currency}`} />
                                {parseFloat(order.tax) > 0 && (
                                    <Row label="Tax" value={`${order.tax} ${order.currency}`} />
                                )}
                                {parseFloat(order.fee) > 0 && (
                                    <Row label="Fees" value={`${order.fee} ${order.currency}`} />
                                )}
                                <Row label="Total" value={`${order.total} ${order.currency}`} strong />
                                <div className="flex items-center justify-between border-t pt-2">
                                    <span className="text-muted-foreground">Status</span>
                                    <span
                                        className={`rounded px-2 py-0.5 text-xs ${
                                            STATUS_COLOR[order.status] ?? 'bg-slate-100 text-slate-700'
                                        }`}
                                    >
                                        {order.status}
                                    </span>
                                </div>
                                {order.payment_method && <Row label="Method" value={order.payment_method} />}
                                {order.payment_reference && (
                                    <Row label="Ref" value={<code className="font-mono text-xs">{order.payment_reference}</code>} />
                                )}
                            </CardContent>
                        </Card>

                        {order.event && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Event</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <Link
                                        href={`${orgBase}/events/${order.event.slug}`}
                                        className="block font-medium hover:underline"
                                    >
                                        {order.event.name}
                                    </Link>
                                    {order.event.starts_at && (
                                        <p className="text-xs text-muted-foreground">{order.event.starts_at}</p>
                                    )}
                                    <Link
                                        href={`${orgBase}/attendees?event=${order.event.slug}`}
                                        className="block text-xs text-primary hover:underline"
                                    >
                                        View attendees for this event →
                                    </Link>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Row({ label, value, strong }: { label: string; value: React.ReactNode; strong?: boolean }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className={strong ? 'font-semibold' : ''}>{value}</span>
        </div>
    );
}

OrderShow.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
