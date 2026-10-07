import { test, expect, type Page, type BrowserContext } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// These cases exercise the width left by desktop scrollbars and the member's
// Large text preference, which the medium-font phone specimens do not cover.
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser/header-regressions');
let cookies: Awaited<ReturnType<BrowserContext['cookies']>> | undefined;

async function signIn(page: Page) {
  if (cookies) {
    await page.context().addCookies(cookies);
    return;
  }
  await page.goto('/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));
  await page.goto('/');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.count()) {
    await skip.click();
    await expect(skip).toBeHidden();
  }
  cookies = await page.context().cookies();
}

async function showWidestCounts(page: Page, fontSize: 'medium' | 'large') {
  await expect(page.locator('.forum-bar [data-bell] [data-notification-count]')).toHaveText('99+');
  await page.evaluate(async (size) => {
    document.documentElement.dataset.fontSize = size;
    // Keep the layout fixture independent of how many unread topics the shared
    // seed has. Polling supplies the real bell/DM render path; Inbox is server-counted.
    let badge = document.querySelector('[data-inbox-unread-count]');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'forum-bar-count';
      document.querySelector('[data-primary-route="inbox"]')!.appendChild(badge);
    }
    badge.setAttribute('data-inbox-unread-count', '105');
    badge.setAttribute('aria-hidden', 'true');
    badge.textContent = '99+';
    await document.fonts.ready;
  }, fontSize);
}

async function headerGeometry(page: Page) {
  return page.locator('.forum-bar').evaluate((header) => {
    const rect = (element: Element) => {
      const r = element.getBoundingClientRect();
      return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width, height: r.height };
    };
    const controls = Array.from(header.querySelectorAll(
      '[data-nav-toggle], .forum-bar-brand, .forum-bar-search, .forum-bar-compose .btn, [data-bell], .forum-bar-user',
    )).filter((element) => element.getClientRects().length > 0).map((element) => ({
      name: element.getAttribute('aria-label')!, ...rect(element),
    }));
    const routes = Array.from(header.querySelectorAll('.forum-bar-surface')).map((element) => ({
      ...rect(element), fontSize: parseFloat(getComputedStyle(element).fontSize),
    }));
    return { header: rect(header), controls, routes, overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
  });
}

function contained(m: Awaited<ReturnType<typeof headerGeometry>>, where: string) {
  expect(m.header.height, `${where}: two header rows`).toBe(108);
  expect(m.controls).toHaveLength(6);
  expect(new Set(m.controls.map((control) => Math.round(control.top))).size, `${where}: one controls row`).toBe(1);
  for (const control of m.controls) {
    expect(control.top, `${where}: ${control.name} is below the viewport top`).toBeGreaterThanOrEqual(m.header.top);
    expect(control.bottom, `${where}: ${control.name} stays inside the header`).toBeLessThanOrEqual(m.header.bottom);
    expect(control.left, `${where}: ${control.name} left edge`).toBeGreaterThanOrEqual(m.header.left);
    expect(control.right, `${where}: ${control.name} right edge`).toBeLessThanOrEqual(m.header.right + 0.5);
    expect(control.height, `${where}: ${control.name} target height`).toBeGreaterThanOrEqual(44);
    expect(control.width, `${where}: ${control.name} target width`).toBeGreaterThanOrEqual(control.name === 'RetroBoards' ? 34 : 44);
  }
  expect(m.routes).toHaveLength(3);
  for (const route of m.routes) {
    expect(route.top, `${where}: routes follow the controls`).toBeGreaterThanOrEqual(m.controls[0].bottom);
    expect(route.bottom, `${where}: route stays inside the header`).toBeLessThanOrEqual(m.header.bottom);
    expect(route.left, `${where}: route left edge`).toBeGreaterThanOrEqual(m.header.left);
    expect(route.right, `${where}: route right edge`).toBeLessThanOrEqual(m.header.right + 0.5);
    expect(route.height, `${where}: route target height`).toBeGreaterThanOrEqual(44);
    expect(route.fontSize, `${where}: route label stays readable`).toBeGreaterThanOrEqual(13);
  }
  expect(m.overflow, `${where}: no horizontal page scroll`).toBeLessThanOrEqual(0);
}

test('classic desktop scrollbars do not create a third header row at narrow widths', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop reflow, including the browser scrollbar gutter');
  await signIn(page);
  await page.route('**/notifications/bell?format=json', (route) => route.fulfill({ json: { unread: 105, dm_unread: 105, items: [] } }));
  for (const width of [320, 330, 340, 350, 360, 390]) {
    await page.setViewportSize({ width, height: 844 });
    await page.goto('/inbox');
    await showWidestCounts(page, 'medium');
    const m = await headerGeometry(page);
    if (process.env.E2E_LAYOUT_BROWSER !== 'webkit') {
      expect(m.header.width, 'Chromium exercises a real classic scrollbar gutter').toBeLessThan(width);
    }
    contained(m, `desktop at ${width}px`);
  }
  await page.setViewportSize({ width: 320, height: 844 });
  await page.goto('/inbox');
  await showWidestCounts(page, 'medium');
  const directory = path.join(OUT, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, 'classic-scrollbar-320.png'), clip: { x: 0, y: 0, width: 320, height: 160 } });
});

test('Large text and capped unread counts keep every phone route inside the viewport', async ({ page }, info) => {
  await signIn(page);
  await page.route('**/notifications/bell?format=json', (route) => route.fulfill({ json: { unread: 105, dm_unread: 105, items: [] } }));
  for (const width of [320, 360, 390]) {
    await page.setViewportSize({ width, height: 844 });
    await page.goto('/inbox');
    await showWidestCounts(page, 'large');
    contained(await headerGeometry(page), `${info.project.name}, Large text at ${width}px`);
  }
  const directory = path.join(OUT, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  for (const theme of ['light', 'dark']) {
    await page.setViewportSize({ width: 320, height: 844 });
    await page.goto('/inbox');
    await showWidestCounts(page, 'large');
    await page.evaluate((value) => { document.documentElement.dataset.theme = value; }, theme);
    await page.screenshot({ path: path.join(directory, `large-text-320-${theme}.png`), clip: { x: 0, y: 0, width: 320, height: 160 }, animations: 'disabled' });
  }
});
