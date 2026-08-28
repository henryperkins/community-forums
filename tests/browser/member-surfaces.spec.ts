import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const memberSurfaceEvidence = path.join(repoRoot, 'docs', 'evidence', 'member-surfaces-production');
const memberSurfaceReferences = path.join(
  repoRoot,
  'docs',
  'design-system',
  'imladris',
  'templates',
  'member-surfaces',
  'screenshots',
);

const visualSurfaces = [
  { name: '01-board-index', route: '/?sort=unanswered&peek=3', width: 924, heading: 'Every board in the valley' },
  { name: '02-forum-inbox', route: '/inbox?scope=for_you&order=active', width: 924, heading: 'Forum inbox' },
  { name: '03-search', route: '/search?q=keyboard&scope=all&order=relevance', width: 924, heading: 'Search the council' },
  { name: '04-compose', route: '/compose?board=general', width: 909, heading: 'Open a topic' },
] as const;

function seedFixture(): void {
  execFileSync('php', ['tests/browser/member-surfaces-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
    stdio: 'inherit',
  });
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.getByLabel('Email').fill('alice@retro.test');
  await page.getByLabel('Password').fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 800 }).catch(() => false)) await skip.click();
}

async function expectNoOverflow(page: Page): Promise<void> {
  const widths = await page.evaluate(() => ({
    client: document.documentElement.clientWidth,
    scroll: document.documentElement.scrollWidth,
  }));
  expect(widths.scroll, JSON.stringify(widths)).toBeLessThanOrEqual(widths.client);
}

function captureBrowserMessages(page: Page): string[] {
  const entries: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' || message.type() === 'warning') entries.push(`${message.type()}: ${message.text()}`);
  });
  page.on('pageerror', (error) => entries.push(`pageerror: ${error.message}`));
  page.on('response', (response) => {
    if (response.status() >= 400) entries.push(`http ${response.status()}: ${response.url()}`);
  });
  return entries;
}

async function expectNoSeriousA11yViolations(page: Page): Promise<void> {
  const result = await new AxeBuilder({ page })
    .include('#main')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  expect(
    result.violations.filter((violation) => violation.impact === 'serious' || violation.impact === 'critical'),
    'serious/critical member-surface accessibility violations',
  ).toEqual([]);
}

async function waitForVisualReady(page: Page): Promise<void> {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.evaluate(async () => {
    await document.fonts.ready;
    await new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())));
  });
}

async function captureComparison(page: Page, name: string, width: number): Promise<void> {
  const reference = fs.readFileSync(path.join(memberSurfaceReferences, `${name}.png`)).toString('base64');
  const production = fs.readFileSync(path.join(memberSurfaceEvidence, 'desktop', `${name}.png`)).toString('base64');
  const gap = 24;
  await page.goto('about:blank');
  await page.setViewportSize({ width: (width * 2) + gap + 48, height: 680 });
  await page.setContent(`<!doctype html>
    <html lang="en"><head><meta charset="utf-8"><title>${name} comparison</title>
    <style>
      * { box-sizing: border-box; }
      body { margin: 0; background: #d7d7d7; color: #171717; font: 600 15px/1.4 system-ui, sans-serif; }
      main { display: grid; grid-template-columns: repeat(2, ${width}px); gap: ${gap}px; padding: 24px; align-items: start; }
      figure { margin: 0; padding: 10px; background: #fff; border: 1px solid #aaa; }
      figcaption { margin: 0 0 8px; }
      img { display: block; width: 100%; height: auto; background: #fff; }
    </style></head><body><main>
      <figure><figcaption>Approved handoff</figcaption><img alt="Approved handoff" src="data:image/png;base64,${reference}"></figure>
      <figure><figcaption>Production transfer</figcaption><img alt="Production transfer" src="data:image/png;base64,${production}"></figure>
    </main></body></html>`, { waitUntil: 'load' });
  await page.locator('img').evaluateAll(async (images) => Promise.all(images.map((image) => image.decode())));
  const output = path.join(memberSurfaceEvidence, 'comparisons', `${name}.png`);
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await page.screenshot({ path: output, fullPage: true, animations: 'disabled' });
}

async function newNoJsPage(
  browser: Browser,
  baseURL: string,
  viewport: { width: number; height: number } = { width: 1280, height: 800 },
): Promise<Page> {
  const context = await browser.newContext({
    baseURL,
    javaScriptEnabled: false,
    reducedMotion: 'reduce',
    viewport,
  });
  const page = await context.newPage();
  await login(page);
  return page;
}

test.beforeEach(seedFixture);

test('member shell shortcuts persist panels and suppress while typing', async ({ page }, info: TestInfo) => {
  await login(page);
  await page.goto('/compose?board=general');

  if (info.project.name === 'desktop') {
    await expect(page.locator('.topbar')).toHaveCSS('height', '62px');
    await expect(page.locator('.sidebar')).toHaveCSS('width', '272px');

    const body = page.locator('body');
    await expect(body).toHaveClass(/is-rail-open/);
    await page.locator('[data-compose-title]').focus();
    await page.keyboard.press('Control+b');
    await expect(body).toHaveClass(/is-rail-open/);

    await page.locator('h1').click();
    await page.keyboard.press('Control+b');
    await expect(body).toHaveClass(/is-rail-closed/);
    await page.reload();
    await expect(body).toHaveClass(/is-rail-closed/);

    await page.keyboard.press('Control+k');
    await expect(page).toHaveURL(/\/search$/);
    await expect(page.locator('.search-query-well')).toBeFocused();

    await page.goto('/inbox');
    await expect(body).toHaveClass(/is-reading-open/);
    await page.keyboard.press('Control+j');
    await expect(body).toHaveClass(/is-reading-closed/);
    await page.reload();
    await expect(body).toHaveClass(/is-reading-closed/);
  } else {
    await expect(page.locator('.topbar')).toHaveCSS('height', '62px');
    await expectNoOverflow(page);
  }
});

test('inbox menu, selection, cursor, preview, and fallback remain canonical', async ({ page }, info) => {
  await login(page);
  await page.goto('/inbox?scope=starred&order=active');

  const scope = page.locator('[data-inbox-scope-menu]');
  const summary = scope.locator('summary');
  await summary.click();
  await expect(scope).toHaveAttribute('open', '');
  const scopePanel = scope.locator('.inbox-scope-menu-panel');
  await expect(scopePanel).toHaveCSS('position', 'fixed');
  const scopeBox = await scopePanel.boundingBox();
  expect(scopeBox).not.toBeNull();
  expect(scopeBox!.x).toBeGreaterThanOrEqual(8);
  expect(scopeBox!.x + scopeBox!.width).toBeLessThanOrEqual(info.project.name === 'mobile' ? 382 : 1272);
  expect(scopeBox!.y + scopeBox!.height).toBeLessThanOrEqual(info.project.name === 'mobile' ? 836 : 792);
  await page.keyboard.press('Escape');
  await expect(scope).not.toHaveAttribute('open', '');
  await expect(summary).toBeFocused();

  await summary.click();
  await page.locator('[data-inbox-list]').evaluate((element) => {
    element.dispatchEvent(new Event('scroll', { bubbles: true }));
  });
  await expect(scope).not.toHaveAttribute('open', '');

  await summary.click();
  await page.locator('.main').evaluate((element) => {
    element.dispatchEvent(new Event('scroll'));
  });
  await expect(scope).not.toHaveAttribute('open', '');

  const lastRowMenu = page.locator('[data-inbox-row-menu]').last();
  await lastRowMenu.locator('summary').click();
  const rowMenuPanel = lastRowMenu.locator('.inbox-row-menu-panel');
  await expect(rowMenuPanel).toHaveCSS('position', 'fixed');
  const rowMenuBox = await rowMenuPanel.boundingBox();
  expect(rowMenuBox).not.toBeNull();
  expect(rowMenuBox!.x).toBeGreaterThanOrEqual(8);
  expect(rowMenuBox!.x + rowMenuBox!.width).toBeLessThanOrEqual(info.project.name === 'mobile' ? 382 : 1272);
  expect(rowMenuBox!.y + rowMenuBox!.height).toBeLessThanOrEqual(info.project.name === 'mobile' ? 836 : 792);
  await page.keyboard.press('Escape');

  const rows = page.locator('[data-inbox-row]');
  expect(await rows.count()).toBeGreaterThanOrEqual(2);
  const boxes = page.locator('[data-inbox-select]');
  await boxes.nth(0).check();
  await boxes.nth(1).check({ modifiers: ['Shift'] });
  await expect(boxes.nth(0)).toBeChecked();
  await expect(boxes.nth(1)).toBeChecked();
  await expect(page.locator('[data-inbox-sweep]')).toHaveClass(/is-active/);

  await page.locator('[data-inbox-list]').focus();
  await page.keyboard.press('j');
  const cursor = page.locator('[data-inbox-row].is-cursor');
  await expect(cursor).toHaveCount(1);
  await page.keyboard.press('Enter');
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  await expect(page).toHaveURL(/[?&]t=\d+/);

  if (info.project.name === 'mobile') {
    await expect(page.locator('[data-inbox-reading]')).toBeInViewport();
    await page.locator('[data-inbox-back]').click();
    await expect(page.locator('[data-inbox-thread-list]')).toBeInViewport();
  }

  const available = page.locator('[data-inbox-row] .inbox-row-title').last();
  const canonical = await available.getAttribute('href');
  await page.route('**/inbox/preview/*', (route) => route.fulfill({ status: 503, body: 'unavailable' }));
  await available.click();
  await expect(page).toHaveURL(new RegExp(`${canonical!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));
});

test('compose destination picker, select, anonymity, and draft status stay synchronized', async ({ page }, info) => {
  await login(page);
  await page.goto('/compose?board=general');
  await expect(page.locator('.topbar')).toBeInViewport();
  expect(await page.evaluate(() => window.scrollY)).toBe(0);
  const richEditor = page.locator('.wysiwyg-composer .ProseMirror');
  if (await richEditor.count()) {
    await expect(richEditor).toBeVisible();
    expect(await page.evaluate(() => window.scrollY)).toBe(0);
  }

  if (info.project.name === 'desktop') {
    await expect(page.locator('[data-compose-board-disabled="announcements"]')).toBeVisible();
    await page.locator('[data-compose-board-picker="feedback"]').click();
  } else {
    await expect(page.locator('[data-compose-board-select] option', { hasText: 'Announcements' })).toHaveAttribute('disabled', '');
    await page.locator('[data-compose-board-select]').selectOption({ label: 'Feedback' });
  }
  await expect(page).toHaveURL(/\/compose\?board=feedback$/);
  await expect(page.locator('[data-compose-board-select] option:checked')).toHaveText('Feedback');
  await expect(page.locator('[data-compose-board-name]')).toHaveText('Posting to Feedback');
  await expect(page.locator('[data-compose-board-picker="feedback"]')).toHaveAttribute('aria-pressed', 'true');

  await page.locator('[data-compose-board-select]').selectOption({ label: 'General' });
  await expect(page).toHaveURL(/\/compose\?board=general$/);
  await expect(page.locator('[data-compose-board-picker="general"]')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('.composer-anonymous-chip')).toBeVisible();

  await page.locator('[data-compose-title]').fill('A draft title');
  await expect(page.locator('[data-compose-draft-copy]')).toBeVisible();
  await expectNoOverflow(page);
  if (info.project.name === 'mobile') {
    const composer = page.locator('.composer-box');
    const composerBox = await composer.boundingBox();
    expect(composerBox).not.toBeNull();
    for (const control of await composer.locator('.composer-actions-bar button:visible, .composer-anonymous-chip:visible').all()) {
      const box = await control.boundingBox();
      expect(box, await control.getAttribute('class')).not.toBeNull();
      expect(box!.x).toBeGreaterThanOrEqual(composerBox!.x);
      expect(box!.x + box!.width).toBeLessThanOrEqual(composerBox!.x + composerBox!.width);
    }
    const anonymityClip = await composer.locator('.composer-anonymous-chip').evaluate((element) => ({
      client: element.clientWidth,
      scroll: element.scrollWidth,
    }));
    expect(anonymityClip.scroll, JSON.stringify(anonymityClip)).toBeLessThanOrEqual(anonymityClip.client);
  }

  // The server must keep the board-dependent anonymity affordance available
  // when the initial destination does not allow it; changing destinations is
  // a client-side state change, not a full render.
  await page.goto('/compose?board=feedback');
  await expect(page.locator('.composer-anonymous-chip')).toBeHidden();
  await page.locator('[data-compose-board-select]').selectOption({ label: 'General' });
  await expect(page.locator('.composer-anonymous-chip')).toBeVisible();
});

test('mobile viewing controls are a reachable sheet', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'Mobile sheet is verified at 390×844.');
  await login(page);
  await page.goto('/');
  const sheet = page.locator('[data-viewbar="narrow"]');
  await expect(sheet).toBeVisible();
  await sheet.locator('summary').click();
  await expect(sheet.locator('[data-directory-sort-option="newest"]')).toBeVisible();
  const box = await sheet.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.y + box!.height).toBeLessThanOrEqual(844);
  await expectNoOverflow(page);
});

test('board viewing changes replace the current URL, redraw, and persist without a document navigation', async ({ page }, info) => {
  await login(page);
  await page.goto('/?sort=category&peek=3');
  await page.evaluate(() => { (window as Window & { memberSurfaceSentinel?: boolean }).memberSurfaceSentinel = true; });

  const viewbar = page.locator(`[data-viewbar="${info.project.name === 'mobile' ? 'narrow' : 'wide'}"]`);
  if (info.project.name === 'mobile') await viewbar.locator('summary').click();
  await viewbar.locator('[data-directory-sort-option="newest"]').click();
  await expect(page).toHaveURL(/\?pane=boards&sort=newest&peek=3$/);
  await expect(page.locator('[data-directory-sort="newest"]')).toBeVisible();
  expect(await page.evaluate(() => (window as Window & { memberSurfaceSentinel?: boolean }).memberSurfaceSentinel)).toBe(true);

  await page.reload();
  await expect(page.locator('[data-directory-sort="newest"]')).toBeVisible();
});

test('member surfaces keep their no-JavaScript routes and forms', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'No-JavaScript proof runs once.');
  const nojs = await newNoJsPage(browser, baseURL!);

  await nojs.goto('/');
  const newest = nojs.locator('[data-viewbar="wide"] [data-directory-sort-option="newest"]');
  const newestForm = newest.locator('xpath=ancestor::form');
  await expect(newestForm).toHaveAttribute('action', '/settings/member-surfaces');
  await expect(newestForm.locator('input[name="return"]')).toHaveValue(/sort=newest/);
  await newest.click();
  await expect(nojs).toHaveURL(/sort=newest/);

  await nojs.goto('/compose?board=general');
  await nojs.locator('[data-compose-board-picker="feedback"]').click();
  await expect(nojs).toHaveURL(/\/compose\?board=feedback$/);

  await nojs.goto('/inbox?scope=starred&order=active');
  const topic = nojs.locator('[data-inbox-row] .inbox-row-title').first();
  const href = await topic.getAttribute('href');
  await topic.click();
  await expect(nojs).toHaveURL(new RegExp(href!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));

  await nojs.context().close();
});

test('member surfaces produce same-state visual evidence at the handoff and mobile viewports', async ({ page, browser, baseURL }, info) => {
  const messages = captureBrowserMessages(page);
  await login(page);
  const project = info.project.name === 'mobile' ? 'mobile' : 'desktop';
  const outputDir = path.join(memberSurfaceEvidence, project);
  fs.mkdirSync(outputDir, { recursive: true });

  for (const surface of visualSurfaces) {
    await page.setViewportSize(project === 'desktop'
      ? { width: surface.width, height: 540 }
      : { width: 390, height: 844 });
    const response = await page.goto(surface.route, { waitUntil: 'load' });
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(400);
    await expect(page.locator('.topbar')).toBeVisible();
    await expect(page.locator('#main')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: surface.heading })).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 0));
    await waitForVisualReady(page);
    await page.evaluate(() => window.scrollTo(0, 0));
    await expectNoOverflow(page);
    await expectNoSeriousA11yViolations(page);
    await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'light'));
    await waitForVisualReady(page);
    await page.screenshot({
      path: path.join(outputDir, `${surface.name}.png`),
      animations: 'disabled',
    });
    await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
    await waitForVisualReady(page);
    await page.screenshot({
      path: path.join(outputDir, `${surface.name}-twilight.png`),
      animations: 'disabled',
    });
  }

  if (project === 'desktop') {
    for (const surface of visualSurfaces) {
      await captureComparison(page, surface.name, surface.width);
    }
  }

  const noJsViewport = project === 'desktop' ? { width: 924, height: 540 } : { width: 390, height: 844 };
  const noJs = await newNoJsPage(browser, baseURL!, noJsViewport);
  const noJsOutput = path.join(memberSurfaceEvidence, 'no-js', project);
  fs.mkdirSync(noJsOutput, { recursive: true });
  for (const surface of visualSurfaces) {
    if (project === 'desktop') await noJs.setViewportSize({ width: surface.width, height: 540 });
    const response = await noJs.goto(surface.route, { waitUntil: 'load' });
    expect(response).not.toBeNull();
    expect(response!.status()).toBeLessThan(400);
    await expect(noJs.getByRole('heading', { level: 1, name: surface.heading })).toBeVisible();
    await noJs.screenshot({
      path: path.join(noJsOutput, `${surface.name}.png`),
      fullPage: true,
      animations: 'disabled',
    });
  }
  await noJs.context().close();
  expect(messages, 'unexpected browser warnings, errors, page errors, or failing responses').toEqual([]);
});
