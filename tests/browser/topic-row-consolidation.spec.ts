import AxeBuilder from '@axe-core/playwright';
import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * One topic row, one star (ADR 0035).
 *
 * The inbox rendered its own row partial with its own five-point star, the board
 * printed a literal ★, and the topic head drew the four-point commend star: two
 * rows for one object and three stars for one bookmark. The row is now one
 * partial with a presentation axis and the star is one control in two sizes.
 * Runs against the design's own dataset (tests/browser/forum-inbox-fixture.php).
 *
 *   DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
 *   npx playwright test topic-row-consolidation.spec.ts
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const OUT = path.resolve(repoRoot, 'docs/evidence/topic-row-consolidation');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

test.beforeAll(() => {
  if (process.env.RB_SKIP_FIXTURE === '1') return;
  execFileSync('php', ['tests/browser/forum-inbox-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
    stdio: 'inherit',
  });
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

function setFixtureTopicStatus(status: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$title = 'Retention windows for anonymised IPs';
$previous = $db->fetchValue('SELECT status FROM threads WHERE title = ? LIMIT 1', [$title]);
if ($previous === false) {
    throw new RuntimeException('Missing archived topic fixture.');
}
$db->run('UPDATE threads SET status = ? WHERE title = ?', [$argv[1], $title]);
echo $previous;
`;
  return execFileSync('php', ['-r', php, status], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
}

async function register(page: Page, density: 'comfortable' | 'compact') {
  await page.evaluate((d) => document.documentElement.setAttribute('data-density', d), density);
  await page.evaluate(() => document.fonts.ready);
  // The pointer is left where the sign-in button was; keep it off the rows.
  await page.mouse.move(0, 0);
}

const glyph = (el: Element) => {
  const svg = el.querySelector('svg')!;
  const style = getComputedStyle(svg);
  return {
    classes: svg.getAttribute('class'),
    fill: style.fill,
    stroke: style.stroke,
    color: style.color,
    box: svg.getBoundingClientRect().width,
  };
};

test('the queue star is the commend star, outlined until the topic is starred', async ({ page }, info) => {
  await signIn(page);
  await page.goto('/inbox');
  await register(page, 'comfortable');

  const starredRow = page.locator('[data-inbox-row][data-inbox-starred="1"]').first();
  const plainRow = page.locator('[data-inbox-row][data-inbox-starred="0"]').first();
  const on = starredRow.locator('.star-toggle');
  const off = plainRow.locator('.star-toggle');

  await expect(on).toHaveAttribute('aria-pressed', 'true');
  await expect(off).toHaveAttribute('aria-pressed', 'false');
  // The name carries the topic, because a list holds many of these buttons.
  const plainTitle = (await plainRow.locator('.thread-title').innerText()).trim();
  await expect(off).toHaveAccessibleName(`Star ${plainTitle}`);

  const onGlyph = await on.evaluate(glyph);
  const offGlyph = await off.evaluate(glyph);
  expect(onGlyph.classes).toBe('icon icon-commend-star');
  expect(offGlyph.classes).toBe('icon icon-commend-star is-outline');
  // Shape, not only ink: the two inks are close in lightness.
  expect(onGlyph.fill).not.toBe('none');
  expect(offGlyph.fill).toBe('none');
  expect(offGlyph.stroke).not.toBe('none');
  expect(onGlyph.color).not.toBe(offGlyph.color);

  await page.locator('[data-inbox-list]').screenshot({ path: shot(info.project.name, 'queue-stars') });
});

test('the archived status word is readable in both inbox themes', async ({ page }, info) => {
  const previous = setFixtureTopicStatus('archived');
  try {
    await signIn(page);
    await page.goto('/inbox?scope=starred');

    const row = page.locator('[data-inbox-row]').filter({ hasText: 'Retention windows for anonymised IPs' });
    await expect(row.locator('.chip-archived')).toHaveText('Archived');

    for (const theme of ['light', 'dark']) {
      await page.evaluate((value) => document.documentElement.setAttribute('data-theme', value), theme);
      const result = await new AxeBuilder({ page })
        .include('[data-inbox-row] .chip-archived')
        .withRules(['color-contrast'])
        .analyze();
      expect(result.violations, `${theme} inbox Archived contrast`).toEqual([]);
      await row.screenshot({ path: shot(info.project.name, `archived-${theme}`) });
    }
  } finally {
    setFixtureTopicStatus(previous);
  }
});

test('starring from the queue is a round trip that keeps one glyph and one name', async ({ page }) => {
  await signIn(page);
  await page.goto('/inbox');

  const row = page.locator('[data-inbox-row][data-inbox-starred="0"]').first();
  const id = await row.getAttribute('data-thread-id');
  const title = (await row.locator('.thread-title').innerText()).trim();

  await row.locator('.star-toggle').click();
  await page.waitForLoadState('domcontentloaded');
  const starred = page.locator(`[data-inbox-row][data-thread-id="${id}"]`);
  await expect(starred).toHaveAttribute('data-inbox-starred', '1');
  await expect(starred.locator('.star-toggle')).toHaveAttribute('aria-pressed', 'true');
  await expect(starred.locator('.star-toggle')).toHaveAccessibleName(`Star ${title}`);
  await expect(starred.locator('.star-toggle svg')).toHaveAttribute('class', 'icon icon-commend-star');

  // The topic head reads the same bookmark, at its labelled size.
  await page.goto(await starred.locator('.thread-title').getAttribute('href') ?? '/inbox');
  await expect(page.locator('.star-btn')).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('.star-btn svg.icon-commend-star')).toHaveCount(1);

  await page.goto('/inbox');
  await page.locator(`[data-inbox-row][data-thread-id="${id}"] .star-toggle`).click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator(`[data-inbox-row][data-thread-id="${id}"] .star-toggle`)).toHaveAttribute('aria-pressed', 'false');
});

test('the index marks a starred topic with the same star, quietly', async ({ page }, info) => {
  await signIn(page);
  await page.goto('/c/audit-trails');
  await register(page, 'comfortable');

  const row = page.locator('.thread-row-board').filter({ hasText: 'Retention windows for anonymised IPs' });
  const marker = row.locator('.thread-row-star .thread-star');
  await expect(marker).toHaveAttribute('role', 'img');
  await expect(marker).toHaveAccessibleName('Starred');
  await expect(marker.locator('svg')).toHaveAttribute('class', 'icon icon-commend-star');

  await page.locator('.board-topics-list').screenshot({ path: shot(info.project.name, 'board-marker') });
});

test('a long board title wraps in compact density instead of collapsing into single words', async ({ page }, info) => {
  await signIn(page);
  await page.goto('/c/audit-trails');
  await register(page, 'compact');

  const row = page.locator('.thread-row-board').filter({ hasText: 'Amending a decision after it has been cited' });
  const measured = await row.evaluate((el) => {
    const title = el.querySelector('.thread-title') as HTMLElement;
    const main = el.querySelector('.thread-row-main') as HTMLElement;
    const meta = el.querySelector('.thread-meta') as HTMLElement;
    const cs = getComputedStyle(title);
    return {
      mainDisplay: getComputedStyle(main).display,
      whiteSpace: cs.whiteSpace,
      textOverflow: cs.textOverflow,
      titleWidth: title.getBoundingClientRect().width,
      overflowing: title.scrollWidth > title.clientWidth + 1,
      metaBelowTitle: meta.getBoundingClientRect().top >= title.getBoundingClientRect().bottom - 1,
    };
  });
  expect(measured.mainDisplay).toBe('block');
  expect(measured.whiteSpace).toBe('normal');
  expect(measured.textOverflow).toBe('clip');
  expect(measured.overflowing).toBe(false);
  expect(measured.metaBelowTitle).toBe(true);
  expect(measured.titleWidth).toBeGreaterThan(info.project.name === 'mobile' ? 150 : 250);

  await page.locator('.board-topics-list').screenshot({ path: shot(info.project.name, 'board-compact') });
});

test('the default list names last activity the way the index and the queue do', async ({ page }, info) => {
  await signIn(page);
  await page.goto('/tags/conduct');
  await register(page, 'comfortable');

  const time = page.locator('.thread-list .thread-row .thread-meta time').first();
  await expect(time).toHaveAttribute('datetime', /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|\+00:00)$/);
  await expect(time).toHaveAttribute('title', / UTC$/);
  await expect(time).not.toHaveText(/ UTC$/);

  await page.locator('.thread-list').screenshot({ path: shot(info.project.name, 'default-list') });
});

test('no surface prints a star character', async ({ page }) => {
  await signIn(page);
  for (const url of ['/inbox', '/inbox?scope=starred', '/c/audit-trails', '/tags/conduct', '/settings/boards']) {
    await page.goto(url);
    const text = await page.locator('main').innerText();
    expect(text, url).not.toMatch(/[★☆]/);
  }
});

test('the queue star keeps its touch target on a phone', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'phone floor');
  await signIn(page);
  await page.goto('/inbox');
  await page.mouse.move(0, 0);
  const box = await page.locator('[data-inbox-row] .star-toggle').first().boundingBox();
  expect(box!.width).toBeGreaterThanOrEqual(34);
  expect(box!.height).toBeGreaterThanOrEqual(34);
  await page.locator('[data-inbox-list]').screenshot({ path: shot(info.project.name, 'queue-stars') });
});
