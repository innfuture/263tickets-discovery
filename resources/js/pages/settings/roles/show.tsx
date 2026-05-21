import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Lock, Trash2, Users } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useConfirm } from '@/components/ui/confirmation-dialog';

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

export default function RoleShow({
    role,
    permissionGroups,
    canManage,
}: {
    role: RolePayload;
    permissionGroups: PermissionGroup[];
    canManage: boolean;
}) {
    const confirm = useConfirm();
    const editable = canManage && !role.isSystem;

    const deleteRole = async () => {
        const ok = await confirm({
            title: `Delete ${role.name}?`,
            description: `Members currently holding this role will lose its permissions immediately. You can't delete a role with assigned members — reassign first.`,
            confirmLabel: 'Delete',
            tone: 'destructive',
        });
        if (!ok) return;

        router.delete(`/settings/roles/${role.id}`);
    };

    return (
        <>
            <Head title={`${role.name} — Role`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="space-y-1">
                        <Button variant="ghost" size="sm" asChild className="px-0">
                            <Link href="/settings/roles">
                                <ArrowLeft className="size-4" />
                                Back to roles
                            </Link>
                        </Button>
                        <Heading
                            variant="small"
                            title={role.name}
                            description={
                                role.description ?? 'Custom role for this organization.'
                            }
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        {role.isSystem ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                <Lock className="size-3" />
                                System role
                            </span>
                        ) : null}
                        {editable ? (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={deleteRole}
                                disabled={role.memberCount > 0}
                                title={
                                    role.memberCount > 0
                                        ? 'Reassign members first'
                                        : undefined
                                }
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </Button>
                        ) : null}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Overview</CardTitle>
                        <CardDescription className="flex flex-wrap items-center gap-3">
                            <span className="inline-flex items-center gap-1.5">
                                <Users className="size-3.5" />
                                {role.memberCount}{' '}
                                {role.memberCount === 1 ? 'member' : 'members'}
                            </span>
                            <span className="rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
                                Level {role.level}
                            </span>
                        </CardDescription>
                    </CardHeader>
                </Card>

                {editable ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Edit role
                            </CardTitle>
                            <CardDescription>
                                Adjust the permission set or rename the role.
                                Changes take effect immediately for every
                                member who holds it.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <RoleForm
                                action={`/settings/roles/${role.id}`}
                                method="patch"
                                permissionGroups={permissionGroups}
                                initialValues={{
                                    name: role.name,
                                    description: role.description,
                                    level: role.level,
                                    permissions: role.permissions,
                                }}
                                submitLabel="Save changes"
                            />
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Permissions
                            </CardTitle>
                            <CardDescription>
                                System roles are read-only. Create a custom
                                role to define a different permission set.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {permissionGroups.map((group) => {
                                const granted = group.items.filter((i) =>
                                    role.permissions.includes(i.value),
                                );
                                if (granted.length === 0) return null;

                                return (
                                    <div
                                        key={group.group}
                                        className="rounded-md border p-3"
                                    >
                                        <h4 className="mb-2 text-sm font-medium">
                                            {group.group}
                                        </h4>
                                        <ul className="grid gap-1 text-sm sm:grid-cols-2">
                                            {granted.map((item) => (
                                                <li
                                                    key={item.value}
                                                    className="text-muted-foreground"
                                                >
                                                    {item.label}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

RoleShow.layout = ({ role }: { role: RolePayload }) => ({
    breadcrumbs: [
        { title: 'Settings', href: '/settings' },
        { title: 'Roles & Permissions', href: '/settings/roles' },
        { title: role.name, href: `/settings/roles/${role.id}` },
    ],
});
