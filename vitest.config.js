import { defineConfig } from 'vitest/config';

// Kept apart from vite.config.js so the Laravel and Tailwind plugins don't load for unit tests.
export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
        environment: 'node',
        restoreMocks: true,
    },
});
