import { SimpleTag, default as RemovableTag } from '@atlaskit/tag';
import type { ReactNode } from 'react';

/**
 * Modern @atlaskit/tag color palette — superset of the legacy names.
 */
export type TagColor =
    | 'standard'
    | 'green'
    | 'greenLight'
    | 'blue'
    | 'blueLight'
    | 'red'
    | 'redLight'
    | 'purple'
    | 'purpleLight'
    | 'teal'
    | 'tealLight'
    | 'yellow'
    | 'yellowLight'
    | 'orange'
    | 'orangeLight'
    | 'magenta'
    | 'magentaLight'
    | 'grey'
    | 'greyLight'
    | 'lime'
    | 'limeLight';

export interface TagProps {
    text: string;
    color?: TagColor;
    href?: string;
    onRemove?: () => void;
    elemBefore?: ReactNode;
    testId?: string;
}

/**
 * Renders the removable variant when `onRemove` is supplied;
 * otherwise the non-interactive SimpleTag.
 */
export function Tag({ text, color = 'standard', href, onRemove, elemBefore, testId }: TagProps) {
    if (onRemove) {
        return (
            <RemovableTag
                text={text}
                color={color}
                href={href}
                onAfterRemoveAction={onRemove}
                elemBefore={elemBefore}
                testId={testId}
            />
        );
    }

    return <SimpleTag text={text} color={color} href={href} elemBefore={elemBefore} testId={testId} />;
}
