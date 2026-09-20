import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/unified-notifications-and-settings/account-settings-repairs');
type Fixture = {
  id: number; board_id: number; status: string; display_name: string; bio: string;
  avatar_path: string | null; has_password: boolean; digest_hour: number | null; pause_all_email: boolean;
  subscription: null | { id: number; target_id: number; frequency: string; email_enabled: number; in_app_enabled: number };
  saved_feeds: Array<{ id: number; name: string; filter_json: string; digest_enabled: number }>;
  deliveries: Array<{ id: number; subject: string; status: string; error: string | null; attempt_count: number; sent_at: string | null; message_id: string | null }>;
};

function fixture(command = 'reset'): Fixture {
  return JSON.parse(execFileSync('php', ['tests/browser/account-settings-repairs-fixture.php', command], {
    cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array' }, encoding: 'utf8',
  }));
}

async function login(page: Page, email = 'settings-repair@retro.test'): Promise<void> {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('button[type="submit"]').click();
  await expect(page).not.toHaveURL(/\/login/);
}

async function capture(page: Page, info: TestInfo, name: string): Promise<void> {
  const directory = path.join(evidence, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: true });
}

async function post(page: Page, url: string, data: Record<string, string> = {}) {
  const token = await page.locator('input[name="_token"]').first().inputValue();
  return page.request.post(url, { form: { _token: token, ...data }, maxRedirects: 0 });
}

test.describe('account settings repairs without JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test.beforeEach(() => fixture());

  test('suspension cannot be removed through stale lifecycle actions', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/account/lifecycle');
    fixture('suspend');
    expect((await post(page, '/settings/account/deactivate', { current_password: 'password123' })).status()).toBe(422);
    expect((await post(page, '/settings/account/reactivate')).status()).toBe(422);
    expect((await post(page, '/settings/account', { display_name: 'Forbidden write' })).status()).toBe(403);
    expect(fixture('inspect').status).toBe('suspended');
    await page.reload();
    await expect(page.locator('form[action="/settings/account/deactivate"]')).toHaveCount(0);
    await capture(page, info, '01-suspension-retained');
  });

  test('scheduled deletion stays restricted and cancellation retains a later suspension', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/account/lifecycle');
    const form = page.locator('form[action="/settings/account/delete/request"]');
    await form.locator('[name="current_password"]').fill('password123');
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('form[action="/settings/account/delete/cancel"]')).toBeVisible();
    expect((await post(page, '/settings/account/deactivate', { current_password: 'password123' })).status()).toBe(422);
    expect((await post(page, '/settings/account/reactivate')).status()).toBe(422);
    expect((await post(page, '/settings/account', { display_name: 'Forbidden write' })).status()).toBe(403);
    fixture('suspend');
    await page.reload();
    await page.locator('form[action="/settings/account/delete/cancel"] button').click();
    expect(fixture('inspect').status).toBe('suspended');
    expect((await post(page, '/settings/account', { display_name: 'Still forbidden' })).status()).toBe(403);
    await capture(page, info, '02-deletion-cancel-retains-restriction');
  });

  for (const hold of ['deactivated', 'pending_deletion']) {
    test(`suspension expiry retains the ${hold} hold until explicit recovery`, async ({ page, browser }, info) => {
      const data = fixture('inspect');
      await login(page);
      await page.goto('/settings/account/lifecycle');
      const action = hold === 'deactivated' ? 'deactivate' : 'delete/request';
      const form = page.locator(`form[action="/settings/account/${action}"]`);
      await form.locator('[name="current_password"]').fill('password123');
      await form.locator('button[type="submit"]').click();
      const adminContext = await browser.newContext({ baseURL: new URL(page.url()).origin, javaScriptEnabled: false });
      try {
        const admin = await adminContext.newPage();
        await login(admin, 'admin@retro.test');
        await admin.goto(`/admin/users/${data.id}`);
        const suspend = admin.locator(`form[action="/admin/users/${data.id}/suspend"]`);
        await suspend.locator('[name="reason"]').fill('Temporary browser evidence suspension');
        await suspend.locator('[name="until"]').fill('2030-01-01 00:00:00');
        await suspend.getByRole('button', { name: 'Suspend', exact: true }).click();
        expect(fixture('inspect').status).toBe(hold);
        await page.reload();
        await expect(page.locator('form[action="/settings/account/reactivate"]')).toHaveCount(0);
        fixture('expire-suspension');
        await page.reload();
        expect((await post(page, '/settings/account', { display_name: 'Still blocked after expiry' })).status()).toBe(403);
        expect(fixture('inspect').status).toBe(hold);
        const recovery = hold === 'deactivated' ? 'reactivate' : 'delete/cancel';
        await expect(page.locator(`form[action="/settings/account/${recovery}"]`)).toBeVisible();
        await capture(page, info, `02-expiry-retains-${hold}`);
        await page.locator(`form[action="/settings/account/${recovery}"] button`).click();
        expect(fixture('inspect').status).toBe('active');
        expect((await post(page, '/settings/account', { display_name: 'Recovered explicitly' })).status()).toBe(303);
      } finally {
        await adminContext.close();
      }
    });
  }

  test('invalid TOTP retains confirmation on the same pending enrollment after reload', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/security');
    const enroll = page.locator('form[action="/settings/security/totp/enroll"]');
    await enroll.locator('[name="current_password"]').fill('password123');
    await enroll.locator('button[type="submit"]').click();
    const secret = await page.getByLabel('Authenticator secret', { exact: true }).inputValue();
    const confirm = page.locator('form[action="/settings/security/totp/confirm"]');
    await confirm.locator('[name="current_password"]').fill('password123');
    // A non-numeric code deterministically fails regardless of the current TOTP.
    await confirm.locator('[name="totp_code"]').fill('invalid');
    const [response] = await Promise.all([
      page.waitForResponse((result) => result.request().method() === 'POST' && result.url().endsWith('/settings/security/totp/confirm')),
      confirm.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBe(422);
    await expect(confirm).toBeVisible();
    // Establish a GET before reloading: reloading a POST re-submits that action.
    await page.goto('/settings/security');
    await page.reload();
    await expect(confirm).toBeVisible();
    await expect(confirm.locator('[name="current_password"]')).toHaveValue('');
    await expect(confirm.locator('[name="totp_code"]')).toHaveValue('');
    await expect(page.getByLabel('Authenticator secret', { exact: true })).toHaveCount(0);
    expect((await page.content()).includes(secret), 'Reload must not disclose the setup secret').toBe(false);
    await capture(page, info, '03-totp-pending-reload');
    const code = execFileSync('php', ['-r', "require 'vendor/autoload.php'; echo (new \\App\\Security\\Totp())->code($argv[1]);", secret], {
      cwd: root, encoding: 'utf8',
    });
    await confirm.locator('[name="current_password"]').fill('password123');
    await confirm.locator('[name="totp_code"]').fill(code);
    await confirm.locator('button[type="submit"]').click();
    await expect(page.locator('ul.code-list code')).toHaveCount(10);
    await page.goto('/settings/security');
    await page.reload();
    await expect(page.locator('ul.code-list code')).toHaveCount(0);
    await expect(confirm).toHaveCount(0);
  });

  test('pending TOTP restart requires the current password and returns fresh setup once', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/security');
    const enroll = page.locator('form[action="/settings/security/totp/enroll"]');
    await enroll.locator('[name="current_password"]').fill('password123');
    await enroll.locator('button[type="submit"]').click();
    const original = await page.getByLabel('Authenticator secret', { exact: true }).inputValue();
    expect((await post(page, '/settings/security/totp/enroll', { current_password: 'incorrect' })).status()).toBe(422);
    await page.goto('/settings/security');
    await page.reload();
    await expect(page.locator('form[action="/settings/security/totp/confirm"]')).toBeVisible();
    await expect(enroll).toBeVisible();
    await enroll.locator('[name="current_password"]').fill('password123');
    await enroll.getByRole('button', { name: /restart setup/i }).click();
    const renewed = await page.getByLabel('Authenticator secret', { exact: true }).inputValue();
    expect(renewed !== original, 'Explicit restart replaces the pending secret').toBe(true);
    await page.goto('/settings/security');
    await page.reload();
    await expect(page.getByLabel('Authenticator secret', { exact: true })).toHaveCount(0);
    await expect(page.locator('form[action="/settings/security/totp/confirm"]')).toBeVisible();
    await capture(page, info, '03-totp-restarted-pending');
  });

  test('passwordless member can set a password from Security with retained validation', async ({ page }, info) => {
    await login(page);
    fixture('passwordless');
    await page.goto('/settings/account/lifecycle');
    await expect(page.locator('input[name="current_password"]')).toHaveCount(0);
    await page.locator('a[href="/settings/security#set-password"]').first().click();
    await expect(page).toHaveURL(/\/settings\/security#set-password$/);
    const form = page.locator('form[action="/settings/security/set-password"]');
    await expect(form).toBeVisible();
    await expect(page.locator('input[name="current_password"]')).toHaveCount(0);
    await form.locator('[name="new_password"]').fill('new-password123');
    await form.locator('[name="new_password_confirm"]').fill('different-password123');
    await form.locator('button[type="submit"]').click();
    await expect(page.getByText('The passwords do not match.', { exact: true })).toBeVisible();
    await expect(form.locator('[name="new_password"]')).toHaveValue('');
    await form.locator('[name="new_password"]').fill('new-password123');
    await form.locator('[name="new_password_confirm"]').fill('new-password123');
    await form.locator('button[type="submit"]').click();
    expect(fixture('inspect').has_password).toBe(true);
    await capture(page, info, '04-password-set');
  });

  test('invalid avatar preserves unsaved profile fields', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/account');
    await page.locator('[name="display_name"]').fill('Draft display name');
    await page.locator('[name="bio"]').fill('Draft biography');
    await page.locator('[name="location"]').fill('Draft place');
    await page.locator('[name="pronouns"]').fill('they/them');
    await page.locator('[name="custom_label_1"]').fill('Draft label');
    await page.locator('[name="custom_value_1"]').fill('Draft value');
    await page.locator('[name="avatar"]').setInputFiles({ name: 'bad.txt', mimeType: 'text/plain', buffer: Buffer.from('not an image') });
    await page.locator('button').filter({ hasText: /^Upload avatar$/ }).click();
    for (const [name, value] of Object.entries({ display_name: 'Draft display name', bio: 'Draft biography', location: 'Draft place', pronouns: 'they/them', custom_label_1: 'Draft label', custom_value_1: 'Draft value' })) {
      await expect(page.locator(`[name="${name}"]`)).toHaveValue(value);
    }
    expect(fixture('inspect').display_name).toBe('Settings member');
    await capture(page, info, '05-avatar-error-retains-draft');
  });

  test('avatar upload and removal keep a draft until Save profile', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/account');
    await page.locator('[name="display_name"]').fill('Keep this draft');
    await page.locator('[name="bio"]').fill('Keep this biography');
    const png = execFileSync('php', ['-r', '$im = imagecreatetruecolor(8, 8); imagefilledrectangle($im, 0, 0, 7, 7, imagecolorallocate($im, 10, 120, 200)); imagepng($im);']);
    await page.locator('[name="avatar"]').setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: png });
    await page.locator('button').filter({ hasText: /^Upload avatar$/ }).click();
    await expect(page.locator('[name="display_name"]')).toHaveValue('Keep this draft');
    await expect(page.locator('[name="bio"]')).toHaveValue('Keep this biography');
    expect(fixture('inspect').display_name).toBe('Settings member');
    expect(fixture('inspect').avatar_path).toMatch(/^\/media\/\d+$/);
    await page.locator('button').filter({ hasText: /^Remove avatar$/ }).click();
    await expect(page.locator('[name="display_name"]')).toHaveValue('Keep this draft');
    expect(fixture('inspect').avatar_path).toBeNull();
    await capture(page, info, '06-avatar-removed-draft-retained');
    await page.locator('form[action="/settings/account"] button[type="submit"]:not([formaction])').click();
    expect(fixture('inspect').display_name).toBe('Keep this draft');
    expect(fixture('inspect').bio).toBe('Keep this biography');
  });

  test('Enter in a profile field saves the profile without triggering an avatar action', async ({ page }) => {
    await login(page);
    await page.goto('/settings/account');
    const name = page.locator('[name="display_name"]');
    await name.fill('Saved with Enter');
    const [response] = await Promise.all([
      page.waitForResponse((result) => result.request().method() === 'POST'),
      name.press('Enter'),
    ]);
    expect(new URL(response.url()).pathname).toBe('/settings/account');
    expect(response.status()).toBe(303);
    expect(fixture('inspect').display_name).toBe('Saved with Enter');
    expect(fixture('inspect').avatar_path).toBeNull();
  });

  test('saved-feed validation preserves the chosen board and digest option', async ({ page }, info) => {
    const data = fixture('inspect');
    await login(page);
    await page.goto('/settings/boards');
    const form = page.locator('form[action="/settings/saved-feeds"]');
    await form.locator('[name="name"]').fill('   ');
    await form.locator('[name="board_id"]').selectOption(String(data.board_id));
    await form.locator('[name="digest_enabled"]').check();
    await form.locator('button[type="submit"]').click();
    await expect(form.locator('[name="board_id"]')).toHaveValue(String(data.board_id));
    await expect(form.locator('[name="digest_enabled"]')).toBeChecked();
    await expect(form.locator('[name="name"]')).toHaveAttribute('aria-invalid', 'true');
    await capture(page, info, '07-feed-validation-retained');
  });

  for (const state of ['suspended', 'banned', 'deactivated', 'pending_deletion']) {
    test(`${state} member can turn off a saved-feed digest without reconfiguring the source`, async ({ page }, info) => {
      const data = fixture('inspect');
      await login(page);
      await page.goto('/settings/boards');
      const create = page.locator('form[action="/settings/saved-feeds"]');
      await create.locator('[name="name"]').fill('Restricted reading');
      await create.locator('[name="board_id"]').selectOption(String(data.board_id));
      await create.locator('[name="digest_enabled"]').check();
      await create.locator('button[type="submit"]').click();
      const original = fixture('inspect').saved_feeds[0];
      expect(Number(original.digest_enabled)).toBe(1);
      fixture('private-board');
      fixture(`restrict-${state}`);
      await page.goto('/settings/boards');
      const url = `/settings/saved-feeds/${original.id}`;
      await page.locator(`form[action="${url}"]`).getByRole('button', { name: 'Turn off digest', exact: true }).click();
      const disabled = fixture('inspect').saved_feeds[0];
      expect(Number(disabled.digest_enabled)).toBe(0);
      expect(disabled.name).toBe(original.name);
      expect(disabled.filter_json).toBe(original.filter_json);
      expect((await post(page, url, { digest_enabled: '1' })).status()).toBe(403);
      expect((await post(page, url, { digest_enabled: '0', name: 'Forbidden rename' })).status()).toBe(403);
      expect(fixture('inspect').saved_feeds[0].name).toBe(original.name);
      expect(fixture('inspect').status).toBe(state);
      await capture(page, info, `07-feed-opt-out-${state}`);
    });

    test(`${state} member can turn off inaccessible subscriptions and all digest delivery`, async ({ page }, info) => {
      await login(page);
      const enabled = fixture('delivery-on').subscription!;
      fixture('private-board');
      fixture(`restrict-${state}`);
      await page.goto('/settings/notifications');
      expect((await page.content()).includes('Settings evidence topic')).toBe(false);
      const off = page.locator(`form[action="/settings/notifications/subscriptions/${enabled.id}"]`).getByRole('button', { name: 'Turn off', exact: true });
      await expect(off).toBeVisible();
      await off.click();
      const disabled = fixture('inspect').subscription!;
      expect(disabled.frequency).toBe('off');
      expect(Number(disabled.email_enabled)).toBe(0);
      expect(Number(disabled.in_app_enabled)).toBe(0);
      expect((await post(page, '/settings/account', { display_name: 'Still blocked' })).status()).toBe(403);
      // A stale target-specific form must offer the same owner opt-out.
      fixture('delivery-on');
      expect((await post(page, `/t/${enabled.target_id}/subscribe`, { frequency: 'off' })).status()).toBe(303);
      expect(fixture('inspect').subscription!.frequency).toBe('off');
      await page.goto('/settings/notifications');
      const global = page.locator('form[action="/settings/notifications"]');
      await global.locator('[name="digest_hour"]').selectOption('');
      await global.locator('[name="pause_all_email"]').check();
      await global.locator('button[type="submit"]').click();
      expect(fixture('inspect').digest_hour).toBeNull();
      expect(fixture('inspect').pause_all_email).toBe(true);
      expect(fixture('inspect').status).toBe(state);
      await capture(page, info, `07-opt-out-${state}`);
    });
  }

  test('mobile settings navigation keeps the first control in view and works by keyboard', async ({ page }, info) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);
    for (const [url, label] of [['/settings/security', 'Security'], ['/settings/account', 'Profile'], ['/settings/notifications', 'Notifications']]) {
      await page.goto(url);
      const nav = page.locator('[data-settings-mobile-nav]');
      const summary = nav.locator(':scope > summary');
      await expect(summary).toHaveText(`Settings: ${label}`);
      await expect(nav).not.toHaveAttribute('open', '');
      const control = page.locator('.settings-pane input:not([type="hidden"]):not(:disabled):visible, .settings-pane select:not(:disabled):visible').first();
      const box = await control.boundingBox();
      expect(box).not.toBeNull();
      expect(box!.y + box!.height, `${label}: first editable control`).toBeLessThan(844);
      await summary.focus();
      await page.keyboard.press('Enter');
      await expect(nav).toHaveAttribute('open', '');
      await expect(nav.locator('[aria-current="page"]')).toHaveText(label);
      await page.keyboard.press('Enter');
      await expect(nav).not.toHaveAttribute('open', '');
      await capture(page, info, `08-mobile-${label.toLowerCase()}`);
    }
    await page.setViewportSize({ width: 320, height: 844 });
    const width = await page.evaluate(() => ({ content: document.documentElement.scrollWidth, viewport: innerWidth }));
    expect(width.content).toBeLessThanOrEqual(width.viewport + 1);
  });

  test('saved feeds can be opened, managed, and remain scoped after board access is lost', async ({ page }, info) => {
    const data = fixture('inspect');
    await login(page);
    await page.goto('/settings/boards');
    const create = page.locator('form[action="/settings/saved-feeds"]');
    await create.locator('[name="name"]').fill('Morning reading');
    await create.locator('[name="board_id"]').selectOption(String(data.board_id));
    await create.locator('[name="digest_enabled"]').check();
    await create.locator('button[type="submit"]').click();
    const link = page.locator('.saved-feed-card a[href^="/feeds/saved/"]').filter({ hasText: 'Morning reading' });
    await expect(link).toBeVisible();
    const url = (await link.getAttribute('href'))!;
    const id = url.split('/').at(-1)!;
    await expect(page.locator(`#sidebar-nav a[href="${url}"]`)).toHaveCount(1);
    await link.click();
    await expect(page).toHaveURL(new RegExp(`${url}$`));
    await expect(page.getByRole('heading', { level: 1, name: 'Morning reading' })).toBeVisible();
    await expect(page.locator('.feed-thread').filter({ hasText: 'Settings evidence topic' })).toBeVisible();
    await capture(page, info, '10-saved-feed-open');
    await page.goto('/compose');
    await expect(page.locator(`#sidebar-nav a[href="${url}"]`)).toHaveCount(0);
    const destination = page.locator('[data-compose-board-picker="settings-repair-board"]');
    await expect(destination).toHaveCount(1);
    await destination.click();
    await expect(page).toHaveURL(/\/compose\?board=settings-repair-board$/);
    await expect(destination).toHaveAttribute('aria-pressed', 'true');
    await page.goto('/settings/boards');
    const edit = page.locator(`form[action="/settings/saved-feeds/${id}"]`).filter({ has: page.locator('input[name="name"]') });
    await edit.locator('[name="name"]').fill('Focused reading');
    await edit.locator('button[type="submit"]').click();
    await expect(page.locator(`.saved-feed-card a[href="${url}"]`)).toHaveText('Focused reading');
    fixture('private-board');
    await page.goto(url);
    await expect(page.locator('.feed-thread')).toHaveCount(0);
    expect((await page.content()).includes('Settings evidence topic')).toBe(false);
    // A revoked selected board must not turn into the all-boards feed.
    await capture(page, info, '11-saved-feed-revoked');
    await page.goto('/settings/boards');
    await page.locator(`form[action="/settings/saved-feeds/${id}/delete"] button`).click();
    await expect(page.locator(`a[href="${url}"]`)).toHaveCount(0);
    expect((await page.request.get(url)).status()).toBe(404);
  });

  test('folder shortcuts can be renamed, removed, and deleted without deleting the board', async ({ page }, info) => {
    const data = fixture('inspect');
    await login(page);
    await page.goto('/settings/boards');
    const create = page.locator('form[action="/settings/board-folders"]');
    await create.locator('[name="name"]').fill('Work reading');
    await create.locator('button[type="submit"]').click();
    const add = page.locator('form[action="/settings/board-folders/0/boards"]');
    const id = await add.locator('[name="folder_id"]').inputValue();
    await add.locator('[name="board_id"]').selectOption(String(data.board_id));
    await add.locator('button[type="submit"]').click();
    await expect(page.locator('#sidebar-nav')).toContainText('Work reading');
    await expect(page.locator('#sidebar-nav a[href="/c/settings-repair-board"]')).toHaveCount(2);
    const rename = page.locator(`form[action="/settings/board-folders/${id}/rename"]`);
    await rename.locator('[name="name"]').fill('Reference reading');
    await rename.locator('button[type="submit"]').click();
    await expect(page.locator('#sidebar-nav')).toContainText('Reference reading');
    await capture(page, info, '12-folder-shortcut');
    await page.goto('/compose');
    await expect(page.locator('#sidebar-nav')).not.toContainText('Reference reading');
    await expect(page.locator('[data-compose-board-picker="settings-repair-board"]')).toHaveCount(1);
    await expect(page.locator('#sidebar-nav .board-rail-foot')).toBeVisible();
    await page.goto('/settings/boards');
    await page.locator(`form[action="/settings/board-folders/${id}/boards/${data.board_id}/remove"] button`).click();
    await expect(page.locator('#sidebar-nav a[href="/c/settings-repair-board"]')).toHaveCount(1);
    await page.locator(`form[action="/settings/board-folders/${id}/delete"] button`).click();
    await expect(page.locator('#sidebar-nav')).not.toContainText('Reference reading');
    expect((await page.request.get('/c/settings-repair-board')).status()).toBe(200);
  });

  test('sessions show readable labels, keep raw details escaped, and revoke other devices', async ({ page }, info) => {
    await login(page);
    fixture('other-sessions');
    await page.goto('/settings/sessions');
    const rows = page.locator('.account-ruled-row');
    await expect(rows).toHaveCount(3);
    const known = rows.filter({ hasText: 'Chrome on Windows' });
    await expect(known.locator('.account-row-name')).toContainText('Chrome on Windows');
    await expect(known.locator('details')).not.toHaveAttribute('open', '');
    await known.locator('details > summary').click();
    await expect(known.locator('details')).toContainText('Chrome/149.0.0.0');
    const unknown = rows.filter({ has: page.locator('.account-row-name', { hasText: 'Unknown device' }) });
    await unknown.locator('details > summary').click();
    await expect(unknown.locator('details')).toContainText('<script>unknown-browser</script>');
    await expect(unknown.locator('script')).toHaveCount(0);
    await expect(page.getByText('This device', { exact: true })).toBeVisible();
    await capture(page, info, '13-sessions-readable');
    await unknown.getByRole('button', { name: 'Sign out', exact: true }).click();
    await expect(rows).toHaveCount(2);
    await page.getByRole('button', { name: 'Log out of all other devices' }).click();
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('This device');
    await capture(page, info, '14-sessions-revoked');
  });

});

test.describe('notification delivery operations without JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test.beforeEach(() => fixture());

  test('terminal delivery rows stay terminal and a valid failed digest can be requeued', async ({ page }, info) => {
    const seeded = fixture('email-ops');
    await login(page, 'admin@retro.test');
    await page.goto('/admin/email?email=settings-repair%40retro.test');
    const rows = page.locator('.notification-delivery-table tbody tr');
    await expect(rows).toHaveCount(4);
    for (const subject of ['Suppressed digest', 'Invalid digest', 'Legacy digest']) {
      const row = rows.filter({ hasText: subject });
      await expect(row).toBeVisible();
      await expect(row.getByRole('button', { name: 'Requeue', exact: true })).toHaveCount(0);
      const job = seeded.deliveries.find((delivery) => delivery.subject === subject)!;
      await post(page, `/admin/email/deliveries/${job.id}/requeue`);
      expect(fixture('inspect').deliveries.find((delivery) => delivery.id === job.id)?.status).toBe(job.status);
    }
    const retry = seeded.deliveries.find((delivery) => delivery.subject === 'Replayable digest')!;
    const requeue = rows.filter({ hasText: 'Replayable digest' }).getByRole('button', { name: 'Requeue', exact: true });
    // Focus must reveal the action inside the table's native horizontal scroll region.
    await requeue.focus();
    await expect(requeue).toBeInViewport();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await capture(page, info, '15-email-terminal-outcomes');
    await requeue.click();
    expect(fixture('inspect').deliveries.find((delivery) => delivery.id === retry.id)?.status).toBe('queued');
    // Pin the capture transport on this CLI process too, not only on the browser server.
    const output = execFileSync('flock', ['/tmp/retroboards-unified-phpunit.lock', 'php', 'bin/console', 'worker:email', '100'], {
      cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array', MAIL_FROM: 'notification-evidence@example.test' }, encoding: 'utf8',
    });
    expect(output).not.toMatch(/fatal|uncaught/i);
    const sent = fixture('inspect').deliveries.find((delivery) => delivery.id === retry.id)!;
    expect(sent.status).toBe('sent');
    expect(Number(sent.attempt_count)).toBe(1);
    expect(sent.sent_at).not.toBeNull();
    expect(sent.message_id).not.toBeNull();
    await page.goto('/admin/email?email=settings-repair%40retro.test');
    await expect(rows.filter({ hasText: 'Replayable digest' })).toContainText('Sent');
    await capture(page, info, '16-email-retry-sent');
  });
});

// Axe uses an injected analysis frame; exercise the same server-rendered forms
// with scripting enabled here. The task flows above independently prove no-JS.
test.describe('account settings accessibility', () => {
  test.beforeEach(() => fixture());
  for (const route of ['security', 'account', 'notifications', 'sessions', 'boards']) {
    test(`settings forms remain accessible in both themes: ${route}`, async ({ page }, info) => {
      if (route === 'notifications') fixture('delivery-on');
      await login(page);
      for (const theme of ['light', 'dark']) {
        if (theme === 'dark') fixture('dark');
        await page.goto(`/settings/${route}`);
        const result = await new AxeBuilder({ page }).include('.settings-screen').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(result.violations.map(({ id, nodes }) => ({ id, targets: nodes.map((node) => node.target) }))).toEqual([]);
        if (theme === 'dark') await capture(page, info, `09-${route}-dark`);
      }
    });
  }

  test('delivery log remains accessible with terminal and replayable outcomes in both themes', async ({ page }, info) => {
    fixture('email-ops');
    await login(page, 'admin@retro.test');
    try {
      for (const theme of ['light', 'dark']) {
        fixture(`admin-${theme}`);
        await page.goto('/admin/email?email=settings-repair%40retro.test');
        const result = await new AxeBuilder({ page }).include('.notification-delivery-card').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(result.violations.map(({ id, nodes }) => ({ id, targets: nodes.map((node) => node.target) }))).toEqual([]);
        await page.getByRole('button', { name: 'Requeue', exact: true }).focus();
        if (theme === 'dark') await capture(page, info, '17-email-outcomes-dark');
      }
    } finally {
      fixture('admin-light');
    }
  });
});
