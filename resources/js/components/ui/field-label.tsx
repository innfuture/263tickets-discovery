import type { ReactNode } from 'react';
import { FieldError } from '@/components/field-error';
import { Label } from '@/components/ui/label';

/**
 * Standard form-field header — label on the left, the validation
 * indicator (a tooltipped alert icon from FieldError) on the right.
 * This is the only sanctioned place to render server-side errors;
 * keeping it inline with the label avoids tall "error" strips below
 * the input and matches the create-event-modal pattern used across
 * the rest of the app.
 */
export function FieldLabel({
    htmlFor,
    error,
    children,
    optional,
}: {
    htmlFor?: string;
    error?: string | null;
    children: ReactNode;
    optional?: boolean;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <Label htmlFor={htmlFor}>
                {children}
                {optional ? (
                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                        (optional)
                    </span>
                ) : null}
            </Label>
            <FieldError message={error} />
        </div>
    );
}
