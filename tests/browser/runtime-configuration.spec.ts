import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/feature-flags-runtime-config-2026-11-08');
function fixture(action = 'state') {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'runtime-config-fixture.php'), action], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}

for (const javaScriptEnabled of [true, false]) {
  test.describe(javaScriptEnabled ? 'with JavaScript' : 'without JavaScript', () => {
    test.use({ javaScriptEnabled });
    test('the inherited moderation mode survives saving and the Block review stays native', async ({ page }, info) => {
      const errors: string[] = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
      fixture('reset');
      await page.goto('/login');
      await page.locator('[name="email"]').fill('admin@retro.test');
      await page.locator('[name="password"]').fill('password123');
      await page.locator('form[action="/login"] button[type="submit"]').click();
      await page.waitForURL(url => url.pathname !== '/login');
      await page.goto('/admin/moderation');
      await expect(page).toHaveTitle(/Anti-abuse/);
      await expect(page).toHaveURL(/\/admin\/moderation$/);
      await expect(page.getByRole('heading', { name: 'Enforcement', exact: true })).toBeVisible();

      const form = page.locator('form[action="/admin/moderation"]');
      const mode = form.locator('[name="antiabuse_mode"]');
      await expect(mode).toHaveValue('hold');
      expect(fixture().has_override).toBe(false);
      mkdirSync(path.join(evidence, info.project.name), { recursive: true });
      const suffix = javaScriptEnabled ? 'js' : 'no-js';
      await page.screenshot({ path: path.join(evidence, info.project.name, `inherited-hold-${suffix}.png`), fullPage: true, animations: 'disabled' });

      await form.locator('textarea').fill('retained phrase');
      await form.getByRole('button', { name: 'Save anti-abuse settings' }).click();
      await expect(page.getByRole('status')).toContainText('Anti-abuse settings updated.');
      await expect(mode).toHaveValue('hold');
      expect(fixture().mode).toBe('hold');
      expect(fixture().before.antiabuse_mode).toBe('hold');
      expect(fixture().after.antiabuse_mode).toBe('hold');

      await mode.selectOption('block');
      await form.getByRole('button', { name: 'Save anti-abuse settings' }).click();
      await expect(page.getByRole('heading', { name: 'Review Block mode' })).toBeVisible();
      expect(fixture().mode).toBe('hold');
      await form.getByRole('button', { name: 'Back to editing' }).click();
      await expect(mode).toHaveValue('block');
      await expect(form.locator('textarea')).toHaveValue('retained phrase');
      await form.getByRole('button', { name: 'Save anti-abuse settings' }).click();
      await form.getByRole('button', { name: 'Confirm Block mode' }).click();
      await expect(mode).toHaveValue('block');
      expect(fixture().mode).toBe('block');
      expect(fixture().before.antiabuse_mode).toBe('hold');
      expect(fixture().after.antiabuse_mode).toBe('block');
      expect(errors).toEqual([]);
      await page.screenshot({ path: path.join(evidence, info.project.name, `confirmed-block-${suffix}.png`), fullPage: true, animations: 'disabled' });
    });
  });
}
