import { Plus, Sparkles, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    AMENITY_CATEGORIES,
    AMENITY_PRESETS,
    AmenityIcon,
    listAvailableIcons,
} from '@/lib/amenity-icons';

export type AmenityInitial = {
    id: number;
    name: string;
    icon: string | null;
    description: string | null;
    category: string | null;
    is_highlighted: boolean;
};

type AmenityDraft = {
    key: string;
    id: number | null;
    name: string;
    icon: string;
    description: string;
    category: string;
    is_highlighted: boolean;
};

const NO_CATEGORY = '__none__';

function newDraft(seed?: Partial<AmenityDraft>): AmenityDraft {
    return {
        key: `new-${Math.random().toString(36).slice(2, 10)}`,
        id: null,
        name: '',
        icon: 'check',
        description: '',
        category: '',
        is_highlighted: false,
        ...seed,
    };
}

export function EventAmenitiesManager({
    initial,
}: {
    initial: AmenityInitial[];
}) {
    const [amenities, setAmenities] = useState<AmenityDraft[]>(
        initial.map((a) => ({
            key: `existing-${a.id}`,
            id: a.id,
            name: a.name,
            icon: a.icon ?? 'check',
            description: a.description ?? '',
            category: a.category ?? '',
            is_highlighted: a.is_highlighted,
        })),
    );

    const iconOptions = useMemo(() => listAvailableIcons(), []);

    const existingNames = useMemo(
        () => new Set(amenities.map((a) => a.name.toLowerCase())),
        [amenities],
    );

    const update = (key: string, patch: Partial<AmenityDraft>) => {
        setAmenities((current) =>
            current.map((a) => (a.key === key ? { ...a, ...patch } : a)),
        );
    };

    const remove = (key: string) => {
        setAmenities((current) => current.filter((a) => a.key !== key));
    };

    const addPreset = (preset: (typeof AMENITY_PRESETS)[number]) => {
        setAmenities((current) => [
            ...current,
            newDraft({
                name: preset.name,
                icon: preset.icon,
                category: preset.category,
            }),
        ]);
    };

    const addCustom = () => {
        setAmenities((current) => [...current, newDraft()]);
    };

    return (
        <div className="space-y-4">
            {/* Quick-pick palette */}
            <div className="rounded-lg border border-dashed bg-muted/20 p-3">
                <div className="mb-2 flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    <Sparkles className="size-3.5" />
                    Quick add
                </div>
                <div className="flex flex-wrap gap-1.5">
                    {AMENITY_PRESETS.map((p) => {
                        const already = existingNames.has(p.name.toLowerCase());

                        return (
                            <Button
                                key={p.name}
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={already}
                                onClick={() => addPreset(p)}
                                className="h-7 gap-1.5 text-xs"
                            >
                                <AmenityIcon
                                    name={p.icon}
                                    className="size-3.5"
                                />
                                {p.name}
                            </Button>
                        );
                    })}
                </div>
            </div>

            {amenities.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No amenities yet. Pick from the quick-add palette above or
                    add a custom one below.
                </p>
            ) : null}

            <ol className="space-y-2">
                {amenities.map((a, idx) => (
                    <li
                        key={a.key}
                        className="rounded-lg border bg-card p-3 shadow-sm"
                    >
                        {a.id !== null ? (
                            <input
                                type="hidden"
                                name={`amenities[${idx}][id]`}
                                value={a.id}
                            />
                        ) : null}
                        <input
                            type="hidden"
                            name={`amenities[${idx}][name]`}
                            value={a.name}
                        />
                        <input
                            type="hidden"
                            name={`amenities[${idx}][icon]`}
                            value={a.icon}
                        />
                        <input
                            type="hidden"
                            name={`amenities[${idx}][description]`}
                            value={a.description}
                        />
                        <input
                            type="hidden"
                            name={`amenities[${idx}][category]`}
                            value={a.category}
                        />
                        <input
                            type="hidden"
                            name={`amenities[${idx}][is_highlighted]`}
                            value={a.is_highlighted ? '1' : '0'}
                        />

                        <div className="grid gap-3 sm:grid-cols-[auto_1fr_auto] sm:items-start">
                            <Select
                                value={a.icon || 'check'}
                                onValueChange={(value) =>
                                    update(a.key, { icon: value })
                                }
                            >
                                <SelectTrigger className="w-24">
                                    <div className="flex items-center gap-1.5">
                                        <AmenityIcon
                                            name={a.icon}
                                            className="size-4 text-primary"
                                        />
                                    </div>
                                </SelectTrigger>
                                <SelectContent className="max-h-72">
                                    {iconOptions.map((key) => (
                                        <SelectItem key={key} value={key}>
                                            <div className="flex items-center gap-2">
                                                <AmenityIcon
                                                    name={key}
                                                    className="size-4"
                                                />
                                                <span className="text-xs">
                                                    {key}
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <div className="space-y-2">
                                <Input
                                    value={a.name}
                                    onChange={(e) =>
                                        update(a.key, { name: e.target.value })
                                    }
                                    placeholder="Amenity name"
                                    maxLength={80}
                                    required
                                />
                                <Textarea
                                    value={a.description}
                                    onChange={(e) =>
                                        update(a.key, {
                                            description: e.target.value,
                                        })
                                    }
                                    rows={2}
                                    maxLength={280}
                                    placeholder="Optional detail — e.g. covered, free for ticket holders, etc."
                                    className="resize-none"
                                />
                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="flex items-center gap-1.5">
                                        <Label className="text-xs">
                                            Category
                                        </Label>
                                        <Select
                                            value={a.category || NO_CATEGORY}
                                            onValueChange={(value) =>
                                                update(a.key, {
                                                    category:
                                                        value === NO_CATEGORY
                                                            ? ''
                                                            : value,
                                                })
                                            }
                                        >
                                            <SelectTrigger className="h-7 w-40 text-xs">
                                                <SelectValue placeholder="(none)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NO_CATEGORY}>
                                                    (none)
                                                </SelectItem>
                                                {AMENITY_CATEGORIES.map((c) => (
                                                    <SelectItem
                                                        key={c.value}
                                                        value={c.value}
                                                    >
                                                        {c.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <label className="flex items-center gap-1.5 text-xs">
                                        <Checkbox
                                            checked={a.is_highlighted}
                                            onCheckedChange={(v) =>
                                                update(a.key, {
                                                    is_highlighted: v === true,
                                                })
                                            }
                                        />
                                        Highlight on details page
                                    </label>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => remove(a.key)}
                                className="size-8 text-destructive hover:text-destructive"
                                title="Remove amenity"
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    </li>
                ))}
            </ol>

            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addCustom}
                className="w-full sm:w-auto"
            >
                <Plus className="size-4" />
                Add custom amenity
            </Button>
        </div>
    );
}
