import { defineConfig } from 'vitest/config';

// Empty plugins array isolates this package from any parent Vite
// config that might inject plugins (matches the scanner SDK setup).
export default defineConfig({ plugins: [] });
