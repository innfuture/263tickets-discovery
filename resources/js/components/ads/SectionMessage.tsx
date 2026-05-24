import AKSectionMessage, { SectionMessageAction } from '@atlaskit/section-message';
import type { ReactNode } from 'react';

export type SectionMessageAppearance = 'information' | 'warning' | 'error' | 'success' | 'discovery';

export interface SectionMessageActionDef {
    text: string;
    onClick?: () => void;
    href?: string;
}

export interface SectionMessageProps {
    title?: string;
    appearance?: SectionMessageAppearance;
    children?: ReactNode;
    actions?: SectionMessageActionDef[];
    testId?: string;
}

/**
 * Block-level message — page banners, inline form notices. For
 * transient feedback after an action use a Toast (Flag). For tiny
 * inline annotations use InlineMessage.
 */
export function SectionMessage({ title, appearance = 'information', children, actions, testId }: SectionMessageProps) {
    return (
        <AKSectionMessage
            title={title}
            appearance={appearance}
            actions={actions?.map((a) => (
                <SectionMessageAction key={a.text} onClick={a.onClick} href={a.href}>
                    {a.text}
                </SectionMessageAction>
            ))}
            testId={testId}
        >
            {children}
        </AKSectionMessage>
    );
}
