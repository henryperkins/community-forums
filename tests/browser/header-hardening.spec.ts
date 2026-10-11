import { test, expect, Page, type BrowserContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Member header hardening (2026-10-06 critique; ADR 0032 follow-up).
 *
 * These tests measure the three defects the critique found in the browser:
 *
 * 1. Page controls at z-index 30 (the phone directory bar, the post toolbar)
 *    painted over the sticky bar, which the layer stacks at 20, and over its
 *    account menu, so a tap on Log out reached the page instead.
 * 2. The operator's name and logo had no width budget: a long community name
 *    pushed the account menu off-screen at narrow desktop widths, and a wide
 *    logo started a third phone row and lifted the first above the viewport.
 * 3. New topic ignored the board being read, the reading-pane toggle claimed a
 *    pane that does not exist below 1280px, and the pane toggles announced
 *    their state three times over.
 */

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/header-hardening-2026-10-06');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
const LONG_NAME = 'The Rivendell Historical Reenactment & Lore Society of the Western Marches Halls';
// A 6:1 operator logo, drawn at the bar's 28px height as 168px wide.
const WIDE_LOGO = 'data:image/svg+xml;base64,' + Buffer.from(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 100" width="600" height="100">'
  + '<rect width="600" height="100" rx="14" fill="#2e4a3a"/>'
  + '<text x="300" y="66" font-family="serif" font-size="54" fill="#faf6ec" text-anchor="middle">Western Marches</text>'
  + '</svg>',
).toString('base64');

function runPhp(code: string): string {
  return execFileSync('php', ['-r', `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
${code}
`], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
}

/** Set the community name and logo; returns a function that restores the seed's. */
function brand(name: string, logo: string): () => void {
  const encode = (value: string) => Buffer.from(value).toString('base64');
  const previous = runPhp(`
$settings = new \\App\\Repository\\SettingRepository($db);
echo json_encode(['site_name' => $settings->getString('site_name'), 'brand_logo_path' => $settings->getString('brand_logo_path')], JSON_THROW_ON_ERROR);
$settings->set('site_name', base64_decode('${encode(name)}'));
$settings->set('brand_logo_path', base64_decode('${encode(logo)}'));
`);
  return () => {
    runPhp(`
$previous = json_decode(base64_decode('${encode(previous)}'), true, 512, JSON_THROW_ON_ERROR);
$settings = new \\App\\Repository\\SettingRepository($db);
$settings->set('site_name', $previous['site_name']);
$settings->set('brand_logo_path', $previous['brand_logo_path']);
`);
  };
}

function shot(name: string, project: string) {
  const dir = path.join(OUT, project);
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
}

// One sign-in per account per run: login is rate-limited per account, and the
// desktop and mobile projects share this worker.
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['cookies']>>>();

async function signInAs(page: Page, email: string) {
  const cached = sessions.get(email);
  if (cached) {
    await page.context().addCookies(cached);
    return;
  }
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.count()) {
    await skip.first().click();
    await expect(skip.first()).toBeHidden();
  }
  sessions.set(email, await page.context().cookies());
}

/** Every point across the element's middle line lands on the element itself. */
async function ownsItsTaps(page: Page, selector: string, index: number): Promise<boolean> {
  return page.locator(selector).nth(index).evaluate((el) => {
    const r = el.getBoundingClientRect();
    const y = r.top + r.height / 2;
    return [r.left + 8, r.left + r.width / 2, r.right - 8].every((x) => {
      const hit = document.elementFromPoint(x, y);
      return hit !== null && (hit === el || el.contains(hit));
    });
  });
}

test('page controls stay under the bar and its account menu', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'the directory bar and the post toolbar meet the bar on a phone');
  // A moderator's menu is the longest a member gets: Moderation, then Log out.
  await signInAs(page, 'alice@retro.test');
  const items = '.identity-menu-panel a, .identity-menu-panel button';

  for (const route of ['/', '/t/1-welcome-to-retroboards']) {
    await page.goto(route);
    await page.locator('.identity-menu > summary').click();
    const count = await page.locator(items).count();
    expect(count, `${route}: the moderator's menu`).toBeGreaterThanOrEqual(8);
    for (let i = 0; i < count; i++) {
      const label = (await page.locator(items).nth(i).innerText()).trim();
      expect(await ownsItsTaps(page, items, i), `${route}: "${label}" takes its own taps`).toBe(true);
    }
    await page.screenshot({ path: shot(`menu-open${route === '/' ? '-home' : '-topic'}.png`, info.project.name), animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(page.locator('.identity-menu')).not.toHaveAttribute('open', '');
  }

  // Scrolled, the directory bar passes under the bar rather than over it.
  await page.goto('/');
  const viewbar = page.locator('.directory-viewbar-mobile');
  await expect(viewbar).toBeVisible();
  const headerHeight = await page.locator('header.forum-bar').evaluate((el) => el.getBoundingClientRect().height);
  const viewbarTop = await viewbar.evaluate((el) => el.getBoundingClientRect().top + window.scrollY);
  await page.evaluate((y) => window.scrollTo(0, y), viewbarTop - headerHeight + 22);
  await expect.poll(() => viewbar.evaluate((el) => el.getBoundingClientRect().top)).toBeLessThan(headerHeight);
  const hits = await page.evaluate(() => {
    const header = document.querySelector('header.forum-bar')!;
    const bottom = header.getBoundingClientRect().bottom;
    return [24, window.innerWidth / 2, window.innerWidth - 24].map((x) => {
      const hit = document.elementFromPoint(x, bottom - 6);
      return hit !== null && header.contains(hit);
    });
  });
  expect(hits, 'the bar keeps its lower edge while the directory bar scrolls beneath it').toEqual([true, true, true]);
  await page.screenshot({ path: shot('directory-bar-under-header.png', info.project.name), animations: 'disabled' });
});

test('a long community name shortens instead of pushing the account menu off-screen', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'narrow desktop widths are where the bar ran out of room');
  expect(LONG_NAME.length, 'the longest name setup and branding accept').toBe(80);
  const restore = brand(LONG_NAME, '');
  try {
    await signInAs(page, 'elrond@retro.test');
    for (const route of ['/', '/inbox']) {
      for (const width of [901, 1024, 1180, 1280, 1440]) {
        await page.setViewportSize({ width, height: 800 });
        await page.goto(route);
        const measured = await page.evaluate(() => {
          const root = document.documentElement;
          const seat = document.querySelector('.forum-bar-user')!.getBoundingClientRect();
          const wordmark = document.querySelector<HTMLElement>('.forum-bar-wordmark')!;
          return {
            overflow: root.scrollWidth - root.clientWidth,
            seatRight: seat.right,
            viewport: root.clientWidth,
            shortened: wordmark.scrollWidth > wordmark.clientWidth,
            ellipsis: getComputedStyle(wordmark).textOverflow,
            name: document.querySelector('.forum-bar-brand')!.getAttribute('aria-label'),
          };
        });
        const where = `${route} at ${width}px`;
        expect(measured.overflow, `${where}: no sideways scroll`).toBeLessThanOrEqual(0);
        expect(measured.seatRight, `${where}: the account seat stays on screen`).toBeLessThanOrEqual(measured.viewport);
        expect(measured.shortened, `${where}: the wordmark yields`).toBe(true);
        expect(measured.ellipsis).toBe('ellipsis');
        expect(measured.name, `${where}: the full name stays the lockup's name`).toBe(LONG_NAME);
        if (route === '/inbox' && (width === 901 || width === 1280)) {
          await page.screenshot({ path: shot(`long-name-inbox-${width}.png`, info.project.name), clip: { x: 0, y: 0, width, height: 120 } });
        }
      }
    }
    // The account menu opens fully on screen at the narrowest desktop width.
    await page.setViewportSize({ width: 901, height: 800 });
    await page.goto('/inbox');
    await page.locator('.identity-menu > summary').click();
    const panel = await page.locator('.identity-menu-panel').boundingBox();
    expect(panel!.x).toBeGreaterThanOrEqual(0);
    expect(panel!.x + panel!.width).toBeLessThanOrEqual(901);
    await expect(page.locator('.identity-menu-panel a[href="/settings/account"]')).toBeInViewport();
  } finally {
    restore();
  }
});

test('a wide operator logo scales down on the first row', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'the two-row phone bar is where a wide logo broke');
  const restore = brand('RetroBoards', WIDE_LOGO);
  try {
    await signInAs(page, 'elrond@retro.test');
    for (const width of [390, 320]) {
      await page.setViewportSize({ width, height: 844 });
      await page.goto('/');
      const m = await page.evaluate(() => {
        const box = (selector: string) => {
          const el = Array.from(document.querySelectorAll(selector)).find((n) => n.getClientRects().length > 0);
          const r = el!.getBoundingClientRect();
          return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, width: r.width };
        };
        return {
          header: box('header.forum-bar'),
          opener: box('.nav-toggle'),
          brand: box('.forum-bar-brand'),
          logo: box('.brand-logo'),
          seat: box('.forum-bar-user'),
          routes: box('.forum-bar-surfaces'),
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
      });
      const where = `${width}px`;
      expect(m.header.top).toBe(0);
      expect(m.header.bottom - m.header.top, `${where}: two rows, not three`).toBe(108);
      expect(m.opener.top, `${where}: the drawer opener is whole`).toBeGreaterThanOrEqual(0);
      expect(m.brand.bottom, `${where}: the logo stays on the first row`).toBeLessThanOrEqual(m.routes.top + 1);
      expect(m.seat.bottom, `${where}: the account seat stays on the first row`).toBeLessThanOrEqual(m.routes.top + 1);
      expect(m.routes.bottom, `${where}: the routes stay inside the bar`).toBeLessThanOrEqual(m.header.bottom + 0.5);
      expect(m.logo.width, `${where}: the logo fits its lockup`).toBeLessThanOrEqual(m.brand.width + 0.5);
      expect(m.logo.width, `${where}: the logo is scaled, not hidden`).toBeGreaterThan(40);
      expect(m.overflow, `${where}: no sideways scroll`).toBeLessThanOrEqual(0);
      await page.screenshot({ path: shot(`wide-logo-${width}.png`, info.project.name), clip: { x: 0, y: 0, width, height: 140 } });
    }
  } finally {
    restore();
  }
});

test('New topic opens the composer on the board being read', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'one viewport proves the link; the phone glyph shares it');
  await signInAs(page, 'elrond@retro.test');
  const newTopic = page.locator('[data-subheader] a[href^="/compose"]');

  await page.goto('/');
  await expect(newTopic).toHaveAttribute('href', '/compose');

  await page.goto('/c/general');
  await expect(newTopic).toHaveAttribute('href', '/compose?board=general');
  const topic = await page.locator('main a[href^="/t/"]').first().getAttribute('href');
  await page.locator('[data-create-trigger]').click();
  await newTopic.click();
  await expect(page).toHaveURL(/\/compose\?board=general$/);
  await expect(page.locator('select[name="board_id"] option:checked')).toHaveText('General');
  await page.screenshot({ path: shot('new-topic-from-general.png', info.project.name), clip: { x: 0, y: 0, width: 1280, height: 420 } });

  await page.goto(topic!);
  await expect(newTopic).toHaveAttribute('href', '/compose?board=general');
});

test('the pane toggles name the pane and appear only where it can show', async ({ page }, info) => {
  await signInAs(page, 'elrond@retro.test');
  const posts: string[] = [];
  page.on('request', (request) => {
    if (request.method() === 'POST' && request.url().endsWith('/settings/member-surfaces')) { posts.push(request.url()); }
  });
  const bodyState = () => page.locator('body').evaluate((el) => [el.className, el.dataset.railOpen, el.dataset.inboxReadingOpen].join('|'));

  if (info.project.name === 'mobile') {
    // Behind the phone drawer the persisted rail state has nothing to show, so ⌘B stands down.
    await page.goto('/inbox');
    await expect(page.locator('[data-panel-form="rail"]')).toBeHidden();
    const before = await bodyState();
    await page.locator('h1').first().click();
    await page.keyboard.press('Control+b');
    await page.waitForTimeout(400);
    expect(posts).toEqual([]);
    expect(await bodyState()).toBe(before);
    return;
  }

  // Below the inbox's 1280px column the reading pane is not a column, so its
  // toggle is not drawn and ⌘J does nothing.
  for (const width of [1024, 1279]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto('/inbox');
    await expect(page.locator('[data-panel-form="reading"]')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Board rail' })).toBeVisible();
    const before = await bodyState();
    await page.locator('h1').first().click();
    await page.keyboard.press('Control+j');
    await page.waitForTimeout(400);
    expect(posts, `${width}px: ⌘J saves nothing`).toEqual([]);
    expect(await bodyState()).toBe(before);
  }

  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('/inbox');
  const rail = page.getByRole('button', { name: 'Board rail' });
  const reading = page.getByRole('button', { name: 'Reading pane' });
  await expect(rail).toBeVisible();
  await expect(reading).toBeVisible();
  for (const toggle of [rail, reading]) {
    expect(await toggle.getAttribute('aria-expanded')).toBeNull();
  }
  const pressed = await reading.getAttribute('aria-pressed');
  const flipped = pressed === 'true' ? 'false' : 'true';
  const saved = page.waitForResponse((r) => r.url().endsWith('/settings/member-surfaces') && r.request().method() === 'POST');
  await reading.click();
  expect((await saved).status()).toBe(303);
  await expect(reading).toHaveAttribute('aria-pressed', flipped);
  await expect(page.getByRole('button', { name: 'Reading pane' })).toHaveCount(1);
  await page.screenshot({ path: shot('pane-toggles-1280.png', info.project.name), clip: { x: 0, y: 0, width: 1280, height: 120 } });
  const restored = page.waitForResponse((r) => r.url().endsWith('/settings/member-surfaces') && r.request().method() === 'POST');
  await reading.click();
  expect((await restored).status()).toBe(303);
  await expect(reading).toHaveAttribute('aria-pressed', pressed!);
});
