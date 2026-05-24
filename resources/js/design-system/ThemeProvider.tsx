import { setGlobalTheme } from '@atlaskit/tokens';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

/**
 * Color-mode switcher that flips ADS's global theme between light,
 * dark, and OS-auto. Persists the user choice in localStorage. Also
 * keeps a `data-theme="light|dark"` attribute on <html> so any non-
 * token CSS (legacy shadcn styles, third-party widgets) can branch.
 */

type ColorMode = 'light' | 'dark' | 'auto';

interface ThemeContextValue {
    colorMode: ColorMode;
    resolvedColorMode: 'light' | 'dark';
    setColorMode: (mode: ColorMode) => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

const STORAGE_KEY = '263t-color-mode';

function resolveAuto(mode: ColorMode): 'light' | 'dark' {
    if (mode !== 'auto') return mode;
    if (typeof window === 'undefined') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(mode: ColorMode): 'light' | 'dark' {
    const resolved = resolveAuto(mode);
    setGlobalTheme({
        colorMode: resolved,
        light: 'light',
        dark: 'dark',
        spacing: 'spacing',
        typography: 'typography',
    });
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-theme', resolved);
        // Keep legacy `.dark` class working for residual shadcn styles
        document.documentElement.classList.toggle('dark', resolved === 'dark');
    }
    return resolved;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
    const [colorMode, setColorModeState] = useState<ColorMode>(() => {
        if (typeof window === 'undefined') return 'light';
        return (window.localStorage.getItem(STORAGE_KEY) as ColorMode | null) ?? 'auto';
    });

    const [resolvedColorMode, setResolvedColorMode] = useState<'light' | 'dark'>(() => resolveAuto(colorMode));

    useEffect(() => {
        const resolved = applyTheme(colorMode);
        setResolvedColorMode(resolved);
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(STORAGE_KEY, colorMode);
        }
    }, [colorMode]);

    // Auto mode also reacts to OS preference changes mid-session.
    useEffect(() => {
        if (colorMode !== 'auto' || typeof window === 'undefined') return;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => {
            const resolved = applyTheme('auto');
            setResolvedColorMode(resolved);
        };
        media.addEventListener('change', handler);
        return () => media.removeEventListener('change', handler);
    }, [colorMode]);

    return (
        <ThemeContext.Provider value={{ colorMode, resolvedColorMode, setColorMode: setColorModeState }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme(): ThemeContextValue {
    const ctx = useContext(ThemeContext);
    if (!ctx) {
        // Fail soft when used outside ThemeProvider — legacy code paths
        // may instantiate components in tests without the provider.
        return {
            colorMode: 'light',
            resolvedColorMode: 'light',
            setColorMode: () => {},
        };
    }
    return ctx;
}
