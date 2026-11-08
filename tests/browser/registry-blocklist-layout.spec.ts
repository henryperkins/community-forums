import { expect, test, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/registry-blocklist-layout-2026-11-08/browser');
test.skip(process.env.APP_ENV !== 'test' || !/^retroboards_e2e_blocklist_layout(?:_[a-z0-9]+)*$/.test(process.env.DB_DATABASE ?? ''),
  'This journey needs its dedicated disposable blocklist-layout database.');
type Block = { id: number; digest: string | null; package_uid: string | null; reason: string };
type Fixture = { blocks: Block[]; audit: unknown[]; other_state_hash: string };
function fixture(command = 'state'): Fixture {
  return JSON.parse(execFileSync('php', ['tests/browser/registry-blocklist-layout-fixture.php', command], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
async function dimensions(row: Locator) {
  return row.evaluate(element => {
    const rect = (node: Element) => {
      const r = node.getBoundingClientRect();
      return { x: r.x, y: r.y, width: r.width, height: r.height, right: r.right, bottom: r.bottom };
    };
    const main = element.querySelector('.packages-rule-main')!;
    const code = element.querySelector('code')!;
    const range = document.createRange();
    range.selectNodeContents(code);
    return {
      row: rect(element), main: rect(main), form: rect(element.querySelector('form')!),
      password: rect(element.querySelector('input[type="password"]')!), button: rect(element.querySelector('button')!),
      identifierLines: [...range.getClientRects()].map(r => ({ x: r.x, right: r.right, width: r.width, height: r.height })),
      documentWidth: document.documentElement.scrollWidth, viewportWidth: document.documentElement.clientWidth,
      contentWidth: main.scrollWidth, lineHeight: parseFloat(getComputedStyle(code).lineHeight),
    };
  });
}
async function post(page: Page, form: Locator, password: string) {
  await form.locator('[name="current_password"]').fill(password);
  const action = await form.getAttribute('action');
  const response = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === action);
  await form.getByRole('button', { name: 'Remove', exact: true }).click();
  const status = (await response).status();
  await page.waitForLoadState('load');
  return status;
}

// Removing row wrapping must fail the narrow-width geometry assertions. These
// tests also prove the visible native controls still remove only their own block.
for (const width of [320, 390, 1280]) {
  for (const javaScriptEnabled of [false, true]) {
    for (const colorScheme of ['light', 'dark'] as const) {
      const mode = javaScriptEnabled ? 'js' : 'no-js';
      test.describe(`${width}px ${mode} ${colorScheme}`, () => {
        test.use({ viewport: { width, height: 844 }, javaScriptEnabled, colorScheme,
          isMobile: width < 500, hasTouch: width < 500 });
        test('identifiers stay readable and the native removal remains usable', async ({ page }) => {
          const before = fixture('reset');
          const short = before.blocks.find(b => b.package_uid === 'acme/legacy-widget')!;
          const directory = path.join(evidence, `${width}-${mode}-${colorScheme}`);
          mkdirSync(directory, { recursive: true });
          await page.goto('/login');
          await page.locator('[name="email"]').fill('blocklist-layout-admin@retro.test');
          await page.locator('[name="password"]').fill('password123');
          await page.locator('form[action="/login"] button[type="submit"]').click();
          await expect(page).not.toHaveURL(/\/login/);
          await page.goto('/admin/registries');
          const form = page.locator(`form[action="/admin/blocklist/${short.id}/remove"]`);
          const panel = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Local blocklist (registry-independent)', exact: true }) });
          await expect(panel).toBeVisible();
          await page.screenshot({ path: path.join(directory, 'blocklist.png'), fullPage: true, animations: 'disabled' });
          const metrics = [];
          for (const block of before.blocks) {
            const row = page.locator(`form[action="/admin/blocklist/${block.id}/remove"]`).locator('xpath=..');
            await expect(row.locator('code')).toHaveText(block.package_uid ?? block.digest!.slice(0, 16) + '…');
            await expect(row.locator('.packages-rule-detail')).toHaveText(block.reason);
            const m = await dimensions(row);
            metrics.push({ id: block.id, ...m });
          }
          writeFileSync(path.join(directory, 'dimensions.json'), JSON.stringify(metrics, null, 2) + '\n');
          for (const m of metrics) {
            expect(m.documentWidth).toBeLessThanOrEqual(m.viewportWidth + 1);
            expect(m.main.width).toBeGreaterThan(180);
            expect(m.contentWidth).toBeLessThanOrEqual(m.main.width + 1);
            for (const line of m.identifierLines) {
              expect(line.x).toBeGreaterThanOrEqual(m.main.x - 1);
              expect(line.right).toBeLessThanOrEqual(m.main.right + 1);
            }
            expect(m.password.width).toBeGreaterThanOrEqual(150);
            expect(m.password.height).toBeGreaterThanOrEqual(44);
            expect(m.button.width).toBeGreaterThanOrEqual(44);
            expect(m.button.height).toBeGreaterThanOrEqual(44);
            expect(m.password.right).toBeLessThanOrEqual(m.row.right + 1);
            expect(m.button.right).toBeLessThanOrEqual(m.row.right + 1);
            if (width < 500) {
              expect(m.main.width).toBeGreaterThanOrEqual(m.row.width - 1);
              expect(m.form.y).toBeGreaterThanOrEqual(m.main.bottom - 1);
            } else {
              expect(m.form.x).toBeGreaterThan(m.main.x);
              expect(m.form.y).toBeLessThan(m.main.bottom);
            }
          }
          const shortMetrics = metrics.find(m => m.id === short.id)!;
          expect(shortMetrics.identifierLines).toHaveLength(1);
          await form.locator('[name="current_password"]').focus();
          await page.keyboard.press('Tab');
          await expect(form.getByRole('button', { name: 'Remove', exact: true })).toBeFocused();
          expect(await post(page, form, 'wrong-password')).toBe(422);
          expect(fixture()).toEqual(before);
          await expect(form.locator('[name="current_password"]')).toHaveValue('');
          expect(await post(page, form, 'password123')).toBe(303);
          await expect(form).toHaveCount(0);
          const after = fixture();
          expect(after.blocks).toEqual(before.blocks.filter(b => b.id !== short.id));
          expect(after.other_state_hash).toBe(before.other_state_hash);
          expect(after.audit).toHaveLength(before.audit.length + 1);
          await page.screenshot({ path: path.join(directory, 'block-removed.png'), fullPage: true, animations: 'disabled' });
        });
      });
    }
  }
}
