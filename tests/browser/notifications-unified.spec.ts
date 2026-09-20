import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
if (!process.env.RB_EVIDENCE_DIR) throw new Error('RB_EVIDENCE_DIR is required for isolated notification captures.');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR);
function fixture(command = 'reset'): { user_id: number; unread: number; old_id: number } {
  return JSON.parse(execFileSync('php', ['tests/browser/notifications-unified-fixture.php', command], {
    cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array', MAIL_FROM: 'notification-evidence@example.test' }, encoding: 'utf8',
  }));
}
async function login(page: Page) {
  await page.goto('/login');
  await page.locator('[name="email"]').fill('notifications-reader@retro.test');
  await page.locator('[name="password"]').fill('password123');
  await page.locator('button[type="submit"]').click();
  await expect(page).not.toHaveURL(/\/login/);
}
async function capture(page: Page, info: TestInfo, name: string) {
  const dir = path.join(evidence, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: false });
}
async function geometry(page: Page) {
  const panel = page.locator('.notification-panel');
  expect(await panel.evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBeTruthy();
  for (const button of await panel.locator('button:visible').all()) {
    const box = (await button.boundingBox())!;
    expect(box.height).toBeGreaterThanOrEqual(44);
    expect(box.x).toBeGreaterThanOrEqual(2);
    expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize()!.width - 2);
  }
  for (const link of await panel.locator('p a').all()) {
    expect(await link.evaluate(el => getComputedStyle(el).textDecorationLine)).toContain('underline');
  }
}
const surfaces = [['standalone', '/notifications'], ['pane', '/?pane=notices']] as const;
test.beforeEach(() => fixture());

test('both entry points render the same authorized rows, UTC time and reachable old unread history', async ({ page }, info) => {
  const { old_id } = fixture('inspect');
  await login(page);
  let first: string[] | undefined;
  for (const [name, url] of surfaces) {
    await page.goto(url);
    const rows = page.locator('[data-notification-list]');
    await expect(rows).toBeVisible();
    await expect(rows.getByRole('button', { name: /Unread\. Anonymous mentioned you in/ })).toBeVisible();
    await expect(rows.getByRole('button', { name: /Someone replied to/ })).toBeVisible();
    await expect(rows.getByRole('button', { name: /Your appeal has been resolved.*Acknowledge notification/ })).toBeVisible();
    await expect(rows.locator('em, script')).toHaveCount(0);
    for (const copy of ['replied to', 'started a thread', 'posted in', 'mentioned you in', 'reacted to your post', 'followed you', 'You earned a badge', 'Your answer was accepted', 'sent you a message', 'Announcement']) {
      await expect(rows).toContainText(copy);
    }
    const labels = await rows.locator('button').allTextContents();
    if (first) expect(labels).toEqual(first); else first = labels;
    const iso = await rows.locator('time').first().getAttribute('datetime');
    expect(iso).toMatch(/T\d{2}:\d{2}:\d{2}(Z|\+00:00)$/);
    expect(Number.isFinite(Date.parse(iso!))).toBeTruthy();
    await capture(page, info, `${name}-populated`);
    await page.getByRole('link', { name: 'Unread', exact: true }).click();
    await expect(rows.locator(`form[action="/notifications/${old_id}/read"]`)).toBeVisible();
    await capture(page, info, `${name}-unread-only`);
    await page.getByRole('link', { name: 'All', exact: true }).click();
    await page.getByRole('link', { name: 'Next', exact: true }).click();
    await expect(rows.locator(`form[action="/notifications/${old_id}/read"]`)).toBeVisible();
    await page.getByRole('link', { name: 'Latest', exact: true }).click();
    await expect(page.locator('.notification-panel')).toBeVisible();
  }
});

test('long content wraps across desktop, phone, stress and 200 percent layouts in both themes', async ({ page, browser }, info) => {
  test.setTimeout(90000);
  await login(page);
  for (const theme of ['light', 'dark']) {
    if (theme === 'dark') fixture('dark');
    for (const length of [40, 64]) {
      fixture(`name${length}`);
      for (const width of [1280, 1440, 390, 320]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const [name, url] of surfaces) {
          await page.goto(url);
          await geometry(page);
          const button = page.locator('.notification-open').first();
          await button.focus();
          await page.keyboard.press('Tab');
          await page.keyboard.press('Shift+Tab');
          await expect(button).toBeFocused();
          expect(await button.evaluate(el => getComputedStyle(el).outlineStyle)).not.toBe('none');
          const dot = (await button.locator('.notification-unread-dot').boundingBox())!;
          const message = (await button.locator('.notification-message').boundingBox())!;
          expect(Math.abs(dot.y - message.y)).toBeLessThan(16);
          await button.scrollIntoViewIfNeeded();
          const box = (await button.boundingBox())!;
          expect(box.y).toBeGreaterThanOrEqual(0);
          if (length === 64 && [1280, 390, 320].includes(width)) await capture(page, info, `${name}-${theme}-${width}`);
        }
      }
    }
    // 1280x1000 at 200% browser zoom has a 640x500 CSS viewport and 2x pixels.
    const zoom = await browser.newContext({ storageState: await page.context().storageState(), viewport: { width: 640, height: 500 }, deviceScaleFactor: 2 });
    const zoomPage = await zoom.newPage();
    for (const [name, url] of surfaces) {
      await zoomPage.goto(new URL(url, page.url()).href);
      await geometry(zoomPage);
      await capture(zoomPage, info, `${name}-${theme}-zoom200`);
      const a11y = await new AxeBuilder({ page: zoomPage }).include('.notification-panel').withRules(['link-in-text-block', 'button-name', 'link-name', 'color-contrast']).analyze();
      expect(a11y.violations).toEqual([]);
    }
    await zoom.close();
  }
});

test('inaccessible and empty states remain consistent at both entry points', async ({ page }, info) => {
  await login(page);
  fixture('private');
  for (const [name, url] of surfaces) {
    await page.goto(url);
    await expect(page.locator('[data-notification-list]')).not.toContainText('LongTitle');
    await expect(page.locator('[data-notification-list]')).not.toContainText('Anonymous evidence');
    await capture(page, info, `${name}-inaccessible`);
  }
  fixture('empty');
  for (const [name, url] of surfaces) {
    await page.goto(url);
    await expect(page.getByText('No notifications yet.', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Mark all read', exact: true })).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Clear all', exact: true })).toBeDisabled();
    await capture(page, info, `${name}-empty`);
  }
});

test.describe('progressive enhancement', () => {
  test.use({ javaScriptEnabled: false });
  for (const [name, url] of surfaces) {
    test(`${name} owned forms and bulk actions work without JavaScript`, async ({ page }, info) => {
      const { old_id } = fixture('inspect');
      await login(page);
      await page.goto(url);
      await page.getByRole('link', { name: 'Unread', exact: true }).click();
      await page.locator(`form[action="/notifications/${old_id}/read"] button`).click();
      await expect(page).toHaveURL(/\/u\/notifications-reader$/);
      await page.goto(url);
      await page.getByRole('button', { name: /Your appeal has been resolved/ }).click();
      await expect(page).toHaveURL(/\/appeals$/);
      await page.goto(url);
      await page.getByRole('button', { name: 'Mark all read', exact: true }).click();
      expect(new URL(page.url()).pathname).toBe(new URL(url, 'http://localhost').pathname);
      await expect(page.getByRole('button', { name: 'Mark all read', exact: true })).toBeDisabled();
      expect(fixture('inspect').unread).toBe(0);
      await page.getByRole('link', { name: 'Unread', exact: true }).click();
      await expect(page.getByText('All caught up. No unread notifications.')).toBeVisible();
      await page.getByRole('link', { name: 'All', exact: true }).click();
      await page.getByRole('button', { name: 'Clear all', exact: true }).click();
      await expect(page.getByText('No notifications yet.', { exact: true })).toBeVisible();
      await capture(page, info, `${name}-nojs-cleared`);
    });
  }
});


test('polling synchronizes every badge without replacing focused notification rows', async ({ page }) => {
  await login(page);
  await page.clock.install();
  let unread = 105;
  let requests = 0;
  await page.route('**/notifications/bell?format=json', route => {
    requests++;
    return route.fulfill({ json: { unread, items: [] } });
  });
  await page.goto('/?pane=notices');
  const counts = page.locator('[data-notification-count]');
  const links = page.locator('[data-notification-link]');
  await expect(counts).toHaveCount(4);
  await expect(counts).toHaveText(['99+', '99+', '99+', '99+']);
  const row = page.locator('.notification-open').first();
  await row.focus();
  await row.evaluate(el => el.setAttribute('data-preserved-row', 'yes'));
  for (const value of [7, 0]) {
    unread = value;
    const before = requests;
    await page.clock.fastForward(60001);
    await expect.poll(() => requests).toBe(before + 1);
    await expect(counts).toHaveText(Array(4).fill(String(value)));
    for (const link of await links.all()) await expect(link).toHaveAttribute('aria-label', value ? 'Notifications, 7 unread' : 'Notifications');
    for (const count of await counts.all()) await expect(count).toHaveAttribute('aria-hidden', 'true');
    await expect(page.locator('.notification-heading h1')).toHaveAccessibleName(value ? 'Notifications, 7 unread' : 'Notifications');
    await expect(row).toBeFocused();
    await expect(row).toHaveAttribute('data-preserved-row', 'yes');
  }
  expect(await counts.evaluateAll(nodes => nodes.every(node => (node as HTMLElement).hidden))).toBeTruthy();
  await expect(page.locator('[aria-live] [data-notification-count]')).toHaveCount(0);
});

test('guests and disabled notifications make no bell request and 404 stops polling', async ({ page }) => {
  await page.clock.install();
  let requests = 0;
  page.on('request', request => { if (request.url().includes('/notifications/bell')) requests++; });
  await page.goto('/');
  await expect(page.locator('[data-bell]')).toHaveCount(0);
  await page.clock.fastForward(120001);
  expect(requests).toBe(0);
  fixture('notifications-off');
  await login(page);
  await expect(page.locator('[data-notification-link]')).toHaveCount(0);
  await page.clock.fastForward(120001);
  expect(requests).toBe(0);
  fixture();
  await page.route('**/notifications/bell?format=json', route => route.fulfill({ status: 404 }));
  await page.goto('/');
  await expect.poll(() => requests).toBe(1);
  await page.clock.fastForward(180001);
  expect(requests).toBe(1);
});

test('replayed Notifications tour step highlights the visible primary bell with account menu closed', async ({ page }, info) => {
  await login(page);
  await page.goto('/settings/account');
  await page.locator('[data-tour-replay]:visible').click();
  const tour = page.locator('.tour-popover');
  await expect(tour.getByRole('heading', { name: 'Welcome', exact: true })).toBeVisible();
  await tour.getByRole('button', { name: 'Next', exact: true }).click();
  await tour.getByRole('button', { name: 'Next', exact: true }).click();
  await expect(tour.getByRole('heading', { name: 'Notifications', exact: true })).toBeVisible();
  await expect(page.locator('[data-bell].tour-highlight')).toBeVisible();
  await expect(page.locator('.identity-menu')).not.toHaveAttribute('open', '');
  await capture(page, info, 'bell-tour');
  await tour.getByRole('button', { name: 'Skip', exact: true }).click();
});

test.describe('persistent bell without JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test('member and admin bell renders zero and 105 counts with a long account name', async ({ page }, info) => {
    await login(page);
    fixture('long-account');
    for (const persona of ['member', 'admin']) {
      if (persona === 'admin') fixture('admin');
      for (const state of ['empty', 'high-count']) {
        fixture(state);
        await page.goto(persona === 'admin' ? '/admin' : '/');
        const bell = page.locator('[data-bell]');
        await expect(bell).toBeVisible();
        await expect(bell).toHaveAccessibleName(state === 'empty' ? 'Notifications' : 'Notifications, 105 unread');
        await expect(bell.locator('[data-notification-count]')).toHaveText(state === 'empty' ? '0' : '99+');
        if (state === 'empty') await expect(bell.locator('[data-notification-count]')).toBeHidden();
        else {
          const badge = (await bell.locator('[data-notification-count]').boundingBox())!;
          const box = (await bell.boundingBox())!;
          expect(badge.x).toBeGreaterThanOrEqual(box.x);
          expect(badge.x + badge.width).toBeLessThanOrEqual(box.x + box.width + 1);
        }
        if (persona === 'admin') {
          const logout = page.locator('.admin-bar-right button[aria-label="Log out"]');
          const box = (await logout.boundingBox())!;
          expect(box.x + box.width).toBeLessThanOrEqual(page.viewportSize()!.width - 2);
          expect((await page.locator('.admin-bar-brand').boundingBox())!.width).toBeGreaterThan(24);
        }
        await capture(page, info, `bell-nojs-${persona}-${state}`);
        if (persona === 'member' && state === 'high-count') {
          await page.locator('.identity-menu > summary').click();
          const shortcut = page.locator('.identity-menu [data-notification-link]');
          await expect(shortcut).toHaveAccessibleName('Notifications, 105 unread');
          const badge = (await shortcut.locator('[data-notification-count]').boundingBox())!;
          const row = (await shortcut.boundingBox())!;
          expect(badge.x).toBeGreaterThanOrEqual(row.x);
          expect(badge.x + badge.width).toBeLessThanOrEqual(row.x + row.width);
          await capture(page, info, 'bell-nojs-account-menu');
          await page.locator('.identity-menu > summary').click();
        }
        await bell.click();
        await expect(page).toHaveURL(/\/notifications$/);
      }
    }
  });
});


test('polling pauses while hidden and backs off after throttling', async ({ page }) => {
  await login(page);
  await page.clock.install();
  let requests = 0;
  await page.route('**/notifications/bell?format=json', route => {
    requests++;
    return requests <= 2 ? route.fulfill({ status: 429 }) : route.fulfill({ json: { unread: 4, items: [] } });
  });
  await page.goto('/');
  await expect.poll(() => requests).toBe(1);
  await page.clock.fastForward(60001);
  await expect.poll(() => requests).toBe(2);
  await page.clock.fastForward(60001);
  expect(requests).toBe(2);
  await page.clock.fastForward(60001);
  await expect.poll(() => requests).toBe(3);
  await expect(page.locator('[data-bell]')).toHaveAccessibleName('Notifications, 4 unread');
  await page.evaluate(() => {
    Object.defineProperty(document, 'hidden', { configurable: true, value: true });
    document.dispatchEvent(new Event('visibilitychange'));
  });
  await page.clock.fastForward(180001);
  expect(requests).toBe(3);
  await page.evaluate(() => {
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    document.dispatchEvent(new Event('visibilitychange'));
  });
  await expect.poll(() => requests).toBe(4);
});
