import AKInlineMessage from '@atlaskit/inline-message';
import type { ReactNode } from 'react';

export type InlineMessageAppearance = 'info' | 'warning' | 'error' | 'confirmation' | 'connectivity';

export interface InlineMessageProps {
    title?: ReactNode;
    secondaryText?: ReactNode;
    appearance?: InlineMessageAppearance;
    iconLabel?: string;
    children?: ReactNode;
    testId?: string;
}

/**
 * Tiny inline annotation — sits next to a field/label with details
 * revealed on click. Use sparingly; SectionMessage is better for
 * anything the user needs to read up-front.
 */
export function InlineMessage({ title, secondaryText, appearance = 'info', iconLabel, children, testId }: InlineMessageProps) {
    return (
        <AKInlineMessage
            title={title}
            secondaryText={secondaryText}
            appearance={appearance}
            iconLabel={iconLabel}
            testId={testId}
        >
            {children}
        </AKInlineMessage>
    );
}
