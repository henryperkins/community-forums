import { test, expect, type Browser, type BrowserContextOptions, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

/**
 * ADR 0054 increment 4: curated style presets. Each preset is a complete
 * combination — surfaces, sidebar, heading type and corners — and must reach
 * navigation, reading, forms and messaging alike. Expected values come from the
 * PHP definitions, so this spec checks the shipped pages against the same
 * source the server uses. E2E_LAYOUT_BROWSER=webkit for WebKit.
 */
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/branding-styles-2026-10-31');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${process.env.E2E_PORT ?? 8011}`;
test.use({ browserName, colorScheme: 'light' });

type Mode = 'light' | 'dark';
type Palette = Record<string, string>;
const php = (code: string) => JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', ['-r', `require 'vendor/autoload.php'; ${code}`], { cwd: root, encoding: 'utf8' }));
const palettes: Record<string, Record<Mode, Palette>> = php('echo json_encode(App\\Support\\BrandPalette::presets());');
const presets: Record<string, { rail: string; type: string; corners: string }> = php('echo json_encode(App\\Support\\BrandStyle::PRESETS);');
const railGround: Record<string, string> = { sunken: '--surface-sunken', flush: '--surface-page', raised: '--surface-raised' };
const headingFamily: Record<string, string> = { imladris: 'Cormorant Garamond', book: 'EB Garamond', lapidary: 'Marcellus' };
const cornerMd: Record<string, string> = { crisp: '3px', imladris: '7px', soft: '10px' };

function identity(...args: string[]) {
  return JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'branding-identity-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
function theming(action = 'reset') {
  execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'theming-remediation-fixture.php'), action], { cwd: root, env: process.env, encoding: 'utf8' });
}
const set = (values: Record<string, string>) => identity('set', JSON.stringify(values));
const rgb = (hex: string) => `rgb(${[1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16)).join(', ')})`;

async function login(page: Page, email = 'admin@retro.test') {
  await page.goto('/login');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}
async function context(browser: Browser, info: TestInfo, options: BrowserContextOptions = {}) {
  const use = info.project.use;
  return browser.newContext({
    baseURL, viewport: use.viewport, deviceScaleFactor: use.deviceScaleFactor,
    isMobile: use.isMobile, hasTouch: use.hasTouch, colorScheme: 'light', ...options,
  });
}
function directory(info: TestInfo) {
  const dir = path.join(evidence, browserName, info.project.name);
  mkdirSync(dir, { recursive: true });
  return dir;
}
async function shot(page: Page, info: TestInfo, name: string, fullPage = false) {
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(directory(info), `${name}.png`), fullPage, animations: 'disabled' });
}
async function regionShot(page: Page, info: TestInfo, selector: string, name: string) {
  const region = page.locator(selector);
  await page.evaluate(() => document.querySelectorAll<HTMLElement>('body *').forEach(node => {
    if (getComputedStyle(node).position === 'sticky') { node.dataset.captureSticky = '1'; node.style.position = 'static'; }
  }));
  await region.scrollIntoViewIfNeeded();
  await region.screenshot({ path: path.join(directory(info), `${name}.png`), animations: 'disabled' });
  await page.evaluate(() => document.querySelectorAll<HTMLElement>('[data-capture-sticky]').forEach(node => {
    node.style.position = '';
    delete node.dataset.captureSticky;
  }));
}
async function submit(page: Page, button: Locator, pathname: string) {
  const posted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === pathname);
  const committed = page.waitForEvent('framenavigated', { predicate: frame => frame === page.mainFrame() });
  await button.click();
  const response = await posted;
  await committed;
  await page.waitForLoadState('load');
  return response.status();
}
async function noOverflow(page: Page) {
  const sizes = await page.evaluate(() => ({ actual: document.documentElement.scrollWidth, viewport: document.documentElement.clientWidth }));
  expect(sizes.actual).toBeLessThanOrEqual(sizes.viewport);
}

/**
 * Surfaces still painted with Classic's grounds while another preset serves.
 * Constant-ground regions (the twilight auth stage and profile cover) and
 * identity tints (monograms, avatars, samples, thumbnails) keep their colours
 * by design and are skipped.
 */
async function classicLeaks(page: Page, mode: Mode) {
  const classic = palettes.classic[mode];
  const colours = ['--surface-page', '--surface-raised', '--surface-sunken', '--surface-soft']
    .map(token => classic[token]).filter(value => /^#[0-9a-f]{6}$/i.test(value)).map(rgb);
  return page.evaluate(({ colours }) => {
    // Status chips keep the application's status families (the pending wash
    // can equal Classic's sunken ground) and are not surfaces.
    const skip = '.auth-stage, .profile-cover, .brand-preview, .theme-thumb, .monogram, .avatar, [class*="mono-"], .presence-dot, [class*="chip"], [class*="status"], .badge';
    const leaks: string[] = [];
    for (const element of Array.from(document.querySelectorAll<HTMLElement>('body *'))) {
      if (element.closest(skip)) continue;
      const box = element.getBoundingClientRect();
      if (box.width < 4 || box.height < 4) continue;
      const style = getComputedStyle(element);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      if (colours.includes(style.backgroundColor)) {
        leaks.push(`${element.tagName.toLowerCase()}.${Array.from(element.classList).join('.')} ${style.backgroundColor}`);
      }
    }
    return leaks;
  }, { colours });
}

/** One page under a preset: its rail ground, heading family, control corners and no Classic surfaces. */
async function checkPage(page: Page, preset: string, mode: Mode, label: string) {
  const parts = presets[preset];
  const palette = palettes[preset][mode];
  const html = page.locator('html');
  const expectAttr = async (name: string, value: string, fallback: string) => value === fallback
    ? expect(html).not.toHaveAttribute(name, /.+/)
    : expect(html).toHaveAttribute(name, value);
  await expectAttr('data-rail', parts.rail, 'sunken');
  await expectAttr('data-type-pairing', parts.type, 'imladris');
  await expectAttr('data-corners', parts.corners, 'imladris');
  const rail = page.locator('.board-rail').first();
  if (await rail.count() > 0) {
    expect(await rail.evaluate(element => getComputedStyle(element).backgroundColor), `${label} rail`).toBe(rgb(palette[railGround[parts.rail]]));
  }
  const firstFamily = (locator: Locator) => locator.evaluate(element => getComputedStyle(element).fontFamily.split(',')[0].replace(/["']/g, '').trim());
  // The study title is a display heading; text stays EB Garamond in every pairing.
  const title = page.locator('h1.thread-study-title');
  if (await title.count() > 0) {
    expect(await firstFamily(title), `${label} display heading`).toBe(headingFamily[parts.type]);
  }
  expect(await firstFamily(page.locator('body')), `${label} text`).toBe('EB Garamond');
  // Every display element took the pairing: no visible text is left in
  // Cormorant once another heading face is chosen.
  if (parts.type !== 'imladris') {
    const cormorant = await page.evaluate(() => Array.from(document.querySelectorAll<HTMLElement>('body *')).filter(element => {
      if (element.closest('.brand-preview')) return false;
      const text = Array.from(element.childNodes).some(node => node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim() !== '');
      const box = element.getBoundingClientRect();
      return text && box.width > 0 && box.height > 0
        && getComputedStyle(element).fontFamily.split(',')[0].replace(/["']/g, '').trim() === 'Cormorant Garamond';
    }).map(element => `${element.tagName.toLowerCase()}.${Array.from(element.classList).join('.')}`));
    expect(cormorant, `${label} text left in Cormorant`).toEqual([]);
  }
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--radius-md').trim()), `${label} corner scale`)
    .toBe(cornerMd[parts.corners]);
  const field = page.locator('select.input:visible, input.input:visible').first();
  if (await field.count() > 0) {
    expect(await field.evaluate(element => getComputedStyle(element).borderTopLeftRadius), `${label} field corner`).toBe(cornerMd[parts.corners]);
  }
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor), `${label} page ground`).toBe(rgb(palette['--surface-page']));
  expect(await classicLeaks(page, mode), `${label} Classic surfaces`).toEqual([]);
}

test.beforeEach(() => { theming('reset'); identity('reset'); });
test.afterAll(() => { theming('reset'); identity('reset'); });

for (const preset of ['ledger', 'vellum'] as const) {
  test(`${preset} reaches navigation, reading, forms and messaging in both modes`, async ({ browser }, info) => {
    set({ brand_theme_preset: preset });
    for (const mode of ['light', 'dark'] as const) {
      const member = await context(browser, info, { colorScheme: mode });
      try {
        const page = await member.newPage();
        await login(page, 'alice@retro.test');
        for (const [surface, route] of [['navigation', '/'], ['reading', '/t/1-welcome-to-retroboards'], ['forms', '/settings/appearance'], ['messaging', '/messages']] as const) {
          await page.goto(route);
          await checkPage(page, preset, mode, `${preset} ${mode} ${surface}`);
          await noOverflow(page);
          await shot(page, info, `${preset}-${mode}-${surface}`);
        }
      } finally { await member.close(); }
    }
  });
}

test('an override replaces one part of a preset, over a package too', async ({ browser, page }, info) => {
  set({ brand_theme_preset: 'vellum', brand_corners: 'crisp', brand_type_pairing: 'book' });
  await page.goto('/');
  await expect(page.locator('html')).toHaveAttribute('data-rail', 'raised');
  await expect(page.locator('html')).toHaveAttribute('data-corners', 'crisp');
  await expect(page.locator('html')).toHaveAttribute('data-type-pairing', 'book');
  expect(await page.locator('h1').first().evaluate(element => getComputedStyle(element).fontFamily)).toMatch(/^"?EB Garamond/);
  await shot(page, info, 'vellum-overrides');

  // With a package active, the preset is suspended; overrides still apply.
  await login(page);
  await page.goto('/admin/themes');
  const row = page.locator('tbody tr').filter({ has: page.locator('code', { hasText: /^acme\/theme-evidence$/ }) });
  await row.locator('[name="current_password"]').fill('password123');
  await row.locator('form[action$="/activate"]').evaluate(async form => {
    await Promise.all(form.getAnimations().map(animation => animation.finished));
  });
  await row.getByRole('button', { name: 'Activate', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Theme activated.');
  const guest = await context(browser, info);
  try {
    const gp = await guest.newPage();
    await gp.goto('/');
    await expect(gp.locator('html')).not.toHaveAttribute('data-rail', /.+/);
    await expect(gp.locator('html')).toHaveAttribute('data-corners', 'crisp');
    await expect(gp.locator('html')).toHaveAttribute('data-type-pairing', 'book');
    await shot(gp, info, 'package-with-overrides');
  } finally { await guest.close(); }
});

test('the density default applies to visitors and undecided members only', async ({ browser }, info) => {
  set({ brand_density_default: 'compact' });
  identity('member', 'bob', JSON.stringify({ density: null }));
  identity('member', 'alice', JSON.stringify({ density: 'comfortable' }));
  const guest = await context(browser, info);
  try {
    const gp = await guest.newPage();
    await gp.goto('/c/general');
    await expect(gp.locator('html')).toHaveAttribute('data-density', 'compact');
    await shot(gp, info, 'density-guest-compact');
  } finally { await guest.close(); }
  for (const [email, expected] of [['bob@retro.test', 'compact'], ['alice@retro.test', 'comfortable']] as const) {
    const member = await context(browser, info);
    try {
      const mp = await member.newPage();
      await login(mp, email);
      await mp.goto('/c/general');
      await expect(mp.locator('html')).toHaveAttribute('data-density', expected);
      await mp.goto('/settings/appearance');
      await expect(mp.locator(`input[name="density"][value="${expected}"]`)).toBeChecked();
      if (expected === 'compact') await shot(mp, info, 'density-undecided-member');
    } finally { await member.close(); }
  }
});

test('without JavaScript the style controls publish and the sample shows the saved style', async ({ browser }, info) => {
  const admin = await context(browser, info, { javaScriptEnabled: false });
  try {
    const ap = await admin.newPage();
    await login(ap);
    await ap.goto('/admin/branding');
    // The branding page keeps the built-in style for its own controls.
    await expect(ap.locator('html')).not.toHaveAttribute('data-rail', /.+/);
    await ap.locator('[name="theme_preset"]').selectOption('ledger');
    await ap.locator('[name="corners"]').selectOption('soft');
    await ap.locator('[name="density_default"]').selectOption('compact');
    expect(await submit(ap, ap.getByRole('button', { name: 'Publish branding', exact: true }), '/admin/branding')).toBe(303);
    const sample = ap.locator('[data-brand-preview]');
    await expect(sample).toHaveAttribute('data-rail', 'flush');
    await expect(sample).toHaveAttribute('data-type-pairing', 'book');
    await expect(sample).toHaveAttribute('data-corners', 'soft');
    await expect(ap.locator('[name="corners"] option[value=""]')).toHaveText('Preset — Crisp (2–6px)');
    await regionShot(ap, info, '.branding-style', 'no-js-style-controls');
    await regionShot(ap, info, '[data-brand-preview]', 'no-js-style-sample');
    const saved = identity();
    expect([saved.brand_theme_preset, saved.brand_corners, saved.brand_density_default]).toEqual(['ledger', 'soft', 'compact']);
  } finally { await admin.close(); }
  const guest = await context(browser, info, { javaScriptEnabled: false });
  try {
    const gp = await guest.newPage();
    await gp.goto('/');
    await expect(gp.locator('html')).toHaveAttribute('data-corners', 'soft');
    await expect(gp.locator('html')).toHaveAttribute('data-rail', 'flush');
    await expect(gp.locator('html')).toHaveAttribute('data-density', 'compact');
  } finally { await guest.close(); }
});

test('the style controls are keyboard operable and the sample follows them', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'Keyboard focus is a desktop interaction.');
  await login(page);
  await page.goto('/admin/branding');
  const sample = page.locator('[data-brand-preview]');
  await page.locator('[name="theme_preset"]').selectOption('vellum');
  await expect(sample).toHaveAttribute('data-rail', 'raised');
  await expect(sample).toHaveAttribute('data-corners', 'soft');
  await expect(page.locator('[name="sidebar_style"] option[value=""]')).toHaveText('Preset — Raised panel');
  // The parts follow the preset in reading order.
  await page.locator('[name="theme_preset"]').focus();
  for (const field of ['sidebar_style', 'type_pairing', 'corners', 'density_default']) {
    await page.keyboard.press('Tab');
    await expect(page.locator(`[name="${field}"]`)).toBeFocused();
  }
  await page.locator('[name="corners"]').focus();
  if (browserName === 'chromium') {
    await page.keyboard.press('ArrowDown');
    await expect(page.locator('[name="corners"]')).toHaveValue('crisp');
  } else {
    await page.locator('[name="corners"]').selectOption('crisp');
  }
  await expect(sample).toHaveAttribute('data-corners', 'crisp');
  expect(await sample.locator('.btn').evaluate(element => getComputedStyle(element).borderTopLeftRadius)).toBe('3px');
  await regionShot(page, info, '.branding-style', 'keyboard-style-controls');
  await regionShot(page, info, '[data-brand-preview]', 'keyboard-style-sample');
});

test('at large text the style controls stay inside the page', async ({ page }, info) => {
  identity('member', 'admin', JSON.stringify({ font_size: 'large' }));
  await login(page);
  await page.goto('/admin/branding');
  await expect(page.locator('html')).toHaveAttribute('data-font-size', 'large');
  await noOverflow(page);
  const width = page.viewportSize()!.width;
  const box = (await page.locator('.branding-style').boundingBox())!;
  expect(box.x + box.width).toBeLessThanOrEqual(width);
  await regionShot(page, info, '.branding-style', 'large-text-style-controls');
});
