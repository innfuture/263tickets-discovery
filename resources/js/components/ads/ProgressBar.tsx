import AKProgressBar from '@atlaskit/progress-bar';

export type ProgressBarAppearance = 'default' | 'success' | 'inverse';

export interface ProgressBarProps {
    /** 0–1 fraction. Use isIndeterminate for unknown duration. */
    value?: number;
    isIndeterminate?: boolean;
    ariaLabel?: string;
    appearance?: ProgressBarAppearance;
    testId?: string;
}

export function ProgressBar({ value, isIndeterminate, ariaLabel = 'Progress', appearance = 'default', testId }: ProgressBarProps) {
    return (
        <AKProgressBar
            value={value}
            isIndeterminate={isIndeterminate}
            ariaLabel={ariaLabel}
            appearance={appearance}
            testId={testId}
        />
    );
}
