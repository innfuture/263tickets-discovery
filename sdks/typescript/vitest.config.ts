import { defineConfig } from 'vitest/config';

export default defineConfig({
    // Isolated config so vitest doesn't walk up into the host Laravel
    // app's vite.config.ts (which expects php artisan to be present).
    test: {
        include: ['src/**/*.test.ts'],
        environment: 'node',
    },
    // Explicitly empty plugins — overrides any inherited from parent.
    plugins: [],
});
