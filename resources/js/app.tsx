import { createInertiaApp } from '@inertiajs/react';
import AppProvider from '@atlaskit/app-provider';
import { ThemeProvider } from '@/design-system';
import { ConfirmProvider, FlashToastBridge, ToastProvider } from '@ads';
import { initializeTheme } from '@/hooks/use-appearance';
import { AppShell } from '@/layouts/ads/AppShell';
import { SettingsLayout as AdsSettingsLayout } from '@/layouts/ads/SettingsLayout';

// Legacy providers kept while pages migrate — they no-op for ADS
// pages but still serve any unmigrated shadcn pages.
import { ConfirmationDialogProvider } from '@/components/ui/confirmation-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';

// ── ADS Foundation CSS ──────────────────────────────────────────────
// Order matters: reset first, then tokens (light / dark / high-
// contrast variants), then our app-level layout overlay.
import '@atlaskit/css-reset';
import '@atlaskit/tokens/css/atlassian-light.css';
import '@atlaskit/tokens/css/atlassian-dark.css';
import '../css/ads.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('settings/'):
                return [AppShell, AdsSettingsLayout];
            default:
                return AppShell;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            // AppProvider is the ADS root. ToastProvider feeds the @ads
            // useToast hook; FlashToastBridge surfaces Inertia flash
            // payloads. Legacy shadcn providers (Tooltip, Confirmation
            // dialog, sonner Toaster) stay nested until every page is
            // migrated off shadcn.
            <AppProvider>
                <ThemeProvider>
                    <ToastProvider>
                        <ConfirmProvider>
                            <FlashToastBridge />
                            <TooltipProvider delayDuration={0}>
                                <ConfirmationDialogProvider>
                                    {app}
                                    <Toaster />
                                </ConfirmationDialogProvider>
                            </TooltipProvider>
                        </ConfirmProvider>
                    </ToastProvider>
                </ThemeProvider>
            </AppProvider>
        );
    },
    progress: {
        color: 'var(--ds-background-brand-bold)',
    },
});

// Legacy hook that sets the dark/light class on <html>. Kept until
// every page reads from the new ThemeProvider directly.
initializeTheme();
