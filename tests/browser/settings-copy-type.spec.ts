import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/settings-copy-type-2026-10-22');

function fixture(command = 'reset') {
  return JSON.parse(execFileSync('php', ['tests/browser/account-settings-repairs-fixture.php', command], {
    cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array' }, encoding: 'utf8',
  }));
}

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('settings-repair@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await expect(page).not.toHaveURL(/\/login/);
}

async function theme(page: Page, value: string) {
  await page.mouse.move(0, 0);
  await page.locator('html').evaluate((html, theme) => html.setAttribute('data-theme', theme), value);
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})));
  });
}

async function capture(page: Page, info: TestInfo, name: string) {
  const directory = path.join(evidence, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: true });
}

async function fits(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
  const outside = await page.locator('.settings-pane input:visible, .settings-pane select:visible, .settings-pane button:visible').evaluateAll(nodes => nodes.filter(node => {
    const box = node.getBoundingClientRect();
    return box.left < -1 || box.right > document.documentElement.clientWidth + 1;
  }).map(node => node.outerHTML.slice(0, 140)));
  expect(outside).toEqual([]);
}

test('settings clarity and type roles remain coherent across themes', async ({ page }, info) => {
  test.setTimeout(120_000);
  fixture();
  fixture('delivery-on');
  await login(page);
  const metrics: Array<Record<string, unknown>> = [];
  for (const route of ['account', 'boards', 'notifications', 'appearance', 'preferences', 'privacy']) {
    await page.goto(`/settings/${route}`);
    await expect(page.locator('.settings-head p')).toHaveText('Manage your profile, preferences, and account security.');
    for (const register of ['light', 'dark']) {
      await theme(page, register);
      const observed = await page.locator('.settings-pane').first().evaluate(pane => {
        const label = pane.querySelector('.field > span:first-child')!;
        const caption = pane.querySelector('.scribe-panel-head')!;
        const style = getComputedStyle(label);
        const head = getComputedStyle(caption);
        return {
          rootSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
          labelSize: parseFloat(style.fontSize), labelWeight: style.fontWeight, labelFamily: style.fontFamily,
          captionSize: parseFloat(head.fontSize), captionWeight: head.fontWeight,
          buttons: Array.from(pane.querySelectorAll('button')).filter(node => node.getClientRects().length).map(node => node.getBoundingClientRect().height),
          labelFontReady: document.fonts.check('400 16px "Marcellus"'),
        };
      });
      metrics.push({ route, register, ...observed });
      const directory = path.join(evidence, info.project.name);
      fs.mkdirSync(directory, { recursive: true });
      fs.writeFileSync(path.join(directory, 'type-metrics.json'), JSON.stringify(metrics, null, 2) + '\n');
      await capture(page, info, `type-${route}-${register}`);
      expect(observed.labelSize).toBeCloseTo(observed.rootSize * 0.86, 2);
      expect(observed.labelWeight).toBe('400');
      expect(observed.labelFamily).toContain('Marcellus');
      expect(observed.captionSize).toBeCloseTo(observed.rootSize * 0.78, 2);
      expect(observed.captionWeight).toBe('400');
      expect(observed.labelFontReady).toBe(true);
      expect(observed.buttons.every(height => height >= 44)).toBe(true);
      await fits(page);
      const result = await new AxeBuilder({ page }).include('.settings-screen')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(result.violations.map(({ id, nodes }) => ({ id, targets: nodes.map(node => node.target) }))).toEqual([]);
    }
  }
});

test.describe('settings clarity with native forms', () => {
  test.use({ javaScriptEnabled: false });
  test('specific creation labels and notification opt-out retain native behavior', async ({ page }, info) => {
    fixture();
    fixture('delivery-on');
    await login(page);
    await page.goto('/settings/boards');
    await expect(page.getByLabel('Board folder name', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Feed name', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Bookmark folder name', { exact: true })).toBeVisible();
    const create = page.locator('form[action="/settings/board-folders"]');
    await create.getByLabel('Board folder name', { exact: true }).fill('My reading');
    await create.getByRole('button', { name: 'New folder', exact: true }).click();
    await expect(page.locator('.board-folder-card .org-folder-name')).toHaveText('My reading');
    await page.goto('/settings/notifications');
    await expect(page.getByRole('combobox', { name: 'Daily digest time', exact: true })).toHaveAttribute('aria-describedby', 'digest-time-hint');
    await expect(page.locator('#digest-time-hint')).toHaveText('Uses the selected timezone, or UTC if none is set.');
    await page.getByRole('switch', { name: 'Email notifications', exact: true }).uncheck();
    await page.getByRole('button', { name: 'Save subscription for Settings evidence topic', exact: true }).click();
    expect(Number(fixture('inspect').subscription.email_enabled)).toBe(0);
    await page.getByRole('button', { name: 'Turn off notifications for Settings evidence topic', exact: true }).click();
    expect(fixture('inspect').subscription.frequency).toBe('off');
    await capture(page, info, 'native-notifications-off');
    await page.setViewportSize({ width: 320, height: 900 });
    for (const route of ['boards', 'notifications', 'preferences']) {
      await page.goto(`/settings/${route}`);
      await page.locator('html').evaluate(html => html.style.fontSize = '32px');
      await fits(page);
      await capture(page, info, `large-text-${route}`);
    }
  });
});
