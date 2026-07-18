import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Focused evidence for the operational admin landing page and grouped admin
 * workspace. The same spec certifies the persistent desktop rail, enhanced
 * mobile drawer, server-rendered no-JS directory, overflow cue, and WCAG AA
 * serious/critical baseline at the approved 1280x800 and 390x844 viewports.
 */
const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');
const GROUPS = [
  'Dashboard',
  'Moderation',
  'Content',
  'People',
  'Appearance',
  'Notifications',
  'Integrations',
  'Settings',
];

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
  });
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', 'admin@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

async function expectAxeClean(page: Page, info: TestInfo): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include('.admin')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = results.violations.filter((item) => item.impact === 'serious' || item.impact === 'critical');
  expect(violations, `${info.project.name} admin serious/critical axe violations`).toEqual([]);
}

function observeBrowserProblems(page: Page): string[] {
  const problems: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') problems.push(`console: ${message.text()}`);
  });
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
  return problems;
}

async function expectGroupedDirectory(page: Page): Promise<void> {
  await expect(page.locator('.admin-nav-group-title')).toHaveText(GROUPS);
  for (const href of [
    '/admin', '/mod/reports', '/mod/approvals', '/admin/audit', '/admin/moderation',
    '/admin/structure', '/admin/tags', '/admin/users', '/admin/roles', '/admin/invitations',
    '/admin/badge-rules', '/admin/branding', '/admin/themes', '/admin/custom-emoji',
    '/admin/email', '/admin/announcements', '/admin/packages', '/admin/registries',
    '/admin/webhooks', '/admin/api-tokens', '/admin/providers', '/admin/extensions',
    '/admin/settings', '/admin/features', '/admin/thread-intelligence',
  ]) {
    await expect(page.locator(`[data-admin-nav] :is(a[href="${href}"], [data-destination="${href}"])`)).toHaveCount(1);
  }
}

test('desktop admin rail and operational hierarchy are complete, quiet, and axe-clean', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop rail evidence uses the 1280x800 project');
  const browserProblems = observeBrowserProblems(page);

  await login(page);
  await page.goto('/admin');
  await expect(page.getByRole('heading', { name: 'Admin console' })).toBeVisible();

  const rail = page.locator('[data-admin-nav]');
  const railBox = await rail.boundingBox();
  expect(railBox).not.toBeNull();
  expect(railBox!.width).toBeGreaterThanOrEqual(220);
  expect(railBox!.width).toBeLessThanOrEqual(226);
  expect(await rail.evaluate((element) => getComputedStyle(element).position)).toBe('sticky');
  await expectGroupedDirectory(page);
  await expect(rail.locator('a.active[aria-current="page"]')).toHaveAttribute('href', '/admin');

  const headings = page.locator('.admin-pane h2');
  await expect(headings).toContainText(['Queue health', 'Needs attention', 'Community today', 'Recent activity']);
  const hierarchy = await page.locator('.admin-pane').evaluate((pane) => {
    const labels = ['Queue health', 'Needs attention', 'Community today', 'Recent activity'];
    return labels.map((label) => Array.from(pane.querySelectorAll('h2')).findIndex((heading) => heading.textContent?.trim() === label));
  });
  expect(hierarchy).toEqual([0, 1, 2, 3]);

  await expect(page.locator('[data-queue-status] .queue-card-head')).toHaveText([
    'Reports', 'Approval hold', 'Email failures', 'Thread Intelligence',
  ]);
  const statuses = await page.locator('[data-queue-status]').evaluateAll((cards) => cards.map((card) => card.getAttribute('data-queue-status')));
  expect(statuses.every((status) => ['attention', 'clear', 'unavailable'].includes(status ?? ''))).toBe(true);
  await expect(page.locator('.activity-card-title')).toHaveText(['New users today', 'Active now']);
  await expect(page.getByRole('link', { name: 'View full audit log' })).toHaveAttribute('href', '/admin/audit');
  await expect(page.locator('form[action="/admin/site"], form[action="/admin/settings"], input[name="shortcode"]')).toHaveCount(0);
  await expect(page.locator('[data-overflow-region]')).toHaveAttribute('tabindex', '0');
  await expect(page.locator('[data-overflow-cue-label]')).toBeHidden();

  await expectAxeClean(page, info);
  await shot(page, info, '07-admin-dashboard');
  expect(browserProblems).toEqual([]);
});

test('mobile drawer closes every way, contains focus, restores focus, and cleans up on resize', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile drawer evidence uses the 390x844 project');
  const browserProblems = observeBrowserProblems(page);

  await login(page);
  await page.goto('/admin');
  const toggle = page.locator('[data-admin-nav-toggle]');
  const nav = page.locator('[data-admin-nav]');
  const close = page.locator('[data-admin-nav-close]');
  const scrim = page.locator('[data-admin-nav-scrim]');

  await expect(toggle).toBeVisible();
  const toggleBox = await toggle.boundingBox();
  expect(toggleBox!.height).toBeGreaterThanOrEqual(44);
  await expect(nav).toHaveAttribute('aria-hidden', 'true');
  await expect(nav).toHaveAttribute('inert', '');
  await shot(page, info, '07-admin-dashboard');

  await toggle.focus();
  await page.keyboard.press('Enter');
  await expect(page.locator('body')).toHaveClass(/admin-nav-open/);
  await expect(nav).toHaveAttribute('aria-hidden', 'false');
  await expect(close).toBeFocused();
  const closeBox = await close.boundingBox();
  expect(closeBox!.height).toBeGreaterThanOrEqual(44);
  await expectGroupedDirectory(page);

  const lastLink = nav.locator('a[href]').last();
  await lastLink.focus();
  await page.keyboard.press('Tab');
  await expect(close).toBeFocused();
  await expectAxeClean(page, info);
  await shot(page, info, '07-admin-dashboard-drawer');

  await close.click();
  await expect(page.locator('body')).not.toHaveClass(/admin-nav-open/);
  await expect(toggle).toBeFocused();

  await toggle.click();
  await page.keyboard.press('Escape');
  await expect(page.locator('body')).not.toHaveClass(/admin-nav-open/);
  await expect(toggle).toBeFocused();

  await toggle.click();
  await scrim.click({ position: { x: 370, y: 20 } });
  await expect(page.locator('body')).not.toHaveClass(/admin-nav-open/);
  await expect(toggle).toBeFocused();

  await toggle.click();
  await nav.getByRole('link', { name: 'Reports', exact: true }).click();
  await page.waitForURL(/\/mod\/reports$/);
  await expect(page.locator('body')).not.toHaveClass(/admin-nav-open/);

  await page.goto('/admin');
  const region = page.locator('[data-overflow-region]');
  const shell = page.locator('[data-overflow-cue]');
  const cue = page.locator('[data-overflow-cue-label]');
  const mobileWidths = await page.evaluate(() => Object.fromEntries(
    ['html', 'body', '.main', '.admin', '.admin-pane', '.recent-activity-card', '.activity-table-shell', '[data-overflow-region]', '.audit-recent']
      .map((selector) => {
        const element = selector === 'html' ? document.documentElement : selector === 'body' ? document.body : document.querySelector(selector);
        return [selector, element ? { client: element.clientWidth, scroll: element.scrollWidth } : null];
      }),
  ));
  expect(mobileWidths.html.scroll, JSON.stringify(mobileWidths, null, 2)).toBeLessThanOrEqual(mobileWidths.html.client);
  await expect(region).toHaveAttribute('role', 'region');
  await expect(region).toHaveAttribute('tabindex', '0');
  await expect(cue).toBeVisible();
  expect(await shell.evaluate((element) => getComputedStyle(element, '::after').opacity)).toBe('1');
  await region.focus();
  await expect(region).toBeFocused();
  await region.evaluate((element) => { element.scrollLeft = element.scrollWidth; });
  await expect(shell).toHaveClass(/is-at-end/);
  await expect(cue).toBeHidden();
  await expect.poll(() => shell.evaluate((element) => getComputedStyle(element, '::after').opacity)).toBe('0');

  await toggle.click();
  await page.setViewportSize({ width: 900, height: 844 });
  await expect(page.locator('body')).not.toHaveClass(/admin-nav-open/);
  await expect(nav).not.toHaveAttribute('aria-hidden', /.+/);
  await expect(nav).not.toHaveAttribute('inert', '');
  await expect(toggle).toBeHidden();
  await page.setViewportSize({ width: 390, height: 844 });
  await expect(toggle).toBeVisible();
  await expect(nav).toHaveAttribute('aria-hidden', 'true');

  expect(browserProblems).toEqual([]);
});

test('no-JS mobile admin directory remains expanded and reaches domain settings', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'one explicit no-JS mobile context is sufficient');
  const context = await (browser as Browser).newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const page = await context.newPage();
  try {
    await login(page);
    await page.goto('/admin');
    await expect(page.locator('html')).not.toHaveClass(/has-js/);
    await expect(page.locator('[data-admin-nav-toggle]')).toBeHidden();
    await expect(page.locator('[data-admin-nav]')).toBeVisible();
    await expectGroupedDirectory(page);
    await page.getByRole('link', { name: 'General & registration' }).click();
    await page.waitForURL(/\/admin\/settings$/);
    await expect(page.getByRole('heading', { name: 'General & registration' })).toBeVisible();
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, 'mobile', '07-admin-dashboard-no-js.png'),
      fullPage: true,
      animations: 'disabled',
    });
  } finally {
    await context.close();
  }
});
