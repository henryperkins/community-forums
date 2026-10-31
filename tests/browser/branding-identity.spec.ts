import { test, expect, type Browser, type BrowserContextOptions, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

/**
 * ADR 0054 identity increment: the community logo on every sign-in screen, a
 * device-chosen logo in System mode (with and without JavaScript), editable
 * community text with defaults, and member appearance thumbnails painted from
 * the resolved theme. Run once per engine: E2E_LAYOUT_BROWSER=webkit for WebKit.
 */
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/branding-identity-2026-10-31');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${process.env.E2E_PORT ?? 8011}`;
test.use({ browserName, colorScheme: 'light' });

type State = Record<string, string>;
function fixture(...args: string[]): State {
  return JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'branding-identity-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
const set = (values: Record<string, string>) => fixture('set', JSON.stringify(values));

async function login(page: Page, email = 'admin@retro.test') {
  await page.goto('/login');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}
async function shot(page: Page, info: TestInfo, name: string) {
  const directory = path.join(evidence, browserName, info.project.name);
  mkdirSync(directory, { recursive: true });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: true, animations: 'disabled' });
}
async function noOverflow(page: Page) {
  const sizes = await page.evaluate(() => ({ actual: document.documentElement.scrollWidth, viewport: document.documentElement.clientWidth }));
  expect(sizes.actual).toBeLessThanOrEqual(sizes.viewport);
}
/** A context with this project's viewport and touch profile plus overrides. */
async function context(browser: Browser, info: TestInfo, options: BrowserContextOptions = {}) {
  const use = info.project.use;
  return browser.newContext({
    baseURL, viewport: use.viewport, deviceScaleFactor: use.deviceScaleFactor,
    isMobile: use.isMobile, hasTouch: use.hasTouch, colorScheme: 'light', ...options,
  });
}
async function currentLogo(page: Page, selector: string) {
  // Rendered images only: a hidden half of a System split has no boxes.
  return page.locator(selector).evaluateAll(images => images
    .filter(image => image.getClientRects().length > 0)
    .map(image => new URL((image as HTMLImageElement).currentSrc || (image as HTMLImageElement).src).pathname));
}
async function visible(page: Page, selector: string) {
  return page.locator(selector).evaluateAll(nodes => nodes.filter(node => node.getClientRects().length > 0).length);
}
async function saveBranding(page: Page) {
  const posted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin/branding');
  // Every save replaces the document (the 303's form, or the 422 re-render).
  // Wait for that commit, not just the POST: WebKit reports the response
  // while the old document is still the one scripts would run in.
  const committed = page.waitForEvent('framenavigated', { predicate: frame => frame === page.mainFrame() });
  await page.getByRole('button', { name: 'Publish branding', exact: true }).click();
  const response = await posted;
  await committed;
  await page.waitForLoadState('load');
  return response.status();
}
async function wordmark(page: Page, label: string, ink: string, ground: string) {
  return Buffer.from((await page.evaluate(([text, fill, background]) => {
    const canvas = document.createElement('canvas');
    canvas.width = 220;
    canvas.height = 56;
    const context = canvas.getContext('2d')!;
    context.fillStyle = background;
    context.fillRect(0, 0, 220, 56);
    context.strokeStyle = fill;
    context.lineWidth = 3;
    context.strokeRect(3, 3, 214, 50);
    context.fillStyle = fill;
    context.font = 'bold 26px serif';
    context.fillText(text, 16, 37);
    return canvas.toDataURL('image/png');
  }, [label, ink, ground])).split(',')[1], 'base64');
}

test.beforeEach(() => { fixture('reset'); });
test.afterAll(() => { fixture('reset'); });

test('every sign-in screen shows the uploaded identity and follows the device in System mode', async ({ page, browser }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="site_name"]').fill('Harbour Commons');
  await page.locator('[name="site_tagline"]').fill('Friends of the old harbour since 1998');
  await page.locator('[name="logo"]').setInputFiles({ name: 'base.png', mimeType: 'image/png', buffer: await wordmark(page, 'BASE', '#2e4a3a', '#faf6ec') });
  await page.locator('[name="logo_light"]').setInputFiles({ name: 'light.png', mimeType: 'image/png', buffer: await wordmark(page, 'LIGHT', '#1b231d', '#edf3ed') });
  await page.locator('[name="logo_dark"]').setInputFiles({ name: 'dark.png', mimeType: 'image/png', buffer: await wordmark(page, 'DARK', '#ece4d2', '#283440') });
  expect(await saveBranding(page)).toBe(303);
  const saved = fixture();
  for (const key of ['brand_logo_path', 'brand_logo_light_path', 'brand_logo_dark_path']) expect(saved[key]).toMatch(/^\/media\/\d+$/);
  // The console bar uses the same resolution for the admin's own System mode.
  await expect.poll(() => currentLogo(page, '.admin-bar-logo')).toEqual([saved.brand_logo_light_path]);

  for (const scheme of ['light', 'dark'] as const) {
    const guest = await context(browser, info, { colorScheme: scheme });
    try {
      const gp = await guest.newPage();
      const expected = scheme === 'dark' ? saved.brand_logo_dark_path : saved.brand_logo_light_path;
      for (const route of ['/login', '/register', '/forgot', '/reset?token=expired', '/verify']) {
        await gp.goto(route);
        await expect.poll(() => currentLogo(gp, '.auth-brand-logo')).toEqual([expected]);
        await expect(gp.getByRole('link', { name: 'Harbour Commons' }).first()).toBeVisible();
        await expect(gp.locator('.auth-tagline')).toHaveText('Friends of the old harbour since 1998');
        expect(await visible(gp, '.auth-brand-star')).toBe(0);
        await noOverflow(gp);
      }
      await gp.goto('/login');
      await shot(gp, info, `login-system-${scheme}`);
      await gp.goto('/register');
      await shot(gp, info, `register-system-${scheme}`);
      await gp.goto('/');
      await expect.poll(() => currentLogo(gp, '.brand-logo')).toEqual([expected]);
      await shot(gp, info, `home-system-${scheme}`);
    } finally { await guest.close(); }
  }

  // A device change follows immediately, without a reload.
  const live = await context(browser, info, { colorScheme: 'light' });
  try {
    const lp = await live.newPage();
    await lp.goto('/login');
    await expect.poll(() => currentLogo(lp, '.auth-brand-logo')).toEqual([saved.brand_logo_light_path]);
    await lp.emulateMedia({ colorScheme: 'dark' });
    await expect.poll(() => currentLogo(lp, '.auth-brand-logo')).toEqual([saved.brand_logo_dark_path]);
    await lp.emulateMedia({ colorScheme: 'light' });
    await expect.poll(() => currentLogo(lp, '.auth-brand-logo')).toEqual([saved.brand_logo_light_path]);
  } finally { await live.close(); }
});

test('without JavaScript the first paint already uses the device or member mode logo', async ({ browser }, info) => {
  const logos = fixture('logos', 'base,light,dark');
  for (const scheme of ['light', 'dark'] as const) {
    const guest = await context(browser, info, { colorScheme: scheme, javaScriptEnabled: false });
    try {
      const gp = await guest.newPage();
      const expected = scheme === 'dark' ? logos.brand_logo_dark_path : logos.brand_logo_light_path;
      await gp.goto('/login');
      expect(await currentLogo(gp, '.auth-brand-logo')).toEqual([expected]);
      await shot(gp, info, `login-no-js-${scheme}`);
      await gp.goto('/');
      expect(await currentLogo(gp, '.brand-logo')).toEqual([expected]);
      await shot(gp, info, `home-no-js-${scheme}`);
    } finally { await guest.close(); }
  }

  // A member's explicit mode wins over the device.
  fixture('member', 'alice', JSON.stringify({ theme: 'dark' }));
  const member = await context(browser, info, { colorScheme: 'light', javaScriptEnabled: false });
  try {
    const mp = await member.newPage();
    await login(mp, 'alice@retro.test');
    await mp.goto('/');
    expect(await currentLogo(mp, '.brand-logo')).toEqual([logos.brand_logo_dark_path]);
    expect(await mp.locator('picture').count()).toBe(0);
    await shot(mp, info, 'member-dark-on-light-device-no-js');
  } finally { await member.close(); }
});

test('missing, single-mode and removed logos fall back to the house mark', async ({ page, browser }, info) => {
  const darkOnly = fixture('logos', 'dark');
  for (const scheme of ['light', 'dark'] as const) {
    const guest = await context(browser, info, { colorScheme: scheme, javaScriptEnabled: false });
    try {
      const gp = await guest.newPage();
      await gp.goto('/login');
      if (scheme === 'dark') {
        expect(await currentLogo(gp, '.auth-brand-logo')).toEqual([darkOnly.brand_logo_dark_path]);
        expect(await visible(gp, '.auth-brand-star')).toBe(0);
      } else {
        expect(await currentLogo(gp, '.auth-brand-logo')).toEqual([]);
        expect(await visible(gp, '.auth-brand-star')).toBe(1);
        await expect(gp.locator('.brand-when-light .auth-brand-name')).toHaveText('RetroBoards');
      }
      await shot(gp, info, `login-dark-logo-only-${scheme}-device`);
      await gp.goto('/');
      expect(await visible(gp, scheme === 'dark' ? '.brand-logo' : '.forum-bar-mark')).toBe(1);
    } finally { await guest.close(); }
  }

  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="remove_logo_dark"]').check();
  expect(await saveBranding(page)).toBe(303);
  expect(fixture().brand_logo_dark_path).toBe('');
  const guest = await context(browser, info, { colorScheme: 'dark' });
  try {
    const gp = await guest.newPage();
    await gp.goto('/login');
    expect(await visible(gp, '.auth-brand-star')).toBe(1);
    expect(await gp.locator('.auth-brand-logo').count()).toBe(0);
    await shot(gp, info, 'login-removed-logo-dark');
  } finally { await guest.close(); }
});

test('long names, taglines and wide logos stay readable on every width', async ({ browser }, info) => {
  set({
    site_name: 'The Northern Lighthouse Keepers and Coastal Weather Watchers Community Forum',
    // 76 characters: near the 80-character tagline limit.
    brand_tagline: 'Harbour lights, tide tables and storm warnings for every keeper on the coast',
  });
  const guest = await context(browser, info, { colorScheme: 'light' });
  try {
    const gp = await guest.newPage();
    await gp.goto('/login');
    await noOverflow(gp);
    await shot(gp, info, 'login-long-name');
    await gp.goto('/');
    await noOverflow(gp);
    await expect(gp.locator('.forum-directory__tagline')).toBeVisible();
    await shot(gp, info, 'home-long-name');

    fixture('logos', 'wide');
    await gp.goto('/login');
    await noOverflow(gp);
    const plate = await gp.locator('.auth-brand-logo').boundingBox();
    const width = gp.viewportSize()!.width;
    expect(plate).not.toBeNull();
    expect(plate!.x).toBeGreaterThanOrEqual(0);
    expect(plate!.x + plate!.width).toBeLessThanOrEqual(width);
    await shot(gp, info, 'login-wide-logo');
  } finally { await guest.close(); }
});

test('directory welcome copy and description publish, and clearing restores the defaults', async ({ page, browser }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="site_tagline"]').fill('A gathering place for small-boat sailors');
  await page.locator('[name="site_description"]').fill('Trip reports, rigging advice and weather talk from small-boat sailors.');
  await page.locator('[name="directory_heading"]').fill('Welcome aboard');
  await page.locator('[name="directory_intro"]').fill('Pick a board to see its topics.\nNew here? Say hello in General.');
  expect(await saveBranding(page)).toBe(303);
  await shot(page, info, 'admin-community-text');
  if (info.project.name === 'desktop') {
    // The longer form leaves the sticky preview column bounded to the
    // viewport; its reset controls scroll into view inside it.
    const reset = page.locator('[name="reset_confirm"]');
    await reset.scrollIntoViewIfNeeded();
    await reset.focus();
    const box = await reset.boundingBox();
    expect(box!.y).toBeGreaterThanOrEqual(0);
    expect(box!.y + box!.height).toBeLessThanOrEqual(page.viewportSize()!.height);
    await page.screenshot({ path: path.join(evidence, browserName, info.project.name, 'admin-reset-reachable.png'), animations: 'disabled' });
  }

  const guest = await context(browser, info, { colorScheme: 'light' });
  try {
    const gp = await guest.newPage();
    await gp.goto('/');
    await expect(gp.getByRole('heading', { level: 1, name: 'Welcome aboard' })).toBeVisible();
    await expect(gp.locator('.forum-directory__tagline')).toHaveText('A gathering place for small-boat sailors');
    await expect(gp.locator('.forum-directory__hero > p:not(.forum-directory__tagline, .forum-directory__stats)')).toHaveText([
      'Pick a board to see its topics.',
      'New here? Say hello in General. Your own cross-board queue is the inbox.',
    ]);
    await expect(gp.locator('meta[name="description"]')).toHaveAttribute('content', 'Trip reports, rigging advice and weather talk from small-boat sailors.');
    await noOverflow(gp);
    await shot(gp, info, 'directory-welcome');
    await gp.emulateMedia({ colorScheme: 'dark' });
    await shot(gp, info, 'directory-welcome-dark');
  } finally { await guest.close(); }

  for (const field of ['site_tagline', 'site_description', 'directory_heading', 'directory_intro']) await page.locator(`[name="${field}"]`).fill('');
  expect(await saveBranding(page)).toBe(303);
  const cleared = await context(browser, info, { colorScheme: 'light' });
  try {
    const gp = await cleared.newPage();
    await gp.goto('/');
    await expect(gp.getByRole('heading', { level: 1, name: 'Every board in the valley' })).toBeVisible();
    await expect(gp.locator('.forum-directory__tagline')).toHaveCount(0);
    await expect(gp.locator('meta[name="description"]')).toHaveAttribute('content', 'RetroBoards — a community forum.');
    await shot(gp, info, 'directory-defaults');
  } finally { await cleared.close(); }
});

test('invalid community text returns the whole draft without JavaScript', async ({ browser }, info) => {
  const admin = await context(browser, info, { javaScriptEnabled: false });
  try {
    const ap = await admin.newPage();
    await login(ap);
    await ap.goto('/admin/branding');
    await ap.locator('[name="site_name"]').fill('Unsaved Harbour');
    await ap.locator('[name="site_tagline"]').fill('Unsaved tagline');
    // The field's maxlength stops typing; a crafted or pasted request is
    // still refused by the server with the draft intact.
    await ap.locator('[name="directory_heading"]').evaluate(input => input.removeAttribute('maxlength'));
    await ap.locator('[name="directory_heading"]').fill('W'.repeat(81));
    expect(await saveBranding(ap)).toBe(422);
    await expect(ap.locator('#err-directory_heading')).toHaveText('Welcome heading must be 80 characters or fewer (currently 81).');
    await expect(ap.locator('[name="directory_heading"]')).toHaveAttribute('aria-invalid', 'true');
    await expect(ap.locator('[name="site_name"]')).toHaveValue('Unsaved Harbour');
    await expect(ap.locator('[name="site_tagline"]')).toHaveValue('Unsaved tagline');
    expect(fixture().site_name).toBe('RetroBoards');
    await shot(ap, info, 'invalid-text-keeps-draft-no-js');
  } finally { await admin.close(); }
});

test('the sign-in identity is keyboard reachable with the stage focus ring', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'Keyboard focus is a desktop interaction.');
  fixture('logos', 'base');
  await page.goto('/login');
  // The email field is autofocused; the identity link is the stop before it.
  await expect(page.locator('[name="email"]')).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  const brand = page.locator('.auth-brand');
  await expect(brand).toBeFocused();
  const outline = await brand.evaluate(element => getComputedStyle(element).outlineStyle);
  expect(outline).not.toBe('none');
  await shot(page, info, 'login-logo-keyboard-focus');
});

test('member appearance choices picture the resolved theme', async ({ page, browser }, info) => {
  set({ brand_theme_preset: 'retro', brand_color_primary: '#8f3d12', brand_color_accent: '#b0611c' });
  // The thumbnails must match what each mode really renders.
  const rendered: Record<string, { page: string; action: string }> = {};
  for (const scheme of ['light', 'dark'] as const) {
    const guest = await context(browser, info, { colorScheme: scheme });
    try {
      const gp = await guest.newPage();
      await gp.goto('/login');
      rendered[scheme] = await gp.evaluate(() => {
        const probe = document.createElement('div');
        document.body.append(probe);
        probe.style.backgroundColor = 'var(--surface-page)';
        probe.style.color = 'var(--accent)';
        const style = getComputedStyle(probe);
        return { page: style.backgroundColor, action: style.color };
      });
    } finally { await guest.close(); }
  }

  await login(page, 'alice@retro.test');
  await page.goto('/settings/appearance');
  for (const mode of ['light', 'dark'] as const) {
    const thumb = page.locator(`label:has(input[value="${mode}"]) .theme-thumb-mode`);
    expect(await thumb.evaluate(element => getComputedStyle(element).backgroundColor)).toBe(rendered[mode].page);
    expect(await thumb.locator('.theme-thumb-action').evaluate(element => getComputedStyle(element).backgroundColor)).toBe(rendered[mode].action);
  }
  await expect(page.locator('.choice-card-title')).toContainText(['Light', 'Dark', 'System']);
  await expect(page.getByText('Retro · light backgrounds')).toBeVisible();
  await noOverflow(page);
  await shot(page, info, 'appearance-thumbnails-light');

  if (info.project.name !== 'mobile') {
    await page.locator('input[name="theme"][value="light"]').focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.locator('input[name="theme"][value="dark"]')).toBeChecked();
    await shot(page, info, 'appearance-keyboard-choice');
  }

  fixture('member', 'alice', JSON.stringify({ theme: 'dark', font_size: 'large' }));
  await page.goto('/settings/appearance');
  await expect(page.locator('html')).toHaveAttribute('data-font-size', 'large');
  await expect(page.locator('input[name="font_size"], select[name="font_size"]').first()).toHaveValue('large');
  await noOverflow(page);
  await shot(page, info, 'appearance-thumbnails-dark-large-text');
});
