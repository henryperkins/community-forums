import { test, expect, Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Board index remediation evidence (ADR 0028).
 *
 * The transfer's own capture was a 924x540 slice that stopped above the first
 * complete board row, which is exactly why the two P0 defects shipped: the peek
 * list rendering beside its board row, and three panes with no CSS at all. Every
 * capture here is deliberately tall enough to show at least two complete board
 * rows WITH their peek lists, and each pane gets its own frame.
 */

const OUT = path.resolve(__dirname, '../../docs/evidence/imladris-board-index-remediation');

// Defaults to the project's own webServer. Set RB_BASE_URL to point the capture
// at a separately served instance (a private evidence database, say).
if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

function shot(name: string) {
  fs.mkdirSync(OUT, { recursive: true });
  return path.join(OUT, name);
}

async function signIn(page: Page, email = 'admin@retro.test') {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

/** The appearance radios are visually replaced by their .choice-card labels. */
async function setAppearance(page: Page, field: 'theme' | 'density', value: string) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="${field}"][value="${value}"]`).check({ force: true });
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

/**
 * THE P0. A board row and the peek list beneath it must be one column. The
 * superseded `.board-index .forum-directory__board` rule made the <article> a
 * two-column grid, so the peek landed to the RIGHT of the row.
 */
test('the peek list sits beneath its board row, not beside it', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 1400 });
  await page.goto('/?sort=active&peek=3');

  const board = page.locator('[data-directory-board]').first();
  await expect(board).toBeVisible();

  const row = board.locator('.forum-directory__board-row');
  const peek = board.locator('.forum-directory__peek');
  await expect(peek).toBeVisible();

  const rowBox = (await row.boundingBox())!;
  const peekBox = (await peek.boundingBox())!;

  // Beneath: the peek starts below the row's bottom edge...
  expect(peekBox.y).toBeGreaterThanOrEqual(rowBox.y + rowBox.height - 1);
  // ...and shares the row's left edge rather than starting in a second column.
  expect(Math.abs(peekBox.x - rowBox.x)).toBeLessThan(40);
  // The <article> must not be a grid with a second track.
  const cols = await board.evaluate((el) => getComputedStyle(el).gridTemplateColumns);
  expect(cols === 'none' || cols.split(' ').length === 1).toBeTruthy();

  await page.screenshot({ path: shot('01-directory-desktop-light.png'), fullPage: false });
});

/**
 * `.main > .read-main { margin: -24px }` cancels `.main`'s own 24px padding, but
 * the boards route zeroes that padding — leaving the negative margin to drag the
 * column left AND, as a shorthand at higher specificity, to override
 * `.board-index { margin: 0 auto }`. The reading column could never centre.
 */
test('the reading column is centred in its pane', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await page.goto('/?sort=category&peek=3');

  const gaps = await page.evaluate(() => {
    const col = document.querySelector('.board-index') as HTMLElement;
    const pane = col.parentElement as HTMLElement;
    const c = col.getBoundingClientRect();
    const p = pane.getBoundingClientRect();
    return { left: Math.round(c.left - p.left), right: Math.round(p.right - c.right) };
  });

  expect(gaps.left).toBeGreaterThan(0);
  expect(Math.abs(gaps.left - gaps.right)).toBeLessThanOrEqual(2);
});

/**
 * The design sets the board name to its own width and lets the description
 * follow 16px later. A `minmax(150px, .55fr)` grid track instead padded every
 * short name out and pushed its description ~90px clear of it.
 */
test('a board description sits beside its name, not a column away', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1200 });
  await page.goto('/?sort=category&peek=3');

  const row = page.locator('[data-directory-board]', {
    has: page.locator('.forum-directory__board-description'),
  }).first();
  const name = row.locator('.forum-directory__board-name');
  const desc = row.locator('.forum-directory__board-description');

  const nameBox = (await name.boundingBox())!;
  const descBox = (await desc.boundingBox())!;

  const gap = descBox.x - (nameBox.x + nameBox.width);
  expect(gap).toBeGreaterThanOrEqual(0);
  expect(gap).toBeLessThanOrEqual(24);

  // And the counts stay pinned to the row's right edge.
  const facts = (await row.locator('.forum-directory__board-facts').boundingBox())!;
  const anchor = (await row.locator('.forum-directory__board-row > a').boundingBox())!;
  expect(Math.abs((anchor.x + anchor.width) - (facts.x + facts.width))).toBeLessThanOrEqual(2);
});

test('the directory reads correctly in twilight', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 1400 });
  await signIn(page);
  await setAppearance(page, 'theme', 'dark');
  await page.goto('/?sort=top&peek=3');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await expect(page.locator('[data-directory-board]').first()).toBeVisible();
  await page.screenshot({ path: shot('02-directory-desktop-twilight.png') });
});

/**
 * Compact is the triage register: the description goes, the reader's Peek
 * choice stays. Hiding the peek made the Viewing bar's Peek control a no-op.
 */
test('compact density drops the description and keeps the chosen peek', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 1200 });
  await signIn(page);
  await setAppearance(page, 'density', 'compact');

  await page.goto('/?sort=active&peek=3');
  await expect(page.locator('html')).toHaveAttribute('data-density', 'compact');

  const board = page.locator('[data-directory-board]').first();
  await expect(board.locator('.forum-directory__peek')).toBeVisible();
  await expect(board.locator('.forum-directory__board-description')).toBeHidden();

  await page.screenshot({ path: shot('03-directory-compact-keeps-peek.png') });
});

test('the three account-adjacent panes are styled', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 1100 });
  await signIn(page);

  for (const [pane, file] of [
    ['tags', '04-pane-tags.png'],
    ['notices', '05-pane-notices.png'],
    ['connections', '06-pane-connections.png'],
  ] as const) {
    await page.goto(`/?pane=${pane}`);
    await expect(page.locator(`[data-directory-pane="${pane}"]`)).toBeVisible();

    // An unstyled <ul> keeps the UA's disc marker and 40px indent. A styled one
    // does not — this is the assertion the missing CSS would have failed.
    const list = page.locator('.directory-light-pane ul').first();
    if (await list.count()) {
      const style = await list.evaluate((el) => {
        const s = getComputedStyle(el);
        return { marker: s.listStyleType, pad: s.paddingInlineStart };
      });
      expect(style.marker).toBe('none');
      expect(parseFloat(style.pad)).toBeLessThan(40);
    }
    await page.screenshot({ path: shot(file) });
  }
});

/**
 * Without JavaScript a <details> closes only via its own <summary>. The scrim is
 * a child of that <details> and painted over it, so the sheet was a trap.
 */
test('the phone viewing sheet can be closed again', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto('/?sort=active&peek=3');

  const sheet = page.locator('.directory-viewbar-mobile');
  const summary = sheet.locator('> summary');
  await expect(summary).toBeVisible();

  await summary.click();
  await expect(sheet).toHaveAttribute('open', '');
  await page.screenshot({ path: shot('07-phone-viewing-sheet-open.png') });

  // The summary must still be the topmost element at its own centre, or there is
  // no way back out of the sheet.
  const box = (await summary.boundingBox())!;
  const topmostIsSummary = await page.evaluate(
    ([x, y]) => {
      const el = document.elementFromPoint(x, y);
      return !!el?.closest('summary');
    },
    [box.x + box.width / 2, box.y + box.height / 2] as const,
  );
  expect(topmostIsSummary).toBeTruthy();

  await summary.click();
  await expect(sheet).not.toHaveAttribute('open', '');
  await page.screenshot({ path: shot('08-phone-viewing-sheet-closed.png') });
});

test('search stays reachable from the shell on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 900 });
  await signIn(page);
  await page.goto('/');

  const entry = page.locator('.topbar-search-entry');
  await expect(entry).toBeVisible();
  await entry.click();
  await page.waitForURL(/\/search/);
  await page.screenshot({ path: shot('09-phone-search-reachable.png') });
});
