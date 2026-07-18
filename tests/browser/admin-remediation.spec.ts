import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Browser evidence for the 2026-07-18 admin-console remediation
 * (docs/history/admin-ux-remediation-2026-07-18.md): board posting/edit-window
 * settings, the restored Move-topic control, board delete with a thread
 * destination, the bulk warn/suspend confirmation flow, the /admin/audit
 * screen, audited PII reveal + typed-username ban confirmation, the /mod/u/{id}
 * staff panel's draft-preserving 422s, announcement rate-limit draft
 * preservation + history, webhook pause/resume/delete affordances, and the
 * 390px scroll regions over the operator tables.
 *
 * Desktop project drives the flows; the mobile project only captures the
 * scroll-region evidence. Mutations are self-contained: the scratch board is
 * deleted again (threads moved back), the banner is cleared, the webhook is
 * deleted, and no seeded account changes state beyond warnings.
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
    await skip.click();
  }
}

function desktopOnly(info: TestInfo): void {
  test.skip(info.project.name !== 'desktop', 'flow evidence is captured on the desktop project');
}

test('board settings expose who-can-post and the edit window', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/structure');
  await page.locator('a[href^="/admin/boards/"][href$="/edit"]').first().click();
  await expect(page.locator('select[name="post_min_role"]')).toBeVisible();
  await expect(page.locator('input[name="edit_window_minutes"]')).toBeVisible();
  await expect(page.locator('select[name="visibility"] option', { hasText: 'Private (members only)' })).toHaveCount(1);
  await shot(page, info, 'remediation-board-edit-settings');
});

test('move topic control relocates a thread and board delete offers a destination', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');

  // Scratch destination board so no seeded board is harmed.
  await page.goto('/admin/structure');
  const addBoard = page.locator('form:has(input[name="slug"])').last();
  await addBoard.locator('select[name="category_id"]').selectOption({ index: 0 });
  await addBoard.locator('input[name="name"]').fill('Move Target');
  await addBoard.locator('input[name="slug"]').fill('move-target');
  await addBoard.locator('button[type="submit"]').last().click();
  await expect(page.locator('body')).toContainText('Move Target');

  // Move a reply-less #general topic into it via the restored Topic-tools
  // control (the tools live in the drawer behind the Topic tools opener).
  const title = 'Mobile layout looks great';
  await page.goto('/c/general');
  await page.locator('main a[href^="/t/"]', { hasText: title }).first().click();
  await page.locator('button[data-topic-tools-open]').first().click();
  await page.locator('details[data-topic-tools-section="management"] > summary').click();
  const moveForm = page.locator('form[action$="/move"]:has(select[name="board_id"])');
  await expect(moveForm.locator('label', { hasText: 'Move to board' })).toBeVisible();
  await shot(page, info, 'remediation-move-topic-control');
  await moveForm.locator('select[name="board_id"]').selectOption({ label: '#Move Target' });
  await moveForm.getByRole('button', { name: 'Move topic' }).click();
  await page.goto('/c/move-target');
  await expect(page.locator('main')).toContainText(title);

  // Deleting the now non-empty board demands a destination, then recounts.
  await page.goto('/admin/structure');
  await page
    .locator('li.admin-board-row', { hasText: 'Move Target' })
    .locator('a', { hasText: 'Delete' })
    .click();
  await expect(page.locator('body')).toContainText('Move its 1 thread to');
  await shot(page, info, 'remediation-board-delete-destination');
  const destination = await page
    .locator('select[name="move_to_board_id"] option', { hasText: '(/c/general)' })
    .getAttribute('value');
  await page.locator('select[name="move_to_board_id"]').selectOption(destination ?? '');
  await page.locator('input[name="confirm"]').fill('move-target');
  await page.locator('form:has(input[name="confirm"]) button[type="submit"]').click();
  await page.goto('/c/general');
  await expect(page.locator('main')).toContainText(title);
});

test('bulk warn flows through an explicit confirmation step', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/users');
  await page.locator('tr', { hasText: 'alice' }).locator('input[name="selected[]"]').check();
  await page.locator('tr', { hasText: 'bob' }).locator('input[name="selected[]"]').check();
  await page.locator('select[name="bulk_action"]').selectOption('warn');
  await page.getByRole('button', { name: 'Review and apply…' }).click();

  await expect(page.locator('body')).toContainText('alice');
  await expect(page.locator('body')).toContainText('bob');
  await shot(page, info, 'remediation-users-bulk-confirm');
  await page.locator('input[name="reason"]').fill('Coordinated off-topic thread derail (evidence run).');
  await page.getByRole('button', { name: 'Warn 2 members' }).click();
  await expect(page.getByRole('status')).toContainText('Warned');
});

test('audit log lists actions and filters by action key', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/audit');
  await expect(page.locator('.table-scroll table tbody tr').first()).toBeVisible();
  await page.locator('input[name="action"]').fill('warn');
  await page.getByRole('button', { name: 'Filter' }).click();
  await expect(page.locator('body')).toContainText('warn');
  await shot(page, info, 'remediation-audit-log');
});

test('member record reveals PII only on audited demand and ban needs the typed username', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/users');
  const recordPath = (await page.locator('a.user-link', { hasText: 'bob' }).first().getAttribute('href')) ?? '';
  expect(recordPath).toMatch(/^\/admin\/users\/\d+$/);
  await page.goto(recordPath);

  await expect(page.locator('body')).toContainText('hidden by default');
  await expect(page.locator('body')).not.toContainText('bob@retro.test');
  await page.getByRole('button', { name: 'Reveal email & IPs (audited)' }).click();
  await expect(page.locator('body')).toContainText('bob@retro.test');
  await shot(page, info, 'remediation-user-record-pii');

  // The reveal itself is auditable evidence.
  await page.goto('/admin/audit');
  await page.locator('input[name="action"]').fill('view_pii');
  await page.getByRole('button', { name: 'Filter' }).click();
  await expect(page.locator('body')).toContainText('view_pii');

  // A ban attempt without the exact username 422s and keeps the rationale.
  await page.goto(recordPath);
  const banForm = page.locator('form:has(input[name="confirm_username"])');
  await banForm.locator('input[name="reason"]').fill('Rationale that must survive the failed confirm.');
  await banForm.locator('input[name="confirm_username"]').fill('wrong-name');
  await banForm.getByRole('button', { name: /Ban/ }).click();
  await expect(page.locator('body')).toContainText('Type the member');
  await expect(page.locator('input[name="reason"][value*="survive"]')).toHaveCount(1);
  await shot(page, info, 'remediation-ban-typed-confirmation');
});

test('board moderator staff panel preserves a failed warn', async ({ page }, info) => {
  desktopOnly(info);
  // Find bob's id via the admin directory, then act as the scoped moderator.
  await login(page, 'admin@retro.test');
  await page.goto('/admin/users');
  const bobHref = await page.locator('a.user-link', { hasText: 'bob' }).first().getAttribute('href');
  const bobId = bobHref?.match(/\/admin\/users\/(\d+)/)?.[1] ?? '';
  expect(bobId).not.toBe('');

  await login(page, 'alice@retro.test');
  await page.goto(`/mod/u/${bobId}`);
  await expect(page.locator('body')).toContainText('@bob');
  await expect(page.locator('body')).toContainText('Issue a warning');
  await expect(page.locator('body')).not.toContainText('bob@retro.test');
  await shot(page, info, 'remediation-mod-user-panel');

  // Whitespace defeats the client-side `required` so the SERVER validation is
  // what the evidence shows: 422 with the panel re-rendered, nothing lost.
  const warnForm = page.locator(`form[action="/mod/u/${bobId}/warn"]`);
  await warnForm.locator('input[name="reason"]').fill('   ');
  await warnForm.getByRole('button', { name: 'Record warning' }).click();
  await expect(page.locator('body')).toContainText('A reason is required.');
});

test('split failure re-renders the thread with the typed title intact', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'alice@retro.test');
  // The seeded shortcuts topic carries two replies, so the split controls are live.
  await page.goto('/c/general');
  await page.locator('main a[href^="/t/"]', { hasText: 'Share your favourite keyboard shortcuts' }).first().click();
  // With JS on, split/merge is a dialog launched from the Topic-tools drawer.
  await page.locator('button[data-topic-tools-open]').first().click();
  await page.locator('details[data-topic-tools-section="management"] > summary').click();
  await page.locator('button[data-thread-restructure-open]').click();
  await page.locator('input[name="title"]').fill('Split evidence draft');
  await page.getByRole('button', { name: 'Split replies out' }).click();
  await expect(page.locator('input[name="title"]')).toHaveValue('Split evidence draft');
  await expect(page.locator('.field-error, [role="alert"]').first()).toBeVisible();
  await shot(page, info, 'remediation-split-draft-preserved');
});

test('announcement flood is a 429 that keeps the banner text and shows history', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/announcements');
  for (let i = 1; i <= 5; i++) {
    await page.locator('textarea[name="message"]').fill(`Maintenance window notice v${i} (evidence run).`);
    await page.getByRole('button', { name: 'Publish banner' }).click();
    // The live banner also has role=status, so target the flash specifically.
    await expect(page.locator('.flash').first()).toBeVisible();
  }
  await page.locator('textarea[name="message"]').fill('The sixth banner that must not be lost.');
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/admin/announcements') && r.request().method() === 'POST'),
    page.getByRole('button', { name: 'Publish banner' }).click(),
  ]);
  expect(response.status()).toBe(429);
  await expect(page.locator('textarea[name="message"]')).toHaveValue('The sixth banner that must not be lost.');
  await shot(page, info, 'remediation-announcement-429');
  await expect(page.locator('h2', { hasText: 'Recent history' })).toBeVisible();
  await expect(page.locator('.table-scroll', { hasText: 'Published v' })).toBeVisible();
  await shot(page, info, 'remediation-announcement-history');
  // Leave the shared evidence DB without an active banner.
  await page.getByRole('button', { name: 'Clear banner' }).click();
});

test('webhook pause/resume flashes say which happened and delete is reauthed', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/webhooks');
  await page.locator('input[name="name"]').fill('Remediation evidence hook');
  await page.locator('input[name="url"]').fill('http://127.0.0.1:9999/hooks/evidence');
  await page.locator('input[name="events[]"]').first().check();
  await page.locator('form:has(input[name="url"]) input[name="current_password"]').fill('password123');
  await page.locator('form:has(input[name="url"]) button[type="submit"]').click();
  await expect(page.locator('body')).toContainText('will not be shown again');
  await page
    .locator('tr', { hasText: 'Remediation evidence hook' })
    .locator('a[href^="/admin/webhooks/"]')
    .first()
    .click();

  await page.getByRole('button', { name: 'Pause' }).click();
  await expect(page.getByRole('status')).toContainText('Webhook paused');
  await page.getByRole('button', { name: 'Resume' }).click();
  await expect(page.getByRole('status')).toContainText('Webhook resumed');
  await shot(page, info, 'remediation-webhook-flashes');

  const deleteForm = page.locator('form:has(button:text("Delete webhook"))');
  await deleteForm.locator('input[name="current_password"]').fill('wrong-password');
  await deleteForm.getByRole('button', { name: 'Delete webhook' }).click();
  await expect(page.locator('.field-error').first()).toBeVisible();
  await shot(page, info, 'remediation-webhook-delete-reauth');
  const retryForm = page.locator('form:has(button:text("Delete webhook"))');
  await retryForm.locator('input[name="current_password"]').fill('password123');
  await retryForm.getByRole('button', { name: 'Delete webhook' }).click();
  await expect(page.getByRole('status')).toContainText('deleted');
});

test('operator tables scroll inside a phone viewport', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', '390px scroll-region evidence');
  await login(page, 'admin@retro.test');
  // The invitations table only renders once a row exists — mint one.
  await page.goto('/admin/invitations');
  await page.locator('input[name="email"]').fill('scroll-evidence@retro.test');
  await page.locator('form:has(input[name="email"]) button[type="submit"]').first().click();
  for (const [slug, name] of [
    ['users', 'users'],
    ['email', 'email'],
    ['providers', 'providers'],
    ['roles', 'roles'],
    ['invitations', 'invitations'],
  ] as const) {
    await page.goto(`/admin/${slug}`);
    await expect(page.locator('.table-scroll').first()).toBeVisible();
    await shot(page, info, `remediation-390-${name}`);
  }
});
