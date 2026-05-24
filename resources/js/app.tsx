import { createInertiaApp } from '@inertiajs/react';
import AppProvider from '@atlaskit/app-provider';
import { ConfirmationDialogProvider } from '@/components/ui/confirmation-dialog';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ThemeProvider } from '@/design-system';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

// ── ADS Foundation CSS ──────────────────────────────────────────────
// Order matters: reset first, then tokens (light / dark / high-
// contrast variants), then our app-level layout overlay. These load
// alongside the existing Tailwind/shadcn app.css so pages can migrate
// to ADS incrementally — both systems coexist during the transition.
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
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            // AppProvider is the ADS root — wires the global theme +
            // motion preferences. Nests existing legacy providers
            // unchanged so shadcn pages keep working.
            <AppProvider>
                <ThemeProvider>
                    <TooltipProvider delayDuration={0}>
                        <ConfirmationDialogProvider>
                            {app}
                            <Toaster />
                        </ConfirmationDialogProvider>
                    </TooltipProvider>
                </ThemeProvider>
            </AppProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load... (legacy hook, kept until
// every page reads from the new ThemeProvider).
initializeTheme();
