import { Code as AKCode, CodeBlock as AKCodeBlock, type SupportedLanguages } from '@atlaskit/code';
import type { ReactNode } from 'react';

export interface CodeProps {
    children: ReactNode;
    testId?: string;
}

/** Inline `<code>` styling. */
export function Code({ children, testId }: CodeProps) {
    return <AKCode testId={testId}>{children as string}</AKCode>;
}

export interface CodeBlockProps {
    text: string;
    language?: SupportedLanguages;
    showLineNumbers?: boolean;
    shouldWrapLongLines?: boolean;
    highlight?: string;
    testId?: string;
}

export function CodeBlock({ text, language = 'text', showLineNumbers, shouldWrapLongLines, highlight, testId }: CodeBlockProps) {
    return (
        <AKCodeBlock
            text={text}
            language={language}
            showLineNumbers={showLineNumbers}
            shouldWrapLongLines={shouldWrapLongLines}
            highlight={highlight}
            testId={testId}
        />
    );
}
