import AKTextField from '@atlaskit/textfield';
import { ErrorMessage, Field, HelperMessage, ValidMessage } from '@atlaskit/form';
import type { ChangeEvent, ReactNode } from 'react';

/**
 * Single-line text input with the ADS Field wrapper so labels, help
 * text, and error messages all stay properly associated for screen
 * readers. Accepts Laravel-style `error` string directly so Inertia
 * forms can pass `errors.field_name` straight through.
 */

export interface TextFieldProps {
    name: string;
    label: string;
    placeholder?: string;
    defaultValue?: string;
    value?: string;
    onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
    isRequired?: boolean;
    isDisabled?: boolean;
    isReadOnly?: boolean;
    isInvalid?: boolean;
    error?: string;
    hint?: string;
    success?: string;
    type?: 'text' | 'email' | 'password' | 'number' | 'url' | 'tel' | 'search';
    autoComplete?: string;
    autoFocus?: boolean;
    elemBeforeInput?: ReactNode;
    elemAfterInput?: ReactNode;
    maxLength?: number;
    testId?: string;
}

export function TextField({
    name,
    label,
    error,
    hint,
    success,
    isInvalid,
    isRequired,
    elemBeforeInput,
    elemAfterInput,
    ...inputProps
}: TextFieldProps) {
    const invalid = isInvalid ?? !!error;

    return (
        <Field name={name} label={label} isRequired={isRequired} defaultValue={inputProps.defaultValue}>
            {({ fieldProps }) => (
                <>
                    <AKTextField
                        {...fieldProps}
                        {...inputProps}
                        isInvalid={invalid}
                        elemBeforeInput={elemBeforeInput}
                        elemAfterInput={elemAfterInput}
                    />
                    {hint && !error && <HelperMessage>{hint}</HelperMessage>}
                    {error && <ErrorMessage>{error}</ErrorMessage>}
                    {success && !error && <ValidMessage>{success}</ValidMessage>}
                </>
            )}
        </Field>
    );
}
