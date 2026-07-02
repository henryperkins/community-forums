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
  });
}

async function visit(page: Page, url: string): Promise<void> {
  const resp = await page.goto(url);
  expect(resp, `no response for ${url}`).not.toBeNull();
  expect(resp!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
}

async function login(page: Page, email: string): Promise<void> {
  await page.goto('/login');
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
  // topic_workflow graduated to default-on (GA 2026-07-01). Drive the no-JS
  // workflow bar as alice — moderator of #general (staff), which #general opts
  // into assignment via assignment_mode=staff (seed). Each control is a plain
  // form POST → PRG redirect back to the thread; the summary bar reflects it.
  await login(page, 'alice@retro.test');

  await visit(page, '/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await page.waitForURL(/\/t\//);
  await expect(page.locator('.wf-bar')).toBeVisible();

  // Status: staff moves the topic to "Needs answer".
  await page.locator('#thread-status').selectOption('needs_answer');
  await page.locator('.wf-actions input[name="reason"]').fill('Needs a reply');
  await page.getByRole('button', { name: 'Update status' }).click();
  await expect(page.locator('.wf-bar')).toContainText('Needs answer');

  // Snooze: a personal reminder (no cross-user effect).
  await page.locator('#thread-snooze').selectOption('tomorrow');
  await page.getByRole('button', { name: 'Snooze', exact: true }).click();
  await expect(page.locator('.wf-bar')).toContainText('Snoozed until');

  // Assignment: staff assigns the topic to @bob (board is opted into assignment).
  await page.locator('#thread-assignee').fill('bob');
  await page.getByRole('button', { name: 'Assign', exact: true }).click();
  await expect(page.locator('.wf-bar')).toContainText('Assigned to @bob');
  await expect(page.getByRole('button', { name: 'Unassign' })).toBeVisible();

  // Status history is surfaced (audit trail) — expand it so the evidence
  // screenshot captures the recorded transitions.
  await page.locator('.wf-history > summary').click();
  await expect(page.locator('.wf-history-list')).toContainText('Needs answer');

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
  // Rules list newest-first, so the rule created here is always the first row.
  const form = page.locator('form.stacked[action="/admin/badge-rules"]');
  await form.locator('select[name="badge_id"]').selectOption({ index: 0 });
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
    .getByRole('button', { name: 'Archive' }).click();
  await expect(page).toHaveURL(/\/admin\/structure/);

  await visit(page, '/c/feedback');
  await expect(page.locator('[data-archived-banner]')).toBeVisible();
  await expect(page.locator('details.composer-details')).toHaveCount(0);
  await shot(page, info, '22-board-archived-readonly');

  // Unarchive restores the composer affordance.
  await visit(page, '/admin/structure');
  await page.locator('li.admin-board-row', { hasText: 'Feedback' })
    .getByRole('button', { name: 'Unarchive' }).click();
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
  await page.getByRole('link', { name: 'Email' }).click();
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
