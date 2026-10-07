import { test, expect, Page, type BrowserContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * The bar's unread marks and its current surface (2026-10-06 critique, owner
 * decision: the Inbox count leads; ADR 0032 follow-up).
 *
 * Gold in the bar means unread. The Inbox count wears the thread row's unread
 * mark, the gold dot with its 2px halo; Messages, the bell and the account
 * menu's Notifications row share one quiet chip in JetBrains Mono with tabular
 * figures. The bell's count sits beside the glyph. The current surface carries
 * a rule that survives forced colours, and the pane toggles read filled when
 * shown and outlined when hidden.
 */

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/header-unread-marks-2026-10-06');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

function shot(name: string, project: string) {
  const dir = path.join(OUT, project);
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
}

// One sign-in per account per run: login is rate-limited per account.
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['cookies']>>>();
async function signInAs(page: Page, email: string) {
  const cached = sessions.get(email);
  if (cached) { await page.context().addCookies(cached); return; }
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.count()) { await skip.first().click(); await expect(skip.first()).toBeHidden(); }
  sessions.set(email, await page.context().cookies());
}

/** chrome_reader with one unread topic in the Inbox (the chrome-consistency fixture). */
function unreadInbox() {
  execFileSync('php', ['tests/browser/chrome-consistency-fixture.php'], {
    cwd: ROOT, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
}

/** The Messages count as the poll writes it (app.js updateDmCount). */
async function showMessagesCount(page: Page, count: number) {
  await page.evaluate((value) => {
    document.querySelectorAll<HTMLElement>('[data-dm-unread-count]').forEach((node) => {
      node.textContent = value > 99 ? '99+' : String(value);
      node.hidden = value === 0;
      // The words live on the link (polish, 2026-10-07), as the bell's do.
      const link = node.closest('[data-count-name]');
      if (link && value > 0) link.setAttribute('aria-label', `${link.getAttribute('data-count-name')}, ${value} unread conversation${value === 1 ? '' : 's'}`);
      else if (link) link.removeAttribute('aria-label');
    });
  }, count);
}

/** WCAG contrast of an element's colour property against its composited background. */
async function contrast(page: Page, selector: string, property: string, pseudo: string | null = null, against: string | null = null) {
  return page.evaluate(([sel, prop, ps, bgSel]) => {
    // Computed colours arrive as rgb()/rgba(), or as color(srgb …) where the
    // bar's own color-mix() wash resolves (channels 0–1).
    const parse = (c: string) => {
      const rgb = c.match(/^rgba?\(([^)]+)\)/);
      if (rgb) { const p = rgb[1].split(/[ ,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; }
      const srgb = c.match(/^color\(srgb ([^)]+)\)/);
      if (srgb) { const p = srgb[1].split(/[ /]+/).filter(Boolean).map(Number); return [p[0] * 255, p[1] * 255, p[2] * 255, p.length > 3 ? p[3] : 1]; }
      throw new Error('Unparsed colour: ' + c);
    };
    const over = (top: number[], under: number[]) => [0, 1, 2].map((i) => top[i] * top[3] + under[i] * (1 - top[3]));
    const lum = (rgb: number[]) => { const f = (v: number) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(rgb[0]) + 0.7152 * f(rgb[1]) + 0.0722 * f(rgb[2]); };
    const el = document.querySelector(sel)!;
    const fg = parse((getComputedStyle(el, ps) as unknown as Record<string, string>)[prop]);
    const layers: number[][] = [];
    for (let n: Element | null = bgSel ? document.querySelector(bgSel) : el; n; n = n.parentElement) {
      const c = parse(getComputedStyle(n).backgroundColor);
      if (c[3] > 0) layers.push(c);
      if (c[3] === 1) break;
    }
    let bg = parse(getComputedStyle(document.body).backgroundColor).slice(0, 3);
    for (let i = layers.length - 1; i >= 0; i--) bg = over(layers[i], bg);
    const fgOnBg = over(fg, bg);
    const [a, b] = [lum(fgOnBg), lum(bg)].sort((x, y) => y - x);
    return (a + 0.05) / (b + 0.05);
  }, [selector, property, pseudo, against] as const);
}

for (const theme of ['light', 'dark'] as const) {
  test(`the Inbox count leads and the other counts share one quiet chip (${theme === 'light' ? 'day' : 'twilight'})`, async ({ page }, info) => {
    unreadInbox();
    await signInAs(page, 'chrome-reader@retro.test');
    await page.goto('/inbox');
    await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme);
    await showMessagesCount(page, 3);
    await page.evaluate(() => {
      document.querySelectorAll<HTMLElement>('.forum-bar [data-notification-count], .identity-menu-panel [data-notification-count]').forEach((node) => {
        node.textContent = '12'; node.hidden = false;
      });
    });
    await page.waitForTimeout(450);

    // The Inbox: the thread row's dot and halo, no chip, numerals a step heavier.
    const inbox = page.locator('.forum-bar .forum-bar-count[data-inbox-unread-count]');
    await expect(inbox).toHaveText('1');
    const lead = await inbox.evaluate((el) => {
      const c = getComputedStyle(el);
      const dot = getComputedStyle(el, '::before');
      return { background: c.backgroundColor, font: c.fontFamily, weight: c.fontWeight, size: c.fontSize, numerals: c.fontVariantNumeric,
        dot: { content: dot.content, width: dot.width, height: dot.height, background: dot.backgroundColor, halo: dot.boxShadow } };
    });
    expect(lead.background).toBe('rgba(0, 0, 0, 0)');
    expect(lead.font).toContain('JetBrains Mono');
    expect(lead.weight).toBe('500');
    expect(lead.size).toBe('11.2px');
    expect(lead.numerals).toBe('tabular-nums');
    expect(lead.dot.content).toBe('""');
    expect([lead.dot.width, lead.dot.height]).toEqual(['8px', '8px']);
    expect(lead.dot.background).toBe('rgb(194, 154, 68)');
    expect(lead.dot.halo).toContain('2px');
    expect(await contrast(page, '.forum-bar .forum-bar-count[data-inbox-unread-count]', 'color')).toBeGreaterThanOrEqual(4.5);

    // One quiet chip for Messages, the bell and the account menu's row.
    const chips = ['.forum-bar [data-dm-unread-count]', '.forum-bar [data-bell] .bell-count', '.identity-menu-panel .notification-count'];
    const styles = await Promise.all(chips.map((selector) => page.locator(selector).evaluate((el) => {
      const c = getComputedStyle(el);
      return [c.fontFamily.split(',')[0], c.fontWeight, c.fontSize, c.fontVariantNumeric, c.backgroundColor, c.color].join(' | ');
    })));
    expect(new Set(styles).size, `one chip: ${styles.join(' / ')}`).toBe(1);
    expect(styles[0]).toContain('JetBrains Mono');
    expect(styles[0]).toContain('tabular-nums');
    expect(styles[0]).not.toContain('rgb(194, 154, 68) |'); // never the solid gold disc
    for (const selector of chips.slice(0, 2)) {
      expect(await contrast(page, selector, 'color'), selector).toBeGreaterThanOrEqual(4.5);
    }

    // The bell's count sits beside the glyph, inside the bell's own target.
    const bell = await page.locator('.forum-bar [data-bell]').evaluate((el) => {
      const box = el.getBoundingClientRect();
      const glyph = el.querySelector('.icon')!.getBoundingClientRect();
      const chip = el.querySelector('.bell-count')!.getBoundingClientRect();
      return { box: { left: box.left, right: box.right, height: box.height }, glyphRight: glyph.right, chip: { left: chip.left, right: chip.right } };
    });
    expect(bell.chip.left, 'the count never covers the glyph').toBeGreaterThanOrEqual(bell.glyphRight);
    expect(bell.chip.right).toBeLessThanOrEqual(bell.box.right + 0.5);
    // 40px on the desktop bar; the phone rows give every control 44px (adapt, 2026-10-07).
    expect(bell.box.height).toBe(info.project.name === 'mobile' ? 44 : 40);

    // The current surface: a rule in the pill's own ink, on the current pill only.
    const rules = await page.locator('.forum-bar-surface').evaluateAll((pills) => pills.map((pill) => ({
      current: pill.getAttribute('aria-current') === 'page',
      rule: getComputedStyle(pill, '::after').content !== 'none' ? getComputedStyle(pill, '::after').borderBottom : null,
    })));
    expect(rules.filter((r) => r.rule !== null).map((r) => r.current)).toEqual([true]);
    expect(rules.find((r) => r.current)!.rule).toMatch(/^2px solid/);
    expect(await contrast(page, '.forum-bar-surface.is-active', 'borderBottomColor', '::after')).toBeGreaterThanOrEqual(3);

    const project = info.project.name;
    const width = page.viewportSize()!.width;
    await page.screenshot({ path: shot(`counts-${theme}.png`, project), clip: { x: 0, y: 0, width, height: width <= 860 ? 112 : 64 } });
    await page.locator('.identity-menu > summary').click();
    const panel = (await page.locator('.identity-menu-panel').boundingBox())!;
    await page.screenshot({ path: shot(`menu-${theme}.png`, project), clip: { x: panel.x - 8, y: panel.y - 8, width: panel.width + 16, height: Math.min(panel.height + 16, 200) } });
  });
}

test('the bell keeps its square target when it has nothing to count', async ({ page }, info) => {
  await signInAs(page, 'chrome-reader@retro.test');
  await page.goto('/');
  await page.evaluate(() => document.querySelectorAll<HTMLElement>('.forum-bar [data-notification-count]').forEach((n) => { n.hidden = true; }));
  const box = (await page.locator('.forum-bar [data-bell]').boundingBox())!;
  // 40px on the desktop bar; the phone rows give every control 44px (adapt, 2026-10-07).
  const floor = info.project.name === 'mobile' ? 44 : 40;
  expect(box.width).toBe(floor);
  expect(box.height).toBe(floor);
});

test('the current surface stays distinguishable in forced colours', async ({ browser, baseURL }, info) => {
  const context = await browser.newContext({ baseURL, forcedColors: 'active', ...(info.project.name === 'mobile' ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } : { viewport: { width: 1280, height: 800 } }) });
  const page = await context.newPage();
  await signInAs(page, 'chrome-reader@retro.test');
  await page.goto('/');
  const pills = await page.locator('.forum-bar-surface').evaluateAll((all) => all.map((pill) => {
    const after = getComputedStyle(pill, '::after');
    return { current: pill.getAttribute('aria-current') === 'page', rule: after.content !== 'none' ? `${after.borderBottomWidth} ${after.borderBottomStyle} ${after.borderBottomColor}` : null, color: getComputedStyle(pill).color };
  }));
  const current = pills.find((p) => p.current)!;
  expect(current.rule, 'the current pill keeps its rule when backgrounds are forced away').toMatch(/^2px solid rgb/);
  expect(current.rule).not.toContain('rgba(0, 0, 0, 0)');
  for (const other of pills.filter((p) => !p.current)) { expect(other.rule).toBeNull(); }
  await page.screenshot({ path: shot('forced-colours.png', info.project.name), clip: { x: 0, y: 0, width: page.viewportSize()!.width, height: info.project.name === 'mobile' ? 112 : 64 } });
  await context.close();
});

test('the pane toggles read filled when shown and outlined when hidden', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'the persisted toggles are desktop chrome');
  // A 3x context, so the 16px glyphs in the evidence crops are legible.
  const context = await browser.newContext({ baseURL, viewport: { width: 1280, height: 800 }, deviceScaleFactor: 3 });
  const page = await context.newPage();
  await signInAs(page, 'chrome-reader@retro.test');
  await page.goto('/inbox');
  for (const theme of ['light', 'dark'] as const) {
    await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme);
    for (const shown of [true, false]) {
      await page.evaluate((on) => document.querySelectorAll('.forum-bar-railtoggle').forEach((button) => {
        button.classList.toggle('is-on', on); button.setAttribute('aria-pressed', on ? 'true' : 'false');
      }), shown);
      await page.waitForTimeout(400);
      for (const kind of ['rail', 'reading']) {
        const button = `[data-panel-form="${kind}"] .forum-bar-railtoggle`;
        const band = await page.locator(`${button} .forum-bar-toggle-band`).evaluate((el) => {
          const c = getComputedStyle(el);
          return { display: c.display, opacity: c.opacity, fill: c.fill, stroke: c.stroke, strokeWidth: c.strokeWidth };
        });
        expect(band.display, `${kind} ${theme}: the band is always drawn`).not.toBe('none');
        expect(band.opacity).toBe('1');
        if (shown) {
          expect(band.fill).not.toBe('none');
          expect(await contrast(page, `${button} .forum-bar-toggle-band`, 'fill', null, button), `${kind} ${theme}: filled band`).toBeGreaterThanOrEqual(3);
        } else {
          expect(band.fill).toBe('none');
          expect(band.strokeWidth).toBe('1.1px');
          expect(await contrast(page, `${button} .forum-bar-toggle-band`, 'stroke', null, button), `${kind} ${theme}: outlined band`).toBeGreaterThanOrEqual(3);
        }
      }
      const box = (await page.locator('.forum-bar-right').boundingBox())!;
      await page.screenshot({ path: shot(`toggles-${shown ? 'shown' : 'hidden'}-${theme}.png`, info.project.name), clip: { x: box.x - 6, y: 8, width: 120, height: 46 } });
    }
  }
  await context.close();
});

test('the bell hover keeps its glyph legible in twilight', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'hover is a pointer state');
  await signInAs(page, 'chrome-reader@retro.test');
  await page.goto('/');
  await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
  await page.waitForTimeout(400);
  await page.locator('.forum-bar [data-bell]').hover();
  await page.waitForTimeout(300);
  expect(await contrast(page, '.forum-bar [data-bell]', 'color')).toBeGreaterThanOrEqual(4.5);
});
