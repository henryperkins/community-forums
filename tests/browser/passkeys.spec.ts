import { expect, test, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');

// Empty string lets Playwright resolve relative paths against use.baseURL.
// E2E_BASE_URL matches tests/browser/playwright.config.ts; RB_BASE_URL is a
// legacy/manual override for ad-hoc runs. Do not hard-code the server port.
const BASE = process.env.E2E_BASE_URL ?? process.env.RB_BASE_URL ?? '';

function runPhp(code: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString();
}

function setPasskeys(enabled: boolean | null): void {
  // null removes the override so the FeatureFlags DEFAULTS value applies.
  const mutation = enabled === null
    ? "unset($features['passkeys']);"
    : `$features['passkeys'] = ${enabled ? 'true' : 'false'};`;
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
${mutation}
$settings->set('features', $features);
`);
}

function clearBobPasskeys(): void {
  runPhp(`
$bob = $db->fetch("SELECT id FROM users WHERE email = 'bob@retro.test' LIMIT 1");
if ($bob !== null) {
    $db->run('DELETE FROM webauthn_challenges WHERE user_id = ?', [(int) $bob['id']]);
    $db->run('DELETE FROM webauthn_credentials WHERE user_id = ?', [(int) $bob['id']]);
}
`);
}

function shot(name: string, projectName: string): string {
  return `../../docs/evidence/browser/${projectName}/${name}.png`;
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page, email: string): Promise<void> {
  await page.goto(`${BASE}/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function addVirtualAuthenticator(page: Page) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('WebAuthn.enable');
  const { authenticatorId } = await cdp.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  });

  return { cdp, authenticatorId };
}

test.describe('passkeys (P5-11 Gate A browser evidence)', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(async () => {
    await setPasskeys(true);
    clearBobPasskeys();
  });

  test.afterAll(async () => {
    clearBobPasskeys();
    await setPasskeys(null);
  });

  test('enroll, sign out, passkey sign-in, revoke - with a real Chromium authenticator', async ({ page }, testInfo) => {
    await addVirtualAuthenticator(page);
    await login(page, 'bob@retro.test');

    await page.goto(`${BASE}/settings/security`);
    const panel = page.locator('[data-passkey-panel]');
    const addForm = page.locator('[data-passkey-add-form]');
    await expect(addForm).toBeVisible();
    await page.screenshot({ path: shot('passkeys-01-panel', testInfo.project.name), fullPage: true });

    await addForm.locator('input[name="current_password"]').fill('password123');
    await addForm.locator('input[name="nickname"]').fill('Evidence key');
    await addForm.locator('[data-passkey-add-btn]').click();
    await expect(panel).toContainText('Evidence key', { timeout: 15000 });
    await page.screenshot({ path: shot('passkeys-02-enrolled', testInfo.project.name), fullPage: true });

    const axe = await new AxeBuilder({ page }).include('[data-passkey-panel]').analyze();
    expect(axe.violations).toEqual([]);

    await page.locator('form[action="/logout"] button[type="submit"]').click();
    await page.goto(`${BASE}/login`);
    await page.fill('input[name="email"]', 'bob@retro.test');
    const signin = page.locator('[data-passkey-signin]');
    await expect(signin).toBeVisible();
    await page.screenshot({ path: shot('passkeys-03-login', testInfo.project.name) });
    await signin.locator('[data-passkey-signin-btn]').click();
    await expect(page).not.toHaveURL(/\/login/, { timeout: 15000 });
    await dismissTour(page);
    await page.screenshot({ path: shot('passkeys-04-signed-in', testInfo.project.name) });

    await page.goto(`${BASE}/settings/security`);
    const revokeForm = page
      .locator('[data-passkey-panel] li')
      .filter({ hasText: 'Evidence key' })
      .locator('[data-passkey-revoke-form]');
    await revokeForm.locator('input[name="current_password"]').fill('password123');
    await revokeForm.locator('button[type="submit"]').click();
    await expect(page.locator('[data-passkey-panel]')).not.toContainText('Evidence key');
    await page.screenshot({ path: shot('passkeys-05-revoked', testInfo.project.name), fullPage: true });
  });

  test('login page shows no passkey affordance while the flag is dark', async ({ page }, testInfo) => {
    await setPasskeys(false);
    await page.goto(`${BASE}/login`);
    await expect(page.locator('[data-passkey-signin]')).toHaveCount(0);
    await page.screenshot({ path: shot('passkeys-06-dark-login', testInfo.project.name) });
    await setPasskeys(true);
  });
});
