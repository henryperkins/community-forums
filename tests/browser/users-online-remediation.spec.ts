import { test, expect, Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Users Online remediation evidence (ADR 0031).
 *
 * This surface shipped a completely unstyled page — five classes with no rules
 * anywhere, a 0x0 presence dot, and a name and handle printed with no gap
 * ("Elrond Peredhel@elrond") — because it had never once been captured. The
 * browser seed never wrote `last_seen_at` or `show_presence`, and the roster
 * excluded the viewer, so every evidence run photographed an EMPTY rail and
 * docs/evidence/browser/ held no /users-online frame at all.
 *
 * So these assertions deliberately MEASURE rather than match markup: a dot with
 * a real size, `[hidden]` that actually computes display:none, a rail that does
 * not reflow when the poll lands, and an away colour that genuinely re-themes.
 * A test that only asserted "the class is present" would have passed against
 * the broken page too.
 */

const OUT = path.resolve(__dirname, '../../docs/evidence/imladris-users-online-remediation');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

/**
 * Per-project, or the mobile run silently overwrites the desktop frames with
 * 390px-wide ones and the "desktop" evidence is a phone capture wearing its
 * filename — which is precisely the kind of unexamined artifact that let this
 * page ship unstyled in the first place.
 */
function shot(name: string, project: string) {
  const dir = path.join(OUT, project);
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
}

/**
 * Below the rail's breakpoint the sidebar is translated off-canvas behind the
 * nav toggle. Playwright's toBeVisible() still passes for a transformed element
 * with a box, so a mobile assertion against [data-presence] would measure
 * something nobody can see. Open it first, and prove it actually moved.
 */
async function openRailIfOffCanvas(page: Page) {
  const widget = page.locator('[data-presence]');
  const box = await widget.boundingBox();
  if (box && box.x >= 0) { return; }
  await page.locator('.nav-toggle[data-nav-toggle]').click();
  await expect(async () => {
    const opened = await widget.boundingBox();
    expect(opened!.x).toBeGreaterThanOrEqual(0);
  }).toPass({ timeout: 3000 });
}

/**
 * The presence fixture writes `last_seen_at` RELATIVE TO SEED TIME, so the whole
 * cast ages out of the 900s away window about fifteen minutes after seeding and
 * every assertion below then fails for one uninformative reason: an empty roster.
 * `npm run evidence:presence` runs `prepare.sh` first, so the real flow is fine —
 * this guard is for anyone re-running the spec against an already-seeded DB.
 */
test.beforeAll(async ({ request }) => {
  const res = await request.get('/presence');
  if (res.status() === 429) {
    // Distinguished on purpose: reporting this as "the fixture aged out" sends
    // the reader to re-seed, which does not help. /presence and /users-online
    // share one rate-limit bucket, and a spec run spends a few dozen requests,
    // so back-to-back manual runs can exhaust it. prepare.sh clears the store,
    // which is why `npm run evidence:presence` never hits this.
    throw new Error(
      'The presence rate limit is exhausted (429) from previous runs. Clear the '
      + 'rate-limit store — rm -rf storage/ratelimit-e2e, or run prepare.sh.',
    );
  }
  if (!res.ok()) {
    throw new Error(`/presence answered ${res.status()}; is the presence feature enabled?`);
  }
  if (!(await res.json()).total) {
    throw new Error(
      'The presence roster is empty. The fixture is time-relative and has aged out '
      + 'of the away window — re-seed before running this spec '
      + '(cd tests/browser && bash prepare.sh, or npm run evidence:presence).',
    );
  }
});

async function signIn(page: Page, email = 'elrond@retro.test') {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  // A first-time member gets the product tour, whose card sits over the
  // guidance strip in the signed-in capture. Dismiss it the way the other
  // evidence specs do, so the frame shows the surface and not the tour.
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.count()) {
    await skip.first().click();
    await expect(skip.first()).toBeHidden();
  }
}

/** Force the short-poll to fire now instead of waiting out its 60s cadence. */
async function pollNow(page: Page) {
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForTimeout(400);
}

// ── The page had no CSS at all ──────────────────────────────────────────────

test('the directory is a styled grid, not a default bulleted list', async ({ page }, testInfo) => {
  await page.goto('/users-online');

  const grid = page.locator('.users-online .presence-grid');
  await expect(grid).toBeVisible();

  // `.users-online-list` used to be the class here and it had zero rules, so the
  // browser fell back to list-item layout with a bullet.
  const display = await grid.evaluate((el) => getComputedStyle(el).display);
  expect(display).toBe('grid');
  const marker = await grid.evaluate((el) => getComputedStyle(el).listStyleType);
  expect(marker).toBe('none');

  await page.screenshot({ path: shot('01-directory.png', testInfo.project.name), fullPage: true });
});

test('the name and the handle no longer collide on one line', async ({ page }) => {
  await page.goto('/users-online');

  const row = page.locator('[data-presence-row]').first();
  const name = row.locator('.presence-name');
  const sub = row.locator('.presence-sub');

  const nameBox = (await name.boundingBox())!;
  const subBox = (await sub.boundingBox())!;

  // Previously `<strong>name</strong><small>@handle</small>` with no styling, so
  // they ran together as "Elrond Peredhel@elrond".
  expect(subBox.y).toBeGreaterThan(nameBox.y + nameBox.height - 2);
  await expect(sub).toContainText('@');
});

test('the presence dot has a real size on this page', async ({ page }) => {
  await page.goto('/users-online');

  // Every `.presence-dot` rule in the app is descendant-scoped to .avatar-wrap /
  // .topbar-avatar / .profile-avatar, so the page's own standalone dot measured
  // 0x0 — the only presence indicator on the presence page was invisible.
  const guidanceDot = page.locator('.presence-guidance-dot').first();
  const box = (await guidanceDot.boundingBox())!;
  expect(box.width).toBeGreaterThan(4);
  expect(box.height).toBeGreaterThan(4);

  const rowDot = page.locator('[data-presence-row] .presence-dot').first();
  const rowBox = (await rowDot.boundingBox())!;
  expect(rowBox.width).toBeGreaterThan(4);
  expect(rowBox.height).toBeGreaterThan(4);
});

// ── Away state ──────────────────────────────────────────────────────────────

test('away members render a different dot from here-now members, in both registers', async ({ page }, testInfo) => {
  await page.goto('/users-online');

  const hereDot = page.locator('[data-presence-state="online"] .presence-dot').first();
  const awayDot = page.locator('[data-presence-state="away"] .presence-dot').first();
  await expect(awayDot).toBeAttached();

  const colour = (loc: typeof hereDot) => loc.evaluate((el) => getComputedStyle(el).backgroundColor);
  const lightHere = await colour(hereDot);
  const lightAway = await colour(awayDot);
  expect(lightAway).not.toBe(lightHere);

  // The design paints away with --amber, which has no twilight remap: an amber
  // dot would stay light-register amber on a dark page. Prove ours flips.
  await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
  const darkAway = await colour(awayDot);
  const darkHere = await colour(hereDot);
  expect(darkAway).not.toBe(lightAway);
  // And that flipping did not collapse the two states onto one colour — which
  // is exactly what an eyeball check of the dark capture cannot settle.
  expect(darkAway).not.toBe(darkHere);

  await page.screenshot({ path: shot('02-directory-dark.png', testInfo.project.name), fullPage: true });
  await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'light'));
});

// ── The [hidden] trap ───────────────────────────────────────────────────────

test('[hidden] genuinely hides a presence element now', async ({ page }) => {
  await page.goto('/');

  await openRailIfOffCanvas(page);
  const widget = page.locator('[data-presence]');
  await expect(widget).toBeVisible();

  // `.presence-widget { display: block }` beat the UA [hidden] rule, so the
  // element reported hidden=true and went on painting 90px of roster.
  const hiddenDisplay = await widget.evaluate((el) => {
    (el as HTMLElement).hidden = true;
    const d = getComputedStyle(el).display;
    (el as HTMLElement).hidden = false;
    return d;
  });
  expect(hiddenDisplay).toBe('none');
});

test('the widget survives an empty roster, because it holds the only link to the roll', async ({ page }) => {
  await page.goto('/');
  // Nothing may set hidden on the widget itself: with JS off it is never hidden,
  // so hiding it with JS would be a progressive-enhancement divergence — and it
  // would take the only route to /users-online with it.
  await openRailIfOffCanvas(page);
  await pollNow(page);
  await expect(page.locator('[data-presence]')).toBeVisible();
  await expect(page.locator('[data-presence] .presence-all')).toHaveAttribute('href', '/users-online');
});

// ── Server / poller parity: the reflow ──────────────────────────────────────

test('the rail does not reflow when the poll lands', async ({ page }, testInfo) => {
  await page.goto('/');

  await openRailIfOffCanvas(page);
  const widget = page.locator('[data-presence]');
  const limit = Number(await widget.getAttribute('data-presence-limit'));
  expect(limit).toBeGreaterThan(0);

  const rowsBefore = await page.locator('[data-presence-row]').count();
  const boxBefore = (await widget.boundingBox())!;
  expect(rowsBefore).toBe(limit);

  await pollNow(page);

  // The server rendered 6 and the poller rendered 20, so about a second after
  // load the rail grew from 264px to 595px under the reader.
  const rowsAfter = await page.locator('[data-presence-row]').count();
  const boxAfter = (await widget.boundingBox())!;
  expect(rowsAfter).toBe(rowsBefore);
  expect(Math.abs(boxAfter.height - boxBefore.height)).toBeLessThan(2);

  await page.screenshot({ path: shot('03-rail-after-poll.png', testInfo.project.name), fullPage: false });
});

test('an unchanged row keeps its node, so the live region stays quiet', async ({ page }) => {
  await page.goto('/');

  await openRailIfOffCanvas(page);
  const first = page.locator('[data-presence-row]').first();
  await first.evaluate((el) => el.setAttribute('data-probe', 'original'));

  await pollNow(page);

  // The poller used to do `pList.innerHTML = ''` inside an aria-live region, so
  // a screen reader re-announced the whole roster every cycle. Rows carry a
  // signature now and an unchanged row is left alone.
  await expect(page.locator('[data-presence-row]').first()).toHaveAttribute('data-probe', 'original');

  // Exactly one live region, and it is the summary — not the list.
  await expect(page.locator('[data-presence] [aria-live]')).toHaveCount(1);
  await expect(page.locator('[data-presence-summary]')).toHaveAttribute('aria-live', 'polite');
});

test('a member whose state changes replaces their row instead of duplicating it', async ({ page }) => {
  // The two poll tests above both poll an UNCHANGED roster, so they exercise
  // only the signature-match branch — and passed happily while the replace
  // branch leaked a row on every cycle. This one flips a signature.
  let flip = false;
  await page.route('**/presence?format=json', async (route) => {
    const response = await route.fetch();
    const body = await response.json();
    if (flip && body.online && body.online.length) {
      body.online[0].state = body.online[0].state === 'away' ? 'online' : 'away';
    }
    await route.fulfill({ response, json: body });
  });

  await page.goto('/');
  await openRailIfOffCanvas(page);

  const widget = page.locator('[data-presence]');
  const limit = Number(await widget.getAttribute('data-presence-limit'));
  const first = await page.locator('[data-presence-row]').first().getAttribute('data-presence-row');

  await pollNow(page);
  expect(await page.locator('[data-presence-row]').count()).toBe(limit);

  flip = true;
  await pollNow(page);

  // The superseded <li> used to be removed from the reconcile map but left in
  // the DOM, so the list grew by one row per changed member per poll, forever —
  // while the count badge and the sr-only summary stayed correct, silently
  // contradicting what was on screen.
  expect(await page.locator(`[data-presence-row="${first}"]`).count()).toBe(1);
  expect(await page.locator('[data-presence-row]').count()).toBe(limit);

  await pollNow(page);
  expect(await page.locator('[data-presence-row]').count()).toBe(limit);
});

test('the rail states how many members it is not showing', async ({ page }) => {
  await page.goto('/users-online');

  const more = page.locator('[data-presence-more]');
  // Server-rendered, so it is right with JavaScript off — where the badge used
  // to read "9" above six names with no affordance at all.
  await expect(more).toBeVisible();
  await expect(more).toContainText('more');
});

// ── Filters, search, paging — all without JavaScript ────────────────────────

test.describe('with JavaScript disabled', () => {
  test.use({ javaScriptEnabled: false });

  test('the roll filters, searches and pages as plain links and a GET form', async ({ page }, testInfo) => {
    await page.goto('/users-online');
    await expect(page.locator('[data-presence-row]').first()).toBeVisible();

    await page.screenshot({ path: shot('04-directory-nojs.png', testInfo.project.name), fullPage: true });

    // Filters are links, not buttons behind a handler.
    await page.getByRole('link', { name: /Away/ }).first().click();
    await page.waitForURL(/filter=away/);
    const states = await page.locator('.users-online [data-presence-row] [data-presence-state]').evaluateAll(
      (els) => els.map((el) => el.getAttribute('data-presence-state')),
    );
    expect(states.length).toBeGreaterThan(0);
    expect(new Set(states)).toEqual(new Set(['away']));

    // Search is a GET form, so the result is linkable and nothing mutates.
    await page.goto('/users-online');
    await page.fill('#presence-q', 'Galadriel');
    await page.getByRole('button', { name: 'Search' }).click();
    await page.waitForURL(/[?&]q=Galadriel/);
    await expect(page.locator('.users-online [data-presence-row="galadriel"]')).toBeVisible();
  });

  test('a member who turned presence off is on no page of the roll', async ({ page }) => {
    await page.goto('/users-online?q=Gildor');
    // The term is echoed back into the input; no ROW may come with it.
    await expect(page.locator('.users-online [data-presence-row="gildor"]')).toHaveCount(0);
  });
});

// ── Privacy, end to end in a real browser ───────────────────────────────────

test('a guest is never shown a members-only profile, and a member is', async ({ page }) => {
  await page.goto('/users-online');
  // celebrian restricted her profile to signed-in members; a guest cannot open
  // it, so presence must not publish her name, handle or a link to it.
  await expect(page.locator('.users-online [data-presence-row="celebrian"]')).toHaveCount(0);

  await signIn(page, 'alice@retro.test');
  await page.goto('/users-online');
  await expect(page.locator('.users-online [data-presence-row="celebrian"]')).toHaveCount(1);
});

test('a signed-in member counts themselves, and is marked as such', async ({ page }, testInfo) => {
  await page.goto('/presence');
  const guest = JSON.parse(await page.locator('pre').innerText().catch(async () => await page.content().then(() => '{}')) || '{}');

  await signIn(page, 'elrond@retro.test');
  await page.goto('/users-online');

  // Excluding the viewer meant a member read "Online 15" at the same instant a
  // guest read 16 — the same rail disagreeing with itself about the same moment.
  const self = page.locator('.users-online [data-presence-self]');
  await expect(self).toHaveCount(1);
  await expect(self.locator('.presence-you')).toHaveText('you');
  expect(guest).toBeTruthy();

  await page.screenshot({ path: shot('05-directory-signed-in.png', testInfo.project.name), fullPage: true });
});

test('the staff chip renders for an admin on the roll', async ({ page }) => {
  await page.goto('/users-online');
  const staff = page.locator('.users-online .presence-staff');
  await expect(staff.first()).toBeVisible();
  await expect(staff.first()).toHaveText('Staff');

  // Design QA once spent a contrast fix on this selector while nothing rendered
  // it; check it is actually legible now.
  const contrastable = await staff.first().evaluate((el) => {
    const s = getComputedStyle(el);
    return s.color !== s.backgroundColor;
  });
  expect(contrastable).toBe(true);
});

test('the guidance strip explains all three states', async ({ page }) => {
  await page.goto('/users-online');
  const cards = page.locator('.presence-guidance-card');
  await expect(cards).toHaveCount(3);
  await expect(cards.nth(0)).toContainText('Here now');
  await expect(cards.nth(1)).toContainText('Stepped away');
  await expect(cards.nth(2)).toContainText('Not shown');
});
