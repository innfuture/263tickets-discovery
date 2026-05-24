import AKSelect from '@atlaskit/select';
import { ErrorMessage, Field, HelperMessage } from '@atlaskit/form';
import type { ReactNode } from 'react';

export interface SelectOption {
    label: string;
    value: string | number;
    description?: string;
    isDisabled?: boolean;
}

export interface SelectOptionGroup {
    label: string;
    options: SelectOption[];
}

interface SelectProps<TIsMulti extends boolean = false> {
    name: string;
    label: string;
    options: SelectOption[] | SelectOptionGroup[];
    value?: TIsMulti extends true ? readonly SelectOption[] : SelectOption | null;
    defaultValue?: TIsMulti extends true ? readonly SelectOption[] : SelectOption | null;
    onChange?: (option: TIsMulti extends true ? readonly SelectOption[] : SelectOption | null) => void;
    isMulti?: TIsMulti;
    isSearchable?: boolean;
    isClearable?: boolean;
    isDisabled?: boolean;
    isLoading?: boolean;
    isRequired?: boolean;
    placeholder?: string;
    error?: string;
    hint?: string;
    inputId?: string;
    menuPortalTarget?: HTMLElement | null;
    testId?: string;
    noOptionsMessage?: () => string;
    formatOptionLabel?: (option: SelectOption) => ReactNode;
}

export function Select<TIsMulti extends boolean = false>({
    name,
    label,
    error,
    hint,
    isRequired,
    ...selectProps
}: SelectProps<TIsMulti>) {
    return (
        <Field name={name} label={label} isRequired={isRequired}>
            {({ fieldProps }) => (
                <>
                    <AKSelect
                        {...(fieldProps as object)}
                        {...(selectProps as object)}
                        validationState={error ? 'error' : 'default'}
                        classNamePrefix="ads-select"
                    />
                    {hint && !error && <HelperMessage>{hint}</HelperMessage>}
                    {error && <ErrorMessage>{error}</ErrorMessage>}
                </>
            )}
        </Field>
    );
}
