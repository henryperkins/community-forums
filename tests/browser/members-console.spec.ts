import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Slice 11 evidence for AdminMembers.dc.html. The four members surfaces —
 * directory, bulk confirmation, member record and invitations — are checked in
 * the light and twilight registers plus the `data-theme="system"` dark path that
 * `layout.php` actually defaults to, at 1280px and 390px.
 *
 * The behavioural contracts (selection count, no-JS 422, audited PII reveal,
 * typed ban confirmation, show-once invite link) live in admin-remediation and
 * invitations; this spec owns the copied body metrics and the register parity.
 */
const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(REPO_ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  const bar = page.locator('.admin-bar');
  const narrow = (page.viewportSize()?.width ?? 1280) <= 390;
  const prior = narrow && await bar.count() > 0
    ? await bar.evaluate((element) => {
      const value = (element as HTMLElement).style.position;
      (element as HTMLElement).style.position = 'static';
      return value;
    })
    : null;
  try {
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
      fullPage: true,
      animations: 'disabled',
    });
  } finally {
    if (prior !== null) {
      await bar.evaluate((element, value) => {
        if (value) (element as HTMLElement).style.position = value;
        else (element as HTMLElement).style.removeProperty('position');
      }, prior);
    }
  }
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('admin@retro.test');
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

/**
 * The full-bleed console bar is `position: sticky`, and axe composites whatever it
 * believes overlaps a control into that control's background — which turns the
 * primary button's gold fill into a blended olive and reports a knife-edge 4.48:1
 * that does not reproduce when the same page is scanned on its own. `shot()` in
 * admin-dashboard.spec.ts already treats the sticky bar as a capture artifact for
 * the same reason; pin it for the scan so the measurement is of the page, not of
 * the compositor.
 */
async function expectAxeClean(page: Page, info: TestInfo, register: string): Promise<void> {
  const bar = page.locator('.admin-bar');
  const prior = await bar.count() > 0
    ? await bar.evaluate((element) => {
      const value = (element as HTMLElement).style.position;
      (element as HTMLElement).style.position = 'static';
      return value;
    })
    : null;
  try {
    const result = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const violations = result.violations.filter((item) => item.impact === 'serious' || item.impact === 'critical');
    expect(violations, `${info.project.name} ${register} serious/critical axe violations`).toEqual([]);
  } finally {
    if (prior !== null) {
      await bar.evaluate((element, value) => {
        if (value) (element as HTMLElement).style.position = value;
        else (element as HTMLElement).style.removeProperty('position');
      }, prior);
    }
  }
}

async function expectNoDocumentOverflow(page: Page): Promise<void> {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
  }));
  expect(dimensions.document).toBeLessThanOrEqual(dimensions.viewport);
}

/**
 * Light and twilight captures, then the `system` register under a dark OS —
 * the default `layout.php` emits and the one the staff-badge defect hid in.
 */
async function exerciseThemeRegisters(page: Page, info: TestInfo, evidenceName: string): Promise<void> {
  for (const { theme, label, colorScheme } of [
    { theme: 'light', label: 'light', colorScheme: 'light' as const },
    { theme: 'dark', label: 'twilight', colorScheme: 'dark' as const },
  ]) {
    await page.emulateMedia({ colorScheme });
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    await expectAxeClean(page, info, label);
    await expectNoDocumentOverflow(page);
    await shot(page, info, `${evidenceName}-${label}`);
  }
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'system'));
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'system');
  await expectAxeClean(page, info, 'system-dark');
  await page.emulateMedia({ colorScheme: 'light' });
}

async function expectMembersArea(page: Page, tab: 'Directory' | 'Invitations'): Promise<void> {
  await expect(page.getByRole('heading', { level: 1, name: 'Members & invitations' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText(tab);
  await expect(page.getByRole('navigation', { name: 'Member sections' })).toHaveCount(1);
}

test('the directory copies the filter grid, roster anatomy and bulk bar in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/users');
  await expectMembersArea(page, 'Directory');

  // Sortable headers stay links: the design's onClick sort is a GET in production.
  const repHeader = page.locator('th[aria-sort] a.member-directory-sort', { hasText: 'Reputation' });
  await expect(repHeader).toHaveAttribute('href', /sort=reputation/);
  // Numerics carry the design's grouped-thousands mono treatment.
  const repCell = page.locator('td.member-directory-number').first();
  await expect(repCell).toHaveCSS('text-align', 'right');
  await expect(page.locator('[data-bulk-selected-count]')).toHaveText('None selected');
  // The shipped focusable scroll region survives the restyle (ADR 0021 / 0023).
  await expect(page.locator('.member-directory-table-card [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'User directory');

  await exerciseThemeRegisters(page, info, 'members-directory');
});

test('the bulk confirmation copies the subject list and actionable count in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/users');
  await page.locator('tr', { hasText: 'alice' }).locator('input[name="selected[]"]').check();
  await page.locator('select[name="bulk_action"]').selectOption('suspend');
  await page.getByRole('button', { name: 'Review and apply…' }).click();

  await expectMembersArea(page, 'Directory');
  await expect(page.getByRole('heading', { level: 2, name: /Suspend 1 member/ })).toBeVisible();
  await expect(page.getByRole('link', { name: 'All members' })).toHaveAttribute('href', '/admin/users');
  // The reason survives as a real field; Cancel is a link, never a mutating GET.
  await expect(page.locator('input[name="reason"]')).toHaveAttribute('required', '');
  await expect(page.getByRole('link', { name: 'Cancel' })).toHaveAttribute('href', '/admin/users');

  await exerciseThemeRegisters(page, info, 'members-bulk-confirm');
});

test('the member record copies the status, restriction and history anatomy in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/users');
  await page.locator('a.member-directory-member-link', { hasText: 'alice' }).first().click();
  await page.waitForURL(/\/admin\/users\/\d+$/);
  await expectMembersArea(page, 'Directory');

  // Status states its six terms as plain text; the pill and chip stay on the row.
  const terms = page.locator('.member-record-status-list dt');
  await expect(terms).toHaveText(['Role', 'State', 'Reputation', 'Posts', 'Joined', 'Last seen']);
  await expect(page.locator('.member-record-status-list .role-pill')).toHaveCount(0);
  // PII stays behind the audited reveal, which is a POST, never a link.
  await expect(page.locator('.member-record-pii-list')).toHaveCount(0);
  await expect(page.locator('form[action$="/pii"] button[type="submit"]')).toBeVisible();
  // The typed ban confirmation is a real, always-rendered, required control.
  await expect(page.locator('input[name="confirm_username"]')).toHaveAttribute('required', '');

  await exerciseThemeRegisters(page, info, 'members-record');
});

test('every members route stays whole with JavaScript disabled', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: info.project.name === 'mobile' ? { width: 390, height: 844 } : { width: 1280, height: 900 },
  });
  const page = await context.newPage();
  try {
    await login(page);
    // Directory: filters, sorting, selection and the bulk bar are all server-rendered.
    await page.goto('/admin/users');
    await expectMembersArea(page, 'Directory');
    await expect(page.locator('[data-bulk-selected-count]')).toHaveText('None selected');
    await expect(page.locator('th[aria-sort] a.member-directory-sort').first()).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'members-directory-no-js');

    // Bulk confirmation reached by a real POST, not a JS view switch.
    await page.locator('tr', { hasText: 'alice' }).locator('input[name="selected[]"]').check();
    await page.locator('select[name="bulk_action"]').selectOption('warn');
    await page.getByRole('button', { name: 'Review and apply…' }).click();
    await expect(page.getByRole('heading', { level: 2, name: /Warn 1 member/ })).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'members-bulk-confirm-no-js');

    // The record's re-auth and typed-confirm controls render inline, always (C-14).
    await page.goto('/admin/users');
    await page.locator('a.member-directory-member-link', { hasText: 'alice' }).first().click();
    await page.waitForURL(/\/admin\/users\/\d+$/);
    await expect(page.locator('input[name="confirm_username"]')).toBeVisible();
    await expect(page.locator('input[name="current_password"]')).toBeVisible();
    await expect(page.locator('form[action$="/pii"] button[type="submit"]')).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'members-record-no-js');

    await page.goto('/admin/invitations');
    await expectMembersArea(page, 'Invitations');
    await expect(page.getByRole('button', { name: 'Issue invitation' })).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'members-invitations-no-js');
  } finally {
    await context.close();
  }
});

test('invitations copies the issue card and issued table in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/invitations');
  await expectMembersArea(page, 'Invitations');
  await expect(page.getByRole('heading', { level: 2, name: 'Issue an invitation' })).toBeVisible();

  // Issue one so the captures show the design's populated table rather than the
  // empty state, and so the show-once panel is part of the register evidence.
  await page.locator('form[action="/admin/invitations"] button[type="submit"]').click();
  await expect(page.locator('.member-invitations-once code')).toBeVisible();

  await expect(page.locator('.member-invitations-list [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'Issued invitations');
  // Revoke mutates, so it is a CSRF-carrying POST rather than the design's bare button.
  const revoke = page.locator('.member-invitations-actions form[method="post"]').first();
  await expect(revoke.locator('input[name="_token"]')).toHaveCount(1);

  await exerciseThemeRegisters(page, info, 'members-invitations');
});
