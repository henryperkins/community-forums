import { test, expect, type Page, type TestInfo } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/inbox-accessibility-hardening');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

test.beforeEach(() => {
  if (!/^retroboards_e2e_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '')) {
    throw new Error('Use a private retroboards_e2e_* database for Inbox evidence.');
  }
  execFileSync('php', ['tests/browser/member-surfaces-fixture.php'], { cwd: ROOT, env: process.env });
});

function output(info: TestInfo, name: string) {
  const dir = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
}

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function settle(page: Page) {
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => new Promise<void>(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve()))));
}

test('System dark keeps Inbox references readable like explicit Dark', async ({ page }, info) => {
  await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
  await login(page);
  await page.goto('/settings/appearance');
  await page.locator('input[name="theme"][value="system"]').check({ force: true });
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
  await page.goto('/inbox?scope=for_you&order=active');
  await settle(page);
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'system');

  const scans = [];
  for (const theme of ['system', 'dark']) {
    await page.evaluate(value => { document.documentElement.dataset.theme = value; }, theme);
    await settle(page);
    const scan = await new AxeBuilder({ page }).include('[data-inbox-list]').include('[data-subheader]')
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    scans.push({ theme, violations: scan.violations });
    fs.writeFileSync(output(info, 'theme-axe.json'), JSON.stringify(scans, null, 2));
    expect(scan.violations, `${theme} must keep text readable on the dark ground`).toEqual([]);
    if (theme === 'system') await page.screenshot({ path: output(info, 'system-dark.png'), animations: 'disabled' });
  }
});

test('character shortcuts leave member chrome alone', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=for_you&order=active');
  const first = page.locator('[data-inbox-row]').first();
  const starred = await first.getAttribute('data-inbox-starred');
  await page.evaluate(() => {
    const state = { keys: [] as { key: string; prevented: boolean }[], submissions: 0 };
    (window as any).inboxOutsideKeys = state;
    window.addEventListener('keydown', event => state.keys.push({ key: event.key, prevented: event.defaultPrevented }));
    // Observe the real native-submit boundary without allowing a broken shortcut
    // to navigate away before its event can be inspected.
    document.addEventListener('submit', event => {
      if ((event.target as Element).matches('[data-inbox-action]')) {
        state.submissions++;
        event.preventDefault();
      }
    }, true);
  });
  const boards = page.getByRole('navigation', { name: 'Primary', exact: true }).getByRole('link', { name: 'Boards', exact: true });
  for (const control of [boards, first.locator('.star-toggle')]) {
    await control.focus();
    for (const key of ['s', 'e', '#', 'j', 'k', 'o']) await page.keyboard.press(key);
    await expect(control).toBeFocused();
  }
  const seen = await page.evaluate(() => (window as any).inboxOutsideKeys);
  expect(seen.submissions, 'A chrome-focused letter must not submit a topic action').toBe(0);
  expect(seen.keys).toHaveLength(12);
  expect(seen.keys.every((key: { prevented: boolean }) => !key.prevented)).toBe(true);
  await expect(first).toHaveAttribute('data-inbox-starred', starred!);
});

test('queue and reading-pane focus retain topic shortcuts', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=for_you&order=active');
  const first = page.locator('[data-inbox-row]').first();
  const id = await first.getAttribute('data-thread-id');
  const starred = await first.getAttribute('data-inbox-starred');
  await first.locator('[data-inbox-preview-url]').focus();
  await Promise.all([page.waitForNavigation(), page.keyboard.press('s')]);
  await expect(page.locator(`[data-thread-id="${id}"]`)).toHaveAttribute('data-inbox-starred', starred === '1' ? '0' : '1');

  await page.locator('[data-inbox-preview-url]').first().click();
  const heading = page.locator('[data-inbox-preview] > header > h2');
  await expect(heading).toBeFocused();
  const previous = await page.locator('[data-inbox-preview]').getAttribute('data-inbox-preview');
  await page.keyboard.press('j');
  await expect(page.locator('[data-inbox-preview]')).not.toHaveAttribute('data-inbox-preview', previous!);
  await expect(heading).toBeFocused();
  const second = await page.locator('[data-inbox-preview]').getAttribute('data-inbox-preview');
  await page.keyboard.press('j');
  await expect(page.locator('[data-inbox-preview]')).not.toHaveAttribute('data-inbox-preview', second!);
  await expect(heading).toBeFocused();
});

test('topic actions follow the focused topic instead of an older cursor', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=for_you&order=active');
  const rows = page.locator('[data-inbox-row]');
  await rows.first().locator('[data-inbox-preview-url]').click();
  await expect(page.locator('[data-inbox-preview] > header > h2')).toBeFocused();
  // On a phone the queue is covered while reading; returning keeps its cursor.
  if (await page.locator('[data-inbox-back]').isVisible()) await page.locator('[data-inbox-back]').click();
  const target = rows.nth(1);
  const id = await target.getAttribute('data-thread-id');
  const starred = await target.getAttribute('data-inbox-starred');
  await target.locator('[data-inbox-preview-url]').focus();
  await Promise.all([page.waitForNavigation(), page.keyboard.press('s')]);
  await expect(page.locator(`[data-thread-id="${id}"]`)).toHaveAttribute('data-inbox-starred', starred === '1' ? '0' : '1');
});

test('retained Unread previews never triage another topic or capture unrelated controls', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=unread&order=active');
  const rows = page.locator('[data-inbox-row]');
  const id = await rows.first().getAttribute('data-thread-id');
  await rows.first().locator('[data-inbox-preview-url]').click();
  const heading = page.locator('[data-inbox-preview] > header > h2');
  await expect(heading).toBeFocused();
  await expect(page.locator(`[data-inbox-row][data-thread-id="${id}"]`)).toHaveCount(0);
  await page.evaluate(() => {
    (window as any).inboxUnexpectedSubmissions = [];
    document.addEventListener('submit', event => {
      if ((event.target as Element).matches('[data-inbox-action]')) {
        (window as any).inboxUnexpectedSubmissions.push((event.target as HTMLFormElement).action);
        event.preventDefault();
      }
    }, true);
  });
  for (const key of ['s', 'e', '#']) await page.keyboard.press(key);
  expect(await page.evaluate(() => (window as any).inboxUnexpectedSubmissions)).toEqual([]);
  // The full-topic link and Back control own their keys, not the preview.
  for (const control of [page.locator('.inbox-preview-open'), page.locator('[data-inbox-back]')]) {
    if (!await control.isVisible()) continue;
    await control.focus();
    for (const key of ['s', 'e', '#']) await page.keyboard.press(key);
  }
  expect(await page.evaluate(() => (window as any).inboxUnexpectedSubmissions)).toEqual([]);
  await heading.focus();
  await page.keyboard.press('j');
  await expect(page.locator('[data-inbox-preview]')).not.toHaveAttribute('data-inbox-preview', id!);
  await expect(heading).toBeFocused();
});

test('a pending keyboard preview respects focus moved to another control', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=for_you&order=active');
  let release!: () => void;
  const gate = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/inbox/preview/*', async route => {
    const response = await route.fetch();
    await gate;
    await route.fulfill({ response });
  });
  const topic = page.locator('[data-inbox-preview-url]').first();
  await topic.focus();
  await page.keyboard.press('o');
  await expect(page.locator('[data-inbox-reading]')).toHaveAttribute('aria-busy', 'true');
  const boards = page.getByRole('navigation', { name: 'Primary', exact: true }).getByRole('link', { name: 'Boards', exact: true });
  await boards.focus();
  release();
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  await expect(boards).toBeFocused();
});

test('focused list navigation owns immediate opening before a preview responds', async ({ page }) => {
  await login(page);
  await page.goto('/inbox?scope=for_you&order=active');
  let release!: () => void;
  const gate = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/inbox/preview/*', async route => {
    await gate;
    try { await route.continue(); } catch { /* Opening cancels the older request. */ }
  });
  const links = page.locator('[data-inbox-preview-url]');
  const secondId = await page.locator('[data-inbox-row]').nth(1).getAttribute('data-thread-id');
  await page.locator('[data-inbox-list]').focus();
  await page.keyboard.press('j');
  await expect(links.first()).toBeFocused();
  await page.keyboard.press('j');
  await expect(links.nth(1)).toBeFocused();
  await page.keyboard.press('Enter');
  release();
  await expect(page).toHaveURL(new RegExp(`[?&]t=${secondId}(?:&|$)`));
  await expect(page.locator('[data-inbox-preview]')).toHaveAttribute('data-inbox-preview', secondId!);
  await expect(page.locator('[data-inbox-preview] > header > h2')).toBeFocused();
});

test('enlarged Inbox text keeps controls and queue content within their panes', async ({ page }, info) => {
  test.setTimeout(120_000);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await login(page);
  const measurements = [];
  for (const scope of ['for_you', 'needs_answer']) {
    await page.goto(`/inbox?scope=${scope}&order=commended`);
    for (const width of [320, 390, 861, 1280, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      for (const density of ['comfortable', 'compact']) {
        for (const rootSize of [16, 18, 32, 36]) {
          await page.evaluate(({ density, rootSize }) => {
            document.documentElement.dataset.density = density;
            document.documentElement.style.fontSize = `${rootSize}px`;
          }, { density, rootSize });
          await settle(page);
          const geometry = await page.evaluate(() => {
            const visible = (el: Element) => el.getBoundingClientRect().width > 0 && getComputedStyle(el).visibility !== 'hidden';
            const list = document.querySelector('[data-inbox-list]')!;
            const controls = [...document.querySelectorAll('[data-subheader] summary, .thread-row-select, .star-toggle, [data-inbox-row-menu] > summary')].filter(visible);
            const viewLeft = document.querySelector('.inbox-view-bar')!.getBoundingClientRect().left;
            const identityOverlaps = [...document.querySelectorAll('.inbox-title-line > *')].filter(el => {
              const range = document.createRange();
              range.selectNodeContents(el);
              return range.getBoundingClientRect().right > viewLeft + 1;
            }).map(el => el.textContent?.trim());
            return {
              pageOverflow: document.documentElement.scrollWidth - innerWidth,
              listOverflow: list.scrollWidth - list.clientWidth,
              clippedControls: controls.filter(el => el.scrollWidth > el.clientWidth + 1).map(el => el.textContent?.trim()),
              smallControls: controls.filter(el => { const b = el.getBoundingClientRect(); return b.width < 43.5 || b.height < 43.5; }).map(el => el.getAttribute('aria-label')),
              identityOverlaps,
              scopeLines: [...document.querySelectorAll('.inbox-selected-scope')].filter(visible).map(el => el.getBoundingClientRect().height / parseFloat(getComputedStyle(el).lineHeight)),
            };
          });
          const state = { scope, width, density, rootSize, ...geometry };
          measurements.push(state);
          fs.writeFileSync(output(info, 'text-resizing.json'), JSON.stringify(measurements, null, 2));
          expect(geometry.pageOverflow, JSON.stringify(state)).toBeLessThanOrEqual(1);
          expect(geometry.listOverflow, JSON.stringify(state)).toBeLessThanOrEqual(1);
          expect(geometry.clippedControls, JSON.stringify(state)).toEqual([]);
          expect(geometry.smallControls, JSON.stringify(state)).toEqual([]);
          expect(geometry.identityOverlaps, JSON.stringify(state)).toEqual([]);
          expect(geometry.scopeLines.length, 'The current view must remain visible').toBeGreaterThan(0);
          expect(geometry.scopeLines.every(lines => lines <= (rootSize > 32 ? 3.1 : 2.1)), `A two-word scope must not become a column of letters: ${JSON.stringify(state)}`).toBe(true);
          if (density === 'comfortable' && ((width === 320 && rootSize === 32) || (width === 1440 && rootSize === 16))) {
            await page.screenshot({ path: output(info, `${scope}-${width}-${rootSize}px.png`), animations: 'disabled' });
          }
        }
      }
    }
  }
});
