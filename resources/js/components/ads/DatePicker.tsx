import { DatePicker as AKDatePicker, DateTimePicker as AKDateTimePicker } from '@atlaskit/datetime-picker';
import { ErrorMessage, Field, HelperMessage } from '@atlaskit/form';

interface CommonProps {
    name: string;
    label: string;
    value?: string;
    defaultValue?: string;
    onChange?: (value: string) => void;
    isDisabled?: boolean;
    isRequired?: boolean;
    isInvalid?: boolean;
    error?: string;
    hint?: string;
    locale?: string;
    timeZone?: string;
    testId?: string;
}

interface DatePickerProps extends CommonProps {
    minDate?: string;
    maxDate?: string;
    placeholder?: string;
}

export function DatePicker({ name, label, error, hint, isRequired, isInvalid, ...rest }: DatePickerProps) {
    const invalid = isInvalid ?? !!error;

    return (
        <Field name={name} label={label} isRequired={isRequired} defaultValue={rest.defaultValue}>
            {({ fieldProps }) => (
                <>
                    <AKDatePicker {...fieldProps} {...rest} isInvalid={invalid} />
                    {hint && !error && <HelperMessage>{hint}</HelperMessage>}
                    {error && <ErrorMessage>{error}</ErrorMessage>}
                </>
            )}
        </Field>
    );
}

interface DateTimePickerProps extends CommonProps {
    placeholder?: string;
    times?: string[];
    timeIsEditable?: boolean;
}

export function DateTimePicker({ name, label, error, hint, isRequired, isInvalid, ...rest }: DateTimePickerProps) {
    const invalid = isInvalid ?? !!error;

    return (
        <Field name={name} label={label} isRequired={isRequired} defaultValue={rest.defaultValue}>
            {({ fieldProps }) => (
                <>
                    <AKDateTimePicker {...fieldProps} {...rest} isInvalid={invalid} />
                    {hint && !error && <HelperMessage>{hint}</HelperMessage>}
                    {error && <ErrorMessage>{error}</ErrorMessage>}
                </>
            )}
        </Field>
    );
}
