/**
 * Central reference map for the Atlassian Design System (ADS) tokens
 * used in this codebase. Import from here anywhere you need a token
 * string in JS/TS — that way a token rename in @atlaskit/tokens is
 * fixed in one file instead of grep-and-replace across the app.
 *
 * For prop-level styling, prefer @atlaskit/primitives (Box, Stack,
 * Inline) which accept token strings directly. This map is for the
 * edge cases where you need an inline style or a CSS-in-JS value.
 *
 * @see https://atlassian.design/components/tokens/all-tokens
 */
export const ds = {
    // ── COLOR ────────────────────────────────────────────────────────────
    color: {
        text: {
            default: 'var(--ds-text)',
            subtle: 'var(--ds-text-subtle)',
            subtlest: 'var(--ds-text-subtlest)',
            disabled: 'var(--ds-text-disabled)',
            inverse: 'var(--ds-text-inverse)',
            success: 'var(--ds-text-success)',
            warning: 'var(--ds-text-warning)',
            danger: 'var(--ds-text-danger)',
            discovery: 'var(--ds-text-discovery)',
            information: 'var(--ds-text-information)',
            brand: 'var(--ds-text-brand)',
            selected: 'var(--ds-text-selected)',
        },
        background: {
            default: 'var(--ds-background-default)',
            sunken: 'var(--ds-background-sunken)',
            input: 'var(--ds-background-input)',
            inputHovered: 'var(--ds-background-input-hovered)',
            inputPressed: 'var(--ds-background-input-pressed)',
            neutral: 'var(--ds-background-neutral)',
            neutralHovered: 'var(--ds-background-neutral-hovered)',
            neutralPressed: 'var(--ds-background-neutral-pressed)',
            neutralSubtle: 'var(--ds-background-neutral-subtle)',
            boldNeutral: 'var(--ds-background-neutral-bold)',
            brand: 'var(--ds-background-brand-bold)',
            brandHovered: 'var(--ds-background-brand-bold-hovered)',
            brandPressed: 'var(--ds-background-brand-bold-pressed)',
            brandSubtle: 'var(--ds-background-brand-subtlest)',
            selected: 'var(--ds-background-selected)',
            selectedHovered: 'var(--ds-background-selected-hovered)',
            danger: 'var(--ds-background-danger-bold)',
            dangerSubtle: 'var(--ds-background-danger)',
            warning: 'var(--ds-background-warning-bold)',
            warningSubtle: 'var(--ds-background-warning)',
            success: 'var(--ds-background-success-bold)',
            successSubtle: 'var(--ds-background-success)',
            discovery: 'var(--ds-background-discovery-bold)',
            discoverySubtle: 'var(--ds-background-discovery)',
            information: 'var(--ds-background-information-bold)',
            informationSubtle: 'var(--ds-background-information)',
        },
        border: {
            default: 'var(--ds-border)',
            bold: 'var(--ds-border-bold)',
            inverse: 'var(--ds-border-inverse)',
            focused: 'var(--ds-border-focused)',
            input: 'var(--ds-border-input)',
            disabled: 'var(--ds-border-disabled)',
            selected: 'var(--ds-border-selected)',
            brand: 'var(--ds-border-brand)',
            danger: 'var(--ds-border-danger)',
            warning: 'var(--ds-border-warning)',
            success: 'var(--ds-border-success)',
        },
        icon: {
            default: 'var(--ds-icon)',
            subtle: 'var(--ds-icon-subtle)',
            inverse: 'var(--ds-icon-inverse)',
            disabled: 'var(--ds-icon-disabled)',
            brand: 'var(--ds-icon-brand)',
            selected: 'var(--ds-icon-selected)',
            danger: 'var(--ds-icon-danger)',
            warning: 'var(--ds-icon-warning)',
            success: 'var(--ds-icon-success)',
            discovery: 'var(--ds-icon-discovery)',
            information: 'var(--ds-icon-information)',
        },
        link: {
            default: 'var(--ds-link)',
            pressed: 'var(--ds-link-pressed)',
            visited: 'var(--ds-link-visited)',
        },
    },

    // ── SPACING ──────────────────────────────────────────────────────────
    // ADS spacing is on a 4-pt scale. Numeric keys are minor-unit pixels.
    space: {
        0: 'var(--ds-space-0)', //   0px
        25: 'var(--ds-space-025)', //   2px
        50: 'var(--ds-space-050)', //   4px
        75: 'var(--ds-space-075)', //   6px
        100: 'var(--ds-space-100)', //   8px
        150: 'var(--ds-space-150)', //  12px
        200: 'var(--ds-space-200)', //  16px
        250: 'var(--ds-space-250)', //  20px
        300: 'var(--ds-space-300)', //  24px
        400: 'var(--ds-space-400)', //  32px
        500: 'var(--ds-space-500)', //  40px
        600: 'var(--ds-space-600)', //  48px
        800: 'var(--ds-space-800)', //  64px
        1000: 'var(--ds-space-1000)', //  80px
    },

    // ── ELEVATION ────────────────────────────────────────────────────────
    elevation: {
        surface: 'var(--ds-elevation-surface)',
        raised: 'var(--ds-elevation-surface-raised)',
        overlay: 'var(--ds-elevation-surface-overlay)',
        sunken: 'var(--ds-elevation-surface-sunken)',
        shadow: {
            raised: 'var(--ds-shadow-raised)',
            overlay: 'var(--ds-shadow-overlay)',
            overflow: 'var(--ds-shadow-overflow)',
        },
    },

    // ── TYPOGRAPHY ───────────────────────────────────────────────────────
    font: {
        family: {
            body: 'var(--ds-font-family-body)',
            heading: 'var(--ds-font-family-heading)',
            code: 'var(--ds-font-family-code)',
            brand: 'var(--ds-font-family-brand-body)',
        },
        size: {
            XXXXL: 'var(--ds-font-size-600)', // 38px
            XXXL: 'var(--ds-font-size-500)', // 30px
            XXL: 'var(--ds-font-size-400)', // 24px
            XL: 'var(--ds-font-size-300)', // 20px
            L: 'var(--ds-font-size-200)', // 16px (body default)
            M: 'var(--ds-font-size-100)', // 14px
            S: 'var(--ds-font-size-075)', // 13px
            XS: 'var(--ds-font-size-050)', // 12px
        },
        weight: {
            regular: 'var(--ds-font-weight-regular)', // 400
            medium: 'var(--ds-font-weight-medium)', // 500
            semibold: 'var(--ds-font-weight-semibold)', // 600
            bold: 'var(--ds-font-weight-bold)', // 700
        },
        lineHeight: {
            none: 1,
            tight: 'var(--ds-font-lineHeight-1)',
            snug: 'var(--ds-font-lineHeight-2)',
            normal: 'var(--ds-font-lineHeight-3)',
            relaxed: 'var(--ds-font-lineHeight-4)',
            loose: 'var(--ds-font-lineHeight-5)',
        },
    },

    // ── BORDER RADIUS ────────────────────────────────────────────────────
    border: {
        radius: {
            none: '0',
            sm: 'var(--ds-border-radius)', // 3px
            md: 'var(--ds-border-radius-200)', // 8px
            lg: 'var(--ds-border-radius-400)', // 12px (cards)
            xl: 'var(--ds-border-radius-800)', // 24px (pills)
            circle: 'var(--ds-border-radius-circle)', // 50%
        },
        width: {
            0: '0',
            default: 'var(--ds-border-width)', // 1px
            outline: 'var(--ds-border-width-outline)', // 2px
        },
    },

    // ── OPACITY ──────────────────────────────────────────────────────────
    opacity: {
        disabled: 'var(--ds-opacity-disabled)', // 0.4
        loading: 'var(--ds-opacity-loading)', // 0.65
        transparent: 'var(--ds-opacity-transparent)', // 0
    },
} as const;

export type DSTokens = typeof ds;
