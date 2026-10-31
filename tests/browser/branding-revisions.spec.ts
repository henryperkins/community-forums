import { test, expect, type Browser, type BrowserContextOptions, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

/**
 * ADR 0054 increment 2a: versioned branding. A stale editor keeps its draft and
 * must confirm replacing newer work, history restores a configuration with its
 * logo, group resets work without JavaScript, and the recovery controls stay
 * readable under an unreadable saved palette. E2E_LAYOUT_BROWSER=webkit for WebKit.
 */
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/branding-revisions-2026-10-31');
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
async function context(browser: Browser, info: TestInfo, options: BrowserContextOptions = {}) {
  const use = info.project.use;
  return browser.newContext({
    baseURL, viewport: use.viewport, deviceScaleFactor: use.deviceScaleFactor,
    isMobile: use.isMobile, hasTouch: use.hasTouch, colorScheme: 'light', ...options,
  });
}
/** Submit and wait for the replacing document, so later reads see it. */
async function submit(page: Page, button: Locator, pathname: string) {
  const posted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === pathname);
  const committed = page.waitForEvent('framenavigated', { predicate: frame => frame === page.mainFrame() });
  await button.click();
  const response = await posted;
  await committed;
  await page.waitForLoadState('load');
  return response.status();
}
const saveBranding = (page: Page) => submit(page, page.getByRole('button', { name: 'Save branding', exact: true }), '/admin/branding');
async function contrastOf(locator: Locator) {
  return locator.evaluate(element => {
    // Normalize any computed colour form (rgb(), color(srgb …), …) to bytes.
    const canvas = document.createElement('canvas').getContext('2d')!;
    const rgb = (value: string): number[] => {
      canvas.clearRect(0, 0, 1, 1);
      canvas.fillStyle = value;
      canvas.fillRect(0, 0, 1, 1);
      return Array.from(canvas.getImageData(0, 0, 1, 1).data.slice(0, 3));
    };
    const luminance = (values: number[]) => values.map(value => {
      const v = value / 255;
      return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
    }).reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
    let background = 'rgb(255, 255, 255)';
    for (let node: Element | null = element; node; node = node.parentElement) {
      const color = getComputedStyle(node).backgroundColor;
      if (color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') { background = color; break; }
    }
    const values = [luminance(rgb(getComputedStyle(element).color)), luminance(rgb(background))].sort((a, b) => a - b);
    return (values[1] + .05) / (values[0] + .05);
  });
}

test.beforeEach(() => { fixture('reset'); });
test.afterAll(() => { fixture('reset'); });

test('a stale branding form keeps its draft and replaces newer work only when confirmed', async ({ page, browser }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="site_name"]').fill('Draft from the first admin');
  await page.locator('[name="site_tagline"]').fill('A tagline typed before the other change');

  const other = await context(browser, info);
  try {
    const op = await other.newPage();
    await login(op, 'elrond@retro.test');
    await op.goto('/admin/branding');
    await op.locator('[name="site_tagline"]').fill('Published by Elrond');
    expect(await saveBranding(op)).toBe(303);
  } finally { await other.close(); }
  const published = fixture();
  expect(published.brand_tagline).toBe('Published by Elrond');

  expect(await saveBranding(page)).toBe(409);
  const conflict = page.locator('.branding-conflict');
  await expect(conflict).toContainText('Branding changed while you were editing');
  await expect(conflict).toContainText('by @elrond: Tagline');
  await expect(page.locator('[name="site_name"]')).toHaveValue('Draft from the first admin');
  await expect(page.locator('[name="site_tagline"]')).toHaveValue('A tagline typed before the other change');
  expect(fixture().site_name).toBe('RetroBoards');
  await noOverflow(page);
  await shot(page, info, 'conflict-keeps-draft');

  await page.locator('[name="overwrite_revision"]').check();
  expect(await saveBranding(page)).toBe(303);
  const replaced = fixture();
  expect(replaced.site_name).toBe('Draft from the first admin');
  expect(replaced.brand_tagline).toBe('A tagline typed before the other change');
  // Baseline, Elrond's change, then the confirmed replacement.
  expect(Number(replaced.revisions)).toBe(3);
});

test('history reviews a revision and restores its logo as a new revision', async ({ page }, info) => {
  const logos = fixture('logos', 'base');
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="site_name"]').fill('Harbour Commons');
  expect(await saveBranding(page)).toBe(303);
  await page.locator('[name="remove_logo"]').check();
  await page.locator('[name="site_name"]').fill('Harbour Commons without a logo');
  expect(await saveBranding(page)).toBe(303);
  expect(fixture().brand_logo_path).toBe('');

  await page.getByRole('link', { name: 'History', exact: true }).click();
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  const rows = page.locator('.branding-history-table tbody tr');
  await expect(rows).toHaveCount(3);
  await expect(rows.first()).toContainText('Live');
  await expect(rows.first()).toContainText('Community name, Logo');
  await expect(rows.last()).toContainText('Configuration before history began');
  await noOverflow(page);
  await shot(page, info, 'history');

  const withLogo = rows.nth(1);
  await withLogo.getByRole('link', { name: /Review revision/ }).click();
  await expect(page.locator('.branding-revision-thumb')).toHaveAttribute('src', logos.brand_logo_path);
  await expect(page.locator('.branding-revision-table')).toContainText('Harbour Commons without a logo');
  await noOverflow(page);
  await shot(page, info, 'revision-review');

  const restore = page.getByRole('button', { name: /Restore revision #\d+/ });
  expect(await submit(page, restore, new URL(await page.locator('form.branding-restore-card').getAttribute('action') ?? '', baseURL).pathname)).toBe(303);
  await expect(page.locator('body')).toContainText(/Restored revision \d+ as revision \d+/);
  const restored = fixture();
  expect(restored.brand_logo_path).toBe(logos.brand_logo_path);
  expect(restored.site_name).toBe('Harbour Commons');
  expect(Number(restored.revisions)).toBe(4);
  await page.goto('/');
  await expect(page.locator('.brand-logo')).toHaveAttribute('src', logos.brand_logo_path);
  await shot(page, info, 'restored-logo-live');
});

test('group resets confirm without JavaScript and leave the other groups alone', async ({ browser }, info) => {
  const logos = fixture('logos', 'base');
  set({ brand_color_primary: '#2e4a3a', brand_color_accent: '#c29a44', brand_theme_preset: 'retro', brand_theme_default: 'dark', brand_tagline: 'Kept text' });
  const admin = await context(browser, info, { javaScriptEnabled: false });
  try {
    const ap = await admin.newPage();
    await login(ap);
    await ap.goto('/admin/branding#branding-recovery');
    const colours = ap.locator('.branding-reset-groups details').filter({ hasText: 'Reset colours…' });
    await colours.locator('summary').click();
    await expect(colours.locator('.dm-confirm-note')).toContainText('Clears the primary and accent overrides.');
    await noOverflow(ap);
    await shot(ap, info, 'reset-colours-step-no-js');
    expect(await submit(ap, colours.getByRole('button', { name: 'Reset colours', exact: true }), '/admin/branding/reset/colours')).toBe(303);
    await expect(ap.locator('body')).toContainText('Reset the colours. Everything else was kept.');
    const after = fixture();
    expect(after.brand_color_primary).toBe('');
    expect(after.brand_color_accent).toBe('');
    expect(after.brand_logo_path).toBe(logos.brand_logo_path);
    expect(after.brand_theme_preset).toBe('retro');
    expect(after.brand_theme_default).toBe('dark');
    expect(after.brand_tagline).toBe('Kept text');
    await shot(ap, info, 'reset-colours-done-no-js');
  } finally { await admin.close(); }
});

test('the reset steps are keyboard operable', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'Keyboard operation is a desktop interaction.');
  set({ brand_theme_preset: 'retro' });
  await login(page);
  await page.goto('/admin/branding');
  const summary = page.locator('.branding-reset-groups summary').filter({ hasText: 'Reset appearance defaults…' });
  await summary.focus();
  await page.keyboard.press('Enter');
  const confirm = page.getByRole('button', { name: 'Reset appearance defaults', exact: true });
  await expect(confirm).toBeVisible();
  await page.keyboard.press('Tab');
  await expect(confirm).toBeFocused();
  await shot(page, info, 'reset-step-keyboard');
  await page.keyboard.press('Escape');
  await expect(confirm).toBeHidden();
  await expect(summary).toBeFocused();
});

test('recovery controls stay readable when the saved palette is not', async ({ page }, info) => {
  // An operator acknowledged colours that make links unreadable.
  set({ brand_color_primary: '#fdfcf8', brand_color_accent: '#fbf7ec', brand_theme_default: 'light' });
  await login(page);
  await page.goto('/');
  const unreadable = await contrastOf(page.locator('.forum-directory__hero a[href="/inbox"]'));
  await shot(page, info, 'unreadable-palette-elsewhere');

  await page.goto('/admin/branding');
  for (const locator of [
    page.locator('.admin-tier-item').first(),
    page.getByRole('link', { name: 'branding history' }),
    page.locator('.branding-reset-groups summary').first(),
    page.getByRole('button', { name: 'Reset branding', exact: true }),
    page.getByRole('button', { name: 'Save branding', exact: true }),
  ]) {
    expect(await contrastOf(locator)).toBeGreaterThanOrEqual(4.5);
  }
  expect(unreadable).toBeLessThan(4.5);
  // The sample still shows the saved palette that needs fixing.
  await expect(page.locator('[data-brand-preview] .btn')).toBeVisible();
  await noOverflow(page);
  await shot(page, info, 'recovery-readable');
  await page.goto('/admin/branding/history');
  expect(await contrastOf(page.locator('.admin-tier-item').first())).toBeGreaterThanOrEqual(4.5);
});
