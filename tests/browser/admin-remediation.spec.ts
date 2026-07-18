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

test('dashboard site-name 422 keeps the typed value on both viewports', async ({ page }, info) => {
  await login(page, 'admin@retro.test');
  await page.goto('/admin');
  // Model the stale-form threat the server contract exists for (PR #44 §5): a
  // cached page carries no maxlength guarantee, so lift the client cap and let
  // the SERVER refuse. PHPUnit pins the same 422 at the HTTP layer.
  const siteForm = page.locator('form[action="/admin/site"]');
  const nameInput = siteForm.locator('input[name="site_name"]');
  await nameInput.evaluate((el) => el.removeAttribute('maxlength'));
  const typed = 'Overlong site name draft — '.repeat(4).trim();
  await nameInput.fill(typed);
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/admin/site') && r.request().method() === 'POST'),
    siteForm.locator('button[type="submit"]').click(),
  ]);
  expect(response.status()).toBe(422);
  await expect(page.locator('body')).toContainText('Site name must be 1–80 characters.');
  await expect(page.locator('form[action="/admin/site"] input[name="site_name"]')).toHaveValue(typed);
  await shot(page, info, 'remediation-dashboard-422-draft');
});

test('scoped moderator panel: overlap select, no global history, out-of-scope 404', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/users');
  const idOf = async (name: string): Promise<string> => {
    const href = await page.locator('a.user-link', { hasText: name }).first().getAttribute('href');
    return href?.match(/\/admin\/users\/(\d+)/)?.[1] ?? '';
  };
  const bobId = await idOf('bob');
  const danaId = await idOf('dana');
  expect(bobId).not.toBe('');
  expect(danaId).not.toBe('');

  await login(page, 'alice@retro.test');
  await page.goto(`/mod/u/${bobId}`);
  // The scoped model (PR #44 §2): an overlap board select and scoped warnings
  // only — no staff notes, no bans, no audit trail, no email.
  await expect(page.locator('select[name="board_id"] option', { hasText: '#General' })).toHaveCount(1);
  await expect(page.locator('body')).not.toContainText('Private staff note');
  await expect(page.locator('body')).not.toContainText('Audit trail');
  await expect(page.locator('body')).not.toContainText('bob@retro.test');
  await shot(page, info, 'remediation-mod-panel-scoped');

  const warnForm = page.locator(`form[action="/mod/u/${bobId}/warn"]`);
  await warnForm.locator('select[name="board_id"]').selectOption({ label: '#General' });
  await warnForm.locator('input[name="reason"]').fill('Scoped warn from the evidence run.');
  await warnForm.getByRole('button', { name: 'Record warning' }).click();
  await expect(page.getByRole('status')).toContainText('Warning recorded.');

  // A member with no participation in alice's boards does not exist for her —
  // the same 404 a missing id produces (no membership oracle).
  const miss = await page.goto(`/mod/u/${danaId}`);
  expect(miss?.status()).toBe(404);
  await shot(page, info, 'remediation-mod-panel-out-of-scope');
});

test('board delete previews the authoritative count including hidden content', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');

  // Scratch board whose only topic is then soft-deleted: the visible counters
  // say zero while one row still exists — the preview must say so (PR #44 §3).
  // Unique name/slug per run so a rerun against the same segment DB (or a
  // leftover from an aborted run) can never collide.
  const suffix = Date.now().toString(36);
  const boardName = `Shadow Attic ${suffix}`;
  const boardSlug = `shadow-attic-${suffix}`;
  await page.goto('/admin/structure');
  const addBoard = page.locator('form:has(input[name="slug"])').last();
  await addBoard.locator('select[name="category_id"]').selectOption({ index: 0 });
  await addBoard.locator('input[name="name"]').fill(boardName);
  await addBoard.locator('input[name="slug"]').fill(boardSlug);
  await addBoard.locator('button[type="submit"]').last().click();
  await expect(page.locator('body')).toContainText(boardName);

  await page.goto(`/c/${boardSlug}`);
  await page.locator('details.composer-details > summary').click();
  const composer = page.locator('form.composer').first();
  await composer.locator('input[name="title"]').fill('Hidden cargo topic');
  await composer.locator('textarea.composer-input').fill('This topic is about to be soft-deleted.');
  await composer.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);

  // Deleting the opening post retracts the topic (a soft-deleted thread row).
  // The delete form lives in the post's \u00b7\u00b7\u00b7 menu disclosure.
  const post = page.locator('article[data-post]').first();
  await post.hover();
  await post.locator('details.post-menu > summary').click();
  await post.locator('form[action$="/delete"] button[type="submit"]').click();
  await expect(page.getByRole('status')).toContainText('deleted');

  await page.goto('/admin/structure');
  // .last(): the row list nests, so the text filter also matches ancestor
  // rows — the innermost match is the scratch board's own row.
  await page
    .locator('li.admin-board-row', { hasText: boardName })
    .last()
    .getByRole('link', { name: 'Delete', exact: true })
    .click();
  await expect(page.locator('body')).toContainText('1 (including hidden, held, and deleted)');
  await expect(page.locator('select[name="move_to_board_id"]')).toBeVisible();
  await shot(page, info, 'remediation-board-delete-authoritative-count');

  const destination = await page
    .locator('select[name="move_to_board_id"] option', { hasText: '(/c/general)' })
    .getAttribute('value');
  await page.locator('select[name="move_to_board_id"]').selectOption(destination ?? '');
  await page.locator('input[name="confirm"]').fill(boardSlug);
  await page.locator('form:has(input[name="confirm"]) button[type="submit"]').click();
  await expect(page.getByRole('status')).toContainText('Moved 1 thread and deleted the board.');
});

test('mint refresh cannot re-mint: the replay is a 409 conflict', async ({ page }, info) => {
  desktopOnly(info);
  await login(page, 'admin@retro.test');
  await page.goto('/admin/api-tokens');
  await page.locator('input[name="name"]').fill('Refresh evidence token');
  await page.locator('input[name="scopes[]"][value="read:boards"]').check();
  await page.locator('input[name="current_password"]').fill('password123');
  await page.getByRole('button', { name: 'Create token' }).click();
  await expect(page.getByText(/will not be shown again/)).toBeVisible();

  // The browser refresh re-POSTs the same idempotency key (PR #44 §7).
  await page.reload();
  await expect(page.getByText('already processed')).toBeVisible();
  await expect(page.locator('code').filter({ hasText: /^rbt_/ })).toHaveCount(0);
  await expect(page.locator('table tbody tr', { hasText: 'Refresh evidence token' })).toHaveCount(1);
  await shot(page, info, 'remediation-token-refresh-conflict');
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
