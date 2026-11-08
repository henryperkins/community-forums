import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/registry-form-errors-2026-11-08/browser');
test.skip(process.env.APP_ENV !== 'test' || !/^retroboards_e2e_registry_forms(?:_[a-z0-9]+)*$/.test(process.env.DB_DATABASE ?? ''),
  'This journey needs its dedicated disposable registry-forms database.');
type Fixture = { a: number; b: number; key_a: number; other_key_a: number; key_b: number; state_hash: string };
function fixture(command = 'state'): Fixture {
  return JSON.parse(execFileSync('php', ['tests/browser/registry-form-errors-fixture.php', command], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
async function capture(page: Page, info: TestInfo, mode: string, name: string, route: string) {
  const directory = path.join(evidence, info.project.name, mode);
  mkdirSync(directory, { recursive: true });
  const form = page.locator(`form[action="${route}"]`);
  const card = form.locator('xpath=ancestor::section[1]');
  await card.screenshot({ path: path.join(directory, `${name}.png`), animations: 'disabled' });
}

for (const javaScriptEnabled of [false, true]) {
  const mode = javaScriptEnabled ? 'js' : 'no-js';
  test.describe(mode, () => {
    test.use({ javaScriptEnabled });
    test('each failed action retains only its own draft, error and target without a mutation', async ({ page }, info) => {
      const ids = fixture('reset');
      await page.goto('/login');
      await page.locator('[name="email"]').fill('registry-forms-admin@retro.test');
      await page.locator('[name="password"]').fill('password123');
      await page.locator('form[action="/login"] button[type="submit"]').click();
      await expect(page).not.toHaveURL(/\/login/);
      const envelope = '\n' + JSON.stringify({ document: '{"note":"</textarea><script id=registry-draft-xss>draft</script>"}',
        signature: Buffer.alloc(64, 115).toString('base64'), key_id: 'root-a' }, null, 2) + '\n';
      const cases = [
        { name: 'pin-password', route: `/admin/registries/${ids.a}/keys`, error: 'current_password',
          draft: { key_id: 'browser-draft-<key>', public_key: Buffer.alloc(32, 100).toString('base64'),
            valid_from: '2031-01-01 00:00:00', valid_until: '2032-01-01 00:00:00' } },
        { name: 'rotation-password', route: `/admin/registries/${ids.a}/rotate`, error: 'current_password', draft: { envelope } },
        { name: 'advisory-password', route: `/admin/registries/${ids.a}/advisories`, error: 'current_password', draft: { envelope } },
        { name: 'revoke-password', route: `/admin/registry-keys/${ids.key_a}/revoke`, error: 'current_password',
          draft: { reason: 'Browser draft <reason> for this key only' } },
        { name: 'rotation-malformed', route: `/admin/registries/${ids.a}/rotate`, error: 'envelope',
          draft: { envelope: '\n  {"document": "Retain this unfinished JSON <draft>' } },
        { name: 'advisory-malformed', route: `/admin/registries/${ids.a}/advisories`, error: 'envelope',
          draft: { envelope: '\n  {"advisory": "Retain this unfinished JSON <draft>' } },
      ];
      for (const item of cases) {
        await page.goto('/admin/registries');
        const form = page.locator(`form[action="${item.route}"]`);
        const disclosure = form.locator('xpath=ancestor::details[1]');
        if (await disclosure.count()) await disclosure.locator(':scope > summary').click();
        for (const [field, value] of Object.entries(item.draft)) await form.locator(`[name="${field}"]`).fill(value);
        const password = item.error === 'current_password' ? 'NEVER_ECHO_BROWSER_PASSWORD' : 'password123';
        await form.locator('[name="current_password"]').fill(password);
        const before = fixture().state_hash;
        const response = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === item.route);
        await form.locator('button[type="submit"]').click();
        expect((await response).status()).toBe(422);
        await page.waitForLoadState('load');
        if (await disclosure.count()) await expect(disclosure).toHaveAttribute('open', '');
        for (const [field, value] of Object.entries(item.draft)) await expect(form.locator(`[name="${field}"]`)).toHaveValue(value);
        const errorField = form.locator(`[name="${item.error}"]`);
        await expect(errorField).toHaveAttribute('aria-invalid', 'true');
        await expect(errorField).toBeFocused();
        const errorId = await errorField.getAttribute('aria-describedby');
        expect(errorId).toBeTruthy();
        await expect(page.locator(`[id="${errorId}"]`)).toHaveCount(1);
        await expect(form.locator(`[id="${errorId}"]`)).toBeVisible();
        await expect(page.locator('#registry-draft-xss')).toHaveCount(0);
        expect(await page.content()).not.toContain(password);
        const values = await page.locator('input[type="password"]').evaluateAll(nodes => nodes.map(n => (n as HTMLInputElement).value));
        expect(values.every(v => v === '')).toBe(true);
        const others = page.locator(`form:not([action="${item.route}"])`);
        await expect(others.locator('[aria-invalid="true"], .field-error')).toHaveCount(0);
        for (const [field, value] of Object.entries(item.draft)) {
          const otherValues = await others.locator(`[name="${field}"]`).evaluateAll(nodes => nodes.map(n => (n as HTMLInputElement).value));
          expect(otherValues).not.toContain(value);
        }
        expect(fixture().state_hash).toBe(before);
        await capture(page, info, mode, item.name, item.route);
      }
    });
  });
}
