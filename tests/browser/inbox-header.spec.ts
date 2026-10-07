import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const out = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/inbox-header');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

test.beforeEach(() => {
  execFileSync('php', ['tests/browser/member-surfaces-fixture.php'], {
    cwd: root,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
});

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function capture(page: Page, project: string, name: string) {
  const dir = path.join(out, project);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`) });
}

test('topics appear early and every primary destination fits without horizontal scrolling', async ({ page }, info) => {
  await login(page);
  for (const theme of ['light', 'dark']) {
    await page.goto('/settings/appearance');
    await page.locator(`input[name="theme"][value="${theme}"]`).check({ force: true });
    await page.getByRole('button', { name: 'Save appearance' }).click();
    await page.waitForURL(/\/settings\/appearance/);
    await page.goto('/inbox?scope=for_you&order=active');
    const widths = info.project.name === 'mobile' ? [320, 390, 430, 760, 860] : [861, 901, 1024, 1080, 1280, 1440];
    for (const width of widths) {
      await page.setViewportSize({ width, height: 844 });
      await page.evaluate(() => document.fonts.ready);
      const first = await page.locator('[data-inbox-row]').first().boundingBox();
      expect(first).not.toBeNull();
      expect(first!.y, `${theme}, ${width}px: topics should precede secondary help/settings`).toBeLessThan(360);
      const nav = page.getByRole('navigation', { name: 'Primary', exact: true });
      const bounds = (await nav.boundingBox())!;
      for (const name of ['Boards', 'Inbox', 'Messages']) {
        const box = (await nav.getByRole('link', { name: new RegExp(`^${name}`) }).boundingBox())!;
        expect(box.x).toBeGreaterThanOrEqual(bounds.x - 1);
        expect(box.x + box.width).toBeLessThanOrEqual(bounds.x + bounds.width + 1);
      }
      expect(await nav.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
      expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
      if (width === 390 || width === 1280) await capture(page, info.project.name, `inbox-${theme}`);
    }
  }
});

test('scope and sort preserve each other and menus support keyboard dismissal and help', async ({ page }, info) => {
  await login(page);
  await page.goto('/inbox?scope=for_you&order=active');
  await page.locator('[data-inbox-scope-menu] > summary').click();
  await page.locator('[data-inbox-scope-menu] a[href="/inbox?scope=starred&order=active"]').click();
  await expect(page).toHaveURL(/scope=starred&order=active$/);
  await page.locator('.inbox-sort-menu > summary').click();
  await page.locator('.inbox-sort-menu').getByRole('link', { name: 'Most commended', exact: true }).click();
  await expect(page).toHaveURL(/scope=starred&order=commended$/);
  await page.locator('[data-inbox-scope-menu] > summary').click();
  await page.keyboard.press('Escape');
  await expect(page.locator('[data-inbox-scope-menu] > summary')).toBeFocused();
  const actions = page.locator('.inbox-actions > summary');
  await actions.focus();
  await page.keyboard.press('Enter');
  await expect(page.getByRole('button', { name: 'Mark this page read', exact: true })).toBeVisible();
  await page.locator('.inbox-help > summary').click();
  await expect(page.locator('.inbox-keyboard-help')).toBeVisible();
  const panel = page.locator('.inbox-actions > .inbox-menu-panel');
  const box = (await panel.boundingBox())!;
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize()!.width);
  expect(box.y + box.height).toBeLessThanOrEqual(page.viewportSize()!.height);
  const audit = await new AxeBuilder({ page }).include('[data-inbox-list]').include('[data-subheader]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  expect(audit.violations).toEqual([]);
  await capture(page, info.project.name, 'inbox-help');
  await page.keyboard.press('Escape');
  await expect(actions).toBeFocused();
  await expect(panel).toBeHidden();
});

test('scope, sort and page actions work without JavaScript', async ({ browser, baseURL }, info) => {
  const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport, isMobile, hasTouch, deviceScaleFactor });
  try {
    const page = await context.newPage();
    await login(page);
    await page.goto('/inbox?scope=for_you&order=active');
    for (const width of info.project.name === 'mobile' ? [390, 430] : [1280]) {
      await page.setViewportSize({ width, height: 844 });
      if (width === 430) {
        const show = (await page.locator('[data-inbox-scope-menu] > summary').boundingBox())!;
        const sort = (await page.locator('.inbox-sort-menu > summary').boundingBox())!;
        expect(Math.abs(show.y - sort.y), 'Pin the side-by-side case that previously overflowed').toBeLessThan(1);
      }
      await page.locator('.inbox-sort-menu > summary').click();
      const panel = (await page.locator('.inbox-sort-menu > .inbox-menu-panel').boundingBox())!;
      expect(panel.x).toBeGreaterThanOrEqual(0);
      expect(panel.x + panel.width).toBeLessThanOrEqual(width);
      await page.locator('.inbox-sort-menu > summary').click();
    }
    await page.locator('[data-inbox-scope-menu] > summary').click();
    await page.locator('[data-inbox-scope-menu] a[href="/inbox?scope=unread&order=active"]').click();
    await page.locator('.inbox-sort-menu > summary').click();
    await page.locator('.inbox-sort-menu').getByRole('link', { name: 'Newest first', exact: true }).click();
    await expect(page).toHaveURL(/scope=unread&order=newest$/);
    const count = await page.locator('[data-inbox-row]').count();
    const displayedIds = await page.locator('[data-inbox-row]').evaluateAll(rows => rows.map(row => row.getAttribute('data-thread-id')));
    expect(count).toBeGreaterThan(0);
    await page.locator('.inbox-actions > summary').click();
    await page.getByRole('button', { name: 'Mark this page read', exact: true }).click();
    await expect(page).toHaveURL(/scope=unread&order=newest$/);
    await expect(page.locator('.flash')).toContainText(`${count} topic`);
    const remainingIds = await page.locator('[data-inbox-row]').evaluateAll(rows => rows.map(row => row.getAttribute('data-thread-id')));
    expect(remainingIds.filter(id => displayedIds.includes(id))).toEqual([]);
    await capture(page, info.project.name, 'inbox-after-page-read-no-js');
  } finally {
    await context.close();
  }
});
