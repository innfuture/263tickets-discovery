import { Checkbox as AKCheckbox } from '@atlaskit/checkbox';
import type { ChangeEvent } from 'react';

export interface CheckboxProps {
    name: string;
    label: string;
    value?: string;
    isChecked?: boolean;
    defaultChecked?: boolean;
    onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
    isDisabled?: boolean;
    isRequired?: boolean;
    isInvalid?: boolean;
    isIndeterminate?: boolean;
    testId?: string;
}

export function Checkbox({ label, ...props }: CheckboxProps) {
    return <AKCheckbox label={label} {...props} />;
}
