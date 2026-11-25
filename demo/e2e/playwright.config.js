import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: './tests',
    timeout: 30_000,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: process.env.DEMO_URL ?? 'http://127.0.0.1:8000',
        viewport: { width: 1280, height: 800 },
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        // Log in once: Filament throttles the login form to 5 attempts per minute.
        { name: 'setup', testMatch: /auth\.setup\.js/ },
        {
            name: 'e2e',
            testMatch: /.*\.spec\.js/,
            dependencies: ['setup'],
            use: { storageState: '.auth/user.json' },
        },
    ],
})
