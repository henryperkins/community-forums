import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/board-index-2026-10-12');
test.use({
  browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium',
  reducedMotion: 'reduce',
});

function fixture(code: string, input: unknown = {}) {
  return execFileSync('php', ['-r', `require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd().'/.env');
$config=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php');
if (!preg_match('/^retroboards_(?:e2e|board_index_fix_e2e)(?:_[a-z0-9_]+)?$/',$config->get('db.database'))) throw new RuntimeException('Use an isolated browser database.');
$db=new \\App\\Core\\Database($config->get('db'));
$input=json_decode(file_get_contents('php://stdin'),true,flags:JSON_THROW_ON_ERROR);
${code}`], { cwd: ROOT, env: process.env, input: JSON.stringify(input) }).toString().trim();
}

function writeJson(name: string, value: unknown) {
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, name), JSON.stringify(value, null, 2) + '\n');
}

async function capture(page: Page, name: string) {
  fs.mkdirSync(OUT, { recursive: true });
  await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage: true, animations: 'disabled' });
}

async function signIn(page: Page) {
  await page.goto('/login');
  await page.locator('[name=email]').fill('alice@retro.test');
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
  if (await page.locator('body').getAttribute('data-tour') === '1') {
    await page.locator('.tour-popover').getByRole('button', { name: 'Skip', exact: true }).click();
    await expect(page.locator('.tour-popover')).toBeHidden();
  }
}

async function appearance(page: Page, theme: string, enlarged = false) {
  await page.evaluate(({ theme, enlarged }) => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.dataset.fontSize = enlarged ? 'large' : 'medium';
    document.documentElement.style.fontSize = enlarged ? '36px' : '';
    return document.fonts.ready;
  }, { theme, enlarged });
}

async function geometry(page: Page) {
  return page.evaluate(() => {
    const main = document.querySelector('main')!;
    const names = [...document.querySelectorAll<HTMLElement>('.forum-directory__board-name')];
    const controls = [...document.querySelectorAll<HTMLElement>('.forum-directory__board-row > a, .forum-directory__peek a, .directory-viewbar__choices a, .directory-viewbar__choices button, .directory-viewbar__peek a, .directory-viewbar__peek button')]
      .filter(e => e.getClientRects().length && !e.closest('details:not([open])'));
    return {
      viewport: innerWidth,
      main: { width: main.clientWidth, scroll: main.scrollWidth },
      names: names.map(e => ({ text: e.textContent, width: e.clientWidth, scroll: e.scrollWidth })),
      controls: controls.map(e => { const r = e.getBoundingClientRect(); return { text: e.textContent?.trim(), width: r.width, height: r.height }; }),
    };
  });
}

function expectGeometry(metrics: Awaited<ReturnType<typeof geometry>>) {
  expect(metrics.main.scroll).toBeLessThanOrEqual(metrics.main.width + 1);
  for (const name of metrics.names) expect(name.scroll, name.text ?? '').toBeLessThanOrEqual(name.width + 1);
  for (const control of metrics.controls) {
    expect(control.width, control.text).toBeGreaterThanOrEqual(43.5);
    expect(control.height, control.text).toBeGreaterThanOrEqual(43.5);
  }
}

test('valid long board names and touch targets fit every directory breakpoint', async ({ page }) => {
  test.setTimeout(90_000);
  const original = fixture("echo $db->fetchValue('SELECT name FROM boards WHERE slug=?',['general']);");
  const measurements: unknown[] = [];
  const names = [
    'Community projects, events, and introductions from around the valley',
    'W'.repeat(80),
    Array.from('資料の共有と地域の取り組み'.repeat(6)).slice(0, 80).join(''),
  ];
  try {
    for (const [index, name] of names.entries()) {
      fixture("if (mb_strlen($input['name'])>80) throw new RuntimeException('Invalid board fixture.'); $db->run('UPDATE boards SET name=? WHERE slug=?',[$input['name'],'general']);", { name });
      const widths = index === 0 ? [320, 390, 760, 861, 1024, 1440] : [320, 861, 1440];
      for (const theme of ['light', 'dark']) {
        for (const width of widths) {
          await page.setViewportSize({ width, height: 900 });
          await page.goto('/?sort=category&peek=3');
          await appearance(page, theme);
          const metrics = await geometry(page);
          measurements.push({ name, theme, ...metrics });
          writeJson('geometry.json', measurements);
          expectGeometry(metrics);
          if (index === 0 && [390, 1440].includes(width)) await capture(page, `directory-${width}-${theme}`);
        }
        if (index === 0) {
          for (const width of [320, 861]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto('/?sort=category&peek=3');
            await appearance(page, theme, true);
            const metrics = await geometry(page);
            measurements.push({ name, theme, enlarged: true, ...metrics });
            writeJson('geometry.json', measurements);
            expectGeometry(metrics);
            if (width === 320) await capture(page, `directory-320-${theme}-large-200`);
          }
        }
      }
    }
  } finally {
    fixture("$db->run('UPDATE boards SET name=? WHERE slug=?',[$input['name'],'general']);", { name: original });
  }
});

test('the viewing header does not cover choices in landscape or with enlarged text', async ({ page }) => {
  for (const { width, height, enlarged } of [
    { width: 620, height: 320, enlarged: false },
    { width: 320, height: 844, enlarged: true },
  ]) {
    await page.setViewportSize({ width, height });
    await page.goto('/?sort=category&peek=3');
    await appearance(page, 'dark', enlarged);
    const sheet = page.locator('[data-directory-view-sheet]');
    const summary = sheet.locator('> summary');
    const panel = sheet.locator('.directory-viewbar-mobile-panel');
    await summary.click();
    const headerBox = (await summary.boundingBox())!;
    const panelBox = (await panel.boundingBox())!;
    expect(headerBox.y).toBeGreaterThanOrEqual(0);
    expect(headerBox.y + headerBox.height).toBeLessThanOrEqual(panelBox.y + 1);
    expect(panelBox.y + panelBox.height).toBeLessThanOrEqual(height + 1);
    for (const control of await panel.locator('a, button').all()) {
      await control.focus();
      const isUncovered = await control.evaluate(element => {
        const rect = element.getBoundingClientRect();
        return rect.top >= 0 && rect.bottom <= innerHeight + 1
          && element.contains(document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2));
      });
      expect(isUncovered, await control.innerText()).toBeTruthy();
    }
    await capture(page, enlarged ? 'sheet-large-200' : 'sheet-landscape');
    await page.keyboard.press('Escape');
    await expect(summary).toBeFocused();
  }
});

for (const member of [false, true]) {
  const audience = member ? 'member' : 'guest';
  test(`${audience} viewing sheet contains focus, dismisses and hands focus across widths`, async ({ page }) => {
    if (member) await signIn(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/?sort=category&peek=3');
    const sheet = page.locator('[data-directory-view-sheet]');
    const shell = page.locator('[data-directory-view-shell]');
    const summary = sheet.locator('> summary');
    const last = sheet.getByRole('link', { name: 'Row appearance settings' });

    await summary.focus();
    await page.keyboard.press('Enter');
    await expect(shell).toHaveAttribute('role', 'dialog');
    await expect(shell).toHaveAttribute('aria-modal', 'true');
    await expect(sheet.locator('[data-directory-sort-option=category]')).toBeFocused();
    await expect(page.locator('.forum-bar')).toHaveAttribute('inert', '');
    await expect(page.locator('.forum-directory__groups')).toHaveAttribute('inert', '');
    await last.focus();
    await page.keyboard.press('Tab');
    await expect(summary).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(last).toBeFocused();
    expectGeometry(await geometry(page));

    for (const theme of ['light', 'dark']) {
      await appearance(page, theme);
      const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      writeJson(`sheet-axe-${audience}-${theme}.json`, results.violations);
      expect(results.violations).toEqual([]);
    }
    await capture(page, `sheet-${audience}-dark`);
    await page.keyboard.press('Escape');
    await expect(sheet).not.toHaveAttribute('open', '');
    await expect(summary).toBeFocused();
    await expect(page.locator('.forum-bar')).not.toHaveAttribute('inert', '');
    await expect(page.locator('.forum-directory__groups')).not.toHaveAttribute('inert', '');

    await summary.click();
    await page.mouse.click(5, 200);
    await expect(sheet).not.toHaveAttribute('open', '');
    await expect(summary).toBeFocused();

    await summary.focus();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Tab');
    await expect(sheet.locator('[data-directory-sort-option=active]')).toBeFocused();
    await page.setViewportSize({ width: 1440, height: 900 });
    await expect(sheet).not.toHaveAttribute('open', '');
    await expect(shell).not.toHaveAttribute('aria-modal', 'true');
    await expect(page.locator('.directory-viewbar [data-directory-sort-option=active]')).toBeFocused();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(summary).toBeFocused();

    await summary.click();
    await sheet.locator('[data-directory-sort-option=active]').click();
    await page.waitForURL(url => url.searchParams.get('sort') === 'active');
    await expect(page.locator('[data-directory-sort]')).toHaveAttribute('data-directory-sort', 'active');
  });

  test(`${audience} view remains an inline disclosure and submits with no JavaScript`, async ({ browser, page, baseURL }) => {
    if (member) await signIn(page);
    const context = await browser.newContext({
      baseURL, javaScriptEnabled: false, reducedMotion: 'reduce',
      viewport: { width: 320, height: 844 },
      ...(member ? { storageState: await page.context().storageState() } : {}),
    });
    try {
      const native = await context.newPage();
      await native.goto('/?sort=category&peek=3');
      const sheet = native.locator('[data-directory-view-sheet]');
      const summary = sheet.locator('> summary');
      await summary.click();
      await expect(sheet).toHaveAttribute('open', '');
      await expect(sheet.locator('.directory-viewbar-mobile-panel')).toHaveCSS('position', 'static');
      expectGeometry(await geometry(native));
      await capture(native, `native-${audience}-view`);
      await summary.click();
      await expect(sheet).not.toHaveAttribute('open', '');
      await summary.click();
      await sheet.locator('[data-directory-sort-option=active]').click();
      await native.waitForURL(url => url.searchParams.get('sort') === 'active');
      await expect(native.locator('[data-directory-sort]')).toHaveAttribute('data-directory-sort', 'active');
      if (member) {
        await native.goto('/');
        await expect(native.locator('[data-directory-sort]')).toHaveAttribute('data-directory-sort', 'active');
      }
    } finally { await context.close(); }
  });
}

test('the directory removes unused script requests while creation and sign-in retain their scripts', async ({ page }) => {
  await page.goto('/');
  const payload = await page.evaluate(() => performance.getEntriesByType('resource')
    .filter(e => e.name.endsWith('.js'))
    .map(e => ({ name: e.name.split('/').pop(), decodedBytes: (e as PerformanceResourceTiming).decodedBodySize })));
  writeJson('script-payload.json', payload);
  expect(payload.some(e => e.name?.startsWith('composer-') || e.name?.startsWith('passkeys-'))).toBeFalsy();
  await page.goto('/login');
  await expect(page.locator('script[src*="/passkeys-"]')).toHaveCount(1);
  await signIn(page);
  await page.goto('/');
  await expect(page.locator('script[src*="/composer-"], script[src*="/passkeys-"]')).toHaveCount(0);
  await page.locator('[data-create-menu] > summary').click();
  await page.getByRole('link', { name: 'New topic', exact: true }).click();
  await page.waitForURL(url => url.pathname === '/compose');
  await expect(page.locator('script[src*="/composer-"]')).toHaveCount(1);
  await expect(page.locator('form.composer')).toBeVisible();
});
