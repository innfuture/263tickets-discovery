import AKSpinner from '@atlaskit/spinner';

export type SpinnerSize = 'xsmall' | 'small' | 'medium' | 'large' | 'xlarge';
export type SpinnerAppearance = 'inherit' | 'invert';

export interface SpinnerProps {
    size?: SpinnerSize;
    appearance?: SpinnerAppearance;
    label?: string;
    testId?: string;
}

export function Spinner({ size = 'medium', appearance = 'inherit', label = 'Loading', testId }: SpinnerProps) {
    return <AKSpinner size={size} appearance={appearance} label={label} testId={testId} />;
}
