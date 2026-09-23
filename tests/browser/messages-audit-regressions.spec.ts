import { test, expect, type Browser, type BrowserContext, type BrowserContextOptions, type Locator, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Regression coverage for the Messages UI audit of 027d878
 * (docs/evidence/dm-reimagine/phase5/source-audit-027d878.md, findings P1-1…P3-6).
 *
 * The audit's own diagnosis of why these got through: the Messages specs never
 * opened the ··· menu, never Tabbed through the compose dialog, never zoomed,
 * and only switched to dark by stamping data-theme="dark" — so OS-dark on the
 * default `system` theme was never exercised. Every test below asserts the
 * measured property the audit reported (a focus position, a pixel height, a
 * contrast ratio, an accessible name), not just a screenshot.
 *
 * Each test builds its own contexts (viewport, touch, colour scheme, zoom, time
 * zone, JS off), so the file runs on the desktop project only. Conversations are
 * seeded straight into the DB through the real Markdown pipeline (the dm rate
 * limit is 20 letters per 600s), idempotently, because Playwright restarts the
 * worker after a failure and re-runs beforeAll.
 *
 * Two P1-2 assertions pin the integrator's post-merge changes rather than a
 * stream's: Send is in view after the dock opens at 640x400 (the dock's focusin
 * scrollIntoView) and a 422'd dock stays open after a tap outside (composer.js
 * collapseIfEmpty). Not covered here, by design: P2-7 (awaits an ADR 0032
 * ruling), P3-1 (CSS distill), P3-3 (poll cost is pinned in PHPUnit; report
 * forms deferred), P3-4/P3-7 (awaiting product-owner rulings), and P2-6's
 * list-open half (no-JS opening from the list still starts at the top).
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const evidence = path.join(repoRoot, 'docs/evidence/dm-reimagine/phase5');
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const DESKTOP = { width: 1440, height: 1000 };
const TOUCH: BrowserContextOptions = { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true };
// 1280x800 at 200% browser zoom is a 640x400 CSS viewport at device scale 2.
const ZOOM: BrowserContextOptions = { viewport: { width: 640, height: 400 }, deviceScaleFactor: 2 };
const GROUP_TITLE = 'Audit regressions — wardens';

// ── Fixture ─────────────────────────────────────────────────────────────────

function runPhp(code: string, env: Record<string, string> = {}): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e', ...env },
  }).toString();
}

type Letter = { from: string; body: string; at: string };
type ConversationSeed =
  | { kind: 'direct'; a: string; b: string; letters: Letter[] }
  | { kind: 'group'; owner: string; members: string[]; title: string; letters: Letter[] };
type Seeded = Record<'counsel' | 'carol' | 'unread' | 'landing' | 'landingPhone' | 'paged' | 'group', { id: number; messages: number[] }>;

const utc = (ms: number) => new Date(ms).toISOString().slice(0, 19).replace('T', ' ');

function run(from: string[], count: number, prefix: string, endMs: number): Letter[] {
  return Array.from({ length: count }, (_, i) => ({
    from: from[i % from.length],
    body: `${prefix} ${i + 1}. The record stays with the people named here.\n\nThe rollback drill and the audit note must agree before we set the day.`,
    at: utc(endMs - (count - i) * 120000),
  }));
}

function fixtureSpec(): Record<keyof Seeded, ConversationSeed> {
  // The main counsel is pinned to 2026-09-20 14:05Z = 9:05 AM in Chicago, so the
  // 12-hour clock has a single-digit hour whatever the wall clock says (P3-6).
  const base = Date.UTC(2026, 8, 20, 14, 5, 0);
  const at = (minutes: number) => utc(base + minutes * 60000);
  const now = Date.now();
  return {
    counsel: { kind: 'direct', a: 'alice', b: 'bob', letters: [
      { from: 'bob', at: at(0), body: 'Morning, Alice. The rollback drill notes are in [the runbook](https://example.com/runbook), and @carol has the timing.' },
      { from: 'bob', at: at(1), body: '[![Rollback diagram](/media/1)](https://example.com/diagram)' },
      { from: 'bob', at: at(2), body: 'The sweep should run after the flip.' },
      { from: 'bob', at: at(3), body: 'Keep the digest window quiet.' },
      { from: 'alice', at: at(8), body: 'Thanks. I left a note on [the drill page](https://example.com/drill) for the wardens.' },
      { from: 'alice', at: at(9), body: 'The record remains available to everyone named here.' },
      { from: 'bob', at: at(15), body: 'Agreed. I will send the checklist after the flip.' },
      { from: 'bob', at: at(16), body: 'One more line so this run has several letters.' },
      { from: 'bob', at: at(17), body: 'And the audit note must agree before we set the day.' },
      { from: 'alice', at: at(25), body: 'Good. The council meets at noon, and the wardens will read the note first.' },
      { from: 'bob', at: at(30), body: 'Then we are ready.' },
      { from: 'bob', at: at(31), body: 'Send it over when the timing is fixed.' },
    ] },
    carol: { kind: 'direct', a: 'alice', b: 'carol', letters: run(['carol', 'alice'], 4, 'Carol counsel', now - 3600000) },
    unread: { kind: 'direct', a: 'nimrodel', b: 'alice', letters: [
      { from: 'nimrodel', at: utc(now - 60000), body: "```php\necho 'hi';\n```\n\nSee `worker:packages`, **bold**, _em_ and [the runbook](https://example.com/runbook).\n\n- Keep it quiet.\n\n> Quoted counsel." },
    ] },
    // 48 letters: the no-JS reply makes a 49-letter newest page, tall enough
    // that the top of the page and the newest letter never share a viewport.
    landing: { kind: 'direct', a: 'alice', b: 'dana', letters: run(['dana', 'alice'], 48, 'Dana counsel', now - 7200000) },
    landingPhone: { kind: 'direct', a: 'alice', b: 'elrond', letters: run(['elrond', 'alice'], 48, 'Elrond counsel', now - 7200000) },
    // 60 letters: two pages, so both "Earlier messages" and "Latest messages" render.
    paged: { kind: 'direct', a: 'alice', b: 'galadriel', letters: run(['galadriel', 'alice'], 60, 'Galadriel counsel', now - 10800000) },
    group: { kind: 'group', owner: 'alice', members: ['bob', 'carol'], title: GROUP_TITLE, letters: run(['alice', 'bob', 'carol'], 3, 'Group counsel', now - 5400000) },
  };
}

let seeded: Seeded | null = null;

function seed(): Seeded {
  if (seeded) return seeded;
  const out = runPhp(`
$users = new \\App\\Repository\\UserRepository($db);
$convs = new \\App\\Repository\\ConversationRepository($db);
$markdown = new \\App\\Support\\Markdown(new \\App\\Support\\HtmlSanitizer(), null, new \\App\\Support\\MentionLinker($users, true));
$uid = static function (string $name) use ($users): int {
    $row = $users->findByUsername($name);
    if ($row === null) { throw new RuntimeException('No seeded member ' . $name); }
    return (int) $row['id'];
};
$out = [];
foreach (json_decode((string) getenv('RB_DM_FIXTURE'), true, 512, JSON_THROW_ON_ERROR) as $key => $c) {
    if ($c['kind'] === 'group') {
        $cid = (int) $db->fetchValue("SELECT id FROM conversations WHERE kind = 'group' AND title = ? ORDER BY id LIMIT 1", [$c['title']]);
        if ($cid === 0) { $cid = $convs->createGroup($uid($c['owner']), $c['title'], array_map($uid, $c['members']), 0); }
    } else {
        $cid = $convs->findOrCreateBetween($uid($c['a']), $uid($c['b']));
    }
    if ((int) $db->fetchValue('SELECT COUNT(*) FROM dm_messages WHERE conversation_id = ?', [$cid]) === 0) {
        foreach ($c['letters'] as $l) {
            $sender = $uid($l['from']);
            $mid = $db->insert(
                'INSERT INTO dm_messages (conversation_id, user_id, body, body_html, created_at) VALUES (?, ?, ?, ?, ?)',
                [$cid, $sender, $l['body'], $markdown->render($l['body'], ['link_mentions' => true]), $l['at']],
            );
            $convs->markRead($cid, $sender, $mid); // a sender has read their own letter
        }
        $convs->touch($cid, end($c['letters'])['at']);
    }
    $ids = array_map('intval', array_column($db->fetchAll('SELECT id FROM dm_messages WHERE conversation_id = ? ORDER BY id', [$cid]), 'id'));
    $out[$key] = ['id' => $cid, 'messages' => $ids];
}
echo json_encode($out);
`, { RB_DM_FIXTURE: JSON.stringify(fixtureSpec()) });
  seeded = JSON.parse(out) as Seeded;
  return seeded;
}

function setWysiwyg(enabled: boolean): void {
  runPhp(`
$settings = new \\App\\Repository\\SettingRepository($db);
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}

function clearDrafts(username: string): void {
  // A typed-but-unsent body autosaves as a server draft and would reopen the
  // dock expanded on the next visit, so no test inherits another's draft.
  runPhp(`$db->run('DELETE d FROM server_drafts d JOIN users u ON u.id = d.user_id WHERE u.username = ?', ['${username}']);`);
}

function setOnline(username: string): void {
  runPhp(`$db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP(), show_presence = 1 WHERE username = ?', ['${username}']);`);
}

function forumThreadPath(): string {
  return runPhp(`
$row = $db->fetch("SELECT t.id, t.slug FROM threads t JOIN boards b ON b.id = t.board_id WHERE b.slug = 'general' ORDER BY t.id LIMIT 1");
echo $row ? (int) $row['id'] . '|' . $row['slug'] : '';
`).trim();
}

// ── Browser helpers ─────────────────────────────────────────────────────────

async function login(page: Page, who: string) {
  await page.goto('/login');
  await page.locator('[name=email]').fill(`${who}@retro.test`);
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

// One login per member per worker; every other context reuses its cookies, so
// the login throttle is never in play however many viewports a test opens.
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['storageState']>>>();

// Colour maths in the page, so any computed colour syntax (rgb, color(srgb …),
// oklch …) resolves the way the browser paints it: a 1x1 canvas does the parse.
function installColorTools() {
  let ctx: CanvasRenderingContext2D | null = null;
  const rgba = (css: string): number[] => {
    if (!ctx) {
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 1;
      ctx = canvas.getContext('2d', { willReadFrequently: true });
    }
    const c = ctx!;
    c.clearRect(0, 0, 1, 1);
    c.fillStyle = 'rgba(0, 0, 0, 0)';
    c.fillStyle = css;
    c.fillRect(0, 0, 1, 1);
    const d = c.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2], d[3] / 255];
  };
  const over = (top: number[], bottom: number[]): number[] => {
    const a = top[3] + bottom[3] * (1 - top[3]);
    if (a === 0) return [0, 0, 0, 0];
    return [0, 1, 2].map(i => (top[i] * top[3] + bottom[i] * bottom[3] * (1 - top[3])) / a).concat(a);
  };
  // The colour an element's content actually sits on: its own background and
  // every translucent ancestor background, down to the first opaque one.
  const backdrop = (el: Element | null): number[] => {
    const layers: number[][] = [];
    for (let n = el; n; n = n.parentElement) {
      const c = rgba(getComputedStyle(n).backgroundColor);
      if (c[3] > 0) layers.push(c);
      if (c[3] >= 1) break;
    }
    let acc = [255, 255, 255, 1];
    for (let i = layers.length - 1; i >= 0; i--) acc = over(layers[i], acc);
    return acc;
  };
  const lum = (c: number[]) => {
    const f = (v: number) => { v /= 255; return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
    return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
  };
  const contrast = (a: number[], b: number[]) => {
    const x = lum(a), y = lum(b);
    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
  };
  (window as any).__rbColor = { rgba, over, backdrop, contrast };
}

async function member(browser: Browser, baseURL: string, who: string, options: BrowserContextOptions = {}) {
  if (!sessions.has(who)) {
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    await login(page, who);
    sessions.set(who, await context.storageState());
    await context.close();
  }
  const context = await browser.newContext({ baseURL, viewport: DESKTOP, colorScheme: 'light', ...options, storageState: sessions.get(who) });
  await context.addInitScript(installColorTools);
  const page = await context.newPage();
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  return { context, page, errors };
}

async function settle(page: Page, ms = 650) {
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => document.getAnimations().forEach(animation => {
    if (animation.effect?.getTiming().iterations !== Infinity) animation.finish();
  }));
  // Colour and border transitions run 140ms; theme flips and focus rings need them done.
  await page.waitForTimeout(ms);
}

async function shot(page: Page, name: string, fullPage = false) {
  fs.mkdirSync(evidence, { recursive: true });
  await settle(page, 200);
  await page.screenshot({ path: path.join(evidence, `${name}.png`), animations: 'disabled', fullPage });
}

async function axe(page: Page, include: string, label: string) {
  const results = await new AxeBuilder({ page }).include(include).withTags(WCAG).analyze();
  expect.soft(results.violations.map(v => `${v.id} (${v.impact}): ${v.nodes.map(n => n.target.join(' ')).join(' | ')}`), label).toEqual([]);
}

/** Record a measured value on the test (shown by the json/html reporters). */
function measure(label: string, value: unknown) {
  test.info().annotations.push({ type: 'measure', description: `${label}: ${typeof value === 'string' ? value : JSON.stringify(value)}` });
}

const insideDialog = (page: Page) => page.evaluate(() => !!document.activeElement?.closest('.dm-dialog'));
const paneGap = (page: Page) => page.locator('[data-dm-scroll]').evaluate(n => n.scrollHeight - n.scrollTop - n.clientHeight);
const paneHeight = (page: Page) => page.locator('[data-dm-scroll]').evaluate(n => n.clientHeight);

/**
 * Is the element (or its ::after hit slop) what a tap at centre ±21.5px lands on?
 * A control inside the letters scroller is centred first, so the probe never
 * reaches past the scroller's own edge; header controls are probed in place.
 */
async function hitArea(target: Locator) {
  return target.evaluate(el => {
    if (el.closest('[data-dm-scroll]')) el.scrollIntoView({ block: 'center', inline: 'nearest' });
    const r = el.getBoundingClientRect();
    const cx = r.left + r.width / 2, cy = r.top + r.height / 2, d = 21.5;
    const own = (x: number, y: number) => { const hit = document.elementFromPoint(x, y); return !!hit && (hit === el || el.contains(hit)); };
    return { box: `${Math.round(r.width)}x${Math.round(r.height)}`, centre: own(cx, cy), left: own(cx - d, cy), right: own(cx + d, cy), up: own(cx, cy - d), down: own(cx, cy + d) };
  });
}
const FULL_HIT = { centre: true, left: true, right: true, up: true, down: true };

test.beforeAll(({}, workerInfo) => {
  if (workerInfo.project.name === 'desktop') seed();
});

test.beforeEach(({}, info) => {
  test.skip(info.project.name !== 'desktop', 'Each test sets its own viewports, touch, zoom, colour scheme and JS state.');
  clearDrafts('alice');
});

test.afterEach(({}, info) => {
  if (info.project.name === 'desktop') clearDrafts('alice');
});

// ── P1 ──────────────────────────────────────────────────────────────────────

test('P1-1 compose dialog is modal: aria-modal, Tab and Shift+Tab stay inside, Escape peels list then dialog', async ({ browser, baseURL }) => {
  test.setTimeout(180000);
  for (const [label, options] of [['1440', { viewport: DESKTOP }], ['390-touch', TOUCH]] as const) {
    const { context, page, errors } = await member(browser, baseURL!, 'alice', options);
    await page.goto('/messages');
    const details = page.locator('details.dm-compose-details');
    const dialog = page.locator('.dm-dialog');
    const to = page.locator('.dm-dialog .dm-to-input');
    await expect.soft(dialog, `${label}: closed dialog is not modal`).not.toHaveAttribute('aria-modal', /.*/);
    await page.locator('.dm-new-btn').click();
    await expect(details).toHaveAttribute('open', '');
    await expect(dialog).toHaveAttribute('role', 'dialog');
    await expect.soft(dialog, `${label}: open dialog`).toHaveAttribute('aria-modal', 'true');
    await expect(to).toBeFocused();
    if (label === '1440') await shot(page, 'compose-dialog-1440-day');

    // At 027d878 press 18 (1440) / 12 (390) landed on the search field behind the scrim.
    const tabEscapes: number[] = [];
    for (let i = 1; i <= 30; i++) { await page.keyboard.press('Tab'); if (!await insideDialog(page)) tabEscapes.push(i); }
    expect.soft(tabEscapes, `${label}: Tab presses that left the dialog`).toEqual([]);
    // Bring focus back into the dialog (at 027d878 it may have left it).
    await to.focus();
    // At 027d878 Shift+Tab press 2 already landed on summary.dm-new-btn.
    const shiftEscapes: number[] = [];
    for (let i = 1; i <= 30; i++) { await page.keyboard.press('Shift+Tab'); if (!await insideDialog(page)) shiftEscapes.push(i); }
    expect.soft(shiftEscapes, `${label}: Shift+Tab presses that left the dialog`).toEqual([]);
    measure(`${label} escapes`, { tab: tabEscapes, shiftTab: shiftEscapes });

    // Escape with the suggestion list open closes only the list.
    if (!await details.evaluate(d => (d as HTMLDetailsElement).open)) await page.locator('.dm-new-btn').click();
    await to.focus();
    await to.fill('bo');
    const suggest = page.locator('.dm-dialog .dm-suggest');
    await expect(suggest).toBeVisible();
    await page.keyboard.press('Escape');
    await expect.soft(suggest, `${label}: Escape closes the list`).toBeHidden();
    await expect.soft(details, `${label}: …and only the list`).toHaveAttribute('open', '');
    // With the list closed, Escape closes the dialog and returns focus to "+".
    // At 027d878 the picker swallowed every Escape, so the dialog stayed open.
    await page.keyboard.press('Escape');
    await expect.soft(details, `${label}: second Escape closes the dialog`).not.toHaveAttribute('open', /.*/);
    await expect.soft(page.locator('.dm-new-btn'), `${label}: focus returns to "+"`).toBeFocused();
    await expect.soft(dialog, `${label}: closed dialog drops aria-modal`).not.toHaveAttribute('aria-modal', /.*/);
    expect.soft(errors).toEqual([]);
    await context.close();
  }

  // Without JS the dialog is a plain disclosure panel: no role, no aria-modal.
  const { context, page } = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, javaScriptEnabled: false });
  await page.goto('/messages');
  await page.locator('.dm-new-btn').click();
  await expect(page.locator('details.dm-compose-details')).toHaveAttribute('open', '');
  await expect.soft(page.locator('.dm-dialog')).not.toHaveAttribute('role', /.*/);
  await expect.soft(page.locator('.dm-dialog')).not.toHaveAttribute('aria-modal', /.*/);
  await context.close();
});

test('P1-2 short screens: the letters keep a readable floor, the phone dock rests as one row and opens with Send in view', async ({ browser, baseURL }) => {
  test.setTimeout(300000);
  const route = `/messages/${seed().counsel.id}`;
  try {
    for (const wysiwyg of [false, true]) {
      setWysiwyg(wysiwyg);
      clearDrafts('alice');
      const mode = wysiwyg ? 'wysiwyg' : 'textarea';
      const input = wysiwyg ? '.dm-composer .ProseMirror' : '.dm-composer textarea.composer-input';

      // 640x400 at dsf 2 = 1280x800 zoomed to 200%. At 027d878 the letters got 34px.
      {
        const { context, page, errors } = await member(browser, baseURL!, 'alice', ZOOM);
        await page.goto(route);
        await settle(page);
        const dock = page.locator('.dm-composer');
        const rest = { pane: await paneHeight(page), dock: (await dock.boundingBox())!.height };
        measure(`${mode} 640x400 at rest`, rest);
        expect.soft(rest.pane, `${mode} 640x400 letters at rest`).toBeGreaterThanOrEqual(128);
        await expect.soft(dock, `${mode} 640x400 is a dock`).toHaveAttribute('data-composer-dock', '1');
        await expect.soft(dock, `${mode} 640x400 rests folded`).not.toHaveClass(/is-expanded/);
        expect.soft(rest.dock, `${mode} 640x400 dock at rest is one row`).toBeLessThan(100);
        await expect.soft(page.locator('.dm-composer .composer-send'), `${mode} 640x400 Send at rest`).toBeInViewport();
        await expect.soft.poll(() => paneGap(page), { message: `${mode} 640x400 opens at the newest letter` }).toBeLessThan(2);
        expect.soft(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        if (!wysiwyg) await shot(page, 'conversation-640x400-zoom-rest');
        await page.locator(input).click();
        await expect.soft(dock, `${mode} 640x400 opens on focus`).toHaveClass(/is-expanded/);
        await settle(page, 300);
        const open = { pane: await paneHeight(page), overflow: await page.evaluate(() => document.documentElement.scrollHeight - innerHeight) };
        measure(`${mode} 640x400 expanded`, open);
        expect.soft(open.pane, `${mode} 640x400 letters with the dock open`).toBeGreaterThanOrEqual(128);
        // The room is no longer locked to the viewport: the page scrolls instead.
        expect.soft(open.overflow, `${mode} 640x400 page scrolls`).toBeGreaterThan(100);
        await expect.soft.poll(() => paneGap(page), { message: `${mode} 640x400 stays at the newest letter` }).toBeLessThan(2);
        await expect.soft(page.locator('.dm-composer .composer-send'), `${mode} 640x400 Send after focus`).toBeInViewport();
        if (!wysiwyg) await shot(page, 'conversation-640x400-zoom-expanded');
        expect.soft(errors).toEqual([]);
        await context.close();
      }

      // Phone at rest: the empty dock took 285px of 844 at 027d878.
      {
        const { context, page, errors } = await member(browser, baseURL!, 'alice', TOUCH);
        await page.goto(route);
        await settle(page);
        const dock = page.locator('.dm-composer');
        const rest = { pane: await paneHeight(page), dock: (await dock.boundingBox())!.height };
        measure(`${mode} 390 at rest`, rest);
        await expect.soft(dock, `${mode} 390 rests folded`).not.toHaveClass(/is-expanded/);
        expect.soft(rest.dock, `${mode} 390 dock at rest`).toBeLessThan(120);
        expect.soft(rest.pane, `${mode} 390 letters at rest`).toBeGreaterThan(500);
        await expect.soft.poll(() => paneGap(page), { message: `${mode} 390 opens at the newest letter` }).toBeLessThan(2);
        await shot(page, `conversation-390-touch-rest${wysiwyg ? '-wysiwyg' : ''}`);
        await page.locator(input).tap();
        await expect.soft(dock, `${mode} 390 opens on tap`).toHaveClass(/is-expanded/);
        await settle(page, 300);
        // Still at the newest letter, and focusing the dock brought Send into view.
        await expect.soft.poll(() => paneGap(page), { message: `${mode} 390 stays at the newest letter` }).toBeLessThan(2);
        const lastLine = (await page.locator('.dm-line').last().boundingBox())!;
        const pane = (await page.locator('[data-dm-scroll]').boundingBox())!;
        expect.soft(lastLine.y + lastLine.height, `${mode} 390 newest letter inside the pane`).toBeLessThanOrEqual(pane.y + pane.height + 1);
        await expect.soft(page.locator('.dm-composer .composer-send'), `${mode} 390 Send after focus`).toBeInViewport();
        measure(`${mode} 390 expanded`, { dock: (await dock.boundingBox())!.height, pane: await paneHeight(page) });
        await shot(page, `conversation-390-touch-expanded${wysiwyg ? '-wysiwyg' : ''}`);

        if (!wysiwyg) {
          // A whitespace-only send comes back 422; the dock must stay open around
          // its error when the member taps elsewhere. Script disables Send for a
          // blank body, so the form is submitted directly, as a no-JS page would.
          await page.locator('.dm-composer textarea.composer-input').fill('   ');
          await Promise.all([page.waitForEvent('load'), page.locator('.dm-composer').evaluate(form => { (form as HTMLFormElement).noValidate = true; (form as HTMLFormElement).submit(); })]);
          await expect(page.locator('.dm-composer .field-error')).toBeVisible();
          await expect.soft(page.locator('.dm-composer'), '422 arrives open').toHaveClass(/is-expanded/);
          await page.locator('.dm-thread-head').tap({ position: { x: 200, y: 8 } });
          await settle(page, 300);
          await expect.soft(page.locator('.dm-composer'), '422 stays open after a tap outside').toHaveClass(/is-expanded/);
          await expect.soft(page.locator('.dm-composer .field-error')).toBeVisible();
        }
        expect.soft(errors).toEqual([]);
        await context.close();
      }
    }
  } finally {
    setWysiwyg(false);
  }
});

test('P1-3 prose links are underlined in letters and posts; image links are not; pagers are controls', async ({ browser, baseURL }) => {
  test.setTimeout(120000);
  const { counsel, paged } = seed();
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  await page.goto(`/messages/${counsel.id}`);
  const decoration = (selector: string) => page.locator(selector).first().evaluate(a => getComputedStyle(a).textDecorationLine);
  expect.soft(await decoration('.dm-group:not(.mine) .dm-body a[href="https://example.com/runbook"]'), 'their link').toBe('underline');
  expect.soft(await decoration('.dm-group.mine .dm-body a[href="https://example.com/drill"]'), 'my link').toBe('underline');
  expect.soft(await decoration('.dm-body a.mention'), 'mention').toBe('underline');
  await expect(page.locator('.dm-body a[href="https://example.com/diagram"] > img')).toHaveCount(1);
  expect.soft(await decoration('.dm-body a[href="https://example.com/diagram"]'), 'image-only link').toBe('none');

  // Forum posts share .formatted-content, so the same rule reaches them.
  const [threadId, slug] = forumThreadPath().split('|');
  await page.goto(`/t/${threadId}-${slug}`);
  const token = await page.locator('input[name=_token]').first().inputValue();
  const reply = await page.request.post(`/t/${threadId}/reply`, { form: { _token: token, body: 'The council note is on [the forum note](https://example.com/forum-note) for everyone.' }, maxRedirects: 0 });
  expect(reply.status()).toBe(303);
  // The Location differs from this page only by #p…, which would be a
  // same-document jump; load it afresh.
  await page.goto(reply.headers().location!.replace(/#.*$/, ''));
  expect.soft(await decoration('.post-body.formatted-content a[href="https://example.com/forum-note"]'), 'forum post link').toBe('underline');

  // "Earlier messages" was the only way back and read as body text.
  const control = (selector: string) => page.locator(selector).evaluate(el => {
    const cs = getComputedStyle(el), C = (window as any).__rbColor;
    return { border: cs.borderTopStyle, borderWidth: parseFloat(cs.borderTopWidth), fill: C.rgba(cs.backgroundColor)[3], decoration: cs.textDecorationLine, height: el.getBoundingClientRect().height };
  });
  await page.goto(`/messages/${paged.id}`);
  await expect(page.locator('.dm-earlier')).toBeVisible();
  const earlier = await control('.dm-earlier');
  await page.goto(`/messages/${paged.id}?page=1`);
  const latest = await control('.dm-latest');
  measure('pagers 1440', { earlier, latest });
  for (const [name, m] of [['Earlier messages', earlier], ['Latest messages', latest]] as const) {
    expect.soft(m.border, `${name} border`).toBe('solid');
    expect.soft(m.borderWidth, `${name} border width`).toBeGreaterThan(0);
    expect.soft(m.fill, `${name} fill`).toBeGreaterThan(0);
    expect.soft(m.decoration, `${name} decoration`).toBe('none');
  }
  expect.soft(errors).toEqual([]);
  await context.close();

  const phone = await member(browser, baseURL!, 'alice', TOUCH);
  await phone.page.goto(`/messages/${paged.id}`);
  const earlierTouch = (await phone.page.locator('.dm-earlier').boundingBox())!.height;
  await phone.page.goto(`/messages/${paged.id}?page=1`);
  const latestTouch = (await phone.page.locator('.dm-latest').boundingBox())!.height;
  measure('pagers 390 touch height', { earlierTouch, latestTouch });
  expect.soft(earlierTouch, 'Earlier messages on touch').toBeGreaterThanOrEqual(44);
  expect.soft(latestTouch, 'Latest messages on touch').toBeGreaterThanOrEqual(44);
  await phone.context.close();
});

async function newButtonContrast(page: Page) {
  return page.locator('.dm-new-btn').evaluate(el => {
    const C = (window as any).__rbColor;
    const icon = el.querySelector('svg') ?? el;
    const fg = C.rgba(getComputedStyle(icon).color);
    const bg = C.backdrop(el);
    return { color: getComputedStyle(icon).color, ratio: Math.round(C.contrast(C.over(fg, bg), bg) * 100) / 100 };
  });
}

test('P1-4 OS-dark on the default system theme: the "+" icon clears 3:1, same as explicit twilight', async ({ browser, baseURL }) => {
  const { context, page, errors } = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, colorScheme: 'dark' });
  await page.goto('/messages');
  expect(await page.evaluate(() => document.documentElement.dataset.theme), 'members default to the system theme').toBe('system');
  expect(await page.evaluate(() => matchMedia('(prefers-color-scheme: dark)').matches)).toBe(true);
  await settle(page);
  const system = await newButtonContrast(page);
  await shot(page, 'list-1440-os-dark');
  await page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; });
  await settle(page);
  const explicit = await newButtonContrast(page);
  measure('"+" icon contrast', { system, explicit });
  // 027d878: 2.86:1 on system + OS-dark, 7.3:1 with data-theme="dark".
  expect.soft(system.ratio, `system/OS-dark icon ${system.color}`).toBeGreaterThanOrEqual(3);
  expect.soft(system.color, 'same colour as explicit twilight').toBe(explicit.color);
  expect.soft(system.ratio).toBeCloseTo(explicit.ratio, 1);
  expect.soft(errors).toEqual([]);
  await context.close();
});

test('P1-5 the ··· menu is a plain disclosure: no role="menu", no axe violations while open', async ({ browser, baseURL }) => {
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  await page.goto(`/messages/${seed().counsel.id}`);
  await page.locator('.dm-thread-actions .dm-menu > summary').click();
  await expect(page.locator('.dm-thread-actions .dm-menu')).toHaveAttribute('open', '');
  await expect(page.locator('.dm-menu-pop')).toBeVisible();
  expect.soft(await page.locator('.dm-menu-pop').getAttribute('role'), 'menu role').toBeNull();
  await expect.soft(page.locator('.dm-menu-pop [role=menuitem]')).toHaveCount(0);
  await settle(page, 300);
  await axe(page, '.dm-thread-actions', '··· menu open');
  await shot(page, 'more-menu-open-1440-day');
  expect.soft(errors).toEqual([]);
  await context.close();
});

test('P1-6 filter pills: the selected pill differs from the unselected one by 3:1 in day and carries aria-current', async ({ browser, baseURL }) => {
  const { context, page, errors } = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, colorScheme: 'light' });
  const pills = async () => page.locator('.dm-listpane-filters').evaluate(box => {
    const C = (window as any).__rbColor;
    const active = box.querySelector('.pill.is-active')!, other = box.querySelector('.pill:not(.is-active)')!;
    const a = C.backdrop(active), o = C.backdrop(other), pane = C.backdrop(box);
    const r = (n: number) => Math.round(n * 100) / 100;
    return { active: active.textContent!.trim(), current: active.getAttribute('aria-current'), otherCurrent: other.getAttribute('aria-current'), vsOther: r(C.contrast(a, o)), vsPane: r(C.contrast(a, pane)) };
  });
  for (const [route, label] of [['/messages', 'All'], ['/messages?filter=unread', 'Unread']] as const) {
    await page.goto(route);
    await settle(page);
    const m = await pills();
    measure(`${route} pills`, m);
    expect(m.active.startsWith(label), `${route} selected pill "${m.active}"`).toBe(true);
    expect.soft(m, `${route} aria-current`).toMatchObject({ current: 'page', otherCurrent: null });
    // 027d878: 1.05:1 against the unselected pill, 1.01:1 against the pane.
    expect.soft(m.vsOther, `${route} selected vs unselected`).toBeGreaterThanOrEqual(3);
    expect.soft(m.vsPane, `${route} selected vs pane`).toBeGreaterThanOrEqual(3);
    if (label === 'All') {
      fs.mkdirSync(evidence, { recursive: true });
      await page.locator('.dm-listpane-head').screenshot({ path: path.join(evidence, 'filter-pills-1440-day.png') });
    }
  }
  expect.soft(errors).toEqual([]);
  await context.close();
});

// ── P2 ──────────────────────────────────────────────────────────────────────

test('P2-1 the saved details choice reopens only a column; overlays start closed with the back control reachable', async ({ browser, baseURL }) => {
  test.setTimeout(120000);
  const { counsel, carol } = seed();
  const wide = await member(browser, baseURL!, 'alice', { viewport: { width: 1800, height: 1000 } });
  await wide.page.goto(`/messages/${counsel.id}`);
  await expect(wide.page.locator('[data-rail-toggle]')).toHaveAttribute('aria-expanded', 'false');
  await wide.page.locator('[data-rail-toggle]').click();
  await expect(wide.page.locator('.dm-shell')).toHaveClass(/rail-open/);
  await expect(wide.page.locator('#dm-rail')).not.toHaveCSS('position', 'fixed');
  expect(await wide.page.evaluate(() => localStorage.getItem('rb-dm-rail-collapsed'))).toBe('0');
  const remembered = await wide.context.storageState();

  for (const [label, options] of [
    ['390-touch', TOUCH],
    ['1280', { viewport: { width: 1280, height: 800 } }],
    ['1440-board-rail-open', { viewport: DESKTOP }],
  ] as const) {
    const context = await browser.newContext({ baseURL, colorScheme: 'light', ...options, storageState: remembered });
    const page = await context.newPage();
    await page.goto(`/messages/${carol.id}`);
    if (label === '1440-board-rail-open') await expect(page.locator('.board-rail')).toBeVisible();
    await expect.soft(page.locator('.dm-shell'), `${label}: arrives closed`).not.toHaveClass(/rail-open/);
    await expect.soft(page.locator('[data-rail-toggle]'), label).toHaveAttribute('aria-expanded', 'false');
    await expect.soft(page.locator('#dm-rail'), label).not.toBeInViewport();
    expect.soft(await page.evaluate(() => localStorage.getItem('rb-dm-rail-collapsed')), `${label} keeps the column choice`).toBe('0');
    if (label === '390-touch') {
      const back = await page.locator('.dm-back').evaluate(el => {
        const r = el.getBoundingClientRect();
        const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
        return !!hit && (hit === el || el.contains(hit));
      });
      expect.soft(back, 'the back control is not covered by the rail').toBe(true);
    }
    if (label === '1280') {
      if (!await page.locator('.dm-shell').evaluate(n => n.classList.contains('rail-open'))) await page.locator('[data-rail-toggle]').click();
      await expect(page.locator('#dm-rail')).toHaveCSS('position', 'fixed');
      await expect(page.locator('#dm-rail')).toBeInViewport();
      await shot(page, 'rail-drawer-1280-day');
      // A drawer is a passing look: closing it does not rewrite the column choice.
      await page.keyboard.press('Escape');
      await expect.soft(page.locator('[data-rail-toggle]')).toBeFocused();
      expect.soft(await page.evaluate(() => localStorage.getItem('rb-dm-rail-collapsed')), '1280 drawer close keeps the column choice').toBe('0');
    }
    await context.close();
  }

  // A column-width page still restores the choice.
  await wide.page.goto(`/messages/${carol.id}`);
  await expect.soft(wide.page.locator('.dm-shell'), '1800 restores the column').toHaveClass(/rail-open/);
  await expect.soft(wide.page.locator('[data-rail-toggle]')).toHaveAttribute('aria-expanded', 'true');
  await expect.soft(wide.page.locator('#dm-rail')).toBeInViewport();
  expect.soft(wide.errors).toEqual([]);
  await wide.context.close();
});

test('P2-2 Search and the To chip field wear the shared focus outline on keyboard focus', async ({ browser, baseURL }) => {
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  const ring = (selector: string) => page.locator(selector).evaluate(el => {
    const cs = getComputedStyle(el);
    return { style: cs.outlineStyle, width: parseFloat(cs.outlineWidth) };
  });
  await page.goto('/messages');
  await page.locator('.dm-new-btn').focus();
  for (let i = 0; i < 8 && !await page.locator('.dm-search input').evaluate(el => el === document.activeElement); i++) await page.keyboard.press('Tab');
  await expect(page.locator('.dm-search input')).toBeFocused();
  await settle(page, 300);
  const search = await ring('.dm-search input');

  await page.locator('.dm-new-btn').click();
  await expect(page.locator('.dm-dialog .dm-to-input')).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Tab');
  await expect(page.locator('.dm-dialog .dm-to-input')).toBeFocused();
  await settle(page, 300);
  const dialogField = await ring('.dm-dialog .dm-to-field');

  await page.goto('/messages/new');
  await page.locator('.dm-compose .dm-to-input').focus();
  await page.keyboard.press('Shift+Tab');
  await page.keyboard.press('Tab');
  await expect(page.locator('.dm-compose .dm-to-input')).toBeFocused();
  await settle(page, 300);
  const newField = await ring('.dm-compose .dm-to-field');
  measure('focus outlines', { search, dialogField, newField });
  for (const [name, m] of [['Search', search], ['dialog To field', dialogField], ['/messages/new To field', newField]] as const) {
    expect.soft(m.style, `${name} outline style`).toBe('solid');
    expect.soft(m.width, `${name} outline width`).toBeGreaterThanOrEqual(2);
  }
  expect.soft(errors).toEqual([]);
  await context.close();
});

test('P2-3 a group composer addresses the group; a direct one keeps the counterpart', async ({ browser, baseURL }) => {
  const { counsel, group } = seed();
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  await page.goto(`/messages/${group.id}`);
  const body = page.locator('.dm-composer textarea[name=body]');
  await expect.soft(body).toHaveAttribute('placeholder', 'Message the group…');
  await expect.soft(body).toHaveAttribute('aria-label', 'Message the group…');
  await page.goto(`/messages/${counsel.id}`);
  await expect.soft(body).toHaveAttribute('placeholder', 'Message @bob…');
  await page.goto('/messages/new?to=bob,carol');
  await expect.soft(page.locator('.dm-compose textarea[name=body]')).toHaveAttribute('placeholder', 'Message the group…');
  await page.goto('/messages/new?to=bob');
  await expect.soft(page.locator('.dm-compose textarea[name=body]')).toHaveAttribute('placeholder', 'Message @bob…');
  expect.soft(errors).toEqual([]);
  await context.close();
});

test('P2-4 touch: every Messages control takes a 44x44 tap', async ({ browser, baseURL }) => {
  const { counsel, group } = seed();
  const { context, page, errors } = await member(browser, baseURL!, 'alice', TOUCH);
  const hits: Record<string, unknown> = {};
  await page.goto('/messages');
  await page.evaluate(() => window.scrollTo(0, 0));
  hits['New message'] = await hitArea(page.locator('.dm-new-btn'));
  const pills = page.locator('.dm-listpane-filters .pill');
  for (let i = 0; i < await pills.count(); i++) hits[`filter pill ${i}`] = await hitArea(pills.nth(i));

  await page.goto(`/messages/${counsel.id}`);
  await page.evaluate(() => window.scrollTo(0, 0));
  for (const [selector, name] of [['.dm-back', 'Back'], ['[data-rail-toggle]', 'Details'], ['.dm-thread-actions .dm-menu > summary', 'More']] as const) {
    const box = (await page.locator(selector).boundingBox())!;
    expect.soft(Math.min(box.width, box.height), `${name} box`).toBeGreaterThanOrEqual(44);
    hits[name] = await hitArea(page.locator(selector));
  }
  // A letter's ··· in the middle of a run: its hit slop must not be shadowed by
  // the next letter's (the later one would win elementFromPoint).
  const dots = page.locator('.dm-group:not(.mine) .dm-line:not(:last-child) .dm-dotbtn');
  expect(await dots.count()).toBeGreaterThan(1);
  hits['letter ···'] = await hitArea(dots.nth(1));
  for (const [name, hit] of Object.entries(hits)) expect.soft(hit, name).toMatchObject(FULL_HIT);

  await page.goto(`/messages/${group.id}`);
  await page.locator('[data-rail-toggle]').tap();
  await expect(page.locator('#dm-rail')).toBeInViewport();
  await settle(page, 300);
  const tools = page.locator('.dm-inforail .dm-member-tools .dm-linkbtn');
  expect(await tools.count()).toBe(4);
  for (let i = 0; i < 4; i++) {
    const box = (await tools.nth(i).boundingBox())!;
    hits[`owner tool ${i}`] = `${Math.round(box.width)}x${Math.round(box.height)}`;
    expect.soft(box.height, `owner tool ${i} height`).toBeGreaterThanOrEqual(44);
    expect.soft(box.width, `owner tool ${i} width`).toBeGreaterThanOrEqual(44);
    expect.soft(await hitArea(tools.nth(i)), `owner tool ${i}`).toMatchObject({ centre: true, up: true, down: true });
  }
  measure('touch hit areas', hits);
  expect.soft(errors).toEqual([]);
  await context.close();
});

test('P2-5 twilight: the own-letter edge is register-aware, not the pale day gold', async ({ browser, baseURL }) => {
  const route = `/messages/${seed().counsel.id}`;
  const edge = (page: Page) => page.locator('.dm-group.mine .dm-body').first().evaluate(el => {
    const C = (window as any).__rbColor, cs = getComputedStyle(el);
    const line = C.over(C.rgba(cs.borderTopColor), C.backdrop(el));
    return { raw: cs.borderTopColor, vsPane: Math.round(C.contrast(line, C.backdrop(el.parentElement)) * 100) / 100 };
  });
  const day = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, colorScheme: 'light' });
  await day.page.goto(route);
  await settle(day.page);
  await shot(day.page, 'conversation-1440-day');
  const daylight = await edge(day.page);
  await day.page.evaluate(() => { document.documentElement.dataset.theme = 'dark'; });
  await settle(day.page);
  const twilight = await edge(day.page);
  await day.context.close();
  const os = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, colorScheme: 'dark' });
  await os.page.goto(route);
  await settle(os.page);
  const osDark = await edge(os.page);
  await shot(os.page, 'conversation-1440-os-dark');
  await os.context.close();
  measure('own-letter edge', { daylight, twilight, osDark });
  // 027d878: gold-200 (#EAD9A8) in every register, ~10:1 against the twilight pane.
  for (const [label, m] of [['data-theme=dark', twilight], ['OS-dark system', osDark]] as const) {
    expect.soft(m.raw, label).not.toBe('rgb(234, 217, 168)');
    expect.soft(m.vsPane, `${label} edge vs pane`).toBeLessThan(4);
  }
});

test('P2-6 a no-JS reply lands on its letter; a scripted reply stays unanchored at the end', async ({ browser, baseURL }) => {
  test.setTimeout(120000);
  const { landing, landingPhone } = seed();
  for (const [label, options, conversation] of [
    ['1440', { viewport: DESKTOP }, landing],
    ['390-touch', TOUCH, landingPhone],
  ] as const) {
    const { context, page } = await member(browser, baseURL!, 'alice', { ...options, javaScriptEnabled: false });
    const route = `/messages/${conversation.id}`;
    await page.goto(route);
    const text = `No-JS landing check at ${label}.`;
    await page.locator('.dm-composer textarea[name=body]').fill(text);
    await Promise.all([page.waitForEvent('load'), page.locator('.dm-composer .composer-send').click()]);
    // 027d878 redirected to the bare route, opening at the top of the page.
    expect.soft(page.url(), `${label} lands on #m{id}`).toMatch(new RegExp(`${route}#m\\d+$`));
    const letter = page.locator('.dm-line').filter({ hasText: text });
    await expect(letter).toHaveCount(1);
    const gap = await paneGap(page);
    measure(`no-JS ${label}`, { url: page.url(), gap });
    await expect.soft(letter, `${label} new letter in view`).toBeInViewport();
    await expect.soft(page.locator('.dm-composer'), `${label} composer in view`).toBeInViewport();
    expect.soft(gap, `${label} pane at its end`).toBeLessThan(2);
    await shot(page, `no-js-reply-landing-${label}`);
    await context.close();
  }

  // With JS the Location stays bare (a #m would suppress app.js's pin-to-end
  // on this load and on every reload/Back), and the pane is at its end.
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  const route = `/messages/${landing.id}`;
  await page.goto(route);
  await page.locator('.dm-composer textarea[name=body]').fill('Scripted landing check.');
  await Promise.all([page.waitForEvent('load'), page.locator('.dm-composer .composer-send').click()]);
  expect.soft(new URL(page.url()).pathname).toBe(route);
  expect.soft(new URL(page.url()).hash, 'scripted send stays unanchored').toBe('');
  await expect(page.locator('.dm-line').last()).toContainText('Scripted landing check.');
  await expect.soft.poll(() => paneGap(page), { message: 'scripted send pinned to the end' }).toBeLessThan(2);
  await expect.soft(page.locator('[data-dm-newpill]')).toBeHidden();
  await page.reload();
  await expect.soft.poll(() => paneGap(page), { message: 'reload pinned to the end' }).toBeLessThan(2);
  expect.soft(errors).toEqual([]);
  await context.close();
});

// ── P3 ──────────────────────────────────────────────────────────────────────

test('P3-2 list previews read as words, not Markdown, and the instant filter still matches the raw letter', async ({ browser, baseURL }) => {
  const { unread } = seed();
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  await page.goto('/messages');
  const preview = page.locator(`.dm-row[href="/messages/${unread.id}"] .dm-preview`);
  const text = (await preview.innerText()).replace(/\s+/g, ' ').trim();
  measure('preview', text);
  for (const markup of ['```', '`', '**', '_em_', '](', '- Keep', '> Quoted']) expect.soft(text, `preview contains ${markup}`).not.toContain(markup);
  expect.soft(text).toContain("echo 'hi'; See worker:packages, bold, em and the runbook.");
  expect.soft(text).toContain('Keep it quiet.');
  const row = page.locator(`.dm-list li:has(.dm-row[href="/messages/${unread.id}"])`);
  await page.locator('.dm-search input[name=q]').fill('example.com');
  await expect.soft(row, 'the filter still matches a link URL').not.toHaveClass(/is-filtered/);
  await page.locator('.dm-search input[name=q]').fill('qqqzzz');
  await expect.soft(row).toHaveClass(/is-filtered/);
  expect.soft(errors).toEqual([]);
  await context.close();
});

test('P3-5 one h1 per page, owner tools name the member, unread leads the row name, the details toggle is a button', async ({ browser, baseURL }) => {
  test.setTimeout(120000);
  const { counsel, group, unread } = seed();
  const { context, page, errors } = await member(browser, baseURL!, 'alice');
  for (const [route, heading] of [['/messages', 'Messages'], [`/messages/${counsel.id}`, 'Bob Brooks'], ['/messages/new', 'New message']] as const) {
    await page.goto(route);
    const h1 = page.getByRole('heading', { level: 1 });
    measure(`h1 on ${route}`, await h1.allInnerTexts());
    await expect.soft(h1, `h1 count on ${route}`).toHaveCount(1);
    await expect.soft(h1.first()).toHaveText(heading);
  }

  await page.goto('/messages');
  const unreadRow = page.locator(`.dm-row[href="/messages/${unread.id}"]`);
  await expect(unreadRow).toHaveClass(/is-unread/);
  await expect.soft(unreadRow, 'unread row name leads with Unread').toHaveAccessibleName(/^Unread\b/);
  await expect.soft(unreadRow.locator('.dm-unread-dot')).toHaveAttribute('aria-hidden', 'true');

  // 1440 beside the open board rail: the details rail is a drawer.
  await page.goto(`/messages/${counsel.id}`);
  const toggle = page.locator('[data-rail-toggle]');
  await expect.soft(toggle, 'details toggle role').toHaveAttribute('role', 'button');
  await expect.soft(toggle).toHaveAttribute('aria-expanded', 'false');
  await expect.soft(page.getByRole('button', { name: 'Details', exact: true })).toHaveCount(1);
  await toggle.focus();
  const scrollY = await page.evaluate(() => window.scrollY);
  await page.keyboard.press(' ');
  await expect.soft(page.locator('.dm-shell'), 'Space opens the rail').toHaveClass(/rail-open/);
  await expect.soft(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect.soft(page.locator('[data-rail-close]').first()).toBeFocused();
  expect.soft(await page.evaluate(() => window.scrollY), 'Space does not scroll the page').toBe(scrollY);
  await page.keyboard.press('Escape');
  await expect.soft(toggle, 'Escape returns focus to the toggle').toBeFocused();

  await page.goto(`/messages/${group.id}`);
  await page.locator('[data-rail-toggle]').click();
  await expect(page.locator('#dm-rail')).toBeInViewport();
  for (const name of ['Make owner: Bob Brooks', 'Remove Bob Brooks', 'Make owner: Carol Chen', 'Remove Carol Chen']) {
    await expect.soft(page.getByRole('button', { name, exact: true }), `owner tool named "${name}"`).toBeVisible();
  }
  expect.soft(errors).toEqual([]);
  await context.close();

  // Without JS the toggle is a plain link that claims no state.
  const nojs = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, javaScriptEnabled: false });
  await nojs.page.goto(`/messages/${counsel.id}`);
  await expect.soft(nojs.page.getByRole('heading', { level: 1 }), 'no-JS h1 count').toHaveCount(1);
  await expect.soft(nojs.page.locator('[data-rail-toggle]'), 'no-JS toggle claims no state').not.toHaveAttribute('aria-expanded', /.*/);
  await expect.soft(nojs.page.locator('[data-rail-toggle]')).not.toHaveAttribute('role', /.*/);
  await nojs.context.close();
});

test('P3-6 clocks, titles and the details presence read cleanly (en-US, America/Chicago)', async ({ browser, baseURL }) => {
  const { counsel } = seed();
  setOnline('bob');
  const { context, page, errors } = await member(browser, baseURL!, 'alice', { viewport: DESKTOP, locale: 'en-US', timezoneId: 'America/Chicago' });
  await page.goto(`/messages/${counsel.id}`);
  await expect(page.locator('.dm-gtime').first()).not.toBeEmpty();
  const clocks = (await page.locator('.dm-gtime').allInnerTexts()).map(t => t.replace(/\s+/g, ' ').trim());
  const titles = await page.locator('time[data-dm-time][title]').evaluateAll(nodes => nodes.map(n => n.getAttribute('title')!));
  measure('clocks and titles', { clocks, titles: titles.slice(0, 3) });
  // 14:05Z is 9:05 AM in Chicago; 027d878 printed "09:05 AM".
  expect.soft(clocks[0], 'first run clock').toBe('9:05 AM');
  for (const clock of clocks) expect.soft(clock).toMatch(/^[1-9]\d?:\d{2} [AP]M$/);
  expect(titles.length).toBeGreaterThan(0);
  // 027d878's JS rewrote them to "2026-09-20T14:05:00Z UTC".
  for (const title of titles) expect.soft(title, 'time title names its zone once').toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} UTC$/);

  await expect.soft(page.locator('.dm-thread-sub')).toContainText('·');
  await page.locator('[data-rail-toggle]').click();
  const presence = page.locator('.dm-inforail .dm-rail-id [data-dm-presence]');
  await expect(presence).toBeVisible();
  expect.soft((await presence.textContent())!.trim(), 'details presence has no leading separator').toBe('Here now');
  expect.soft(errors).toEqual([]);
  await context.close();
});

// ── Axe in the two conditions the audit said were never run ─────────────────

test('axe: OS-dark system theme and 640x400 zoom over the list and a conversation with the rail and ··· menu open', async ({ browser, baseURL }) => {
  test.setTimeout(180000);
  const { counsel, group } = seed();
  for (const [label, options] of [
    ['os-dark-1440', { viewport: DESKTOP, colorScheme: 'dark' }],
    ['zoom-640x400', ZOOM],
  ] as const) {
    const { context, page, errors } = await member(browser, baseURL!, 'alice', options);
    await page.goto('/messages');
    await settle(page);
    await axe(page, '.dm-shell', `${label} /messages`);
    for (const route of [`/messages/${counsel.id}`, `/messages/${group.id}`]) {
      await page.goto(route);
      await settle(page);
      await axe(page, '.dm-shell', `${label} ${route}`);
      await page.locator('[data-rail-toggle]').click();
      await expect(page.locator('.dm-shell')).toHaveClass(/rail-open/);
      await settle(page);
      await axe(page, '.dm-shell', `${label} ${route} rail open`);
      if (label === 'zoom-640x400' && route.endsWith(`/${counsel.id}`)) await shot(page, 'rail-drawer-640x400-zoom');
      await page.keyboard.press('Escape');
      await expect(page.locator('.dm-shell')).not.toHaveClass(/rail-open/);
      await page.locator('.dm-thread-actions .dm-menu > summary').click();
      await expect(page.locator('.dm-thread-actions .dm-menu')).toHaveAttribute('open', '');
      await settle(page, 300);
      await axe(page, '.dm-shell', `${label} ${route} ··· menu open`);
      if (label === 'os-dark-1440' && route.endsWith(`/${counsel.id}`)) await shot(page, 'more-menu-open-1440-os-dark');
    }
    expect.soft(errors).toEqual([]);
    await context.close();
  }
});
