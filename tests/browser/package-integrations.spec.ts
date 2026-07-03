import { expect, test, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const BASE = process.env.E2E_BASE_URL ?? process.env.RB_BASE_URL ?? '';

async function loginAdmin(page: Page): Promise<void> {
  await page.context().clearCookies(); // avoid the authed-GET-/login redirect trap
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'admin@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
}

async function openIntegrationDetail(page: Page): Promise<void> {
  await page.goto(`${BASE}/admin/packages`);
  const row = page.locator('table tbody tr', { hasText: 'Browser Remote App' });
  await row.getByRole('link', { name: 'Details' }).click();
  await expect(page.locator('#integration')).toBeVisible();
}

// Desktop + mobile share one seeded DB and one fixture install; only one active
// api_token credential is allowed per install, so clear any prior provision first.
async function revokeExistingCredential(page: Page): Promise<void> {
  const revoke = page.locator('#integration').getByRole('button', { name: 'Revoke' }).first();
  if (await revoke.isVisible({ timeout: 500 }).catch(() => false)) {
    await revoke.click({ force: true });
    await expect(page.locator('#integration')).toBeVisible();
  }
}

test.describe('package integration operator surface (P5-04)', () => {
  test('renders grant summary and remote-run copy', async ({ page }) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    await expect(page.locator('#integration')).toContainText('runs remotely');
    await expect(page.locator('#integration')).toContainText('read:boards');
    await expect(page.locator('#integration')).toContainText('Display name');
  });

  test('saves a setting with a no-JS form and redisplays it', async ({ page }, info) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    await page.fill('#integration input[name="display_name"]', 'Acme Concierge');
    await page.getByRole('button', { name: 'Save settings' }).click();
    await expect(page.locator('#integration input[name="display_name"]')).toHaveValue('Acme Concierge');
    await page.screenshot({ path: `../../docs/evidence/browser/${info.project.name}/package-integrations-settings.png` });
  });

  test('provisions a credential and reveals it exactly once', async ({ page }, info) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    await revokeExistingCredential(page);
    await page.fill('#integration .integration-actions input[name="current_password"]', 'password123');
    await page.getByRole('button', { name: 'Provision credentials' }).click();
    await expect(page.locator('.reveal')).toContainText('shown only once');
    await page.screenshot({ path: `../../docs/evidence/browser/${info.project.name}/package-integrations-reveal.png` });

    // Reload: reveal is gone, credential now listed as active.
    await page.reload();
    await expect(page.locator('.reveal')).toHaveCount(0);
    await expect(page.locator('#integration')).toContainText('active');
  });

  test('integration section has no serious axe violations', async ({ page }, info) => {
    await loginAdmin(page);
    await openIntegrationDetail(page);
    const results = await new AxeBuilder({ page })
      .include('#integration')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const serious = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(serious, `${info.project.name} #integration serious/critical axe violations`).toEqual([]);
  });
});
