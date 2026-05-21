import { Form } from '@inertiajs/react';
import { Info, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type PermissionItem = { value: string; label: string };
type PermissionGroup = { group: string; items: PermissionItem[] };

export type RoleFormProps = {
    action: string;
    method: 'post' | 'patch';
    permissionGroups: PermissionGroup[];
    initialValues?: {
        name?: string;
        description?: string | null;
        level?: number;
        permissions?: string[];
    };
    onSuccess?: () => void;
    submitLabel?: string;
};

/**
 * Shared create/edit form for org roles. Renders the permission
 * catalogue as a stack of grouped checkbox sections, mirroring the
 * `permission.group()` bucketing from PHP. Each checkbox posts under
 * `permissions[]` so the server receives an array of permission names.
 */
export function RoleForm({
    action,
    method,
    permissionGroups,
    initialValues,
    onSuccess,
    submitLabel = 'Save role',
}: RoleFormProps) {
    const [name, setName] = useState(initialValues?.name ?? '');
    const [description, setDescription] = useState(
        initialValues?.description ?? '',
    );
    const [level, setLevel] = useState<number>(initialValues?.level ?? 30);
    const [selected, setSelected] = useState<Set<string>>(
        () => new Set(initialValues?.permissions ?? []),
    );

    const toggle = (perm: string) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(perm)) {
                next.delete(perm);
            } else {
                next.add(perm);
            }
            return next;
        });
    };

    const toggleGroup = (group: PermissionGroup, on: boolean) => {
        setSelected((prev) => {
            const next = new Set(prev);
            group.items.forEach((item) => {
                if (on) next.add(item.value);
                else next.delete(item.value);
            });
            return next;
        });
    };

    return (
        <Form
            action={action}
            method={method}
            className="flex min-h-0 flex-1 flex-col"
            onSuccess={() => {
                onSuccess?.();
            }}
            onError={() =>
                toast.error(
                    "Couldn't save the role — review the highlighted fields and try again.",
                )
            }
        >
            {({ processing, errors }) => (
                <>
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 pb-4">
                        <div className="grid gap-4">
                            <div className="grid gap-4 sm:grid-cols-[1fr_140px]">
                                <div className="grid gap-2">
                                    <FieldLabel
                                        htmlFor="role-name"
                                        error={errors.name}
                                    >
                                        Role name
                                    </FieldLabel>
                                    <Input
                                        id="role-name"
                                        name="name"
                                        value={name}
                                        onChange={(e) =>
                                            setName(e.target.value)
                                        }
                                        required
                                        minLength={2}
                                        maxLength={60}
                                        placeholder="e.g. Event Producer"
                                        aria-invalid={!!errors.name}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between gap-2">
                                        <FieldLabel
                                            htmlFor="role-level"
                                            error={errors.level}
                                        >
                                            Level
                                        </FieldLabel>
                                        <LevelInfoPopover />
                                    </div>
                                    <Input
                                        id="role-level"
                                        name="level"
                                        type="number"
                                        min={10}
                                        max={90}
                                        value={level}
                                        onChange={(e) =>
                                            setLevel(
                                                Number(e.target.value) || 30,
                                            )
                                        }
                                        aria-invalid={!!errors.level}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <FieldLabel
                                    htmlFor="role-description"
                                    error={errors.description}
                                    optional
                                >
                                    Description
                                </FieldLabel>
                                <Textarea
                                    id="role-description"
                                    name="description"
                                    rows={2}
                                    maxLength={190}
                                    value={description ?? ''}
                                    onChange={(e) =>
                                        setDescription(e.target.value)
                                    }
                                    placeholder="What this role is for. Shown on the roles list."
                                    aria-invalid={!!errors.description}
                                />
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="text-sm font-semibold">
                                        Permissions
                                    </h3>
                                    <span className="text-xs text-muted-foreground">
                                        {selected.size} selected
                                    </span>
                                </div>

                                {permissionGroups.map((group) => {
                                    const allOn = group.items.every((i) =>
                                        selected.has(i.value),
                                    );
                                    const someOn = group.items.some((i) =>
                                        selected.has(i.value),
                                    );

                                    return (
                                        <div
                                            key={group.group}
                                            className="rounded-md border p-3"
                                        >
                                            <div className="mb-3 flex items-center justify-between">
                                                <h4 className="text-sm font-medium">
                                                    {group.group}
                                                </h4>
                                                <button
                                                    type="button"
                                                    className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                                                    onClick={() =>
                                                        toggleGroup(
                                                            group,
                                                            !allOn,
                                                        )
                                                    }
                                                >
                                                    {allOn
                                                        ? 'Clear group'
                                                        : someOn
                                                          ? 'Select all'
                                                          : 'Select all'}
                                                </button>
                                            </div>
                                            <div className="grid gap-2 sm:grid-cols-2">
                                                {group.items.map((item) => {
                                                    const checked =
                                                        selected.has(
                                                            item.value,
                                                        );
                                                    return (
                                                        <label
                                                            key={item.value}
                                                            className={cn(
                                                                'flex cursor-pointer items-start gap-2 rounded px-2 py-1 text-sm transition-colors',
                                                                checked
                                                                    ? 'bg-blue-50 dark:bg-blue-950/40'
                                                                    : 'hover:bg-muted',
                                                            )}
                                                        >
                                                            <Checkbox
                                                                checked={
                                                                    checked
                                                                }
                                                                onCheckedChange={() =>
                                                                    toggle(
                                                                        item.value,
                                                                    )
                                                                }
                                                            />
                                                            <span
                                                                className={cn(
                                                                    checked &&
                                                                        'font-medium text-blue-700 underline underline-offset-2 dark:text-blue-300',
                                                                )}
                                                            >
                                                                {item.label}
                                                            </span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    );
                                })}

                                {/* Hidden inputs that actually post to
                                    the server — Inertia's <Form> reads
                                    them straight from the DOM. */}
                                {[...selected].map((p) => (
                                    <input
                                        key={p}
                                        type="hidden"
                                        name="permissions[]"
                                        value={p}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="flex shrink-0 items-center justify-end gap-2 border-t bg-background px-6 py-4">
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="size-4 animate-spin" />
                                    Saving…
                                </>
                            ) : (
                                submitLabel
                            )}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

/**
 * Hover-open Popover used as the info affordance next to the Level
 * label. Radix Popover triggers on click by default; we drive
 * `open` from mouse-enter/leave so a hover surfaces the explainer
 * (and clicks still toggle for touch/keyboard users).
 */
function LevelInfoPopover() {
    const [open, setOpen] = useState(false);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    onMouseEnter={() => setOpen(true)}
                    onMouseLeave={() => setOpen(false)}
                    onFocus={() => setOpen(true)}
                    onBlur={() => setOpen(false)}
                    className="text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 rounded-sm"
                    aria-label="About role levels"
                >
                    <Info className="size-3.5" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                side="top"
                align="end"
                sideOffset={6}
                className="w-64"
                onMouseEnter={() => setOpen(true)}
                onMouseLeave={() => setOpen(false)}
            >
                <p>
                    10 (lowest) – 90. Higher levels outrank lower ones for
                    member-edit actions.
                </p>
            </PopoverContent>
        </Popover>
    );
}
