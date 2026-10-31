import { test, expect, type Browser, type BrowserContextOptions, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

/**
 * ADR 0054 increment 3: independent light and dark colours. Each mode's
 * override (with suggestions) reaches the light/dark state samples and fresh
 * public pages identically — for Classic, Retro and an active package, with
 * extreme colours and after clearing. E2E_LAYOUT_BROWSER=webkit for WebKit.
 */
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/branding-palettes-2026-10-31');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${process.env.E2E_PORT ?? 8011}`;
test.use({ browserName, colorScheme: 'light' });

function identity(...args: string[]) {
  return JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'branding-identity-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
function theming(action = 'reset') {
  execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'theming-remediation-fixture.php'), action], { cwd: root, env: process.env, encoding: 'utf8' });
}
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
async function context(browser: Browser, info: TestInfo, options: BrowserContextOptions = {}) {
  const use = info.project.use;
  return browser.newContext({
    baseURL, viewport: use.viewport, deviceScaleFactor: use.deviceScaleFactor,
    isMobile: use.isMobile, hasTouch: use.hasTouch, colorScheme: 'light', ...options,
  });
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
async function publish(page: Page) {
  const button = () => page.getByRole('button', { name: 'Publish branding', exact: true });
  let status = await submit(page, button(), '/admin/branding');
  if (status === 422 && await page.locator('.branding-contrast-warning').count() > 0) {
    await page.locator('[name="contrast_ack"]').check();
    status = await submit(page, button(), '/admin/branding');
  }
  return status;
}
async function activatePackage(page: Page) {
  await page.goto('/admin/themes');
  const row = page.locator('tbody tr').filter({ has: page.locator('code', { hasText: /^acme\/theme-evidence$/ }) });
  await row.locator('[name="current_password"]').fill('password123');
  await row.locator('form[action$="/activate"]').evaluate(async form => {
    await Promise.all(form.getAnimations().map(animation => animation.finished));
  });
  await row.getByRole('button', { name: 'Activate', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Theme activated.');
}
/** Rendered colours as 8-bit channels, whatever the computed colour syntax. */
async function paint(locator: Locator, property: 'backgroundColor' | 'color') {
  return locator.evaluate((element, prop) => {
    const canvas = document.createElement('canvas').getContext('2d')!;
    canvas.fillStyle = getComputedStyle(element)[prop as 'color'];
    canvas.fillRect(0, 0, 1, 1);
    return Array.from(canvas.getImageData(0, 0, 1, 1).data.slice(0, 3));
  }, property);
}
function distance(actual: number[], expected: number[]) {
  return Math.max(...actual.map((channel, i) => Math.abs(channel - expected[i])));
}
function near(actual: number[], expected: number[], label: string) {
  // Unset overrides take the stylesheet's color-mix() fallback in public, the
  // resolver's whole-channel mix in the sample: allow that rounding only.
  actual.forEach((channel, i) => expect(Math.abs(channel - expected[i]), `${label} channel ${i}: ${actual} vs ${expected}`).toBeLessThanOrEqual(1));
}
/** The sample's state colours for one mode against a fresh public page in that mode. */
async function compareMode(page: Page, browser: Browser, info: TestInfo, mode: 'light' | 'dark') {
  const strip = page.locator(`[data-brand-states][data-brand-theme="${mode}"]`);
  const sample = {
    action: await paint(strip.locator('.brand-state-action'), 'backgroundColor'),
    actionInk: await paint(strip.locator('.brand-state-action'), 'color'),
    hover: await paint(strip.locator('.brand-state-hover'), 'backgroundColor'),
    selected: await paint(strip.locator('.brand-state-selected'), 'backgroundColor'),
    selectedInk: await paint(strip.locator('.brand-state-selected'), 'color'),
    link: await paint(strip.locator('.brand-state-link'), 'color'),
  };
  // Reduced motion collapses the button's background transition, so the
  // hovered colour read is the settled one.
  const guest = await context(browser, info, { colorScheme: mode, reducedMotion: 'reduce' });
  try {
    const gp = await guest.newPage();
    await gp.goto('/');
    near(await paint(gp.locator('.forum-bar-surface.is-active').first(), 'backgroundColor'), sample.selected, `${mode} selected`);
    near(await paint(gp.locator('.forum-bar-surface.is-active').first(), 'color'), sample.selectedInk, `${mode} selected ink`);
    near(await paint(gp.locator('.forum-directory__hero a[href="/inbox"]'), 'color'), sample.link, `${mode} link`);
    await gp.goto('/login');
    const button = gp.locator('.auth-form .btn');
    near(await paint(button, 'backgroundColor'), sample.action, `${mode} action`);
    near(await paint(button, 'color'), sample.actionInk, `${mode} action ink`);
    // WebKit applies :hover a frame after the pointer moves, so wait for the
    // settled fill instead of reading the first frame.
    await button.hover();
    await expect.poll(async () => distance(await paint(button, 'backgroundColor'), sample.hover), {
      message: `${mode} hover vs sample ${sample.hover}`, timeout: 5000,
    }).toBeLessThanOrEqual(1);
  } finally { await guest.close(); }
}

/** One region on its own: the per-mode fields or the light/dark state samples. */
async function regionShot(page: Page, info: TestInfo, selector: string, name: string) {
  const region = page.locator(selector);
  // Sticky chrome would cover the top of a region scrolled beneath it, so it
  // scrolls with the page for the capture and is restored afterwards.
  await page.evaluate(() => document.querySelectorAll<HTMLElement>('body *').forEach(node => {
    if (getComputedStyle(node).position === 'sticky') { node.dataset.captureSticky = '1'; node.style.position = 'static'; }
  }));
  await region.scrollIntoViewIfNeeded();
  const directory = path.join(evidence, browserName, info.project.name);
  mkdirSync(directory, { recursive: true });
  await region.screenshot({ path: path.join(directory, `${name}.png`), animations: 'disabled' });
  await page.evaluate(() => document.querySelectorAll<HTMLElement>('[data-capture-sticky]').forEach(node => {
    node.style.position = '';
    delete node.dataset.captureSticky;
  }));
}
const stripShot = (page: Page, info: TestInfo, name: string) => regionShot(page, info, '.brand-mode-states', name);

async function noOverflow(page: Page) {
  const sizes = await page.evaluate(() => ({ actual: document.documentElement.scrollWidth, viewport: document.documentElement.clientWidth }));
  expect(sizes.actual).toBeLessThanOrEqual(sizes.viewport);
}
function rgb(hex: string) {
  return [1, 3, 5].map(i => parseInt(hex.slice(i, i + 2), 16));
}
const publishButton = (page: Page) => page.getByRole('button', { name: 'Publish branding', exact: true });

async function openModes(page: Page) {
  await page.locator('details.branding-mode-colours').evaluate(details => { (details as HTMLDetailsElement).open = true; });
}

test.beforeEach(() => { theming('reset'); identity('reset'); });
test.afterAll(() => { theming('reset'); identity('reset'); });

test('a dark override from the suggestion fixes dark mode without touching light', async ({ page, browser }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="color_primary"]').fill('#2e4a3a');
  expect(await submit(page, page.getByRole('button', { name: 'Publish branding', exact: true }), '/admin/branding')).toBe(422);
  await expect(page.locator('.branding-contrast-warning')).toContainText('on dark surfaces');
  const disclosure = page.locator('details.branding-mode-colours');
  await expect(disclosure).toHaveAttribute('open', '');
  const suggestion = (await page.locator('[data-brand-suggest="color_primary_dark"]').getAttribute('data-brand-suggest-value'))!;
  expect(suggestion).toMatch(/^#[0-9a-f]{6}$/);
  await shot(page, info, 'dark-suggestion-offered');
  await page.locator('[data-brand-suggest="color_primary_dark"]').click();
  await expect(page.locator('[name="color_primary_dark"]')).toHaveValue(suggestion);
  expect(await submit(page, page.getByRole('button', { name: 'Publish branding', exact: true }), '/admin/branding')).toBe(303);
  const saved = identity();
  expect(saved.brand_color_primary).toBe('#2e4a3a');
  expect(saved.brand_color_primary_dark).toBe(suggestion);
  await regionShot(page, info, 'details.branding-mode-colours', 'dark-suggestion-published');
  await stripShot(page, info, 'dark-suggestion-states');
  await compareMode(page, browser, info, 'light');
  await compareMode(page, browser, info, 'dark');
});

for (const base of ['classic', 'retro', 'package'] as const) {
  test(`${base}: extreme and cleared per-mode colours match fresh public pages`, async ({ page, browser }, info) => {
    await login(page);
    if (base === 'package') await activatePackage(page);
    await page.goto('/admin/branding');
    await page.locator('[name="theme_preset"]').selectOption(base === 'retro' ? 'retro' : 'classic');
    await page.locator('[name="color_primary"]').fill('#005fcc');
    await openModes(page);
    // Extremes: black and white actions, a saturated accent per mode.
    await page.locator('[name="color_primary_light"]').fill('#000000');
    await page.locator('[name="color_primary_dark"]').fill('#ffffff');
    await page.locator('[name="color_accent_light"]').fill('#ff00ff');
    await page.locator('[name="color_accent_dark"]').fill('#00ff00');
    expect(await publish(page)).toBe(303);
    for (const mode of ['light', 'dark'] as const) await compareMode(page, browser, info, mode);
    await regionShot(page, info, 'details.branding-mode-colours', `${base}-extreme-fields`);
    await stripShot(page, info, `${base}-extreme-states`);

    // Cleared: every mode returns to its package or preset roles.
    await openModes(page);
    for (const field of ['color_primary', 'color_primary_light', 'color_primary_dark', 'color_accent_light', 'color_accent_dark']) {
      await page.locator(`[name="${field}"]`).fill('');
    }
    expect(await publish(page)).toBe(303);
    for (const mode of ['light', 'dark'] as const) await compareMode(page, browser, info, mode);
    await stripShot(page, info, `${base}-cleared-states`);
  });
}

test('without JavaScript a dark warning opens the mode colours with a suggestion to type', async ({ browser }, info) => {
  const admin = await context(browser, info, { javaScriptEnabled: false });
  try {
    const ap = await admin.newPage();
    await login(ap);
    await ap.goto('/admin/branding');
    await ap.locator('[name="color_primary"]').fill('#2e4a3a');
    expect(await submit(ap, publishButton(ap), '/admin/branding')).toBe(422);
    await expect(ap.locator('details.branding-mode-colours')).toHaveAttribute('open', '');
    const offer = ap.locator('.branding-colour-suggestion:has([data-brand-suggest="color_primary_dark"])');
    await expect(offer).toContainText('Suggested for readable links in dark mode');
    // The fill-in button needs JavaScript; the colour itself is plain text.
    await expect(offer.getByRole('button', { name: 'Use suggestion' })).toBeHidden();
    const suggestion = (await offer.locator('code').textContent())!.trim();
    expect(suggestion).toMatch(/^#[0-9a-f]{6}$/);
    await ap.locator('[name="color_primary_dark"]').fill(suggestion);
    await ap.locator('[name="color_primary_dark"]').scrollIntoViewIfNeeded();
    await shot(ap, info, 'no-js-suggestion-typed');
    expect(await submit(ap, publishButton(ap), '/admin/branding')).toBe(303);
    const saved = identity();
    expect(saved.brand_color_primary).toBe('#2e4a3a');
    expect(saved.brand_color_primary_dark).toBe(suggestion);
  } finally { await admin.close(); }
});

test('the mode colours and their suggestion are keyboard operable', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'Keyboard focus is a desktop interaction.');
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="color_primary"]').fill('#2e4a3a');
  expect(await submit(page, publishButton(page), '/admin/branding')).toBe(422);
  const disclosure = page.locator('details.branding-mode-colours');
  const suggestion = (await page.locator('[data-brand-suggest="color_primary_dark"]').getAttribute('data-brand-suggest-value'))!;
  // The review opened the disclosure; it closes and reopens from the keyboard.
  await page.locator('[name="color_accent"]').focus();
  await page.keyboard.press('Tab');
  await expect(disclosure.locator('summary')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(disclosure).not.toHaveAttribute('open', '');
  await page.keyboard.press('Enter');
  await expect(disclosure).toHaveAttribute('open', '');
  // Light primary, light accent, dark primary, then the dark suggestion.
  for (const field of ['color_primary_light', 'color_accent_light', 'color_primary_dark']) {
    await page.keyboard.press('Tab');
    await expect(page.locator(`[name="${field}"]`)).toBeFocused();
  }
  await page.keyboard.press('Tab');
  await expect(page.getByRole('button', { name: 'Use suggestion' })).toBeFocused();
  await page.keyboard.press('Enter');
  const dark = page.locator('[name="color_primary_dark"]');
  await expect(dark).toHaveValue(suggestion);
  await expect(dark).toBeFocused();
  // The dark sample follows at once; the light one keeps the shared colour.
  near(await paint(page.locator('[data-brand-states][data-brand-theme="dark"] .brand-state-action'), 'backgroundColor'), rgb(suggestion), 'dark sample');
  near(await paint(page.locator('[data-brand-states][data-brand-theme="light"] .brand-state-action'), 'backgroundColor'), rgb('#2e4a3a'), 'light sample');
  await regionShot(page, info, 'details.branding-mode-colours', 'keyboard-suggestion-applied');
  await stripShot(page, info, 'keyboard-suggestion-states');
});

test('at large text the mode colours and state samples stay inside the page', async ({ page }, info) => {
  identity('member', 'admin', JSON.stringify({ font_size: 'large' }));
  await login(page);
  await page.goto('/admin/branding');
  await expect(page.locator('html')).toHaveAttribute('data-font-size', 'large');
  await openModes(page);
  await page.locator('[name="color_primary_dark"]').fill('#9fc3a8');
  await page.locator('[name="color_accent_light"]').fill('#8f3d12');
  await noOverflow(page);
  const width = page.viewportSize()!.width;
  for (const selector of ['.branding-mode-grid', '.brand-mode-states']) {
    const box = (await page.locator(selector).boundingBox())!;
    expect(box.x + box.width, selector).toBeLessThanOrEqual(width);
  }
  await regionShot(page, info, 'details.branding-mode-colours', 'large-text-modes');
  await stripShot(page, info, 'large-text-states');
});
