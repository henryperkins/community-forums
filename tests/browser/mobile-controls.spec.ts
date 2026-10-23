import { expect, test, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const out = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? '/tmp/retroboards-mobile-controls');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
let errors: string[];
test.beforeEach(({ page }) => {
  errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
});
test.afterEach(() => { expect(errors).toEqual([]); });

function php(code: string): string {
  return execFileSync('php', ['-r', `require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd().'/.env');
$config=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php');
$db=new \\App\\Core\\Database($config->get('db'));
$settings=new \\App\\Repository\\SettingRepository($db);
${code}`], { cwd: root, env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' } }).toString().trim();
}

const topic = () => php(`$t=$db->fetch('SELECT id,slug FROM threads WHERE title=?', ['Share your favourite keyboard shortcuts']);
echo '/t/'.$t['id'].'-'.$t['slug'];`);

async function login(page: Page) {
  await page.goto('/login');
  await page.locator('input[name=email]').fill('alice@retro.test');
  await page.locator('input[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

function reactionFixture(): { post: number; snapshot: string } {
  const snapshot = php(`$t=$db->fetch('SELECT id FROM threads WHERE title=?', ['Share your favourite keyboard shortcuts']);
$post=(int)$db->fetchValue('SELECT id FROM posts WHERE thread_id=? AND is_op=1', [$t['id']]);
$viewer=$db->fetch('SELECT id,status,suspended_until FROM users WHERE email=?', ['alice@retro.test']);
$bob=(int)$db->fetchValue('SELECT id FROM users WHERE email=?', ['bob@retro.test']);
$snapshot=['post'=>$post,'viewer'=>$viewer,'reactions'=>$db->fetchAll('SELECT * FROM reactions WHERE post_id=?', [$post]),
  'prefs'=>$db->fetch('SELECT * FROM user_preferences WHERE user_id=?', [$viewer['id']])];
$db->transaction(function() use($db,$post,$bob) {
  $db->run('DELETE FROM reactions WHERE post_id=?', [$post]);
  foreach(['👍','💯','👀'] as $emoji) $db->run('INSERT INTO reactions(post_id,user_id,emoji,created_at) VALUES(?,?,?,UTC_TIMESTAMP())', [$post,$bob,$emoji]);
});
echo json_encode($snapshot);`);
  return { post: JSON.parse(snapshot).post, snapshot };
}

function restoreReactionFixture(fixture: { snapshot: string }) {
  const encoded = Buffer.from(fixture.snapshot).toString('base64');
  php(`$s=json_decode(base64_decode('${encoded}'), true);
$db->transaction(function() use($db,$s) {
  $db->run('DELETE FROM reactions WHERE post_id=?', [$s['post']]);
  foreach($s['reactions'] as $r) $db->run('INSERT INTO reactions(id,post_id,user_id,emoji,created_at) VALUES(?,?,?,?,?)', [$r['id'],$r['post_id'],$r['user_id'],$r['emoji'],$r['created_at']]);
  $db->run('UPDATE users SET status=?,suspended_until=? WHERE id=?', [$s['viewer']['status'],$s['viewer']['suspended_until'],$s['viewer']['id']]);
  $db->run('DELETE FROM user_preferences WHERE user_id=?', [$s['viewer']['id']]);
  if($s['prefs']) $db->run('INSERT INTO user_preferences(user_id,prefs,updated_at) VALUES(?,?,?)', [$s['prefs']['user_id'],$s['prefs']['prefs'],$s['prefs']['updated_at']]);
});`);
}

async function screenshot(page: Page, name: string) {
  mkdirSync(out, { recursive: true });
  await page.evaluate(async () => {
    // WebKit can retain completed transitions with unsettled finished promises.
    // Their final frame is visible; only live finite effects need settling.
    const animations = document.getAnimations().filter(a =>
      a.playState !== 'finished' && a.effect?.getTiming().iterations !== Infinity);
    await Promise.all(animations.map(a => a.finished.catch(() => {})));
  });
  const before = errors.length;
  // WebKit's Playwright screenshot preparation injects `body {}` to sync
  // animations. Identify only that capture-time diagnostic; application
  // errors before and after capture still fail the test, and CSP stays strict.
  await page.screenshot({ path: path.join(out, name + '.png'), caret: 'initial' });
  if (process.env.E2E_LAYOUT_BROWSER === 'webkit') {
    const captureErrors = errors.splice(before);
    for (const message of captureErrors) {
      expect(message).toBe("Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive of the Content Security Policy.");
    }
  }
}

async function containedButton(form: Locator) {
  return form.evaluate(element => {
    const frame = element.querySelector('.composer-box')!;
    const button = element.querySelector('.composer-send')!;
    const a = frame.getBoundingClientRect();
    const b = button.getBoundingClientRect();
    const radius = Math.min(parseFloat(getComputedStyle(frame).borderTopRightRadius), a.width / 2, a.height / 2);
    const buttonRadius = Math.min(parseFloat(getComputedStyle(button).borderTopRightRadius), b.width / 2, b.height / 2);
    // The button's rounded corners must fit inside the frame's rounded edge,
    // as well as inside its bounding box. A rectangular Reply control can
    // otherwise visibly protrude through the corner of a pill-shaped frame.
    const inset = buttonRadius * (1 - Math.SQRT1_2);
    const fitsCorner = (x: number, y: number, cx: number, cy: number) =>
      x <= cx || (y >= a.top + radius && y <= a.bottom - radius) ||
      (x - cx) ** 2 + (y - cy) ** 2 <= radius ** 2 + 1;
    return {
      rectangular: b.left >= a.left && b.right <= a.right && b.top >= a.top && b.bottom <= a.bottom,
      rounded: fitsCorner(b.right - inset, b.top + inset, a.right - radius, a.top + radius) &&
        fitsCorner(b.right - inset, b.bottom - inset, a.right - radius, a.bottom - radius),
      height: b.height,
    };
  });
}

test('mobile board drawer leaves room beside every member surface', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile drawer');
  await login(page);
  for (const width of [320, 390, 820]) {
    await page.setViewportSize({ width, height: 844 });
    for (const route of ['/?pane=boards', '/inbox', '/messages', topic(), '/settings']) {
      await page.goto(route);
      const opener = page.getByRole('button', { name: 'Open board rail', exact: true });
      await opener.click();
      const rail = page.locator('[data-sidebar]');
      await expect(rail).toBeVisible();
      const box = (await rail.boundingBox())!;
      expect.soft(box.width, `${route} at ${width}px`).toBeLessThanOrEqual(Math.min(240, width * .7) + 1);
      await expect(rail.getByRole('link', { name: 'Boards', exact: true })).toBeVisible();
      await rail.getByRole('link', { name: 'Close board rail' }).click();
      await expect(opener).toBeFocused();
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/?pane=boards');
  await page.getByRole('button', { name: 'Open board rail', exact: true }).click();
  await screenshot(page, 'drawer-' + info.project.name);
  await page.keyboard.press('Escape');
  await expect(page.getByRole('button', { name: 'Open board rail', exact: true })).toBeFocused();
});

for (const rich of [false, true]) {
  test(`reply stays inside its frame and preserves a minimized draft (${rich ? 'rich' : 'Markdown'})`, async ({ page }, info) => {
    const original = php(`echo json_encode($settings->get('features', []));`);
    try {
      php(`$f=$settings->get('features', []); $f['wysiwyg_composer']=${rich ? 'true' : 'false'}; $settings->set('features', $f);
$db->run('DELETE FROM server_drafts WHERE user_id=(SELECT id FROM users WHERE email=?)', ['alice@retro.test']);`);
      await login(page);
      await page.goto(topic());
      const form = page.locator('form.reply-composer');
      await expect(form).toHaveAttribute('data-composer-dock', '1');
      for (const width of info.project.name === 'mobile' ? [320, 390, 820] : [1280]) {
        await page.setViewportSize({ width, height: 844 });
        for (const scale of ['100%', '200%']) {
          await page.evaluate(scale => { document.documentElement.style.fontSize = scale; }, scale);
          await form.scrollIntoViewIfNeeded();
          const fit = await containedButton(form);
          expect.soft(fit.rectangular, `bounding box at ${width}px/${scale}`).toBe(true);
          expect.soft(fit.rounded, `rounded edge at ${width}px/${scale}`).toBe(true);
          expect.soft(fit.height).toBeGreaterThanOrEqual(44);
          const box = (await form.boundingBox())!;
          expect.soft(box.x + box.width, `reply width at ${width}px/${scale}`).toBeLessThanOrEqual(width);
        }
      }
      await page.evaluate(() => { document.documentElement.style.fontSize = '100%'; });
      await page.setViewportSize({ width: info.project.name === 'mobile' ? 390 : 1280, height: 844 });
      await page.goto(topic());
      await expect(form).toHaveAttribute('data-composer-dock', '1');
      await form.scrollIntoViewIfNeeded();
      await screenshot(page, `reply-${rich ? 'rich' : 'markdown'}-${info.project.name}`);
      const body = form.locator(rich ? '.ProseMirror' : 'textarea.composer-input');
      await body.fill('A draft that survives minimizing.');
      await expect(form).toHaveClass(/is-expanded/);
      if (info.project.name === 'mobile') {
        await form.getByRole('button', { name: 'Minimize reply' }).click();
        await expect(form).not.toHaveClass(/is-expanded/);
        expect.soft((await containedButton(form)).rounded).toBe(true);
        await body.click();
        await expect(form).toHaveClass(/is-expanded/);
      }
      await expect(form.locator('textarea.composer-input')).toHaveValue('A draft that survives minimizing.');
    } finally {
      const encoded = Buffer.from(original).toString('base64');
      php(`$settings->set('features', json_decode(base64_decode('${encoded}'), true));
$db->run('DELETE FROM server_drafts WHERE user_id=(SELECT id FROM users WHERE email=?)', ['alice@retro.test']);`);
    }
  });
}

test('mobile reaction choices show distinct icons and visible names in labelled rows', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile post menu');
  // This contract covers the nine built-in icons. Gate A's earlier journeys
  // leave custom reactions in the shared DB; preserve their catalogue and
  // restore the original feature map even if this test fails.
  const originalFeatures = php(`echo json_encode($settings->get('features', []));`);
  const encodedFeatures = Buffer.from(originalFeatures).toString('base64');
  php(`$features = json_decode(base64_decode('${encodedFeatures}'), true);
$features['custom_emoji'] = false;
$settings->set('features', $features);`);
  try {
    await login(page);
    await page.goto(topic());
    const post = page.locator('.post').first();
    await post.locator('.post-menu > summary').click();
    const tray = post.locator('.post-menu-reactions');
    const heartButton = tray.getByRole('button', { name: 'Heart', exact: true });
    const wasOn = await heartButton.getAttribute('aria-pressed') === 'true';
    if (wasOn) {
      const removed = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/react'));
      await heartButton.click();
      expect((await removed).ok()).toBe(true);
      await expect(heartButton).toHaveAttribute('aria-pressed', 'false');
    }
    const buttons = tray.locator('button.reaction');
    await expect(buttons).toHaveCount(9);
    const shapes = new Set<string>();
    const rows = new Set<number>();
    for (const button of await buttons.all()) {
      const label = await button.getAttribute('data-label');
      await expect(button).toHaveAccessibleName(label!);
      const name = button.locator('.reaction-name');
      await expect(name).toBeVisible();
      await expect(name).toHaveText(label!);
      const box = (await button.boundingBox())!;
      const nameBox = (await name.boundingBox())!;
      expect.soft(box.width).toBeGreaterThanOrEqual(44);
      expect.soft(box.height).toBeGreaterThanOrEqual(44);
      expect.soft(nameBox.width).toBeGreaterThan(1);
      expect.soft(nameBox.x).toBeGreaterThanOrEqual(box.x);
      expect.soft(nameBox.x + nameBox.width).toBeLessThanOrEqual(box.x + box.width);
      rows.add(Math.round(box.y));
      shapes.add(await button.locator('svg').innerHTML());
    }
    expect.soft(rows.size).toBe(9);
    expect.soft(shapes.size).toBe(9);
    expect.soft(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
      await page.evaluate(() => document.documentElement.clientWidth),
    );
    await screenshot(page, 'reaction-menu-' + info.project.name);
    const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/react'));
    await heartButton.click();
    expect((await response).ok()).toBe(true);
    const chip = post.locator('.reactions button[data-label="Heart"]');
    await expect(chip).toHaveAttribute('aria-pressed', 'true');
    await expect(chip.locator('svg')).toHaveClass(/icon-heart/);
    await expect(chip).toHaveAccessibleName(/Heart/);
    if (!wasOn) {
      const removed = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/react'));
      await heartButton.click();
      expect((await removed).ok()).toBe(true);
      await expect(heartButton).toHaveAttribute('aria-pressed', 'false');
    }
  } finally {
    php(`$settings->set('features', json_decode(base64_decode('${encodedFeatures}'), true));`);
  }
});

for (const javaScriptEnabled of [true, false]) {
  test.describe(javaScriptEnabled ? 'enhanced reactions' : 'native reactions', () => {
    test.use({ javaScriptEnabled });

    test('responsive reaction pickers stay inside the viewport and retain native submission', async ({ page }, info) => {
      test.skip(info.project.name !== 'desktop', 'fine-pointer picker');
      const fixture = reactionFixture();
      try {
        await login(page);
        for (const theme of ['light', 'dark']) {
          php(`(new \\App\\Repository\\UserPreferenceRepository($db))->merge((int)$db->fetchValue('SELECT id FROM users WHERE email=?', ['alice@retro.test']), ['theme'=>'${theme}']);`);
          for (const width of [769, 820, 860, 861, 900, 1024, 1280]) {
            // Each sample is a fresh responsive page, not a resize of the old
            // header while the next navigation is already tearing it down.
            await page.goto('about:blank');
            await page.setViewportSize({ width, height: 844 });
            await page.goto(topic());
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            await page.evaluate(() => document.fonts.ready);
            const post = page.locator(`#p${fixture.post}`);
            // The compact shell exposes reactions through More even for a mouse.
            // Above 860px the fine-pointer toolbar owns the picker instead.
            if (width <= 860) {
              await expect(post.locator('.reaction-add > summary')).toBeHidden();
              await post.locator('[data-post-menu] > summary').click();
            } else {
              await post.hover({ position: { x: 80, y: 80 } });
              await post.locator('.reaction-add > summary').click();
            }
            const menu = post.locator(width <= 860 ? '.post-menu-reactions' : '.reaction-menu');
            await expect(menu).toBeVisible();
            const box = (await menu.boundingBox())!;
            const viewport = await page.evaluate(() => document.documentElement.clientWidth);
            expect.soft(box.x, `${theme}/${width}px left edge`).toBeGreaterThanOrEqual(0);
            expect.soft(box.x + box.width, `${theme}/${width}px right edge`).toBeLessThanOrEqual(viewport);
            expect.soft(await page.evaluate(() => document.documentElement.scrollWidth), `${theme}/${width}px document width`).toBeLessThanOrEqual(viewport);
            for (const button of await menu.locator('button.reaction').all()) {
              const target = (await button.boundingBox())!;
              expect.soft(target.x).toBeGreaterThanOrEqual(0);
              expect.soft(target.x + target.width).toBeLessThanOrEqual(viewport);
              expect.soft(target.width).toBeGreaterThanOrEqual(44);
              expect.soft(target.height).toBeGreaterThanOrEqual(44);
            }
            if (width === 860) await screenshot(page, `reaction-picker-compact-${theme}-${javaScriptEnabled ? 'enhanced' : 'native'}`);
            if (width === 861) await screenshot(page, `reaction-picker-${theme}-${javaScriptEnabled ? 'enhanced' : 'native'}`);
          }
        }
        const button = page.locator(`#p${fixture.post} .reaction-menu`).getByRole('button', { name: 'Seconded', exact: true });
        const response = page.waitForResponse(r => r.url().endsWith(`/posts/${fixture.post}/react`) && r.request().method() === 'POST');
        await button.click();
        expect((await response).status()).toBe(303);
        await expect(page.locator(`#p${fixture.post} .reactions button[data-label="Seconded"]`)).toHaveAttribute('aria-pressed', 'true');
      } finally {
        restoreReactionFixture(fixture);
      }
    });

    for (const viewer of ['guest', 'suspended', 'banned']) {
      test(`${viewer} reaction counts keep visible names without reactor identities`, async ({ page }, info) => {
        const fixture = reactionFixture();
        try {
          if (viewer !== 'guest') {
            await login(page);
            php(`$db->run('UPDATE users SET status=?,suspended_until=? WHERE email=?', ['${viewer}','2099-01-01 00:00:00','alice@retro.test']);`);
          }
          for (const width of info.project.name === 'mobile' ? [320, 390, 820] : [861, 900, 1280]) {
            await page.goto('about:blank');
            await page.setViewportSize({ width, height: 844 });
            await page.goto(topic());
            await page.evaluate(() => document.fonts.ready);
            const tray = page.locator(`#p${fixture.post} .reactions`);
            await tray.scrollIntoViewIfNeeded();
            await expect(tray.locator('.reaction-static')).toHaveCount(3);
            await expect(tray.locator('button, form, .reaction-tip')).toHaveCount(0);
            expect(await tray.innerHTML()).not.toContain('@bob');
            for (const [name, icon] of [['Commend', 'commend-star'], ['Seconded', 'check'], ['Illuminating', 'lightbulb']]) {
              const chip = tray.locator('.reaction-static').filter({ has: page.locator('.reaction-name').filter({ hasText: new RegExp(`^${name}$`) }) });
              const label = chip.locator('.reaction-name');
              await expect(label).toBeVisible();
              await expect(label).not.toHaveClass(/sr-only/);
              expect.soft((await label.boundingBox())!.width, `${viewer}/${width}px ${name}`).toBeGreaterThan(1);
              await expect(chip.locator(`.icon-${icon}`)).toHaveCount(1);
              await expect(chip.locator('.reaction-n-val')).toHaveText('1');
            }
            expect.soft(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth));
            if (javaScriptEnabled && width === (info.project.name === 'mobile' ? 390 : 1280)) {
              await screenshot(page, `reaction-counts-${viewer}-${info.project.name}`);
            }
          }
        } finally {
          restoreReactionFixture(fixture);
        }
      });
    }
  });
}
