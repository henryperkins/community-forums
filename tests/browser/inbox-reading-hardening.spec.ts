import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Inbox reading and triage hardening (ADR 0029 follow-up, 2026-11-07).
 *
 * The 2026-11-07 critique of /inbox (.impeccable/critique/2026-11-07T10-59-44Z__templates-inbox-php.md)
 * found the reading pane locked: an open pane was clipped and its content pinned
 * to the pane's height, so the wheel scrolled the whole shell off the page while
 * later replies and the reply box were cut away. Beside a 660px queue the pane
 * was about 340px; its reply box never took the topic dock's one-line rest, so
 * its phone Minimize did nothing; the menus printed labels and counts in faint
 * 10.56px mono; the tour's card floated at the foot of the screen far from what
 * it described. Each test below measures one of those in the browser.
 *
 *   DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
 *   npx playwright test inbox-reading-hardening.spec.ts
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const OUT = path.resolve(repoRoot, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/inbox-critique-2026-11-07');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

// The design's own dataset (tests/browser/forum-inbox-fixture.php), which also
// marks every member onboarded again, so a tour left running cannot leak.
test.beforeAll(() => {
  if (process.env.RB_SKIP_FIXTURE === '1') return;
  execFileSync('php', ['tests/browser/forum-inbox-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
    stdio: 'inherit',
  });
});

async function capture(page: Page, info: TestInfo, name: string) {
  const dir = path.join(OUT, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${name}.png`), animations: 'disabled' });
}

async function signIn(page: Page, email = 'erestor@retro.test') {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

/** The top bar's Reading pane toggle, persisted, so the next load renders the same queue. */
async function setReadingPane(page: Page, open: boolean) {
  const toggle = page.locator('[data-panel-form="reading"] button[type="submit"]');
  if ((await toggle.getAttribute('aria-pressed')) === String(open)) return;
  await Promise.all([
    page.waitForResponse((r) => r.request().method() === 'POST' && new URL(r.url()).pathname === '/settings/member-surfaces'),
    toggle.click(),
  ]);
  await expect(toggle).toHaveAttribute('aria-pressed', String(open));
}

async function setTheme(page: Page, value: 'light' | 'dark' | 'system') {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="theme"][value="${value}"]`).check({ force: true });
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

/** WCAG relative luminance / contrast, over the two rgb() strings the DOM gives back. */
function contrast(a: string, b: string): number {
  const lum = (css: string) => {
    const [r, g, bl] = css.match(/\d+(\.\d+)?/g)!.slice(0, 3).map((n) => Number(n) / 255);
    const f = (c: number) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(bl);
  };
  const [x, y] = [lum(a), lum(b)];
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

/** Opens the For You topic with the most replies, so its preview has something to scroll. */
async function openLongestTopic(page: Page) {
  await page.goto('/inbox?scope=for_you&order=active');
  const href = await page.evaluate(() => {
    let best = '';
    let most = -1;
    for (const row of Array.from(document.querySelectorAll('[data-inbox-row]'))) {
      const replies = Number(((row.textContent ?? '').match(/(\d+)\s+repl/) ?? [])[1] ?? 0);
      if (replies > most) {
        most = replies;
        best = row.querySelector('[data-inbox-preview-url]')!.getAttribute('href')!;
      }
    }
    return best;
  });
  await page.locator(`[data-inbox-row] a[data-inbox-preview-url][href="${href}"]`).click();
  await expect(page.locator('[data-inbox-reading-content] [data-inbox-preview]')).toBeVisible();
  return href;
}

test('an open reading pane scrolls itself, never the page, and its reply box is reachable', async ({ page }, info) => {
  const desktop = info.project.name === 'desktop';
  await page.setViewportSize(desktop ? { width: 1280, height: 520 } : { width: 390, height: 560 });
  await signIn(page);
  if (desktop) {
    await page.goto('/inbox');
    await setReadingPane(page, true);
  }
  await openLongestTopic(page);
  const reading = page.locator('[data-inbox-reading]');
  const pane = await reading.evaluate((el) => ({ overflow: getComputedStyle(el).overflowY, client: el.clientHeight, scroll: el.scrollHeight }));
  expect(pane.overflow).toBe('auto');
  expect(pane.scroll, 'precondition: the preview overflows its pane').toBeGreaterThan(pane.client);

  const box = (await reading.boundingBox())!;
  await page.mouse.move(box.x + box.width / 2, box.y + Math.min(box.height - 20, 160));
  for (let i = 0; i < 12; i++) {
    await page.mouse.wheel(0, 400);
    await page.waitForTimeout(40);
  }
  await expect.poll(() => reading.evaluate((el) => el.scrollTop)).toBeGreaterThan(0);
  const after = await page.evaluate(() => ({
    winY: window.scrollY,
    overflow: document.documentElement.scrollHeight - window.innerHeight,
  }));
  expect(after.winY, 'the wheel must scroll the pane, not the shell').toBe(0);
  expect(after.overflow, 'nothing in the pane may stretch the document').toBeLessThanOrEqual(1);

  // At the pane's end the reply box is wholly inside it, not clipped away.
  await reading.evaluate((el) => { el.scrollTop = el.scrollHeight; });
  const reply = (await page.locator('.inbox-preview-reply').boundingBox())!;
  const shown = (await reading.boundingBox())!;
  expect(reply.y + reply.height).toBeLessThanOrEqual(shown.y + shown.height + 1);
  await capture(page, info, 'pane-scrolled-to-reply');
});

test('the queue is the list rail and the topic being read takes the rest of the frame', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the reading pane is a column only from 1280px');
  await signIn(page);
  await page.goto('/inbox');
  await setReadingPane(page, true);
  for (const width of [1280, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await openLongestTopic(page);
    const seen = await page.evaluate(() => {
      const w = (selector: string) => Math.round(document.querySelector(selector)!.getBoundingClientRect().width);
      const row = document.querySelector('[data-inbox-row]')!;
      return {
        list: w('[data-inbox-list]'),
        reading: w('[data-inbox-reading]'),
        preview: w('.inbox-preview'),
        title: Math.round(row.querySelector('.thread-title-line')!.getBoundingClientRect().width),
        monogram: Array.from(row.querySelectorAll(':scope > .monogram, :scope > .avatar-wrap')).filter((el) => el.getClientRects().length > 0).length,
      };
    });
    // DESIGN.md: the topic list is 410px (--list-w).
    expect(seen.list).toBe(410);
    expect(seen.reading).toBeGreaterThanOrEqual(560);
    expect(seen.preview).toBeGreaterThanOrEqual(480);
    // Beside the pane the row gives its title the row's width, not the avatar's leftovers.
    expect(seen.monogram).toBe(0);
    expect(seen.title).toBeGreaterThan(250);
    await capture(page, info, `split-${width}`);
  }
});

test("the pane's reply box rests on one line, opens to answer and folds away", async ({ page }, info) => {
  const desktop = info.project.name === 'desktop';
  if (desktop) await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);
  if (desktop) {
    await page.goto('/inbox');
    await setReadingPane(page, true);
  }
  await openLongestTopic(page);

  const reply = page.locator('.inbox-preview-reply .reply-composer');
  await reply.scrollIntoViewIfNeeded();
  await expect(reply).toHaveAttribute('data-composer-dock', '1');
  await expect(reply).not.toHaveClass(/\bis-expanded\b/);
  await expect(reply.locator('.composer-toolbar')).toBeHidden();
  const rest = await reply.evaluate((el) => ({
    height: el.getBoundingClientRect().height,
    buttons: Array.from(el.querySelectorAll('button')).filter((b) => b.getClientRects().length > 0).map((b) => (b.textContent ?? '').trim()),
    send: el.querySelector('.composer-send')!.getBoundingClientRect().height,
  }));
  expect(rest.height).toBeLessThanOrEqual(60);
  expect(rest.buttons).toEqual(['Reply']);
  expect(rest.send).toBeGreaterThanOrEqual(44);
  const input = reply.locator('.composer-input:visible, .ProseMirror:visible').first();
  const line = await input.evaluate((el) => {
    const css = getComputedStyle(el);
    return { available: el.clientHeight, required: parseFloat(css.lineHeight) + parseFloat(css.paddingTop) + parseFloat(css.paddingBottom) };
  });
  expect(line.available, 'the resting composer must fit a full line of the active reading type').toBeGreaterThanOrEqual(line.required);
  const previousSize = await page.locator('html').getAttribute('data-font-size');
  await page.evaluate(() => { document.documentElement.dataset.fontSize = 'large'; });
  const largeLine = await input.evaluate((el) => {
    const css = getComputedStyle(el);
    return { available: el.clientHeight, required: parseFloat(css.lineHeight) + parseFloat(css.paddingTop) + parseFloat(css.paddingBottom) };
  });
  expect(largeLine.available, 'Large text must also fit the resting reply field').toBeGreaterThanOrEqual(largeLine.required);
  await page.evaluate(value => {
    if (value === null) delete document.documentElement.dataset.fontSize;
    else document.documentElement.dataset.fontSize = value;
  }, previousSize);
  await expect(page.locator('.inbox-preview-open')).toHaveCSS('min-height', '44px');
  const eyebrow = await page.locator('.inbox-preview-reply > .eyebrow').evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
  expect(eyebrow).toBeGreaterThanOrEqual(11.5);
  await capture(page, info, 'reply-at-rest');

  await reply.locator('.composer-input:visible, .ProseMirror:visible').first().click();
  await expect(reply).toHaveClass(/\bis-expanded\b/);
  await expect(reply.locator('.composer-toolbar')).toBeVisible();

  // The composer's own Emoji control is chrome, so it is drawn rather than printed.
  const emoji = reply.locator('.composer-emoji-toggle');
  await expect(emoji).toHaveAttribute('aria-label', 'Emoji');
  await expect(emoji.locator('svg')).toHaveCount(1);
  expect(await emoji.textContent()).not.toMatch(/\p{Extended_Pictographic}/u);
  await capture(page, info, 'reply-open');

  if (!desktop) {
    const minimize = reply.locator('[data-composer-minimize]');
    await expect(minimize).toBeVisible();
    await minimize.click();
    await expect(reply).not.toHaveClass(/\bis-expanded\b/);
    expect((await reply.boundingBox())!.height).toBeLessThanOrEqual(60);
    await capture(page, info, 'reply-minimized');
  }
});

test('the whole queue row opens its preview, and its controls keep their own clicks', async ({ page }, info) => {
  const desktop = info.project.name === 'desktop';
  if (desktop) await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);
  if (desktop) {
    await page.goto('/inbox');
    await setReadingPane(page, true);
  }
  await page.goto('/inbox?scope=for_you&order=active');
  const rows = page.locator('[data-inbox-row]');
  const second = rows.nth(1);
  const id = await second.getAttribute('data-thread-id');

  // A checkbox is a control: ticking it selects, it does not read.
  await second.locator('.thread-row-select input').check();
  await expect(page.locator('[data-inbox-reading-content] [data-inbox-preview]')).toHaveCount(0);
  await second.locator('.thread-row-select input').uncheck();

  // The meta line's time is not a link, but it is the row: it opens the topic.
  await second.locator('.thread-meta time').first().click();
  await expect(page.locator(`[data-inbox-reading-content] [data-inbox-preview="${id}"]`)).toBeVisible();
  await expect(second).toHaveClass(/\bis-active\b/);
  if (!desktop) await expect(page.locator('[data-inbox-reading]')).toHaveClass(/\bis-open\b/);
});

test('the view menus print every label in the menu type and every count by class', async ({ page }, info) => {
  const desktop = info.project.name === 'desktop';
  await page.setViewportSize(desktop ? { width: 1440, height: 900 } : { width: 320, height: 760 });
  await signIn(page);
  await page.goto('/inbox?scope=for_you&order=active');

  const menus = desktop ? ['[data-inbox-scope-menu]', '.inbox-sort-menu'] : ['.inbox-compact-menu'];
  for (const selector of menus) {
    const menu = page.locator(selector);
    await menu.locator(':scope > summary').click();
    const panel = menu.locator('.inbox-menu-panel');
    await expect(panel).toBeVisible();
    const seen = await panel.evaluate((el) => ({
      groups: Array.from(el.querySelectorAll(':scope > .inbox-scope-group-label')).map((g) => (g.textContent ?? '').trim()),
      labels: Array.from(el.querySelectorAll(':scope > a > span:first-child')).map((s) => `${getComputedStyle(s).fontSize} ${getComputedStyle(s).fontFamily.split(',')[0]}`),
      counts: Array.from(el.querySelectorAll(':scope > a > .inbox-choice-state')).map((s) => getComputedStyle(s).fontSize),
    }));
    // One register for every label: the unselected Sort labels were 10.56px mono.
    expect(new Set(seen.labels).size, `${selector} labels: ${seen.labels.join(', ')}`).toBe(1);
    expect(seen.labels[0]).toContain('system-ui');
    // The Forum theme keeps secondary text at least 0.875rem.
    for (const size of seen.counts) expect(parseFloat(size)).toBeGreaterThanOrEqual(14);
    if (selector === '.inbox-compact-menu') {
      // The panel reads in its trigger's order: which topics, then in what sequence.
      expect(seen.groups[0]).toBe('Your queue');
      expect(seen.groups[seen.groups.length - 1]).toBe('Sort');
    }
    await capture(page, info, `menu-${selector.replace(/[^a-z]+/g, '-').replace(/^-|-$/g, '')}`);
    await page.keyboard.press('Escape');
  }
});

test('a scope holding nothing recedes, unless it is the scope being shown', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'one width is enough for a menu rule');
  await page.setViewportSize({ width: 1440, height: 900 });
  // A seeded member with no personal signals, so most scopes are empty.
  await signIn(page, 'council3@retro.test');
  await page.goto('/inbox?scope=mentions&order=active');
  await page.locator('[data-inbox-scope-menu] > summary').click();
  const panel = page.locator('[data-inbox-scope-menu] .inbox-menu-panel');
  await expect(panel).toBeVisible();
  const seen = await panel.evaluate((el) => {
    const ground = getComputedStyle(el).backgroundColor;
    return Array.from(el.querySelectorAll(':scope > a')).map((a) => ({
      label: (a.querySelector('span')!.textContent ?? '').trim(),
      count: Number(a.querySelector('[data-inbox-scope-count]')!.textContent),
      cls: a.className,
      color: getComputedStyle(a).color,
      ground,
      height: a.getBoundingClientRect().height,
    }));
  });
  const current = seen.find((s) => s.label === 'Mentions')!;
  expect(current.cls).toBe('is-active');
  const empty = seen.filter((s) => s.cls === 'is-empty');
  const full = seen.filter((s) => s.cls === '');
  expect(empty.length).toBeGreaterThan(0);
  expect(full.length).toBeGreaterThan(0);
  for (const s of empty) {
    expect(s.count).toBe(0);
    expect(s.height).toBeGreaterThanOrEqual(44);
    expect(s.color).not.toBe(full[0].color);
    expect(contrast(s.color, s.ground)).toBeGreaterThanOrEqual(4.5);
  }
  for (const s of full) expect(s.count).toBeGreaterThan(0);
  await capture(page, info, 'menu-empty-scopes');
});

test('the queue in twilight draws its checkboxes in the dark scheme', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the scheme is the same rule at every width');
  await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);
  const scheme = () => page.evaluate(() => ['.thread-row-select input', '.inbox-select-all input']
    .map((s) => getComputedStyle(document.querySelector(s)!).colorScheme));

  try {
    await setTheme(page, 'system');
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/inbox');
    expect(await scheme()).toEqual(['dark', 'dark']);
    await page.emulateMedia({ colorScheme: 'light' });
    expect(await scheme()).not.toContain('dark');

    await setTheme(page, 'dark');
    await page.goto('/inbox');
    expect(await scheme()).toEqual(['dark', 'dark']);
    await capture(page, info, 'queue-twilight');
  } finally {
    await setTheme(page, 'system');
  }
});

test('the queue meta wraps in lines, and the pager is a 44px target', async ({ page }, info) => {
  await page.setViewportSize(info.project.name === 'desktop' ? { width: 1440, height: 900 } : { width: 320, height: 700 });
  await signIn(page);
  await page.goto('/inbox?scope=unread&order=active');
  const meta = page.locator('[data-inbox-row] .thread-meta').first();
  await expect(meta).toHaveCSS('row-gap', '2px');
  await expect(meta).toHaveCSS('column-gap', '10px');

  const pages = page.locator('[data-inbox] .pagination .page');
  expect(await pages.count(), 'precondition: the Unread scope spans pages').toBeGreaterThan(1);
  for (const target of await pages.all()) {
    const b = (await target.boundingBox())!;
    expect(b.width).toBeGreaterThanOrEqual(44);
    expect(b.height).toBeGreaterThanOrEqual(44);
  }
  await page.locator('[data-inbox] .pagination').scrollIntoViewIfNeeded();
  await capture(page, info, 'queue-pager');
});

test('a guest sent from the Inbox is told why, and the passkey stands apart', async ({ page }, info) => {
  await page.goto('/inbox');
  await expect(page).toHaveURL(/\/login\?next=%2Finbox$/);
  const lede = page.locator('.auth-lede');
  await expect(lede).toHaveText('Your Inbox is personal to your account. Log in to see it, or read the boards without one.');
  await expect(lede.getByRole('link', { name: 'read the boards' })).toHaveAttribute('href', '/');
  const passkey = page.locator('.passkey-signin');
  if (await passkey.isVisible()) {
    const button = (await page.locator('.auth-form button[type="submit"]').boundingBox())!;
    const second = (await passkey.boundingBox())!;
    expect(second.y - (button.y + button.height)).toBeGreaterThanOrEqual(12);
  }
  await capture(page, info, 'login-from-inbox');

  await page.goto('/login');
  await expect(page.locator('.auth-lede')).toHaveCount(0);
});

test('the welcome tour stands beside what it describes', async ({ page }, info) => {
  const desktop = info.project.name === 'desktop';
  if (desktop) await page.setViewportSize({ width: 1440, height: 900 });
  await signIn(page);
  await page.goto('/inbox');
  // Replay re-arms the first-run tour, which then starts on the next page.
  const token = await page.locator('input[name="_token"]').first().inputValue();
  const replay = await page.request.post('/onboarding/replay', {
    form: { _token: token },
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  expect(replay.ok()).toBe(true);

  await page.goto('/inbox');
  const pop = page.locator('.tour-popover');
  try {
    await expect(pop).toBeVisible();
    await expect(pop.locator('p')).toContainText('community’s name or mark');
    const steps: Array<{ title: string; anchored: boolean; overlaps: boolean; inside: boolean; below: boolean; beside: boolean }> = [];
    for (let i = 0; i < 8; i++) {
      await page.waitForTimeout(150);
      steps.push(await page.evaluate(() => {
        const card = document.querySelector('.tour-popover')!;
        const c = card.getBoundingClientRect();
        const target = document.querySelector('.tour-highlight');
        const t = target ? target.getBoundingClientRect() : null;
        const vw = document.documentElement.clientWidth;
        return {
          title: (card.querySelector('h3')!.textContent ?? '').trim(),
          anchored: card.classList.contains('is-anchored'),
          overlaps: !!t && c.left < t.right && c.right > t.left && c.top < t.bottom && c.bottom > t.top,
          inside: c.left >= 0 && c.right <= vw && c.top >= 0 && c.bottom <= window.innerHeight,
          below: !!t && c.top >= t.bottom,
          beside: !!t && c.left >= t.right,
        };
      }));
      if (i === 0) await capture(page, info, 'tour-welcome');
      const next = pop.getByRole('button', { name: /^(Next|Done)$/ });
      if ((await next.textContent())?.trim() === 'Done') break;
      await next.click();
    }
    for (const step of steps) {
      expect(step.inside, `${step.title} stays in the viewport`).toBe(true);
      if (step.anchored) expect(step.overlaps, `${step.title} does not cover its target`).toBe(false);
    }
    // The lockup is a control at the top-left: the card stands under it.
    expect(steps[0]).toMatchObject({ title: 'Welcome', anchored: true, below: true });
    if (desktop) {
      // The board rail is a column: the card stands alongside it.
      expect(steps.find((s) => s.title === 'Boards')).toMatchObject({ anchored: true, beside: true });
    }
    await pop.getByRole('button', { name: 'Done' }).click();
    await expect(pop).toHaveCount(0);
  } finally {
    if (await pop.count()) await page.keyboard.press('Escape');
  }
});
