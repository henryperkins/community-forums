import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const OUT = path.resolve(__dirname, '../..', process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/forum-theme');
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
test.beforeEach(({ baseURL }) => {
  if (!baseURL || !['localhost', '127.0.0.1', '[::1]'].includes(new URL(baseURL).hostname)) {
    throw new Error('Forum theme evidence requires the disposable local browser fixture.');
  }
});

function contrastRatio(foreground: string, background: string): number {
  const luminance = (color: string) => {
    const values = color.match(/[\d.]+/g)!.slice(0, 3).map(value => {
      const channel = Number(value) / 255;
      return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
    });
    return .2126 * values[0] + .7152 * values[1] + .0722 * values[2];
  };
  const a = luminance(foreground), b = luminance(background);
  return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
}
function output(info: TestInfo, name: string): string {
  const directory = path.join(OUT, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  return path.join(directory, name);
}
async function visit(page: Page, route: string) {
  const response = await page.goto(route);
  expect(response?.status(), route).toBeLessThan(400);
  await page.evaluate(() => document.fonts.ready);
}
async function login(page: Page, email = 'admin@retro.test') {
  await visit(page, '/login');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}
async function inspect(page: Page, info: TestInfo, route: string, label: string) {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await visit(page, route);
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => { document.documentElement.dataset.theme = value; }, theme);
    const scan = await new AxeBuilder({ page }).withTags(tags).analyze();
    fs.writeFileSync(output(info, `${label}-${theme}-axe.json`), JSON.stringify({ violations: scan.violations, incomplete: scan.incomplete }, null, 2));
    expect.soft(scan.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) })), `${label} ${theme}`).toEqual([]);
    const overflow = await page.evaluate(() => ({ content: document.documentElement.scrollWidth, viewport: innerWidth }));
    expect.soft(overflow.content, `${label} ${theme} page reflow`).toBeLessThanOrEqual(overflow.viewport + 1);
    await page.screenshot({ path: output(info, `${label}-${theme}.png`), animations: 'disabled' });
  }
}

test('Forum guest routes have readable light/dark states without legacy fonts', async ({ page }, info) => {
  test.setTimeout(180_000);
  const fonts: string[] = [];
  const errors: string[] = [];
  page.on('request', request => { if (request.resourceType() === 'font') fonts.push(request.url()); });
  page.on('pageerror', error => errors.push(error.message));
  for (const [route, label] of [['/', 'boards'], ['/c/general', 'board'], ['/t/1-welcome-to-retroboards', 'topic'], ['/login', 'login'], ['/register', 'register'], ['/search?q=welcome', 'search'], ['/u/alice', 'profile']]) {
    await inspect(page, info, route, label);
  }
  expect(fonts).toEqual([]);
  expect(errors).toEqual([]);
});

test('Forum member and operator surfaces retain readable states', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  for (const [route, label] of [['/inbox', 'inbox'], ['/messages', 'messages'], ['/settings/appearance', 'appearance'], ['/admin/branding', 'branding']]) {
    await inspect(page, info, route, label);
  }
});

test('Keyboard, narrow reflow, text spacing and system preferences remain usable', async ({ page }, info) => {
  test.setTimeout(60_000);
  await visit(page, '/');
  await page.keyboard.press('Tab');
  await expect(page.locator('.skip-link')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main')).toBeFocused();
  await page.setViewportSize({ width: 320, height: 800 });
  await visit(page, '/t/1-welcome-to-retroboards');
  await page.route('**/forum-test-spacing.css', route => route.fulfill({ contentType: 'text/css', body: '* { line-height:1.5 !important; letter-spacing:.12em !important; word-spacing:.16em !important; } p { margin-block-end:2em !important; }' }));
  // A same-origin test stylesheet respects the application's strict CSP.
  await page.evaluate(() => { const sheet = document.createElement('link'); sheet.rel = 'stylesheet'; sheet.href = '/forum-test-spacing.css'; document.head.append(sheet); });
  await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).letterSpacing)).not.toBe('normal');
  const size = await page.evaluate(() => ({ content: document.documentElement.scrollWidth, viewport: innerWidth }));
  expect(size.content).toBeLessThanOrEqual(size.viewport + 1);
  const post = page.locator('.formatted-content, .post-body').first();
  await expect(post).toBeVisible();
  await page.screenshot({ path: output(info, 'topic-320-text-spacing.png'), animations: 'disabled' });
  await page.setViewportSize({ width: 1280, height: 900 });
  await visit(page, '/');
  await page.route('**/forum-test-large-text.css', route => route.fulfill({ contentType: 'text/css', body: 'html { font-size:200% !important; }' }));
  await page.evaluate(() => { const sheet = document.createElement('link'); sheet.rel = 'stylesheet'; sheet.href = '/forum-test-large-text.css'; document.head.append(sheet); });
  await expect.poll(() => page.evaluate(() => getComputedStyle(document.documentElement).fontSize)).toBe('32px');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(1281);
  await page.screenshot({ path: output(info, 'boards-200-text.png'), animations: 'disabled' });
  await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
  await page.evaluate(() => { document.documentElement.dataset.theme = 'system'; });
  expect(await page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(17, 24, 39)');
  const transition = await page.locator('.forum-bar').evaluate(el => getComputedStyle(el).transitionDuration);
  expect(parseFloat(transition)).toBeLessThanOrEqual(.001);
  await page.emulateMedia({ forcedColors: 'active' });
  await page.locator('.skip-link').focus();
  expect(await page.locator('.skip-link').evaluate(el => getComputedStyle(el).outlineStyle)).not.toBe('none');
  await page.screenshot({ path: output(info, 'forced-colors-focus.png'), animations: 'disabled' });
});

test.describe('HTML appearance workflow', () => {
  test.use({ javaScriptEnabled: false });
  test('authentication and appearance persist without JavaScript', async ({ page }, info) => {
    await login(page);
    await visit(page, '/settings/appearance');
    await page.locator('input[name="theme"][value="dark"]').check();
    await page.getByRole('button', { name: 'Save appearance', exact: true }).click();
    await page.waitForURL(/\/settings\/appearance/);
    await visit(page, '/');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.getByRole('heading', { name: 'Community boards', exact: true })).toBeVisible();
    await page.screenshot({ path: output(info, 'no-js-dark.png'), animations: 'disabled' });
    await visit(page, '/settings/appearance');
    await page.locator('input[name="theme"][value="system"]').check();
    await page.getByRole('button', { name: 'Save appearance', exact: true }).click();
    await visit(page, '/messages/new?to=bob');
    await page.locator('.dm-compose textarea[name="body"]').fill('Local no-JavaScript posting fixture.');
    await page.locator('.dm-compose').getByRole('button', { name: 'Send', exact: true }).click();
    await page.waitForURL(/\/messages\/\d+/);
    await expect(page.getByText('Local no-JavaScript posting fixture.', { exact: true }).last()).toBeVisible();
  });
});

test('Reading, composing and forced-color switches use the shared accessible controls', async ({ page }, info) => {
  test.setTimeout(90_000);
  await login(page, 'alice@retro.test');
  await inspect(page, info, '/compose', 'compose');
  await page.getByLabel('Title', { exact: true }).fill('A readable forum topic');
  const composer = page.locator('textarea[name="body"]').first();
  await expect(composer).toBeVisible();
  await composer.fill('This is a local browser test draft. It is not submitted.');
  expect(await composer.evaluate(el => parseFloat(getComputedStyle(el).fontSize))).toBeGreaterThanOrEqual(16);
  const submit = page.getByRole('button', { name: 'Create topic', exact: true });
  const bounds = await submit.boundingBox();
  expect(bounds?.height).toBeGreaterThanOrEqual(44);
  // Create a conversation only in the disposable local test database.
  await visit(page, '/messages/new?to=bob');
  await page.locator('.dm-compose textarea[name="body"]').fill('A local accessibility fixture with a [readable link](https://example.com), ||a readable spoiler||, and a long URL: https://example.com/' + 'reading'.repeat(24));
  await page.locator('.dm-compose').getByRole('button', { name: 'Send', exact: true }).click();
  await page.waitForURL(/\/messages\/\d+/);
  await inspect(page, info, new URL(page.url()).pathname, 'conversation');
  const body = page.locator('.dm-body.formatted-content').first();
  await expect(body).toBeVisible();
  expect(await body.evaluate(el => parseFloat(getComputedStyle(el).fontSize))).toBe(18);
  const spoiler = page.locator('.dm-body .spoiler').last();
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => { document.documentElement.dataset.theme = value; }, theme);
    await spoiler.focus();
    const colors = await spoiler.evaluate(el => ({ foreground: getComputedStyle(el).color, background: getComputedStyle(el).backgroundColor }));
    expect(contrastRatio(colors.foreground, colors.background), `${theme} revealed spoiler text`).toBeGreaterThanOrEqual(4.5);
    await page.locator('[data-dm-scroll]').evaluate(el => { el.scrollTop = 0; });
    for (const selector of ['.dm-day', '.dm-name', '.dm-gtime']) {
      const metadata = page.locator(selector).first();
      await expect(metadata).toBeVisible();
      expect(await metadata.evaluate(el => parseFloat(getComputedStyle(el).fontSize)), selector).toBeGreaterThanOrEqual(14);
    }
    await page.screenshot({ path: output(info, `conversation-opening-${theme}.png`), animations: 'disabled' });
  }
  await visit(page, '/settings/appearance');
  await page.emulateMedia({ forcedColors: 'active' });
  const motion = page.locator('input[name="reduced_motion"]');
  await motion.check();
  await expect(motion).toBeChecked();
  expect(await motion.evaluate(el => getComputedStyle(el).appearance)).toBe('auto');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(motion).toBeFocused();
  expect(await motion.evaluate(el => getComputedStyle(el).outlineStyle)).not.toBe('none');
  await page.screenshot({ path: output(info, 'forced-colors-switch.png'), animations: 'disabled' });
});
