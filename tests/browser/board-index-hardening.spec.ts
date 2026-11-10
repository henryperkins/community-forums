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

function contrastRatio(foreground: string, background: string): number {
  const luminance = (color: string) => {
    const [r, g, b] = color.match(/[\d.]+/g)!.slice(0, 3).map(value => {
      const channel = Number(value) / 255;
      return channel <= .04045 ? channel / 12.92 : ((channel + .055) / 1.055) ** 2.4;
    });
    return .2126 * r + .7152 * g + .0722 * b;
  };
  const a = luminance(foreground), b = luminance(background);
  return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
}

/** The actual rendered controls must remain legible, bounded and distinguishable. */
async function expectForumViewbar(page: Page, selector: string) {
  const controls = await page.locator(selector).evaluate(root => {
    const background = (element: Element | null): string => {
      for (let parent = element; parent; parent = parent.parentElement) {
        const color = getComputedStyle(parent).backgroundColor;
        if (color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') return color;
      }
      return getComputedStyle(document.body).backgroundColor;
    };
    return [...root.querySelectorAll('[data-directory-sort-option], [data-directory-peek-option]')].map(element => {
      const style = getComputedStyle(element), box = element.getBoundingClientRect();
      return {
        text: element.textContent?.trim(),
        selected: element.matches('[aria-current="true"], [aria-pressed="true"]'),
        width: box.width, height: box.height,
        foreground: style.color, background: background(element), adjacent: background(element.parentElement),
        border: style.borderTopColor, borderWidth: parseFloat(style.borderTopWidth),
        fontSize: parseFloat(style.fontSize), weight: Number(style.fontWeight),
      };
    });
  });
  expect(controls).toHaveLength(9);
  const selected = controls.filter(control => control.selected);
  expect(selected).toHaveLength(2);
  for (const control of controls) {
    expect(control.width, control.text).toBeGreaterThanOrEqual(43.5);
    expect(control.height, control.text).toBeGreaterThanOrEqual(43.5);
    expect(control.fontSize, control.text).toBeGreaterThanOrEqual(14);
    expect(control.borderWidth, control.text).toBeGreaterThan(0);
    expect(contrastRatio(control.foreground, control.background), `${control.text} text`).toBeGreaterThanOrEqual(4.5);
    expect(Math.max(contrastRatio(control.border, control.adjacent), contrastRatio(control.background, control.adjacent)), `${control.text} boundary`).toBeGreaterThanOrEqual(3);
  }
  for (const control of selected) {
    expect(control.weight, `${control.text} non-color selection cue`).toBeGreaterThan(controls.find(item => !item.selected)!.weight);
    expect(control.background, `${control.text} selected fill`).not.toBe(controls.find(item => !item.selected)!.background);
  }
  return controls;
}

async function expectForcedColorViewbar(page: Page, selector: string) {
  await page.emulateMedia({ forcedColors: 'active' });
  const controls = page.locator(selector).locator('[data-directory-sort-option], [data-directory-peek-option]');
  for (const control of await controls.all()) {
    await expect(control).toHaveCSS('border-top-style', 'solid');
    expect(await control.evaluate(element => parseFloat(getComputedStyle(element).borderTopWidth))).toBeGreaterThan(0);
  }
  await controls.first().focus();
  // Enter keyboard modality after the pointer opened the native disclosure.
  await page.keyboard.press('Tab');
  await page.keyboard.press('Shift+Tab');
  await expect(controls.first()).toBeFocused();
  await expect(controls.first()).toHaveCSS('outline-style', 'solid');
  expect(await controls.first().evaluate(element => parseFloat(getComputedStyle(element).outlineWidth))).toBeGreaterThanOrEqual(2);
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
  test(`${audience} desktop view controls retain readable Forum states`, async ({ page }) => {
    if (member) await signIn(page);
    await page.setViewportSize({ width: 1280, height: 844 });
    await page.goto('/?sort=category&peek=3');
    for (const theme of ['light', 'dark']) {
      await appearance(page, theme);
      writeJson(`desktop-${audience}-${theme}-controls.json`, await expectForumViewbar(page, '.directory-viewbar'));
      expectGeometry(await geometry(page));
      await capture(page, `desktop-${audience}-${theme}`);
    }
    await expectForcedColorViewbar(page, '.directory-viewbar');
    await capture(page, `desktop-${audience}-forced-colors`);
    await page.emulateMedia({ forcedColors: 'none' });
    await page.locator('.directory-viewbar [data-directory-sort-option=active]').click();
    await page.waitForURL(url => url.searchParams.get('sort') === 'active');
    await page.locator('.directory-viewbar [data-directory-peek-option="0"]').click();
    await page.waitForURL(url => url.searchParams.get('peek') === '0');
    await expect(page.locator('[data-directory-sort]')).toHaveAttribute('data-directory-sort', 'active');
    await expect(page.locator('[data-directory-peek]')).toHaveAttribute('data-directory-peek', '0');
  });

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
      writeJson(`sheet-${audience}-${theme}-controls.json`, await expectForumViewbar(page, '.directory-viewbar-mobile-panel'));
      await expect(last).toHaveCSS('text-decoration-line', 'underline');
      for (const label of await sheet.locator('.directory-viewbar__label').all()) {
        await expect(label).toHaveCSS('font-weight', '600');
        await expect(label).toHaveCSS('text-transform', 'none');
      }
      const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      writeJson(`sheet-axe-${audience}-${theme}.json`, results.violations);
      expect(results.violations).toEqual([]);
      await capture(page, `sheet-${audience}-${theme}`);
    }
    await expectForcedColorViewbar(page, '.directory-viewbar-mobile-panel');
    await capture(page, `sheet-${audience}-forced-colors`);
    await page.emulateMedia({ forcedColors: 'none' });
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
      for (const theme of ['light', 'dark']) {
        await appearance(native, theme);
        writeJson(`native-${audience}-${theme}-controls.json`, await expectForumViewbar(native, '.directory-viewbar-mobile-panel'));
        await capture(native, `native-${audience}-${theme}-view`);
      }
      await expectForcedColorViewbar(native, '.directory-viewbar-mobile-panel');
      await capture(native, `native-${audience}-forced-colors`);
      await native.emulateMedia({ forcedColors: 'none' });
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
