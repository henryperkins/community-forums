import { defineConfig } from '@playwright/test';
import recovery from './playwright.draft-recovery.config';

export default defineConfig({
  ...recovery,
  testMatch: ['draft-recovery.spec.ts', 'server-drafts.spec.ts', 'thread-mobile-recovery.spec.ts', 'resumed-draft-completion.spec.ts', 'mobile-account-menu.spec.ts'],
});
