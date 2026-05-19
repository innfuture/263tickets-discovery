import { GripVertical, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type LineupDraft = {
    key: string; // local key for React list
    id: number | null;
    name: string;
    role: string;
    bio: string;
    image_path: string;
    social_url: string;
    is_headliner: boolean;
};

function newDraft(): LineupDraft {
    return {
        key: `new-${Math.random().toString(36).slice(2, 10)}`,
        id: null,
        name: '',
        role: '',
        bio: '',
        image_path: '',
        social_url: '',
        is_headliner: false,
    };
}

export function EventLineupManager({
    initial,
}: {
    initial: Array<{
        id: number;
        name: string;
        role: string | null;
        bio: string | null;
        image_path: string | null;
        social_url: string | null;
        is_headliner: boolean;
    }>;
}) {
    const [artists, setArtists] = useState<LineupDraft[]>(
        initial.map((a) => ({
            key: `existing-${a.id}`,
            id: a.id,
            name: a.name,
            role: a.role ?? '',
            bio: a.bio ?? '',
            image_path: a.image_path ?? '',
            social_url: a.social_url ?? '',
            is_headliner: a.is_headliner,
        })),
    );

    const add = () => setArtists((prev) => [...prev, newDraft()]);
    const remove = (key: string) =>
        setArtists((prev) => prev.filter((a) => a.key !== key));
    const update = <K extends keyof LineupDraft>(
        key: string,
        field: K,
        value: LineupDraft[K],
    ) => {
        setArtists((prev) =>
            prev.map((a) => (a.key === key ? { ...a, [field]: value } : a)),
        );
    };

    return (
        <div className="space-y-3">
            {artists.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No lineup added yet. Add artists, speakers, or special
                    guests.
                </p>
            ) : null}

            {artists.map((artist, i) => (
                <div
                    key={artist.key}
                    className={cn(
                        'space-y-3 rounded-md border bg-muted/20 p-4',
                        artist.is_headliner && 'ring-1 ring-yellow-400/40',
                    )}
                >
                    {artist.id ? (
                        <input
                            type="hidden"
                            name={`lineup[${i}][id]`}
                            value={artist.id}
                        />
                    ) : null}

                    <div className="flex items-start gap-2">
                        <GripVertical className="mt-2 size-4 shrink-0 text-muted-foreground" />
                        <div className="flex-1 space-y-3">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Name</Label>
                                    <Input
                                        name={`lineup[${i}][name]`}
                                        value={artist.name}
                                        onChange={(e) =>
                                            update(
                                                artist.key,
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Jane Doe"
                                        required
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">
                                        Role / billing
                                    </Label>
                                    <Input
                                        name={`lineup[${i}][role]`}
                                        value={artist.role}
                                        onChange={(e) =>
                                            update(
                                                artist.key,
                                                'role',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="DJ · Speaker · Guest"
                                    />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label className="text-xs">Bio</Label>
                                <Textarea
                                    name={`lineup[${i}][bio]`}
                                    value={artist.bio}
                                    onChange={(e) =>
                                        update(
                                            artist.key,
                                            'bio',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="A short bio shown to attendees"
                                    rows={2}
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">
                                        Photo URL / path
                                    </Label>
                                    <Input
                                        name={`lineup[${i}][image_path]`}
                                        value={artist.image_path}
                                        onChange={(e) =>
                                            update(
                                                artist.key,
                                                'image_path',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="https://..."
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">
                                        Social / profile URL
                                    </Label>
                                    <Input
                                        name={`lineup[${i}][social_url]`}
                                        value={artist.social_url}
                                        onChange={(e) =>
                                            update(
                                                artist.key,
                                                'social_url',
                                                e.target.value,
                                            )
                                        }
                                        type="url"
                                        placeholder="https://..."
                                    />
                                </div>
                            </div>

                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`lineup-${artist.key}-headliner`}
                                        checked={artist.is_headliner}
                                        onCheckedChange={(v) =>
                                            update(
                                                artist.key,
                                                'is_headliner',
                                                v === true,
                                            )
                                        }
                                    />
                                    <input
                                        type="hidden"
                                        name={`lineup[${i}][is_headliner]`}
                                        value={artist.is_headliner ? '1' : '0'}
                                    />
                                    <Label
                                        htmlFor={`lineup-${artist.key}-headliner`}
                                        className="flex items-center gap-1 font-normal"
                                    >
                                        <Star className="size-3.5 fill-yellow-400 text-yellow-400" />
                                        Headliner
                                    </Label>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => remove(artist.key)}
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="size-4" />
                                    Remove
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            ))}

            <Button
                type="button"
                variant="outline"
                onClick={add}
                className="w-full"
            >
                <Plus className="size-4" />
                Add artist / guest
            </Button>
        </div>
    );
}
