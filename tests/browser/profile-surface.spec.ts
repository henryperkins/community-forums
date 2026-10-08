import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceRoot = path.resolve(repoRoot, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/imladris-profile-production');
const credentials = {
  member: ['bob@retro.test', 'password123'],
  self: ['galadriel@retro.test', 'password123'],
  moderator: ['alice@retro.test', 'password123'],
} as const;

// The fixture's narrow-cover member: a 32-character unbroken handle (the
// username maximum), no display name, and an unbroken website.
const longHandle = 'Celebrimbor_of_Eregion_Ringsmith';

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

/**
 * Foreground and background of `element` as opaque rgb() strings the contrast
 * helper can read. The browser's own canvas normalises any CSS colour syntax,
 * and translucent backgrounds are composited up the ancestor chain.
 */
async function effectiveColors(element: Locator): Promise<{ fg: string; bg: string }> {
  return element.evaluate((node) => {
    const canvas = document.createElement('canvas');
    canvas.width = 1;
    canvas.height = 1;
    const context = canvas.getContext('2d', { willReadFrequently: true })!;
    const rgba = (css: string): [number, number, number, number] => {
      context.clearRect(0, 0, 1, 1);
      context.fillStyle = '#000';
      context.fillStyle = css;
      context.fillRect(0, 0, 1, 1);
      const [r, g, b, a] = context.getImageData(0, 0, 1, 1).data;
      return [r, g, b, a / 255];
    };
    const over = (top: [number, number, number, number], base: number[]): number[] =>
      [0, 1, 2].map((i) => top[i] * top[3] + base[i] * (1 - top[3]));
    const chain: Element[] = [];
    for (let current: Element | null = node; current; current = current.parentElement) chain.unshift(current);
    let base = [255, 255, 255];
    for (const ancestor of chain) base = over(rgba(getComputedStyle(ancestor).backgroundColor), base);
    const fg = over(rgba(getComputedStyle(node).color), base);
    const css = (channels: number[]): string => `rgb(${channels.map((c) => Math.round(c)).join(', ')})`;
    return { fg: css(fg), bg: css(base) };
  });
}

async function expectReadable(element: Locator, label: string, minimum = 4.5): Promise<void> {
  const { fg, bg } = await effectiveColors(element);
  expect(contrastRatio(fg, bg), `${label}: ${fg} on ${bg}`).toBeGreaterThanOrEqual(minimum);
}

/**
 * The open ··· popover is wholly on screen: inside the viewport, with no
 * horizontal page scroll, painted above everything at all four corners (so no
 * ancestor clips it and no later panel covers it), under the ··· that opened
 * it (the two overlap horizontally), and, on a phone, inside the cover's
 * horizontal extent.
 */
async function expectMenuOnScreen(page: Page, pop: Locator, label: string, withinCover: boolean): Promise<void> {
  await expect(pop, `${label}: menu should be open`).toBeVisible();
  await pop.evaluate((element) => {
    const rect = element.getBoundingClientRect();
    if (rect.bottom > window.innerHeight) window.scrollBy(0, rect.bottom - window.innerHeight + 12);
  });
  const geometry = await pop.evaluate((element) => {
    const rect = element.getBoundingClientRect();
    const cover = element.closest('.profile-cover')!.getBoundingClientRect();
    const trigger = element.closest('details.dm-menu')!.querySelector(':scope > summary')!.getBoundingClientRect();
    const inset = 6;
    const probes: Array<[number, number]> = [
      [rect.left + inset, rect.top + inset],
      [rect.right - inset, rect.top + inset],
      [rect.left + inset, rect.bottom - inset],
      [rect.right - inset, rect.bottom - inset],
    ];
    return {
      pop: { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom },
      cover: { left: cover.left, right: cover.right },
      trigger: { left: trigger.left, right: trigger.right },
      viewport: { width: document.documentElement.clientWidth, height: window.innerHeight },
      scrollX: window.scrollX,
      corners: probes.map(([x, y]) => element.contains(document.elementFromPoint(x, y))),
    };
  });
  const detail = `${label} ${JSON.stringify(geometry)}`;
  expect(geometry.scrollX, detail).toBe(0);
  expect(geometry.pop.left, detail).toBeGreaterThanOrEqual(0);
  expect(geometry.pop.top, detail).toBeGreaterThanOrEqual(0);
  expect(geometry.pop.right, detail).toBeLessThanOrEqual(geometry.viewport.width);
  expect(geometry.pop.bottom, detail).toBeLessThanOrEqual(geometry.viewport.height);
  expect(geometry.corners, `${detail}: every corner is painted on top`).toEqual([true, true, true, true]);
  expect(
    Math.min(geometry.pop.right, geometry.trigger.right) - Math.max(geometry.pop.left, geometry.trigger.left),
    `${detail}: opens under its ··· trigger`,
  ).toBeGreaterThan(0);
  if (withinCover) {
    expect(geometry.pop.left, `${detail}: inside the cover`).toBeGreaterThanOrEqual(geometry.cover.left - 0.5);
    expect(geometry.pop.right, `${detail}: inside the cover`).toBeLessThanOrEqual(geometry.cover.right + 0.5);
  }
}

async function expectInViewport(page: Page, element: Locator, label: string): Promise<void> {
  await expect(element, label).toBeVisible();
  const box = await element.boundingBox();
  const size = page.viewportSize()!;
  expect(box, `${label} should be rendered`).not.toBeNull();
  const detail = `${label} ${JSON.stringify(box)} in ${JSON.stringify(size)}`;
  expect(box!.x, detail).toBeGreaterThanOrEqual(0);
  expect(box!.y, detail).toBeGreaterThanOrEqual(0);
  expect(box!.x + box!.width, detail).toBeLessThanOrEqual(size.width);
  expect(box!.y + box!.height, detail).toBeLessThanOrEqual(size.height);
}

/** Phone widths exercise the stacked cover; the desktop project keeps its own. */
function coverWidths(info: TestInfo): number[] {
  return projectName(info) === 'mobile' ? [390, 320] : [viewport(info).width];
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

// The imported .dc.html is source-only (PREVIEW_STATUS.md): its upstream
// component compiler is absent. Compare production to the handoff's documented
// values below; never label an unresolved loader as a rendered reference.

test.beforeAll(() => seedProfileFixture());

test('profile review: phone activity titles remain fully readable', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  for (const width of projectName(info) === 'mobile' ? [390, 320] : [1440, 1024]) {
    await page.setViewportSize({ width, height: 900 });
    await visit(page, '/u/galadriel');
    await settle(page);
    if (width <= 760) {
      const titles = await page.locator('.profile-activity-title').evaluateAll((elements) =>
        elements.map((element) => ({ client: element.clientWidth, scroll: element.scrollWidth })),
      );
      for (const title of titles) expect(title.scroll, JSON.stringify(title)).toBeLessThanOrEqual(title.client + 1);
    }
    await page.locator('[data-activity-toggle]').first().click();
    await expect(page.locator('.profile-activity-detail').first()).toBeVisible();
    await expect(page.locator('.profile-activity-detail .profile-number').first()).toHaveCSS('font-family', /JetBrains Mono/);
    await page.evaluate(() => window.scrollTo(0, 0));
    for (const theme of ['light', 'dark'] as const) await capture(page, info, `review-activity-${width}`, theme);
    if (width === 1024) {
      const sections = await page.locator('.profile-overview-aside section').evaluateAll((elements) =>
        elements.map((element) => ({ x: element.getBoundingClientRect().x, y: element.getBoundingClientRect().y })),
      );
      expect(sections).toHaveLength(2);
      expect(Math.abs(sections[0].y - sections[1].y)).toBeLessThan(1);
      expect(sections[1].x).toBeGreaterThan(sections[0].x);
    }
  }
  expect(messages).toEqual([]);
});

test('profile review: touch tablets wrap activity titles while a mouse keeps one line', async ({ page, browser, baseURL }, info) => {
  const messages = captureBrowserMessages(page);
  const touch = projectName(info) === 'mobile';
  // 820 and 834 are tablet portrait, where the details card sits beside the
  // list and titles clip without the wrap; 1180 is tablet landscape beside the rail.
  for (const [width, height] of [[820, 1180], [834, 1194], [1180, 820]] as const) {
    await page.setViewportSize({ width, height });
    await visit(page, '/u/galadriel');
    await settle(page);
    const hoverless = await page.evaluate(() => matchMedia('(hover: none), (pointer: coarse)').matches);
    expect(hoverless, `${width}px pointer emulation`).toBe(touch);
    const titles = await page.locator('.profile-activity-title').evaluateAll((elements) =>
      elements.map((element) => ({ wrap: getComputedStyle(element).whiteSpace, client: element.clientWidth, scroll: element.scrollWidth })),
    );
    expect(titles).toHaveLength(6);
    for (const title of titles) {
      const detail = `${width}px ${JSON.stringify(title)}`;
      if (touch) {
        // Without hover there is no tooltip, so no title may be clipped.
        expect(title.wrap, detail).toBe('normal');
        expect(title.scroll, detail).toBeLessThanOrEqual(title.client + 1);
      } else {
        // A mouse keeps the one-line title; its tooltip carries the rest.
        expect(title.wrap, detail).toBe('nowrap');
      }
    }
  }
  expect(messages).toEqual([]);
  if (!touch) return;

  // A full-page capture drops Chromium's touch emulation, both in the image and
  // for the rest of the page, so each theme gets a fresh touch context and a
  // viewport capture that ends at the list.
  for (const theme of ['light', 'dark'] as const) {
    const context = await browser.newContext({
      baseURL, viewport: { width: 820, height: 1180 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true,
    });
    const tablet = await context.newPage();
    const tabletMessages = captureBrowserMessages(tablet);
    await visit(tablet, '/u/galadriel');
    await applyTheme(tablet, theme);
    await tablet.locator('#recent-activity').evaluate((section) => section.scrollIntoView({ block: 'end' }));
    await settle(tablet);
    const title = tablet.locator('.profile-activity-title').first();
    await expect(title).toHaveCSS('white-space', 'normal');
    await expectNoHorizontalOverflow(tablet);
    await expectNoSeriousA11yViolations(tablet);
    const output = path.join(evidenceRoot, 'tablet', `review-activity-820-${theme}.png`);
    fs.mkdirSync(path.dirname(output), { recursive: true });
    await tablet.screenshot({ path: output, animations: 'disabled' });
    await expect(title, 'touch emulation held through the capture').toHaveCSS('white-space', 'normal');
    expect(tabletMessages).toEqual([]);
    await context.close();
  }
});

test('profile review: search controls align and activity timestamps retain exact instants', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  for (const tab of ['threads', 'posts', 'connections']) {
    await visit(page, `/u/galadriel?tab=${tab}`);
    await settle(page);
    const input = await page.getByRole('searchbox').boundingBox();
    const submit = await page.getByRole('button', { name: 'Search', exact: true }).boundingBox();
    expect(input).not.toBeNull();
    expect(submit).not.toBeNull();
    expect(Math.abs(input!.height - submit!.height), `${tab} search heights`).toBeLessThan(1);
    if (tab !== 'connections') {
      const times = page.locator('.profile-row-meta time');
      await expect(times).toHaveCount(20);
      await expect(times.first()).toHaveAttribute('datetime', /^\d{4}-\d\d-\d\dT/);
      await expect(times.first()).toHaveAttribute('title', /UTC$/);
      await expect(times.first()).not.toContainText('UTC');
    }
    await expect(page.locator('.profile-number').first()).toHaveCSS('font-family', /JetBrains Mono/);
    for (const theme of ['light', 'dark'] as const) await capture(page, info, `review-${tab}`, theme);
  }
  expect(messages).toEqual([]);
});

test('profile review: follower actions name their target and fit below phone identities', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  await login(page, 'self');
  await visit(page, '/u/galadriel?tab=connections');
  const card = page.locator('.profile-conn-card').filter({ has: page.getByRole('link', { name: 'Erestor', exact: true }) });
  const remove = card.getByRole('button', { name: 'Remove follower Erestor (@erestor)', exact: true });
  await expect(remove).toBeVisible();
  if (projectName(info) === 'mobile') {
    const identity = await card.locator('.profile-conn-id').boundingBox();
    const action = await remove.boundingBox();
    expect(action!.y).toBeGreaterThanOrEqual(identity!.y + identity!.height);
    const meta = await card.locator('.profile-conn-meta').evaluate((element) => ({ client: element.clientWidth, scroll: element.scrollWidth }));
    expect(meta.scroll).toBeLessThanOrEqual(meta.client + 1);
  }
  for (const theme of ['light', 'dark'] as const) await capture(page, info, 'review-own-followers', theme);
  await visit(page, '/u/galadriel/followers');
  await expect(page.getByRole('button', { name: 'Remove Erestor (@erestor)', exact: true })).toBeVisible();
  expect(messages).toEqual([]);
});

test('profile review: badge explanations work by keyboard without JavaScript', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: viewport(info) });
  const page = await context.newPage();
  const messages = captureBrowserMessages(page);
  await visit(page, '/u/galadriel');
  const badge = page.locator('.badge-row details').first();
  const explanation = badge.locator('.badge-description');
  // Page coordinates: focusing a chip below the fold scrolls the page.
  const layout = () => page.locator('.profile-badges').evaluate((section) => ({
    label: Math.round(section.querySelector('.profile-badges-label')!.getBoundingClientRect().top + window.scrollY),
    chips: [...section.querySelectorAll('.badge-chip')].map((chip) => {
      const rect = chip.getBoundingClientRect();
      return { left: Math.round(rect.left + window.scrollX), top: Math.round(rect.top + window.scrollY) };
    }),
  }));
  const closed = await layout();
  await expect(explanation).toBeHidden();
  await badge.locator('summary').focus();
  await page.keyboard.press('Enter');
  await expect(explanation).toBeVisible();
  await expect(explanation).not.toBeEmpty();
  await expectNoHorizontalOverflow(page);
  // Opening grows the row downward only: the label stays on the first line of
  // chips, and the chips beside the open one keep their places.
  const open = await layout();
  const detail = JSON.stringify({ closed, open });
  expect(open.label, detail).toBe(closed.label);
  const firstLine = closed.chips.filter((chip) => chip.top === closed.chips[0].top);
  expect(firstLine.length, detail).toBeGreaterThan(1);
  expect(open.chips.slice(0, firstLine.length), detail).toEqual(firstLine);
  // requestAnimationFrame callbacks do not run with page scripts disabled.
  await page.evaluate(() => window.scrollTo(0, 0));
  const output = path.join(evidenceRoot, projectName(info), 'review-badge-no-js.png');
  fs.mkdirSync(path.dirname(output), { recursive: true });
  await captureScreenshot(page, output);
  await badge.locator('summary').click();
  await expect(explanation).toBeHidden();
  await page.locator('.profile-cover-note').getByRole('link', { name: 'Log in', exact: true }).click();
  await expect(page).toHaveURL(/\/login\?next=\/u\/galadriel$/);
  expect(messages).toEqual([]);
  await context.close();
});

test('recent activity filters navigate and independent disclosures work by keyboard', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  await visit(page, '/u/galadriel');
  await expect(page).toHaveTitle(/Galadriel/);
  const activity = page.getByRole('region', { name: 'Recent activity' });
  await expect(activity.locator('.profile-activity-row')).toHaveCount(6);
  await expect(activity.locator('time').first()).toHaveAttribute('title', /UTC$/);
  await expect(activity.locator('time').first()).not.toContainText('UTC');
  const toggles = activity.getByRole('button');
  const first = toggles.nth(0);
  const firstDetail = page.locator('#' + await first.getAttribute('aria-controls'));
  await expect(firstDetail).toBeHidden();
  await first.focus();
  await page.keyboard.press('Enter');
  await expect(first).toHaveAttribute('aria-expanded', 'true');
  await expect(first).toHaveAccessibleName(/^Hide details: /);
  await expect(firstDetail).toBeVisible();
  await toggles.nth(1).click();
  await expect(firstDetail).toBeVisible();
  await expect(toggles.nth(1)).toHaveAttribute('aria-expanded', 'true');
  await expect(firstDetail.locator('.profile-row-meta')).toContainText(/#.+\d+ (repl|commend)/);
  for (const theme of ['light', 'dark'] as const) {
    await capture(page, info, 'activity-expanded', theme);
    await expect(firstDetail).toHaveCSS('animation-name', 'none');
  }
  await first.focus();
  await page.keyboard.press('Space');
  await expect(firstDetail).toBeHidden();
  await expect(first).toHaveAccessibleName(/^Show details: /);

  for (const [label, kind, value] of [['Topics', 'Topic', 'threads'], ['Replies', 'Reply', 'posts']] as const) {
    await activity.getByRole('link', { name: label, exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`activity=${value}#recent-activity$`));
    await expect(activity.locator('[aria-current="page"]')).toHaveText(label);
    await expect(activity.locator('.profile-activity-kind')).toHaveText(Array(6).fill(kind));
    await expectNoHorizontalOverflow(page);
  }
  await activity.getByRole('link', { name: 'All', exact: true }).click();
  await expect(page).toHaveURL(/\/u\/galadriel#recent-activity$/);
  expect(messages).toEqual([]);
});

test('profile controls use system sizing, focus, current states and stable disabled fills', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  await visit(page, '/u/galadriel?tab=threads');
  const tabs = page.getByRole('navigation', { name: 'Profile activity' });
  const order = page.getByRole('navigation', { name: 'Order' });
  const search = page.getByRole('searchbox');
  const pager = page.getByRole('navigation', { name: 'Pagination' });
  await expect(tabs).toHaveCSS('flex-wrap', 'wrap');
  await expect(tabs.getByRole('link', { name: 'Topics', exact: true })).toHaveCSS('font-weight', '400');
  await expect(order.locator('[aria-current="page"]')).toHaveText('Newest');
  await order.getByRole('link', { name: 'Most commended' }).click();
  await expect(order.locator('[aria-current="page"]')).toHaveText('Most commended');
  await expect(pager.getByRole('link', { name: 'Next' })).toHaveAttribute('rel', 'next');
  await expect(pager.locator('[aria-disabled="true"]')).toHaveCSS('opacity', '0.5');

  for (const theme of ['light', 'dark'] as const) {
    await applyTheme(page, theme);
    await expect(order.locator('[aria-current="page"]')).toHaveCSS('color', 'rgb(250, 246, 236)');
    await expect(tabs.getByRole('link', { name: 'Topics', exact: true })).toHaveCSS('color', theme === 'light' ? 'rgb(27, 35, 29)' : 'rgb(250, 246, 236)');
    await search.focus();
    await expect(search).toHaveCSS('padding-left', '35px');
    await expect(search).toHaveCSS('border-radius', '999px');
    await expect(search).toHaveCSS('font-size', '17px');
    await expect(search).toHaveCSS('outline-width', '2px');
    await expect(search).toHaveCSS('background-color', theme === 'light' ? 'rgb(245, 239, 225)' : 'rgb(22, 29, 36)');
    await expect(order.locator('[aria-current="page"]')).toHaveCSS('background-color', theme === 'light' ? 'rgb(46, 74, 58)' : 'rgb(78, 116, 89)');
    await capture(page, info, 'system-controls-focused', theme);
  }

  // Real disabled controls exercise the global system additions through the
  // production cascade, including app.css's otherwise winning hover rules.
  await page.locator('.profile').evaluate((profile) => {
    for (const variant of ['secondary', 'ghost', 'accent', 'danger']) {
      const button = document.createElement('button');
      button.type = 'button';
      button.disabled = true;
      button.className = `btn btn-${variant}`;
      button.textContent = `Disabled ${variant}`;
      button.dataset.disabledProbe = variant;
      profile.append(button);
    }
  });
  for (const theme of ['light', 'dark'] as const) {
    await applyTheme(page, theme);
    for (const variant of ['secondary', 'ghost', 'accent', 'danger']) {
      const button = page.locator(`[data-disabled-probe="${variant}"]`);
      await page.mouse.move(0, 0);
      await page.waitForTimeout(160);
      const background = await button.evaluate((element) => getComputedStyle(element).backgroundColor);
      await button.hover({ force: true });
      await page.waitForTimeout(160);
      await expect(button).toHaveCSS('background-color', background);
    }
  }
  expect(messages).toEqual([]);
});

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
  const finalTabBox = await page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Connections', exact: true }).boundingBox();
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
  await capture(page, info, 'member-populated', 'light');
  await capture(page, info, 'member-populated', 'dark');

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

});

test('tabs, search, sorting, paging, menu, copy link, and keyboard focus work', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  await page.context().grantPermissions(['clipboard-read', 'clipboard-write']);
  await login(page, 'member');
  await visit(page, '/u/galadriel');

  // The header counts open the in-page Connections tab on the matching list.
  const stats = page.locator('.profile-cover .profile-stats');
  const followersStat = stats.getByRole('link', { name: 'Followers', exact: true });
  const followingStat = stats.getByRole('link', { name: 'Following', exact: true });
  await expect(followersStat).toHaveAttribute('href', '/u/galadriel?tab=connections');
  await expect(followingStat).toHaveAttribute('href', '/u/galadriel?tab=connections&c=following');
  const statValue = async (name: string): Promise<string> => (await stats.locator('div')
    .filter({ has: page.getByRole('link', { name, exact: true }) }).locator('dd').innerText()).trim();
  const followerTotal = await statValue('Followers');
  const followingTotal = await statValue('Following');
  await followersStat.click();
  await expect(page).toHaveURL(/\/u\/galadriel\?tab=connections$/);
  await expect(page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Connections', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('.profile-seg-opt.is-on')).toHaveText(`Followers · ${followerTotal}`);
  await expect(page.locator('.profile-conn-card')).toHaveCount(Number(followerTotal));
  await followingStat.click();
  await expect(page).toHaveURL(/\/u\/galadriel\?tab=connections&c=following$/);
  await expect(page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Connections', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('.profile-seg-opt.is-on')).toHaveText(`Following · ${followingTotal}`);
  await expect(page.locator('.profile-conn-card')).toHaveCount(Number(followingTotal));

  const topics = page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Topics', exact: true });
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

  await page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Connections', exact: true }).click();
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
  // By its hook, not its name: the name reads "Copied" for two seconds.
  const copy = page.locator('.profile-cover [data-copy-link]');
  await expect(copy).toBeVisible();
  await expect(copy).toHaveAccessibleName('Copy link');
  await copy.focus();
  await expect(copy).toBeFocused();
  // Record every change to the live region, so a repeat copy can be shown to
  // clear it first and then announce again.
  const copyStatus = page.locator('.profile-actions [data-copy-status]');
  await expect(copyStatus).toHaveAttribute('role', 'status');
  await copyStatus.evaluate((region) => {
    const log: string[] = [];
    (window as unknown as { rbCopyStatusLog: string[] }).rbCopyStatusLog = log;
    new MutationObserver(() => log.push(region.textContent ?? '')).observe(region, { childList: true, characterData: true, subtree: true });
  });
  const copyLog = (): Promise<string[]> =>
    page.evaluate(() => (window as unknown as { rbCopyStatusLog: string[] }).rbCopyStatusLog);
  const copyLabel = copy.locator('span');
  await page.keyboard.press('Enter');
  await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toMatch(/\/u\/galadriel$/);
  await expect(copyLabel).toHaveText('Copied');
  await expect(copyStatus).toHaveText('Link copied.');
  // The label goes back to naming the action (the reset fires at 2s).
  await page.waitForTimeout(2500);
  await expect(copyLabel).toHaveText('Copy link');
  await copy.click();
  await expect(copyLabel).toHaveText('Copied');
  await expect.poll(copyLog).toEqual(['Link copied.', '', 'Link copied.']);
  await page.waitForTimeout(2500);
  await expect(copyLabel).toHaveText('Copy link');

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

  const activity = page.getByRole('region', { name: 'Recent activity' });
  await expect(activity.getByRole('button')).toHaveCount(0);
  await expect(activity.locator('.profile-activity-detail:visible')).toHaveCount(0);
  await activity.getByRole('link', { name: 'Replies', exact: true }).click();
  await expect(page).toHaveURL(/activity=posts#recent-activity$/);
  await expect(activity.locator('.profile-activity-kind')).toHaveText(Array(6).fill('Reply'));
  await activity.getByRole('link', { name: 'Topics', exact: true }).click();
  await expect(activity.locator('.profile-activity-kind')).toHaveText(Array(6).fill('Topic'));
  await activity.getByRole('link', { name: 'All', exact: true }).click();
  const noJsCapture = path.join(evidenceRoot, projectName(info), 'activity-no-js.png');
  fs.mkdirSync(path.dirname(noJsCapture), { recursive: true });
  await captureScreenshot(page, noJsCapture);

  const menu = page.locator('details.dm-menu');
  await menu.locator(':scope > summary').click();
  await expect(menu).toHaveAttribute('open', '');
  await expect(menu.getByRole('link', { name: 'Copy link' })).toHaveAttribute('href', '/u/galadriel');

  await page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Topics', exact: true }).click();
  await page.getByRole('searchbox', { name: "Search this member's activity" }).fill('rollback');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page).toHaveURL(/q=rollback/);
  await expect(page.locator('.profile-row')).not.toHaveCount(0);

  await page.getByRole('navigation', { name: 'Profile activity' }).getByRole('link', { name: 'Connections', exact: true }).click();
  await page.getByRole('searchbox', { name: 'Find a member' }).fill('Erestor');
  await page.getByRole('button', { name: 'Search' }).click();
  await expect(page.getByRole('link', { name: 'Erestor', exact: true })).toBeVisible();
  await expectNoHorizontalOverflow(page);

  // The header counts are plain links, so they reach the tab without script.
  await page.locator('.profile-cover .profile-stats').getByRole('link', { name: 'Following', exact: true }).click();
  await expect(page).toHaveURL(/\/u\/galadriel\?tab=connections&c=following$/);
  await expect(page.locator('.profile-seg-opt.is-on')).toHaveText(/^Following · \d+$/);
  expect(messages, 'unexpected no-JS console errors or HTTP failures').toEqual([]);
  await context.close();
});

test('Commends meets contrast in both themes and every empty state keeps the shared frame', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));

  await visit(page, '/u/galadriel?tab=commends');
  await expect(page.getByRole('link', { name: 'Commends', exact: true })).toHaveAttribute('aria-current', 'page');
  const rows = page.locator('.profile-commend-rows li');
  await expect(rows).toHaveCount(4);
  await expect(rows.first().locator('.profile-commend-count')).toHaveText('4');
  await expect(rows.first()).toContainText('The migration that could not be undone');
  const card = page.locator('.profile-regard-card');
  await expect(card.locator('.profile-regard-value')).toHaveText('5,140');
  // The raised surface with a gold hairline, not the source's gold-100 wash.
  await expect(card).toHaveCSS('background-image', 'none');
  await expect(page.locator('.profile-commend-list > h2')).toHaveCSS('text-transform', 'uppercase');

  for (const theme of ['light', 'dark'] as const) {
    await capture(page, info, 'guest-commends', theme);
    for (const [index, count] of (await page.locator('.profile-commend-count').all()).entries()) {
      await expectReadable(count, `${theme} commend count ${index + 1}`);
    }
    await expectReadable(card.locator('.profile-regard-value'), `${theme} Regard value`);
    await expectReadable(card.locator('.profile-regard-label'), `${theme} Regard label`);
    await expectReadable(card.locator('.profile-regard-note'), `${theme} Regard note`);
  }

  // The Commends empty title is a heading below the section's h2, not a second
  // eyebrow.
  await visit(page, '/u/empty-seat?tab=commends');
  const commendsEmpty = page.locator('.profile-commend-list .profile-panel-empty');
  await expect(commendsEmpty.getByRole('heading', { level: 3, name: 'No commended posts yet.' })).toBeVisible();
  await expect(commendsEmpty.locator('h3')).toHaveCSS('text-transform', 'none');

  // Every empty state on the profile, the no-results ones included, uses the
  // one dashed frame, and each carries a sentence under its title.
  const empties: Array<[string, string]> = [
    ['/u/empty-seat', 'No public activity yet'],
    ['/u/empty-seat?tab=commends', 'No commended posts yet.'],
    ['/u/empty-seat?tab=threads', 'No topics started yet.'],
    ['/u/empty-seat?tab=posts', 'No posts yet.'],
    ['/u/galadriel?tab=threads&q=zzzz-no-such-topic', 'Nothing matches “zzzz-no-such-topic”'],
    ['/u/empty-seat?tab=connections', 'No one here yet'],
    ['/u/galadriel?tab=connections&cq=zzzz-no-such-member', 'Nothing matches “zzzz-no-such-member”'],
    ['/u/empty-seat/followers', 'No followers yet.'],
    ['/u/empty-seat/following', 'Not following anyone yet.'],
  ];
  for (const [route, title] of empties) {
    await visit(page, route);
    const empty = page.locator('.profile-panel-empty');
    await expect(empty, route).toHaveCount(1);
    await expect(empty.getByRole('heading', { name: title }), route).toBeVisible();
    await expect(empty, route).toHaveCSS('border-top-style', 'dashed');
    await expect(empty.locator('p').first(), route).not.toBeEmpty();
  }
  await expect(page.locator('.profile-panel-empty p').first()).toHaveText('Empty Seat is not following anyone yet.');
  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
});

test('phone stat numbers share a baseline and a 32-character handle wraps inside the cover', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  const mobile = projectName(info) === 'mobile';
  await page.setViewportSize(viewport(info));

  for (const route of ['/u/galadriel', `/u/${longHandle}`]) {
    await visit(page, route);
    const stats = page.locator('.profile-cover .profile-stats > div');
    await expect(stats.locator('dt')).toHaveText(['Posts', 'Followers', 'Following']);
    for (const width of coverWidths(info)) {
      await page.setViewportSize({ width, height: viewport(info).height });
      await settle(page);
      const layout = await stats.evaluateAll((items) => items.map((item) => {
        const dd = item.querySelector('dd')!.getBoundingClientRect();
        return { row: Math.round(item.getBoundingClientRect().top), ddTop: dd.top, ddOffset: dd.top - item.getBoundingClientRect().top };
      }));
      const detail = `${route} at ${width}px ${JSON.stringify(layout)}`;
      // The 44px tap target must not push a linked stat's number down: each
      // number sits the same distance under its label, and numbers on one row
      // share a top. (At 320px the id column beside the avatar is ~176px, so
      // Following wraps under Posts; that row break is layout, not a baseline.)
      const offsets = layout.map((item) => item.ddOffset);
      expect(Math.max(...offsets) - Math.min(...offsets), detail).toBeLessThanOrEqual(1);
      for (const row of new Set(layout.map((item) => item.row))) {
        const tops = layout.filter((item) => item.row === row).map((item) => item.ddTop);
        expect(Math.max(...tops) - Math.min(...tops), detail).toBeLessThanOrEqual(1);
      }
      if (width >= 390) expect(new Set(layout.map((item) => item.row)).size, `${detail}: one row`).toBe(1);
      if (mobile) {
        // The tap target is the link's centred ::after, at least 44px each way,
        // including the guest's Log in link.
        const targets = await page.locator('.profile-cover .profile-stats dt a, .profile-cover .profile-web a, .profile-cover .profile-cover-note a')
          .evaluateAll((links) => links.map((link) => {
            const after = getComputedStyle(link, '::after');
            return { width: parseFloat(after.width), height: parseFloat(after.height) };
          }));
        expect(targets.length, detail).toBe(4);
        for (const target of targets) {
          expect(target.height, `${detail} ${JSON.stringify(targets)}`).toBeGreaterThanOrEqual(44);
          expect(target.width, `${detail} ${JSON.stringify(targets)}`).toBeGreaterThanOrEqual(44);
        }
      }
    }
    await page.setViewportSize(viewport(info));
  }

  // The longest possible handle, unbroken, in the h1 and the handle line, next
  // to an unbroken website: no horizontal scroll at the narrowest phone.
  await visit(page, `/u/${longHandle}`);
  await expect(page.locator('.profile-name')).toContainText(longHandle);
  await expect(page.locator('.profile-handle')).toHaveText(`@${longHandle}`);
  expect(longHandle).toMatch(/^[A-Za-z0-9_]{32}$/);
  for (const width of coverWidths(info)) {
    await page.setViewportSize({ width, height: viewport(info).height });
    await settle(page);
    await expectNoHorizontalOverflow(page);
    const fit = await page.locator('.profile-cover').evaluate((cover) => {
      const bounds = cover.getBoundingClientRect();
      return ['.profile-name', '.profile-handle', '.profile-web a'].map((selector) => {
        const rect = cover.querySelector(selector)!.getBoundingClientRect();
        return { selector, left: rect.left, right: rect.right, coverLeft: bounds.left, coverRight: bounds.right };
      });
    });
    for (const item of fit) {
      expect(item.left, `${width}px ${JSON.stringify(item)}`).toBeGreaterThanOrEqual(item.coverLeft);
      expect(item.right, `${width}px ${JSON.stringify(item)}`).toBeLessThanOrEqual(item.coverRight);
    }
  }
  // Mobile files the capture at the narrowest width, where wrapping is forced.
  await capture(page, info, 'guest-long-handle', 'light');
  await capture(page, info, 'guest-long-handle', 'dark');
  await page.setViewportSize(viewport(info));
  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
});

test('Connections pages past twenty and leaves members-only followers out for guests', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  await page.setViewportSize(viewport(info));
  const profileUrl = '/u/lamplighter';

  // Signed-in: 21 public followers and one members-only follower, 20 a page.
  await login(page, 'member');
  await visit(page, `${profileUrl}?tab=connections`);
  const pager = page.getByRole('navigation', { name: 'Pagination' });
  await expect(page.locator('.profile-seg-opt.is-on')).toHaveText('Followers · 22');
  await expect(page.locator('.profile-conn-card')).toHaveCount(20);
  await expect(page.locator('.profile-conn-card').first()).toContainText('Hearth 21');
  await expect(pager).toContainText('Page 1 of 2');
  await pager.getByRole('link', { name: 'Next', exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`${profileUrl}\\?tab=connections&page=2$`));
  await expect(pager).toContainText('Page 2 of 2');
  await expect(page.locator('.profile-conn-card')).toHaveCount(2);
  await expect(page.getByRole('link', { name: 'Private Seat', exact: true })).toBeVisible();
  await expectNoHorizontalOverflow(page);
  await pager.getByRole('link', { name: 'Previous', exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`${profileUrl}\\?tab=connections$`));
  await expect(pager).toContainText('Page 1 of 2');

  await visit(page, `${profileUrl}/followers`);
  const standalonePager = page.getByRole('navigation', { name: 'Pagination' });
  await expect(page.locator('.people-list li')).toHaveCount(20);
  await expect(page.locator('.people-list .person-rep')).toHaveCount(20);
  await expect(standalonePager).toContainText('Page 1 of 2');
  await standalonePager.getByRole('link', { name: 'Next', exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`${profileUrl}/followers\\?page=2$`));
  await expect(page.locator('.people-list li')).toHaveCount(2);
  await expect(page.getByRole('link', { name: 'Private Seat', exact: true })).toBeVisible();

  // A guest pages the same list without the members-only account (ADR 0031 §2).
  await page.context().clearCookies();
  await visit(page, `${profileUrl}?tab=connections&page=2`);
  // A guest's count is the people a guest is shown.
  await expect(page.locator('.profile-seg-opt.is-on')).toHaveText('Followers · 21');
  await expect(pager).toContainText('Page 2 of 2');
  await expect(page.locator('.profile-conn-card')).toHaveCount(1);
  await expect(page.locator('.profile-conn-card')).toContainText('Hearth 01');
  await expect(page.getByRole('link', { name: 'Private Seat', exact: true })).toHaveCount(0);
  await expectNoHorizontalOverflow(page);
  await visit(page, `${profileUrl}/followers?page=2`);
  await expect(page.locator('.people-list li')).toHaveCount(1);
  await expect(page.getByRole('link', { name: 'Private Seat', exact: true })).toHaveCount(0);
  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
});

test('the ··· menu opens on screen, folds its Block step, and holds on a short action row', async ({ page }, info) => {
  const messages = captureBrowserMessages(page);
  const mobile = projectName(info) === 'mobile';
  await page.setViewportSize(viewport(info));
  await login(page, 'member');

  // A full action row: Following, Message, ···.
  await visit(page, '/u/galadriel');
  const actions = page.locator('.profile-cover .profile-actions');
  const menu = actions.locator('details.dm-menu');
  const trigger = menu.locator(':scope > summary');
  const pop = menu.locator('.dm-menu-pop');
  await expect(actions.getByRole('button', { name: 'Following', exact: true })).toBeVisible();
  await expect(actions.getByRole('link', { name: 'Message', exact: true })).toBeVisible();
  // The ··· trigger takes the cover's mist ink in both registers (non-text
  // contrast, 3:1), not its own muted light-panel ink: at rest, and hovered,
  // which a tap leaves behind on a touch screen.
  for (const theme of ['light', 'dark'] as const) {
    await applyTheme(page, theme);
    await expectReadable(trigger, `${theme} ··· trigger on the cover`, 3);
    await trigger.hover();
    await page.waitForTimeout(250);
    await expectReadable(trigger, `${theme} hovered ··· trigger on the cover`, 3);
    await page.mouse.move(0, 0);
    await page.waitForTimeout(250);
  }
  for (const width of coverWidths(info)) {
    await page.setViewportSize({ width, height: viewport(info).height });
    await trigger.click();
    await expect(menu).toHaveAttribute('open', '');
    await expectMenuOnScreen(page, pop, `full row at ${width}px`, mobile);
    await trigger.click();
    await expect(menu).not.toHaveAttribute('open');
  }
  await page.setViewportSize(viewport(info));
  await page.evaluate(() => window.scrollTo(0, 0));
  await trigger.click();
  await capture(page, info, 'member-menu-open', 'light');
  await capture(page, info, 'member-menu-open', 'dark');

  // The nested Block step: its confirmation is reachable on screen.
  const blockStep = pop.locator('details.profile-block');
  const confirm = pop.getByRole('button', { name: 'Block @galadriel', exact: true });
  await blockStep.locator(':scope > summary').click();
  await expect(blockStep).toHaveAttribute('open', '');
  await expect(pop).toContainText('Any follow between you ends, and unblocking does not restore it.');
  await expectMenuOnScreen(page, pop, 'Block step open', mobile);
  await expectInViewport(page, confirm, 'Block confirmation');
  await page.evaluate(() => window.scrollTo(0, 0));
  await capture(page, info, 'member-block-confirm', 'light');
  await capture(page, info, 'member-block-confirm', 'dark');

  // Escape closes the menu and folds the step, so ··· reopens on its first level.
  await page.keyboard.press('Escape');
  await expect(menu).not.toHaveAttribute('open');
  await expect(trigger).toBeFocused();
  await trigger.click();
  await expect(menu).toHaveAttribute('open', '');
  await expect(blockStep).not.toHaveAttribute('open');
  await expect(confirm).toBeHidden();
  await expect(pop.getByRole('link', { name: 'Copy link' })).toBeVisible();

  // So does a click outside the menu.
  await blockStep.locator(':scope > summary').click();
  await expect(confirm).toBeVisible();
  await page.locator('.profile-cover .profile-meta').click();
  await expect(menu).not.toHaveAttribute('open');
  await trigger.click();
  await expect(blockStep).not.toHaveAttribute('open');
  await expect(confirm).toBeHidden();
  await trigger.click();
  await expect(menu).not.toHaveAttribute('open');

  // A short action row: once the viewer blocks the member, only ··· remains.
  // The block is made, and then undone, through the real ··· forms; the
  // fixture also clears it for the next project.
  const profileUrl = `/u/${longHandle}`;
  await visit(page, profileUrl);
  await expect(actions.getByRole('button', { name: 'Follow', exact: true })).toBeVisible();
  await trigger.click();
  await blockStep.locator(':scope > summary').click();
  // The 32-character handle wraps inside the Block step instead of spilling
  // past the menu's edge.
  for (const width of coverWidths(info)) {
    await page.setViewportSize({ width, height: viewport(info).height });
    const spill = await pop.evaluate((element) => {
      const box = element.getBoundingClientRect();
      return Array.from(element.querySelectorAll('.profile-block form > p, .profile-block form > button'))
        .map((child) => child.getBoundingClientRect().right - box.right);
    });
    expect(Math.max(...spill), `Block step for a long handle at ${width}px`).toBeLessThanOrEqual(0.5);
  }
  await page.setViewportSize(viewport(info));
  await pop.getByRole('button', { name: `Block @${longHandle}`, exact: true }).click();
  await page.waitForURL((url) => url.pathname === profileUrl);
  await expect(page.locator('.flash')).toHaveText('Blocked. They can no longer message or @mention you.');
  await expect(actions.getByRole('button', { name: 'Follow', exact: true })).toHaveCount(0);
  await expect(actions.getByRole('link', { name: 'Message', exact: true })).toHaveCount(0);
  await expect(actions.locator(':scope > :not([data-copy-status])')).toHaveCount(1);
  await page.reload();
  await expect(page.locator('.profile-cover [role="status"]').filter({ hasText: 'You blocked' })).toContainText(longHandle);
  await page.evaluate(() => window.scrollTo(0, 0));
  for (const theme of ['light', 'dark'] as const) await capture(page, info, 'review-blocked-persistent', theme);
  for (const width of coverWidths(info)) {
    await page.setViewportSize({ width, height: viewport(info).height });
    await trigger.click();
    await expect(pop.getByRole('button', { name: 'Unblock', exact: true })).toBeVisible();
    await expectMenuOnScreen(page, pop, `short row at ${width}px`, mobile);
    await trigger.click();
    await expect(menu).not.toHaveAttribute('open');
  }
  await page.setViewportSize(viewport(info));
  await page.evaluate(() => window.scrollTo(0, 0));
  await trigger.click();
  await capture(page, info, 'member-blocked-menu', 'light');
  await capture(page, info, 'member-blocked-menu', 'dark');

  await pop.getByRole('button', { name: 'Unblock', exact: true }).click();
  await page.waitForURL((url) => url.pathname === profileUrl);
  await expect(page.locator('.flash')).toHaveText('Unblocked.');
  await expect(actions.getByRole('button', { name: 'Follow', exact: true })).toBeVisible();
  await expect(actions.getByRole('link', { name: 'Message', exact: true })).toBeVisible();
  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
});
