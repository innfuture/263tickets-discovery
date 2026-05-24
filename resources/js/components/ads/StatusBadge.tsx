import Lozenge from '@atlaskit/lozenge';

/**
 * 263Tickets domain status vocabulary mapped to ADS Lozenge appearances.
 * Adding a new status? Add the key + config here — never use raw
 * Lozenge appearance values across pages, that breaks consistency.
 */

export type TicketStatus =
    | 'available'
    | 'sold_out'
    | 'reserved'
    | 'cancelled'
    | 'pending'
    | 'completed'
    | 'draft'
    | 'published'
    | 'refunded'
    | 'checking_in'
    | 'checked_in'
    | 'no_show'
    | 'voided'
    | 'transferred';

type LozengeAppearance = 'default' | 'inprogress' | 'moved' | 'new' | 'removed' | 'success';

interface StatusConfig {
    label: string;
    appearance: LozengeAppearance;
    isBold: boolean;
}

const statusConfig: Record<TicketStatus, StatusConfig> = {
    available: { label: 'Available', appearance: 'success', isBold: false },
    sold_out: { label: 'Sold out', appearance: 'removed', isBold: true },
    reserved: { label: 'Reserved', appearance: 'inprogress', isBold: false },
    cancelled: { label: 'Cancelled', appearance: 'removed', isBold: false },
    pending: { label: 'Pending', appearance: 'moved', isBold: false },
    completed: { label: 'Completed', appearance: 'success', isBold: true },
    draft: { label: 'Draft', appearance: 'default', isBold: false },
    published: { label: 'Published', appearance: 'new', isBold: false },
    refunded: { label: 'Refunded', appearance: 'moved', isBold: true },
    checking_in: { label: 'Checking in', appearance: 'inprogress', isBold: true },
    checked_in: { label: 'Checked in', appearance: 'success', isBold: true },
    no_show: { label: 'No-show', appearance: 'default', isBold: false },
    voided: { label: 'Voided', appearance: 'removed', isBold: false },
    transferred: { label: 'Transferred', appearance: 'new', isBold: false },
};

export interface StatusBadgeProps {
    status: TicketStatus;
    customLabel?: string;
    maxWidth?: number | string;
    testId?: string;
}

export function StatusBadge({ status, customLabel, maxWidth, testId }: StatusBadgeProps) {
    const config = statusConfig[status];
    return (
        <span data-testid={testId}>
            <Lozenge appearance={config.appearance} isBold={config.isBold} maxWidth={maxWidth}>
                {customLabel ?? config.label}
            </Lozenge>
        </span>
    );
}
