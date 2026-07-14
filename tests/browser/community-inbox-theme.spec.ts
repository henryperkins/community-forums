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

function setWysiwygComposer(enabled: boolean | null): boolean | null {
  const mutation = enabled === null
    ? "unset($features['wysiwyg_composer']);"
    : `$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};`;
  const php = `
require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd() . '/.env');
$config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
$settings = new \\App\\Repository\\SettingRepository($db);
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$previous = array_key_exists('wysiwyg_composer', $features) ? (bool) $features['wysiwyg_composer'] : null;
${mutation}
$settings->set('features', $features);
echo json_encode($previous);
`;
  return JSON.parse(execFileSync('php', ['-r', php], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim()) as boolean | null;
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
  const link = page.locator('[data-inbox-list] .thread-row')
    .filter({ hasText: 'Share your favourite keyboard shortcuts' })
    .locator('a.thread-title');
  await expect(link).toHaveCount(1);
  await link.click();
  const reading = page.locator('[data-inbox-reading]');
  await expect(reading.locator('[data-thread-study]')).toBeVisible();
  return { link, reading, form: reading.locator('#reply') };
}

async function openTopicTools(page: Page, section: 'watch' | 'standing' | 'tags' | 'memory' | 'management') {
  const trigger = page.getByRole('button', { name: 'Topic tools', exact: true });
  await trigger.click();
  const tools = page.locator('[data-topic-tools]');
  await expect(tools).toBeVisible();
  await tools.evaluate(async (element) => Promise.all(element.getAnimations().map((animation) => animation.finished)));
  const details = tools.locator(`[data-topic-tools-section="${section}"]`);
  if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) await details.locator(':scope > summary').click();
  return { tools, details };
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
  const topic = list.locator('a.thread-title').first();
  const topicHref = await topic.getAttribute('href');
  expect(topicHref).toMatch(/^\/t\/\d+/);

  await topic.click();
  await expect(page).toHaveURL(/\/inbox\?.*t=\d+/);
  await expect(reading.locator('.thread')).toBeVisible();
  await expect(reading.locator('h1').first()).toBeFocused();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

  const topicTools = await openTopicTools(page, 'watch');
  await expect(topicTools.details).toBeVisible();
  await reading.getByRole('button', { name: 'Close Topic tools' }).click();
  await expect(topicTools.tools).toBeHidden();

  const posts = reading.locator('article[data-post]');
  expect(await posts.count()).toBeGreaterThan(0);
  const post = posts.first();
  const toolbar = post.locator('[data-post-toolbar]');
  await post.hover();
  await expect(toolbar).toBeVisible();
  const moreActions = post.locator('[data-post-menu] > summary');
  await moreActions.focus();
  await expect(moreActions).toBeFocused();
  await expect(toolbar).toBeVisible();

  const dockContainment = await reading.evaluate((element) => {
    const readingBox = element.getBoundingClientRect();
    const dockBox = element.querySelector('.thread-dock')!.getBoundingClientRect();
    return {
      left: dockBox.left >= readingBox.left - 1,
      right: dockBox.right <= readingBox.right + 1,
      top: dockBox.top >= readingBox.top - 1,
      bottom: dockBox.bottom <= readingBox.bottom + 1,
    };
  });
  expect(dockContainment).toEqual({ left: true, right: true, top: true, bottom: true });

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
    await expect(reading.locator('.thread')).toBeVisible();
  } else {
    await expect(list).toBeVisible();
    await expect(reading).toBeVisible();
  }
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
  await expectNoSeriousA11yViolations(page, '[data-inbox-reading] .thread-dock');
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

test('Inbox Study Quote inserts exactly once through source and rich adapters', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'source and rich adapter quote paths are verified once');
  const previous = setWysiwygComposer(true);
  try {
    await login(page);
    const { reading, form } = await openShortcutInboxTopic(page);
    await expect(form.getByRole('button', { name: 'Source' })).toBeVisible();
    await form.getByRole('button', { name: 'Source' }).click();
    const textarea = form.locator('textarea[name="body"]');
    await textarea.fill('');
    const post = reading.locator('article[data-post]').nth(1);
    await post.hover();
    await post.getByRole('button', { name: 'Quote in your reply' }).click();
    expect((await textarea.inputValue()).match(/^> /gm) ?? []).toHaveLength(1);

    await textarea.fill('');
    await form.getByRole('button', { name: 'Rich text' }).click();
    const editor = form.locator('.wysiwyg-composer .ProseMirror');
    await editor.fill('');
    await post.hover();
    await post.getByRole('button', { name: 'Quote in your reply' }).click();
    await expect.poll(async () => form.evaluate((element) => {
      const adapter = (element as HTMLFormElement & { _rbComposerAdapter?: { getMarkdown?: () => string } })._rbComposerAdapter;
      return adapter?.getMarkdown?.() ?? '';
    })).toMatch(/^> [^\n]+\n*$/);
    const richMarkdown = await form.evaluate((element) => {
      const adapter = (element as HTMLFormElement & { _rbComposerAdapter?: { getMarkdown?: () => string } })._rbComposerAdapter;
      return adapter?.getMarkdown?.() ?? '';
    });
    expect(richMarkdown.match(/^> /gm) ?? []).toHaveLength(1);
  } finally {
    setWysiwygComposer(previous);
  }
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

test('mobile top bar stays one row and Search remains reachable in the rail', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile chrome contract');
  await login(page);

  const topbar = page.locator('.topbar');
  const topbarBox = await topbar.boundingBox();
  expect(topbarBox).not.toBeNull();
  expect(topbarBox!.height).toBeLessThanOrEqual(64);
  await expect(page.locator('.topbar-search')).toBeHidden();

  const viewportFits = await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);
  expect(viewportFits).toBe(true);

  const navigation = page.getByRole('button', { name: 'Open navigation' });
  const navigationBox = await navigation.boundingBox();
  expect(navigationBox).not.toBeNull();
  expect(navigationBox!.width).toBeGreaterThanOrEqual(44);
  expect(navigationBox!.height).toBeGreaterThanOrEqual(44);
  await navigation.click();
  await expect(page.locator('[data-sidebar]')).toBeVisible();
  await expect(page.locator('[data-sidebar]').getByRole('link', { name: 'Search' })).toBeVisible();
});

test('mobile conversation keeps the reply dock visible and expands it on focus', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile reply dock contract');
  await login(page);

  await page.locator('[data-inbox-list] a.thread-title').first().click();
  const dock = page.locator('[data-inbox-reading] .thread-dock');
  const composer = dock.locator('.reply-composer');
  await expect(dock).toBeVisible();
  await expect(composer).toBeVisible();

  const dockBox = await dock.boundingBox();
  expect(dockBox).not.toBeNull();
  expect(dockBox!.y + dockBox!.height).toBeLessThanOrEqual(844);
  await expect(composer).not.toHaveClass(/\bis-expanded\b/);

  await composer.locator('textarea[name="body"]').focus();
  await expect(composer).toHaveClass(/\bis-expanded\b/);
});

test('direct mobile Inbox URLs open the conversation state', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile direct-link contract');
  await login(page);

  const topic = page.locator('[data-inbox-list] a.thread-title').first();
  const href = await topic.getAttribute('href');
  const id = href?.match(/^\/t\/(\d+)/)?.[1];
  expect(id).toBeTruthy();

  await page.goto(`/inbox?t=${id}`);
  await expect(page.locator('[data-inbox-list]')).toBeHidden();
  await expect(page.locator('[data-inbox-reading] .thread')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Back to topics' })).toBeVisible();
});

test('failed Inbox fetches fall back to the canonical topic route', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile fetch-fallback contract');
  await login(page);
  await page.route('**/t/**', async (route) => {
    if (route.request().headers()['x-requested-with'] === 'XMLHttpRequest') {
      await route.fulfill({ status: 500, contentType: 'text/plain', body: 'forced fetch failure' });
      return;
    }
    await route.continue();
  });

  await page.locator('[data-inbox-list] a.thread-title').first().click();
  await expect(page).toHaveURL(/\/t\/\d+/);
  await expect(page.locator('.thread-conversation')).toBeVisible();
});

test('canonical mobile composer keeps formatting and anonymous controls contained', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile', 'mobile composer containment contract');
  await login(page);

  const generalTopic = page.locator('[data-inbox-list] .thread-row')
    .filter({ hasText: 'Share your favourite keyboard shortcuts' })
    .locator('a.thread-title');
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
  await page.locator('[data-inbox-list] a.thread-title').first().click();
  await expect(page.locator('.thread-dock')).toBeVisible();

  const measure = async (theme: 'light' | 'dark') => {
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    return page.evaluate(() => {
      const reading = document.querySelector('[data-inbox-reading]')!.getBoundingClientRect();
      const dock = document.querySelector('.thread-dock')!.getBoundingClientRect();
      return {
        readingWidth: reading.width,
        dockWidth: dock.width,
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
  expect(Math.abs(parchment.readingWidth - twilight.readingWidth)).toBeLessThanOrEqual(1);
  expect(Math.abs(parchment.dockWidth - twilight.dockWidth)).toBeLessThanOrEqual(1);
});

test('Inbox and reply dock have no serious or critical axe violations', async ({ page }) => {
  await login(page);
  await expectNoSeriousA11yViolations(page, '[data-inbox]');
  await page.locator('[data-inbox-list] a.thread-title').first().click();
  await expect(page.locator('.thread-dock')).toBeVisible();
  await expectNoSeriousA11yViolations(page, '.thread-dock');
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

    const topic = page.locator('[data-inbox-list] .thread-row')
      .filter({ hasText: 'Share your favourite keyboard shortcuts' })
      .locator('a.thread-title');
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
