import type { ReactNode } from 'react';
import { AppShell } from './AppShell';

/**
 * Settings pages keep the same top + side nav as the rest of the app
 * — the side-nav highlights the settings entry when on /settings/*.
 * The sub-navigation (account / appearance / billing / …) is
 * rendered as in-page tabs on each settings page.
 *
 * This wrapper exists so app.tsx can keep the legacy two-layer layout
 * mapping (`return [AppLayout, SettingsLayout]`) without forcing a
 * page-level rewrite; both layouts now resolve to the same ADS shell.
 */
export function SettingsLayout({ children }: { children: ReactNode }) {
    return <>{children}</>;
}
