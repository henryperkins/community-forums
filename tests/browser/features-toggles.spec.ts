import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import os from 'node:os';
import path from 'node:path';

/**
 * Browser evidence for the guarded flag toggles on /admin/features (ADR 0049):
 * one native <details> confirm per row that names the consequence, a POST of
 * one flag with an explicit target, the row returning to rest with live cells
 * and a one-shot marker, the audit entry, and the locked states. The whole
 * flow is server-rendered; the no-JS walk proves it.
 *
 * The evidence seed pins flags in settings.features, and a round trip back to
 * a default removes a pinned key, so every test restores the raw JSON.
 */
const root = path.resolve(__dirname, '..', '..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser');

function fixture(action: string, argument = ''): Record<string, unknown> {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'features-toggles-fixture.php'), action, argument], { cwd: root, env: process.env, encoding: 'utf8' }));
}

let snapshot = '';
test.beforeEach(() => {
  const raw = fixture('snapshot').raw;
  snapshot = Buffer.from(typeof raw === 'string' ? raw : '').toString('base64');
});
test.afterEach(() => {
  fixture('restore', snapshot);
});

// One sign-in per project: login is rate-limited per account, and the specs
// beside this one sign the same admin in.
const sessions = new Map<string, string>();
async function adminPage(browser: Browser, info: TestInfo, javaScriptEnabled = true): Promise<Page> {
  const use = info.project.use;
  const options = {
    baseURL: use.baseURL,
    viewport: use.viewport,
    deviceScaleFactor: use.deviceScaleFactor,
    isMobile: use.isMobile,
    hasTouch: use.hasTouch,
  };
  let state = sessions.get(info.project.name);
  if (state === undefined) {
    const context = await browser.newContext(options);
    const page = await context.newPage();
    await page.goto('/login');
    await page.locator('input[name="email"]').fill('admin@retro.test');
    await page.locator('input[name="password"]').fill('password123');
    await page.locator('form[action="/login"] button[type="submit"]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    const skip = page.getByRole('button', { name: 'Skip' });
    if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
    state = path.join(os.tmpdir(), `rb-features-toggles-${info.project.name}-${process.pid}.json`);
    await context.storageState({ path: state });
    await context.close();
    sessions.set(info.project.name, state);
  }
  const context = await browser.newContext({ ...options, storageState: state, javaScriptEnabled });
  return context.newPage();
}

async function shot(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: path.join(evidence, info.project.name, `${name}.png`), fullPage: false, animations: 'disabled' });
}

function flagRow(page: Page, flag: string) {
  return page.locator(`tr#flag-${flag}`);
}

async function expectAxeClean(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include('.admin-pane')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  expect(results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')).toEqual([]);
}

test('a flag turns off and back on through its inline confirm, with proof in the row and the audit log', async ({ browser }, info) => {
  test.setTimeout(120_000);
  // The audit log is append-only and shared by both projects' runs.
  const audited = (fixture('audit', 'polls').audit as unknown[]).length;
  const page = await adminPage(browser, info);
  await page.goto('/admin/features');
  const row = flagRow(page, 'polls');
  const toggle = row.locator('details.features-flag-toggle');
  const summary = toggle.locator('summary');

  await expect(summary).toHaveAccessibleName('Turn off polls');
  await expect(toggle).not.toHaveAttribute('open', /.*/);
  await summary.click();
  await expect(toggle).toHaveAttribute('open', '');
  await expect(summary).toHaveAccessibleName('Cancel turning off polls');
  // <details> is itself an (unnamed) group; the panel is the named one inside it.
  const panel = row.getByRole('group', { name: /^Polls\./ });
  await expect(panel).toContainText('Polls. Poll panels and creating, voting on or closing polls go away; polls and votes are kept');
  // The seed pins polls=true, so turning it off stores false.
  await expect(panel).toContainText('Saves features.polls=false and records an audit entry.');
  const commit = panel.getByRole('button', { name: 'Turn off polls now' });
  await expect(commit).toHaveClass(/\bdanger\b/);
  await expect(commit).toHaveAccessibleDescription('Saves features.polls=false and records an audit entry.');
  await expectAxeClean(page);
  await row.scrollIntoViewIfNeeded();
  await shot(page, info, 'toggle-confirm-open');
  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'dark'));
  await page.waitForTimeout(250);
  await shot(page, info, 'toggle-confirm-open-twilight');
  await page.locator('html').evaluate((element) => element.setAttribute('data-theme', 'light'));

  await Promise.all([page.waitForURL(/\/admin\/features#flag-polls$/), commit.click()]);
  await expect(page.locator('.flash').first()).toContainText('polls turned off. Saved features.polls=false. The change is recorded in the audit log.');
  const changed = flagRow(page, 'polls');
  await expect(changed.locator('.features-effective')).toHaveText('Effective off');
  await expect(changed).toContainText('Override off');
  await expect(changed.locator('.features-flag-outcome')).toHaveText('Turned off just now. See the audit log');
  await expect(changed.locator('details.features-flag-toggle summary')).toHaveAccessibleName('Turn on polls');
  // The fragment lands the row below the sticky admin bar, not under it.
  const bar = await page.locator('.admin-bar').boundingBox();
  const landed = await changed.boundingBox();
  expect(bar).not.toBeNull();
  expect(landed).not.toBeNull();
  expect(landed!.y).toBeGreaterThanOrEqual(bar!.y + bar!.height - 1);
  expect(landed!.y).toBeLessThan(page.viewportSize()!.height);
  await shot(page, info, 'toggle-after-off');

  await changed.locator('.features-flag-outcome').getByRole('link', { name: 'See the audit log' }).click();
  await expect(page).toHaveURL(/\/admin\/audit\?action=feature_flag\.$/);
  await expect(page.locator('table').first()).toContainText('feature_flag.disable');
  await expect(page.locator('table').first()).toContainText('features.polls');

  // Back on: the code default is on, so the console removes the override.
  await page.goto('/admin/features?q=polls');
  const back = flagRow(page, 'polls');
  await back.locator('summary').click();
  await expect(back.getByRole('group', { name: /^Polls\./ })).toContainText('Removes the features.polls override so the code default applies, and records an audit entry.');
  const restore = back.getByRole('button', { name: 'Turn on polls now' });
  await expect(restore).not.toHaveClass(/\bdanger\b/);
  await Promise.all([page.waitForURL(/\/admin\/features\?q=polls#flag-polls$/), restore.click()]);
  await expect(flagRow(page, 'polls').locator('.features-effective')).toHaveText('Effective on');
  await expect(flagRow(page, 'polls')).toContainText('No override');
  expect((fixture('audit', 'polls').audit as Array<{ action: string }>).slice(audited).map((entry) => entry.action))
    .toEqual(['feature_flag.disable', 'feature_flag.enable']);
  await page.context().close();
});

test('confirms open without a request, one at a time, and cancel by keyboard', async ({ browser }, info) => {
  test.skip(info.project.name !== 'desktop', 'keyboard order is pinned on the desktop project');
  const page = await adminPage(browser, info);
  await page.goto('/admin/features');
  const requests: string[] = [];
  page.on('request', (request) => requests.push(request.url()));

  const polls = flagRow(page, 'polls').locator('details.features-flag-toggle');
  const search = flagRow(page, 'search').locator('details.features-flag-toggle');
  await polls.locator('summary').click();
  await search.locator('summary').click();
  // name="features-flag-confirm" makes the panels an exclusive accordion.
  await expect(search).toHaveAttribute('open', '');
  await expect(polls).not.toHaveAttribute('open', /.*/);
  await search.locator('summary').click();
  await expect(search).not.toHaveAttribute('open', /.*/);

  const summary = polls.locator('summary');
  await summary.focus();
  await page.keyboard.press('Enter');
  await expect(polls).toHaveAttribute('open', '');
  await expect(summary).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(polls.getByRole('button', { name: 'Turn off polls now' })).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(summary).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(polls).not.toHaveAttribute('open', /.*/);
  expect(requests).toEqual([]);
  await page.context().close();
});

test('rows the console cannot act on say why, and a corrupt blob locks every row', async ({ browser }, info) => {
  const page = await adminPage(browser, info);
  await page.goto('/admin/features?q=custom_css');
  const css = flagRow(page, 'custom_css');
  await expect(css.locator('details')).toHaveCount(0);
  await expect(css.locator('.features-flag-locked')).toHaveAttribute('aria-disabled', 'true');
  await expect(css.locator('.features-flag-locked')).toHaveText('Turn on custom_css');
  await expect(css.locator('.features-flag-toggle-note')).toHaveText('Cannot be turned on here. Safety-blocked: theme safe mode does not suppress custom CSS yet.');
  await shot(page, info, 'toggle-locked-blocked');

  fixture('corrupt');
  await page.goto('/admin/features');
  await expect(page.locator('.features-corrupt-alert')).toContainText('Flag changes are locked here until then.');
  await expect(page.locator('details.features-flag-toggle')).toHaveCount(0);
  await expect(page.locator('form[action^="/admin/features/"]')).toHaveCount(0);
  await expect(page.locator('.features-flag-toggle-note').first()).toHaveText('Locked until settings.features is repaired.');
  await expect(page.locator('.features-flag-locked')).toHaveCount(57);
  await expectAxeClean(page);
  await shot(page, info, 'toggle-corrupt-locked');
  await page.context().close();
});

test('a refused write re-renders the inventory as a 422 alert that takes focus', async ({ browser }, info) => {
  const page = await adminPage(browser, info);
  await page.goto('/admin/features?q=css&readiness=safety_blocked');
  // No control asks to enable a blocked flag, so stand in for a stale or forged
  // request: point another row's real, CSRF-bearing form at custom_css.
  await page.goto('/admin/features?q=polls');
  const form = flagRow(page, 'polls').locator('form');
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/admin/features/custom_css') && r.request().method() === 'POST'),
    form.evaluate((element) => {
      const f = element as HTMLFormElement;
      f.action = '/admin/features/custom_css';
      (f.querySelector('input[name="enabled"]') as HTMLInputElement).value = '1';
      (f.querySelector('input[name="return"]') as HTMLInputElement).value = '/admin/features?q=css&readiness=safety_blocked';
      f.requestSubmit();
    }),
  ]);
  expect(response.status()).toBe(422);
  const alert = page.getByRole('alert').filter({ hasText: 'custom_css was not changed.' });
  await expect(alert).toContainText('The console cannot turn on custom_css. Safety-blocked:');
  await expect(alert).toBeFocused();
  await expect(page.locator('.features-flags-table tbody tr')).toHaveCount(1);
  await expect(page.locator('select[name="readiness"]')).toHaveValue('safety_blocked');
  expect(fixture('audit', 'custom_css').audit).toEqual([]);
  await expectAxeClean(page);
  await shot(page, info, 'toggle-refused-422');
  await page.context().close();
});

test('the whole flow works with JavaScript disabled', async ({ browser }, info) => {
  const page = await adminPage(browser, info, false);
  await page.goto('/admin/features?q=search');
  const row = flagRow(page, 'search');
  await row.locator('summary').click();
  await expect(row.locator('details.features-flag-toggle')).toHaveAttribute('open', '');
  await shot(page, info, 'toggle-no-js-confirm');
  await Promise.all([page.waitForURL(/\/admin\/features\?q=search#flag-search$/), row.getByRole('button', { name: 'Turn off search now' }).click()]);
  await expect(flagRow(page, 'search').locator('.features-effective')).toHaveText('Effective off');
  await expect(page.locator('.flash').first()).toContainText('search turned off. Saved features.search=false.');
  await expect(flagRow(page, 'search').locator('.features-flag-outcome')).toContainText('Turned off just now.');

  // Undo is the same two steps on the row the page returned to.
  await flagRow(page, 'search').locator('summary').click();
  await Promise.all([page.waitForURL(/#flag-search$/), flagRow(page, 'search').getByRole('button', { name: 'Turn on search now' }).click()]);
  await expect(flagRow(page, 'search').locator('.features-effective')).toHaveText('Effective on');
  await page.context().close();
});

test('the confirm fits the phone scroll area and keeps 44px targets', async ({ browser }, info) => {
  test.skip(!info.project.name.endsWith('mobile'), 'the 390px geometry is the mobile project');
  const page = await adminPage(browser, info);
  await page.goto('/admin/features');
  const row = flagRow(page, 'uploads');
  const summary = row.locator('summary');
  await row.scrollIntoViewIfNeeded();
  expect((await summary.boundingBox())!.height).toBeGreaterThanOrEqual(44);
  await summary.click();
  const panel = row.locator('.features-flag-confirm');
  await expect(panel).toBeVisible();
  const box = (await panel.boundingBox())!;
  const region = (await row.locator('xpath=ancestor::div[contains(@class,"table-scroll")]').boundingBox())!;
  // Readable without scrolling the table sideways.
  expect(box.x).toBeGreaterThanOrEqual(region.x - 1);
  expect(box.x + box.width).toBeLessThanOrEqual(region.x + region.width + 1);
  const commit = panel.getByRole('button', { name: 'Turn off uploads now' });
  expect((await commit.boundingBox())!.height).toBeGreaterThanOrEqual(44);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await panel.scrollIntoViewIfNeeded();
  await shot(page, info, 'toggle-mobile-confirm');
  await page.context().close();
});
