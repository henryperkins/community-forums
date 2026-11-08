import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

// ADR 0059: an unsubscribe link signed with a previous key opts out but cannot
// resubscribe; the member turns notification emails back on from settings.
// The server must run with APP_PREVIOUS_KEY_1 listed in APP_PREVIOUS_KEY_VERIFY_IDS.
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/app-key-keyring-2026-11-08/browser');
const disposableDatabase = process.env.APP_ENV === 'test' && process.env.DB_DATABASE === 'retroboards_e2e_app_key_keyring';
test.skip(!disposableDatabase, 'Select DB_DATABASE=retroboards_e2e_app_key_keyring in APP_ENV=test; this spec never mutates a shared evidence DB.');
test.skip(!process.env.APP_PREVIOUS_KEY_1 || !process.env.APP_PREVIOUS_KEY_VERIFY_IDS, 'Run with a previous key permitted to verify.');
test.use({ colorScheme: 'light' });
test.setTimeout(90_000);

type Links = { email: string; previous_link: string; active_link: string };

function fixture<T>(...args: string[]): T {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'app-key-keyring-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}

async function shot(page: Page, info: TestInfo, mode: string, name: string, panel?: Locator): Promise<void> {
  const directory = path.join(evidence, info.project.name, mode);
  mkdirSync(directory, { recursive: true });
  const file = path.join(directory, `${name}.png`);
  if (panel) await panel.screenshot({ path: file, animations: 'disabled' });
  else await page.screenshot({ path: file, fullPage: true, animations: 'disabled' });
}

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

async function submit(page: Page, form: Locator, pathname: string): Promise<number> {
  const posted = page.waitForResponse(response => response.request().method() === 'POST'
    && new URL(response.url()).pathname === pathname);
  await form.locator('button[type="submit"]').click();
  const status = (await posted).status();
  await page.waitForLoadState('load');
  return status;
}

for (const javaScriptEnabled of [false, true]) {
  const mode = javaScriptEnabled ? 'js' : 'no-js';
  test.describe(mode, () => {
    test.use({ javaScriptEnabled });

    test(`a previous-key link opts out and settings turn email back on (${mode})`, async ({ page }, info) => {
      const links = fixture<Links>('reset');

      await page.goto(links.previous_link);
      await expect(page.locator('#main')).toContainText(`Unsubscribe ${links.email}`);
      expect(await submit(page, page.locator('form[action="/unsubscribe"]'), '/unsubscribe')).toBe(200);
      await expect(page.locator('#main')).toContainText('will no longer receive notification emails.');
      await expect(page.locator('form[action="/resubscribe"]')).toHaveCount(0);
      const signIn = page.locator('#main').getByRole('link', { name: 'Sign in' });
      await expect(signIn).toHaveAttribute('href', '/settings/notifications');
      await expect(page.locator('#main')).toContainText('Sign in to turn notification emails back on.');
      expect(fixture<{ suppression: string | null }>('state').suppression).toBe('unsubscribe');
      await noOverflow(page);
      await accessible(page, javaScriptEnabled);
      await shot(page, info, mode, 'keyring-01-previous-key-opt-out');

      await signIn.click();
      await expect(page).toHaveURL(/\/login/);
      await page.locator('input[name="email"]').fill(links.email);
      await page.locator('input[name="password"]').fill('password123');
      await page.locator('form[action="/login"] button[type="submit"]').click();
      await expect(page).toHaveURL(/\/settings\/notifications$/);
      const panel = page.locator('section.scribe-panel', { has: page.getByRole('heading', { name: 'Notification emails are off', exact: true }) });
      await expect(panel).toBeVisible();
      await expect(panel).toContainText(`You unsubscribed ${links.email} from notification emails`);
      await noOverflow(page);
      await accessible(page, javaScriptEnabled);
      await shot(page, info, mode, 'keyring-02-settings-emails-off', panel);

      await panel.getByRole('button', { name: 'Turn notification emails back on' }).click();
      await expect(page).toHaveURL(/\/settings\/notifications$/);
      await expect(page.locator('body')).toContainText('Notification emails are back on.');
      await expect(page.getByRole('heading', { name: 'Notification emails are off', exact: true })).toHaveCount(0);
      expect(fixture<{ suppression: string | null }>('state').suppression).toBeNull();
      // A full-page capture of the settings shell clips its sticky rail; capture the pane.
      await shot(page, info, mode, 'keyring-03-settings-emails-back-on', page.locator('#main'));
    });

    test(`an active-key link opts out and offers Re-subscribe (${mode})`, async ({ page }, info) => {
      const links = fixture<Links>('reset');

      await page.goto(links.active_link);
      expect(await submit(page, page.locator('form[action="/unsubscribe"]'), '/unsubscribe')).toBe(200);
      const resubscribe = page.locator('form[action="/resubscribe"]');
      await expect(resubscribe.getByRole('button', { name: 'Re-subscribe' })).toBeVisible();
      await expect(page.locator('#main')).not.toContainText('Sign in to turn notification emails back on.');
      await noOverflow(page);
      await accessible(page, javaScriptEnabled);
      await shot(page, info, mode, 'keyring-04-active-key-opt-out');

      expect(await submit(page, resubscribe, '/resubscribe')).toBe(200);
      await expect(page.locator('#main')).toContainText(`${links.email} has been re-subscribed to notification emails.`);
      expect(fixture<{ suppression: string | null }>('state').suppression).toBeNull();
      await shot(page, info, mode, 'keyring-05-active-key-resubscribed');
    });
  });
}
