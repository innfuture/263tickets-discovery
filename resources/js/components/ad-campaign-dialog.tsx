import { Form } from '@inertiajs/react';
import { Loader2, Megaphone } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { SUPPORTED_CURRENCIES } from '@/lib/currencies';

const PLATFORM_OPTIONS = [
    { value: 'google_ads', label: 'Google Ads' },
    { value: 'meta_ads', label: 'Meta Ads (Facebook / Instagram)' },
    { value: 'youtube', label: 'YouTube' },
];

const OBJECTIVE_OPTIONS = [
    { value: 'awareness', label: 'Brand Awareness' },
    { value: 'traffic', label: 'Website Traffic' },
    { value: 'engagement', label: 'Engagement' },
    { value: 'conversions', label: 'Ticket Sales / Conversions' },
    { value: 'video_views', label: 'Video Views' },
    { value: 'app_installs', label: 'App Installs' },
];

const BIDDING_OPTIONS = [
    { value: 'auto', label: 'Automatic (recommended)' },
    { value: 'cpc', label: 'Manual CPC' },
    { value: 'cpm', label: 'Manual CPM' },
    { value: 'cpv', label: 'Manual CPV (video)' },
    { value: 'roas', label: 'Target ROAS' },
];

const GENDER_OPTIONS = [
    { value: 'all', label: 'All genders' },
    { value: 'female', label: 'Women' },
    { value: 'male', label: 'Men' },
];

/**
 * Generic, scope-agnostic dialog for creating an ad campaign.
 * Pass `action` (the POST URL) so this can be reused across event / venue /
 * organisation pages.
 */
export function AdCampaignDialog({
    action,
    defaultName = '',
    defaultDestinationUrl = '',
    children,
}: {
    action: string;
    defaultName?: string;
    defaultDestinationUrl?: string;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [platform, setPlatform] = useState('google_ads');
    const [budgetCurrency, setBudgetCurrency] = useState('USD');
    const [objective, setObjective] = useState('conversions');
    const [bidding, setBidding] = useState('auto');
    const [gender, setGender] = useState('all');

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="flex max-h-[90dvh] flex-col gap-0 p-0 sm:max-w-2xl">
                <Form
                    key={String(open)}
                    action={action}
                    method="post"
                    className="flex min-h-0 flex-1 flex-col"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader className="shrink-0 px-6 pt-6 pb-4">
                                <DialogTitle className="flex items-center gap-2">
                                    <Megaphone className="size-4" />
                                    New Ad Campaign
                                </DialogTitle>
                                <DialogDescription>
                                    Configure budget, audience, and creative.
                                    Connect platform credentials in Settings to
                                    push the campaign live; otherwise it remains
                                    in Draft.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 pb-4">
                                <Section title="Campaign basics">
                                    <Field
                                        label="Campaign name"
                                        htmlFor="cmp-name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="cmp-name"
                                            name="name"
                                            maxLength={120}
                                            defaultValue={defaultName}
                                            required
                                            aria-invalid={!!errors.name}
                                        />
                                    </Field>

                                    <Field
                                        label="Platform"
                                        htmlFor="cmp-platform"
                                        error={errors.platform}
                                    >
                                        <input
                                            type="hidden"
                                            name="platform"
                                            value={platform}
                                        />
                                        <Select
                                            value={platform}
                                            onValueChange={setPlatform}
                                        >
                                            <SelectTrigger
                                                id="cmp-platform"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {PLATFORM_OPTIONS.map((p) => (
                                                    <SelectItem
                                                        key={p.value}
                                                        value={p.value}
                                                    >
                                                        {p.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        label="External ad account ID"
                                        htmlFor="cmp-ad-account"
                                        hint="From your Google Ads / Meta Business Manager. Required to push live."
                                        error={errors.external_ad_account_id}
                                    >
                                        <Input
                                            id="cmp-ad-account"
                                            name="external_ad_account_id"
                                            placeholder="e.g. 123-456-7890"
                                            maxLength={255}
                                        />
                                    </Field>
                                </Section>

                                <Section title="Budget & schedule">
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label="Daily budget"
                                            htmlFor="cmp-budget-daily"
                                            error={errors.budget_daily}
                                        >
                                            <Input
                                                id="cmp-budget-daily"
                                                name="budget_daily"
                                                type="number"
                                                min={1}
                                                step={0.01}
                                                placeholder="0.00"
                                            />
                                        </Field>
                                        <Field
                                            label="Total budget"
                                            htmlFor="cmp-budget-total"
                                            hint="Cap on lifetime spend"
                                            error={errors.budget_total}
                                        >
                                            <Input
                                                id="cmp-budget-total"
                                                name="budget_total"
                                                type="number"
                                                min={1}
                                                step={0.01}
                                                placeholder="0.00"
                                            />
                                        </Field>
                                    </div>

                                    <Field
                                        label="Currency"
                                        htmlFor="cmp-currency"
                                        error={errors.budget_currency}
                                    >
                                        <input
                                            type="hidden"
                                            name="budget_currency"
                                            value={budgetCurrency}
                                        />
                                        <Select
                                            value={budgetCurrency}
                                            onValueChange={setBudgetCurrency}
                                        >
                                            <SelectTrigger
                                                id="cmp-currency"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {SUPPORTED_CURRENCIES.map(
                                                    (c) => (
                                                        <SelectItem
                                                            key={c.code}
                                                            value={c.code}
                                                        >
                                                            {c.code} — {c.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label="Start date"
                                            htmlFor="cmp-from"
                                            error={errors.runs_from}
                                        >
                                            <Input
                                                id="cmp-from"
                                                name="runs_from"
                                                type="date"
                                            />
                                        </Field>
                                        <Field
                                            label="End date"
                                            htmlFor="cmp-until"
                                            error={errors.runs_until}
                                        >
                                            <Input
                                                id="cmp-until"
                                                name="runs_until"
                                                type="date"
                                            />
                                        </Field>
                                    </div>
                                </Section>

                                <Section title="Strategy">
                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            label="Objective"
                                            htmlFor="cmp-objective"
                                        >
                                            <input
                                                type="hidden"
                                                name="platform_config[objective]"
                                                value={objective}
                                            />
                                            <Select
                                                value={objective}
                                                onValueChange={setObjective}
                                            >
                                                <SelectTrigger
                                                    id="cmp-objective"
                                                    className="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {OBJECTIVE_OPTIONS.map(
                                                        (o) => (
                                                            <SelectItem
                                                                key={o.value}
                                                                value={o.value}
                                                            >
                                                                {o.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field
                                            label="Bidding"
                                            htmlFor="cmp-bidding"
                                        >
                                            <input
                                                type="hidden"
                                                name="platform_config[bidding_strategy]"
                                                value={bidding}
                                            />
                                            <Select
                                                value={bidding}
                                                onValueChange={setBidding}
                                            >
                                                <SelectTrigger
                                                    id="cmp-bidding"
                                                    className="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {BIDDING_OPTIONS.map(
                                                        (b) => (
                                                            <SelectItem
                                                                key={b.value}
                                                                value={b.value}
                                                            >
                                                                {b.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                    </div>
                                </Section>

                                <Section title="Audience targeting">
                                    <Field
                                        label="Locations"
                                        htmlFor="cmp-locations"
                                        hint="Comma-separated city or country names"
                                    >
                                        <Input
                                            id="cmp-locations"
                                            name="targeting[locations]"
                                            placeholder="Harare, Johannesburg, Cape Town"
                                            maxLength={500}
                                        />
                                    </Field>

                                    <div className="grid grid-cols-3 gap-3">
                                        <Field
                                            label="Min age"
                                            htmlFor="cmp-age-min"
                                        >
                                            <Input
                                                id="cmp-age-min"
                                                name="targeting[age_min]"
                                                type="number"
                                                min={13}
                                                max={65}
                                                placeholder="18"
                                            />
                                        </Field>
                                        <Field
                                            label="Max age"
                                            htmlFor="cmp-age-max"
                                        >
                                            <Input
                                                id="cmp-age-max"
                                                name="targeting[age_max]"
                                                type="number"
                                                min={13}
                                                max={65}
                                                placeholder="65"
                                            />
                                        </Field>
                                        <Field
                                            label="Gender"
                                            htmlFor="cmp-gender"
                                        >
                                            <input
                                                type="hidden"
                                                name="targeting[gender]"
                                                value={gender}
                                            />
                                            <Select
                                                value={gender}
                                                onValueChange={setGender}
                                            >
                                                <SelectTrigger
                                                    id="cmp-gender"
                                                    className="w-full"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {GENDER_OPTIONS.map((g) => (
                                                        <SelectItem
                                                            key={g.value}
                                                            value={g.value}
                                                        >
                                                            {g.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                    </div>

                                    <Field
                                        label="Interests / keywords"
                                        htmlFor="cmp-interests"
                                        hint={
                                            platform === 'google_ads'
                                                ? 'Search keywords for Google Ads'
                                                : platform === 'meta_ads'
                                                  ? 'Interest categories for Meta'
                                                  : 'Video categories for YouTube'
                                        }
                                    >
                                        <Textarea
                                            id="cmp-interests"
                                            name="targeting[interests]"
                                            rows={2}
                                            placeholder="live music, festivals, concerts"
                                            maxLength={1000}
                                        />
                                    </Field>
                                </Section>

                                <Section title="Creative">
                                    <Field
                                        label="Headline"
                                        htmlFor="cmp-headline"
                                        hint="Maximum 30 characters for Google, 40 for Meta"
                                    >
                                        <Input
                                            id="cmp-headline"
                                            name="creative_assets[headline]"
                                            maxLength={90}
                                            placeholder="Don't miss the Spring Festival"
                                        />
                                    </Field>

                                    <Field
                                        label="Body / description"
                                        htmlFor="cmp-body"
                                    >
                                        <Textarea
                                            id="cmp-body"
                                            name="creative_assets[body]"
                                            rows={2}
                                            maxLength={500}
                                            placeholder="Two days of live music. Get your tickets today."
                                        />
                                    </Field>

                                    <Field
                                        label="Destination URL"
                                        htmlFor="cmp-destination"
                                        hint="Where buyers land. Defaults to the event page."
                                    >
                                        <Input
                                            id="cmp-destination"
                                            name="creative_assets[destination_url]"
                                            type="url"
                                            defaultValue={defaultDestinationUrl}
                                            maxLength={2048}
                                        />
                                    </Field>

                                    <Field
                                        label="Creative image / video URL"
                                        htmlFor="cmp-creative-asset"
                                        hint={
                                            platform === 'youtube'
                                                ? 'YouTube video URL'
                                                : 'Image or banner URL (1200×628 recommended)'
                                        }
                                    >
                                        <Input
                                            id="cmp-creative-asset"
                                            name="creative_assets[asset_url]"
                                            type="url"
                                            maxLength={2048}
                                        />
                                    </Field>
                                </Section>
                            </div>

                            <DialogFooter className="shrink-0 gap-2 border-t bg-background px-6 py-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : null}
                                    Create campaign
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-3">
            <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            <div className="space-y-3">{children}</div>
        </div>
    );
}

function Field({
    label,
    htmlFor,
    hint,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    hint?: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={htmlFor} className="text-xs font-medium">
                {label}
            </Label>
            {children}
            {error ? (
                <p className="text-xs text-destructive">{error}</p>
            ) : hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}
