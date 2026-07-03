import { test, expect, type Page, type Browser } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Evidence for the DM "one reading room" reimagine — Phase 1 (de-box) and
 * Phase 2 (details rail + header ··· menu).
 *
 * Drives the real server-rendered app in Chromium and proves the reimagined
 * Messages surface renders — and still works with JavaScript disabled:
 *   • list: de-boxed rows, the round `+`, a lone gold unread dot;
 *   • conversation: grouped "letters" (consecutive messages share one author
 *     line; mine wear the one gold plate, theirs read plain), the hairline day
 *     divider, and the hover ··· report control;
 *   • the details rail as the reading room's third column, and the header's
 *     one ··· overflow (both native <details> — no-JS proof included);
 *   • the JS enhancement layer: the rail toggle collapses/restores (and
 *     persists) at wide widths, opens as a drawer+scrim at narrow widths, and
 *     the ··· menus dismiss on outside-click / Escape.
 *
 * Runs against a seeded DB (alice/bob/admin, all password123). Isolated from the
 * shared evidence DB via DB_DATABASE — see the npm/run command in the PR notes.
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/dm-reimagine/phase1');

function ensureDir(): void {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

async function shot(page: Page, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, `${name}.png`), fullPage: true });
}

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  // The onboarding tour is JS-only; dismiss it when present so it never covers a shot.
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click().catch(() => {});
  }
}

/** Start a DM (no-JS form post) and return the /messages/{id} path it redirects to. */
async function startDm(page: Page, to: string, body: string): Promise<string> {
  await page.goto('/messages/new');
  await page.fill('input[name="to"]', to);
  await page.fill('textarea[name="body"]', body);
  await page.getByRole('button', { name: 'Send message' }).click();
  await page.waitForURL(/\/messages\/\d+/);
  return new URL(page.url()).pathname;
}

async function reply(page: Page, convPath: string, body: string): Promise<void> {
  await page.goto(convPath);
  await page.fill('.dm-composer textarea[name="body"]', body);
  await page.locator('.dm-composer button[type="submit"]').click();
  await page.waitForURL(new RegExp(convPath.replace(/\//g, '\\/') + '(\\?|$)'));
}

test('DM reading room: de-boxed rows, grouped letters, no-JS report', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'Phase 1 evidence is captured at the desktop viewport.');
  ensureDir();

  // ── Seed the conversation over real HTTP (separate contexts avoid the
  //    same-page re-login cookie trap) ───────────────────────────────────────
  const aliceCtx = await browser.newContext({ baseURL: baseURL!, viewport: { width: 1440, height: 900 } });
  const alice = await aliceCtx.newPage();
  await login(alice, 'alice@retro.test');
  const convA = await startDm(alice, 'bob', 'First private counsel — the audit note holds.');
  // A second, consecutive message from alice: proves the author-run grouping.
  await reply(alice, convA, 'The rollback drill is drafted; I will attach it once the day is named.');

  const bobCtx = await browser.newContext({ baseURL: baseURL! });
  const bob = await bobCtx.newPage();
  await login(bob, 'bob@retro.test');
  await reply(bob, convA, 'Do that — and send me the rollback drill for the wardens.');

  // A second conversation alice never opens → a lone gold unread dot in her list.
  const adminCtx = await browser.newContext({ baseURL: baseURL! });
  const admin = await adminCtx.newPage();
  await login(admin, 'admin@retro.test');
  await startDm(admin, 'alice', 'A separate matter needs your counsel before the council convenes.');

  // ── List: de-boxed rows + round + + gold unread dot ───────────────────────
  await alice.goto('/messages');
  await expect(alice.locator('.dm-new-btn')).toBeVisible();
  await expect(alice.locator('.dm-unread-dot').first()).toBeVisible();
  await expect(alice.locator('.dm-group-meta')).toHaveCount(0); // the old participant-name line is gone
  await shot(alice, '01-list');

  // ── Conversation: grouped letters (mine gold plate, theirs plain), hairline
  //    day divider, lightened reference cards, hover ··· report ──────────────
  await alice.goto(convA);
  await expect(alice.getByText('Beginning of your counsel')).toBeVisible();
  await expect(alice.locator('.dm-group.mine .dm-body').first()).toBeVisible();
  await expect(alice.locator('.dm-group:not(.mine) .dm-body').first()).toBeVisible();
  await expect(alice.locator('.dm-bubble')).toHaveCount(0);     // de-boxed: no bordered bubble cards
  // Alice's two consecutive messages group under a single author line.
  await expect(alice.locator('.dm-group.mine').first().locator('.dm-ghead')).toHaveCount(1);
  await expect(alice.locator('.dm-group.mine .dm-line')).toHaveCount(2);
  // The details rail is the third column at wide widths; the header carries one
  // ··· overflow. Standing Mute/Leave buttons and the inline group panel are gone.
  await expect(alice.locator('.dm-inforail .dm-rail-name')).toHaveText('Bob Brooks');
  await expect(alice.locator('.dm-inforail')).toContainText('Mute conversation');
  await expect(alice.locator('.dm-inforail')).toContainText('Block Bob Brooks');
  await expect(alice.locator('.dm-thread-actions .dm-menu > summary')).toBeVisible();
  await expect(alice.locator('.dm-head-btn')).toHaveCount(0);
  await shot(alice, '02-conversation');

  // ── No-JS: the same page renders and the report <details> still opens ─────
  const nojsCtx = await browser.newContext({ baseURL: baseURL!, javaScriptEnabled: false });
  const nojs = await nojsCtx.newPage();
  await login(nojs, 'alice@retro.test');
  await nojs.goto(convA);
  await expect(nojs.locator('.dm-group .dm-body').first()).toBeVisible();
  // The hover ··· is a native <details>; clicking its summary reveals the report form with no JS.
  const menuSummary = nojs.locator('.dm-group:not(.mine) .dm-line-menu summary').first();
  await menuSummary.click();
  await expect(nojs.locator('.dm-report-form').first()).toBeVisible();
  await expect(nojs.locator('.dm-report-form select[name="reason_code"]').first()).toBeVisible();
  // The header ··· overflow is a native <details> — it opens with no JS.
  await nojs.locator('.dm-thread-actions .dm-menu > summary').click();
  await expect(nojs.locator('.dm-menu-pop')).toBeVisible();
  await expect(nojs.locator('.dm-menu-pop')).toContainText('Mute conversation');
  await shot(nojs, '03-conversation-no-js');

  // ── Dark theme: the reimagine uses only theme-aware :root tokens, so it must
  //    hold under a dark surface (no hardcoded colors) ────────────────────────
  const darkCtx = await browser.newContext({ baseURL: baseURL!, colorScheme: 'dark' });
  const dark = await darkCtx.newPage();
  await login(dark, 'alice@retro.test');
  await dark.goto(convA);
  await expect(dark.locator('.dm-group.mine .dm-body').first()).toBeVisible();
  await expect(dark.locator('.dm-group:not(.mine) .dm-body').first()).toBeVisible();
  await shot(dark, '04-conversation-dark');

  await Promise.all([aliceCtx.close(), bobCtx.close(), adminCtx.close(), nojsCtx.close(), darkCtx.close()]);
});

test('DM reading room: rail toggle, menu dismissal, mobile drawer (JS enhancement)', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'Exercised at the desktop viewport; the mobile drawer is checked via an explicit narrow context below.');
  ensureDir();

  const wideCtx = await browser.newContext({ baseURL: baseURL!, viewport: { width: 1440, height: 900 } });
  const wide = await wideCtx.newPage();
  await login(wide, 'alice@retro.test');
  const convB = await startDm(wide, 'bob', 'A note for the rail-toggle evidence run.');
  await wide.goto(convB);

  // ── Wide (>=1400px): the rail toggle collapses the third column and the
  //    preference survives a reload (localStorage) ──────────────────────────
  const railToggle = wide.locator('[data-rail-toggle]');
  await expect(wide.locator('.dm-inforail')).toBeVisible();
  await expect(railToggle).toHaveAttribute('aria-expanded', 'true');
  await railToggle.click();
  await expect(wide.locator('.dm-inforail')).toBeHidden();
  await expect(railToggle).toHaveAttribute('aria-expanded', 'false');
  await wide.reload();
  await expect(wide.locator('.dm-inforail')).toBeHidden('the collapsed preference persists across a reload');
  await railToggle.click();
  await expect(wide.locator('.dm-inforail')).toBeVisible();

  // ── Header ··· menu: opens, and dismisses on outside-click and on Escape ──
  const menuSummary = wide.locator('.dm-thread-actions .dm-menu > summary');
  await menuSummary.click();
  await expect(wide.locator('.dm-menu-pop')).toBeVisible();
  // Click into the message scroll area's padding — inert background, not the
  // thread title (which is a profile link for direct conversations and would
  // navigate away instead of merely testing the outside-click dismissal).
  await wide.locator('.dm-scroll').click({ position: { x: 10, y: 10 } });
  await expect(wide.locator('.dm-menu-pop')).toBeHidden();
  await menuSummary.click();
  await expect(wide.locator('.dm-menu-pop')).toBeVisible();
  await wide.keyboard.press('Escape');
  await expect(wide.locator('.dm-menu-pop')).toBeHidden();
  await shot(wide, '05-rail-toggle-collapsed-then-menu');

  await wideCtx.close();

  // ── Narrow (<=1399px): the rail opens as a drawer with a scrim, closed by
  //    the scrim click ─────────────────────────────────────────────────────
  const narrowCtx = await browser.newContext({ baseURL: baseURL!, viewport: { width: 1024, height: 800 } });
  const narrow = await narrowCtx.newPage();
  await login(narrow, 'alice@retro.test');
  await narrow.goto(convB);
  const narrowToggle = narrow.locator('[data-rail-toggle]');
  await expect(narrow.locator('.dm-inforail')).not.toBeInViewport();
  await narrowToggle.click();
  await expect(narrow.locator('.dm-inforail')).toBeInViewport();
  await expect(narrow.locator('[data-rail-scrim]')).toBeVisible();
  await shot(narrow, '06-rail-drawer-open-narrow');
  await narrow.locator('[data-rail-scrim]').click({ position: { x: 5, y: 5 } });
  await expect(narrow.locator('.dm-inforail')).not.toBeInViewport();

  await narrowCtx.close();
});
