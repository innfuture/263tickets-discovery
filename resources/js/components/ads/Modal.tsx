import AKModal, {
    ModalBody,
    ModalFooter,
    ModalHeader,
    ModalTitle,
    ModalTransition,
} from '@atlaskit/modal-dialog';
import type { ReactNode } from 'react';
import { Button, type ButtonVariant } from './Button';

export type ModalWidth = 'small' | 'medium' | 'large' | 'x-large' | number;
export type ModalAppearance = 'danger' | 'warning';

export interface ModalAction {
    label: string;
    onClick: () => void;
    variant?: ButtonVariant;
    isLoading?: boolean;
    isDisabled?: boolean;
}

export interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    width?: ModalWidth;
    appearance?: ModalAppearance;
    actions?: ModalAction[];
    hasTitleBar?: boolean;
    shouldScrollInViewport?: boolean;
    shouldCloseOnEscapePress?: boolean;
    shouldCloseOnOverlayClick?: boolean;
    testId?: string;
}

export function Modal({
    isOpen,
    onClose,
    title,
    children,
    width = 'medium',
    appearance,
    actions = [],
    hasTitleBar = true,
    shouldScrollInViewport,
    shouldCloseOnEscapePress = true,
    shouldCloseOnOverlayClick = true,
    testId,
}: ModalProps) {
    return (
        <ModalTransition>
            {isOpen && (
                <AKModal
                    onClose={onClose}
                    width={width}
                    shouldScrollInViewport={shouldScrollInViewport}
                    shouldCloseOnEscapePress={shouldCloseOnEscapePress}
                    shouldCloseOnOverlayClick={shouldCloseOnOverlayClick}
                    testId={testId}
                >
                    {hasTitleBar && (
                        <ModalHeader>
                            <ModalTitle appearance={appearance}>{title}</ModalTitle>
                        </ModalHeader>
                    )}
                    <ModalBody>{children}</ModalBody>
                    {actions.length > 0 && (
                        <ModalFooter>
                            {actions.map((action, i) => (
                                <Button
                                    key={`${action.label}-${i}`}
                                    variant={action.variant ?? (i === 0 ? 'primary' : 'secondary')}
                                    onClick={action.onClick}
                                    isLoading={action.isLoading}
                                    isDisabled={action.isDisabled}
                                >
                                    {action.label}
                                </Button>
                            ))}
                        </ModalFooter>
                    )}
                </AKModal>
            )}
        </ModalTransition>
    );
}
