import { Form, Head, Link, router } from '@inertiajs/react';
import { Loader2, Pause, Play, Plus, Tag, Ticket } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Breadcrumb = { title: string; href: string };

type Discount = {
    id: number;
    name: string;
    discount_type: string;
    value: string;
    min_quantity: number;
    max_uses: number | null;
    uses_count: number;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
    category: string | null;
    event_name: string | null;
    event_slug: string | null;
};

type Promo = {
    id: number;
    code: string;
    discount_type: string;
    value: string;
    max_uses: number | null;
    uses_count: number;
    expires_at: string | null;
    is_active: boolean;
    category: string | null;
    event_name: string | null;
    event_slug: string | null;
};

type Category = { value: number; label: string };

type Props = {
    discounts: Discount[];
    promo_codes: Promo[];
    categories: Category[];
    permissions: { can_manage_promo: boolean };
    breadcrumbs: Breadcrumb[];
};

export default function Discounts({ discounts, promo_codes, categories, permissions }: Props) {
    const orgBase = window.location.pathname.split('/').slice(0, 2).join('/');
    const [tab, setTab] = useState<'discounts' | 'promos'>('discounts');

    const [discountCat, setDiscountCat] = useState<string>(String(categories[0]?.value ?? ''));
    const [discountType, setDiscountType] = useState<'percentage' | 'fixed'>('percentage');
    const [promoCat, setPromoCat] = useState<string>(String(categories[0]?.value ?? ''));
    const [promoType, setPromoType] = useState<'percentage' | 'fixed'>('percentage');

    const toggleDiscount = (id: number) =>
        router.post(`${orgBase}/discounts/${id}/toggle`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Updated.'),
        });
    const togglePromo = (id: number) =>
        router.post(`${orgBase}/promo-codes/${id}/toggle`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success('Updated.'),
        });

    return (
        <>
            <Head title="Discounts" />
            <div className="space-y-4 p-4">
                <Heading
                    variant="small"
                    title="Discounts"
                    description="Org-wide library of automatic discounts and promo codes."
                />

                {categories.length === 0 ? (
                    <Card>
                        <CardContent className="p-6 text-sm text-muted-foreground">
                            No ticket categories yet. Create an event with categories first to attach discounts.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="flex gap-1 border-b">
                            <TabButton active={tab === 'discounts'} onClick={() => setTab('discounts')}>
                                Discounts ({discounts.length})
                            </TabButton>
                            <TabButton active={tab === 'promos'} onClick={() => setTab('promos')}>
                                Promo codes ({promo_codes.length})
                            </TabButton>
                        </div>

                        {tab === 'discounts' && (
                            <>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Plus className="size-4" /> Add discount
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <Form
                                            action={`${orgBase}/discounts`}
                                            method="post"
                                            onError={() => toast.error('Check fields.')}
                                        >
                                            {({ processing }) => (
                                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                    <input type="hidden" name="ticket_category_id" value={discountCat} />
                                                    <input type="hidden" name="discount_type" value={discountType} />
                                                    <div className="grid gap-1">
                                                        <FieldLabel>Category</FieldLabel>
                                                        <Select value={discountCat} onValueChange={setDiscountCat}>
                                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                                            <SelectContent>
                                                                {categories.map((c) => (
                                                                    <SelectItem key={c.value} value={String(c.value)}>
                                                                        {c.label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <FieldLabel htmlFor="d-name">Name</FieldLabel>
                                                        <Input id="d-name" name="name" placeholder="Early bird" />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <FieldLabel>Type</FieldLabel>
                                                        <Select
                                                            value={discountType}
                                                            onValueChange={(v) => setDiscountType(v as 'percentage' | 'fixed')}
                                                        >
                                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="percentage">Percentage</SelectItem>
                                                                <SelectItem value="fixed">Fixed</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <FieldLabel htmlFor="d-value">Value</FieldLabel>
                                                        <Input id="d-value" name="value" type="number" step="0.01" />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <FieldLabel htmlFor="d-min">Min qty</FieldLabel>
                                                        <Input id="d-min" name="min_quantity" type="number" defaultValue={1} />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <FieldLabel htmlFor="d-max">Max uses (optional)</FieldLabel>
                                                        <Input id="d-max" name="max_uses" type="number" />
                                                    </div>
                                                    <div className="grid gap-1">
                                                        <FieldLabel htmlFor="d-expires">Expires</FieldLabel>
                                                        <Input id="d-expires" name="expires_at" type="datetime-local" />
                                                    </div>
                                                    <div className="flex items-end">
                                                        <Button type="submit" disabled={processing} className="w-full">
                                                            {processing ? <Loader2 className="size-4 animate-spin" /> : 'Add'}
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                        </Form>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="overflow-x-auto p-0">
                                        <table className="w-full text-sm">
                                            <thead className="text-left text-xs uppercase text-muted-foreground">
                                                <tr>
                                                    <th className="p-3">Name</th>
                                                    <th className="p-3">Event · Category</th>
                                                    <th className="p-3">Value</th>
                                                    <th className="p-3">Usage</th>
                                                    <th className="p-3">Expires</th>
                                                    <th className="p-3">State</th>
                                                    <th />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {discounts.length === 0 && (
                                                    <tr>
                                                        <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                                            No discounts yet.
                                                        </td>
                                                    </tr>
                                                )}
                                                {discounts.map((d) => (
                                                    <tr key={d.id} className="border-t">
                                                        <td className="p-3">
                                                            <Tag className="mr-1 inline size-3.5 text-muted-foreground" />
                                                            {d.name}
                                                        </td>
                                                        <td className="p-3 text-xs">
                                                            {d.event_slug ? (
                                                                <Link
                                                                    href={`${orgBase}/events/${d.event_slug}/tickets`}
                                                                    className="hover:underline"
                                                                >
                                                                    {d.event_name} · {d.category}
                                                                </Link>
                                                            ) : (
                                                                <span className="text-muted-foreground">orphan</span>
                                                            )}
                                                        </td>
                                                        <td className="p-3 font-mono text-xs">
                                                            {d.discount_type === 'percentage' ? `${d.value}%` : d.value}
                                                        </td>
                                                        <td className="p-3 text-xs">
                                                            {d.uses_count}
                                                            {d.max_uses ? ` / ${d.max_uses}` : ''}
                                                        </td>
                                                        <td className="p-3 text-xs text-muted-foreground">
                                                            {d.expires_at ?? '—'}
                                                        </td>
                                                        <td className="p-3">
                                                            <span
                                                                className={`rounded px-2 py-0.5 text-xs ${
                                                                    d.is_active
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : 'bg-slate-100 text-slate-700'
                                                                }`}
                                                            >
                                                                {d.is_active ? 'active' : 'paused'}
                                                            </span>
                                                        </td>
                                                        <td className="p-3 text-right">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => toggleDiscount(d.id)}
                                                            >
                                                                {d.is_active ? (
                                                                    <Pause className="size-3.5" />
                                                                ) : (
                                                                    <Play className="size-3.5" />
                                                                )}
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </CardContent>
                                </Card>
                            </>
                        )}

                        {tab === 'promos' && (
                            <>
                                {permissions.can_manage_promo && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Plus className="size-4" /> Add promo code
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <Form
                                                action={`${orgBase}/promo-codes`}
                                                method="post"
                                                onError={() => toast.error('Check fields.')}
                                            >
                                                {({ processing }) => (
                                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                                        <input type="hidden" name="ticket_category_id" value={promoCat} />
                                                        <input type="hidden" name="discount_type" value={promoType} />
                                                        <div className="grid gap-1">
                                                            <FieldLabel>Category</FieldLabel>
                                                            <Select value={promoCat} onValueChange={setPromoCat}>
                                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                                <SelectContent>
                                                                    {categories.map((c) => (
                                                                        <SelectItem key={c.value} value={String(c.value)}>
                                                                            {c.label}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <FieldLabel htmlFor="p-code">Code</FieldLabel>
                                                            <Input id="p-code" name="code" placeholder="SUMMER20" />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <FieldLabel>Type</FieldLabel>
                                                            <Select
                                                                value={promoType}
                                                                onValueChange={(v) => setPromoType(v as 'percentage' | 'fixed')}
                                                            >
                                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="percentage">Percentage</SelectItem>
                                                                    <SelectItem value="fixed">Fixed</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <FieldLabel htmlFor="p-value">Value</FieldLabel>
                                                            <Input id="p-value" name="value" type="number" step="0.01" />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <FieldLabel htmlFor="p-max">Max uses</FieldLabel>
                                                            <Input id="p-max" name="max_uses" type="number" />
                                                        </div>
                                                        <div className="grid gap-1">
                                                            <FieldLabel htmlFor="p-expires">Expires</FieldLabel>
                                                            <Input id="p-expires" name="expires_at" type="datetime-local" />
                                                        </div>
                                                        <div className="flex items-end sm:col-span-2">
                                                            <Button type="submit" disabled={processing} className="w-full">
                                                                {processing ? <Loader2 className="size-4 animate-spin" /> : 'Add code'}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                )}
                                            </Form>
                                        </CardContent>
                                    </Card>
                                )}

                                <Card>
                                    <CardContent className="overflow-x-auto p-0">
                                        <table className="w-full text-sm">
                                            <thead className="text-left text-xs uppercase text-muted-foreground">
                                                <tr>
                                                    <th className="p-3">Code</th>
                                                    <th className="p-3">Event · Category</th>
                                                    <th className="p-3">Value</th>
                                                    <th className="p-3">Usage</th>
                                                    <th className="p-3">Expires</th>
                                                    <th className="p-3">State</th>
                                                    <th />
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {promo_codes.length === 0 && (
                                                    <tr>
                                                        <td colSpan={7} className="p-6 text-center text-muted-foreground">
                                                            No promo codes yet.
                                                        </td>
                                                    </tr>
                                                )}
                                                {promo_codes.map((p) => (
                                                    <tr key={p.id} className="border-t">
                                                        <td className="p-3">
                                                            <Ticket className="mr-1 inline size-3.5 text-muted-foreground" />
                                                            <code className="font-mono">{p.code}</code>
                                                        </td>
                                                        <td className="p-3 text-xs">
                                                            {p.event_slug ? (
                                                                <Link
                                                                    href={`${orgBase}/events/${p.event_slug}/tickets`}
                                                                    className="hover:underline"
                                                                >
                                                                    {p.event_name} · {p.category}
                                                                </Link>
                                                            ) : (
                                                                <span className="text-muted-foreground">orphan</span>
                                                            )}
                                                        </td>
                                                        <td className="p-3 font-mono text-xs">
                                                            {p.discount_type === 'percentage' ? `${p.value}%` : p.value}
                                                        </td>
                                                        <td className="p-3 text-xs">
                                                            {p.uses_count}
                                                            {p.max_uses ? ` / ${p.max_uses}` : ''}
                                                        </td>
                                                        <td className="p-3 text-xs text-muted-foreground">
                                                            {p.expires_at ?? '—'}
                                                        </td>
                                                        <td className="p-3">
                                                            <span
                                                                className={`rounded px-2 py-0.5 text-xs ${
                                                                    p.is_active
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : 'bg-slate-100 text-slate-700'
                                                                }`}
                                                            >
                                                                {p.is_active ? 'active' : 'paused'}
                                                            </span>
                                                        </td>
                                                        <td className="p-3 text-right">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => togglePromo(p.id)}
                                                            >
                                                                {p.is_active ? (
                                                                    <Pause className="size-3.5" />
                                                                ) : (
                                                                    <Play className="size-3.5" />
                                                                )}
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function TabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`-mb-px border-b-2 px-3 py-2 text-sm ${
                active ? 'border-primary font-semibold' : 'border-transparent text-muted-foreground hover:text-foreground'
            }`}
        >
            {children}
        </button>
    );
}

Discounts.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
