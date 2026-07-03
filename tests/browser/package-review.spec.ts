import { test, expect, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'node:path';

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
  await page.fill('input[name="email"]', 'admin@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
}

// Resolve the seeded dark-surface remote_app package detail (Local review form host).
async function openReviewablePackage(page: Page): Promise<void> {
  await page.goto('/admin/packages');
  const row = page.locator('table tbody tr', { hasText: 'Browser Remote App' });
  await row.getByRole('link', { name: 'Details' }).click();
  await expect(page.locator('form.review-decision-form select[name="decision"]').first()).toBeVisible();
}

test.describe('package local-review console (deploy-dark)', () => {
  test.skip(!process.env.RB_BROWSER_DARK_SURFACES, 'requires the dark-surface package seed');

  test('review form renders, is axe-clean, and records without JS', async ({ page }, info: TestInfo) => {
    await login(page);
    await openReviewablePackage(page);

    const axe = await new AxeBuilder({ page }).analyze();
    const serious = axe.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(serious, JSON.stringify(serious, null, 2)).toEqual([]);

    const form = page.locator('form.review-decision-form').first();
    await form.locator('select[name="decision"]').selectOption('revoked');
    await form.locator('input[name="current_password"]').fill('password123');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('body')).toContainText('Local review decision recorded');
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, 'package-review.png'),
      fullPage: true,
    });
  });
});
