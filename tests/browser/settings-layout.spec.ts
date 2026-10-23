import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/settings-layout-2026-10-22');

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

async function register(page: Page, value: string) {
  await page.mouse.move(0, 0);
  await page.locator('html').evaluate((html, theme) => html.setAttribute('data-theme', theme), value);
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(document.getAnimations().map(animation => animation.finished.catch(() => {})));
  });
}

async function capture(page: Page, info: TestInfo, name: string) {
  const dir = path.join(evidence, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: path.join(dir, `${name}.png`), fullPage: true });
}

async function fits(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
  const outside = await page.locator('.settings-screen input:visible, .settings-screen select:visible, .settings-screen button:visible, .settings-screen summary:visible').evaluateAll(nodes => nodes.filter(node => {
    const box = node.getBoundingClientRect();
    return box.left < -1 || box.right > document.documentElement.clientWidth + 1;
  }).map(node => node.outerHTML.slice(0, 150)));
  expect(outside).toEqual([]);
}

test.describe('settings layout native workflows', () => {
  test.use({ javaScriptEnabled: false });
  test.beforeEach(() => fixture());

  test('profile pairs are plain labelled fields with ordered focus and retained validation', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/account');
    const group = page.locator('.custom-profile-fields');
    await expect(group).toHaveCSS('border-top-width', '0px');
    await expect(group.locator('.custom-profile-pair')).toHaveCount(3);
    await expect(group.locator('.row-bullet, .row-mark')).toHaveCount(0);
    const first = page.getByLabel('Label 1', { exact: true });
    await first.focus();
    await expect(first).toHaveCSS('outline-style', 'solid');
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Value 1', { exact: true })).toBeFocused();
    await first.fill('Reading');
    await page.getByLabel('Value 1', { exact: true }).fill('中 المجلس — long retained value');
    await page.getByLabel('Website', { exact: true }).fill('ftp://example.test');
    const [response] = await Promise.all([
      page.waitForNavigation(), page.getByRole('button', { name: 'Save profile', exact: true }).click(),
    ]);
    expect(response?.status()).toBe(422);
    await expect(page.getByLabel('Website', { exact: true })).toBeFocused();
    await expect(page.getByLabel('Value 1', { exact: true })).toHaveValue('中 المجلس — long retained value');
    for (const theme of ['light', 'dark']) {
      await register(page, theme);
      await fits(page);
      await capture(page, info, `profile-422-${theme}`);
    }
  });

  test('folder editors disclose by keyboard and reopen only the rejected native form', async ({ page }, info) => {
    await login(page);
    await page.goto('/settings/boards');
    const create = page.locator('form[action="/settings/board-folders"]');
    await create.locator('[name="name"]').fill('A'.repeat(80));
    await create.getByRole('button', { name: 'New folder', exact: true }).click();
    const manager = page.locator('.board-folder-card .org-manage');
    const rename = manager.locator('form[action$="/rename"]');
    await expect(manager).not.toHaveAttribute('open', '');
    await expect(rename).not.toBeVisible();
    await manager.locator('summary').focus();
    await page.keyboard.press('Enter');
    await expect(manager).toHaveAttribute('open', '');
    await rename.locator('[name="name"]').fill(' ');
    const [response] = await Promise.all([page.waitForNavigation(), rename.getByRole('button', { name: 'Rename folder' }).click()]);
    expect(response?.status()).toBe(422);
    await expect(manager).toHaveAttribute('open', '');
    await expect(manager.locator('.callout-danger')).toBeFocused();
    // Organization names are trimmed by the existing server validator.
    await expect(rename.locator('[name="name"]')).toHaveValue('');
    for (const theme of ['light', 'dark']) {
      await register(page, theme);
      await fits(page);
      await capture(page, info, `folder-open-422-${theme}`);
    }
    await rename.locator('[name="name"]').fill('Reference reading');
    await rename.getByRole('button', { name: 'Rename folder' }).click();
    await expect(manager).not.toHaveAttribute('open', '');
    await expect(page.locator('.board-folder-card .org-folder-name')).toHaveText('Reference reading');
  });

  test('feed opt-out stays visible while editing is disclosed and validation retains its board', async ({ page }, info) => {
    const data = fixture('inspect');
    await login(page);
    await page.goto('/settings/boards');
    const create = page.locator('form[action="/settings/saved-feeds"]');
    await create.locator('[name="name"]').fill('My reading');
    await create.locator('[name="board_id"]').selectOption(String(data.board_id));
    await create.getByRole('switch', { name: 'Digest', exact: true }).check();
    await create.getByRole('button', { name: 'Save feed', exact: true }).click();
    const manager = page.locator('.saved-feed-card .org-manage');
    await expect(manager).not.toHaveAttribute('open', '');
    await expect(page.getByRole('button', { name: 'Turn off digest', exact: true })).toBeVisible();
    await manager.locator('summary').click();
    const edit = manager.locator('form').filter({ has: page.locator('input[name="name"]') });
    await edit.locator('[name="name"]').fill(' ');
    const [response] = await Promise.all([page.waitForNavigation(), edit.getByRole('button', { name: 'Update feed' }).click()]);
    expect(response?.status()).toBe(422);
    await expect(manager).toHaveAttribute('open', '');
    await expect(manager.locator('.callout-danger')).toBeFocused();
    await expect(edit.locator('[name="board_id"]')).toHaveValue(String(data.board_id));
    await expect(edit.getByRole('switch', { name: 'Digest', exact: true })).toBeChecked();
    await fits(page);
    await capture(page, info, 'feed-open-422');
    await page.goto('/settings/boards');
    await expect(manager).not.toHaveAttribute('open', '');
    await page.getByRole('button', { name: 'Turn off digest', exact: true }).click();
    expect(Number(fixture('inspect').saved_feeds[0].digest_enabled)).toBe(0);
  });
});

test('settings layout remains accessible and grouped in both registers', async ({ page }, info) => {
  test.setTimeout(90_000);
  fixture();
  fixture('delivery-on');
  await login(page);
  const token = await page.locator('input[name="_token"]').first().inputValue();
  expect((await page.request.post('/settings/board-folders', { form: { _token: token, name: 'Reference reading' }, maxRedirects: 0 })).status()).toBe(303);
  const metrics: Array<Record<string, unknown>> = [];
  for (const route of ['account', 'boards', 'notifications', 'privacy']) {
    await page.goto(`/settings/${route}`);
    if (route === 'boards') await page.locator('.org-manage > summary').click();
    for (const theme of ['light', 'dark']) {
      await register(page, theme);
      await fits(page);
      const result = await new AxeBuilder({ page }).include('.settings-screen')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(result.violations.map(({ id, nodes }) => ({ id, targets: nodes.map(node => node.target) }))).toEqual([]);
      const switches = await page.locator('.settings-pane .switchline').evaluateAll(nodes => nodes.map(node => {
        const track = node.querySelector('input')!.getBoundingClientRect();
        const text = node.querySelector('.switch-text')!.getBoundingClientRect();
        return { display: getComputedStyle(node).display, height: node.getBoundingClientRect().height, separation: text.left - track.right };
      }));
      for (const control of switches) {
        expect(control.display).toBe('flex');
        expect(control.height).toBeGreaterThanOrEqual(44);
        expect(control.separation).toBeGreaterThan(0);
      }
      const boardActionOffsets = await page.locator('.board-pref-actions').evaluateAll(groups => groups.map(group => {
        const actions = Array.from(group.querySelectorAll('button')).map(button => button.getBoundingClientRect());
        return Math.abs(actions[0].top - actions[1].top);
      }));
      expect(boardActionOffsets.every(offset => offset <= 1)).toBe(true);
      const measured = await page.locator('.settings-screen').evaluate(screen => {
        const pane = screen.querySelector('.settings-pane')!;
        const fields = screen.querySelector('.field-grid');
        const organization = screen.querySelector('.personal-org-grid');
        return {
          rootSize: parseFloat(getComputedStyle(document.documentElement).fontSize),
          taskGap: parseFloat(getComputedStyle(pane).rowGap),
          fieldGap: fields ? parseFloat(getComputedStyle(fields).rowGap) : null,
          organizationGap: organization ? parseFloat(getComputedStyle(organization).rowGap) : null,
          gridRows: getComputedStyle(screen.querySelector('.settings')!).gridTemplateRows,
        };
      });
      expect(measured.taskGap).toBe(measured.rootSize * 1.5);
      if (measured.fieldGap !== null) expect(measured.fieldGap).toBe(measured.rootSize);
      if (measured.organizationGap !== null) expect(measured.organizationGap).toBe(measured.rootSize * 2);
      metrics.push({ route, theme, ...measured, switches, boardActionOffsets });
      if (route !== 'privacy') await capture(page, info, `${route}-${theme}`);
    }
  }
  const dir = path.join(evidence, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, 'layout-metrics.json'), JSON.stringify(metrics, null, 2) + '\n');
});

test('settings layout reflows without phantom grid rows at intermediate widths and enlarged text', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop project drives the explicit width matrix');
  fixture();
  await login(page);
  for (const width of [320, 861, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const route of ['account', 'boards', 'notifications']) {
      await page.goto(`/settings/${route}`);
      for (const theme of ['light', 'dark']) {
        await register(page, theme);
        if (width === 861) await page.locator('html').evaluate(html => html.style.fontSize = '32px');
        await fits(page);
        const tracks = await page.locator('.settings').evaluate(node => getComputedStyle(node).gridTemplateRows.split(' ').length);
        expect(tracks).toBeLessThanOrEqual(2);
        await capture(page, info, `reflow-${width}-${route}-${theme}`);
      }
    }
  }
});
