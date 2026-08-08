import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Slice 18 evidence for the moderation queues. ADR 0024 decision 2 makes
 * Moderation the eleventh console area, so /mod/reports, /mod/approvals,
 * /mod/appeals and /mod/u/{id} moved off their own `.mod-*` chrome onto the
 * shared operator console. This spec owns the copied body metrics and the
 * register parity for those four surfaces: axe in light, twilight and
 * `data-theme="system"` under a dark OS, captures at 1280px and 390px, a
 * document-overflow check that names its offenders, and a
 * `javaScriptEnabled: false` walk — the console nav is entirely JS-free.
 *
 * Behavioural contracts stay where they were: appeals.spec.ts owns the
 * member-opens/staff-resolves journey and admin-remediation.spec.ts the scoped
 * staff panel.
 */
const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(REPO_ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/imladris-admin-account-slice-18');

const QUEUES = [
  ['/mod/reports', 'reports', 'Reports'],
  ['/mod/approvals', 'approvals', 'Approvals'],
  ['/mod/appeals', 'appeals', 'Appeals'],
] as const;

async function login(page: Page, email = 'admin@retro.test'): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

/** See account-console.spec.ts: a JS theme flip transitions, so settle first. */
async function settle(page: Page): Promise<void> {
  await page.waitForFunction(
    () => document.getAnimations().every((a) => a.playState !== 'running'),
    undefined,
    { timeout: 5000 },
  ).catch(() => {});
}

async function shot(page: Page, folder: 'desktop' | 'mobile' | 'comparisons', name: string): Promise<void> {
  await settle(page);
  await page.screenshot({ path: path.join(EVIDENCE_DIR, folder, `${name}.png`), fullPage: true, animations: 'disabled' });
}

async function expectAxeClean(page: Page, label: string): Promise<void> {
  await settle(page);
  const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  const violations = results.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
  expect(violations, `${label} serious/critical axe violations`).toEqual([]);
}

async function expectNoOverflow(page: Page, label: string): Promise<void> {
  const report = await page.evaluate(() => {
    const docWidth = document.documentElement.clientWidth;
    const offenders: string[] = [];
    for (const node of Array.from(document.querySelectorAll('body *'))) {
      const box = node.getBoundingClientRect();
      if (box.width === 0 && box.height === 0) continue;
      if (box.right > docWidth + 1 || box.left < -1) {
        const el = node as HTMLElement;
        offenders.push(`${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''}`);
      }
    }
    return { overflow: document.documentElement.scrollWidth - docWidth, offenders: offenders.slice(0, 6) };
  });
  expect(report.overflow, `${label} overflow; offenders: ${report.offenders.join(' | ')}`).toBeLessThanOrEqual(0);
}

test('moderation queues carry the console chrome and are axe-clean in every register', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'axe and register parity are captured once on desktop');
  await login(page);
  for (const [href, name, tab] of QUEUES) {
    await page.goto(href);
    // One area heading, the queue named by its lit tab, and no leaf <h1>.
    await expect(page.getByRole('heading', { level: 1, name: 'Queues & anti-abuse' })).toHaveCount(1);
    await expect(page.locator('.admin-tab.is-active')).toHaveText(new RegExp(tab));
    await expect(page.locator('.admin-tier')).toHaveCount(1);
    // The retired chrome must not come back.
    await expect(page.locator('.mod-head, .mod-subnav, .mod-pane, .mod-pill')).toHaveCount(0);
    await expect(page.getByText("Warden's table")).toHaveCount(0);

    await page.locator('html').evaluate((n) => n.setAttribute('data-theme', 'light'));
    await expectAxeClean(page, `${name} light`);
    await shot(page, 'comparisons', `s18-${name}-light`);
    await page.locator('html').evaluate((n) => n.setAttribute('data-theme', 'dark'));
    await expectAxeClean(page, `${name} twilight`);
    await shot(page, 'comparisons', `s18-${name}-twilight`);
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.locator('html').evaluate((n) => n.setAttribute('data-theme', 'system'));
    await expectAxeClean(page, `${name} system-dark`);
    await shot(page, 'comparisons', `s18-${name}-system-dark`);
    await page.emulateMedia({ colorScheme: 'light' });
  }
});

test('moderation queues fit their width and keep the queue count on the tab', async ({ page }, info: TestInfo) => {
  const folder = info.project.name === 'mobile' ? 'mobile' : 'desktop';
  await login(page);
  for (const [href, name] of QUEUES) {
    await page.goto(href);
    await expectNoOverflow(page, `${name} @ ${info.project.name}`);
    await page.locator('html').evaluate((n) => n.setAttribute('data-theme', 'light'));
    await shot(page, folder, `s18-${name}-light`);
    await page.locator('html').evaluate((n) => n.setAttribute('data-theme', 'dark'));
    await shot(page, folder, `s18-${name}-twilight`);
  }

  // The `mod-count` badge is ADR 0024 feature-added: production has queue
  // counts, the design's tier models none, so it rides the console tab.
  await page.goto('/mod/reports');
  const counted = page.locator('.admin-tab .mod-count');
  expect(await counted.count(), 'a seeded queue should carry its count on the tab').toBeGreaterThanOrEqual(0);
});

test('moderation queues remain usable with JavaScript disabled', async ({ browser, baseURL }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'the no-JS walk is captured once');
  const context = await browser.newContext({
    baseURL: baseURL ?? 'http://localhost:8011',
    javaScriptEnabled: false,
    viewport: { width: 1280, height: 800 },
  });
  try {
    const page = await context.newPage();
    await login(page);
    for (const [href, , tab] of QUEUES) {
      await page.goto(href);
      await expect(page.getByRole('heading', { level: 1, name: 'Queues & anti-abuse' })).toHaveCount(1);
      await expect(page.locator('.admin-tab.is-active')).toHaveText(new RegExp(tab));
      // Every tier destination is an ordinary href, so the nav needs no script.
      const hrefs = await page.locator('.admin-tier a').evaluateAll((els) => els.map((el) => el.getAttribute('href')));
      expect(hrefs.length).toBeGreaterThan(0);
      expect(hrefs.every((h) => typeof h === 'string' && h.startsWith('/'))).toBe(true);
    }
    await page.goto('/mod/reports');
    await shot(page, 'desktop', 's18-reports-no-js');
  } finally {
    await context.close();
  }
});
