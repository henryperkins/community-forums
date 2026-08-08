import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Browser, type BrowserContext, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const REPO_ROOT = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.resolve(REPO_ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/imladris-admin-account-slice-4');
const INTRO = 'Everything this community knows about you, and everything it does on your behalf.';
const CONDITIONAL_FLAGS = ['drafts', 'oauth', 'account_lifecycle', 'appeals', 'product_tour'] as const;
const DESTINATION_LABELS = [
  'Profile', 'Security', 'Privacy', 'Appearance', 'Reading', 'Composing', 'Drafts', 'Boards',
  'Notifications', 'Connections', 'Blocks', 'Sessions', 'Account', 'Appeals',
] as const;
const DESTINATIONS = [
  ['/settings/account', 'profile'],
  ['/settings/security', 'security'],
  ['/settings/privacy', 'privacy'],
  ['/settings/appearance', 'appearance'],
  ['/settings/preferences', 'reading'],
  ['/settings/composing', 'composing'],
  ['/drafts', 'drafts'],
  ['/settings/boards', 'boards'],
  ['/settings/notifications', 'notifications'],
  ['/settings/connections', 'connections'],
  ['/settings/blocks', 'blocks'],
  ['/settings/sessions', 'sessions'],
  ['/settings/account/lifecycle', 'account'],
  ['/appeals', ''],
] as const;
const GROUP_KEYS = [
  ['profile', 'security', 'privacy'],
  ['appearance', 'reading', 'composing', 'drafts', 'boards'],
  ['notifications', 'connections', 'blocks', 'sessions', 'account', 'appeals'],
] as const;
const CONDITIONAL_CONTROLS: Record<(typeof CONDITIONAL_FLAGS)[number], string> = {
  drafts: 'a[href="/drafts"]',
  oauth: 'a[href="/settings/connections"]',
  account_lifecycle: 'a[href="/settings/account/lifecycle"]',
  appeals: 'a[href="/appeals"]',
  product_tour: '[data-tour-replay]',
};

type FeatureMap = Record<string, boolean>;

function runPhp(code: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: REPO_ROOT,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
}

function readFeatureMap(): FeatureMap {
  const raw = runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
echo json_encode($features, JSON_UNESCAPED_SLASHES);
`);
  return JSON.parse(raw || '{}') as FeatureMap;
}

function writeFeatureMap(features: FeatureMap): void {
  const encoded = Buffer.from(JSON.stringify(features), 'utf8').toString('base64');
  runPhp(`
$features = json_decode(base64_decode('${encoded}'), true);
if (!is_array($features)) { throw new RuntimeException('Invalid account-console feature fixture.'); }
$settings->set('features', $features);
`);
}

function featureState(original: FeatureMap, enabled: boolean): FeatureMap {
  return Object.fromEntries([
    ...Object.entries(original),
    ...CONDITIONAL_FLAGS.map((flag) => [flag, enabled]),
  ]);
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', 'bob@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

/**
 * Wait for every running transition/animation to finish.
 *
 * Flipping `data-theme` from JS does not repaint instantly: `.btn` (and several
 * other components) transition `background`, so for `--dur-fast` the element
 * still holds the *previous* register's colour. Sampling in that window reports
 * the light parchment fill under the dark register's cream text — measured at
 * 3.18:1 by axe, while the settled pair is correct. Verified: the export button
 * reads rgb(250,246,236) immediately after the flip and rgb(30,39,48) 600ms
 * later.
 *
 * The strict CSP rules out the usual fix of injecting a transition-killing
 * <style> tag (`style-src 'self'`, no unsafe-inline), so settle rather than
 * disable. Called from shot() and expectAxeClean() so every existing call site
 * gets it without changing what they assert — only when they measure.
 */
async function settle(page: Page): Promise<void> {
  await page.waitForFunction(
    () => document.getAnimations().every((animation) => animation.playState !== 'running'),
    undefined,
    { timeout: 5000 },
  ).catch(() => { /* a perpetual animation must not fail the gate */ });
}

async function shot(page: Page, folder: 'desktop' | 'mobile' | 'comparisons', name: string): Promise<void> {
  await settle(page);
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, folder, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
  });
}

async function expectAxeClean(page: Page, label: string): Promise<void> {
  await settle(page);
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = results.violations.filter((item) => item.impact === 'serious' || item.impact === 'critical');
  expect(violations, `${label} serious/critical axe violations`).toEqual([]);
}

async function expectOneCurrent(page: Page, key: string): Promise<void> {
  const nav = page.getByRole('navigation', { name: 'Settings sections' });
  await expect(nav.locator('.settings-rail-link.is-active[aria-current="page"]')).toHaveCount(1);
  await expect(nav.locator(`.settings-rail-link[data-settings-key="${key}"]`)).toHaveAttribute('aria-current', 'page');
}

test('desktop grouped rail and common heading match the account shell contract', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'desktop account-shell evidence uses 1280x800');
  await login(page);
  await page.goto('/settings/account');

  await expect(page.getByRole('heading', { level: 1, name: 'Account settings' })).toHaveCount(1);
  await expect(page.getByText(INTRO, { exact: true })).toBeVisible();
  const nav = page.getByRole('navigation', { name: 'Settings sections' });
  await expect(nav.locator('.settings-rail-title')).toHaveText(['Account', 'Reading & writing', 'Community']);
  await expect(nav.locator('.settings-rail-link')).toHaveText([...DESTINATION_LABELS]);
  expect(await nav.locator('.settings-rail-group').evaluateAll((groups) => groups.map((group) => (
    Array.from(group.querySelectorAll('[data-settings-key]'), (link) => link.getAttribute('data-settings-key'))
  )))).toEqual(GROUP_KEYS);
  await expectOneCurrent(page, 'profile');

  const geometry = await page.locator('.settings').evaluate((layout) => {
    const rail = layout.querySelector('.settings-rail');
    if (!(rail instanceof HTMLElement)) throw new Error('Settings rail missing');
    const railBox = rail.getBoundingClientRect();
    const pane = layout.querySelector('.settings-pane');
    if (!(pane instanceof HTMLElement)) throw new Error('Settings pane missing');
    return {
      railWidth: Math.round(railBox.width),
      railTop: Math.round(railBox.top),
      paneLeft: Math.round(pane.getBoundingClientRect().left),
      railRight: Math.round(railBox.right),
      sticky: getComputedStyle(rail).position,
      overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
  });
  expect(geometry.railWidth).toBe(232);
  expect(geometry.paneLeft - geometry.railRight).toBe(30);
  expect(geometry.sticky).toBe('sticky');
  expect(geometry.railTop).toBeGreaterThanOrEqual(80);
  expect(geometry.overflow).toBeLessThanOrEqual(0);

  const stickyAfterScroll = await page.locator('.settings-rail').evaluate((rail) => {
    window.scrollTo(0, Math.min(500, Math.max(0, document.documentElement.scrollHeight - window.innerHeight - 1)));
    const expectedTop = Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--topbar-h')) + 22;
    return new Promise<{ actualTop: number; expectedTop: number; scrollY: number }>((resolve) => {
      requestAnimationFrame(() => resolve({
        actualTop: rail.getBoundingClientRect().top,
        expectedTop,
        scrollY: window.scrollY,
      }));
    });
  });
  expect(stickyAfterScroll.scrollY).toBeGreaterThan(0);
  expect(Math.abs(stickyAfterScroll.actualTop - stickyAfterScroll.expectedTop)).toBeLessThanOrEqual(1);
  await page.evaluate(() => window.scrollTo(0, 0));

  await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
  await shot(page, 'desktop', 'profile-light');
  await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
  await shot(page, 'desktop', 'profile-twilight');
});

test('feature-dark entries vanish and Replay remains subordinate when enabled', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'feature hierarchy is captured once on desktop');
  const original = readFeatureMap();
  try {
    writeFeatureMap(featureState(original, false));
    await login(page);
    await page.goto('/settings/account');
    const nav = page.getByRole('navigation', { name: 'Settings sections' });
    for (const href of ['/drafts', '/settings/connections', '/settings/account/lifecycle', '/appeals']) {
      await expect(nav.locator(`a[href="${href}"]`)).toHaveCount(0);
    }
    await expect(nav.locator('[data-tour-replay], .is-disabled, [aria-disabled="true"]')).toHaveCount(0);

    for (const darkFeature of CONDITIONAL_FLAGS) {
      const flags = featureState(original, true);
      flags[darkFeature] = false;
      writeFeatureMap(flags);
      await page.reload();
      for (const feature of CONDITIONAL_FLAGS) {
        await expect(nav.locator(CONDITIONAL_CONTROLS[feature])).toHaveCount(feature === darkFeature ? 0 : 1);
      }
    }

    writeFeatureMap(featureState(original, true));
    await page.reload();
    const links = nav.locator('.settings-rail-link');
    await expect(links).toHaveCount(14);
    const replay = nav.locator('button[type="button"][data-tour-replay]');
    await expect(replay).toHaveCount(1);
    await expect(replay).not.toHaveAttribute('aria-current', 'page');
    const hierarchy = await nav.evaluate((node) => {
      const lastGroup = node.querySelector('.settings-rail-group:last-of-type');
      const replayButton = node.querySelector('[data-tour-replay]');
      return !!lastGroup && !!replayButton && !!(lastGroup.compareDocumentPosition(replayButton) & Node.DOCUMENT_POSITION_FOLLOWING);
    });
    expect(hierarchy).toBe(true);
  } finally {
    writeFeatureMap(original);
  }
});

test('representative Profile Security and Lifecycle 422 responses preserve explicit active state', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'validation ownership is captured once on desktop');
  await login(page);

  await page.goto('/settings/account');
  await page.locator('form[action="/settings/account"] input[name="display_name"]').fill('Preserved browser name');
  await page.locator('form[action="/settings/account"] input[name="website"]').fill('not-a-url');
  await page.locator('form[action="/settings/account"]').evaluate((form) => {
    (form as HTMLFormElement).noValidate = true;
    (form as HTMLFormElement).requestSubmit();
  });
  await expect(page.getByText('Enter a valid http(s) URL.', { exact: true })).toBeVisible();
  await expect(page.locator('input[name="display_name"]')).toHaveValue('Preserved browser name');
  await expectOneCurrent(page, 'profile');

  await page.goto('/settings/security');
  const password = page.locator('form[action="/settings/security"]');
  await password.locator('input[name="current_password"]').fill('wrong');
  await password.locator('input[name="new_password"]').fill('newpassword456');
  await password.locator('input[name="new_password_confirm"]').fill('newpassword456');
  await password.locator('button[type="submit"]').click();
  await expect(page.getByText('Your current password is incorrect.', { exact: true })).toBeVisible();
  await expectOneCurrent(page, 'security');

  await page.goto('/settings/account/lifecycle');
  const deactivate = page.locator('form[action="/settings/account/deactivate"]');
  await deactivate.locator('input[name="current_password"]').fill('wrong');
  await deactivate.locator('button[type="submit"]').click();
  await expect(page.getByRole('alert').getByText('Your current password is incorrect.', { exact: true })).toBeVisible();
  await expectOneCurrent(page, 'account');
});

test('mobile account rail records light and twilight responsive states', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'mobile', 'mobile account-shell evidence uses 390x844');
  await login(page);
  await page.goto('/settings/account');
  await expectOneCurrent(page, 'profile');

  await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
  await shot(page, 'mobile', 'profile-light');
  await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
  await shot(page, 'mobile', 'profile-twilight');
});

test('mobile no-JavaScript account navigation follows every available ordinary href', async ({ browser, baseURL }, info: TestInfo) => {
  test.skip(info.project.name !== 'mobile', 'no-JavaScript account-shell evidence uses 390x844');
  const original = readFeatureMap();
  let context: BrowserContext | undefined;
  try {
    writeFeatureMap(featureState(original, true));
    context = await (browser as Browser).newContext({
      baseURL: baseURL ?? 'http://localhost:8011',
      javaScriptEnabled: false,
      viewport: { width: 390, height: 844 },
      deviceScaleFactor: 2,
      isMobile: true,
      hasTouch: true,
    });
    const page = await context.newPage();
    await login(page);

    await page.goto('/settings/account');
    const initialNav = page.getByRole('navigation', { name: 'Settings sections' });
    await expect(initialNav.locator('.settings-rail-title')).toHaveText(['Account', 'Reading & writing', 'Community']);
    expect(await initialNav.locator('a').evaluateAll((links) => links.map((link) => link.getAttribute('href'))))
      .toEqual(DESTINATIONS.map(([href]) => href));
    const tabOrder = await initialNav.locator('a, button').evaluateAll((controls) => controls.map((control) => ({
      label: control.textContent?.trim(),
      tabIndex: (control as HTMLElement).tabIndex,
    })));
    expect(tabOrder.map(({ label }) => label)).toEqual([...DESTINATION_LABELS, 'Replay tour']);
    expect(tabOrder.every(({ tabIndex }) => tabIndex >= 0)).toBe(true);
    const wrap = await initialNav.locator('.settings-rail-group').evaluateAll((groups) => groups.map((group) => getComputedStyle(group).flexWrap));
    expect(wrap).toEqual(['wrap', 'wrap', 'wrap']);

    for (const [href, active] of DESTINATIONS) {
      await page.goto('/settings/account');
      const nav = page.getByRole('navigation', { name: 'Settings sections' });
      const link = nav.locator(`a[href="${href}"]`);
      await expect(link).toHaveCount(1);
      const [response] = await Promise.all([page.waitForNavigation(), link.click()]);
      expect(response?.status(), `GET ${href}`).toBeLessThan(400);
      if (active !== '') await expectOneCurrent(page, active);

      if (href !== '/appeals') {
        const metrics = await page.evaluate(() => {
          const rail = document.querySelector('.settings-rail');
          const pane = document.querySelector('.settings-pane');
          if (!(rail instanceof HTMLElement) || !(pane instanceof HTMLElement)) throw new Error('Account shell missing');
          const controls = Array.from(rail.querySelectorAll('a, button')).map((control) => Math.round(control.getBoundingClientRect().height));
          return {
            railBottom: Math.round(rail.getBoundingClientRect().bottom),
            paneTop: Math.round(pane.getBoundingClientRect().top),
            position: getComputedStyle(rail).position,
            controls,
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          };
        });
        expect(metrics.position).toBe('static');
        expect(metrics.paneTop).toBeGreaterThanOrEqual(metrics.railBottom);
        expect(metrics.controls.every((height) => height >= 44)).toBe(true);
        expect(metrics.overflow).toBeLessThanOrEqual(0);
      }
    }

    await page.goto('/settings/account');
    await shot(page, 'mobile', 'account-no-js');
  } finally {
    await context?.close();
    writeFeatureMap(original);
  }
});

test('full account shell is axe-clean in light twilight and system-dark registers', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'theme and axe evidence is captured once on desktop');
  const original = readFeatureMap();
  try {
    writeFeatureMap(featureState(original, true));
    await login(page);
    for (const [href, name] of [
      ['/settings/account', 'profile'],
      ['/settings/security', 'security'],
      ['/settings/connections', 'connections'],
    ] as const) {
      await page.goto(href);
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
      await expectAxeClean(page, `${name} light`);
      await shot(page, 'comparisons', `${name}-light`);
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
      await expectAxeClean(page, `${name} twilight`);
      await shot(page, 'comparisons', `${name}-twilight`);
      await page.emulateMedia({ colorScheme: 'dark' });
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'system'));
      await expectAxeClean(page, `${name} system-dark`);
      await shot(page, 'comparisons', `${name}-system-dark`);
    }
  } finally {
    writeFeatureMap(original);
  }
});

// ─── Slice 16: the eight account panes ──────────────────────────────────────
// Slice 4 certified the rail and shell; slice 15 the Profile and Security
// bodies. These own the remaining eight pane bodies. They are additive on
// purpose: the three tests above keep writing slice 4/15's captures unchanged.
const SLICE16_PANES = [
  ['/settings/privacy', 'privacy', 'Who can see you'],
  ['/settings/appearance', 'appearance', 'Theme'],
  ['/settings/preferences', 'reading', 'Pagination'],
  ['/settings/composing', 'composing', 'Composing'],
  ['/settings/notifications', 'notifications', 'Daily digest'],
  ['/settings/connections', 'connections', 'Connected accounts'],
  ['/settings/sessions', 'sessions', 'Active sessions & devices'],
  ['/settings/blocks', 'blocks', 'Blocked members'],
] as const;

/**
 * Document overflow, naming the offending elements. Slice 13 found two real
 * layout defects this way and slice 14 a third; a bare scrollWidth comparison
 * only says "something is too wide".
 */
async function expectNoOverflow(page: Page, label: string): Promise<void> {
  const report = await page.evaluate(() => {
    const docWidth = document.documentElement.clientWidth;
    const offenders: string[] = [];
    for (const node of Array.from(document.querySelectorAll('body *'))) {
      const box = node.getBoundingClientRect();
      if (box.width === 0 && box.height === 0) continue;
      if (box.right > docWidth + 1 || box.left < -1) {
        const el = node as HTMLElement;
        offenders.push(`${el.tagName.toLowerCase()}${el.className ? '.' + String(el.className).trim().split(/\s+/).join('.') : ''} [${Math.round(box.left)}…${Math.round(box.right)}]`);
      }
    }
    return {
      overflow: document.documentElement.scrollWidth - docWidth,
      offenders: offenders.slice(0, 8),
    };
  });
  expect(report.overflow, `${label} horizontal overflow; offenders: ${report.offenders.join(' | ')}`).toBeLessThanOrEqual(0);
}

test('slice 16 panes are axe-clean in light twilight and system-dark registers', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'axe and register parity are captured once on desktop');
  const original = readFeatureMap();
  try {
    writeFeatureMap(featureState(original, true));
    await login(page);
    for (const [href, name, head] of SLICE16_PANES) {
      await page.goto(href);
      // Every pane titles itself with a real heading now — four of these were
      // `<span class="scribe-panel-head">` before this slice and sat outside
      // the heading outline entirely.
      await expect(page.getByRole('heading', { level: 2, name: head })).toHaveCount(1);

      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
      await expectAxeClean(page, `${name} light`);
      await shot(page, 'comparisons', `s16-${name}-light`);

      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
      await expectAxeClean(page, `${name} twilight`);
      await shot(page, 'comparisons', `s16-${name}-twilight`);

      await page.emulateMedia({ colorScheme: 'dark' });
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'system'));
      await expectAxeClean(page, `${name} system-dark`);
      await shot(page, 'comparisons', `s16-${name}-system-dark`);
      await page.emulateMedia({ colorScheme: 'light' });
    }
  } finally {
    writeFeatureMap(original);
  }
});

test('slice 16 panes keep one boolean idiom and fit their width', async ({ page }, info: TestInfo) => {
  const original = readFeatureMap();
  const folder = info.project.name === 'mobile' ? 'mobile' : 'desktop';
  try {
    writeFeatureMap(featureState(original, true));
    await login(page);
    for (const [href, name] of SLICE16_PANES) {
      await page.goto(href);
      await expectOneCurrent(page, name);
      await expectNoOverflow(page, `${name} @ ${info.project.name}`);

      // The design models exactly one boolean control, the DS Switch, and it
      // renders role="switch" (components/forms/Switch.jsx). No pane may mix a
      // gem checkbox or a bare checkline alongside it.
      const switches = page.locator('input.switch[role="switch"]');
      const legacy = page.locator('input.gem-check, .toggle-stack, .checkline');
      await expect(legacy).toHaveCount(0);
      if (['privacy', 'appearance', 'reading', 'composing', 'notifications'].includes(name)) {
        expect(await switches.count(), `${name} should render switches`).toBeGreaterThan(0);
      }

      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
      await shot(page, folder, `s16-${name}-light`);
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
      await shot(page, folder, `s16-${name}-twilight`);
    }
  } finally {
    writeFeatureMap(original);
  }
});

test('refused set-password re-renders inline on the connections pane', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'the 422 round-trip is captured once on desktop');
  // Before slice 16 this path caught ValidationException and redirected with a
  // flash, so the pane's own error slot could never render and the
  // confirm-field message had nowhere to go. Only an account with no password
  // sees the form, so this walks the seeded OAuth-only member if one exists.
  await login(page);
  await page.goto('/settings/connections');
  const form = page.locator('form[action="/settings/connections/set-password"]');
  if (await form.count() === 0) {
    test.skip(true, 'seed has no password-less account; covered by AppOAuthTest');
  }
  await form.locator('input[name="new_password"]').fill('brandnewpass');
  await form.locator('input[name="new_password_confirm"]').fill('branddifferent');
  await form.locator('button[type="submit"]').click();
  await page.waitForLoadState('load');
  await expect(page.getByText('The passwords do not match.', { exact: true })).toBeVisible();
  // The whole pane comes back, not a bare form.
  await expect(page.getByRole('heading', { level: 2, name: 'Connected accounts' })).toHaveCount(1);
  await shot(page, 'desktop', 's16-connections-422');
});

// ─── Slice 17: Boards, Drafts and Account lifecycle ─────────────────────────
const SLICE17_PANES = [
  ['/settings/boards', 'boards', 'Organize your boards'],
  ['/drafts', 'drafts', 'Drafts'],
  ['/settings/account/lifecycle', 'account', 'Export account data'],
] as const;

test('slice 17 panes are axe-clean in light twilight and system-dark registers', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'axe and register parity are captured once on desktop');
  const original = readFeatureMap();
  try {
    writeFeatureMap(featureState(original, true));
    await login(page);
    for (const [href, name, head] of SLICE17_PANES) {
      await page.goto(href);
      await expect(page.getByRole('heading', { level: 2, name: head })).toHaveCount(1);

      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
      await expectAxeClean(page, `${name} light`);
      await shot(page, 'comparisons', `s17-${name}-light`);

      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
      await expectAxeClean(page, `${name} twilight`);
      await shot(page, 'comparisons', `s17-${name}-twilight`);

      await page.emulateMedia({ colorScheme: 'dark' });
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'system'));
      await expectAxeClean(page, `${name} system-dark`);
      await shot(page, 'comparisons', `s17-${name}-system-dark`);
      await page.emulateMedia({ colorScheme: 'light' });
    }
  } finally {
    writeFeatureMap(original);
  }
});

test('slice 17 panes fit their width and keep the drafts hooks', async ({ page }, info: TestInfo) => {
  const original = readFeatureMap();
  const folder = info.project.name === 'mobile' ? 'mobile' : 'desktop';
  try {
    writeFeatureMap(featureState(original, true));
    await login(page);
    for (const [href, name] of SLICE17_PANES) {
      await page.goto(href);
      await expectOneCurrent(page, name);
      await expectNoOverflow(page, `${name} @ ${info.project.name}`);
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'light'));
      await shot(page, folder, `s17-${name}-light`);
      await page.locator('html').evaluate((node) => node.setAttribute('data-theme', 'dark'));
      await shot(page, folder, `s17-${name}-twilight`);
    }

    // The pane head must survive composer.js, which wipes [data-drafts-list].
    await page.goto('/drafts');
    await expect(page.getByRole('heading', { level: 2, name: 'Drafts' })).toBeVisible();
    await expect(page.locator('[data-drafts-list]')).toHaveCount(1);
    await expect(page.locator('.account-draft-head')).toHaveCount(1);
    // The moderation queue's vocabulary must not be back on this pane.
    await expect(page.locator('.report-row, .report-excerpt')).toHaveCount(0);
  } finally {
    writeFeatureMap(original);
  }
});

test('slice 17 lifecycle scopes a refused deletion to its own form', async ({ page }, info: TestInfo) => {
  test.skip(info.project.name !== 'desktop', 'the 422 round-trip is captured once on desktop');
  await login(page);
  await page.goto('/settings/account/lifecycle');
  const remove = page.locator('form[action="/settings/account/delete/request"]');
  await remove.locator('input[name="current_password"]').fill('definitely-wrong');
  await remove.locator('button[type="submit"]').click();
  await page.waitForLoadState('load');

  // The message lands on the delete form, not on the deactivate form above it.
  await expect(page.locator('#err-delete-current_password')).toBeVisible();
  await expect(page.locator('#err-deactivate-current_password')).toHaveCount(0);
  await shot(page, 'desktop', 's17-lifecycle-422');
});
