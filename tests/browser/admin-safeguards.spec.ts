import { test, expect, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/admin-safeguards-2026-10-23');
function fixture(action = 'state') {
  return JSON.parse(execFileSync(process.env.PHP_BINARY ?? 'php', [path.join(__dirname, 'admin-safeguards-fixture.php'), action], { cwd: root, env: process.env, encoding: 'utf8' }));
}
async function login(page: Page) {
  await page.goto('/login');
  await page.locator('[name="email"]').fill('admin@retro.test');
  await page.locator('[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}
async function shot(page: Page, info: TestInfo, name: string) {
  mkdirSync(path.join(evidence, info.project.name), { recursive: true });
  await page.screenshot({ path: path.join(evidence, info.project.name, `${name}.png`), fullPage: true, animations: 'disabled' });
}
async function noOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth));
}

test.beforeEach(() => fixture('reset'));

test('block review preserves phrases through editing and cancel before a confirmed save', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/moderation');
  const form = page.locator('form[action="/admin/moderation"]');
  const draft = '\nFirst phrase\n<script>literal phrase</script>\nLast phrase';
  await form.locator('[name="antiabuse_mode"]').selectOption('block');
  await form.locator('[name="antiabuse_blocked_words"]').fill(draft);
  await form.getByRole('button', { name: 'Save anti-abuse settings' }).click();
  await expect(page.getByRole('heading', { name: 'Review Block mode' })).toBeVisible();
  await expect(form.locator('textarea')).toHaveValue(draft);
  expect(fixture().mode).toBe('hold');
  expect(fixture().words).toEqual(['original phrase']);
  await noOverflow(page);
  await shot(page, info, 'block-review');
  await form.getByRole('button', { name: 'Back to editing' }).click();
  await expect(form.locator('textarea')).toHaveValue(draft);
  await form.getByRole('button', { name: 'Save anti-abuse settings' }).click();
  await form.getByRole('link', { name: 'Cancel', exact: true }).click();
  await expect(form.locator('[name="antiabuse_mode"]')).toHaveValue('hold');
  expect(fixture().words).toEqual(['original phrase']);
  await form.locator('[name="antiabuse_mode"]').selectOption('block');
  await form.locator('textarea').fill(draft);
  await form.getByRole('button', { name: 'Save anti-abuse settings' }).click();
  await form.getByRole('button', { name: 'Confirm Block mode' }).click();
  await expect(page.getByRole('status')).toContainText('Anti-abuse settings updated.');
  expect(fixture().mode).toBe('block');
  expect(fixture().words).toEqual(draft.trim().split('\n'));
});

test('member expiry uses UTC native controls and a server-evaluated preset', async ({ page }, info) => {
  const member = fixture();
  await login(page);
  await page.goto(`/admin/users/${member.id}`);
  await page.getByText('Suspend or ban', { exact: true }).click();
  const form = page.locator(`form[action="/admin/users/${member.id}/suspend"]`);
  await expect(form.locator('[name="until"]')).toHaveAttribute('type', 'datetime-local');
  await expect(form).toContainText('Enter UTC, not your local time.');
  await form.locator('[name="reason"]').fill('Native UTC evidence');
  await form.locator('[name="until"]').fill('2031-06-04T12:34:56');
  await noOverflow(page);
  await shot(page, info, 'utc-restriction');
  await form.getByRole('button', { name: 'Suspend', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('User suspended.');
  expect(fixture().until).toBe('2031-06-04 12:34:56');
  fixture('reset');
  await page.goto(`/admin/users/${member.id}`);
  await page.getByText('Suspend or ban', { exact: true }).click();
  await form.locator('[name="reason"]').fill('Preset evidence');
  await form.locator('[name="duration"]').selectOption('day');
  const before = Date.now();
  await form.getByRole('button', { name: 'Suspend', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('User suspended.');
  const expires = Date.parse(fixture().until.replace(' ', 'T') + 'Z');
  expect(expires).toBeGreaterThanOrEqual(before + 86400_000 - 1000);
  expect(expires).toBeLessThanOrEqual(Date.now() + 86400_000);
});

test('audit names readable changes and safely discloses unknown payloads', async ({ page }, info) => {
  fixture('audit');
  await login(page);
  await page.goto('/admin/audit?target_type=user');
  const changed = page.locator('tbody tr').filter({ hasText: 'Safeguards browser evidence' }).first();
  await expect(changed).toContainText('@admin');
  await expect(changed).toContainText('Evidence member (@safeguardtarget)');
  await expect(changed).toContainText('Changed role');
  await expect(changed).toContainText('Member → Moderator');
  await expect(changed.getByRole('link', { name: /Evidence member/ })).toHaveAttribute('href', `/admin/users/${fixture().id}`);
  const unknown = page.locator('tbody tr').filter({ hasText: 'Safeguards unknown action' }).first();
  await expect(unknown).toContainText('System or deleted actor');
  await expect(unknown).toContainText('Unavailable member #987654321');
  await unknown.getByText('Raw details', { exact: true }).click();
  await expect(unknown.locator('details')).toContainText('future.action');
  await expect(unknown.locator('details')).toContainText('<script>must stay text</script>');
  await expect(unknown.locator('script')).toHaveCount(0);
  await noOverflow(page);
  // Scan only the changed content; the existing sticky console chrome is qualified separately.
  const accessibility = await new AxeBuilder({ page }).include('.audit-table-card').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  expect(accessibility.violations.filter(v => v.impact === 'serious' || v.impact === 'critical')).toEqual([]);
  await shot(page, info, 'readable-audit');
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });
  test('registration and block confirmations remain native forms', async ({ page }, info) => {
    await login(page);
    await page.goto('/admin/settings');
    const form = page.locator('form[action="/admin/settings/registration"]');
    await form.locator('[name="registration_mode"]').selectOption('closed');
    await form.getByRole('button', { name: 'Save registration mode' }).click();
    await expect(page.getByRole('heading', { name: 'Review closing registration' })).toBeVisible();
    expect(fixture().registration).toBe('open');
    await noOverflow(page);
    await shot(page, info, 'registration-no-js');
    await form.getByRole('button', { name: 'Back to editing' }).click();
    await expect(form.locator('select')).toHaveValue('closed');
    await form.getByRole('button', { name: 'Save registration mode' }).click();
    await form.getByRole('button', { name: 'Confirm closing registration' }).click();
    await expect(page.getByRole('status')).toContainText('Registration settings updated.');
    expect(fixture().registration).toBe('closed');
    await page.goto('/admin/moderation');
    const moderation = page.locator('form[action="/admin/moderation"]');
    await moderation.locator('[name="antiabuse_mode"]').selectOption('block');
    await moderation.locator('textarea').fill('No script phrase');
    await moderation.getByRole('button', { name: 'Save anti-abuse settings' }).click();
    expect(fixture().mode).toBe('hold');
    await moderation.getByRole('button', { name: 'Confirm Block mode' }).click();
    await expect(page.getByRole('status')).toContainText('Anti-abuse settings updated.');
    expect(fixture().words).toEqual(['No script phrase']);
  });
  test('bulk expiry and a rejected invalid date keep the selected members and draft', async ({ page }, info) => {
    const member = fixture();
    await login(page);
    await page.goto('/admin/users?q=safeguardtarget');
    await page.locator(`input[name="selected[]"][value="${member.id}"]`).check();
    await page.locator('[name="bulk_action"]').selectOption('suspend');
    await page.getByRole('button', { name: 'Review and apply…' }).click();
    const form = page.locator('form[action="/admin/users/bulk/apply"]');
    await expect(form.locator('[name="until"]')).toHaveAttribute('type', 'datetime-local');
    await form.locator('[name="reason"]').fill('Preserved no-JS draft');
    // Model a crafted/stale form that bypasses native validity; the real
    // server response must retain the invalid value instead of native erasure.
    await form.locator('[name="until"]').evaluate((input: HTMLInputElement) => { input.type = 'text'; });
    await form.locator('[name="until"]').fill('2031-02-30T12:00');
    const [response] = await Promise.all([
      page.waitForResponse(response => response.url().endsWith('/admin/users/bulk/apply') && response.request().method() === 'POST'),
      form.getByRole('button', { name: 'Suspend 1 member', exact: true }).click(),
    ]);
    expect(response.status()).toBe(422);
    await expect(form.locator('[name="until"]')).toHaveValue('2031-02-30T12:00');
    await expect(form.locator('[name="until"]')).toHaveAttribute('type', 'text');
    await expect(form.locator('[name="reason"]')).toHaveValue('Preserved no-JS draft');
    await expect(form.locator('[name="selected[]"]')).toHaveValue(String(member.id));
    expect(fixture().status).toBe('active');
    await shot(page, info, 'invalid-date-no-js');
    await form.locator('[name="until"]').fill('2031-06-04T12:34');
    await noOverflow(page);
    await shot(page, info, 'bulk-utc-no-js');
    await form.getByRole('button', { name: 'Suspend 1 member', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Suspended 1 member');
    expect(fixture().until).toBe('2031-06-04 12:34:00');
  });
});
