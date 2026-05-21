import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { Organization } from '@/types/organizations';
import type { Team, User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    organization = null,
    team = null,
}: {
    user: User;
    showEmail?: boolean;
    organization?: Organization | null;
    team?: Team | null;
}) {
    const getInitials = useInitials();
    const showAvatar = Boolean(user.avatar && user.avatar !== '');

    // Org takes precedence in the sidebar subtitle — it's the
    // primary tenancy context the user is acting under. Team is
    // shown only if no org is passed (e.g. legacy callsites).
    const subtitle = organization?.name ?? team?.name ?? null;

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-lg">
                {showAvatar ? (
                    <AvatarImage src={user.avatar} alt={user.name} />
                ) : null}
                <AvatarFallback className="rounded-lg text-black dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {subtitle ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {subtitle}
                    </span>
                ) : null}
                {!subtitle && showEmail ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                ) : null}
            </div>
        </>
    );
}
