import AKTextarea from '@atlaskit/textarea';
import { ErrorMessage, Field, HelperMessage } from '@atlaskit/form';
import type { ChangeEvent } from 'react';

export interface TextareaProps {
    name: string;
    label: string;
    placeholder?: string;
    value?: string;
    defaultValue?: string;
    onChange?: (e: ChangeEvent<HTMLTextAreaElement>) => void;
    isRequired?: boolean;
    isDisabled?: boolean;
    isReadOnly?: boolean;
    isInvalid?: boolean;
    error?: string;
    hint?: string;
    rows?: number;
    minimumRows?: number;
    maxLength?: number;
    resize?: 'auto' | 'smart' | 'horizontal' | 'vertical' | 'none';
    testId?: string;
}

export function Textarea({
    name,
    label,
    error,
    hint,
    isInvalid,
    isRequired,
    rows,
    minimumRows,
    resize = 'smart',
    defaultValue,
    value,
    onChange,
    placeholder,
    isDisabled,
    isReadOnly,
    maxLength,
    testId,
}: TextareaProps) {
    const invalid = isInvalid ?? !!error;

    return (
        <Field name={name} label={label} isRequired={isRequired} defaultValue={defaultValue}>
            {({ fieldProps }) => (
                <>
                    <AKTextarea
                        {...fieldProps}
                        value={value}
                        onChange={onChange}
                        placeholder={placeholder}
                        isDisabled={isDisabled}
                        isReadOnly={isReadOnly}
                        maxLength={maxLength}
                        isInvalid={invalid}
                        minimumRows={minimumRows ?? rows}
                        resize={resize}
                        testId={testId}
                    />
                    {hint && !error && <HelperMessage>{hint}</HelperMessage>}
                    {error && <ErrorMessage>{error}</ErrorMessage>}
                </>
            )}
        </Field>
    );
}
