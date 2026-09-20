import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import path from 'node:path';

/**
 * First-paint mobile geometry must not depend on when a deferred bundle arrives.
 * Request routing deliberately disables browser cache: these are controlled
 * startup/failure regressions, not cold/warm production performance benchmarks.
 * Run against an already seeded, dedicated DB; this spec never rebuilds it.
 */
const root = path.resolve(__dirname, '..', '..');
const evidence = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/browser/startup-geometry');
const title = 'Share your favourite keyboard shortcuts';
let threadPath = '';
let originalFeatures: Record<string, unknown>;

function fixturePhp(code: string): string {
  if (process.env.APP_ENV === 'production') {
    throw new Error('Startup geometry requires a dedicated seeded local fixture, not APP_ENV=production.');
  }
  return execFileSync('php', ['-r', `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
${code}
`], { cwd: root, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' } }).toString().trim();
}

function writeFeatures(value: Record<string, unknown>): void {
  const encoded = Buffer.from(JSON.stringify(value)).toString('base64');
  fixturePhp(`$settings->set('features', json_decode(base64_decode('${encoded}'), true));`);
}

test.beforeAll(() => {
  originalFeatures = JSON.parse(fixturePhp("echo json_encode($settings->get('features', []));"));
  const encoded = Buffer.from(title).toString('base64');
  threadPath = fixturePhp(`
$row = $db->fetch('SELECT id, slug FROM threads WHERE title = ? ORDER BY id LIMIT 1', [base64_decode('${encoded}')]);
echo $row ? '/t/' . (int) $row['id'] . '-' . $row['slug'] : '';
`);
  expect(threadPath).toMatch(/^\/t\/\d+-/);
});

test.beforeEach(({}, info) => {
  test.skip(info.project.name !== 'mobile', 'the startup shift being guarded is the mobile layout');
  writeFeatures({
    ...originalFeatures,
    rich_composer: true,
    wysiwyg_composer: false,
    product_tour: false,
    drafts: false,
    server_drafts: false,
  });
});

test.afterAll(() => {
  if (originalFeatures) writeFeatures(originalFeatures);
});

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
}

function assetPath(name: string): string {
  const manifest = JSON.parse(readFileSync(path.join(root, 'config/assets.json'), 'utf8'));
  return new URL(manifest.urls[name] ?? `/assets/${name}`, 'http://fixture.invalid').pathname;
}

async function holdScript(page: Page, name: string): Promise<{ release: () => void; requested: () => boolean }> {
  let release!: () => void;
  let requested = false;
  const gate = new Promise<void>((resolve) => { release = resolve; });
  const pathname = assetPath(name);
  await page.route((url) => url.pathname === pathname, async (route) => {
    requested = true;
    await gate;
    await route.continue().catch(() => {}); // A failing assertion can close the page while held.
  });
  return { release, requested: () => requested };
}

async function blockScript(page: Page, name: string): Promise<void> {
  const pathname = assetPath(name);
  await page.route((url) => url.pathname === pathname, (route) => route.abort('blockedbyclient'));
}

type Box = { x: number; y: number; width: number; height: number };
type Geometry = Record<string, Box | null>;
type Probe = {
  shifts: Array<{ startTime: number; value: number; hadRecentInput: boolean }>;
  frames: Array<{ time: number; boxes: Geometry }>;
};

async function observeStartup(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const state: Probe = { shifts: [], frames: [] };
    (window as Window & { __startupGeometry?: Probe }).__startupGeometry = state;
    new PerformanceObserver((list) => {
      for (const item of list.getEntries()) {
        const shift = item as PerformanceEntry & { value: number; hadRecentInput: boolean };
        state.shifts.push({ startTime: shift.startTime, value: shift.value, hadRecentInput: shift.hadRecentInput });
      }
    }).observe({ type: 'layout-shift', buffered: true });
    let previous = '';
    const frame = () => {
      if (performance.getEntriesByName('first-contentful-paint').length) {
        const boxes: Geometry = {};
        for (const [name, selector] of Object.entries({ main: '#main', scroll: '.thread-scroll', dock: '.thread-dock' })) {
          const element = document.querySelector(selector);
          const box = element?.getBoundingClientRect();
          boxes[name] = box ? Object.fromEntries(['x', 'y', 'width', 'height'].map((key) => [
            key, Math.round(box[key as keyof DOMRect] as number * 100) / 100,
          ])) as Box : null;
        }
        const serialized = JSON.stringify(boxes);
        if (serialized !== previous && state.frames.length < 200) {
          state.frames.push({ time: performance.now(), boxes });
          previous = serialized;
        }
      }
      requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  });
}

async function settledGeometry(page: Page): Promise<Geometry> {
  await page.evaluate(async () => {
    await document.fonts.ready;
    await new Promise<void>((resolve) => requestAnimationFrame(() => requestAnimationFrame(() => resolve())));
  });
  const boxes: Geometry = {};
  for (const [name, selector] of Object.entries({ main: '#main', scroll: '.thread-scroll', dock: '.thread-dock' })) {
    boxes[name] = await page.locator(selector).count() ? await page.locator(selector).boundingBox() : null;
  }
  return boxes;
}

function cls(shifts: Probe['shifts']): number {
  let largest = 0;
  let value = 0;
  let first = 0;
  let last = 0;
  for (const shift of shifts) {
    if (shift.hadRecentInput) continue;
    if (value > 0 && shift.startTime - last < 1000 && shift.startTime - first < 5000) {
      value += shift.value;
    } else {
      value = shift.value;
      first = shift.startTime;
    }
    last = shift.startTime;
    largest = Math.max(largest, value);
  }
  return largest;
}

async function saveEvidence(page: Page, info: TestInfo, name: string, value: unknown): Promise<void> {
  const directory = path.join(evidence, info.project.name);
  await mkdir(directory, { recursive: true });
  await writeFile(path.join(directory, `${name}.json`), `${JSON.stringify(value, null, 2)}\n`);
  await page.screenshot({ path: path.join(directory, `${name}.png`), fullPage: true, animations: 'disabled' });
}

function expectStable(before: Geometry, after: Geometry, phase: string): void {
  for (const name of ['main', 'scroll', 'dock']) {
    if (!before[name]) continue;
    expect.soft(after[name], `${phase}: ${name} is still rendered`).not.toBeNull();
    if (!after[name]) continue;
    for (const dimension of ['x', 'y', 'width', 'height'] as const) {
      expect.soft(Math.abs(after[name]![dimension] - before[name]![dimension]), `${phase}: ${name}.${dimension}`).toBeLessThanOrEqual(1);
    }
  }
}

for (const destination of ['home', 'thread'] as const) {
  test(`${destination} keeps first-paint geometry as deferred app and composer arrive`, async ({ page }, info) => {
    await login(page);
    await observeStartup(page);
    const app = await holdScript(page, 'app.js');
    const composer = await holdScript(page, 'composer.js');
    try {
      await page.goto(destination === 'home' ? '/' : threadPath, { waitUntil: 'commit' });
      await expect(page.locator('#main')).toBeVisible();
      await expect.poll(app.requested).toBe(true);
      await expect.poll(composer.requested).toBe(true);
      const before = await settledGeometry(page);
      await expect(page.locator('html')).not.toHaveClass(/has-js/);
      await page.waitForTimeout(600); // Give the fallback a real painted interval before enhancement.
      app.release();
      await expect(page.locator('html')).toHaveClass(/has-js/);
      if (destination === 'thread') await expect(page.locator('[data-thread-study]')).toHaveAttribute('data-thread-enhanced', '1');
      const afterApp = await settledGeometry(page);
      await page.waitForTimeout(600);
      composer.release();
      await page.waitForLoadState('load');
      if (destination === 'thread') await expect(page.locator('#reply')).toHaveAttribute('data-composer-dock', '1');
      await page.waitForTimeout(250);
      const afterComposer = await settledGeometry(page);
      const probe = await page.evaluate(() => (window as Window & { __startupGeometry: Probe }).__startupGeometry);
      const result = { mode: 'controlled delayed bundles; routing disables cache', before, afterApp, afterComposer, cls: cls(probe.shifts), ...probe };
      await saveEvidence(page, info, `startup-${destination}-delayed`, result);
      expectStable(before, afterApp, 'app startup');
      expectStable(afterApp, afterComposer, 'composer startup');
      expect.soft(result.cls, JSON.stringify(result.shifts)).toBeLessThan(0.1);
    } finally {
      app.release();
      composer.release();
    }
  });
}

async function tabTo(page: Page, target: Locator, limit = 100): Promise<void> {
  await expect(target).toBeVisible();
  for (let presses = 0; presses < limit; presses++) {
    if (await target.evaluate((element) => element === document.activeElement)) return;
    await page.keyboard.press('Tab');
  }
  throw new Error('Native fallback control was not reachable with Tab.');
}

test('blocked app still exposes the rail and Topic tools through native keyboard links', async ({ page }, info) => {
  await login(page);
  await blockScript(page, 'app.js');
  await page.goto(threadPath);
  await expect(page.locator('html')).not.toHaveClass(/has-js/);
  const rail = page.locator('#sidebar-nav');
  const railOpen = page.locator('[data-nav-fallback]');
  await expect(rail).toBeHidden();
  await tabTo(page, railOpen);
  await page.keyboard.press('Enter');
  await expect(rail).toBeVisible();
  await expect(rail).toBeInViewport();
  const boardLink = rail.locator('a[href="/c/general"]').first();
  await tabTo(page, boardLink);
  await expect(boardLink).toBeFocused();
  await tabTo(page, rail.locator('[data-nav-close]'));
  await page.keyboard.press('Enter');
  await expect(rail).toBeHidden();

  const tools = page.locator('[data-topic-tools]');
  const toolsOpen = page.locator('[data-topic-tools-fallback]');
  await expect(tools).toBeHidden();
  await tabTo(page, toolsOpen);
  await page.keyboard.press('Enter');
  await expect(tools).toBeVisible();
  await expect(tools).toBeInViewport();
  const summary = tools.locator('details > summary').first();
  const wasOpen = await summary.evaluate((element) => (element.parentElement as HTMLDetailsElement).open);
  await tabTo(page, summary);
  await page.keyboard.press('Space');
  await expect(summary.locator('..')).toHaveJSProperty('open', !wasOpen);
  await tabTo(page, tools.getByRole('link', { name: 'Close Topic tools', exact: true }));
  await page.keyboard.press('Enter');
  await expect(tools).toBeHidden();
  await saveEvidence(page, info, 'startup-blocked-app-native-controls', { railKeyboardReachable: true, topicToolsKeyboardReachable: true });
});

test('native drawers preserve open state and keyboard focus when app enhancement arrives', async ({ page }, info) => {
  await login(page);
  const railApp = await holdScript(page, 'app.js');
  try {
    await page.goto(threadPath, { waitUntil: 'commit' });
    await expect.poll(railApp.requested).toBe(true);
    const rail = page.locator('#sidebar-nav');
    await tabTo(page, page.locator('[data-nav-fallback]'));
    await page.keyboard.press('Enter');
    await expect(rail).toBeVisible();
    const boardLink = rail.locator('a[href="/c/general"]').first();
    await tabTo(page, boardLink);
    railApp.release();
    await expect(page.locator('body')).toHaveAttribute('data-nav-ready', '1');
    await expect(rail).toBeVisible();
    await expect(page.locator('body')).toHaveClass(/nav-open/);
    await expect(boardLink).toBeFocused();
    await expect(page.locator('[data-nav-fallback]')).toBeHidden();
    await page.keyboard.press('Escape');
    await expect(rail).toBeHidden();
    await expect(page.locator('[data-nav-toggle]')).toBeFocused();
  } finally {
    railApp.release();
  }

  const toolsApp = await holdScript(page, 'app.js');
  try {
    await page.goto(threadPath, { waitUntil: 'commit' });
    await expect.poll(toolsApp.requested).toBe(true);
    const tools = page.locator('[data-topic-tools]');
    await tabTo(page, page.locator('[data-topic-tools-fallback]'));
    await page.keyboard.press('Enter');
    await expect(tools).toBeVisible();
    const summary = tools.locator('details > summary').first();
    await tabTo(page, summary);
    toolsApp.release();
    await expect(page.locator('[data-thread-study]')).toHaveAttribute('data-thread-enhanced', '1');
    await expect(tools).toBeVisible();
    await expect(tools).toHaveAttribute('role', 'dialog');
    await expect(summary).toBeFocused();
    await expect(page.locator('[data-topic-tools-fallback]')).toBeHidden();
    await expect(page.locator('[data-topic-tools-fallback-close]')).toBeHidden();
    await page.keyboard.press('Escape');
    await expect(tools).toBeHidden();
    const enhancedOpen = page.locator('[data-topic-tools-open]');
    await expect(enhancedOpen).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(tools).toBeVisible();
    await expect(tools.locator('button[data-topic-tools-close]')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(tools).toBeHidden();
    await expect(enhancedOpen).toBeFocused();
    await saveEvidence(page, info, 'startup-native-drawer-handoff', {
      railOpenStatePreserved: true,
      railFocusPreserved: true,
      toolsOpenStatePreserved: true,
      toolsFocusPreserved: true,
      enhancedToolsFocusesCloseButton: true,
      escapeRestoresEnhancedOpeners: true,
    });
  } finally {
    toolsApp.release();
  }
});

test('blocked composer keeps its native textarea and submit controls accessible', async ({ page }, info) => {
  await login(page);
  await blockScript(page, 'composer.js');
  await page.goto(threadPath);
  const form = page.locator('#reply');
  const textarea = form.locator('textarea[name="body"]');
  await expect(form).not.toHaveAttribute('data-composer-dock', '1');
  await expect(textarea).toBeVisible();
  const compact = await form.boundingBox();
  await textarea.focus();
  await page.keyboard.type('Native fallback remains usable.');
  await expect(form.locator('.composer-actions-start')).toBeVisible();
  await expect(form.locator('button[type="submit"]')).toBeVisible();
  const expanded = await form.boundingBox();
  expect(expanded!.height).toBeGreaterThan(compact!.height + 40);
  await tabTo(page, form.locator('button[type="submit"]'));
  await expect(textarea).toHaveValue('Native fallback remains usable.');
  await saveEvidence(page, info, 'startup-blocked-composer-native-form', { compact, expanded, textareaUsable: true, submitKeyboardReachable: true });
});

test('delayed rich adapter preserves text entered through the first-paint textarea', async ({ page }, info) => {
  writeFeatures({ ...originalFeatures, rich_composer: true, wysiwyg_composer: true, product_tour: false, drafts: false, server_drafts: false });
  await login(page);
  const adapter = await holdScript(page, 'wysiwyg-composer.js');
  try {
    await page.goto(threadPath, { waitUntil: 'commit' });
    await expect.poll(adapter.requested).toBe(true);
    const form = page.locator('#reply');
    const textarea = form.locator('textarea[name="body"]');
    await expect(textarea).toBeVisible();
    await textarea.focus();
    const initial = 'Typed while the editor module was delayed.';
    await page.keyboard.type(initial);
    await page.keyboard.press('Control+Home');
    for (let index = 0; index < 6; index++) await page.keyboard.press('ArrowRight');
    expect(await textarea.evaluate((element: HTMLTextAreaElement) => [element.selectionStart, element.selectionEnd])).toEqual([6, 6]);
    await expect(form.locator('.composer-actions-start')).toBeVisible();
    const before = await form.boundingBox();
    adapter.release();
    await expect(form).toHaveAttribute('data-composer-dock', '1');
    await expect(form.locator('.wysiwyg-composer')).toBeAttached();
    // Upgrading must not leave focus in a clipped source textarea: subsequent
    // keystrokes would otherwise disappear from the visible rich document.
    await expect(textarea).not.toHaveClass(/is-wysiwyg-source-hidden/);
    await expect(textarea).toBeFocused();
    await expect(textarea).toHaveValue(initial);
    expect(await textarea.evaluate((element: HTMLTextAreaElement) => [element.selectionStart, element.selectionEnd])).toEqual([6, 6]);
    expect((await textarea.boundingBox())!.height).toBeGreaterThan(44);
    await page.keyboard.type('still ');
    const complete = 'Typed still while the editor module was delayed.';
    await expect(textarea).toHaveValue(complete);
    expect(await textarea.evaluate((element: HTMLTextAreaElement) => [element.selectionStart, element.selectionEnd])).toEqual([12, 12]);
    await expect(textarea).toBeFocused();
    await form.getByRole('button', { name: 'Rich text', exact: true }).click();
    const richEditor = form.locator('.wysiwyg-composer .ProseMirror');
    await expect(richEditor).toBeVisible();
    await expect(richEditor).toContainText(complete);
    await expect(richEditor).toBeFocused();
    await expect(form).toHaveClass(/is-expanded/);
    await expect(form.locator('button[type="submit"]')).toBeVisible();
    await saveEvidence(page, info, 'startup-delayed-adapter-preserves-input', {
      beforeUpgrade: before,
      afterUpgrade: await form.boundingBox(),
      inputPreserved: true,
      sourceFocusAndCaretPreserved: true,
      continuedTypingVisible: true,
      explicitRichSwitchPreservesCompleteInput: true,
    });
  } finally {
    adapter.release();
  }
});

test('no JavaScript retains the natural rail, readable thread, and full native form', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const page = await context.newPage();
  try {
    await login(page);
    await page.goto(threadPath);
    await expect(page.locator('html')).not.toHaveClass(/has-js/);
    await expect(page.locator('#sidebar-nav')).toBeVisible();
    await expect(page.locator('[data-thread-enhanced]')).toHaveCount(0);
    const natural = await page.evaluate(() => {
      const scroll = document.querySelector('.thread-scroll') as HTMLElement;
      const conversation = document.querySelector('.thread-conversation') as HTMLElement;
      const rail = document.querySelector('#sidebar-nav') as HTMLElement;
      return {
        overflowY: getComputedStyle(scroll).overflowY,
        scrollHeight: scroll.scrollHeight,
        clientHeight: scroll.clientHeight,
        conversationClipped: conversation.scrollHeight > conversation.clientHeight + 1,
        railPosition: getComputedStyle(rail).position,
      };
    });
    expect(natural.overflowY).toBe('visible');
    expect(natural.scrollHeight).toBeLessThanOrEqual(natural.clientHeight + 1);
    expect(natural.conversationClipped).toBe(false);
    expect(natural.railPosition).not.toBe('fixed');
    await expect(page.locator('[data-topic-tools]')).toBeVisible();
    const form = page.locator('#reply');
    await expect(form.locator('.composer-anonymous-disclosure')).toBeVisible();
    await expect(form.getByRole('checkbox', { name: 'Anonymous', exact: true })).toBeVisible();
    await form.locator('textarea[name="body"]').fill('Plain HTML is still writable.');
    await expect(form.locator('button[type="submit"]')).toBeVisible();
    await saveEvidence(page, info, 'startup-no-javascript-natural-layout', natural);
  } finally {
    await context.close();
  }
});
