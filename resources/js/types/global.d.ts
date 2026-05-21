import type { Auth } from '@/types/auth';
import type { Organization } from '@/types/organizations';
import type { Team } from '@/types/teams';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;

            // Top of the hierarchy — active organization and the
            // viewer's org memberships.
            currentOrganization: Organization | null;
            organizations: Organization[];

            // Sub-team layer within the active organization.
            currentTeam: Team | null;
            teams: Team[];

            [key: string]: unknown;
        };
    }
}
