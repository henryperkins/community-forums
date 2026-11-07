import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// ADR 0057 evidence: the Forum theme's typographic contract holds on the
// retained component and application layers, labels are written in sentence
// case, link-style controls centre their labels, and short viewports keep the
// topic readable. Uses the standard disposable browser fixture (prepare.sh); no
// database helpers or flag changes. Run with `npm run evidence:forum`.
const OUT = path.resolve(__dirname, '../..', process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/forum-refinement');
const axeTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'];
const LONG_NAME = 'Maximiliana Theodora Featherstonehaugh-Abernathy';
test.use({ reducedMotion: 'reduce' });
test.beforeEach(({ baseURL }) => {
  if (!baseURL || !['localhost', '127.0.0.1', '[::1]'].includes(new URL(baseURL).hostname)) {
    throw new Error('Forum refinement evidence requires the disposable local browser fixture.');
  }
});

function output(info: TestInfo, name: string): string {
  const directory = path.join(OUT, info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  return path.join(directory, name);
}

async function visit(page: Page, route: string): Promise<void> {
  const response = await page.goto(route);
  expect(response?.status(), route).toBeLessThan(400);
  await page.evaluate(() => document.fonts.ready);
}

async function login(page: Page, email: string): Promise<void> {
  await visit(page, '/login');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

type TypeFinding = { kind: string; text: string; where: string; value: string };

/**
 * Visible text that breaks the Forum typographic contract: below the size
 * floor, in capitals or positive tracking, in the code face outside code, or a
 * label that starts in lower case. Covers aria-hidden text and text drawn by
 * ::before/::after; only initials and glyphs are exempt.
 */
async function typeFindings(page: Page): Promise<TypeFinding[]> {
  return page.evaluate(() => {
    const findings: { kind: string; text: string; where: string; value: string }[] = [];
    const identifier = /(^|\s)(mono|is-mono|code-area|[\w-]*-mono[\w-]*|[\w-]*uid|[\w-]*digest|[\w-]*-key|[\w-]*hex-input|[\w-]*secret[\w-]*|[\w-]*rule-id|[\w-]*emoji-path|[\w-]*pii-value|[\w-]*slug|[\w-]*-version|[\w-]*-hosts)(\s|$)/;
    const glyph = '.monogram, .dm-chip-mono, .avatar-wrap, .star-marker, svg';
    // Labels that ADR 0056's layers once forced into capitals; their text is
    // written by templates, never by members.
    const labels = [
      '.badge', '.pill', '.role-pill', '.state', '.chip', '.tag:not(a)', '.board-rail-tag', '.stat-label',
      '.account-draft-chip', '.account-state-chip', '.ref-type', '.poll-eyebrow', '.poll-eyebrow-mode',
      '.forum-directory__board-signal', '.oauth-sep', '.presence-you', '.presence-staff', '.thread-state-chip',
      '.thread-status-chip', '.thread-mark', '.content-board-chip', '.queue-card-state', '.dm-tier-pill', '.m-role',
      '.dm-rank', '.dm-receipt', '.first-unread-divider', '.features-stat-head', '.settings-rail-title',
      '.packages-eyebrow', '.packages-fact-term', '.scribe-panel-head', '.account-subhead', '.directory-viewbar__label',
      '.inbox-scope-group-label', '.search-view-label', '[class$="-eyebrow"]', '.role-risk', '.accepted-flag',
      '.admin-bar-mode', '.branding-section-label', '.thread-related-label', '.profile-activity-kind', 'th', 'dt', 'legend',
    ].join(', ');
    const hidden = (style: CSSStyleDeclaration) => style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0;
    const visible = (element: Element): boolean => {
      for (let node: Element | null = element; node; node = node.parentElement) {
        if (hidden(getComputedStyle(node))) return false;
        if (node instanceof HTMLDetailsElement && !node.open && node !== element && !element.closest('summary')) return false;
      }
      const box = element.getBoundingClientRect();
      return box.width > 1 && box.height > 1;
    };
    const describe = (element: Element, suffix = '') => `${element.tagName.toLowerCase()}.${(element.getAttribute('class') ?? '').trim().split(/\s+/).slice(0, 2).join('.')}${suffix}`;
    const measure = (style: CSSStyleDeclaration, text: string, where: string, code: boolean) => {
      if (parseFloat(style.fontSize) < 13.9) findings.push({ kind: 'size', text, where, value: style.fontSize });
      if (style.textTransform === 'uppercase' || style.textTransform === 'capitalize') findings.push({ kind: 'capitals', text, where, value: style.textTransform });
      if (style.letterSpacing !== 'normal' && parseFloat(style.letterSpacing) > 0) findings.push({ kind: 'tracking', text, where, value: style.letterSpacing });
      const family = style.fontFamily.split(',')[0].replace(/["']/g, '').trim();
      if (/mono|consol|courier|menlo/i.test(family) && !code) findings.push({ kind: 'code face', text, where, value: family });
    };
    const isCode = (element: Element) => !!element.closest('code, pre, kbd, samp')
      || [element, element.parentElement].some(candidate => candidate && identifier.test(candidate.getAttribute('class') ?? ''));

    const seen = new Set<Element>();
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const text = (node.nodeValue ?? '').replace(/\s+/g, ' ').trim();
      const element = node.parentElement;
      if (!text || !element || seen.has(element)) continue;
      seen.add(element);
      if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'OPTION'].includes(element.tagName)) continue;
      if (element.closest('.sr-only, .visually-hidden') || element.closest(glyph) || !visible(element)) continue;
      measure(getComputedStyle(element), text, describe(element), isCode(element));
    }

    for (const element of document.body.querySelectorAll('*')) {
      if (element.closest(glyph) || !visible(element)) continue;
      for (const pseudo of ['::before', '::after']) {
        const style = getComputedStyle(element, pseudo);
        const content = style.content.replace(/url\([^)]*\)/g, '');
        if (content === 'none' || content === 'normal' || hidden(style)) continue;
        if (!/attr\(|["'][^"']*[\p{L}\p{N}][^"']*["']/u.test(content)) continue;
        measure(style, content, describe(element, pseudo), isCode(element));
      }
    }

    for (const element of document.body.querySelectorAll(labels)) {
      if (element.closest('.sr-only, .visually-hidden') || !visible(element)) continue;
      const text = (element.textContent ?? '').replace(/\s+/g, ' ').trim();
      const start = text.replace(/^[^\p{L}\p{N}@#]+/u, '');
      if (/^\p{Ll}/u.test(start)) findings.push({ kind: 'lower-case label', text, where: describe(element), value: start.slice(0, 1) });
    }
    return findings;
  });
}

/** Vertical offset between a control's label and its box, in CSS pixels. */
async function labelOffsets(page: Page, selector: string): Promise<{ name: string; offset: number }[]> {
  return page.locator(selector).evaluateAll(elements => elements.flatMap(element => {
    const box = element.getBoundingClientRect();
    if (box.height < 1) return [];
    for (let node: Element | null = element; node; node = node.parentElement) {
      if (getComputedStyle(node).display === 'none') return [];
    }
    const range = document.createRange();
    range.selectNodeContents(element);
    const rects = [...range.getClientRects()].filter(rect => rect.width > 0 && rect.height > 0);
    if (!rects.length) return [];
    const top = Math.min(...rects.map(rect => rect.top));
    const bottom = Math.max(...rects.map(rect => rect.bottom));
    return [{ name: (element.textContent ?? '').replace(/\s+/g, ' ').trim(), offset: (top + bottom) / 2 - (box.top + box.bottom) / 2 }];
  }));
}

async function expectCentred(page: Page, groups: Record<string, string>): Promise<void> {
  for (const [group, selector] of Object.entries(groups)) {
    const offsets = await labelOffsets(page, selector);
    expect(offsets.length, `${group} controls were measured`).toBeGreaterThan(0);
    for (const { name, offset } of offsets) expect(Math.abs(offset), `${group}: ${name}`).toBeLessThanOrEqual(1.5);
  }
}

async function sweep(page: Page, info: TestInfo, routes: [string, string][]): Promise<void> {
  for (const [route, label] of routes) {
    await visit(page, route);
    for (const theme of ['light', 'dark']) {
      await page.evaluate(async value => {
        document.documentElement.dataset.theme = value;
        // Measure settled colours, not a frame of a theme transition. Only
        // finite animations can finish; an infinite one would hang the wait.
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const finite = document.getAnimations().filter(animation => Number.isFinite(Number(animation.effect?.getComputedTiming().endTime)));
        await Promise.race([
          Promise.all(finite.map(animation => animation.finished.catch(() => undefined))),
          new Promise(resolve => setTimeout(resolve, 2000)),
        ]);
      }, theme);
      expect.soft(await typeFindings(page), `${label} ${theme} typography`).toEqual([]);
      const scan = await new AxeBuilder({ page }).withTags(axeTags).analyze();
      fs.writeFileSync(output(info, `${label}-${theme}-axe.json`), JSON.stringify({ violations: scan.violations, incomplete: scan.incomplete }, null, 2));
      expect.soft(scan.violations.map(v => ({ id: v.id, targets: v.nodes.map(n => n.target.join(' ')) })), `${label} ${theme} axe`).toEqual([]);
      const width = await page.evaluate(() => ({ content: document.documentElement.scrollWidth, viewport: innerWidth }));
      expect.soft(width.content, `${label} ${theme} reflow`).toBeLessThanOrEqual(width.viewport + 1);
      await page.screenshot({ path: output(info, `${label}-${theme}.png`), animations: 'disabled' });
    }
  }
}

test('guest routes keep sentence case, readable sizes and the interface face', async ({ page }, info) => {
  test.setTimeout(240_000);
  await sweep(page, info, [['/', 'boards'], ['/c/general', 'board'], ['/t/1-welcome-to-retroboards', 'topic'], ['/search?q=welcome', 'search'], ['/u/alice', 'profile'], ['/login', 'login'], ['/leaderboard', 'leaderboard']]);
  // Discussion prose follows the reader's own sans-serif; interface text keeps system-ui.
  await visit(page, '/t/1-welcome-to-retroboards');
  const faces = await page.evaluate(() => ({
    prose: getComputedStyle(document.querySelector('.post-body')!).fontFamily,
    ui: getComputedStyle(document.querySelector('.post-author, .post-name')!).fontFamily,
    title: document.title,
  }));
  expect(faces.prose).toBe('sans-serif');
  expect(faces.ui).toMatch(/^system-ui/);
  expect(faces.title).toBe('Welcome to RetroBoards · RetroBoards');
});

test('member, moderation and operator routes keep the same contract', async ({ page }, info) => {
  test.setTimeout(420_000);
  await login(page, 'alice@retro.test');
  await sweep(page, info, [['/inbox', 'inbox'], ['/messages', 'messages'], ['/notifications', 'notifications'], ['/compose', 'compose'], ['/settings/appearance', 'appearance'], ['/drafts', 'drafts']]);
  // Bob belongs to the private #staff-room (its rail tag) and has a removed post to appeal.
  await page.context().clearCookies();
  await login(page, 'bob@retro.test');
  await expect(page.locator('.board-rail-tag').first()).toHaveText('Private');
  await sweep(page, info, [['/appeals', 'appeals']]);
  await page.context().clearCookies();
  await login(page, 'admin@retro.test');
  await sweep(page, info, [['/admin', 'admin'], ['/admin/users', 'admin-users'], ['/admin/structure', 'admin-structure'], ['/admin/branding', 'branding'], ['/mod/reports', 'mod-reports'], ['/mod/appeals', 'mod-appeals']]);
});

test('link-style controls centre their labels', async ({ page }, info) => {
  await visit(page, '/');
  // Phones choose the order and previews in a bottom sheet.
  if (info.project.name === 'mobile') {
    await page.locator('details.directory-viewbar-mobile > summary').click();
    await expect(page.locator('details.directory-viewbar-mobile')).toHaveAttribute('open', '');
  }
  await expectCentred(page, {
    'sign-in': '.forum-bar-signin, .forum-bar-signup',
    order: '.directory-viewbar__choices a',
    previews: '.directory-viewbar__peek a',
  });
  await visit(page, '/search?q=welcome');
  await expectCentred(page, { scope: '.search-scope-options a', 'search order': '.search-order-options a' });
  await login(page, 'admin@retro.test');
  await visit(page, '/admin/users');
  await expectCentred(page, { operator: 'a.btn, .admin-console a.btn-small' });
});

test('the composer board picker wraps a long board name inside the rail', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'The rail is a drawer on phones.');
  await login(page, 'alice@retro.test');
  await visit(page, '/compose');
  const fit = await page.locator('.compose-board-picker').first().evaluate(button => {
    const name = button.querySelector<HTMLElement>('.board-rail-name')!;
    name.textContent = 'Supercalifragilisticexpialidociousannouncements';
    const outer = button.getBoundingClientRect();
    const inner = name.getBoundingClientRect();
    return {
      inside: inner.left >= outer.left - 0.5 && inner.right <= outer.right + 0.5,
      overflow: button.scrollWidth > button.clientWidth + 0.5 || name.scrollWidth > name.clientWidth + 0.5,
    };
  });
  expect(fit).toEqual({ inside: true, overflow: false });
});

/** Each presence tag sits whole inside its row while a long name truncates. */
async function presenceRows(page: Page) {
  return page.locator('.board-rail .presence-row:has(.presence-you, .presence-staff)').evaluateAll(rows => rows.map(row => {
    const name = row.querySelector('.presence-name')!.getBoundingClientRect();
    const text = row.querySelector<HTMLElement>('.presence-name-text');
    return {
      text: text?.textContent ?? null,
      truncated: !!text && text.scrollWidth > text.clientWidth && getComputedStyle(text).textOverflow === 'ellipsis',
      tags: [...row.querySelectorAll<HTMLElement>('.presence-you, .presence-staff')].map(tag => {
        const box = tag.getBoundingClientRect();
        return { text: tag.textContent, inside: box.right <= name.right + 0.5 && box.left >= name.left - 0.5, clipped: tag.scrollWidth > tag.clientWidth };
      }),
    };
  }));
}

test('presence tags stay whole beside long names', async ({ page }, info) => {
  test.skip(info.project.name === 'mobile', 'The rail is a drawer on phones.');
  // The admin's own row carries both tags, the tightest case.
  await login(page, 'admin@retro.test');

  // Rows as the server draws them: hold the poll, then lengthen the name.
  const poll = (url: URL) => url.pathname === '/presence' && url.searchParams.get('format') === 'json';
  await page.route(poll, route => route.abort());
  await visit(page, '/c/general');
  await page.locator('.board-rail .presence-row:has(.presence-you) .presence-name-text').evaluate((element, name) => { element.textContent = name; }, LONG_NAME);
  const server = await presenceRows(page);
  expect(server.map(row => row.tags.map(tag => tag.text))).toContainEqual(['You', 'Staff']);
  expect(server.filter(row => row.text === LONG_NAME).map(row => row.truncated), 'a long server-drawn name truncates').toEqual([true]);
  for (const tag of server.flatMap(row => row.tags)) expect(tag, `${tag.text}`).toEqual({ text: tag.text, inside: true, clipped: false });

  // Rows the presence poll rebuilds must match the server's markup and labels.
  await page.unroute(poll);
  await page.route(poll, async route => {
    const response = await route.fetch();
    const data = await response.json();
    data.online = [
      { username: 'admin', display_name: LONG_NAME, state: 'away', is_self: true, is_staff: true },
      // A changed state changes the row signature, so the poll redraws both.
      { username: 'elrond', display_name: LONG_NAME, state: 'away', is_self: false, is_staff: true },
    ];
    Object.assign(data, { here: 1, total: 2, capped: false });
    await route.fulfill({ response, json: data });
  });
  await visit(page, '/c/general');
  await expect(page.locator('.board-rail [data-presence-sig="admin:away:s:y"]')).toHaveCount(1);
  const rebuilt = await presenceRows(page);
  expect(rebuilt).toHaveLength(2);
  expect(rebuilt.map(row => row.tags.map(tag => tag.text))).toEqual([['You', 'Staff'], ['Staff']]);
  for (const row of rebuilt) {
    expect(row.text, 'a rebuilt row keeps the name span').toBe(LONG_NAME);
    expect(row.truncated, 'a long rebuilt name truncates').toBe(true);
    for (const tag of row.tags) expect(tag, `${tag.text}`).toEqual({ text: tag.text, inside: true, clipped: false });
  }
  await page.locator('.board-rail [data-presence]').screenshot({ path: output(info, 'presence-long-names.png'), animations: 'disabled' });
});

/** At 320 x 256 nothing sticks to the topic and focus is never hidden. */
async function expectReadableShortTopic(page: Page): Promise<void> {
  const layout = await page.evaluate(() => {
    const root = getComputedStyle(document.documentElement);
    return {
      dock: getComputedStyle(document.querySelector('.thread-dock')!).position,
      bar: getComputedStyle(document.querySelector('.forum-bar')!).position,
      padding: [root.scrollPaddingTop, root.scrollPaddingBottom],
    };
  });
  expect(layout).toEqual({ dock: 'static', bar: 'relative', padding: ['12px', '12px'] });
  const authors = page.locator('a.post-author');
  expect(await authors.count()).toBeGreaterThan(0);
  for (let index = 0; index < await authors.count(); index++) {
    const author = authors.nth(index);
    await author.focus();
    const covered = await author.evaluate(element => {
      element.scrollIntoView({ block: 'nearest' });
      const box = element.getBoundingClientRect();
      const top = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
      return !(top && (top === element || element.contains(top)));
    });
    expect(covered, `post author ${index} hidden by author-created content`).toBe(false);
  }
}

test('a short viewport keeps the reply dock in the topic flow', async ({ page, browser }, info) => {
  test.skip(info.project.name === 'mobile', 'One emulated 400% zoom viewport is enough.');
  // 1280 x 1024 CSS pixels at 400% browser zoom lays out as 320 x 256.
  const viewport = { width: 320, height: 256 };
  await page.setViewportSize(viewport);
  await visit(page, '/t/1-welcome-to-retroboards');
  await expectReadableShortTopic(page);
  await page.screenshot({ path: output(info, 'topic-320x256.png'), animations: 'disabled' });

  // Without scripting the dock keeps its native form and the fallback height.
  const noScript = await browser.newContext({ javaScriptEnabled: false, viewport, reducedMotion: 'reduce' });
  const plain = await noScript.newPage();
  await visit(plain, '/t/1-welcome-to-retroboards');
  await expectReadableShortTopic(plain);
  await noScript.close();

  // A member's dock holds the reply composer, the tallest form of it.
  await page.setViewportSize({ width: 1280, height: 800 });
  await login(page, 'alice@retro.test');
  await page.setViewportSize(viewport);
  await visit(page, '/t/1-welcome-to-retroboards');
  await expect(page.locator('.thread-dock textarea, .thread-dock [contenteditable="true"]').first()).toBeAttached();
  await expectReadableShortTopic(page);
  await page.screenshot({ path: output(info, 'topic-320x256-member.png'), animations: 'disabled' });
});
