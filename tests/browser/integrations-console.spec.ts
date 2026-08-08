import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

/**
 * Slice 12 evidence for AdminIntegrations.dc.html. The five integration
 * surfaces — API tokens, the webhook list, the webhook detail, the sign-in
 * provider roster and the disable drill-in — are checked in the light and
 * twilight registers plus the `data-theme="system"` dark path that
 * `layout.php` actually defaults to, at 1280px and 390px.
 *
 * The behavioural contracts (mint-once, 409 replay, rotate/delete re-auth,
 * enable-error row routing, sole-method listing) live in api-tokens,
 * webhooks, providers and admin-remediation; this spec owns the copied body
 * metrics, the register parity and the no-JS walk.
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

/** Same compositor caveat as members-console/admin-dashboard: pin the sticky bar for the scan. */
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

async function expectIntegrationsArea(
  page: Page,
  tab: 'API tokens' | 'Webhooks' | 'Sign-in providers',
): Promise<void> {
  await expect(page.getByRole('heading', { level: 1, name: 'Tokens, webhooks & sign-in' })).toBeVisible();
  await expect(page.locator('span.admin-tab.is-active[aria-current="page"]')).toHaveText(tab);
  await expect(page.getByRole('navigation', { name: 'Integration sections' })).toHaveCount(1);
}

/** Register an endpoint through the real no-JS form and return to its detail page. */
async function registerEndpoint(page: Page, name: string): Promise<void> {
  await page.goto('/admin/webhooks');
  await page.locator('input[name="name"]').fill(name);
  await page.locator('input[name="url"]').fill('https://ops.example.com/hooks/forum');
  await page.locator('input[name="events[]"][value="ping"]').check();
  await page.locator('form[action="/admin/webhooks"] input[name="current_password"]').fill('password123');
  await page.getByRole('button', { name: 'Register endpoint' }).click();
  await expect(page.locator('.flash-secret code')).toBeVisible();
}

test('API tokens copies the two-up panel, scope fieldset and reveal-once plate in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/api-tokens');
  await expectIntegrationsArea(page, 'API tokens');

  // The design's tables sit in unheaded cards; the shipped scroll region survives.
  await expect(page.getByRole('heading', { level: 2, name: 'Tokens', exact: true })).toHaveCount(0);
  await expect(page.locator('.integrations-table-card [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'API tokens');
  await expect(page.getByText('A token carries only the scopes you tick.')).toBeVisible();
  // Scope rows carry the design's `code` key + em-dash description over the real catalogue.
  await expect(page.locator('.integrations-check code').first()).toHaveText('read:boards');

  const tokenName = `Read-only mirror (${info.project.name}-${Date.now()})`;
  await page.locator('input[name="name"]').fill(tokenName);
  await page.locator('input[name="scopes[]"][value="read:boards"]').check();
  await page.locator('input[name="current_password"]').fill('password123');
  await page.getByRole('button', { name: 'Create token' }).click();

  // A minted credential gets the gold plate, not the green success flash.
  const secret = page.locator('.flash.flash-secret');
  await expect(secret).toContainText('Copy this token now — it will not be shown again:');
  await expect(secret.locator('code')).toContainText('rbt_');
  const row = page.locator('tbody tr', { hasText: tokenName });
  await expect(row.getByRole('button', { name: `Revoke the ${tokenName} token` })).toBeVisible();
  await expect(row.locator('.state')).toHaveText('active');

  await exerciseThemeRegisters(page, info, 'integrations-tokens');

  // The 409 replay banner is the error plate, and the list still renders behind it.
  const replay = await page.reload();
  expect(replay?.status()).toBe(409);
  await expect(page.locator('.flash.flash-error')).toContainText('already processed');
  await expect(page.locator('tbody tr', { hasText: tokenName })).toHaveCount(1);
  await expectNoDocumentOverflow(page);
  await shot(page, info, 'integrations-tokens-conflict');
});

test('the webhook list copies the register card, six-attempt intro and endpoint table in every register', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await registerEndpoint(page, `Ops bridge (${info.project.name}-${Date.now()})`);
  await expectIntegrationsArea(page, 'Webhooks');

  // Six, not the design's three — webhooks.max_attempts is 6.
  await expect(page.getByText('retried up to six times before they go dead')).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: 'Endpoints', exact: true })).toHaveCount(0);
  await expect(page.locator('.integrations-table-card [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'Webhook endpoints');
  // Manage navigates, so it stays an <a> wearing the design's outline button.
  const manage = page.getByRole('link', { name: 'Manage' }).first();
  await expect(manage).toHaveAttribute('href', /\/admin\/webhooks\/\d+$/);
  await expect(page.locator('th.integrations-col-numeric', { hasText: 'Last status' })).toHaveCSS('text-align', 'right');

  await exerciseThemeRegisters(page, info, 'integrations-webhooks');
});

test('the webhook detail merges the action row, fields and Save/Delete into one card', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  const name = `Ops bridge detail (${info.project.name}-${Date.now()})`;
  await registerEndpoint(page, name);
  await page.goto('/admin/webhooks');
  await page.locator('tbody tr', { hasText: name }).getByRole('link', { name: 'Manage' }).click();
  await page.waitForURL(/\/admin\/webhooks\/\d+$/);
  await expectIntegrationsArea(page, 'Webhooks');

  // One h2 carrying the endpoint name; the design's Configuration/Actions split is gone.
  await expect(page.locator('h2.admin-record-title')).toHaveText(name);
  await expect(page.getByRole('link', { name: 'All endpoints' })).toHaveAttribute('href', '/admin/webhooks');
  await expect(page.locator('.webhook-detail-card')).toHaveCount(1);
  await expect(page.getByRole('heading', { level: 2, name: 'Configuration' })).toHaveCount(0);

  // Re-auth renders inline and always — never behind a JS disclosure (C-14).
  await expect(page.locator('form[action$="/rotate"] input[name="current_password"]')).toBeVisible();
  await expect(page.locator('form[action$="/delete"] input[name="current_password"]')).toBeVisible();
  // Save reaches its form by id so Delete keeps its own form around the reauth field.
  await expect(page.getByRole('button', { name: 'Save' })).toHaveAttribute('form', 'webhook-config');
  await expect(page.getByRole('button', { name: 'Delete endpoint' })).toBeVisible();

  // Queue a delivery so the log is populated for the captures.
  await page.locator('form[action$="/test"] button[type="submit"]').click();
  await expect(page.getByRole('status')).toContainText('Test event queued');
  await expect(page.getByRole('heading', { level: 3, name: 'Recent deliveries' })).toBeVisible();
  await expect(page.locator('.integrations-deliveries-table thead th')).toHaveCount(5);
  const delivery = page.locator('.integrations-deliveries-table tbody tr').first();
  await expect(delivery.locator('.state')).toHaveText('queued');
  await expect(delivery.locator('td.integrations-col-numeric').first()).toHaveText('0 / 6');

  await exerciseThemeRegisters(page, info, 'integrations-webhook-detail');
});

test('the provider roster copies the merged identity cell, status pill and link actions', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  await page.goto('/admin/providers');
  await expectIntegrationsArea(page, 'Sign-in providers');

  await expect(page.getByRole('heading', { level: 2, name: 'Providers', exact: true })).toHaveCount(0);
  await expect(page.locator('.integrations-table-card [role="region"][tabindex="0"]'))
    .toHaveAttribute('aria-label', 'Sign-in providers');
  // Seven columns: Key is merged into Provider, Sole-method renamed and right-aligned.
  await expect(page.locator('.integrations-providers-table thead th')).toHaveCount(7);
  await expect(page.locator('.integrations-providers-table thead th').nth(4)).toHaveText('Sole-method');
  await expect(page.locator('.integrations-providers-table thead th').nth(4)).toHaveCSS('text-align', 'right');
  // The test-anchored lockout count survives the restyle (ADR 0023).
  await expect(page.locator('td[data-sole-count]').first()).toBeVisible();

  const key = `oidc-${info.project.name}-${Date.now()}`;
  const label = `Corporate Keycloak (${info.project.name})`;
  await page.locator('input[name="provider_key"]').fill(key);
  await page.locator('input[name="display_name"]').fill(label);
  await page.locator('input[name="issuer"]').fill('https://id.example.com/realms/main');
  await page.locator('input[name="client_id"]').fill('evidence-client-id');
  await page.locator('input[name="client_secret"]').fill('evidence-client-secret');
  await page.locator('form[action="/admin/providers"] input[name="current_password"]').fill('password123');
  await page.getByRole('button', { name: 'Add provider' }).click();
  await expect(page.getByRole('status')).toContainText(`${label} was added, disabled.`);

  const row = page.locator('tbody tr', { hasText: label });
  await expect(row.locator('.provider-key')).toHaveText(key);
  await expect(row.locator('.provider-status')).toHaveText('Disabled');
  // Health is the real DB enum, composed into the design's one middot-joined string.
  await expect(row.locator('td.provider-health')).toHaveText('unknown');
  await expect(row.getByRole('button', { name: 'Test connection' })).toBeVisible();

  await exerciseThemeRegisters(page, info, 'integrations-providers');
});

test('the disable drill-in copies the single heading, rust plate and text-link cancel', async ({ page }, info) => {
  test.setTimeout(180_000);
  await login(page);
  const key = `oidc-dis-${info.project.name}-${Date.now()}`;
  const label = `Corporate Keycloak disable (${info.project.name})`;
  await page.goto('/admin/providers');
  await page.locator('input[name="provider_key"]').fill(key);
  await page.locator('input[name="display_name"]').fill(label);
  await page.locator('input[name="issuer"]').fill('https://id.example.com/realms/main');
  await page.locator('input[name="client_id"]').fill('evidence-client-id');
  await page.locator('input[name="client_secret"]').fill('evidence-client-secret');
  await page.locator('form[action="/admin/providers"] input[name="current_password"]').fill('password123');
  await page.getByRole('button', { name: 'Add provider' }).click();

  const enableForm = page.locator('tbody tr', { hasText: label }).locator('form[action$="/enable"]');
  await enableForm.locator('input[name="current_password"]').fill('password123');
  await enableForm.getByRole('button', { name: 'Enable' }).click();
  await expect(page.getByRole('status')).toContainText(`${label} is enabled and now offered on the sign-in page.`);

  // Disable stays a link to a real confirm route (PE + TM-ID-09 clause 2).
  await page.locator('tbody tr', { hasText: label }).getByRole('link', { name: 'Disable…' }).click();
  await page.waitForURL(/\/admin\/providers\/\d+\/disable$/);
  await expectIntegrationsArea(page, 'Sign-in providers');

  await expect(page.getByRole('link', { name: 'Sign-in providers' }).first())
    .toHaveAttribute('href', '/admin/providers');
  await expect(page.locator('h2.admin-record-title')).toHaveText(`Before you disable ${label}`);
  await expect(page.getByRole('heading', { level: 2, name: 'Before you disable', exact: true })).toHaveCount(0);
  await expect(page.getByText('No accounts rely on this provider as their only sign-in method.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Cancel' })).toHaveAttribute('href', '/admin/providers');
  await expect(page.getByRole('button', { name: `Disable ${label}` })).toHaveClass(/danger/);

  await exerciseThemeRegisters(page, info, 'integrations-provider-disable');
});

test('every integrations route stays whole with JavaScript disabled', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: info.project.name === 'mobile' ? { width: 390, height: 844 } : { width: 1280, height: 900 },
  });
  const page = await context.newPage();
  try {
    await login(page);

    await page.goto('/admin/api-tokens');
    await expectIntegrationsArea(page, 'API tokens');
    await expect(page.locator('input[name="current_password"]')).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'integrations-tokens-no-js');

    const name = `No-JS endpoint (${info.project.name}-${Date.now()})`;
    await registerEndpoint(page, name);
    await expectIntegrationsArea(page, 'Webhooks');
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'integrations-webhooks-no-js');

    await page.goto('/admin/webhooks');
    await page.locator('tbody tr', { hasText: name }).getByRole('link', { name: 'Manage' }).click();
    await page.waitForURL(/\/admin\/webhooks\/\d+$/);
    // Both re-auth confirms are present and submittable without any JavaScript.
    await expect(page.locator('form[action$="/rotate"] input[name="current_password"]')).toBeVisible();
    await expect(page.locator('form[action$="/delete"] input[name="current_password"]')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Save' })).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'integrations-webhook-detail-no-js');

    // The form= pairing still posts the config form with no JS involved.
    await page.locator('#webhook-config input[name="name"]').fill(`${name} renamed`);
    await page.getByRole('button', { name: 'Save' }).click();
    await expect(page.getByRole('status')).toContainText('Endpoint configuration saved.');
    await expect(page.locator('h2.admin-record-title')).toHaveText(`${name} renamed`);

    await page.goto('/admin/providers');
    await expectIntegrationsArea(page, 'Sign-in providers');
    await expect(page.getByRole('heading', { level: 2, name: 'Add an OIDC provider' })).toBeVisible();
    await expectNoDocumentOverflow(page);
    await shot(page, info, 'integrations-providers-no-js');
  } finally {
    await context.close();
  }
});
