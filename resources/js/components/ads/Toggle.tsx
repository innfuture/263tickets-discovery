import AKToggle from '@atlaskit/toggle';
import type { ChangeEvent } from 'react';

export interface ToggleProps {
    id?: string;
    name?: string;
    label?: string;
    isChecked?: boolean;
    defaultChecked?: boolean;
    onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
    isDisabled?: boolean;
    size?: 'regular' | 'large';
    testId?: string;
}

/**
 * Boolean toggle. Distinct from Checkbox semantically — use Toggle for
 * settings (immediate-effect on/off) and Checkbox for form values.
 */
export function Toggle({ label, ...props }: ToggleProps) {
    return <AKToggle aria-label={label} {...props} />;
}
