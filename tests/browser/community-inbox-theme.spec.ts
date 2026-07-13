import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

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
  expect(['auto', 'scroll']).toContain(toolbarLayout.overflowX);

  const anonymous = composer.locator('.checkline');
  await expect(anonymous).toBeVisible();
  const containment = await composer.evaluate((element) => {
    const composerRect = element.getBoundingClientRect();
    const toolbarRect = element.querySelector('.composer-toolbar')!.getBoundingClientRect();
    const anonymousRect = element.querySelector('.checkline')!.getBoundingClientRect();
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

test('the no-JavaScript journey uses canonical topics and the server reply form', async ({ browser, baseURL }, info) => {
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

    const topic = page.locator('[data-inbox-list] a.thread-title').first();
    const href = await topic.getAttribute('href');
    await topic.click();
    await expect(page).toHaveURL(new RegExp(`${href!.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`));
    await expect(page.locator('.thread-dock #reply textarea[name="body"]')).toBeVisible();

    const body = `No-JavaScript reply ${Date.now()}`;
    await page.fill('#reply textarea[name="body"]', body);
    await page.click('#reply button[type="submit"]');
    await expect(page).toHaveURL(/\/t\/\d+.*#p\d+$/);
    await expect(page.locator('.post-body').getByText(body, { exact: true })).toBeVisible();
  } finally {
    await context.close();
  }
});
