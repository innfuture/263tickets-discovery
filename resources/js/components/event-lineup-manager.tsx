import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { usePage } from '@inertiajs/react';
import { GripVertical, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { LineupPhotoUploader } from '@/components/lineup-photo-uploader';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type LineupDraft = {
    key: string;
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
    eventSlug,
    initial,
}: {
    eventSlug: string;
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
    const page = usePage<{ currentTeam?: { slug: string } | null }>();
    const teamSlug = page.props.currentTeam?.slug ?? '';
    const photoUploadUrl = `/${teamSlug}/events/${eventSlug}/lineup/photo`;

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

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
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

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setArtists((prev) => {
            const oldIndex = prev.findIndex((a) => a.key === active.id);
            const newIndex = prev.findIndex((a) => a.key === over.id);

            if (oldIndex < 0 || newIndex < 0) {
                return prev;
            }

            return arrayMove(prev, oldIndex, newIndex);
        });
    };

    return (
        <div className="space-y-3">
            {artists.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No lineup added yet. Add artists, speakers, or special
                    guests.
                </p>
            ) : null}

            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext
                    items={artists.map((a) => a.key)}
                    strategy={verticalListSortingStrategy}
                >
                    {artists.map((artist, i) => (
                        <SortableArtistRow
                            key={artist.key}
                            artist={artist}
                            index={i}
                            photoUploadUrl={photoUploadUrl}
                            onUpdate={(field, value) =>
                                update(artist.key, field, value)
                            }
                            onRemove={() => remove(artist.key)}
                        />
                    ))}
                </SortableContext>
            </DndContext>

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

function SortableArtistRow({
    artist,
    index,
    photoUploadUrl,
    onUpdate,
    onRemove,
}: {
    artist: LineupDraft;
    index: number;
    photoUploadUrl: string;
    onUpdate: <K extends keyof LineupDraft>(
        field: K,
        value: LineupDraft[K],
    ) => void;
    onRemove: () => void;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: artist.key });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={cn(
                'space-y-3 rounded-md border bg-muted/20 p-4',
                artist.is_headliner && 'ring-1 ring-yellow-400/40',
                isDragging && 'z-10 opacity-90 shadow-lg',
            )}
        >
            {artist.id ? (
                <input
                    type="hidden"
                    name={`lineup[${index}][id]`}
                    value={artist.id}
                />
            ) : null}

            <div className="flex items-start gap-2">
                <button
                    type="button"
                    {...attributes}
                    {...listeners}
                    aria-label="Drag to reorder"
                    className="mt-2 cursor-grab touch-none text-muted-foreground hover:text-foreground active:cursor-grabbing"
                >
                    <GripVertical className="size-4" />
                </button>

                <div className="flex-1 space-y-3">
                    <LineupPhotoUploader
                        uploadUrl={photoUploadUrl}
                        value={artist.image_path}
                        onChange={(path) => onUpdate('image_path', path)}
                    />
                    <input
                        type="hidden"
                        name={`lineup[${index}][image_path]`}
                        value={artist.image_path}
                    />

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Name</Label>
                            <Input
                                name={`lineup[${index}][name]`}
                                value={artist.name}
                                onChange={(e) =>
                                    onUpdate('name', e.target.value)
                                }
                                placeholder="Jane Doe"
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Role / billing</Label>
                            <Input
                                name={`lineup[${index}][role]`}
                                value={artist.role}
                                onChange={(e) =>
                                    onUpdate('role', e.target.value)
                                }
                                placeholder="DJ · Speaker · Guest"
                            />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Bio</Label>
                        <Textarea
                            name={`lineup[${index}][bio]`}
                            value={artist.bio}
                            onChange={(e) => onUpdate('bio', e.target.value)}
                            rows={2}
                            placeholder="A short bio shown to attendees"
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Social / profile URL</Label>
                        <Input
                            name={`lineup[${index}][social_url]`}
                            value={artist.social_url}
                            onChange={(e) =>
                                onUpdate('social_url', e.target.value)
                            }
                            type="url"
                            placeholder="https://..."
                        />
                    </div>

                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id={`lineup-${artist.key}-headliner`}
                                checked={artist.is_headliner}
                                onCheckedChange={(v) =>
                                    onUpdate('is_headliner', v === true)
                                }
                            />
                            <input
                                type="hidden"
                                name={`lineup[${index}][is_headliner]`}
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
                            onClick={onRemove}
                            className="text-destructive hover:text-destructive"
                        >
                            <Trash2 className="size-4" />
                            Remove
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}
