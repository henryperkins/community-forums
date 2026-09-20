import { test, expect, Page, Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

/**
 * Unified member chrome evidence (ADR 0032).
 *
 * The topbar and the rail are the design system's ForumNav and BoardRail,
 * rendered in their own class vocabulary and styled by the layered
 * /assets/imladris.css. These tests MEASURE the things the port could get
 * wrong silently: that the layer's geometry actually lands (an unlayered rule
 * left behind in app.css would beat it regardless of specificity), that the
 * seat's leaf and the profile dot still take their away colour now the
 * unlayered restatements are gone, that a rail row without an avatar still has
 * a dot with a box, that the persisted rail state and the phone drawer survive
 * the rewrite, and that the two controls the design does not draw (Sign up, the
 * compose glyph on a phone) are still there.
 */

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/imladris-unified-chrome');

if (process.env.RB_BASE_URL) {
  test.use({ baseURL: process.env.RB_BASE_URL });
}

/**
 * Refresh the seed's time-relative presence cast for every test, then restore
 * exact prior timestamps, display names, and preferences after an assertion
 * fails. A delayed run must not photograph an empty roster, and an interrupted
 * avatars/rail journey must not change the next test's first paint.
 */
function runPhp(code: string): string {
  return execFileSync('php', ['-r', `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
${code}
`], {
    cwd: ROOT,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
}

let previousState: string | undefined;

test.beforeEach(() => {
  previousState = undefined;
  previousState = runPhp(`
$cast = [
    'elrond' => 40, 'galadriel' => 65, 'glorfindel' => 90, 'arwen' => 120,
    'cirdan' => 150, 'melian' => 180, 'nimrodel' => 210, 'orophin' => 240,
    'idril' => 270, 'ecthelion' => 290, 'erestor' => 420, 'lindir' => 500,
    'haldir' => 620, 'rumil' => 740, 'finduilas' => 860, 'gildor' => 50,
    'celebrian' => 55, 'voronwe' => 4000,
];
$original = $db->transaction(function () use ($db, $cast) {
    $users = $db->fetchAll(
        'SELECT id, username, display_name, last_seen_at, show_presence, profile_visibility, onboarded_at FROM users WHERE username IN ('
        . implode(',', array_fill(0, count($cast), '?')) . ') ORDER BY id',
        array_keys($cast),
    );
    if (count($users) !== count($cast)) {
        throw new RuntimeException('Missing chrome presence fixture: run tests/browser/prepare.sh first.');
    }
    $elrond = array_values(array_filter($users, fn ($user) => $user['username'] === 'elrond'))[0];
    $prefs = $db->fetch('SELECT prefs, updated_at FROM user_preferences WHERE user_id = ?', [$elrond['id']]);
    foreach ($users as $user) {
        $username = $user['username'];
        $db->run(
            'UPDATE users SET last_seen_at = ?, show_presence = ?, profile_visibility = ? WHERE id = ?',
            [gmdate('Y-m-d H:i:s', time() - $cast[$username]), $username === 'gildor' ? 0 : 1,
                $username === 'celebrian' ? 'members' : 'public', $user['id']],
        );
    }
    $db->run('UPDATE users SET onboarded_at = UTC_TIMESTAMP() WHERE id = ?', [$elrond['id']]);
    (new \\App\\Repository\\UserPreferenceRepository($db))->merge((int) $elrond['id'], [
        'show_avatars' => true, 'rail_open' => true, 'theme' => 'light',
    ]);
    return ['users' => $users, 'user_id' => $elrond['id'], 'prefs' => $prefs];
});
echo json_encode($original, JSON_THROW_ON_ERROR);
`);
});

test.afterEach(async ({ context }) => {
  try {
    // Stop browser heartbeats before restoring their timestamp targets.
    await context.close();
  } finally {
    if (previousState !== undefined) {
      const encoded = Buffer.from(previousState).toString('base64');
      runPhp(`
$original = json_decode(base64_decode('${encoded}'), true, 512, JSON_THROW_ON_ERROR);
$db->transaction(function () use ($db, $original) {
    foreach ($original['users'] as $user) {
        $db->run(
            'UPDATE users SET last_seen_at = ?, show_presence = ?, profile_visibility = ?, onboarded_at = ?, display_name = ? WHERE id = ?',
            [$user['last_seen_at'], $user['show_presence'], $user['profile_visibility'], $user['onboarded_at'], $user['display_name'], $user['id']],
        );
    }
    if ($original['prefs'] === null) {
        $db->run('DELETE FROM user_preferences WHERE user_id = ?', [$original['user_id']]);
    } else {
        $db->run('UPDATE user_preferences SET prefs = ?, updated_at = ? WHERE user_id = ?',
            [$original['prefs']['prefs'], $original['prefs']['updated_at'], $original['user_id']]);
    }
});
`);
      previousState = undefined;
    }
  }
});

function shot(name: string, project: string) {
  const dir = path.join(OUT, project);
  fs.mkdirSync(dir, { recursive: true });
  return path.join(dir, name);
}

async function signIn(page: Page, email = 'elrond@retro.test') {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.count()) {
    await skip.first().click();
    await expect(skip.first()).toBeHidden();
  }
}

const colour = (page: Page, selector: string, prop = 'backgroundColor') =>
  page.locator(selector).first().evaluate((el, p) => (getComputedStyle(el) as unknown as Record<string, string>)[p], prop);

async function railIsOffCanvas(page: Page): Promise<boolean> {
  const box = await page.locator('.board-rail').boundingBox();
  return !box || box.x < 0;
}

// ── The vocabulary and the geometry ─────────────────────────────────────────

test('the bar and the rail are the design system\'s, at its geometry, in both registers', async ({ page }, testInfo) => {
  await signIn(page);
  await page.goto('/');

  const bar = page.locator('header.forum-bar');
  await expect(bar).toBeVisible();
  await expect(bar).toHaveCSS('height', '62px');
  await expect(page.locator('.forum-bar-surfaces[aria-label="Primary"]')).toBeVisible();
  await expect(page.locator('.forum-bar-surface.is-active[aria-current="page"]')).toHaveText('Boards');
  await expect(page.locator('.forum-bar-search')).toHaveAttribute('href', '/search');

  if (testInfo.project.name === 'desktop') {
    // The design's 22px gutter is the floor; wider viewports centre the contents.
    const padding = await bar.evaluate((el) => parseFloat(getComputedStyle(el).paddingLeft));
    expect(padding).toBeGreaterThanOrEqual(22);
    const rail = page.locator('nav.board-rail');
    await expect(rail).toBeVisible();
    await expect(rail).toHaveCSS('width', '272px');
    // A retired transfer rule would have kept the old 3px inset shadow; the
    // design marks the active board with a 2px gold left rule.
    await page.goto('/c/general');
    const active = page.locator('.board-rail-item.is-active[aria-current="page"]');
    await expect(active).toHaveCount(1);
    await expect(active).toHaveCSS('border-left-width', '2px');
    await page.goto('/');
  }

  await page.screenshot({ path: shot('01-chrome-light.png', testInfo.project.name), clip: { x: 0, y: 0, width: testInfo.project.name === 'desktop' ? 1280 : 390, height: 420 } });

  // The house mark is inline SVG on currentColor, so the register flips it.
  const lightMark = await colour(page, '.forum-bar-mark', 'color');
  await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
  const darkMark = await colour(page, '.forum-bar-mark', 'color');
  expect(darkMark).not.toBe(lightMark);
  await page.screenshot({ path: shot('02-chrome-dark.png', testInfo.project.name), clip: { x: 0, y: 0, width: testInfo.project.name === 'desktop' ? 1280 : 390, height: 420 } });
  await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'light'));
});

test('long stored account names fit the bar and keep the account menu reachable', async ({ page }, testInfo) => {
  const storeDisplayName = (name: string) => {
    const encoded = Buffer.from(name).toString('base64');
    runPhp(`$db->run('UPDATE users SET display_name = ? WHERE username = ?', [base64_decode('${encoded}'), 'elrond']);`);
  };
  const withinViewport = async (locator: Locator) => {
    await expect(locator).toBeVisible();
    const box = (await locator.boundingBox())!;
    const viewport = await page.evaluate(() => ({
      width: document.documentElement.clientWidth,
      height: document.documentElement.clientHeight,
    }));
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height);
  };
  const account = page.locator('.identity-menu > summary');
  const menu = page.locator('.identity-menu-panel');
  const label = account.locator('.forum-bar-username');
  const desktop = testInfo.project.name === 'desktop';

  storeDisplayName('Elrond Peredhel');
  await signIn(page);
  await page.goto('/inbox');
  await page.evaluate(() => document.fonts.ready);
  if (desktop) {
    // A short name still uses the design's natural 30px avatar, label, 14px
    // chevron, and two 9px gaps; the overflow fix must not reserve a large seat.
    await expect(label).toBeVisible();
    await expect(account.locator('.monogram')).toHaveCSS('width', '30px');
    await expect(account.locator('> .icon')).toHaveCSS('width', '14px');
    const geometry = await account.evaluate((el) => ({
      width: el.getBoundingClientRect().width,
      children: Array.from(el.children).reduce((sum, child) => sum + child.getBoundingClientRect().width, 0),
      labelFits: el.querySelector('.forum-bar-username')!.scrollWidth === el.querySelector('.forum-bar-username')!.clientWidth,
    }));
    expect(geometry.labelFits).toBe(true);
    expect(geometry.width).toBeCloseTo(geometry.children + 18, 0);
  }

  // Start at the reported failure width, then exercise both sides of the
  // 1080px label breakpoint, the narrow desktop bar, and the standard capture.
  const widths = desktop ? [1100, 901, 924, 1024, 1080, 1081, 1280] : [390];
  const names = ['Elrond Peredhel Keeper of the Last House', 'W'.repeat(64)];
  for (const name of names) {
    storeDisplayName(name);
    await page.goto('/inbox');
    await page.evaluate(() => document.fonts.ready);
    await expect(label).toHaveText(name);
    for (const width of widths) {
      await page.setViewportSize({ width, height: desktop ? 800 : 844 });
      for (const theme of ['light', 'dark']) {
        await test.step(`${name.length}-character name at ${width}px in ${theme}`, async () => {
          await page.evaluate((value) => document.documentElement.setAttribute('data-theme', value), theme);
          expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
          await withinViewport(account);
          await expect(account).toHaveAccessibleName(`Open account menu for ${name}`);
          await expect(account.locator('.monogram')).toHaveCSS('width', '30px');

          // Native summary activation and real Tab order prove that shrinking
          // the identity cannot hide or clip the account's actual destinations.
          await account.press('Enter');
          await expect(page.locator('.identity-menu')).toHaveAttribute('open', '');
          const focusedBox = (await account.boundingBox())!;
          const contentWidth = await page.evaluate(() => document.documentElement.clientWidth);
          // The keyboard outline extends 4px beyond the control's border.
          expect(focusedBox.x).toBeGreaterThanOrEqual(4);
          expect(focusedBox.x + focusedBox.width + 4).toBeLessThanOrEqual(contentWidth);
          await withinViewport(menu);
          const unobscured = await menu.evaluate((el) => {
            const box = el.getBoundingClientRect();
            const centerX = box.left + box.width / 2;
            const centerY = box.top + box.height / 2;
            return [[centerX, box.top + 2], [box.right - 2, centerY],
              [centerX, box.bottom - 2], [box.left + 2, centerY]]
              .every(([x, y]) => el.contains(document.elementFromPoint(x, y)));
          });
          expect(unobscured, 'all four menu edges must remain visible outside the bar').toBe(true);
          if (name === names[0] && width === widths[0] && theme === 'light') {
            await page.screenshot({ path: shot('08-long-account-name.png', testInfo.project.name) });
          }
          for (const action of await menu.locator('a, button').all()) {
            await page.keyboard.press('Tab');
            await expect(action).toBeFocused();
            await withinViewport(action);
          }
          expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width);
          await account.press('Enter');
          await expect(menu).toBeHidden();
        });
      }
    }
  }
  await account.press('Enter');
  await menu.locator('a[href="/settings/account"]').press('Enter');
  await expect(page).toHaveURL(/\/settings\/account$/);
  await expect(page.getByRole('heading', { name: 'Account settings', exact: true })).toBeVisible();
});

// ── The cascade cases the unlayered restatements used to cover ──────────────

test('the seat\'s leaf and the profile dot still take the away colour from the layer', async ({ page }) => {
  await signIn(page);
  await page.goto('/');

  // The viewer just signed in, so their own leaf is drawn by the layer's
  // .avatar-wrap .presence-dot — the topbar no longer has an unlayered copy.
  const leaf = page.locator('.forum-bar-user .avatar-wrap .presence-dot');
  await expect(leaf).toHaveCount(1);
  const leafBox = (await leaf.boundingBox())!;
  expect(leafBox.width).toBeGreaterThan(4);
  const here = await colour(page, '.forum-bar-user .presence-dot');
  await leaf.evaluate((el) => el.classList.add('is-away'));
  const away = await colour(page, '.forum-bar-user .presence-dot');
  expect(away).not.toBe(here);

  // The profile keeps its unlayered .profile-avatar dot, so ADR 0031's
  // `.presence-dot.is-away` restatement stays for it — prove it still lands.
  await page.goto('/u/lindir');    // refreshed as stepped away (500s) before this test
  const profileAway = page.locator('.profile-avatar .presence-dot.is-away[role="img"]');
  await expect(profileAway).toHaveAttribute('aria-label', 'Stepped away');
  const profileAwayColour = await colour(page, '.profile-avatar .presence-dot');
  await page.goto('/u/galadriel'); // refreshed as here now
  const profileHereColour = await colour(page, '.profile-avatar .presence-dot');
  expect(profileAwayColour).not.toBe(profileHereColour);
});

test('a rail row without an avatar keeps a dot with a box', async ({ page }) => {
  await signIn(page);
  // "Show avatars" is a reading preference; off, the row's dot stands alone.
  await page.goto('/settings/preferences');
  const avatars = page.locator('input[name="show_avatars"]');
  await expect(avatars).toBeChecked();
  await avatars.uncheck();
  await page.locator('form:has(input[name="show_avatars"]) button[type="submit"]').first().click();
  await page.goto('/');
  if (await railIsOffCanvas(page)) { await page.locator('.nav-toggle[data-nav-toggle]').click(); }

  const bare = page.locator('[data-presence] .presence-dot-bare').first();
  await expect(bare).toBeAttached();
  const box = (await bare.boundingBox())!;
  expect(box.width).toBeGreaterThan(4);
  expect(box.height).toBeGreaterThan(4);
  await expect(page.locator('[data-presence] .avatar-wrap')).toHaveCount(0);

  await page.goto('/settings/preferences');
  await page.locator('input[name="show_avatars"]').check();
  await page.locator('form:has(input[name="show_avatars"]) button[type="submit"]').first().click();
});

test('rail rows hover without an underline and "See everyone online" takes the accent', async ({ page }) => {
  await signIn(page);
  await page.goto('/');
  if (await railIsOffCanvas(page)) { await page.locator('.nav-toggle[data-nav-toggle]').click(); }

  // The transfer block's unlayered `.presence-list a:hover` is gone, and the
  // layer's `a.presence-person:hover` cannot beat app.css's own unlayered
  // `a:hover` underline — the "Member chrome" block suppresses it explicitly.
  const row = page.locator('[data-presence] a.presence-person').first();
  await row.hover();
  const decoration = await row.evaluate((el) => getComputedStyle(el).textDecorationLine);
  expect(decoration).toBe('none');

  // The bridge's unlayered `.presence-all { color: --text-muted }` swallowed
  // the design's hover accent for as long as it existed.
  const all = page.locator('[data-presence] .presence-all');
  const rest = await all.evaluate((el) => getComputedStyle(el).color);
  await all.hover();
  await expect.poll(() => all.evaluate((el) => getComputedStyle(el).color)).not.toBe(rest);
});

// ── The state the design leaves to the client, kept server-owned here ───────

test('reading a topic keeps the chrome unread counts capped, named, and synchronized', async ({ page }, testInfo) => {
  // A real unread queue crosses the 99+ boundary. All fixture rows belong to a
  // temporary member and board, so cleanup leaves existing read state intact.
  const fixture = JSON.parse(runPhp(`
$fixture = $db->transaction(function () use ($db) {
    $users = new \\App\\Repository\\UserRepository($db);
    $seed = $users->findByUsername('elrond');
    $username = 'chrome_counts_' . bin2hex(random_bytes(4));
    $userId = $users->create([
        'username' => $username, 'email' => $username . '@retro.test',
        'password_hash' => $seed['password_hash'], 'display_name' => 'Unread reader',
    ]);
    $db->run('UPDATE users SET onboarded_at = UTC_TIMESTAMP(), show_presence = 0 WHERE id = ?', [$userId]);
    $db->run('INSERT INTO user_board_prefs (user_id, board_id, is_muted) SELECT ?, id, 1 FROM boards', [$userId]);
    (new \\App\\Repository\\UserPreferenceRepository($db))->merge($userId, [
        'rail_open' => true, 'inbox_reading_open' => true,
    ]);
    $categoryId = (new \\App\\Repository\\CategoryRepository($db))->create('Unread review', -1);
    $boardId = (new \\App\\Repository\\BoardRepository($db))->create([
        'category_id' => $categoryId, 'slug' => $username, 'name' => 'Unread review',
    ]);
    $threads = new \\App\\Repository\\ThreadRepository($db);
    $posts = new \\App\\Repository\\PostRepository($db);
    $state = new \\App\\Repository\\ThreadUserRepository($db);
    for ($i = 1; $i <= 102; $i++) {
        $threadId = $threads->create($boardId, $userId, 'Unread review ' . $i, 'unread-review-' . $i);
        $postId = $posts->create([
            'thread_id' => $threadId, 'user_id' => $userId, 'is_op' => true,
            'body' => 'A topic to read.', 'body_html' => '<p>A topic to read.</p>',
        ]);
        $threads->updateLastPost($threadId, $postId, $userId, gmdate('Y-m-d H:i:s'));
        $state->markUnread($userId, $threadId);
    }
    $db->run('UPDATE boards SET thread_count = 102, post_count = 102 WHERE id = ?', [$boardId]);
    $db->run('UPDATE users SET post_count = 102 WHERE id = ?', [$userId]);
    return ['user' => $userId, 'category' => $categoryId, 'board' => $boardId, 'username' => $username];
});
echo json_encode($fixture, JSON_THROW_ON_ERROR);
`));
  try {
    await signIn(page, `${fixture.username}@retro.test`);
    await page.goto('/inbox?scope=unread&order=newest');
    const count = page.locator('.forum-bar-count');
    const railCount = page.locator(`[data-board-slug="${fixture.username}"] [data-board-unread-count]`);
    await expect(count).toHaveText('99+');
    await expect(count).toHaveAttribute('aria-label', '102 unread topics');
    await expect(railCount).toHaveText('99+');

    for (const remaining of [101, 100, 99]) {
      await page.locator('[data-inbox-row] .inbox-row-title').first().click();
      await expect(page.locator('[data-inbox-preview]')).toBeVisible();
      await expect(count).toHaveAttribute('data-inbox-unread-count', String(remaining));
      await expect(page.locator('[data-inbox-current-count]')).toHaveText(String(remaining));
      await expect(count).toHaveText(remaining > 99 ? '99+' : '99');
      await expect(count).toHaveAttribute('aria-label', `${remaining} unread topics`);
      await expect(railCount).toHaveAttribute('data-board-unread-count', String(remaining));
      await expect(railCount).toHaveText(remaining > 99 ? '99+' : '99');
      await expect(railCount).toHaveAttribute('aria-label', `${remaining} unread topics`);
      await expect(railCount).toHaveAttribute('title', `${remaining} unread topics`);
      if (testInfo.project.name === 'mobile') await page.locator('[data-inbox-back]').click();
    }
    await page.screenshot({ path: shot('09-unread-counts.png', testInfo.project.name), fullPage: true });
    await page.goto('/inbox?scope=unread&order=newest');
    await expect(count).toHaveText('99');
    await expect(railCount).toHaveText('99');

    // Check the singular label and final badge removal without reading a
    // hundred topics through the browser. Only this member's fixture changes.
    runPhp(`
$state = new \\App\\Repository\\ThreadUserRepository($db);
$threads = $db->fetchAll('SELECT id, last_post_id FROM threads WHERE board_id = ? ORDER BY id', [${fixture.board}]);
foreach (array_slice($threads, 2) as $thread) {
    $state->markRead(${fixture.user}, (int) $thread['id'], (int) $thread['last_post_id']);
}
`);
    await page.goto('/inbox?scope=unread&order=newest');
    await expect(count).toHaveText('2');
    for (const remaining of [1, 0]) {
      await page.locator('[data-inbox-row] .inbox-row-title').first().click();
      await expect(page.locator('[data-inbox-preview]')).toBeVisible();
      await expect(page.locator('[data-inbox-current-count]')).toHaveText(String(remaining));
      if (remaining === 1) {
        await expect(count).toHaveText('1');
        await expect(count).toHaveAttribute('aria-label', '1 unread topic');
        await expect(railCount).toHaveText('1');
        await expect(railCount).toHaveAttribute('aria-label', '1 unread topic');
      } else {
        await expect(count).toHaveCount(0);
        await expect(railCount).toHaveCount(0);
      }
      if (testInfo.project.name === 'mobile') await page.locator('[data-inbox-back]').click();
    }
    await page.goto('/inbox');
    await expect(count).toHaveCount(0);
    await expect(railCount).toHaveCount(0);
  } finally {
    await page.context().close();
    runPhp(`
$db->transaction(function () use ($db) {
    $db->run('UPDATE threads SET last_post_id = NULL WHERE board_id = ?', [${fixture.board}]);
    $db->run('DELETE FROM posts WHERE thread_id IN (SELECT id FROM threads WHERE board_id = ?)', [${fixture.board}]);
    $db->run('DELETE FROM threads WHERE board_id = ?', [${fixture.board}]);
    $db->run('DELETE FROM boards WHERE id = ?', [${fixture.board}]);
    $db->run('DELETE FROM categories WHERE id = ?', [${fixture.category}]);
    $db->run('DELETE FROM users WHERE id = ?', [${fixture.user}]);
});
`);
  }
});

test('the rail state persists and the drawer opens', async ({ page }, testInfo) => {
  await signIn(page);
  await page.goto('/');

  if (testInfo.project.name === 'desktop') {
    const toggle = page.locator('[data-panel-form="rail"] .forum-bar-railtoggle');
    await expect(toggle).toHaveClass(/is-on/);
    await page.locator('h1').first().click();
    const closed = page.waitForResponse((response) => response.url().endsWith('/settings/member-surfaces')
      && response.request().method() === 'POST');
    await page.keyboard.press('Control+b');
    expect((await closed).status()).toBe(303);
    await expect(page.locator('body')).toHaveClass(/is-rail-closed/);
    await expect(toggle).not.toHaveClass(/is-on/);
    await expect(page.locator('nav.board-rail')).toBeHidden();
    await page.screenshot({ path: shot('04-rail-closed.png', testInfo.project.name), clip: { x: 0, y: 0, width: 1280, height: 420 } });
    await page.reload();
    await expect(page.locator('body')).toHaveClass(/is-rail-closed/);
    const reopened = page.waitForResponse((response) => response.url().endsWith('/settings/member-surfaces')
      && response.request().method() === 'POST');
    await page.keyboard.press('Control+b');
    expect((await reopened).status()).toBe(303);
    await expect(page.locator('body')).toHaveClass(/is-rail-open/);
    await expect(toggle).toHaveClass(/is-on/);
  } else {
    expect(await railIsOffCanvas(page)).toBe(true);
    const opener = page.getByRole('button', { name: 'Open board rail' });
    const box = (await opener.boundingBox())!;
    expect(box.width).toBeGreaterThanOrEqual(44);
    await opener.click();
    await expect.poll(() => railIsOffCanvas(page)).toBe(false);
    await page.screenshot({ path: shot('03-drawer-open.png', testInfo.project.name) });
    // The persisted toggle has no meaning behind a drawer, so it is not shown.
    await expect(page.locator('[data-panel-form="rail"]')).toBeHidden();
  }
});

test('the closed phone drawer is excluded from keyboard navigation', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile', 'phone drawer accessibility contract');
  await signIn(page);
  await page.goto('/');

  const rail = page.locator('nav.board-rail');
  const opener = page.locator('[data-nav-toggle]');
  await expect(rail).toBeHidden();
  await expect(opener).toHaveAttribute('aria-expanded', 'false');
  await opener.press('Enter');
  await expect(rail).toBeVisible();
  await expect(opener).toHaveAttribute('aria-expanded', 'true');
  await expect.poll(() => railIsOffCanvas(page)).toBe(false);

  // Walk the real tab order from the opener into the shown board links.
  let reachedBoard = false;
  for (let index = 0; index < 25; index++) {
    await page.keyboard.press('Tab');
    reachedBoard = await rail.locator('a').evaluateAll((links) => links.includes(document.activeElement as HTMLAnchorElement));
    if (reachedBoard) break;
  }
  expect(reachedBoard, 'the opened drawer must expose its board links to the keyboard').toBe(true);
  await page.keyboard.press('Escape');
  await expect(rail).toBeHidden();
  await expect(opener).toHaveAttribute('aria-expanded', 'false');
  await expect(opener).toBeFocused();
});

test('phone primary navigation reaches Messages without horizontal page overflow', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile', 'phone primary navigation contract');
  await signIn(page);
  await page.goto('/');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

  const messages = page.locator('.forum-bar-surfaces a[href="/messages"]');
  await expect(messages).toBeVisible();
  await messages.click();
  await expect(page).toHaveURL(/\/messages$/);
  await expect(messages).toHaveAttribute('aria-current', 'page');
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
});

test('a guest gets the design\'s Log in pill and production\'s Sign up beside it', async ({ page }, testInfo) => {
  await page.goto('/');
  await expect(page.locator('.forum-bar-signin')).toHaveText('Log in');
  await expect(page.locator('.forum-bar-signup')).toHaveText('Sign up');
  await expect(page.locator('.forum-bar-user')).toHaveCount(0);
  await page.screenshot({ path: shot('05-guest.png', testInfo.project.name), clip: { x: 0, y: 0, width: testInfo.project.name === 'desktop' ? 1280 : 390, height: 420 } });
});

test('New topic survives the phone breakpoint as its glyph', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'mobile', 'phone chrome contract');
  await signIn(page);
  await page.goto('/inbox');
  // The design hides compose below 720px; only a board page has a floating
  // compose, so the control stays as a 40px glyph everywhere else.
  const compose = page.locator('.forum-bar-compose .btn');
  await expect(compose).toBeVisible();
  const box = (await compose.boundingBox())!;
  expect(box.width).toBeGreaterThanOrEqual(40);
  await expect(compose.locator('span')).toBeHidden();
  await expect(compose.locator('.icon')).toBeVisible();
});


// ── Native navigation and persisted forms are the baseline ─────────────────

test.describe('with JavaScript disabled', () => {
  test.use({ javaScriptEnabled: false, reducedMotion: 'reduce' });

  test('the desktop rail POST persists across reloads and destinations', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'the persisted toggle is desktop-only');
    await signIn(page);
    await page.goto('/c/general');

    const toggle = page.locator('[data-panel-form="rail"] button');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('nav.board-rail')).toBeVisible();
    const closed = page.waitForResponse((response) => response.url().endsWith('/settings/member-surfaces')
      && response.request().method() === 'POST');
    await toggle.click();
    expect((await closed).status()).toBe(303);
    await expect(page).toHaveURL(/\/c\/general$/);
    await expect(page.locator('body')).toHaveClass(/is-rail-closed/);
    await expect(page.locator('nav.board-rail')).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await page.reload();
    await expect(page.locator('nav.board-rail')).toBeHidden();

    await page.locator('.forum-bar-surfaces a[href="/inbox"]').click();
    await expect(page).toHaveURL(/\/inbox$/);
    await expect(page.locator('nav.board-rail')).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await page.screenshot({ path: shot('06-rail-closed-nojs.png', testInfo.project.name), fullPage: true });

    const reopened = page.waitForResponse((response) => response.url().endsWith('/settings/member-surfaces')
      && response.request().method() === 'POST');
    await toggle.click();
    expect((await reopened).status()).toBe(303);
    await expect(page.locator('nav.board-rail')).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.reload();
    await expect(page.locator('body')).toHaveClass(/is-rail-open/);
    await expect(page.locator('nav.board-rail')).toBeVisible();
  });

  test('mobile chrome reaches boards, messages, and account settings through native controls', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'mobile', 'phone progressive-enhancement contract');
    await signIn(page);
    await page.goto('/');

    await expect(page.locator('.nav-toggle')).toBeHidden();
    const rail = page.locator('nav.board-rail');
    await expect(rail).toBeVisible();
    expect(await railIsOffCanvas(page)).toBe(false);
    await rail.locator('a[href="/c/general"]').click();
    await expect(page).toHaveURL(/\/c\/general$/);
    await expect(rail.locator('a[aria-current="page"]')).toHaveAttribute('href', '/c/general');

    await page.locator('.forum-bar-surfaces a[href="/inbox"]').click();
    await expect(page).toHaveURL(/\/inbox$/);
    await expect(page.locator('.forum-bar-surfaces a[aria-current="page"]')).toContainText('Inbox');

    // A narrow bar may move Messages into the native account disclosure; one
    // visible shared entry point must survive either responsive arrangement.
    await page.locator('.identity-menu > summary').click();
    await expect(page.locator('.identity-menu')).toHaveAttribute('open', '');
    const messages = page.locator('.forum-bar a[href="/messages"]:visible, .board-rail a[href="/messages"]:visible').first();
    await expect(messages).toBeVisible();
    await messages.click();
    await expect(page).toHaveURL(/\/messages$/);

    await page.locator('.identity-menu > summary').click();
    const settings = page.locator('.identity-menu-panel a[href="/settings/account"]');
    await expect(settings).toBeVisible();
    const menu = (await page.locator('.identity-menu-panel').boundingBox())!;
    expect(menu.x).toBeGreaterThanOrEqual(0);
    expect(menu.x + menu.width).toBeLessThanOrEqual(390);
    await page.screenshot({ path: shot('07-account-menu-nojs.png', testInfo.project.name), fullPage: true });
    await settings.click();
    await expect(page).toHaveURL(/\/settings\/account$/);
    await expect(page.getByRole('heading', { name: 'Account settings', exact: true })).toBeVisible();
  });
});
