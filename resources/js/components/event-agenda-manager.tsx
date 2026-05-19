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
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { cn } from '@/lib/utils';

export type AgendaDraft = {
    key: string;
    id: number | null;
    starts_at: string;
    ends_at: string;
    title: string;
    description: string;
    host_name: string;
    host_role: string;
};

const HOST_ROLES = [
    'Speaker',
    'Performer',
    'Artist',
    'Guide',
    'Host',
    'Panelist',
    'Moderator',
];

const NONE_ROLE = '__none__';

function newDraft(): AgendaDraft {
    return {
        key: `new-${Math.random().toString(36).slice(2, 10)}`,
        id: null,
        starts_at: '',
        ends_at: '',
        title: '',
        description: '',
        host_name: '',
        host_role: '',
    };
}

function isoToLocalInput(iso: string | null, timezone: string): string {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date);
    const part = (type: string) =>
        parts.find((p) => p.type === type)?.value ?? '00';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

export function EventAgendaManager({
    initial,
    timezone,
}: {
    initial: Array<{
        id: number;
        starts_at: string | null;
        ends_at: string | null;
        title: string;
        description: string | null;
        host_name: string | null;
        host_role: string | null;
    }>;
    timezone: string;
}) {
    const [entries, setEntries] = useState<AgendaDraft[]>(
        initial.map((e) => ({
            key: `existing-${e.id}`,
            id: e.id,
            starts_at: isoToLocalInput(e.starts_at, timezone),
            ends_at: isoToLocalInput(e.ends_at, timezone),
            title: e.title,
            description: e.description ?? '',
            host_name: e.host_name ?? '',
            host_role: e.host_role ?? '',
        })),
    );

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const add = () => setEntries((prev) => [...prev, newDraft()]);

    const remove = (key: string) =>
        setEntries((prev) => prev.filter((e) => e.key !== key));

    const update = <K extends keyof AgendaDraft>(
        key: string,
        field: K,
        value: AgendaDraft[K],
    ) => {
        setEntries((prev) =>
            prev.map((e) => (e.key === key ? { ...e, [field]: value } : e)),
        );
    };

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        setEntries((prev) => {
            const oldIndex = prev.findIndex((e) => e.key === active.id);
            const newIndex = prev.findIndex((e) => e.key === over.id);

            if (oldIndex < 0 || newIndex < 0) {
                return prev;
            }

            return arrayMove(prev, oldIndex, newIndex);
        });
    };

    return (
        <div className="space-y-3">
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No agenda items yet. Add talks, performances, breaks, or
                    other scheduled activities.
                </p>
            ) : null}

            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
            >
                <SortableContext
                    items={entries.map((e) => e.key)}
                    strategy={verticalListSortingStrategy}
                >
                    {entries.map((entry, i) => (
                        <SortableAgendaRow
                            key={entry.key}
                            entry={entry}
                            index={i}
                            onUpdate={(field, value) =>
                                update(entry.key, field, value)
                            }
                            onRemove={() => remove(entry.key)}
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
                Add agenda item
            </Button>
        </div>
    );
}

function SortableAgendaRow({
    entry,
    index,
    onUpdate,
    onRemove,
}: {
    entry: AgendaDraft;
    index: number;
    onUpdate: <K extends keyof AgendaDraft>(
        field: K,
        value: AgendaDraft[K],
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
    } = useSortable({ id: entry.key });

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
                isDragging && 'z-10 opacity-90 shadow-lg',
            )}
        >
            {entry.id ? (
                <input
                    type="hidden"
                    name={`agenda[${index}][id]`}
                    value={entry.id}
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
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Starts at</Label>
                            <Input
                                name={`agenda[${index}][starts_at]`}
                                type="datetime-local"
                                value={entry.starts_at}
                                onChange={(e) =>
                                    onUpdate('starts_at', e.target.value)
                                }
                                required
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">
                                Ends at (optional)
                            </Label>
                            <Input
                                name={`agenda[${index}][ends_at]`}
                                type="datetime-local"
                                value={entry.ends_at}
                                onChange={(e) =>
                                    onUpdate('ends_at', e.target.value)
                                }
                                min={entry.starts_at || undefined}
                            />
                        </div>
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">Title</Label>
                        <Input
                            name={`agenda[${index}][title]`}
                            value={entry.title}
                            onChange={(e) => onUpdate('title', e.target.value)}
                            placeholder="Opening keynote"
                            required
                        />
                    </div>

                    <div className="grid gap-1.5">
                        <Label className="text-xs">
                            Description (optional)
                        </Label>
                        <Textarea
                            name={`agenda[${index}][description]`}
                            value={entry.description}
                            onChange={(e) =>
                                onUpdate('description', e.target.value)
                            }
                            rows={2}
                        />
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Host / performer</Label>
                            <Input
                                name={`agenda[${index}][host_name]`}
                                value={entry.host_name}
                                onChange={(e) =>
                                    onUpdate('host_name', e.target.value)
                                }
                                placeholder="Jane Doe"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label className="text-xs">Role</Label>
                            <input
                                type="hidden"
                                name={`agenda[${index}][host_role]`}
                                value={entry.host_role}
                            />
                            <Select
                                value={entry.host_role || NONE_ROLE}
                                onValueChange={(v) =>
                                    onUpdate(
                                        'host_role',
                                        v === NONE_ROLE ? '' : v,
                                    )
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Select role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE_ROLE}>
                                        No role
                                    </SelectItem>
                                    {HOST_ROLES.map((r) => (
                                        <SelectItem key={r} value={r}>
                                            {r}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="flex justify-end">
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
