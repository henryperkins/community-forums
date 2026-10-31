import { test, expect, type Browser, type BrowserContextOptions, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

/**
 * ADR 0054 increment 2b: private branding drafts. A draft and its images stay
 * private, its author previews it across the community, and it reaches
 * visitors only when explicitly published. E2E_LAYOUT_BROWSER=webkit for WebKit.
 */
const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/branding-drafts-2026-10-31');
const browserName = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
const baseURL = process.env.E2E_BASE_URL ?? `http://localhost:${process.env.E2E_PORT ?? 8011}`;
test.use({ browserName, colorScheme: 'light' });

type State = Record<string, string>;
function fixture(...args: string[]): State {
  return JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'branding-identity-fixture.php'), ...args], {
    cwd: root, env: process.env, encoding: 'utf8',
  }));
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
async function submit(page: Page, button: Locator, pathname: string) {
  const posted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === pathname);
  const committed = page.waitForEvent('framenavigated', { predicate: frame => frame === page.mainFrame() });
  await button.click();
  const response = await posted;
  await committed;
  await page.waitForLoadState('load');
  return response.status();
}
const saveDraft = (page: Page) => submit(page, page.getByRole('button', { name: 'Save draft', exact: true }), '/admin/branding');
async function publish(page: Page) {
  let status = await submit(page, page.getByRole('button', { name: 'Publish branding', exact: true }), '/admin/branding');
  if (status === 422 && await page.locator('.branding-contrast-warning').count() > 0) {
    await page.locator('[name="contrast_ack"]').check();
    status = await submit(page, page.getByRole('button', { name: 'Publish branding', exact: true }), '/admin/branding');
  }
  return status;
}
const logoPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAKAAAAAoCAIAAAD2TmbPAAAACXBIWXMAAA7EAAAOxAGVKw4bAAABZUlEQVR4nO2cMQ6DMAxFQ8WROAcTx2TiHByFmYGpQ6rICsENNJT467+pajAyeTQxWGqzrYsjuLTOuW7on06D3MI8Tm349GwqpDj+d/t6Og1yLxQMDgWDQ8HgUDA4FAwOBYNDweBQMDgUDA4Fg0PB4LQ5B+3bTaE5kexERa0Lf4z88qh/NY+TMvQ1Q+UYedpkeslRJcoKWYI9Umo39PKCo0mRo2GOohB3MGVRYJFpladKppe8NCXKEFeWaHn9OmGO8kOKEymUmezvoSjVZJQtbtyDYebINCeWaAVlG8uMsrj6maCM4MApT7VJDTdcbYn9QhnBSiViaE1Wani7lNyDk7usL1tC8UL+zBXBygNMhZVUlJJMXslWibLFiSW6/proqNbza29yi42GMqMM0WzrYvQRnuh4rXwXDQ4Fg0PB4FAwOBQMDgWDQ8HgUDA4FAwOBYNDweBQMDifblJVDT5SkIZ/o4TNGwhGzpw9xcsOAAAAAElFTkSuQmCC', 'base64');
async function contrastOf(locator: Locator) {
  return locator.evaluate(element => {
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

test('a draft stays private while its author previews it, then publishes explicitly', async ({ page, browser }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="site_name"]').fill('Draft Harbour');
  await page.locator('[name="site_tagline"]').fill('Only the author sees this yet');
  await page.locator('[name="directory_heading"]').fill('Welcome to the drafted harbour');
  await page.locator('[name="logo"]').setInputFiles({ name: 'draft-logo.png', mimeType: 'image/png', buffer: logoPng });
  expect(await saveDraft(page)).toBe(303);
  await expect(page.locator('.branding-draft-panel')).toContainText('Unpublished draft');
  await expect(page.locator('.branding-save-row [role="status"]')).toHaveText('Draft saved. Only you can see it; the published branding is unchanged.');
  await expect(page.locator('[name="site_name"]')).toHaveValue('Draft Harbour');
  const draftLogo = await page.locator('[data-brand-asset="logo"]').getAttribute('data-brand-saved-asset');
  expect(draftLogo).toMatch(/^\/media\/\d+$/);
  expect(fixture().site_name).toBe('RetroBoards');
  await noOverflow(page);
  await shot(page, info, 'draft-saved');

  const guest = await context(browser, info);
  try {
    const gp = await guest.newPage();
    await gp.goto('/');
    await expect(gp.getByRole('heading', { level: 1, name: 'Every board in the valley' })).toBeVisible();
    await expect(gp.locator('.branding-draft-bar')).toHaveCount(0);
    expect((await gp.request.get(draftLogo!)).status()).toBe(404);
    const draftCss = await gp.request.get('/brand.css?v=probe&draft=1');
    expect(draftCss.headers()['cache-control']).toBe('private, no-store');
    await shot(gp, info, 'guest-sees-published');
  } finally { await guest.close(); }

  expect(await submit(page, page.getByRole('button', { name: 'Preview the community with this draft' }), '/admin/branding/preview')).toBe(303);
  await expect(page).toHaveURL(/\/$/);
  const bar = page.locator('.branding-draft-bar');
  await expect(bar).toContainText('Previewing your branding draft.');
  await expect(page.getByRole('heading', { level: 1, name: 'Welcome to the drafted harbour' })).toBeVisible();
  await expect(page.locator('.brand-logo')).toHaveAttribute('src', draftLogo!);
  await noOverflow(page);
  await shot(page, info, 'draft-preview-home');
  await bar.getByRole('link', { name: 'Sign-in page' }).click();
  await expect(page.locator('.auth-brand-logo')).toHaveAttribute('src', draftLogo!);
  await expect(page.locator('.auth-tagline')).toHaveText('Only the author sees this yet');
  await shot(page, info, 'draft-preview-sign-in');
  for (const target of [bar.getByRole('link', { name: 'Review and publish' }), bar.getByRole('button', { name: 'Exit preview' })]) {
    expect(await contrastOf(target)).toBeGreaterThanOrEqual(4.5);
  }

  await bar.getByRole('link', { name: 'Review and publish' }).click();
  await expect(page.locator('[name="site_name"]')).toHaveValue('Draft Harbour');
  expect(await publish(page)).toBe(303);
  await expect(page.locator('.branding-save-row [role="status"]')).toHaveText('Published. Branding appears on the next page load.');
  await expect(page.locator('.branding-draft-panel')).toHaveCount(0);
  await expect(page.locator('.branding-draft-bar')).toHaveCount(0);
  const live = fixture();
  expect(live.site_name).toBe('Draft Harbour');
  expect(live.brand_logo_path).toBe(draftLogo);

  const after = await context(browser, info);
  try {
    const gp = await after.newPage();
    await gp.goto('/login');
    await expect(gp.locator('.auth-brand-logo')).toHaveAttribute('src', draftLogo!);
    expect((await gp.request.get(draftLogo!)).status()).toBe(200);
    await shot(gp, info, 'published-sign-in');
  } finally { await after.close(); }
});

test('discarding a draft without JavaScript keeps the published branding', async ({ browser }, info) => {
  const admin = await context(browser, info, { javaScriptEnabled: false });
  try {
    const ap = await admin.newPage();
    await login(ap);
    await ap.goto('/admin/branding');
    await ap.locator('[name="site_name"]').fill('A draft to throw away');
    expect(await saveDraft(ap)).toBe(303);
    const discard = ap.locator('.branding-draft-actions details');
    await discard.locator('summary').click();
    await expect(discard.locator('.dm-confirm-note')).toContainText('Deletes this unpublished draft');
    await noOverflow(ap);
    await shot(ap, info, 'discard-step-no-js');
    expect(await submit(ap, discard.getByRole('button', { name: 'Discard draft', exact: true }), '/admin/branding/draft/discard')).toBe(303);
    await expect(ap.locator('body')).toContainText('Draft discarded. The published branding is unchanged.');
    await expect(ap.locator('[name="site_name"]')).toHaveValue('RetroBoards');
    expect(fixture().site_name).toBe('RetroBoards');
  } finally { await admin.close(); }
});

test('the preview bar stays readable whatever the draft palette', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="color_primary"]').fill('#fdfcf8');
  await page.locator('[name="color_accent"]').fill('#fbf7ec');
  expect(await saveDraft(page)).toBe(303);
  expect(await submit(page, page.getByRole('button', { name: 'Preview the community with this draft' }), '/admin/branding/preview')).toBe(303);
  const bar = page.locator('.branding-draft-bar');
  expect(await contrastOf(bar.locator('.branding-draft-bar-text'))).toBeGreaterThanOrEqual(4.5);
  for (const target of [bar.getByRole('link', { name: 'Review and publish' }), bar.getByRole('link', { name: 'Sign-in page' }), bar.getByRole('button', { name: 'Exit preview' })]) {
    expect(await contrastOf(target)).toBeGreaterThanOrEqual(4.5);
  }
  await noOverflow(page);
  await shot(page, info, 'preview-bar-hard-palette');
  expect(await submit(page, bar.getByRole('button', { name: 'Exit preview' }), '/admin/branding/preview')).toBe(303);
  await expect(page.locator('.branding-draft-bar')).toHaveCount(0);
});
