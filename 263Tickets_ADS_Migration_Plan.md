# 263Tickets — Complete Atlassian Design System Migration Plan
### Full Removal of shadcn/ui & Tailwind CSS · Jira-like UX/UI · Claude Code Ready

---

## Table of Contents

1. [Executive Overview](#1-executive-overview)
2. [ADS Philosophy & Jira Design Principles](#2-ads-philosophy--jira-design-principles)
3. [Repository & Toolchain Setup](#3-repository--toolchain-setup)
4. [Foundations Layer: Tokens, Typography, Color, Spacing, Motion, Icons](#4-foundations-layer)
5. [Component Architecture & Wrapper Strategy](#5-component-architecture--wrapper-strategy)
6. [Full Component Library Mapping](#6-full-component-library-mapping)
7. [Layout System (Jira-like Shell)](#7-layout-system-jira-like-shell)
8. [Navigation System](#8-navigation-system)
9. [Forms & Data Entry](#9-forms--data-entry)
10. [Data Display & Tables](#10-data-display--tables)
11. [Messaging & Feedback](#11-messaging--feedback)
12. [Overlays, Drawers & Modals](#12-overlays-drawers--modals)
13. [Status & Indicator Components](#13-status--indicator-components)
14. [Motion & Animation System](#14-motion--animation-system)
15. [Iconography System](#15-iconography-system)
16. [Theming: Light, Dark & High-Contrast](#16-theming-light-dark--high-contrast)
17. [Accessibility Standards](#17-accessibility-standards)
18. [Inertia.js Integration Notes](#18-inertiajs-integration-notes)
19. [Removal of Tailwind CSS & shadcn/ui](#19-removal-of-tailwind-css--shadcnui)
20. [Storybook & Component Documentation](#20-storybook--component-documentation)
21. [File & Folder Structure (Final State)](#21-file--folder-structure-final-state)
22. [Phased Execution Plan](#22-phased-execution-plan)
23. [Claude Code Prompt Templates](#23-claude-code-prompt-templates)

---

## 1. Executive Overview

**Goal:** Completely replace shadcn/ui + Tailwind CSS with the Atlassian Design System (ADS / Atlaskit) across the entire 263Tickets Laravel + React + Inertia.js application, producing a Jira-quality UX/UI that is scalable, accessible, themeable, and maintainable.

**Key Outcomes:**
- 100% ADS token-driven styling (zero raw hex values, zero arbitrary CSS classes)
- Jira-equivalent shell layout: top navigation bar, collapsible side navigation, content canvas
- Full Atlaskit component library adoption across all UI surfaces
- WCAG AA accessibility compliance baked in at foundation level
- Light mode (default), dark mode, and high-contrast mode support
- Zero Tailwind classes remaining anywhere in the codebase
- Zero shadcn/ui component imports remaining anywhere in the codebase
- A private wrapper component layer (`/resources/js/components/ui/`) that proxies all Atlaskit components

**Stack:**
```
Laravel 11+         — backend / routing / Inertia responses
Inertia.js v2/v3    — SPA adapter
React 18+           — frontend framework
Vite 5+             — build tool
@atlaskit/*         — entire UI component suite
@atlaskit/tokens    — design token system
@compiled/react     — CSS-in-JS engine (required by ADS)
```

---

## 2. ADS Philosophy & Jira Design Principles

### 2.1 The ADS Mental Model

The Atlassian Design System is **not** a component library you drop in. It is a **layered system**:

```
┌──────────────────────────────────┐
│         PRODUCT (263Tickets)     │  ← your pages & features
├──────────────────────────────────┤
│      WRAPPER COMPONENTS          │  ← your /components/ui/ layer
├──────────────────────────────────┤
│    @atlaskit/* COMPONENTS        │  ← Atlaskit packages
├──────────────────────────────────┤
│    @atlaskit/primitives          │  ← Box, Stack, Inline, Text
├──────────────────────────────────┤
│    @atlaskit/tokens              │  ← CSS custom properties
├──────────────────────────────────┤
│    @atlaskit/css-reset           │  ← baseline browser reset
└──────────────────────────────────┘
```

Every layer is intentional. **Never skip the wrapper layer** — it's what makes the system maintainable when Atlaskit releases breaking changes.

### 2.2 Jira UX/UI Design Principles to Apply

| Principle | Implementation |
|---|---|
| **Calm interface** | High information density without visual noise. Neutral backgrounds, subtle borders |
| **Status at a glance** | Heavy use of Lozenge, Badge, Tag for state communication |
| **Keyboard-first** | Every interaction reachable via keyboard. Focus rings always visible |
| **Progressive disclosure** | Details in drawers/modals, not inline. Collapsed sections by default |
| **Contextual actions** | Dropdown menus and inline edit, never exposing all controls at once |
| **Data density** | Compact spacing variants. Information-rich tables with sorting/filtering |
| **Predictable navigation** | Fixed top bar + fixed side nav. Content area scrolls, chrome stays still |
| **Feedback immediacy** | Flags (toasts) for async operations. Spinners inline, never full-page |
| **Empty states** | EmptyState component on every list/table that can be empty |
| **Inline editing** | InlineEdit component for field-level edits on detail pages |

---

## 3. Repository & Toolchain Setup

### 3.1 Remove Tailwind & shadcn/ui

```bash
# Remove Tailwind
npm uninstall tailwindcss postcss autoprefixer @tailwindcss/forms @tailwindcss/typography

# Remove shadcn/ui and its deps
npm uninstall @radix-ui/react-dialog @radix-ui/react-dropdown-menu \
  @radix-ui/react-select @radix-ui/react-checkbox @radix-ui/react-label \
  @radix-ui/react-separator @radix-ui/react-slot @radix-ui/react-toast \
  class-variance-authority clsx tailwind-merge lucide-react cmdk \
  @radix-ui/react-popover @radix-ui/react-scroll-area

# Delete shadcn config files
rm -f tailwind.config.js tailwind.config.ts postcss.config.js components.json
```

### 3.2 Install the ADS Foundation

```bash
# === FOUNDATION (install first, everything depends on these) ===
npm install @atlaskit/tokens @atlaskit/css-reset @atlaskit/app-provider

# === PRIMITIVES (layout building blocks) ===
npm install @atlaskit/primitives

# === COMPILED CSS-IN-JS (required by ADS components) ===
npm install @compiled/react

# === TYPOGRAPHY ===
npm install @atlaskit/heading

# === MOTION ===
npm install @atlaskit/motion

# === ICONS ===
npm install @atlaskit/icon @atlaskit/icon-lab

# === NAVIGATION ===
npm install @atlaskit/navigation-system @atlaskit/page-layout \
  @atlaskit/side-navigation @atlaskit/atlassian-navigation \
  @atlaskit/breadcrumbs @atlaskit/tabs @atlaskit/menu @atlaskit/pagination

# === FORMS & INPUT ===
npm install @atlaskit/button @atlaskit/form @atlaskit/textfield \
  @atlaskit/textarea @atlaskit/select @atlaskit/checkbox \
  @atlaskit/radio @atlaskit/toggle @atlaskit/range \
  @atlaskit/datetime-picker @atlaskit/calendar \
  @atlaskit/dropdown-menu @atlaskit/focus-ring \
  @atlaskit/inline-edit

# === DATA DISPLAY ===
npm install @atlaskit/dynamic-table @atlaskit/table \
  @atlaskit/code @atlaskit/visually-hidden

# === STATUS & INDICATORS ===
npm install @atlaskit/badge @atlaskit/lozenge @atlaskit/tag \
  @atlaskit/tag-group @atlaskit/progress-bar \
  @atlaskit/progress-indicator @atlaskit/progress-tracker \
  @atlaskit/empty-state @atlaskit/avatar @atlaskit/avatar-group

# === MESSAGING & FEEDBACK ===
npm install @atlaskit/flag @atlaskit/banner @atlaskit/section-message \
  @atlaskit/inline-message @atlaskit/spinner @atlaskit/skeleton

# === OVERLAYS ===
npm install @atlaskit/modal-dialog @atlaskit/drawer \
  @atlaskit/popup @atlaskit/tooltip @atlaskit/blanket \
  @atlaskit/portal @atlaskit/spotlight

# === DRAG & DROP (for ticket boards) ===
npm install @atlaskit/pragmatic-drag-and-drop \
  @atlaskit/pragmatic-drag-and-drop-hitbox \
  @atlaskit/pragmatic-drag-and-drop-react-beautiful-dnd-migration

# === IMAGES & LOGOS ===
npm install @atlaskit/logo @atlaskit/image

# === TOOLING ===
npm install --save-dev @atlaskit/eslint-plugin-design-system \
  @atlaskit/stylelint-design-system \
  @atlaskit/storybook-addon-design-system
```

### 3.3 Vite Configuration

```typescript
// vite.config.ts
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.tsx', 'resources/css/app.css'],
      refresh: true,
    }),
    react({
      // Required: Compiled CSS-in-JS babel transform for ADS
      babel: {
        plugins: ['@compiled/babel-plugin'],
      },
    }),
  ],
  optimizeDeps: {
    // Pre-bundle all @atlaskit packages for faster dev server startup
    include: [
      '@atlaskit/tokens',
      '@atlaskit/primitives',
      '@atlaskit/button',
      '@atlaskit/form',
      '@atlaskit/textfield',
      '@atlaskit/select',
      '@atlaskit/dynamic-table',
      '@atlaskit/modal-dialog',
      '@atlaskit/flag',
      '@atlaskit/navigation-system',
      '@atlaskit/side-navigation',
      '@atlaskit/page-layout',
      '@atlaskit/icon',
      '@atlaskit/lozenge',
      '@atlaskit/badge',
      '@atlaskit/spinner',
      '@atlaskit/heading',
    ],
  },
  resolve: {
    alias: {
      '@': '/resources/js',
      '@ui': '/resources/js/components/ui',
      '@ds': '/resources/js/design-system',
    },
  },
});
```

### 3.4 Compiled Babel Plugin Setup

```bash
npm install --save-dev @compiled/babel-plugin
```

```json
// babel.config.json (create if doesn't exist)
{
  "plugins": ["@compiled/babel-plugin"]
}
```

### 3.5 ESLint Configuration for ADS

```json
// .eslintrc.json — add to existing config
{
  "plugins": ["@atlaskit/design-system"],
  "rules": {
    "@atlaskit/design-system/no-banned-imports": "error",
    "@atlaskit/design-system/ensure-design-token-usage": "warn",
    "@atlaskit/design-system/no-unsafe-design-token-usage": "error"
  }
}
```

---

## 4. Foundations Layer

### 4.1 Global Entry Point (`resources/js/app.tsx`)

```tsx
// resources/js/app.tsx
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppProvider from '@atlaskit/app-provider';

// ADS Foundation CSS — import ORDER matters
import '@atlaskit/css-reset/dist/bundle.css';
import '@atlaskit/tokens/css/atlassian-light.css';     // default theme
import '@atlaskit/tokens/css/atlassian-dark.css';       // dark theme
import '@atlaskit/tokens/css/atlassian-light-increased-contrast.css';

// App-level styles (custom properties only, no Tailwind)
import '../css/app.css';

createInertiaApp({
  title: (title) => `${title} — 263Tickets`,
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob('./Pages/**/*.tsx'),
    ),
  setup({ el, App, props }) {
    createRoot(el).render(
      <AppProvider>
        <App {...props} />
      </AppProvider>,
    );
  },
  progress: {
    color: 'var(--ds-background-brand-bold)',
  },
});
```

### 4.2 CSS Custom Properties (`resources/css/app.css`)

```css
/* resources/css/app.css
   ONLY custom properties here — no Tailwind, no raw values
   All visual values MUST come from ADS tokens */

:root {
  /* 263Tickets brand mapped to ADS token system */
  --app-brand-primary: var(--ds-background-brand-bold);
  --app-brand-subtle: var(--ds-background-brand-subtlest);

  /* Surface hierarchy — use these in layout, not raw colors */
  --app-surface-page: var(--ds-background-default);
  --app-surface-card: var(--ds-elevation-surface);
  --app-surface-raised: var(--ds-elevation-surface-raised);
  --app-surface-overlay: var(--ds-elevation-surface-overlay);

  /* Navigation dimensions — Jira-accurate measurements */
  --app-topnav-height: 56px;
  --app-sidenav-width-expanded: 240px;
  --app-sidenav-width-collapsed: 56px;

  /* Z-index scale aligned to ADS layering */
  --z-base: 0;
  --z-card: 100;
  --z-dropdown: 200;
  --z-sticky: 300;
  --z-navigation: 400;
  --z-modal: 500;
  --z-notification: 600;
  --z-tooltip: 700;
}

/* Scrollbar styling consistent with Jira */
* {
  scrollbar-width: thin;
  scrollbar-color: var(--ds-border) transparent;
}

*::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

*::-webkit-scrollbar-thumb {
  background: var(--ds-border);
  border-radius: 4px;
}

/* Body defaults */
body {
  background-color: var(--ds-background-default);
  color: var(--ds-text);
  font-family: var(--ds-font-family-body);
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
```

### 4.3 Token Reference Map (`resources/js/design-system/tokens.ts`)

```typescript
// resources/js/design-system/tokens.ts
// Central reference — import this anywhere you need token strings in JS

export const ds = {
  // ── COLOR ────────────────────────────────────────────────────────────
  color: {
    text: {
      default:    'var(--ds-text)',
      subtle:     'var(--ds-text-subtle)',
      subtlest:   'var(--ds-text-subtlest)',
      disabled:   'var(--ds-text-disabled)',
      inverse:    'var(--ds-text-inverse)',
      success:    'var(--ds-text-success)',
      warning:    'var(--ds-text-warning)',
      danger:     'var(--ds-text-danger)',
      discovery:  'var(--ds-text-discovery)',
      information:'var(--ds-text-information)',
      brand:      'var(--ds-text-brand)',
      selected:   'var(--ds-text-selected)',
    },
    background: {
      default:           'var(--ds-background-default)',
      sunken:            'var(--ds-background-sunken)',
      input:             'var(--ds-background-input)',
      inputHovered:      'var(--ds-background-input-hovered)',
      inputPressed:      'var(--ds-background-input-pressed)',
      neutral:           'var(--ds-background-neutral)',
      neutralHovered:    'var(--ds-background-neutral-hovered)',
      neutralPressed:    'var(--ds-background-neutral-pressed)',
      neutralSubtle:     'var(--ds-background-neutral-subtle)',
      boldNeutral:       'var(--ds-background-neutral-bold)',
      brand:             'var(--ds-background-brand-bold)',
      brandHovered:      'var(--ds-background-brand-bold-hovered)',
      brandPressed:      'var(--ds-background-brand-bold-pressed)',
      brandSubtle:       'var(--ds-background-brand-subtlest)',
      selected:          'var(--ds-background-selected)',
      selectedHovered:   'var(--ds-background-selected-hovered)',
      danger:            'var(--ds-background-danger-bold)',
      dangerSubtle:      'var(--ds-background-danger)',
      warning:           'var(--ds-background-warning-bold)',
      warningSubtle:     'var(--ds-background-warning)',
      success:           'var(--ds-background-success-bold)',
      successSubtle:     'var(--ds-background-success)',
      discovery:         'var(--ds-background-discovery-bold)',
      discoverySubtle:   'var(--ds-background-discovery)',
      information:       'var(--ds-background-information-bold)',
      informationSubtle: 'var(--ds-background-information)',
    },
    border: {
      default:  'var(--ds-border)',
      bold:     'var(--ds-border-bold)',
      inverse:  'var(--ds-border-inverse)',
      focused:  'var(--ds-border-focused)',
      input:    'var(--ds-border-input)',
      disabled: 'var(--ds-border-disabled)',
      selected: 'var(--ds-border-selected)',
      brand:    'var(--ds-border-brand)',
      danger:   'var(--ds-border-danger)',
      warning:  'var(--ds-border-warning)',
      success:  'var(--ds-border-success)',
    },
    icon: {
      default:     'var(--ds-icon)',
      subtle:      'var(--ds-icon-subtle)',
      inverse:     'var(--ds-icon-inverse)',
      disabled:    'var(--ds-icon-disabled)',
      brand:       'var(--ds-icon-brand)',
      selected:    'var(--ds-icon-selected)',
      danger:      'var(--ds-icon-danger)',
      warning:     'var(--ds-icon-warning)',
      success:     'var(--ds-icon-success)',
      discovery:   'var(--ds-icon-discovery)',
      information: 'var(--ds-icon-information)',
    },
    link: {
      default: 'var(--ds-link)',
      pressed: 'var(--ds-link-pressed)',
      visited: 'var(--ds-link-visited)',
    },
  },

  // ── SPACING ──────────────────────────────────────────────────────────
  space: {
    0:    'var(--ds-space-0)',       // 0px
    25:   'var(--ds-space-025)',     // 2px
    50:   'var(--ds-space-050)',     // 4px
    75:   'var(--ds-space-075)',     // 6px
    100:  'var(--ds-space-100)',     // 8px
    150:  'var(--ds-space-150)',     // 12px
    200:  'var(--ds-space-200)',     // 16px
    250:  'var(--ds-space-250)',     // 20px
    300:  'var(--ds-space-300)',     // 24px
    400:  'var(--ds-space-400)',     // 32px
    500:  'var(--ds-space-500)',     // 40px
    600:  'var(--ds-space-600)',     // 48px
    800:  'var(--ds-space-800)',     // 64px
    1000: 'var(--ds-space-1000)',    // 80px
  },

  // ── ELEVATION ────────────────────────────────────────────────────────
  elevation: {
    surface:        'var(--ds-elevation-surface)',
    raised:         'var(--ds-elevation-surface-raised)',
    overlay:        'var(--ds-elevation-surface-overlay)',
    sunken:         'var(--ds-elevation-surface-sunken)',
    shadow: {
      raised:   'var(--ds-shadow-raised)',
      overlay:  'var(--ds-shadow-overlay)',
      overflow: 'var(--ds-shadow-overflow)',
    },
  },

  // ── TYPOGRAPHY ───────────────────────────────────────────────────────
  font: {
    family: {
      body:    'var(--ds-font-family-body)',
      heading: 'var(--ds-font-family-heading)',
      code:    'var(--ds-font-family-code)',
      brand:   'var(--ds-font-family-brand-body)',
    },
    size: {
      // ADS typography scale (rem-based)
      XXXXL: 'var(--ds-font-size-600)',  // 2.375rem / 38px
      XXXL:  'var(--ds-font-size-500)',  // 1.875rem / 30px
      XXL:   'var(--ds-font-size-400)',  // 1.5rem   / 24px
      XL:    'var(--ds-font-size-300)',  // 1.25rem  / 20px
      L:     'var(--ds-font-size-200)',  // 1rem     / 16px (body default)
      M:     'var(--ds-font-size-100)',  // 0.875rem / 14px
      S:     'var(--ds-font-size-075)',  // 0.8125rem/ 13px
      XS:    'var(--ds-font-size-050)',  // 0.75rem  / 12px
    },
    weight: {
      regular: 'var(--ds-font-weight-regular)',   // 400
      medium:  'var(--ds-font-weight-medium)',    // 500
      semibold:'var(--ds-font-weight-semibold)',  // 600
      bold:    'var(--ds-font-weight-bold)',      // 700
    },
    lineHeight: {
      none:    1,
      tight:   'var(--ds-font-lineHeight-1)',   // 1.25
      snug:    'var(--ds-font-lineHeight-2)',   // 1.375
      normal:  'var(--ds-font-lineHeight-3)',   // 1.5
      relaxed: 'var(--ds-font-lineHeight-4)',   // 1.625
      loose:   'var(--ds-font-lineHeight-5)',   // 2
    },
  },

  // ── BORDER RADIUS ────────────────────────────────────────────────────
  border: {
    radius: {
      none:   '0',
      sm:     'var(--ds-border-radius)',         // 3px
      md:     'var(--ds-border-radius-200)',     // 8px
      lg:     'var(--ds-border-radius-400)',     // 12px  (cards)
      xl:     'var(--ds-border-radius-800)',     // 24px  (pills)
      circle: 'var(--ds-border-radius-circle)', // 50%
    },
    width: {
      0: '0',
      default: 'var(--ds-border-width)',           // 1px
      outline: 'var(--ds-border-width-outline)',   // 2px
    },
  },

  // ── OPACITY ──────────────────────────────────────────────────────────
  opacity: {
    disabled:     'var(--ds-opacity-disabled)',    // 0.4
    loading:      'var(--ds-opacity-loading)',     // 0.65
    transparent:  'var(--ds-opacity-transparent)', // 0
  },
} as const;

export type DSTokenPath = typeof ds;
```

---

## 4. Foundations Layer (continued)

### 4.4 Typography System

ADS uses **two font families**:
- **Charlie Sans** — Atlassian's custom brand font (use for headings in marketing contexts)
- **UI System Stack** — `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto` (use for product UI — Jira uses this)

For 263Tickets product UI, follow the Jira approach: **system font stack for body, Inter/Charlie for headings if brand feel is desired.**

Typography is implemented via two ADS components:

```tsx
// HEADINGS — use @atlaskit/heading
import Heading from '@atlaskit/heading';

// Available sizes: xxlarge | xlarge | large | medium | small | xsmall | xxsmall
<Heading size="xlarge">Event Dashboard</Heading>  // h1 equivalent
<Heading size="large">Upcoming Events</Heading>   // h2 equivalent
<Heading size="medium">Ticket Details</Heading>   // h3 equivalent

// BODY TEXT — use @atlaskit/primitives Text
import { Text } from '@atlaskit/primitives';

// Available sizes: large | medium (default) | small | UNSAFE_small
<Text size="large">Primary body text at 16px</Text>
<Text size="medium">Standard body at 14px</Text>
<Text size="small">Secondary / metadata at 12px</Text>
<Text color="color.text.subtle">Subdued text</Text>
<Text weight="semibold">Emphasized text</Text>
```

**Typography Rules:**
- NEVER use raw `<h1>–<h6>` tags without ADS `Heading` or a token-driven style
- NEVER use raw `<p>` or `<span>` for styled text — use `<Text>` from primitives
- NEVER set font-size, font-weight, line-height in raw CSS — only via token variables
- For code blocks, use `@atlaskit/code`

---

## 5. Component Architecture & Wrapper Strategy

### 5.1 The Wrapper Layer (Non-Negotiable)

Every Atlaskit component is wrapped in your own component. This is the most important architectural decision:

```
@atlaskit/button  →  resources/js/components/ui/Button.tsx
@atlaskit/modal-dialog  →  resources/js/components/ui/Modal.tsx
@atlaskit/flag  →  resources/js/components/ui/Toast.tsx
```

**Why:**
1. If Atlaskit renames a prop or breaks an API, you fix 1 file, not 200
2. You can enforce your own prop conventions (e.g. `variant` instead of `appearance`)
3. You can add 263Tickets-specific defaults (e.g. default button spacing for ticket CTAs)
4. Claude Code generates against YOUR API, not Atlaskit's raw API

### 5.2 Wrapper Pattern Template

```tsx
// resources/js/components/ui/_template.tsx

// 1. Import ONLY from @atlaskit — never re-export from shadcn
// 2. Define YOUR prop interface (not Atlaskit's internal types)
// 3. Map YOUR props to Atlaskit's props internally
// 4. Export only the component, no raw Atlaskit re-exports

import type { ReactNode } from 'react';
import AtlaskitComponent from '@atlaskit/some-package';

// YOUR interface — stable even if Atlaskit changes
export interface ComponentProps {
  // ... your props
}

export function Component(props: ComponentProps) {
  // Map and transform props here
  return <AtlaskitComponent {...mappedProps} />;
}
```

---

## 6. Full Component Library Mapping

### 6.1 Button (`resources/js/components/ui/Button.tsx`)

```tsx
import AtlasButton from '@atlaskit/button/new';
import LoadingButton from '@atlaskit/button/loading-button';
import type { ReactNode } from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'subtle' | 'link' | 'warning';
export type ButtonSize = 'default' | 'compact';

interface ButtonProps {
  variant?: ButtonVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  isDisabled?: boolean;
  iconBefore?: ReactNode;
  iconAfter?: ReactNode;
  children?: ReactNode;
  onClick?: (e: React.MouseEvent) => void;
  type?: 'button' | 'submit' | 'reset';
  autoFocus?: boolean;
  testId?: string;
}

const appearanceMap: Record<ButtonVariant, string> = {
  primary:   'primary',
  secondary: 'default',
  danger:    'danger',
  subtle:    'subtle',
  link:      'link',
  warning:   'warning',
};

export function Button({
  variant = 'secondary',
  size = 'default',
  isLoading = false,
  children,
  ...props
}: ButtonProps) {
  const Tag = isLoading ? LoadingButton : AtlasButton;

  return (
    <Tag
      appearance={appearanceMap[variant]}
      spacing={size === 'compact' ? 'compact' : 'default'}
      isLoading={isLoading}
      {...props}
    >
      {children}
    </Tag>
  );
}

// Icon-only button variant
export function IconButton({ icon, label, variant = 'subtle', ...props }: {
  icon: ReactNode;
  label: string;
  variant?: ButtonVariant;
} & Omit<ButtonProps, 'children' | 'iconBefore' | 'iconAfter'>) {
  return (
    <AtlasButton
      appearance={appearanceMap[variant]}
      iconBefore={icon}
      aria-label={label}
      {...props}
    />
  );
}
```

### 6.2 TextField (`resources/js/components/ui/TextField.tsx`)

```tsx
import AtlasTextField from '@atlaskit/textfield';
import { Field, HelperMessage, ErrorMessage, ValidMessage } from '@atlaskit/form';

interface TextFieldProps {
  name: string;
  label: string;
  placeholder?: string;
  defaultValue?: string;
  value?: string;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  isRequired?: boolean;
  isDisabled?: boolean;
  isReadOnly?: boolean;
  isInvalid?: boolean;
  error?: string;
  hint?: string;
  success?: string;
  type?: 'text' | 'email' | 'password' | 'number' | 'url' | 'tel' | 'search';
  autoComplete?: string;
  elemBeforeInput?: ReactNode;
  elemAfterInput?: ReactNode;
  testId?: string;
}

export function TextField({
  name, label, error, hint, success, isInvalid, ...props
}: TextFieldProps) {
  return (
    <Field name={name} label={label} isRequired={props.isRequired}>
      {({ fieldProps }) => (
        <>
          <AtlasTextField
            {...fieldProps}
            {...props}
            isInvalid={isInvalid || !!error}
          />
          {hint && !error && <HelperMessage>{hint}</HelperMessage>}
          {error && <ErrorMessage>{error}</ErrorMessage>}
          {success && <ValidMessage>{success}</ValidMessage>}
        </>
      )}
    </Field>
  );
}
```

### 6.3 Select (`resources/js/components/ui/Select.tsx`)

```tsx
import AtlasSelect from '@atlaskit/select';
import { Field } from '@atlaskit/form';

export interface SelectOption {
  label: string;
  value: string | number;
  isDisabled?: boolean;
}

export interface SelectGroup {
  label: string;
  options: SelectOption[];
}

interface SelectProps {
  name: string;
  label: string;
  options: SelectOption[] | SelectGroup[];
  value?: SelectOption | null;
  defaultValue?: SelectOption | null;
  onChange?: (option: SelectOption | null) => void;
  isMulti?: boolean;
  isSearchable?: boolean;
  isClearable?: boolean;
  isDisabled?: boolean;
  isLoading?: boolean;
  placeholder?: string;
  error?: string;
  hint?: string;
  isRequired?: boolean;
  testId?: string;
}

export function Select({ name, label, error, hint, ...props }: SelectProps) {
  return (
    <Field name={name} label={label} isRequired={props.isRequired}>
      {({ fieldProps }) => (
        <>
          <AtlasSelect
            {...fieldProps}
            {...props}
            validationState={error ? 'error' : 'default'}
            classNamePrefix="ads-select"
          />
          {hint && !error && <HelperMessage>{hint}</HelperMessage>}
          {error && <ErrorMessage>{error}</ErrorMessage>}
        </>
      )}
    </Field>
  );
}
```

### 6.4 Modal (`resources/js/components/ui/Modal.tsx`)

```tsx
import AtlasModal, {
  ModalBody,
  ModalFooter,
  ModalHeader,
  ModalTitle,
  ModalTransition,
} from '@atlaskit/modal-dialog';
import { Button } from './Button';

export type ModalWidth = 'small' | 'medium' | 'large' | 'x-large' | number;

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  width?: ModalWidth;
  actions?: Array<{
    label: string;
    onClick: () => void;
    variant?: ButtonVariant;
    isLoading?: boolean;
    isDisabled?: boolean;
  }>;
  hasTitleBar?: boolean;
  shouldScrollInViewport?: boolean;
  testId?: string;
}

export function Modal({
  isOpen, onClose, title, children, width = 'medium',
  actions = [], hasTitleBar = true, ...props
}: ModalProps) {
  return (
    <ModalTransition>
      {isOpen && (
        <AtlasModal onClose={onClose} width={width} {...props}>
          {hasTitleBar && (
            <ModalHeader>
              <ModalTitle>{title}</ModalTitle>
            </ModalHeader>
          )}
          <ModalBody>{children}</ModalBody>
          {actions.length > 0 && (
            <ModalFooter>
              {actions.map((action, i) => (
                <Button
                  key={i}
                  variant={action.variant ?? (i === 0 ? 'primary' : 'secondary')}
                  onClick={action.onClick}
                  isLoading={action.isLoading}
                  isDisabled={action.isDisabled}
                >
                  {action.label}
                </Button>
              ))}
            </ModalFooter>
          )}
        </AtlasModal>
      )}
    </ModalTransition>
  );
}
```

### 6.5 Toast / Flag (`resources/js/components/ui/Toast.tsx`)

```tsx
import { useFlags, FlagGroup, Flag } from '@atlaskit/flag';
import SuccessIcon from '@atlaskit/icon/glyph/check-circle';
import ErrorIcon from '@atlaskit/icon/glyph/error';
import WarningIcon from '@atlaskit/icon/glyph/warning';
import InfoIcon from '@atlaskit/icon/glyph/info';
import { token } from '@atlaskit/tokens';
import { createContext, useContext, useCallback } from 'react';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastOptions {
  title: string;
  description?: string;
  type?: ToastType;
  duration?: number;   // ms, 0 = persistent
  actions?: Array<{ content: string; onClick: () => void }>;
}

// Global toast context
const ToastContext = createContext<{
  toast: (options: ToastOptions) => void;
  dismiss: (id: string) => void;
} | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const { showFlag, dismissFlag, flags } = useFlags();

  const toast = useCallback((options: ToastOptions) => {
    const iconMap = {
      success: <SuccessIcon label="Success" primaryColor={token('color.icon.success')} />,
      error:   <ErrorIcon   label="Error"   primaryColor={token('color.icon.danger')} />,
      warning: <WarningIcon label="Warning" primaryColor={token('color.icon.warning')} />,
      info:    <InfoIcon    label="Info"    primaryColor={token('color.icon.information')} />,
    };

    showFlag({
      icon: iconMap[options.type ?? 'info'],
      title: options.title,
      description: options.description,
      actions: options.actions,
      isAutoDismiss: (options.duration ?? 5000) > 0,
      autoDismissDelay: options.duration ?? 5000,
    });
  }, [showFlag]);

  return (
    <ToastContext.Provider value={{ toast, dismiss: dismissFlag }}>
      {children}
      <FlagGroup>
        {flags.map(flag => <Flag {...flag} key={flag.id} />)}
      </FlagGroup>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used within ToastProvider');
  return ctx;
}
```

### 6.6 Lozenge (`resources/js/components/ui/StatusBadge.tsx`)

```tsx
import Lozenge from '@atlaskit/lozenge';

// 263Tickets-specific status vocabulary mapped to ADS appearances
export type TicketStatus =
  | 'available'
  | 'sold_out'
  | 'reserved'
  | 'cancelled'
  | 'pending'
  | 'completed'
  | 'draft'
  | 'published'
  | 'refunded';

const statusConfig: Record<TicketStatus, {
  label: string;
  appearance: 'default' | 'inprogress' | 'moved' | 'new' | 'removed' | 'success';
  isBold: boolean;
}> = {
  available:  { label: 'Available',  appearance: 'success',    isBold: false },
  sold_out:   { label: 'Sold Out',   appearance: 'removed',    isBold: true  },
  reserved:   { label: 'Reserved',   appearance: 'inprogress', isBold: false },
  cancelled:  { label: 'Cancelled',  appearance: 'removed',    isBold: false },
  pending:    { label: 'Pending',    appearance: 'moved',      isBold: false },
  completed:  { label: 'Completed',  appearance: 'success',    isBold: true  },
  draft:      { label: 'Draft',      appearance: 'default',    isBold: false },
  published:  { label: 'Published',  appearance: 'new',        isBold: false },
  refunded:   { label: 'Refunded',   appearance: 'moved',      isBold: true  },
};

interface StatusBadgeProps {
  status: TicketStatus;
  customLabel?: string;
}

export function StatusBadge({ status, customLabel }: StatusBadgeProps) {
  const config = statusConfig[status];
  return (
    <Lozenge
      appearance={config.appearance}
      isBold={config.isBold}
    >
      {customLabel ?? config.label}
    </Lozenge>
  );
}
```

### 6.7 DataTable (`resources/js/components/ui/DataTable.tsx`)

```tsx
import DynamicTable from '@atlaskit/dynamic-table';
import type { RowType, HeadType } from '@atlaskit/dynamic-table/types';
import { Spinner } from './Spinner';
import { EmptyState } from './EmptyState';

interface Column<T> {
  key: string;
  header: string;
  isSortable?: boolean;
  width?: number;          // percentage
  render: (row: T) => ReactNode;
}

interface DataTableProps<T extends { id: string | number }> {
  columns: Column<T>[];
  rows: T[];
  isLoading?: boolean;
  emptyStateHeading?: string;
  emptyStateDescription?: string;
  emptyStateAction?: ReactNode;
  rowsPerPage?: number;
  testId?: string;
  onSort?: (key: string, order: 'ASC' | 'DESC') => void;
  highlightedRowIndex?: number;
  isFixedSize?: boolean;
  caption?: string;
}

export function DataTable<T extends { id: string | number }>({
  columns, rows, isLoading, emptyStateHeading = 'No data found',
  emptyStateDescription, emptyStateAction, rowsPerPage = 20,
  onSort, testId, isFixedSize = true, caption,
}: DataTableProps<T>) {
  const head: HeadType = {
    cells: columns.map(col => ({
      key: col.key,
      content: col.header,
      isSortable: col.isSortable ?? false,
      width: col.width,
    })),
  };

  const tableRows: RowType[] = rows.map(row => ({
    key: String(row.id),
    cells: columns.map(col => ({
      key: col.key,
      content: col.render(row),
    })),
  }));

  return (
    <DynamicTable
      head={head}
      rows={tableRows}
      isLoading={isLoading}
      isFixedSize={isFixedSize}
      rowsPerPage={rowsPerPage}
      loadingSpinnerSize="large"
      emptyView={
        <EmptyState
          heading={emptyStateHeading}
          description={emptyStateDescription}
          primaryAction={emptyStateAction}
        />
      }
      onSort={onSort ? ({ key, sortOrder }) => onSort(key, sortOrder) : undefined}
      testId={testId}
      caption={caption}
    />
  );
}
```

### 6.8 Drawer (`resources/js/components/ui/Drawer.tsx`)

```tsx
import AtlasDrawer from '@atlaskit/drawer';

export type DrawerWidth = 'narrow' | 'medium' | 'wide' | 'extended' | 'full';

interface DrawerProps {
  isOpen: boolean;
  onClose: () => void;
  children: ReactNode;
  width?: DrawerWidth;
  label?: string;
  shouldUnmountOnExit?: boolean;
  testId?: string;
}

export function Drawer({
  isOpen, onClose, children,
  width = 'medium',
  shouldUnmountOnExit = true,
  label = 'Detail panel',
  ...props
}: DrawerProps) {
  return (
    <AtlasDrawer
      isOpen={isOpen}
      onClose={onClose}
      width={width}
      label={label}
      shouldUnmountOnExit={shouldUnmountOnExit}
      {...props}
    >
      {children}
    </AtlasDrawer>
  );
}
```

### 6.9 Additional Wrappers (Summary)

| File | Atlaskit Source | Key Props |
|---|---|---|
| `Checkbox.tsx` | `@atlaskit/checkbox` | `name, label, isChecked, onChange, isIndeterminate` |
| `RadioGroup.tsx` | `@atlaskit/radio` | `name, options[], value, onChange` |
| `Toggle.tsx` | `@atlaskit/toggle` | `name, label, isChecked, onChange, size` |
| `DatePicker.tsx` | `@atlaskit/datetime-picker` | `name, label, value, onChange, minDate, maxDate` |
| `Textarea.tsx` | `@atlaskit/textarea` | `name, label, rows, resize, error, hint` |
| `Badge.tsx` | `@atlaskit/badge` | `count, max, appearance` |
| `Tag.tsx` | `@atlaskit/tag` | `text, color, onRemove, href, isRemovable` |
| `TagGroup.tsx` | `@atlaskit/tag-group` | `tags[], maxTags, onRemove` |
| `Avatar.tsx` | `@atlaskit/avatar` | `src, name, size, presence, status` |
| `AvatarGroup.tsx` | `@atlaskit/avatar-group` | `avatars[], maxCount, size, appearance` |
| `Tooltip.tsx` | `@atlaskit/tooltip` | `content, position, delay, children` |
| `Popup.tsx` | `@atlaskit/popup` | `isOpen, trigger, content, placement` |
| `Spinner.tsx` | `@atlaskit/spinner` | `size, label, appearance` |
| `Skeleton.tsx` | `@atlaskit/skeleton` | `width, height, borderRadius, isShimmering` |
| `Breadcrumbs.tsx` | `@atlaskit/breadcrumbs` | `items[{text, href}], maxItems` |
| `Tabs.tsx` | `@atlaskit/tabs` | `tabs[{label, content}], selected, onChange` |
| `Pagination.tsx` | `@atlaskit/pagination` | `total, page, perPage, onChange` |
| `ProgressBar.tsx` | `@atlaskit/progress-bar` | `value, isIndeterminate, appearance` |
| `SectionMessage.tsx` | `@atlaskit/section-message` | `title, appearance, actions, children` |
| `InlineMessage.tsx` | `@atlaskit/inline-message` | `title, type, secondaryText` |
| `Banner.tsx` | `@atlaskit/banner` | `appearance, icon, children, isOpen` |
| `InlineEdit.tsx` | `@atlaskit/inline-edit` | `defaultValue, editView, readView, onConfirm` |
| `EmptyState.tsx` | `@atlaskit/empty-state` | `heading, description, primaryAction, imageUrl` |
| `DropdownMenu.tsx` | `@atlaskit/dropdown-menu` | `trigger, items[], placement` |
| `Code.tsx` | `@atlaskit/code` | `children, language, shouldWrapLongLines` |

---

## 7. Layout System (Jira-like Shell)

### 7.1 App Shell Architecture

```
┌─────────────────────────────────────────────────────────┐
│  TOP NAVIGATION BAR (56px fixed, z-index: 400)         │  @atlaskit/atlassian-navigation
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│  SIDE    │  CONTENT AREA                                │
│  NAV     │  - Page header (breadcrumbs + title + CTAs) │
│  240px   │  - Main content                              │
│  fixed   │  - Scrollable                                │
│          │                                              │
│          │                                              │
└──────────┴──────────────────────────────────────────────┘
```

### 7.2 AppShell Layout Component

```tsx
// resources/js/layouts/AppShell.tsx
import { Content, Main, PageLayout, LeftSidebarWithoutResize,
         TopNavigation } from '@atlaskit/page-layout';
import { TopNav } from '@/components/navigation/TopNav';
import { SideNav } from '@/components/navigation/SideNav';
import type { ReactNode } from 'react';

interface AppShellProps {
  children: ReactNode;
}

export function AppShell({ children }: AppShellProps) {
  return (
    <PageLayout>
      <TopNavigation
        isFixed
        height={56}
        skipLinkTitle="Skip to main content"
        id="topNav"
        testId="top-nav"
      >
        <TopNav />
      </TopNavigation>

      <Content testId="page-content">
        <LeftSidebarWithoutResize
          isFixed
          width={240}
          id="leftSidebar"
          testId="left-sidebar"
          collapsedState="collapsed"
        >
          <SideNav />
        </LeftSidebarWithoutResize>

        <Main id="main-content" skipLinkTitle="Main content" testId="main">
          {children}
        </Main>
      </Content>
    </PageLayout>
  );
}
```

### 7.3 Page Layout for Individual Pages

```tsx
// resources/js/layouts/PageContent.tsx
// Wraps each page's inner content with consistent padding and header
import { Stack, Box } from '@atlaskit/primitives';
import PageHeader from '@atlaskit/page-header';
import Breadcrumbs, { BreadcrumbsItem } from '@atlaskit/breadcrumbs';

interface BreadcrumbItem {
  text: string;
  href?: string;
}

interface PageContentProps {
  title: string;
  breadcrumbs?: BreadcrumbItem[];
  actions?: ReactNode;        // Right-side CTAs in page header
  children: ReactNode;
  bottomBar?: ReactNode;      // Status bar / secondary actions
}

export function PageContent({
  title, breadcrumbs, actions, children, bottomBar
}: PageContentProps) {
  const breadcrumbNav = breadcrumbs?.length ? (
    <Breadcrumbs>
      {breadcrumbs.map(crumb => (
        <BreadcrumbsItem
          key={crumb.text}
          text={crumb.text}
          href={crumb.href}
          component={crumb.href ? 'a' : 'span'}
        />
      ))}
    </Breadcrumbs>
  ) : undefined;

  return (
    <Box
      padding="space.400"
      backgroundColor="color.background.default"
      style={{ minHeight: '100vh' }}
    >
      <Stack space="space.300">
        <PageHeader
          breadcrumbs={breadcrumbNav}
          actions={actions}
          bottomBar={bottomBar}
        >
          {title}
        </PageHeader>

        {children}
      </Stack>
    </Box>
  );
}
```

---

## 8. Navigation System

### 8.1 Top Navigation (`resources/js/components/navigation/TopNav.tsx`)

```tsx
import {
  AtlassianNavigation,
  PrimaryButton,
  PrimaryDropdownButton,
  ProductHome,
  Search,
  Help,
  NotificationIndicator,
  Profile,
  Settings,
  Create,
} from '@atlaskit/atlassian-navigation';
import { usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';

export function TopNav() {
  const { props } = usePage<{ auth: { user: User } }>();
  const user = props.auth?.user;

  return (
    <AtlassianNavigation
      label="263Tickets"
      renderProductHome={() => (
        <ProductHome
          onClick={() => router.visit(route('dashboard'))}
          siteTitle="263Tickets"
          logoUrl="/images/logo.svg"
        />
      )}
      renderCreate={() => (
        <Create
          onClick={() => router.visit(route('events.create'))}
          buttonTooltip="Create event"
          iconButtonTooltip="Create"
          text="Create"
        />
      )}
      renderSearch={() => (
        <Search
          onClick={() => { /* open search drawer */ }}
          placeholder="Search events, tickets..."
          tooltip="Search"
          label="Search"
        />
      )}
      renderSettings={() => (
        <Settings
          onClick={() => router.visit(route('settings'))}
          tooltip="Settings"
        />
      )}
      renderHelp={() => (
        <Help
          onClick={() => { /* open help */ }}
          tooltip="Help"
        />
      )}
      renderProfile={() => (
        <Profile
          icon={
            <img src={user?.avatar_url} alt={user?.name} />
          }
          tooltip={user?.name}
          onClick={() => { /* profile menu */ }}
        />
      )}
      primaryItems={[
        <PrimaryButton onClick={() => router.visit(route('events.index'))}>
          Events
        </PrimaryButton>,
        <PrimaryButton onClick={() => router.visit(route('tickets.index'))}>
          Tickets
        </PrimaryButton>,
        <PrimaryDropdownButton trigger="Reports">
          {/* DropdownItemGroup here */}
        </PrimaryDropdownButton>,
      ]}
    />
  );
}
```

### 8.2 Side Navigation (`resources/js/components/navigation/SideNav.tsx`)

```tsx
import {
  NavigationContent,
  NavigationHeader,
  NavigationFooter,
  NestableNavigationContent,
  Section,
  SideNavigation,
  ButtonItem,
  LinkItem,
  GoBackItem,
  NestingItem,
  HeaderSection,
  MenuSection,
  NavigationHeader as NavHeader,
} from '@atlaskit/side-navigation';
import { usePage, router } from '@inertiajs/react';

// Icons
import DashboardIcon from '@atlaskit/icon/glyph/dashboard';
import PeopleIcon from '@atlaskit/icon/glyph/people';
import CalendarIcon from '@atlaskit/icon/glyph/calendar';
import TicketIcon from '@atlaskit/icon/glyph/ticket';
import ReportIcon from '@atlaskit/icon/glyph/graph-line';
import SettingsIcon from '@atlaskit/icon/glyph/settings';

interface NavItem {
  id: string;
  label: string;
  href: string;
  icon: ReactNode;
  routeName: string;
}

const navItems: NavItem[] = [
  { id: 'dashboard', label: 'Dashboard',  href: '/dashboard',  icon: <DashboardIcon label="" />, routeName: 'dashboard' },
  { id: 'events',    label: 'Events',     href: '/events',     icon: <CalendarIcon  label="" />, routeName: 'events.index' },
  { id: 'tickets',   label: 'Tickets',    href: '/tickets',    icon: <TicketIcon    label="" />, routeName: 'tickets.index' },
  { id: 'customers', label: 'Customers',  href: '/customers',  icon: <PeopleIcon    label="" />, routeName: 'customers.index' },
  { id: 'reports',   label: 'Reports',    href: '/reports',    icon: <ReportIcon    label="" />, routeName: 'reports.index' },
];

export function SideNav() {
  const { url } = usePage();

  return (
    <SideNavigation label="Project navigation" testId="side-nav">
      <NavigationContent>
        <NestableNavigationContent>
          <Section>
            {navItems.map(item => (
              <LinkItem
                key={item.id}
                href={item.href}
                iconBefore={item.icon}
                isSelected={url.startsWith(item.href)}
                onClick={(e) => {
                  e.preventDefault();
                  router.visit(item.href);
                }}
              >
                {item.label}
              </LinkItem>
            ))}
          </Section>
        </NestableNavigationContent>
      </NavigationContent>

      <NavigationFooter>
        <Section>
          <LinkItem
            href="/settings"
            iconBefore={<SettingsIcon label="" />}
            isSelected={url.startsWith('/settings')}
            onClick={(e) => { e.preventDefault(); router.visit('/settings'); }}
          >
            Settings
          </LinkItem>
        </Section>
      </NavigationFooter>
    </SideNavigation>
  );
}
```

---

## 9. Forms & Data Entry

### 9.1 Form Page Pattern (Jira-style)

```tsx
// resources/js/Pages/Events/Create.tsx
import { Form } from '@atlaskit/form';
import { useForm } from '@inertiajs/react';
import { Stack, Box, Inline } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';
import { Button } from '@ui/Button';
import { TextField } from '@ui/TextField';
import { Select } from '@ui/Select';
import { Textarea } from '@ui/Textarea';
import { DatePicker } from '@ui/DatePicker';
import { SectionMessage } from '@ui/SectionMessage';
import { PageContent } from '@/layouts/PageContent';

export default function CreateEvent() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    category_id: '',
    venue: '',
    date: '',
    description: '',
    max_tickets: '',
  });

  const handleSubmit = (formData: typeof data) => {
    post(route('events.store'));
  };

  return (
    <PageContent
      title="Create Event"
      breadcrumbs={[
        { text: 'Events', href: '/events' },
        { text: 'Create Event' },
      ]}
    >
      <Box
        backgroundColor="elevation.surface"
        padding="space.400"
        style={{ borderRadius: 'var(--ds-border-radius-200)', maxWidth: 720 }}
      >
        <Form onSubmit={handleSubmit}>
          {({ formProps }) => (
            <form {...formProps}>
              <Stack space="space.300">
                {Object.keys(errors).length > 0 && (
                  <SectionMessage
                    title="Please correct the following errors"
                    appearance="error"
                  >
                    {Object.values(errors).map((err, i) => (
                      <p key={i}>{err}</p>
                    ))}
                  </SectionMessage>
                )}

                <TextField
                  name="name"
                  label="Event Name"
                  placeholder="e.g. Harare Jazz Festival 2025"
                  value={data.name}
                  onChange={e => setData('name', e.target.value)}
                  isRequired
                  error={errors.name}
                />

                <Select
                  name="category_id"
                  label="Category"
                  options={categories}
                  onChange={opt => setData('category_id', String(opt?.value ?? ''))}
                  error={errors.category_id}
                  isRequired
                />

                <Inline space="space.200" shouldWrap>
                  <Box style={{ flex: 1 }}>
                    <TextField
                      name="venue"
                      label="Venue"
                      value={data.venue}
                      onChange={e => setData('venue', e.target.value)}
                      error={errors.venue}
                    />
                  </Box>
                  <Box style={{ flex: 1 }}>
                    <DatePicker
                      name="date"
                      label="Event Date"
                      value={data.date}
                      onChange={val => setData('date', val)}
                      minDate={new Date().toISOString().split('T')[0]}
                      error={errors.date}
                    />
                  </Box>
                </Inline>

                <Textarea
                  name="description"
                  label="Description"
                  placeholder="Describe the event..."
                  value={data.description}
                  onChange={e => setData('description', e.target.value)}
                  rows={4}
                  error={errors.description}
                />

                <Inline space="space.100" alignBlock="center">
                  <Button
                    type="submit"
                    variant="primary"
                    isLoading={processing}
                    isDisabled={processing}
                  >
                    Create Event
                  </Button>
                  <Button
                    variant="secondary"
                    onClick={() => router.visit(route('events.index'))}
                  >
                    Cancel
                  </Button>
                </Inline>
              </Stack>
            </form>
          )}
        </Form>
      </Box>
    </PageContent>
  );
}
```

---

## 10. Data Display & Tables

### 10.1 Events Index Page Pattern (Jira issue list style)

```tsx
// resources/js/Pages/Events/Index.tsx
import { Stack, Box, Inline, Text } from '@atlaskit/primitives';
import { DataTable } from '@ui/DataTable';
import { StatusBadge } from '@ui/StatusBadge';
import { Button, IconButton } from '@ui/Button';
import { DropdownMenu } from '@ui/DropdownMenu';
import { Avatar } from '@ui/Avatar';
import { Badge } from '@ui/Badge';
import { PageContent } from '@/layouts/PageContent';
import { router } from '@inertiajs/react';
import MoreIcon from '@atlaskit/icon/glyph/more';

export default function EventsIndex({ events, pagination }) {
  const columns = [
    {
      key: 'name',
      header: 'Event',
      isSortable: true,
      width: 35,
      render: (event) => (
        <Inline space="space.100" alignBlock="center">
          <Avatar name={event.name} size="small" />
          <Stack space="space.025">
            <Text weight="semibold" color="color.text">
              <a
                href={`/events/${event.id}`}
                onClick={(e) => { e.preventDefault(); router.visit(`/events/${event.id}`); }}
                style={{ color: 'var(--ds-link)', textDecoration: 'none' }}
              >
                {event.name}
              </a>
            </Text>
            <Text size="small" color="color.text.subtle">{event.venue}</Text>
          </Stack>
        </Inline>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      isSortable: true,
      width: 12,
      render: (event) => <StatusBadge status={event.status} />,
    },
    {
      key: 'date',
      header: 'Date',
      isSortable: true,
      width: 15,
      render: (event) => (
        <Text size="medium" color="color.text.subtle">
          {new Date(event.date).toLocaleDateString('en-ZW', {
            day: 'numeric', month: 'short', year: 'numeric'
          })}
        </Text>
      ),
    },
    {
      key: 'tickets',
      header: 'Tickets Sold',
      isSortable: true,
      width: 15,
      render: (event) => (
        <Inline space="space.100" alignBlock="center">
          <Text>{event.tickets_sold}</Text>
          <Text color="color.text.subtlest">/ {event.max_tickets}</Text>
        </Inline>
      ),
    },
    {
      key: 'revenue',
      header: 'Revenue',
      isSortable: true,
      width: 13,
      render: (event) => (
        <Text weight="semibold">
          ${event.revenue.toLocaleString()}
        </Text>
      ),
    },
    {
      key: 'actions',
      header: '',
      width: 5,
      render: (event) => (
        <DropdownMenu
          trigger={<IconButton icon={<MoreIcon label="" />} label="More actions" />}
          items={[
            { label: 'View details', onClick: () => router.visit(`/events/${event.id}`) },
            { label: 'Edit event', onClick: () => router.visit(`/events/${event.id}/edit`) },
            { label: 'Manage tickets', onClick: () => router.visit(`/events/${event.id}/tickets`) },
            { type: 'separator' },
            { label: 'Cancel event', onClick: () => { /* confirm dialog */ }, isDestructive: true },
          ]}
        />
      ),
    },
  ];

  return (
    <PageContent
      title="Events"
      breadcrumbs={[{ text: 'Events' }]}
      actions={
        <Button
          variant="primary"
          onClick={() => router.visit(route('events.create'))}
        >
          Create event
        </Button>
      }
    >
      <DataTable
        columns={columns}
        rows={events.data}
        rowsPerPage={20}
        emptyStateHeading="No events found"
        emptyStateDescription="Create your first event to get started."
        emptyStateAction={
          <Button variant="primary" onClick={() => router.visit(route('events.create'))}>
            Create event
          </Button>
        }
        testId="events-table"
      />
    </PageContent>
  );
}
```

---

## 11. Messaging & Feedback

### 11.1 Feedback Hierarchy (Follow Jira's Pattern)

| Scenario | Component | When |
|---|---|---|
| Async operation success | Flag (Toast) | After form submit, delete, update |
| Async operation failure | Flag (Toast) | On API error |
| Form validation errors | SectionMessage | Inline above form |
| Page-level info | Banner | Maintenance mode, feature announcements |
| Field-level errors | ErrorMessage in Form | Inline below field |
| In-context warnings | InlineMessage | Inside content areas |
| Process in progress | Spinner inline | Button loading states |
| Long load time | ProgressBar | Multi-step operations |
| Content loading | Skeleton | Card/table loading states |

### 11.2 SectionMessage Patterns

```tsx
// resources/js/components/ui/SectionMessage.tsx
import AtlasSectionMessage, { SectionMessageAction } from '@atlaskit/section-message';

type SectionMessageAppearance = 'information' | 'warning' | 'error' | 'success' | 'discovery';

interface SectionMessageProps {
  title: string;
  appearance?: SectionMessageAppearance;
  children?: ReactNode;
  actions?: Array<{ text: string; onClick: () => void; href?: string }>;
  testId?: string;
}

export function SectionMessage({
  title, appearance = 'information', children, actions, testId
}: SectionMessageProps) {
  return (
    <AtlasSectionMessage
      title={title}
      appearance={appearance}
      actions={actions?.map(a => (
        <SectionMessageAction key={a.text} onClick={a.onClick} href={a.href}>
          {a.text}
        </SectionMessageAction>
      ))}
      testId={testId}
    >
      {children}
    </AtlasSectionMessage>
  );
}
```

---

## 12. Overlays, Drawers & Modals

### 12.1 Confirmation Dialog Pattern (Jira Delete Pattern)

```tsx
// resources/js/components/ui/ConfirmDialog.tsx
import { Modal } from './Modal';
import { Text } from '@atlaskit/primitives';

interface ConfirmDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  isDangerous?: boolean;
  isLoading?: boolean;
}

export function ConfirmDialog({
  isOpen, onClose, onConfirm, title, message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  isDangerous = false,
  isLoading = false,
}: ConfirmDialogProps) {
  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={title}
      width="small"
      actions={[
        {
          label: confirmLabel,
          onClick: onConfirm,
          variant: isDangerous ? 'danger' : 'primary',
          isLoading,
        },
        {
          label: cancelLabel,
          onClick: onClose,
          variant: 'secondary',
        },
      ]}
    >
      <Text>{message}</Text>
    </Modal>
  );
}
```

### 12.2 Detail Drawer Pattern (Jira Side Panel)

```tsx
// resources/js/components/navigation/EventDetailDrawer.tsx
import { Drawer } from '@ui/Drawer';
import { Stack, Box, Inline, Text } from '@atlaskit/primitives';
import Heading from '@atlaskit/heading';
import { StatusBadge } from '@ui/StatusBadge';
import { Button } from '@ui/Button';
import { Tabs } from '@ui/Tabs';

interface EventDetailDrawerProps {
  eventId: number | null;
  isOpen: boolean;
  onClose: () => void;
}

export function EventDetailDrawer({ eventId, isOpen, onClose }: EventDetailDrawerProps) {
  // Fetch event data based on eventId
  return (
    <Drawer isOpen={isOpen} onClose={onClose} width="wide" label="Event details">
      <Box padding="space.400">
        <Stack space="space.300">
          <Inline spread="space-between" alignBlock="center">
            <Heading size="large">{event.name}</Heading>
            <StatusBadge status={event.status} />
          </Inline>

          <Tabs
            tabs={[
              { label: 'Overview', content: <EventOverviewTab event={event} /> },
              { label: 'Tickets', content: <EventTicketsTab eventId={event.id} /> },
              { label: 'Attendees', content: <EventAttendeesTab eventId={event.id} /> },
              { label: 'Analytics', content: <EventAnalyticsTab eventId={event.id} /> },
            ]}
          />
        </Stack>
      </Box>
    </Drawer>
  );
}
```

---

## 13. Status & Indicator Components

### 13.1 Lozenge Status Map (Complete 263Tickets Vocabulary)

| Business State | ADS Appearance | Bold? | Use Case |
|---|---|---|---|
| Available | `success` | false | Tickets on sale |
| Sold Out | `removed` | true | No tickets remain |
| Reserved | `inprogress` | false | Held in cart |
| Cancelled | `removed` | false | Event/ticket cancelled |
| Pending Payment | `moved` | false | Awaiting confirmation |
| Completed | `success` | true | Event has passed |
| Draft | `default` | false | Unpublished event |
| Published | `new` | false | Live and bookable |
| Refunded | `moved` | true | Refund issued |
| Checking In | `inprogress` | true | Real-time door scan |
| Checked In | `success` | true | Scanned successfully |
| No Show | `default` | false | Did not attend |

### 13.2 Progress Tracking (Ticket Purchase Flow)

```tsx
// resources/js/components/tickets/PurchaseProgress.tsx
import { ProgressTracker } from '@atlaskit/progress-tracker';
import type { Stages } from '@atlaskit/progress-tracker/types';

const stages: Stages = [
  { id: 'select',  label: 'Select Tickets', percentageComplete: 100, status: 'visited',  href: '#' },
  { id: 'details', label: 'Your Details',   percentageComplete: 50,  status: 'current',  href: '#' },
  { id: 'payment', label: 'Payment',        percentageComplete: 0,   status: 'unvisited', href: '#' },
  { id: 'confirm', label: 'Confirmation',   percentageComplete: 0,   status: 'unvisited', href: '#' },
];

export function PurchaseProgress({ currentStep }: { currentStep: number }) {
  // Map currentStep index to stages array
  return <ProgressTracker items={stages} />;
}
```

---

## 14. Motion & Animation System

### 14.1 ADS Motion Philosophy

ADS motion is categorised into three types:
- **Candid** — Natural, human-like movements (entering content, expanding panels)
- **Contextual** — Action-oriented transitions tied to user actions (button press → modal open)
- **Witty** — Delightful micro-interactions sparingly applied

### 14.2 Installation & Usage

```tsx
import { ExitingPersistence, FadeIn, SlideIn, Staggered } from '@atlaskit/motion';

// Page transition wrapper
export function PageTransition({ children }: { children: ReactNode }) {
  return (
    <ExitingPersistence appear>
      <FadeIn duration={200}>
        {motion => <div {...motion}>{children}</div>}
      </FadeIn>
    </ExitingPersistence>
  );
}

// Staggered list reveal (Jira board card entrance)
export function AnimatedList({ items }: { items: ReactNode[] }) {
  return (
    <Staggered duration={200} durationStep={30}>
      {items.map((item, i) => (
        <FadeIn key={i}>
          {motion => <div {...motion}>{item}</div>}
        </FadeIn>
      ))}
    </Staggered>
  );
}

// Slide-in panel (Drawer alternative for lighter contexts)
export function SlidePanel({ isOpen, children }: { isOpen: boolean; children: ReactNode }) {
  return (
    <ExitingPersistence>
      {isOpen && (
        <SlideIn enterFrom="right" duration={250}>
          {motion => <div {...motion}>{children}</div>}
        </SlideIn>
      )}
    </ExitingPersistence>
  );
}
```

### 14.3 Motion Token Reference

```typescript
// Motion durations (ms) — from @atlaskit/motion
export const motionDurations = {
  instant:   0,    // no transition
  fast:      100,  // hover states, small toggles
  medium:    200,  // most component transitions (default)
  slow:      300,  // page transitions, large panels
  verySlow:  500,  // onboarding, spotlights
};

// Easing curves
export const motionEasing = {
  enter:  'cubic-bezier(0.15, 1, 0.3, 1)',   // decelerate into view
  exit:   'cubic-bezier(0.5, 0, 0.7, 0)',     // accelerate out
  inOut:  'cubic-bezier(0.45, 0, 0.55, 1)',   // equal in/out
  spring: 'cubic-bezier(0.8, -0.3, 0.2, 1.3)',// springy bounce
};
```

### 14.4 Reduced Motion Compliance

```tsx
// Always respect prefers-reduced-motion
import { useReducedMotion } from '@atlaskit/motion';

function AnimatedComponent() {
  const prefersReducedMotion = useReducedMotion();

  return (
    <ExitingPersistence appear>
      <FadeIn duration={prefersReducedMotion ? 0 : 200}>
        {motion => <div {...motion}>Content</div>}
      </FadeIn>
    </ExitingPersistence>
  );
}
```

---

## 15. Iconography System

### 15.1 Icon System Rules

ADS uses `@atlaskit/icon` — a curated set of ~400 SVG icons sized at 16px and 24px.

**Rules:**
- NEVER import icons from lucide-react, heroicons, or any other library
- ALWAYS use `@atlaskit/icon/glyph/{icon-name}` format
- ALWAYS provide a `label` prop (empty string for decorative icons)
- Use `primaryColor` token for coloring, never raw hex

### 15.2 Icon Import Pattern

```tsx
// Correct icon import pattern
import CalendarIcon    from '@atlaskit/icon/glyph/calendar';
import TicketIcon      from '@atlaskit/icon/glyph/ticket';
import PersonIcon      from '@atlaskit/icon/glyph/person';
import SearchIcon      from '@atlaskit/icon/glyph/search';
import AddIcon         from '@atlaskit/icon/glyph/add';
import EditIcon        from '@atlaskit/icon/glyph/edit';
import TrashIcon       from '@atlaskit/icon/glyph/trash';
import CheckIcon       from '@atlaskit/icon/glyph/check';
import CrossIcon       from '@atlaskit/icon/glyph/cross';
import ChevronDown     from '@atlaskit/icon/glyph/chevron-down';
import MoreIcon        from '@atlaskit/icon/glyph/more';
import DashboardIcon   from '@atlaskit/icon/glyph/dashboard';
import FilterIcon      from '@atlaskit/icon/glyph/filter';
import ExportIcon      from '@atlaskit/icon/glyph/export';
import NotifyIcon      from '@atlaskit/icon/glyph/notification';
import LockIcon        from '@atlaskit/icon/glyph/lock';
import UnlockIcon      from '@atlaskit/icon/glyph/unlock';
import SuccessIcon     from '@atlaskit/icon/glyph/check-circle';
import ErrorIcon       from '@atlaskit/icon/glyph/error';
import WarningIcon     from '@atlaskit/icon/glyph/warning';
import InfoIcon        from '@atlaskit/icon/glyph/info';
import QRCodeIcon      from '@atlaskit/icon/glyph/qr-code';
import DownloadIcon    from '@atlaskit/icon/glyph/download';
import RefreshIcon     from '@atlaskit/icon/glyph/refresh';

// Usage — decorative (no screen reader text needed)
<CalendarIcon label="" />

// Usage — semantic (screen reader reads the label)
<CalendarIcon label="Event date" />

// Usage — coloured with ADS token
import { token } from '@atlaskit/tokens';
<SuccessIcon label="Success" primaryColor={token('color.icon.success')} />
```

### 15.3 Icon Size Conventions (Jira-accurate)

| Context | Size | ADS Size Prop |
|---|---|---|
| In buttons | 16px | `size="small"` |
| Side nav items | 20px | `size="medium"` (default) |
| Top nav | 24px | `size="medium"` |
| Empty state illustrations | 48px | `size="xlarge"` |
| Status icons in flags | 24px | `size="medium"` |
| Inline with text | 16px | `size="small"` |

---

## 16. Theming: Light, Dark & High-Contrast

### 16.1 Theme Switcher

```tsx
// resources/js/design-system/ThemeProvider.tsx
import { setGlobalTheme } from '@atlaskit/tokens';
import { createContext, useContext, useEffect, useState } from 'react';

type ColorMode = 'light' | 'dark' | 'auto';

interface ThemeContextValue {
  colorMode: ColorMode;
  setColorMode: (mode: ColorMode) => void;
}

const ThemeContext = createContext<ThemeContextValue>({
  colorMode: 'light',
  setColorMode: () => {},
});

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [colorMode, setColorModeState] = useState<ColorMode>(() => {
    return (localStorage.getItem('263t-color-mode') as ColorMode) ?? 'light';
  });

  const setColorMode = (mode: ColorMode) => {
    setColorModeState(mode);
    localStorage.setItem('263t-color-mode', mode);

    const resolvedMode = mode === 'auto'
      ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : mode;

    setGlobalTheme({
      colorMode: resolvedMode,
      theme: 'atlassian',
      typography: 'typography-adg3',
    });

    // Update data-theme attribute for any non-token CSS
    document.documentElement.setAttribute('data-theme', resolvedMode);
  };

  useEffect(() => {
    setColorMode(colorMode);
  }, []);

  return (
    <ThemeContext.Provider value={{ colorMode, setColorMode }}>
      {children}
    </ThemeContext.Provider>
  );
}

export const useTheme = () => useContext(ThemeContext);
```

### 16.2 Theme Toggle UI

```tsx
// resources/js/components/ui/ThemeToggle.tsx
import { useTheme } from '@ds/ThemeProvider';
import { DropdownMenu } from './DropdownMenu';
import { IconButton } from './Button';
import SunIcon from '@atlaskit/icon/glyph/sunshine';
import MoonIcon from '@atlaskit/icon/glyph/moon';

export function ThemeToggle() {
  const { colorMode, setColorMode } = useTheme();

  return (
    <DropdownMenu
      trigger={
        <IconButton
          icon={colorMode === 'dark' ? <MoonIcon label="" /> : <SunIcon label="" />}
          label="Toggle theme"
        />
      }
      items={[
        { label: '☀️  Light', onClick: () => setColorMode('light') },
        { label: '🌙  Dark',  onClick: () => setColorMode('dark') },
        { label: '⚙️  System', onClick: () => setColorMode('auto') },
      ]}
    />
  );
}
```

---

## 17. Accessibility Standards

### 17.1 Required Practices

Every component must meet these ADS-aligned WCAG AA requirements:

```
Color contrast: 4.5:1 for normal text, 3:1 for large text
                ✅ Handled automatically by ADS tokens

Keyboard navigation: All interactive elements focusable
                     ✅ ADS components include focus management
                     ✅ FocusRing component for custom elements

Screen reader: All icons need label prop (empty string = decorative)
               All form fields need associated labels via Field
               All modal dialogs have aria-labelledby
               All tables have captions

Focus management: Modals trap focus
                  Drawers trap focus
                  Return focus to trigger on close

Motion: Respect prefers-reduced-motion (use useReducedMotion hook)
```

### 17.2 Semantic HTML Rules

```tsx
// ✅ Correct — semantic structure
<Heading size="xlarge" as="h1">Page Title</Heading>  // sets both visual + semantic
<Heading size="large" as="h2">Section</Heading>
<Heading size="medium" as="h3">Subsection</Heading>

// ✅ Form fields always via Field wrapper (handles label association)
<Field name="email" label="Email address" isRequired>
  {({ fieldProps }) => <AtlasTextField {...fieldProps} type="email" />}
</Field>

// ✅ VisuallyHidden for SR-only text
import { VisuallyHidden } from '@atlaskit/visually-hidden';
<VisuallyHidden>Loading event data</VisuallyHidden>

// ✅ Announce dynamic content changes
import { useNotify } from '@atlaskit/flag'; // or custom announcer
```

---

## 18. Inertia.js Integration Notes

### 18.1 Link Handling (Critical)

ADS Link and navigation components use `<a>` tags. With Inertia you must intercept clicks:

```tsx
// Pattern for ADS links with Inertia navigation
import { router } from '@inertiajs/react';
import { Link } from '@atlaskit/primitives';

// Use this for all internal navigation links
<Link
  href="/events/123"
  onClick={(e) => {
    e.preventDefault();
    router.visit('/events/123');
  }}
>
  View Event
</Link>

// For ButtonItem in SideNavigation
<LinkItem
  href={item.href}
  onClick={(e) => {
    e.preventDefault();
    router.visit(item.href);
  }}
>
  {item.label}
</LinkItem>
```

### 18.2 Shared Layout with Inertia

```tsx
// resources/js/Pages/Dashboard.tsx
import { AppShell } from '@/layouts/AppShell';
import { PageContent } from '@/layouts/PageContent';

// Inertia persistent layout pattern
const Dashboard = () => {
  return (
    <PageContent title="Dashboard" breadcrumbs={[{ text: 'Dashboard' }]}>
      {/* page content */}
    </PageContent>
  );
};

Dashboard.layout = (page: ReactNode) => <AppShell>{page}</AppShell>;

export default Dashboard;
```

### 18.3 Form Errors Mapping

```tsx
// Map Laravel validation errors to ADS field errors
const { errors } = usePage<{ errors: Record<string, string> }>().props;

// In your form:
<TextField
  name="email"
  label="Email"
  error={errors.email}   // passes string, TextField renders ErrorMessage
/>
```

### 18.4 Progress Indicator for Inertia Navigation

```tsx
// resources/js/app.tsx — configure Inertia's progress bar using ADS token
createInertiaApp({
  progress: {
    color: 'var(--ds-background-brand-bold)',
    showSpinner: false,
    delay: 100,
  },
  // ...
});
```

---

## 19. Removal of Tailwind CSS & shadcn/ui

### 19.1 Removal Checklist

```bash
# Step 1: Uninstall all packages
npm uninstall tailwindcss postcss autoprefixer \
  @tailwindcss/forms @tailwindcss/typography @tailwindcss/container-queries \
  @radix-ui/react-dialog @radix-ui/react-dropdown-menu \
  @radix-ui/react-select @radix-ui/react-checkbox @radix-ui/react-label \
  @radix-ui/react-separator @radix-ui/react-slot @radix-ui/react-toast \
  @radix-ui/react-popover @radix-ui/react-scroll-area @radix-ui/react-tabs \
  @radix-ui/react-switch @radix-ui/react-avatar @radix-ui/react-progress \
  @radix-ui/react-accordion @radix-ui/react-collapsible \
  class-variance-authority clsx tailwind-merge lucide-react cmdk \
  vaul embla-carousel-react sonner

# Step 2: Delete config and generated files
rm -f tailwind.config.js tailwind.config.ts
rm -f postcss.config.js postcss.config.cjs
rm -f components.json
rm -rf resources/js/components/ui/   # old shadcn components — replace with new ADS wrappers

# Step 3: Scrub CSS files
# Remove all @tailwind directives from app.css
# Remove all @apply statements
# Remove all arbitrary Tailwind classes from JSX files (use codemod or grep)

# Step 4: Find remaining Tailwind classes
grep -r "className=\".*\(bg-\|text-\|flex \|grid \|p-\|m-\|w-\|h-\|rounded\|border \|gap-\|space-\)" \
  resources/js --include="*.tsx" --include="*.jsx" -l

# Step 5: Find remaining shadcn imports
grep -r "from '@/components/ui/" resources/js --include="*.tsx" -l
grep -r "from 'lucide-react'" resources/js --include="*.tsx" -l
grep -r "from '@radix-ui" resources/js --include="*.tsx" -l
grep -r "cn(" resources/js --include="*.tsx" -l
```

### 19.2 Class Migration Reference

| Old (Tailwind) | New (ADS Primitive) |
|---|---|
| `flex items-center gap-2` | `<Inline space="space.100" alignBlock="center">` |
| `flex flex-col gap-4` | `<Stack space="space.200">` |
| `p-4` | `<Box padding="space.200">` |
| `px-4 py-2` | `<Box paddingInline="space.200" paddingBlock="space.100">` |
| `bg-white rounded-lg shadow` | `<Box backgroundColor="elevation.surface" xcss={cardStyles}>` |
| `text-sm text-gray-500` | `<Text size="small" color="color.text.subtle">` |
| `text-lg font-semibold` | `<Heading size="medium">` |
| `w-full` | CSS `style={{ width: '100%' }}` or Box `style` prop |
| `hidden sm:block` | ADS Responsive primitives or CSS media queries |
| `disabled:opacity-50` | `isDisabled` prop on ADS components |

---

## 20. Storybook & Component Documentation

### 20.1 Storybook Setup

```bash
npm install --save-dev @storybook/react-vite @storybook/react \
  @storybook/addon-a11y @storybook/addon-docs \
  @atlaskit/storybook-addon-design-system
```

### 20.2 Storybook Config

```typescript
// .storybook/main.ts
import type { StorybookConfig } from '@storybook/react-vite';

const config: StorybookConfig = {
  stories: ['../resources/js/**/*.stories.@(ts|tsx)'],
  addons: [
    '@storybook/addon-docs',
    '@storybook/addon-a11y',
    '@atlaskit/storybook-addon-design-system',
  ],
  framework: '@storybook/react-vite',
};

export default config;
```

```tsx
// .storybook/preview.tsx
import type { Preview } from '@storybook/react';
import AppProvider from '@atlaskit/app-provider';
import '@atlaskit/css-reset/dist/bundle.css';
import '@atlaskit/tokens/css/atlassian-light.css';

const preview: Preview = {
  decorators: [
    (Story) => (
      <AppProvider>
        <Story />
      </AppProvider>
    ),
  ],
  parameters: {
    backgrounds: {
      default: 'atlassian-light',
      values: [
        { name: 'atlassian-light', value: 'var(--ds-background-default)' },
        { name: 'atlassian-dark',  value: '#1D2125' },
      ],
    },
  },
};

export default preview;
```

### 20.3 Example Story

```tsx
// resources/js/components/ui/Button.stories.tsx
import type { Meta, StoryObj } from '@storybook/react';
import { Button } from './Button';
import AddIcon from '@atlaskit/icon/glyph/add';

const meta: Meta<typeof Button> = {
  title: '263Tickets/UI/Button',
  component: Button,
  tags: ['autodocs'],
};
export default meta;

type Story = StoryObj<typeof Button>;

export const Primary: Story   = { args: { children: 'Create Event', variant: 'primary' } };
export const Secondary: Story = { args: { children: 'Cancel', variant: 'secondary' } };
export const Danger: Story    = { args: { children: 'Delete Event', variant: 'danger' } };
export const Loading: Story   = { args: { children: 'Saving...', variant: 'primary', isLoading: true } };
export const WithIcon: Story  = {
  args: {
    children: 'Create Event',
    variant: 'primary',
    iconBefore: <AddIcon label="" />,
  },
};
```

---

## 21. File & Folder Structure (Final State)

```
resources/
├── css/
│   └── app.css                    ← ADS token custom properties only
│
└── js/
    ├── app.tsx                    ← AppProvider, theme CSS imports, Inertia setup
    │
    ├── design-system/             ← ADS abstraction layer
    │   ├── tokens.ts              ← Typed token reference map
    │   ├── ThemeProvider.tsx      ← setGlobalTheme, color mode state
    │   └── index.ts               ← barrel export
    │
    ├── components/
    │   ├── ui/                    ← ← ← WRAPPER LAYER (your stable API)
    │   │   ├── Button.tsx
    │   │   ├── TextField.tsx
    │   │   ├── Textarea.tsx
    │   │   ├── Select.tsx
    │   │   ├── Checkbox.tsx
    │   │   ├── RadioGroup.tsx
    │   │   ├── Toggle.tsx
    │   │   ├── DatePicker.tsx
    │   │   ├── Modal.tsx
    │   │   ├── ConfirmDialog.tsx
    │   │   ├── Drawer.tsx
    │   │   ├── Toast.tsx          ← Flag + FlagGroup + useToast hook
    │   │   ├── Tooltip.tsx
    │   │   ├── Popup.tsx
    │   │   ├── Tabs.tsx
    │   │   ├── Badge.tsx
    │   │   ├── StatusBadge.tsx    ← Lozenge with 263Tickets status vocab
    │   │   ├── Tag.tsx
    │   │   ├── TagGroup.tsx
    │   │   ├── Avatar.tsx
    │   │   ├── AvatarGroup.tsx
    │   │   ├── DataTable.tsx      ← DynamicTable wrapper
    │   │   ├── Spinner.tsx
    │   │   ├── Skeleton.tsx
    │   │   ├── ProgressBar.tsx
    │   │   ├── SectionMessage.tsx
    │   │   ├── InlineMessage.tsx
    │   │   ├── Banner.tsx
    │   │   ├── EmptyState.tsx
    │   │   ├── Breadcrumbs.tsx
    │   │   ├── Pagination.tsx
    │   │   ├── DropdownMenu.tsx
    │   │   ├── InlineEdit.tsx
    │   │   ├── Code.tsx
    │   │   ├── ThemeToggle.tsx
    │   │   └── index.ts           ← barrel export: export * from './Button', etc.
    │   │
    │   ├── navigation/
    │   │   ├── TopNav.tsx
    │   │   ├── SideNav.tsx
    │   │   └── EventDetailDrawer.tsx
    │   │
    │   ├── tickets/
    │   │   ├── PurchaseProgress.tsx
    │   │   ├── TicketCard.tsx
    │   │   └── QRScanner.tsx
    │   │
    │   ├── events/
    │   │   ├── EventCard.tsx
    │   │   ├── EventFilters.tsx
    │   │   └── EventStatusTimeline.tsx
    │   │
    │   └── common/
    │       ├── PageTransition.tsx
    │       ├── AnimatedList.tsx
    │       └── ConfirmOnLeave.tsx
    │
    ├── layouts/
    │   ├── AppShell.tsx           ← PageLayout + TopNavigation + LeftSidebar
    │   ├── PageContent.tsx        ← PageHeader + breadcrumbs + content area
    │   ├── AuthLayout.tsx         ← Centred card layout for login/register
    │   └── FullscreenLayout.tsx   ← No nav (ticket purchase, QR scan)
    │
    ├── Pages/                     ← Inertia pages
    │   ├── Dashboard.tsx
    │   ├── Auth/
    │   │   ├── Login.tsx
    │   │   └── Register.tsx
    │   ├── Events/
    │   │   ├── Index.tsx
    │   │   ├── Create.tsx
    │   │   ├── Edit.tsx
    │   │   └── Show.tsx
    │   ├── Tickets/
    │   │   ├── Index.tsx
    │   │   ├── Purchase.tsx
    │   │   └── Confirmation.tsx
    │   ├── Customers/
    │   ├── Reports/
    │   └── Settings/
    │
    ├── hooks/
    │   ├── useToast.ts            ← re-exports from Toast.tsx for convenience
    │   ├── useConfirm.ts          ← imperative confirm dialog hook
    │   └── useTheme.ts            ← re-exports from ThemeProvider
    │
    └── types/
        ├── models.ts              ← Event, Ticket, Customer TypeScript types
        └── inertia.d.ts           ← Shared Inertia prop types
```

---

## 22. Phased Execution Plan

### Phase 0 — Infrastructure (Day 1) ✅ Do First
```
□ Uninstall Tailwind, postcss, shadcn/ui, radix, lucide-react
□ Delete tailwind.config, postcss.config, components.json
□ Install @atlaskit/tokens, css-reset, app-provider, primitives
□ Configure Vite with @compiled/babel-plugin
□ Set up ESLint rules for ADS
□ Create resources/css/app.css with ADS custom properties only
□ Update app.tsx with AppProvider and ADS CSS imports
□ Create design-system/tokens.ts
□ Verify Vite build runs without errors
```

### Phase 1 — Core Wrapper Components (Days 2-4)
```
□ Button.tsx + IconButton
□ TextField.tsx + Textarea.tsx
□ Select.tsx
□ Checkbox.tsx + RadioGroup.tsx + Toggle.tsx
□ Modal.tsx + ConfirmDialog.tsx
□ Toast.tsx (Flag system) + ToastProvider
□ Spinner.tsx + Skeleton.tsx
□ SectionMessage.tsx + InlineMessage.tsx
□ Tooltip.tsx
□ Breadcrumbs.tsx
□ EmptyState.tsx
□ StatusBadge.tsx (Lozenge)
□ Badge.tsx + Tag.tsx + TagGroup.tsx
□ Avatar.tsx + AvatarGroup.tsx
□ components/ui/index.ts barrel export
□ Storybook: create stories for all above
```

### Phase 2 — Layout & Navigation (Days 5-6)
```
□ AppShell.tsx (PageLayout)
□ TopNav.tsx (@atlaskit/atlassian-navigation)
□ SideNav.tsx (@atlaskit/side-navigation)
□ PageContent.tsx (PageHeader + breadcrumbs)
□ AuthLayout.tsx
□ FullscreenLayout.tsx
□ ThemeProvider.tsx + ThemeToggle.tsx
□ Inertia layout wiring (Dashboard.layout = ...)
□ Test navigation with Inertia router.visit()
```

### Phase 3 — Forms Migration (Days 7-9)
```
□ DatePicker.tsx
□ DropdownMenu.tsx
□ Popup.tsx
□ Tabs.tsx
□ InlineEdit.tsx
□ Events/Create.tsx → migrate to ADS Form
□ Events/Edit.tsx → migrate to ADS Form
□ Tickets/Purchase.tsx → migrate to ADS Form
□ Auth/Login.tsx + Register.tsx → migrate
□ Settings pages → migrate
□ PurchaseProgress.tsx (ProgressTracker)
```

### Phase 4 — Data Tables & Lists (Days 10-11)
```
□ DataTable.tsx (DynamicTable wrapper)
□ Pagination.tsx
□ Events/Index.tsx → migrate to DataTable
□ Tickets/Index.tsx → migrate to DataTable
□ Customers/Index.tsx → migrate to DataTable
□ Reports/Index.tsx → migrate
□ Add sorting, filtering, empty states to all tables
```

### Phase 5 — Detail Views & Drawers (Days 12-13)
```
□ Drawer.tsx
□ EventDetailDrawer.tsx
□ Events/Show.tsx → Jira-like detail layout
□ Tickets/Show.tsx
□ TicketCard.tsx
□ EventCard.tsx
□ ConfirmOnLeave.tsx (warn on unsaved changes)
```

### Phase 6 — Motion & Polish (Day 14)
```
□ PageTransition.tsx (FadeIn on route change)
□ AnimatedList.tsx (Staggered for card grids)
□ Verify useReducedMotion in all animated components
□ Audit all icons — replace any non-atlaskit icons
□ Audit all colours — zero raw hex values
□ ESLint ADS rules: fix all warnings
□ Accessibility audit (keyboard nav, focus management)
□ Dark mode QA pass
□ High-contrast mode QA pass
□ Performance audit (bundle size, code splitting)
```

### Phase 7 — QA & Launch (Days 15-16)
```
□ Cross-browser test (Chrome, Firefox, Safari, Edge)
□ Mobile responsive test
□ Screen reader test (NVDA + Chrome, VoiceOver + Safari)
□ Keyboard-only navigation test
□ Full Storybook documentation review
□ Remove any remaining console.log, dead code
□ Final grep for Tailwind classes (should return 0 results)
□ Final grep for shadcn/radix imports (should return 0 results)
□ Final grep for raw hex colors in JSX/CSS (should return 0 results)
```

---

## 23. Claude Code Prompt Templates

Use these prompts verbatim when working with Claude Code on this codebase:

### Prompt: New Feature Page
```
Using the 263Tickets ADS design system defined in /resources/js/design-system/tokens.ts 
and the wrapper components in /resources/js/components/ui/index.ts, create a new Inertia 
page at /resources/js/Pages/[Feature]/[Page].tsx.

The page must:
- Use AppShell.layout as its persistent layout
- Use PageContent component for the inner layout with title and breadcrumbs
- Use ONLY components from @ui alias (never import @atlaskit directly in pages)
- Use ONLY @atlaskit/primitives (Box, Stack, Inline, Text) for layout — no Tailwind
- Use Heading from @atlaskit/heading for all headings
- Use DataTable wrapper for any tabular data
- Use StatusBadge for any status display
- Include EmptyState for all lists that could be empty
- Connect to Inertia props typed with the models in /resources/js/types/models.ts
- Include loading states with Skeleton components
- Follow Jira's information density and visual hierarchy
```

### Prompt: New Wrapper Component
```
Create a new ADS wrapper component at /resources/js/components/ui/[ComponentName].tsx.

Requirements:
- Import ONLY from the specific @atlaskit package, never from multiple @atlaskit sources
- Define a clean TypeScript interface using 263Tickets vocabulary (not Atlaskit's raw prop names)
- Map our prop names to Atlaskit's props internally
- Export both the component and its prop interface
- Add to /resources/js/components/ui/index.ts barrel export
- Create a companion .stories.tsx file in the same directory
- The component must accept a testId prop for QA automation
- All color values must use ds tokens from design-system/tokens.ts
```

### Prompt: Migrate Existing Page
```
Migrate /resources/js/Pages/[ExistingPage].tsx from shadcn/ui + Tailwind to ADS.

Migration rules:
1. Replace every import from '@/components/ui/*' (old shadcn) with imports from '@ui/*' (new ADS wrappers)
2. Replace every import from 'lucide-react' with equivalent from '@atlaskit/icon/glyph/*'
3. Replace every Tailwind className with @atlaskit/primitives (Box, Stack, Inline) or inline style using ds tokens
4. Replace every raw color value with ADS token from design-system/tokens.ts
5. Preserve all Inertia logic (usePage, useForm, router.visit) unchanged
6. Wrap the page export with AppShell.layout
7. Verify zero remaining Tailwind classes after migration
```

---

*This plan is the single source of truth for the 263Tickets ADS migration.*
*Last updated: May 2026*
*Target: 100% ADS, 0% Tailwind, 0% shadcn/ui*
```
