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

  await page.reload();
  await openNewTopicComposer(page);
  await expect(textarea).toHaveValue(/Browser evidence/);

  await visit(page, '/drafts');
  await expect(page.locator('[data-drafts-list] article')).toContainText('New topic');
  await expect(page.locator('[data-drafts-list] article')).toContainText('Browser evidence');
  await shot(page, info, '16-drafts-view');
  const discard = page.getByRole('button', { name: 'Discard' });
  await discard.evaluate((el) => el.scrollIntoView({ block: 'center', inline: 'nearest' }));
  await discard.click();
  await expect(page.locator('[data-drafts-list]')).toContainText('No saved drafts');

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
  await visit(page, '/drafts');
  await expect(page.locator('[data-drafts-list]')).toContainText('No saved drafts');
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
  await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', () => resolve()));
  const address = server.address();
  if (typeof address === 'string' || address === null) {
    throw new Error('expected TCP server address');
  }
  const hookUrl = `http://127.0.0.1:${address.port}/hook`;

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

    await expect(page.getByText(/will not be shown again/)).toBeVisible();
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
    const worker = execFileAsync('php', ['bin/console', 'worker:webhooks'], {
      cwd: repoRoot,
      env: {
        ...process.env,
        DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e',
        WEBHOOK_ALLOW_HTTP: 'true',
        WEBHOOK_ALLOWED_PRIVATE_CIDRS: '127.0.0.1/32',
        MAIL_DRIVER: 'array',
      },
    });
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
  await expect(page.getByRole('link', { name: 'bob' })).toBeVisible();
  await shot(page, info, '14-admin-users');

  await page.getByRole('link', { name: 'bob' }).click();
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
