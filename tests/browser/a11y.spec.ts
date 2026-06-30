import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

test('admin dark-surface pages have no serious axe violations', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/email');
  await expect(page.getByRole('heading', { name: 'Email delivery' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/admin/extensions');
  await expect(page.getByRole('heading', { name: 'Server extensions' })).toBeVisible();
  await expect(page.getByText('browser-evidence')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
});

test('member appeal and server-draft pages have no serious axe violations', async ({ page }, info) => {
  await login(page, 'bob@retro.test');

  await visit(page, '/appeals');
  await expect(page.getByRole('button', { name: 'Submit appeal' }).first()).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);

  await visit(page, '/drafts');
  await expect(page.getByText('Saved reply draft')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
});
