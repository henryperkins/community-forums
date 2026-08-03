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

test('board identity opens the one topic composer and restores focus to its actual opener', async ({ page }, info) => {
  await login(page);
  await page.goto('/c/general');

  const identity = page.locator('[data-board-identity]');
  const details = page.locator('details.composer-details#new-topic');
  const title = details.locator('input[name="title"]');
  const promoted = page.locator('[data-open-topic-composer]');
  const fab = page.locator('a.fab[href="#new-topic"]');

  await expect(identity).toBeVisible();
  await expect(identity).toHaveCSS('background-color', 'rgb(46, 74, 58)');
  await expect(identity).toHaveCSS('border-bottom-color', 'rgb(194, 154, 68)');
  await expect(identity).toHaveCSS('color', 'rgb(250, 246, 236)');
  await expect(details).toHaveCount(1);
  await expect(promoted).toHaveAttribute('aria-expanded', 'false');

  const opener = info.project.name === 'mobile' ? fab : promoted;
  await expect(opener).toBeVisible();
  await opener.click();

  await expect(details).toHaveJSProperty('open', true);
  await expect(promoted).toHaveAttribute('aria-expanded', 'true');
  await expect(title).toBeFocused();

  await page.keyboard.press('Escape');

  await expect(details).toHaveJSProperty('open', false);
  await expect(promoted).toHaveAttribute('aria-expanded', 'false');
  await expect(opener).toBeFocused();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('board route does not overflow at the 860px shell transition', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'The desktop project owns the intermediate-width regression.');

  await page.setViewportSize({ width: 800, height: 800 });
  await login(page);
  await page.goto('/c/general');

  const layout = await page.evaluate(() => {
    const board = document.querySelector('.board-view');
    const boardRect = board?.getBoundingClientRect();
    return {
      viewportWidth: document.documentElement.clientWidth,
      scrollWidth: document.documentElement.scrollWidth,
      boardLeft: boardRect ? Math.round(boardRect.left) : null,
      boardRight: boardRect ? Math.round(boardRect.right) : null,
    };
  });

  expect(layout.viewportWidth).toBe(800);
  expect(layout.scrollWidth, JSON.stringify(layout)).toBe(800);
});
