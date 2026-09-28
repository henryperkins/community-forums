import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * An uploaded avatar replaces the member's monogram wherever the app draws that
 * member (USER §5.2), not only on the profile header. Alice uploads a picture
 * through the real settings form; Bob then meets it on the topic, the board,
 * the inbox, the presence rail (server-rendered and rebuilt by the poll) and a
 * direct message. Each picture must take exactly the box the monogram had, and
 * disappear wherever "Show avatars" already hid monograms.
 *
 * The run leaves the seed as it found it: Alice removes the picture at the end,
 * Bob's preference is restored, and the star is toggled only when missing.
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceRoot = path.resolve(repoRoot, process.env.RB_AVATAR_EVIDENCE_DIR ?? 'docs/evidence/avatar-display-2026-09-28');
const TOPIC = 'Share your favourite keyboard shortcuts';

// A gold bust on a river-teal ground: unmistakably a picture, not a monogram.
const portrait = execFileSync('php', ['-r', `
  $im = imagecreatetruecolor(240, 240);
  imagefilledrectangle($im, 0, 0, 239, 239, imagecolorallocate($im, 38, 84, 96));
  $gold = imagecolorallocate($im, 214, 170, 76);
  imagefilledellipse($im, 120, 98, 104, 104, $gold);
  imagefilledellipse($im, 120, 250, 196, 170, $gold);
  imagepng($im);
`]);

async function login(page: Page, who: string) {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('[name=email]').fill(`${who}@retro.test`);
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL((url) => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function post(page: Page, url: string, fields: Record<string, string> = {}) {
  const token = await page.locator('input[name=_token]').first().inputValue();
  return page.request.post(url, { form: { ...fields, _token: token }, maxRedirects: 0 });
}

async function capture(page: Page, info: TestInfo, name: string, focus?: Locator) {
  const dir = path.join(evidenceRoot, info.project.name);
  fs.mkdirSync(dir, { recursive: true });
  await page.evaluate(() => document.fonts.ready);
  if (focus) await focus.scrollIntoViewIfNeeded();
  // Viewport shots: a full-page capture drops touch emulation on the phone
  // project, and every surface here sits in the first screen once scrolled to.
  await page.screenshot({ path: path.join(dir, `${name}.png`), animations: 'disabled' });
}

async function loaded(img: Locator) {
  await expect.poll(() => img.evaluate((el) => (el as HTMLImageElement).complete && (el as HTMLImageElement).naturalWidth > 0)).toBe(true);
}

/**
 * The picture takes exactly the box a monogram gets in the same slot, or is
 * hidden exactly where that monogram would be. The reference is a probe span
 * carrying the same classes minus `avatar-img`, dropped in beside the picture,
 * so every `.monogram` sizing and visibility rule of this context applies to
 * it — the thread rail, the 26px compact row, the phone inbox that hides them.
 */
async function expectAvatar(img: Locator) {
  await expect(img).toHaveCount(1);
  const slot = await img.evaluate((el) => {
    const probe = document.createElement('span');
    el.classList.forEach((name) => { if (name !== 'avatar-img') probe.classList.add(name); });
    probe.textContent = 'AB';
    el.before(probe);
    const shown = probe.checkVisibility();
    const rect = probe.getBoundingClientRect();
    probe.remove();
    return { shown, width: rect.width, height: rect.height };
  });
  if (!slot.shown) {
    await expect(img).toBeHidden();
    return;
  }
  await img.scrollIntoViewIfNeeded();
  await expect(img).toBeVisible();
  await loaded(img);
  const style = await img.evaluate((el) => {
    const s = getComputedStyle(el);
    return { radius: s.borderRadius, fit: s.objectFit };
  });
  expect(style).toEqual({ radius: '50%', fit: 'cover' });
  const box = (await img.boundingBox())!;
  expect(Math.abs(box.width - slot.width), 'avatar width matches the monogram slot').toBeLessThan(0.5);
  expect(Math.abs(box.height - slot.height), 'avatar height matches the monogram slot').toBeLessThan(0.5);
  expect(slot.width).toBeGreaterThanOrEqual(20);
}

async function openRailIfOffCanvas(page: Page) {
  const box = await page.locator('.board-rail').boundingBox();
  if (!box || box.x < 0) await page.locator('.nav-toggle[data-nav-toggle]').click();
}

/** Force the presence short-poll now instead of waiting out its cadence. */
async function pollNow(page: Page) {
  await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForTimeout(400);
}

/** Mark Alice's rail row stale so the next poll rebuilds it in app.js. */
async function staleAliceRow(page: Page) {
  await page.locator('[data-presence-row="alice"]').evaluate((row) => {
    row.setAttribute('data-presence-sig', 'stale');
    row.setAttribute('data-probe', 'server');
  });
}

async function setShowAvatars(page: Page, on: boolean) {
  await page.goto('/settings/preferences');
  const toggle = page.locator('input[name="show_avatars"]');
  if ((await toggle.isChecked()) !== on) {
    await toggle.setChecked(on);
    await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && new URL(r.url()).pathname === '/settings/preferences'),
      page.locator('form:has(input[name="show_avatars"]) button[type="submit"]').first().click(),
    ]);
    await page.waitForLoadState('load');
  }
  await page.goto('/settings/preferences');
  await expect(page.locator('input[name="show_avatars"]')).toBeChecked({ checked: on });
}

test('an uploaded avatar replaces the monogram wherever the member is drawn', async ({ page }, info) => {
  test.setTimeout(180_000);
  const errors: string[] = [];
  page.on('pageerror', (error) => errors.push(error.message));

  // Alice uploads through the settings form. The page re-renders in place to
  // keep her draft, and the shell's own seat must show the new picture at once.
  await login(page, 'alice');
  await page.goto('/settings/account');
  await page.locator('[name="avatar"]').setInputFiles({ name: 'portrait.png', mimeType: 'image/png', buffer: portrait });
  await page.locator('button').filter({ hasText: /^Upload avatar$/ }).click();
  await expect(page.getByText('Avatar updated.', { exact: false })).toBeVisible();
  const src = await page.locator('.profile-media-panel img.avatar-img').first().getAttribute('src');
  expect(src).toMatch(/^\/media\/\d+$/);
  const seat = page.locator(`.forum-bar-user img.avatar-img[src="${src}"]`);
  await expect(seat).toHaveCount(1);
  await loaded(seat);
  await capture(page, info, '01-settings-upload-and-seat', page.locator('.profile-media-panel'));

  // Her own composer identity wears it too.
  await page.goto('/c/general');
  await page.getByRole('link', { name: TOPIC, exact: true }).first().click();
  await page.waitForURL(/\/t\/\d+/);
  const topicPath = new URL(page.url()).pathname;
  const topicId = topicPath.match(/^\/t\/(\d+)/)![1];
  await expectAvatar(page.locator(`.composer-identity img.avatar-img[src="${src}"]`).first());

  // A letter to Bob, so his Messages show her.
  await page.goto('/messages/new');
  const letter = await post(page, '/messages', { to: 'bob', body: 'Bob, the new portrait is up. Does it read at rail size?' });
  expect(letter.status()).toBe(303);
  const conversationPath = new URL(letter.headers().location!, 'http://localhost').pathname;

  await login(page, 'bob');

  // The topic: her opening post and her place in the council stack.
  await page.goto(topicPath);
  await expectAvatar(page.locator(`article.post-op > .post-avatar > img.avatar-img[src="${src}"]`));
  await expect(page.locator('article.post-op > .post-avatar > span.monogram')).toHaveCount(0);
  // Repliers who have not uploaded keep their monograms.
  await expect(page.locator('article.post:not(.post-op) > .post-avatar > span.monogram').first()).toBeAttached();
  await expect(page.locator('article.post:not(.post-op) > .post-avatar > img')).toHaveCount(0);
  await expectAvatar(page.locator(`.thread-participants li.participant img.avatar-img[src="${src}"]`));
  await capture(page, info, '02-topic-post-and-council', page.locator('article.post-op'));

  // The board: her row beside Bob's own monogram row.
  await page.goto('/c/general');
  const aliceRow = page.locator('li.thread-row').filter({ hasText: TOPIC });
  await expectAvatar(aliceRow.locator(`img.avatar-img[src="${src}"]`));
  await expect(page.locator('li.thread-row').filter({ hasText: 'Mobile layout looks great' }).locator('span.monogram')).toHaveCount(1);
  await capture(page, info, '03-board-rows', aliceRow);

  // The inbox: star the topic once (the toggle is idempotent across projects).
  await page.goto('/inbox?scope=starred&order=active');
  if (!(await page.locator('li.thread-row').filter({ hasText: TOPIC }).count())) {
    expect((await post(page, `/t/${topicId}/star`)).status()).toBe(303);
    await page.goto('/inbox?scope=starred&order=active');
  }
  const inboxRow = page.locator('li.thread-row').filter({ hasText: TOPIC });
  await expectAvatar(inboxRow.locator(`img.avatar-img[src="${src}"]`));
  await capture(page, info, '04-inbox-row', inboxRow);

  // The presence rail, as the server drew it: her row beside Galadriel's monogram.
  await page.goto('/');
  await openRailIfOffCanvas(page);
  const railAlice = page.locator(`[data-presence-row="alice"] .avatar-wrap > img.avatar-img[src="${src}"]`);
  await expectAvatar(railAlice);
  await capture(page, info, '05-presence-rail', page.locator('[data-presence]'));

  // …and as the poll rebuilds a changed row in app.js.
  await staleAliceRow(page);
  await pollNow(page);
  const rebuilt = page.locator('[data-presence-row="alice"]');
  await expect(rebuilt).toHaveCount(1);
  await expect(rebuilt).not.toHaveAttribute('data-probe', 'server');
  await expectAvatar(rebuilt.locator(`.avatar-wrap > img.avatar-img[src="${src}"]`));

  // Messages: the list row, the letter head, the letter and the details rail.
  await page.goto('/messages');
  const listRow = page.locator(`a.dm-row[href="${conversationPath}"]`);
  await expectAvatar(listRow.locator(`img.avatar-img[src="${src}"]`));
  await page.goto(conversationPath);
  await expectAvatar(page.locator(`.dm-thread-id > img.avatar-img[src="${src}"]`));
  await expectAvatar(page.locator(`.dm-group:not(.mine) .dm-mono-col > img.avatar-img[src="${src}"]`).last());
  await expectAvatar(page.locator(`.dm-rail-id > img.avatar-img[src="${src}"]`));
  await capture(page, info, '06-direct-message', page.locator('.dm-thread-id'));

  // "Show avatars" off: hidden wherever monograms were, the rail's bare dot
  // included — even for a row the poll rebuilds.
  await setShowAvatars(page, false);
  await page.goto(topicPath);
  await expect(page.locator(`article.post img.avatar-img[src="${src}"]`)).toHaveCount(0);
  await page.goto('/');
  await openRailIfOffCanvas(page);
  await expect(page.locator('[data-presence]')).toHaveAttribute('data-presence-avatars', '0');
  await staleAliceRow(page);
  await pollNow(page);
  await expect(page.locator('[data-presence-row="alice"]')).not.toHaveAttribute('data-probe', 'server');
  await expect(page.locator('[data-presence-row="alice"] .presence-dot-bare')).toHaveCount(1);
  await expect(page.locator('[data-presence] img, [data-presence] .avatar-wrap')).toHaveCount(0);
  await setShowAvatars(page, true);

  // Removal puts the monogram back in the same response.
  await login(page, 'alice');
  await page.goto('/settings/account');
  await page.locator('button').filter({ hasText: /^Remove avatar$/ }).click();
  await expect(page.getByText('Avatar removed.', { exact: false })).toBeVisible();
  await expect(page.locator('.forum-bar-user img')).toHaveCount(0);
  await expect(page.locator('.forum-bar-user span.monogram')).toHaveCount(1);
  await page.goto(topicPath);
  await expect(page.locator('article.post-op > .post-avatar > span.monogram')).toHaveCount(1);

  expect(errors).toEqual([]);
});
