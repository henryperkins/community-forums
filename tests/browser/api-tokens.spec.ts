import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * SP0 browser evidence for the landed admin API-token surface (SLICE-API-TOKENS).
 * Drives the no-JS mint form, proves the plaintext is shown exactly once, revokes
 * the token via the row form, and certifies /admin/api-tokens is free of
 * serious/critical axe violations. Seed enables api_tokens + admin@retro.test.
 *
 * Isolation: `npm run evidence` shares one seeded DB, and gate-a's theme-rollback
 * journey deliberately leaves an evidence package theme active site-wide. That theme
 * only overrides a subset of tokens (surfaces/--text), so shared admin-table styles
 * (.audit th → --gold-ink, .audit td → --text-body) desync on its dark surface and
 * fail WCAG AA. This spec certifies the api-tokens surface under the app's own
 * appearance, so it neutralises any leftover active theme via the app's theme safe
 * mode first (the same fallback an operator uses when a theme misbehaves).
 */
const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function openAdminSections(page: Page): Promise<void> {
  const toggle = page.locator('[data-admin-nav-toggle]');
  if (await toggle.isVisible()) {
    await toggle.click();
    await expect(page.locator('[data-admin-nav]')).toHaveAttribute('aria-hidden', 'false');
  }
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
  }
}

// Neutralise any package theme gate-a left active site-wide on the shared evidence DB so
// the api-tokens surface is certified under the standard appearance. The recovery page
// always renders both forms, so ownership is determined from its status copy.
async function enterThemeSafeMode(page: Page): Promise<boolean> {
  await page.goto('/admin/themes/safe-mode');
  if (await page.getByText('Safe mode is on. The built-in system theme is being served.', { exact: true }).isVisible()) {
    return false;
  }

  const enter = page.getByRole('button', { name: 'Enter safe mode' });
  await enter.click();
  await expect(page.getByRole('status').getByText('Theme safe mode is on.')).toBeVisible();
  return true;
}

async function exitThemeSafeMode(page: Page, changed: boolean): Promise<void> {
  if (!changed) return;

  await page.goto('/admin/themes/safe-mode');
  const exit = page.getByRole('button', { name: 'Exit safe mode' });
  if (await exit.isVisible({ timeout: 1000 }).catch(() => false)) {
    // Exiting reauths (setSafeMode(false) requires the current password); enter does not.
    await page.fill('form:has(input[name="exit"]) input[name="current_password"]', 'password123');
    await exit.click();
    await expect(page.getByRole('status').getByText('Theme safe mode was exited.')).toBeVisible();
  }
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  if (include !== undefined) builder = builder.include(include);
  const results = await builder.analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

test('admin API tokens: no-JS mint shows the secret once, axe-clean, then revoke', async ({ page }, info) => {
  await login(page, 'admin@retro.test');
  const themeSafeModeChanged = await enterThemeSafeMode(page);

  // Flag-gated discovery link off the admin dashboard (seed enables api_tokens).
  await visit(page, '/admin');
  await openAdminSections(page);
  await page.getByRole('link', { name: 'API tokens' }).click();
  await page.waitForURL(/\/admin\/api-tokens$/);
  await expect(page.getByRole('heading', { name: 'API tokens' })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info); // empty-form state is accessible

  // Desktop + mobile share one seeded DB — unique name so rows never collide.
  const tokenName = `Evidence CI token (${info.project.name}-${Date.now()})`;
  await page.fill('input[name="name"]', tokenName);
  await page.check('input[name="scopes[]"][value="read:boards"]');
  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Create token' }).click();

  // One-time plaintext, rendered directly (never via Flash / Set-Cookie).
  await expect(page.getByText(/will not be shown again/)).toBeVisible();
  await expect(page.locator('code').filter({ hasText: /^rbt_/ })).toBeVisible();
  await expectNoSeriousA11yViolations(page, info); // reveal state is accessible
  await shot(page, info, 'api-token-minted');

  // Refresh-safety (PR #44 §7): the reload re-POSTs the same idempotency key
  // and is refused at 409 — one row, no plaintext, page still usable.
  const replay = await page.reload();
  expect(replay?.status()).toBe(409);
  await expect(page.getByText('already processed')).toBeVisible();
  await expect(page.locator('code').filter({ hasText: /^rbt_/ })).toHaveCount(0);
  await expect(page.locator('table tbody tr', { hasText: tokenName })).toHaveCount(1);
  await expectNoSeriousA11yViolations(page, info); // conflict state is accessible

  // Revoke via the row form (PRG back to the list).
  const row = page.locator('table tbody tr', { hasText: tokenName });
  await expect(row).toContainText('active');
  const revokeBtn = row.getByRole('button', { name: 'Revoke' });
  await revokeBtn.scrollIntoViewIfNeeded();
  // force: on the tall mobile page Playwright's hit-test transiently reports the
  // mint-form card as topmost; the toContainText('revoked') below proves it fired.
  await revokeBtn.click({ force: true });
  await expect(page.locator('table tbody tr', { hasText: tokenName })).toContainText('revoked');
  await shot(page, info, 'api-token-revoked');

  // Leave the shared site appearance as we found it for any later spec in the run.
  await exitThemeSafeMode(page, themeSafeModeChanged);
});
