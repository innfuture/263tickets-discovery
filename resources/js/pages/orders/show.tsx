import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Mail, RefreshCw, Send } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Attendee = {
    id: number;
    name: string;
    email: string;
    ticketType: string;
    checkedInAt: string | null;
};

type OrderDetail = {
    reference: string;
    buyerName: string;
    buyerEmail: string;
    buyerPhone: string | null;
    event: string;
    total: string;
    status: 'paid' | 'pending' | 'refunded' | 'voided';
    placedAt: string;
    paymentMethod: string;
    attendees: Attendee[];
};

export default function OrderShow({
    order,
    orderRef,
    permissions,
}: {
    order: OrderDetail | null;
    orderRef: string;
    permissions: { manage: boolean; refund: boolean; resend: boolean };
}) {
    const page = usePage<{
        currentOrganization?: { slug: string } | null;
    }>();
    const ordersUrl = page.props.currentOrganization
        ? `/${page.props.currentOrganization.slug}/orders`
        : '/orders';

    if (!order) {
        return (
            <>
                <Head title={`Order ${orderRef}`} />
                <div className="space-y-6 p-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={ordersUrl}>
                            <ArrowLeft className="size-4" />
                            Back to orders
                        </Link>
                    </Button>
                    <Card className="border-dashed">
                        <CardContent className="py-12 text-center">
                            <p className="text-sm font-medium">
                                Order {orderRef} not found
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Either the reference is wrong or the order
                                belongs to a different organization. The
                                Order data model lands shortly — once it
                                ships, real orders will resolve here.
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
                                <ArrowLeft className="size-4" />
                                Back to orders
                            </Link>
                        </Button>
                        <Heading
                            title={`Order ${order.reference}`}
                            description={`${order.event} · placed ${order.placedAt}`}
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {permissions.resend ? (
                            <Button variant="outline" size="sm">
                                <Send className="size-4" />
                                Resend tickets
                            </Button>
                        ) : null}
                        {permissions.refund ? (
                            <Button variant="outline" size="sm">
                                <RefreshCw className="size-4" />
                                Refund
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Attendees</CardTitle>
                            <CardDescription>
                                Manage who's coming on this order. Edit
                                names, transfer tickets, or contact
                                attendees individually.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {order.attendees.map((a) => (
                                    <li
                                        key={a.id}
                                        className="flex items-center justify-between gap-3 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {a.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {a.email} · {a.ticketType}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground">
                                                {a.checkedInAt
                                                    ? `Checked in ${a.checkedInAt}`
                                                    : 'Not yet checked in'}
                                            </span>
                                            {permissions.manage ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    Manage
                                                </Button>
                                            ) : null}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Buyer</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p className="font-medium">
                                    {order.buyerName}
                                </p>
                                <p className="flex items-center gap-2 text-muted-foreground">
                                    <Mail className="size-3.5" />
                                    {order.buyerEmail}
                                </p>
                                {order.buyerPhone ? (
                                    <p className="text-muted-foreground">
                                        {order.buyerPhone}
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Payment
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Total
                                    </span>
                                    <span className="font-medium">
                                        {order.total}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Status
                                    </span>
                                    <span className="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
                                        {order.status}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Method
                                    </span>
                                    <span>{order.paymentMethod}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

OrderShow.layout = (props: {
    currentOrganization?: { slug: string } | null;
    orderRef?: string;
}) => ({
    breadcrumbs: [
        {
            title: 'Orders',
            href: props.currentOrganization
                ? `/${props.currentOrganization.slug}/orders`
                : '/orders',
        },
        {
            title: props.orderRef ?? 'Order',
            href: '#',
        },
    ],
});
