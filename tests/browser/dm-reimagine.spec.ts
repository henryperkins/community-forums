import { test, expect, type Page, type Browser } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Phase 1 evidence for the DM "one reading room" reimagine (de-box).
 *
 * Drives the real server-rendered app in Chromium and proves the reimagined
 * Messages surface renders — and still works with JavaScript disabled:
 *   • list: de-boxed rows, the round `+`, a lone gold unread dot;
 *   • conversation: grouped "letters" (consecutive messages share one author
 *     line; mine wear the one gold plate, theirs read plain), the hairline day
 *     divider, and the hover ··· report control;
 *   • no-JS: the same page renders and the report <details> still opens.
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
  const aliceCtx = await browser.newContext({ baseURL: baseURL! });
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
