import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Phase 5 Inc 8 (P5-12 provider registry + generic OIDC) browser evidence for
 * the no-JS operator console and the member-facing sign-in surface. Journey:
 * add a GitLab-shaped generic-OIDC provider on /admin/providers (client
 * secret straight into the vault; lands disabled) -> probe health via "Test
 * connection" (the .example issuer is deterministically unreachable, so the
 * row records `down` — honestly demonstrating the health surface) -> enable
 * it with password reauth -> the provider button appears on /login -> the
 * disable flow goes through the confirm page with the TM-ID-09-clause-2
 * sole-method listing (empty state here; the populated listing is PHPUnit
 * territory) -> after disable the sign-in button is gone. Certifies
 * /admin/providers and the disable-confirm page free of serious/critical axe
 * violations.
 *
 * `features.provider_registry` (+ its §E prerequisite `service_secrets`) is
 * seeded true unconditionally in seed.php, so — like api-tokens.spec.ts —
 * this spec has no env-var skip guard.
 *
 * Isolation: mirrors api-tokens.spec.ts's theme-safe-mode neutralisation and
 * its admin-login / user-switch-cookie-trap helpers verbatim. Desktop +
 * mobile share one seeded DB and provider keys are unique + immutable, so the
 * key carries the project name and a timestamp.
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
    await page.fill('form:has(input[name="exit"]) input[name="current_password"]', 'password123');
    await exit.click();
    await expect(page.getByRole('status').getByText('Theme safe mode was exited.')).toBeVisible();
  }
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

test('provider console: add a generic OIDC provider, probe health, enable, sign-in button, sole-method disable confirm (axe-clean)', async ({ page }, info) => {
  const key = `oidc-${info.project.name}-${Date.now()}`;
  const label = `GitLab (${info.project.name})`;

  await login(page, 'admin@retro.test');
  const themeSafeModeChanged = await enterThemeSafeMode(page);

  // ---- add (lands disabled; secret goes to the vault) ----------------------
  await visit(page, '/admin/providers');
  await expect(page.getByRole('heading', { name: 'Sign-in providers' })).toBeVisible();
  await page.fill('input[name="provider_key"]', key);
  await page.fill('input[name="display_name"]', label);
  await page.fill('input[name="issuer"]', 'https://gitlab.example');
  await page.fill('input[name="client_id"]', 'evidence-client-id');
  await page.fill('input[name="client_secret"]', 'evidence-client-secret');
  await page.fill('form[action="/admin/providers"] input[name="current_password"]', 'password123');
  await page.click('form[action="/admin/providers"] button[type="submit"]');
  await expect(page.getByRole('status').getByText('Provider added (disabled). Run "Test connection", then enable it.')).toBeVisible();

  const row = page.locator('table tbody tr', { hasText: label });
  await expect(row).toContainText('Disabled');
  await expect(row).toContainText('unknown');

  // ---- health probe (the .example issuer is deterministically unreachable) --
  await row.locator('form[action$="/test"] button').click();
  await expect(page.getByRole('status').getByText(/Provider health: down/)).toBeVisible();
  await expect(page.locator('table tbody tr', { hasText: label })).toContainText('down');

  // ---- enable (password reauth on the inline no-JS form) --------------------
  const enableForm = page.locator('table tbody tr', { hasText: label }).locator('form[action$="/enable"]');
  await enableForm.locator('input[name="current_password"]').fill('password123');
  await enableForm.locator('button[type="submit"]').click();
  await expect(page.getByRole('status').getByText(`${label} is now offered at sign-in.`)).toBeVisible();
  await expect(page.locator('table tbody tr', { hasText: label })).toContainText('Enabled');

  await expectNoSeriousA11yViolations(page, info);
  await shot(page, info, '66-admin-providers-console');

  // ---- the member-facing surface: a sign-in button appears ------------------
  await page.context().clearCookies(); // authed GET /login 302s home — sign out first
  await visit(page, '/login');
  await expect(page.locator(`a[href="/auth/${key}/redirect"]`)).toHaveText(label);
  await shot(page, info, '67-login-generic-provider-button');

  // ---- disable: confirm page surfaces the sole-method listing FIRST ---------
  await login(page, 'admin@retro.test');
  await visit(page, '/admin/providers');
  await page.locator('table tbody tr', { hasText: label }).getByRole('link', { name: 'Disable…' }).click();
  await page.waitForURL(/\/admin\/providers\/\d+\/disable$/);
  await expect(page.getByRole('heading', { name: `Disable ${label}` })).toBeVisible();
  await expect(page.getByText('No accounts rely on this provider as their only sign-in method.')).toBeVisible();
  await expectNoSeriousA11yViolations(page, info);
  await shot(page, info, '68-provider-disable-confirm');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: `Disable ${label}` }).click();
  await expect(page.getByRole('status').getByText(new RegExp(`${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')} disabled`))).toBeVisible();
  await expect(page.locator('table tbody tr', { hasText: label })).toContainText('Disabled');

  // The sign-in button disappears with the provider; identities would be retained.
  await page.context().clearCookies();
  await visit(page, '/login');
  await expect(page.locator(`a[href="/auth/${key}/redirect"]`)).toHaveCount(0);

  await login(page, 'admin@retro.test');
  await exitThemeSafeMode(page, themeSafeModeChanged);
});
