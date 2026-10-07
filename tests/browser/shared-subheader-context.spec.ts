import { test, expect, type BrowserContext, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/shared-subheader-2026-10-07');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
let memberCookies: Awaited<ReturnType<BrowserContext['cookies']>> | undefined;

test.beforeEach(() => {
  if (!/^retroboards_e2e_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '')) {
    throw new Error('Use an explicit private retroboards_e2e_* database for shared-header fixtures.');
  }
  execFileSync('php', ['tests/browser/member-surfaces-fixture.php'], { cwd: ROOT, env: process.env });
});

async function login(page: Page) {
  if (memberCookies) {
    await page.context().addCookies(memberCookies);
    return;
  }
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
  memberCookies = await page.context().cookies();
}

async function saveAppearance(page: Page, theme: string, large: boolean) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="theme"][value="${theme}"]`).check({ force: true });
  await page.locator('select[name="font_size"]').selectOption(large ? 'large' : 'medium');
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

// Compare control rectangles, rather than their text baselines: the display
// heading and the 44px targets use different font metrics but must occupy one row.
async function expectSharedRow(page: Page, inbox = false) {
  const row = page.locator('[data-subheader]');
  await expect(row).toHaveCount(1);
  await expect(page.locator('main h1')).toHaveCount(1);
  const selectors = inbox
    ? ['h1', '[data-inbox-scope-menu] > summary', '.inbox-sort-menu > summary', '.inbox-actions > summary', '[data-create-trigger]']
    : ['h1', '[data-create-trigger]'];
  for (const selector of selectors) {
    await expect(row.locator(selector), selector).toHaveCount(1);
    await expect(row.locator(selector), selector).toBeVisible();
  }
  const geometry = await row.evaluate((element, selectors) => {
    const bounds = element.getBoundingClientRect();
    return {
      row: { x: bounds.left, right: bounds.right, y: bounds.top, bottom: bounds.bottom },
      client: element.clientWidth, scroll: element.scrollWidth,
      controls: selectors.map(selector => {
        const control = element.querySelector(selector)!;
        const box = control.getBoundingClientRect();
        return { selector, x: box.left, right: box.right, y: box.top, bottom: box.bottom, width: box.width, height: box.height };
      }),
      viewport: document.documentElement.clientWidth,
      pageScroll: document.documentElement.scrollWidth,
    };
  }, selectors);
  expect(geometry.pageScroll, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.viewport);
  expect(geometry.scroll, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.client + 1);
  const overlap = Math.min(...geometry.controls.map(box => box.bottom)) - Math.max(...geometry.controls.map(box => box.y));
  expect(overlap, `All context controls must share a row: ${JSON.stringify(geometry)}`).toBeGreaterThan(1);
  for (const [index, box] of geometry.controls.entries()) {
    expect(box.x, box.selector).toBeGreaterThanOrEqual(geometry.row.x - 1);
    expect(box.right, box.selector).toBeLessThanOrEqual(geometry.row.right + 1);
    expect(box.y, box.selector).toBeGreaterThanOrEqual(geometry.row.y - 1);
    expect(box.bottom, box.selector).toBeLessThanOrEqual(geometry.row.bottom + 1);
    if (index) expect(box.x, `${box.selector} overlaps its preceding control`).toBeGreaterThanOrEqual(geometry.controls[index - 1].right - 1);
    if (box.selector !== 'h1') expect(box.height, box.selector).toBeGreaterThanOrEqual(44);
  }
  if (inbox) {
    await expect(page.locator('[data-inbox] h1, [data-inbox] .inbox-list-head, [data-inbox] [data-inbox-scope-menu], [data-inbox] .inbox-sort-menu, [data-inbox] .inbox-actions')).toHaveCount(0);
    await expect(row.locator('[data-inbox-current-count]')).toHaveCount(1);
    await expect(row.locator('[data-inbox-current-count]')).toBeVisible();
    await expect(row.locator('[data-inbox-count-label]')).toHaveText(/^(topic|topics)$/);
    await expect(row.locator('[data-inbox-scope-menu] > summary')).toContainText('Show:');
    await expect(row.locator('.inbox-sort-menu > summary')).toContainText('Sort:');
  }
}

async function expectPanelContained(page: Page, selector: string) {
  const panel = page.locator(selector);
  await expect(panel).toBeVisible();
  const box = (await panel.boundingBox())!;
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize()!.width + 1);
  expect(box.y).toBeGreaterThanOrEqual(0);
  expect(box.y + box.height).toBeLessThanOrEqual(page.viewportSize()!.height + 1);
}

async function capture(page: Page, project: string, name: string) {
  const directory = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', project);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), animations: 'disabled' });
}

for (const javaScriptEnabled of [true, false]) {
  test(`Inbox context, Show/count, Sort, actions and creation share one row ${javaScriptEnabled ? 'enhanced' : 'native'}`, async ({ browser, baseURL }, info) => {
    test.setTimeout(180000);
    const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport, isMobile, hasTouch, deviceScaleFactor });
    try {
      const page = await context.newPage();
      await login(page);
      for (const theme of ['light', 'dark']) {
        for (const large of [false, true]) {
          await saveAppearance(page, theme, large);
          for (const width of info.project.name === 'mobile' ? [320, 390, 860] : [861, 901, 1440]) {
            await page.setViewportSize({ width, height: 844 });
            await page.goto('/inbox?scope=starred&order=commended');
            if (javaScriptEnabled) await page.evaluate(() => document.fonts.ready);
            await expectSharedRow(page, true);
            await page.locator('[data-inbox-scope-menu] > summary').click();
            await expectPanelContained(page, '.inbox-scope-menu-panel');
            await expect(page.locator('[data-inbox-scope-menu] a[aria-current="page"]')).toContainText('Starred');
            await page.locator('[data-inbox-scope-menu] > summary').click();
            await page.locator('.inbox-sort-menu > summary').click();
            await expectPanelContained(page, '.inbox-sort-menu > .inbox-menu-panel');
            await expect(page.locator('.inbox-sort-menu a[aria-current="page"]')).toContainText('Most commended');
            await page.locator('.inbox-sort-menu > summary').click();
            await page.locator('.inbox-actions > summary').click();
            await expectPanelContained(page, '.inbox-actions > .inbox-menu-panel');
            await page.locator('.inbox-actions > summary').click();
            if (width === 390 || width === 1440) await capture(page, info.project.name, `inbox-${width}-${theme}-${large ? 'large' : 'default'}-${javaScriptEnabled ? 'js' : 'native'}`);
          }
        }
      }
    } finally {
      await context.close();
    }
  });

  test(`representative page contexts sit beside shared creation ${javaScriptEnabled ? 'enhanced' : 'native'}`, async ({ browser, baseURL }, info) => {
    test.setTimeout(180000);
    const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport, isMobile, hasTouch, deviceScaleFactor });
    try {
      const page = await context.newPage();
      await login(page);
      for (const [theme, large] of [['light', false], ['dark', true]] as const) {
        await saveAppearance(page, theme, large);
        for (const width of info.project.name === 'mobile' ? [320, 860] : [861, 1440]) {
          await page.setViewportSize({ width, height: 844 });
          for (const route of ['/messages', '/search', '/compose?board=general', '/notifications', '/settings/account', '/feed', '/leaderboard', '/tags']) {
            await page.goto(route);
            if (javaScriptEnabled) await page.evaluate(() => document.fonts.ready);
            await expectSharedRow(page);
          }
        }
      }
    } finally {
      await context.close();
    }
  });

  test(`Search scope and order remain native URL choices in the shared row ${javaScriptEnabled ? 'enhanced' : 'native'}`, async ({ browser, baseURL }, info) => {
    const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport, isMobile, hasTouch, deviceScaleFactor });
    try {
      const page = await context.newPage();
      await login(page);
      await page.goto('/search?q=keyboard&scope=everything&order=relevance');
      await expectSharedRow(page);
      for (const [name, parameter, value] of [['Replies', 'scope', 'replies'], ['Newest', 'order', 'newest']] as const) {
        const link = page.locator('[data-subheader]').getByRole('link', { name, exact: true, includeHidden: true });
        const menu = link.locator('xpath=ancestor::details[1]');
        await expect(menu).toHaveCount(1);
        await menu.locator(':scope > summary').click();
        await expect(link).toBeVisible();
        await link.click();
        await expect.poll(() => new URL(page.url()).searchParams.get(parameter)).toBe(value);
        expect(new URL(page.url()).searchParams.get('q')).toBe('keyboard');
      }
      expect(new URL(page.url()).searchParams.get('scope')).toBe('replies');
      expect(new URL(page.url()).searchParams.get('order')).toBe('newest');
      await expect(page.locator('.search-query-well')).toHaveValue('keyboard');
    } finally {
      await context.close();
    }
  });

  test(`Feed and Leaderboard choices remain native destinations beside creation ${javaScriptEnabled ? 'enhanced' : 'native'}`, async ({ browser, baseURL }, info) => {
    const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport, isMobile, hasTouch, deviceScaleFactor });
    try {
      const page = await context.newPage();
      await login(page);
      for (const [route, name, parameter, value] of [
        ['/feed?view=following', 'Latest', 'view', 'latest'],
        ['/leaderboard?window=all', 'Month', 'window', 'month'],
      ] as const) {
        await page.goto(route);
        await expectSharedRow(page);
        const link = page.locator('[data-subheader]').getByRole('link', { name, exact: true, includeHidden: true });
        const menu = link.locator('xpath=ancestor::details[1]');
        await expect(menu).toHaveCount(1);
        await menu.locator(':scope > summary').click();
        await expect(link).toBeVisible();
        await link.click();
        await expect.poll(() => new URL(page.url()).searchParams.get(parameter)).toBe(value);
        await expectSharedRow(page);
      }
    } finally {
      await context.close();
    }
  });
}

test('the Messages search disclosure preserves native search and unread filters', async ({ browser, baseURL }, info) => {
  const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport, isMobile, hasTouch, deviceScaleFactor });
  try {
    const page = await context.newPage();
    await login(page);
    await page.goto('/messages?filter=unread');
    const menu = page.locator('[data-subheader] .dm-search-menu');
    await menu.locator(':scope > summary').click();
    const search = menu.locator('input[name="q"]');
    await expect(search).toBeVisible();
    await search.fill('qqqzzz');
    await search.press('Enter');
    await expect.poll(() => new URL(page.url()).searchParams.get('q')).toBe('qqqzzz');
    expect(new URL(page.url()).searchParams.get('filter')).toBe('unread');
    await expect(page.locator('.dm-listpane')).toContainText('No conversations match your search.');
    await page.locator('.dm-listpane-filters').getByRole('link', { name: 'All', exact: true }).click();
    expect(new URL(page.url()).searchParams.get('q')).toBe('qqqzzz');
    expect(new URL(page.url()).searchParams.has('filter')).toBe(false);
    await page.locator('.dm-listpane-filters').getByRole('link', { name: /^Unread/ }).click();
    expect(new URL(page.url()).searchParams.get('q')).toBe('qqqzzz');
    expect(new URL(page.url()).searchParams.get('filter')).toBe('unread');
  } finally {
    await context.close();
  }
});

test('the consolidated Inbox choices preserve URLs, shared dismissal and accessible menus', async ({ page }, info) => {
  await page.setViewportSize({ width: info.project.name === 'mobile' ? 390 : 1440, height: 844 });
  await login(page);
  await page.goto('/inbox?scope=for_you&order=newest');
  await expectSharedRow(page, true);
  await page.locator('[data-inbox-scope-menu] > summary').click();
  await page.locator('[data-inbox-scope-menu] a[href="/inbox?scope=starred&order=newest"]').click();
  await expect(page).toHaveURL(/scope=starred&order=newest$/);
  await page.locator('.inbox-sort-menu > summary').click();
  await page.locator('.inbox-sort-menu').getByRole('link', { name: 'Most commended', exact: true }).click();
  await expect(page).toHaveURL(/scope=starred&order=commended$/);
  const show = page.locator('[data-inbox-scope-menu] > summary');
  await show.click();
  await page.locator('[data-create-trigger]').click();
  await expect(page.locator('[data-inbox-scope-menu]')).not.toHaveAttribute('open', '');
  await page.keyboard.press('Escape');
  await expect(page.locator('[data-create-trigger]')).toBeFocused();
  const actions = page.locator('.inbox-actions > summary');
  await actions.focus();
  await page.keyboard.press('Enter');
  await page.locator('.inbox-help > summary').click();
  await expect(page.locator('.inbox-keyboard-help')).toBeVisible();
  await expectPanelContained(page, '.inbox-actions > .inbox-menu-panel');
  const audit = await new AxeBuilder({ page }).include('[data-subheader]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  expect(audit.violations).toEqual([]);
  await page.keyboard.press('Escape');
  await expect(actions).toBeFocused();
  await expect(page.locator('.inbox-actions > .inbox-menu-panel')).toBeHidden();
  await page.setViewportSize({ width: page.viewportSize()!.width, height: 300 });
  await show.click();
  await expect.poll(() => page.locator('[data-inbox-list]').evaluate(element => element.scrollHeight - element.clientHeight)).toBeGreaterThan(0);
  await page.locator('[data-inbox-list]').evaluate(element => { element.scrollTop = element.scrollHeight; });
  await expect(page.locator('[data-inbox-scope-menu]')).not.toHaveAttribute('open', '');
});
