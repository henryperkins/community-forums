import { expect, test, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = process.env.RB_EVIDENCE_DIR;
if (!evidence) throw new Error('Set RB_EVIDENCE_DIR for the isolated Members/People run.');
const output = path.resolve(root, evidence);
type State = { admin_id: number; target_id: number; same_day_id: number; role_id: number; boards: { id: number; name: string }[]; categories: { id: number; name: string }[]; target_warnings: number; deliveries: number; mode: string };
function fixture(command = 'inspect'): State {
  return JSON.parse(execFileSync('php', ['tests/browser/members-people-remediation-fixture.php', command], {
    cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array' }, encoding: 'utf8',
  }));
}
async function login(page: Page) {
  await page.goto('/login');
  await page.locator('[name=email]').fill('mp-admin@retro.test');
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}
async function capture(page: Page, info: TestInfo, name: string) {
  const directory = path.join(output, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: false });
}
let cookies: Awaited<ReturnType<import('@playwright/test').BrowserContext['cookies']>>;
test.beforeAll(async ({ browser, baseURL }) => {
  fixture('seed');
  const context = await browser.newContext({ baseURL });
  await login(await context.newPage());
  cookies = await context.cookies();
  await context.close();
});
test.beforeEach(async ({ context }) => {
  fixture('reset-actions'); fixture('light'); fixture('flag-default');
  await context.addCookies(cookies);
});
test.afterAll(() => { fixture('light'); fixture('flag-default'); expect(fixture().deliveries).toBe(0); });

test('Joined to includes members throughout the selected day', async ({ page }, info) => {
  await page.goto('/admin/users?q=mp-sameday&joined_to=2025-04-15');
  await capture(page, info, 'joined-to-full-day');
  await expect(page.locator('.member-directory-member-link')).toHaveText(['mp-sameday']);
  await page.goto('/admin/users?q=mp-nextday&joined_to=2025-04-15');
  await expect(page.locator('.member-directory-member-link')).toHaveCount(0);
});

test('member record fits a 320px viewport', async ({ page }, info) => {
  const state = fixture();
  await page.setViewportSize({ width: 320, height: 844 });
  await page.goto(`/admin/users/${state.target_id}`);
  await capture(page, info, 'member-record-320');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
});

test('empty role capabilities are linked, announced and focused without losing draft', async ({ page }, info) => {
  await page.goto('/admin/roles');
  const form = page.locator('.role-create-form');
  await form.locator('[name=name]').fill('Retained draft role');
  await form.locator('[name=description]').fill('Keep the operator explanation.');
  await form.locator('[name=current_password]').fill('password123');
  const response = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin/roles');
  await form.getByRole('button', { name: 'Create role', exact: true }).click();
  expect((await response).status()).toBe(422);
  await capture(page, info, 'create-capabilities-error');
  await expect(form.locator('[name=name]')).toHaveValue('Retained draft role');
  await expect(form.locator('[name=description]')).toHaveValue('Keep the operator explanation.');
  const group = form.getByRole('group', { name: 'Capabilities', exact: true });
  await expect(group).toHaveAttribute('aria-invalid', 'true');
  const errorId = await group.getAttribute('aria-describedby');
  expect(errorId).toBeTruthy();
  await expect(page.locator(`#${errorId}`)).toContainText(/at least one capability/i);
  await expect(page.locator(`#${errorId}`)).toHaveAttribute('role', 'alert');
  expect(await group.evaluate(element => element === document.activeElement || element.contains(document.activeElement))).toBeTruthy();
});

test('bulk warning skips self consistently and previews the actionable count in both row orders', async ({ page }, info) => {
  const state = fixture();
  for (const direction of ['asc', 'desc']) {
    fixture('reset-actions');
    await page.goto('/admin/users?' + new URLSearchParams({ q: 'Bulk review', sort: 'username', direction }));
    await page.locator(`[name="selected[]"][value="${state.admin_id}"]`).check();
    await page.locator(`[name="selected[]"][value="${state.target_id}"]`).check();
    await page.locator('[name=bulk_action]').selectOption('warn');
    await page.getByRole('button', { name: 'Review and apply…', exact: true }).click();
    const self = page.locator('.member-bulk-subject', { hasText: '@mp-admin' });
    await expect(self).toContainText(/skipped/i);
    await expect(page.getByRole('button', { name: 'Warn 1 member', exact: true })).toBeVisible();
    await capture(page, info, `bulk-${direction}-eligible-preview`);
    await page.locator('.member-bulk-form [name=reason]').fill(`Order-independent warning ${direction}`);
    const response = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin/users/bulk/apply');
    await page.getByRole('button', { name: 'Warn 1 member', exact: true }).click();
    expect((await response).status()).toBe(303);
    await expect(page.locator('.flash')).toContainText('Warned 1 member');
    expect(fixture().target_warnings).toBe(1);
  }
});

for (const javaScriptEnabled of [true, false]) {
  test(`named board/category targets keep matching ids distinct with JavaScript ${javaScriptEnabled ? 'on' : 'off'}`, async ({ browser, baseURL }, info) => {
    const state = fixture();
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport: { width: info.project.name === 'mobile' ? 390 : 1280, height: 844 } });
    await context.addCookies(cookies);
    const page = await context.newPage();
    try {
      await page.goto(`/admin/roles/${state.role_id}`);
      const form = page.locator('.role-assign-form');
      const target = form.locator('select[name=scope_target]');
      const category = state.categories[0];
      const board = state.boards.find(board => board.id === category.id)!;
      expect(board, 'fixture must include the same numeric id in both namespaces').toBeTruthy();
      await expect(target.locator(`option[value="category:${category.id}"]`)).toHaveText(`Category: ${category.name}`);
      await expect(target.locator(`option[value="board:${board.id}"]`)).toHaveText(`Board: ${board.name}`);
      for (const [kind, id, name] of [['category', category.id, category.name], ['board', board.id, board.name]] as const) {
        await form.locator('[name=username]').fill('mp-target');
        await target.selectOption(`${kind}:${id}`);
        await form.locator('[name=reason]').fill(`Named ${kind} regression`);
        await form.locator('[name=current_password]').fill('password123');
        await form.getByRole('button', { name: 'Assign', exact: true }).click();
        const row = page.locator('.role-assignment-table tbody tr').filter({ hasText: '@mp-target' }).filter({ hasText: name });
        await expect(row).toContainText(kind);
        await expect(row.locator('.role-assignment-status-active')).toHaveText('Active');
      }
      await page.locator('.role-assignment-table').scrollIntoViewIfNeeded();
      await capture(page, info, `scope-names-js-${javaScriptEnabled}`);
    } finally { await context.close(); }
  });
}

test('simulator uses readable capability and board choices and rejects invalid calendar dates', async ({ page }, info) => {
  const state = fixture();
  await page.goto('/admin/roles/simulator');
  await expect(page.locator('select[name=board_id] option').filter({ hasText: state.boards[0].name })).toHaveCount(1);
  await expect(page.locator('select[name=capability] option[value="core.thread.lock"]')).not.toHaveText('core.thread.lock');
  for (const at of ['2026-02-30 12:00', '2026-13-01 12:00', '2026-04-31 12:00', '2026-02-29 12:00']) {
    await page.goto('/admin/roles/simulator?' + new URLSearchParams({ actor: 'mp-target', capability: 'core.thread.lock', board_id: String(state.boards[0].id), at }));
    await expect(page.locator('.role-simulator-error')).toBeVisible();
    await expect(page.locator('.role-simulator-result')).toHaveCount(0);
    await expect(page.locator('[name=at]')).toHaveValue(at);
  }
  await capture(page, info, 'simulator-invalid-date');
  await page.goto('/admin/roles/simulator?' + new URLSearchParams({ actor: 'mp-target', capability: 'core.thread.lock', board_id: String(state.boards[0].id), at: '2028-02-29 12:00' }));
  await expect(page.locator('.role-simulator-result')).toBeVisible();
});

test('role list and editor retain all visible text at 200 percent text size', async ({ page }, info) => {
  const state = fixture();
  await page.setViewportSize({ width: 390, height: 844 });
  for (const [name, url] of [['list', '/admin/roles'], ['editor', `/admin/roles/${state.role_id}`]]) {
    await page.goto(url);
    await page.evaluate(() => { document.documentElement.style.fontSize = '200%'; });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    const clipped = await page.locator('main code').evaluateAll(elements => elements.filter(element => {
      if (element.closest('.table-scroll') || !element.getClientRects().length) return false;
      const range = document.createRange(); range.selectNodeContents(element);
      return Array.from(range.getClientRects()).some(rect => rect.width > 0 && (rect.right > 391 || rect.left < -1));
    }).map(element => element.textContent));
    expect(clipped, `${name} must wrap capability and mode text instead of clipping it`).toEqual([]);
    await capture(page, info, `roles-${name}-text200-header`);
    await page.locator('.role-capability-option:has(input:not([disabled]))').first().scrollIntoViewIfNeeded();
    await capture(page, info, `roles-${name}-text200-capabilities`);
  }
});

test('mobile role actions are reachable immediately and remaining columns scroll by keyboard', async ({ page }, info) => {
  const state = fixture();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/admin/roles');
  const region = page.getByRole('region', { name: 'Role definitions', exact: true });
  await region.scrollIntoViewIfNeeded();
  const edit = page.locator('.role-table tbody tr', { hasText: 'Members People review role' }).getByRole('link', { name: 'Edit', exact: true });
  for (const action of await page.locator('.role-row-action').all()) {
    const box = (await action.boundingBox())!;
    expect(box.x).toBeGreaterThanOrEqual(0); expect(box.x + box.width).toBeLessThanOrEqual(390);
    expect(box.height).toBeGreaterThanOrEqual(44);
    expect(await action.evaluate(element => {
      const range = document.createRange(); range.selectNodeContents(element);
      return Array.from(range.getClientRects()).filter(rect => rect.width > 0).length;
    }), `${await action.textContent()} must stay readable on one line`).toBe(1);
  }
  await capture(page, info, 'roles-mobile-actions');
  await region.focus();
  const initial = await region.evaluate(element => element.scrollLeft);
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => region.evaluate(element => element.scrollLeft)).toBeGreaterThan(initial);
  await edit.focus(); await page.keyboard.press('Enter');
  await expect(page).toHaveURL(new RegExp(`/admin/roles/${state.role_id}$`));
});

test('Members selection has a real 44px clickable target around its native checkbox', async ({ page }, info) => {
  const state = fixture();
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/admin/users?q=Bulk%20review');
  const input = page.locator(`[name="selected[]"][value="${state.target_id}"]`);
  await input.scrollIntoViewIfNeeded();
  const target = await input.evaluate(element => {
    const checkbox = element as HTMLInputElement;
    const label = checkbox.labels?.[0];
    if (!label) return null;
    const box = label.getBoundingClientRect();
    return { x: box.x, y: box.y, width: box.width, height: box.height };
  });
  expect(target, 'the native checkbox needs an associated enlarged label hitbox').toBeTruthy();
  expect(target!.width).toBeGreaterThanOrEqual(44); expect(target!.height).toBeGreaterThanOrEqual(44);
  await page.mouse.click(target!.x + 4, target!.y + 4);
  await expect(input).toBeChecked();
  const response = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin/users/bulk');
  await page.getByRole('button', { name: 'Review and apply…', exact: true }).click();
  expect((await response).status()).toBe(422);
  await expect(input).toBeChecked();
  await expect(page.locator('.member-directory-filter-form [name=q]')).toHaveValue('Bulk review');
  await expect(page.locator('[data-bulk-selected-count]')).toHaveText('1 member selected');
  await expect(page.locator('[data-bulk-toggle]')).toHaveJSProperty('indeterminate', true);
  await input.uncheck();
  await expect(page.locator('[data-bulk-selected-count]')).toHaveText('None selected');
  await expect(page.locator('[data-bulk-toggle]')).toHaveJSProperty('indeterminate', false);
  await capture(page, info, 'members-selection-target');
});

test('no-JavaScript Members never exposes an inert select-all and native bulk review works', async ({ browser, baseURL }, info) => {
  const state = fixture();
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: info.project.name === 'mobile' ? 390 : 1280, height: 844 } });
  await context.addCookies(cookies);
  const page = await context.newPage();
  try {
    await page.goto('/admin/users?q=Bulk%20review');
    const toggle = page.locator('[data-bulk-toggle]');
    if (await toggle.isVisible()) await expect(toggle).toBeDisabled();
    const selected = page.locator(`[name="selected[]"][value="${state.target_id}"]`);
    await selected.check();
    const error = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === '/admin/users/bulk');
    await page.getByRole('button', { name: 'Review and apply…', exact: true }).click();
    expect((await error).status()).toBe(422);
    await expect(selected).toBeChecked();
    await expect(page.locator('.member-directory-filter-form [name=q]')).toHaveValue('Bulk review');
    if (await toggle.isVisible()) await expect(toggle).toBeDisabled();
    await page.locator('[name=bulk_action]').selectOption('suspend');
    await page.getByRole('button', { name: 'Review and apply…', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Suspend 1 member', exact: true })).toBeVisible();
    await capture(page, info, 'members-nojs-bulk-preview');
  } finally { await context.close(); }
});

test('roles explain live effects beside editing and assignment, with human capability labels first', async ({ page }, info) => {
  const state = fixture();
  await page.goto(`/admin/roles/${state.role_id}`);
  const effects = page.locator('[data-role-live-effect]');
  expect(await effects.count()).toBeGreaterThanOrEqual(2);
  for (const effect of await effects.all()) {
    await expect(effect).toContainText(state.mode === 'enforce' ? 'Permissions are live (enforce).' : 'Permissions are in preview mode (shadow).');
    await expect(effect).toContainText(state.mode === 'enforce' ? 'when assignments are active' : 'does not change live access');
  }
  await effects.first().scrollIntoViewIfNeeded();
  await capture(page, info, 'role-editor-live-effect');
  const footer = page.locator('.role-definition-footer');
  await footer.scrollIntoViewIfNeeded();
  if (page.viewportSize()!.width <= 640) {
    const passwordField = (await footer.locator('.role-reauth-field').boundingBox())!;
    expect(passwordField.height).toBeGreaterThan(0);
    expect(passwordField.height, 'the mobile password field must not stretch into a tall empty flex item').toBeLessThan(150);
  }
  await capture(page, info, 'role-editor-save');
  const lock = page.locator('.role-definition-form').getByRole('checkbox', { name: /^Lock.*(?:thread|topic)/i });
  await expect(lock).toBeVisible();
  const unavailable = page.locator('details').filter({ has: page.locator('summary', { hasText: 'Unavailable capabilities' }) });
  await expect(unavailable).toHaveCount(1);
  expect(await unavailable.evaluate(element => (element as HTMLDetailsElement).open)).toBe(false);
  await unavailable.locator('summary').click();
  const choices = unavailable.locator('input[type=checkbox]');
  expect(await choices.count()).toBeGreaterThan(0);
  for (const choice of await choices.all()) await expect(choice).toBeDisabled();
  await capture(page, info, 'role-editor-human-labels-mode');
});

test('no-JavaScript custom role creation and named assignment remain native form flows', async ({ browser, baseURL }, info) => {
  const state = fixture();
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: info.project.name === 'mobile' ? 390 : 1280, height: 844 } });
  await context.addCookies(cookies);
  const page = await context.newPage();
  try {
    await page.goto('/admin/roles');
    const name = `No JS role ${info.project.name} ${Date.now()}`;
    const create = page.locator('.role-create-form');
    await create.locator('[name=name]').fill(name);
    await create.locator('[value="core.thread.lock"]').check();
    await create.locator('[name=current_password]').fill('password123');
    await create.getByRole('button', { name: 'Create role', exact: true }).click();
    await page.locator('.role-table tbody tr', { hasText: name }).getByRole('link', { name: 'Edit', exact: true }).click();
    const assign = page.locator('.role-assign-form');
    await assign.locator('[name=username]').fill('mp-target');
    await assign.locator('[name=scope_target]').selectOption(`board:${state.boards[1].id}`);
    await assign.locator('[name=current_password]').fill('password123');
    await assign.getByRole('button', { name: 'Assign', exact: true }).click();
    await expect(page.locator('.role-assignment-table')).toContainText(state.boards[1].name);
    await expect(page.locator('.role-assignment-status-active')).toHaveText('Active');
    await capture(page, info, 'role-nojs-create-assign');
  } finally { await context.close(); }
});

test('light and dark Members and People surfaces remain axe-clean with keyboard focus', async ({ page }, info) => {
  const state = fixture();
  for (const theme of ['light', 'dark']) {
    fixture(theme);
    for (const [name, url] of [['members', '/admin/users'], ['record', `/admin/users/${state.target_id}`], ['roles', '/admin/roles'], ['editor', `/admin/roles/${state.role_id}`], ['simulator', '/admin/roles/simulator']]) {
      await page.goto(url);
      await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
      const audit = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(audit.violations, `${theme} ${name}`).toEqual([]);
      await capture(page, info, `${theme}-${name}`);
    }
  }
  await page.goto('/admin/users');
  const region = page.getByRole('region', { name: 'User directory', exact: true });
  await region.focus();
  expect(await region.evaluate(element => getComputedStyle(element).outlineStyle)).not.toBe('none');
});

test('capabilities flag darkens People routes and default restoration keeps Members available', async ({ page }) => {
  try {
    fixture('flag-dark');
    expect((await page.goto('/admin/roles'))!.status()).toBe(404);
    expect((await page.goto('/admin/users'))!.status()).toBe(200);
    await expect(page.locator('a[href^="/admin/roles"]')).toHaveCount(0);
  } finally { fixture('flag-default'); }
});
