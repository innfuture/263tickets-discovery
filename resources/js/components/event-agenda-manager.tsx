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
    if (!iso) return '';
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

    return (
        <div className="space-y-3">
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground italic">
                    No agenda items yet. Add talks, performances, breaks, or
                    other scheduled activities.
                </p>
            ) : null}

            {entries.map((entry, i) => (
                <div
                    key={entry.key}
                    className="space-y-3 rounded-md border bg-muted/20 p-4"
                >
                    {entry.id ? (
                        <input
                            type="hidden"
                            name={`agenda[${i}][id]`}
                            value={entry.id}
                        />
                    ) : null}

                    <div className="flex items-start gap-2">
                        <GripVertical className="mt-2 size-4 shrink-0 text-muted-foreground" />
                        <div className="flex-1 space-y-3">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Starts at</Label>
                                    <Input
                                        name={`agenda[${i}][starts_at]`}
                                        type="datetime-local"
                                        value={entry.starts_at}
                                        onChange={(e) =>
                                            update(
                                                entry.key,
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">
                                        Ends at (optional)
                                    </Label>
                                    <Input
                                        name={`agenda[${i}][ends_at]`}
                                        type="datetime-local"
                                        value={entry.ends_at}
                                        onChange={(e) =>
                                            update(
                                                entry.key,
                                                'ends_at',
                                                e.target.value,
                                            )
                                        }
                                        min={entry.starts_at || undefined}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label className="text-xs">Title</Label>
                                <Input
                                    name={`agenda[${i}][title]`}
                                    value={entry.title}
                                    onChange={(e) =>
                                        update(
                                            entry.key,
                                            'title',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Opening keynote"
                                    required
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label className="text-xs">
                                    Description (optional)
                                </Label>
                                <Textarea
                                    name={`agenda[${i}][description]`}
                                    value={entry.description}
                                    onChange={(e) =>
                                        update(
                                            entry.key,
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">
                                        Host / performer
                                    </Label>
                                    <Input
                                        name={`agenda[${i}][host_name]`}
                                        value={entry.host_name}
                                        onChange={(e) =>
                                            update(
                                                entry.key,
                                                'host_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Jane Doe"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label className="text-xs">Role</Label>
                                    <input
                                        type="hidden"
                                        name={`agenda[${i}][host_role]`}
                                        value={entry.host_role}
                                    />
                                    <Select
                                        value={entry.host_role || NONE_ROLE}
                                        onValueChange={(v) =>
                                            update(
                                                entry.key,
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
                                    onClick={() => remove(entry.key)}
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
                Add agenda item
            </Button>
        </div>
    );
}
