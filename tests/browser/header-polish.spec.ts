import { test, expect, Page, type BrowserContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * The header's polish pass (2026-10-06 critique, minor observations and
 * persona flags; /impeccable polish).
 *
 * A focused control keeps its own corner and wears the gold halo. Shortcut
 * hints appear only where the shortcut works, in the platform's own modifier.
 * Counts carry their words on their links, as the bell's always did. The bell
 * is current on its own page. The account menu falls into groups and Settings
 * has its own glyph. A guest's Log in answers the pointer. The tour's first
 * steps say what is true at every width.
 */

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/header-polish-2026-10-07');
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

/** chrome_reader with one unread topic in the Inbox (the chrome-consistency fixture). */
function unreadInbox() {
  execFileSync('php', ['tests/browser/chrome-consistency-fixture.php'], {
    cwd: ROOT, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
}

/** The poll's answer, served at once (app.js shortPoll runs on load). */
async function serveCounts(page: Page, unread: number, dms: number) {
  await page.route('**/notifications/bell?format=json', (route) => route.fulfill({ json: { unread, dm_unread: dms, items: [] } }));
}

/** The engine's own platform, read the way app.js reads it. */
async function apple(page: Page) {
  return page.evaluate(() => /Mac|iPhone|iPad|iPod/i.test(((navigator as Navigator & { userAgentData?: { platform?: string } }).userAgentData?.platform)
    || navigator.platform || navigator.userAgent || ''));
}

async function theme(page: Page, value: 'light' | 'dark') {
  await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), value);
  await page.waitForTimeout(300);
}

test('a focused control keeps its own corner and wears the gold halo', async ({ page }, info) => {
  await signInAs(page, 'alice@retro.test');
  await page.goto('/inbox');
  await page.locator('body').focus();
  const stops: { index: number; name: string; focused: string; halo: string }[] = [];
  for (let step = 0; step < 20 && stops.length < 12; step++) {
    await page.keyboard.press('Tab');
    const stop = await page.evaluate(async (index) => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || !el.closest('.forum-bar')) return null;
      el.setAttribute('data-probe', String(index));
      // .btn eases its shadow in; read the settled state.
      await Promise.all(el.getAnimations().map((a) => a.finished.catch(() => undefined)));
      const cs = getComputedStyle(el);
      return { index, name: el.getAttribute('aria-label') ?? el.textContent!.trim(), focused: cs.borderTopLeftRadius, halo: cs.boxShadow };
    }, stops.length);
    if (stop) stops.push(stop);
    else if (stops.length) break;
  }
  expect(stops.length, 'the walk reached the whole bar').toBeGreaterThanOrEqual(info.project.name === 'mobile' ? 9 : 10);
  await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
  for (const stop of stops) {
    const rest = await page.locator(`[data-probe="${stop.index}"]`).evaluate((el) => getComputedStyle(el).borderTopLeftRadius);
    expect(stop.focused, `${stop.name}: the corner it has at rest`).toBe(rest);
    expect(stop.halo, `${stop.name}: the gold halo`).toMatch(/0px 0px 0px 3px/);
  }
  // The search field stays a pill when focused (it squared off to 2px).
  if (info.project.name === 'desktop') {
    await page.locator('.forum-bar-search').focus();
    await page.keyboard.press('Shift+Tab');
    await page.keyboard.press('Tab');
    expect(await page.locator('.forum-bar-search').evaluate((el) => getComputedStyle(el).borderTopLeftRadius)).toBe('999px');
    const box = (await page.locator('.forum-bar-search').boundingBox())!;
    await page.screenshot({ path: shot('focus-search.png', info.project.name), clip: { x: box.x - 10, y: box.y - 10, width: box.width + 20, height: box.height + 20 } });
  }
  const opener = info.project.name === 'mobile' ? page.locator('[data-nav-toggle]') : page.locator('.forum-bar-brand');
  await opener.focus();
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Tab');
  const focused = (await opener.boundingBox())!;
  await page.screenshot({ path: shot('focus-first-stop.png', info.project.name), clip: { x: Math.max(0, focused.x - 10), y: Math.max(0, focused.y - 10), width: focused.width + 20, height: focused.height + 20 } });
});

test('on a phone the lockup holds only what it shows', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'the phone lockup');
  await signInAs(page, 'alice@retro.test');
  await page.route('**/notifications/bell?format=json', (route) => route.fulfill({ json: { unread: 105, dm_unread: 105, items: [] } }));
  await page.goto('/inbox');
  await expect(page.locator('.forum-bar [data-bell] [data-notification-count]')).toHaveText('99+');
  const brand = page.locator('.forum-bar-brand');
  // At 390px with the widest counts the name has no room for five letters: it goes whole.
  await expect(page.locator('.forum-bar-wordmark')).toBeHidden();
  const box = (await brand.boundingBox())!;
  expect(box.width, 'the link is the mark').toBeLessThanOrEqual(44.5);
  // The spare room beside it is not the link: a tap there goes nowhere.
  const spare = await page.evaluate(([x, y]) => {
    const hit = document.elementFromPoint(x, y);
    return { inLink: !!hit?.closest('.forum-bar-brand'), inLockup: !!hit?.closest('.forum-bar-lockup') };
  }, [box.x + box.width + 12, box.y + box.height / 2]);
  expect(spare).toEqual({ inLink: false, inLockup: true });
  await brand.focus();
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Tab');
  await expect(brand).toBeFocused();
  await page.screenshot({ path: shot('lockup-focus-390.png', info.project.name), clip: { x: 0, y: 0, width: 390, height: 112 } });
  // With room to spare the name returns, still inside the link and nothing more.
  await page.setViewportSize({ width: 600, height: 844 });
  await expect(page.locator('.forum-bar-wordmark')).toBeVisible();
  const wide = (await brand.boundingBox())!;
  const mark = (await page.locator('.forum-bar-wordmark').boundingBox())!;
  expect(wide.x + wide.width, 'the link ends with the name').toBeLessThanOrEqual(mark.x + mark.width + 1);
});

test('shortcut hints appear only where the shortcut works, in the platform\'s own modifier', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the hint is drawn from 1081px');
  await signInAs(page, 'alice@retro.test');
  await page.goto('/inbox');
  const mac = await apple(page);
  const hint = page.locator('.forum-bar-search [data-shortcut-hint]');
  await expect(hint).toBeVisible();
  await expect(hint).toHaveText(mac ? '⌘K' : 'Ctrl K');
  expect(parseFloat(await hint.evaluate((el) => getComputedStyle(el).fontSize)), 'the 0.7rem chip floor').toBeGreaterThanOrEqual(11.2);
  await expect(page.locator('.forum-bar-search')).toHaveAttribute('aria-keyshortcuts', mac ? 'Meta+K' : 'Control+K');
  await expect(page.locator('.forum-bar-search')).toHaveAccessibleName('Search the council');
  await expect(page.locator('[data-panel-form="rail"] button')).toHaveAttribute('title', mac ? 'Board rail (⌘B)' : 'Board rail (Ctrl+B)');
  await expect(page.locator('[data-panel-form="reading"] button')).toHaveAttribute('aria-keyshortcuts', mac ? 'Meta+J' : 'Control+J');
  const box = (await page.locator('.forum-bar-search').boundingBox())!;
  await page.screenshot({ path: shot('hint.png', info.project.name), clip: { x: box.x - 6, y: box.y - 6, width: box.width + 12, height: box.height + 12 } });
  // The hint is true: the modifier it names opens search.
  await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
  await page.keyboard.press(mac ? 'Meta+k' : 'Control+k');
  await expect(page).toHaveURL(/\/search$/);
});

test('on an Apple device the hints name ⌘', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'the hint is drawn from 1081px');
  // The engines here report Linux; this context reports a Mac, the way Safari
  // and Chrome on macOS do (navigator.platform, and userAgentData where it exists).
  const context = await browser.newContext({ baseURL, viewport: { width: 1280, height: 800 } });
  await context.addInitScript(() => {
    Object.defineProperty(Navigator.prototype, 'platform', { get: () => 'MacIntel' });
    if ('userAgentData' in Navigator.prototype) {
      Object.defineProperty(Navigator.prototype, 'userAgentData', { get: () => ({ platform: 'macOS', mobile: false, brands: [] }) });
    }
  });
  const page = await context.newPage();
  try {
    await signInAs(page, 'alice@retro.test');
    await page.goto('/inbox');
    await expect(page.locator('.forum-bar-search [data-shortcut-hint]')).toHaveText('⌘K');
    await expect(page.locator('.forum-bar-search')).toHaveAttribute('aria-keyshortcuts', 'Meta+K');
    await expect(page.locator('[data-panel-form="rail"] button')).toHaveAttribute('title', 'Board rail (⌘B)');
    const box = (await page.locator('.forum-bar-search').boundingBox())!;
    await page.screenshot({ path: shot('hint-apple.png', info.project.name), clip: { x: box.x - 6, y: box.y - 6, width: box.width + 12, height: box.height + 12 } });
  } finally {
    await context.close();
  }
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test('no shortcut is claimed, because nothing answers it', async ({ page }, info) => {
    test.skip(info.project.name !== 'desktop', 'the hint is drawn from 1081px');
    await signInAs(page, 'alice@retro.test');
    await page.goto('/inbox');
    await expect(page.locator('.forum-bar-search [data-shortcut-hint]')).toBeHidden();
    await expect(page.locator('.forum-bar-search')).not.toHaveAttribute('aria-keyshortcuts', /.+/);
    await expect(page.locator('.forum-bar-search')).toHaveAccessibleName('Search the council');
    await expect(page.locator('[data-panel-form="rail"] button')).toHaveAttribute('title', 'Board rail');
  });
});

test('counts carry their words on their links, as the bell\'s do', async ({ page }) => {
  unreadInbox();
  await signInAs(page, 'chrome-reader@retro.test');
  const inbox = page.locator('[data-primary-route="inbox"]');
  const messages = page.locator('[data-primary-route="messages"]');
  for (const [dms, name] of [[1, 'Messages, 1 unread conversation'], [3, 'Messages, 3 unread conversations'], [0, 'Messages']] as const) {
    await page.unrouteAll({ behavior: 'ignoreErrors' });
    await serveCounts(page, 12, dms);
    await page.goto('/inbox');
    await expect(page.locator('.forum-bar [data-bell] [data-notification-count]')).toHaveText('12');
    await expect(messages).toHaveAccessibleName(name);
    if (dms > 0) await expect(messages.locator('[data-dm-unread-count]')).toHaveAttribute('aria-hidden', 'true');
  }
  await expect(inbox).toHaveAccessibleName('Inbox, 1 unread topic');
  await expect(inbox.locator('[data-inbox-unread-count]')).toHaveAttribute('aria-hidden', 'true');
  await expect(page.locator('.forum-bar [data-bell]')).toHaveAccessibleName('Notifications, 12 unread');
});

test('the bell is current on the notifications page, as a route is on its own', async ({ page }, info) => {
  await signInAs(page, 'alice@retro.test');
  await page.goto('/inbox');
  const wash = await page.locator('.forum-bar-surface.is-active').evaluate((el) => getComputedStyle(el).backgroundColor);
  await expect(page.locator('.forum-bar [data-bell]')).not.toHaveAttribute('aria-current', /.+/);
  await page.goto('/notifications');
  const bell = page.locator('.forum-bar [data-bell]');
  await expect(bell).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('.forum-bar-surface[aria-current="page"]')).toHaveCount(0);
  expect(await bell.evaluate((el) => getComputedStyle(el).backgroundColor), 'the current wash').toBe(wash);
  expect(await bell.evaluate((el) => getComputedStyle(el, '::after').borderBottom), 'the current rule').toMatch(/^2px solid/);
  const width = page.viewportSize()!.width;
  for (const register of ['light', 'dark'] as const) {
    await theme(page, register);
    await page.screenshot({ path: shot(`bell-current-${register}.png`, info.project.name), clip: { x: 0, y: 0, width, height: width <= 860 ? 112 : 64 } });
  }
});

test('the account menu falls into groups and Settings has its own glyph', async ({ page }, info) => {
  await signInAs(page, 'elrond@retro.test');
  await page.goto('/inbox');
  const account = page.locator('.identity-menu > summary');
  await expect(account).toHaveAccessibleName('Account menu for Elrond Peredhel');
  await account.click();
  const panel = page.locator('.identity-menu-panel');
  await expect(panel).toBeVisible();
  const settings = panel.getByRole('link', { name: 'Settings', exact: true });
  await expect(settings.locator('svg')).toHaveClass(/icon-settings/);
  await expect(panel.getByRole('link', { name: 'Profile', exact: true }).locator('svg')).toHaveClass(/icon-user/);
  // Two hairlines: one opens settings and authority, one sets Log out apart.
  const breaks = await panel.locator('.identity-menu-break').evaluateAll((nodes) => nodes.map((n) => ({
    text: n.textContent!.trim(), rule: getComputedStyle(n, '::before').borderTopWidth + ' ' + getComputedStyle(n, '::before').borderTopStyle,
  })));
  expect(breaks.map((b) => b.text)).toEqual(['Settings', 'Log out']);
  for (const b of breaks) expect(b.rule).toBe('1px solid');
  const box = (await panel.boundingBox())!;
  const registers = info.project.name === 'desktop' ? ['light', 'dark'] as const : ['light'] as const;
  for (const register of registers) {
    await theme(page, register);
    await page.screenshot({ path: shot(`menu-${register}.png`, info.project.name), clip: { x: box.x - 8, y: box.y - 8, width: box.width + 16, height: box.height + 16 } });
  }
});

test('a guest\'s Log in answers the pointer and Sign up keeps a target', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'hover is a pointer state');
  await page.goto('/');
  const signin = page.locator('.forum-bar-signin');
  const rest = await signin.evaluate((el) => getComputedStyle(el).backgroundColor);
  await signin.hover();
  await expect.poll(() => signin.evaluate((el) => getComputedStyle(el).backgroundColor)).not.toBe(rest);
  const signup = (await page.locator('.forum-bar-signup').boundingBox())!;
  expect(signup.height).toBeGreaterThanOrEqual(32);
  await page.screenshot({ path: shot('guest-hover.png', info.project.name), clip: { x: 980, y: 0, width: 300, height: 64 } });
});

test('the tour\'s first steps say what is true at every width', async ({ page }, info) => {
  await signInAs(page, 'alice@retro.test');
  await page.goto('/settings/account');
  await page.evaluate(() => (document.querySelector('[data-tour-replay]') as HTMLElement).click());
  const popover = page.locator('.tour-popover');
  await expect(popover).toBeVisible();
  await expect(popover).toContainText('This is your community home. Select it any time to come back here.');
  await expect(popover).not.toContainText('Click the name');
  await page.screenshot({ path: shot('tour-welcome.png', info.project.name) });
  await popover.getByRole('button', { name: 'Next' }).click();
  await expect(popover).toContainText('Find topics and people from search, at the top of every page.');
  await expect(popover).not.toContainText('search box');
  await popover.getByRole('button', { name: 'Skip' }).click();
});
