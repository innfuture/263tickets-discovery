import { Text } from '@atlaskit/primitives';
import { Modal } from './Modal';

export interface ConfirmDialogProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    isDangerous?: boolean;
    isLoading?: boolean;
    testId?: string;
}

/**
 * Standard yes/no confirmation. Use this instead of native window.confirm
 * everywhere — accessible, themed, focus-trapped.
 */
export function ConfirmDialog({
    isOpen,
    onClose,
    onConfirm,
    title,
    message,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
    isDangerous = false,
    isLoading = false,
    testId,
}: ConfirmDialogProps) {
    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={title}
            width="small"
            appearance={isDangerous ? 'danger' : undefined}
            testId={testId}
            actions={[
                {
                    label: confirmLabel,
                    onClick: onConfirm,
                    variant: isDangerous ? 'danger' : 'primary',
                    isLoading,
                },
                {
                    label: cancelLabel,
                    onClick: onClose,
                    variant: 'secondary',
                    isDisabled: isLoading,
                },
            ]}
        >
            <Text>{message}</Text>
        </Modal>
    );
}
