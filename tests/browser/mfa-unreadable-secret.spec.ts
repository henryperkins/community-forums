import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import * as crypto from 'node:crypto';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

// Two-factor sign-in, disable and pending enrollment when the stored TOTP secret
// is sealed under a key the server does not have (the fixture's dummy key).
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/mfa-unreadable-secret-2026-11-08/browser');
const disposableDatabase = process.env.APP_ENV === 'test' && process.env.DB_DATABASE === 'retroboards_e2e_mfa_unreadable';
test.skip(!disposableDatabase, 'Select DB_DATABASE=retroboards_e2e_mfa_unreadable in APP_ENV=test; this spec never mutates a shared evidence DB.');
test.use({ colorScheme: 'light' });
test.setTimeout(90_000);

const GUIDANCE = 'Authenticator codes cannot be checked for this account right now.';
const RESTART = 'This setup can no longer be verified. Restart setup to get a new authenticator secret.';

function fixture<T>(...args: string[]): T {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'mfa-unreadable-secret-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}

function b32decode(s: string): Buffer {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = 0;
  let value = 0;
  const out: number[] = [];
  for (const ch of s.replace(/=+$/, '')) {
    value = (value << 5) | alphabet.indexOf(ch);
    bits += 5;
    if (bits >= 8) {
      out.push((value >>> (bits - 8)) & 0xff);
      bits -= 8;
    }
  }
  return Buffer.from(out);
}

function totp(secret: string, at: number = Date.now()): string {
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(Math.floor(at / 1000 / 30)));
  const h = crypto.createHmac('sha1', b32decode(secret)).update(msg).digest();
  const off = h[h.length - 1] & 0x0f;
  return ((h.readUInt32BE(off) & 0x7fffffff) % 1_000_000).toString().padStart(6, '0');
}

async function shot(page: Page, info: TestInfo, mode: string, name: string, panel?: Locator): Promise<void> {
  const directory = path.join(evidence, info.project.name, mode);
  mkdirSync(directory, { recursive: true });
  const file = path.join(directory, `${name}.png`);
  // Settings scroll to the autofocused error, and a full-page capture of that
  // document repaints the sticky top bar mid-page. Capture the panel instead.
  if (panel) await panel.screenshot({ path: file, animations: 'disabled' });
  else await page.screenshot({ path: file, fullPage: true, animations: 'disabled' });
}

const twoFactorPanel = (page: Page): Locator => page.locator('section.scribe-panel', {
  has: page.getByRole('heading', { name: 'Two-factor authentication', exact: true }),
});

// axe injects a script, so it runs in the JavaScript pass only; the server-rendered
// markup it checks is the same in both passes.
async function accessible(page: Page, javaScriptEnabled: boolean): Promise<void> {
  if (!javaScriptEnabled) return;
  const result = await new AxeBuilder({ page }).include('#main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  expect(result.violations.filter(row => row.impact === 'serious' || row.impact === 'critical')).toEqual([]);
}

async function noOverflow(page: Page): Promise<void> {
  const sizes = await page.evaluate(() => ({ actual: document.documentElement.scrollWidth, viewport: document.documentElement.clientWidth }));
  expect(sizes.actual).toBeLessThanOrEqual(sizes.viewport);
}

/** Submit a POST form and return the status of its (non-redirect) response. */
async function submit(page: Page, form: string, pathname: string): Promise<number> {
  const posted = page.waitForResponse(response => response.request().method() === 'POST'
    && new URL(response.url()).pathname === pathname);
  await page.locator(`${form} button[type="submit"]`).click();
  const status = (await posted).status();
  await page.waitForLoadState('load');
  return status;
}

async function passwordStep(page: Page): Promise<void> {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('mfa-unreadable@retro.test');
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await expect(page.locator('form[action="/login/mfa"]')).toBeVisible();
}

for (const javaScriptEnabled of [false, true]) {
  const mode = javaScriptEnabled ? 'js' : 'no-js';
  test.describe(mode, () => {
    test.use({ javaScriptEnabled });

    test(`recovery code still works and the authenticator explains itself (${mode})`, async ({ page }, info) => {
      fixture('reset');
      const { secret, codes } = fixture<{ secret: string; codes: string[] }>('enroll');

      await passwordStep(page);
      await page.locator('input[name="code"]').fill(totp(secret));
      expect(await submit(page, 'form[action="/login/mfa"]', '/login/mfa')).toBe(422);
      const field = page.locator('form[action="/login/mfa"] input[name="code"]');
      await expect(field).toHaveAttribute('aria-invalid', 'true');
      await expect(page.locator('#main')).toContainText(GUIDANCE);
      await expect(page.locator('#main')).toContainText('Enter one of your recovery codes instead, or contact the forum operator.');
      await noOverflow(page);
      await accessible(page, javaScriptEnabled);
      await shot(page, info, mode, 'mfa-unreadable-01-authenticator-denied');

      await field.fill(codes[0]);
      await page.locator('form[action="/login/mfa"] button[type="submit"]').click();
      await expect(page).not.toHaveURL(/\/login/);
      await shot(page, info, mode, 'mfa-unreadable-02-recovery-signed-in');
      expect(fixture<{ unused_recovery_codes: number }>('state').unused_recovery_codes).toBe(9);

      await page.goto('/settings/security');
      const disable = 'form[action="/settings/security/totp/disable"]';
      await page.locator(`${disable} input[name="current_password"]`).fill('password123');
      await page.locator(`${disable} input[name="disable_code"]`).fill(totp(secret));
      expect(await submit(page, disable, '/settings/security/totp/disable')).toBe(422);
      await expect(page.locator('#main')).toContainText(`${GUIDANCE} Enter one of your recovery codes to disable two-factor authentication.`);
      await expect(page.locator(`${disable} input[name="disable_code"]`)).toHaveAttribute('aria-invalid', 'true');
      await noOverflow(page);
      await accessible(page, javaScriptEnabled);
      await shot(page, info, mode, 'mfa-unreadable-03-disable-guidance', twoFactorPanel(page));

      await page.locator(`${disable} input[name="current_password"]`).fill('password123');
      await page.locator(`${disable} input[name="disable_code"]`).fill(codes[1]);
      await page.locator(`${disable} button[type="submit"]`).click();
      await expect(page.locator('form[action="/settings/security/totp/enroll"]')).toBeVisible();
      expect(fixture<{ enabled: boolean }>('state').enabled).toBe(false);
    });

    test(`an unreadable pending setup asks for a restart (${mode})`, async ({ page }, info) => {
      fixture('reset');
      await page.goto('/login');
      await page.locator('input[name="email"]').fill('mfa-unreadable@retro.test');
      await page.locator('input[name="password"]').fill('password123');
      await page.locator('form[action="/login"] button[type="submit"]').click();
      await expect(page).not.toHaveURL(/\/login/);
      const { secret } = fixture<{ secret: string }>('pending');

      await page.goto('/settings/security');
      const confirm = 'form[action="/settings/security/totp/confirm"]';
      await page.locator(`${confirm} input[name="current_password"]`).fill('password123');
      await page.locator(`${confirm} input[name="totp_code"]`).fill(totp(secret));
      expect(await submit(page, confirm, '/settings/security/totp/confirm')).toBe(422);
      await expect(page.locator('#main')).toContainText(RESTART);
      await expect(page.locator(`${confirm} input[name="totp_code"]`)).toHaveAttribute('aria-invalid', 'true');
      await expect(page.locator('form[action="/settings/security/totp/enroll"] button[type="submit"]')).toHaveText('Restart setup');
      await noOverflow(page);
      await accessible(page, javaScriptEnabled);
      await shot(page, info, mode, 'mfa-unreadable-04-pending-restart', twoFactorPanel(page));

      const enroll = 'form[action="/settings/security/totp/enroll"]';
      await page.locator(`${enroll} input[name="current_password"]`).fill('password123');
      expect(await submit(page, enroll, '/settings/security/totp/enroll')).toBe(200);
      const fresh = (await page.locator('label.field', { hasText: 'Authenticator secret' }).locator('input').inputValue()).trim();
      expect(fresh).toMatch(/^[A-Z2-7]{16,}$/);
      expect(fresh).not.toBe(secret);
      await shot(page, info, mode, 'mfa-unreadable-05-restarted', twoFactorPanel(page));

      await page.locator(`${confirm} input[name="current_password"]`).fill('password123');
      await page.locator(`${confirm} input[name="totp_code"]`).fill(totp(fresh));
      expect(await submit(page, confirm, '/settings/security/totp/confirm')).toBe(200);
      await expect(page.locator('ul.code-list code')).toHaveCount(10);
      expect(fixture<{ enabled: boolean }>('state').enabled).toBe(true);
    });
  });
}
