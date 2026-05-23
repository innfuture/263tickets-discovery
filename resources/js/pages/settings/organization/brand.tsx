import { Form, Head, router } from '@inertiajs/react';
import { Loader2, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Breadcrumb = { title: string; href: string };

type Brand = {
    primary_color: string;
    accent_color: string;
    heading_font: string;
    body_font: string;
    email_footer: string | null;
    pdf_header_text: string | null;
    logo_light_path: string | null;
    logo_dark_path: string | null;
    logo_email_path: string | null;
    logo_light_url: string | null;
    logo_dark_url: string | null;
    logo_email_url: string | null;
};

export default function BrandPage({
    brand,
    fontOptions,
}: {
    brand: Brand;
    fontOptions: string[];
    breadcrumbs: Breadcrumb[];
}) {
    const [dirty, setDirty] = useState(false);

    return (
        <>
            <Head title="Brand kit — Settings" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Brand kit"
                    description="Logo variants, palette, fonts, and email theming. Applied to event pages, attendee emails, and PDF tickets."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Logo variants</CardTitle>
                        <CardDescription>PNG or SVG up to 4MB. Use the email variant on dark email backgrounds.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-3">
                        {(['light', 'dark', 'email'] as const).map((variant) => (
                            <LogoSlot
                                key={variant}
                                variant={variant}
                                url={brand[`logo_${variant}_url`] as string | null}
                            />
                        ))}
                    </CardContent>
                </Card>

                <Form
                    action="/settings/organization/brand"
                    method="post"
                    onChange={() => setDirty(true)}
                    onSuccess={() => setDirty(false)}
                    onError={() => toast.error("Couldn't save brand settings.")}
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Palette &amp; typography</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="primary_color" error={errors.primary_color}>Primary color</FieldLabel>
                                        <div className="flex items-center gap-2">
                                            <Input id="primary_color" name="primary_color" defaultValue={brand.primary_color} className="font-mono" />
                                            <input type="color" defaultValue={brand.primary_color} onChange={(e) => {
                                                const t = document.getElementById('primary_color') as HTMLInputElement | null;
                                                if (t) {
                                                    t.value = e.target.value;
                                                    setDirty(true);
                                                }
                                            }} className="size-10 cursor-pointer rounded border" />
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel htmlFor="accent_color" error={errors.accent_color}>Accent color</FieldLabel>
                                        <div className="flex items-center gap-2">
                                            <Input id="accent_color" name="accent_color" defaultValue={brand.accent_color} className="font-mono" />
                                            <input type="color" defaultValue={brand.accent_color} onChange={(e) => {
                                                const t = document.getElementById('accent_color') as HTMLInputElement | null;
                                                if (t) {
                                                    t.value = e.target.value;
                                                    setDirty(true);
                                                }
                                            }} className="size-10 cursor-pointer rounded border" />
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FontSelect name="heading_font" label="Heading font" defaultValue={brand.heading_font} options={fontOptions} error={errors.heading_font} />
                                    <FontSelect name="body_font" label="Body font" defaultValue={brand.body_font} options={fontOptions} error={errors.body_font} />
                                </div>

                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="email_footer" error={errors.email_footer}>Email footer</FieldLabel>
                                    <Textarea id="email_footer" name="email_footer" defaultValue={brand.email_footer ?? ''} placeholder="Plain text appended to every attendee email." rows={3} />
                                </div>

                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="pdf_header_text" error={errors.pdf_header_text}>PDF ticket header</FieldLabel>
                                    <Input id="pdf_header_text" name="pdf_header_text" defaultValue={brand.pdf_header_text ?? ''} placeholder="Shown above the QR on PDF tickets." />
                                </div>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing || !dirty}>
                                        {processing ? (<><Loader2 className="size-4 animate-spin" /> Saving…</>) : 'Save brand kit'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </>
    );
}

function FontSelect({ name, label, defaultValue, options, error }: { name: string; label: string; defaultValue: string; options: string[]; error?: string }) {
    const [value, setValue] = useState(defaultValue);
    return (
        <div className="grid gap-2">
            <FieldLabel htmlFor={name} error={error}>{label}</FieldLabel>
            <input type="hidden" name={name} value={value} />
            <Select value={value} onValueChange={setValue}>
                <SelectTrigger id={name}>
                    <SelectValue placeholder="Choose…" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => (<SelectItem key={o} value={o}>{o}</SelectItem>))}
                </SelectContent>
            </Select>
        </div>
    );
}

function LogoSlot({ variant, url }: { variant: 'light' | 'dark' | 'email'; url: string | null }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const upload = (file: File) => {
        const form = new FormData();
        form.set('variant', variant);
        form.set('logo', file);
        setUploading(true);
        router.post('/settings/organization/brand/logo', form, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
            onError: () => toast.error("Couldn't upload that image."),
        });
    };

    const remove = () => {
        router.delete(`/settings/organization/brand/logo/${variant}`, { preserveScroll: true });
    };

    return (
        <div className="rounded-md border border-dashed p-4">
            <p className="mb-2 text-xs font-medium capitalize text-muted-foreground">{variant} logo</p>
            <div className="flex h-24 items-center justify-center rounded bg-muted/30">
                {url ? <img src={url} alt={`${variant} logo`} className="max-h-20 max-w-full object-contain" /> : <span className="text-xs text-muted-foreground">no logo</span>}
            </div>
            <div className="mt-3 flex gap-2">
                <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => e.target.files?.[0] && upload(e.target.files[0])} />
                <Button size="sm" variant="outline" type="button" disabled={uploading} onClick={() => fileRef.current?.click()}>
                    {uploading ? <Loader2 className="size-3.5 animate-spin" /> : <Upload className="size-3.5" />}
                    {url ? 'Replace' : 'Upload'}
                </Button>
                {url && (
                    <Button size="sm" variant="ghost" type="button" onClick={remove}>
                        <Trash2 className="size-3.5" />
                    </Button>
                )}
            </div>
        </div>
    );
}

BrandPage.layout = ({ breadcrumbs }: { breadcrumbs: Breadcrumb[] }) => ({ breadcrumbs });
