import { expect, test, type Browser, type BrowserContextOptions, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/email-change-2026-11-01');
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${process.env.E2E_PORT ?? 8057}`;
test.use({ browserName, colorScheme: 'light' });
const disposableDatabase = process.env.APP_ENV === 'test'
  && /^retroboards_e2e_emailchange_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '');
test.skip(!disposableDatabase, 'Select the dedicated email-change database in APP_ENV=test; this spec never mutates a shared evidence DB.');

type State = {
  email_change_override: boolean | 'absent';
  email: string; verified: boolean; token?: string; pending: { new_email: string; expires_at: string } | null;
  active_sessions: number; security_notices: number;
};
function fixture(...args: string[]): State {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'email-change-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
async function login(page: Page, email = 'email-change@retro.test'): Promise<void> {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await expect(page).not.toHaveURL(/\/login(?:\?|$)/);
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible({ timeout: 800 }).catch(() => false)) await skip.click();
}
async function isolatedContext(browser: Browser, info: TestInfo, options: BrowserContextOptions = {}) {
  const use = info.project.use;
  return browser.newContext({ baseURL, viewport: use.viewport, deviceScaleFactor: use.deviceScaleFactor,
    isMobile: use.isMobile, hasTouch: use.hasTouch, colorScheme: 'light', ...options });
}
async function noOverflow(page: Page): Promise<void> {
  const sizes = await page.evaluate(() => ({ actual: document.documentElement.scrollWidth, viewport: document.documentElement.clientWidth }));
  expect(sizes.actual).toBeLessThanOrEqual(sizes.viewport);
}
async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  const directory = path.join(evidence, browserName, info.project.name);
  mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: true, animations: 'disabled' });
}
async function accessible(page: Page, scope = '#main'): Promise<void> {
  const result = await new AxeBuilder({ page }).include(scope).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  expect(result.violations.filter(row => row.impact === 'serious' || row.impact === 'critical')).toEqual([]);
}
async function sendPassword(page: Page, email = 'changed@retro.test', password = 'password123'): Promise<number> {
  await page.locator('input[name="new_email"]').fill(email);
  await page.locator('input[name="current_password"]').fill(password);
  const posted = page.waitForResponse(response => response.request().method() === 'POST'
    && new URL(response.url()).pathname === '/settings/account/email');
  await page.getByRole('button', { name: 'Send confirmation email', exact: true }).click();
  const response = await posted;
  await page.waitForLoadState('load');
  return response.status();
}

test.beforeEach(() => { if (disposableDatabase) fixture('reset'); });
test.afterAll(() => { if (disposableDatabase) fixture('reset'); });

test('validation preserves the candidate, pending keeps the old contact, and cancel retires it', async ({ page }, info) => {
  expect(fixture().email_change_override).toBe('absent');
  await login(page);
  await page.goto('/settings/account');
  await page.getByRole('link', { name: 'Change email address', exact: true }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Change email address' })).toBeVisible();
  await noOverflow(page);
  await accessible(page);
  await shot(page, info, 'request-form');

  expect(await sendPassword(page, 'changed@retro.test', 'wrong-private-password')).toBe(422);
  await expect(page.locator('input[name="new_email"]')).toHaveValue('changed@retro.test');
  await expect(page.locator('input[name="current_password"]')).toHaveValue('');
  expect(await page.content()).not.toContain('wrong-private-password');
  await expect(page.locator('.field-error').filter({ hasText: /password/i })).toBeVisible();
  expect(fixture().email).toBe('email-change@retro.test');
  await noOverflow(page);
  await accessible(page);
  await shot(page, info, 'request-validation');

  expect(await sendPassword(page)).toBe(303);
  await expect(page.getByRole('heading', { name: 'Awaiting confirmation' })).toBeVisible();
  expect(fixture().pending?.new_email).toBe('changed@retro.test');
  expect(fixture().email).toBe('email-change@retro.test');
  await noOverflow(page);
  await shot(page, info, 'pending-confirmation');
  await page.getByRole('button', { name: 'Cancel email change', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Awaiting confirmation' })).toHaveCount(0);
  expect(fixture().pending).toBeNull();
});

test('owner previews without mutation, confirms with POST, and signs out another session', async ({ page, browser }, info) => {
  await login(page);
  const other = await isolatedContext(browser, info);
  try {
    const second = await other.newPage();
    await login(second);
    expect(fixture().active_sessions).toBe(2);
    const token = fixture('issue').token!;
    const response = await page.goto('/settings/account/email/confirm?token=' + token);
    expect(response?.headers()['cache-control']).toBe('private, no-store');
    expect(response?.headers()['referrer-policy']).toBe('no-referrer');
    await expect(page.getByRole('button', { name: 'Confirm email change', exact: true })).toBeVisible();
    expect(fixture().email).toBe('email-change@retro.test');
    await page.reload();
    expect(fixture().email).toBe('email-change@retro.test');
    await noOverflow(page);
    await accessible(page);
    await shot(page, info, 'owner-confirmation');

    const posted = page.waitForResponse(row => row.request().method() === 'POST'
      && new URL(row.url()).pathname === '/settings/account/email/confirm');
    await page.getByRole('button', { name: 'Confirm email change', exact: true }).click();
    expect((await posted).status()).toBe(303);
    await expect(page).toHaveURL(/\/settings\/account$/);
    await expect(page.locator('input[type="email"]')).toHaveValue('changed@retro.test');
    const state = fixture();
    expect(state.email).toBe('changed@retro.test');
    expect(state.verified).toBe(true);
    expect(state.active_sessions).toBe(1);
    expect(state.security_notices).toBe(1);
    await shot(page, info, 'confirmed-account');
    await second.goto('/settings/account');
    await expect(second).toHaveURL(/\/login\?next=/);
    await page.goto('/settings/account/email/confirm?token=' + token);
    await expect(page.getByRole('heading', { name: 'This link cannot be used' })).toBeVisible();
  } finally { await other.close(); }
});

test('replaced, expired and foreign links reveal no candidate or confirmation action', async ({ page }, info) => {
  await login(page);
  const replaced = fixture('issue', 'replaced@retro.test').token!;
  fixture('issue', 'replacement@retro.test');
  await page.goto('/settings/account/email/confirm?token=' + replaced);
  await expect(page.getByRole('heading', { name: 'This link cannot be used' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Confirm email change', exact: true })).toHaveCount(0);
  const expired = fixture('issue').token!;
  fixture('expire');
  await page.goto('/settings/account/email/confirm?token=' + expired);
  await expect(page.getByRole('heading', { name: 'This link cannot be used' })).toBeVisible();
  const foreign = fixture('foreign', 'foreign-secret@retro.test').token!;
  await page.goto('/settings/account/email/confirm?token=' + foreign);
  await expect(page.getByRole('heading', { name: 'This link cannot be used' })).toBeVisible();
  expect(await page.content()).not.toContain('foreign-secret@retro.test');
  expect(await page.content()).not.toContain(foreign);
  expect(fixture().email).toBe('email-change@retro.test');
  await noOverflow(page);
  await accessible(page);
  await shot(page, info, 'unusable-link');
});

test('native forms complete without JavaScript, including at 320px', async ({ browser }, info) => {
  const context = await isolatedContext(browser, info, { javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    await login(page);
    await page.goto('/settings/account/email');
    expect(await sendPassword(page)).toBe(303);
    await expect(page.getByRole('heading', { name: 'Awaiting confirmation' })).toBeVisible();
    await noOverflow(page);
    await shot(page, info, 'no-js-pending');
    const token = fixture('issue').token!;
    await page.goto('/settings/account/email/confirm?token=' + token);
    await noOverflow(page);
    if (info.project.name === 'mobile') {
      await page.setViewportSize({ width: 320, height: 844 });
      await noOverflow(page);
      await shot(page, info, 'no-js-confirmation-320');
    }
    await page.getByRole('button', { name: 'Confirm email change', exact: true }).click();
    await expect(page.locator('input[type="email"]')).toHaveValue('changed@retro.test');
    expect(fixture().email).toBe('changed@retro.test');
  } finally { await context.close(); }
});

test('rolling the subsystem back hides the entry and makes pending links unavailable', async ({ page }) => {
  await login(page);
  const token = fixture('issue').token!;
  fixture('dark');
  await page.goto('/settings/account');
  await expect(page.getByRole('link', { name: 'Change email address', exact: true })).toHaveCount(0);
  expect((await page.goto('/settings/account/email'))?.status()).toBe(404);
  expect((await page.goto('/settings/account/email/confirm?token=' + token))?.status()).toBe(404);
  expect(fixture().email).toBe('email-change@retro.test');
});

test('a real passkey authenticates the email request and native required-field validation runs first', async ({ page }, info) => {
  test.skip(browserName !== 'chromium' || info.project.name !== 'desktop', 'Virtual WebAuthn evidence uses Chromium desktop CDP.');
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  await cdp.send('WebAuthn.addVirtualAuthenticator', { options: {
    protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true,
    isUserVerified: true, automaticPresenceSimulation: true,
  } });
  await login(page);
  await page.goto('/settings/security');
  const addForm = page.locator('[data-passkey-add-form]');
  await addForm.locator('input[name="current_password"]').fill('password123');
  await addForm.locator('input[name="nickname"]').fill('Email confirmation key');
  await addForm.locator('[data-passkey-add-btn]').click();
  await expect(page.locator('[data-passkey-panel]')).toContainText('Email confirmation key', { timeout: 15000 });
  fixture('passwordless');
  await page.goto('/settings/account/email');
  const step = page.locator('[data-passkey-stepup-btn]');
  await expect(step).toBeVisible();
  const challenges: string[] = [];
  page.on('request', request => {
    if (new URL(request.url()).pathname.endsWith('/step-up-challenge')) challenges.push(request.method());
  });
  await step.click();
  expect(challenges).toEqual([]);
  expect(await page.locator('input[name="new_email"]').evaluate((input: HTMLInputElement) => input.validity.valueMissing)).toBe(true);
  await page.locator('input[name="new_email"]').fill('passkey-change@retro.test');
  await step.click();
  await expect(page.getByRole('heading', { name: 'Awaiting confirmation' })).toBeVisible({ timeout: 15000 });
  expect(challenges).toEqual(['POST']);
  expect(fixture().pending?.new_email).toBe('passkey-change@retro.test');
  expect(fixture().email).toBe('email-change@retro.test');
  await shot(page, info, 'passkey-pending');
});

test('provider continuation and return use native navigation and a same-site POST under strict CSP', async ({ page }, info) => {
  test.skip(process.env.OAUTH_GOOGLE_CLIENT_ID !== 'email-change-fixture'
    || process.env.OAUTH_GOOGLE_CLIENT_SECRET !== 'email-change-fixture', 'Run this intercepted provider fixture with the documented synthetic credentials.');
  fixture('provider');
  await login(page);
  await page.goto('/settings/account/email');
  await page.locator('input[name="new_email"]').fill('provider-change@retro.test');
  await page.getByRole('button', { name: 'Confirm with Google', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Continue with Google', exact: true })).toBeVisible();
  const continueLink = page.getByRole('link', { name: 'Continue to Google', exact: true });
  const url = new URL((await continueLink.getAttribute('href'))!);
  expect(url.origin).toBe('https://accounts.google.com');
  expect(url.searchParams.get('client_id')).toBe('email-change-fixture');
  expect(url.searchParams.get('code_challenge_method')).toBe('S256');
  expect(fixture().pending).toBeNull();
  await noOverflow(page);
  await accessible(page);
  await shot(page, info, 'provider-continuation');
  const intercepted: string[] = [];
  await page.route('https://accounts.google.com/**', async route => {
    intercepted.push(route.request().url());
    await route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>Intercepted fixture provider</title><h1>Provider navigation intercepted</h1>' });
  });
  await continueLink.click();
  await expect(page.getByRole('heading', { name: 'Provider navigation intercepted' })).toBeVisible();
  expect(intercepted).toEqual([url.href]);
  // No provider network or code exchange occurs: the callback GET only renders
  // a native same-site POST handoff, which this browser check does not submit.
  const response = await page.goto('/auth/google/callback?state=' + encodeURIComponent(url.searchParams.get('state')!) + '&code=synthetic-code');
  expect(response?.status()).toBe(200);
  expect(response?.headers()['content-security-policy']).toContain("form-action 'self'");
  expect(response?.headers()['referrer-policy']).toBe('no-referrer');
  await expect(page.getByRole('heading', { name: 'Finish confirmation with Google', exact: true })).toBeVisible();
  const handoff = page.locator('form[action="/auth/google/callback"]');
  await expect(handoff).toHaveAttribute('method', 'post');
  await expect(handoff.locator('input[name="state"]')).toHaveValue(url.searchParams.get('state')!);
  await expect(handoff.getByRole('button', { name: 'Send confirmation email', exact: true })).toBeVisible();
  expect(fixture().pending).toBeNull();
  expect(fixture().email).toBe('email-change@retro.test');
  await noOverflow(page);
  await accessible(page);
  await shot(page, info, 'provider-return-handoff');
});

test('the operator catalog exposes the guarded rollback control and its consequences', async ({ page }, info) => {
  expect(fixture().email_change_override).toBe('absent');
  await login(page, 'admin@retro.test');
  await page.goto('/admin/features');
  const row = page.locator('#flag-email_change');
  await expect(row).toBeVisible();
  const toggle = row.locator('details.features-flag-toggle');
  await toggle.locator('summary').click();
  await expect(toggle).toContainText('Verified email changes');
  await expect(toggle).toContainText('completed email changes remain in place');
  await expect(toggle).toContainText('Pending links cannot be used while it is off');
  await expect(toggle.getByRole('button', { name: 'Turn off email_change now', exact: true })).toBeVisible();
  await expect(toggle.locator('input[name="enabled"]')).toHaveValue('0');
  await expect(toggle.locator('input[name="_token"]')).not.toHaveValue('');
  await noOverflow(page);
  await accessible(page, '.admin-pane');
  // The existing catalog intentionally scrolls its table on small screens;
  // capture the visible control rather than producing the entire long ledger.
  const directory = path.join(evidence, browserName, info.project.name);
  mkdirSync(directory, { recursive: true });
  await row.locator('td').first().screenshot({ path: path.join(directory, 'operator-rollback-control.png'), animations: 'disabled' });
});
