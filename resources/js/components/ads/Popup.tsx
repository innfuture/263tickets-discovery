import AKPopup from '@atlaskit/popup';
import type { ReactElement, ReactNode } from 'react';

export type PopupPlacement =
    | 'top'
    | 'top-start'
    | 'top-end'
    | 'bottom'
    | 'bottom-start'
    | 'bottom-end'
    | 'left'
    | 'left-start'
    | 'left-end'
    | 'right'
    | 'right-start'
    | 'right-end';

export interface PopupProps {
    isOpen: boolean;
    onClose: () => void;
    trigger: (triggerProps: object) => ReactElement;
    content: (api: { setInitialFocusRef: (el: HTMLElement | null) => void }) => ReactNode;
    placement?: PopupPlacement;
    shouldRenderToParent?: boolean;
    testId?: string;
}

/**
 * Positioned popover. Use for filter dropdowns, color pickers, etc.
 * For action menus, use DropdownMenu instead — it adds keyboard +
 * roving-focus semantics on top of this primitive.
 */
export function Popup({ trigger, content, placement = 'bottom-start', ...rest }: PopupProps) {
    return <AKPopup trigger={trigger} content={content} placement={placement} {...rest} />;
}
