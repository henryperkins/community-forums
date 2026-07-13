import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Phase 5 Inc 9 (P5-13 invitations) browser evidence for the no-JS operator
 * console and the member redemption journey. Journey: issue an invitation on
 * /admin/invitations (raw link rendered exactly once) -> issue + revoke a
 * second one so the list shows both statuses -> flip registration to
 * invite-only on the dashboard -> logged-out /register is blocked -> the
 * /invite/<token> landing redirects into /register with the invite banner ->
 * complete the server-rendered form ("Accept invitation") and land signed in
 * -> a bogus token shows the deliberately uniform invalid banner -> restore
 * open registration. Certifies the console and the invite-bearing register
 * page free of serious/critical axe violations.
 *
 * `features.invitations` is seeded true unconditionally in seed.php, so —
 * like providers.spec.ts — this spec has no env-var skip guard.
 *
 * Isolation: mirrors providers.spec.ts's theme-safe-mode neutralisation and
 * its admin-login / user-switch-cookie-trap helpers verbatim. Desktop +
 * mobile share one seeded DB (workers=1), so usernames carry the project
 * name and registration_mode is restored to `open` at the end of the run.
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

async function setRegistrationMode(page: Page, mode: 'open' | 'invite' | 'closed'): Promise<void> {
  await visit(page, '/admin');
  await page.selectOption('select[name="registration_mode"]', mode);
  await page.click('form[action="/admin/settings"] button[type="submit"]');
  await page.waitForURL((u) => u.pathname === '/admin');
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

// Timeout-safe restore for the shared evidence DB: Playwright abandons a
// timed-out test body (its finally may never run), but afterAll hooks still
// execute — without this, a hang after setRegistrationMode('invite') would
// leave the DB invite-only and cascade failures into the specs that follow.
test.afterAll(async ({ browser }) => {
  const context = await browser.newContext();
  const page = await context.newPage();
  try {
    await login(page, 'admin@retro.test');
    await setRegistrationMode(page, 'open');
  } finally {
    await context.close();
  }
});

test('invitations: show-once issue + revoke console, invite-only registration, uniform invalid banner (axe-clean)', async ({ page }, info) => {
  const who = `${info.project.name}${Date.now().toString(36)}`.replace(/[^a-zA-Z0-9]/g, '').slice(-12);

  await login(page, 'admin@retro.test');
  const themeSafeModeChanged = await enterThemeSafeMode(page);

  try {
    // ---- issue: the raw link is rendered exactly once --------------------
    await visit(page, '/admin/invitations');
    await expect(page.getByRole('heading', { name: 'Invitations', level: 1 })).toBeVisible();
    await page.click('form[action="/admin/invitations"] button[type="submit"]'); // defaults: 1 use, 14 days
    await expect(page.getByText('Copy this invitation link now')).toBeVisible();
    const inviteUrl = await page.locator('.flash code').innerText();
    const token = inviteUrl.match(/\/invite\/([0-9a-f]{64})/)?.[1];
    expect(token, `show-once panel must contain the invite URL (got: ${inviteUrl})`).toBeTruthy();
    await expectNoSeriousA11yViolations(page, info);
    await shot(page, info, '69-admin-invitations-show-once');

    // ---- a second invitation, revoked: the list carries both statuses ----
    await page.click('form[action="/admin/invitations"] button[type="submit"]');
    await page.locator('table tbody tr').first().getByRole('button', { name: 'Revoke' }).click();
    await expect(page.getByRole('status').getByText('Invitation revoked.')).toBeVisible();
    await expect(page.locator('table tbody tr', { hasText: 'Revoked' }).first()).toBeVisible();
    await expect(page.locator('table tbody tr', { hasText: 'Active' }).first()).toBeVisible();
    // The raw token never reappears in the list (show-once, hash-only at rest).
    await expect(page.locator('body')).not.toContainText(token!);
    await shot(page, info, '70-admin-invitations-list');

    // ---- invite-only mode: logged-out register is blocked ----------------
    await setRegistrationMode(page, 'invite');
    await page.context().clearCookies(); // authed GET /register 302s home — sign out first
    await visit(page, '/register');
    await expect(page.getByText('Registration is by invitation only.')).toBeVisible();
    await expect(page.locator('input[name="username"]')).toHaveCount(0); // form suppressed
    await shot(page, info, '71-register-invite-mode-blocked');

    // ---- the landing link redirects into register with the banner --------
    await visit(page, `/invite/${token}`);
    await page.waitForURL(/\/register\?invite=/);
    await expect(page.getByText('You’ve been invited to join this community.')).toBeVisible();
    await expectNoSeriousA11yViolations(page, info);
    await shot(page, info, '72-register-invite-banner');

    // ---- accept: plain server-rendered form, lands signed in -------------
    await page.fill('input[name="username"]', `invited${who}`);
    await page.fill('input[name="email"]', `invited${who}@retro.test`);
    await page.fill('input[name="password"]', 'password123');
    await page.fill('input[name="password_confirm"]', 'password123');
    await page.getByRole('button', { name: 'Accept invitation' }).click();
    await page.waitForURL((u) => u.pathname === '/inbox');
    const skip = page.getByRole('button', { name: 'Skip' });
    if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
      await skip.click();
    }
    // The welcome flash proves the signed-in landing on both breakpoints
    // (the shell username sits in the collapsed menu on mobile).
    await expect(page.getByRole('status').getByText(`Welcome to the community, invited${who}!`, { exact: false })).toBeVisible();
    await shot(page, info, '73-invited-member-home');

    // ---- enumeration surface: deliberately uniform invalid banner --------
    await page.context().clearCookies();
    await visit(page, `/invite/${'f'.repeat(64)}`);
    await page.waitForURL(/\/register\?invite=/);
    await expect(page.getByText('This invitation link is invalid or no longer active.')).toBeVisible();
    await expect(page.locator('input[name="username"]')).toHaveCount(0); // invite mode: no valid token, no form
    await shot(page, info, '74-invite-invalid-uniform');
  } finally {
    // Restore the shared evidence DB for the specs that follow.
    await login(page, 'admin@retro.test');
    await setRegistrationMode(page, 'open');
    await exitThemeSafeMode(page, themeSafeModeChanged);
  }
});
