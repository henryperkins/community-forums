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
type CapturedSurface = Surface | 'inbox';
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

async function expectBoardIdentityContent(page: Page, theme: Theme, project: 'desktop' | 'mobile'): Promise<void> {
  const board = page.locator('[data-board-identity]');
  await expect(board.locator('.eyebrow'), `${theme} board eyebrow`).toBeVisible();
  await expect(board.locator('h1'), `${theme} board title`).toBeVisible();
  await expect(board.locator('h1'), `${theme} board title text`).toContainText('#General');
  await expect(board.getByText('Talk about anything.'), `${theme} board description`).toBeVisible();
  await expect(board.locator('[data-board-fact]'), `${theme} board facts`).toHaveCount(3);
  for (const fact of await board.locator('[data-board-fact]').all()) {
    await expect(fact, `${theme} board fact`).toBeVisible();
  }
  // Below 680px the slab sheds its New topic button so the first topic clears the
  // fold; the FAB carries the action there. Above it, the slab button is the one.
  // Located by hook, not by role: a display:none button has no accessible role.
  const promotedTopic = board.locator('[data-open-topic-composer]');
  await expect(promotedTopic, `${theme} promoted New topic present`).toHaveCount(1);
  if (project === 'mobile') {
    await expect(promotedTopic, `${theme} promoted New topic hidden on phones`).toBeHidden();
    await expect(page.locator('a.fab[href="#new-topic"]'), `${theme} FAB carries New topic`).toBeVisible();
  } else {
    await expect(board.getByRole('button', { name: 'New topic' }), `${theme} promoted New topic`).toBeVisible();
  }
}

async function expectInboxContent(page: Page, project: 'desktop' | 'mobile'): Promise<void> {
  const inbox = page.locator('[data-inbox]');
  const scopeMenu = inbox.locator('.inbox-scope-menu');
  const activeFilter = scopeMenu.locator('.inbox-scope-menu-panel a.is-active');
  const topic = inbox.locator('[data-inbox-list] .inbox-row-title').first();
  await expect(inbox).toBeVisible();
  await expect(inbox.locator('[data-inbox-list]')).toBeVisible();
  await expect(scopeMenu.locator('.inbox-scope-menu-panel a')).not.toHaveCount(0);
  await expect(scopeMenu.locator(':scope > summary')).toContainText('For You');
  await expect(activeFilter).toHaveCount(1);
  await expect(activeFilter).toContainText('For You');
  await expect(activeFilter).toHaveAttribute('aria-current', 'page');
  await expect(inbox.locator('[data-inbox-list] [data-inbox-row].is-active')).toHaveCount(0);
  const keepsReadingPaneOpen = await page.evaluate(() => matchMedia('(min-width: 1280px)').matches);
  if (keepsReadingPaneOpen) {
    await expect(inbox.locator('[data-inbox-reading] .inbox-empty')).toBeVisible();
  } else {
    await expect(inbox.locator('[data-inbox-reading]')).toBeHidden();
  }
  await expect(topic).toBeVisible();
  await expect(topic).toHaveAttribute('href', /^\/t\/\d+/);
  await expect(page.locator('[data-board-identity]')).toHaveCount(0);
}

async function expectApprovedBoardDensity(page: Page): Promise<void> {
  const row = page.locator('.board-topics-list .thread-row-board')
    .filter({ hasText: 'Share your favourite keyboard shortcuts' });
  const title = row.locator('.thread-title');
  await expect(row).toHaveCount(1);
  await expect(title).toBeVisible();

  const geometry = await row.evaluate((element) => {
    const titleElement = element.querySelector<HTMLElement>('.thread-title');
    const rowRect = element.getBoundingClientRect();
    if (!titleElement) throw new Error('seeded row is missing its canonical title link');
    const titleStyle = getComputedStyle(titleElement);
    const lineHeight = Number.parseFloat(titleStyle.lineHeight);
    return {
      rowHeight: rowRect.height,
      titleHeight: titleElement.getBoundingClientRect().height,
      lineHeight,
    };
  });

  expect(geometry.titleHeight, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.lineHeight + 1);
  expect(geometry.rowHeight, JSON.stringify(geometry)).toBeGreaterThanOrEqual(60);
  expect(geometry.rowHeight, JSON.stringify(geometry)).toBeLessThanOrEqual(72);
}

function productionScreenshot(project: 'desktop' | 'mobile', surface: CapturedSurface, theme: Theme): string {
  return path.join(evidenceRoot, project, `${surface}-${theme}.png`);
}

async function captureSurface(
  page: Page,
  project: 'desktop' | 'mobile',
  surface: CapturedSurface,
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

  await visit(page, '/inbox');
  await expectInboxContent(page, project);
  await expectNoHorizontalOverflow(page);
  await expectNoSeriousA11yViolations(page, '[data-inbox]');
  await captureSurface(page, project, 'inbox', 'light', () => expectInboxContent(page, project));
  await captureSurface(page, project, 'inbox', 'dark', () => expectInboxContent(page, project));

  await visit(page, '/');
  await expect(page.locator('.forum-directory__hero')).toBeVisible();
  await expect(page.getByText('Your own cross-board queue')).toBeVisible();
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
  if (project === 'desktop') {
    await expectApprovedBoardDensity(page);
  }
  await expectNoHorizontalOverflow(page);
  await expectNoSeriousA11yViolations(page, '.board-view');
  await captureSurface(page, project, 'board', 'light', () => expectBoardIdentityContent(page, 'light', project));
  await captureSurface(page, project, 'board', 'dark', () => expectBoardIdentityContent(page, 'dark', project));

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
  // Two triggers carry this hook now — the slab's and the condensed bar's
  // pointer-only duplicate. The slab's is the keyboard one.
  const promoted = page.locator('.board-identity-actions [data-open-topic-composer]');
  const condensed = page.locator('.board-identity-condensed [data-open-topic-composer]');
  const fab = page.locator('a.fab[href="#new-topic"]');

  await expect(identity).toBeVisible();
  await expect(identity).toHaveCSS('background-color', 'rgb(46, 74, 58)');
  await expect(identity).toHaveCSS('border-bottom-color', 'rgb(194, 154, 68)');
  await expect(identity).toHaveCSS('border-bottom-width', '3px');
  await expect(identity).toHaveCSS('color', 'rgb(250, 246, 236)');
  await expect(details).toHaveCount(1);
  await expect(summary).toHaveClass(/js-native-topic-trigger/);
  await expect(promoted).toHaveAttribute('aria-expanded', 'false');

  // The condensed duplicate stays out of the accessibility tree until the slab
  // scrolls away, and never joins the tab order.
  await expect(condensed).toHaveCount(1);
  await expect(condensed).toBeHidden();
  await expect(condensed).toHaveAttribute('tabindex', '-1');

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

  if (info.project.name === 'mobile') {
    // Below 680px the slab sheds its New topic button so the first topic clears
    // the fold; the FAB is the keyboard path there, and it was exercised above.
    await expect(promoted).toBeHidden();
    await expect(fab).toBeVisible();
  } else {
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
  }
  await expectNoHorizontalOverflow(page);
  expectNoBrowserMessages(messages);
});

test('the board identity condenses on scroll without adding flow height or a tab stop', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await setEvidenceViewport(page, info);
  await login(page);
  await visit(page, '/c/general');

  const view = page.locator('.board-view');
  const slab = page.locator('[data-board-identity]');
  const bar = page.locator('.board-identity-condensed');
  const barTrigger = bar.locator('[data-open-topic-composer]');

  // Unscrolled: the slab is the masthead and the bar is invisible and inert.
  await expect(view).not.toHaveClass(/is-condensed/);
  await expect(bar).toHaveCSS('opacity', '0');
  await expect(bar).toHaveCSS('pointer-events', 'none');
  await expect(barTrigger).toBeHidden();

  // The seeded board is only a couple of topics tall, so give the document scroll
  // room below the list. The observer and the sticky rule under test do not care
  // why the page is tall, and padding below the list moves nothing above it.
  // (CSSOM writes are not subject to the style-src CSP the app ships.)
  await page.locator('.board-topics').evaluate((element) => {
    (element as HTMLElement).style.paddingBottom = '1600px';
  });

  // The wrapper is zero-height, so revealing the bar never reflows the list.
  const wrapperHeight = await page.locator('.board-identity-sticky').evaluate(
    (element) => element.getBoundingClientRect().height,
  );
  expect(wrapperHeight).toBe(0);
  const listTopBefore = await page.locator('.board-topics-list').evaluate(
    (element) => element.getBoundingClientRect().top + window.scrollY,
  );

  await slab.evaluate((element) => {
    window.scrollTo(0, element.getBoundingClientRect().bottom + window.scrollY + 200);
  });
  await expect(view).toHaveClass(/is-condensed/);
  await expect(bar).toHaveCSS('opacity', '1');
  await expect(bar).toBeInViewport();
  await expect(barTrigger).toBeVisible();
  // Still pointer-only: aria-hidden with no tab stop of its own.
  await expect(page.locator('.board-identity-sticky')).toHaveAttribute('aria-hidden', 'true');
  await expect(barTrigger).toHaveAttribute('tabindex', '-1');

  const listTopAfter = await page.locator('.board-topics-list').evaluate(
    (element) => element.getBoundingClientRect().top + window.scrollY,
  );
  expect(listTopAfter, 'the condensed bar must not push the topic list down').toBe(listTopBefore);

  await expectNoSeriousA11yViolations(page, '.board-view');
  await expectNoHorizontalOverflow(page);

  // And it stands down again on the way back up.
  await page.evaluate(() => window.scrollTo(0, 0));
  await expect(view).not.toHaveClass(/is-condensed/);
  await expect(barTrigger).toBeHidden();
  expectNoBrowserMessages(messages);
});

test('the phone board keeps its activity column, its touch targets and its Follow state', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'The phone grid and the touch floors are the mobile project contract.');
  const messages = captureBrowserMessages(page);
  await setEvidenceViewport(page, info);
  await login(page);
  await visit(page, '/c/general');

  // The activity column is the thing the redesign exists to make comparable down
  // the list, so it must never be the track that collapses. The row clips its own
  // overflow for the 3px status rule, so a starved column deletes the reply count
  // and the last-activity stamp *silently* — expectNoHorizontalOverflow, which
  // reads document.scrollWidth, cannot see it.
  const rows = await page.locator('.board-topics-list .thread-row-board').evaluateAll((elements) =>
    elements.map((row) => {
      const activity = row.querySelector('.thread-row-activity') as HTMLElement;
      const stamp = activity.querySelector('time') as HTMLElement;
      return {
        title: row.querySelector('.thread-title')?.textContent?.trim().slice(0, 30) ?? '',
        clipped: Math.round(activity.scrollWidth - activity.clientWidth),
        overhang: Math.round(stamp.getBoundingClientRect().right - row.getBoundingClientRect().right),
      };
    }),
  );
  expect(rows.length).toBeGreaterThan(0);
  for (const row of rows) {
    expect(row.clipped, JSON.stringify(rows)).toBeLessThanOrEqual(0);
    expect(row.overhang, JSON.stringify(rows)).toBeLessThanOrEqual(0);
  }

  // The shell's ≥44px touch floor covers the slab's Follow chip and — once it is
  // revealed — the condensed bar's duplicate, which the slab's own 38px desktop
  // rule would otherwise shrink past.
  const follow = await page.locator('.board-identity-actions .btn-secondary').boundingBox();
  expect(Math.round((follow?.height ?? 0) * 100) / 100).toBeGreaterThanOrEqual(44);

  // Following must still read as followed at this width: the phone outline-chip
  // rule ties .btn-on on specificity and would win on source order without a
  // :not() guard, making the two states pixel-identical.
  // (border-color and color, not background: .btn transitions background, so a
  // computed read straight after the class flip returns the pre-transition value.)
  const followTones = await page.locator('.board-identity-actions .btn-secondary').evaluate((element) => {
    const read = () => {
      const style = getComputedStyle(element);
      return `${style.borderTopColor} / ${style.color}`;
    };
    const off = read();
    element.classList.add('btn-on');
    const on = read();
    element.classList.remove('btn-on');
    return { off, on };
  });
  expect(followTones.on, JSON.stringify(followTones)).not.toBe(followTones.off);
  expect(followTones.on, 'Following keeps the gold on-state at phone widths')
    .toContain('rgb(194, 154, 68)');

  // A long board name must ellipsize rather than push New topic out of the pane:
  // the sticky wrapper is overflow: visible, so an unshrinkable title escapes the
  // viewport entirely. The seeded name is short, so widen it in place.
  await page.locator('.board-topics').evaluate((element) => {
    (element as HTMLElement).style.paddingBottom = '1600px';
  });
  await page.locator('.board-identity-condensed h2').evaluate((element) => {
    element.textContent = '#announcements-and-community-notices';
  });
  await page.evaluate(() => window.scrollTo(0, 900));
  await expect(page.locator('.board-view')).toHaveClass(/is-condensed/);

  const barTrigger = page.locator('.board-identity-condensed [data-open-topic-composer]');
  await expect(barTrigger).toBeVisible();
  const trigger = await barTrigger.boundingBox();
  expect(Math.round((trigger?.height ?? 0) * 100) / 100).toBeGreaterThanOrEqual(44);

  const pane = await page.locator('.board-identity-condensed').boundingBox();
  expect(trigger!.x + trigger!.width, 'New topic must stay inside the condensed pane')
    .toBeLessThanOrEqual(pane!.x + pane!.width + 1);
  await expectNoHorizontalOverflow(page);
  expectNoBrowserMessages(messages);
});

test('board route does not overflow at the 860px shell transition', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'The desktop project owns the intermediate-width regression.');
  const messages = captureBrowserMessages(page);

  await login(page);

  for (const width of [800, 681, 680, 390]) {
    await page.setViewportSize({ width, height: 800 });
    await visit(page, '/c/general');
    await expectNoHorizontalOverflow(page);
    const rect = await page.locator('.board-view').evaluate((element) => element.getBoundingClientRect());
    expect(Math.round(rect.left)).toBeGreaterThanOrEqual(0);
    expect(Math.round(rect.right)).toBeLessThanOrEqual(width);
  }

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
        windowWidth: window.innerWidth,
        viewportWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        indexLeft: rect ? Math.round(rect.left) : null,
        indexRight: rect ? Math.round(rect.right) : null,
      };
    });

    expect(layout.windowWidth).toBe(width);
    expect(layout.scrollWidth, JSON.stringify(layout)).toBeLessThanOrEqual(layout.viewportWidth);
    expect(layout.indexLeft, JSON.stringify(layout)).toBeGreaterThanOrEqual(0);
    expect(layout.indexRight, JSON.stringify(layout)).toBeLessThanOrEqual(layout.viewportWidth);
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
    // Both promoted triggers ship [hidden] and only JS unhides them, and the
    // condensed bar has no way to be revealed at all without .has-js — so the
    // slab stays the one masthead and <details> stays the one composer path.
    const promoted = page.locator('[data-open-topic-composer]');
    await expect(promoted).toHaveCount(2);
    for (const button of await promoted.all()) {
      await expect(button).toHaveAttribute('hidden', '');
      await expect(button).toBeHidden();
      expect(await button.boundingBox()).toBeNull();
    }
    await expect(page.locator('.board-identity-sticky')).toBeHidden();
    expect(await page.locator('.board-identity-sticky').boundingBox()).toBeNull();
    const details = page.locator('details.composer-details#new-topic');
    await details.locator(':scope > summary').click();
    await expect(details).toHaveJSProperty('open', true);
    await expect(details.locator('form[action="/threads"]')).toBeVisible();
    await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
    await expect(page.locator('[data-thread-study]')).toBeVisible();
    await expect(page.locator('[data-board-identity]')).toHaveCount(0);

    // FT-01. Without JS the thread is ONE naturally-flowing document: no
    // viewport-capped column, no nested scroll port, and Topic tools reachable by
    // ordinary page scrolling. Visibility alone is not enough — the reported
    // defect was a .thread-scroll collapsed to 12px of an 854px viewport, which
    // still satisfies toBeVisible() and produces no horizontal overflow.
    const geometry = await page.evaluate(() => {
      const conversation = document.querySelector('.thread-conversation') as HTMLElement | null;
      const scroll = document.querySelector('.thread-scroll') as HTMLElement | null;
      const tools = document.querySelector('[data-topic-tools]') as HTMLElement | null;
      const stream = document.querySelector('.post-stream') as HTMLElement | null;
      return {
        enhancedCount: document.querySelectorAll('[data-thread-enhanced]').length,
        overflowY: scroll ? getComputedStyle(scroll).overflowY : null,
        clientHeight: scroll ? scroll.clientHeight : null,
        scrollHeight: scroll ? scroll.scrollHeight : null,
        conversationHeight: conversation ? getComputedStyle(conversation).height : null,
        conversationClipped: conversation ? conversation.scrollHeight > conversation.clientHeight + 1 : null,
        toolsInsideScroll: !!(tools && scroll && scroll.contains(tools)),
        toolsBeforeStream: !!(tools && stream
          && (tools.compareDocumentPosition(stream) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0),
        toolsRendered: !!(tools && tools.getClientRects().length > 0),
        viewportHeight: window.innerHeight,
      };
    });
    const why = JSON.stringify(geometry);
    expect(geometry.enhancedCount, why).toBe(0);
    expect(geometry.overflowY, why).toBe('visible');
    // Guards the @media (max-width: 860px) twin of the height rule: gating only the
    // desktop rule leaves the column capped here, so the content clips with no
    // scroll port to recover it. The mobile project is what makes this meaningful.
    expect(geometry.conversationClipped, why).toBe(false);
    expect(geometry.scrollHeight!, why).toBeLessThanOrEqual(geometry.clientHeight! + 1);
    expect(geometry.toolsInsideScroll, why).toBe(true);
    expect(geometry.toolsBeforeStream, why).toBe(true);
    expect(geometry.toolsRendered, why).toBe(true);

    // Native disclosures still operate the tools without script.
    const watch = page.locator('[data-topic-tools-section="watch"]');
    await expect(watch).toHaveCount(1);

    // And a real reply still posts through the server-rendered form.
    const replyForm = page.locator('form[action$="/reply"]').first();
    await expect(replyForm.locator('input[name="_token"]')).toHaveCount(1);
    const marker = `no-JS reply ${info.project.name}`;
    await replyForm.locator('textarea').first().fill(marker);
    await replyForm.locator('button[type="submit"], input[type="submit"]').first().click();
    await expect(page.locator('.post-stream')).toContainText(marker);

    await expectNoHorizontalOverflow(page);
    expectNoBrowserMessages(messages);
  } finally {
    await context.close();
  }
});
