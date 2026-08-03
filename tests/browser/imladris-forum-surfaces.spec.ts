import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceRoot = path.join(repoRoot, 'docs', 'evidence', 'imladris-forum-surfaces-production');
const prototypeRoot = path.join(
  repoRoot,
  'docs',
  'superpowers',
  'prototypes',
  '2026-08-02-imladris-forum-surfaces',
  'evidence',
);

type Surface = 'forum-index' | 'board' | 'thread';
type Theme = 'light' | 'dark';

const approvedReferences: Record<'desktop' | 'mobile', Record<Surface, string>> = {
  desktop: {
    'forum-index': path.join(prototypeRoot, 'forum-index-qa-1266x854-v2.png'),
    board: path.join(prototypeRoot, 'board-identity-desktop.png'),
    thread: path.join(prototypeRoot, 'thread-identity-desktop.png'),
  },
  mobile: {
    'forum-index': path.join(prototypeRoot, 'forum-index-mobile-v2.png'),
    board: path.join(prototypeRoot, 'board-identity-mobile.png'),
    thread: path.join(prototypeRoot, 'thread-mobile.png'),
  },
};

function evidenceViewport(info: TestInfo): { width: number; height: number } {
  return info.project.name === 'mobile'
    ? { width: 390, height: 844 }
    : { width: 1266, height: 854 };
}

async function setEvidenceViewport(page: Page, info: TestInfo): Promise<void> {
  await page.setViewportSize(evidenceViewport(info));
}

function captureBrowserMessages(page: Page): string[] {
  const entries: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error' || message.type() === 'warning') {
      const location = message.location();
      const source = location.url === '' ? '' : ` (${location.url}:${location.lineNumber}:${location.columnNumber})`;
      entries.push(`${message.type()}: ${message.text()}${source}`);
    }
  });
  page.on('pageerror', (error) => entries.push(`pageerror: ${error.message}`));
  page.on('response', (response) => {
    if (response.status() >= 400) {
      entries.push(`http ${response.status()}: ${response.url()}`);
    }
  });
  return entries;
}

function expectNoBrowserMessages(entries: string[]): void {
  expect(entries, 'unexpected console warnings/errors or page errors').toEqual([]);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await visit(page, '/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
  await dismissTour(page);
}

async function loginWithoutJavaScript(page: Page): Promise<void> {
  await visit(page, '/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
}

async function visit(page: Page, route: string): Promise<void> {
  const response = await page.goto(route, { waitUntil: 'load' });
  expect(response, `no response for ${route}`).not.toBeNull();
  expect(response!.status(), `GET ${route} should not be an error`).toBeLessThan(400);
}

async function openSeedThread(page: Page): Promise<void> {
  if (!new URL(page.url()).pathname.startsWith('/c/general')) {
    await visit(page, '/c/general');
  }
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await expect(page.locator('[data-thread-study]')).toBeVisible();
}

async function expectNoSeriousA11yViolations(page: Page, include: string): Promise<void> {
  const result = await new AxeBuilder({ page })
    .include(include)
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = result.violations.filter((violation) =>
    violation.impact === 'serious' || violation.impact === 'critical',
  );
  expect(violations, `${include} serious/critical axe violations`).toEqual([]);
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const layout = await page.evaluate(() => ({
    viewportWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(layout.scrollWidth, JSON.stringify(layout)).toBeLessThanOrEqual(layout.viewportWidth);
}

async function expectBoardIdentityContent(page: Page, theme: Theme): Promise<void> {
  const board = page.locator('[data-board-identity]');
  await expect(board.locator('.eyebrow'), `${theme} board eyebrow`).toBeVisible();
  await expect(board.locator('h1'), `${theme} board title`).toBeVisible();
  await expect(board.locator('h1'), `${theme} board title text`).toContainText('#General');
  await expect(board.getByText('Talk about anything.'), `${theme} board description`).toBeVisible();
  await expect(board.locator('[data-board-fact]'), `${theme} board facts`).toHaveCount(3);
  for (const fact of await board.locator('[data-board-fact]').all()) {
    await expect(fact, `${theme} board fact`).toBeVisible();
  }
  await expect(board.getByRole('button', { name: 'New topic' }), `${theme} promoted New topic`).toBeVisible();
}

function productionScreenshot(project: 'desktop' | 'mobile', surface: Surface, theme: Theme): string {
  return path.join(evidenceRoot, project, `${surface}-${theme}.png`);
}

async function captureSurface(
  page: Page,
  project: 'desktop' | 'mobile',
  surface: Surface,
  theme: Theme,
  assertReady?: () => Promise<void>,
): Promise<void> {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.evaluate((nextTheme) => {
    document.documentElement.setAttribute('data-theme', nextTheme);
  }, theme);
  await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
  await page.evaluate(async () => {
    await document.fonts.ready;
    await new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())));
  });
  if (assertReady) {
    await assertReady();
  }
  await page.screenshot({ fullPage: true, animations: 'disabled' });
  await page.evaluate(() => new Promise<void>((resolve) => requestAnimationFrame(() => resolve())));
  if (assertReady) {
    await assertReady();
  }
  const output = productionScreenshot(project, surface, theme);
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await page.screenshot({ path: output, fullPage: true, animations: 'disabled' });
}

function comparisonName(project: 'desktop' | 'mobile', surface: Surface): string {
  const suffix = project === 'mobile' ? '-mobile' : '';
  return `${surface}${suffix}.png`;
}

async function captureComparison(
  page: Page,
  project: 'desktop' | 'mobile',
  surface: Surface,
  viewport: { width: number; height: number },
): Promise<void> {
  const approved = fs.readFileSync(approvedReferences[project][surface]).toString('base64');
  const production = fs.readFileSync(productionScreenshot(project, surface, 'light')).toString('base64');
  const gap = 24;
  const sheetWidth = viewport.width * 2 + gap + 48;
  await page.goto('about:blank');
  await page.setViewportSize({ width: sheetWidth, height: Math.max(900, viewport.height + 100) });
  await page.setContent(`<!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <title>${surface} comparison</title>
        <style>
          * { box-sizing: border-box; }
          body { margin: 0; background: #d7d7d7; color: #171717; font: 600 16px/1.4 system-ui, sans-serif; }
          main { display: grid; grid-template-columns: repeat(2, ${viewport.width}px); gap: ${gap}px; padding: 24px; align-items: start; }
          figure { margin: 0; padding: 12px; background: #fff; border: 1px solid #aaa; }
          figcaption { margin: 0 0 10px; }
          img { display: block; width: ${viewport.width}px; height: auto; background: #fff; }
        </style>
      </head>
      <body>
        <main>
          <figure><figcaption>Approved prototype</figcaption><img alt="Approved prototype" src="data:image/png;base64,${approved}"></figure>
          <figure><figcaption>Production</figcaption><img alt="Production" src="data:image/png;base64,${production}"></figure>
        </main>
      </body>
    </html>`, { waitUntil: 'load' });
  await page.locator('img').evaluateAll(async (images) => {
    await Promise.all(images.map((image) => image.decode()));
  });
  await page.screenshot({ fullPage: true, animations: 'disabled' });
  await page.evaluate(() => new Promise<void>((resolve) => requestAnimationFrame(() => resolve())));
  const output = path.join(evidenceRoot, 'comparisons', comparisonName(project, surface));
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await page.screenshot({ path: output, fullPage: true, animations: 'disabled' });
}

test('forum index, board, and canonical thread satisfy production visual and accessibility contracts', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await setEvidenceViewport(page, info);
  await login(page);
  const project = info.project.name === 'mobile' ? 'mobile' : 'desktop';
  const viewport = evidenceViewport(info);

  await visit(page, '/');
  await expect(page.locator('.forum-directory__hero')).toBeVisible();
  await expect(page.getByText('personal cross-board queue')).toBeVisible();
  await expect(page.locator('[data-board-identity]')).toHaveCount(0);
  await expectNoHorizontalOverflow(page);
  await expectNoSeriousA11yViolations(page, '.board-index');
  await captureSurface(page, project, 'forum-index', 'light');
  await captureSurface(page, project, 'forum-index', 'dark');

  await visit(page, '/c/general');
  const board = page.locator('[data-board-identity]');
  await expect(board).toBeVisible();
  await expect(board).toHaveCSS('background-color', 'rgb(46, 74, 58)');
  await expect(board).toHaveCSS('color', 'rgb(250, 246, 236)');
  await expect(board).toHaveCSS('border-bottom-color', 'rgb(194, 154, 68)');
  await expect(board).toHaveCSS('border-bottom-width', '3px');
  await expect(page.getByText('Pinned first, then last post')).toBeVisible();
  await expectNoHorizontalOverflow(page);
  await expectNoSeriousA11yViolations(page, '.board-view');
  await captureSurface(page, project, 'board', 'light', () => expectBoardIdentityContent(page, 'light'));
  await captureSurface(page, project, 'board', 'dark', () => expectBoardIdentityContent(page, 'dark'));

  await openSeedThread(page);
  await expect(page.locator('[data-thread-study]')).toBeVisible();
  await expect(page.locator('[data-board-identity]')).toHaveCount(0);
  await expectNoHorizontalOverflow(page);
  await expectNoSeriousA11yViolations(page, '[data-thread-study]');
  await captureSurface(page, project, 'thread', 'light');
  await captureSurface(page, project, 'thread', 'dark');

  for (const surface of ['forum-index', 'board', 'thread'] as const) {
    await captureComparison(page, project, surface, viewport);
  }
  expectNoBrowserMessages(messages);
});

test('board identity opens the one topic composer and restores focus to its actual opener', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await setEvidenceViewport(page, info);
  await login(page);
  await visit(page, '/c/general');

  const identity = page.locator('[data-board-identity]');
  const details = page.locator('details.composer-details#new-topic');
  const summary = details.locator(':scope > summary');
  const title = details.locator('input[name="title"]');
  const promoted = page.locator('[data-open-topic-composer]');
  const fab = page.locator('a.fab[href="#new-topic"]');

  await expect(identity).toBeVisible();
  await expect(identity).toHaveCSS('background-color', 'rgb(46, 74, 58)');
  await expect(identity).toHaveCSS('border-bottom-color', 'rgb(194, 154, 68)');
  await expect(identity).toHaveCSS('border-bottom-width', '3px');
  await expect(identity).toHaveCSS('color', 'rgb(250, 246, 236)');
  await expect(details).toHaveCount(1);
  await expect(summary).toHaveClass(/js-native-topic-trigger/);
  await expect(promoted).toHaveAttribute('aria-expanded', 'false');

  const opener = info.project.name === 'mobile' ? fab : promoted;
  await expect(opener).toBeVisible();
  await opener.click();

  await expect(details).toHaveJSProperty('open', true);
  await expect(promoted).toHaveAttribute('aria-expanded', 'true');
  await expect(title).toBeFocused();

  await page.keyboard.press('Escape');

  await expect(details).toHaveJSProperty('open', false);
  await expect(promoted).toHaveAttribute('aria-expanded', 'false');
  await expect(opener).toBeFocused();

  const follow = page.locator('.board-identity-actions form button');
  await follow.focus();
  await expect(follow).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(promoted).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(details).toHaveJSProperty('open', true);
  await expect(promoted).toHaveAttribute('aria-expanded', 'true');
  await expect(title).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(details).toHaveJSProperty('open', false);
  await expect(promoted).toHaveAttribute('aria-expanded', 'false');
  await expect(promoted).toBeFocused();
  await expectNoHorizontalOverflow(page);
  expectNoBrowserMessages(messages);
});

test('board route does not overflow at the 860px shell transition', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'The desktop project owns the intermediate-width regression.');
  const messages = captureBrowserMessages(page);

  await page.setViewportSize({ width: 800, height: 800 });
  await login(page);
  await visit(page, '/c/general');

  const layout = await page.evaluate(() => {
    const board = document.querySelector('.board-view');
    const boardRect = board?.getBoundingClientRect();
    return {
      viewportWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      boardLeft: boardRect ? Math.round(boardRect.left) : null,
      boardRight: boardRect ? Math.round(boardRect.right) : null,
    };
  });

  expect(layout.viewportWidth).toBe(800);
  expect(layout.scrollWidth, JSON.stringify(layout)).toBe(800);
  expectNoBrowserMessages(messages);
});

test('forum index does not overflow across the 860px shell transition', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'The desktop project owns the shell-transition regression.');
  const messages = captureBrowserMessages(page);

  await page.setViewportSize({ width: 800, height: 800 });
  await login(page);

  for (const width of [800, 390]) {
    await page.setViewportSize({ width, height: 800 });
    await visit(page, '/');
    const layout = await page.evaluate(() => {
      const index = document.querySelector('.board-index');
      const rect = index?.getBoundingClientRect();
      return {
        viewportWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        indexLeft: rect ? Math.round(rect.left) : null,
        indexRight: rect ? Math.round(rect.right) : null,
      };
    });

    expect(layout.viewportWidth).toBe(width);
    expect(layout.scrollWidth, JSON.stringify(layout)).toBe(width);
    expect(layout.indexLeft, JSON.stringify(layout)).toBeGreaterThanOrEqual(0);
    expect(layout.indexRight, JSON.stringify(layout)).toBeLessThanOrEqual(width);
  }

  expectNoBrowserMessages(messages);
});

test('no-JavaScript board composer and canonical thread remain usable', async ({ browser, baseURL }, info) => {
  expect(baseURL).toBeTruthy();
  const viewport = evidenceViewport(info);
  const context = await browser.newContext({
    baseURL,
    javaScriptEnabled: false,
    viewport,
    deviceScaleFactor: info.project.name === 'mobile' ? 2 : 1,
    isMobile: info.project.name === 'mobile',
    hasTouch: info.project.name === 'mobile',
  });
  const page = await context.newPage();
  const messages = captureBrowserMessages(page);
  try {
    await loginWithoutJavaScript(page);
    await visit(page, '/c/general');
    await expect(page.locator('html')).not.toHaveClass(/has-js/);
    const promoted = page.locator('[data-open-topic-composer]');
    await expect(promoted).toHaveAttribute('hidden', '');
    await expect(promoted).toBeHidden();
    expect(await promoted.boundingBox()).toBeNull();
    const details = page.locator('details.composer-details#new-topic');
    await details.locator(':scope > summary').click();
    await expect(details).toHaveJSProperty('open', true);
    await expect(details.locator('form[action="/threads"]')).toBeVisible();
    await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
    await expect(page.locator('[data-thread-study]')).toBeVisible();
    await expect(page.locator('[data-board-identity]')).toHaveCount(0);
    await expectNoHorizontalOverflow(page);
    expectNoBrowserMessages(messages);
  } finally {
    await context.close();
  }
});
