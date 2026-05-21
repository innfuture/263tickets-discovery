import { Form, Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    RoleOption,
    TeamMember,
    TeamPermissions,
    TeamRole,
} from '@/types/teams';

const DESCRIPTION_MAX = 180;

type SubTeam = {
    id: number;
    name: string;
    description: string | null;
    slug: string;
    isPersonal: boolean;
};

export default function TeamEdit({
    team,
    members,
    permissions,
    availableRoles,
}: {
    team: SubTeam;
    members: TeamMember[];
    permissions: TeamPermissions;
    availableRoles: RoleOption[];
}) {
    const confirm = useConfirm();
    const action = `/settings/teams/${team.slug}`;

    const [description, setDescription] = useState(team.description ?? '');

    const removeMember = async (member: TeamMember) => {
        const ok = await confirm({
            title: 'Remove member?',
            description: `${member.name} will lose access to ${team.name} but will remain in the organization.`,
            confirmLabel: 'Remove',
            tone: 'destructive',
        });
        if (!ok) return;

        router.delete(`${action}/members/${member.id}`);
    };

    const changeRole = (member: TeamMember, role: TeamRole) => {
        router.patch(`${action}/members/${member.id}`, { role });
    };

    const deleteTeam = async () => {
        const ok = await confirm({
            title: 'Delete team?',
            description: `This removes ${team.name} from your organization. Members stay in the org, but every team-scoped membership is dropped.`,
            confirmLabel: 'Delete',
            tone: 'destructive',
        });
        if (!ok) return;

        router.delete(action, { data: { name: team.name } });
    };

    return (
        <>
            <Head title={`${team.name} — Team settings`} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={team.name}
                    description="Sub-team within your organization. Rename, manage members, or delete."
                />

                {permissions.canUpdateTeam ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Team details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={action}
                                method="patch"
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="team-name"
                                                error={errors.name}
                                            >
                                                Name
                                            </FieldLabel>
                                            <Input
                                                id="team-name"
                                                name="name"
                                                defaultValue={team.name}
                                                required
                                                aria-invalid={!!errors.name}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <div className="flex items-center justify-between gap-2">
                                                <FieldLabel
                                                    htmlFor="team-description"
                                                    error={errors.description}
                                                    optional
                                                >
                                                    Description
                                                </FieldLabel>
                                                <span
                                                    className={
                                                        description.length >
                                                        DESCRIPTION_MAX
                                                            ? 'text-xs text-destructive'
                                                            : 'text-xs text-muted-foreground'
                                                    }
                                                >
                                                    {description.length}/
                                                    {DESCRIPTION_MAX}
                                                </span>
                                            </div>
                                            <Textarea
                                                id="team-description"
                                                name="description"
                                                value={description}
                                                onChange={(e) =>
                                                    setDescription(
                                                        e.target.value,
                                                    )
                                                }
                                                rows={3}
                                                maxLength={DESCRIPTION_MAX}
                                                placeholder="One short sentence about what this team is for (visible to org members)."
                                                aria-invalid={
                                                    !!errors.description
                                                }
                                            />
                                        </div>
                                        <div className="flex justify-end">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    description.length >
                                                        DESCRIPTION_MAX
                                                }
                                            >
                                                Save
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Members</CardTitle>
                        <CardDescription>
                            Anyone in the organization can be assigned to this
                            team.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {members.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No members yet.
                            </p>
                        ) : (
                            members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center justify-between gap-3 rounded-md border p-3"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {member.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {member.email}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {permissions.canUpdateMember &&
                                        member.role !== 'owner' ? (
                                            <Select
                                                value={member.role}
                                                onValueChange={(v) =>
                                                    changeRole(
                                                        member,
                                                        v as TeamRole,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-32">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableRoles.map((r) => (
                                                        <SelectItem
                                                            key={r.value}
                                                            value={r.value}
                                                        >
                                                            {r.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs">
                                                {member.role_label}
                                            </span>
                                        )}
                                        {permissions.canRemoveMember &&
                                        member.role !== 'owner' ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeMember(member)
                                                }
                                                aria-label="Remove member"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        ) : null}
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {permissions.canDeleteTeam && !team.isPersonal ? (
                    <Card className="border-destructive/30">
                        <CardHeader>
                            <CardTitle className="text-base text-destructive">
                                Delete team
                            </CardTitle>
                            <CardDescription>
                                This removes the team from your organization.
                                Members stay in the org.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={deleteTeam}
                            >
                                Delete team
                            </Button>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

TeamEdit.layout = ({ team }: { team: SubTeam }) => ({
    breadcrumbs: [
        { title: 'Settings', href: '/settings' },
        { title: 'Teams', href: '/settings/teams' },
        { title: team.name, href: `/settings/teams/${team.slug}` },
    ],
});
