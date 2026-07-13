import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { expect, test, type BrowserContext, type Page, type TestInfo } from '@playwright/test';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceDir = path.join(repoRoot, 'docs', 'evidence', 'browser');
const fixtureScript = path.join(__dirname, 'thread-intelligence-fixture.php');

type FixtureState = {
  fallback: { path: string };
  brief: { path: string; source_id: number; source_path: string; related_path: string };
  last_good: { path: string };
  source_invalid: { path: string };
  admin: { thread_title: string };
};

function projectKey(info: TestInfo): 'desktop' | 'mobile' {
  return info.project.name === 'mobile' ? 'mobile' : 'desktop';
}

function fixture(action: string, info: TestInfo): FixtureState {
  const output = execFileSync(process.env.PHP_BINARY ?? 'php', [fixtureScript, action, projectKey(info)], {
    cwd: repoRoot,
    env: {
      ...process.env,
      APP_KEY: process.env.APP_KEY,
      OPENAI_API_KEY: process.env.OPENAI_API_KEY,
      DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e',
    },
  }).toString();

  return JSON.parse(output) as FixtureState;
}

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.evaluate(() => window.scrollTo(0, 0));
  await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
  if (name === '79-admin-thread-intelligence' && info.project.name === 'mobile') {
    await page.locator('.thread-intelligence-admin .table-scroll').evaluate((region) => {
      region.scrollLeft = region.scrollWidth;
    });
  }
  await page.screenshot({
    path: path.join(evidenceDir, info.project.name, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
  });
}

async function visit(page: Page, url: string): Promise<void> {
  const response = await page.goto(url);
  expect(response, `no response for ${url}`).not.toBeNull();
  expect(response!.status(), `GET ${url} should not be an error`).toBeLessThan(400);
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

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1_000 }).catch(() => false)) {
    await skip.click();
  }
}

async function login(page: Page, email: string): Promise<void> {
  await page.context().clearCookies();
  await visit(page, '/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await page.getByRole('button', { name: 'Log in' }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  await dismissTour(page);
}

async function loginWithoutJavaScript(page: Page, email: string): Promise<void> {
  await visit(page, '/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.getByRole('button', { name: 'Log in' }).click(),
  ]);
}

async function enterThemeSafeMode(page: Page): Promise<void> {
  await visit(page, '/admin/themes/safe-mode');
  const enter = page.getByRole('button', { name: 'Enter safe mode' });
  if (await enter.isVisible({ timeout: 1_000 }).catch(() => false)) {
    await enter.click();
    await expect(page.getByRole('status').getByText('Theme safe mode is on.')).toBeVisible();
  }
}

async function exitThemeSafeMode(page: Page): Promise<void> {
  await visit(page, '/admin/themes/safe-mode');
  const exit = page.getByRole('button', { name: 'Exit safe mode' });
  if (await exit.isVisible({ timeout: 1_000 }).catch(() => false)) {
    await page.locator('form:has(input[name="exit"]) input[name="current_password"]').fill('password123');
    await exit.click();
    await expect(page.getByRole('status').getByText('Theme safe mode was exited.')).toBeVisible();
  }
}

function generationRow(page: Page, threadTitle: string) {
  return page.getByRole('link', { name: threadTitle, exact: true }).locator('xpath=ancestor::tr');
}

async function expectNoSeriousA11yViolations(page: Page, info: TestInfo, include: string): Promise<void> {
  const result = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .include(include)
    .analyze();
  const violations = result.violations.filter((item) => item.impact === 'serious' || item.impact === 'critical');
  expect(violations, `${info.project.name} ${page.url()} ${include} serious/critical axe violations`).toEqual([]);
}

async function expectNoHorizontalOverflow(page: Page, selector: string): Promise<void> {
  const metrics = await page.locator(selector).evaluateAll((nodes) => nodes.map((node) => ({
    clientWidth: (node as HTMLElement).clientWidth,
    scrollWidth: (node as HTMLElement).scrollWidth,
  })));
  expect(metrics.length, `${selector} should have rendered elements`).toBeGreaterThan(0);
  for (const metric of metrics) {
    expect(metric.scrollWidth, `${selector} content should wrap`).toBeLessThanOrEqual(metric.clientWidth + 1);
  }
}

test.describe.configure({ mode: 'serial' });

test('fallback and generated Living Brief render safely with provenance, navigation, and responsive wrapping', async ({ page }, info) => {
  const state = fixture('reset-brief', info);

  await visit(page, state.fallback.path);
  await expect(page.locator('.related-topic-fallback')).toBeVisible();
  await expect(page.locator('.living-brief')).toHaveCount(0);
  await expect(page.locator('.thread-memory-slot')).not.toBeEmpty();
  await shot(page, info, '75-thread-intelligence-fallback');

  await visit(page, state.brief.path);
  const brief = page.locator('.living-brief');
  await expect(brief).toBeVisible();
  await expect(brief).toContainText('AI-generated living brief');
  await expect(brief).toContainText('Version 1');
  await expect(brief.locator('time')).toHaveAttribute('datetime', /Z$/);
  await expect(brief.getByRole('link', { name: 'AI-generated living brief' })).toHaveAttribute('href', '/privacy#thread-intelligence');
  await expect(brief.locator('.living-brief-sources a')).toHaveCount(8);
  await expect(brief.locator('.living-brief-related-card')).toHaveCount(1);

  await brief.locator('.living-brief-sources a').first().click();
  await expect(page).toHaveURL(new RegExp(`#p${state.brief.source_id}$`));
  await page.goBack();
  await brief.locator('.living-brief-related-card').click();
  await expect(page).toHaveURL(new RegExp(state.brief.related_path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'));
  await page.goBack();

  await expectNoHorizontalOverflow(page, '.living-brief');
  await expectNoHorizontalOverflow(page, '.living-brief-related-card');
  await expectNoHorizontalOverflow(page, '.living-brief-sources li');
  if (info.project.name === 'mobile') {
    const cards = await page.locator('.living-brief-related-card').evaluateAll((nodes) => nodes.map((node) => ({
      left: Math.round(node.getBoundingClientRect().left),
      width: Math.round(node.getBoundingClientRect().width),
    })));
    expect(new Set(cards.map((card) => card.left)).size).toBe(1);
    expect(cards[0].width).toBeGreaterThan(250);
  }
  await shot(page, info, '76-living-brief');
});

test('curator edit, real-worker refresh, retirement, restoration, and explicit resume preserve lineage', async ({ page }, info) => {
  const state = fixture('reset-brief', info);
  await login(page, 'alice@retro.test');
  await visit(page, state.brief.path);

  let memory = await openTopicTools(page, 'memory');
  await expect(memory.details.locator('form[action$="/summary"]')).toBeVisible();
  await shot(page, info, '77-living-brief-curator-controls');

  const editor = memory.details.locator('form[action$="/summary"]');
  await editor.locator('textarea[name="body"]').fill(`Curator baseline for ${projectKey(info)} with retained public evidence.`);
  await editor.locator('input[name="source_post_ids"]').fill(String(state.brief.source_id));
  await editor.getByRole('button', { name: 'Publish summary' }).click();
  await expect(page.locator('.living-brief')).toContainText('AI-generated · curator edited');
  await expect(page.locator('.living-brief')).toContainText('@alice');

  fixture('prepare-refresh', info);
  await page.reload();
  memory = await openTopicTools(page, 'memory');
  await memory.details.getByRole('button', { name: 'Refresh living brief' }).click();
  await expect(page.getByRole('status')).toContainText('Refresh queued');
  fixture('run-refresh', info);
  await page.reload();
  await expect(page.locator('.living-brief')).toContainText('AI-generated living brief');
  await expect(page.locator('.living-brief')).toContainText('Curator baseline carried forward');
  memory = await openTopicTools(page, 'memory');
  await expect(memory.details.locator('form[action$="/summary/restore"] select')).toContainText('AI-generated · curator edited');

  await memory.details.getByRole('button', { name: 'Retire summary' }).click();
  await expect(page.locator('.living-brief')).toHaveCount(0);
  memory = await openTopicTools(page, 'memory');
  await expect(memory.details.getByText('Automatic refresh is paused for this topic.')).toBeVisible();

  const restore = memory.details.locator('form[action$="/summary/restore"]');
  await restore.locator('select[name="summary_id"]').selectOption({ index: 0 });
  await restore.getByRole('button', { name: 'Restore summary' }).click();
  await expect(page.locator('.living-brief')).toBeVisible();
  memory = await openTopicTools(page, 'memory');
  await memory.details.getByRole('button', { name: 'Resume automatic refresh' }).click();
  memory = await openTopicTools(page, 'memory');
  await expect(memory.details.getByRole('button', { name: 'Resume automatic refresh' })).toHaveCount(0);
});

test('provider failure, budget exhaustion, and stale sources preserve or suppress the correct last-good content', async ({ page }, info) => {
  const state = fixture('reset-guardrails', info);
  try {
    await login(page, 'alice@retro.test');
    await visit(page, state.last_good.path);
    await expect(page.locator('.living-brief')).toContainText('Last good brief remains published');
    await expect(page.locator('.living-brief')).toContainText('Version 1');
    const memory = await openTopicTools(page, 'memory');
    await expect(memory.details.getByText('Daily refresh capacity has been reached')).toBeVisible();
    await shot(page, info, '78-living-brief-last-good');

    fixture('invalidate-source', info);
    try {
      await visit(page, state.source_invalid.path);
      await expect(page.locator('.living-brief')).toHaveCount(0);
      await expect(page.locator('.related-topic-fallback')).toBeVisible();
      await expect(page.locator('.living-brief-related-card')).toHaveCount(0);
    } finally {
      fixture('restore-source', info);
    }
  } finally {
    fixture('restore-guardrails', info);
  }
});

test('admin status and restorative retry, reconcile, latch, pause, thread, and budget controls remain operable', async ({ page }, info) => {
  const state = fixture('reset-admin', info);
  await login(page, 'admin@retro.test');
  await enterThemeSafeMode(page);

  try {
    await visit(page, '/admin/thread-intelligence');
    const dashboard = page.locator('.thread-intelligence-admin');
    await expect(dashboard.getByRole('heading', { name: 'Thread Intelligence' })).toBeVisible();
    await expect(dashboard).toContainText('community memory on');
    await expect(dashboard).toContainText('automated context on');
    await expect(dashboard).toContainText('Ready');
    await expect(dashboard.locator('.ti-budget')).toContainText(/Calls \d+ of 100/);
    await expect(dashboard.locator('.ti-evidence').first()).toBeVisible();

    let row = generationRow(page, state.admin.thread_title);
    await row.getByRole('button', { name: 'Retry', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Refresh queued');
    row = generationRow(page, state.admin.thread_title);
    await row.getByRole('button', { name: 'Reconcile', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Reconciliation queued');

    row = generationRow(page, state.admin.thread_title);
    await row.getByRole('button', { name: 'Pause', exact: true }).click();
    row = generationRow(page, state.admin.thread_title);
    await expect(row.getByRole('button', { name: 'Resume', exact: true })).toBeVisible();
    await row.getByRole('button', { name: 'Resume', exact: true }).click();

    await page.getByRole('button', { name: 'Pause generation' }).click();
    await expect(page.getByRole('button', { name: 'Resume generation' })).toBeVisible();
    await page.getByRole('button', { name: 'Resume generation' }).click();

    fixture('latch-provider', info);
    await page.reload();
    await expect(dashboard).toContainText('latched');
    await expect(dashboard).toContainText('Provider configuration is latched');
    await page.getByRole('button', { name: 'Retry provider configuration' }).click();
    await expect(dashboard).toContainText('available');

    fixture('exhaust-budget', info);
    await page.reload();
    await expect(dashboard.locator('.ti-budget')).toContainText('Calls 100 of 100');
    fixture('reset-admin', info);
    await page.reload();
    await expect(dashboard.locator('.ti-budget')).not.toContainText('Calls 100 of 100');
    await shot(page, info, '79-admin-thread-intelligence');
  } finally {
    fixture('reset-admin', info);
    await exitThemeSafeMode(page);
  }
});

test('no-JS: Living Brief, source and related navigation, details, and curator forms remain native', async ({ browser, baseURL }, info) => {
  const state = fixture('show', info);
  const context: BrowserContext = await browser.newContext({ javaScriptEnabled: false, baseURL });
  const page = await context.newPage();
  try {
    await loginWithoutJavaScript(page, 'alice@retro.test');
    await visit(page, state.brief.path);
    await expect(page.locator('.living-brief')).toBeVisible();

    await page.locator('.living-brief-sources a').first().click();
    await expect(page).toHaveURL(new RegExp(`#p${state.brief.source_id}$`));
    await page.goBack();
    const related = page.locator('.living-brief-related-card');
    await related.focus();
    await expect(related).toBeFocused();
    await related.press('Enter');
    await expect(page).toHaveURL(new RegExp(state.brief.related_path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'));
    await page.goBack();

    const tools = page.locator('[data-topic-tools]');
    const details = tools.locator('[data-topic-tools-section="memory"]');
    const summary = details.locator(':scope > summary');
    await expect(tools).toBeVisible();
    await expect(details).toBeVisible();
    await expect(summary).toBeVisible();
    await summary.focus();
    await expect(summary).toBeFocused();
    await summary.press('Enter');
    await expect(details).toHaveAttribute('open', '');
    await expect(details.locator('form[action$="/summary"]')).toBeVisible();
    await expect(details.locator('form[action$="/summary/restore"]')).toBeVisible();
    await expect(details.locator('form[action$="/related"]')).toBeVisible();
  } finally {
    await context.close();
  }
});

test('axe: Living Brief, provenance, history, curator, fallback, and admin surfaces have no serious findings', async ({ page }, info) => {
  const state = fixture('reset-admin', info);
  await login(page, 'admin@retro.test');
  await enterThemeSafeMode(page);
  try {
    await visit(page, '/admin/thread-intelligence');
    await expectNoSeriousA11yViolations(page, info, '.thread-intelligence-admin');

    await login(page, 'alice@retro.test');
    await visit(page, state.brief.path);
    await expectNoSeriousA11yViolations(page, info, '.living-brief');
    await expectNoSeriousA11yViolations(page, info, '.living-brief-sources');
    await expectNoSeriousA11yViolations(page, info, '.living-brief-related');
    await openTopicTools(page, 'memory');
    await expectNoSeriousA11yViolations(page, info, '[data-topic-tools]');

    await visit(page, state.fallback.path);
    await expectNoSeriousA11yViolations(page, info, '.related-topic-fallback');
  } finally {
    await login(page, 'admin@retro.test');
    await exitThemeSafeMode(page);
  }
});
