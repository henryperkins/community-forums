import { test, expect, Page, type BrowserContext } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * The phone header's rows (2026-10-06 critique; the owner kept ADR 0042's two
 * rows and asked for the details to be fixed).
 *
 * At the drawer breakpoint every first-row control is a 44px target with 8px
 * between targets (easing to 4px only on the narrowest phones), the routes'
 * second row keeps the desktop's 13px labels, and the reading and focus order
 * follow the rows. A bar with a single route keeps one row. The community's
 * name shortens instead of vanishing, and drops whole rather than to a stub.
 * The divider stands only beside a pane toggle. The drawer has its own close
 * control. Without JavaScript the phone rail scrolls away instead of covering
 * the page. Touch screens above the breakpoint keep the same 44px floor, and
 * above 1080px the name and the search share the room.
 */

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/header-phone-rows-2026-10-07');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

function shot(name: string, project: string) {
  const dir = path.join(OUT, project);
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
}

// One sign-in per account per run: login is rate-limited per account.
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['cookies']>>>();
async function signInAs(page: Page, email: string) {
  const cached = sessions.get(email);
  if (cached) { await page.context().addCookies(cached); return; }
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  await page.goto('/');
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.count()) { await skip.first().click(); await expect(skip.first()).toBeHidden(); }
  sessions.set(email, await page.context().cookies());
}

/** The widest counts the bar can show: the poll's answer, and the Inbox's server count. */
async function widestCounts(page: Page) {
  await page.route('**/notifications/bell?format=json', (route) => route.fulfill({ json: { unread: 105, dm_unread: 105, items: [] } }));
}
async function widestInbox(page: Page) {
  await expect(page.locator('.forum-bar [data-bell] [data-notification-count]')).toHaveText('99+');
  await page.evaluate(() => {
    let count = document.querySelector('.forum-bar [data-inbox-unread-count]');
    if (!count) {
      count = document.createElement('span');
      count.className = 'forum-bar-count';
      count.setAttribute('data-inbox-unread-count', '105');
      document.querySelector('.forum-bar [data-primary-route="inbox"]')!.appendChild(count);
    }
    count.textContent = '99+';
  });
}

/** The first row's controls left to right, the gaps between them, and the routes' row. */
async function bar(page: Page) {
  return page.evaluate(() => {
    const header = document.querySelector('.forum-bar')!;
    const shown = (el: Element) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
    const boxes = (name: string, selector: string) => Array.from(header.querySelectorAll(selector)).filter(shown).map((el) => {
      const r = el.getBoundingClientRect();
      return { name, left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
    });
    const row = [
      ...boxes('opener', ':scope > .nav-toggle'), ...boxes('lockup', '.forum-bar-brand'), ...boxes('search', '.forum-bar-search'),
      ...boxes('compose', '.forum-bar-compose .btn'), ...boxes('bell', '[data-bell]'), ...boxes('seat', '.forum-bar-user'),
      ...boxes('signup', '.forum-bar-signup'), ...boxes('signin', '.forum-bar-signin'),
    ].sort((a, b) => a.left - b.left);
    const routes = Array.from(header.querySelectorAll('.forum-bar-surface')).filter(shown).map((el) => {
      const r = el.getBoundingClientRect();
      return { top: r.top, height: r.height, left: r.left, right: r.right, fontSize: parseFloat(getComputedStyle(el).fontSize) };
    });
    const root = document.documentElement;
    return {
      row, routes,
      gaps: row.slice(1).map((box, i) => box.left - row[i].right),
      height: header.getBoundingClientRect().height,
      token: getComputedStyle(root).getPropertyValue('--topbar-h').trim(),
      overflow: root.scrollWidth - root.clientWidth,
      viewport: root.clientWidth,
    };
  });
}

/** Whether the community's name shows, and how much of it. */
async function name(page: Page) {
  return page.evaluate(() => {
    const lockup = document.querySelector('.forum-bar-brand')!.getBoundingClientRect();
    const mark = document.querySelector<HTMLElement>('.forum-bar-wordmark')!;
    const r = mark.getBoundingClientRect();
    return {
      shown: r.width > 0 && r.top < lockup.bottom - 1 && r.bottom > lockup.top + 1,
      width: r.width,
      em: parseFloat(getComputedStyle(mark).fontSize),
      whole: mark.scrollWidth <= mark.clientWidth + 1,
      ellipsis: getComputedStyle(mark).textOverflow,
    };
  });
}

test('the phone rows give every control a 44px target, 8px apart, and the routes 13px labels', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'the two-row phone bar');
  await signInAs(page, 'alice@retro.test');
  await widestCounts(page);
  for (const width of [390, 360, 320]) {
    await page.setViewportSize({ width, height: 844 });
    await page.goto('/inbox');
    await widestInbox(page);
    const m = await bar(page);
    const where = `${width}px`;
    expect(m.height, `${where}: ADR 0042's two rows`).toBe(108);
    expect(m.token).toBe('108px');
    expect(m.overflow, `${where}: no sideways scroll, even with "99+" counts`).toBeLessThanOrEqual(0);
    expect(m.row.map((box) => box.name)).toEqual(['opener', 'lockup', 'search', 'compose', 'bell', 'seat']);
    expect(new Set(m.row.map((box) => Math.round(box.top))).size, `${where}: one first row`).toBe(1);
    for (const box of m.row) {
      expect(box.height, `${where}: ${box.name} height`).toBeGreaterThanOrEqual(44);
      // The lockup is the one control that yields past 44px, never past its mark.
      expect(box.width, `${where}: ${box.name} width`).toBeGreaterThanOrEqual(box.name === 'lockup' ? 34 : 44);
    }
    expect(Math.min(...m.gaps), `${where}: space between targets`).toBeGreaterThanOrEqual(width >= 340 ? 7.5 : 3.5);
    expect(m.routes).toHaveLength(3);
    for (const route of m.routes) {
      expect(route.top, `${where}: the routes take the second row`).toBeGreaterThanOrEqual(m.row[0].bottom);
      expect(route.fontSize, `${where}: route labels`).toBeGreaterThanOrEqual(13);
      expect(route.height).toBeGreaterThanOrEqual(44);
      expect(route.left).toBeGreaterThanOrEqual(0);
      expect(route.right).toBeLessThanOrEqual(m.viewport);
    }
    if (width === 390) {
      for (const theme of ['light', 'dark'] as const) {
        await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme);
        await page.waitForTimeout(300);
        await page.screenshot({ path: shot(`rows-390-${theme}.png`, info.project.name), clip: { x: 0, y: 0, width, height: 112 } });
      }
    } else {
      await page.screenshot({ path: shot(`rows-${width}.png`, info.project.name), clip: { x: 0, y: 0, width, height: 112 } });
    }
  }
});

test('the reading and focus order follow the rows', async ({ page }, info) => {
  await signInAs(page, 'alice@retro.test');
  // The lockup's wrapper is display: contents above the drawer breakpoint, so
  // it counts as present unless it is display: none.
  const order = () => page.evaluate(() => Array.from(document.querySelector('.forum-bar')!.children)
    .filter((el) => getComputedStyle(el).display !== 'none')
    .map((el) => el.classList[0]));
  if (info.project.name === 'desktop') {
    // Above the drawer breakpoint the routes sit beside the lockup, as drawn.
    await page.goto('/inbox');
    expect(await order()).toEqual(['forum-bar-lockup', 'forum-bar-surfaces', 'forum-bar-searchwrap', 'forum-bar-right']);
    // Crossing the breakpoint moves them with the layout, and back.
    await page.setViewportSize({ width: 390, height: 844 });
    await expect.poll(order).toEqual(['nav-toggle', 'forum-bar-lockup', 'forum-bar-searchwrap', 'forum-bar-right', 'forum-bar-surfaces']);
    await page.setViewportSize({ width: 1280, height: 800 });
    await expect.poll(order).toEqual(['forum-bar-lockup', 'forum-bar-surfaces', 'forum-bar-searchwrap', 'forum-bar-right']);
    return;
  }
  await page.goto('/inbox');
  expect(await order()).toEqual(['nav-toggle', 'forum-bar-lockup', 'forum-bar-searchwrap', 'forum-bar-right', 'forum-bar-surfaces']);
  // Tab walks the first row left to right, then the routes' row.
  await page.locator('body').focus();
  const visited: { name: string; top: number; left: number }[] = [];
  for (let step = 0; step < 16 && visited.length < 9; step++) {
    await page.keyboard.press('Tab');
    const stop = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || !el.closest('.forum-bar')) return null;
      const r = el.getBoundingClientRect();
      return { name: el.getAttribute('aria-label') ?? el.textContent!.trim().replace(/\d+\+?$/, ''), top: Math.round(r.top), left: Math.round(r.left) };
    });
    if (stop) visited.push(stop);
  }
  expect(visited.map((stop) => stop.name)).toEqual([
    'Open board rail', 'RetroBoards', 'Search the council', 'New topic',
    expect.stringMatching(/^Notifications/), 'Account menu for Alice Avery', 'Boards', expect.stringMatching(/^Inbox/), expect.stringMatching(/^Messages/),
  ]);
  // Row by row: the top never climbs back, and within a row the stops run left to right.
  for (let i = 1; i < visited.length; i++) {
    expect(visited[i].top).toBeGreaterThanOrEqual(visited[i - 1].top);
    if (visited[i].top === visited[i - 1].top) expect(visited[i].left).toBeGreaterThan(visited[i - 1].left);
  }
});

test('a guest\'s phone bar keeps one row, the community\'s name and 44px sign-in targets', async ({ page }, info) => {
  await page.goto('/');
  if (info.project.name === 'desktop') {
    // Wide bars keep the route: it is the guest's current surface beside the search.
    await expect(page.getByRole('navigation', { name: 'Primary', exact: true })).toBeVisible();
    expect((await bar(page)).height).toBe(62);
    return;
  }
  const m = await bar(page);
  expect(m.height, 'one row: Boards alone does not take a second').toBe(62);
  expect(m.token).toBe('62px');
  await expect(page.getByRole('navigation', { name: 'Primary', exact: true })).toBeHidden();
  await expect(page.locator('.forum-bar-brand')).toHaveAttribute('href', '/');
  expect(m.row.map((box) => box.name)).toEqual(['opener', 'lockup', 'search', 'signup', 'signin']);
  for (const box of m.row) expect(box.height, box.name).toBeGreaterThanOrEqual(44);
  const lockup = await name(page);
  expect(lockup.shown, 'the community is named at 390px').toBe(true);
  expect(lockup.width).toBeGreaterThanOrEqual(lockup.em * 3 - 1);
  for (const theme of ['light', 'dark'] as const) {
    await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme);
    await page.waitForTimeout(300);
    await page.screenshot({ path: shot(`guest-390-${theme}.png`, info.project.name), clip: { x: 0, y: 0, width: 390, height: 70 } });
  }
  // The drawer and its scrim start under the one row.
  await page.locator('[data-nav-toggle]').click();
  const rail = (await page.locator('#sidebar-nav').boundingBox())!;
  const scrim = (await page.locator('[data-nav-scrim]').boundingBox())!;
  expect(Math.round(rail.y)).toBe(62);
  expect(Math.round(scrim.y)).toBe(62);
});

test('the community\'s name shortens, and drops whole rather than to a stub', async ({ page }, info) => {
  await signInAs(page, 'alice@retro.test');
  await widestCounts(page);
  const widths = info.project.name === 'mobile' ? [320, 360, 375, 390, 414, 430, 600, 860] : [861, 900, 1081, 1180, 1280];
  const seen: string[] = [];
  for (const width of widths) {
    await page.setViewportSize({ width, height: 844 });
    await page.goto('/inbox');
    await widestInbox(page);
    const lockup = await name(page);
    const where = `${width}px`;
    if (lockup.shown && !lockup.whole) {
      expect(lockup.ellipsis).toBe('ellipsis');
      expect(lockup.width, `${where}: at least about five letters, never a stub`).toBeGreaterThanOrEqual(lockup.em * 3 - 1);
    }
    seen.push(`${width}:${lockup.shown ? (lockup.whole ? 'whole' : 'short') : 'mark'}`);
    if (!lockup.shown && info.project.name === 'mobile') {
      // The spare room is not part of the link: with the name gone it is the mark's target.
      expect((await page.locator('.forum-bar-brand').boundingBox())!.width, `${where}: no blank link`).toBeLessThanOrEqual(44.5);
    }
    // Where the layer hid the name (900px and below) a wide enough row now shows it whole.
    if (width >= 600 && width <= 900) expect(lockup.shown && lockup.whole, `${where}: the name is shown whole`).toBe(true);
    if (width === 1180) {
      // Above 1080px the name and the search share the room: the default name
      // was clipped there while the search kept its 300px.
      expect(lockup.whole, `${where}: the name is whole`).toBe(true);
      const search = (await page.locator('.forum-bar-search').boundingBox())!;
      expect(search.width).toBeGreaterThanOrEqual(180);
      await page.screenshot({ path: shot('lockup-1180.png', info.project.name), clip: { x: 0, y: 0, width, height: 64 } });
    }
    if (width === 900 || width === 860 || width === 430) {
      await page.screenshot({ path: shot(`lockup-${width}.png`, info.project.name), clip: { x: 0, y: 0, width, height: width <= 860 ? 112 : 64 } });
    }
  }
  fs.writeFileSync(shot('lockup-widths.txt', info.project.name), seen.join('\n') + '\n');
});

test('the divider stands only beside a pane toggle', async ({ page }, info) => {
  await signInAs(page, 'alice@retro.test');
  if (info.project.name === 'mobile') {
    // 721–860px: the toggles give way to the drawer, and the divider with them.
    await page.setViewportSize({ width: 800, height: 844 });
    await page.goto('/inbox');
    await expect(page.locator('.forum-bar-divider')).toBeHidden();
    await expect(page.locator('.forum-bar-compose .btn span')).toBeVisible();
    await page.screenshot({ path: shot('divider-800.png', info.project.name), clip: { x: 0, y: 0, width: 800, height: 112 } });
    return;
  }
  await page.setViewportSize({ width: 1024, height: 800 });
  await page.goto('/c/general');
  await expect(page.locator('[data-panel-form="rail"] button')).toBeVisible();
  await expect(page.locator('.forum-bar-divider')).toBeVisible();
  // A page without the member shell (the plain error page) has no toggle to divide.
  const missing = await page.goto('/no-such-page-header-phone-rows');
  expect(missing!.status()).toBe(404);
  await expect(page.locator('.forum-bar')).toBeVisible();
  await expect(page.locator('.forum-bar-divider')).toHaveCount(0);
  await page.screenshot({ path: shot('divider-404.png', info.project.name), clip: { x: 0, y: 0, width: 1024, height: 64 } });
});

test('the drawer closes from inside itself', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'the drawer is the phone rail');
  await signInAs(page, 'alice@retro.test');
  await page.goto('/inbox');
  const opener = page.locator('[data-nav-toggle]');
  await opener.click();
  const rail = page.locator('#sidebar-nav');
  const close = rail.getByRole('link', { name: 'Close board rail', exact: true });
  await expect(close).toBeVisible();
  await expect(close, 'the drawer hands focus to its close control, as Topic tools does').toBeFocused();
  const box = (await close.boundingBox())!;
  const railBox = (await rail.boundingBox())!;
  expect(box.width).toBeGreaterThanOrEqual(44);
  expect(box.height).toBeGreaterThanOrEqual(44);
  expect(box.x + box.width).toBeLessThanOrEqual(railBox.x + railBox.width);
  expect(box.y).toBeGreaterThanOrEqual(railBox.y);
  for (const theme of ['light', 'dark'] as const) {
    await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme);
    await page.waitForTimeout(350);
    await page.screenshot({ path: shot(`drawer-${theme}.png`, info.project.name) });
  }
  // Shift+Tab from the first stop wraps inside the drawer rather than escaping it.
  await page.keyboard.press('Shift+Tab');
  expect(await page.evaluate(() => !!document.activeElement && !!document.activeElement.closest('#sidebar-nav'))).toBe(true);
  await close.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('body')).not.toHaveClass(/nav-open/);
  await expect(opener).toBeFocused();
  await expect(opener).toHaveAttribute('aria-expanded', 'false');
  // A tap closes it too.
  await opener.click();
  await close.tap();
  await expect(page.locator('body')).not.toHaveClass(/nav-open/);
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('the phone rail scrolls away instead of covering the page', async ({ page }, info) => {
    test.skip(info.project.name !== 'mobile', 'the stacked rail is the phone layout without a drawer');
    await signInAs(page, 'alice@retro.test');
    await page.goto('/c/general');
    const rail = page.locator('nav.board-rail');
    await expect(rail).toBeVisible();
    expect(await rail.evaluate((el) => getComputedStyle(el).position)).toBe('static');
    expect((await rail.boundingBox())!.height, 'bounded, so the content starts on the first screen').toBeLessThanOrEqual(176);
    await page.screenshot({ path: shot('nojs-board-top.png', info.project.name) });
    const before = (await rail.boundingBox())!.y;
    const scrolled = await page.evaluate(() => { window.scrollTo(0, document.documentElement.scrollHeight); return window.scrollY; });
    await page.waitForTimeout(150);
    expect(scrolled, 'the board page scrolls').toBeGreaterThan(150);
    // It moves with the page: a sticky rail would have stayed under the bar.
    expect((await rail.boundingBox())!.y).toBeCloseTo(before - scrolled, 0);
    const hit = await page.evaluate(() => {
      const el = document.elementFromPoint(document.documentElement.clientWidth / 2, window.innerHeight / 2);
      return { inRail: !!el?.closest('.board-rail'), inMain: !!el?.closest('main') };
    });
    expect(hit, 'the middle of the screen is the page, not the rail').toEqual({ inRail: false, inMain: true });
    await page.screenshot({ path: shot('nojs-board-scrolled.png', info.project.name) });
    // The filter that the stuck rail intercepted (notifications-unified:139).
    await page.goto('/notifications');
    const unread = page.getByRole('link', { name: 'Unread', exact: true });
    await unread.tap();
    await expect(page).toHaveURL(/filter=unread/);
    await expect(page.getByRole('link', { name: 'Unread', exact: true })).toHaveAttribute('aria-current', 'page');
  });
});

test('touch screens above the drawer breakpoint keep the 44px floor', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'a tablet-width touch screen');
  const context = await browser.newContext({ baseURL, viewport: { width: 1024, height: 768 }, hasTouch: true });
  const page = await context.newPage();
  try {
    await signInAs(page, 'alice@retro.test');
    await page.goto('/inbox');
    expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true);
    const m = await bar(page);
    expect(m.height).toBe(62);
    expect(m.overflow).toBeLessThanOrEqual(0);
    for (const box of m.row) expect(box.height, box.name).toBeGreaterThanOrEqual(44);
    for (const selector of ['.forum-bar-surface', '[data-panel-form="rail"] button']) {
      for (const control of await page.locator(selector).all()) {
        expect((await control.boundingBox())!.height, selector).toBeGreaterThanOrEqual(44);
      }
    }
    const compose = page.locator('.forum-bar-compose .btn');
    const label = await compose.evaluate((el) => {
      const button = el.getBoundingClientRect();
      const text = el.querySelector('span')!.getBoundingClientRect();
      return { above: text.top - button.top, below: button.bottom - text.bottom };
    });
    expect(Math.abs(label.above - label.below), 'New topic stays centred in its taller target').toBeLessThanOrEqual(2);
    await page.screenshot({ path: shot('touch-1024.png', info.project.name), clip: { x: 0, y: 0, width: 1024, height: 64 } });
  } finally {
    await context.close();
  }
});
