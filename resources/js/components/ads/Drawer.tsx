import AKDrawer from '@atlaskit/drawer';
import type { ReactNode } from 'react';

export type DrawerWidth = 'narrow' | 'medium' | 'wide' | 'extended' | 'full';

export interface DrawerProps {
    isOpen: boolean;
    onClose: () => void;
    children: ReactNode;
    width?: DrawerWidth;
    label?: string;
    testId?: string;
}

/**
 * Side-anchored panel for progressive disclosure of detail — Jira
 * issue panel style. For modal-blocking confirmations use Modal
 * instead; Drawer is for "show me more about this row" flows.
 *
 * Modern @atlaskit/drawer dropped `shouldCloseOnEscapePress`,
 * `shouldCloseOnOverlayClick`, and `shouldUnmountOnExit` props.
 */
export function Drawer({ isOpen, onClose, children, width = 'medium', label = 'Detail panel', testId }: DrawerProps) {
    return (
        <AKDrawer isOpen={isOpen} onClose={onClose} width={width} label={label} testId={testId}>
            {children}
        </AKDrawer>
    );
}
