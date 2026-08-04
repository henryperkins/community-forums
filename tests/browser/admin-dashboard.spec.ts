import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Focused evidence for the operational admin landing page and shared console
 * chrome. The same spec certifies the horizontal area tier, area tabs,
 * server-rendered no-JS navigation, overflow cues, theme registers, and WCAG
 * AA serious/critical baseline at the approved 1280x800 and 390x844 viewports.
 */
const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(REPO_ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser');
const TIERS = [
  'Overview',
  'Moderation',
  'Content',
  'People',
  'Members',
  'Appearance',
  'Notifications',
  'Integrations',
  'Packages',
  'Features',
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

async function expectAdminTier(page: Page): Promise<void> {
  const tier = page.locator('[data-admin-tier]');
  await expect(tier).toBeVisible();
  await expect(tier.locator('.admin-tier-item')).toHaveCount(11);
  await expect(tier.locator('.admin-tier-item')).toHaveText(TIERS);
}

async function captureThemeRegisters(page: Page, info: TestInfo): Promise<void> {
  for (const { register, label } of [
    { register: 'light', label: 'light' },
    { register: 'dark', label: 'twilight' },
  ]) {
    await page.locator('html').evaluate((element, theme) => element.setAttribute('data-theme', theme), register);
    await expect(page.locator('html')).toHaveAttribute('data-theme', register);
    await shot(page, info, `07-admin-dashboard-${label}`);
  }
}

async function expectSystemDarkAxeClean(page: Page, info: TestInfo): Promise<void> {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'system'));
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'system');

  const runtimeColors = await page.evaluate(() => {
    const grid = document.querySelector('.admin-dashboard-grid');
    if (!(grid instanceof HTMLElement)) throw new Error('Queue health grid is missing');

    const clear = grid.querySelector('.queue-card.queue-status-clear .queue-card-state');
    if (!(clear instanceof HTMLElement)) throw new Error('Seeded Clear queue state is missing');

    for (const status of ['attention', 'unavailable']) {
      if (grid.querySelector(`.queue-card.queue-status-${status} .queue-card-state`)) continue;

      const card = document.createElement('div');
      card.className = `card queue-card queue-status-${status} is-static`;
      card.dataset.queueStatus = status;
      card.dataset.runtimeQueueState = status;

      const heading = document.createElement('span');
      heading.className = 'queue-card-head';
      heading.textContent = `Runtime ${status} status`;

      const count = document.createElement('strong');
      count.className = 'queue-card-count';
      count.textContent = '0';

      const detail = document.createElement('span');
      detail.className = 'queue-card-detail';
      detail.textContent = `Representative ${status} state for accessibility verification`;

      const state = document.createElement('span');
      state.className = 'queue-card-state';
      state.textContent = status[0].toUpperCase() + status.slice(1);

      card.append(heading, count, detail, state);
      grid.append(card);
    }

    return Object.fromEntries([
      ['clear', '--on-done'],
      ['attention', '--danger'],
      ['unavailable', '--text-faint'],
    ].map(([status, token]) => {
      const state = grid.querySelector(`.queue-card.queue-status-${status} .queue-card-state`);
      if (!(state instanceof HTMLElement)) throw new Error(`${status} queue state is missing`);

      const probe = document.createElement('span');
      probe.dataset.runtimeSemanticProbe = status;
      probe.hidden = true;
      probe.style.color = `var(${token})`;
      document.body.append(probe);

      return [status, {
        foreground: getComputedStyle(state).color,
        semantic: getComputedStyle(probe).color,
      }];
    }));
  });

  try {
    expect(runtimeColors.clear.foreground).toBe(runtimeColors.clear.semantic);
    expect(runtimeColors.attention.foreground).toBe(runtimeColors.attention.semantic);
    expect(runtimeColors.unavailable.foreground).toBe(runtimeColors.unavailable.semantic);
    await expectAxeClean(page, info);
  } finally {
    await page.locator('[data-runtime-queue-state], [data-runtime-semantic-probe]').evaluateAll(
      (nodes) => nodes.forEach((node) => node.remove()),
    );
  }
}

test('desktop admin tier and operational hierarchy are complete, quiet, and axe-clean', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop tier evidence uses the 1280x800 project');
  const browserProblems = observeBrowserProblems(page);

  await login(page);
  await page.goto('/admin');
  await expect(page.getByRole('heading', { name: 'Admin console' })).toBeVisible();

  await expectAdminTier(page);
  await expect(
    page.locator('span.admin-tier-item.is-active[aria-current="page"]'),
  ).toHaveText('Overview');

  const headings = page.locator('.admin-pane h2');
  await expect(headings).toContainText(['Queue health', 'Needs attention', 'Community today', 'Recent activity']);
  const hierarchy = await page.locator('.admin-pane').evaluate((pane) => {
    const labels = ['Queue health', 'Needs attention', 'Community today', 'Recent activity'];
    return labels.map((label) => Array.from(pane.querySelectorAll('h2')).findIndex((heading) => heading.textContent?.trim() === label));
  });
  expect(hierarchy).toEqual([0, 1, 2, 3]);

  await expect(page.locator('[data-queue-status] .queue-card-head')).toHaveText([
    'Reports', 'Approval hold', 'Appeals', 'Email failures', 'Thread Intelligence',
  ]);
  const statuses = await page.locator('[data-queue-status]').evaluateAll((cards) => cards.map((card) => card.getAttribute('data-queue-status')));
  expect(statuses.every((status) => ['attention', 'clear', 'unavailable'].includes(status ?? ''))).toBe(true);
  await expect(page.locator('.activity-card-title')).toHaveText(['New users today', 'Active now']);
  await expect(page.getByRole('link', { name: 'View full audit log' })).toHaveAttribute('href', '/admin/audit');
  await expect(page.locator('form[action="/admin/site"], form[action="/admin/settings"], input[name="shortcode"]')).toHaveCount(0);
  await expect(page.locator('[data-overflow-region]')).toHaveAttribute('tabindex', '0');
  await expect(page.locator('[data-overflow-cue-label]')).toBeHidden();

  await captureThemeRegisters(page, info);
  await expectSystemDarkAxeClean(page, info);
  expect(browserProblems).toEqual([]);
});

test('mobile admin tier scrolls horizontally while page and tables keep their own overflow cues', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile tier evidence uses the 390x844 project');
  const browserProblems = observeBrowserProblems(page);

  await login(page);
  await page.goto('/admin');
  await expectAdminTier(page);
  const tier = page.locator('[data-admin-tier]');
  const tierMetrics = await tier.evaluate((element) => ({
    overflowX: getComputedStyle(element).overflowX,
    clientWidth: element.clientWidth,
    scrollWidth: element.scrollWidth,
  }));
  expect(['auto', 'scroll']).toContain(tierMetrics.overflowX);
  expect(tierMetrics.scrollWidth).toBeGreaterThan(tierMetrics.clientWidth);
  const tierItemHeights = await tier.locator('.admin-tier-item').evaluateAll((items) => items.map(
    (item) => item.getBoundingClientRect().height,
  ));
  expect(tierItemHeights.every((height) => height >= 44)).toBe(true);

  const region = page.locator('[data-overflow-region]');
  const shell = page.locator('[data-overflow-cue]');
  const cue = page.locator('[data-overflow-cue-label]');
  const mobileWidths = await page.evaluate(() => Object.fromEntries(
    ['html', 'body', '.admin-bar', '.admin-console', '.admin-pane', '.recent-activity-card', '.activity-table-shell', '[data-overflow-region]', '.audit-recent']
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

  await region.evaluate((element) => {
    element.scrollLeft = 0;
    if (element instanceof HTMLElement) element.blur();
    window.scrollTo(0, 0);
  });
  await expect.poll(() => region.evaluate((element) => element.scrollLeft)).toBe(0);
  await expect(region).not.toBeFocused();
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
  await expect(shell).not.toHaveClass(/is-at-end/);
  await expect(cue).toBeVisible();
  await expect.poll(() => shell.evaluate((element) => getComputedStyle(element, '::after').opacity)).toBe('1');
  await page.evaluate(() => new Promise<void>((resolve) => requestAnimationFrame(() => resolve())));

  await captureThemeRegisters(page, info);
  await expectSystemDarkAxeClean(page, info);
  expect(browserProblems).toEqual([]);
});

test('no-JS mobile tier and tabs remain usable and reach domain settings', async ({ browser, baseURL }, info) => {
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
    await expectAdminTier(page);
    await expect(page.locator('.admin-tabs')).toBeVisible();
    await page.locator('[data-admin-tier]').getByRole('link', { name: 'Settings', exact: true }).click();
    await page.waitForURL(/\/admin\/settings$/);
    await expect(page.getByRole('heading', { name: 'General & intelligence' })).toBeVisible();
    await expect(page.locator('span.admin-tier-item.is-active[aria-current="page"]')).toHaveText('Settings');
    await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('General & registration');
    await page.mouse.move(0, 0);
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, 'mobile', '07-admin-dashboard-no-js.png'),
      fullPage: true,
      animations: 'disabled',
    });
  } finally {
    await context.close();
  }
});
