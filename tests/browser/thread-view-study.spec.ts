import { expect, test, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}

async function openSeedTopic(page: Page): Promise<void> {
  await page.goto('/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await expect(page.locator('[data-thread-study]')).toBeVisible();
}

async function openManagement(page: Page): Promise<void> {
  const management = page.locator('[data-topic-tools-section="management"]');
  if (!(await management.evaluate((element) => (element as HTMLDetailsElement).open))) {
    await management.locator(':scope > summary').click();
  }
}

test('desktop Topic tools accords, traps focus, and restores each opener', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'desktop drawer contract');
  await login(page);
  await openSeedTopic(page);

  const trigger = page.getByRole('button', { name: 'Topic tools' });
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
  await page.locator('[data-topic-tools-scrim]').dispatchEvent('click');
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();

  await trigger.click();
  await page.keyboard.press('Escape');
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();
});

test('split or merge closes by every dismissal path and restores focus', async ({ page }) => {
  await login(page);
  await openSeedTopic(page);

  const topicTrigger = page.getByRole('button', { name: 'Topic tools' });
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

  await openRestructure();
  await page.locator('[data-thread-restructure-scrim]').dispatchEvent('click');
  await expect(dialog).toBeHidden();
  await expect(topicTrigger).toBeFocused();
});

test('post menus are exclusive, dismiss outside, and open real disclosures safely', async ({ page }) => {
  await login(page);
  await openSeedTopic(page);

  const posts = page.locator('article[data-post]');
  const firstMenu = posts.nth(0).locator('[data-post-menu]');
  const secondMenu = posts.nth(1).locator('[data-post-menu]');
  expect(await posts.count()).toBeGreaterThanOrEqual(2);

  await firstMenu.locator(':scope > summary').click();
  await expect(firstMenu).toHaveAttribute('open', '');
  await secondMenu.locator(':scope > summary').click();
  await expect(firstMenu).not.toHaveAttribute('open', '');
  await expect(secondMenu).toHaveAttribute('open', '');
  await page.keyboard.press('Escape');
  await expect(secondMenu).not.toHaveAttribute('open', '');
  await expect(secondMenu.locator(':scope > summary')).toBeFocused();
  await secondMenu.locator(':scope > summary').click();
  await page.locator('.thread-study-title').click();
  await expect(secondMenu).not.toHaveAttribute('open', '');

  await firstMenu.locator(':scope > summary').click();
  await firstMenu.getByRole('button', { name: 'Edit' }).click();
  const editDisclosure = posts.nth(0).locator('.post-native-disclosure.post-edit');
  await expect(firstMenu).not.toHaveAttribute('open', '');
  await expect(editDisclosure).toHaveAttribute('open', '');
  expect(await editDisclosure.evaluate((element) => element.contains(document.activeElement))).toBe(true);
});

test('copy link keeps anchor navigation when Clipboard is absent or rejects', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'fallback contract only needs one browser project');
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined });
  });
  await login(page);
  await openSeedTopic(page);

  const post = page.locator('article[data-post]').nth(1);
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

test('Inbox-inserted topics get idempotent drawer, quote, and keyboard enhancement', async ({ page }) => {
  await login(page);
  const shortcutRow = page.locator('[data-inbox-list] .thread-row', { hasText: 'Share your favourite keyboard shortcuts' });
  await shortcutRow.locator('a.thread-title').click();

  const reading = page.locator('[data-inbox-reading]');
  const root = reading.locator('[data-thread-study]');
  await expect(root).toHaveAttribute('data-thread-enhanced', '1');
  await reading.getByRole('button', { name: 'Topic tools' }).click();
  await expect(reading.locator('[data-topic-tools]')).toBeVisible();
  await reading.getByRole('button', { name: 'Close Topic tools' }).click();

  await page.goBack();
  await expect(page).toHaveURL(/\/inbox$/);
  await shortcutRow.locator('a.thread-title').click();
  await expect(root).toHaveAttribute('data-thread-enhanced', '1');
  await expect(reading.locator('[data-topic-tools]')).toHaveCount(1);
  await expect(reading.getByRole('button', { name: 'Topic tools' })).toHaveCount(1);
  await reading.getByRole('button', { name: 'Topic tools' }).click();
  await expect(reading.locator('[data-topic-tools]')).toBeVisible();
  await reading.getByRole('button', { name: 'Close Topic tools' }).click();

  const reply = reading.locator('#reply textarea[name="body"]');
  await reading.locator('article[data-post]').nth(1).getByRole('button', { name: 'Quote in your reply' }).click();
  const quotedValue = await reply.inputValue();
  expect(quotedValue.match(/^> /gm) ?? []).toHaveLength(1);
  expect(quotedValue).toMatch(/^> [^\n]+\n\n$/);
  await expect(reply).toBeFocused();
  await expect(page.locator('html')).toHaveCSS('--keyboard-inset', /\d+px/);
});
