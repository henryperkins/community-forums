import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/settings-hardening-2026-10-22');

function fixture(command = 'reset') {
  return JSON.parse(execFileSync('php', ['tests/browser/account-settings-repairs-fixture.php', command], {
    cwd: root, env: { ...process.env, APP_ENV: 'test', MAIL_DRIVER: 'array' }, encoding: 'utf8',
  }));
}

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('settings-repair@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await expect(page).not.toHaveURL(/\/login/);
}

async function theme(page: Page, value: string) {
  await page.locator('html').evaluate((html, theme) => html.setAttribute('data-theme', theme), value);
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})));
  });
}

async function capture(page: Page, info: TestInfo, name: string) {
  const dir = path.join(evidence, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: true });
}

async function fits(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
  const offenders = await page.locator('.settings-pane input:visible, .settings-pane select:visible, .settings-pane button:visible, .settings-pane textarea:visible').evaluateAll(nodes => nodes.filter(node => {
    const box = node.getBoundingClientRect();
    return box.right > document.documentElement.clientWidth + 1 || box.left < -1;
  }).map(node => node.outerHTML.slice(0, 160)));
  expect(offenders).toEqual([]);
}

async function accessible(page: Page, nativeWorkflow = false) {
  // Axe needs asynchronous JavaScript. Audit a snapshot of the native DOM in
  // a separate context, blocking application scripts; keep the actual workflow
  // and its POST/validation history entirely script-disabled.
  const auditContext = nativeWorkflow ? await page.context().browser()!.newContext({
    javaScriptEnabled: true,
    viewport: page.viewportSize(),
    colorScheme: await page.evaluate(() => matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
  }) : null;
  try {
    let auditPage = page;
    if (auditContext) {
      const html = await page.content();
      auditPage = await auditContext.newPage();
      await auditPage.route('**/*', route => {
        const request = route.request();
        if (request.isNavigationRequest() && request.frame() === auditPage.mainFrame()) {
          return route.fulfill({ contentType: 'text/html', body: html });
        }
        return request.resourceType() === 'script' ? route.abort() : route.continue();
      });
      await auditPage.goto(page.url());
      await auditPage.evaluate(() => document.fonts.ready);
      await expect(auditPage.locator('html')).not.toHaveClass(/\bhas-js\b/);
    }
    const result = await new AxeBuilder({ page: auditPage }).include('.settings-screen')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(result.violations.filter(v => ['serious', 'critical'].includes(v.impact ?? ''))).toEqual([]);
  } finally {
    await auditContext?.close();
  }
}

test.describe('settings resilience with native forms', () => {
  test.use({ javaScriptEnabled: false });
  test.beforeEach(() => fixture());

  test('custom labels persist, invalid profile text is retained and keyboard focus is clear', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/account');
    await page.getByLabel('Label 1', { exact: true }).fill('A long public label');
    await page.getByLabel('Value 1', { exact: true }).fill('中 المجلس — a retained profile value');
    await page.getByLabel('Website', { exact: true }).fill('ftp://example.test');
    const [response] = await Promise.all([
      page.waitForNavigation(), page.getByRole('button', { name: 'Save profile', exact: true }).click(),
    ]);
    expect(response?.status()).toBe(422);
    await expect(page.getByLabel('Website', { exact: true })).toBeFocused();
    await expect(page.getByLabel('Value 1', { exact: true })).toHaveValue('中 المجلس — a retained profile value');
    for (const register of ['light', 'dark']) {
      await theme(page, register);
      await fits(page);
      await accessible(page, true);
      const input = page.getByLabel('Label 1', { exact: true });
      await input.focus();
      await expect(input).toHaveCSS('outline-style', 'solid');
      await capture(page, info, `profile-422-${register}`);
    }
  });

  test('organization 422 alerts span the form and keep long values and digest choices', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/boards');
    const create = page.locator('form[action="/settings/board-folders"]');
    await create.locator('[name="name"]').fill('A'.repeat(80));
    await create.getByRole('button', { name: 'New folder', exact: true }).click();
    const rename = page.locator('form[action$="/rename"]').first();
    await page.locator('.org-manage').filter({ has: rename }).locator('summary').click();
    await rename.locator('[name="name"]').fill(' ');
    const [response] = await Promise.all([page.waitForNavigation(), rename.getByRole('button', { name: 'Rename folder' }).click()]);
    expect(response?.status()).toBe(422);
    const alert = page.locator('.settings-pane .callout-danger');
    await expect(alert).toBeFocused();
    for (const register of ['light', 'dark']) {
      await theme(page, register);
      await fits(page);
      const geometry = await rename.evaluate(form => {
        const folder = form.closest('.org-folder')!.getBoundingClientRect();
        const error = form.previousElementSibling!.getBoundingClientRect();
        const box = form.getBoundingClientRect();
        return { width: box.width, folder: folder.width, sameLeft: Math.abs(box.left - error.left), top: box.top, errorBottom: error.bottom };
      });
      expect(geometry.width).toBeGreaterThan(geometry.folder * 0.7);
      expect(geometry.sameLeft).toBeLessThanOrEqual(1);
      expect(geometry.top).toBeGreaterThanOrEqual(geometry.errorBottom);
      await accessible(page, true);
      await capture(page, info, `organization-422-${register}`);
    }
    const feed = page.locator('form[action="/settings/saved-feeds"]');
    await feed.locator('[name="name"]').fill(' ');
    await feed.getByRole('switch', { name: 'Digest', exact: true }).check();
    await feed.getByRole('button', { name: 'Save feed', exact: true }).click();
    await expect(feed.getByRole('switch', { name: 'Digest', exact: true })).toBeChecked();
    await expect(page.locator('#feed-create-errors')).toBeFocused();
  });

  test('board state and subscription delivery stay operable with 44px labelled targets', async ({ page }, info) => {
    const data = fixture('delivery-on');
    await login(page);
    await page.goto('/settings/boards');
    const mute = page.getByRole('button', { name: 'Mute Settings evidence board', exact: true });
    await expect(mute).toHaveAttribute('aria-pressed', 'false');
    await mute.click();
    await expect(mute).toHaveAttribute('aria-pressed', 'true');
    expect((await mute.boundingBox())!.height).toBeGreaterThanOrEqual(44);
    await page.goto('/settings/notifications');
    const subscription = page.locator(`form[action="/settings/notifications/subscriptions/${data.subscription.id}"]`).first();
    await subscription.getByRole('switch', { name: 'Email notifications', exact: true }).uncheck();
    await subscription.getByRole('button', { name: /^Save subscription for / }).click();
    await expect(subscription.getByRole('switch', { name: 'Email notifications', exact: true })).not.toBeChecked();
    const sizes = await page.locator('.settings-pane .switchline').evaluateAll(nodes => nodes.map(n => n.getBoundingClientRect().height));
    expect(sizes.every(height => height >= 44)).toBe(true);
    for (const register of ['light', 'dark']) {
      await theme(page, register);
      await fits(page);
      await expect(page.locator('select[name="timezone"]')).toHaveCSS('appearance', 'auto');
      await accessible(page, true);
      await capture(page, info, `subscriptions-${register}`);
    }
  });

  test('digest errors focus the invalid control and native navigation names Appeals', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/notifications');
    // Native select options cannot express malformed data; exercise the same
    // real navigation with the browser's form submission API.
    await page.locator('form[action="/settings/notifications"]').evaluate(form => {
      const input = document.createElement('input');
      input.name = 'timezone'; input.value = 'Invalid/Zone';
      form.querySelector('[name="timezone"]')!.replaceWith(input);
      (form as HTMLFormElement).requestSubmit();
    });
    await page.waitForLoadState('load');
    await expect(page.locator('select[name="timezone"]')).toBeFocused();
    await expect(page.locator('select[name="timezone"]')).toHaveAttribute('aria-describedby', 'err-timezone');
    await capture(page, info, 'digest-422');
    await page.goto('/appeals');
    const chooser = page.locator('[data-settings-mobile-nav]');
    if (await chooser.isVisible()) {
      await expect(chooser.locator('summary')).toHaveText('Settings: Appeals');
      await chooser.locator('summary').focus();
      await page.keyboard.press('Enter');
    }
    await expect(page.getByRole('navigation', { name: 'Settings sections' }).getByRole('link', { name: 'Appeals', exact: true })).toHaveAttribute('aria-current', 'page');
  });
});

test('tablet widths and enlarged text adapt to pane width in both registers', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop project drives the explicit tablet/reflow matrix');
  test.setTimeout(90_000);
  fixture();
  await login(page);
  for (const width of [320, 761, 861, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const url of ['/settings/account', '/settings/boards', '/settings/appearance', '/settings/security']) {
      await page.goto(url);
      for (const register of ['light', 'dark']) {
        await theme(page, register);
        if (width === 761) await page.locator('html').evaluate(html => html.style.fontSize = '32px');
        await fits(page);
        await capture(page, info, `reflow-${width}-${url.split('/').pop()}-${register}`);
      }
    }
  }
});

test('dark-system and forced-colour fields retain native affordances and keyboard focus', async ({ page }, info) => {
  fixture();
  await login(page);
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/settings/privacy');
  await theme(page, 'system');
  await expect(page.locator('select').first()).toHaveCSS('background-image', 'none');
  await accessible(page);
  await page.emulateMedia({ forcedColors: 'active' });
  await page.locator('.settings-pane select').first().focus();
  await expect(page.locator('.settings-pane select').first()).toHaveCSS('appearance', 'auto');
  await expect(page.locator('.settings-pane select').first()).toHaveCSS('outline-style', 'solid');
  await fits(page);
  await capture(page, info, 'forced-colours');
});
