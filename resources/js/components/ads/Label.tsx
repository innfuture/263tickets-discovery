import type { ReactNode } from 'react';

export interface LabelProps {
    htmlFor?: string;
    children: ReactNode;
    isRequired?: boolean;
    testId?: string;
}

/**
 * Standalone label using ADS typography tokens. For form fields,
 * prefer TextField / Textarea / Select wrappers which include the
 * label via @atlaskit/form's Field component (correct a11y wiring).
 */
export function Label({ htmlFor, children, isRequired, testId }: LabelProps) {
    return (
        <label
            htmlFor={htmlFor}
            data-testid={testId}
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: 'var(--ds-space-050)',
                fontSize: 'var(--ds-font-size-075)',
                fontWeight: 'var(--ds-font-weight-semibold)' as never,
                color: 'var(--ds-text)',
                lineHeight: 1,
            }}
        >
            {children}
            {isRequired ? (
                <span aria-hidden="true" style={{ color: 'var(--ds-text-danger)' }}>
                    *
                </span>
            ) : null}
        </label>
    );
}
