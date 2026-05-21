import { PhoneInput } from 'react-international-phone';
import 'react-international-phone/style.css';
import { useId, useState } from 'react';
import { FieldLabel } from '@/components/ui/field-label';
import { cn } from '@/lib/utils';

/**
 * Standardised phone input. Wraps react-international-phone's
 * `PhoneInput` so:
 *
 *   - the value posts as a single E.164 string under `name`, ready
 *     for server-side persistence (no parsing required);
 *   - the country flag + dial code is always selectable, and the
 *     selector opens as a floating dropdown rather than expanding
 *     the form container;
 *   - the field uses the same `FieldLabel`/`FieldError` inline-alert
 *     pattern as the rest of the app — server errors and a
 *     client-side minimum-length check share the same tooltip.
 */
type Props = {
    name: string;
    label?: React.ReactNode;
    optional?: boolean;
    defaultValue?: string | null;
    defaultCountry?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    /** Server-side error message (Laravel/Inertia). */
    error?: string;
    /** Custom id; auto-generated when omitted. */
    id?: string;
};

export function PhoneField({
    name,
    label,
    optional = false,
    defaultValue,
    defaultCountry = 'us',
    required = false,
    disabled = false,
    placeholder,
    error,
    id,
}: Props) {
    const autoId = useId();
    const inputId = id ?? `phone-${autoId}`;

    const [value, setValue] = useState<string>(defaultValue ?? '');
    const [touched, setTouched] = useState(false);

    // Minimum-length check after the user has interacted at least once.
    // Required-only — optional fields show no client error when empty.
    const clientError =
        touched &&
        required &&
        (value === '' || value.replace(/[^\d]/g, '').length < 5)
            ? 'Enter a complete phone number with country code.'
            : null;

    const displayedError = error ?? clientError ?? undefined;

    return (
        <div className="grid gap-2">
            {label ? (
                <FieldLabel
                    htmlFor={inputId}
                    error={displayedError}
                    optional={optional}
                >
                    {label}
                </FieldLabel>
            ) : null}
            <PhoneInput
                value={value}
                defaultCountry={defaultCountry}
                onChange={(phone) => setValue(phone)}
                disabled={disabled}
                inputProps={{
                    id: inputId,
                    name,
                    required,
                    placeholder,
                    onBlur: () => setTouched(true),
                    'aria-invalid': !!displayedError,
                }}
                className={cn(
                    'react-intl-phone-shadcn',
                    displayedError && 'react-intl-phone-error',
                )}
            />
        </div>
    );
}
