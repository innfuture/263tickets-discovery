import { RadioGroup as AKRadioGroup } from '@atlaskit/radio';
import { ErrorMessage, Field, HelperMessage } from '@atlaskit/form';
import type { ChangeEvent } from 'react';

export interface RadioOption {
    label: string;
    value: string;
    isDisabled?: boolean;
}

export interface RadioGroupProps {
    name: string;
    label: string;
    options: RadioOption[];
    value?: string;
    defaultValue?: string;
    onChange?: (e: ChangeEvent<HTMLInputElement>) => void;
    isRequired?: boolean;
    isDisabled?: boolean;
    error?: string;
    hint?: string;
    testId?: string;
}

export function RadioGroup({ name, label, options, error, hint, isRequired, ...rest }: RadioGroupProps) {
    return (
        <Field name={name} label={label} isRequired={isRequired} defaultValue={rest.defaultValue}>
            {({ fieldProps }) => (
                <>
                    <AKRadioGroup {...fieldProps} {...rest} options={options} />
                    {hint && !error && <HelperMessage>{hint}</HelperMessage>}
                    {error && <ErrorMessage>{error}</ErrorMessage>}
                </>
            )}
        </Field>
    );
}
