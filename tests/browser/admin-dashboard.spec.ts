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

async function shot(page: Page, info: TestInfo, name: string, neutralizeStickyChrome = false): Promise<void> {
  const adminBar = page.locator('.admin-bar');
  const previousPosition = neutralizeStickyChrome && await adminBar.count() > 0
    ? await adminBar.evaluate((element) => {
      const prior = (element as HTMLElement).style.position;
      (element as HTMLElement).style.position = 'static';
      return prior;
    })
    : null;
  try {
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
      fullPage: true,
      animations: 'disabled',
    });
  } finally {
    if (previousPosition !== null) {
      await adminBar.evaluate((element, prior) => {
        if (prior) (element as HTMLElement).style.position = prior;
        else (element as HTMLElement).style.removeProperty('position');
      }, previousPosition);
    }
  }
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

async function createUserTargetAuditRow(page: Page): Promise<void> {
  await page.goto('/admin/users');
  const row = page.locator('tr', { hasText: 'alice' });
  await row.locator('input[name="selected[]"]').check();
  await page.locator('select[name="bulk_action"]').selectOption('warn');
  await page.getByRole('button', { name: 'Review and apply…' }).click();
  await page.locator('input[name="reason"]').fill('Admin overview target-link evidence.');
  await page.getByRole('button', { name: /Warn 1 member/ }).click();
  await expect(page.getByRole('status')).toContainText('Warned');
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

async function captureThemeRegisters(page: Page, info: TestInfo, evidenceName = '07-admin-dashboard'): Promise<void> {
  for (const { register, label } of [
    { register: 'light', label: 'light' },
    { register: 'dark', label: 'twilight' },
  ]) {
    await page.locator('html').evaluate((element, theme) => element.setAttribute('data-theme', theme), register);
    await expect(page.locator('html')).toHaveAttribute('data-theme', register);
    await shot(page, info, `${evidenceName}-${label}`, evidenceName === '05-admin-audit' && info.project.name === 'mobile');
  }
}

async function expectCurrentPageSystemDarkAxeClean(page: Page, info: TestInfo): Promise<void> {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'system'));
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'system');
  const targetLink = page.locator('.audit-target-link').first();
  await expect(targetLink).toBeVisible();
  const targetColors = await targetLink.evaluate((element) => {
    const probe = document.createElement('span');
    probe.style.color = 'var(--on-info)';
    document.body.append(probe);
    const colors = {
      actual: getComputedStyle(element).color,
      expected: getComputedStyle(probe).color,
    };
    probe.remove();
    return colors;
  });
  expect(targetColors.actual).toBe(targetColors.expected);
  await expectAxeClean(page, info);
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
    await page.locator('[data-runtime-queue-state], [data-runtime-semantic-probe]').evaluateAll(
      (nodes) => nodes.forEach((node) => node.remove()),
    );
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
    'Reports open', 'Approval hold', 'Appeals', 'Email failures', 'Thread Intelligence',
  ]);
  const dashboardCardStyles = await page.evaluate(() => {
    const styleOf = (selector: string) => {
      const element = document.querySelector(selector);
      if (!(element instanceof HTMLElement)) throw new Error(`${selector} is missing`);
      const style = getComputedStyle(element);
      return { padding: style.padding, boxShadow: style.boxShadow, overflowX: style.overflowX };
    };
    const probe = document.createElement('div');
    probe.style.boxShadow = 'var(--shadow-sm)';
    document.body.append(probe);
    const shadowSm = getComputedStyle(probe).boxShadow;
    probe.remove();

    return {
      queue: styleOf('.queue-card'),
      attention: styleOf('.attention-panel'),
      activity: styleOf('.activity-card'),
      recent: styleOf('.recent-activity-card'),
      attentionHeadingMarginBottom: getComputedStyle(document.querySelector('.attention-panel .section-heading-row')!).marginBottom,
      communityGap: getComputedStyle(document.querySelector('.community-section')!).gap,
      communityHeadingMargin: getComputedStyle(document.querySelector('.community-section > h2')!).margin,
      activityCopyMinWidth: getComputedStyle(document.querySelector('.activity-card-copy')!).minWidth,
      shadowSm,
    };
  });
  expect(dashboardCardStyles.queue).toMatchObject({ padding: '16px 17px 14px', boxShadow: dashboardCardStyles.shadowSm, overflowX: 'hidden' });
  expect(dashboardCardStyles.attention).toMatchObject({ padding: '18px 20px', boxShadow: dashboardCardStyles.shadowSm });
  expect(dashboardCardStyles.activity).toMatchObject({ padding: '15px 17px', boxShadow: 'none' });
  expect(dashboardCardStyles.recent).toMatchObject({ padding: '18px 20px 8px', boxShadow: dashboardCardStyles.shadowSm });
  expect(dashboardCardStyles.attentionHeadingMarginBottom).toBe('10px');
  expect(dashboardCardStyles.communityGap).toBe('0px');
  expect(dashboardCardStyles.communityHeadingMargin).toBe('4px 0px 12px');
  expect(dashboardCardStyles.activityCopyMinWidth).toBe('0px');

  await page.evaluate(() => {
    const probe = document.createElement('div');
    probe.id = 'static-queue-hover-probe';
    probe.className = 'card queue-card is-static';
    probe.textContent = 'Static queue state';
    document.querySelector('.admin-dashboard-grid')?.append(probe);
  });
  const staticQueueProbe = page.locator('#static-queue-hover-probe');
  await staticQueueProbe.hover();
  await expect.poll(() => staticQueueProbe.evaluate((element) => getComputedStyle(element).boxShadow)).toBe(dashboardCardStyles.shadowSm);
  await staticQueueProbe.evaluate((element) => element.remove());

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

  const queueGridMetrics = await page.locator('.admin-overview-dashboard .admin-dashboard-grid').evaluate((grid) => {
    const firstCard = grid.querySelector('.queue-card');
    if (!(firstCard instanceof HTMLElement)) throw new Error('Queue health card is missing');

    return {
      columns: getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length,
      gridWidth: grid.getBoundingClientRect().width,
      cardWidth: firstCard.getBoundingClientRect().width,
    };
  });
  expect(queueGridMetrics.columns).toBe(1);
  expect(queueGridMetrics.cardWidth).toBeGreaterThanOrEqual(queueGridMetrics.gridWidth - 2);

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

test('audit log is contained, focusable, theme-complete, and axe-clean on both viewports', async ({ page }, info) => {
  const browserProblems = observeBrowserProblems(page);

  await login(page);
  await createUserTargetAuditRow(page);
  await page.goto('/admin/audit');
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText('Audit log');
  await expect(page.locator('.audit-filter-card')).toBeVisible();
  await expect(page.locator('.audit-table-card')).toBeVisible();

  const region = page.locator('.audit-table-card .table-scroll');
  await expect(region).toHaveAttribute('role', 'region');
  await expect(region).toHaveAttribute('tabindex', '0');
  await region.focus();
  await expect(region).toBeFocused();

  const widths = await page.evaluate(() => ({
    documentClient: document.documentElement.clientWidth,
    documentScroll: document.documentElement.scrollWidth,
    bodyClient: document.body.clientWidth,
    bodyScroll: document.body.scrollWidth,
  }));
  expect(widths.documentScroll, JSON.stringify(widths, null, 2)).toBeLessThanOrEqual(widths.documentClient);
  expect(widths.bodyScroll, JSON.stringify(widths, null, 2)).toBeLessThanOrEqual(widths.bodyClient);

  if (info.project.name === 'mobile') {
    const controls = page.locator('.audit-filter-card .btn, .admin-overview-audit .pager-control, .audit-table-card .state-empty a');
    const heights = await controls.evaluateAll((items) => items.map((item) => item.getBoundingClientRect().height));
    expect(heights.length).toBeGreaterThan(0);
    expect(heights.every((height) => height >= 44)).toBe(true);
    const tableWidths = await region.evaluate((element) => ({ client: element.clientWidth, scroll: element.scrollWidth }));
    expect(tableWidths.scroll).toBeGreaterThan(tableWidths.client);
    const filterColumns = await page.locator('.admin-overview-audit .filter-grid').evaluate(
      (element) => getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length,
    );
    expect(filterColumns).toBe(1);
  }

  const auditStyles = await page.evaluate(() => {
    const tokenColor = (token: string) => {
      const probe = document.createElement('span');
      probe.style.color = `var(${token})`;
      document.body.append(probe);
      const color = getComputedStyle(probe).color;
      probe.remove();
      return color;
    };
    const colorOf = (selector: string) => {
      const element = document.querySelector(selector);
      if (!(element instanceof HTMLElement)) throw new Error(`${selector} is missing`);
      return getComputedStyle(element).color;
    };
    const filterActions = document.querySelector('.admin-overview-audit .filter-actions');
    const filterGrid = document.querySelector('.admin-overview-audit .filter-grid');
    if (!(filterActions instanceof HTMLElement)) throw new Error('Audit filter actions are missing');
    const apply = filterActions.querySelector('button');
    const reset = filterActions.querySelector('a');
    const targetInput = document.querySelector('input[name="target_id"]');
    const dateInput = document.querySelector('input[name="from"]');
    const pager = document.querySelector('.admin-overview-audit .pager');
    if (!(apply instanceof HTMLElement) || !(reset instanceof HTMLElement)
      || !(targetInput instanceof HTMLElement) || !(dateInput instanceof HTMLElement)
      || !(pager instanceof HTMLElement) || !(filterGrid instanceof HTMLElement)) {
      throw new Error('Audit presentation controls are missing');
    }
    const controlStyle = (element: HTMLElement) => {
      const style = getComputedStyle(element);
      return {
        padding: style.padding,
        fontSize: style.fontSize,
        letterSpacing: style.letterSpacing,
        boxShadow: style.boxShadow,
        borderTopWidth: style.borderTopWidth,
        color: style.color,
      };
    };
    const monoProbe = document.createElement('span');
    monoProbe.style.fontFamily = 'var(--font-mono)';
    document.body.append(monoProbe);
    const monoFamily = getComputedStyle(monoProbe).fontFamily;
    monoProbe.remove();

    return {
      filterMarginTop: getComputedStyle(filterActions).marginTop,
      filterGap: getComputedStyle(filterActions).gap,
      filterAlignItems: getComputedStyle(filterGrid).alignItems,
      apply: controlStyle(apply),
      reset: controlStyle(reset),
      targetInput: { fontFamily: getComputedStyle(targetInput).fontFamily, fontSize: getComputedStyle(targetInput).fontSize },
      dateInput: { fontFamily: getComputedStyle(dateInput).fontFamily, fontSize: getComputedStyle(dateInput).fontSize },
      pagerMarginTop: getComputedStyle(pager).marginTop,
      monoFamily,
      time: colorOf('.audit-time'),
      reason: colorOf('.audit-reason'),
      textFaint: tokenColor('--text-faint'),
      textMuted: tokenColor('--text-muted'),
    };
  });
  expect(auditStyles.filterMarginTop).toBe('16px');
  expect(auditStyles.filterGap).toBe('10px');
  expect(auditStyles.filterAlignItems).toBe('start');
  expect(auditStyles.apply).toMatchObject({ padding: '8px 17px', fontSize: '12.8px', letterSpacing: '0.512px', boxShadow: 'none', borderTopWidth: '0px' });
  // Chromium quantizes the declared 1.5px border to a 1px computed width.
  expect(auditStyles.reset).toMatchObject({ padding: '8px 17px', fontSize: '12.8px', letterSpacing: '0.512px', boxShadow: 'none', borderTopWidth: '1px', color: auditStyles.textMuted });
  expect(auditStyles.targetInput).toEqual({ fontFamily: auditStyles.monoFamily, fontSize: '14.08px' });
  expect(auditStyles.dateInput).toEqual({ fontFamily: auditStyles.monoFamily, fontSize: '13.76px' });
  expect(auditStyles.pagerMarginTop).toBe('18px');
  expect(auditStyles.time).toBe(auditStyles.textFaint);
  expect(auditStyles.reason).toBe(auditStyles.textMuted);

  await region.evaluate((element) => {
    if (element instanceof HTMLElement) element.blur();
    element.scrollLeft = 0;
    window.scrollTo(0, 0);
  });
  await expect(region).not.toBeFocused();
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);

  await captureThemeRegisters(page, info, '05-admin-audit');
  await expectCurrentPageSystemDarkAxeClean(page, info);

  await page.goto('/admin/audit?actor=__no_matching_operator__');
  const emptyState = page.locator('.audit-table-card .state-empty');
  await expect(emptyState).toBeVisible();
  const emptyStyles = await emptyState.evaluate((element) => {
    const paragraph = element.querySelector('p');
    const reset = element.querySelector('a');
    if (!(paragraph instanceof HTMLElement) || !(reset instanceof HTMLElement)) {
      throw new Error('Filtered audit empty state is incomplete');
    }
    return {
      paragraphMargin: getComputedStyle(paragraph).margin,
      paragraphFontSize: getComputedStyle(paragraph).fontSize,
      resetMarginTop: getComputedStyle(reset).marginTop,
    };
  });
  expect(emptyStyles).toEqual({ paragraphMargin: '6px 0px 0px', paragraphFontSize: '14.88px', resetMarginTop: '15px' });
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
