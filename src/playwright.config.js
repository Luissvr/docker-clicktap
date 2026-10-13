import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    workers: 1,
    timeout: 60000,
    expect: { timeout: 15000 },
    use: {
        baseURL: process.env.CT_UI_URL || 'http://127.0.0.1:8080',
        browserName: 'chromium',
        channel: process.env.CT_UI_BROWSER || 'msedge',
        viewport: { width: 1024, height: 900 },
        screenshot: 'only-on-failure',
    },
});
