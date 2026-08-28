import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Thread view remediation evidence (ADR 0030).
 *
 * Every capture runs against tests/browser/thread-view-fixture.php, which
 * reproduces `thread-data.js` — the design's own topic — row for row: workflow
 * status and its history, an assignment, tags, a poll, a living brief with three
 * versions, an accepted answer, a grouped reply, an anonymous post, reactions, a
 * signature, a referenced topic and a link preview. A topic with three plain
 * replies and nothing attached cannot disagree with its design, and most of what
 * follows was hiding in exactly the states such a topic never reaches.
 *
 * Each screenshot is written by an assertion that MEASURES, not one that looks.
 *
 *   DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
 *   npx playwright test thread-view-remediation.spec.ts --project=desktop
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const OUT = path.resolve(repoRoot, 'docs/evidence/imladris-thread-view-remediation');

/** The design declares this once, as a custom property (ThreadView.dc.html:56). */
const MEASURE = 646;

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

let topicPath = '';

function seed(): string {
  if (process.env.RB_SKIP_FIXTURE === '1') {
    return topicPath || '/t/30-ratified-decisions';
  }
  return execFileSync('php', ['tests/browser/thread-view-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
}

test.beforeAll(() => {
  topicPath = seed();
});

// Opening the topic advances the read position, so the unread strip and the
// unread boundary are gone by the second visit. Re-seeding restores them.
function reseed(): void {
  topicPath = seed();
}

function shot(name: string) {
  fs.mkdirSync(OUT, { recursive: true });
  return path.join(OUT, name);
}

async function signIn(page: Page, email: string) {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
}

async function setAppearance(page: Page, field: 'theme' | 'density', value: string) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="${field}"][value="${value}"]`).check({ force: true });
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

/** Document order of the surface's regions, by the class each region announces. */
async function regionOrder(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    [...document.querySelectorAll('.catch-up, .post[data-post], .poll-card, .thread-memory-slot, .thread-related')]
      .map((el) => {
        if (el.classList.contains('post')) {
          return el.classList.contains('post-op') ? 'post:op' : 'post';
        }
        return [...el.classList].find((c) => ['catch-up', 'poll-card', 'thread-memory-slot', 'thread-related'].includes(c))!;
      }));
}

test.describe('desktop', () => {
  test.skip(({ isMobile }) => !!isMobile, 'desktop geometry');

  /**
   * The surface used to be 860px wide with the prose capped at 70ch inside it,
   * so the topic's own byline ran 743px while the sentence under it ran 539px,
   * and the poll, the brief and the composer all sat ~200px past the measure
   * they exist to stand beside.
   */
  test('one column, and the column is the measure', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const widths = await page.evaluate(() => {
      const w = (sel: string) => {
        const el = document.querySelector(sel);
        return el ? Math.round(el.getBoundingClientRect().width) : -1;
      };
      return {
        facts: w('.thread-facts'),
        poll: w('.poll-card'),
        brief: w('.living-brief'),
        dock: w('.thread-dock'),
      };
    });
    // Every region shares one width, and that width is the design's measure.
    for (const [name, value] of Object.entries(widths)) {
      expect(value, `${name} is the reading measure`).toBeGreaterThan(MEASURE - 4);
      expect(value, `${name} is the reading measure`).toBeLessThan(MEASURE + 4);
    }
  });

  /**
   * Both belong to the opening post: the opening post asked the question the
   * poll puts to a vote, and the brief summarises that same question. They used
   * to render above the stream, so every reader met a ballot and an AI-written
   * summary before a single word of the topic.
   */
  test('the poll and the brief follow the opening post, and Related follows the stream', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    reseed();
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const order = await regionOrder(page);
    expect(order[0]).toBe('catch-up');
    expect(order[1]).toBe('post:op');
    expect(order[2]).toBe('poll-card');
    expect(order[3]).toBe('thread-memory-slot');
    expect(order[4]).toBe('post');
    expect(order[order.length - 1]).toBe('thread-related');
  });

  /**
   * The identity side of the facts row used to carry the tag chips, a visible
   * "In council" label and a Tended-by/Quiet-until group as well. With five
   * competing items on a deliberately nowrap row, the one shrinkable item gave
   * up all of its width and the topic's byline rendered as "Opened by Erestor ·
   * 5 repl".
   */
  test('the byline states opener, date and reply count, on one line, unelided', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const byline = page.locator('.thread-byline');
    await expect(byline).toContainText('Opened by Erestor');
    await expect(byline).toContainText('5 replies');

    const seen = await byline.evaluate((el) => ({
      scrollWidth: el.scrollWidth,
      clientWidth: el.clientWidth,
      lines: Math.round(el.getBoundingClientRect().height / parseFloat(getComputedStyle(el).lineHeight)),
    }));
    expect(seen.scrollWidth, 'the byline is not ellipsised').toBeLessThanOrEqual(seen.clientWidth);
    expect(seen.lines, 'the byline is one line').toBe(1);

    // The facts row itself is one line, and the tags are not on it.
    const rowLines = await page.locator('.thread-facts').evaluate((el) => el.getBoundingClientRect().height);
    expect(rowLines).toBeLessThan(40);
    expect(await page.locator('.thread-facts .tag').count()).toBe(0);
    expect(await page.locator('.thread-study-tags .tag').count()).toBe(2);
    // The roster keeps its name without spending a line on it.
    await expect(page.locator('.thread-participants')).toHaveAttribute('aria-label', 'In council');

    await page.locator('.thread-study-head').screenshot({ path: shot('01-topic-head.png') });
  });

  /**
   * A general `margin-left: auto` inherited from the pre-Imladris post-bit sent
   * the stamp to the trailing edge of the head — 456px from the name it dates.
   */
  test('the post stamp stays inside the byline it dates', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const gap = await page.locator('.post-op .post-head').evaluate((head) => {
      const marks = [...head.querySelectorAll('.badge, .post-title-chip')];
      const last = marks[marks.length - 1].getBoundingClientRect();
      const time = head.querySelector('.post-time')!.getBoundingClientRect();
      return Math.round(time.left - last.right);
    });
    expect(gap, 'the stamp follows the badges').toBeLessThan(24);

    // And no reader is told the word "Commends" once per post: it is the
    // plinth's accessible name, not its caption.
    const regard = page.locator('.post-op .regard-block');
    await expect(regard).toHaveAttribute('title', '3,940 commends');
    const visible = await regard.evaluate((el) => {
      const clone = el.cloneNode(true) as HTMLElement;
      clone.querySelectorAll('.sr-only').forEach((n) => n.remove());
      return clone.textContent!.replace(/\s+/g, ' ').trim();
    });
    expect(visible).toBe('3,940');
  });

  /**
   * The poll was a raised card with a gold rule down its edge holding sunken
   * options — raised-on-raised put it at the post's own elevation, and three
   * sunken rows inside it read as one field of tan.
   */
  test('the poll is a sunken panel with raised options and one eyebrow line', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const seen = await page.evaluate(() => {
      const card = document.querySelector('.poll-card')!;
      const opt = document.querySelector('.poll-option')!;
      const eyebrow = document.querySelector('.poll-eyebrow')!;
      return {
        card: getComputedStyle(card).backgroundColor,
        cardRadius: getComputedStyle(card).borderTopLeftRadius,
        option: getComputedStyle(opt).backgroundColor,
        eyebrowText: eyebrow.textContent!.replace(/\s+/g, ' ').trim(),
        eyebrowLines: Math.round(eyebrow.getBoundingClientRect().height / parseFloat(getComputedStyle(eyebrow).lineHeight)),
        statusPills: document.querySelectorAll('.poll-card .poll-status').length,
      };
    });
    expect(seen.card).toBe('rgb(236, 228, 210)');   // --surface-sunken
    expect(seen.option).toBe('rgb(250, 246, 236)'); // --surface-raised
    expect(seen.cardRadius).toBe('7px');            // --radius-md
    expect(seen.eyebrowText).toBe('Poll· choose one');
    expect(seen.eyebrowLines).toBe(1);
    expect(seen.statusPills, 'the eyebrow carries the state; no second pill').toBe(0);

    await page.locator('.poll-card').screenshot({ path: shot('02-poll.png') });
  });

  /**
   * The summary always shows; its provenance is one disclosure away. Version,
   * publication stamp and the source posts used to print unconditionally — a
   * metadata line above the summary and an <h3>Sources</h3> list below it — so
   * the three-sentence artifact a reader came for arrived wrapped in six lines
   * of bookkeeping, and the stamp was the only machine time on a reading page.
   */
  test('the brief is a gold-edged panel whose provenance is one disclosure away', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1400 });
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const brief = page.locator('.living-brief');
    const frame = await brief.evaluate((el) => {
      const s = getComputedStyle(el);
      return { border: s.borderTopColor, radius: s.borderTopLeftRadius, padding: s.padding };
    });
    expect(frame.border).toBe('rgb(234, 217, 168)'); // --gold-200
    expect(frame.radius).toBe('7px');
    expect(frame.padding).toBe('15px 17px');

    // Closed: no version, no stamp, no source list on screen. `toContainText`
    // reads textContent, which a closed <details> still has, so this asks the
    // layout engine instead.
    await expect(page.locator('.living-brief-meta')).toBeHidden();
    await expect(page.locator('.living-brief-sources')).toBeHidden();
    await brief.locator('.living-brief-provenance > summary').click();
    await expect(page.locator('.living-brief-meta')).toBeVisible();
    await expect(page.locator('.living-brief-eyebrow')).toHaveText('Drawn from');
    await expect(brief).toContainText('Version 3');
    // The stamp reads in the same register as every post byline on the page.
    await expect(brief.locator('.living-brief-meta time')).not.toContainText('UTC');

    // The source pointer takes --artifact-link and clears AA against its panel.
    const link = page.locator('.living-brief-sources a').first();
    const ink = await link.evaluate((el) => getComputedStyle(el).color);
    const ground = await brief.evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(contrast(ink, ground)).toBeGreaterThanOrEqual(4.5);

    await brief.screenshot({ path: shot('03-living-brief-open.png') });
  });

  /**
   * "Catch me up" costs one line until it is asked to open. It replaced a full
   * panel — a heading, a count paragraph and a bulleted excerpt list — printed
   * above the topic on every visit that had anything unread at all.
   */
  test('catch me up is one line closed and opens without JavaScript', async ({ page, browser }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    reseed();
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const strip = page.locator('.catch-up');
    await expect(strip).toBeVisible();
    await expect(strip.locator('.catch-up-line')).toHaveText('1 reply — Arwen');
    const closed = await strip.evaluate((el) => Math.round(el.getBoundingClientRect().height));
    expect(closed, 'closed, the strip is one row').toBeLessThan(48);
    await expect(strip.locator('.catch-up-points')).toBeHidden();
    await page.locator('.catch-up-summary').screenshot({ path: shot('04-catch-up-closed.png') });

    await strip.locator('summary').click();
    await expect(strip.locator('.catch-up-points li').first()).toBeVisible();
    await strip.screenshot({ path: shot('05-catch-up-open.png') });

    // The same, with JavaScript off: <details> is the whole mechanism.
    const noJs = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 1200 } });
    const bare = await noJs.newPage();
    reseed();
    await signIn(bare, 'elladan@retro.test');
    await bare.goto(topicPath);
    await bare.locator('.catch-up summary').click();
    await expect(bare.locator('.catch-up-points li').first()).toBeVisible();
    await noJs.close();
  });

  /**
   * "First unread" named the rule without answering the question a reader
   * crossing it is holding.
   */
  test('the unread boundary states how much is past it', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    reseed();
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const rule = page.locator('.first-unread-divider');
    await expect(rule).toHaveText(/New since you last read · 1 reply/);
    // Asymmetric: a stub on the left, the long rule on the right.
    const [before, after] = await rule.evaluate((el) => {
      const b = getComputedStyle(el, '::before').flexBasis;
      const a = getComputedStyle(el, '::after').flexGrow;
      return [b, a];
    });
    expect(before).toBe('14px');
    expect(Number(after)).toBeGreaterThan(0);
    await rule.screenshot({ path: shot('06-unread-boundary.png') });
  });

  /**
   * The design system ships a `.link-preview` component and this surface had no
   * consumer for it: an unfurl rendered as a `.reference-card` with the host
   * name inside `.badge.badge-muted` — the product's uppercase status pill —
   * which drew a full-width shouting bar over every preview.
   */
  test('an unfurl is a host line, a title and a description', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1400 });
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const card = page.locator('.post-op .link-preview').first();
    await expect(card).toBeVisible();
    expect(await page.locator('.link-preview .badge').count()).toBe(0);
    const host = card.locator('.link-preview-host');
    await expect(host).toContainText('imladris.council');
    const hostCase = await host.evaluate((el) => getComputedStyle(el).textTransform);
    expect(hostCase).toBe('none');

    // The reference card is a quotation from the record, and is drawn as one.
    const ref = page.locator('.post-stream .reference-card').first();
    await expect(ref.locator('.ref-type')).toHaveText('#interpretability · referenced');
    const rule = await ref.evaluate((el) => getComputedStyle(el).borderLeftColor);
    expect(rule).toBe('rgb(194, 154, 68)'); // --gold-500

    await page.locator('.post-op').screenshot({ path: shot('07-opening-post.png') });
  });

  /**
   * A select plus a Save charged two interactions and a page load for the one
   * setting whose value is that it is quick to change.
   */
  test('the drawer commits a watch in one press, and states pin and lock as states', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1200 });
    await signIn(page, 'elrond@retro.test');
    await page.goto(topicPath);
    await page.click('[data-topic-tools-open]');

    const watch = page.locator('.watch-segmented');
    await expect(watch).toBeVisible();
    await expect(watch.getByRole('button', { name: 'Instant' })).toHaveAttribute('aria-pressed', 'true');
    await page.locator('.topic-tools').screenshot({ path: shot('08-topic-tools-watch.png') });

    await watch.getByRole('button', { name: 'Daily' }).click();
    await page.waitForURL(/\/t\//);
    await page.click('[data-topic-tools-open]');
    await expect(page.locator('.watch-segmented').getByRole('button', { name: 'Daily' }))
      .toHaveAttribute('aria-pressed', 'true');

    await page.locator('[data-topic-tools-section="management"] > summary').click();
    const pin = page.getByRole('switch', { name: 'Pinned above the board' });
    await expect(pin).toHaveAttribute('aria-checked', 'false');
    await expect(page.getByRole('switch', { name: 'Locked to replies' })).toHaveAttribute('aria-checked', 'false');
    await expect(page.locator('.topic-tools-foot')).toHaveText('Esc closes. Warden acts are recorded in the ledger.');
    await page.locator('.topic-tools').screenshot({ path: shot('09-topic-tools-management.png') });
  });

  /** The twilight register flips from the same tokens, so it is measured too. */
  test('the twilight register keeps the brief and its sources legible', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1400 });
    reseed();
    await signIn(page, 'elladan@retro.test');
    await setAppearance(page, 'theme', 'dark');
    await page.goto(topicPath);

    const brief = page.locator('.living-brief');
    await brief.locator('.living-brief-provenance > summary').click();
    const [ink, ground] = await page.evaluate(() => {
      const b = document.querySelector('.living-brief')!;
      const a = document.querySelector('.living-brief-sources a')!;
      return [getComputedStyle(a).color, getComputedStyle(b).backgroundColor];
    });
    try {
      expect(contrast(ink, ground)).toBeGreaterThanOrEqual(4.5);
      await page.locator('.thread-study').screenshot({ path: shot('10-twilight.png') });
    } finally {
      // The register is a stored preference, so a failure here would leave every
      // later run of this file reading a dark page it did not ask for.
      await setAppearance(page, 'theme', 'system');
    }
  });

  test('the whole surface, as a reader meets it', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 2400 });
    reseed();
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);
    await expect(page.locator('.post-op')).toBeVisible();
    await page.screenshot({ path: shot('11-thread-view-desktop.png') });
  });
});

test.describe('mobile', () => {
  test.skip(({ isMobile }) => !isMobile, 'phone layout');

  /**
   * Below 768px two lines is the right answer: the identity group takes the
   * first, the controls the second, and nothing is ellipsised away to reach one.
   */
  test('the facts row wraps rather than eliding, and the strip survives', async ({ page }) => {
    reseed();
    await signIn(page, 'elladan@retro.test');
    await page.goto(topicPath);

    const byline = page.locator('.thread-byline');
    const seen = await byline.evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth }));
    expect(seen.scrollWidth).toBeLessThanOrEqual(seen.clientWidth);
    await expect(byline).toContainText('5 replies');
    await expect(page.locator('.catch-up')).toBeVisible();
    await expect(page.locator('.thread-study-tags .tag')).toHaveCount(2);
    await page.screenshot({ path: shot('12-thread-view-mobile.png'), fullPage: false });
  });
});
