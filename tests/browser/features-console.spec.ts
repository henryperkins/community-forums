import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Slice 13 evidence for AdminFeatures.dc.html. The four capability surfaces —
 * the feature-flag ledger, the badge-rule console, the badge-rule preview
 * drill-in and the custom emoji catalogue — are checked in the light and
 * twilight registers plus the `data-theme="system"` dark path that
 * `layout.php` actually defaults to, at 1280px and 390px.
 *
 * The behavioural contracts (readiness classification, flag rollback, award
 * backfill/revoke, shortcode rendering) live in admin-features, gate-a and
 * a11y; this spec owns the copied body metrics, the register parity, the
 * anti-draft-loss 422 captures and the no-JS walk.
 *
 * Two states in the plan's capture list are unreachable from the browser and
 * are proved in PHPUnit instead: a corrupt `settings.features` blob and the
 * all-flags-dark tier, neither of which has a UI that can produce it — the
 * ledger is deliberately toggle-free. See AppAdminFeaturesTest.
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

/** Same compositor caveat as members-console/integrations-console: pin the sticky bar for the scan. */
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
  const dimensions = await page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const offenders: string[] = [];
    document.querySelectorAll<HTMLElement>('*').forEach((el) => {
      const r = el.getBoundingClientRect();
      if (r.right > vw + 1) offenders.push(`${el.tagName}.${el.className || '-'}@${Math.round(r.right)}`);
    });
    return {
      viewport: vw,
      document: document.documentElement.scrollWidth,
      url: location.pathname,
      offenders: offenders.slice(0, 12).join(' | '),
    };
  });
  expect(dimensions.document, `${dimensions.url} :: ${dimensions.offenders}`).toBeLessThanOrEqual(dimensions.viewport);
}

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

async function expectFeaturesArea(
  page: Page,
  tab: 'Feature flags' | 'Badge rules' | 'Custom emoji',
): Promise<void> {
  await expect(page.getByRole('heading', { level: 1, name: 'Features & badges' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText(tab);
  await expect(page.getByRole('navigation', { name: 'Capability sections' })).toHaveCount(1);
}

/** Create a badge rule through the real no-JS form and return its list row. */
async function createRule(page: Page): Promise<void> {
  await page.goto('/admin/badge-rules');
  await page.locator('select[name="rule_type"]').selectOption('post_count');
  await page.locator('input[name="threshold"]').fill('2');
  await page.getByRole('button', { name: 'Create rule' }).click();
  await expect(page.getByRole('status')).toContainText('Badge rule created.');
  // The flash lands in the DOM before app.css finishes loading on the redirect,
  // and every layout assertion after this point measures computed geometry.
  await page.waitForLoadState('load');
}

/**
 * Add a catalogue entry through the real no-JS form. The shortcode is unique per
 * call because prepare.sh seeds once per group, not once per test — reusing one
 * would take the honest "replaced" branch and never prove the create flash.
 */
async function createEmoji(page: Page, prefix: string): Promise<string> {
  const shortcode = `${prefix}${Date.now()}`.toLowerCase();
  await page.goto('/admin/custom-emoji');
  await page.locator('input[name="shortcode"]').fill(shortcode);
  await page.locator('input[name="name"]').fill('Party');
  await page.locator('input[name="image_path"]').fill(`/emoji/${shortcode}.webp`);
  await page.locator('select[name="mime"]').selectOption('image/webp');
  await page.locator('input[name="allow_reactions"]').check();
  await page.getByRole('button', { name: 'Save emoji' }).click();
  await expect(page.getByRole('status')).toContainText('Custom emoji saved.');
  await page.waitForLoadState('load');
  return shortcode;
}

test('the feature-flag ledger copies the tiles, table chrome and relocated legend in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/features');
  await expectFeaturesArea(page, 'Feature flags');

  // Four summary tiles in the design's own card, not the overview .queue-card
  // (which carries a 3px accent rule and a 168px floor this screen does not).
  await expect(page.locator('.features-stat-grid .features-stat')).toHaveCount(4);
  await expect(page.locator('.features-stat-head').first()).toHaveText('Declared');
  await expect(page.locator('.queue-card')).toHaveCount(0);

  // The intro is the design's three sentences; the readiness key and both doc
  // pointers moved below the tables rather than being dropped.
  await expect(page.locator('.features-intro')).toContainText('there are intentionally no toggles here');
  await expect(page.locator('.features-intro')).not.toContainText('Reserved (ADR 0018)');
  const legend = page.locator('.features-legend');
  await expect(legend).toContainText('Reserved (ADR 0018)');
  await expect(legend).toContainText('src/Core/FeatureFlags.php');
  await expect(page.locator('table .features-legend')).toHaveCount(0);

  // Effective keeps the full string (C-23) and gains the design's dot.
  const effective = page.locator('.features-effective').first();
  await expect(effective).toContainText('Effective on');
  await expect(effective.locator('.features-dot')).toHaveCount(1);
  // Every group table stays a keyboard-reachable scroll region (C-05).
  const regions = page.locator('.features-group-card [role="region"][tabindex="0"]');
  expect(await regions.count()).toBeGreaterThan(0);
  await expect(page.locator('.features-flags-table th').first()).toHaveText('Flag');

  await exerciseThemeRegisters(page, info, 'features-flags');
});

test('badge rules copies the two-up create card, rule rows and action order in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await createRule(page);
  await expectFeaturesArea(page, 'Badge rules');

  await expect(page.locator('.features-two-up')).toHaveCount(1);
  await expect(page.getByText('Rules award automatically once the metric crosses the threshold.')).toBeVisible();
  await expect(page.locator('.features-field-pair')).toHaveCount(1);

  // Rules are created inert, so the first row offers Enable, and the design's
  // action order is Preview · Backfill · {toggle} · Revoke awards.
  const row = page.locator('.features-rule-list > li').first();
  await expect(row.locator('.features-pill')).toHaveText('Disabled');
  await expect(row.locator('.features-rule-star')).toHaveAttribute('aria-hidden', 'true');
  const actions = row.locator('.features-rowbtn');
  await expect(actions).toHaveText(['Preview', 'Backfill', 'Enable', 'Revoke awards']);
  // Preview navigates; every mutation is a POST form (C-11).
  await expect(row.getByRole('link', { name: 'Preview' })).toHaveAttribute('href', /\/preview$/);
  await expect(row.locator('form[method="post"]')).toHaveCount(3);

  await exerciseThemeRegisters(page, info, 'features-badge-rules');

  // Anti-draft-loss: an invalid rule type re-renders at 422 with the typed
  // threshold intact and the select programmatically wired to its error.
  await page.goto('/admin/badge-rules');
  await page.locator('input[name="threshold"]').fill('37');
  await page.locator('form[action="/admin/badge-rules"]').evaluate((form) => {
    (form as HTMLFormElement).noValidate = true;
    const select = form.querySelector('select[name="rule_type"]') as HTMLSelectElement;
    const option = document.createElement('option');
    option.value = 'not-a-rule';
    select.append(option);
    select.value = 'not-a-rule';
  });
  const invalid = await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/badge-rules') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create rule' }).click(),
  ]);
  expect(invalid[0].status()).toBe(422);
  await expect(page.locator('input[name="threshold"]')).toHaveValue('37');
  await expect(page.locator('select[name="rule_type"]')).toHaveAttribute('aria-invalid', 'true');
  await expectNoDocumentOverflow(page);
  await shot(page, info, 'features-badge-rules-422');
});

test('the badge-rule preview drill-in keeps its parent tab lit and copies the monogram roster', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await createRule(page);
  await page.locator('.features-rule-list > li').first().getByRole('link', { name: 'Preview' }).click();
  await page.waitForURL(/\/admin\/badge-rules\/\d+\/preview$/);

  // FC-12: the drill-in is a real route, so it keeps its own record title while
  // the area heading and the Badge rules tab stay owned by the console.
  await expectFeaturesArea(page, 'Badge rules');
  await expect(page.getByRole('heading', { level: 2, name: 'Badge rule preview' })).toBeVisible();
  await expect(page.locator('.admin-back')).toHaveText(/Back to badge rules/);
  await expect(page.locator('.features-preview-meta')).toContainText('post_count');
  // FC-14: no lead count — preview() pages at 100 while backfill runs at 1000.
  await expect(page.locator('.features-preview-card')).not.toContainText('meet this rule');

  const rows = page.locator('.features-preview-list > li');
  if (await rows.count() > 0) {
    await expect(rows.first().locator('.monogram')).toHaveCount(1);
    await expect(rows.first().locator('.features-preview-metric')).toContainText('Metric:');
  } else {
    await expect(page.locator('.features-empty')).toHaveText('No members would receive this badge.');
  }

  await exerciseThemeRegisters(page, info, 'features-badge-rule-preview');
});

test('the custom emoji catalogue copies the switch, chip and status pills in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);

  // The unseeded catalogue proves the design's centred italic empty state first.
  await page.goto('/admin/custom-emoji');
  await expectFeaturesArea(page, 'Custom emoji');
  if (await page.locator('.features-emoji-table').count() === 0) {
    await expect(page.locator('.features-empty')).toHaveText('No custom emoji have been added yet.');
  }
  // FR-28: the media-root sentence is not shipped — no /emoji/* route serves it.
  await expect(page.locator('.features-intro')).not.toContainText('media root');

  // The reaction control is a real checkbox painted as the design's switch, so
  // it still submits with the form when JavaScript is off.
  const toggle = page.locator('.features-switch input[name="allow_reactions"]');
  await expect(toggle).toBeVisible();
  await expect(toggle).toHaveAttribute('type', 'checkbox');

  const code = await createEmoji(page, 'party');
  const row = page.locator('.features-emoji-table tbody tr').filter({ hasText: `:${code}:` });
  await expect(row.locator('.features-emoji-chip img')).toHaveAttribute('alt', `:${code}:`);
  await expect(row.locator('.features-pill')).toHaveText('Enabled');
  await expect(row.getByRole('button', { name: `Disable the :${code}: emoji` })).toBeVisible();
  // The catalogue section is unheaded; the scroll region is the one named region.
  await expect(page.getByRole('heading', { level: 2, name: 'Catalogue' })).toHaveCount(0);
  await expect(page.locator('.features-table-card [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'Custom emoji catalogue');
  await expect(page.locator('.features-col-actions .sr-only')).toHaveText('Action');

  await exerciseThemeRegisters(page, info, 'features-custom-emoji');

  // The toggle flash names the row it closed rather than a generic outcome.
  await row.getByRole('button', { name: `Disable the :${code}: emoji` }).click();
  await expect(page.getByRole('status')).toContainText(`:${code}: disabled — it will render as plain text.`);

  // Anti-draft-loss: a rejected shortcode replays every other typed field.
  await page.locator('input[name="shortcode"]').fill('X!');
  await page.locator('input[name="name"]').fill('Typed Name');
  await page.locator('input[name="image_path"]').fill('/emoji/typed.webp');
  await page.locator('form[action="/admin/custom-emoji"]').evaluate((form) => {
    (form as HTMLFormElement).noValidate = true;
  });
  const rejected = await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/custom-emoji') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Save emoji' }).click(),
  ]);
  expect(rejected[0].status()).toBe(422);
  await expect(page.locator('input[name="name"]')).toHaveValue('Typed Name');
  await expect(page.locator('input[name="image_path"]')).toHaveValue('/emoji/typed.webp');
  await expect(page.locator('#err-emoji-shortcode')).toBeVisible();
  await expectNoDocumentOverflow(page);
  await shot(page, info, 'features-custom-emoji-422');
});

test('every features route stays whole with JavaScript disabled', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: info.project.name === 'mobile' ? { width: 390, height: 844 } : { width: 1280, height: 900 },
  });
  const page = await context.newPage();
  try {
    await login(page);

    await page.goto('/admin/features');
    await expectFeaturesArea(page, 'Feature flags');
    await expect(page.locator('.features-stat')).toHaveCount(4);
    await expect(page.locator('.features-legend')).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'features-flags-no-js');

    // Creating a rule, previewing it and toggling emoji are all plain form posts.
    await createRule(page);
    await expectFeaturesArea(page, 'Badge rules');
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'features-badge-rules-no-js');

    await page.locator('.features-rule-list > li').first().getByRole('link', { name: 'Preview' }).click();
    await page.waitForURL(/\/admin\/badge-rules\/\d+\/preview$/);
    await expectFeaturesArea(page, 'Badge rules');
    await expect(page.getByRole('heading', { level: 2, name: 'Badge rule preview' })).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'features-badge-rule-preview-no-js');

    const code = await createEmoji(page, `nojs${info.project.name}`);
    await expectFeaturesArea(page, 'Custom emoji');
    const row = page.locator('.features-emoji-table tbody tr').filter({ hasText: `:${code}:` });
    await expect(row.locator('.features-pill')).toHaveText('Enabled');
    // The switch submitted its value with no JavaScript in the page.
    await expect(row.locator('.features-emoji-reactions')).toHaveText('Allowed');
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'features-custom-emoji-no-js');
  } finally {
    await context.close();
  }
});
