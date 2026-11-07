import { test, expect, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const out = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/chrome-consistency-2026-10-06');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
let fixture: { topic: string; thread: number };

test.beforeEach(() => {
  fixture = JSON.parse(execFileSync('php', ['tests/browser/chrome-consistency-fixture.php'], {
    cwd: root, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString());
});

async function login(page: Page, admin = false) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill(admin ? 'admin@retro.test' : 'chrome-reader@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function theme(page: Page, value: string) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="theme"][value="${value}"]`).check({ force: true });
  await page.getByRole('button', { name: 'Save appearance', exact: true }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

async function capture(page: Page, info: TestInfo, name: string) {
  const dir = path.join(out, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`), animations: 'disabled' });
}

async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
}

test('the open board drawer owns keyboard focus and restores it on dismissal and resize', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'drawer behavior uses the mobile project');
  await login(page);
  await page.goto('/settings/account');
  const opener = page.locator('[data-nav-toggle]');
  const rail = page.locator('[data-sidebar]');
  await opener.click();
  await expect.poll(() => rail.evaluate(el => el.contains(document.activeElement))).toBe(true);
  await expect(page.locator('#main')).toHaveAttribute('inert', '');
  const stops = rail.locator('a[href], button, summary, [tabindex]').filter({ visible: true });
  await stops.last().focus();
  await page.keyboard.press('Tab');
  await expect.poll(() => rail.evaluate(el => el.contains(document.activeElement))).toBe(true);
  await stops.first().focus();
  await page.keyboard.press('Shift+Tab');
  await expect(stops.last()).toBeFocused();
  await capture(page, info, 'drawer-keyboard');
  await page.keyboard.press('Escape');
  await expect(opener).toBeFocused();
  await expect(opener).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('#main')).not.toHaveAttribute('inert', '');
  await opener.click();
  await page.locator('[data-nav-scrim]').click({ position: { x: 375, y: 100 } });
  await expect(opener).toBeFocused();
  await opener.click();
  await page.setViewportSize({ width: 1280, height: 800 });
  await expect(page.locator('body')).not.toHaveClass(/nav-open/);
  await expect(page.locator('#main')).not.toHaveAttribute('inert', '');
  await expect(page.locator('[data-nav-scrim]')).toBeHidden();
  await expect.poll(() => rail.evaluate(el => el.contains(document.activeElement))).toBe(true);
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(opener).toBeFocused();
  await expect(rail).toBeHidden();
  // A persisted closed desktop rail must never keep focus after a phone resize.
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.locator('[data-panel-form="rail"] button').click();
  await expect(page.locator('body')).toHaveAttribute('data-rail-open', '0');
  await page.setViewportSize({ width: 390, height: 844 });
  await opener.click();
  await page.setViewportSize({ width: 1280, height: 800 });
  await expect(page.locator('#main')).toBeFocused();
  await expect(page.locator('#main')).not.toHaveAttribute('inert', '');
  await expect(rail).toBeHidden();
});

test('document-scrolling rails stay below the header and fill the remaining viewport', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'sticky document rail uses a desktop viewport');
  await login(page);
  for (const register of ['light', 'dark']) {
    await theme(page, register);
    await page.goto('/settings/account');
    await page.evaluate(() => window.scrollTo(0, 450));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(100);
    const bar = (await page.locator('.forum-bar').boundingBox())!;
    const rail = (await page.locator('[data-sidebar]').boundingBox())!;
    expect(rail.y).toBeGreaterThanOrEqual(bar.y + bar.height - 1);
    expect(rail.y + rail.height).toBeLessThanOrEqual(801);
    await capture(page, info, `settings-scrolled-${register}`);
    // Fixed-height routes have a different scroll owner, but the same visible offset.
    for (const route of ['/', '/inbox', '/search', '/compose']) {
      await page.goto(route);
      const header = (await page.locator('.forum-bar').boundingBox())!;
      expect((await page.locator('[data-sidebar]').boundingBox())!.y).toBeCloseTo(header.y + header.height, 0);
    }
  }
});

test('every member route, the inbox included, shares one centred shell frame', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the --maxw frame binds only at desktop widths');
  await login(page);
  // The inbox once lifted the cap for itself, so above 1280px the rail jumped to
  // the window edge on the way in and back under the lockup on the way out.
  const routes = ['/', '/c/chrome-place', fixture.topic, '/inbox', '/messages', '/notifications', '/search', '/compose', '/settings/account'];
  const frame = () => page.evaluate(() => {
    const box = (selector: string) => {
      const rect = document.querySelector(selector)!.getBoundingClientRect();
      return [Math.round(rect.x), Math.round(rect.width)];
    };
    return {
      shell: box('.app-shell'), rail: box('[data-sidebar]'), main: box('#main'),
      subheader: box('.forum-subheader'), lockup: box('.forum-bar-brand')[0],
    };
  });
  for (const width of [1280, 1440, 1920]) {
    await page.setViewportSize({ width, height: 800 });
    let reference: Awaited<ReturnType<typeof frame>> | undefined;
    for (const route of routes) {
      expect((await page.goto(route))!.status()).toBe(200);
      const measured = await frame();
      reference ??= measured;
      expect(measured, `${route} at ${width}px`).toEqual(reference);
    }
    expect(reference!.shell[1]).toBeLessThanOrEqual(1280);
    // Once the window clears --maxw and the bar's 22px gutters, the rail starts under the lockup.
    if (width >= 1440) expect(reference!.rail[0]).toBe(reference!.lockup);
    await noOverflow(page);
  }
  for (const route of ['/', '/inbox']) {
    await page.goto(route);
    await capture(page, info, `shell-1920-${route === '/' ? 'boards' : 'inbox'}`);
  }
  // A closed rail hands its width to the content inside the same frame, on every route.
  // The background save follows the form's redirect; leaving mid-chain would abort it
  // into the native fallback, so wait for the chain's final response.
  const saved = page.waitForResponse(response => response.request().resourceType() === 'fetch'
    && response.request().redirectedFrom()?.url().endsWith('/settings/member-surfaces') === true);
  await page.locator('[data-panel-form="rail"] button').click();
  await saved;
  await expect(page.locator('body')).toHaveAttribute('data-rail-open', '0');
  const closed = await frame();
  expect(closed.main).toEqual(closed.shell);
  await capture(page, info, 'shell-1920-inbox-rail-closed');
  for (const route of ['/', '/settings/account', '/inbox']) {
    await page.goto(route);
    expect(await frame(), `${route} with the rail closed`).toEqual(closed);
  }
});

test('the inbox reading pane keeps every formatting command reachable inside the shared frame', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the reading pane is a column only from 1280px');
  await login(page);
  await page.setViewportSize({ width: 1920, height: 800 });
  const openPreview = async () => {
    await page.goto('/inbox?scope=watching');
    await page.locator(`[data-inbox-row] a[data-inbox-preview-url][href="${fixture.topic}"]`).click();
    await expect(page.locator('[data-inbox-reading-content]')).toContainText('Chrome regression body.');
    // The pane's reply box rests on one line, as the topic dock does, until
    // someone answers; its formatting row belongs to the open box.
    const reply = page.locator('[data-inbox-reading] .reply-composer');
    await expect(reply.locator('.composer-toolbar')).toBeHidden();
    await reply.locator('.composer-input:visible, .ProseMirror:visible').first().click();
    await expect(reply).toHaveClass(/\bis-expanded\b/);
    await reply.locator('.composer-toolbar').scrollIntoViewIfNeeded();
  };
  const formattingRow = () => page.evaluate(() => {
    const pane = document.querySelector('[data-inbox-reading]')!.getBoundingClientRect();
    const shown = [...document.querySelectorAll('[data-inbox-reading] .composer-toolbar button')]
      .filter(button => button.getClientRects().length > 0)
      .map(button => {
        const box = button.getBoundingClientRect();
        return { name: button.getAttribute('aria-label')!.replace(/ \(.*\)$/, ''), inside: box.left >= pane.left && box.right <= pane.right };
      });
    return { pane: pane.width, names: shown.map(button => button.name), clipped: shown.filter(button => !button.inside).map(button => button.name) };
  });

  const wholeRow = ['Bold', 'Italic', 'Strike', 'Inline code', 'Quote', 'Heading', 'Bullet list', 'Numbered list', 'Code block', 'Spoiler', 'Link'];

  // With the board rail open the queue is the --list-w column (410px) and the
  // pane takes the rest of --maxw, about 600px: room for the whole row. Below
  // 30rem the pane would keep the essentials and name its overflow (the
  // container rule AppInboxRemediationTest pins); the split no longer gets there.
  await openPreview();
  const railOpen = await formattingRow();
  expect(railOpen.pane).toBeGreaterThan(480);
  expect(railOpen.names).toEqual(wholeRow);
  expect(railOpen.clipped).toEqual([]);
  await capture(page, info, 'reading-pane-formatting-row');

  // A closed rail only widens it.
  const saved = page.waitForResponse(response => response.request().resourceType() === 'fetch'
    && response.request().redirectedFrom()?.url().endsWith('/settings/member-surfaces') === true);
  await page.locator('[data-panel-form="rail"] button').click();
  await saved;
  await openPreview();
  const railClosed = await formattingRow();
  expect(railClosed.pane).toBeGreaterThan(railOpen.pane);
  expect(railClosed.names).toEqual(wholeRow);
  expect(railClosed.clipped).toEqual([]);
});

test('plain error headers expose only real pane targets and preserve member preferences', async ({ page }, info) => {
  await login(page);
  for (const route of ['/chrome-page-does-not-exist', '/admin/settings']) {
    const response = await page.goto(route);
    expect(response!.status()).toBe(route.startsWith('/admin') ? 403 : 404);
    await expect(page.locator('.forum-bar [data-panel-form], .forum-bar [data-nav-toggle], .forum-bar [data-nav-fallback]')).toHaveCount(0);
    const results = await new AxeBuilder({ page }).include('.forum-bar').analyze();
    expect(results.violations).toEqual([]);
    await noOverflow(page);
  }
  await capture(page, info, 'error-header');
  await page.goto('/');
  await expect(page.locator('body')).toHaveAttribute('data-rail-open', '1');
  await expect(page.locator('[data-panel-form="rail"]')).toHaveCount(1);
});

test('Boards owns tag pages and canonical topics while folder rows share active and unread state', async ({ page }, info) => {
  await login(page);
  for (const route of ['/tags', '/tags/chrome-tag', fixture.topic]) {
    expect((await page.goto(route))!.status()).toBe(200);
    await expect(page.locator('[data-primary-route="boards"]')).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('[data-primary-route="boards"]')).toHaveAttribute('href', '/');
    // A current entry is still a link to its surface (ADR 0032 decision 1), so it
    // keeps the link cursor: Chromium computes `pointer`, WebKit `auto`.
    await expect(page.locator('[data-primary-route="boards"]')).not.toHaveCSS('cursor', 'default');
  }
  for (const route of ['/c/chrome-place', fixture.topic]) {
    await page.goto(route);
    const rows = page.locator('[data-sidebar] a[href="/c/chrome-place"]');
    await expect(rows).toHaveCount(2);
    for (const row of await rows.all()) {
      await expect(row).toHaveClass(/is-active/);
      await expect(row).toHaveAttribute('aria-current', 'page');
      await expect(row).not.toHaveCSS('cursor', 'default');
      await expect(row).toHaveAttribute('data-board-slug', 'chrome-place');
    }
  }
  await capture(page, info, 'topic-parent-state');
});

test('reading an inbox preview updates every folder and category unread pill', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=unread');
  const rows = page.locator('[data-sidebar] a[href="/c/chrome-place"]');
  await expect(rows).toHaveCount(2);
  for (const row of await rows.all()) await expect(row.locator('[data-board-unread-count]')).toHaveAttribute('data-board-unread-count', '1');
  await page.locator(`[data-inbox-row] a[data-inbox-preview-url][href="${fixture.topic}"]`).click();
  await expect(page.locator('[data-inbox-reading-content]')).toContainText('Chrome regression body.');
  await expect(rows.locator('[data-board-unread-count]')).toHaveCount(0);
  await page.reload();
  await expect(rows.locator('[data-board-unread-count]')).toHaveCount(0);
});

test('the current admin area remains visible on narrow screens in both themes', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'narrow admin areas use the mobile project');
  await login(page, true);
  for (const register of ['light', 'dark']) {
    await theme(page, register);
    for (const route of ['/admin/settings', '/admin/features', '/admin/packages']) {
      await page.goto(route);
      const current = page.locator('[data-admin-current-area]');
      await expect(current).toBeVisible();
      const box = (await current.boundingBox())!;
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(391);
      await current.click();
      const areas = page.locator('[data-admin-mobile-areas]');
      await expect(areas).toBeVisible();
      await expect(areas.locator('[aria-current="page"]')).toHaveCount(1);
      await expect(areas.locator('a[href="/admin"]')).toBeVisible();
      const axe = await new AxeBuilder({ page }).include('.admin-bar').analyze();
      expect(axe.violations).toEqual([]);
      await noOverflow(page);
      if (route.endsWith('/settings')) await capture(page, info, `admin-current-area-${register}`);
    }
  }
});

for (const javaScriptEnabled of [true, false]) {
  test(`long subscription names fit the settings content with JavaScript ${javaScriptEnabled ? 'on' : 'off'}`, async ({ browser, baseURL }, info) => {
    test.skip(info.project.name !== 'mobile', 'content wrapping uses narrow viewports');
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    try {
      await login(page);
      for (const register of ['light', 'dark']) {
        await theme(page, register);
        for (const width of [320, 390, 719, 720, 860, 861, 1024, 1280, 1440]) {
          await page.setViewportSize({ width, height: 844 });
          await page.goto('/settings/notifications');
          await noOverflow(page);
          const name = page.locator('a.account-row-name').first();
          await expect(name).toHaveText('UnbrokenTitle'.repeat(12));
          expect(await name.evaluate(el => el.scrollWidth - el.clientWidth)).toBeLessThanOrEqual(1);
          const row = await name.evaluate(el => {
            const parent = el.closest('li')!;
            const title = el.getBoundingClientRect();
            const controls = parent.querySelector('form.stacked')!.getBoundingClientRect();
            const off = parent.querySelector('form:last-child')!.getBoundingClientRect();
            return { width: parent.getBoundingClientRect().width, titleWidth: title.width,
              titleBottom: title.bottom, controlsTop: controls.top, controlsBottom: controls.bottom, offTop: off.top };
          });
          expect(row.titleWidth, `${width}px: the title has a readable measure`).toBeGreaterThanOrEqual(Math.min(row.width, 320) * .9);
          if (row.titleWidth >= row.width * .9) {
            expect(row.controlsTop, `${width}px: controls follow the title`).toBeGreaterThanOrEqual(row.titleBottom - 1);
            expect(row.offTop, `${width}px: the off action follows the controls`).toBeGreaterThanOrEqual(row.controlsBottom - 1);
          }
          if (width === 390) {
            await name.scrollIntoViewIfNeeded();
            await capture(page, info, `subscriptions-${register}-${javaScriptEnabled ? 'js' : 'no-js'}`);
          }
        }
      }
    } finally { await context.close(); }
  });
}

test('native admin area navigation and board shortcuts work without JavaScript', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'mobile', 'one no-JS mobile context per engine');
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await login(page, true);
    await page.goto('/admin/settings');
    const current = page.locator('[data-admin-current-area]');
    await expect(current).toContainText('Settings');
    await current.click();
    await page.locator('[data-admin-mobile-areas]').getByRole('link', { name: 'Overview', exact: true }).click();
    await expect(page).toHaveURL(/\/admin$/);
    await expect(current).toContainText('Overview');
    await capture(page, info, 'admin-no-js');
    await context.clearCookies();
    await login(page);
    await page.goto('/c/chrome-place');
    const rows = page.locator('[data-sidebar] a[href="/c/chrome-place"]');
    await expect(rows).toHaveCount(2);
    for (const row of await rows.all()) await expect(row).toHaveAttribute('aria-current', 'page');
    await rows.first().click();
    await expect(page).toHaveURL(/\/c\/chrome-place$/);
    await noOverflow(page);
  } finally { await context.close(); }
});
