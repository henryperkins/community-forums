import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { execFile } from 'node:child_process';
import http from 'node:http';
import path from 'node:path';
import { promisify } from 'node:util';

/**
 * Gate A browser evidence: drive the real, server-rendered app in Chromium at
 * two viewports and capture each key surface. These are evidence-generating
 * journeys, but each navigation also asserts a non-error status (and a stable,
 * viewport-visible anchor where useful) so a broken page fails the run instead
 * of silently capturing an error screen.
 *
 * Seeded credentials (see seed.php): admin@retro.test, bob@retro.test — both
 * password123. Login uses the no-JS form post, so these double as proof the
 * server-rendered POST→redirect auth path works in a real browser.
 */

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');
const execFileAsync = promisify(execFile);
const PNG_1X1 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC';
const CUSTOM_EMOJI_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="#d94848"/><circle cx="11" cy="12" r="2" fill="#fff7df"/><circle cx="21" cy="12" r="2" fill="#fff7df"/><path d="M10 21c3.5 3 8.5 3 12 0" fill="none" stroke="#fff7df" stroke-width="2.5" stroke-linecap="round"/></svg>';

async function runWebhookWorker(repoRoot: string): Promise<{ stdout: string; stderr: string }> {
  if (process.env.E2E_SKIP_WEBSERVER === '1') {
    return execFileAsync('docker', ['compose', '-f', 'tests/prodlike/compose.yml', 'exec', '-T', 'app', 'php', 'bin/console', 'worker:webhooks'], {
      cwd: repoRoot,
      env: process.env,
    });
  }

  return execFileAsync('php', ['bin/console', 'worker:webhooks'], {
    cwd: repoRoot,
    env: {
      ...process.env,
      DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e',
      WEBHOOK_ALLOW_HTTP: 'true',
      WEBHOOK_ALLOWED_PRIVATE_CIDRS: '127.0.0.1/32,::1/128',
      MAIL_DRIVER: 'array',
    },
  });
}

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
  });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function openTopicTools(page: Page, section: 'watch' | 'standing' | 'tags' | 'memory' | 'management') {
  const trigger = page.getByRole('button', { name: 'Topic tools', exact: true });
  await trigger.click();
  const tools = page.locator('[data-topic-tools]');
  await expect(tools).toBeVisible();
  await tools.evaluate(async (element) => Promise.all(element.getAnimations().map((animation) => animation.finished)));
  const details = tools.locator(`[data-topic-tools-section="${section}"]`);
  if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) await details.locator(':scope > summary').click();
  return { tools, details };
}

function lifecyclePackageUid(info: TestInfo): string {
  return info.project.name === 'mobile' ? 'acme/midnight-theme-mobile' : 'acme/midnight-theme';
}

function themeEvidenceUid(): string {
  return 'acme/theme-evidence';
}

function themeEvidenceAltUid(): string {
  return 'acme/theme-evidence-alt';
}

async function openLifecyclePackageDetail(page: Page, info: TestInfo): Promise<void> {
  await visit(page, '/admin/packages');
  const row = page.locator('table.audit tbody tr').filter({ hasText: lifecyclePackageUid(info) }).first();
  await expect(row).toBeVisible();
  await row.getByRole('link', { name: 'Details' }).click();
  await page.waitForURL(/\/admin\/packages\/\d+$/);
}

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.endsWith('/login')); // PRG redirect off the login page
  await dismissTour(page);
}

async function clickThemePreview(page: Page, uid: string): Promise<void> {
  await visit(page, '/admin/themes');
  const row = page
    .getByRole('region', { name: 'Installed theme packages' })
    .locator('tbody tr')
    .filter({ hasText: uid })
    .first();
  await expect(row).toBeVisible();
  await row.getByRole('button', { name: 'Preview' }).click();
  await expect(page.getByRole('status').getByText('Previewing this theme in your session only.')).toBeVisible();
}

async function activateTheme(page: Page, uid: string): Promise<void> {
  await visit(page, '/admin/themes');
  const row = page
    .getByRole('region', { name: 'Installed theme packages' })
    .locator('tbody tr')
    .filter({ hasText: uid })
    .first();
  await expect(row).toBeVisible();
  await row.locator('form[action$="/activate"] input[name="current_password"]').fill('password123');
  await row.getByRole('button', { name: 'Activate' }).click();
  await expect(page.getByRole('status').getByText('Theme activated.')).toBeVisible();
}

async function activeThemeDigest(page: Page): Promise<string> {
  const href = await page
    .locator('link[rel="stylesheet"][href^="/theme/"][href$=".css"]')
    .first()
    .getAttribute('href');
  expect(href, 'active package theme stylesheet link').toBeTruthy();
  const match = href!.match(/\/theme\/([a-f0-9]{64})\.css/);
  expect(match, `active theme href should be digest-addressed: ${href}`).not.toBeNull();

  return match![1];
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function openNewTopicComposer(page: Page): Promise<void> {
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();
}

async function dropTinyPng(page: Page): Promise<void> {
  const dataTransfer = await page.evaluateHandle((base64) => {
    const bin = atob(base64);
    const bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    const dt = new DataTransfer();
    dt.items.add(new File([bytes], 'tiny.png', { type: 'image/png' }));
    return dt;
  }, PNG_1X1);
  await page.locator('form.composer textarea.composer-input').first().dispatchEvent('drop', { dataTransfer });
}

async function expectOneTimeSecret(page: Page, context: string): Promise<void> {
  const secret = page.getByText(/will not be shown again/);
  if (await secret.isVisible({ timeout: 10_000 }).catch(() => false)) {
    return;
  }

  const errors = (await page.locator('.field-error').allTextContents())
    .map((text) => text.trim())
    .filter(Boolean)
    .join(' | ');
  const body = (await page.locator('body').innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 1000);
  throw new Error(`${context} did not show the one-time secret. URL=${page.url()} errors=${errors || 'none'} body=${body}`);
}

test('public pages render and capture', async ({ page }, info) => {
  await visit(page, '/');
  await expect(page.locator('a[href^="/c/"]').first()).toBeVisible();
  await shot(page, info, '01-home');

  await visit(page, '/c/general');
  await expect(page.locator('a[href^="/t/"]').first()).toBeVisible();
  await shot(page, info, '02-board-general');

  // Open the first thread from the board (id-agnostic).
  await page.locator('a[href^="/t/"]').first().click();
  await page.waitForURL(/\/t\//);
  await shot(page, info, '03-thread');

  await visit(page, '/leaderboard');
  await shot(page, info, '04-leaderboard');

  await visit(page, '/login');
  await expect(page.locator('button[type="submit"]')).toBeVisible();
  await shot(page, info, '05-login');

  await visit(page, '/register');
  await expect(page.locator('input[name="username"]')).toBeVisible();
  await shot(page, info, '06-register');
});

test('admin + member pages render and capture', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin');
  await shot(page, info, '07-admin-dashboard');

  await visit(page, '/admin/structure');
  await expect(page.locator('a[href^="/c/"]').first()).toBeVisible();
  await shot(page, info, '08-admin-structure');

  // The board-roster UI (board moderators + members) — open #General's edit page.
  await page
    .locator('li.admin-board-row', { hasText: 'General' })
    .getByRole('link', { name: 'Edit' })
    .click();
  await page.waitForURL(/\/admin\/boards\/\d+\/edit/);
  await expect(page.getByRole('heading', { name: 'Moderators' })).toBeVisible();
  await expect(page.getByText('@alice')).toBeVisible(); // seeded board moderator
  await shot(page, info, '09-admin-board-roster');

  await visit(page, '/inbox');
  await shot(page, info, '10-inbox');

  await visit(page, '/notifications');
  await shot(page, info, '11-notifications');

  await visit(page, '/settings');
  await shot(page, info, '12-settings');

  await visit(page, '/search?q=keyboard');
  await shot(page, info, '13-search');
});

test('private board access for a member', async ({ page }, info) => {
  await login(page, 'bob@retro.test');
  // Membership grants read access — visit() asserts 200 (a non-member would 404).
  await visit(page, '/c/staff-room');
  await expect(page.getByText('Staff Room').first()).toBeVisible();
  await shot(page, info, '14-private-board-member');
});

test('phase 4 poll vote works through the server-rendered thread flow', async ({ page }, info) => {
  const voter = info.project.name === 'mobile' ? 'carol@retro.test' : 'bob@retro.test';
  await login(page, voter);

  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);
  await expect(page.getByText('Which shortcut do you reach for first?')).toBeVisible();

  const option = page.locator('form[action^="/polls/"] input[name="option_ids[]"]').first();
  await option.check();
  await page.getByRole('button', { name: 'Vote' }).click();

  await expect(page.locator('.poll-panel .link-list')).toBeVisible();
  await expect(page.locator('.poll-panel')).toContainText(/\d+ vote/);
  await shot(page, info, '25-poll-voted');
});

test('phase 4 topic workflow: status, snooze, and assignment via the server-rendered thread', async ({ page }, info) => {
  // topic_workflow graduated to default-on (GA 2026-07-01). Drive the enhanced
  // Study tools as alice — moderator of #general (staff), which #general opts
  // into assignment via assignment_mode=staff (seed). Each control is a plain
  // form POST → PRG redirect back to the thread; reopen the fresh tools HTML
  // after every mutation and verify the quiet thread facts reflect it.
  await login(page, 'alice@retro.test');

  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);
  await expect(page.locator('[data-thread-study]')).toBeVisible();

  // Status: staff moves the topic to "Needs answer".
  let standing = await openTopicTools(page, 'standing');
  await standing.details.locator('select[name="status"]').selectOption('needs_answer');
  await standing.details.locator('input[name="reason"]').fill('Needs a reply');
  await standing.details.getByRole('button', { name: 'Update status' }).click();
  await expect(page.locator('[data-thread-status="needs_answer"]')).toBeVisible();

  // Snooze: a personal reminder (no cross-user effect).
  const watch = await openTopicTools(page, 'watch');
  await watch.details.locator('select[name="until"]').selectOption('tomorrow');
  await watch.details.getByRole('button', { name: 'Save snooze' }).click();
  await expect(page.locator('.thread-byline')).toContainText('Quiet until');

  // Assignment: staff assigns the topic to @bob (board is opted into assignment).
  let management = await openTopicTools(page, 'management');
  await management.details.locator('input[name="assignee"]').fill('bob');
  await management.details.getByRole('button', { name: 'Assign', exact: true }).click();
  await expect(page.locator('.thread-byline')).toContainText('Tended by @bob');
  management = await openTopicTools(page, 'management');
  await expect(management.details.getByRole('button', { name: 'Unassign' })).toBeVisible();

  // Status history is surfaced (audit trail) — expand it so the evidence
  // screenshot captures the recorded transitions in the Study drawer.
  await page.getByRole('button', { name: 'Close Topic tools' }).click();
  standing = await openTopicTools(page, 'standing');
  const history = standing.details.locator('[data-thread-status-history]');
  if (!(await history.evaluate((element) => (element as HTMLDetailsElement).open))) await history.locator(':scope > summary').click();
  await expect(history.locator('.thread-status-history-list')).toContainText('Needs answer');

  await shot(page, info, '29-topic-workflow');
});

test('role editor: create a custom role and simulate a decision (no-JS forms)', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/roles');
  await expect(page.getByRole('heading', { name: 'Roles & capabilities' })).toBeVisible();
  await expect(page.getByText('system.admin')).toBeVisible();

  const roleName = `Board Helper ${info.project.name}`;
  await page.fill('input[name="name"]', roleName);
  await page.check('input[name="capabilities[]"][value="core.thread.lock"]');
  await page.check('input[name="capabilities[]"][value="core.thread.pin"]');
  await page.fill('input[name="current_password"]', 'password123');
  await page.click('form[action="/admin/roles"] button[type="submit"]');
  await expect(page.getByText(roleName)).toBeVisible();
  await shot(page, info, '30-admin-role-created');

  await visit(page, '/admin/roles/simulator?actor=guest&capability=core.thread.lock&board_id=1');
  await expect(page.getByText('Denied')).toBeVisible();
  await shot(page, info, '31-admin-role-simulator');
});

test('package registry: staff-only read-only catalogue browse (Inc 2)', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/packages');
  await expect(page.getByRole('heading', { name: 'Package catalogue' })).toBeVisible();
  await expect(page.locator('code', { hasText: lifecyclePackageUid(info) }).first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Details' }).first()).toBeVisible();
  await shot(page, info, '32-admin-package-catalogue');

  await openLifecyclePackageDetail(page, info);
  await expect(page.getByRole('heading', { name: /Releases \(immutable/ })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Install plan' })).toBeVisible();
  await shot(page, info, '33-admin-package-detail');

  await visit(page, '/admin/registries');
  await expect(page.getByRole('heading', { name: 'Registry trust & security response' })).toBeVisible();
  await expect(page.getByText('Local blocklist', { exact: false }).first()).toBeVisible();
  await shot(page, info, '34-admin-registry-trust');
});

test('package lifecycle: plan, consent, enable, and update re-consent (Inc 3)', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await openLifecyclePackageDetail(page, info);

  await page.getByRole('button', { name: 'Install plan' }).click();
  await expect(page.getByRole('heading', { level: 1, name: /^Install plan - / })).toBeVisible();
  await expect(page.getByText('Store its own settings and data', { exact: false })).toBeVisible();
  await shot(page, info, '35-admin-package-install-plan');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: /Install/ }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Consent to permissions' })).toBeVisible();
  await shot(page, info, '36-admin-package-consent');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Grant and continue' }).click();

  await page.fill('form[action$="/enable"] input[name="current_password"]', 'password123');
  await page.locator('form[action$="/enable"] button[type="submit"]').click();
  await expect(page.getByText('Enabled', { exact: true })).toBeVisible();
  await shot(page, info, '37-admin-package-enabled');

  await page.fill('form[action$="/update"] input[name="current_password"]', 'password123');
  await page.locator('form[action$="/update"] button[type="submit"]').click();
  await expect(page.getByRole('heading', { level: 1, name: /^Approve update to / })).toBeVisible();
  await expect(page.getByText('api.example.com')).toBeVisible();
  await shot(page, info, '38-admin-package-update-diff');

  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Grant and continue' }).click();
  await expect(page.getByRole('row', { name: /^Version 1\.1\.0$/ })).toBeVisible();
});

test('theme packages: preview, activate, safe mode, and LKG rollback (Inc 4)', async ({ page, browser, baseURL }, info) => {
  await login(page, 'admin@retro.test');

  await clickThemePreview(page, themeEvidenceUid());
  await expect(page.locator('link[href^="/theme/preview.css"]')).toHaveCount(1);
  await shot(page, info, '39-admin-themes-preview');

  const anonymous = await browser.newContext({ baseURL });
  try {
    const anon = await anonymous.newPage();
    await visit(anon, '/');
    await expect(anon.locator('link[href^="/theme/preview.css"]')).toHaveCount(0);
  } finally {
    await anonymous.close();
  }

  await activateTheme(page, themeEvidenceUid());
  await visit(page, '/');
  const firstDigest = await activeThemeDigest(page);
  await shot(page, info, '40-admin-theme-active');

  await visit(page, '/admin/themes/safe-mode');
  await page.getByRole('button', { name: 'Enter safe mode' }).click();
  await expect(page.getByRole('status').getByText('Theme safe mode is on.')).toBeVisible();
  await shot(page, info, '41-admin-theme-safe-mode');
  await visit(page, '/');
  await expect(page.locator('link[href*="/theme/"]')).toHaveCount(0);

  await visit(page, '/admin/themes/safe-mode');
  await page.fill('form:has(input[name="exit"]) input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Exit safe mode' }).click();
  await expect(page.getByRole('status').getByText('Theme safe mode was exited.')).toBeVisible();

  await activateTheme(page, themeEvidenceAltUid());
  await visit(page, '/');
  const secondDigest = await activeThemeDigest(page);
  expect(secondDigest).not.toBe(firstDigest);

  await visit(page, '/admin/themes');
  await page.fill('form[action="/admin/themes/rollback"] input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Roll back' }).click();
  await expect(page.getByRole('status').getByText('Rolled back to the last-known-good theme.')).toBeVisible();
  await visit(page, '/');
  await expect(page.locator(`link[href="/theme/${firstDigest}.css"]`)).toHaveCount(1);
  await shot(page, info, '42-admin-theme-rollback');
});

test('mobile no-JS keeps navigation reachable without an inert drawer button', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile-only progressive enhancement check');

  const context = await browser.newContext({
    baseURL,
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
    javaScriptEnabled: false,
  });
  const page = await context.newPage();
  try {
    await visit(page, '/');
    await expect(page.locator('.nav-toggle')).toBeHidden();
    await expect(page.locator('#sidebar-nav')).toBeVisible();
  } finally {
    await context.close();
  }
});

test('phase 3 composer, drafts, upload, and preferences JS journeys', async ({ page }, info) => {
  await login(page, 'bob@retro.test');

  await visit(page, '/settings/preferences');
  await expect(page.locator('select[name="threads_per_page"]')).toHaveValue('20');
  await expect(page.locator('select[name="posts_per_page"]')).toHaveValue('20');
  await shot(page, info, '15-reading-preferences');

  await openNewTopicComposer(page);
  const textarea = page.locator('form.composer textarea.composer-input').first();
  await expect(page.getByRole('button', { name: 'Insert bold' }).first()).toBeVisible();
  await page.getByRole('button', { name: 'Insert bold' }).first().click();
  await expect(textarea).toHaveValue('****');
  await expect(page.getByRole('button', { name: 'Insert bold' }).first()).toHaveAttribute('aria-pressed', 'true');

  await textarea.fill('Browser evidence **bold** :smile:');
  await expect(page.locator('.composer-preview strong').first()).toHaveText('bold');
  await expect(page.locator('.composer-preview').first()).toContainText('😄');
  // server_drafts graduated to default-on: the composer now syncs to the server.
  // Wait for the confirmed save before reloading so the debounced POST is not lost.
  await expect(page.getByText('Saved to server drafts.')).toBeVisible({ timeout: 5000 });

  await page.reload();
  await openNewTopicComposer(page);
  await expect(textarea).toHaveValue(/Browser evidence/);

  // The Drafts view is now the server-owned list (data-server-drafts): the draft
  // persisted above is titled from the composer label and shows its body excerpt.
  await visit(page, '/drafts');
  const draftsList = page.locator('[data-drafts-list][data-server-drafts]');
  const serverDraftRows = draftsList.locator('.report-row:not([data-local-draft-row])');
  await expect(serverDraftRows).toContainText('New topic');
  await expect(serverDraftRows).toContainText('Browser evidence');
  await expect(page.getByRole('heading', { name: 'Saved in this browser' })).toBeVisible();
  await expect(draftsList.locator('[data-local-draft-row]')).toContainText('Browser evidence');
  await shot(page, info, '16-drafts-view');
  const discard = page.getByRole('button', { name: 'Discard' });
  await discard.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
  await discard.click();
  await expect(page.locator('[data-drafts-list]')).toContainText('No server drafts yet.');
  // Clear the browser-local mirror so the upload sub-journey opens an empty composer.
  await page.evaluate(() => { try { localStorage.removeItem('rb-draft:bob:/threads'); } catch (e) {} });

  await openNewTopicComposer(page);
  await dropTinyPng(page);
  await expect(page.locator('.composer-upload-status').first()).toContainText('Uploaded image');
  await expect(textarea).toHaveValue(/!\[\]\(\/media\/\d+\)/);
  await page.locator('.composer-upload-card input[aria-label="Image alt text"]').fill('Tiny test image');
  await expect(textarea).toHaveValue(/!\[Tiny test image\]\(\/media\/\d+\)/);
  await shot(page, info, '17-composer-upload');

  await page.locator('form.composer input[name="title"]').first().fill('Browser upload evidence');
  await page.locator('form.composer button[type="submit"]').first().click();
  await page.waitForURL(/\/t\/\d+-/);
  // A successful submit clears the synced server draft on the next navigation.
  await visit(page, '/drafts');
  await expect(page.locator('[data-drafts-list]')).toContainText('No server drafts yet.');
});

test('phase 4 content references render read-gated cards for public targets and redact private targets', async ({ page }, info) => {
  // content_references graduated to default-on (GA 2026-07-02). Drive the
  // no-JS server-rendered path: create a thread in #general that links to a
  // public thread and to the private #staff-room, then assert the public target
  // renders as a card while the private target is redacted for a non-member.
  // The seeded private-board fixture already has bob as a member (see the
  // "private board access for a member" journey), so we view as carol, who is
  // not a member of #staff-room.
  const author = info.project.name === 'mobile' ? 'carol@retro.test' : 'bob@retro.test';
  const viewer = 'carol@retro.test';
  const publicTargetTitle = 'Public reference target';

  await login(page, author);

  // Create a stable public target thread in #general.
  await openNewTopicComposer(page);
  const authorTextarea = page.locator('form.composer textarea.composer-input').first();
  await page.locator('form.composer input[name="title"]').first().fill(publicTargetTitle);
  await authorTextarea.fill('This is the public target for reference cards.');
  await page.locator('form.composer button[type="submit"]').first().click();
  await page.waitForURL(/\/t\/\d+-/);
  const publicTargetUrl = new URL(page.url()).pathname;

  // Create the source thread linking to the public target and the private board.
  await visit(page, '/c/general');
  await openNewTopicComposer(page);
  const sourceTitle = `Reference source ${Date.now()}`;
  await page.locator('form.composer input[name="title"]').first().fill(sourceTitle);
  await page.locator('form.composer textarea.composer-input').first().fill(
    `See the [public target](${publicTargetUrl}) and the [private board](/c/staff-room).`,
  );
  await page.locator('form.composer button[type="submit"]').first().click();
  await page.waitForURL(/\/t\/\d+-/);

  // Reload the source page as the author to confirm both cards render for a
  // privileged viewer (bob is a member of #staff-room; carol only sees public).
  const sourceUrl = new URL(page.url()).pathname;
  await page.goto(sourceUrl);
  await expect(page.locator('.reference-cards')).toContainText(publicTargetTitle);
  if (author === 'bob@retro.test') {
    await expect(page.locator('.reference-cards')).toContainText('Staff Room');
  }

  // View as a non-member: the public card is present, the private one is absent.
  // Navigate by opening a new tab because session cookies are scoped to the
  // current browser context; logging in again from the same page can race the
  // server-rendered redirect and the previous composer state.
  const viewerContext = await page.context().browser()?.newContext({ viewport: page.viewportSize() ?? undefined });
  if (viewerContext === undefined) {
    throw new Error('Could not create viewer context for content-reference evidence');
  }
  const viewerPage = await viewerContext.newPage();
  await login(viewerPage, viewer);
  await viewerPage.goto(sourceUrl);
  await expect(viewerPage.locator('.reference-cards')).toContainText(publicTargetTitle);
  await expect(viewerPage.locator('.reference-cards')).not.toContainText('Staff Room');
  await shot(viewerPage, info, '43-content-references-redacted');
  await viewerContext.close();
});

test('phase 4 profile media: avatar upload, signature, and admin moderation', async ({ page }, info) => {
  await login(page, 'bob@retro.test');

  await visit(page, '/settings/account');
  const avatarPanel = page.locator('.profile-media-panel');
  await expect(avatarPanel).toBeVisible();
  await avatarPanel.locator('input[name="avatar"]').setInputFiles({
    name: 'avatar.png',
    mimeType: 'image/png',
    buffer: Buffer.from(PNG_1X1, 'base64'),
  });
  await avatarPanel.locator('form[action="/settings/avatar"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/account$/);
  await expect(page.getByRole('status').getByText('Avatar updated.')).toBeVisible();
  await expect(page.locator('.profile-media-panel img[src^="/media/"]')).toBeVisible();

  await page.locator('textarea[name="signature"]').fill(`Profile media evidence (${info.project.name})`);
  await page.getByRole('button', { name: 'Save changes' }).click();
  await page.waitForURL(/\/settings\/account$/);
  await expect(page.getByRole('status').getByText('Your profile has been updated.')).toBeVisible();

  await visit(page, '/u/bob');
  await expect(page.locator('.profile-avatar img[src^="/media/"]')).toBeVisible();
  await shot(page, info, '46-profile-media-avatar');

  await page.context().clearCookies();
  await login(page, 'admin@retro.test');
  await visit(page, '/admin/users');
  await page.getByRole('link', { name: 'bob', exact: true }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/);

  const profileMedia = page.locator('.profile-media-card');
  await expect(profileMedia).toBeVisible();
  await expect(profileMedia.getByRole('button', { name: 'Remove avatar' })).toBeVisible();
  await expect(profileMedia.getByRole('button', { name: 'Remove signature' })).toBeVisible();
  await shot(page, info, '47-profile-media-moderation');

  await profileMedia.getByRole('button', { name: 'Remove avatar' }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.getByRole('status').getByText('Avatar removed.')).toBeVisible();
  await expect(profileMedia).toContainText('No uploaded avatar set.');

  await profileMedia.getByRole('button', { name: 'Remove signature' }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.getByRole('status').getByText('Signature removed.')).toBeVisible();
  await expect(profileMedia).toContainText('No signature set.');
});

test('phase 4 custom emoji: admin catalogue, Markdown render, and reaction', async ({ page }, info) => {
  const suffix = info.project.name.replace(/[^a-z0-9_-]/gi, '').toLowerCase();
  const shortcode = `party_${suffix}`;
  const token = `:${shortcode}:`;
  const imagePath = `/emoji/${shortcode}.png`;

  await page.route(`**${imagePath}`, async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'image/svg+xml',
      body: CUSTOM_EMOJI_SVG,
    });
  });

  await login(page, 'admin@retro.test');
  await visit(page, '/admin');
  const panel = page.locator('.custom-emoji-panel');
  await expect(panel).toBeVisible();
  await panel.locator('input[name="shortcode"]').fill(shortcode);
  await panel.locator('input[name="name"]').fill(`Party ${suffix}`);
  await panel.locator('input[name="image_path"]').fill(imagePath);
  await panel.locator('select[name="mime"]').selectOption('image/png');
  await panel.locator('input[name="allow_reactions"]').check();
  await panel.getByRole('button', { name: 'Save emoji' }).click();
  await page.waitForURL(/\/admin$/);
  await expect(page.getByRole('status').getByText('Custom emoji saved.')).toBeVisible();
  await expect(page.locator('.custom-emoji-panel')).toContainText(token);
  await shot(page, info, '48-custom-emoji-admin');

  await page.context().clearCookies();
  await login(page, 'bob@retro.test');
  await openNewTopicComposer(page);
  const form = page.locator('form.composer').first();
  const topicTitle = `Custom emoji evidence ${suffix} ${Date.now()}`;
  await form.locator('input[name="title"]').fill(topicTitle);
  await form.locator('textarea.composer-input').fill(`Hello ${token} and \`${token}\``);
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);

  await expect(page.locator(`.post-body img[src="${imagePath}"][alt="${token}"]`)).toBeVisible();
  await expect(page.locator('.post-body code').filter({ hasText: token })).toBeVisible();
  await page.locator('.reaction-add > summary').first().click();
  await page.getByRole('button', { name: token }).click();
  await expect(page.locator('.reaction-on').filter({ hasText: token })).toBeVisible();
  const postBody = page.locator('.post-body').first();
  const reactions = page.locator('.reactions').first();
  await postBody.scrollIntoViewIfNeeded();
  await page.evaluate(() => window.scrollBy(0, -140));
  const bodyBox = await postBody.boundingBox();
  const reactionsBox = await reactions.boundingBox();
  const viewport = page.viewportSize();
  if (!bodyBox || !reactionsBox || !viewport) {
    throw new Error('Unable to calculate custom emoji evidence screenshot bounds');
  }
  const x = Math.max(0, Math.min(bodyBox.x, reactionsBox.x) - 8);
  const y = Math.max(0, Math.min(bodyBox.y, reactionsBox.y) - 8);
  const right = Math.min(viewport.width, Math.max(bodyBox.x + bodyBox.width, reactionsBox.x + reactionsBox.width) + 8);
  const bottom = Math.min(viewport.height, Math.max(bodyBox.y + bodyBox.height, reactionsBox.y + reactionsBox.height) + 8);
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, '49-custom-emoji-thread.png'),
    clip: { x, y, width: right - x, height: bottom - y },
  });
});

test('phase 4 slash menu inserts approved snippets and GIPHY media', async ({ page }, info) => {
  await page.route('https://api.giphy.com/v1/gifs/search**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        data: [
          {
            title: 'Evidence cat',
            images: {
              fixed_height_small: { url: 'https://media4.giphy.com/media/cat/100.gif' },
              original: { url: 'https://media4.giphy.com/media/cat/giphy.gif' },
            },
          },
        ],
      }),
    });
  });
  await page.route('https://media4.giphy.com/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'image/png',
      body: Buffer.from(PNG_1X1, 'base64'),
    });
  });

  await login(page, 'bob@retro.test');
  await openNewTopicComposer(page);

  const form = page.locator('form.composer').first();
  const textarea = form.locator('textarea.composer-input');

  // The menu is an ARIA combobox: the textarea drives a role=listbox of
  // role=option items, navigable entirely from the keyboard.
  await textarea.fill('/');
  await expect(page.locator('form.composer .composer-slash-menu[role="listbox"]').first()).toBeVisible();
  await expect(textarea).toHaveAttribute('aria-expanded', 'true');
  const tableOption = page.getByRole('option', { name: 'Insert table' });
  const taskOption = page.getByRole('option', { name: 'Insert task list' });
  await expect(tableOption).toBeVisible();
  await expect(taskOption).toBeVisible();
  // First option is active by default; Arrow keys move the active descendant.
  await expect(tableOption).toHaveAttribute('aria-selected', 'true');
  await shot(page, info, '26-slash-menu');

  await textarea.press('ArrowDown');
  await expect(taskOption).toHaveAttribute('aria-selected', 'true');
  await expect(tableOption).toHaveAttribute('aria-selected', 'false');
  await textarea.press('ArrowUp');
  await expect(tableOption).toHaveAttribute('aria-selected', 'true');
  // Enter inserts the active option (not a newline / form submit) and closes.
  await textarea.press('Enter');
  await expect(textarea).toHaveValue(/\| Heading \| Heading \|/);
  await expect(page.getByRole('option', { name: 'Insert table' })).toHaveCount(0);
  await expect(textarea).toHaveAttribute('aria-expanded', 'false');

  await textarea.fill('/gif cat');
  await page.getByRole('option', { name: 'Search GIPHY' }).click();
  await expect(page.getByRole('option', { name: 'Insert GIF Evidence cat' })).toBeVisible();
  await page.getByRole('option', { name: 'Insert GIF Evidence cat' }).click();
  await expect(textarea).toHaveValue('![Evidence cat](https://media4.giphy.com/media/cat/giphy.gif)');
  // Escape closes the menu without disturbing the composed value.
  await textarea.fill('/');
  await expect(textarea).toHaveAttribute('aria-expanded', 'true');
  await textarea.press('Escape');
  await expect(textarea).toHaveAttribute('aria-expanded', 'false');
  await textarea.fill('![Evidence cat](https://media4.giphy.com/media/cat/giphy.gif)');
  await shot(page, info, '27-giphy-inserted');

  const topicTitle = `Slash GIPHY evidence ${Date.now()}`;
  await form.locator('input[name="title"]').fill(topicTitle);
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);
  await expect(page.locator('.post-body img[src="https://media4.giphy.com/media/cat/giphy.gif"]')).toBeVisible();
});

test('phase 4 slash status rows consume Enter when enter-to-send is enabled', async ({ page }) => {
  await login(page, 'bob@retro.test');

  await visit(page, '/settings/composing');
  await page.locator('input[name="enter_to_send"]').check();
  await page.locator('form[action="/settings/composing"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/composing$/);

  await openNewTopicComposer(page);
  const form = page.locator('form.composer').first();
  const textarea = form.locator('textarea.composer-input');
  await form.locator('input[name="title"]').fill('Do not submit slash status');

  await textarea.fill('/gif');
  await expect(page.getByRole('option', { name: 'Search GIPHY' })).toBeVisible();
  await textarea.press('Enter');
  await expect(page.getByRole('option', { name: 'Type a search after /gif.' })).toBeVisible();

  const submitted = page.waitForRequest((request) => {
    const url = new URL(request.url());
    return request.method() === 'POST' && url.pathname === '/threads';
  }, { timeout: 500 }).then(() => true).catch(() => false);

  await textarea.press('Enter');

  expect(await submitted).toBe(false);
  await expect(textarea).toHaveValue('/gif');
  await expect(page).toHaveURL(/\/c\/general$/);
});

test('phase 3 branding preview and product-tour replay', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/branding');
  await page.locator('[data-brand-name]').fill('Lakeside Forum');
  await page.locator('[data-brand-primary]').fill('#005fcc');
  await page.locator('[data-brand-accent]').fill('#a33300');
  await expect(page.locator('[data-brand-preview-name]')).toHaveText('Lakeside Forum');
  await expect(page.locator('[data-brand-preview]')).toHaveCSS('--preview-accent', '#005fcc');
  await shot(page, info, '18-branding-preview');

  await visit(page, '/settings/account');
  const replay = page.locator('[data-tour-replay]');
  await expect(replay).toBeVisible();
  await replay.click();
  await expect(page.locator('.tour-popover')).toHaveAttribute('aria-modal', 'true');
  await expect(page.locator('.tour-popover')).toContainText('Welcome');
  await shot(page, info, '19-tour-replay');

  await page.keyboard.press('Escape');
  await expect(page.locator('.tour-popover')).toHaveCount(0);
  await expect(replay).toBeFocused();
});

test('admin API tokens: mint shows the secret once, then revoke', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  // The flag-gated discovery link appears on the admin dashboard (seed enables api_tokens).
  await visit(page, '/admin');
  await page.getByRole('link', { name: 'API tokens' }).click();
  await page.waitForURL(/\/admin\/api-tokens$/);
  await expect(page.getByRole('heading', { name: 'API tokens' })).toBeVisible();

  // Unique per viewport project: desktop + mobile share one seeded DB, so a fixed
  // name would collide once the first project leaves its row behind.
  const tokenName = `Evidence CI token (${info.project.name}-${Date.now()})`;

  // Mint via the no-JS form post: name + a scope + password reauth.
  await page.fill('input[name="name"]', tokenName);
  await page.check('input[name="scopes[]"][value="read:boards"]');
  await page.fill('input[name="current_password"]', 'password123');
  await page.getByRole('button', { name: 'Create token' }).click();

  // The one-time plaintext is shown exactly once, directly in the response (never via Flash).
  await expect(page.getByText(/will not be shown again/)).toBeVisible();
  await expect(page.locator('code').filter({ hasText: /^rbt_/ })).toBeVisible();
  await shot(page, info, '20-admin-api-token-minted');

  // The token is listed active; revoke it via the row form (PRG redirect back to the list).
  const row = page.locator('table tbody tr', { hasText: tokenName });
  await expect(row).toContainText('active');
  const revokeBtn = row.getByRole('button', { name: 'Revoke' });
  await revokeBtn.scrollIntoViewIfNeeded();
  // force: the button is confirmed visible/enabled/correctly-placed (see 20-*.png); on the
  // tall mobile page Playwright's hit-test transiently reports the mint-form card as the top
  // element. The toContainText('revoked') assertion below still proves the revoke fired.
  await revokeBtn.click({ force: true });
  await expect(page.locator('table tbody tr', { hasText: tokenName })).toContainText('revoked');
  await shot(page, info, '21-admin-api-token-revoked');
});

test('admin webhooks: register shows the secret once, domain event delivers', async ({ page }, info) => {
  let received = false;
  let receivedEvent = '';
  let markReceived: (() => void) | null = null;
  const receivedPromise = new Promise<void>((resolve) => {
    markReceived = resolve;
  });
  const server = http.createServer((req, res) => {
    const chunks: Buffer[] = [];
    req.on('data', (chunk) => chunks.push(Buffer.from(chunk)));
    req.on('end', () => {
      received = true;
      try {
        const payload = JSON.parse(Buffer.concat(chunks).toString('utf8'));
        receivedEvent = payload.event;
      } catch {
        receivedEvent = '';
      }
      markReceived?.();
      res.statusCode = 200;
      res.end('ok');
    });
  });
  const prodlikeTarget = process.env.E2E_SKIP_WEBSERVER === '1';
  const receiverBind = prodlikeTarget ? '0.0.0.0' : '127.0.0.1';
  const receiverHost = prodlikeTarget ? 'host.docker.internal' : '127.0.0.1';
  await new Promise<void>((resolve) => server.listen(0, receiverBind, () => resolve()));
  const address = server.address();
  if (typeof address === 'string' || address === null) {
    throw new Error('expected TCP server address');
  }
  const hookUrl = `http://${receiverHost}:${address.port}/hook`;

  try {
    await login(page, 'admin@retro.test');
    await visit(page, '/admin');
    await expect(page.getByRole('link', { name: 'Webhooks' })).toHaveAttribute('href', '/admin/webhooks');
    await visit(page, '/admin/webhooks');
    await expect(page.getByRole('heading', { name: 'Webhooks' })).toBeVisible();

    const webhookName = `Evidence webhook (${info.project.name}-${Date.now()})`;
    await page.fill('input[name="name"]', webhookName);
    await page.fill('input[name="url"]', hookUrl);
    await page.check('input[name="events[]"][value="topic.created"]');
    await page.fill('input[name="current_password"]', 'password123');
    await page.getByRole('button', { name: 'Register endpoint' }).click();

    await expectOneTimeSecret(page, 'Webhook registration');
    await shot(page, info, '22-admin-webhook-registered');

    const topicTitle = `Webhook domain evidence ${Date.now()}`;
    await openNewTopicComposer(page);
    await page.locator('form.composer input[name="title"]').first().fill(topicTitle);
    await page.locator('form.composer textarea.composer-input').first().fill('Webhook domain delivery body should stay out of the payload.');
    await page.locator('form.composer button[type="submit"]').first().click();
    await page.waitForURL(/\/t\//);

    await visit(page, '/admin/webhooks');
    await page.locator('table tbody tr', { hasText: webhookName }).getByRole('link', { name: 'Manage' }).click();
    await page.waitForURL(/\/admin\/webhooks\/\d+$/);

    const repoRoot = path.resolve(__dirname, '..', '..');
    const deliverySeen = Promise.race([
      receivedPromise,
      new Promise<void>((_, reject) => {
        setTimeout(() => reject(new Error('webhook receiver did not receive a POST')), 10_000);
      }),
    ]);
    const worker = runWebhookWorker(repoRoot);
    const [{ stdout }] = await Promise.all([worker, deliverySeen]);
    expect(stdout).toContain('Webhook delivery: delivered=');
    expect(stdout).not.toContain('delivered=0');
    expect(received).toBe(true);
    expect(receivedEvent).toBe('topic.created');

    await page.reload();
    const deliveryRow = page
      .locator('table.audit tbody tr')
      .filter({ has: page.getByRole('cell', { name: 'topic.created', exact: true }) })
      .first();
    await expect(deliveryRow).toBeVisible();
    await expect(deliveryRow).toContainText('delivered');
    await expect(deliveryRow).toContainText('200');
    await shot(page, info, '23-admin-webhook-delivery-log');

    // Desktop and mobile projects share one seeded database. Remove this
    // project-local endpoint while its receiver is still alive so later topic
    // events cannot queue ahead of the next project's live receiver and spend
    // that test's delivery deadline retrying a closed port.
    await page.getByRole('button', { name: 'Delete' }).click();
    await page.waitForURL(/\/admin\/webhooks$/);
    await expect(page.getByText('Webhook deleted.')).toBeVisible();
  } finally {
    await new Promise<void>((resolve) => server.close(() => resolve()));
  }
});

test('admin per-user record: badges + title', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/users');
  // exact:true — the directory row link is exactly "bob"; a substring match would
  // ALSO hit the presence sidebar's "Bob Brooks" (/u/bob) link (strict-mode violation).
  await expect(page.getByRole('link', { name: 'bob', exact: true })).toBeVisible();
  await shot(page, info, '14-admin-users');

  await page.getByRole('link', { name: 'bob', exact: true }).click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.getByRole('heading', { name: /bob/ })).toBeVisible();

  // Grant a manual badge (no-JS form post).
  await page.locator('form[action$="/badges/grant"] select[name="slug"]').selectOption('staff');
  await page.locator('form[action$="/badges/grant"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  // Scope to the held-badges list: a bare getByText('Staff') would also match the
  // (non-visible) <option> in the grant <select>, which precedes it in the DOM.
  await expect(page.locator('ul.link-list').getByText('Staff')).toBeVisible();
  await shot(page, info, '15-admin-user-record');

  // Revoke it.
  await page.locator('form[action$="/badges/revoke"] button[type="submit"]').first().click();
  await page.waitForURL(/\/admin\/users\/\d+$/);

  // Set then clear a cosmetic title.
  await page.locator('form.stacked[action$="/title"] input[name="title"]').fill('Community Hero');
  await page.locator('form.stacked[action$="/title"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.locator('form.stacked[action$="/title"] input[name="title"]')).toHaveValue('Community Hero');

  await page.locator('form.inline-form[action$="/title"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expect(page.locator('form.stacked[action$="/title"] input[name="title"]')).toHaveValue('');
});

test('phase 4 badge rules: create, preview, enable, backfill, disable, revoke', async ({ page }, info) => {
  // badge_rules graduated to default-on (GA 2026-07-02). Admin-only operator
  // surface; the dashboard has no link to it, so navigate directly (the app only
  // links to it from within the badge-rules screens themselves).
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/badge-rules');
  await expect(page.getByRole('heading', { name: 'Badge rules' })).toBeVisible();
  // The run is serial against a shared DB, so a prior (desktop) pass may have left
  // a rule behind; assert the create form is present rather than an empty list.
  await expect(page.getByRole('button', { name: 'Create rule' })).toBeVisible();

  // Create a post-count rule (threshold 1, all boards) through the no-JS form.
  // Pick a badge that the seeded posting users do not already hold; the first
  // catalogue option is Welcome, which can become fully held by the mobile pass
  // after invitation/account flows have run in the shared evidence DB.
  // Rules list newest-first, so the rule created here is always the first row.
  const form = page.locator('form.stacked[action="/admin/badge-rules"]');
  await form.locator('select[name="badge_id"]').selectOption({ label: 'Appreciated' });
  await form.locator('select[name="rule_type"]').selectOption('post_count');
  await form.locator('input[name="threshold"]').fill('1');
  await form.getByRole('button', { name: 'Create rule' }).click();
  await page.waitForURL(/\/admin\/badge-rules$/);
  await expect(page.getByText('Badge rule created.')).toBeVisible();

  // The new rule lists as Disabled (new rules start disabled).
  const ruleRow = page.locator('ul.link-list > li').first();
  await expect(ruleRow.getByText('Disabled')).toBeVisible();
  await shot(page, info, '32-badge-rules');

  // Preview eligible users — works even while the rule is disabled.
  await ruleRow.getByRole('link', { name: 'Preview' }).click();
  await page.waitForURL(/\/admin\/badge-rules\/\d+\/preview$/);
  await expect(page.getByRole('heading', { name: 'Badge rule preview' })).toBeVisible();
  await expect(page.getByText(/Metric:/).first()).toBeVisible();
  await shot(page, info, '33-badge-rule-preview');

  // Enable, then backfill: awards happen only on this explicit action, and the
  // flash reports the award count. Scope every action to the test's own row (the
  // first, newest, row) — a prior serial pass may have left other rules present.
  await visit(page, '/admin/badge-rules');
  await ruleRow.locator('form[action$="/enable"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/badge-rules$/);
  await expect(ruleRow.getByText('Enabled')).toBeVisible();

  await ruleRow.locator('form[action$="/backfill"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/badge-rules$/);
  await expect(page.getByText(/Badge rule backfilled \d+ awards\./)).toBeVisible();
  await shot(page, info, '34-badge-rule-backfilled');

  // Disable, then revoke the rule's awards: the flash reports the revoke count.
  await ruleRow.locator('form[action$="/disable"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/badge-rules$/);
  await expect(ruleRow.getByText('Disabled')).toBeVisible();

  await ruleRow.locator('form[action$="/revoke"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/badge-rules$/);
  await expect(page.getByText(/Badge rule revoked \d+ awards\./)).toBeVisible();
});

test('phase 4 account lifecycle: export, deactivate/reactivate, request/cancel deletion', async ({ page }, info) => {
  // account_lifecycle graduated to default-on (GA 2026-07-02, ADR 0006). Drive the
  // whole member self-serve slice as a no-JS form journey. Uses the dedicated
  // `dana` account (not bob/carol) because deactivate/delete are destructive; the
  // journey self-restores to `active` so the serial desktop→mobile re-run is safe.
  await login(page, 'dana@retro.test');

  // The lifecycle surface is reached through the settings rail, which only renders
  // the "Account" link when the flag is live — so this also proves the gated nav.
  await visit(page, '/settings/account');
  await page.getByRole('link', { name: 'Account', exact: true }).click();
  await page.waitForURL(/\/settings\/account\/lifecycle$/);
  await expect(page.getByRole('heading', { name: 'Delete account' })).toBeVisible();
  await shot(page, info, '35-account-lifecycle');

  // Export is a CSRF-protected POST that streams a JSON attachment: assert the
  // download fires (the page must NOT navigate away) rather than a page load.
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.locator('form[action="/settings/account/export"] button[type="submit"]').click(),
  ]);
  expect(download.suggestedFilename()).toBe('retroboards-account-export.json');

  // Deactivate (reversible) → the section flips to the reactivate control…
  await page.locator('form[action="/settings/account/deactivate"] input[name="current_password"]').fill('password123');
  await page.locator('form[action="/settings/account/deactivate"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/account\/lifecycle$/);
  await expect(page.getByRole('button', { name: 'Reactivate account' })).toBeVisible();

  // …then reactivate restores write access (the deactivate form returns).
  await page.locator('form[action="/settings/account/reactivate"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/account\/lifecycle$/);
  await expect(page.getByRole('button', { name: 'Deactivate account' })).toBeVisible();

  // Request deletion → the danger zone shows the 30-day grace + cancel control.
  await page.locator('form[action="/settings/account/delete/request"] input[name="current_password"]').fill('password123');
  await page.locator('form[action="/settings/account/delete/request"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/account\/lifecycle$/);
  await expect(page.getByRole('button', { name: 'Cancel deletion request' })).toBeVisible();
  await expect(page.getByText(/Deletion is scheduled after the grace period/)).toBeVisible();
  await shot(page, info, '36-account-deletion-scheduled');

  // Cancel during grace restores the account to active (leaves dana reusable).
  await page.locator('form[action="/settings/account/delete/cancel"] button[type="submit"]').click();
  await page.waitForURL(/\/settings\/account\/lifecycle$/);
  await expect(page.getByRole('button', { name: 'Request account deletion' })).toBeVisible();
});

test('admin can reorder and archive boards', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  await visit(page, '/admin/structure');
  await shot(page, info, '20-structure-before');

  // Move #feedback up one slot via the server-rendered button (no-JS path).
  const feedbackRow = page.locator('li.admin-board-row[data-board-id]', { hasText: 'Feedback' });
  await feedbackRow.getByRole('button', { name: /Move Feedback up/i }).click();
  await expect(page).toHaveURL(/\/admin\/structure/);
  await shot(page, info, '21-structure-after-move');

  // Archive #feedback, then confirm the board page is read-only.
  await page.locator('li.admin-board-row', { hasText: 'Feedback' })
    .getByRole('link', { name: 'Archive' }).click();
  await expect(page.getByRole('heading', { name: 'Archive board' })).toBeVisible();
  await page.fill('form[action$="/archive"] input[name="confirm"]', 'feedback');
  await page.getByRole('button', { name: 'Archive board' }).click();
  await expect(page).toHaveURL(/\/admin\/structure/);

  await visit(page, '/c/feedback');
  await expect(page.locator('[data-archived-banner]')).toBeVisible();
  await expect(page.locator('details.composer-details')).toHaveCount(0);
  await shot(page, info, '22-board-archived-readonly');

  // Unarchive restores the composer affordance.
  await visit(page, '/admin/structure');
  await page.locator('li.admin-board-row', { hasText: 'Feedback' })
    .getByRole('link', { name: 'Unarchive' }).click();
  await expect(page.getByRole('heading', { name: 'Unarchive board' })).toBeVisible();
  await page.fill('form[action$="/unarchive"] input[name="confirm"]', 'feedback');
  await page.getByRole('button', { name: 'Unarchive board' }).click();
  await expect(page).toHaveURL(/\/admin\/structure/);
  await visit(page, '/c/feedback');
  await expect(page.locator('details.composer-details')).toBeVisible();
  await shot(page, info, '23-board-unarchived');
});

test('site announcement banner: publish, render, dismiss, and persist', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  // Publish a dismissible banner through the real admin form (a no-JS POST).
  await visit(page, '/admin/announcements');
  await page.fill('textarea[name="message"]', 'Scheduled maintenance at 02:00 UTC.');
  await page.check('input[name="dismissible"]');
  await page.getByRole('button', { name: 'Publish banner' }).click();
  await page.waitForURL((u) => u.pathname === '/admin/announcements');

  // It renders in the global shell at this viewport.
  await visit(page, '/');
  const banner = page.locator('[data-announcement]');
  await expect(banner).toBeVisible();
  await expect(banner).toContainText('Scheduled maintenance at 02:00 UTC.');
  await shot(page, info, '20-announcement-banner');

  // PE dismissal hides it and records the version in localStorage…
  await page.locator('[data-announcement-dismiss]').click();
  await expect(banner).toBeHidden();

  // …and the dismissal persists across navigation.
  await visit(page, '/c/general');
  await expect(page.locator('[data-announcement]')).toBeHidden();
  await shot(page, info, '21-announcement-dismissed');

  // Clean up so the banner does not bleed into later evidence runs.
  await visit(page, '/admin/announcements');
  await page.getByRole('button', { name: 'Clear banner' }).click();
});

test('admin email delivery: dashboard, suppress/remove, and a test-send', async ({ page }, info) => {
  await login(page, 'admin@retro.test');

  // The email link appears on the admin dashboard subnav (email flag defaults on).
  await visit(page, '/admin');
  await page.getByRole('navigation', { name: 'Admin navigation' })
    .getByRole('link', { name: 'Email', exact: true }).click();
  await page.waitForURL(/\/admin\/email$/);
  await expect(page.getByRole('heading', { name: 'Email delivery' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Queue status' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Delivery log' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Suppressed addresses' })).toBeVisible();
  await shot(page, info, '22-admin-email-dashboard');

  // Suppress a unique address (desktop + mobile share one DB), confirm it lists, then remove it.
  const target = `evidence-${info.project.name}-${Date.now()}@example.test`;
  await page.fill('form[action="/admin/email/suppressions"] input[name="email"]', target);
  await page.locator('form[action="/admin/email/suppressions"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/email$/);
  const row = page.locator('table tbody tr', { hasText: target });
  await expect(row).toBeVisible();
  await shot(page, info, '23-admin-email-suppressed');

  await row.getByRole('button', { name: 'Remove' }).click({ force: true });
  await page.waitForURL(/\/admin\/email$/);
  await expect(page.locator('table tbody tr', { hasText: target })).toHaveCount(0);

  // Test-send (transport is the configured ArrayMailer in evidence runs) → flash confirmation.
  await page.locator('form[action="/admin/email/test"] button[type="submit"]').click();
  await page.waitForURL(/\/admin\/email$/);
  await expect(page.locator('.flash')).toContainText(/Test email sent/);
  await shot(page, info, '24-admin-email-test-sent');
});

test('phase 4 split/merge: moderator splits a reply out then merges it back', async ({ page }, info) => {
  // split_merge graduated to default-on (GA 2026-07-03). Drive the whole
  // moderator restructure surface end-to-end as no-JS forms: create a throwaway
  // source topic + reply, split the reply into a new topic, then merge it back.
  // Everything is created fresh per run (unique title) so the destructive ops
  // never touch shared seed threads and the serial desktop→mobile re-run over
  // one DB stays independent. Driven as alice, the board moderator of #general.
  const suffix = info.project.name.replace(/[^a-z0-9_-]/gi, '').toLowerCase();
  await login(page, 'alice@retro.test');

  // Fresh source topic (OP) …
  await openNewTopicComposer(page);
  const composer = page.locator('form.composer').first();
  await composer.locator('input[name="title"]').fill(`Split source ${suffix} ${Date.now()}`);
  await composer.locator('textarea.composer-input').fill('Opening post for the split/merge evidence journey.');
  await composer.locator('button[type="submit"]').click();
  await page.waitForURL(/\/t\/\d+-/);
  const sourceId = page.url().match(/\/t\/(\d+)/)![1];

  // … plus a non-OP reply (only replies are movable by a split).
  await page.locator('form#reply textarea[name="body"]').fill('A reply that will be split into its own topic.');
  await page.locator('form#reply button[type="submit"]').click();
  await expect(
    page.locator('.post-body', { hasText: 'A reply that will be split into its own topic.' }),
  ).toBeVisible();

  // Open Topic management and use the enhanced trigger for the same native
  // restructure forms, then capture the Study modal.
  let management = await openTopicTools(page, 'management');
  await management.details.locator('[data-thread-restructure-open]').click();
  const panel = page.locator('.thread-restructure-dialog');
  await expect(panel).toBeVisible();
  await shot(page, info, '50-split-merge-panel');

  // Split the reply out into a brand-new topic (redirects to it, flash "Thread split.").
  await panel.locator('form[action$="/split"] input[name="post_ids[]"]').first().check();
  await panel.locator('form[action$="/split"] input[name="title"]').fill(`Split out ${suffix}`);
  await panel.locator('form[action$="/split"]').getByRole('button', { name: 'Split replies out' }).click();
  await expect(page.locator('.flash')).toContainText('Thread split.');
  const splitId = page.url().match(/\/t\/(\d+)/)![1];
  expect(splitId).not.toEqual(sourceId);

  // Merge the split-out topic back into the source (both in #general, which
  // alice moderates) — redirects to the source, flash "Thread merged.".
  management = await openTopicTools(page, 'management');
  await management.details.locator('[data-thread-restructure-open]').click();
  const mergePanel = page.locator('.thread-restructure-dialog');
  await expect(mergePanel).toBeVisible();
  await mergePanel.locator('form[action$="/merge"] input[name="target_thread_id"]').fill(sourceId);
  await mergePanel.locator('form[action$="/merge"]').getByRole('button', { name: 'Merge topics' }).click();
  await expect(page.locator('.flash')).toContainText('Thread merged.');
  await shot(page, info, '51-thread-merged');
});

test('phase 4 custom profile fields: member self-edit and public display', async ({ page }, info) => {
  // custom_profile_fields graduated to default-on (GA 2026-07-03). Bounded (3)
  // member-authored label/value facts editable on /settings/account and shown on
  // the public profile. Driven as carol (isolated from the profile-media/appeals
  // fixtures); replaceForUser makes the fill idempotent across the re-run.
  await login(page, 'carol@retro.test');
  await visit(page, '/settings/account');

  const fieldsPanel = page.locator('.custom-profile-fields');
  await expect(fieldsPanel).toBeVisible();
  await fieldsPanel.locator('input[name="custom_label_1"]').fill('Favourite editor');
  await fieldsPanel.locator('input[name="custom_value_1"]').fill('Vim');
  await fieldsPanel.locator('input[name="custom_label_2"]').fill('Timezone');
  await fieldsPanel.locator('input[name="custom_value_2"]').fill('UTC');
  await shot(page, info, '52-custom-profile-fields-edit');

  await page.getByRole('button', { name: 'Save changes' }).click();
  await page.waitForURL(/\/settings\/account$/);
  await expect(page.locator('.flash')).toContainText('Your profile has been updated.');

  // Public profile (Overview tab) renders the saved facts.
  await visit(page, '/u/carol');
  const publicFields = page.locator('.profile-fields');
  await expect(publicFields).toBeVisible();
  await expect(publicFields).toContainText('Favourite editor');
  await expect(publicFields).toContainText('Vim');
  await expect(publicFields).toContainText('Timezone');
  await expect(publicFields).toContainText('UTC');
  await shot(page, info, '53-custom-profile-fields-profile');
});
