import { defineConfig, devices } from '@playwright/test';

/**
 * Browser tests, because the feature suite cannot see JavaScript.
 *
 * Laravel Dusk was the obvious choice and is not installable here: it requires
 * guzzle ^7.5 and this project runs guzzle 8. Downgrading a library the
 * application actually uses, in order to install a test tool, is the wrong way
 * round.
 *
 * The server is started by Playwright against a throwaway SQLite file, so a
 * run never touches the development database and never needs a live provider.
 */
export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: process.env.CI ? 'github' : 'list',

    use: {
        baseURL: 'http://127.0.0.1:8123',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    ],

    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8123 --env=e2e',
        url: 'http://127.0.0.1:8123',
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
    },
});
