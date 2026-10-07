import type { Page } from '@playwright/test';

export async function openNewMessage(page: Page) {
  await page.locator('[data-create-trigger]').click();
  await page.locator('[data-new-message]').click();
}
