import { test, expect, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/theming-remediation-2026-10-31');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
test.use({ browserName, colorScheme: 'light' });

function fixture(action = 'state') {
  return JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'theming-remediation-fixture.php'), action], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
}
async function login(page: Page) {
  await page.goto('/login');
  await page.locator('[name="email"]').fill('admin@retro.test');
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
async function post(page: Page, button: Locator, pathname: string) {
  const [response] = await Promise.all([
    page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === pathname),
    button.click(),
  ]);
  await page.waitForLoadState('networkidle');
  return response.status();
}
async function saveBranding(page: Page) {
  return post(page, page.getByRole('button', { name: 'Save branding', exact: true }), '/admin/branding');
}
const brandAssetFields = ['logo', 'logo_light', 'logo_dark', 'favicon'] as const;
const brandAssetPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC', 'base64');
async function uploadBrandingMarks(page: Page) {
  await page.goto('/admin/branding');
  await page.locator('[name="site_name"]').fill('Community with individual marks');
  await page.locator('[name="theme_preset"]').selectOption('retro');
  await page.locator('[name="theme_default"]').selectOption('dark');
  for (const field of brandAssetFields) {
    await page.locator(`[name="${field}"]`).setInputFiles({ name: `${field}.png`, mimeType: 'image/png', buffer: brandAssetPng });
  }
  expect(await saveBranding(page)).toBe(303);
  const saved = fixture();
  for (const field of brandAssetFields) expect(saved.assets[field]).toMatch(/^\/media\//);
  return saved;
}
function brandSettings(state: ReturnType<typeof fixture>) {
  return { site_name: state.site_name, primary: state.primary, accent: state.accent, preset: state.preset, theme_default: state.theme_default };
}
function themeRow(page: Page, uid: string) {
  return page.locator('tbody tr').filter({ has: page.locator('code', { hasText: new RegExp(`^${uid.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }) });
}
async function activate(page: Page, uid: string) {
  const row = themeRow(page, uid);
  const action = await row.locator('form[action$="/activate"]').getAttribute('action');
  await row.locator('[name="current_password"]').fill('password123');
  // WebKit's no-JS actionability poll can stall if it starts during aaRise.
  // Wait for the real entrance animation; keep motion and ordinary clicks.
  await row.locator('form[action$="/activate"]').evaluate(async form => {
    await Promise.all(form.getAnimations().map(animation => animation.finished));
  });
  expect(await post(page, row.getByRole('button', { name: 'Activate', exact: true }), action!)).toBe(303);
  await expect(page.getByRole('status')).toContainText('Theme activated.');
}
async function renderedContrast(locator: Locator) {
  return locator.evaluate(element => {
    const rgb = (value: string): number[] => (value.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
    const luminance = (values: number[]) => values.map(value => {
      const v = value / 255;
      return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
    }).reduce((sum, value, index) => sum + value * [.2126, .7152, .0722][index], 0);
    let background = 'rgb(255, 255, 255)';
    for (let node: Element | null = element; node; node = node.parentElement) {
      const color = getComputedStyle(node).backgroundColor;
      if (color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') { background = color; break; }
    }
    const foreground = getComputedStyle(element).color;
    const values = [luminance(rgb(foreground)), luminance(rgb(background))].sort((a, b) => a - b);
    return { foreground, background, ratio: (values[1] + .05) / (values[0] + .05) };
  });
}

test.beforeEach(() => fixture('reset'));
test.afterEach(() => fixture('enable-preview-package'));

test('package preview serves private assets without changing guest assets', async ({ page, browser, baseURL }, info) => {
  await login(page);
  await page.goto('/admin/themes');
  await themeRow(page, 'acme/theme-evidence').getByRole('button', { name: 'Preview', exact: true }).click();
  const preview = await page.request.get('/theme/preview.css');
  expect(preview.status()).toBe(200);
  expect(preview.headers()['cache-control']).toContain('private');
  const assetPath = (await preview.text()).match(/\/theme\/asset\/[a-f0-9]{64}/)?.[0];
  expect(assetPath).toBeTruthy();
  const asset = await page.request.get(assetPath!);
  expect(asset.status()).toBe(200);
  expect(asset.headers()['cache-control']).toContain('private');
  expect(asset.headers()['cache-control']).toContain('no-store');
  const guest = await browser.newContext({ baseURL });
  try {
    expect((await guest.request.get(assetPath!)).status()).toBe(404);
    expect(await (await guest.request.get('/')).text()).not.toContain('/theme/preview.css');
    const anotherAdmin = await browser.newContext({ baseURL });
    try {
      const otherPage = await anotherAdmin.newPage();
      await login(otherPage);
      expect((await anotherAdmin.request.get(assetPath!)).status()).toBe(404);
    } finally { await anotherAdmin.close(); }
    await shot(page, info, 'private-package-preview');
    await activate(page, 'acme/theme-evidence');
    const liveBefore = fixture().theme.active_build_id;
    expect((await guest.request.get(assetPath!)).status()).toBe(200);
    await themeRow(page, 'acme/midnight-theme').getByRole('button', { name: 'Preview', exact: true }).click();
    expect(fixture().theme.active_build_id).toBe(liveBefore);
    const sharedAsset = await guest.request.get(assetPath!);
    expect(sharedAsset.status()).toBe(200);
    expect(sharedAsset.headers()['cache-control']).toContain('immutable');
    expect(await sharedAsset.body()).toEqual(await asset.body());
    await shot(page, info, 'shared-texture-preview-isolated');
  } finally { await guest.close(); }
});

test('ending or disabling a package preview removes access to its private assets', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/themes');
  await themeRow(page, 'acme/theme-evidence').getByRole('button', { name: 'Preview', exact: true }).click();
  await expect(page.locator('link[href*="/theme/preview.css"]')).toHaveCount(1);
  const css = await (await page.request.get('/theme/preview.css')).text();
  const assetPath = css.match(/\/theme\/asset\/[a-f0-9]{64}/)?.[0];
  expect(assetPath).toBeTruthy();
  expect((await page.request.get(assetPath!)).status()).toBe(200);
  await page.getByRole('button', { name: 'End preview', exact: true }).click();
  await expect(page.locator('link[href*="/theme/preview.css"]')).toHaveCount(0);
  expect((await page.request.get(assetPath!)).status()).toBe(404);
  await themeRow(page, 'acme/theme-evidence').getByRole('button', { name: 'Preview', exact: true }).click();
  await expect(page.locator('link[href*="/theme/preview.css"]')).toHaveCount(1);
  fixture('disable-preview-package');
  expect((await page.request.get(assetPath!)).status()).toBe(404);
  await page.goto('/admin/themes');
  await expect(page.locator('.theme-preview-card')).toContainText('No session preview is active.');
  await shot(page, info, 'disabled-preview-cleared');
});

test('branding contrast requires explicit acknowledgement and records the override', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  const previousOverride = fixture().override_id;
  await page.locator('[name="site_name"]').fill('Readable community draft');
  await page.locator('[name="color_primary"]').fill('#ffffff');
  await page.locator('[name="color_accent"]').fill('#000000');
  expect(await saveBranding(page)).toBe(422);
  await expect(page.locator('[name="site_name"]')).toHaveValue('Readable community draft');
  await expect(page.locator('[name="color_primary"]')).toHaveValue('#ffffff');
  await expect(page.locator('[name="contrast_ack"]')).not.toBeChecked();
  expect(fixture().primary).toBe('');
  expect(fixture().site_name).toBe('RetroBoards');
  expect(fixture().override_id).toBe(previousOverride);
  await noOverflow(page);
  await shot(page, info, 'contrast-warning-before-save');
  await page.locator('[name="contrast_ack"]').check();
  await page.locator('[name="color_primary"]').fill('#eeeeee');
  await expect(page.locator('[name="contrast_ack"]')).not.toBeChecked();
  await page.locator('[name="color_primary"]').fill('#ffffff');
  await expect(page.locator('[name="contrast_ack"]')).toBeDisabled();
  expect(await saveBranding(page)).toBe(422);
  await page.locator('[name="contrast_ack"]').check();
  expect(await saveBranding(page)).toBe(303);
  expect(fixture().primary).toBe('#ffffff');
  expect(fixture().override_id).not.toBe(previousOverride);
  expect(fixture().override.color_primary).toBe('#ffffff');
  expect(fixture().override.warnings.length).toBeGreaterThan(0);
  expect((await renderedContrast(page.locator('.admin-bar .bell-count'))).ratio).toBeGreaterThanOrEqual(4.5);
});

test('Retro keeps guest login readable in light dark and system-dark modes', async ({ page, browser, baseURL }, info) => {
  await login(page);
  for (const mode of ['light', 'dark', 'system']) {
    await page.goto('/admin/branding');
    await page.locator('[name="theme_preset"]').selectOption('retro');
    await page.locator('[name="theme_default"]').selectOption(mode);
    expect(await saveBranding(page)).toBe(303);
    const guest = await browser.newContext({ baseURL, viewport: info.project.use.viewport, colorScheme: mode === 'system' ? 'dark' : 'light' });
    try {
      const loginPage = await guest.newPage();
      await loginPage.goto('/login');
      await loginPage.locator('[name="email"]').fill('member@retro.test');
      expect((await renderedContrast(loginPage.locator('[name="email"]'))).ratio).toBeGreaterThanOrEqual(4.5);
      expect((await renderedContrast(loginPage.getByRole('heading', { name: 'Log in', exact: true }))).ratio).toBeGreaterThanOrEqual(3);
      expect((await renderedContrast(loginPage.getByRole('link', { name: 'Forgot your password?' }))).ratio).toBeGreaterThanOrEqual(4.5);
      await noOverflow(loginPage);
      await shot(loginPage, info, `retro-${mode}-login`);
    } finally { await guest.close(); }
  }
});

test('branding preview changes the candidate palette without changing the admin page', async ({ page, browser, baseURL }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  const bodyBefore = await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor);
  await page.locator('[name="theme_default"]').selectOption('light');
  const light = await page.locator('.brand-preview-body').evaluate(element => getComputedStyle(element).backgroundColor);
  await page.locator('[name="theme_default"]').selectOption('dark');
  await expect.poll(() => page.locator('.brand-preview-body').evaluate(element => getComputedStyle(element).backgroundColor)).not.toBe(light);
  const classicDark = await page.locator('.brand-preview-body').evaluate(element => getComputedStyle(element).backgroundColor);
  await page.locator('[name="theme_preset"]').selectOption('retro');
  await expect.poll(() => page.locator('.brand-preview-body').evaluate(element => getComputedStyle(element).backgroundColor)).not.toBe(classicDark);
  expect(await page.locator('body').evaluate(element => getComputedStyle(element).backgroundColor)).toBe(bodyBefore);
  // Public discussion titles use content ink at rest and action ink on hover.
  await page.locator('.brand-preview-body .thread-title').hover();
  const candidate = await renderedContrast(page.locator('.brand-preview-body .thread-title'));
  expect(candidate.ratio).toBeGreaterThanOrEqual(4.5);
  await shot(page, info, 'retro-dark-live-preview');
  expect(await saveBranding(page)).toBe(303);
  const guest = await browser.newContext({ baseURL });
  try {
    const gp = await guest.newPage();
    await gp.goto('/login');
    const actualSurface = await gp.locator('.auth-card').evaluate(element => getComputedStyle(element).backgroundColor);
    expect(actualSurface).toBe(candidate.background);
    expect((await renderedContrast(gp.getByRole('link', { name: 'Forgot your password?' }))).foreground).toBe(candidate.foreground);
  } finally { await guest.close(); }
});

test('branding preview shows unsaved logo variants and restores the saved fallback', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  const logoBytes = async (color: string) => Buffer.from((await page.evaluate(fill => {
    const canvas = document.createElement('canvas');
    canvas.width = 24;
    canvas.height = 24;
    const context = canvas.getContext('2d')!;
    context.fillStyle = fill;
    context.fillRect(0, 0, 24, 24);
    return canvas.toDataURL('image/png');
  }, color)).split(',')[1], 'base64');
  const preview = page.locator('[data-brand-preview-logo]');
  await page.locator('[name="theme_default"]').selectOption('light');
  await page.locator('[name="logo"]').setInputFiles({ name: 'base-logo.png', mimeType: 'image/png', buffer: await logoBytes('#256346') });
  await expect(preview).toBeVisible();
  await expect(preview).toHaveAttribute('src', /^data:image\/png/);
  await expect.poll(() => preview.evaluate(image => (image as HTMLImageElement).naturalWidth)).toBe(24);
  expect(await saveBranding(page)).toBe(303);
  await expect(preview).toHaveAttribute('src', /^\/media\//);
  const savedSource = await preview.getAttribute('src');
  await page.locator('[name="logo"]').setInputFiles({ name: 'unsaved-base-logo.png', mimeType: 'image/png', buffer: await logoBytes('#5743a2') });
  await expect(preview).toHaveAttribute('src', /^data:image\/png/);
  const baseSource = await preview.getAttribute('src');
  await page.locator('[name="logo_dark"]').setInputFiles({ name: 'dark-logo.png', mimeType: 'image/png', buffer: await logoBytes('#d4b066') });
  await page.locator('[name="theme_default"]').selectOption('dark');
  await expect(preview).toHaveAttribute('src', /^data:image\/png/);
  await expect.poll(() => preview.getAttribute('src')).not.toBe(baseSource);
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.locator('[name="theme_default"]').selectOption('system');
  await expect(preview).toHaveAttribute('src', baseSource!);
  await shot(page, info, 'unsaved-logo-preview');
  await page.locator('[name="logo"]').setInputFiles([]);
  await expect(preview).toHaveAttribute('src', savedSource!);
  expect(fixture().preset).toBe('classic');
});

test('branding removes individual marks while preserving other assets and settings', async ({ page }, info) => {
  await login(page);
  const saved = await uploadBrandingMarks(page);
  const preview = page.locator('[data-brand-preview-logo]');
  await expect(preview).toHaveAttribute('src', saved.assets.logo_dark);
  await page.locator('[name="remove_logo_dark"]').check();
  await expect(preview).toHaveAttribute('src', saved.assets.logo);
  await page.locator('[name="remove_logo"]').check();
  await expect(preview).toBeHidden();
  await page.locator('[name="remove_logo_dark"]').uncheck();
  await expect(preview).toHaveAttribute('src', saved.assets.logo_dark);
  await page.locator('[name="remove_logo"]').uncheck();
  expect(fixture().assets).toEqual(saved.assets);

  const expectedAssets = { ...saved.assets };
  for (const field of ['logo_light', 'logo_dark', 'favicon', 'logo'] as const) {
    await page.locator(`[name="remove_${field}"]`).check();
    if (field === 'logo_dark') {
      await noOverflow(page);
      await shot(page, info, 'pending-dark-logo-removal');
    }
    expect(await saveBranding(page)).toBe(303);
    expectedAssets[field] = '';
    const current = fixture();
    expect(current.assets).toEqual(expectedAssets);
    expect(brandSettings(current)).toEqual(brandSettings(saved));
    expect(current.branding_update.removed_assets).toEqual([field]);
    await expect(page.locator('link[rel="icon"]')).toHaveAttribute('href', expectedAssets.favicon || 'data:,');
  }
  await expect(preview).toBeHidden();
  await noOverflow(page);
  await shot(page, info, 'individual-marks-removed');
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('safe mode retains rollback and a later rollback clears the session preview', async ({ page }, info) => {
    await login(page);
    await page.goto('/admin/themes');
    await activate(page, 'acme/theme-evidence');
    const firstBuild = fixture().theme.active_build_id;
    await activate(page, 'acme/theme-evidence-alt');
    await page.getByRole('button', { name: 'Enter safe mode', exact: true }).click();
    await page.goto('/admin/themes');
    await expect(page.getByRole('button', { name: 'Roll back to last-known-good', exact: true })).toBeVisible();
    expect(fixture().safe_mode).toBe(true);
    await shot(page, info, 'safe-mode-rollback-available');
    await page.locator('form[action="/admin/themes/rollback"] [name="current_password"]').fill('password123');
    await page.getByRole('button', { name: 'Roll back to last-known-good', exact: true }).click();
    expect(fixture().theme.active_build_id).toBe(firstBuild);
    expect(fixture().safe_mode).toBe(true);
    await expect(page.locator('link[href*="/theme/"]')).toHaveCount(0);
    await page.goto('/admin/themes/safe-mode');
    await page.locator('[name="current_password"]').fill('password123');
    await page.getByRole('button', { name: 'Exit safe mode', exact: true }).click();
    await page.goto('/admin/themes');
    await themeRow(page, 'acme/theme-evidence').getByRole('button', { name: 'Preview', exact: true }).click();
    await expect(page.locator('link[href*="/theme/preview.css"]')).toHaveCount(1);
    await page.locator('form[action="/admin/themes/rollback"] [name="current_password"]').fill('password123');
    await page.getByRole('button', { name: 'Roll back to last-known-good', exact: true }).click();
    await expect(page.locator('link[href*="/theme/preview.css"]')).toHaveCount(0);
    await expect(page.locator('.theme-preview-card')).toContainText('No session preview is active.');
    await shot(page, info, 'rollback-clears-preview-no-js');
  });

  test('branding preserves invalid input guards reset and confirms contrast without JavaScript', async ({ page }, info) => {
    await login(page);
    await page.goto('/admin/branding');
    await page.locator('[name="site_name"]').fill('No-script branding draft');
    await page.locator('[name="color_primary"]').fill('broken');
    expect(await saveBranding(page)).toBe(422);
    await expect(page.locator('[name="site_name"]')).toHaveValue('No-script branding draft');
    await expect(page.locator('[name="color_primary"]')).toHaveValue('broken');
    await page.getByRole('button', { name: 'Reset branding', exact: true }).click();
    expect(await page.locator('[name="reset_confirm"]').evaluate((input: HTMLInputElement) => input.validity.valid)).toBe(false);
    await expect(page.locator('[name="site_name"]')).toHaveValue('No-script branding draft');
    expect(fixture().site_name).toBe('RetroBoards');
    await page.locator('[name="color_primary"]').fill('#ffffff');
    expect(await saveBranding(page)).toBe(422);
    await page.locator('[name="contrast_ack"]').check();
    expect(await saveBranding(page)).toBe(303);
    expect(fixture().site_name).toBe('No-script branding draft');
    await page.locator('[name="reset_confirm"]').fill('RESET');
    expect(await post(page, page.getByRole('button', { name: 'Reset branding', exact: true }), '/admin/branding')).toBe(303);
    expect(fixture().primary).toBe('');
    await noOverflow(page);
    await shot(page, info, 'branding-no-js-reset');
    fixture('flags-off');
    expect((await page.request.get('/admin/branding')).status()).toBe(404);
    expect((await page.request.get('/admin/themes')).status()).toBe(404);
    expect((await page.request.get('/admin/themes/safe-mode')).status()).toBe(200);
  });

  test('branding removal survives upload conflicts and contrast review without JavaScript', async ({ page }, info) => {
    await login(page);
    const saved = await uploadBrandingMarks(page);
    await page.locator('[name="remove_logo"]').check();
    await page.locator('[name="logo"]').setInputFiles({ name: 'replacement.png', mimeType: 'image/png', buffer: brandAssetPng });
    expect(await saveBranding(page)).toBe(422);
    await expect(page.locator('[name="remove_logo"]')).toBeChecked();
    await expect(page.locator('.field-error').filter({ hasText: /Choose either removing/ })).toBeVisible();
    expect(fixture().assets).toEqual(saved.assets);
    expect(brandSettings(fixture())).toEqual(brandSettings(saved));

    // The browser clears upload inputs on a validation rerender. The retained
    // removal is still a draft until the independently reviewed colour saves.
    await expect(page.locator('[name="logo"]')).toHaveValue('');
    await page.locator('[name="color_primary"]').fill('#ffffff');
    expect(await saveBranding(page)).toBe(422);
    await expect(page.locator('[name="remove_logo"]')).toBeChecked();
    await expect(page.locator('[name="color_primary"]')).toHaveValue('#ffffff');
    expect(fixture().assets).toEqual(saved.assets);
    await noOverflow(page);
    await shot(page, info, 'pending-removal-contrast-review-no-js');
    await page.locator('[name="contrast_ack"]').check();
    expect(await saveBranding(page)).toBe(303);
    const current = fixture();
    expect(current.assets).toEqual({ ...saved.assets, logo: '' });
    expect(brandSettings(current)).toEqual({ ...brandSettings(saved), primary: '#ffffff' });
    expect(current.branding_update.removed_assets).toEqual(['logo']);
    await shot(page, info, 'base-logo-removed-no-js');
  });
});
