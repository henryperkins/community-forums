import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

async function expectNoSeriousA11y(page: Page, info: TestInfo, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  if (include !== undefined) builder = builder.include(include);
  const results = await builder.analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

test.describe('package security-response console (deploy-dark)', () => {
  test.skip(!process.env.RB_BROWSER_DARK_SURFACES, 'requires the dark-surface package seed');

  test('operator drives the no-JS package security console and toggles the emergency brake', async ({ page }, info) => {
    await login(page, 'admin@retro.test');

    await visit(page, '/admin/packages/security');
    await expect(page.getByRole('heading', { name: 'Package security response' })).toBeVisible();
    await expect(page.getByText('Acme Themes').first()).toBeVisible();
    await expectNoSeriousA11y(page, info);
    await shot(page, info, '60-package-security-console');

    // Flip the flag-independent emergency brake (plain form POST -> PRG redirect).
    const brake = page.locator('form[action="/admin/packages/security/execution"]');
    await brake.locator('input[name="current_password"]').fill('password123');
    await brake.getByRole('button', { name: /Emergency-disable all packages/ }).click();
    await page.waitForURL(/\/admin\/packages\/security$/);
    await expect(page.locator('.flash')).toContainText('Package execution disabled');
    await expect(page.getByText('Package execution is halted')).toBeVisible();
    await expectNoSeriousA11y(page, info);

    // Resume so a serial mobile pass starts from a live console.
    const resume = page.locator('form[action="/admin/packages/security/execution"]');
    await resume.locator('input[name="current_password"]').fill('password123');
    await resume.getByRole('button', { name: /Resume package execution/ }).click();
    await page.waitForURL(/\/admin\/packages\/security$/);
    await expect(page.locator('.flash')).toContainText('Package execution resumed');

    // Publisher detail (trust status + keys + lifecycle forms) also clears axe.
    await page.getByRole('link', { name: 'Manage' }).first().click();
    await page.waitForURL(/\/admin\/packages\/publishers\/\d+$/);
    await expect(page.getByRole('heading', { name: /Acme Themes/ })).toBeVisible();
    await expectNoSeriousA11y(page, info);
    await shot(page, info, '61-package-publisher-detail');
  });
});
