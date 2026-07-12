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
