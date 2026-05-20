import { Form, router } from '@inertiajs/react';
import {
    Eye,
    EyeOff,
    Loader2,
    Pause,
    Percent,
    Play,
    Plus,
    PlusCircle,
    RefreshCw,
    Settings,
    Tag,
    Ticket,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import ImageDropzone from '@/components/image-dropzone';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { SectionTitle } from '@/components/ui/section-title';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    findCurrency,
    formatPrice,
    SUPPORTED_CURRENCIES,
} from '@/lib/currencies';
import { cn } from '@/lib/utils';

type GenerationStatus = 'pending' | 'processing' | 'completed' | 'failed';

type CurrencyPrice = {
    id: number;
    currency: string;
    price: number;
};

type Discount = {
    id: number;
    name: string;
    type: 'fixed' | 'percentage';
    value: number;
    max_uses: number | null;
    starts_at: string | null;
    ends_at: string | null;
};

type PromoCode = {
    id: number;
    code: string;
    type: 'fixed' | 'percentage';
    value: number;
    max_uses: number | null;
    starts_at: string | null;
    ends_at: string | null;
};

type TicketCategory = {
    id: number;
    uuid: string;
    name: string;
    description: string | null;
    image_url: string | null;
    offline_quantity: number;
    online_quantity: number;
    base_price: number;
    base_currency: string;
    min_per_order: number | null;
    max_per_order: number | null;
    is_visible: boolean;
    sales_start_at: string | null;
    sales_end_at: string | null;
    generation_status: GenerationStatus | null;
    generation_progress: number | null;
    sale_status: { value: string; label: string };
    admission_type: { value: string; label: string } | null;
    pass_type: { value: string; label: string } | null;
    currency_prices: CurrencyPrice[];
    discounts: Discount[];
    promo_codes: PromoCode[];
    sort_order: number;
};

type CurrencyPriceDraft = {
    key: string;
    id: number | null;
    currency: string;
    price: string;
};

function newCurrencyPriceDraft(): CurrencyPriceDraft {
    return {
        key: `new-${Math.random().toString(36).slice(2, 10)}`,
        id: null,
        currency: 'EUR',
        price: '',
    };
}

const ADMISSION_TYPES = [
    { value: 'admit_one', label: 'Admit 1' },
    { value: 'admit_two', label: 'Admit 2' },
    { value: 'admit_five', label: 'Admit 5' },
    { value: 'admit_ten', label: 'Admit 10' },
    { value: 'admit_all', label: 'Admit All (Group)' },
];

const PASS_TYPES = [
    { value: 'single', label: 'Single Entry' },
    { value: 'multiple', label: 'Multiple Re-entry' },
    { value: 'unlimited', label: 'Unlimited Entry' },
];

const SALE_STATUS_COLORS: Record<string, string> = {
    active: 'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-400',
    paused: 'bg-amber-500/15 text-amber-700 border-amber-500/30 dark:text-amber-400',
    stopped: 'bg-destructive/15 text-destructive border-destructive/30',
};

const GEN_STATUS_COLORS: Record<GenerationStatus, string> = {
    pending: 'bg-muted text-muted-foreground',
    processing:
        'bg-blue-500/15 text-blue-700 border-blue-500/30 dark:text-blue-400',
    completed:
        'bg-green-500/15 text-green-700 border-green-500/30 dark:text-green-400',
    failed: 'bg-destructive/15 text-destructive border-destructive/30',
};

// ─────────────────────────────────────────────────────────────────────────────
// Polling for offline ticket generation progress
// ─────────────────────────────────────────────────────────────────────────────

function useGenerationPoller(
    teamSlug: string,
    eventSlug: string,
    categories: TicketCategory[],
) {
    const [statuses, setStatuses] = useState<
        Record<string, { status: GenerationStatus; progress: number | null }>
    >({});

    const poll = useCallback(() => {
        const active = categories.filter(
            (c) =>
                c.generation_status === 'pending' ||
                c.generation_status === 'processing',
        );

        if (active.length === 0) {
            return;
        }

        active.forEach((cat) => {
            const url = `/${teamSlug}/events/${eventSlug}/tickets/${cat.uuid}/status`;
            fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then((r) => r.json())
                .then(
                    (data: {
                        generation_status: GenerationStatus;
                        generation_progress: number | null;
                    }) => {
                        setStatuses((prev) => ({
                            ...prev,
                            [cat.uuid]: {
                                status: data.generation_status,
                                progress: data.generation_progress,
                            },
                        }));

                        if (
                            data.generation_status === 'completed' ||
                            data.generation_status === 'failed'
                        ) {
                            router.reload({
                                only: ['event'],
                                preserveScroll: true,
                            });
                        }
                    },
                )
                .catch(() => {});
        });
    }, [teamSlug, eventSlug, categories]);

    useEffect(() => {
        const id = setInterval(poll, 4000);

        return () => clearInterval(id);
    }, [poll]);

    return statuses;
}

// ─────────────────────────────────────────────────────────────────────────────
// Reusable category form (used by both Create dialog and Settings sheet)
// ─────────────────────────────────────────────────────────────────────────────

type CategoryFormState = {
    name: string;
    description: string;
    admissionType: string;
    passType: string;
    offlineQuantity: string;
    onlineQuantity: string;
    basePrice: string;
    baseCurrency: string;
    minPerOrder: string;
    maxPerOrder: string;
    isVisible: boolean;
    salesStartAt: string;
    salesEndAt: string;
    currencyPrices: CurrencyPriceDraft[];
};

function isoToLocalInput(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const tzOffset = date.getTimezoneOffset() * 60000;

    return new Date(date.getTime() - tzOffset).toISOString().slice(0, 16);
}

function initialFormState(category?: TicketCategory | null): CategoryFormState {
    return {
        name: category?.name ?? '',
        description: category?.description ?? '',
        admissionType: category?.admission_type?.value ?? 'admit_one',
        passType: category?.pass_type?.value ?? 'single',
        offlineQuantity: String(category?.offline_quantity ?? 0),
        onlineQuantity: String(category?.online_quantity ?? 100),
        basePrice: category ? String(category.base_price) : '',
        baseCurrency: category?.base_currency ?? 'USD',
        minPerOrder:
            category?.min_per_order != null
                ? String(category.min_per_order)
                : '',
        maxPerOrder:
            category?.max_per_order != null
                ? String(category.max_per_order)
                : '',
        isVisible: category?.is_visible ?? true,
        salesStartAt: isoToLocalInput(category?.sales_start_at ?? null),
        salesEndAt: isoToLocalInput(category?.sales_end_at ?? null),
        currencyPrices:
            category?.currency_prices.map((cp) => ({
                key: `existing-${cp.id}`,
                id: cp.id,
                currency: cp.currency,
                price: String(cp.price),
            })) ?? [],
    };
}

function CategoryFormFields({
    state,
    setState,
    errors,
    imageClientError,
    setImageClientError,
    showImage = true,
    mode,
}: {
    state: CategoryFormState;
    setState: React.Dispatch<React.SetStateAction<CategoryFormState>>;
    errors: Record<string, string>;
    imageClientError: string | null;
    setImageClientError: (v: string | null) => void;
    showImage?: boolean;
    mode: 'create' | 'edit';
}) {
    const update = <K extends keyof CategoryFormState>(
        key: K,
        value: CategoryFormState[K],
    ) => setState((prev) => ({ ...prev, [key]: value }));

    const totalQuantity =
        (parseInt(state.offlineQuantity || '0', 10) || 0) +
        (parseInt(state.onlineQuantity || '0', 10) || 0);

    return (
        <div className="space-y-4">
            <div className="grid gap-1.5">
                <Label htmlFor="cat-name">Category name</Label>
                <Input
                    id="cat-name"
                    name="name"
                    value={state.name}
                    onChange={(e) => update('name', e.target.value)}
                    maxLength={100}
                    required
                    placeholder="General Admission"
                    aria-invalid={!!errors.name}
                />
                {errors.name ? (
                    <p className="text-xs text-destructive">{errors.name}</p>
                ) : null}
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="cat-desc">
                    Description{' '}
                    <span className="text-muted-foreground">(optional)</span>
                </Label>
                <Textarea
                    id="cat-desc"
                    name="description"
                    value={state.description}
                    onChange={(e) => update('description', e.target.value)}
                    rows={2}
                    maxLength={500}
                />
            </div>

            {showImage ? (
                <div className="grid gap-1.5">
                    <div className="flex items-center justify-between gap-2">
                        <Label>
                            Category image{' '}
                            <span className="text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        {imageClientError ? (
                            <p className="text-xs text-destructive">
                                {imageClientError}
                            </p>
                        ) : null}
                    </div>
                    <ImageDropzone
                        name="image"
                        hasError={!!imageClientError}
                        onValidationError={setImageClientError}
                    />
                </div>
            ) : null}

            <div className="grid grid-cols-2 gap-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="cat-admission">Admission</Label>
                    <input
                        type="hidden"
                        name="admission_type"
                        value={state.admissionType}
                    />
                    <Select
                        value={state.admissionType}
                        onValueChange={(v) => update('admissionType', v)}
                    >
                        <SelectTrigger
                            id="cat-admission"
                            className="w-full"
                            aria-invalid={!!errors.admission_type}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {ADMISSION_TYPES.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="cat-pass">Pass type</Label>
                    <input
                        type="hidden"
                        name="pass_type"
                        value={state.passType}
                    />
                    <Select
                        value={state.passType}
                        onValueChange={(v) => update('passType', v)}
                    >
                        <SelectTrigger
                            id="cat-pass"
                            className="w-full"
                            aria-invalid={!!errors.pass_type}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PASS_TYPES.map((opt) => (
                                <SelectItem key={opt.value} value={opt.value}>
                                    {opt.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <Separator />

            <div className="space-y-1.5">
                <Label>Quantity</Label>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-offline"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Offline (pre-printed)
                        </Label>
                        <Input
                            id="cat-offline"
                            name="offline_quantity"
                            type="number"
                            min={0}
                            max={100000}
                            value={state.offlineQuantity}
                            onChange={(e) =>
                                update('offlineQuantity', e.target.value)
                            }
                            aria-invalid={!!errors.offline_quantity}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-online"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Online (on-demand)
                        </Label>
                        <Input
                            id="cat-online"
                            name="online_quantity"
                            type="number"
                            min={0}
                            max={500000}
                            value={state.onlineQuantity}
                            onChange={(e) =>
                                update('onlineQuantity', e.target.value)
                            }
                            aria-invalid={!!errors.online_quantity}
                        />
                    </div>
                </div>
                <p className="text-xs text-muted-foreground">
                    {totalQuantity > 0
                        ? `${totalQuantity.toLocaleString()} total tickets in this category`
                        : 'At least one of Offline or Online must be greater than zero.'}
                </p>
                {errors.offline_quantity || errors.online_quantity ? (
                    <p className="text-xs text-destructive">
                        {errors.offline_quantity ?? errors.online_quantity}
                    </p>
                ) : null}
                {mode === 'edit' ? (
                    <p className="text-xs text-amber-700 dark:text-amber-400">
                        Editing offline quantity after generation requires
                        regenerating the batch. Reducing online quantity below
                        sold count is rejected.
                    </p>
                ) : null}
            </div>

            <Separator />

            <div className="space-y-1.5">
                <Label>Pricing</Label>
                <div className="grid grid-cols-[1fr_140px] gap-3">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-price"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Base price
                        </Label>
                        <Input
                            id="cat-price"
                            name="base_price"
                            type="number"
                            min={0}
                            max={99999.99}
                            step={0.01}
                            value={state.basePrice}
                            onChange={(e) =>
                                update('basePrice', e.target.value)
                            }
                            placeholder="0.00"
                            required
                            aria-invalid={!!errors.base_price}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-currency"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Currency
                        </Label>
                        <input
                            type="hidden"
                            name="base_currency"
                            value={state.baseCurrency}
                        />
                        <Select
                            value={state.baseCurrency}
                            onValueChange={(v) => update('baseCurrency', v)}
                        >
                            <SelectTrigger
                                id="cat-currency"
                                className="w-full"
                                aria-invalid={!!errors.base_currency}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {SUPPORTED_CURRENCIES.map((c) => (
                                    <SelectItem key={c.code} value={c.code}>
                                        {c.code} — {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                {errors.base_price || errors.base_currency ? (
                    <p className="text-xs text-destructive">
                        {errors.base_price ?? errors.base_currency}
                    </p>
                ) : null}
            </div>

            <Separator />

            <div className="space-y-2">
                <Label>Order limits & visibility</Label>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-min-order"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Min per order
                        </Label>
                        <Input
                            id="cat-min-order"
                            name="min_per_order"
                            type="number"
                            min={1}
                            value={state.minPerOrder}
                            onChange={(e) =>
                                update('minPerOrder', e.target.value)
                            }
                            placeholder="1"
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-max-order"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Max per order
                        </Label>
                        <Input
                            id="cat-max-order"
                            name="max_per_order"
                            type="number"
                            min={1}
                            value={state.maxPerOrder}
                            onChange={(e) =>
                                update('maxPerOrder', e.target.value)
                            }
                            placeholder="10"
                        />
                    </div>
                </div>
                <div className="flex items-center gap-2 pt-2">
                    <Checkbox
                        id="cat-visible"
                        checked={state.isVisible}
                        onCheckedChange={(v) => update('isVisible', v === true)}
                    />
                    <input
                        type="hidden"
                        name="is_visible"
                        value={state.isVisible ? '1' : '0'}
                    />
                    <Label
                        htmlFor="cat-visible"
                        className="flex items-center gap-1 text-sm font-normal"
                    >
                        {state.isVisible ? (
                            <Eye className="size-3.5" />
                        ) : (
                            <EyeOff className="size-3.5" />
                        )}
                        Visible to public buyers
                    </Label>
                </div>
            </div>

            <Separator />

            <div className="space-y-2">
                <Label>Sales window</Label>
                <p className="text-xs text-muted-foreground">
                    Optional. Sales auto-open at the start time and close at the
                    end time. Leave blank to control manually.
                </p>
                <div className="grid grid-cols-2 gap-3">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-sales-start"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Opens
                        </Label>
                        <Input
                            id="cat-sales-start"
                            name="sales_start_at"
                            type="datetime-local"
                            value={state.salesStartAt}
                            onChange={(e) =>
                                update('salesStartAt', e.target.value)
                            }
                            aria-invalid={!!errors.sales_start_at}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor="cat-sales-end"
                            className="text-xs font-normal text-muted-foreground"
                        >
                            Closes
                        </Label>
                        <Input
                            id="cat-sales-end"
                            name="sales_end_at"
                            type="datetime-local"
                            value={state.salesEndAt}
                            onChange={(e) =>
                                update('salesEndAt', e.target.value)
                            }
                            min={state.salesStartAt || undefined}
                            aria-invalid={!!errors.sales_end_at}
                        />
                    </div>
                </div>
            </div>

            <Separator />

            <div className="space-y-2">
                <div className="flex items-center justify-between gap-2">
                    <Label>Multi-currency prices</Label>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            update('currencyPrices', [
                                ...state.currencyPrices,
                                newCurrencyPriceDraft(),
                            ])
                        }
                    >
                        <Plus className="size-3.5" />
                        Add currency
                    </Button>
                </div>
                <p className="text-xs text-muted-foreground">
                    Optional. Set fixed prices in additional currencies so
                    buyers see the exact amount in their local currency.
                </p>

                {state.currencyPrices.length === 0 ? (
                    <p className="py-1 text-xs text-muted-foreground italic">
                        Only the base price ({state.baseCurrency}) is used.
                    </p>
                ) : (
                    <div className="space-y-2">
                        {state.currencyPrices.map((row, i) => (
                            <div
                                key={row.key}
                                className="grid grid-cols-[180px_1fr_auto] items-end gap-2"
                            >
                                {row.id ? (
                                    <input
                                        type="hidden"
                                        name={`currency_prices[${i}][id]`}
                                        value={row.id}
                                    />
                                ) : null}
                                <div>
                                    <input
                                        type="hidden"
                                        name={`currency_prices[${i}][currency_code]`}
                                        value={row.currency}
                                    />
                                    <Select
                                        value={row.currency}
                                        onValueChange={(v) => {
                                            const next = [
                                                ...state.currencyPrices,
                                            ];
                                            next[i] = {
                                                ...next[i],
                                                currency: v,
                                            };
                                            update('currencyPrices', next);
                                        }}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {SUPPORTED_CURRENCIES.filter(
                                                (c) =>
                                                    c.code !==
                                                    state.baseCurrency,
                                            ).map((c) => (
                                                <SelectItem
                                                    key={c.code}
                                                    value={c.code}
                                                >
                                                    {c.code} — {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Input
                                    name={`currency_prices[${i}][price]`}
                                    type="number"
                                    min={0}
                                    step={0.01}
                                    value={row.price}
                                    onChange={(e) => {
                                        const next = [...state.currencyPrices];
                                        next[i] = {
                                            ...next[i],
                                            price: e.target.value,
                                        };
                                        update('currencyPrices', next);
                                    }}
                                    placeholder="0.00"
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-9 text-destructive hover:text-destructive"
                                    onClick={() =>
                                        update(
                                            'currencyPrices',
                                            state.currencyPrices.filter(
                                                (_, idx) => idx !== i,
                                            ),
                                        )
                                    }
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Create dialog
// ─────────────────────────────────────────────────────────────────────────────

function CreateCategoryDialog({
    teamSlug,
    eventSlug,
}: {
    teamSlug: string;
    eventSlug: string;
}) {
    const [open, setOpen] = useState(false);
    const [imageClientError, setImageClientError] = useState<string | null>(
        null,
    );
    const [state, setState] = useState<CategoryFormState>(() =>
        initialFormState(),
    );

    const handleOpenChange = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setImageClientError(null);
            setState(initialFormState());
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm" className="w-full">
                    <PlusCircle className="size-4" />
                    Add ticket category
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[90dvh] flex-col gap-0 p-0 sm:max-w-lg">
                <Form
                    key={String(open)}
                    action={`/${teamSlug}/events/${eventSlug}/tickets`}
                    method="post"
                    className="flex min-h-0 flex-1 flex-col"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader className="shrink-0 px-6 pt-6 pb-4">
                                <DialogTitle className="flex items-center gap-2">
                                    <Ticket className="size-4" />
                                    New Ticket Category
                                </DialogTitle>
                                <DialogDescription>
                                    Offline tickets are pre-generated in the
                                    background as unique QR-coded tickets.
                                    Online tickets are minted on-demand at
                                    checkout.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="min-h-0 flex-1 overflow-y-auto px-6 pb-4">
                                <CategoryFormFields
                                    state={state}
                                    setState={setState}
                                    errors={errors as Record<string, string>}
                                    imageClientError={imageClientError}
                                    setImageClientError={setImageClientError}
                                    mode="create"
                                />
                            </div>

                            <DialogFooter className="shrink-0 gap-2 border-t bg-background px-6 py-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => handleOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : null}
                                    Create category
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Settings sheet (gear icon)
// ─────────────────────────────────────────────────────────────────────────────

function CategorySettingsSheet({
    category,
    teamSlug,
    eventSlug,
    children,
}: {
    category: TicketCategory;
    teamSlug: string;
    eventSlug: string;
    children: React.ReactNode;
}) {
    const baseUrl = `/${teamSlug}/events/${eventSlug}/tickets/${category.uuid}`;
    const [imageClientError, setImageClientError] = useState<string | null>(
        null,
    );
    const [state, setState] = useState<CategoryFormState>(() =>
        initialFormState(category),
    );

    return (
        <Sheet>
            <SheetTrigger asChild>{children}</SheetTrigger>
            <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="shrink-0 border-b px-6 py-4">
                    <SheetTitle className="flex items-center gap-2">
                        <Settings className="size-4" />
                        {category.name}
                    </SheetTitle>
                    <SheetDescription>
                        Manage details, pricing, sale window, discounts, and
                        promo codes.
                    </SheetDescription>
                </SheetHeader>

                <Tabs
                    defaultValue="details"
                    className="flex min-h-0 flex-1 flex-col gap-0"
                >
                    <TabsList className="mx-6 mt-4 w-fit">
                        <TabsTrigger value="details">Details</TabsTrigger>
                        <TabsTrigger value="discounts">Discounts</TabsTrigger>
                        <TabsTrigger value="promo">Promo codes</TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="details"
                        className="mt-0 min-h-0 flex-1 overflow-y-auto px-6 py-4"
                    >
                        <Form
                            action={baseUrl}
                            method="patch"
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <CategoryFormFields
                                        state={state}
                                        setState={setState}
                                        errors={
                                            errors as Record<string, string>
                                        }
                                        imageClientError={imageClientError}
                                        setImageClientError={
                                            setImageClientError
                                        }
                                        showImage={false}
                                        mode="edit"
                                    />
                                    <div className="flex justify-end gap-2 border-t pt-4">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : null}
                                            Save changes
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </TabsContent>

                    <TabsContent
                        value="discounts"
                        className="mt-0 min-h-0 flex-1 overflow-y-auto px-6 py-4"
                    >
                        <DiscountManager
                            category={category}
                            baseUrl={baseUrl}
                        />
                    </TabsContent>

                    <TabsContent
                        value="promo"
                        className="mt-0 min-h-0 flex-1 overflow-y-auto px-6 py-4"
                    >
                        <PromoCodeManager
                            category={category}
                            baseUrl={baseUrl}
                        />
                    </TabsContent>
                </Tabs>
            </SheetContent>
        </Sheet>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Discount + Promo managers
// ─────────────────────────────────────────────────────────────────────────────

function DiscountManager({
    category,
    baseUrl,
}: {
    category: TicketCategory;
    baseUrl: string;
}) {
    const [showForm, setShowForm] = useState(false);
    const symbol = findCurrency(category.base_currency)?.symbol ?? '';

    return (
        <div className="space-y-3">
            {category.discounts.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No discounts yet. Add one to apply a fixed amount or
                    percentage off the base price.
                </p>
            ) : (
                <div className="space-y-2">
                    {category.discounts.map((d) => (
                        <div
                            key={d.id}
                            className="flex items-start justify-between gap-2 rounded-md border bg-muted/30 p-3"
                        >
                            <div className="min-w-0 space-y-0.5">
                                <p className="truncate text-sm font-medium">
                                    {d.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {d.type === 'percentage'
                                        ? `${d.value}% off`
                                        : `${symbol}${d.value.toFixed(2)} off`}
                                    {d.max_uses
                                        ? ` · Up to ${d.max_uses} uses`
                                        : ''}
                                    {d.ends_at
                                        ? ` · Ends ${new Date(d.ends_at).toLocaleDateString()}`
                                        : ''}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {showForm ? (
                <DiscountForm
                    baseUrl={baseUrl}
                    onDone={() => setShowForm(false)}
                />
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowForm(true)}
                    className="w-full"
                >
                    <Plus className="size-4" />
                    Add discount
                </Button>
            )}
        </div>
    );
}

function DiscountForm({
    baseUrl,
    onDone,
}: {
    baseUrl: string;
    onDone: () => void;
}) {
    const [type, setType] = useState<'fixed' | 'percentage'>('percentage');

    return (
        <Form
            action={`${baseUrl}/discounts`}
            method="post"
            className="space-y-3 rounded-md border bg-card p-3"
            onSuccess={onDone}
        >
            {({ errors, processing }) => (
                <>
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium">New discount</p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            onClick={onDone}
                        >
                            <X className="size-3.5" />
                        </Button>
                    </div>
                    <div className="grid gap-1.5">
                        <Label className="text-xs">Name</Label>
                        <Input
                            name="name"
                            maxLength={80}
                            required
                            placeholder="Early bird"
                            aria-invalid={!!errors.name}
                        />
                    </div>
                    <div className="grid grid-cols-[1fr_120px] gap-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Value</Label>
                            <Input
                                name="value"
                                type="number"
                                min={0}
                                step={0.01}
                                required
                                aria-invalid={!!errors.value}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Type</Label>
                            <input type="hidden" name="type" value={type} />
                            <Select
                                value={type}
                                onValueChange={(v) =>
                                    setType(v as 'fixed' | 'percentage')
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage">
                                        % off
                                    </SelectItem>
                                    <SelectItem value="fixed">Fixed</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Max uses</Label>
                            <Input
                                name="max_uses"
                                type="number"
                                min={1}
                                placeholder="∞"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Starts</Label>
                            <Input name="starts_at" type="datetime-local" />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Ends</Label>
                            <Input name="ends_at" type="datetime-local" />
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" size="sm" disabled={processing}>
                            Save discount
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function PromoCodeManager({
    category,
    baseUrl,
}: {
    category: TicketCategory;
    baseUrl: string;
}) {
    const [showForm, setShowForm] = useState(false);
    const symbol = findCurrency(category.base_currency)?.symbol ?? '';

    return (
        <div className="space-y-3">
            {category.promo_codes.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No promo codes yet. Buyers enter codes at checkout to redeem
                    discounts.
                </p>
            ) : (
                <div className="space-y-2">
                    {category.promo_codes.map((p) => (
                        <div
                            key={p.id}
                            className="flex items-start justify-between gap-2 rounded-md border bg-muted/30 p-3"
                        >
                            <div className="min-w-0 space-y-0.5">
                                <p className="font-mono text-sm font-semibold">
                                    {p.code}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {p.type === 'percentage'
                                        ? `${p.value}% off`
                                        : `${symbol}${p.value.toFixed(2)} off`}
                                    {p.max_uses
                                        ? ` · Up to ${p.max_uses} uses`
                                        : ''}
                                    {p.ends_at
                                        ? ` · Expires ${new Date(p.ends_at).toLocaleDateString()}`
                                        : ''}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {showForm ? (
                <PromoCodeForm
                    baseUrl={baseUrl}
                    onDone={() => setShowForm(false)}
                />
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setShowForm(true)}
                    className="w-full"
                >
                    <Plus className="size-4" />
                    Add promo code
                </Button>
            )}
        </div>
    );
}

function PromoCodeForm({
    baseUrl,
    onDone,
}: {
    baseUrl: string;
    onDone: () => void;
}) {
    const [type, setType] = useState<'fixed' | 'percentage'>('percentage');

    return (
        <Form
            action={`${baseUrl}/promo-codes`}
            method="post"
            className="space-y-3 rounded-md border bg-card p-3"
            onSuccess={onDone}
        >
            {({ errors, processing }) => (
                <>
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-medium">New promo code</p>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-6"
                            onClick={onDone}
                        >
                            <X className="size-3.5" />
                        </Button>
                    </div>
                    <div className="grid gap-1.5">
                        <Label className="text-xs">Code</Label>
                        <Input
                            name="code"
                            maxLength={32}
                            required
                            placeholder="EARLYBIRD"
                            className="font-mono uppercase"
                            aria-invalid={!!errors.code}
                        />
                    </div>
                    <div className="grid grid-cols-[1fr_120px] gap-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Value</Label>
                            <Input
                                name="value"
                                type="number"
                                min={0}
                                step={0.01}
                                required
                                aria-invalid={!!errors.value}
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Type</Label>
                            <input type="hidden" name="type" value={type} />
                            <Select
                                value={type}
                                onValueChange={(v) =>
                                    setType(v as 'fixed' | 'percentage')
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage">
                                        % off
                                    </SelectItem>
                                    <SelectItem value="fixed">Fixed</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Max uses</Label>
                            <Input
                                name="max_uses"
                                type="number"
                                min={1}
                                placeholder="∞"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Starts</Label>
                            <Input name="starts_at" type="datetime-local" />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Ends</Label>
                            <Input name="ends_at" type="datetime-local" />
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" size="sm" disabled={processing}>
                            Save promo code
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Category row
// ─────────────────────────────────────────────────────────────────────────────

function CategoryRow({
    category,
    teamSlug,
    eventSlug,
    pollStatus,
}: {
    category: TicketCategory;
    teamSlug: string;
    eventSlug: string;
    pollStatus?: { status: GenerationStatus; progress: number | null };
}) {
    const baseUrl = `/${teamSlug}/events/${eventSlug}/tickets/${category.uuid}`;
    const genStatus = pollStatus?.status ?? category.generation_status;
    const genProgress = pollStatus?.progress ?? category.generation_progress;
    const isGenerating = genStatus === 'processing' || genStatus === 'pending';

    const changeSaleStatus = (status: string) =>
        router.patch(
            `${baseUrl}/sale-status`,
            { sale_status: status },
            { preserveScroll: true },
        );

    const remove = () => {
        if (
            !window.confirm(
                `Delete "${category.name}"? This permanently removes all tickets in this category.`,
            )
        ) {
            return;
        }

        router.delete(baseUrl, { preserveScroll: true });
    };

    return (
        <div className="space-y-2 rounded-md border bg-card p-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-1.5 text-sm font-medium">
                        <span className="truncate">{category.name}</span>
                        <Badge
                            className={cn(
                                'rounded-full border text-xs font-normal',
                                SALE_STATUS_COLORS[
                                    category.sale_status.value
                                ] ?? 'bg-muted',
                            )}
                        >
                            {category.sale_status.label}
                        </Badge>
                        {genStatus ? (
                            <Badge
                                className={cn(
                                    'rounded-full border text-xs font-normal',
                                    GEN_STATUS_COLORS[genStatus],
                                )}
                            >
                                {isGenerating ? (
                                    <Loader2 className="mr-1 size-2.5 animate-spin" />
                                ) : null}
                                {genStatus}
                            </Badge>
                        ) : null}
                        {!category.is_visible ? (
                            <Badge variant="outline" className="text-xs">
                                <EyeOff className="size-3" />
                                Hidden
                            </Badge>
                        ) : null}
                    </div>
                    <div className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">
                            {formatPrice(
                                category.base_price,
                                category.base_currency,
                            )}
                        </span>
                        {category.admission_type ? (
                            <span>{category.admission_type.label}</span>
                        ) : null}
                        {category.offline_quantity > 0 ? (
                            <span>{category.offline_quantity} offline</span>
                        ) : null}
                        {category.online_quantity > 0 ? (
                            <span>{category.online_quantity} online</span>
                        ) : null}
                        {category.discounts.length > 0 ? (
                            <span className="inline-flex items-center gap-0.5">
                                <Percent className="size-3" />
                                {category.discounts.length}
                            </span>
                        ) : null}
                        {category.promo_codes.length > 0 ? (
                            <span className="inline-flex items-center gap-0.5">
                                <Tag className="size-3" />
                                {category.promo_codes.length}
                            </span>
                        ) : null}
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-1">
                    {category.sale_status.value === 'active' ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            title="Pause sales"
                            onClick={() => changeSaleStatus('paused')}
                        >
                            <Pause className="size-3.5" />
                        </Button>
                    ) : (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            title="Resume sales"
                            onClick={() => changeSaleStatus('active')}
                        >
                            <Play className="size-3.5" />
                        </Button>
                    )}

                    <CategorySettingsSheet
                        category={category}
                        teamSlug={teamSlug}
                        eventSlug={eventSlug}
                    >
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            title="Settings"
                        >
                            <Settings className="size-3.5" />
                        </Button>
                    </CategorySettingsSheet>

                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7 text-destructive hover:text-destructive"
                        title="Delete"
                        onClick={remove}
                    >
                        <Trash2 className="size-3.5" />
                    </Button>
                </div>
            </div>

            {isGenerating && genProgress != null ? (
                <div className="space-y-1">
                    <Progress value={genProgress} className="h-1.5" />
                    <p className="text-xs text-muted-foreground">
                        Generating tickets… {genProgress}%
                    </p>
                </div>
            ) : null}
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main export
// ─────────────────────────────────────────────────────────────────────────────

export function EventTicketManager({
    categories: initialCategories,
    teamSlug,
    eventSlug,
}: {
    categories: TicketCategory[];
    teamSlug: string;
    eventSlug: string;
}) {
    const pollStatuses = useGenerationPoller(
        teamSlug,
        eventSlug,
        initialCategories,
    );

    return (
        <Card>
            <CardHeader>
                <SectionTitle
                    icon={<Ticket className="size-4" />}
                    count={initialCategories.length}
                    actions={
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            title="Refresh"
                            onClick={() =>
                                router.reload({
                                    only: ['event'],
                                    preserveScroll: true,
                                })
                            }
                        >
                            <RefreshCw className="size-3.5" />
                        </Button>
                    }
                >
                    Tickets
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {initialCategories.length === 0 ? (
                    <EmptyState
                        tone="primary"
                        icon={<Ticket className="size-6" />}
                        title="No ticket categories yet"
                        description="Add a category (e.g. General Admission, VIP) to start selling."
                    />
                ) : (
                    initialCategories.map((cat) => (
                        <CategoryRow
                            key={cat.uuid}
                            category={cat}
                            teamSlug={teamSlug}
                            eventSlug={eventSlug}
                            pollStatus={pollStatuses[cat.uuid]}
                        />
                    ))
                )}
                <CreateCategoryDialog
                    teamSlug={teamSlug}
                    eventSlug={eventSlug}
                />
            </CardContent>
        </Card>
    );
}
