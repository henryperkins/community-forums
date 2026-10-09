import { expect, type Page } from '@playwright/test';

/** Follow the console's native area navigation at either viewport. */
export async function openAdminArea(page: Page, name: string): Promise<void> {
  let navigation = page.locator('[data-admin-tier]');
  const chooser = page.locator('.admin-area-menu');
  if (await chooser.isVisible()) {
    if (!await chooser.evaluate((element) => (element as HTMLDetailsElement).open)) {
      await chooser.locator(':scope > summary').click();
    }
    navigation = page.locator('[data-admin-mobile-areas]');
  }
  await expect(navigation).toBeVisible();
  await navigation.getByRole('link', { name, exact: true }).click();
}
