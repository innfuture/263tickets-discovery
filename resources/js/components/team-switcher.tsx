import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import type { Organization } from '@/types/organizations';

/**
 * Switches the viewer's active Organization. Sits at the top of the
 * sidebar; despite the legacy `TeamSwitcher` filename, the entity it
 * toggles is the org — that's what scopes the URL prefix and what
 * every page binds against post-refactor.
 */
type TeamSwitcherProps = {
    inHeader?: boolean;
};

export function TeamSwitcher({ inHeader = false }: TeamSwitcherProps) {
    const page = usePage();
    const isMobile = useIsMobile();
    const current = page.props.currentOrganization;
    const orgs = page.props.organizations ?? [];

    const switchOrg = (org: Organization) => {
        const previousSlug = current?.slug;

        router.visit(`/settings/organizations/${org.slug}/switch`, {
            method: 'post',
            onFinish: () => {
                if (!previousSlug || typeof window === 'undefined') {
                    router.reload();

                    return;
                }

                const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
                const segment = `/${previousSlug}`;

                if (currentUrl.includes(segment)) {
                    router.visit(currentUrl.replace(segment, `/${org.slug}`), {
                        replace: true,
                    });

                    return;
                }

                router.reload();
            },
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="team-switcher-trigger"
                    className={
                        inHeader
                            ? 'h-8 gap-1 px-2'
                            : 'w-full justify-start px-2 has-[>svg]:px-2 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
                    }
                >
                    <Building2
                        className={
                            inHeader
                                ? 'hidden'
                                : 'hidden size-4 shrink-0 group-data-[collapsible=icon]:block'
                        }
                    />
                    <div
                        className={
                            inHeader
                                ? 'grid flex-1 text-left text-sm leading-tight'
                                : 'grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                        }
                    >
                        <span
                            className={
                                inHeader
                                    ? 'max-w-[120px] truncate font-medium'
                                    : 'truncate font-semibold'
                            }
                        >
                            {current?.name ?? 'Select organization'}
                        </span>
                    </div>
                    <ChevronsUpDown
                        className={
                            inHeader
                                ? 'size-4 opacity-50'
                                : 'ml-auto group-data-[collapsible=icon]:hidden'
                        }
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className={
                    inHeader
                        ? 'w-56'
                        : 'w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
                }
                side={inHeader ? undefined : isMobile ? 'bottom' : 'right'}
                align={inHeader ? 'end' : 'start'}
                sideOffset={inHeader ? undefined : 4}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    Organizations
                </DropdownMenuLabel>
                {orgs.map((org) => (
                    <DropdownMenuItem
                        key={org.id}
                        data-test="team-switcher-item"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={() => switchOrg(org)}
                    >
                        {org.name}
                        {current?.id === org.id && (
                            <Check
                                className={
                                    inHeader
                                        ? 'ml-auto size-4'
                                        : 'ml-auto h-4 w-4'
                                }
                            />
                        )}
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    asChild
                    className={
                        inHeader
                            ? 'cursor-pointer gap-2'
                            : 'cursor-pointer gap-2 p-2'
                    }
                >
                    <a href="/settings/organizations">
                        <Plus className={inHeader ? 'size-4' : 'h-4 w-4'} />
                        <span className="text-muted-foreground">
                            Manage organizations
                        </span>
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
