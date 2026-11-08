import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Fluent System Icons and the member's icon size (ADR 0060, USER §4.1).
 *
 * Appearance → Icon size draws standard icons at 16px or larger icons at 20px
 * wherever an icon takes the shared --icon-size: menus, toolbars, rails and
 * buttons. Marks sized for rows, chips, reactions and empty states keep their
 * own sizes, and the settings rail draws the current section with Fluent's
 * Filled artwork. Runs against the design's own dataset
 * (tests/browser/forum-inbox-fixture.php).
 *
 *   DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
 *   npx playwright test fluent-icons.spec.ts
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const OUT = process.env.RB_EVIDENCE_DIR
  ? path.resolve(repoRoot, process.env.RB_EVIDENCE_DIR)
  : path.resolve(repoRoot, 'docs/evidence/fluent-icons-2026-11-08');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

// One member's preference is changed and restored, so the tests run in order.
test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  if (process.env.RB_SKIP_FIXTURE === '1') return;
  execFileSync('php', ['tests/browser/forum-inbox-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
    stdio: 'inherit',
  });
});

test.afterAll(async ({ browser }) => {
  // Leave the fixture member on standard icons for every other spec.
  const page = await browser.newPage();
  await signIn(page);
  await setIconSize(page, 'standard');
  await page.close();
});

function shot(project: string, name: string) {
  fs.mkdirSync(OUT, { recursive: true });
  return path.join(OUT, `${project}-${name}.png`);
}

async function signIn(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'erestor@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('form.auth-form button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

async function setIconSize(page: Page, size: 'standard' | 'large') {
  await page.goto('/settings/appearance');
  await page.locator('label.choice-card', { hasText: size === 'large' ? 'Larger' : 'Standard' }).click();
  await expect(page.locator(`input[name="icon_size"][value="${size}"]`)).toBeChecked();
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL((u) => u.pathname === '/settings/appearance');
  await expect(page.locator('html')).toHaveAttribute('data-icon-size', size);
}

// The Inbox opens a title in its reading pane; go to the topic page itself.
async function openTopic(page: Page) {
  const href = await page.locator('[data-inbox-row] a.thread-title').first().getAttribute('href');
  expect(href).toMatch(/^\/t\//);
  await page.goto(href!);
  await expect(page.locator('[data-post-toolbar]').first()).toBeVisible();
}

async function iconBox(icon: Locator) {
  return icon.evaluate((svg) => {
    const box = svg.getBoundingClientRect();
    const style = getComputedStyle(svg);
    return {
      width: Math.round(box.width * 100) / 100,
      height: Math.round(box.height * 100) / 100,
      viewBox: svg.getAttribute('viewBox'),
      fill: style.fill,
      stroke: style.stroke,
      d: svg.querySelector('path')?.getAttribute('d') ?? '',
    };
  });
}

test('Appearance offers standard and larger icons, each previewed at its own size', async ({ page }, info) => {
  await signIn(page);
  await setIconSize(page, 'standard');

  const standardPreview = page.locator('.icon-size-prev:not(.is-large) .icon').first();
  const largePreview = page.locator('.icon-size-prev.is-large .icon').first();
  expect(await iconBox(standardPreview)).toMatchObject({ width: 16, viewBox: '0 0 16 16' });
  expect(await iconBox(largePreview)).toMatchObject({ width: 20, viewBox: '0 0 20 20' });
  // Fluent artwork is filled outlines painted with the text colour.
  const painted = await iconBox(standardPreview);
  expect(painted.fill).not.toBe('none');
  expect(painted.stroke).toBe('none');

  // The page carries a desktop rail and a phone disclosure; measure the one shown.
  const railIcon = (p: Page) => p.locator('.settings-rail-link[data-settings-key="appearance"]:visible .icon').first();
  const rail = railIcon(page);
  const railVisible = await rail.isVisible();
  if (railVisible) {
    expect(await iconBox(rail)).toMatchObject({ width: 16, viewBox: '0 0 16 16' });
  }
  await page.locator('.settings').screenshot({ path: shot(info.project.name, 'appearance-standard') });

  await setIconSize(page, 'large');
  // The previews keep their own sizes whatever the member chose.
  expect(await iconBox(standardPreview)).toMatchObject({ width: 16, viewBox: '0 0 16 16' });
  expect(await iconBox(largePreview)).toMatchObject({ width: 20, viewBox: '0 0 20 20' });
  if (railVisible) {
    const active = await iconBox(rail);
    expect(active).toMatchObject({ width: 20, viewBox: '0 0 20 20' });
    // The current section is drawn Filled; the same link elsewhere is Regular.
    await page.goto('/settings/privacy');
    const inactive = await iconBox(railIcon(page));
    expect(inactive.viewBox).toBe('0 0 20 20');
    expect(inactive.d).not.toBe(active.d);
    await page.goto('/settings/appearance');
  }
  await page.locator('.settings').screenshot({ path: shot(info.project.name, 'appearance-larger') });

  await page.emulateMedia({ colorScheme: 'dark' });
  await page.locator('.settings').screenshot({ path: shot(info.project.name, 'appearance-larger-dark') });
  await page.emulateMedia({ colorScheme: 'light' });
});

test('larger icons grow menus, toolbars and buttons but not row marks', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'Measured at the desktop layout; the phone layout is captured above and below.');
  await signIn(page);

  const measure = async () => {
    await page.goto('/inbox');
    const bell = await iconBox(page.locator('.forum-bar [data-bell] .icon'));
    const star = await iconBox(page.locator('[data-inbox-row] .star-toggle .icon').first());
    const chips = page.locator('.thread-row-inbox .thread-row-chips .icon');
    const chip = (await chips.count()) > 0 ? await iconBox(chips.first()) : null;
    await page.locator('details.identity-menu > summary').click();
    const menu = await iconBox(page.locator('.identity-menu-panel a[href="/drafts"] .icon, .identity-menu-panel a[href^="/u/"] .icon').first());
    await page.locator('details.identity-menu > summary').click();
    await openTopic(page);
    const toolbar = await iconBox(page.locator('[data-post-toolbar] .post-toolbar-button .icon').first());
    return { bell, star, chip, menu, toolbar };
  };

  await setIconSize(page, 'large');
  const large = await measure();
  expect(large.menu).toMatchObject({ width: 20, viewBox: '0 0 20 20' });
  expect(large.toolbar).toMatchObject({ width: 20, viewBox: '0 0 20 20' });
  expect(large.bell.width).toBe(20);
  expect(large.star.width).toBe(18);
  if (large.chip) expect(large.chip.width).toBe(9);
  await page.locator('[data-post-toolbar]').first().screenshot({ path: shot(info.project.name, 'post-toolbar-larger') });

  await setIconSize(page, 'standard');
  const standard = await measure();
  expect(standard.menu).toMatchObject({ width: 16, viewBox: '0 0 16 16' });
  expect(standard.toolbar).toMatchObject({ width: 16, viewBox: '0 0 16 16' });
  expect(standard.bell.width).toBe(18);
  expect(standard.star.width).toBe(18);
  if (standard.chip) expect(standard.chip.width).toBe(9);
  await page.locator('[data-post-toolbar]').first().screenshot({ path: shot(info.project.name, 'post-toolbar-standard') });
});

test('icons stay filled inside containers whose older rules stroke an svg', async ({ page }) => {
  // `.fab svg`, `.chip svg`, `.accepted-flag svg` and `.nav-toggle-ic` were
  // written for the retired stroked glyphs; the Forum theme keeps them off
  // the partial's filled artwork.
  await signIn(page);
  const outlined = () => page.locator('svg.icon').evaluateAll((icons) => icons
    .filter((svg) => [svg, ...svg.querySelectorAll('path')]
      .map((el) => getComputedStyle(el))
      .some((style) => style.fill === 'none' || style.stroke !== 'none'))
    .map((svg) => `${svg.getAttribute('class')} in .${String(svg.parentElement?.className ?? '').trim()}`));

  await page.goto('/c/general');
  await expect(page.locator('.fab .icon')).toHaveCount(1);
  await expect(page.locator('.nav-toggle .icon').first()).toBeAttached();
  expect(await outlined(), '/c/general').toEqual([]);

  await page.goto('/c/interpretability');
  await expect(page.locator('.chip-solved .icon').first()).toBeAttached();
  expect(await outlined(), '/c/interpretability').toEqual([]);
  const solved = await page.getByRole('link', { name: 'Interpretability findings from the Bruinen run' }).first().getAttribute('href');
  expect(solved).toMatch(/^\/t\//);
  await page.goto(solved!);
  await expect(page.locator('.accepted-flag .icon')).toHaveCount(1);
  expect(await outlined(), solved!).toEqual([]);

  for (const route of ['/inbox', '/leaderboard', '/u/galadriel', '/settings/appearance']) {
    await page.goto(route);
    expect(await outlined(), route).toEqual([]);
  }
});

for (const size of ['standard', 'large'] as const) {
  test(`member surfaces draw Fluent icons at ${size} size`, async ({ page }, info) => {
    await signIn(page);
    await setIconSize(page, size);
    for (const scheme of ['light', 'dark'] as const) {
      await page.emulateMedia({ colorScheme: scheme });
      await page.goto('/inbox');
      await expect(page.locator('html')).toHaveAttribute('data-icon-size', size);
      await page.screenshot({ path: shot(info.project.name, `inbox-${size}-${scheme}`) });
      await openTopic(page);
      await page.screenshot({ path: shot(info.project.name, `topic-${size}-${scheme}`) });
    }
    await page.emulateMedia({ colorScheme: 'light' });
  });
}
