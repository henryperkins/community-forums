import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');

function setRichComposer(enabled: boolean | null): boolean | null {
  const mutation = enabled === null
    ? "unset($features['rich_composer']);"
    : `$features['rich_composer'] = ${enabled ? 'true' : 'false'};`;
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$previous = array_key_exists('rich_composer', $features) ? (bool) $features['rich_composer'] : null;
${mutation}
$settings->set('features', $features);
echo json_encode($previous);
`;
  return JSON.parse(execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim()) as boolean | null;
}

function markInboxTopicUnreadForAlice(title: string, promoteInActiveList = false): void {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$thread = $db->fetch(
    'SELECT t.id, p.id AS op_id FROM threads t JOIN posts p ON p.thread_id = t.id AND p.is_op = 1 WHERE t.title = ? AND t.is_deleted = 0 ORDER BY t.id DESC LIMIT 1',
    [${JSON.stringify(title)}],
);
$aliceId = $db->fetchValue('SELECT id FROM users WHERE email = ?', ['alice@retro.test']);
if ($thread === null || $aliceId === false) {
    throw new RuntimeException('Missing Inbox unread fixture.');
}
$db->run(
    'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE last_read_post_id = VALUES(last_read_post_id), snoozed_until = NULL',
    [(int) $aliceId, (int) $thread['id'], (int) $thread['op_id']],
);
${promoteInActiveList ? "$db->run('UPDATE threads SET last_post_at = UTC_TIMESTAMP() WHERE id = ?', [(int) $thread['id']]);" : ''}
`;
  execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
}

function setInboxBoardMutedForAlice(slug: string, muted: boolean): void {
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$aliceId = $db->fetchValue('SELECT id FROM users WHERE email = ?', ['alice@retro.test']);
$boardId = $db->fetchValue('SELECT id FROM boards WHERE slug = ?', [${JSON.stringify(slug)}]);
if ($aliceId === false || $boardId === false) {
    throw new RuntimeException('Missing Inbox mute fixture.');
}
$db->run(
    'INSERT INTO user_board_prefs (user_id, board_id, is_muted) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_muted = VALUES(is_muted)',
    [(int) $aliceId, (int) $boardId, ${muted ? 1 : 0}],
);
`;
  execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
}

async function dismissTour(page: Page): Promise<void> {
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) {
    await skip.click();
    await expect(page.locator('.tour-popover')).toHaveCount(0);
  }
}

async function login(page: Page): Promise<void> {
  await page.context().clearCookies();
  await page.goto('/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
  await dismissTour(page);
}

async function openShortcutInboxTopic(page: Page) {
  const link = page.locator('[data-inbox-list] [data-inbox-row]')
    .filter({ hasText: 'Share your favourite keyboard shortcuts' })
    .locator('.inbox-row-title');
  await expect(link).toHaveCount(1);
  await link.click();
  const reading = page.locator('[data-inbox-reading]');
  const preview = reading.locator('[data-inbox-preview]');
  await expect(preview).toBeVisible();
  return { link, reading, preview, form: preview.locator('#reply') };
}

async function expectNoSeriousA11yViolations(page: Page, include: string): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include(include)
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const violations = results.violations.filter((violation) =>
    violation.impact === 'serious' || violation.impact === 'critical',
  );
  expect(violations, `${include} serious/critical axe violations`).toEqual([]);
}

test('responsive Inbox opens a topic in place and mobile Back restores its link', async ({ page }, info) => {
  await login(page);

  const inbox = page.locator('[data-inbox]');
  const list = inbox.locator('[data-inbox-list]');
  const reading = inbox.locator('[data-inbox-reading]');
  const topic = list.locator('.inbox-row-title').first();
  const topicHref = await topic.getAttribute('href');
  expect(topicHref).toMatch(/^\/t\/\d+/);

  await topic.click();
  await expect(page).toHaveURL(/\/inbox\?.*t=\d+/);
  const preview = reading.locator('[data-inbox-preview]');
  await expect(preview).toBeVisible();
  await expect(preview.locator('h2').first()).toBeFocused();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

  await expect(reading.locator('[data-thread-study], [data-topic-tools], article[data-post], .thread-dock')).toHaveCount(0);
  const previewContainment = await reading.evaluate((element) => {
    const readingBox = element.getBoundingClientRect();
    const previewBox = element.querySelector('[data-inbox-preview]')!.getBoundingClientRect();
    return {
      left: previewBox.left >= readingBox.left - 1,
      right: previewBox.right <= readingBox.right + 1,
      top: previewBox.top >= readingBox.top - 1,
    };
  });
  expect(previewContainment).toEqual({ left: true, right: true, top: true });

  if (info.project.name === 'mobile') {
    await expect(list).toBeHidden();
    const back = reading.getByRole('button', { name: 'Back to topics' });
    await expect(back).toBeVisible();
    await back.click();
    await expect(page).toHaveURL(/\/inbox$/);
    await expect(list).toBeVisible();
    await expect(reading).toBeHidden();
    await expect(topic).toBeFocused();
    await page.goForward();
    await expect(page).toHaveURL(/\/inbox\?.*t=\d+/);
    await expect(list).toBeHidden();
    await expect(reading.locator('[data-inbox-preview]')).toBeVisible();
    await expect(reading.locator('[data-inbox-preview] h2').first()).toBeFocused();
  } else {
    await expect(list).toBeVisible();
    await expect(reading).toBeVisible();
  }
});

test('Inbox preserves the latest selection when an older topic fetch finishes late', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'request ordering is verified once');
  await login(page);

  const links = page.locator('[data-inbox-list] .inbox-row-title');
  const first = links.nth(0);
  const second = links.nth(1);
  const firstHref = await first.getAttribute('href');
  const firstEndpoint = await first.getAttribute('data-inbox-preview-url');
  const secondHref = await second.getAttribute('href');
  const secondEndpoint = await second.getAttribute('data-inbox-preview-url');
  const secondTitle = (await second.textContent())?.trim();
  const secondId = secondHref?.match(/^\/t\/(\d+)/)?.[1];
  expect(firstHref).toMatch(/^\/t\/\d+/);
  expect(firstEndpoint).toMatch(/^\/inbox\/preview\/\d+$/);
  expect(secondHref).toMatch(/^\/t\/\d+/);
  expect(secondEndpoint).toMatch(/^\/inbox\/preview\/\d+$/);
  expect(secondTitle).toBeTruthy();
  expect(secondId).toBeTruthy();

  let firstRequestHeld = false;
  let firstResponseReleased = false;
  let releaseFirst: (() => void) | undefined;
  await page.route('**/inbox/preview/*', async (route) => {
    const request = route.request();
    if (
      request.headers()['x-requested-with'] === 'XMLHttpRequest'
      && new URL(request.url()).pathname === firstEndpoint
      && !firstRequestHeld
    ) {
      const response = await route.fetch();
      firstRequestHeld = true;
      await new Promise<void>((resolve) => { releaseFirst = resolve; });
      await route.fulfill({ response }).catch(() => {});
      firstResponseReleased = true;
      return;
    }
    await route.continue();
  });

  await first.click();
  await expect.poll(() => firstRequestHeld).toBe(true);
  await second.click();
  await expect(page.locator('[data-inbox-preview] h2').filter({ hasText: secondTitle! }).first()).toBeVisible();
  expect(new URL(page.url()).searchParams.get('t')).toBe(secondId);

  releaseFirst?.();
  await expect.poll(() => firstResponseReleased).toBe(true);
  await page.waitForTimeout(100);
  await expect(page.locator('[data-inbox-preview] h2').filter({ hasText: secondTitle! }).first()).toBeVisible();
  await expect.poll(() => new URL(page.url()).searchParams.get('t')).toBe(secondId);
});

test('Inbox opening reconciles an unread row and its count', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'read reconciliation is verified once');
  const title = 'Share your favourite keyboard shortcuts';
  markInboxTopicUnreadForAlice(title);
  await login(page);
  await page.goto('/inbox?scope=unread&order=active');

  const row = page.locator('[data-inbox-list] [data-inbox-row]').filter({ hasText: title });
  const badge = page.locator('[data-inbox] [data-inbox-unread-count]');
  await expect(row).toHaveCount(1);
  await expect(row).toHaveClass(/\bis-unread\b/);
  await expect(row).toHaveAttribute('data-inbox-unread', '1');
  await expect(row.locator('.unread-dot')).toHaveCount(1);
  const before = Number(await badge.getAttribute('data-inbox-unread-count'));
  expect(before).toBeGreaterThan(0);

  await row.locator('.inbox-row-title').click();
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  await expect(row).toHaveCount(0);
  if (before === 1) {
    await expect(badge).toHaveCount(0);
  } else {
    await expect(badge).toHaveAttribute('data-inbox-unread-count', String(before - 1));
  }
});

test('Inbox opening a muted unread row leaves the queue badge unchanged', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'read reconciliation is verified once');
  const title = 'Share your favourite keyboard shortcuts';
  markInboxTopicUnreadForAlice('Welcome to RetroBoards');
  markInboxTopicUnreadForAlice(title, true);
  setInboxBoardMutedForAlice('general', true);

  try {
    await login(page);
    await page.goto('/inbox?scope=for_you&order=active');

    const row = page.locator('[data-inbox-list] [data-inbox-row]').filter({ hasText: title });
    const badge = page.locator('[data-inbox] [data-inbox-unread-count]');
    await expect(row).toHaveCount(1);
    await expect(row).toHaveClass(/\bis-unread\b/);
    await expect(row).not.toHaveAttribute('data-inbox-unread', '1');
    const before = Number(await badge.getAttribute('data-inbox-unread-count'));
    expect(before).toBeGreaterThan(0);

    await row.locator('.inbox-row-title').click();
    await expect(page.locator('[data-inbox-preview]')).toBeVisible();
    await expect(row).not.toHaveClass(/\bis-unread\b/);
    await expect(row.locator('.unread-dot')).toHaveCount(0);
    await expect(badge).toHaveAttribute('data-inbox-unread-count', String(before));
  } finally {
    setInboxBoardMutedForAlice('general', false);
  }
});

test('Inbox Forward reloads a read queue topic in the reading pane', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'history fallback is verified once');
  const title = 'Share your favourite keyboard shortcuts';
  markInboxTopicUnreadForAlice(title);
  await login(page);
  await page.goto('/inbox?scope=unread&order=active');

  const row = page.locator('[data-inbox-list] [data-inbox-row]').filter({ hasText: title });
  await expect(row).toHaveCount(1);
  await row.locator('.inbox-row-title').click();
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  await expect(row).toHaveCount(0);

  await page.goBack();
  await expect(page).toHaveURL(/\/inbox\?scope=unread&order=active$/);
  await expect(page.locator('[data-inbox-list]')).toBeVisible();

  await page.goForward();
  await expect(page).toHaveURL(/\/inbox\?scope=unread&order=active&t=\d+$/);
  const reading = page.locator('[data-inbox-reading]');
  await expect(reading.locator('[data-inbox-preview]')).toBeVisible();
  await expect(reading.locator('[data-inbox-preview] h2').first()).toBeFocused();
});

test('Inbox fragments receive one complete composer enhancement', async ({ page }) => {
  await login(page);
  let opened = await openShortcutInboxTopic(page);
  let form = opened.form;
  await expect(form.locator('textarea.composer-input')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(form.locator('.composer-toolbar')).toHaveCount(1);
  await expect(form.locator('.composer-emoji-toggle')).toHaveCount(1);
  await expect(form.locator('.composer-attach-toggle')).toHaveCount(1);

  const editor = form.locator('.ProseMirror:visible, textarea[name="body"]:visible').first();
  await editor.focus();
  await expect(form).toHaveClass(/\bis-expanded\b/);
  await expect(form.getByRole('button', { name: 'Emoji', exact: true })).toBeVisible();
  await expect(form.getByRole('button', { name: 'Attach images', exact: true })).toBeVisible();
  await editor.fill(':sm');
  await expect(form.locator('.composer-reference-menu')).toBeVisible();
  await editor.press('Escape');
  await form.getByRole('button', { name: 'Emoji', exact: true }).click();
  await expect(form.getByRole('dialog', { name: 'Emoji' })).toBeVisible();
  await form.getByRole('dialog', { name: 'Emoji' }).press('Escape');

  await editor.fill(`Inbox draft ${Date.now()}`);
  await expect(form.locator('[data-composer-draft-slot]')).toContainText('Draft saved', { timeout: 5000 });
  await expectNoSeriousA11yViolations(page, '[data-inbox-preview] form.reply-composer');
  const discard = form.getByRole('button', { name: 'Discard draft' });
  await expect(discard).toBeVisible();
  await discard.click();

  await page.goBack();
  await expect(page).toHaveURL(/\/inbox$/);
  opened = await openShortcutInboxTopic(page);
  form = opened.form;
  await expect(form.locator('.composer-toolbar')).toHaveCount(1);
  await expect(form.locator('.composer-emoji-toggle')).toHaveCount(1);
  await expect(form.locator('.composer-attach-toggle')).toHaveCount(1);
  await expect(form.locator('input[type="file"][data-composer-upload-input]')).toHaveCount(1);
});

test('Inbox replacement destroys the previous composer lifecycle before enhancing the next fragment', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'fragment lifecycle is verified once');
  await login(page);
  const opened = await openShortcutInboxTopic(page);
  await expect(opened.form.locator('.ProseMirror:visible, textarea.composer-input:visible').first()).toBeVisible();
  expect(await page.evaluate(() => typeof (window as Window & {
    RetroBoardsComposer?: { destroyWithin?: (root: ParentNode) => void };
  }).RetroBoardsComposer?.destroyWithin)).toBe('function');

  await opened.form.evaluate((element) => {
    const trackedWindow = window as Window & {
      __rbComposerLifecycle?: { destroyWithin: number; adapterDestroy: number };
      RetroBoardsComposer?: { destroyWithin?: (root: ParentNode) => void };
    };
    trackedWindow.__rbComposerLifecycle = { destroyWithin: 0, adapterDestroy: 0 };
    const api = trackedWindow.RetroBoardsComposer!;
    const originalDestroyWithin = api.destroyWithin!.bind(api);
    api.destroyWithin = (root) => {
      trackedWindow.__rbComposerLifecycle!.destroyWithin++;
      originalDestroyWithin(root);
    };
    const adapter = (element as HTMLFormElement & {
      _rbComposerAdapter?: { destroy?: () => void };
    })._rbComposerAdapter;
    const originalAdapterDestroy = adapter?.destroy?.bind(adapter);
    if (adapter && originalAdapterDestroy) {
      adapter.destroy = () => {
        trackedWindow.__rbComposerLifecycle!.adapterDestroy++;
        originalAdapterDestroy();
      };
    }
  });

  const nextTopic = page.locator('[data-inbox-list] [data-inbox-row]:not(.is-active) .inbox-row-title').first();
  await expect(nextTopic).toBeVisible();
  const previousUrl = page.url();
  await nextTopic.click();
  await page.waitForURL((url) => url.toString() !== previousUrl && url.searchParams.has('t'));
  await expect(page.locator('[data-inbox-preview] form.reply-composer textarea.composer-input')).toHaveAttribute('data-rb-enhanced', '1');
  expect(await page.evaluate(() => (window as Window & {
    __rbComposerLifecycle?: { destroyWithin: number; adapterDestroy: number };
  }).__rbComposerLifecycle)).toEqual({ destroyWithin: 1, adapterDestroy: 1 });
});

test('Inbox Enter submission navigates to the canonical posted reply', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop Enter-to-send navigation contract');
  await login(page);
  const { form } = await openShortcutInboxTopic(page);
  const body = `Inbox Enter reply ${Date.now()}`;
  const textarea = form.locator('textarea[name="body"]');
  await expect(textarea).toBeVisible();
  await textarea.fill(body);
  await textarea.press('Enter');
  await expect(page).toHaveURL(/\/t\/\d+-[^#]+#p\d+$/);
  await expect(page.locator('.post-body').getByText(body, { exact: true })).toBeVisible();
});

test('rich composer off keeps Inbox loading in pane with a plain shell', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'kill-switch contract is verified once');
  const previous = setRichComposer(false);
  const pageErrors: string[] = [];
  page.on('pageerror', (error) => pageErrors.push(error.message));
  try {
    await login(page);
    const { form } = await openShortcutInboxTopic(page);
    await expect(page).toHaveURL(/\/inbox\?.*t=\d+/);
    await expect(form.locator('textarea[name="body"]')).toBeVisible();
    await expect(form.locator('.composer-toolbar')).toHaveCount(0);
    await expect(form.locator('textarea.composer-input')).not.toHaveAttribute('data-rb-enhanced', '1');
    expect(await page.evaluate(() => typeof (window as Window & { RetroBoardsComposer?: unknown }).RetroBoardsComposer)).toBe('undefined');
    expect(pageErrors).toEqual([]);
  } finally {
    setRichComposer(previous);
  }
});

test('mobile top bar stays one row and Search remains reachable', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile chrome contract');
  await login(page);

  const topbar = page.locator('.topbar');
  const topbarBox = await topbar.boundingBox();
  expect(topbarBox).not.toBeNull();
  expect(topbarBox!.height).toBeLessThanOrEqual(64);
  await expect(page.locator('.topbar-search')).toBeHidden();
  const search = page.locator('.topbar-search-entry');
  await expect(search).toBeVisible();
  await expect(search).toHaveAttribute('href', '/search');
  const searchBox = await search.boundingBox();
  expect(searchBox).not.toBeNull();
  expect(searchBox!.width).toBeGreaterThanOrEqual(40);
  expect(searchBox!.height).toBeGreaterThanOrEqual(40);

  const viewportFits = await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);
  expect(viewportFits).toBe(true);

  const navigation = page.getByRole('button', { name: 'Open board rail' });
  const navigationBox = await navigation.boundingBox();
  expect(navigationBox).not.toBeNull();
  expect(navigationBox!.width).toBeGreaterThanOrEqual(44);
  expect(navigationBox!.height).toBeGreaterThanOrEqual(44);
  await navigation.click();
  await expect(page.locator('[data-sidebar]')).toBeVisible();
});

test('mobile preview keeps its reply composer contained and expands it on focus', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile preview composer contract');
  await login(page);

  await page.locator('[data-inbox-list] .inbox-row-title').first().click();
  const preview = page.locator('[data-inbox-preview]');
  const composer = preview.locator('.reply-composer');
  await expect(preview).toBeVisible();
  await expect(composer).toBeVisible();

  expect(await preview.evaluate((element) => {
    const previewBox = element.getBoundingClientRect();
    const composerBox = element.querySelector('.reply-composer')!.getBoundingClientRect();
    return composerBox.left >= previewBox.left - 1 && composerBox.right <= previewBox.right + 1;
  })).toBe(true);
  await expect(composer).not.toHaveClass(/\bis-expanded\b/);

  await composer.locator('textarea[name="body"]').focus();
  await expect(composer).toHaveClass(/\bis-expanded\b/);
});

test('direct mobile Inbox URLs open the conversation state', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile direct-link contract');
  await login(page);

  const topic = page.locator('[data-inbox-list] .inbox-row-title').first();
  const href = await topic.getAttribute('href');
  const id = href?.match(/^\/t\/(\d+)/)?.[1];
  expect(id).toBeTruthy();

  await page.goto(`/inbox?t=${id}`);
  await expect(page.locator('[data-inbox-list]')).toBeHidden();
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  await expect(page.locator('[data-inbox-preview] h2').first()).toBeFocused();
  await expect(page.getByRole('button', { name: 'Back to topics' })).toBeVisible();
});

test('failed Inbox fetches fall back to the canonical topic route', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile fetch-fallback contract');
  await login(page);
  await page.route('**/inbox/preview/*', async (route) => {
    if (route.request().headers()['x-requested-with'] === 'XMLHttpRequest') {
      await route.fulfill({ status: 500, contentType: 'text/plain', body: 'forced fetch failure' });
      return;
    }
    await route.continue();
  });

  await page.locator('[data-inbox-list] .inbox-row-title').first().click();
  await expect(page).toHaveURL(/\/t\/\d+/);
  await expect(page.locator('.thread-conversation')).toBeVisible();
});

test('canonical mobile composer keeps formatting and anonymous controls contained', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile composer containment contract');
  await login(page);

  const generalTopic = page.locator('[data-inbox-list] [data-inbox-row]')
    .filter({ hasText: 'Share your favourite keyboard shortcuts' })
    .locator('.inbox-row-title');
  await expect(generalTopic).toHaveCount(1);
  await page.goto((await generalTopic.getAttribute('href'))!);

  const composer = page.locator('.reply-composer');
  const editor = composer.locator('.ProseMirror:visible, textarea[name="body"]:visible').first();
  await expect(editor).toBeVisible();
  await editor.focus();
  await expect(composer).toHaveClass(/\bis-expanded\b/);

  const toolbar = composer.locator('.composer-toolbar');
  await expect(toolbar).toBeVisible();
  const toolbarLayout = await toolbar.evaluate((element) => {
    const style = getComputedStyle(element);
    return { flexWrap: style.flexWrap, overflowX: style.overflowX, right: element.getBoundingClientRect().right };
  });
  expect(toolbarLayout.flexWrap).toBe('nowrap');
  expect(toolbarLayout.overflowX).toBe('hidden');
  await expect(toolbar.getByRole('button', { name: 'More formatting' })).toBeVisible();

  const anonymous = composer.locator('.composer-anonymous-chip');
  const disclosure = composer.locator('.composer-anonymous-disclosure');
  await expect(anonymous).toBeVisible();
  await expect(disclosure).toBeVisible();
  const containment = await composer.evaluate((element) => {
    const composerRect = element.getBoundingClientRect();
    const toolbarRect = element.querySelector('.composer-toolbar')!.getBoundingClientRect();
    const anonymousRect = element.querySelector('.composer-anonymous-chip')!.getBoundingClientRect();
    return {
      toolbarContained: toolbarRect.right <= composerRect.right + 1,
      anonymousContained: anonymousRect.right <= composerRect.right + 1,
      pageContained: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
    };
  });
  expect(containment).toEqual({ toolbarContained: true, anonymousContained: true, pageContained: true });
});

test('parchment and twilight preserve the Inbox layout', async ({ page }) => {
  await login(page);
  await page.locator('[data-inbox-list] .inbox-row-title').first().click();
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();

  const measure = async (theme: 'light' | 'dark') => {
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    return page.evaluate(() => {
      const reading = document.querySelector('[data-inbox-reading]')!.getBoundingClientRect();
      const preview = document.querySelector('[data-inbox-preview]')!.getBoundingClientRect();
      return {
        readingWidth: reading.width,
        previewWidth: preview.width,
        previewContained: preview.left >= reading.left - 1 && preview.right <= reading.right + 1,
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        surface: getComputedStyle(document.documentElement).getPropertyValue('--surface-raised').trim(),
      };
    });
  };

  const parchment = await measure('light');
  const twilight = await measure('dark');
  expect(parchment.surface).not.toBe(twilight.surface);
  expect(parchment.overflow).toBe(false);
  expect(twilight.overflow).toBe(false);
  expect(parchment.previewContained).toBe(true);
  expect(twilight.previewContained).toBe(true);
  expect(Math.abs(parchment.readingWidth - twilight.readingWidth)).toBeLessThanOrEqual(1);
  expect(Math.abs(parchment.previewWidth - twilight.previewWidth)).toBeLessThanOrEqual(1);
});

test('Inbox and preview composer have no serious or critical axe violations', async ({ page }) => {
  await login(page);
  await expectNoSeriousA11yViolations(page, '[data-inbox]');
  await page.locator('[data-inbox-list] .inbox-row-title').first().click();
  await expect(page.locator('[data-inbox-preview]')).toBeVisible();
  await expectNoSeriousA11yViolations(page, '[data-inbox-preview]');
});

test('the no-JavaScript 390px journey keeps disclosure and submits the server reply form', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'run the no-JavaScript journey once');
  const context = await browser.newContext({
    baseURL: baseURL!,
    javaScriptEnabled: false,
    viewport: { width: 390, height: 844 },
  });
  const page = await context.newPage();
  try {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'alice@retro.test');
    await page.fill('input[name="password"]', 'password123');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/inbox(?:\?|$)/);

    const topic = page.locator('[data-inbox-list] [data-inbox-row]')
      .filter({ hasText: 'Share your favourite keyboard shortcuts' })
      .locator('.inbox-row-title');
    const href = await topic.getAttribute('href');
    await topic.click();
    await expect(page).toHaveURL(new RegExp(`${href!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));
    await expect(page.locator('.thread-dock #reply textarea[name="body"]')).toBeVisible();
    const disclosure = page.locator('#reply .composer-anonymous-disclosure');
    const anonymous = page.getByRole('checkbox', { name: 'Anonymous' });
    await expect(disclosure).toBeVisible();
    await anonymous.check();
    await expect(disclosure).toBeVisible();

    const body = `No-JavaScript reply ${Date.now()}`;
    await page.fill('#reply textarea[name="body"]', body);
    await page.click('#reply button[type="submit"]');
    await expect(page).toHaveURL(/\/t\/\d+.*#p\d+$/);
    await expect(page.locator('.post-body').getByText(body, { exact: true })).toBeVisible();
  } finally {
    await context.close();
  }
});
