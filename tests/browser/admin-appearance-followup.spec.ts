import { test, expect, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const engine = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
test.use({ browserName: engine, colorScheme: 'light' });
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC', 'base64');

function fixture(action = 'state') {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'theming-remediation-fixture.php'), action], {
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
async function save(page: Page) {
  const response = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/admin/branding');
  await page.getByRole('button', { name: 'Save branding', exact: true }).click();
  const status = (await response).status();
  await page.waitForLoadState('networkidle');
  return status;
}
async function shot(page: Page, info: TestInfo, name: string) {
  const out = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/admin-appearance-followup-2026-10-31', engine, info.project.name);
  mkdirSync(out, { recursive: true });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(out, `${name}.png`), fullPage: true, animations: 'disabled' });
}
async function noOverflow(page: Page) {
  await page.evaluate(() => document.fonts.ready);
  const sizes = await page.evaluate(() => ({ width: document.documentElement.clientWidth, actual: document.documentElement.scrollWidth }));
  expect(sizes.actual).toBeLessThanOrEqual(sizes.width);
}
async function fieldContrasts(page: Page) {
  return page.locator('.branding-field .input, .branding-reset-input, .branding-file-input').evaluateAll(elements => {
    const luminance = (color: string) => (color.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number).reduce((sum, value, index) => {
      const v = value / 255;
      return sum + (v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4) * [.2126, .7152, .0722][index];
    }, 0);
    const ratio = (a: string, b: string) => {
      const values = [luminance(a), luminance(b)].sort((x, y) => x - y);
      return (values[1] + .05) / (values[0] + .05);
    };
    return elements.map(element => {
      const style = getComputedStyle(element);
      let parent = element.parentElement;
      while (parent && ['transparent', 'rgba(0, 0, 0, 0)'].includes(getComputedStyle(parent).backgroundColor)) parent = parent.parentElement;
      return { id: element.id, border: style.borderColor, ratio: Math.min(
        ratio(style.borderColor, style.backgroundColor), ratio(style.borderColor, getComputedStyle(parent!).backgroundColor),
      ) };
    });
  });
}

test.beforeEach(() => fixture('reset'));

test('PHP-rejected branding files preserve the draft and saved marks', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="logo"]').setInputFiles({ name: 'saved-logo.png', mimeType: 'image/png', buffer: png });
  expect(await save(page)).toBe(303);
  const original = fixture();
  await page.locator('[name="site_name"]').fill('Draft with an oversized logo');
  await page.locator('[name="logo"]').setInputFiles({ name: 'too-large.png', mimeType: 'image/png', buffer: Buffer.concat([png, Buffer.alloc(9 * 1024 * 1024)]) });
  expect(await save(page)).toBe(422);
  await expect(page.locator('[name="site_name"]')).toHaveValue('Draft with an oversized logo');
  await expect(page.locator('[name="logo"]')).toHaveAttribute('aria-invalid', 'true');
  await expect(page.locator('#err-logo')).toContainText('server upload limit');
  await expect(page.getByText('Your selected logo or favicon files were not uploaded.', { exact: false })).toBeVisible();
  expect(fixture().site_name).toBe(original.site_name);
  expect(fixture().assets).toEqual(original.assets);
  await shot(page, info, 'php-upload-error-preserves-draft');
});

test('branding field boundaries stay visible in both palettes and modes', async ({ page }, info) => {
  await login(page);
  for (const preset of ['classic', 'retro']) {
    await page.goto('/admin/branding');
    await page.locator('[name="theme_preset"]').selectOption(preset);
    expect(await save(page)).toBe(303);
    for (const colorScheme of ['light', 'dark'] as const) {
      await page.emulateMedia({ colorScheme });
      for (const field of await fieldContrasts(page)) expect(field.ratio, `${preset}/${colorScheme} ${field.id}`).toBeGreaterThanOrEqual(3);
      await noOverflow(page);
      await shot(page, info, `field-contrast-${preset}-${colorScheme}`);
    }
  }
  await page.emulateMedia({ forcedColors: 'active' });
  await page.locator('[name="color_primary"]').focus();
  await expect(page.locator('[name="color_primary"]')).toBeFocused();
  await shot(page, info, 'branding-forced-colours-focus');
});

test('package details and guarded actions fit together on narrow screens', async ({ page }, info) => {
  await login(page);
  fixture('long-theme-name');
  await page.goto('/admin/themes');
  const row = page.locator('tbody tr').filter({ has: page.locator('code', { hasText: /^acme\/theme-evidence$/ }) });
  await row.getByRole('button', { name: 'Preview', exact: true }).click();
  for (const width of info.project.name === 'mobile' ? [390, 320] : [1280, 900]) {
    await page.setViewportSize({ width, height: 844 });
    await noOverflow(page);
    const scroller = await page.locator('.theme-packages-card .table-scroll').evaluate(element => ({ width: element.clientWidth, scroll: element.scrollWidth }));
    expect(scroller.scroll).toBeLessThanOrEqual(scroller.width);
    const actions = row.locator('.theme-row-actions');
    for (const control of [actions.getByRole('button', { name: 'Preview', exact: true }), actions.locator('[name="current_password"]'), actions.getByRole('button', { name: 'Activate', exact: true })]) {
      const box = await control.boundingBox();
      expect(box!.x).toBeGreaterThanOrEqual(0);
      expect(box!.x + box!.width).toBeLessThanOrEqual(width);
    }
    await shot(page, info, `package-actions-${width}`);
  }
  if (info.project.name === 'mobile') {
    await page.evaluate(() => document.documentElement.style.fontSize = '32px');
    await noOverflow(page);
    await shot(page, info, 'package-actions-enlarged-text');
  }
  // The signed texture fixture supplies deliberately incomplete colour tokens.
  // Measure the operator UI after ending that preview; built digests remain.
  await page.getByRole('button', { name: 'End preview', exact: true }).click();
  const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  expect(axe.violations).toEqual([]);
});

test('pending favicon and inactive logo choices are visible and clearable', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="theme_default"]').selectOption('light');
  await expect(page.locator('[data-brand-asset="favicon"] [data-brand-file-clear]')).toBeHidden();
  for (const field of ['favicon', 'logo_dark']) {
    const asset = page.locator(`[data-brand-asset="${field}"]`);
    await asset.locator('input[type="file"]').setInputFiles({ name: 'Evening & "council" mark.png', mimeType: 'image/png', buffer: png });
    await expect(asset.locator('[data-brand-asset-status]')).toContainText('Selected');
    await expect(asset.locator('[data-brand-asset-image]')).toBeVisible();
    await expect(asset.locator('[data-brand-asset-image]')).toHaveAttribute('src', /^data:image\/png/);
    expect(await asset.locator('input[type="file"]').evaluate(element => getComputedStyle(element).opacity)).toBe('1');
  }
  await shot(page, info, 'pending-favicon-and-dark-logo');
  await page.locator('[data-brand-asset="favicon"] [data-brand-file-clear]').click();
  await expect(page.locator('[name="favicon"]')).toHaveValue('');
  await expect(page.locator('[data-brand-asset="favicon"] [data-brand-file-clear]')).toBeHidden();
  await expect(page.locator('[data-brand-asset="favicon"] [data-brand-asset-status]')).toHaveText('No image saved.');
  expect(await save(page)).toBe(303);
  expect(fixture().assets.favicon).toBe('');
  expect(fixture().assets.logo_dark).toMatch(/^\/media\//);
  await expect(page.locator('[data-brand-asset="logo_dark"] [data-brand-asset-image]')).toHaveAttribute('src', fixture().assets.logo_dark);
  await shot(page, info, 'saved-inactive-logo-thumbnail');
});

test('branding reset and package safe mode describe their retained state', async ({ page, browser, baseURL }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="site_name"]').fill('Scoped recovery community');
  await page.locator('[name="theme_preset"]').selectOption('retro');
  expect(await save(page)).toBe(303);
  await page.goto('/admin/themes');
  const row = page.locator('tbody tr').filter({ has: page.locator('code', { hasText: /^acme\/theme-evidence$/ }) });
  await row.locator('[name="current_password"]').fill('password123');
  await row.getByRole('button', { name: 'Activate', exact: true }).click();
  const activeId = fixture().theme.active_build_id;
  expect(activeId).toBeTruthy();
  await page.goto('/admin/branding');
  await expect(page.locator('.branding-reset-card')).toContainText('Keeps the community name and active package theme.');
  await page.locator('[name="reset_confirm"]').fill('RESET');
  await page.getByRole('button', { name: 'Reset branding', exact: true }).click();
  await expect(page.locator('.branding-reset-status')).toContainText('community name and package theme were kept');
  expect(fixture().theme.active_build_id).toBe(activeId);
  expect(fixture().site_name).toBe('Scoped recovery community');
  await page.locator('[name="theme_preset"]').selectOption('retro');
  expect(await save(page)).toBe(303);
  await page.getByRole('link', { name: 'theme safe mode', exact: true }).click();
  await page.getByRole('button', { name: 'Enter safe mode', exact: true }).click();
  await expect(page.locator('.safe-mode-status')).toContainText('saved branding still applies');
  expect(fixture().preset).toBe('retro');
  expect(fixture().theme.active_build_id).toBe(activeId);
  await expect(page.getByRole('link', { name: 'review the branding reset' })).toHaveAttribute('href', '/admin/branding#branding-reset');
  const guest = await browser.newContext({ baseURL, viewport: info.project.use.viewport, colorScheme: 'light' });
  try {
    const gp = await guest.newPage();
    await gp.goto('/login');
    expect(await gp.locator('link[href^="/theme/"]').count()).toBe(0);
    await expect(gp.locator('.auth-card')).toHaveCSS('background-color', 'rgb(255, 247, 220)');
  } finally { await guest.close(); }
  await shot(page, info, 'safe-mode-explains-retained-branding');
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test('native file choices and upload errors remain usable', async ({ page }, info) => {
    await login(page);
    await page.goto('/admin/branding');
    await expect(page.locator('#branding-upload-help')).toContainText('5 MiB');
    const input = page.locator('[name="favicon"]');
    await expect(input).toHaveAttribute('accept', 'image/jpeg,image/png,image/gif,image/webp');
    expect(await input.evaluate(element => getComputedStyle(element).opacity)).toBe('1');
    await input.setInputFiles({ name: 'favicon.png', mimeType: 'image/png', buffer: png });
    await shot(page, info, 'native-filename-no-js');
    expect(await save(page)).toBe(303);
    const saved = fixture().assets;
    await page.locator('[name="site_name"]').fill('No-script transport draft');
    await page.locator('[name="logo_dark"]').setInputFiles({ name: 'large.png', mimeType: 'image/png', buffer: Buffer.alloc(9 * 1024 * 1024) });
    expect(await save(page)).toBe(422);
    await expect(page.locator('[name="site_name"]')).toHaveValue('No-script transport draft');
    expect(fixture().assets).toEqual(saved);
    await noOverflow(page);
    await shot(page, info, 'upload-error-no-js');
    await page.locator('[name="logo_dark"]').setInputFiles({ name: 'request-too-large.png', mimeType: 'image/png', buffer: Buffer.alloc(11 * 1024 * 1024) });
    expect(await save(page)).toBe(413);
    await expect(page.getByText('Nothing was saved', { exact: false })).toBeVisible();
    expect(fixture().assets).toEqual(saved);
    await shot(page, info, 'discarded-request-no-js');
    await page.goto('/admin/themes');
    await noOverflow(page);
    await shot(page, info, 'package-actions-no-js');
  });
});
