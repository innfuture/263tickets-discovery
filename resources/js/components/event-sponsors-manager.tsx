import { usePage } from '@inertiajs/react';
import { ExternalLink, Globe, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { SponsorLogoUploader } from '@/components/sponsor-logo-uploader';
import { Button } from '@/components/ui/button';
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

export type SponsorTierOption = {
    value: string;
    label: string;
    rank: number;
};

export type SponsorInitial = {
    id: number;
    name: string;
    tier: { value: string; label: string; rank: number };
    logo_path: string | null;
    logo_url: string | null;
    website_url: string | null;
    social_url: string | null;
    description: string | null;
};

type SponsorDraft = {
    key: string;
    id: number | null;
    name: string;
    tier: string;
    logo_path: string;
    website_url: string;
    social_url: string;
    description: string;
};

function newDraft(defaultTier: string): SponsorDraft {
    return {
        key: `new-${Math.random().toString(36).slice(2, 10)}`,
        id: null,
        name: '',
        tier: defaultTier,
        logo_path: '',
        website_url: '',
        social_url: '',
        description: '',
    };
}

export function EventSponsorsManager({
    eventSlug,
    initial,
    tiers,
}: {
    eventSlug: string;
    initial: SponsorInitial[];
    tiers: SponsorTierOption[];
}) {
    const page = usePage<{ currentOrganization?: { slug: string } | null }>();
    const teamSlug = page.props.currentOrganization?.slug ?? '';
    const logoUploadUrl = `/${teamSlug}/events/${eventSlug}/sponsors/logo`;

    const defaultTier =
        tiers.find((t) => t.value === 'partner')?.value ??
        tiers[0]?.value ??
        'partner';

    const [sponsors, setSponsors] = useState<SponsorDraft[]>(
        initial.map((s) => ({
            key: `existing-${s.id}`,
            id: s.id,
            name: s.name,
            tier: s.tier.value,
            logo_path: s.logo_path ?? '',
            website_url: s.website_url ?? '',
            social_url: s.social_url ?? '',
            description: s.description ?? '',
        })),
    );

    const update = (key: string, patch: Partial<SponsorDraft>) => {
        setSponsors((current) =>
            current.map((s) => (s.key === key ? { ...s, ...patch } : s)),
        );
    };

    const remove = (key: string) => {
        setSponsors((current) => current.filter((s) => s.key !== key));
    };

    const add = () => {
        setSponsors((current) => [...current, newDraft(defaultTier)]);
    };

    return (
        <div className="space-y-4">
            {sponsors.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No sponsors yet. Add your first below.
                </p>
            ) : null}

            <ol className="space-y-3">
                {sponsors.map((s, idx) => (
                    <li
                        key={s.key}
                        className="rounded-lg border bg-card p-4 shadow-sm"
                    >
                        {/* Hidden submission fields — these are the only thing
                            the server reads; the visible inputs above mirror
                            their state via React. */}
                        {s.id !== null ? (
                            <input
                                type="hidden"
                                name={`sponsors[${idx}][id]`}
                                value={s.id}
                            />
                        ) : null}
                        <input
                            type="hidden"
                            name={`sponsors[${idx}][name]`}
                            value={s.name}
                        />
                        <input
                            type="hidden"
                            name={`sponsors[${idx}][tier]`}
                            value={s.tier}
                        />
                        <input
                            type="hidden"
                            name={`sponsors[${idx}][logo_path]`}
                            value={s.logo_path}
                        />
                        <input
                            type="hidden"
                            name={`sponsors[${idx}][website_url]`}
                            value={s.website_url}
                        />
                        <input
                            type="hidden"
                            name={`sponsors[${idx}][social_url]`}
                            value={s.social_url}
                        />
                        <input
                            type="hidden"
                            name={`sponsors[${idx}][description]`}
                            value={s.description}
                        />

                        <div className="grid gap-4 sm:grid-cols-[auto_1fr]">
                            <SponsorLogoUploader
                                uploadUrl={logoUploadUrl}
                                value={s.logo_path}
                                onChange={(path) =>
                                    update(s.key, { logo_path: path })
                                }
                            />

                            <div className="space-y-3">
                                <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor={`sponsor-name-${s.key}`}
                                            className="text-xs"
                                        >
                                            Sponsor name
                                        </Label>
                                        <Input
                                            id={`sponsor-name-${s.key}`}
                                            value={s.name}
                                            onChange={(e) =>
                                                update(s.key, {
                                                    name: e.target.value,
                                                })
                                            }
                                            maxLength={160}
                                            placeholder="Acme Corp"
                                            required
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">Tier</Label>
                                        <Select
                                            value={s.tier}
                                            onValueChange={(value) =>
                                                update(s.key, { tier: value })
                                            }
                                        >
                                            <SelectTrigger className="w-44">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tiers.map((t) => (
                                                    <SelectItem
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">
                                            Website URL
                                        </Label>
                                        <div className="relative">
                                            <Globe className="pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                type="url"
                                                value={s.website_url}
                                                onChange={(e) =>
                                                    update(s.key, {
                                                        website_url:
                                                            e.target.value,
                                                    })
                                                }
                                                className="pl-8"
                                                placeholder="https://acme.com"
                                                maxLength={2048}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label className="text-xs">
                                            Social / page URL
                                        </Label>
                                        <div className="relative">
                                            <ExternalLink className="pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                type="url"
                                                value={s.social_url}
                                                onChange={(e) =>
                                                    update(s.key, {
                                                        social_url:
                                                            e.target.value,
                                                    })
                                                }
                                                className="pl-8"
                                                placeholder="https://x.com/acme"
                                                maxLength={2048}
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-1.5">
                                    <Label className="text-xs">
                                        Short tagline{' '}
                                        <span className="text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Textarea
                                        value={s.description}
                                        onChange={(e) =>
                                            update(s.key, {
                                                description: e.target.value,
                                            })
                                        }
                                        rows={2}
                                        maxLength={280}
                                        placeholder="Powering live experiences across Africa."
                                    />
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => remove(s.key)}
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Remove sponsor
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </li>
                ))}
            </ol>

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={add}
                className="w-full sm:w-auto"
            >
                <Plus className="size-4" />
                Add sponsor
            </Button>
        </div>
    );
}
