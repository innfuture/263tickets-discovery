import AKTooltip from '@atlaskit/tooltip';
import type { ReactElement, ReactNode } from 'react';

export type TooltipPosition = 'top' | 'right' | 'bottom' | 'left' | 'mouse';

export interface TooltipProps {
    content: ReactNode;
    children: ReactElement;
    position?: TooltipPosition;
    delay?: number;
    hideTooltipOnClick?: boolean;
    testId?: string;
}

export function Tooltip({ content, children, position = 'top', delay = 300, hideTooltipOnClick, testId }: TooltipProps) {
    return (
        <AKTooltip content={content} position={position} delay={delay} hideTooltipOnClick={hideTooltipOnClick} testId={testId}>
            {children}
        </AKTooltip>
    );
}
