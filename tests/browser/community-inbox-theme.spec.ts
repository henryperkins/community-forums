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

  if (info.project.name === 'mobile') {
    await expect(list).toBeHidden();
    const back = reading.getByRole('button', { name: 'Back to topics' });
    await expect(back).toBeVisible();
    await back.click();
    await expect(page).toHaveURL(/\/inbox$/);
    await expect(list).toBeVisible();
    await expect(reading).toBeHidden();
    await expect(topic).toBeFocused();
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
