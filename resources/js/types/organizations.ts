import type { TeamRole } from '@/types/teams';

/**
 * Top of the hierarchy: Organization → Teams → Members.
 *
 * Shipped via HandleInertiaRequests on every request so the switcher,
 * sidebar header, and any page that needs to render the active org's
 * brand can read it without an extra fetch.
 */
export type Organization = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    logoUrl?: string | null;
    isVerified?: boolean | null;
    isCurrent?: boolean | null;
};

export type OrganizationInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};
