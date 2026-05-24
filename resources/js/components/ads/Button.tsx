import AKButton, { IconButton as AKIconButton } from '@atlaskit/button/new';
import type { MouseEvent, ReactNode } from 'react';

/**
 * 263Tickets-flavoured wrapper around @atlaskit/button (new entry).
 * Uses our own `variant` vocabulary instead of Atlaskit's `appearance`
 * so a future SDK rename doesn't ripple across the app.
 *
 * Loading state is handled inline on the new button (no separate
 * LoadingButton export in this generation of the package).
 */

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'subtle' | 'warning' | 'discovery';
export type ButtonSize = 'default' | 'compact';

type Appearance = 'primary' | 'default' | 'danger' | 'subtle' | 'warning' | 'discovery';

const appearanceMap: Record<ButtonVariant, Appearance> = {
    primary: 'primary',
    secondary: 'default',
    danger: 'danger',
    subtle: 'subtle',
    warning: 'warning',
    discovery: 'discovery',
};

export interface ButtonProps {
    variant?: ButtonVariant;
    size?: ButtonSize;
    isLoading?: boolean;
    isDisabled?: boolean;
    iconBefore?: ReactNode;
    iconAfter?: ReactNode;
    children?: ReactNode;
    onClick?: (e: MouseEvent<HTMLButtonElement>) => void;
    type?: 'button' | 'submit' | 'reset';
    autoFocus?: boolean;
    fullWidth?: boolean;
    testId?: string;
    'aria-label'?: string;
}

export function Button({
    variant = 'secondary',
    size = 'default',
    isLoading = false,
    isDisabled,
    iconBefore,
    iconAfter,
    children,
    onClick,
    type = 'button',
    autoFocus,
    fullWidth,
    testId,
    'aria-label': ariaLabel,
}: ButtonProps) {
    return (
        <AKButton
            appearance={appearanceMap[variant]}
            spacing={size === 'compact' ? 'compact' : 'default'}
            isDisabled={isDisabled}
            isLoading={isLoading}
            iconBefore={iconBefore as never}
            iconAfter={iconAfter as never}
            onClick={onClick}
            type={type}
            autoFocus={autoFocus}
            shouldFitContainer={fullWidth}
            testId={testId}
            aria-label={ariaLabel}
        >
            {children}
        </AKButton>
    );
}

// IconButton is more restrictive than full Button — only the four
// quiet appearances support a square icon-only render.
export type IconButtonVariant = 'primary' | 'secondary' | 'subtle' | 'discovery';

const iconAppearanceMap: Record<IconButtonVariant, 'primary' | 'default' | 'subtle' | 'discovery'> = {
    primary: 'primary',
    secondary: 'default',
    subtle: 'subtle',
    discovery: 'discovery',
};

export interface IconButtonProps {
    icon: ReactNode;
    label: string;
    variant?: IconButtonVariant;
    size?: ButtonSize;
    isDisabled?: boolean;
    onClick?: (e: MouseEvent<HTMLButtonElement>) => void;
    testId?: string;
}

export function IconButton({
    icon,
    label,
    variant = 'subtle',
    size = 'default',
    isDisabled,
    onClick,
    testId,
}: IconButtonProps) {
    return (
        <AKIconButton
            appearance={iconAppearanceMap[variant]}
            spacing={size === 'compact' ? 'compact' : 'default'}
            icon={icon as never}
            label={label}
            isDisabled={isDisabled}
            onClick={onClick}
            testId={testId}
        />
    );
}
