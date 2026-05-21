import { Form, Head } from '@inertiajs/react';
import {
    Building2,
    Facebook,
    Globe,
    Image as ImageIcon,
    Instagram,
    Linkedin,
    Loader2,
    Mail,
    MapPin,
    Star,
    Twitter,
    Youtube,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import ImageDropzone from '@/components/image-dropzone';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { EmailField } from '@/components/ui/email-field';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { PhoneField } from '@/components/ui/phone-field';
import { SectionTitle } from '@/components/ui/section-title';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type OrganizationPayload = {
    id: number;
    uuid: string;
    name: string;
    brand_name: string | null;
    slug: string;
    tagline: string | null;
    description: string | null;
    organizer_type: { value: string; label: string } | null;
    logo_url: string | null;
    banner_url: string | null;
    contact_email: string | null;
    support_email: string | null;
    contact_phone: string | null;
    website_url: string | null;
    social_links: Record<string, string>;
    address: {
        line_1: string | null;
        line_2: string | null;
        city: string | null;
        region: string | null;
        country_code: string | null;
        postal_code: string | null;
        latitude: number | null;
        longitude: number | null;
    };
    founded_year: number | null;
    is_verified: boolean;
    followers_count: number;
    tax_id: string | null;
    default_currency: string | null;
    default_timezone: string | null;
};

type TypeOptionGroup = {
    group: string;
    options: { value: string; label: string }[];
};

const NO_TYPE = '__none__';

export default function OrganizationEdit({
    organization,
    organizerTypes,
}: {
    organization: OrganizationPayload;
    organizerTypes: TypeOptionGroup[];
}) {
    // POSTs to the settings-scoped route; the active org is implicit
    // from the viewer's session (current_organization_id), so the URL
    // carries no slug.
    const action = '/settings/organization';

    const [organizerType, setOrganizerType] = useState<string>(
        organization.organizer_type?.value ?? NO_TYPE,
    );
    const [tagline, setTagline] = useState(organization.tagline ?? '');
    const taglineLen = tagline.length;
    const taglineValid = taglineLen === 0 || (taglineLen >= 15 && taglineLen <= 190);

    // Dirty-state tracking. Any input/change event inside the form
    // flips this true; a successful save flips it back. Drives the
    // Save button's disabled state so users can't re-submit an
    // unchanged form.
    const [isDirty, setIsDirty] = useState(false);

    return (
        <>
            <Head title="Organization settings" />
            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Organization profile"
                        description="The public face of your organization on the platform. Attendees see this on event pages and on your /o/ public profile."
                    />
                    <div className="flex items-center gap-2">
                        {organization.is_verified ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                <Star className="size-3.5 fill-current" />
                                Verified
                            </span>
                        ) : null}
                    </div>
                </div>

                <Form
                    action={action}
                    method="post"
                    className="space-y-6"
                    onChange={() => setIsDirty(true)}
                    onSuccess={() => setIsDirty(false)}
                    onError={() =>
                        toast.error(
                            'We couldn’t save your changes. Check the highlighted fields and try again.',
                        )
                    }
                >
                    {({ errors, processing }) => (
                        <div className="grid gap-6 lg:grid-cols-12">
                            <div className="space-y-6 lg:col-span-8">
                                <Section
                                    icon={<Building2 className="size-4" />}
                                    title="Basics"
                                    description="Identity that shows on every event card and the organizer profile page."
                                >
                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="org-name"
                                            error={errors.name}
                                        >
                                            Organization name{' '}
                                            <span className="text-xs font-normal text-muted-foreground">
                                                (formally registered)
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id="org-name"
                                            name="name"
                                            defaultValue={organization.name}
                                            required
                                            maxLength={120}
                                            placeholder="InnFuture Technologies Private Limited"
                                            aria-invalid={!!errors.name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="org-brand"
                                            error={errors.brand_name}
                                            optional
                                        >
                                            Brand name
                                        </FieldLabel>
                                        <Input
                                            id="org-brand"
                                            name="brand_name"
                                            defaultValue={
                                                organization.brand_name ?? ''
                                            }
                                            maxLength={120}
                                            placeholder="263tickets"
                                            aria-invalid={!!errors.brand_name}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            The customer-facing trading name
                                            shown on event listings and your
                                            public profile. Leave blank to use
                                            your organization name.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="org-type"
                                            error={errors.organizer_type}
                                        >
                                            Organization type
                                        </FieldLabel>
                                        <input
                                            type="hidden"
                                            name="organizer_type"
                                            value={organizerType === NO_TYPE ? '' : organizerType}
                                        />
                                        <Select
                                            value={organizerType}
                                            onValueChange={setOrganizerType}
                                        >
                                            <SelectTrigger
                                                id="org-type"
                                                aria-invalid={!!errors.organizer_type}
                                            >
                                                <SelectValue placeholder="Pick a category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NO_TYPE}>
                                                    Uncategorised
                                                </SelectItem>
                                                {organizerTypes.map((group) => (
                                                    <SelectGroup key={group.group}>
                                                        <SelectLabel>
                                                            {group.group}
                                                        </SelectLabel>
                                                        {group.options.map((o) => (
                                                            <SelectItem
                                                                key={o.value}
                                                                value={o.value}
                                                            >
                                                                {o.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <FieldLabel
                                                htmlFor="org-tagline"
                                                error={errors.tagline}
                                            >
                                                Tagline{' '}
                                                <span className="text-xs font-normal text-muted-foreground">
                                                    (15–190 characters)
                                                </span>
                                            </FieldLabel>
                                            <span
                                                className={
                                                    taglineLen > 0 && !taglineValid
                                                        ? 'text-xs text-destructive'
                                                        : 'text-xs text-muted-foreground'
                                                }
                                            >
                                                {taglineLen}/190
                                            </span>
                                        </div>
                                        <Textarea
                                            id="org-tagline"
                                            name="tagline"
                                            value={tagline}
                                            onChange={(e) => setTagline(e.target.value)}
                                            rows={2}
                                            maxLength={200}
                                            placeholder="One or two sentences attendees see on the organizer card."
                                            aria-invalid={!!errors.tagline}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="org-description"
                                            optional
                                        >
                                            Long description
                                        </FieldLabel>
                                        <Textarea
                                            id="org-description"
                                            name="description"
                                            defaultValue={organization.description ?? ''}
                                            rows={6}
                                            maxLength={5000}
                                            placeholder="Tell attendees about your work, the kind of events you produce, and what they can expect."
                                        />
                                    </div>
                                </Section>

                                <Section
                                    icon={<ImageIcon className="size-4" />}
                                    title="Brand assets"
                                    description="Square logo (preferred SVG/PNG) and a 5:2 banner for the public profile."
                                >
                                    {/*
                                      Row layout: the banner's aspect-5/2
                                      ratio drives the row's intrinsic
                                      height. `items-stretch` (the grid
                                      default) makes the logo cell match
                                      that height, and the logo dropzone
                                      uses `h-full aspect-square` so it
                                      fills the cell vertically and is
                                      always a square at the banner's
                                      height — no JS, no fixed pixel
                                      values.
                                    */}
                                    <div className="grid gap-6 sm:grid-cols-[auto_minmax(0,1fr)]">
                                        <div className="flex flex-col gap-2">
                                            <FieldLabel error={errors.logo}>
                                                Logo
                                            </FieldLabel>
                                            <ImageDropzone
                                                name="logo"
                                                existingUrl={
                                                    organization.logo_url
                                                }
                                                aspectClassName="aspect-square h-full"
                                                objectFit="contain"
                                                imagePaddingClassName="p-2"
                                                removeFieldName="remove_logo"
                                                altText="Organization logo"
                                                hasError={!!errors.logo}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-2">
                                            <FieldLabel error={errors.banner}>
                                                Profile banner
                                            </FieldLabel>
                                            <ImageDropzone
                                                name="banner"
                                                existingUrl={
                                                    organization.banner_url
                                                }
                                                aspectClassName="aspect-5/2"
                                                objectFit="cover"
                                                removeFieldName="remove_banner"
                                                altText="Organization profile banner"
                                                hasError={!!errors.banner}
                                            />
                                        </div>
                                    </div>
                                </Section>

                                <Section
                                    icon={<Mail className="size-4" />}
                                    title="Contact"
                                    description="How attendees and partners reach you."
                                >
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <EmailField
                                            id="org-contact-email"
                                            name="contact_email"
                                            label="Contact email"
                                            defaultValue={
                                                organization.contact_email
                                            }
                                            error={errors.contact_email}
                                        />
                                        <EmailField
                                            id="org-support-email"
                                            name="support_email"
                                            label="Attendee support email"
                                            optional
                                            defaultValue={
                                                organization.support_email
                                            }
                                            placeholder="Falls back to contact email"
                                            error={errors.support_email}
                                        />
                                        <PhoneField
                                            id="org-phone"
                                            name="contact_phone"
                                            label="Phone"
                                            optional
                                            defaultCountry={
                                                organization.address.country_code?.toLowerCase() ??
                                                'us'
                                            }
                                            defaultValue={
                                                organization.contact_phone
                                            }
                                            error={errors.contact_phone}
                                        />
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="org-website"
                                                error={errors.website_url}
                                                optional
                                            >
                                                Website
                                            </FieldLabel>
                                            <div className="relative">
                                                <Globe className="pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    id="org-website"
                                                    name="website_url"
                                                    type="url"
                                                    defaultValue={organization.website_url ?? ''}
                                                    className="pl-8"
                                                    placeholder="https://example.com"
                                                    aria-invalid={!!errors.website_url}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </Section>

                                <Section
                                    icon={<Twitter className="size-4" />}
                                    title="Social profiles"
                                    description="Each link surfaces as an icon on the public profile page. Leave blank to hide."
                                >
                                    <SocialField
                                        icon={<Twitter className="size-4" />}
                                        name="twitter_url"
                                        label="Twitter / X"
                                        defaultValue={organization.social_links.twitter}
                                        error={errors.twitter_url}
                                        placeholder="https://x.com/your-org"
                                    />
                                    <SocialField
                                        icon={<Instagram className="size-4" />}
                                        name="instagram_url"
                                        label="Instagram"
                                        defaultValue={organization.social_links.instagram}
                                        error={errors.instagram_url}
                                        placeholder="https://instagram.com/your-org"
                                    />
                                    <SocialField
                                        icon={<Facebook className="size-4" />}
                                        name="facebook_url"
                                        label="Facebook"
                                        defaultValue={organization.social_links.facebook}
                                        error={errors.facebook_url}
                                        placeholder="https://facebook.com/your-org"
                                    />
                                    <SocialField
                                        icon={<Linkedin className="size-4" />}
                                        name="linkedin_url"
                                        label="LinkedIn"
                                        defaultValue={organization.social_links.linkedin}
                                        error={errors.linkedin_url}
                                        placeholder="https://linkedin.com/company/your-org"
                                    />
                                    <SocialField
                                        icon={<Youtube className="size-4" />}
                                        name="youtube_url"
                                        label="YouTube"
                                        defaultValue={organization.social_links.youtube}
                                        error={errors.youtube_url}
                                    />
                                    <SocialField
                                        icon={null}
                                        name="tiktok_url"
                                        label="TikTok"
                                        defaultValue={organization.social_links.tiktok}
                                        error={errors.tiktok_url}
                                    />
                                </Section>

                                <Section
                                    icon={<MapPin className="size-4" />}
                                    title="Physical address"
                                    description="Used on the public profile and (optionally) for tax / invoicing."
                                >
                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="org-line1"
                                            error={errors.address_line_1}
                                            optional
                                        >
                                            Address line 1
                                        </FieldLabel>
                                        <Input
                                            id="org-line1"
                                            name="address_line_1"
                                            defaultValue={organization.address.line_1 ?? ''}
                                            aria-invalid={!!errors.address_line_1}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <FieldLabel
                                            htmlFor="org-line2"
                                            error={errors.address_line_2}
                                            optional
                                        >
                                            Address line 2
                                        </FieldLabel>
                                        <Input
                                            id="org-line2"
                                            name="address_line_2"
                                            defaultValue={organization.address.line_2 ?? ''}
                                        />
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="org-city"
                                                error={errors.city}
                                                optional
                                            >
                                                City
                                            </FieldLabel>
                                            <Input
                                                id="org-city"
                                                name="city"
                                                defaultValue={organization.address.city ?? ''}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="org-region"
                                                error={errors.region}
                                                optional
                                            >
                                                Region
                                            </FieldLabel>
                                            <Input
                                                id="org-region"
                                                name="region"
                                                defaultValue={organization.address.region ?? ''}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="org-postal"
                                                error={errors.postal_code}
                                                optional
                                            >
                                                Postal code
                                            </FieldLabel>
                                            <Input
                                                id="org-postal"
                                                name="postal_code"
                                                defaultValue={organization.address.postal_code ?? ''}
                                            />
                                        </div>
                                    </div>
                                    <div className="grid gap-2 sm:max-w-32">
                                        <FieldLabel
                                            htmlFor="org-country"
                                            error={errors.country_code}
                                            optional
                                        >
                                            Country (ISO-2)
                                        </FieldLabel>
                                        <Input
                                            id="org-country"
                                            name="country_code"
                                            maxLength={2}
                                            defaultValue={organization.address.country_code ?? ''}
                                            className="uppercase"
                                            aria-invalid={!!errors.country_code}
                                        />
                                    </div>
                                </Section>
                            </div>

                            <aside className="space-y-4 lg:sticky lg:top-4 lg:col-span-4 lg:self-start">
                                <Card>
                                    <CardHeader>
                                        <SectionTitle>Business</SectionTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="org-tax"
                                                error={errors.tax_id}
                                                optional
                                            >
                                                Tax ID / business reg.
                                            </FieldLabel>
                                            <Input
                                                id="org-tax"
                                                name="tax_id"
                                                defaultValue={organization.tax_id ?? ''}
                                                placeholder="VAT / EIN / etc."
                                            />
                                        </div>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            <div className="grid gap-1">
                                                <FieldLabel
                                                    htmlFor="org-currency"
                                                    error={errors.default_currency}
                                                >
                                                    Default currency
                                                </FieldLabel>
                                                <Input
                                                    id="org-currency"
                                                    name="default_currency"
                                                    maxLength={3}
                                                    defaultValue={organization.default_currency ?? ''}
                                                    className="uppercase"
                                                    placeholder="USD"
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <FieldLabel
                                                    htmlFor="org-founded"
                                                    error={errors.founded_year}
                                                >
                                                    Founded
                                                </FieldLabel>
                                                <Input
                                                    id="org-founded"
                                                    name="founded_year"
                                                    type="number"
                                                    min={1800}
                                                    max={new Date().getFullYear()}
                                                    defaultValue={organization.founded_year ?? ''}
                                                />
                                            </div>
                                        </div>
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="org-tz"
                                                error={errors.default_timezone}
                                                optional
                                            >
                                                Default timezone
                                            </FieldLabel>
                                            <Input
                                                id="org-tz"
                                                name="default_timezone"
                                                defaultValue={organization.default_timezone ?? ''}
                                                placeholder="Africa/Harare"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="flex flex-col gap-2 px-6">
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !taglineValid ||
                                                !isDirty
                                            }
                                            className="w-full"
                                        >
                                            {processing ? (
                                                <>
                                                    <Loader2 className="size-4 animate-spin" />
                                                    Saving…
                                                </>
                                            ) : (
                                                'Save profile'
                                            )}
                                        </Button>
                                        <p className="text-center text-xs text-muted-foreground">
                                            {organization.followers_count.toLocaleString()}{' '}
                                            followers
                                        </p>
                                    </CardContent>
                                </Card>
                            </aside>
                        </div>
                    )}
                </Form>
            </div>
        </>
    );
}

OrganizationEdit.layout = {
    breadcrumbs: [
        { title: 'Settings', href: '/settings' },
        { title: 'Organization', href: '/settings/organization' },
    ],
};

function Section({
    icon,
    title,
    description,
    children,
}: {
    icon: React.ReactNode;
    title: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <SectionTitle icon={icon}>{title}</SectionTitle>
                {description ? (
                    <p className="text-sm text-muted-foreground">{description}</p>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">{children}</CardContent>
        </Card>
    );
}

function SocialField({
    icon,
    name,
    label,
    defaultValue,
    error,
    placeholder,
}: {
    icon: React.ReactNode | null;
    name: string;
    label: string;
    defaultValue?: string;
    error?: string;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-2">
            <FieldLabel htmlFor={`org-${name}`} error={error}>
                {label}
            </FieldLabel>
            <div className="relative">
                {icon ? (
                    <span className="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-muted-foreground">
                        {icon}
                    </span>
                ) : null}
                <Input
                    id={`org-${name}`}
                    name={name}
                    type="url"
                    defaultValue={defaultValue ?? ''}
                    className={icon ? 'pl-8' : ''}
                    placeholder={placeholder}
                    aria-invalid={!!error}
                />
            </div>
        </div>
    );
}
