import { test, expect, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const engine = process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium';
test.use({ browserName: engine, colorScheme: 'light' });
const artifact = (info: TestInfo, name: string) => {
  const directory = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/branding-public-theme-coverage-2026-10-31', engine, info.project.name);
  mkdirSync(directory, { recursive: true });
  return path.join(directory, name);
};
function fixture(action = 'reset') {
  return execFileSync('php', ['tests/browser/theming-remediation-fixture.php', action], { cwd: root, env: process.env, encoding: 'utf8' });
}
async function login(page: Page, username = 'admin') {
  await page.goto('/login');
  await page.locator('[name="email"]').fill(`${username}@retro.test`);
  await page.locator('[name="password"]').fill('password123');
  await page.locator('form[action="/login"] button[type="submit"]').click();
  await page.waitForURL(url => url.pathname !== '/login');
}
async function save(page: Page) {
  const submit = async () => {
    const [response] = await Promise.all([
      page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/admin/branding'),
      page.getByRole('button', { name: 'Save branding', exact: true }).click(),
    ]);
    await page.waitForLoadState('networkidle');
    return response.status();
  };
  let status = await submit();
  if (status === 422) {
    await expect(page.locator('.branding-contrast-warning')).toBeVisible();
    await page.locator('[name="contrast_ack"]').check();
    status = await submit();
  }
  expect(status).toBe(303);
}
async function activatePackage(page: Page) {
  await page.goto('/admin/themes');
  const row = page.locator('tbody tr').filter({ has: page.locator('code', { hasText: /^acme\/theme-evidence$/ }) });
  await row.locator('[name="current_password"]').fill('password123');
  await row.getByRole('button', { name: 'Activate', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Theme activated.');
}
async function palette(page: Page, preset: string, mode: string, primary: string, accent: string) {
  const bases = JSON.parse((await page.locator('[data-brand-palettes]').getAttribute('data-brand-palettes'))!);
  return JSON.parse(execFileSync('php', ['-r', 'require "vendor/autoload.php"; echo json_encode(App\\Support\\BrandPalette::withColours(json_decode($argv[1], true), $argv[2], $argv[3]));', JSON.stringify(bases[preset][mode]), primary, accent], { cwd: root, encoding: 'utf8' }));
}
const rgb = (hex: string) => `rgb(${[1,3,5].map(i => parseInt(hex.slice(i,i+2),16)).join(', ')})`;
async function pair(locator: Locator) {
  await expect(locator).toBeVisible();
  return locator.evaluate(element => {
    const css = getComputedStyle(element);
    const lum = (s: string) => (s.match(/[\d.]+/g) ?? []).slice(0,3).map(Number).reduce((sum, n, i) => {
      const v = n / 255; return sum + (v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4) * [.2126,.7152,.0722][i];
    }, 0);
    let background = css.backgroundColor;
    for (let parent = element.parentElement; ['transparent', 'rgba(0, 0, 0, 0)'].includes(background) && parent; parent = parent.parentElement) background = getComputedStyle(parent).backgroundColor;
    const a = lum(css.color), b = lum(background);
    return { colour: css.color, background, ratio: (Math.max(a,b)+.05)/(Math.min(a,b)+.05), outline: css.outlineColor, outlineStyle: css.outlineStyle };
  });
}
async function noOverflow(page: Page) {
  await page.evaluate(() => document.fonts.ready);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
}
async function shot(page: Page, info: TestInfo, name: string) {
  await noOverflow(page);
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.screenshot({ path: artifact(info, `${name}.png`), fullPage: true, animations: 'disabled' });
}
test.beforeEach(() => { fixture(); fixture('reader-unread'); });

for (const base of ['classic', 'retro', 'package']) {
  for (const mode of ['light', 'dark'] as const) {
    test(`${base} ${mode}: admin save reaches public selections and honours member preference`, async ({ page, browser, baseURL }, info) => {
      await login(page);
      if (base === 'package') await activatePackage(page);
      await page.goto('/admin/branding');
      const preset = base === 'classic' ? 'classic' : 'retro';
      const primary = mode === 'light' ? '#005fcc' : '#6cafff';
      const accent = mode === 'light' ? '#a33300' : '#f08d57';
      await page.locator('[name="theme_preset"]').selectOption(preset);
      await page.locator('[name="theme_default"]').selectOption(mode);
      await page.locator('[name="color_primary"]').fill(primary);
      await page.locator('[name="color_accent"]').fill(accent);
      await page.locator('[name="site_name"]').fill('Brand propagation evidence');
      const expected = await palette(page, preset, mode, primary, accent);
      const sample = page.locator('[data-brand-preview]');
      const sampleNav = sample.locator('.forum-bar-surface.is-active');
      await expect(sampleNav).toHaveCSS('background-color', rgb(expected['--brand-subtle']));
      await expect(sampleNav).toHaveCSS('color', rgb(expected['--on-brand-subtle']));
      await expect(sample.locator('.board-rail-item.is-active')).toHaveCSS('border-left-color', rgb(accent));
      const pairs: Record<string, unknown> = {};
      for (const selector of ['.forum-bar-surface.is-active', '.board-rail-item.is-active', '.inbox-tab.is-active', '.board-rail-unread', '.btn', '.brand-preview-accent']) {
        const result = await pair(sample.locator(selector));
        expect(result.ratio, selector).toBeGreaterThanOrEqual(4.5);
        pairs[`preview ${selector}`] = result;
      }
      await sample.locator('.btn').hover();
      await expect(sample.locator('.btn')).toHaveCSS('background-color', rgb(expected['--brand-primary-hover']));
      expect((await pair(sample.locator('.btn'))).ratio).toBeGreaterThanOrEqual(4.5);
      await page.mouse.down();
      await expect(sample.locator('.btn')).toHaveCSS('background-color', rgb(expected['--brand-primary-press']));
      await page.mouse.up();
      await sample.locator('.input').focus();
      await expect(sample.locator('.input')).toHaveCSS('outline-color', rgb(primary));
      await shot(page, info, `${base}-${mode}-preview`);
      const guest = await browser.newContext({ baseURL, viewport: info.project.use.viewport, javaScriptEnabled: false, colorScheme: 'light' });
      const member = await browser.newContext({ baseURL, viewport: info.project.use.viewport, javaScriptEnabled: false, colorScheme: mode === 'light' ? 'dark' : 'light' });
      try {
        const gp = await guest.newPage();
        await gp.goto('/');
        const oldColour = await gp.locator('.forum-bar-signin').evaluate(el => getComputedStyle(el).backgroundColor);
        await save(page);
        // Existing tabs change on the next page load, not by a live broadcast.
        expect(await gp.locator('.forum-bar-signin').evaluate(el => getComputedStyle(el).backgroundColor)).toBe(oldColour);
        await gp.reload();
        await expect(gp.locator('html')).toHaveAttribute('data-theme', mode);
        await expect(gp.locator('.forum-bar-signin')).toHaveCSS('background-color', rgb(primary));
        await expect(gp.locator('.forum-bar-surface.is-active')).toHaveCSS('background-color', rgb(expected['--brand-subtle']));
        await expect(gp.locator('.board-rail-item.is-active').first()).toHaveCSS('border-left-color', rgb(accent));
        await expect(gp.locator('.forum-bar-brand')).toContainText('Brand propagation evidence');
        pairs.guestNavigation = await pair(gp.locator(info.project.name === 'mobile' ? '.board-rail-item.is-active' : '.forum-bar-surface.is-active').first());
        await shot(gp, info, `${base}-${mode}-guest-no-js`);
        const mp = await member.newPage();
        await login(mp, 'bob');
        await mp.goto('/inbox');
        const memberMode = mode === 'light' ? 'dark' : 'light';
        const memberPalette = await palette(page, preset, memberMode, primary, accent);
        await expect(mp.locator('.forum-bar-surface.is-active')).toHaveCSS('background-color', rgb(memberPalette['--brand-subtle']));
        await expect(mp.locator('.forum-bar-surface.is-active')).toHaveCSS('color', rgb(memberPalette['--on-brand-subtle']));
        pairs.memberNavigation = await pair(mp.locator('.forum-bar-surface.is-active'));
        expect((pairs.memberNavigation as any).ratio).toBeGreaterThanOrEqual(4.5);
        const count = mp.locator('.board-rail-unread').first();
        pairs.memberUnreadCount = await pair(count);
        expect((pairs.memberUnreadCount as any).ratio).toBeGreaterThanOrEqual(4.5);
        await expect(count).toHaveCSS('background-color', rgb(memberPalette['--gold-soft']));
        await mp.locator('.thread-title').first().hover();
        await expect(mp.locator('.thread-title').first()).toHaveCSS('color', rgb(primary));
        await mp.keyboard.press('Tab');
        await mp.locator('.forum-bar-surface.is-active').focus();
        await expect(mp.locator('.forum-bar-surface.is-active')).toHaveCSS('outline-color', rgb(primary));
        await shot(mp, info, `${base}-${mode}-member-inbox-no-js`);
        await mp.goto('/feed');
        await expect(mp.locator('html')).toHaveAttribute('data-theme', 'system');
        await mp.locator('.feed-view-menu > summary').click();
        const selected = mp.locator('.feed-view-menu .inbox-tab.is-active');
        await expect(selected).toBeVisible();
        await expect(selected).toHaveCSS('background-color', rgb(primary));
        expect((await pair(selected)).ratio).toBeGreaterThanOrEqual(4.5);
        pairs.memberFeed = await pair(selected);
        await shot(mp, info, `${base}-${mode}-member-feed-no-js`);
        // The member uses the other mode, but retains the same operator overrides.
        expect(await mp.locator('html').evaluate(el => getComputedStyle(el).getPropertyValue('--brand-subtle').trim())).toBe(memberPalette['--brand-subtle']);
        writeFileSync(artifact(info, `${base}-${mode}-pairs.json`), JSON.stringify(pairs, null, 2));
      } finally { await guest.close(); await member.close(); }
    });
  }
}

test('unsaved preview matches PHP for all derived roles and clears saved overrides', async ({ page }, info) => {
  await login(page);
  await page.goto('/admin/branding');
  await page.locator('[name="color_primary"]').fill('#005fcc');
  await page.locator('[name="color_accent"]').fill('#a33300');
  await save(page);
  const bodyBefore = () => page.locator('body').evaluate(el => getComputedStyle(el).backgroundColor);
  for (const base of ['classic', 'retro', 'package']) {
    if (base === 'package') { await activatePackage(page); await page.goto('/admin/branding'); }
    const body = await bodyBefore();
    const preset = base === 'classic' ? 'classic' : 'retro';
    await page.locator('[name="theme_preset"]').selectOption(preset);
    for (const mode of ['light', 'dark'] as const) {
      await page.locator('[name="theme_default"]').selectOption(mode);
      const statusBefore = await pair(page.locator('[data-brand-preview] .chip-decision_made'));
      for (const [primary, accent] of [['#005fcc', '#a33300'], ['#ffffff', '#000000'], ['#777777', '#ffff00'], ['', '#a33300'], ['#005fcc', ''], ['', '']]) {
        await page.locator('[name="color_primary"]').fill(primary);
        await page.locator('[name="color_accent"]').fill(accent);
        const expected = await palette(page, preset, mode, primary, accent);
        const inline = await page.locator('[data-brand-preview]').evaluate(el => Object.fromEntries(Array.from((el as HTMLElement).style).filter(key => !key.startsWith('--preview-')).map(key => [key, (el as HTMLElement).style.getPropertyValue(key)])));
        expect(inline).toEqual(expected);
        expect(await bodyBefore()).toBe(body);
        expect(await pair(page.locator('[data-brand-preview] .chip-decision_made'))).toEqual(statusBefore);
      }
    }
  }
  // The public package may change the page, but candidate-only edits never do.
  await page.locator('[name="theme_default"]').selectOption('system');
  await page.emulateMedia({ colorScheme: 'dark' });
  const expected = await palette(page, 'retro', 'dark', '', '');
  await expect(page.locator('[data-brand-preview]')).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
  expect(await page.locator('[data-brand-preview]').evaluate(el => getComputedStyle(el).getPropertyValue('--brand').trim())).toBe(expected['--brand']);
  await shot(page, info, 'cleared-package-system-preview');
});

test('no-JavaScript admin save and clear restore the public base palette', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({ baseURL, viewport: info.project.use.viewport, javaScriptEnabled: false });
  try {
    const page = await context.newPage();
    await login(page);
    await page.goto('/admin/branding');
    await page.locator('[name="color_primary"]').fill('#005fcc');
    await page.locator('[name="color_accent"]').fill('#a33300');
    await save(page);
    await expect(page.locator('[data-brand-preview] .inbox-tab.is-active')).toHaveCSS('background-color', rgb('#005fcc'));
    await page.locator('[name="color_primary"]').fill('');
    await page.locator('[name="color_accent"]').fill('');
    await save(page);
    await page.goto('/feed');
    await page.locator('.feed-view-menu > summary').click();
    await expect(page.locator('.feed-view-menu .inbox-tab.is-active')).toHaveCSS('background-color', rgb('#2e4a3a'));
    expect(await page.locator('link[href^="/brand.css"]').count()).toBe(0);
    await shot(page, info, 'cleared-public-defaults-no-js');
  } finally { await context.close(); }
});

test('cleared Retro dark and System defaults retain readable selected menus without JavaScript', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({ baseURL, viewport: info.project.use.viewport, javaScriptEnabled: false, colorScheme: 'dark' });
  try {
    const page = await context.newPage();
    await login(page);
    await page.goto('/admin/branding');
    await page.locator('[name="theme_preset"]').selectOption('retro');
    await page.locator('[name="color_primary"]').fill('#005fcc');
    await page.locator('[name="color_accent"]').fill('#a33300');
    await save(page);
    const pairs: Record<string, unknown> = {};
    for (const mode of ['dark', 'system']) {
      await page.goto('/admin/branding');
      await page.locator('[name="theme_default"]').selectOption(mode);
      await page.locator('[name="color_primary"]').fill('');
      await page.locator('[name="color_accent"]').fill('');
      await save(page);
      const preview = page.locator('[data-brand-preview] .inbox-tab.is-active');
      await expect(preview).toHaveCSS('background-color', rgb('#d99b58'));
      pairs[`${mode} preview`] = await pair(preview);
      expect.soft((pairs[`${mode} preview`] as any).ratio).toBeGreaterThanOrEqual(4.5);
      await shot(page, info, `cleared-retro-${mode}-preview-no-js`);
      await page.goto('/feed');
      await page.locator('.feed-view-menu > summary').click();
      const selected = page.locator('.feed-view-menu .inbox-tab.is-active');
      await expect(selected).toHaveCSS('background-color', rgb('#d99b58'));
      pairs[`${mode} public`] = await pair(selected);
      expect.soft((pairs[`${mode} public`] as any).ratio).toBeGreaterThanOrEqual(4.5);
      await shot(page, info, `cleared-retro-${mode}-feed-no-js`);
    }
    writeFileSync(artifact(info, 'cleared-retro-dark-pairs.json'), JSON.stringify(pairs, null, 2));
  } finally { await context.close(); }
});

for (const mode of ['light', 'dark'] as const) {
  test(`Messages ${mode}: filled selections and new-message hover retain readable labels`, async ({ page, browser, baseURL }, info) => {
    await login(page);
    await page.goto('/admin/branding');
    const primary = mode === 'light' ? '#ffffff' : '#000000';
    await page.locator('[name="color_primary"]').fill(primary);
    await save(page);
    const context = await browser.newContext({ baseURL, viewport: info.project.use.viewport, javaScriptEnabled: false, colorScheme: mode });
    try {
      const member = await context.newPage();
      await login(member, 'bob');
      await member.goto('/messages');
      const filter = member.locator('.dm-listpane-filters .pill.is-active');
      await expect(filter).toHaveCSS('background-color', rgb(primary));
      const selected = await pair(filter);
      expect.soft(selected.ratio).toBeGreaterThanOrEqual(4.5);
      await shot(member, info, `messages-${mode}-selected`);
      const conversation = JSON.parse(fixture('reader-conversation'));
      await member.goto(`/messages/${conversation.conversation_id}`);
      const pill = member.locator('.dm-newpill');
      // Render the real server-provided element in its new-message state. This
      // qualifies its colour/hover, not polling or receipt of a new message.
      await pill.evaluate(element => { (element as HTMLElement).hidden = false; });
      await pill.hover();
      await expect(pill).toHaveCSS('background-color', rgb(primary));
      const hovered = await pair(pill);
      expect.soft(hovered.ratio).toBeGreaterThanOrEqual(4.5);
      await pill.screenshot({ path: artifact(info, `messages-${mode}-new-message-hover.png`) });
      writeFileSync(artifact(info, `messages-${mode}-pairs.json`), JSON.stringify({ selected, hovered }, null, 2));
    } finally { await context.close(); }
  });
}
