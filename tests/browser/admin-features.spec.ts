import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Browser evidence for the read-only readiness classification on the
 * /admin/features inventory (2026-07-13 dark-flag readiness audit,
 * docs/evidence/deploy-dark-features.md): the readiness column renders the
 * six categories beside their rows, actionable rows link to their real
 * surfaces, a console that would 404 while its flag is dark is never linked,
 * and the pane is free of serious/critical axe violations.
 *
 * Posture notes against the shared evidence seed: CAPABILITIES_MODE is unset,
 * so the `capabilities` row shows the live-computed shadow-posture badge; the
 * seed stores giphy_public_key, so the `slash_giphy` row proves the badge
 * CLEARS once the operational step is done (the unset-key badge itself is
 * pinned by AppAdminFeaturesTest).
 */
const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
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

async function expectAxeClean(page: Page, info: TestInfo, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  if (include !== undefined) builder = builder.include(include);
  const results = await builder.analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

// Neutralise any package theme gate-a left active site-wide on the shared
// evidence DB (same rationale and shape as api-tokens.spec.ts): this spec
// certifies /admin/features under the app's own appearance.
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

function flagRow(page: Page, flag: string) {
  return page.locator('tr').filter({ has: page.locator('td code').getByText(flag, { exact: true }) });
}

test('admin feature inventory classifies readiness and links actionable surfaces', async ({ page }, info) => {
  await login(page, 'admin@retro.test');
  const themeSafeModeChanged = await enterThemeSafeMode(page);

  await page.goto('/admin/features');
  await expect(page.getByRole('heading', { name: 'Feature flags' })).toBeVisible();
  expect(await page.locator('th', { hasText: 'Readiness / next step' }).count()).toBeGreaterThan(0);

  // The four dark carryovers carry their categories, with links to surfaces
  // that answer today.
  const groupDms = flagRow(page, 'group_dms');
  await expect(groupDms.getByText('Ready for acceptance')).toBeVisible();
  await expect(groupDms.getByRole('link', { name: 'Report queue' })).toHaveAttribute('href', '/mod/reports');
  await expect(flagRow(page, 'expanded_files').getByText('Missing user UI')).toBeVisible();
  await expect(flagRow(page, 'link_previews').getByText('Missing admin operations')).toBeVisible();
  const customCss = flagRow(page, 'custom_css');
  await expect(customCss.getByText('Safety-blocked')).toBeVisible();
  await expect(customCss.getByRole('link', { name: 'Custom CSS editor' })).toHaveAttribute('href', '/admin/branding');

  // The operational-configuration badge is computed live: capabilities is on
  // with an unset CAPABILITIES_MODE (shadow), so its badge shows; the seed
  // stores a GIPHY key, so slash_giphy's badge has cleared.
  const capabilities = flagRow(page, 'capabilities');
  await expect(capabilities.getByText('Operational configuration required')).toBeVisible();
  await expect(capabilities.getByRole('link', { name: 'Roles & resolver posture' })).toHaveAttribute('href', '/admin/roles');
  await expect(flagRow(page, 'slash_giphy').getByText('Operational configuration required')).toHaveCount(0);

  // The Gate B reservation renders on all four rows (the fifth match is the
  // legend copy in the pane intro), and the dark extensions console is never
  // linked (the nav shows it disabled instead).
  await expect(page.locator('table .state').filter({ hasText: 'Reserved (ADR 0018)' })).toHaveCount(4);
  await expect(page.locator('a[href="/admin/extensions"]')).toHaveCount(0);

  await shot(page, info, 'admin-feature-readiness');
  await expectAxeClean(page, info, '.admin-pane');

  await exitThemeSafeMode(page, themeSafeModeChanged);
});
