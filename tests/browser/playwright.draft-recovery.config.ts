import { defineConfig } from '@playwright/test';
import base from './playwright.config';

export default defineConfig({
  ...base,
  webServer: base.webServer ? { ...base.webServer as object, reuseExistingServer: false } : undefined,
  testMatch: ['draft-recovery.spec.ts', 'server-drafts.spec.ts', 'thread-mobile-recovery.spec.ts'],
  projects: ['chromium', 'webkit'].map(browserName => ({
    name: browserName,
    use: { browserName: browserName as 'chromium' | 'webkit', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true },
  })),
});
