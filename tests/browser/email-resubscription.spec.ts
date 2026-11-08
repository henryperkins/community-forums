import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/email-resubscription-2026-11-08/browser');
const route = '/settings/notifications/email/resubscribe';
test.skip(process.env.APP_ENV !== 'test'
  || !/^retroboards_e2e_resubscription(?:_[a-z0-9]+)*$/.test(process.env.DB_DATABASE ?? ''),
  'This journey requires its own disposable resubscription database.');

type State = {
  email: string; status: string; suppression: string | null; other_suppression: string;
  prefs: Record<string, unknown>; timezone: string; digest_hour: number | null;
};
function fixture(command = 'state'): State {
  return JSON.parse(execFileSync('php', ['tests/browser/email-resubscription-fixture.php', command], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
async function capture(page: Page, info: TestInfo, mode: string, name: string) {
  const directory = path.join(evidence, info.project.name, mode);
  mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: true });
}
async function submit(page: Page, form: Locator, target: string) {
  const response = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === target);
  await form.locator('button[type="submit"]').click();
  const status = (await response).status();
  await page.waitForLoadState('load');
  return status;
}

for (const javaScriptEnabled of [false, true]) {
  const mode = javaScriptEnabled ? 'js' : 'no-js';
  test.describe(mode, () => {
    test.use({ javaScriptEnabled });
    test('account state, operator suppressions and independent delivery controls survive native submits', async ({ page }, info) => {
      const original = fixture('reset');
      await page.goto('/login');
      await page.locator('[name="email"]').fill(original.email);
      await page.locator('[name="password"]').fill('password123');
      await page.locator('form[action="/login"] button[type="submit"]').click();
      await expect(page).not.toHaveURL(/\/login/);
      await page.goto('/settings/notifications');
      const resubscribe = page.locator(`form[action="${route}"]`);
      await expect(resubscribe).toBeVisible();
      await expect(page.locator('[name="pause_all_email"]')).toBeChecked();

      fixture('restrict'); // The form and session predate this suspension.
      expect(await submit(page, resubscribe, route)).toBe(403);
      await expect(page.locator('#main')).toContainText('Your account is suspended and cannot perform this action.');
      await expect(page.locator('body')).not.toContainText('Notification emails are back on.');
      expect(fixture()).toMatchObject({ status: 'suspended', suppression: 'unsubscribe', prefs: original.prefs });
      await capture(page, info, mode, '01-suspension-denied');
      await page.goto('/settings/notifications');
      await expect(page.getByRole('heading', { name: 'Notification emails are off', exact: true })).toBeVisible();
      await expect(page.getByRole('status')).toHaveCount(0);

      fixture('activate');
      expect(await submit(page, resubscribe, route)).toBe(303);
      await expect(page.getByRole('status')).toContainText('Your email unsubscribe was removed. Your other notification settings still apply.');
      await expect(page.getByRole('heading', { name: 'Notification emails are off', exact: true })).toHaveCount(0);
      await expect(page.locator('[name="pause_all_email"]')).toBeChecked();
      await expect(page.locator('[name="timezone"]')).toHaveValue('Europe/Paris');
      await expect(page.locator('[name="digest_hour"]')).toHaveValue('9');
      expect(fixture()).toMatchObject({ suppression: null, other_suppression: 'unsubscribe', prefs: original.prefs,
        timezone: 'Europe/Paris', digest_hour: 9 });
      await capture(page, info, mode, '02-unsubscribe-removed-pause-retained');

      fixture('unsubscribe');
      await page.goto('/settings/notifications');
      await expect(resubscribe).toBeVisible();
      fixture('complaint'); // An operator changes the suppression after the form was rendered.
      expect(await submit(page, resubscribe, route)).toBe(303);
      await expect(page.getByRole('status')).toContainText('No unsubscribe was removed.');
      expect(fixture()).toMatchObject({ suppression: 'complaint', other_suppression: 'unsubscribe', prefs: original.prefs });
      await capture(page, info, mode, '03-operator-complaint-retained');

      fixture('restrict');
      const settings = page.locator('form[action="/settings/notifications"]');
      await settings.locator('[name="digest_hour"]').selectOption({ label: 'Off' });
      expect(await submit(page, settings, '/settings/notifications')).toBe(303);
      expect(fixture()).toMatchObject({ status: 'suspended', suppression: 'complaint', digest_hour: null,
        prefs: original.prefs });
      await expect(page.locator('[name="digest_hour"]')).toHaveValue('');
      await expect(page.locator('[name="pause_all_email"]')).toBeChecked();
      await capture(page, info, mode, '04-restricted-reduction-permitted');
    });
  });
}
