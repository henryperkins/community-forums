import { test, expect, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Appeals browser evidence (ADR 0007). Drives the no-JS moderation-appeals flow
 * end to end in a real browser: a member opens an appeal against a
 * moderator-deleted post on /appeals, then a staff member resolves it from the
 * board-scoped queue on /mod/appeals. Both faces are plain form POST → PRG
 * redirects, so this doubles as proof the server-rendered appeals paths work
 * without JavaScript.
 *
 * appeals graduated to default-ON (GA 2026-07-02): the seed enables the flag on
 * the standard evidence path and soft-deletes bob's #general reply with a
 * matching delete_post moderation_log row (see seed.php), so /appeals renders an
 * appealable target by default.
 *
 * Serial single-DB safety (playwright.config.ts: workers=1, fullyParallel=false):
 * the desktop project runs before mobile against one shared DB. The member leg
 * asserts the submit form is *present* rather than the "Your appeals" list is
 * empty (a prior desktop pass may have left an open appeal), and the staff leg
 * resolves with the non-reversing `dismissed` outcome so bob's post stays
 * soft-deleted and re-appealable for the mobile pass.
 *
 * Seeded credentials (see seed.php): bob@retro.test (the appellant — his removed
 * reply is the seeded target), alice@retro.test (moderator of #general, so the
 * board-scoped queue surfaces the post appeal), both password123.
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
    fullPage: true,
  });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function login(page: Page, email: string): Promise<void> {
  // Clear any prior session before switching users on the same page: an
  // authenticated GET /login 302-redirects to '/' (AuthController::showLogin),
  // which would hide input[name=email] and hang this helper for the staff leg.
  // Cookies are context-scoped — see the same note in the content-references
  // journey (gate-a.spec.ts). This also covers a cold PHP server on the first hit.
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').waitFor({ state: 'visible' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login')); // PRG redirect off the login page
  await dismissTour(page);
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

test('member opens an appeal and staff resolves it through the no-JS queue', async ({ page }, info) => {
  // Member leg: bob appeals his moderator-removed #general reply.
  await login(page, 'bob@retro.test');

  await visit(page, '/appeals');
  const appealForm = page.locator('form.appeal-form').first();
  await expect(appealForm).toBeVisible();
  await expect(appealForm.getByRole('button', { name: 'Submit appeal' })).toBeVisible();

  await appealForm.locator('textarea[name="reason"]').fill(
    'This reply was on-topic — please review the removal.',
  );
  await appealForm.getByRole('button', { name: 'Submit appeal' }).click();
  await page.waitForURL(/\/appeals$/);
  await expect(page.locator('.flash')).toContainText('Appeal submitted.');
  // The open appeal now shows in "Your appeals" with an open-status badge.
  await expect(page.locator('.report-list')).toContainText('open');
  await shot(page, info, '44-appeals-member');

  // Staff leg: alice (moderator of #general) resolves the post appeal from the
  // board-scoped queue. Resolve as `dismissed` so the reversal path is not taken
  // and bob's post stays soft-deleted + re-appealable for the serial mobile pass.
  await login(page, 'alice@retro.test');

  await visit(page, '/mod/appeals');
  await expect(page.getByRole('heading', { name: 'Appeals queue' })).toBeVisible();
  const queueRow = page.locator('li.report-row').filter({ has: page.locator('form.appeal-resolve') }).first();
  await expect(queueRow).toBeVisible();
  await shot(page, info, '45-appeals-staff-queue');

  const resolveForm = queueRow.locator('form.appeal-resolve');
  await resolveForm.locator('select[name="outcome"]').selectOption('dismissed');
  await resolveForm.locator('textarea[name="note"]').fill('Reviewed — removal upheld per board rules.');
  await resolveForm.getByRole('button', { name: 'Resolve appeal' }).click();
  await page.waitForURL(/\/mod\/appeals$/);
  await expect(page.locator('.flash')).toContainText('Appeal resolved.');
});
