import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Every icon is a Fluent System Icon (ADR 0060, completed 2026-11-09).
 *
 * The notification type icons, the composer's formatting buttons, the
 * verification emblems and the marks that were still drawn by hand (search
 * fields, chevrons, the structure arrows, the private-board lock, the pane
 * toggles, the admin bell and the admin console's flash marks) draw the set's
 * artwork. Marks with a size of their own keep it; icon buttons and rows follow
 * the member's icon size; nothing draws a stroked or hand-made icon. Data:
 * tests/browser/fluent-icons-fixture.php.
 *
 *   DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
 *   npx playwright test fluent-icons-complete.spec.ts
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const OUT = process.env.RB_EVIDENCE_DIR
  ? path.resolve(repoRoot, process.env.RB_EVIDENCE_DIR)
  : path.resolve(repoRoot, 'docs/evidence/fluent-icons-complete-2026-11-09');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

// The fixture member's icon size is changed and restored, so the tests run in order.
test.describe.configure({ mode: 'serial' });

const MEMBER = 'fluent-notices@retro.test';
const ADMIN = 'admin@retro.test';
// Inline SVG that is not an icon: the community's default mark, a poll
// result's bar, a branding colour swatch and the retired profile ornament.
const ARTWORK = ['forum-bar-mark', 'auth-brand-star', 'admin-bar-star', 'brand-mark', 'poll-result-progress', 'branding-revision-swatch', 'profile-cover-star'];
const ACTION_ICONS: Record<string, string> = {
  bold: 'text-bold', italic: 'text-italic', strike: 'text-strikethrough', code: 'code', quote: 'quote', h2: 'heading',
  list: 'list-bullet', orderedList: 'list-numbered', codeblock: 'code-block', spoiler: 'spoiler', link: 'link',
};
const NOTIFICATION_ICONS = ['announcement', 'badge', 'comment', 'direct-message', 'follow', 'mention', 'new-topic', 'reaction', 'reply', 'shield', 'solved'];

function fixture(command: 'reset' | 'verify' | 'teardown'): Record<string, unknown> {
  const out = execFileSync('php', ['tests/browser/fluent-icons-fixture.php', command], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
  return JSON.parse(out.toString());
}

test.beforeAll(() => {
  fixture('reset');
});

test.afterAll(async ({ browser }) => {
  const page = await browser.newPage();
  await signIn(page, MEMBER);
  await setIconSize(page, 'standard');
  await page.close();
  fixture('teardown');
});

function shot(project: string, name: string) {
  fs.mkdirSync(OUT, { recursive: true });
  return path.join(OUT, `${project}-${name}.png`);
}

async function signIn(page: Page, email: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('form.auth-form button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}

async function setIconSize(page: Page, size: 'standard' | 'large') {
  await page.goto('/settings/appearance');
  await page.locator('label.choice-card', { hasText: size === 'large' ? 'Larger' : 'Standard' }).click();
  await expect(page.locator(`input[name="icon_size"][value="${size}"]`)).toBeChecked();
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL((u) => u.pathname === '/settings/appearance');
  await expect(page.locator('html')).toHaveAttribute('data-icon-size', size);
}

// Sizes are the computed CSS lengths, so an opening animation's transform
// does not skew them.
async function iconBox(icon: Locator) {
  return icon.evaluate((svg) => {
    const style = getComputedStyle(svg);
    return {
      cls: svg.getAttribute('class'),
      width: Math.round(parseFloat(style.width) * 100) / 100,
      height: Math.round(parseFloat(style.height) * 100) / 100,
      viewBox: svg.getAttribute('viewBox'),
      fill: style.fill,
      stroke: style.stroke,
    };
  });
}

/** Every drawn svg is filled Fluent artwork or listed artwork that is not an icon. */
async function strayIcons(page: Page) {
  return page.locator('svg').evaluateAll((svgs, artwork) => svgs
    .filter((svg) => svg.getClientRects().length > 0)
    .filter((svg) => !artwork.some((name) => svg.classList.contains(name)))
    .filter((svg) => !svg.classList.contains('icon') || [svg, ...svg.querySelectorAll('path')]
      .map((el) => getComputedStyle(el))
      .some((style) => style.fill === 'none' || style.stroke !== 'none'))
    .map((svg) => `${svg.getAttribute('class') ?? '(no class)'} in ${String(svg.parentElement?.className ?? '')}`), ARTWORK);
}

async function openNewTopicComposer(page: Page) {
  await page.goto('/c/general');
  const details = page.locator('details.composer-details#new-topic');
  const promoted = page.locator('.board-identity-actions [data-open-topic-composer]');
  const fab = page.locator('a.fab[href="#new-topic"]');
  const summary = details.locator(':scope > summary');
  const opener = await promoted.isVisible() ? promoted : (await fab.isVisible() ? fab : summary);
  await opener.click();
  await expect(details).toHaveJSProperty('open', true);
  const form = page.locator('form.composer').first();
  await expect(form.locator('.composer-toolbar')).toBeAttached();
  return form;
}

test('notification rows draw each type its Fluent icon at the member\'s icon size', async ({ page }, info) => {
  await signIn(page, MEMBER);
  for (const size of ['standard', 'large'] as const) {
    await setIconSize(page, size);
    await page.goto('/notifications');
    const icons = page.locator('.notification-row .notification-icon svg');
    await expect(icons).toHaveCount(NOTIFICATION_ICONS.length);
    const drawn = await icons.evaluateAll((svgs) => svgs.map((svg) => {
      const style = getComputedStyle(svg);
      return {
        name: [...svg.classList].find((c) => c.startsWith('icon-'))?.slice(5) ?? '',
        width: parseFloat(style.width), viewBox: svg.getAttribute('viewBox'), fill: style.fill, stroke: style.stroke,
      };
    }));
    expect(drawn.map((icon) => icon.name).sort()).toEqual(NOTIFICATION_ICONS);
    const px = size === 'large' ? 20 : 16;
    for (const icon of drawn) {
      expect(icon, icon.name).toMatchObject({ width: px, viewBox: `0 0 ${px} ${px}`, stroke: 'none' });
      expect(icon.fill, icon.name).not.toBe('none');
    }
    expect(await strayIcons(page)).toEqual([]);
    for (const scheme of ['light', 'dark'] as const) {
      await page.emulateMedia({ colorScheme: scheme });
      await page.screenshot({ path: shot(info.project.name, `notifications-${size}-${scheme}`) });
    }
    await page.emulateMedia({ colorScheme: 'light' });
  }
});

test('the composer draws Fluent formatting icons at the member\'s icon size', async ({ page }, info) => {
  await signIn(page, MEMBER);
  for (const size of ['standard', 'large'] as const) {
    await setIconSize(page, size);
    const form = await openNewTopicComposer(page);
    const bar = form.locator('.composer-toolbar');
    if (await bar.isHidden()) await form.locator('.composer-format-toggle').click();
    await expect(bar).toBeVisible();
    const icons = await bar.locator('[data-composer-action]').evaluateAll((buttons) => buttons.map((button) => {
      const svg = button.querySelector('svg');
      const style = svg ? getComputedStyle(svg) : null;
      return {
        action: button.getAttribute('data-composer-action') ?? '',
        cls: svg?.getAttribute('class') ?? null,
        viewBox: svg?.getAttribute('viewBox') ?? null,
        shown: !!svg && svg.getClientRects().length > 0,
        width: style ? parseFloat(style.width) : 0,
        fill: style?.fill ?? null,
        stroke: style?.stroke ?? null,
      };
    }));
    expect(icons.map((icon) => icon.action)).toEqual(Object.keys(ACTION_ICONS));
    const px = size === 'large' ? 20 : 16;
    for (const icon of icons) {
      expect(icon.cls, icon.action).toBe(`icon icon-${ACTION_ICONS[icon.action]}`);
      // Fluent draws its H2 at 20px only, so Heading uses that artwork at either size.
      const art = icon.action === 'h2' ? 20 : px;
      expect(icon.viewBox, icon.action).toBe(`0 0 ${art} ${art}`);
      expect(icon.stroke, icon.action).toBe('none');
      expect(icon.fill, icon.action).not.toBe('none');
      // Narrow layouts show only the essential actions.
      if (icon.shown) expect(icon.width, icon.action).toBe(px);
    }
    await expect(form.locator('.composer-more-toggle > svg')).toHaveClass('icon icon-plus');
    const attach = form.locator('.composer-attach-toggle > svg');
    await expect(attach).toHaveClass('icon icon-attach');
    expect((await iconBox(attach)).width).toBe(px);
    const trigger = form.locator('.composer-emoji-toggle');
    await expect(trigger.locator('svg')).toHaveClass('icon icon-emoji');
    expect((await iconBox(trigger.locator('svg'))).width).toBe(px);
    for (const scheme of ['light', 'dark'] as const) {
      await page.emulateMedia({ colorScheme: scheme });
      await form.screenshot({ path: shot(info.project.name, `composer-${size}-${scheme}`) });
    }
    await page.emulateMedia({ colorScheme: 'light' });

    await trigger.click();
    const dialog = form.locator('.composer-emoji-dialog');
    await expect(dialog).toBeVisible();
    await expect(dialog.locator('.composer-emoji-close > svg')).toHaveClass('icon icon-x');
    expect((await iconBox(dialog.locator('.composer-emoji-close > svg'))).width).toBe(px);
    expect(await strayIcons(page)).toEqual([]);
    await dialog.locator('.composer-emoji-close').click();
    await expect(dialog).toBeHidden();
  }
});

test('the verification page draws Fluent emblems at 48px', async ({ page }, info) => {
  await page.context().clearCookies();
  await page.goto('/verify?token=not-a-token');
  expect(await iconBox(page.locator('.auth-emblem.warn > svg'))).toMatchObject({
    cls: 'icon icon-verify-failed', width: 48, viewBox: '0 0 48 48', stroke: 'none',
  });
  expect(await strayIcons(page)).toEqual([]);
  await page.locator('.auth-card').screenshot({ path: shot(info.project.name, 'verify-invalid') });

  const { token } = fixture('verify') as { token: string };
  await page.goto(`/verify?token=${token}`);
  await expect(page.locator('h1')).toHaveText('Email verified');
  expect(await iconBox(page.locator('.auth-emblem:not(.warn) > svg'))).toMatchObject({
    cls: 'icon icon-verified', width: 48, viewBox: '0 0 48 48', stroke: 'none',
  });
  expect(await strayIcons(page)).toEqual([]);
  await page.locator('.auth-card').screenshot({ path: shot(info.project.name, 'verify-ok') });
});

test('marks keep their own sizes while buttons follow the icon size', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'Measured at the desktop layout; the sweep below covers the phone.');
  await signIn(page, MEMBER);
  await page.goto('/c/general');
  const topic = await page.locator('a.thread-title').first().getAttribute('href');
  expect(topic).toMatch(/^\/t\//);
  for (const size of ['standard', 'large'] as const) {
    await setIconSize(page, size);
    const px = size === 'large' ? 20 : 16;
    const marks: Record<string, { width: number, viewBox: string | null }> = {};
    const measure = async (name: string, icon: Locator) => {
      await expect(icon).toBeVisible();
      const box = await iconBox(icon);
      expect(box.stroke, name).toBe('none');
      marks[name] = { width: box.width, viewBox: box.viewBox };
    };
    await page.goto(topic!);
    await measure('breadcrumb', page.locator('.breadcrumb-back-ic'));
    await measure('search', page.locator('.forum-bar-search > .icon'));
    await page.goto('/users-online');
    await measure('online', page.locator('.users-online-field-icon'));
    await page.goto('/u/admin?tab=threads');
    await measure('affix', page.locator('.input-affix-icon .icon').first());
    await page.goto('/drafts');
    await measure('drafts', page.locator('.account-draft-sync .icon'));
    await page.goto('/compose');
    await measure('lock', page.locator('.board-rail-item.is-locked .board-rail-lock').first());
    const art = `0 0 ${px} ${px}`;
    expect(marks).toEqual({
      breadcrumb: { width: 13, viewBox: art }, search: { width: 13, viewBox: art },
      online: { width: 14, viewBox: art }, affix: { width: 14, viewBox: art },
      drafts: { width: 12, viewBox: art }, lock: { width: 11, viewBox: art },
    });
    await page.locator('.board-rail-item.is-locked').first().screenshot({ path: shot(info.project.name, `rail-lock-${size}`) });

    // The pane toggles draw Panel Regular while the pane is hidden and Panel
    // Filled while it is shown, at the icon size.
    await page.goto('/inbox');
    for (const kind of ['rail', 'reading']) {
      const button = page.locator(`[data-panel-form="${kind}"] .forum-bar-railtoggle`);
      const on = await button.evaluate((el) => el.classList.contains('is-on'));
      const shown = button.locator(on ? '.forum-bar-toggle-on' : '.forum-bar-toggle-off');
      const hidden = button.locator(on ? '.forum-bar-toggle-off' : '.forum-bar-toggle-on');
      await expect(shown).toBeVisible();
      await expect(hidden).toBeHidden();
      expect(await iconBox(shown)).toMatchObject({ width: px, viewBox: art, stroke: 'none' });
    }
    await page.locator('.forum-bar-right').screenshot({ path: shot(info.project.name, `pane-toggles-${size}`) });

    // With its label gone, the search glyph is the control and takes the icon size.
    await page.setViewportSize({ width: 1000, height: 800 });
    await page.goto('/inbox');
    expect((await iconBox(page.locator('.forum-bar-search > .icon'))).width).toBe(px);
    await page.setViewportSize({ width: 1280, height: 800 });
  }
});

test('the admin console draws Fluent marks', async ({ page }, info) => {
  await signIn(page, ADMIN);
  await page.goto('/admin/structure');
  const desktop = info.project.name === 'desktop';
  const bell = page.locator('.admin-console [data-bell] > svg, [data-bell] > .bell-ic').first();
  expect(await iconBox(bell)).toMatchObject({ cls: 'icon icon-bell bell-ic', viewBox: '0 0 16 16', stroke: 'none' });
  if (desktop) expect((await iconBox(bell)).width).toBe(18);
  expect(await iconBox(page.locator('.admin-bar-exit > svg'))).toMatchObject({ cls: 'icon icon-chevron-left', width: 13, stroke: 'none' });
  const categoryUp = page.locator('.content-icon-button-category .icon-move-up').first();
  const boardDown = page.locator('.content-icon-button-board .icon-move-down').first();
  expect(await iconBox(categoryUp)).toMatchObject({ width: 14, viewBox: '0 0 16 16', stroke: 'none' });
  expect(await iconBox(boardDown)).toMatchObject({ width: 12, viewBox: '0 0 16 16', stroke: 'none' });
  expect(await strayIcons(page)).toEqual([]);

  // The success flash's mark is Fluent's Checkmark Circle. Move the first
  // category down and back up, so the structure is unchanged.
  const down = page.locator('.content-icon-button-category[aria-label$=" down"]').first();
  const label = (await down.getAttribute('aria-label'))!;
  await down.click();
  const flash = page.locator('.admin-console .flash').first();
  await expect(flash).toContainText('Order updated.');
  const mask = await flash.evaluate((el) => {
    const before = getComputedStyle(el, '::before');
    return decodeURIComponent(before.maskImage || before.getPropertyValue('-webkit-mask-image'));
  });
  expect(mask).toContain("viewBox='0 0 16 16'");
  expect(mask).toContain('M2 8a6 6 0 1 1 12 0A6 6 0 0 1 2 8');
  expect(mask).not.toContain('stroke');
  await flash.screenshot({ path: shot(info.project.name, 'admin-flash') });
  await page.locator(`.content-icon-button-category[aria-label="${label.replace(/ down$/, ' up')}"]`).click();
  await expect(page.locator('.admin-console .flash').first()).toContainText('Order updated.');

  await page.locator('.admin-console').first().screenshot({ path: shot(info.project.name, 'admin-structure') });

  // The console's selects show the browser's own arrow. Its drawn chevron
  // never rendered under the Forum theme, which left them with none.
  for (const route of ['/admin/structure', '/admin/users', '/admin/email', '/mod/reports']) {
    await page.goto(route);
    const arrowless = await page.locator('.admin-console select').evaluateAll((selects) => selects
      .filter((el) => getComputedStyle(el).appearance === 'none' && getComputedStyle(el).backgroundImage === 'none')
      .map((el) => el.getAttribute('name') ?? el.className));
    expect(arrowless, route).toEqual([]);
  }
  await page.goto('/admin/users');
  await page.locator('.admin-console select').first().screenshot({ path: shot(info.project.name, 'admin-select') });

  await page.goto('/admin/tags');
  expect(await iconBox(page.locator('.content-tag-search > svg'))).toMatchObject({ cls: 'icon icon-search', width: 14, stroke: 'none' });
  expect(await strayIcons(page)).toEqual([]);
  await page.goto('/admin/roles');
  expect(await iconBox(page.locator('.role-callout > svg'))).toMatchObject({ cls: 'icon icon-info', width: 16, stroke: 'none' });
  expect(await strayIcons(page)).toEqual([]);
  await page.locator('.role-callout').screenshot({ path: shot(info.project.name, 'admin-roles-callout') });
});

test('no page draws a hand-made or outlined icon', async ({ page }) => {
  const sweep = async (routes: string[]) => {
    for (const route of routes) {
      await page.goto(route);
      expect(await strayIcons(page), route).toEqual([]);
    }
  };
  await page.context().clearCookies();
  await sweep(['/', '/login', '/register', '/c/general', '/search?q=the']);

  await signIn(page, MEMBER);
  await page.goto('/c/general');
  const topic = await page.locator('a.thread-title').first().getAttribute('href');
  await sweep(['/inbox', '/notifications', '/compose', '/drafts', '/users-online', '/u/admin?tab=threads',
    '/settings/appearance', '/settings/account', '/leaderboard', topic!]);

  await signIn(page, ADMIN);
  await sweep(['/admin', '/admin/structure', '/admin/tags', '/admin/roles', '/admin/users', '/mod/reports']);
});
