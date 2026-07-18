import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Acceptance evidence for the group_dms graduation (GA default-on 2026-07-18;
 * ADR 0022). Drives the bounded group-conversation member journey end-to-end
 * through the real server-rendered app — the committed browser/no-JS evidence
 * the 2026-07-13 readiness audit listed as the flag's last engineering gap.
 *
 * Posture note: the evidence seed's features override deliberately does NOT
 * mention group_dms, so this journey proves the GA DEFAULT answers with no
 * override (the wysiwyg-composer graduation precedent).
 *
 * Captures (docs/evidence/browser/<viewport>/):
 *   group-dms-01-created                     new group with the details rail open
 *   group-dms-02-validation-draft-preserved  422 re-render keeps the typed draft
 *   group-dms-03-owner-tools                 add/rename/transfer tools + history
 *   group-dms-04-membership-interval         a late joiner sees no pre-join history
 *   group-dms-05-left-readonly               a departed member's read-only view
 *   group-dms-06-report-queue                staff report queue (report-only access)
 *   group-dms-07-no-js                       the same surface with JavaScript off
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click().catch(() => {});
  }
}

/**
 * Below 1400px (both evidence viewports) the details rail is an off-canvas
 * drawer driven by :target; app.js wires [data-rail-toggle] to it. Open it
 * when it is not already in view.
 */
async function openRail(page: Page): Promise<void> {
  const rail = page.locator('.dm-inforail');
  try {
    await expect(rail).toBeInViewport({ timeout: 500 });
  } catch {
    await page.locator('[data-rail-toggle]').click();
  }
  await expect(rail).toBeInViewport();
}

async function createGroup(page: Page, to: string, title: string, body: string): Promise<string> {
  await page.goto('/messages/new');
  await page.fill('input[name="to"]', to);
  await page.fill('.dm-compose input[name="title"]', title);
  await page.fill('.dm-form textarea[name="body"]', body);
  await page.locator('.dm-form button[type="submit"]').click();
  await page.waitForURL(/\/messages\/\d+/);
  return new URL(page.url()).pathname;
}

async function replyInConversation(page: Page, convPath: string, body: string): Promise<void> {
  await page.goto(convPath);
  await page.fill('.dm-composer textarea[name="body"]', body);
  await page.locator('.dm-composer button[type="submit"]').click();
  await page.waitForURL(new RegExp(convPath.replace(/\//g, '\\/') + '(\\?|$)'));
}

test('group DMs: create, draft-preserving validation, owner actions, membership intervals, mute/leave, report', async ({ page }, info) => {
  const firstBody = 'Welcome to the council — the private group evidence run begins here.';
  const lateBody = 'After Dana joined — visible to everyone currently in the group.';

  // ── Create: alice starts a bounded group through the no-JS form ───────────
  await login(page, 'alice@retro.test');
  const convPath = await createGroup(page, 'bob, carol', 'Launch council', firstBody);
  await expect(page.locator('.dm-thread-eyebrow')).toContainText('Private group');
  await expect(page.locator('.dm-thread-title')).toContainText('Launch council');
  await openRail(page);
  await expect(page.locator('.dm-inforail .dm-rail-name')).toHaveText('Launch council');
  await expect(page.locator('.dm-inforail .dm-member')).toHaveCount(3);
  await expect(page.locator('.dm-inforail .dm-member', { hasText: '(you)' }).locator('.m-role')).toHaveText('Owner');
  await expect(page.locator('.dm-inforail .dm-owner-tool')).toHaveCount(2);
  await shot(page, info, 'group-dms-01-created');

  // ── Validation keeps the typed draft: an unknown recipient 422-re-renders
  //    the form with the title and body intact (anti-draft-loss) ─────────────
  await page.goto('/messages/new');
  await page.fill('input[name="to"]', 'bob, no_such_member');
  await page.fill('.dm-compose input[name="title"]', 'Doomed council');
  await page.fill('.dm-form textarea[name="body"]', 'This typed draft must survive validation.');
  await page.locator('.dm-form button[type="submit"]').click();
  await expect(page.locator('.field-error')).toContainText('No member found with the username "no_such_member"');
  await expect(page.locator('.dm-compose input[name="title"]')).toHaveValue('Doomed council');
  await expect(page.locator('.dm-form textarea[name="body"]')).toHaveValue('This typed draft must survive validation.');
  await shot(page, info, 'group-dms-02-validation-draft-preserved');

  // ── Owner actions: add a member, rename, and read the group history ───────
  await page.goto(convPath);
  await openRail(page);
  await page.fill('.dm-inforail input[name="username"]', 'dana');
  await page.locator('.dm-inforail .dm-owner-tool').filter({ has: page.locator('input[name="username"]') }).getByRole('button', { name: 'Add' }).click();
  await expect(page.locator('.flash')).toContainText('Member added.');
  await openRail(page);
  await page.fill('.dm-inforail input[name="title"]', 'Launch council — war room');
  await page.locator('.dm-inforail .dm-owner-tool').filter({ has: page.locator('input[name="title"]') }).getByRole('button', { name: 'Rename' }).click();
  await expect(page.locator('.flash')).toContainText('Group renamed.');
  // After the rename redirect the drawer is closed again: read the group
  // history from the thread pane first (an open drawer would cover it at
  // these sub-1400px widths), then reopen the rail for the roster capture.
  await page.locator('.dm-events > summary').click();
  await expect(page.locator('.dm-events')).toContainText('member added @dana');
  await expect(page.locator('.dm-events')).toContainText('renamed');
  await openRail(page);
  await expect(page.locator('.dm-inforail .dm-member')).toHaveCount(4);
  await shot(page, info, 'group-dms-03-owner-tools');

  // ── Membership interval: dana joined after the first message, so her view
  //    starts empty; only messages sent after her join boundary render ───────
  await login(page, 'dana@retro.test');
  await page.goto(convPath);
  await expect(page.locator('.dm-thread-title')).toContainText('Launch council — war room');
  await expect(page.locator('body')).not.toContainText(firstBody);
  await expect(page.getByText('No messages yet.')).toBeVisible();

  await login(page, 'alice@retro.test');
  await replyInConversation(page, convPath, lateBody);

  await login(page, 'dana@retro.test');
  await page.goto(convPath);
  await expect(page.locator('.dm-body', { hasText: lateBody })).toBeVisible();
  await expect(page.locator('body')).not.toContainText(firstBody);
  await shot(page, info, 'group-dms-04-membership-interval');

  // ── Unread boundary: bob has not opened the group since the new message ───
  await login(page, 'bob@retro.test');
  await page.goto('/messages');
  await expect(page.locator('.dm-unread-dot').first()).toBeVisible();

  // ── Owner-leave guard + transfer: the owner cannot abandon the group; after
  //    transferring, the old owner loses the owner tools ─────────────────────
  await login(page, 'alice@retro.test');
  await page.goto(convPath);
  await openRail(page);
  await page.locator('.dm-inforail .dm-rail-btn.danger', { hasText: 'Leave group' }).click();
  await expect(page.locator('.flash')).toContainText('Transfer ownership before the owner leaves.');
  await openRail(page);
  await page.locator('.dm-inforail .dm-member', { hasText: '@bob' }).getByRole('button', { name: 'Make owner' }).click();
  await expect(page.locator('.flash')).toContainText('Ownership transferred.');
  await openRail(page);
  await expect(page.locator('.dm-inforail .dm-owner-tool')).toHaveCount(0);
  await expect(page.locator('.dm-inforail .dm-member', { hasText: '@bob' }).locator('.m-role')).toHaveText('Owner');

  // ── Mute, then leave: a member can quiet the group and walk away; the
  //    departed view is read-only history up to the departure ────────────────
  await login(page, 'carol@retro.test');
  await page.goto(convPath);
  await openRail(page);
  await page.locator('.dm-inforail .dm-rail-btn', { hasText: 'Mute conversation' }).click();
  await expect(page.locator('.flash')).toContainText('Conversation muted.');
  await expect(page.locator('.dm-thread-sub')).toContainText('muted');
  await openRail(page);
  await page.locator('.dm-inforail .dm-rail-btn.danger', { hasText: 'Leave group' }).click();
  await expect(page.locator('.flash')).toContainText('Member removed.');
  await page.goto(convPath);
  await expect(page.locator('.joinbar')).toContainText('no longer an active participant');
  await expect(page.locator('.dm-composer')).toHaveCount(0);
  await shot(page, info, 'group-dms-05-left-readonly');

  // ── Report → staff queue, and the report-only boundary: staff see the
  //    reported message in /mod/reports but have NO conversation browser ─────
  await login(page, 'dana@retro.test');
  await page.goto(convPath);
  const reportedLine = page.locator('.dm-group:not(.mine) .dm-line', { hasText: lateBody });
  await reportedLine.locator('.dm-line-menu summary').click();
  await reportedLine.locator('.dm-report-form select[name="reason_code"]').selectOption('spam');
  await reportedLine.locator('.dm-report-form input[name="reason"]').fill('Evidence-run report.');
  await reportedLine.getByRole('button', { name: 'Report message' }).click();
  await expect(page.locator('.flash')).toContainText('Thanks — our moderators will review this message.');

  await login(page, 'admin@retro.test');
  await page.goto('/mod/reports');
  await expect(page.locator('.report-target', { hasText: 'Launch council — war room' }).first()).toBeVisible();
  await expect(page.getByText(lateBody).first()).toBeVisible();
  await shot(page, info, 'group-dms-06-report-queue');

  // Admin access stays report-only: a staff account that is not a participant
  // gets 404 on the conversation itself (no private-message browser).
  const denied = await page.goto(convPath);
  expect(denied, 'conversation view should answer').not.toBeNull();
  expect(denied!.status(), 'non-participant staff must not browse a group conversation').toBe(404);
});

test('group DMs work with JavaScript disabled (create, rail via :target, mute, report)', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'The no-JS proof is captured once at the desktop viewport.');

  // reducedMotion drops the drawer's slide transition (it is gated on
  // prefers-reduced-motion), so the :target-opened rail is stable for the
  // click actionability checks without any script involvement.
  const nojsCtx = await browser.newContext({ baseURL: baseURL!, javaScriptEnabled: false, reducedMotion: 'reduce', viewport: { width: 1280, height: 800 } });
  const nojs = await nojsCtx.newPage();
  await login(nojs, 'bob@retro.test');

  // Create a group through the plain form post.
  const convPath = await createGroup(nojs, 'alice, carol', 'No-JS council', 'Formed without a single script.');
  await expect(nojs.locator('.dm-thread-eyebrow')).toContainText('Private group');

  // The details rail opens via the header menu's #dm-rail anchor (:target) —
  // no JavaScript involved — and its owner tools are plain POST forms.
  await nojs.locator('.dm-thread-actions .dm-menu > summary').click();
  await nojs.locator('.dm-menu-pop a[href="#dm-rail"]').click();
  await expect(nojs.locator('.dm-inforail')).toBeInViewport();
  await expect(nojs.locator('.dm-inforail .dm-owner-tool')).toHaveCount(2);
  await nojs.fill('.dm-inforail input[name="username"]', 'dana');
  await nojs.locator('.dm-inforail .dm-owner-tool').filter({ has: nojs.locator('input[name="username"]') }).getByRole('button', { name: 'Add' }).click();
  await expect(nojs.locator('.flash')).toContainText('Member added.');

  // Mute through the rail (reopened via :target after the redirect).
  await nojs.locator('.dm-thread-actions .dm-menu > summary').click();
  await nojs.locator('.dm-menu-pop a[href="#dm-rail"]').click();
  await expect(nojs.locator('.dm-inforail')).toBeInViewport();
  await nojs.locator('.dm-inforail .dm-rail-btn', { hasText: 'Mute conversation' }).click();
  await expect(nojs.locator('.flash')).toContainText('Conversation muted.');
  await shot(nojs, info, 'group-dms-07-no-js');

  // Report another member's message: the ··· control is a native <details>,
  // so the reason form opens and posts with JavaScript disabled too. bob is
  // still a member of the journey group above, which holds alice's message.
  await nojs.goto('/messages');
  await nojs.locator('.dm-row', { hasText: 'Launch council — war room' }).first().click();
  const foreignLine = nojs.locator('.dm-group:not(.mine) .dm-line').first();
  await foreignLine.locator('.dm-line-menu summary').click();
  await foreignLine.locator('.dm-report-form select[name="reason_code"]').selectOption('other');
  await foreignLine.getByRole('button', { name: 'Report message' }).click();
  await expect(nojs.locator('.flash')).toContainText('Thanks — our moderators will review this message.');

  await nojsCtx.close();
});
