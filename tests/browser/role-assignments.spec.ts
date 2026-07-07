import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Phase 5 Inc 6 (resolver-enforcement cutover) browser evidence for the no-JS
 * admin role-assignment surface (Task 12) and the per-button moderation
 * display flags it feeds (Task 4b). Journey: create a custom role holding
 * ONLY core.thread.lock -> grant it to a seeded member (bob) at the #general
 * board scope via the plain assign form -> confirm the deputy's thread view
 * renders the Lock control (and NOT Pin, which the role was never granted) ->
 * revoke the assignment and confirm the admin surface reflects `revoked`.
 * Certifies /admin/roles/{id} is free of serious/critical axe violations in
 * both the active- and revoked-assignment states.
 *
 * The deputy-visibility half of this journey needs the app booted with
 * CAPABILITIES_MODE=enforce: under the default `shadow` posture the resolver
 * only shadow-compares while legacy authority alone decides, and bob has no
 * legacy moderator row on #general, so nothing would render. Run:
 *   CAPABILITIES_MODE=enforce npx playwright test role-assignments.spec.ts
 * The admin-side grant/revoke half is mode-independent (RoleAssignmentService
 * consults CapabilityResolver directly for the grantor-ceiling check, not the
 * AuthorityGate seam that CAPABILITIES_MODE switches), so it passes under
 * either posture.
 *
 * `features.capabilities` is seeded true unconditionally in seed.php (unlike
 * the package and webhooks dark fixtures, it is not gated behind
 * RB_BROWSER_DARK_SURFACES), so — like api-tokens.spec.ts — this spec has no
 * env-var skip guard; it runs whenever invoked.
 *
 * Isolation: mirrors api-tokens.spec.ts's theme-safe-mode neutralisation
 * (gate-a's theme-rollback journey leaves a package theme active site-wide in
 * the shared evidence DB; that theme desyncs shared admin-table styles under
 * WCAG AA) and its admin-login / user-switch-cookie-trap helpers verbatim.
 */
const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({ path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`), fullPage: true });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
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

// Neutralise any package theme gate-a left active site-wide on the shared evidence DB so
// this surface is certified under the standard appearance. Idempotent: if safe mode is
// already on the "Enter" button is absent and this is a no-op.
async function enterThemeSafeMode(page: Page): Promise<void> {
  await page.goto('/admin/themes/safe-mode');
  const enter = page.getByRole('button', { name: 'Enter safe mode' });
  if (await enter.isVisible({ timeout: 1000 }).catch(() => false)) {
    await enter.click();
    await expect(page.getByRole('status').getByText('Theme safe mode is on.')).toBeVisible();
  }
}

async function exitThemeSafeMode(page: Page): Promise<void> {
  await page.goto('/admin/themes/safe-mode');
  const exit = page.getByRole('button', { name: 'Exit safe mode' });
  if (await exit.isVisible({ timeout: 1000 }).catch(() => false)) {
    // Exiting reauths (setSafeMode(false) requires the current password); enter does not.
    await page.fill('form:has(input[name="exit"]) input[name="current_password"]', 'password123');
    await exit.click();
    await expect(page.getByRole('status').getByText('Theme safe mode was exited.')).toBeVisible();
  }
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include?: string): Promise<void> {
  let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);
  if (include !== undefined) builder = builder.include(include);
  const results = await builder.analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} serious/critical axe violations`).toEqual([]);
}

test('admin role assignment: no-JS grant surfaces the deputy lock control, then revoke (axe-clean)', async ({ page }, info) => {
  await login(page, 'admin@retro.test');
  await enterThemeSafeMode(page);

  await visit(page, '/admin/roles');
  await expect(page.getByRole('heading', { name: 'Roles & capabilities' })).toBeVisible();

  // Desktop + mobile share one seeded DB — unique name so rows never collide.
  const roleName = `Deputy role (${info.project.name}-${Date.now()})`;
  await page.fill('input[name="name"]', roleName);
  await page.check('input[name="capabilities[]"][value="core.thread.lock"]');
  await page.fill('input[name="current_password"]', 'password123');
  await page.click('form[action="/admin/roles"] button[type="submit"]');
  await expect(page.getByText(roleName)).toBeVisible();

  // Open the freshly created role's editor (unique row by name — see above).
  const roleRow = page.locator('table tbody tr', { hasText: roleName });
  await roleRow.getByRole('link', { name: 'Edit' }).click();
  await page.waitForURL(/\/admin\/roles\/\d+$/);
  const roleId = Number(new URL(page.url()).pathname.match(/(\d+)$/)?.[1]);
  expect(roleId, 'role id parsed from the edit URL').toBeGreaterThan(0);

  // Resolve #general's board id from the assign form's own datalist rather than
  // hardcoding a seed-order-dependent id.
  const generalBoardId = await page.locator('#assignment-board-options option[label="General"]').getAttribute('value');
  expect(generalBoardId, 'General board id from the assignment datalist').not.toBeNull();

  await page.fill('input[name="username"]', 'bob');
  await page.selectOption('select[name="scope_type"]', 'board');
  await page.fill('input[name="scope_id"]', generalBoardId!);
  await page.fill('input[name="reason"]', 'Browser evidence: Inc 6 deputy grant');
  await page.fill('form[action$="/assignments"] input[name="current_password"]', 'password123');
  await page.click('form[action$="/assignments"] button[type="submit"]');

  const assignmentRow = page.locator('table tbody tr', { hasText: '@bob' });
  await expect(assignmentRow).toContainText('active');
  await expect(assignmentRow).toContainText('General'); // board-scoped, not site-wide
  await expectNoSeriousA11yViolations(page, info); // active-assignment state is accessible
  await shot(page, info, '62-admin-role-assigned');

  // --- Option B (owner-approved): prove the grant is genuinely UI-usable ----
  // A deputy holding ONLY core.thread.lock at #general sees the per-button
  // Lock control (Task 4b) on a #general thread, and NOT Pin (never granted).
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Mobile layout looks great' }).click();
  await page.waitForURL(/\/t\//);
  await page.locator('.thread-actions .dm-menu > summary').click();
  const menu = page.locator('.dm-menu-pop');
  await expect(menu).toBeVisible();
  await expect(menu.getByRole('button', { name: 'Lock' })).toBeVisible();
  await expect(menu.getByRole('button', { name: 'Pin' })).toHaveCount(0);
  await shot(page, info, '64-deputy-sees-lock-control');

  // --- Back to admin: revoke, and confirm the surface reflects it ----------
  await login(page, 'admin@retro.test');
  await visit(page, '/admin/roles/' + roleId);
  const revokeBtn = page.locator('table tbody tr', { hasText: '@bob' }).getByRole('button', { name: 'Revoke' });
  await revokeBtn.scrollIntoViewIfNeeded();
  // force: mirrors api-tokens.spec.ts's tall-mobile-page hit-test quirk — the
  // toContainText('revoked') assertion below proves the click fired.
  await revokeBtn.click({ force: true });
  await expect(page.locator('table tbody tr', { hasText: '@bob' })).toContainText('revoked');
  await expectNoSeriousA11yViolations(page, info); // revoked-assignment state is accessible
  await shot(page, info, '63-admin-role-assignment-revoked');

  await exitThemeSafeMode(page);
});

/**
 * Inc 6 follow-up (queue discovery): an approve-only deputy reaches the
 * approvals queue scoped to exactly their granted board. Requires
 * CAPABILITIES_MODE=enforce like the deputy half above — under shadow the
 * legacy door (bare isModerator()) still decides and carol would be 403'd.
 */
test('deputy queue discovery: approve-only deputy reaches their scoped approvals queue (axe-clean)', async ({ page }, info) => {
  const suffix = `${info.project.name}-${Date.now()}`;

  await login(page, 'admin@retro.test');
  await enterThemeSafeMode(page);

  // The roles page surfaces the effective posture (Inc 6 follow-up).
  await visit(page, '/admin/roles');
  await expect(page.locator('p', { hasText: 'Resolver posture:' })).toContainText('enforce');

  // Two fresh approval-required boards: carol will be scoped to one only.
  const makeApprovalBoard = async (name: string): Promise<{ id: string; slug: string }> => {
    await visit(page, '/admin/structure');
    const form = page.locator('form[action="/admin/boards"]');
    await form.locator('input[name="name"]').fill(name);
    await form.locator('button[type="submit"]').click();
    const row = page.locator('li.admin-board-row', { hasText: name }).first();
    await row.getByRole('link', { name: 'Edit' }).click();
    await page.waitForURL(/\/admin\/boards\/\d+\/edit$/);
    const boardId = new URL(page.url()).pathname.match(/(\d+)\/edit$/)![1];
    const slug = await page.inputValue('input[name="slug"]');
    await page.check('input[name="require_approval"]');
    await page.click(`form[action="/admin/boards/${boardId}"] button:has-text("Save board")`);
    return { id: boardId, slug };
  };
  const scopedBoard = `Queue scoped ${suffix}`;
  const foreignBoard = `Queue foreign ${suffix}`;
  const scoped = await makeApprovalBoard(scopedBoard);
  const foreign = await makeApprovalBoard(foreignBoard);
  const scopedBoardId = scoped.id;

  // Role holding ONLY core.content.approve, granted to carol on the scoped board.
  await visit(page, '/admin/roles');
  const roleName = `Approvals deputy (${suffix})`;
  await page.fill('input[name="name"]', roleName);
  await page.check('input[name="capabilities[]"][value="core.content.approve"]');
  await page.fill('input[name="current_password"]', 'password123');
  await page.click('form[action="/admin/roles"] button[type="submit"]');
  await page.locator('table tbody tr', { hasText: roleName }).getByRole('link', { name: 'Edit' }).click();
  await page.waitForURL(/\/admin\/roles\/\d+$/);
  await page.fill('input[name="username"]', 'carol');
  await page.selectOption('select[name="scope_type"]', 'board');
  await page.fill('input[name="scope_id"]', scopedBoardId);
  await page.fill('input[name="reason"]', 'Browser evidence: queue discovery');
  await page.fill('form[action$="/assignments"] input[name="current_password"]', 'password123');
  await page.click('form[action$="/assignments"] button[type="submit"]');
  await expect(page.locator('table tbody tr', { hasText: '@carol' })).toContainText('active');

  // bob (no staff standing on these boards) posts one topic into each — held.
  await login(page, 'bob@retro.test');
  const scopedTitle = `Scoped approval probe ${suffix}`;
  const foreignTitle = `Foreign approval probe ${suffix}`;
  const postHeld = async (slug: string, title: string): Promise<void> => {
    await visit(page, `/c/${slug}`);
    await page.locator('details.composer-details > summary').click();
    await page.fill('input[name="title"]', title);
    await page.fill('textarea[name="body"]', 'Queue-discovery browser evidence body.');
    await page.getByRole('button', { name: 'Create topic' }).click();
    await expect(page.getByText('awaiting moderator approval')).toBeVisible();
  };
  await postHeld(scoped.slug, scopedTitle);
  await postHeld(foreign.slug, foreignTitle);

  // carol: the door opens via discovery, rows are scoped, approval works.
  await login(page, 'carol@retro.test');
  await visit(page, '/mod/approvals');
  await expect(page.getByText(scopedTitle)).toBeVisible();
  await expect(page.getByText(foreignTitle)).toHaveCount(0);
  await expectNoSeriousA11yViolations(page, info);
  await shot(page, info, '65-deputy-approvals-queue');
  await page.locator('li', { hasText: scopedTitle }).getByRole('button', { name: 'Approve' }).first().click();
  await expect(page.getByRole('status').getByText('Topic approved and published.')).toBeVisible();

  // carol holds approve, not report.handle: the reports queue stays closed.
  const reports = await page.goto('/mod/reports');
  expect(reports!.status()).toBe(404);

  // Leave the shared evidence DB clean: admin releases the foreign hold.
  await login(page, 'admin@retro.test');
  await visit(page, '/mod/approvals');
  await page.locator('li', { hasText: foreignTitle }).getByRole('button', { name: 'Approve' }).first().click();
  await expect(page.getByRole('status').getByText('Topic approved and published.')).toBeVisible();
  await exitThemeSafeMode(page);
});
