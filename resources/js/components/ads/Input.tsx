import AKTextField from '@atlaskit/textfield';
import type { ChangeEvent, ReactNode } from 'react';

/**
 * Bare-input wrapper for cases where you don't need the Field wrapper
 * (label + error). For form fields use TextField instead.
 */
export interface InputProps {
    name?: string;
    id?: string;
    type?: 'text' | 'email' | 'password' | 'number' | 'url' | 'tel' | 'search';
    value?: string;
    defaultValue?: string;
    onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
    placeholder?: string;
    isDisabled?: boolean;
    isReadOnly?: boolean;
    isInvalid?: boolean;
    isRequired?: boolean;
    autoComplete?: string;
    autoFocus?: boolean;
    elemBeforeInput?: ReactNode;
    elemAfterInput?: ReactNode;
    maxLength?: number;
    'aria-label'?: string;
    testId?: string;
}

export function Input(props: InputProps) {
    return <AKTextField {...props} />;
}
