import { defineConfig } from '@playwright/test';
import base from './playwright.config';

export default defineConfig({
  ...base,
  testMatch: 'members-select-contrast.spec.ts',
  projects: ['chromium', 'webkit'].flatMap(browserName => [
    { name: `${browserName}-desktop`, use: { browserName: browserName as 'chromium' | 'webkit', viewport: { width: 1280, height: 800 } } },
    { name: `${browserName}-mobile`, use: { browserName: browserName as 'chromium' | 'webkit', viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true } },
  ]),
});
