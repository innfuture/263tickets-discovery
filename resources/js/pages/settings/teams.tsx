import { Form, Head, Link, router } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const DESCRIPTION_MAX = 180;

type SubTeam = {
    id: number;
    name: string;
    description: string | null;
    slug: string;
    isPersonal: boolean;
    isCurrent: boolean;
    memberCount: number;
};

export default function SettingsTeams({ teams }: { teams: SubTeam[] }) {
    const [description, setDescription] = useState('');

    return (
        <>
            <Head title="Teams" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title="Teams"
                        description="Sub-teams inside your current organization. Add a team for each department or project squad and assign existing org members to it."
                    />

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button size="sm">
                                <Plus className="size-4" />
                                New team
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create a new team</DialogTitle>
                                <DialogDescription>
                                    Teams scope members within your
                                    organization. You'll be added as the owner.
                                </DialogDescription>
                            </DialogHeader>
                            <Form
                                action="/settings/teams"
                                method="post"
                                className="space-y-4"
                                resetOnSuccess
                                onSuccess={() => setDescription('')}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <FieldLabel
                                                htmlFor="team-name"
                                                error={errors.name}
                                            >
                                                Team name
                                            </FieldLabel>
                                            <Input
                                                id="team-name"
                                                name="name"
                                                autoFocus
                                                required
                                                maxLength={120}
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
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                >
                                                    Cancel
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    description.length >
                                                        DESCRIPTION_MAX
                                                }
                                            >
                                                Create team
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="grid gap-3">
                    {teams.length === 0 ? (
                        <Card>
                            <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                Your organization has no teams yet. Create one
                                to start grouping members.
                            </CardContent>
                        </Card>
                    ) : (
                        teams.map((team) => (
                            <Card key={team.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <CardTitle className="text-base">
                                                {team.name}
                                                {team.isCurrent ? (
                                                    <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                        Active
                                                    </span>
                                                ) : null}
                                                {team.isPersonal ? (
                                                    <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                        Default
                                                    </span>
                                                ) : null}
                                            </CardTitle>
                                            {team.description ? (
                                                <CardDescription className="mt-1">
                                                    {team.description}
                                                </CardDescription>
                                            ) : null}
                                            <CardDescription className="mt-1 flex items-center gap-1.5">
                                                <Users className="size-3.5" />
                                                {team.memberCount}{' '}
                                                {team.memberCount === 1
                                                    ? 'member'
                                                    : 'members'}
                                            </CardDescription>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {!team.isCurrent ? (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            `/settings/teams/${team.slug}/switch`,
                                                        )
                                                    }
                                                >
                                                    Switch
                                                </Button>
                                            ) : null}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={`/settings/teams/${team.slug}`}
                                                >
                                                    Manage
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

SettingsTeams.layout = {
    breadcrumbs: [
        { title: 'Settings', href: '/settings' },
        { title: 'Teams', href: '/settings/teams' },
    ],
};
