import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * The inline reply dock's expand/minimize state model, and the single-surface
 * anatomy the shared shell presents once the rich editor stops drawing a second
 * card inside it (COMPOSER.md §13).
 *
 * The dock is deliberately two-way: an EMPTY composer folds back up when
 * interest leaves it, a composer holding a draft stays open until the member
 * minimizes it by hand, and minimizing never discards anything.
 */

const repoRoot = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.join(repoRoot, 'docs/evidence/browser');
const TEST_EMAIL = 'alice@retro.test';

function runPhp(code: string): string {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
${code}
`;
  return execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString();
}

function setWysiwygComposer(enabled: boolean): void {
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['rich_composer'] = true;
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}

function clearDrafts(): void {
  runPhp(`
$user = $db->fetch('SELECT id FROM users WHERE email = ?', ['${TEST_EMAIL}']);
if ($user) { $db->run('DELETE FROM server_drafts WHERE user_id = ?', [(int) $user['id']]); }
`);
}

function seededThreadPath(title: string): string {
  const title64 = Buffer.from(title, 'utf8').toString('base64');
  return runPhp(`
$row = $db->fetch('SELECT id, slug FROM threads WHERE title = ? ORDER BY id DESC LIMIT 1', [base64_decode('${title64}')]);
echo $row ? '/t/' . (int) $row['id'] . '-' . $row['slug'] : '';
`).trim();
}

test.beforeEach(() => {
  clearDrafts();
});

test.afterEach(() => {
  setWysiwygComposer(true);
  clearDrafts();
});

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', TEST_EMAIL);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  await dismissTour(page);
}

/** Local drafts are per-browser; a stale one would pre-fill the dock and defeat the empty-state cases. */
async function clearLocalDrafts(page: Page): Promise<void> {
  await page.evaluate(() => {
    for (let i = window.localStorage.length - 1; i >= 0; i--) {
      const key = window.localStorage.key(i);
      if (key && key.indexOf('rb-composer') === 0) { window.localStorage.removeItem(key); }
    }
  });
}

async function openReply(page: Page): Promise<Locator> {
  const threadPath = seededThreadPath('Share your favourite keyboard shortcuts');
  expect(threadPath).toMatch(/^\/t\/\d+-/);
  await page.goto(threadPath);
  await clearLocalDrafts(page);
  await page.reload();
  await dismissTour(page);
  const form = page.locator('form.reply-composer.composer-shell');
  await expect(form).toBeVisible();
  // The dock stamp is the composer script's own marker; the compact CSS keys on
  // it, so waiting for it is waiting for the enhancement to be live.
  await expect(form).toHaveAttribute('data-composer-dock', '1');
  return form;
}

/** The writing surface of whichever adapter is mounted. */
function bodyOf(form: Locator, rich: boolean): Locator {
  return rich ? form.locator('.wysiwyg-composer .ProseMirror') : form.locator('textarea.composer-input');
}

async function typeBody(form: Locator, rich: boolean, text: string): Promise<void> {
  const body = bodyOf(form, rich);
  await body.click();
  await body.type(text);
}

async function canonicalMarkdown(form: Locator): Promise<string> {
  return form.locator('textarea.composer-input').inputValue();
}

/**
 * Tab forward until focus leaves the composer, returning how many presses it
 * took. Returns 0 if focus never escapes — i.e. the shell traps it.
 */
async function tabUntilOutside(page: Page, form: Locator, limit = 20): Promise<number> {
  for (let press = 1; press <= limit; press++) {
    await page.keyboard.press('Tab');
    const inside = await form.evaluate((element) => element.contains(document.activeElement));
    if (!inside) {
      // The fold decision is deferred one tick past focusout.
      await page.waitForTimeout(50);
      return press;
    }
  }
  return 0;
}

/** A page region that is genuinely outside the composer and does not navigate. */
async function tapOutside(page: Page): Promise<void> {
  await page.locator('.post-body').first().click({ position: { x: 5, y: 5 } });
}

/**
 * The New Topic modal rises from opacity 0. Measuring colour or geometry while
 * that animation is in flight reads the modal composited over its own scrim, so
 * every anatomy and contrast assertion waits for it to settle first.
 */
async function settle(target: Locator): Promise<void> {
  await target.evaluate(async (element) => {
    await Promise.all(element.getAnimations({ subtree: true }).map((animation) => animation.finished.catch(() => {})));
  });
}

async function openNewTopic(page: Page): Promise<Locator> {
  await page.goto('/c/general');
  await dismissTour(page);
  await page.locator('[data-open-topic-composer]').click();
  const form = page.locator('form.composer-shell[data-composer-instance^="new-thread-board-"]').first();
  await expect(form).toBeVisible();
  await settle(form);
  return form;
}

async function expectNoSeriousA11yViolations(page: Page, include: string): Promise<void> {
  const result = await new AxeBuilder({ page })
    .include(include)
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = result.violations.filter((violation) =>
    violation.impact === 'serious' || violation.impact === 'critical',
  );
  expect(violations, `${include} serious/critical axe violations`).toEqual([]);
}

for (const mode of [
  { name: 'Milkdown', rich: true },
  { name: 'textarea', rich: false },
]) {
  test(`reply dock expands on tap and folds back when an empty composer is left (${mode.name})`, async ({ page }, info) => {
    test.skip(info.project.name !== 'mobile', 'the compact dock is a mobile contract');
    setWysiwygComposer(mode.rich);
    await login(page);
    const form = await openReply(page);

    // 1. Collapsed on arrival.
    await expect(form).not.toHaveClass(/\bis-expanded\b/);
    await expect(form.locator('.composer-format-slot')).toBeHidden();
    if (mode.rich) {
      await page.screenshot({
        path: path.join(EVIDENCE_DIR, info.project.name, '92-composer-reply-collapsed.png'),
        fullPage: false,
      });
    }

    // 2. Tapping the dock expands it and puts the caret in the active editor.
    await form.locator('.composer-box').click({ position: { x: 40, y: 20 } });
    await expect(form).toHaveClass(/\bis-expanded\b/);
    await expect(bodyOf(form, mode.rich)).toBeFocused();
    if (mode.rich) {
      await page.screenshot({
        path: path.join(EVIDENCE_DIR, info.project.name, '90-composer-reply-expanded-after.png'),
        fullPage: false,
      });
    }

    // 3. Tapping outside an empty dock folds it back up.
    await tapOutside(page);
    await expect(form).not.toHaveClass(/\bis-expanded\b/);
    if (mode.rich) {
      await form.scrollIntoViewIfNeeded();
      await page.screenshot({
        path: path.join(EVIDENCE_DIR, info.project.name, '93-composer-reply-folded-after-outside-tap.png'),
        fullPage: false,
      });
    }
  });

  test(`empty reply dock folds when focus tabs away, and keeps a draft open (${mode.name})`, async ({ page }, info) => {
    test.skip(info.project.name !== 'mobile', 'the compact dock is a mobile contract');
    setWysiwygComposer(mode.rich);
    await login(page);
    const form = await openReply(page);

    // 4. Tabbing out of an empty dock folds it without trapping focus. The
    // expanded shell has its own tab ring (format row, action bar), so walking
    // out takes several presses — the contract is that it terminates.
    await bodyOf(form, mode.rich).click();
    await expect(form).toHaveClass(/\bis-expanded\b/);
    const tabsToLeave = await tabUntilOutside(page, form);
    expect(tabsToLeave, 'focus must not be trapped inside the composer').toBeGreaterThan(0);
    await expect(form).not.toHaveClass(/\bis-expanded\b/);

    // 6. Typed text keeps the dock open when interest leaves it.
    await typeBody(form, mode.rich, 'a draft worth keeping');
    await tapOutside(page);
    await expect(form).toHaveClass(/\bis-expanded\b/);
    await bodyOf(form, mode.rich).click();
    expect(await tabUntilOutside(page, form)).toBeGreaterThan(0);
    await page.waitForTimeout(50);
    await expect(form).toHaveClass(/\bis-expanded\b/);
  });

  test(`composer-owned controls never fold the dock (${mode.name})`, async ({ page }, info) => {
    test.skip(info.project.name !== 'mobile', 'the compact dock is a mobile contract');
    setWysiwygComposer(mode.rich);
    await login(page);
    const form = await openReply(page);
    await bodyOf(form, mode.rich).click();
    await expect(form).toHaveClass(/\bis-expanded\b/);

    // 5. Every composer-owned control is an internal focus move, not a departure.
    const stops: Locator[] = [
      form.locator('.composer-toolbar [data-composer-action="bold"]'),
      form.getByRole('button', { name: 'Formatting', exact: true }),
      form.getByRole('button', { name: 'Attach images', exact: true }),
      form.getByRole('button', { name: 'Emoji', exact: true }),
      form.getByRole('button', { name: 'Preview', exact: true }),
    ];
    if (mode.rich) {
      stops.push(form.getByRole('button', { name: 'Source', exact: true }));
    }
    for (const stop of stops) {
      await stop.focus();
      // The fold decision is deferred a tick past focusout; give it the chance
      // to fire before asserting it did not.
      await page.waitForTimeout(50);
      await expect(form, `focusing ${await stop.getAttribute('aria-label') ?? 'a control'} folded the dock`)
        .toHaveClass(/\bis-expanded\b/);
    }

    // Opening the emoji dialog moves focus into a composer-owned popover.
    await form.getByRole('button', { name: 'Emoji', exact: true }).click();
    await expect(form.getByRole('dialog', { name: 'Emoji' })).toBeVisible();
    await page.waitForTimeout(50);
    await expect(form).toHaveClass(/\bis-expanded\b/);
    await page.keyboard.press('Escape');

    // The mention suggestion popover is the same story.
    await bodyOf(form, mode.rich).click();
    await bodyOf(form, mode.rich).type('@al');
    await page.waitForTimeout(400);
    await expect(form).toHaveClass(/\bis-expanded\b/);
  });

  test(`minimize folds a draft away without discarding it and reopening restores it (${mode.name})`, async ({ page }, info) => {
    test.skip(info.project.name !== 'mobile', 'the compact dock is a mobile contract');
    setWysiwygComposer(mode.rich);
    await login(page);
    const form = await openReply(page);

    const draft = 'Ctrl+K, then **jump to inbox**';
    await typeBody(form, mode.rich, draft);
    await expect(form).toHaveClass(/\bis-expanded\b/);
    await expect
      .poll(async () => canonicalMarkdown(form))
      .toContain('jump to inbox');
    const beforeMinimize = await canonicalMarkdown(form);

    // 7. The explicit control folds a NON-empty dock — minimizing is not
    // cancelling, so nothing may be cleared.
    const minimize = form.getByRole('button', { name: 'Minimize reply' });
    await expect(minimize).toBeVisible();
    await minimize.click();
    await expect(form).not.toHaveClass(/\bis-expanded\b/);
    expect(await canonicalMarkdown(form)).toBe(beforeMinimize);
    // The soft keyboard must not stay up over a dock that was just put away.
    expect(await form.evaluate((element) => element.contains(document.activeElement))).toBe(false);

    // 8. Reopening restores the draft, the editor mode, and the caret.
    await form.locator('.composer-box').click({ position: { x: 40, y: 20 } });
    await expect(form).toHaveClass(/\bis-expanded\b/);
    await expect(bodyOf(form, mode.rich)).toBeFocused();
    // toContainText reads a textarea's child text, not its value, so the draft
    // is checked through the canonical field either way.
    expect(await canonicalMarkdown(form)).toContain('jump to inbox');
    if (mode.rich) {
      await expect(form.locator('.wysiwyg-composer')).toBeVisible();
      await expect(form.locator('.wysiwyg-composer .ProseMirror')).toContainText('jump to inbox');
      await expect(form.getByRole('button', { name: 'Source', exact: true })).toBeVisible();
    }

    // …and it still submits.
    await bodyOf(form, mode.rich).click();
    await page.keyboard.press('End');
    await bodyOf(form, mode.rich).type(' — sending now');
    await form.locator('.composer-send').click();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.post-body').last()).toContainText('sending now');
  });
}

test('a restored browser-local reply draft opens instead of hiding in the collapsed dock', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'the compact dock is a mobile contract');
  setWysiwygComposer(false);
  await login(page);
  const form = await openReply(page);
  const draft = 'A browser-local draft must stay visible after reload.';

  await typeBody(form, false, draft);
  await expect.poll(async () => canonicalMarkdown(form)).toBe(draft);
  await expect.poll(async () => page.evaluate((expected) => {
    for (let index = 0; index < window.localStorage.length; index++) {
      const key = window.localStorage.key(index);
      if (key && window.localStorage.getItem(key) === expected) { return true; }
    }
    return false;
  }, draft)).toBe(true);

  await page.reload();
  await dismissTour(page);
  const restored = page.locator('form.reply-composer.composer-shell');
  await expect(restored).toHaveAttribute('data-composer-dock', '1');
  await expect.poll(async () => canonicalMarkdown(restored)).toBe(draft);
  await expect(restored).toHaveClass(/\bis-expanded\b/);
});

test('the reply composer is one framed surface with no nested editor card', async ({ page }, info) => {
  setWysiwygComposer(true);
  await login(page);
  const form = await openReply(page);
  if (info.project.name === 'mobile') {
    await form.locator('.composer-box').click({ position: { x: 40, y: 20 } });
    await expect(form).toHaveClass(/\bis-expanded\b/);
  }
  await expect(form.locator('.wysiwyg-composer')).toBeVisible();

  const framing = await form.evaluate((element) => {
    const read = (selector: string) => {
      const node = element.querySelector(selector) as HTMLElement | null;
      if (!node) { return null; }
      const style = getComputedStyle(node);
      return {
        borderWidth: style.borderTopWidth,
        radius: style.borderTopLeftRadius,
        background: style.backgroundColor,
      };
    };
    return { box: read('.composer-box'), editor: read('.wysiwyg-composer') };
  });
  expect(framing.box!.borderWidth).not.toBe('0px');
  // The editor is an interior region: no border, no radius, no ground of its own.
  expect(framing.editor).toEqual({
    borderWidth: '0px',
    radius: '0px',
    background: 'rgba(0, 0, 0, 0)',
  });

  // One focus-within treatment, owned by the box: the writing surfaces inside
  // it must not stack a second ring of their own.
  await form.locator('.wysiwyg-composer .ProseMirror').click();
  const rings = await form.evaluate((element) => {
    const ring = (selector: string) => {
      const node = element.querySelector(selector) as HTMLElement | null;
      if (!node) { return null; }
      const style = getComputedStyle(node);
      return { boxShadow: style.boxShadow, outlineStyle: style.outlineStyle };
    };
    return {
      box: ring('.composer-box'),
      editor: ring('.wysiwyg-composer'),
      surface: ring('.wysiwyg-composer .ProseMirror'),
      textarea: ring('textarea.composer-input'),
    };
  });
  expect(rings.box!.boxShadow, 'the box paints the focus ring').not.toBe('none');
  for (const [name, ringed] of Object.entries(rings)) {
    if (name === 'box' || !ringed) { continue; }
    expect(ringed, `${name} must not stack a second focus ring`).toEqual({ boxShadow: 'none', outlineStyle: 'none' });
  }

  // The Source control is no longer a standalone shelf between body and actions.
  const source = form.getByRole('button', { name: 'Source', exact: true });
  await expect(source).toBeVisible();
  expect(await source.evaluate((node) => !!node.closest('.composer-format-slot'))).toBe(true);
  expect(await source.evaluate((node) => node.className)).not.toContain('btn-secondary');

  const minimize = form.getByRole('button', { name: 'Minimize reply' });
  if (info.project.name === 'mobile') {
    await expect(minimize).toBeVisible();
  } else {
    await expect(minimize).toBeHidden();
  }
});

test('an expanded empty reply leaves most of the mobile viewport to the conversation', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile height budget');
  setWysiwygComposer(true);
  await login(page);
  const form = await openReply(page);
  await form.locator('.composer-box').click({ position: { x: 40, y: 20 } });
  await expect(form).toHaveClass(/\bis-expanded\b/);

  const box = (await form.locator('.composer-box').boundingBox())!;
  const viewport = page.viewportSize()!;
  expect(box.height / viewport.height).toBeLessThan(0.3);
});

test('new topic renders as one composer surface with the title as its header field', async ({ page }, info) => {
  setWysiwygComposer(true);
  await login(page);
  const form = await openNewTopic(page);

  // 10. The title is inside the one framed surface, not a detached input above it.
  const title = form.locator('input[name="title"]');
  expect(await title.evaluate((node) => !!node.closest('.composer-box'))).toBe(true);
  const geometry = await form.evaluate((element) => {
    const rect = (selector: string) => {
      const node = element.querySelector(selector) as HTMLElement | null;
      return node ? node.getBoundingClientRect().left : null;
    };
    const titleNode = element.querySelector('input[name="title"]') as HTMLElement;
    const titleStyle = getComputedStyle(titleNode);
    return {
      titleLeft: rect('input[name="title"]'),
      bodyLeft: rect('.wysiwyg-composer .ProseMirror'),
      titlePadding: titleStyle.paddingLeft,
      titleBorder: titleStyle.borderTopWidth,
      bodyPadding: getComputedStyle(element.querySelector('.wysiwyg-composer .ProseMirror') as HTMLElement).paddingLeft,
    };
  });
  // Title and body share one horizontal inset, and the title has surrendered
  // its own border so the box paints the only outline.
  expect(geometry.titleLeft).toBeCloseTo(geometry.bodyLeft!, 0);
  expect(geometry.titlePadding).toBe(geometry.bodyPadding);
  expect(geometry.titleBorder).toBe('0px');

  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, 'composer-new-topic-surface.png'),
    fullPage: false,
  });
  if (info.project.name === 'mobile') {
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, '91-composer-new-topic-after.png'),
      fullPage: false,
    });
  }

  // 11. Title and body remain editable and submittable.
  const unique = `Composer surface ${Date.now()}`;
  await title.fill(unique);
  const body = form.locator('.wysiwyg-composer .ProseMirror');
  await body.click();
  await body.type('One coherent composer surface.');
  await form.locator('.composer-send').click();
  await page.waitForLoadState('networkidle');
  await expect(page.locator('h1')).toContainText(unique);
  await expect(page.locator('.post-op .post-body')).toContainText('One coherent composer surface');
});

test('the editor mode control stays keyboard and screen-reader operable', async ({ page }) => {
  setWysiwygComposer(true);
  await login(page);
  const form = await openNewTopic(page);

  // 12. It is a real button with an accessible name that reports the mode it
  // switches to, reachable and operable from the keyboard alone.
  const source = form.getByRole('button', { name: 'Source', exact: true });
  await expect(source).toBeVisible();
  await source.focus();
  await expect(source).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(form.locator('textarea.composer-input')).toBeVisible();
  const rich = form.getByRole('button', { name: 'Rich text', exact: true });
  await expect(rich).toBeVisible();
  await rich.focus();
  await page.keyboard.press(' ');
  await expect(form.getByRole('button', { name: 'Source', exact: true })).toBeVisible();
  await expect(form.locator('.wysiwyg-composer')).toBeVisible();
});

test('DM and edit mounts keep working through the shared-shell change', async ({ page }) => {
  // 13. Both reuse the shell but never the reply dock: they must stay fully
  // expanded, keep their own wrapper fields, and carry no minimize control.
  setWysiwygComposer(true);
  await login(page);

  await page.goto('/messages/new?to=bob');
  await dismissTour(page);
  const dm = page.locator('form.composer-shell[data-composer-context="dm"]').first();
  await expect(dm).toBeVisible();
  await expect(dm).not.toHaveAttribute('data-composer-dock', '1');
  await expect(dm.getByRole('button', { name: 'Minimize reply' })).toHaveCount(0);
  const recipient = dm.locator('input[name="to"]');
  await expect(recipient).toBeVisible();
  await expect(recipient).toHaveValue('bob');
  const dmBody = dm.locator('.wysiwyg-composer .ProseMirror');
  await dmBody.click();
  await dmBody.type('Shared shell, still sending.');
  await expect
    .poll(async () => dm.locator('textarea.composer-input').inputValue())
    .toContain('still sending');

  const threadPath = seededThreadPath('Share your favourite keyboard shortcuts');
  await page.goto(threadPath);
  await dismissTour(page);
  // Alice owns the opening post. With JS the native <summary> is replaced by a
  // promoted opener inside the post menu, so the menu opens first.
  const ownPost = page.locator('.post-op').first();
  await ownPost.hover();   // the post menu reveals on hover at desktop widths
  await ownPost.locator('[data-post-menu] > summary').click();
  await ownPost.locator('[data-post-disclosure-open^="post-edit-"]').click();
  const edit = page.locator('form.composer-shell[data-composer-context="edit"]').first();
  await expect(edit).toBeVisible();
  await expect(edit).not.toHaveAttribute('data-composer-dock', '1');
  await expect(edit.getByRole('button', { name: 'Minimize reply' })).toHaveCount(0);
  // The edit mount is remounted over an existing body, which must survive.
  expect(await edit.locator('textarea.composer-input').inputValue()).not.toBe('');
  await expect(edit.getByRole('button', { name: 'Source', exact: true })).toBeVisible();
});

test('the reworked composer surfaces introduce no serious axe violations', async ({ page }, info) => {
  // 14/15. Both viewports, both composer surfaces, collapsed and expanded.
  setWysiwygComposer(true);
  await login(page);
  const form = await openReply(page);
  const instance = await form.getAttribute('data-composer-instance');
  await expectNoSeriousA11yViolations(page, `[data-composer-instance="${instance}"]`);

  if (info.project.name === 'mobile') {
    await form.locator('.composer-box').click({ position: { x: 40, y: 20 } });
    await expect(form).toHaveClass(/\bis-expanded\b/);
    await expectNoSeriousA11yViolations(page, `[data-composer-instance="${instance}"]`);
    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, 'composer-reply-expanded.png'),
      fullPage: false,
    });

    // A mobile-Safari-like narrow viewport must not spill the shell sideways.
    await page.setViewportSize({ width: 375, height: 667 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
    await page.setViewportSize({ width: 320, height: 568 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  }

  const newTopic = await openNewTopic(page);
  await expectNoSeriousA11yViolations(page, `[data-composer-instance="${await newTopic.getAttribute('data-composer-instance')}"]`);
});
