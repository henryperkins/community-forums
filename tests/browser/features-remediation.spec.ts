import { expect, test, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser');

function fixture(action: string, shortcode = '') {
  return JSON.parse(execFileSync('php', [path.join(__dirname, 'features-remediation-fixture.php'), action, shortcode], { cwd: root, env: process.env, encoding: 'utf8' }));
}

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[name=email]').fill('admin@retro.test');
  await page.locator('input[name=password]').fill('password123');
  await page.locator('form[action="/login"] button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}

async function shot(page: Page, info: TestInfo, name: string) {
  await page.waitForLoadState('load');
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(evidence, info.project.name, `${name}.png`), fullPage: true });
}

async function axe(page: Page) {
  const result = await new AxeBuilder({ page }).include('.admin-pane').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  expect(result.violations).toEqual([]);
}

test.afterEach(({}, info) => {
  if (info.title.startsWith('preview history reaches older rows')) fixture('clear-history');
});

test('inventory filters work without JavaScript and Operations links have 44px targets', async ({ browser }, info) => {
  const context = await browser.newContext({ baseURL: info.project.use.baseURL ?? process.env.E2E_BASE_URL ?? 'http://localhost:8011', javaScriptEnabled: false, viewport: info.project.use.viewport });
  const page = await context.newPage();
  await login(page);
  await page.goto('/admin/features');
  const form = page.getByRole('form', { name: 'Filter feature flags' });
  await form.getByLabel('Flag name').fill('CSS');
  await form.getByLabel('Readiness').selectOption('safety_blocked');
  await form.getByRole('button', { name: 'Filter flags' }).click();
  await expect(page.locator('.features-flags-table tbody tr')).toHaveCount(1);
  await expect(page.locator('.features-flag-cell > code')).toHaveText('custom_css');
  await expect(page.getByText('Showing 1 of 57 declared flags.', { exact: false })).toBeVisible();
  await expect(form.getByLabel('Readiness')).toHaveValue('safety_blocked');
  await shot(page, info, 'remediation-filtered-flags');
  await form.getByLabel('Flag name').fill('no_matching_flag');
  await form.getByRole('button', { name: 'Filter flags' }).click();
  await expect(page.getByText('No flags match these filters.', { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Clear filters', exact: true }).click();
  await expect(page.locator('.features-flags-table tbody tr')).toHaveCount(57);
  const targets = await page.locator('.features-flag-cell > a').evaluateAll(links => links.map(link => { const r = link.getBoundingClientRect(); return { width: r.width, height: r.height }; }));
  expect(targets.length).toBeGreaterThan(0);
  expect(targets.every(r => r.width >= 44 && r.height >= 44)).toBeTruthy();
  await context.close();
});

test('badge bulk changes require an impact review and typed confirmation without JavaScript', async ({ browser }, info) => {
  const context = await browser.newContext({ baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8011', javaScriptEnabled: false, viewport: info.project.use.viewport });
  const page = await context.newPage();
  await login(page);
  await page.goto('/admin/badge-rules');
  await expect(page.getByText(/Awards happen only when you run Backfill/)).toBeVisible();
  await page.locator('select[name=badge_id]').selectOption({ label: 'Founder' });
  await page.locator('input[name=threshold]').fill('1');
  await page.getByRole('button', { name: 'Create rule', exact: true }).click();
  const row = page.locator('.features-rule-list > li').first();
  await row.getByRole('link', { name: /Backfill/ }).click();
  const id = page.url().match(/badge-rules\/(\d+)\/backfill$/)?.[1];
  expect(id).toBeTruthy();
  await expect(page.getByText(/members in this batch/)).toBeVisible();
  await expect(page.getByText(/Backfill processes up to 1,000 members per run/)).toBeVisible();
  await page.locator('input[name=confirm]').fill('WRONG');
  const invalid = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/backfill'));
  await page.getByRole('button', { name: 'Backfill', exact: true }).click();
  expect((await invalid).status()).toBe(422);
  await expect(page.locator('input[name=confirm]')).toHaveValue('WRONG');
  await shot(page, info, 'remediation-backfill-confirmation-422');
  await page.locator('input[name=confirm]').fill(`BACKFILL ${id}`);
  await page.getByRole('button', { name: 'Backfill', exact: true }).click();
  await expect(page.getByRole('status')).toContainText(/backfilled \d+ awards/);
  await page.locator('.features-rule-list > li').first().getByRole('link', { name: /Revoke/ }).click();
  await expect(page.getByText('Active rule awards to revoke', { exact: true })).toBeVisible();
  await shot(page, info, 'remediation-revoke-impact');
  await page.locator('input[name=confirm]').fill(`REVOKE ${id}`);
  await page.getByRole('button', { name: 'Revoke awards', exact: true }).click();
  await expect(page.getByRole('status')).toContainText(/revoked \d+ awards/);
  await context.close();
});

test('emoji reservations preserve inputs and every catalogue mutation is audited', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/custom-emoji');
  await page.locator('input[name=shortcode]').fill('heart');
  await page.locator('input[name=name]').fill('Keep this custom asset');
  await page.locator('input[name=image_path]').fill('/emoji/features-review.png');
  await page.locator('select[name=mime]').selectOption('image/png');
  const invalid = page.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith('/admin/custom-emoji'));
  await page.getByRole('button', { name: 'Save emoji', exact: true }).click();
  expect((await invalid).status()).toBe(422);
  await expect(page.getByText(/reserved for a built-in emoji/)).toBeVisible();
  await expect(page.locator('input[name=name]')).toHaveValue('Keep this custom asset');
  await expect(page.locator('select[name=mime]')).toHaveValue('image/png');
  await shot(page, info, 'remediation-reserved-emoji-422');
  await axe(page);
  const shortcode = `review_${info.project.name}_${Date.now()}`;
  await page.locator('input[name=shortcode]').fill(shortcode);
  await page.getByRole('button', { name: 'Save emoji', exact: true }).click();
  await page.getByRole('button', { name: `Disable the :${shortcode}: emoji`, exact: true }).click();
  await page.getByRole('button', { name: `Enable the :${shortcode}: emoji`, exact: true }).click();
  await page.locator('input[name=shortcode]').fill(shortcode);
  await page.locator('input[name=name]').fill('Updated review asset');
  await page.locator('input[name=image_path]').fill('/emoji/features-review.png');
  await page.getByRole('button', { name: 'Save emoji', exact: true }).click();
  const audit = fixture('snapshot', shortcode).emoji_audit;
  expect(audit.map((row: { action: string }) => row.action)).toEqual(['custom_emoji.create', 'custom_emoji.disable', 'custom_emoji.enable', 'custom_emoji.replace']);
  expect(audit.every((row: { actor_id: number }) => Number(row.actor_id) > 0)).toBeTruthy();
  // axe injects JavaScript, so scan the confirmation surface in this enabled
  // context; the preceding test separately proves its complete no-JS flow.
  await page.goto('/admin/badge-rules');
  await page.locator('.features-rule-list > li').first().getByRole('link', { name: /Backfill/ }).click();
  await axe(page);
});

test('preview history reaches older rows and board actions remain visible at narrow widths', async ({ page }, info) => {
  const snapshot = fixture('history');
  expect(snapshot.fetched).toBeGreaterThan(50);
  await login(page);
  await page.goto('/admin/link-previews?status=fetched');
  await expect(page.locator('.link-preview-table tbody tr')).toHaveCount(50);
  const pager = page.getByRole('navigation', { name: 'Link preview history pages' });
  await pager.getByRole('link', { name: /Next/ }).click();
  await expect(page).toHaveURL(/status=fetched.*page=2/);
  await expect(page.locator('.link-preview-table')).toContainText('admin-review-1');
  await expect(page.locator('form[action="/admin/link-previews/settings"] input[name=return]')).toHaveValue('/admin/link-previews?status=fetched&page=2');
  await shot(page, info, 'remediation-preview-history-page-2');
  await axe(page);
  await page.locator('.link-preview-table tbody tr').last().getByRole('button', { name: /Re-queue/ }).click();
  await expect(page).toHaveURL(/status=fetched.*page=2/);
  for (const width of [1440, 901, 390, 320]) {
    await page.setViewportSize({ width, height: 1000 });
    await page.goto('/admin/link-previews');
    const bounds = await page.locator('.link-preview-board-table').evaluate(table => {
      const region = table.parentElement!;
      const rr = region.getBoundingClientRect();
      return { width: region.clientWidth, scroll: region.scrollWidth, viewport: document.documentElement.clientWidth, document: document.documentElement.scrollWidth, controls: [...table.querySelectorAll('button')].map(button => { const r = button.getBoundingClientRect(); return { left: r.left, right: r.right, height: r.height, visible: r.left >= rr.left && r.right <= rr.right + 1 }; }), collisions: [...table.querySelectorAll('tbody tr')].some(row => { const state = row.querySelector('.state')!.getBoundingClientRect(); const button = row.querySelector('button')!.getBoundingClientRect(); return state.right > button.left; }) };
    });
    expect(bounds.scroll, `${width}px board table`).toBeLessThanOrEqual(bounds.width + 1);
    expect(bounds.document).toBeLessThanOrEqual(bounds.viewport);
    expect(bounds.controls.every(control => control.visible && control.height >= 44)).toBeTruthy();
    expect(bounds.collisions, `${width}px state/button overlap`).toBeFalsy();
    if (width === 390 || width === 1440) {
      await shot(page, info, `remediation-board-controls-${width}`);
      await page.locator('.link-preview-config > section').last().screenshot({ path: path.join(evidence, info.project.name, `remediation-board-controls-${width}-detail.png`) });
    }
  }
});
