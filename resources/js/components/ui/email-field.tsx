import { useId, useState } from 'react';
import { validate as isValidEmail } from 'react-email-validator';
import { FieldLabel } from '@/components/ui/field-label';
import { Input } from '@/components/ui/input';

/**
 * Standardised email input. Pairs a normal text Input with on-blur
 * shape validation from `react-email-validator` so users see a
 * client-side hint immediately, while still surfacing server-side
 * errors (`error`) via the same inline FieldLabel/FieldError pattern
 * the rest of the forms use.
 *
 * The component is uncontrolled — Inertia's `<Form>` reads the value
 * straight from the DOM via `name`, same as a plain `<input>`. We
 * only track touched/dirty state for the local validation pass.
 */
type Props = {
    name: string;
    label?: React.ReactNode;
    optional?: boolean;
    defaultValue?: string | null;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    /** Server-side error from Laravel/Inertia. */
    error?: string;
    id?: string;
    autoComplete?: string;
};

export function EmailField({
    name,
    label,
    optional = false,
    defaultValue,
    required = false,
    disabled = false,
    placeholder,
    error,
    id,
    autoComplete = 'email',
}: Props) {
    const autoId = useId();
    const inputId = id ?? `email-${autoId}`;

    const [value, setValue] = useState<string>(defaultValue ?? '');
    const [touched, setTouched] = useState(false);

    // Empty + optional → no client error (server handles required). Empty +
    // required → also defer to the server so we don't double-message.
    // Non-empty + malformed → flag locally.
    const clientError =
        touched && value !== '' && !isValidEmail(value)
            ? "That doesn't look like a valid email address."
            : null;

    // Server error wins over client error when both are present —
    // server has authoritative context (e.g. uniqueness).
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
            <Input
                id={inputId}
                name={name}
                type="email"
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onBlur={() => setTouched(true)}
                required={required}
                disabled={disabled}
                placeholder={placeholder}
                autoComplete={autoComplete}
                aria-invalid={!!displayedError}
            />
        </div>
    );
}
