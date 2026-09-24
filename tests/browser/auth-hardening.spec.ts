import AxeBuilder from '@axe-core/playwright';
import { chromium, expect, test, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Browser evidence for the login/logout harden pass (2026-09-24 audit, DESIGN §13).
 *
 * PHPUnit owns the server contract
 * ({@link ../Integration/Core/AppAuthHardeningTest.php}): the no-store header, the
 * stale-tab logout redirect, the refused sign-in markup, the Log in return path.
 * What only a real browser can show:
 *
 *  - Enter on a focused control on /inbox reaches that control, so the keyboard
 *    can log out from the page every sign-in lands on,
 *  - the account menu closes on Escape, outside click, and Tab past its end,
 *  - Back after Log out does not re-show the member's page, from the HTTP cache
 *    or from the back/forward cache,
 *  - a cancelled passkey prompt reads in the product's words, once.
 */

const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(
  REPO_ROOT,
  process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/auth-login-logout-hardening',
);
const CANCELLED = 'Passkey step was cancelled or unavailable — your other sign-in methods still work.';

async function shot(page: Page, name: string, fullPage = false): Promise<void> {
  const directory = path.join(EVIDENCE_DIR, test.info().project.name);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage, animations: 'disabled' });
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

async function signIn(page: Page, email = 'alice@retro.test'): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.locator('.auth-form button[type="submit"]').click(),
  ]);
  await dismissTour(page);
}

async function tabInto(page: Page, selector: string, limit = 20): Promise<void> {
  for (let i = 0; i < limit; i++) {
    await page.keyboard.press('Tab');
    if (await page.evaluate((s) => !!document.activeElement?.closest(s), selector)) return;
  }
  throw new Error(`Tab never reached ${selector}`);
}

const seat = '.identity-menu > summary';
const isMenuOpen = (page: Page) => page.evaluate(() => (document.querySelector('.identity-menu') as HTMLDetailsElement).open);

test.describe('login and logout, hardened', () => {
  test('the keyboard logs out from /inbox, where every sign-in lands', async ({ page }) => {
    await signIn(page);
    await expect(page).toHaveURL(/\/inbox$/);
    await expect(page.locator('[data-inbox-row]').first()).toBeVisible();

    // Enter opens the seat and presses Log out: the queue's Enter shortcut used
    // to take both keys and open the cursor topic instead.
    await page.locator(seat).focus();
    await page.keyboard.press('Enter');
    expect(await isMenuOpen(page)).toBe(true);
    await tabInto(page, 'form[action="/logout"]');
    await Promise.all([page.waitForURL((url) => url.pathname === '/'), page.keyboard.press('Enter')]);
    await expect(page.locator('.flash')).toHaveText('You have been signed out.');
    await expect(page.locator('.forum-bar-signin')).toBeVisible();
    await shot(page, 'inbox-keyboard-logout');
  });

  test('Enter on a focused link on /inbox follows it; the list still opens its cursor', async ({ page }, info) => {
    await signIn(page);
    await page.goto('/inbox');
    await expect(page.locator('[data-inbox-row]').first()).toBeVisible();

    // The skip link is focusable at both widths; the rail only on desktop.
    await page.locator('a.skip-link').focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/inbox#main$/);

    if (info.project.name === 'desktop') {
      await page.locator('a.board-rail-item[href="/c/general"]').focus();
      await Promise.all([page.waitForURL(/\/c\/general$/), page.keyboard.press('Enter')]);
      await page.goto('/inbox');
    }

    // The shortcut itself is unchanged when focus is on the list, not a control.
    await page.locator('[data-inbox-list]').focus();
    await page.keyboard.press('j');
    await expect(page.locator('[data-inbox-row].is-cursor')).toHaveCount(1);
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/[?&]t=\d+/);
    await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  });

  test('the account menu closes on Escape, a click outside, and Tab past its end', async ({ page }) => {
    await signIn(page);
    await page.goto('/c/general');

    await page.locator(seat).click();
    expect(await isMenuOpen(page)).toBe(true);
    await page.keyboard.press('Escape');
    expect(await isMenuOpen(page)).toBe(false);
    await expect(page.locator(seat)).toBeFocused();
    const seatBox = (await page.locator(seat).boundingBox())!;
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, test.info().project.name, 'account-menu-escape-focus.png'),
      clip: { x: Math.max(0, seatBox.x - 24), y: Math.max(0, seatBox.y - 12), width: seatBox.width + 48, height: seatBox.height + 24 },
    });

    await page.locator(seat).click();
    expect(await isMenuOpen(page)).toBe(true);
    // main's own padding: outside the panel at both widths, and not a control.
    await page.locator('main').click({ position: { x: 4, y: 4 } });
    expect(await isMenuOpen(page)).toBe(false);

    await page.locator(seat).focus();
    await page.keyboard.press('Enter');
    expect(await isMenuOpen(page)).toBe(true);
    await tabInto(page, 'form[action="/logout"]');
    await page.keyboard.press('Tab');
    await expect.poll(() => isMenuOpen(page)).toBe(false);
    expect(await page.evaluate(() => !document.activeElement?.closest('.identity-menu'))).toBe(true);
  });

  test('Back after Log out does not re-show the member page, with or without the back/forward cache', async ({ page, baseURL }) => {
    const response = await (async () => {
      await signIn(page);
      return page.goto('/settings/account');
    })();
    expect(response?.headers()['cache-control']).toBe('private, no-store');

    for (const mode of ['http-cache', 'bfcache'] as const) {
      const browser = mode === 'bfcache'
        ? await chromium.launch({ ignoreDefaultArgs: ['--disable-back-forward-cache'] })
        : null;
      const context = browser ? await browser.newContext({ baseURL, viewport: page.viewportSize() ?? undefined }) : page.context();
      const tab = browser ? await context.newPage() : page;
      if (browser) {
        await signIn(tab);
        await tab.goto('/settings/account');
      }
      await expect(tab.locator('input[type="email"]').first()).toHaveValue('alice@retro.test');

      await tab.locator(seat).click();
      await Promise.all([tab.waitForURL((url) => url.pathname === '/'), tab.locator('form[action="/logout"] button').click()]);
      await tab.goBack();
      await expect(tab).toHaveURL(/\/login\?next=%2Fsettings%2Faccount$/);
      await expect(tab.locator('.identity-menu')).toHaveCount(0);
      await expect(tab.locator('body')).not.toContainText('alice@retro.test');
      await shot(tab, `back-after-logout-${mode}`);
      if (browser) await browser.close();
    }
  });

  test('Log out from a stale second tab says so instead of failing', async ({ page, context }) => {
    await signIn(page);
    await page.goto('/c/general');
    const second = await context.newPage();
    await second.goto('/c/feedback');

    await page.bringToFront();
    await page.locator(seat).click();
    await Promise.all([page.waitForURL((url) => url.pathname === '/'), page.locator('form[action="/logout"] button').click()]);

    await second.bringToFront();
    await second.locator(seat).click();
    await Promise.all([second.waitForURL((url) => url.pathname === '/'), second.locator('form[action="/logout"] button').click()]);
    await expect(second.locator('.flash')).toHaveText('You are already signed out.');
    await expect(second.locator('main')).not.toContainText('403');
    await shot(second, 'stale-tab-logout');
  });

  test('a refused sign-in lands on the password with the reason attached', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'alice@retro.test');
    await page.fill('input[name="password"]', 'not-the-password');
    const [refusal] = await Promise.all([
      page.waitForResponse((r) => r.url().endsWith('/login') && r.request().method() === 'POST'),
      page.press('input[name="password"]', 'Enter'),
    ]);
    expect(refusal.status()).toBe(422);

    const password = page.locator('input[name="password"]');
    await expect(password).toBeFocused();
    await expect(password).toHaveAttribute('aria-describedby', 'login-error');
    await expect(page.locator('#login-error')).toHaveText('The email or password you entered is incorrect.');
    await expect(page.locator('input[name="email"]')).toHaveValue('alice@retro.test');
    const axe = await new AxeBuilder({ page }).include('.auth-card').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(axe.violations).toEqual([]);
    await shot(page, 'login-refused-password-focus');
  });

  test('a cancelled passkey prompt answers once, in the product\'s words', async ({ page }) => {
    // A virtual authenticator holding no credential: the ceremony fails the way
    // a cancelled OS prompt does (NotAllowedError), fast and deterministically.
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('WebAuthn.enable');
    await cdp.send('WebAuthn.addVirtualAuthenticator', {
      options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true },
    });
    let challenges = 0;
    page.on('request', (request) => { if (request.url().endsWith('/login/passkey/challenge')) challenges++; });

    await page.goto('/login');
    const button = page.locator('[data-passkey-signin-btn]');
    const line = page.locator('[data-passkey-signin-error]');
    await expect(button).toBeVisible();
    await expect(line).toHaveAttribute('role', 'alert');
    await expect(line).toBeEmpty();

    await button.dblclick();
    await expect(line).toHaveText(CANCELLED);
    await expect(line).not.toContainText('w3.org');
    await expect(button).not.toHaveAttribute('aria-busy');
    expect(challenges).toBe(1);
    await shot(page, 'passkey-cancelled');

    // A retry clears the line first, so the repeat is a change a reader hears.
    await button.click();
    await expect(line).toHaveText(CANCELLED);
    expect(challenges).toBe(2);
  });

  test('topbar Log in brings a guest back to the board they were reading', async ({ page }) => {
    await page.goto('/c/general');
    const link = page.locator('.forum-bar-signin');
    await expect(link).toHaveAttribute('href', '/login?next=%2Fc%2Fgeneral');
    await Promise.all([page.waitForURL(/\/login\?next=/), link.click()]);
    await page.fill('input[name="email"]', 'alice@retro.test');
    await page.fill('input[name="password"]', 'password123');
    await Promise.all([page.waitForURL(/\/c\/general$/), page.locator('.auth-form button[type="submit"]').click()]);
    await dismissTour(page);
    await expect(page.locator('.identity-menu')).toHaveCount(1);
  });
});
