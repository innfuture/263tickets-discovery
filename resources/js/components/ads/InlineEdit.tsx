import AKInlineEdit from '@atlaskit/inline-edit';
import type { ReactNode } from 'react';

export interface InlineEditProps<T = string> {
    defaultValue: T;
    label?: string;
    editView: (fieldProps: object, ref: React.Ref<HTMLInputElement>) => ReactNode;
    readView: () => ReactNode;
    onConfirm: (value: T) => void;
    onCancel?: () => void;
    hideActionButtons?: boolean;
    isRequired?: boolean;
    keepEditViewOpenOnBlur?: boolean;
    readViewFitContainerWidth?: boolean;
    testId?: string;
}

/**
 * Jira-style inline editing — click read-view → swap to edit-view →
 * save on blur (or via the explicit save button when hideActionButtons
 * is false).
 */
export function InlineEdit<T = string>(props: InlineEditProps<T>) {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const Comp = AKInlineEdit as any;
    return <Comp {...props} />;
}
