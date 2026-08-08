import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Slice 14 evidence for AdminPackages.dc.html. The supply-chain surfaces — the
 * signed catalogue, the package record, the security response console, the
 * publisher record, registry trust and the server-extension probe — are checked
 * in the light and twilight registers plus the `data-theme="system"` dark path
 * that `layout.php` actually defaults to, at 1280px and 390px.
 *
 * Behavioural contracts (install/consent/enable journey, brake re-auth, review
 * refusal, credential provisioning) live in gate-a, package-security,
 * package-review and package-integrations; this spec owns the copied body
 * metrics, register parity, the anti-draft-loss 422 captures and the no-JS walk.
 *
 * The whole file needs the dark-surface seed: `/admin/extensions` 404s without
 * `server_extensions`, and the publisher/security fixtures only exist there.
 */
test.skip(
  !process.env.RB_BROWSER_DARK_SURFACES,
  'needs RB_BROWSER_DARK_SURFACES=1: server_extensions and the publisher fixtures are seeded only there',
);

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

/** Same compositor caveat as members-console/features-console: pin the sticky bar for the scan. */
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

/** Names the offending elements: this is how slice 13's two layout defects were found. */
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

async function expectPackagesArea(
  page: Page,
  tab: 'Packages' | 'Registry trust' | 'Extensions',
): Promise<void> {
  await expect(page.getByRole('heading', { level: 1, name: 'Packages & registries' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText(tab);
  await expect(page.getByRole('navigation', { name: 'Supply chain sections' })).toHaveCount(1);
}

test('the signed catalogue copies the headless table card and chip vocabulary in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/packages');
  await expectPackagesArea(page, 'Packages');

  // The design's catalogue card is headless; the labelled scroll region names it.
  await expect(page.getByRole('heading', { level: 2, name: 'Packages', exact: true })).toHaveCount(0);
  await expect(page.locator('.packages-table-card [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'Package catalogue');
  await expect(page.locator('.packages-col-actions .sr-only').first()).toHaveText('Actions');
  // The security console keeps a real link, not the design's button.
  await expect(page.locator('.packages-intro-action')).toHaveAttribute('href', '/admin/packages/security');
  // The catalogue stays a real table with a tbody — four specs navigate through it.
  await expect(page.locator('table.audit.packages-catalogue-table tbody tr').first()).toBeVisible();
  await expect(page.getByRole('link', { name: 'Details' }).first()).toBeVisible();

  await exerciseThemeRegisters(page, info, 'packages-catalogue');
});

test('the package record copies the two-up panels, fact grids and ruled lists in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/packages');
  await page.getByRole('link', { name: 'Details' }).first().click();
  await page.waitForURL(/\/admin\/packages\/\d+$/);
  await expectPackagesArea(page, 'Packages');

  // The drill-in keeps its own record title under the area h1, with the uid below.
  await expect(page.locator('h2.admin-record-title')).toBeVisible();
  await expect(page.locator('.packages-record-uid')).toBeVisible();
  await expect(page.locator('.admin-split').first()).toBeVisible();
  await expect(page.getByRole('heading', { level: 3, name: 'Provenance' })).toBeVisible();
  await expect(page.locator('.packages-facts .packages-fact-term').first()).toBeVisible();
  // Releases is a section title plus a caption, not one long heading.
  await expect(page.getByRole('heading', { level: 3, name: 'Releases' })).toBeVisible();
  await expect(page.getByText('Immutable: any changed byte is a new release.')).toBeVisible();
  // The per-row review form stays visible on first render — never behind a disclosure.
  await expect(page.locator('form.review-decision-form select[name="decision"]').first()).toBeVisible();
  await expect(page.locator('details.packages-disclosure')).toHaveCount(0);

  await exerciseThemeRegisters(page, info, 'packages-detail');
});

test('the security console keeps the brake off the admin pill and copies the ruled log', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/packages/security');
  await expectPackagesArea(page, 'Packages');

  await expect(page.getByRole('heading', { level: 2, name: 'Package security response' })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: 'Emergency execution brake' })).toBeVisible();
  // .pill-admin means three different things across 41 call sites; the brake is not one.
  await expect(page.locator('.pill-admin')).toHaveCount(0);
  await expect(page.getByText('The brake applies regardless of the package flag.')).toBeVisible();
  // Both re-auth inputs render inline and always.
  await expect(page.locator('form[action="/admin/packages/security/execution"] input[name="current_password"]')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Emergency-disable all packages' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Manage' }).first()).toBeVisible();

  await exerciseThemeRegisters(page, info, 'packages-security');

  // Anti-draft-loss: a refused brake toggle keeps the typed reason and shows the error.
  await page.locator('input[name="reason"]').fill('incident-typed');
  await page.locator('input[name="current_password"]').fill('wrong-password');
  const refused = await Promise.all([
    page.waitForResponse((r) => r.url().endsWith('/admin/packages/security/execution') && r.request().method() === 'POST'),
    page.getByRole('button', { name: 'Emergency-disable all packages' }).click(),
  ]);
  expect(refused[0].status()).toBe(422);
  await page.waitForLoadState('load');
  await expect(page.locator('input[name="reason"]')).toHaveValue('incident-typed');
  await expect(page.locator('#err-brake-current_password')).toBeVisible();
  await expectNoDocumentOverflow(page);
  await shot(page, info, 'packages-brake-422');
});

test('the publisher record copies the head chips, key table and re-openable disclosures', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/packages/security');
  await page.getByRole('link', { name: 'Manage' }).first().click();
  await page.waitForURL(/\/admin\/packages\/publishers\/\d+$/);
  await expectPackagesArea(page, 'Packages');

  await expect(page.locator('.packages-card-head h2.admin-record-title')).toBeVisible();
  await expect(page.locator('.packages-card-head .packages-pill').first()).toBeVisible();
  await expect(page.locator('table.registry-keys-table')).toBeVisible();
  await expect(page.locator('details.packages-disclosure')).toHaveCount(2);

  await exerciseThemeRegisters(page, info, 'packages-publisher');

  // A refused suspend replays its own reason and never the revoke form's.
  const suspend = page.locator('form[action$="/suspend"]');
  if (await suspend.count() > 0) {
    await suspend.locator('input[name="reason"]').fill('suspend-typed');
    await suspend.locator('input[name="current_password"]').fill('wrong-password');
    const refused = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/suspend') && r.request().method() === 'POST'),
      suspend.getByRole('button', { name: 'Suspend publisher' }).click(),
    ]);
    expect(refused[0].status()).toBe(422);
    await page.waitForLoadState('load');
    await expect(page.locator('form[action$="/suspend"] input[name="reason"]')).toHaveValue('suspend-typed');
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'packages-publisher-422');
  }
});

test('registry trust copies the card head toggle, ruled blocklist and disclosures', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/registries');
  await expectPackagesArea(page, 'Registry trust');

  await expect(page.locator('.packages-registry-card').first()).toBeVisible();
  await expect(page.locator('.registry-toggle-form').first()).toBeVisible();
  await expect(page.locator('.registry-snapshot').first()).toBeVisible();
  // The asymmetry is stated, not parenthesised into a button label.
  await expect(page.getByText('Blocking takes effect immediately and needs no password; removing a block does.')).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: /Local blocklist/ })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Block now' })).toBeVisible();

  await exerciseThemeRegisters(page, info, 'packages-registries');

  // A refused key pin re-opens its disclosure and replays the typed key id. The
  // disclosure starts closed, so open it first — <details> needs no JavaScript.
  await page.locator('details.packages-disclosure').first().locator('summary').click();
  const pin = page.locator('form[action$="/keys"]').first();
  await pin.locator('input[name="key_id"]').fill('root-2');
  await pin.locator('input[name="public_key"]').fill('not-a-valid-key');
  await pin.locator('input[name="current_password"]').fill('password123');
  const refused = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/keys') && r.request().method() === 'POST'),
    pin.getByRole('button', { name: 'Pin key' }).click(),
  ]);
  expect(refused[0].status()).toBe(422);
  await page.waitForLoadState('load');
  await expect(page.locator('form[action$="/keys"] input[name="key_id"]').first()).toHaveValue('root-2');
  await expect(page.locator('details.packages-disclosure[open]').first()).toBeVisible();
  await expectNoDocumentOverflow(page);
  await shot(page, info, 'packages-registries-422');
});

test('the extensions probe copies the honest flag callout and the run-history ladder', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/extensions');
  await expectPackagesArea(page, 'Extensions');

  // C-32: the design's copy claims this page renders while the flag is dark. It cannot.
  await expect(page.locator('.extension-flag-note')).toBeVisible();
  await expect(page.getByText('this page is only reachable while that flag is on')).toBeVisible();
  await expect(page.locator('.extension-probe-state')).toBeVisible();
  await expect(page.getByRole('heading', { level: 3, name: 'Run history' })).toBeVisible();

  await exerciseThemeRegisters(page, info, 'packages-extensions');
});

test('every packages route stays whole with JavaScript disabled', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: info.project.name === 'mobile' ? { width: 390, height: 844 } : { width: 1280, height: 900 },
  });
  const page = await context.newPage();
  try {
    await login(page);

    await page.goto('/admin/packages');
    await expectPackagesArea(page, 'Packages');
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'packages-catalogue-no-js');

    await page.getByRole('link', { name: 'Details' }).first().click();
    await page.waitForURL(/\/admin\/packages\/\d+$/);
    await expectPackagesArea(page, 'Packages');
    await expect(page.locator('form.review-decision-form select[name="decision"]').first()).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'packages-detail-no-js');

    await page.goto('/admin/packages/security');
    await expectPackagesArea(page, 'Packages');
    await expect(page.locator('form[action="/admin/packages/security/execution"] input[name="current_password"]')).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'packages-security-no-js');

    await page.goto('/admin/registries');
    await expectPackagesArea(page, 'Registry trust');
    // The three disclosures are <details>, which work with no JavaScript at all.
    await expect(page.locator('details.packages-disclosure').first()).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'packages-registries-no-js');

    await page.goto('/admin/extensions');
    await expectPackagesArea(page, 'Extensions');
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'packages-extensions-no-js');
  } finally {
    await context.close();
  }
});
