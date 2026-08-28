import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const EVIDENCE_DIR = path.join(repoRoot, 'docs/evidence/browser');

function setWysiwygComposer(enabled: boolean | null): boolean | null {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$previous = array_key_exists('wysiwyg_composer', $features) ? (bool) $features['wysiwyg_composer'] : null;
${enabled === null ? "unset($features['wysiwyg_composer']);" : `$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};`}
$settings->set('features', $features);
echo json_encode($previous);
`;
  const previous = execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
  return JSON.parse(previous) as boolean | null;
}

function purgeEvidenceRepliesWithText(text: string): number {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$body = (string) ($argv[1] ?? '');
$rows = $db->fetchAll(
    'SELECT p.id FROM posts p JOIN users u ON u.id = p.user_id WHERE p.is_op = 0 AND p.body = ? AND u.email = ?',
    [$body, 'alice@retro.test'],
);
$db->transaction(function () use ($db, $rows): void {
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $db->run('DELETE FROM notifications WHERE post_id = ?', [$id]);
        $db->run('DELETE FROM email_deliveries WHERE idempotency_key LIKE ?', [(string) $id . ':%']);
        $db->run("DELETE FROM submission_idempotency WHERE result_type = 'post' AND result_id = ?", [$id]);
        $db->run("DELETE FROM content_references WHERE source_type = 'post' AND source_id = ?", [$id]);
        $db->run("DELETE FROM link_previews WHERE source_type = 'post' AND source_id = ?", [$id]);
        $db->run('DELETE FROM attachments WHERE post_id = ?', [$id]);
        $db->run('UPDATE posts SET parent_post_id = NULL WHERE parent_post_id = ?', [$id]);
        $db->run('UPDATE threads SET accepted_answer_post_id = NULL WHERE accepted_answer_post_id = ?', [$id]);
        $db->run('UPDATE thread_user SET last_read_post_id = NULL WHERE last_read_post_id = ?', [$id]);
        $db->run('DELETE FROM posts WHERE id = ?', [$id]);
    }
});
echo (string) count($rows);
`;
  const value = execFileSync('php', ['-r', php, text], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
  const count = Number(value);
  if (!Number.isInteger(count) || count < 0) {
    throw new Error(`Unexpected evidence-reply purge result: ${JSON.stringify(value)}`);
  }
  return count;
}

type ShotName =
  | '80-thread-study'
  | '81-thread-tools'
  | '89-thread-standing-chips'
  | '90-thread-star-pill'
  | '91-thread-reaction-chip';

async function shot(page: Page, info: TestInfo, name: ShotName): Promise<void> {
  await expect(page.locator('.error-card')).toHaveCount(0);
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
  });
}

async function login(page: Page, email = 'alice@retro.test'): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await page.locator('body[data-tour="1"]').count()) {
    await expect(skip).toBeVisible();
    await skip.click();
  }
  await expect(page.locator('.tour-popover')).toHaveCount(0);
}

async function openSeedTopic(page: Page): Promise<void> {
  await page.goto('/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await expect(page.locator('[data-thread-study]')).toBeVisible();
}

/**
 * Quote a post the way the viewer's pointer type actually presents the control.
 * A fine pointer gets the reference's floating toolbar pill; a coarse pointer
 * collapses that toolbar to its overflow disclosure (there is no hover to reveal
 * it, and four in-flow 44px targets cost a whole band under every post), so
 * quote lives in the menu there. Same user-facing outcome either way.
 */
async function quotePost(post: Locator): Promise<void> {
  await post.hover();
  const inToolbar = post.locator('[data-post-toolbar] > [data-quote-post]');
  if (await inToolbar.isVisible()) {
    await inToolbar.click();
    return;
  }
  await post.locator('[data-post-menu] > summary').click();
  await post.locator('.post-menu-touch [data-quote-post]').click();
}

async function openManagement(page: Page): Promise<void> {
  const management = page.locator('[data-topic-tools-section="management"]');
  if (!(await management.evaluate((element) => (element as HTMLDetailsElement).open))) {
    await management.locator(':scope > summary').click();
  }
}

async function tabTo(page: Page, target: Locator, limit = 160): Promise<number> {
  await expect(target).toHaveCount(1);
  for (let presses = 0; presses <= limit; presses += 1) {
    if (await target.evaluate((element) => document.activeElement === element)) {
      return presses;
    }
    await page.keyboard.press('Tab');
  }
  throw new Error(`Tab order did not reach ${await target.evaluate((element) => element.outerHTML.slice(0, 160))}`);
}

async function setReactionState(post: Locator, emoji: string, enabled: boolean): Promise<void> {
  const active = post.locator('.reactions .reaction-on').filter({ hasText: emoji }).first();
  const isEnabled = await active.count() > 0;
  if (isEnabled === enabled) return;
  if (isEnabled) {
    await active.click();
    return;
  }

  await post.hover();
  const picker = post.locator('[data-post-toolbar] .reaction-add');
  if (!(await picker.evaluate((element) => (element as HTMLDetailsElement).open))) {
    await picker.locator(':scope > summary').click();
  }
  const forms = picker.locator('form');
  for (let index = 0; index < await forms.count(); index += 1) {
    const form = forms.nth(index);
    if (await form.locator('input[name="emoji"]').inputValue() === emoji) {
      await form.locator('button.reaction').click();
      return;
    }
  }
  throw new Error(`Reaction picker did not offer ${JSON.stringify(emoji)}`);
}

async function deleteOwnedRepliesWithText(page: Page, text: string): Promise<void> {
  for (let attempts = 0; attempts < 20; attempts += 1) {
    const matches = page.locator('article[data-post]').filter({
      has: page.locator('.post-body').filter({ hasText: text }),
    });
    if (await matches.count() === 0) {
      purgeEvidenceRepliesWithText(text);
      return;
    }

    const post = matches.first();
    const menu = post.locator('[data-post-menu]');
    if (!(await menu.evaluate((element) => (element as HTMLDetailsElement).open))) {
      await menu.locator(':scope > summary').click();
    }
    await menu.locator('form[action$="/delete"] button').click();
    await expect(page.locator('[data-thread-study]')).toBeVisible();
  }
  throw new Error(`Could not clean every browser-evidence reply matching ${JSON.stringify(text)}`);
}

test('desktop Topic tools accords, traps focus, and restores each opener', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop drawer contract');
  await login(page);
  await openSeedTopic(page);

  const trigger = page.getByRole('button', { name: /^Topic tools/ });
  const tools = page.locator('[data-topic-tools]');
  const closeTools = page.getByRole('button', { name: 'Close Topic tools' });

  await trigger.click();
  await expect(tools).toBeVisible();
  await expect(tools).toHaveAttribute('aria-modal', 'true');
  await expect(closeTools).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  expect(await tools.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Tab');
  await expect(closeTools).toBeFocused();

  await tools.locator('[data-topic-tools-section="standing"] > summary').click();
  await expect(tools.locator('[data-topic-tools-section="standing"]')).toHaveAttribute('open', '');
  await expect(tools.locator('[data-topic-tools-section="watch"]')).not.toHaveAttribute('open', '');
  await closeTools.click();
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();

  await trigger.evaluate((element) => element.setAttribute('data-topic-tools-open', 'standing'));
  await trigger.click();
  await expect(tools.locator('[data-topic-tools-section="standing"]')).toHaveAttribute('open', '');
  await expect(tools.locator('[data-topic-tools-section="watch"]')).not.toHaveAttribute('open', '');
  await page.locator('[data-topic-tools-scrim]').click({ position: { x: 5, y: 5 } });
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();

  await trigger.click();
  await page.keyboard.press('Escape');
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();
});

test('split or merge closes by every dismissal path and restores focus', async ({ page }, info) => {
  await login(page);
  await openSeedTopic(page);

  const topicTrigger = page.getByRole('button', { name: /^Topic tools/ });
  const dialog = page.locator('.thread-restructure-dialog');
  const closeRestructure = dialog.getByRole('button', { name: 'Close split or merge' });

  const openRestructure = async () => {
    await topicTrigger.click();
    await openManagement(page);
    await page.locator('[data-topic-tools-section="management"]').getByRole('button', { name: 'Split or merge' }).click();
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAttribute('aria-modal', 'true');
    await expect(closeRestructure).toBeFocused();
  };

  await openRestructure();
  await page.keyboard.press('Shift+Tab');
  expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Tab');
  await expect(closeRestructure).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(topicTrigger).toBeFocused();

  await openRestructure();
  await closeRestructure.click();
  await expect(dialog).toBeHidden();
  await expect(topicTrigger).toBeFocused();

  if (info.project.name === 'desktop') {
    await openRestructure();
    await page.locator('[data-thread-restructure-scrim]').click({ position: { x: 5, y: 5 } });
    await expect(dialog).toBeHidden();
    await expect(topicTrigger).toBeFocused();
  }
});

test('post menus are exclusive, dismiss outside, and open real disclosures safely', async ({ page }, info) => {
  await login(page);
  await openSeedTopic(page);

  const posts = page.locator('article[data-post]');
  const firstMenu = posts.nth(0).locator('[data-post-menu]');
  const secondMenu = posts.nth(1).locator('[data-post-menu]');
  expect(await posts.count()).toBeGreaterThanOrEqual(2);

  await posts.nth(0).hover();
  await firstMenu.locator(':scope > summary').click();
  await expect(firstMenu).toHaveAttribute('open', '');
  await posts.nth(1).hover();
  await secondMenu.locator(':scope > summary').click();
  await expect(firstMenu).not.toHaveAttribute('open', '');
  await expect(secondMenu).toHaveAttribute('open', '');
  await page.keyboard.press('Escape');
  await expect(secondMenu).not.toHaveAttribute('open', '');
  await expect(secondMenu.locator(':scope > summary')).toBeFocused();
  await secondMenu.locator(':scope > summary').click();
  await page.locator('.thread-study-title').click();
  await expect(secondMenu).not.toHaveAttribute('open', '');

  await posts.nth(0).hover();
  await firstMenu.locator(':scope > summary').click();
  await firstMenu.getByRole('button', { name: 'Edit' }).click();
  const editDisclosure = posts.nth(0).locator('.post-native-disclosure.post-edit');
  await expect(firstMenu).not.toHaveAttribute('open', '');
  await expect(editDisclosure).toHaveAttribute('open', '');
  expect(await editDisclosure.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Escape');
  await expect(editDisclosure).not.toHaveAttribute('open', '');
  await expect(firstMenu.locator(':scope > summary')).toBeFocused();

  await posts.nth(1).hover();
  await secondMenu.locator(':scope > summary').click();
  await secondMenu.getByRole('button', { name: 'Report' }).click();
  const reportDisclosure = posts.nth(1).locator('.post-native-disclosure[id^="post-report-"]');
  await expect(reportDisclosure).toHaveAttribute('open', '');
  const reportClose = reportDisclosure.getByRole('button', { name: 'Close report form' });
  if (info.project.name === 'mobile') {
    const closeBox = await reportClose.boundingBox();
    expect(closeBox).not.toBeNull();
    expect(closeBox!.width).toBeGreaterThanOrEqual(44);
    expect(closeBox!.height).toBeGreaterThanOrEqual(44);
  }
  await reportClose.click();
  await expect(reportDisclosure).not.toHaveAttribute('open', '');
  await expect(secondMenu.locator(':scope > summary')).toBeFocused();

  await page.context().clearCookies();
  await login(page, 'admin@retro.test');
  await openSeedTopic(page);
  const adminPost = page.locator('article[data-post]').first();
  const adminMenu = adminPost.locator('[data-post-menu]');
  await adminPost.hover();
  await adminMenu.locator(':scope > summary').click();
  await adminMenu.getByRole('button', { name: /Remove .*warden/ }).click();
  const removeDisclosure = adminPost.locator('.post-native-disclosure[id^="post-remove-"]');
  await expect(removeDisclosure).toHaveAttribute('open', '');
  await removeDisclosure.getByRole('button', { name: 'Close remove form' }).click();
  await expect(removeDisclosure).not.toHaveAttribute('open', '');
  await expect(adminMenu.locator(':scope > summary')).toBeFocused();
});

test('copy link keeps anchor navigation when Clipboard is absent or rejects', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'fallback contract only needs one browser project');
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
  });
  await login(page);
  await openSeedTopic(page);

  const post = page.locator('article[data-post]').nth(1);
  await post.hover();
  await post.locator('[data-post-menu] > summary').click();
  const copy = post.getByRole('link', { name: 'Copy link' });
  const href = await copy.getAttribute('href');
  expect(href).toMatch(/#p\d+$/);
  await copy.click();
  await expect(page).toHaveURL(new RegExp(`${href!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));

  await page.evaluate(() => {
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: () => Promise.reject(new Error('clipboard denied for fallback evidence')) },
    });
  });
  const firstPost = page.locator('article[data-post].post-op');
  await firstPost.hover();
  await firstPost.locator('[data-post-menu] > summary').click();
  const rejectedCopy = firstPost.getByRole('link', { name: 'Copy link' });
  const rejectedHref = await rejectedCopy.getAttribute('href');
  expect(rejectedHref).toMatch(/#p\d+$/);
  await rejectedCopy.click();
  await expect(page).toHaveURL(new RegExp(`${rejectedHref!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));
});

test('quote controls stay hidden when the rendered topic has no reply composer target', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'negative enhancement contract only needs one browser project');
  await login(page);
  await page.route('**/t/*', async (route) => {
    const response = await route.fetch();
    const body = (await response.text()).replace(' id="reply" ', ' ');
    await route.fulfill({ response, body });
  });
  await openSeedTopic(page);

  await expect(page.locator('#reply textarea[name="body"]')).toHaveCount(0);
  const quoteButtons = page.locator('[data-thread-study] [data-quote-post]');
  expect(await quoteButtons.count()).toBeGreaterThan(0);
  await expect(quoteButtons.first()).toBeHidden();
});

test('quote inserts through the active WYSIWYG adapter and survives submit synchronization', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'one project proves the default WYSIWYG adapter contract');
  const previous = setWysiwygComposer(true);
  try {
    await login(page);
    await openSeedTopic(page);

    const form = page.locator('#reply');
    const editor = form.locator('.wysiwyg-composer .ProseMirror');
    await expect(editor).toBeVisible();
    await editor.fill('My response');

    const post = page.locator('article[data-post]').nth(1);
    await post.hover();
    await post.getByRole('button', { name: 'Quote in your reply' }).click();

    await expect.poll(async () => form.evaluate((element) => {
      const adapter = (element as HTMLFormElement & { _rbComposerAdapter?: { getMarkdown?: () => string } })._rbComposerAdapter;
      return adapter?.getMarkdown?.() ?? '';
    })).toMatch(/^My response\n+> [^\n]+$/);

    const adapterMarkdown = await form.evaluate((element) => {
      const adapter = (element as HTMLFormElement & { _rbComposerAdapter?: { getMarkdown?: () => string } })._rbComposerAdapter;
      return adapter?.getMarkdown?.() ?? '';
    });

    const submittedMarkdown = await form.evaluate((element) => {
      const composer = element as HTMLFormElement;
      composer.addEventListener('submit', (event) => event.preventDefault(), { once: true });
      composer.requestSubmit();
      return (composer.querySelector('textarea[name="body"]') as HTMLTextAreaElement).value;
    });
    expect(submittedMarkdown).toBe(adapterMarkdown);
  } finally {
    setWysiwygComposer(previous);
  }
});

test('quote inserts exactly once through the source adapter', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'one project proves the source adapter contract');
  const previous = setWysiwygComposer(true);
  try {
    await login(page);
    await openSeedTopic(page);

    const form = page.locator('#reply');
    await form.getByRole('button', { name: 'Source' }).click();
    const reply = form.locator('textarea[name="body"]');
    await expect(reply).toBeVisible();
    await reply.fill('');

    await quotePost(page.locator('article[data-post]').nth(1));
    const quotedValue = await reply.inputValue();
    expect(quotedValue.match(/^> /gm) ?? []).toHaveLength(1);
    expect(quotedValue).toMatch(/^> [^\n]+\n\n$/);
    await expect(reply).toBeFocused();
  } finally {
    setWysiwygComposer(previous);
  }
});

test('Study layout matches desktop and mobile geometry', async ({ page }, info) => {
  if (info.project.name === 'desktop') {
    await page.setViewportSize({ width: 1280, height: 1200 });
  }
  await login(page);
  await openSeedTopic(page);

  const thread = page.locator('[data-thread-study]');
  // ADR 0030 #2: the column IS the measure the design declares once, 646px, and
  // the scroll gutter hangs outside it. It was 860px with the prose capped
  // separately at 70ch inside, so the byline ran 200px past its own sentence.
  await expect(thread).toHaveCSS('width', info.project.name === 'desktop' ? '646px' : '362px');
  const box = await thread.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.width).toBeLessThanOrEqual(646);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  await expect(thread.locator('.thread-facts')).toHaveCSS('flex-wrap', info.project.name === 'desktop' ? 'nowrap' : 'wrap');
  await expect(thread.locator('.thread-facts-identity')).toHaveCSS('flex-wrap', info.project.name === 'desktop' ? 'nowrap' : 'wrap');
  await expect(thread.locator('#reply textarea.composer-input')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(thread.locator('#reply .composer-toolbar')).toHaveCount(1);
  await expect(thread.locator('#reply .composer-attach-toggle')).toHaveCount(1);
  await shot(page, info, '80-thread-study');

  if (info.project.name === 'desktop') {
    await page.setViewportSize({ width: 1280, height: 800 });
  }

  await page.getByRole('button', { name: /^Topic tools/ }).click();
  const tools = page.locator('[data-topic-tools]');
  await tools.evaluate(async (element) => {
    await Promise.all(element.getAnimations().map((animation) => animation.finished));
  });
  const toolsBox = await tools.boundingBox();
  expect(toolsBox).not.toBeNull();
  const closeStyles = await page.getByRole('button', { name: 'Close Topic tools' }).evaluate((element) => {
    const style = getComputedStyle(element);
    const box = element.getBoundingClientRect();
    return { width: box.width, height: box.height, borderWidth: style.borderTopWidth, borderRadius: style.borderRadius, background: style.backgroundColor };
  });
  const starColors = await page.locator('.topic-tools-open .icon-eight-point-star, .topic-tools-head .icon-eight-point-star').evaluateAll((icons) => {
    const probe = document.createElement('span');
    probe.style.color = 'var(--gold-600)';
    document.body.appendChild(probe);
    const expected = getComputedStyle(probe).color;
    probe.remove();
    return { expected, actual: icons.map((icon) => getComputedStyle(icon).color) };
  });
  expect(starColors.actual.every((color) => color === starColors.expected)).toBe(true);
  if (info.project.name === 'desktop') {
    expect(toolsBox!.width).toBeLessThanOrEqual(392);
    expect(closeStyles.width).toBe(28);
    expect(closeStyles.height).toBe(28);
    expect(closeStyles.borderWidth).toBe('0px');
    expect(closeStyles.borderRadius).toBe('999px');
    expect(closeStyles.background).toBe('rgba(0, 0, 0, 0)');
    const layoutViewportRight = await page.evaluate(() => document.body.getBoundingClientRect().right);
    expect(
      Math.abs((toolsBox!.x + toolsBox!.width) - layoutViewportRight),
    ).toBeLessThanOrEqual(2);
  } else {
    expect(toolsBox!.width).toBeCloseTo(390, 0);
    expect(toolsBox!.height).toBeLessThanOrEqual(844 * 0.86 + 1);
    const actionBoxes = await page.locator('[data-post-toolbar] button:visible, [data-post-toolbar] summary:visible').evaluateAll((items) => items.map((item) => {
      const itemBox = item.getBoundingClientRect();
      return { width: itemBox.width, height: itemBox.height };
    }));
    expect(actionBoxes.length).toBeGreaterThan(0);
    expect(actionBoxes.every((item) => item.width >= 44 && item.height >= 44)).toBe(true);
  }
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  await shot(page, info, '81-thread-tools');

  await openManagement(page);
  await page.locator('[data-topic-tools-section="management"]').getByRole('button', { name: 'Split or merge' }).click();
  const dialog = page.locator('.thread-restructure-dialog');
  const dialogBox = await dialog.boundingBox();
  expect(dialogBox).not.toBeNull();
  const restructureChrome = await dialog.evaluate((element) => {
    const header = element.querySelector(':scope > header');
    const close = element.querySelector('[data-thread-restructure-close]');
    const style = getComputedStyle(close!);
    const box = close!.getBoundingClientRect();
    return {
      headerDisplay: getComputedStyle(header!).display,
      width: box.width,
      height: box.height,
      borderWidth: style.borderTopWidth,
      borderRadius: style.borderRadius,
      background: style.backgroundColor,
    };
  });
  expect(restructureChrome.headerDisplay).toBe('flex');
  expect(restructureChrome.borderWidth).toBe('0px');
  expect(restructureChrome.borderRadius).toBe('999px');
  expect(restructureChrome.background).toBe('rgba(0, 0, 0, 0)');
  if (info.project.name === 'desktop') {
    expect(dialogBox!.width).toBeLessThanOrEqual(600);
    expect(restructureChrome.width).toBe(28);
    expect(restructureChrome.height).toBe(28);
  } else {
    expect(dialogBox!.x).toBeLessThanOrEqual(1);
    expect(dialogBox!.y).toBeLessThanOrEqual(1);
    expect(dialogBox!.width).toBeCloseTo(390, 0);
    expect(dialogBox!.height).toBeCloseTo(844, 0);
    expect(restructureChrome.width).toBe(44);
    expect(restructureChrome.height).toBe(44);
  }
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('coarse pointers keep post actions visible and reachable above the mobile breakpoint', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile');
  await page.setViewportSize({ width: 820, height: 1000 });
  await login(page);
  await openSeedTopic(page);

  expect(page.viewportSize()!.width).toBeGreaterThan(768);
  expect(await page.evaluate(() => matchMedia('(hover: none), (pointer: coarse)').matches)).toBe(true);
  const post = page.locator('article[data-post]').first();
  const toolbar = post.locator('[data-post-toolbar]');
  await expect(toolbar).toBeVisible();
  await expect(toolbar).toHaveCSS('opacity', '1');
  const targetBoxes = await toolbar.locator('button:visible, summary:visible').evaluateAll((items) => items.map((item) => {
    const box = item.getBoundingClientRect();
    return { width: box.width, height: box.height };
  }));
  expect(targetBoxes.length).toBeGreaterThan(0);
  expect(targetBoxes.every((item) => item.width >= 44 && item.height >= 44)).toBe(true);

  // The toolbar collapses to exactly one control here, and it is out of flow —
  // the reference stream's actions cost zero layout height, and an in-flow row
  // of 44px targets was adding ~52px under every post.
  expect(targetBoxes).toHaveLength(1);
  await expect(toolbar).toHaveCSS('position', 'absolute');
  // Out of flow is only safe if it does not land on the prose. The byline row
  // reserves the control's own height and trailing edge for exactly this.
  const overlap = await post.evaluate((element) => {
    const bar = element.querySelector('[data-post-toolbar]')!.getBoundingClientRect();
    const body = element.querySelector('.post-body')!.getBoundingClientRect();
    const head = element.querySelector('.post-head')!.getBoundingClientRect();
    return {
      body: bar.bottom > body.top && bar.top < body.bottom && bar.right > body.left && bar.left < body.right,
      withinHead: bar.top >= head.top - 1 && bar.bottom <= head.bottom + 1,
    };
  });
  expect(overlap.body).toBe(false);
  expect(overlap.withinHead).toBe(true);

  // What left the toolbar has to still be reachable, so the menu carries it.
  await toolbar.locator('[data-post-menu] > summary').click();
  const touch = post.locator('.post-menu-touch');
  await expect(touch).toBeVisible();
  await expect(touch.locator('[data-quote-post]')).toBeVisible();
  await expect(touch.locator('.post-menu-reactions .reaction').first()).toBeVisible();
});

/**
 * The overflow menu is a column of rows, not a bag of default-chrome buttons.
 *
 * Regression guard: `.post-menu-pop` shipped with the popover box styled and no
 * rule at all for what sits inside it. The reference carries each row on the
 * element (ThreadView.dc.html:436), so the port had nothing to copy and the
 * items fell back to user-agent chrome — the <a> ran `inline` and the <button>
 * `inline-block`, putting "Copy link" and "Edit" on one line, while the <form>
 * wrapping every POST action broke the next row onto its own, all of it in 2px
 * outset borders and 13px Arial.
 */
test('post menu rows stack full-width with no user-agent chrome', async ({ page }) => {
  await login(page);
  await openSeedTopic(page);

  const post = page.locator('[data-post]:has([data-post-menu] > summary)').first();
  const menuTrigger = post.locator('[data-post-menu] > summary');
  // Fine-pointer post actions are deliberately revealed by hovering the post.
  // Establish that interaction state before asking Playwright to click the
  // otherwise pointer-events:none trigger.
  await post.hover();
  await expect(menuTrigger).toHaveCSS('pointer-events', 'auto');
  await menuTrigger.click();
  const pop = post.locator('.post-menu-pop');
  await expect(pop).toBeVisible();

  const menu = await pop.evaluate((element) => {
    const popStyle = getComputedStyle(element);
    // clientWidth, not the border box: a row's `width: 100%` resolves against
    // the padding box, and the popover carries a 1px border on each side.
    const inner = element.clientWidth
      - parseFloat(popStyle.paddingLeft)
      - parseFloat(popStyle.paddingRight);
    // The touch duplicates are display:none on a mouse; a child of a hidden
    // parent still reports its own cascaded `display`, so filter on real boxes.
    const rows = [...element.querySelectorAll<HTMLElement>(
      ':scope > a, :scope > button, :scope > form > button,'
      + ' :scope > .post-menu-touch > button, :scope > .post-menu-touch > form > button',
    )].filter((row) => row.getClientRects().length > 0);
    return {
      inner,
      direction: popStyle.flexDirection,
      rows: rows.map((row) => {
        const style = getComputedStyle(row);
        return {
          label: (row.textContent ?? '').trim(),
          top: Math.round(row.getBoundingClientRect().top),
          width: Math.round(row.getBoundingClientRect().width),
          height: row.getBoundingClientRect().height,
          borderTop: parseFloat(style.borderTopWidth),
          fontFamily: style.fontFamily,
        };
      }),
    };
  });

  expect(menu.direction).toBe('column');
  expect(menu.rows.length).toBeGreaterThan(1);
  // One row per line: no two share a top edge.
  expect(new Set(menu.rows.map((row) => row.top)).size).toBe(menu.rows.length);
  for (const row of menu.rows) {
    expect(row.width, `${row.label} fills the menu`).toBe(Math.round(menu.inner));
    // The touch block's leading separator is the only sanctioned border in here.
    expect(row.borderTop, `${row.label} carries no user-agent button border`).toBeLessThanOrEqual(1);
    expect(row.fontFamily, `${row.label} uses the label face`).toContain('Marcellus');
  }

  // On a coarse pointer this menu is not a convenience — it is where the four
  // toolbar targets went when hover stopped existing, so its rows owe the same
  // 44px the toolbar buttons pay. The shared row above is sized for a mouse.
  const coarse = await page.evaluate(() => matchMedia('(pointer: coarse)').matches
    || matchMedia('(hover: none)').matches
    || window.innerWidth <= 768);
  if (coarse) {
    for (const row of menu.rows) {
      expect(row.height, `${row.label} clears the 44px touch target`).toBeGreaterThanOrEqual(44);
    }
    const pill = pop.locator('.post-menu-reactions .reaction').first();
    if (await pill.count()) {
      const box = (await pill.boundingBox())!;
      expect(box.height, 'reaction pills clear the 44px touch target').toBeGreaterThanOrEqual(44);
    }
  }
});

test('reduced motion removes Study animations', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await login(page);
  await openSeedTopic(page);
  await page.getByRole('button', { name: /^Topic tools/ }).click();
  const duration = await page.locator('[data-topic-tools]').evaluate((element) => getComputedStyle(element).animationDuration);
  expect(duration).toBe('0s');
});

test('light and dark Study surfaces retain readable semantic colors', async ({ page }) => {
  await login(page);
  await openSeedTopic(page);
  for (const theme of ['light', 'dark'] as const) {
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    const semanticContrast = await page.locator('[data-thread-study]').evaluate((element) => {
      const channels = (value: string) => (value.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
      const luminance = (value: string) => {
        const linear = channels(value).map((channel) => {
          const normalized = channel / 255;
          return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
      };
      const contrast = (foreground: string, background: string) => {
        const foregroundLuminance = luminance(foreground);
        const backgroundLuminance = luminance(background);
        return (Math.max(foregroundLuminance, backgroundLuminance) + 0.05)
          / (Math.min(foregroundLuminance, backgroundLuminance) + 0.05);
      };
      const pageProbe = document.createElement('span');
      pageProbe.style.background = 'var(--surface-page)';
      document.body.appendChild(pageProbe);
      const pageBackground = getComputedStyle(pageProbe).backgroundColor;
      pageProbe.remove();
      const title = element.querySelector('.thread-study-title')!;
      const chip = element.querySelector('.thread-status-chip')!;
      const titleStyle = getComputedStyle(title);
      const chipStyle = getComputedStyle(chip);
      return {
        title: contrast(titleStyle.color, pageBackground),
        chip: contrast(chipStyle.color, chipStyle.backgroundColor),
      };
    });
    expect(semanticContrast.title).toBeGreaterThanOrEqual(4.5);
    expect(semanticContrast.chip).toBeGreaterThanOrEqual(4.5);
    const toolsContrast = await page.getByRole('button', { name: /^Topic tools/ }).evaluate((element) => {
      const style = getComputedStyle(element);
      const channels = (value: string) => (value.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
      const luminance = (value: string) => {
        const linear = channels(value).map((channel) => {
          const normalized = channel / 255;
          return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
      };
      const foreground = luminance(style.color);
      const background = luminance(style.backgroundColor);
      return (Math.max(foreground, background) + 0.05) / (Math.min(foreground, background) + 0.05);
    });
    expect(toolsContrast).toBeGreaterThanOrEqual(4.5);
  }
});

test('mobile composer honors a representative keyboard inset', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile');
  await login(page);
  await openSeedTopic(page);
  await page.locator('#reply textarea[name="body"]').focus();
  await page.locator('html').evaluate((element) => element.style.setProperty('--keyboard-inset', '240px'));
  const composer = page.locator('[data-thread-composer]');
  const box = await composer.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.y + box!.height).toBeLessThanOrEqual(844 - 240 + 2);
});

test('the staff badge flips register and clears AA in both', async ({ page }) => {
  await login(page);
  await openSeedTopic(page);

  const measured: Record<string, { ratio: number; ground: string }> = {};
  for (const theme of ['light', 'dark'] as const) {
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    measured[theme] = await page.locator('[data-thread-study]').evaluate((element) => {
      const channels = (value: string) => (value.match(/[\d.]+/g) ?? []).map(Number);
      const luminance = (rgb: number[]) => {
        const linear = rgb.map((channel) => {
          const normalized = channel / 255;
          return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
        });
        return 0.2126 * linear[0] + 0.7152 * linear[1] + 0.0722 * linear[2];
      };
      const contrast = (a: number[], b: number[]) => {
        const [x, y] = [luminance(a), luminance(b)];
        return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
      };

      const probe = document.createElement('span');
      probe.className = 'badge badge-staff';
      probe.textContent = 'Staff';
      element.appendChild(probe);
      const style = getComputedStyle(probe);
      const ink = channels(style.color);
      const chip = channels(style.backgroundColor);
      probe.remove();

      // The chip ground is translucent in the twilight register, so it has to be
      // composited over the surface it actually sits on before being measured.
      const under = document.createElement('span');
      under.style.background = 'var(--surface-raised)';
      element.appendChild(under);
      const surface = channels(getComputedStyle(under).backgroundColor);
      under.remove();

      const alpha = chip.length > 3 ? chip[3] : 1;
      const ground = [0, 1, 2].map((i) => alpha * chip[i] + (1 - alpha) * surface[i]);
      return { ratio: contrast(ink, ground), ground: ground.map(Math.round).join(',') };
    });
  }

  expect(measured.light.ratio, JSON.stringify(measured)).toBeGreaterThanOrEqual(4.5);
  expect(measured.dark.ratio, JSON.stringify(measured)).toBeGreaterThanOrEqual(4.5);
  // A pair built from the numbered gold ramp clears both ratios while never
  // flipping: the chip stays light-register cream sitting on a twilight page.
  // The ratio alone cannot catch that, so assert the ground actually changes.
  expect(measured.dark.ground, JSON.stringify(measured)).not.toBe(measured.light.ground);
});
/**
 * B1/B2 from the round-2 thread-view handoff. The standing chips used to be
 * children of the <h1>, so the page heading announced itself as "Pinned Locked
 * Solved Where should…", and the solved chip alone carried a check glyph while
 * the Topic tools drawer stated the identical state as a bare word.
 */
test('standing chips sit above the title and state status as a word alone', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'one pointer type is enough for a markup contract');
  await login(page);
  await openSeedTopic(page);

  const heading = page.getByRole('heading', { level: 1 });
  const title = (await heading.textContent())?.trim() ?? '';
  expect(title.length).toBeGreaterThan(0);

  const setStatus = async (value: string): Promise<void> => {
    await page.getByRole('button', { name: /^Topic tools/ }).click();
    const tools = page.locator('[data-topic-tools]');
    await tools.locator('[data-topic-tools-section="standing"] > summary').click();
    await tools.locator('select[name="status"]').selectOption(value);
    await tools.getByRole('button', { name: 'Update status' }).click();
    await expect(page.locator('[data-thread-study]')).toBeVisible();
  };

  try {
    await setStatus('solved');

    const chip = page.locator('.thread-status-chip');
    await expect(chip).toHaveCount(1);
    // The word alone. Not the check glyph plus the word — the drawer states the
    // same state glyph-less, and two labels for one status is the defect.
    await expect(chip).toHaveText('Solved');
    await expect(chip).toHaveAttribute('data-thread-status', 'solved');

    // The chip is a sibling ABOVE the heading, never a descendant of it, so the
    // heading's accessible name is the topic title and nothing else.
    await expect(page.locator('.thread-study-chips')).toHaveCount(1);
    expect(await chip.evaluate((el) => el.closest('h1') !== null)).toBe(false);
    expect(await chip.evaluate((el) => {
      const row = el.closest('.thread-study-chips');
      const h1 = document.querySelector('.thread-study-title');
      return !!row && !!h1 && row.nextElementSibling === h1;
    })).toBe(true);
    await expect(heading).toHaveAccessibleName(title);
    expect(title).not.toContain('Solved');

    await shot(page, info, '89-thread-standing-chips');
  } finally {
    await setStatus('open');
  }
});

/**
 * B3. One esteem glyph in the system — the four-point commend star that already
 * marks regard and the accepted answer — and a facts row that does not break to
 * a second line when "Star" grows into "Starred".
 */
test('the star pill uses the commend star and never wraps the facts row', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the wrap this guards against is a desktop-width failure');
  await login(page);
  await openSeedTopic(page);

  const star = page.locator('.star-btn');
  await expect(star).toHaveCount(1);
  // The SVG is decorative, so the accessible name is exactly the label.
  await expect(star).toHaveAccessibleName(/^Star(red)?$/);
  await expect(star.locator('svg.icon-commend-star')).toHaveCount(1);
  await expect(star.locator('svg')).toHaveAttribute('aria-hidden', 'true');
  await expect(star.locator('svg path')).toHaveAttribute(
    'd',
    'M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z',
  );
  // No second star glyph anywhere in the head.
  expect(await page.locator('.thread-study-head').innerText()).not.toMatch(/[★☆]/);

  const rowHeight = async (): Promise<number> =>
    page.locator('.thread-facts').evaluate((el) => el.getBoundingClientRect().height);
  const before = await rowHeight();
  const labelBefore = (await star.innerText()).trim();
  const initiallyStarred = await star.getAttribute('aria-pressed') === 'true';

  try {
    // Toggle to the other label and prove the row is still one line. This is the
    // layout half of B3: a wrapping flex container breaks its lines from content
    // widths BEFORE flex-shrink applies, so a shrinkable byline on its own would
    // not have saved it.
    await expect(page.locator('.thread-facts')).toHaveCSS('flex-wrap', 'nowrap');
    await star.click();
    await expect(page.locator('.star-btn')).not.toHaveText(labelBefore);
    const after = await rowHeight();
    expect(Math.abs(after - before)).toBeLessThanOrEqual(1);

    await shot(page, info, '90-thread-star-pill');
  } finally {
    const current = await page.locator('.star-btn').getAttribute('aria-pressed') === 'true';
    if (current !== initiallyStarred) await page.locator('.star-btn').click();
  }
});

/**
 * B4. `.reaction-n::before` puts a separator between a reaction's NAME and its
 * count. Production reactions are raw emoji with no name, so the separator had
 * nothing to separate and rendered as a stray dot before the number.
 */
test('a bare reaction chip drops the orphaned name separator', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'the reaction picker differs only in placement by pointer type');
  await login(page);
  await openSeedTopic(page);

  const post = page.locator('[data-post]').first();
  const emoji = await post.locator('[data-post-toolbar] .reaction-menu input[name="emoji"]').first().inputValue();
  const initiallyOn = await post.locator('.reactions .reaction-on').filter({ hasText: emoji }).count() > 0;
  await setReactionState(post, emoji, false);
  try {
    await setReactionState(post, emoji, true);
    await expect(page.locator('[data-thread-study]')).toBeVisible();

    const chip = post.locator('.reactions .reaction-on').filter({ hasText: emoji }).first();
    await expect(chip).toHaveClass(/reaction-bare/);
    expect((await chip.innerText()).trim()).not.toContain('·');
    // The rule has to WIN, not merely exist: the identical ::before ships inside
    // @layer imladris.components, and app.css is unlayered.
    const separator = await chip.locator('.reaction-n').evaluate(
      (el) => getComputedStyle(el, '::before').content,
    );
    expect(separator).toBe('none');

    await shot(page, info, '91-thread-reaction-chip');
  } finally {
    await setReactionState(post, emoji, initiallyOn);
  }
});

test('server-invalid engraved controls receive the effective danger frame', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'cascade is viewport-independent');
  await page.goto('/login');
  const input = page.locator('.input-engraved').first();
  const before = await input.evaluate((element) => getComputedStyle(element).backgroundImage);
  const result = await input.evaluate((element) => {
    element.setAttribute('aria-invalid', 'true');
    const probe = document.createElement('span');
    probe.style.color = 'var(--danger)';
    document.body.appendChild(probe);
    const danger = getComputedStyle(probe).color;
    probe.remove();
    const image = getComputedStyle(element).backgroundImage;
    return { image, danger, layers: (image.match(/linear-gradient/g) ?? []).length };
  });
  expect(result.image).not.toBe(before);
  expect(result.image.replace(/\s/g, '')).toContain(result.danger.replace(/\s/g, ''));
  expect(result.layers).toBeGreaterThanOrEqual(8);
});

/**
 * Part D's no-JS pass. Every flow on this surface is server-rendered first; the
 * enhancement only swaps which copy of a control is exposed.
 */
test('the Study reads and replies with JavaScript disabled', async ({ browser }, info) => {
  test.skip(info.project.name !== 'desktop', 'the no-JS contract is pointer-independent');
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  const evidenceReply = 'A no-JavaScript reply owned by the thread-view evidence run.';
  try {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'alice@retro.test');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await page.goto('/c/general');
    await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
    await expect(page.locator('[data-thread-study]')).toBeVisible();
    await deleteOwnedRepliesWithText(page, evidenceReply);

    // Nothing is enhanced, so the toolbar stands at full opacity with no pointer.
    await expect(page.locator('[data-thread-study]')).not.toHaveAttribute('data-thread-enhanced', '1');
    const toolbar = page.locator('[data-post-toolbar]').first();
    await expect(toolbar).toBeVisible();
    await expect(toolbar).toHaveCSS('opacity', '1');

    // Edit / Report / Remove reach the reader through the in-flow native
    // disclosures, which JS hides in favour of the menu copies.
    await expect(page.locator('.post-native-disclosure > summary').first()).toBeVisible();

    // Topic tools is a JS-only affordance and stays hidden rather than dead.
    await expect(page.locator('[data-topic-tools-open]').first()).toBeHidden();

    // The composer degrades to a plain Markdown textarea that still posts.
    const composer = page.locator('form[data-thread-composer]');
    await expect(composer).toHaveCount(1);
    await expect(composer.locator('.wysiwyg-surface')).toHaveCount(0);
    const body = composer.locator('textarea[name="body"]');
    await expect(body).toBeVisible();
    await body.fill(evidenceReply);
    await composer.locator('button.composer-send').click();
    // Scoped to the stream: the same words also land in the split/merge picker
    // and the catch-me-up strip, which is itself proof the plain POST went
    // through every downstream consumer.
    await expect(
      page.locator('[data-post] .post-body').filter({ hasText: evidenceReply }),
    ).toHaveCount(1);

    await page.screenshot({
      path: path.join(EVIDENCE_DIR, info.project.name, '92-thread-no-js.png'),
      fullPage: true,
      animations: 'disabled',
    });
  } finally {
    if (!page.isClosed()) await deleteOwnedRepliesWithText(page, evidenceReply);
    await context.close();
  }
});

/** Part D's keyboard pass: every post action reachable without a pointer. */
test('post actions are reachable by keyboard alone', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'coarse pointers stand the toolbar permanently');
  await login(page);
  await openSeedTopic(page);

  const posts = page.locator('[data-post]:has([data-post-menu] > summary)');
  const post = posts.first();
  const toolbar = post.locator('[data-post-toolbar]');
  // At rest, with no pointer anywhere near it: present in the DOM but quiet.
  // Park the pointer first — Playwright leaves it wherever the last click was,
  // and openSeedTopic's own click lands inside the reading column, so "at rest"
  // was a claim this test never actually established.
  await page.mouse.move(0, 0);
  await expect(toolbar).toHaveCount(1);
  await expect(toolbar).toHaveCSS('opacity', '0');

  // Focus alone reveals it — this is why the toolbar fades rather than
  // unmounting or going `visibility: hidden`: a hidden control is not tabbable,
  // and tabbing to it is the point.
  const menu = post.locator('[data-post-menu] > summary');
  expect(await tabTo(page, menu)).toBeGreaterThan(0);
  await expect(toolbar).toHaveCSS('opacity', '1');
  await expect(menu).toBeFocused();

  await page.keyboard.press('Enter');
  await expect(post.locator('[data-post-menu]')).toHaveAttribute('open', '');
  await expect(post.locator('.post-menu-pop')).toBeVisible();

  const edit = post.locator('[data-post-menu]').getByRole('button', { name: 'Edit' });
  expect(await tabTo(page, edit)).toBeGreaterThan(0);
  await page.keyboard.press('Enter');
  const editDisclosure = post.locator('.post-native-disclosure.post-edit');
  await expect(editDisclosure).toHaveAttribute('open', '');

  const secondMenu = posts.nth(1).locator('[data-post-menu] > summary');
  expect(await tabTo(page, secondMenu)).toBeGreaterThan(0);
  await page.keyboard.press('Enter');
  await expect(posts.nth(1).locator('[data-post-menu]')).toHaveAttribute('open', '');

  // Escape peels the topmost keyboard layer only: the second post menu closes,
  // while the earlier edit disclosure remains open until the next keypress.
  await page.keyboard.press('Escape');
  await expect(posts.nth(1).locator('[data-post-menu]')).not.toHaveAttribute('open', '');
  await expect(secondMenu).toBeFocused();
  await expect(editDisclosure).toHaveAttribute('open', '');
  await page.keyboard.press('Escape');
  await expect(editDisclosure).not.toHaveAttribute('open', '');
  await expect(menu).toBeFocused();
});
