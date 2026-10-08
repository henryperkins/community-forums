import { test, expect, type Browser, type BrowserContext, type Locator, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/inbox-controls-polish-2026-10-07');
const TOPIC = 'Share your favourite keyboard shortcuts';
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

test.beforeAll(async ({ request }) => {
  const manifest = JSON.parse(fs.readFileSync(path.join(ROOT, 'config/assets.json'), 'utf8'));
  const served = [];
  for (const entry of ['app.css', 'app.js']) {
    const url = manifest.urls[entry];
    const response = await request.get(url);
    expect(response.status(), `${entry} must serve the recorded build`).toBe(200);
    const body = await response.body();
    const sha256 = createHash('sha256').update(body).digest('hex');
    expect(sha256, `${entry} must match its manifest fingerprint`).toBe(manifest.files[url].sha256);
    expect(body.toString('utf8')).toContain(entry === 'app.css' ? 'inbox-compact-menu' : 'data-inbox-help-dialog');
    served.push({ entry, url, sha256 });
  }
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'served-assets.json'), JSON.stringify({ version: manifest.version, served }, null, 2));
});

test.beforeEach(() => {
  if (!/^retroboards_e2e_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '')) {
    throw new Error('Use an explicit private retroboards_e2e_* database for Inbox control evidence.');
  }
  execFileSync('php', ['tests/browser/member-surfaces-fixture.php'], { cwd: ROOT, env: process.env });
  // Each journey starts from visible, unread, starred sample topics. Clearing
  // through the repository also resets the persistent manual-hide state.
  execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    \\App\\Core\\Env::load(getcwd() . '/.env');
    $config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
    $db = new \\App\\Core\\Database($config->get('db'));
    $alice = (new \\App\\Repository\\UserRepository($db))->findByUsername('alice');
    $state = new \\App\\Repository\\ThreadUserRepository($db);
    foreach ($db->fetchAll('SELECT id FROM threads') as $topic) {
      $state->setSnooze((int) $alice['id'], (int) $topic['id'], null);
    }
    $db->run("UPDATE threads SET status = 'needs_answer' WHERE title = :title", ['title' => '${TOPIC}']);
  `], { cwd: ROOT, env: process.env });
});

function scopeMenu(page: Page) {
  return page.locator('[data-inbox-scope-menu]:visible, .inbox-compact-menu:visible');
}

function sortMenu(page: Page) {
  return page.locator('.inbox-sort-menu:visible, .inbox-compact-menu:visible');
}

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function appearance(page: Page, theme: string, large: boolean) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="theme"][value="${theme}"]`).check({ force: true });
  await page.locator('select[name="font_size"]').selectOption(large ? 'large' : 'medium');
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

async function capture(page: Page, project: string, name: string) {
  const directory = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', project);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), animations: 'disabled' });
}

async function targetAtLeast44(target: Locator) {
  const box = await target.boundingBox();
  expect(box, await target.getAttribute('aria-label') ?? await target.textContent() ?? 'control').not.toBeNull();
  expect(box!.height).toBeGreaterThanOrEqual(44);
  expect(box!.width).toBeGreaterThanOrEqual(44);
}

async function contained(page: Page, panel: Locator) {
  await expect(panel).toBeVisible();
  const box = (await panel.boundingBox())!;
  const viewport = page.viewportSize()!;
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(viewport.width + 1);
  expect(box.y).toBeGreaterThanOrEqual(0);
  expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 1);
  expect(await panel.evaluate(element => element.scrollWidth - element.clientWidth)).toBeLessThanOrEqual(1);
}

async function toolbarFits(page: Page) {
  const row = page.locator('[data-subheader]');
  const choices = await page.locator('.inbox-compact-menu').isVisible()
    ? ['.inbox-compact-menu > summary']
    : ['[data-inbox-scope-menu] > summary', '.inbox-sort-menu > summary'];
  const selectors = ['h1', ...choices, '.inbox-actions > summary', '[data-create-trigger]'];
  const geometry = await row.evaluate((element, selectors) => {
    const bounds = element.getBoundingClientRect();
    return {
      left: bounds.left, right: bounds.right,
      client: element.clientWidth, scroll: element.scrollWidth,
      viewport: document.documentElement.clientWidth, document: document.documentElement.scrollWidth,
      controls: selectors.map(selector => {
        const control = element.querySelector(selector)!;
        const box = control.getBoundingClientRect();
        return { selector, x: box.left, right: box.right, y: box.top, bottom: box.bottom, height: box.height };
      }),
    };
  }, selectors);
  expect(geometry.document, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.viewport);
  expect(geometry.scroll, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.client + 1);
  expect(Math.min(...geometry.controls.map(box => box.bottom)) - Math.max(...geometry.controls.map(box => box.y)), JSON.stringify(geometry)).toBeGreaterThan(1);
  for (const [index, box] of geometry.controls.entries()) {
    expect(box.x, box.selector).toBeGreaterThanOrEqual(geometry.left - 1);
    expect(box.right, box.selector).toBeLessThanOrEqual(geometry.right + 1);
    if (index) expect(box.x, `${box.selector} overlaps previous control`).toBeGreaterThanOrEqual(geometry.controls[index - 1].right - 1);
    if (box.selector !== 'h1') expect(box.height, box.selector).toBeGreaterThanOrEqual(44);
  }
  await expect(row.locator('[data-inbox-unread-count]')).toBeVisible();
  await expect(page.locator('[data-inbox-current-count]:visible')).toBeVisible();
  await expect(scopeMenu(page).locator(':scope > summary')).toHaveAccessibleName(/Needs Answer/);
  await expect(sortMenu(page).locator(':scope > summary')).toHaveAccessibleName(/Most commended|most commended/);
  for (const selector of choices) {
    await expect(row.locator(selector).locator('svg').last()).toBeVisible();
  }
}

function topicRow(page: Page, id?: string) {
  return id ? page.locator(`[data-inbox-row][data-thread-id="${id}"]`)
    : page.locator('[data-inbox-row]').filter({ has: page.getByRole('link', { name: TOPIC, exact: true }) });
}

async function rowMenu(row: Locator, native = false) {
  const menu = row.locator('[data-inbox-row-menu]');
  await menu.locator(':scope > summary').click();
  const panel = menu.locator('.thread-row-menu-panel');
  if (native) {
    expect(await panel.evaluate(element => getComputedStyle(element).position), 'Native panels must expand the topic in normal flow').toBe('static');
    await panel.scrollIntoViewIfNeeded();
    await panel.evaluate(element => {
      const owner = element.closest('[data-inbox-list]')!;
      const bounds = owner.getBoundingClientRect();
      const panel = element.getBoundingClientRect();
      const ceiling = Math.max(0, bounds.top);
      const floor = Math.min(innerHeight, bounds.bottom);
      if (panel.bottom > floor) owner.scrollTop += Math.ceil(panel.bottom - floor) + 1;
      else if (panel.top < ceiling) owner.scrollTop -= Math.ceil(ceiling - panel.top) + 1;
    });
  }
  return panel;
}

test('long scope and order labels preserve one row, counts and readable content across constrained layouts', async ({ page }, info) => {
  test.setTimeout(180000);
  await login(page);
  const widths = info.project.name === 'mobile' ? [320, 393] : [861, 1440];
  for (const theme of ['light', 'dark']) {
    for (const large of [false, true]) {
      await appearance(page, theme, large);
      for (const width of widths) {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/inbox?scope=needs_answer&order=commended');
        await page.evaluate(() => document.fonts.ready);
        await capture(page, info.project.name, `closed-${width}-${theme}-${large ? 'large' : 'default'}`);
        await toolbarFits(page);
        const row = topicRow(page);
        await expect(row).toBeVisible();
        const rowBox = (await row.boundingBox())!;
        const contentBox = (await row.locator('.thread-row-main').boundingBox())!;
        if (width < 400) expect(contentBox.width, 'Topic copy must retain useful width beside its controls').toBeGreaterThan(rowBox.width * 0.6);
        await targetAtLeast44(row.locator('[data-inbox-row-menu] > summary'));
        await targetAtLeast44(row.locator('.star-toggle'));
        await expect(page.locator('.inbox-select-all')).toContainText('Select all on this page');
      }
    }
  }
});

test('shared panels stay contained, expose recovery early and use consistent touch targets and dismissal', async ({ page }, info) => {
  test.setTimeout(180000);
  await login(page);
  const widths = info.project.name === 'mobile' ? [320, 393] : [861, 1440];
  for (const theme of ['light', 'dark']) {
    await appearance(page, theme, true);
    for (const width of widths) {
      await page.setViewportSize({ width, height: 844 });
      await page.goto('/inbox?scope=starred&order=commended');
      const compact = await page.locator('.inbox-compact-menu').isVisible();
      const menus: Array<[string, string]> = [
        ...(compact ? [['.inbox-compact-menu', '.inbox-menu-panel']] : [
          ['[data-inbox-scope-menu]', '.inbox-scope-menu-panel'],
          ['.inbox-sort-menu', '.inbox-menu-panel'],
        ]) as Array<[string, string]>,
        ['[data-create-menu]', '.create-menu-panel'],
      ];
      for (const [selector, panelSelector] of menus) {
        const menu = page.locator(selector);
        const trigger = menu.locator(':scope > summary');
        await trigger.focus();
        await page.keyboard.press('Enter');
        const panel = menu.locator(panelSelector);
        await expect(panel).toBeVisible();
        await capture(page, info.project.name, `menu-${selector.includes('compact') ? 'view' : selector.includes('scope') ? 'scope' : selector.includes('sort') ? 'sort' : 'create'}-${width}-${theme}`);
        await contained(page, panel);
        for (const action of await panel.getByRole('link').all()) await targetAtLeast44(action);
        if (selector === '[data-inbox-scope-menu]' || selector === '.inbox-compact-menu') {
          const recovery = panel.getByRole('link', { name: /^Snoozed/ });
          const recoveryBox = (await recovery.boundingBox())!;
          const panelBox = (await panel.boundingBox())!;
          expect(recoveryBox.y + recoveryBox.height, 'Snoozed should be discoverable before scrolling the scope menu').toBeLessThanOrEqual(panelBox.y + panelBox.height);
        }
        await page.keyboard.press('Escape');
        await expect(trigger).toBeFocused();
        await expect(panel).toBeHidden();
      }
      const row = topicRow(page);
      const panel = await rowMenu(row);
      await contained(page, panel);
      await expect(panel.getByRole('button', { name: 'Til tomorrow', exact: true })).toHaveCount(1);
      await expect(panel.getByRole('switch', { name: 'Show in Inbox', exact: true })).toHaveAttribute('aria-checked', 'true');
      for (const action of await panel.locator('button').all()) await targetAtLeast44(action);
      await expect(panel).not.toContainText(/Later today|Monday|Next week/);
      await capture(page, info.project.name, `row-menu-${width}-${theme}`);
      await page.keyboard.press('Escape');
      await expect(row.locator('[data-inbox-row-menu] > summary')).toBeFocused();

      const selections = page.locator('[data-inbox-select]');
      await selections.first().check();
      await expect(page.locator('[data-inbox-select-all]')).toHaveJSProperty('indeterminate', true);
      await expect(page.locator('[data-inbox-selection-label]')).toHaveText('1 selected');
      const sweep = page.locator('[data-inbox-sweep]');
      const bulkMenu = sweep.locator('[data-inbox-bulk-menu]');
      await bulkMenu.locator(':scope > summary').click();
      await contained(page, bulkMenu.locator('.inbox-menu-panel'));
      for (const action of await bulkMenu.locator('.inbox-menu-panel button').all()) await targetAtLeast44(action);
      await expect(bulkMenu).toContainText('Hide from Inbox');
      await capture(page, info.project.name, `bulk-menu-${width}-${theme}`);
      await page.keyboard.press('Escape');
      await expect(bulkMenu.locator(':scope > summary')).toBeFocused();
      await page.locator('[data-inbox-select-all]').check();
      await expect(page.locator('[data-inbox-select-all]')).toHaveJSProperty('indeterminate', false);
      const loaded = await selections.count();
      await expect(page.locator('[data-inbox-selection-label]')).toHaveText(`${loaded} selected`);
      await page.locator('[data-inbox-clear-selection]').click();
      await expect(page.locator('[data-inbox-select-all]')).not.toBeChecked();
      await expect(sweep).not.toHaveClass(/is-active/);

      await page.locator('.inbox-actions > summary').click();
      const helpTrigger = page.locator('[data-inbox-help-open]');
      await helpTrigger.click();
      const help = page.locator('[data-inbox-help-dialog]');
      await expect(help).toBeVisible();
      await expect(help).toContainText(/Til tomorrow/);
      await expect(help).not.toContainText('Monday');
      const visibleIds = await page.locator('[data-inbox-row]').evaluateAll(rows => rows.map(row => row.getAttribute('data-thread-id')));
      const mutations: string[] = [];
      const observeMutation = (request: { method(): string; url(): string }) => {
        if (request.method() === 'POST' && /\/t\/\d+\/(read|star|snooze)$/.test(new URL(request.url()).pathname)) mutations.push(request.url());
      };
      page.on('request', observeMutation);
      await help.locator('[data-inbox-help-close]').focus();
      await page.keyboard.press('#');
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
      page.off('request', observeMutation);
      expect(mutations, 'Help must suppress topic mutation shortcuts while it owns focus').toEqual([]);
      expect(await page.locator('[data-inbox-row]').evaluateAll(rows => rows.map(row => row.getAttribute('data-thread-id'))), 'Inbox shortcuts must not mutate topics while Help owns focus').toEqual(visibleIds);
      const audit = await new AxeBuilder({ page }).include('[data-subheader]').include('[data-inbox-list]').include('[data-inbox-help-dialog]').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
      expect(audit.violations).toEqual([]);
      await capture(page, info.project.name, `help-${width}-${theme}`);
      await page.keyboard.press('Escape');
      await expect(page.locator('.inbox-actions > summary')).toBeFocused();
    }
  }
});

async function contextFor(browser: Browser, baseURL: string | undefined, javaScriptEnabled: boolean, info: TestInfo): Promise<BrowserContext> {
  const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
  return browser.newContext({ baseURL, javaScriptEnabled, viewport: viewport as { width: number; height: number }, isMobile: isMobile as boolean, hasTouch: hasTouch as boolean, deviceScaleFactor: deviceScaleFactor as number });
}

for (const javaScriptEnabled of [true, false]) {
  test(`row hiding, tomorrow and restoration work ${javaScriptEnabled ? 'enhanced' : 'without JavaScript'}`, async ({ browser, baseURL }, info) => {
    const context = await contextFor(browser, baseURL, javaScriptEnabled, info);
    try {
      const page = await context.newPage();
      await login(page);
      await page.goto('/inbox?scope=starred&order=active');
      const id = (await topicRow(page).getAttribute('data-thread-id'))!;
      let panel = await rowMenu(topicRow(page, id), !javaScriptEnabled);
      await contained(page, panel);
      await panel.getByRole('switch', { name: 'Show in Inbox', exact: true }).click();
      await expect(topicRow(page, id)).toHaveCount(0);
      await page.goto('/inbox?scope=snoozed&order=active');
      await expect(topicRow(page, id).locator('.thread-meta')).toContainText('Until you turn it back on');
      panel = await rowMenu(topicRow(page, id), !javaScriptEnabled);
      await contained(page, panel);
      const toggle = panel.getByRole('switch', { name: 'Show in Inbox', exact: true });
      await expect(toggle).toHaveAttribute('aria-checked', 'false');
      await capture(page, info.project.name, `manual-hidden-${javaScriptEnabled ? 'js' : 'native'}`);
      await toggle.click();
      await expect(topicRow(page, id)).toHaveCount(0);
      await page.goto('/inbox?scope=starred&order=active');
      panel = await rowMenu(topicRow(page, id), !javaScriptEnabled);
      await panel.getByRole('button', { name: 'Til tomorrow', exact: true }).click();
      await expect(topicRow(page, id)).toHaveCount(0);
      await page.goto('/inbox?scope=snoozed&order=active');
      await expect(topicRow(page, id).locator('.thread-meta')).toContainText(/Til /);
      await expect(topicRow(page, id).locator('.thread-meta')).not.toContainText('Until you turn it back on');
      panel = await rowMenu(topicRow(page, id), !javaScriptEnabled);
      await expect(panel.getByRole('switch', { name: 'Show in Inbox', exact: true })).toHaveAttribute('aria-checked', 'false');
      await capture(page, info.project.name, `tomorrow-hidden-${javaScriptEnabled ? 'js' : 'native'}`);
      await panel.getByRole('switch', { name: 'Show in Inbox', exact: true }).click();
      await expect(topicRow(page, id)).toHaveCount(0);
      await page.goto('/inbox?scope=starred&order=active');
      await expect(topicRow(page, id)).toBeVisible();
      await expect(topicRow(page, id).locator('.thread-meta')).not.toContainText(/Until you turn it back on|Til /);
      if (!javaScriptEnabled) {
        const widths = info.project.name === 'mobile' ? [320, 393] : [861, 1440];
        const measurements = [];
        for (const width of widths) {
          await page.setViewportSize({ width, height: 844 });
          const last = page.locator('[data-inbox-row]').last();
          await last.locator('[data-inbox-row-menu] > summary').scrollIntoViewIfNeeded();
          const lastPanel = await rowMenu(last, true);
          await capture(page, info.project.name, `last-row-native-${width}`);
          measurements.push(await lastPanel.evaluate(element => {
            const box = element.getBoundingClientRect();
            const scrollingOwner = element.closest('[data-inbox-list]')!;
            const owner = scrollingOwner.getBoundingClientRect();
            return {
              width: innerWidth, clientWidth: document.documentElement.clientWidth,
              panel: { x: box.left, right: box.right, y: box.top, bottom: box.bottom, height: box.height },
              owner: { y: owner.top, bottom: owner.bottom, clientHeight: scrollingOwner.clientHeight, scrollHeight: scrollingOwner.scrollHeight, scrollTop: scrollingOwner.scrollTop },
              position: getComputedStyle(element).position,
              panelBottomInScroll: box.bottom - owner.top + scrollingOwner.scrollTop,
              paintedHeight: Math.max(0, Math.min(box.bottom, owner.bottom, innerHeight) - Math.max(box.top, owner.top, 0)),
            };
          }));
          const directory = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', info.project.name);
          fs.writeFileSync(path.join(directory, 'native-last-row-geometry.json'), JSON.stringify(measurements, null, 2));
          await contained(page, lastPanel);
          const measurement = measurements.at(-1)!;
          expect(measurement.paintedHeight, 'A native row panel must fit its scrolling pane, including the last topic').toBeGreaterThanOrEqual(measurement.panel.height - 1);
          expect(measurement.panelBottomInScroll, 'The expanded native panel must contribute to reachable scrolling bounds').toBeLessThanOrEqual(measurement.owner.scrollHeight + 1);
          await last.locator('[data-inbox-row-menu] > summary').click();
        }
      }
    } finally {
      await context.close();
    }
  });

  test(`bulk hide, tomorrow and restore operate on the selected page ${javaScriptEnabled ? 'enhanced' : 'without JavaScript'}`, async ({ browser, baseURL }, info) => {
    const context = await contextFor(browser, baseURL, javaScriptEnabled, info);
    try {
      const page = await context.newPage();
      await login(page);
      let selectedIds: string[] = [];
      for (const command of ['Hide from Inbox', 'Til tomorrow']) {
        await page.goto('/inbox?scope=starred&order=active');
        const rows = page.locator('[data-inbox-row]');
        selectedIds = [];
        for (let index = 0; index < 2; index++) {
          selectedIds.push((await rows.nth(index).getAttribute('data-thread-id'))!);
          await rows.nth(index).locator('[data-inbox-select]').check();
        }
        const bulk = page.locator('[data-inbox-sweep]');
        if (!javaScriptEnabled) {
          await expect(bulk.locator('[data-inbox-clear-selection]')).toHaveCount(1);
          await expect(bulk.locator('[data-inbox-clear-selection]')).toBeHidden();
        }
        await bulk.locator('[data-inbox-bulk-menu] > summary').click();
        await bulk.getByRole('button', { name: command, exact: command === 'Til tomorrow' }).click();
        for (const id of selectedIds) await expect(topicRow(page, id)).toHaveCount(0);
        await page.goto('/inbox?scope=snoozed&order=active');
        for (const id of selectedIds) {
          const row = topicRow(page, id);
          await expect(row).toBeVisible();
          await expect(row.locator('.thread-meta')).toContainText(command === 'Hide from Inbox' ? 'Until you turn it back on' : /Til /);
          await row.locator('[data-inbox-select]').check();
        }
        await page.locator('[data-inbox-sweep] [data-inbox-bulk-menu] > summary').click();
        await page.locator('[data-inbox-sweep]').getByRole('button', { name: 'Show in Inbox', exact: true }).click();
        for (const id of selectedIds) await expect(topicRow(page, id)).toHaveCount(0);
        await page.goto('/inbox?scope=starred&order=active');
        for (const id of selectedIds) await expect(topicRow(page, id)).toBeVisible();
      }
      await capture(page, info.project.name, `bulk-restored-${javaScriptEnabled ? 'js' : 'native'}`);
    } finally {
      await context.close();
    }
  });
}

test('the snooze shortcut uses the same tomorrow action as the menu', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=starred&order=active');
  const first = page.locator('[data-inbox-row]').first();
  const id = (await first.getAttribute('data-thread-id'))!;
  await page.keyboard.press('j');
  const [snoozeResponse] = await Promise.all([
    page.waitForResponse(response => response.request().method() === 'POST'
      && new URL(response.url()).pathname === `/t/${id}/snooze`),
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.keyboard.press('#'),
  ]);
  expect(snoozeResponse.status(), 'Rapid keyboard input must complete the native snooze POST').toBe(303);
  await expect(page).toHaveURL(/\/inbox\?scope=starred&order=active/);
  await expect(topicRow(page, id)).toHaveCount(0);
  await page.goto('/inbox?scope=snoozed&order=active');
  await expect(topicRow(page, id).locator('.thread-meta')).toContainText(/Til /);
  await expect(topicRow(page, id).locator('.thread-meta')).not.toContainText('Until you turn it back on');
});

test('preview removal preserves live range selection and page actions; persistent rows offer the current read action', async ({ page }) => {
  execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    \\App\\Core\\Env::load(getcwd() . '/.env');
    $config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
    $db = new \\App\\Core\\Database($config->get('db'));
    $alice = (new \\App\\Repository\\UserRepository($db))->findByUsername('alice');
    $state = new \\App\\Repository\\ThreadUserRepository($db);
    foreach ($db->fetchAll('SELECT id, last_post_id FROM threads') as $topic) {
      $state->markRead((int) $alice['id'], (int) $topic['id'], (int) $topic['last_post_id']);
    }
    foreach ($db->fetchAll("SELECT id FROM threads WHERE title IN ('${TOPIC}', 'Mobile layout looks great', 'Welcome to RetroBoards')") as $topic) {
      $state->markUnread((int) $alice['id'], (int) $topic['id']);
    }
  `], { cwd: ROOT, env: process.env });
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page);
  await page.goto('/inbox?scope=unread&order=active');
  const rows = page.locator('[data-inbox-row]');
  await expect(rows).toHaveCount(3);
  const firstId = (await rows.first().getAttribute('data-thread-id'))!;
  await rows.first().locator('[data-inbox-preview-url]').click();
  await expect(rows).toHaveCount(2);
  await expect(topicRow(page, firstId)).toHaveCount(0);
  const back = page.locator('[data-inbox-back]');
  if (await back.isVisible()) await back.click();
  const remainingIds = await rows.evaluateAll(rows => rows.map(row => row.getAttribute('data-thread-id')));
  const pageIds = await page.locator('.inbox-mark-all input[name="thread_ids[]"]').evaluateAll(inputs => inputs.map(input => (input as HTMLInputElement).value));
  expect(pageIds.sort(), 'Page read must submit only rows still present after a preview').toEqual([...remainingIds].sort());
  await rows.last().locator('[data-inbox-select]').check();
  await rows.first().locator('[data-inbox-select]').click({ modifiers: ['Shift'] });
  await expect(page.locator('[data-inbox-selection-label]')).toHaveText('2 selected');
  await expect(page.locator('[data-inbox-select-all]')).toBeChecked();
  await expect(page.locator('[data-inbox-select-all]')).toHaveJSProperty('indeterminate', false);
  expect(errors, 'Removing an earlier row must not leave a stale Shift-selection index').toEqual([]);
  await page.locator('.inbox-actions > summary').click();
  await page.getByRole('button', { name: 'Mark this page read', exact: true }).click();
  await expect(rows).toHaveCount(0);
  await expect(page.locator('.flash')).toContainText('2 topics updated');

  // Reload with the same topic unread in a scope that keeps read topics. A
  // preview must repaint the surviving native action as well as the marker.
  execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    \\App\\Core\\Env::load(getcwd() . '/.env');
    $config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
    $db = new \\App\\Core\\Database($config->get('db'));
    $alice = (new \\App\\Repository\\UserRepository($db))->findByUsername('alice');
    (new \\App\\Repository\\ThreadUserRepository($db))->markUnread((int) $alice['id'], ${firstId});
  `], { cwd: ROOT, env: process.env });
  await page.goto('/inbox?scope=starred&order=active');
  const persistent = topicRow(page, firstId);
  await expect(persistent.locator('[data-inbox-action="read"] input[name="state"]')).toHaveValue('read');
  await persistent.locator('[data-inbox-preview-url]').click();
  await expect(persistent).toHaveAttribute('data-inbox-unread', '0');
  if (await back.isVisible()) await back.click();
  await expect(persistent.locator('[data-inbox-action="read"] input[name="state"]')).toHaveValue('unread');
  await expect(persistent.locator('[data-inbox-action="read"] button')).toHaveText('Mark unread');
  const panel = await rowMenu(persistent);
  await panel.getByRole('button', { name: 'Mark unread', exact: true }).click();
  await expect(topicRow(page, firstId)).toHaveAttribute('data-inbox-unread', '1');
  expect(errors).toEqual([]);
});

test('long unbroken topic text stays within its content column at default and large type', async ({ page }, info) => {
  const title = 'Topic ' + 'W'.repeat(110);
  const updateTitle = (title: string) => execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    \\App\\Core\\Env::load(getcwd() . '/.env');
    $config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
    $db = new \\App\\Core\\Database($config->get('db'));
    $db->run('UPDATE threads SET title = :title WHERE slug = :slug', ['title' => '${title}', 'slug' => 'mobile-layout-looks-great']);
  `], { cwd: ROOT, env: process.env });
  updateTitle(title);
  try {
    await login(page);
    for (const [theme, large] of [['light', false], ['dark', true]] as const) {
      await appearance(page, theme, large);
      for (const width of info.project.name === 'mobile' ? [320, 393] : [861, 1440]) {
        await page.setViewportSize({ width, height: 844 });
        await page.goto('/inbox?scope=starred&order=active');
        const link = page.getByRole('link', { name: title, exact: true });
        await expect(link).toBeVisible();
        await capture(page, info.project.name, `unbroken-title-${width}-${theme}`);
        const geometry = await link.evaluate(element => {
          const main = element.closest('.thread-row-main')!;
          const box = main.getBoundingClientRect();
          const rowStyle = getComputedStyle(element.closest('[data-inbox-row]')!);
          const range = document.createRange();
          range.selectNodeContents(element);
          return {
            main: { x: box.left, right: box.right, client: main.clientWidth, scroll: main.scrollWidth },
            text: Array.from(range.getClientRects()).map(rect => ({ x: rect.left, right: rect.right })),
            row: { borderRadius: rowStyle.borderRadius, borderLeftWidth: rowStyle.borderLeftWidth },
          };
        });
        expect(geometry.main.scroll, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.main.client + 1);
        expect(geometry.row.borderRadius, 'Inbox dividers must stay straight through the final cascade').toBe('0px');
        expect(geometry.row.borderLeftWidth, 'Selection spacing must retain the one-pixel border width').toBe('1px');
        for (const line of geometry.text) {
          expect(line.x).toBeGreaterThanOrEqual(geometry.main.x - 1);
          expect(line.right).toBeLessThanOrEqual(geometry.main.right + 1);
        }
      }
    }
  } finally {
    updateTitle('Mobile layout looks great');
  }
});
