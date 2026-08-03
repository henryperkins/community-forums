import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceRoot = path.join(repoRoot, 'docs', 'evidence', 'imladris-profile-production');
const sourceFile = path.join(
  repoRoot,
  'docs',
  'design-system',
  'imladris',
  'templates',
  'user-profile',
  'UserProfile.dc.html',
);
const credentials = {
  member: ['bob@retro.test', 'password123'],
  self: ['galadriel@retro.test', 'password123'],
  moderator: ['alice@retro.test', 'password123'],
} as const;

type ProjectName = 'desktop' | 'mobile';
type Theme = 'light' | 'dark';

function contrastRatio(first: string, second: string): number {
  const luminance = (color: string): number => {
    const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
    if (!channels || channels.length !== 3) throw new Error(`Unsupported CSS color: ${color}`);
    const [red, green, blue] = channels.map((channel) => {
      const normalized = channel / 255;
      return normalized <= 0.04045
        ? normalized / 12.92
        : ((normalized + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
  };
  const a = luminance(first);
  const b = luminance(second);
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

async function expectVisibleCodeBoundary(code: Locator): Promise<void> {
  const colors = await code.evaluate((element) => {
    const style = getComputedStyle(element);
    return { border: style.borderTopColor, fill: style.backgroundColor };
  });
  expect(
    contrastRatio(colors.border, colors.fill),
    `code border ${colors.border} should remain distinct from fill ${colors.fill}`,
  ).toBeGreaterThanOrEqual(1.9);
}

function viewport(info: TestInfo): { width: number; height: number } {
  return info.project.name === 'mobile' ? { width: 390, height: 844 } : { width: 1160, height: 900 };
}

function projectName(info: TestInfo): ProjectName {
  return info.project.name === 'mobile' ? 'mobile' : 'desktop';
}

function seedProfileFixture(): void {
  execFileSync('php', ['tests/browser/profile-surface-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
    stdio: 'inherit',
  });
}

function captureBrowserMessages(page: Page): string[] {
  const entries: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' || message.type() === 'warning') {
      entries.push(`${message.type()}: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => entries.push(`pageerror: ${error.message}`));
  page.on('response', (response) => {
    if (response.status() >= 400) entries.push(`http ${response.status()}: ${response.url()}`);
  });
  return entries;
}

async function visit(page: Page, route: string): Promise<void> {
  const response = await page.goto(route, { waitUntil: 'load' });
  expect(response, `no response for ${route}`).not.toBeNull();
  expect(response!.status(), `GET ${route} should not be an error`).toBeLessThan(400);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 800 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page, who: keyof typeof credentials): Promise<void> {
  await page.context().clearCookies();
  await visit(page, '/login');
  await page.getByLabel('Email').fill(credentials[who][0]);
  await page.getByLabel('Password').fill(credentials[who][1]);
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function settle(page: Page): Promise<void> {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())));
  });
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(dimensions.scrollWidth, JSON.stringify(dimensions)).toBeLessThanOrEqual(dimensions.clientWidth);
}

async function expectNoSeriousA11yViolations(page: Page): Promise<void> {
  const result = await new AxeBuilder({ page })
    .include('.profile')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const blocking = result.violations.filter((violation) =>
    violation.impact === 'serious' || violation.impact === 'critical',
  );
  expect(blocking, `${page.url()} serious/critical profile axe violations`).toEqual([]);
}

async function applyTheme(page: Page, theme: Theme): Promise<void> {
  await page.evaluate((nextTheme) => document.documentElement.setAttribute('data-theme', nextTheme), theme);
  await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
  await settle(page);
}

async function captureScreenshot(page: Page, output: string): Promise<void> {
  const options = { path: output, fullPage: true, animations: 'disabled' as const };

  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      await page.screenshot(options);
      return;
    } catch (error) {
      const isTransientWindowsOpenError = error instanceof Error
        && error.message.includes('UNKNOWN: unknown error, open');
      if (!isTransientWindowsOpenError || attempt === 2) throw error;
      await page.waitForTimeout(100 * (attempt + 1));
    }
  }
}

async function capture(
  page: Page,
  info: TestInfo,
  state: string,
  theme: Theme,
  checkA11y = true,
): Promise<string> {
  await applyTheme(page, theme);
  await expectNoHorizontalOverflow(page);
  if (checkA11y) await expectNoSeriousA11yViolations(page);
  const output = path.join(evidenceRoot, projectName(info), `${state}-${theme}.png`);
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await captureScreenshot(page, output);
  return output;
}

async function captureReference(page: Page, info: TestInfo, theme: Theme): Promise<string> {
  await page.setViewportSize(viewport(info));
  await page.goto(pathToFileURL(sourceFile).href, { waitUntil: 'load' });
  await expect(page.locator('[data-screen-label="User profile"]')).toBeVisible();
  await applyTheme(page, theme);
  const output = path.join(evidenceRoot, 'reference', projectName(info), `profile-${theme}.png`);
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await captureScreenshot(page, output);
  return output;
}

async function captureComparison(
  page: Page,
  info: TestInfo,
  theme: Theme,
  reference: string,
  production: string,
): Promise<void> {
  const size = viewport(info);
  const gap = 24;
  const referenceData = fs.readFileSync(reference).toString('base64');
  const productionData = fs.readFileSync(production).toString('base64');
  await page.goto('about:blank');
  await page.setViewportSize({ width: size.width * 2 + gap + 72, height: Math.max(900, size.height + 100) });
  await page.setContent(`<!doctype html>
    <html lang="en"><head><meta charset="utf-8"><title>Profile comparison</title>
      <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #d4d4d4; color: #171717; font: 600 15px/1.4 system-ui, sans-serif; }
        main { display: grid; grid-template-columns: repeat(2, ${size.width}px); gap: ${gap}px; padding: 24px; align-items: start; }
        figure { margin: 0; padding: 11px; background: #fff; border: 1px solid #aaa; }
        figcaption { margin-bottom: 9px; }
        img { display: block; width: 100%; height: auto; background: #fff; }
      </style></head><body><main>
        <figure><figcaption>Imladris source (${theme})</figcaption><img alt="Imladris source" src="data:image/png;base64,${referenceData}"></figure>
        <figure><figcaption>Production profile (${theme})</figcaption><img alt="Production profile" src="data:image/png;base64,${productionData}"></figure>
      </main></body></html>`, { waitUntil: 'load' });
  await page.locator('img').evaluateAll(async (images) => Promise.all(images.map((image) => image.decode())));
  const output = path.join(evidenceRoot, 'comparisons', `${projectName(info)}-${theme}.png`);
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await captureScreenshot(page, output);
}

test.beforeAll(() => seedProfileFixture());

test('profile states match the approved anatomy in light and dark themes', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));

  await visit(page, '/u/galadriel');
  await expect(page.locator('.profile-name')).toHaveText(/Galadriel/);
  await expect(page.locator('.profile-tabs .profile-tab')).toHaveCount(5);
  const bio = page.locator('.profile-bio .formatted-content');
  await expect(bio.getByRole('heading', { level: 2, name: 'Evidence discipline' })).toBeVisible();
  await expect(bio.locator('li')).toHaveCount(2);
  await expect(bio.locator('blockquote')).toContainText('keep the record whole');
  await expect(bio.locator('code').first()).toHaveText('repair');
  await expect(bio.locator('pre code')).toContainText('verified profile content');
  const proseType = await bio.evaluate((element) => {
    const style = getComputedStyle(element);
    return { fontSize: style.fontSize, lineHeight: style.lineHeight };
  });
  expect(proseType).toEqual({ fontSize: '17px', lineHeight: '28.9px' });
  const finalTabBox = await page.getByRole('link', { name: 'Connections', exact: true }).boundingBox();
  expect(finalTabBox, 'Connections tab should be rendered').not.toBeNull();
  expect(finalTabBox!.x + finalTabBox!.width, 'Connections tab should not be clipped initially')
    .toBeLessThanOrEqual(viewport(info).width);
  await expect(page.locator('.profile-rep-value .icon-commend-star')).toBeVisible();
  await capture(page, info, 'guest-populated', 'light');
  await expectVisibleCodeBoundary(bio.locator('code').first());
  await capture(page, info, 'guest-populated', 'dark');
  await expectVisibleCodeBoundary(bio.locator('code').first());
  await expect(page.locator('.profile-cover')).toHaveCSS('background-color', 'rgb(30, 39, 48)');

  await visit(page, '/u/private-seat');
  await expect(page.getByRole('heading', { name: 'This seat is kept private' })).toBeVisible();
  await expect(page.getByText('Request access')).toHaveCount(0);
  await expect(page.getByText('Message')).toHaveCount(0);
  await capture(page, info, 'guest-gated', 'light');
  await capture(page, info, 'guest-gated', 'dark');

  await visit(page, '/u/empty-seat');
  await expect(page.getByRole('heading', { name: 'No public activity yet' })).toBeVisible();
  await capture(page, info, 'guest-empty', 'light');
  await capture(page, info, 'guest-empty', 'dark');

  await login(page, 'member');
  await visit(page, '/u/galadriel');
  await expect(page.getByRole('link', { name: 'Message', exact: true })).toBeVisible();
  const memberLight = await capture(page, info, 'member-populated', 'light');
  const memberDark = await capture(page, info, 'member-populated', 'dark');

  await login(page, 'self');
  await visit(page, '/u/galadriel?tab=connections');
  await expect(page.getByRole('link', { name: 'Edit profile' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Remove follower' }).first()).toBeVisible();
  await capture(page, info, 'self-connections', 'light');
  await capture(page, info, 'self-connections', 'dark');

  await login(page, 'moderator');
  await visit(page, '/u/galadriel');
  await expect(page.getByRole('region', { name: 'Moderator context' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Open member record' })).toBeVisible();
  await capture(page, info, 'moderator-overview', 'light');
  await capture(page, info, 'moderator-overview', 'dark');

  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
  page.removeAllListeners('console');
  page.removeAllListeners('pageerror');
  page.removeAllListeners('response');
  const referenceLight = await captureReference(page, info, 'light');
  const referenceDark = await captureReference(page, info, 'dark');
  await captureComparison(page, info, 'light', referenceLight, memberLight);
  await captureComparison(page, info, 'dark', referenceDark, memberDark);

});

test('tabs, search, sorting, paging, menu, copy link, and keyboard focus work', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
  await login(page, 'member');
  await visit(page, '/u/galadriel');

  const topics = page.getByRole('link', { name: 'Topics', exact: true });
  await topics.focus();
  await expect(topics).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/tab=threads/);

  const search = page.getByRole('searchbox', { name: "Search this member's activity" });
  await search.fill('rollback');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page).toHaveURL(/q=rollback/);
  await expect(page.getByText(/entries|1 entry/).first()).toBeVisible();
  await page.getByRole('link', { name: 'Most commended' }).click();
  await expect(page).toHaveURL(/sort=commends/);
  await expect(page.locator('.profile-row-title').first()).toContainText('A rollback drill');

  await search.fill('');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.getByText(/22 entries/)).toBeVisible();
  await page.getByRole('link', { name: 'Next', exact: true }).click();
  await expect(page.getByText('Page 2 of 2')).toBeVisible();

  await page.getByRole('link', { name: 'Connections', exact: true }).click();
  const connectionSearch = page.getByRole('searchbox', { name: 'Find a member' });
  await connectionSearch.fill('Erestor');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.getByRole('link', { name: 'Erestor', exact: true })).toBeVisible();
  await visit(page, '/u/galadriel/followers');
  await expect(page.getByRole('heading', { name: /followers/i })).toBeVisible();
  await visit(page, '/u/galadriel/following');
  await expect(page.getByRole('heading', { name: /following/i })).toBeVisible();

  await visit(page, '/u/galadriel');
  const menuButton = page.locator('details.dm-menu > summary[aria-label="More actions"]');
  await menuButton.focus();
  await page.keyboard.press('Enter');
  const copy = page.getByRole('link', { name: 'Copy link' });
  await expect(copy).toBeVisible();
  await copy.focus();
  await expect(copy).toBeFocused();
  await page.keyboard.press('Enter');
  await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toMatch(/\/u\/galadriel$/);

  await expectNoHorizontalOverflow(page);
  await expectNoSeriousA11yViolations(page);
  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
});

test('profile navigation and GET forms remain complete without JavaScript', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    reducedMotion: 'reduce',
    viewport: viewport(info),
  });
  const page = await context.newPage();
  const messages = captureBrowserMessages(page);
  await login(page, 'member');
  await visit(page, '/u/galadriel');

  const menu = page.locator('details.dm-menu');
  await menu.locator('summary').click();
  await expect(menu).toHaveAttribute('open', '');
  await expect(menu.getByRole('link', { name: 'Copy link' })).toHaveAttribute('href', '/u/galadriel');

  await page.getByRole('link', { name: 'Topics', exact: true }).click();
  await page.getByRole('searchbox', { name: "Search this member's activity" }).fill('rollback');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page).toHaveURL(/q=rollback/);
  await expect(page.locator('.profile-row')).not.toHaveCount(0);

  await page.getByRole('link', { name: 'Connections', exact: true }).click();
  await page.getByRole('searchbox', { name: 'Find a member' }).fill('Erestor');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.getByRole('link', { name: 'Erestor', exact: true })).toBeVisible();
  await expectNoHorizontalOverflow(page);
  expect(messages, 'unexpected no-JS console errors or HTTP failures').toEqual([]);
  await context.close();
});
