import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import * as crypto from 'node:crypto';
import path from 'node:path';

test.use({ javaScriptEnabled: false });
test.setTimeout(70_000);

// Empty string lets Playwright resolve relative paths against use.baseURL.
const BASE = process.env.E2E_BASE_URL ?? process.env.RB_BASE_URL ?? '';
const repoRoot = path.resolve(__dirname, '..', '..');

function runPhp(code: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString();
}

function resetTotp(email: string): void {
  const encoded = Buffer.from(email, 'utf8').toString('base64');
  runPhp(`
$email = base64_decode('${encoded}');
$user = $db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
if ($user) {
    $db->run('DELETE FROM user_recovery_codes WHERE user_id = ?', [(int) $user['id']]);
    $db->run('DELETE FROM user_totp_credentials WHERE user_id = ?', [(int) $user['id']]);
    $db->run('DELETE FROM mfa_login_challenges WHERE user_id = ?', [(int) $user['id']]);
}
`);
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
  const counter = Math.floor(at / 1000 / 30);
  const msg = Buffer.alloc(8);
  msg.writeBigUInt64BE(BigInt(counter));
  const h = crypto.createHmac('sha1', b32decode(secret)).update(msg).digest();
  const off = h[h.length - 1] & 0x0f;
  return ((h.readUInt32BE(off) & 0x7fffffff) % 1_000_000).toString().padStart(6, '0');
}

function shot(name: string, projectName: string): string {
  return `../../docs/evidence/browser/${projectName}/${name}.png`;
}

async function waitForFreshTotpStep(): Promise<void> {
  await new Promise((resolve) => {
    setTimeout(resolve, 30_000 - (Date.now() % 30_000) + 1_100);
  });
}

test('TOTP enroll, second-factor login, and disable all work without JavaScript', async ({ page }, testInfo) => {
  resetTotp('alice@retro.test');

  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  await page.goto(`${BASE}/settings/security`);
  await page.fill('form[action="/settings/security/totp/enroll"] input[name="current_password"]', 'password123');
  await page.click('form[action="/settings/security/totp/enroll"] button[type="submit"]');
  const secret = await page.locator('label.field', { hasText: 'Authenticator secret' }).locator('input').inputValue();
  const cleaned = (secret || '').trim().replace(/\s+/g, '');
  expect(cleaned).toMatch(/^[A-Z2-7]{16,}$/);
  await page.screenshot({ path: shot('totp-01-enroll', testInfo.project.name), fullPage: true });

  await page.fill('form[action="/settings/security/totp/confirm"] input[name="current_password"]', 'password123');
  await page.fill('form[action="/settings/security/totp/confirm"] input[name="totp_code"]', totp(cleaned));
  await page.click('form[action="/settings/security/totp/confirm"] button[type="submit"]');
  await expect(page.locator('body')).toContainText(/recovery code/i);
  const recoveryCode = ((await page.locator('ul.code-list code').first().textContent()) || '').trim();
  expect(recoveryCode).toMatch(/^[A-F0-9-]{11}$/);
  await page.screenshot({ path: shot('totp-02-recovery-codes', testInfo.project.name), fullPage: true });

  await waitForFreshTotpStep();
  await page.click('form[action="/logout"] button[type="submit"]');
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page.locator('body')).toContainText(/verification/i);
  await page.screenshot({ path: shot('totp-03-interstitial', testInfo.project.name), fullPage: true });
  await page.fill('input[name="code"]', totp(cleaned));
  await page.click('form[action="/login/mfa"] button[type="submit"]');
  await expect(page).not.toHaveURL(/\/login/);

  await page.goto(`${BASE}/settings/security`);
  await page.fill('form[action="/settings/security/totp/disable"] input[name="current_password"]', 'password123');
  await page.fill('form[action="/settings/security/totp/disable"] input[name="disable_code"]', recoveryCode);
  await page.click('form[action="/settings/security/totp/disable"] button[type="submit"]');
  await expect(page.locator('form[action="/settings/security/totp/enroll"]')).toBeVisible();
});
