import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Lock,
    Plus,
    Search,
    ShieldCheck,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

import { RoleForm } from './role-form';

type RolePayload = {
    id: number;
    name: string;
    description: string | null;
    level: number;
    isSystem: boolean;
    memberCount: number;
    permissions: string[];
};

type PermissionGroup = {
    group: string;
    items: { value: string; label: string }[];
};

export default function RolesIndex({
    roles,
    permissionGroups,
    canManage,
}: {
    roles: RolePayload[];
    permissionGroups: PermissionGroup[];
    canManage: boolean;
}) {
    const [createOpen, setCreateOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);

    // Client-side filter — the catalogue is small (10 system + a handful
    // of custom), so no server round-trip is justified. Match against
    // name + description, case-insensitive.
    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return roles;

        return roles.filter(
            (r) =>
                r.name.toLowerCase().includes(q) ||
                (r.description?.toLowerCase().includes(q) ?? false),
        );
    }, [query, roles]);

    // Paginate the filtered slice. 10 per page matches the system-role
    // count exactly, so a fresh org with no custom roles fits on a
    // single page.
    const PER_PAGE = 10;
    const totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));

    // Whenever the filter changes (search or upstream role list), drop
    // back to page 1 so the user never lands on an empty page.
    useEffect(() => {
        setPage(1);
    }, [query, roles]);

    // Clamp in case the dataset shrank under us (e.g. after a delete).
    const safePage = Math.min(page, totalPages);
    const start = (safePage - 1) * PER_PAGE;
    const pageItems = filtered.slice(start, start + PER_PAGE);

    return (
        <>
            <Head title="Roles & Permissions" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Roles & Permissions"
                        description="Roles bundle permissions for the people in your organization. System roles ship with sensible defaults; create custom roles when those don't fit."
                    />

                    {canManage ? (
                        <Dialog
                            open={createOpen}
                            onOpenChange={setCreateOpen}
                        >
                            <DialogTrigger asChild>
                                <Button size="sm">
                                    <Plus className="size-4" />
                                    New role
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="flex max-h-[85dvh] flex-col gap-0 p-0 sm:max-w-3xl">
                                <DialogHeader className="shrink-0 px-6 pt-6 pb-4">
                                    <DialogTitle>
                                        Create a custom role
                                    </DialogTitle>
                                    <DialogDescription>
                                        Roles are scoped to your organization.
                                        Pick a name and the permissions members
                                        with this role should hold.
                                    </DialogDescription>
                                </DialogHeader>
                                <RoleForm
                                    action="/settings/roles"
                                    method="post"
                                    permissionGroups={permissionGroups}
                                    onSuccess={() => setCreateOpen(false)}
                                />
                            </DialogContent>
                        </Dialog>
                    ) : null}
                </div>

                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search roles by name or description…"
                        className="pl-9"
                        aria-label="Search roles"
                    />
                    {query ? (
                        <button
                            type="button"
                            onClick={() => setQuery('')}
                            className="absolute top-1/2 right-2 -translate-y-1/2 rounded-sm p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Clear search"
                        >
                            <X className="size-4" />
                        </button>
                    ) : null}
                </div>

                {filtered.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No roles match “{query}”.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {pageItems.map((role) => (
                            <Link
                                key={role.id}
                                href={`/settings/roles/${role.id}`}
                                className="group block rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                aria-label={`Open ${role.name}`}
                            >
                                <Card className="h-full transition-[transform,box-shadow,border-color] duration-150 group-hover:-translate-y-0.5 group-hover:border-primary/40 group-hover:shadow-md group-focus-visible:border-primary/40 group-focus-visible:shadow-md">
                                    <CardHeader>
                                        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
                                            {role.name}
                                            {role.isSystem ? (
                                                <span
                                                    className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                                                    title="System roles ship with the platform and can't be edited."
                                                >
                                                    <Lock className="size-3" />
                                                    System
                                                </span>
                                            ) : null}
                                            <span className="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
                                                Level {role.level}
                                            </span>
                                        </CardTitle>
                                        {role.description ? (
                                            <CardDescription className="mt-1">
                                                {role.description}
                                            </CardDescription>
                                        ) : null}
                                        <CardDescription className="mt-2 flex flex-wrap items-center gap-3">
                                            <span className="inline-flex items-center gap-1.5">
                                                <Users className="size-3.5" />
                                                {role.memberCount}{' '}
                                                {role.memberCount === 1
                                                    ? 'member'
                                                    : 'members'}
                                            </span>
                                            <span className="inline-flex items-center gap-1.5">
                                                <ShieldCheck className="size-3.5" />
                                                {role.permissions.length}{' '}
                                                permissions
                                            </span>
                                        </CardDescription>
                                    </CardHeader>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}

                {totalPages > 1 ? (
                    <Pagination
                        page={safePage}
                        totalPages={totalPages}
                        onPageChange={setPage}
                        rangeStart={start + 1}
                        rangeEnd={start + pageItems.length}
                        total={filtered.length}
                    />
                ) : null}
            </div>
        </>
    );
}

/**
 * Compact pager: prev/next chevrons + numbered buttons with a sliding
 * window. Kept inline rather than in a shared `ui/pagination` because
 * it's the only spot in the app that needs it today; lift it out when
 * a second caller appears.
 */
function Pagination({
    page,
    totalPages,
    onPageChange,
    rangeStart,
    rangeEnd,
    total,
}: {
    page: number;
    totalPages: number;
    onPageChange: (next: number) => void;
    rangeStart: number;
    rangeEnd: number;
    total: number;
}) {
    // Show up to 5 page buttons centred around the current page, with
    // ellipses for the gaps. Avoids a long bar of numbers once a large
    // org accumulates dozens of custom roles.
    const windowSize = 5;
    const half = Math.floor(windowSize / 2);
    let lo = Math.max(1, page - half);
    let hi = Math.min(totalPages, lo + windowSize - 1);
    if (hi - lo + 1 < windowSize) {
        lo = Math.max(1, hi - windowSize + 1);
    }
    const numbers: number[] = [];
    for (let i = lo; i <= hi; i++) numbers.push(i);

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-xs text-muted-foreground">
                Showing {rangeStart}–{rangeEnd} of {total}
            </p>
            <nav
                className="flex items-center gap-1"
                aria-label="Roles pagination"
            >
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    onClick={() => onPageChange(page - 1)}
                    disabled={page <= 1}
                    aria-label="Previous page"
                >
                    <ChevronLeft className="size-4" />
                </Button>
                {lo > 1 ? (
                    <>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 min-w-8"
                            onClick={() => onPageChange(1)}
                        >
                            1
                        </Button>
                        {lo > 2 ? (
                            <span className="px-1 text-xs text-muted-foreground">
                                …
                            </span>
                        ) : null}
                    </>
                ) : null}
                {numbers.map((n) => (
                    <Button
                        key={n}
                        variant={n === page ? 'default' : 'ghost'}
                        size="sm"
                        className="h-8 min-w-8"
                        onClick={() => onPageChange(n)}
                        aria-current={n === page ? 'page' : undefined}
                    >
                        {n}
                    </Button>
                ))}
                {hi < totalPages ? (
                    <>
                        {hi < totalPages - 1 ? (
                            <span className="px-1 text-xs text-muted-foreground">
                                …
                            </span>
                        ) : null}
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-8 min-w-8"
                            onClick={() => onPageChange(totalPages)}
                        >
                            {totalPages}
                        </Button>
                    </>
                ) : null}
                <Button
                    variant="outline"
                    size="icon"
                    className="size-8"
                    onClick={() => onPageChange(page + 1)}
                    disabled={page >= totalPages}
                    aria-label="Next page"
                >
                    <ChevronRight className="size-4" />
                </Button>
            </nav>
        </div>
    );
}

RolesIndex.layout = {
    breadcrumbs: [
        { title: 'Settings', href: '/settings' },
        { title: 'Roles & Permissions', href: '/settings/roles' },
    ],
};

// Re-exposed for the show page so it gets the right router action.
export { RoleForm };
export { router };
