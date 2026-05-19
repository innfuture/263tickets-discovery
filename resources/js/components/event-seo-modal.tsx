import { Form, usePage } from '@inertiajs/react';
import { Globe, Settings2 } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

export type EventSeo = {
    meta_title: string | null;
    meta_description: string | null;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_path: string | null;
    twitter_card: string | null;
    twitter_creator: string | null;
};

const TWITTER_CARD_OPTIONS = [
    { value: 'summary', label: 'Summary' },
    { value: 'summary_large_image', label: 'Summary with large image' },
    { value: 'player', label: 'Player' },
    { value: 'app', label: 'App' },
];

function FieldRow({
    htmlFor,
    error,
    label,
    hint,
    children,
}: {
    htmlFor?: string;
    error?: string | null;
    label: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label htmlFor={htmlFor}>{label}</Label>
                <FieldError message={error} />
            </div>
            {children}
            {hint ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}

export function EventSeoModal({
    seo,
    eventName,
    eventDescription,
    eventSlug,
    children,
}: PropsWithChildren<{
    seo: EventSeo;
    eventName: string;
    eventDescription: string | null;
    eventSlug: string;
}>) {
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const teamSlug = page.props.currentTeam?.slug ?? '';
    const action = `/${teamSlug}/events/${eventSlug}/seo`;

    const [open, setOpen] = useState(false);
    const [twitterCard, setTwitterCard] = useState<string>(
        seo.twitter_card ?? '',
    );

    const previewTitle = seo.meta_title || eventName;
    const previewDescription =
        seo.meta_description ||
        (eventDescription ? stripHtml(eventDescription).slice(0, 160) : '');
    const previewUrl = seo.canonical_url || `https://example.com/${eventSlug}`;

    return (
        <Dialog
            open={open}
            onOpenChange={(o) => {
                setOpen(o);
                if (!o) {
                    setTwitterCard(seo.twitter_card ?? '');
                }
            }}
        >
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent className="flex max-h-[90dvh] flex-col gap-0 p-0 sm:max-w-3xl">
                <Form
                    key={String(open)}
                    action={action}
                    method="patch"
                    className="flex min-h-0 flex-1 flex-col"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader className="shrink-0 px-6 pt-6 pb-4">
                                <DialogTitle className="flex items-center gap-2">
                                    <Settings2 className="size-5" />
                                    SEO & Social Settings
                                </DialogTitle>
                                <DialogDescription>
                                    Control how this event appears in search
                                    engines and when shared on social media.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 pb-4">
                                {/* Preview */}
                                <div className="space-y-2 rounded-md border bg-muted/30 p-4">
                                    <div className="flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                        <Globe className="size-3.5" />
                                        Search preview
                                    </div>
                                    <div className="space-y-1">
                                        <p className="truncate text-xs text-green-700 dark:text-green-400">
                                            {previewUrl}
                                        </p>
                                        <p className="line-clamp-1 text-base font-medium text-blue-700 hover:underline dark:text-blue-400">
                                            {previewTitle}
                                        </p>
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {previewDescription || (
                                                <span className="italic">
                                                    No description set
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <Tabs defaultValue="serp" className="gap-4">
                                    <TabsList>
                                        <TabsTrigger value="serp">
                                            Search engines
                                        </TabsTrigger>
                                        <TabsTrigger value="og">
                                            Open Graph
                                        </TabsTrigger>
                                        <TabsTrigger value="twitter">
                                            Twitter / X
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent
                                        value="serp"
                                        className="mt-0 space-y-4"
                                    >
                                        <FieldRow
                                            htmlFor="seo-meta-title"
                                            label="Meta title"
                                            error={errors.meta_title}
                                            hint="Shown as the headline in search results. Keep under 60 characters."
                                        >
                                            <Input
                                                id="seo-meta-title"
                                                name="meta_title"
                                                defaultValue={
                                                    seo.meta_title ?? ''
                                                }
                                                maxLength={60}
                                                placeholder={eventName}
                                                aria-invalid={
                                                    !!errors.meta_title
                                                }
                                            />
                                        </FieldRow>

                                        <FieldRow
                                            htmlFor="seo-meta-description"
                                            label="Meta description"
                                            error={errors.meta_description}
                                            hint="Shown as the snippet in search results. Keep under 160 characters."
                                        >
                                            <Textarea
                                                id="seo-meta-description"
                                                name="meta_description"
                                                defaultValue={
                                                    seo.meta_description ?? ''
                                                }
                                                maxLength={160}
                                                rows={3}
                                                aria-invalid={
                                                    !!errors.meta_description
                                                }
                                            />
                                        </FieldRow>

                                        <FieldRow
                                            htmlFor="seo-canonical"
                                            label="Canonical URL"
                                            error={errors.canonical_url}
                                            hint="Override the default URL if this page is duplicated elsewhere."
                                        >
                                            <Input
                                                id="seo-canonical"
                                                name="canonical_url"
                                                type="url"
                                                defaultValue={
                                                    seo.canonical_url ?? ''
                                                }
                                                placeholder="https://"
                                                aria-invalid={
                                                    !!errors.canonical_url
                                                }
                                            />
                                        </FieldRow>
                                    </TabsContent>

                                    <TabsContent
                                        value="og"
                                        className="mt-0 space-y-4"
                                    >
                                        <FieldRow
                                            htmlFor="seo-og-title"
                                            label="OG title"
                                            error={errors.og_title}
                                            hint="Used by Facebook, LinkedIn, Slack, etc. Falls back to meta title."
                                        >
                                            <Input
                                                id="seo-og-title"
                                                name="og_title"
                                                defaultValue={
                                                    seo.og_title ?? ''
                                                }
                                                maxLength={60}
                                                aria-invalid={!!errors.og_title}
                                            />
                                        </FieldRow>

                                        <FieldRow
                                            htmlFor="seo-og-description"
                                            label="OG description"
                                            error={errors.og_description}
                                        >
                                            <Textarea
                                                id="seo-og-description"
                                                name="og_description"
                                                defaultValue={
                                                    seo.og_description ?? ''
                                                }
                                                maxLength={160}
                                                rows={3}
                                                aria-invalid={
                                                    !!errors.og_description
                                                }
                                            />
                                        </FieldRow>

                                        <FieldRow
                                            htmlFor="seo-og-image"
                                            label="OG image path / URL"
                                            error={errors.og_image_path}
                                            hint="Recommended size: 1200x630. Falls back to the event banner."
                                        >
                                            <Input
                                                id="seo-og-image"
                                                name="og_image_path"
                                                defaultValue={
                                                    seo.og_image_path ?? ''
                                                }
                                                placeholder="events/og/..."
                                                aria-invalid={
                                                    !!errors.og_image_path
                                                }
                                            />
                                        </FieldRow>
                                    </TabsContent>

                                    <TabsContent
                                        value="twitter"
                                        className="mt-0 space-y-4"
                                    >
                                        <FieldRow
                                            htmlFor="seo-twitter-card"
                                            label="Twitter card type"
                                            error={errors.twitter_card}
                                        >
                                            <input
                                                type="hidden"
                                                name="twitter_card"
                                                value={twitterCard}
                                            />
                                            <Select
                                                value={
                                                    twitterCard || '__none__'
                                                }
                                                onValueChange={(v) =>
                                                    setTwitterCard(
                                                        v === '__none__'
                                                            ? ''
                                                            : v,
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="seo-twitter-card"
                                                    className="w-full"
                                                    aria-invalid={
                                                        !!errors.twitter_card
                                                    }
                                                >
                                                    <SelectValue placeholder="Default (Summary with large image)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none__">
                                                        Use default
                                                    </SelectItem>
                                                    {TWITTER_CARD_OPTIONS.map(
                                                        (opt) => (
                                                            <SelectItem
                                                                key={opt.value}
                                                                value={
                                                                    opt.value
                                                                }
                                                            >
                                                                {opt.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </FieldRow>

                                        <FieldRow
                                            htmlFor="seo-twitter-creator"
                                            label="Twitter creator handle"
                                            error={errors.twitter_creator}
                                            hint="Your event's Twitter handle, e.g. @yourevent. The @ is added automatically."
                                        >
                                            <Input
                                                id="seo-twitter-creator"
                                                name="twitter_creator"
                                                defaultValue={
                                                    seo.twitter_creator ?? ''
                                                }
                                                placeholder="@yourevent"
                                                aria-invalid={
                                                    !!errors.twitter_creator
                                                }
                                            />
                                        </FieldRow>
                                    </TabsContent>
                                </Tabs>
                            </div>

                            <DialogFooter className="shrink-0 gap-2 border-t bg-background px-6 py-4">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save SEO'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function stripHtml(html: string): string {
    if (typeof document === 'undefined') {
        return html.replace(/<[^>]*>/g, '');
    }

    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent ?? tmp.innerText ?? '';
}
