import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Forum inbox remediation evidence (ADR 0029).
 *
 * The transfer's own capture was a 924px slice of a queue holding ONE topic, so
 * nothing it recorded could disagree with the design: a queue with one row shows
 * no chip that is not that row's, no order the reader did not pick, and no empty
 * state at all. Every capture here runs against tests/browser/forum-inbox-fixture.php,
 * which reproduces the design's own sixteen topics across its eight boards with
 * every personal signal represented, and each screenshot is written by an
 * assertion rather than taken beside one.
 *
 *   DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
 *   DB_DATABASE=retroboards_e2e php tests/browser/forum-inbox-fixture.php
 *   npx playwright test forum-inbox-remediation.spec.ts --project=desktop
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const OUT = path.resolve(repoRoot, 'docs/evidence/imladris-forum-inbox-remediation');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

// The design's own dataset, seeded the way member-surfaces.spec.ts seeds its
// own. Set RB_SKIP_FIXTURE=1 when pointing RB_BASE_URL at a database somebody
// else has already prepared.
test.beforeAll(() => {
  if (process.env.RB_SKIP_FIXTURE === '1') return;
  execFileSync('php', ['tests/browser/forum-inbox-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
    stdio: 'inherit',
  });
});

function shot(name: string) {
  fs.mkdirSync(OUT, { recursive: true });
  return path.join(OUT, name);
}

async function signIn(page: Page, email = 'erestor@retro.test') {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

async function setAppearance(page: Page, field: 'theme' | 'density', value: string) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="${field}"][value="${value}"]`).check({ force: true });
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

/** WCAG relative luminance / contrast, over the two rgb() strings the DOM gives back. */
function contrast(a: string, b: string): number {
  const lum = (css: string) => {
    const [r, g, bl] = css.match(/\d+(\.\d+)?/g)!.slice(0, 3).map((n) => Number(n) / 255);
    const f = (c: number) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(bl);
  };
  const [x, y] = [lum(a), lum(b)];
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

/**
 * The inclusion cue answers "why am I being shown this?". `.chip-reason` had no
 * rules at all, so it fell through to the evergreen status pill and shouted a
 * whole sentence in caps.
 */
test('the inclusion cue is gold and in sentence case, unlike the status pills', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await setAppearance(page, 'density', 'compact');
  await page.goto('/inbox?scope=for_you&order=active');

  const reason = page.locator('.inbox-row-chips .chip-reason').first();
  await expect(reason).toBeVisible();
  const seen = await reason.evaluate((el) => {
    const s = getComputedStyle(el);
    return { transform: s.textTransform, size: s.fontSize, color: s.color, text: el.textContent?.trim() };
  });
  expect(seen.transform).toBe('none');
  expect(seen.text).toMatch(/^[A-Z][a-z]/);
  // Gold ink, not the evergreen the base .chip carries.
  expect(seen.color).toBe('rgb(126, 95, 34)');

  // A status pill on the same row keeps the lapidary uppercase register.
  const status = page.locator('.inbox-row-chips .chip-pinned, .inbox-row-chips .chip-decision_made').first();
  await expect(status).toHaveCSS('text-transform', 'uppercase');

  // And the brand star inside the cue is filled, not the hollow Lucide stroke
  // `.chip svg` would otherwise impose on it.
  const star = reason.locator('svg');
  await expect(star).toHaveCSS('fill', 'rgb(126, 95, 34)');

  await page.screenshot({ path: shot('01-queue-desktop-light.png') });
});

/** The unread count states a fact in words; `.badge` was shouting it. */
test('the unread count is a sentence, not a shout', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');
  const pill = page.locator('.inbox-list-head .badge');
  await expect(pill).toBeVisible();
  await expect(pill).toHaveCSS('text-transform', 'none');
  await expect(pill).toHaveText(/^\d+ unread$/);
});

/** The design rules the Viewing bar along its bottom edge only. */
test('the viewing bar carries one rule, beneath it', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');
  const bar = page.locator('.inbox-view-bar');
  await expect(bar).toHaveCSS('border-top-width', '0px');
  await expect(bar).toHaveCSS('border-bottom-width', '1px');

  // The density statement qualifies the bar it sits in and names the register
  // the reader actually has, exactly as the board index states it.
  const density = page.locator('.inbox-density');
  await expect(density).toHaveText(/^(Compact|Comfortable) rows change$/);
  await expect(density).toHaveCSS('flex-grow', '0');
});

/**
 * Commends are the Commended order's own column. Printed in every order they
 * were a fourth statistic competing for the meta line, which then wrapped.
 */
test('commends appear in the commended order and nowhere else', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');
  await expect(page.locator('.inbox-row-commends')).toHaveCount(0);

  await page.goto('/inbox?scope=for_you&order=commended');
  await expect(page.locator('.inbox-row-commends').first()).toBeVisible();

  // One line, still: the meta row must not have wrapped.
  const meta = page.locator('.inbox-row-meta').first();
  const box = (await meta.boundingBox())!;
  const lineHeight = await meta.evaluate((el) => parseFloat(getComputedStyle(el).lineHeight));
  expect(box.height).toBeLessThan(lineHeight * 1.8);

  await page.screenshot({ path: shot('02-queue-commended-order.png') });
});

/**
 * A board reference is a citation of the record; the design spends its one
 * --artifact-link on it. river-500 is 3.08:1 on the twilight page, so the token
 * has to climb in that register the way --info does.
 */
test('the board reference is Bruinen, and legible in both registers', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);

  await page.goto('/inbox?scope=for_you&order=active');
  const link = page.locator('.inbox-row-meta a').first();
  await expect(link).toHaveCSS('color', 'rgb(63, 110, 137)');
  let seen = await link.evaluate((el) => ({
    fg: getComputedStyle(el).color,
    bg: getComputedStyle(el.closest('.inbox-list') as HTMLElement).backgroundColor,
  }));
  expect(contrast(seen.fg, seen.bg)).toBeGreaterThanOrEqual(4.5);

  await setAppearance(page, 'theme', 'dark');
  await page.goto('/inbox?scope=for_you&order=active');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  seen = await page.locator('.inbox-row-meta a').first().evaluate((el) => ({
    fg: getComputedStyle(el).color,
    bg: getComputedStyle(el.closest('.inbox-list') as HTMLElement).backgroundColor,
  }));
  expect(contrast(seen.fg, seen.bg)).toBeGreaterThanOrEqual(4.5);

  await page.screenshot({ path: shot('03-queue-desktop-twilight.png') });
  await setAppearance(page, 'theme', 'light');
});

/**
 * THE READING PANE. The design states who is speaking before it prints what
 * they said, and sets the opening post as the topic's lede rather than the first
 * row of the reply list. The transfer had neither, so a topic could be read here
 * without ever learning whose it was.
 */
test('the reading pane names the topic author and leads with the opening post', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');
  await page.locator('.inbox-row-title').first().click();

  const pane = page.locator('.inbox-reading');
  await expect(pane.locator('.inbox-preview-author')).toBeVisible();
  await expect(pane.locator('.inbox-preview-attribution .monogram')).toBeVisible();
  await expect(pane.locator('.inbox-preview-lede')).toBeVisible();

  // The byline rules off the header, and the reply count is pinned to its right.
  const row = pane.locator('.inbox-preview-attribution');
  await expect(row).toHaveCSS('border-bottom-width', '1px');
  const rowBox = (await row.boundingBox())!;
  const countBox = (await pane.locator('.inbox-preview-count').boundingBox())!;
  expect(rowBox.x + rowBox.width - (countBox.x + countBox.width)).toBeLessThan(120);

  // The lede sits above the replies, and the opening post is not one of them.
  const ledeBox = (await pane.locator('.inbox-preview-lede').boundingBox())!;
  const first = pane.locator('.inbox-preview-posts > li').first();
  if (await first.count()) {
    const firstBox = (await first.boundingBox())!;
    expect(firstBox.y).toBeGreaterThan(ledeBox.y + ledeBox.height - 1);
  }

  // The kicker times the topic the way the row that opened it does.
  await expect(pane.locator('.inbox-preview-kicker time')).toHaveText(/ago|just now/);

  // The column keeps the measure a topic gets on its own page.
  const width = await page
    .locator('.inbox-reading > [data-inbox-reading-content]')
    .evaluate((el) => parseFloat(getComputedStyle(el).maxWidth));
  expect(width).toBe(760);

  await page.screenshot({ path: shot('04-reading-pane.png') });
});

/**
 * The pane prints an author now, so it has to be able to prove it prints the
 * mask instead — and withholds the rank, which would narrow the field the mask
 * exists to widen.
 */
test('an anonymously opened topic is masked in the reading pane', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await page.goto('/inbox?scope=starred&order=newest');

  const row = page.locator('[data-inbox-row]', {
    has: page.getByText('A question I would rather not sign'),
  }).first();
  await expect(row).toBeVisible();
  await row.locator('.inbox-row-title').click();

  const pane = page.locator('.inbox-reading');
  await expect(pane.locator('.inbox-preview-author')).toHaveText('Anonymous');
  await expect(pane.locator('.inbox-preview-tier')).toHaveCount(0);
  await expect(pane).not.toContainText('Lindir');

  await page.screenshot({ path: shot('05-reading-pane-anonymous.png') });
});

/**
 * Nothing chosen yet is a quiet state in the middle of an empty pane. The mark
 * was an <img>, which cannot take currentColor, sized 56px by a rule the rewrite
 * left upstream of its own replacement.
 */
test('the quiet state is centred, gold, and 30px', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');

  const empty = page.locator('.inbox-reading .inbox-empty');
  await expect(empty).toBeVisible();

  const star = empty.locator('.inbox-empty-star');
  await expect(star).toBeVisible();
  const mark = await star.evaluate((el) => {
    const s = getComputedStyle(el);
    return { tag: el.tagName.toLowerCase(), w: parseFloat(s.width), fill: s.fill };
  });
  expect(mark.tag).toBe('svg');
  expect(mark.w).toBeLessThanOrEqual(32);
  expect(mark.fill).toBe('rgb(126, 95, 34)');

  // Centred in the pane, not stacked at the top of it.
  const paneBox = (await page.locator('.inbox-reading').boundingBox())!;
  const box = (await empty.boundingBox())!;
  const above = box.y - paneBox.y;
  const below = paneBox.y + paneBox.height - (box.y + box.height);
  expect(Math.abs(above - below)).toBeLessThan(120);

  await page.screenshot({ path: shot('06-reading-pane-quiet-state.png') });
});

/**
 * The queue's own empty state had no rules at all. Under a strict CSP an
 * unstyled div is a star, a heading and a paragraph against the left gutter.
 */
test('the empty queue is a composed state, not an unstyled div', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1100 });
  await signIn(page, 'bob@retro.test');
  await page.goto('/inbox?scope=mentions&order=active');

  const state = page.locator('.inbox-empty-state');
  await expect(state).toBeVisible();
  await expect(state).toHaveCSS('text-align', 'center');
  expect(await state.evaluate((el) => parseFloat(getComputedStyle(el).paddingTop))).toBeGreaterThan(24);
  await expect(state.locator('.inbox-empty-title')).toBeVisible();
  await expect(state.locator('svg')).toBeVisible();
  expect(await state.locator('svg').evaluate((el) => parseFloat(getComputedStyle(el).width))).toBeLessThanOrEqual(32);

  await page.screenshot({ path: shot('07-empty-scope.png') });
});

/** Rows are separated by their own hairline; a grid gap drew a second channel. */
test('one hairline separates two rows', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');

  const rows = page.locator('[data-inbox-row]');
  expect(await rows.count()).toBeGreaterThan(2);
  const a = (await rows.nth(0).boundingBox())!;
  const b = (await rows.nth(1).boundingBox())!;
  // Touching: the next row starts where the previous one's hairline ends.
  expect(b.y - (a.y + a.height)).toBeLessThan(0.5);
  // And nothing adds a second channel on top of that hairline.
  const gap = await page.locator('.inbox-thread-list').evaluate((el) => {
    const s = getComputedStyle(el);
    return { display: s.display, row: s.rowGap };
  });
  expect(gap.display).not.toBe('grid');
  expect(['normal', '0px']).toContain(gap.row);
});

/**
 * Compact is the triage register, and the design states it in four rules its own
 * inline row styles then outrank. Production applies what those rules say.
 */
test('compact density applies the register the design states', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await signIn(page);
  await setAppearance(page, 'density', 'compact');
  await page.goto('/inbox?scope=for_you&order=active');
  await expect(page.locator('html')).toHaveAttribute('data-density', 'compact');

  const seen = await page.locator('[data-inbox-row]').first().evaluate((el) => {
    const row = getComputedStyle(el);
    const title = getComputedStyle(el.querySelector('.inbox-row-title')!);
    const meta = getComputedStyle(el.querySelector('.inbox-row-meta')!);
    const chips = getComputedStyle(el.querySelector('.inbox-row-chips')!);
    return {
      pad: row.paddingTop, gap: row.gap, title: title.fontSize,
      metaTop: meta.marginTop, metaSize: meta.fontSize, chipGap: chips.gap,
    };
  });
  expect(seen.pad).toBe('4px');
  expect(seen.gap).toBe('9px');
  expect(seen.title).toBe('15.84px');   // .99rem
  expect(seen.metaTop).toBe('1px');
  expect(seen.metaSize).toBe('10.72px'); // .67rem
  expect(seen.chipGap).toBe('5px');

  await expect(page.locator('.inbox-row-snippet').first()).toBeHidden();

  await setAppearance(page, 'density', 'comfortable');
  await page.goto('/inbox?scope=for_you&order=active');
  await expect(page.locator('.inbox-row-snippet').first()).toBeVisible();
  await page.screenshot({ path: shot('08-queue-comfortable.png') });
  await setAppearance(page, 'density', 'compact');
});

/**
 * On a phone the dot is the only unread cue a row has — `.is-unread` styles
 * nothing — so hiding it left an unread-triage surface with no unread signal.
 */
test('the unread dot survives on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 900 });
  await signIn(page);
  await page.goto('/inbox?scope=unread&order=active');

  const dot = page.locator('[data-inbox-row][data-inbox-unread="1"] .unread-dot').first();
  await expect(dot).toBeVisible();
  const size = await dot.evaluate((el) => el.getBoundingClientRect().width);
  expect(size).toBeGreaterThan(4);

  // The reading pane stands down; the queue owns the viewport.
  await expect(page.locator('.inbox-reading')).toBeHidden();
  await page.screenshot({ path: shot('09-phone-queue.png') });
});
