import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceDir = path.join(repoRoot, 'docs/evidence/browser');
const richTitle = 'Rich content rendering contract';

test.beforeAll(() => {
  execFileSync('php', ['tests/browser/rich-content-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  });
});

async function installImageFixtures(page: Page): Promise<void> {
  await page.route('**/emoji/rich-content.png', async (route) => {
    await route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#8b6f2f"/><path d="M16 4l2.8 8.2L27 15l-8.2 2.8L16 26l-2.8-8.2L5 15l8.2-2.8z" fill="#fff4bf"/></svg>',
    });
  });
  await page.route('https://media4.giphy.com/**', async (route) => {
    await route.fulfill({
      contentType: 'image/svg+xml',
      body: '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="420" viewBox="0 0 1200 420"><rect width="1200" height="420" fill="#17332c"/><path d="M0 330L260 130l190 150 210-210 250 230 150-120 140 150v90H0z" fill="#b99a4c"/><text x="55" y="90" fill="#fff" font-size="38" font-family="sans-serif">Responsive rendering evidence</text></svg>',
    });
  });
}

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}

async function openRichTopic(page: Page): Promise<void> {
  await page.goto('/c/general');
  const href = await page.getByRole('link', { name: richTitle, exact: true }).first().getAttribute('href');
  expect(href).toMatch(/^\/t\/\d+/);
  await page.goto(href!);
  await expect(page.getByRole('heading', { level: 1, name: new RegExp(richTitle) })).toBeVisible();
}

async function pageOverflowReport(page: Page): Promise<{ clientWidth: number; scrollWidth: number; offenders: string[] }> {
  return page.evaluate(() => {
    const clientWidth = document.documentElement.clientWidth;
    const offenders = Array.from(document.body.querySelectorAll('*')).flatMap((element) => {
      const box = element.getBoundingClientRect();
      const style = getComputedStyle(element);
      if (box.width === 0 || box.height === 0 || box.right <= clientWidth + 1 || style.position === 'fixed') return [];
      const name = element.tagName.toLowerCase()
        + (element.id ? `#${element.id}` : '')
        + (element.classList.length ? `.${Array.from(element.classList).join('.')}` : '');
      return [`${name} right=${Math.round(box.right)} width=${Math.round(box.width)} overflow-x=${style.overflowX}`];
    }).slice(0, 20);
    return { clientWidth, scrollWidth: document.documentElement.scrollWidth, offenders };
  });
}

test('rich post semantics stay faithful, accessible, and contained', async ({ page }, info) => {
  await installImageFixtures(page);
  await login(page);
  await openRichTopic(page);

  const body = page.locator('.post-op .post-body.formatted-content');
  const tableRegion = body.getByRole('region', { name: 'Scrollable table' });
  await expect(body.getByRole('heading', { level: 2, name: 'Rendering contract' })).toBeVisible();
  await expect(body.getByRole('heading', { level: 3, name: 'Ordered from four' })).toBeVisible();
  await expect(body.locator('ol')).toHaveAttribute('start', '4');
  await expect(body.locator('pre code')).toHaveClass('language-php');
  const taskCheckboxes = body.locator('input[type="checkbox"]');
  await expect(taskCheckboxes).toHaveCount(2);
  expect(await taskCheckboxes.evaluateAll((items) => items.every((item) => (item as HTMLInputElement).disabled))).toBe(true);
  await expect(body.locator('.spoiler')).toContainText('Spoiler text remains available');
  await expect(tableRegion).toHaveAttribute('tabindex', '0');
  await expect(tableRegion).toHaveAttribute('role', 'region');
  await expect(body.locator('th').nth(0)).toHaveAttribute('align', 'left');
  await expect(body.locator('th').nth(1)).toHaveAttribute('align', 'center');
  await expect(body.locator('th').nth(2)).toHaveAttribute('align', 'right');

  const emoji = body.locator('img.custom-emoji');
  const image = body.locator('img:not(.custom-emoji)');
  await expect(emoji).toHaveAttribute('src', '/emoji/rich-content.png');
  await expect(emoji).toHaveAttribute('alt', ':render_spark:');
  await expect(image).toHaveAttribute('alt', 'Wide rendering evidence');
  await expect(image).toBeVisible();

  const geometry = await body.evaluate((element) => {
    const bodyBox = element.getBoundingClientRect();
    const regular = element.querySelector('img:not(.custom-emoji)') as HTMLImageElement;
    const custom = element.querySelector('img.custom-emoji') as HTMLImageElement;
    const table = element.querySelector('.formatted-table') as HTMLElement;
    const imageBox = regular.getBoundingClientRect();
    const emojiBox = custom.getBoundingClientRect();
    return {
      bodyWidth: bodyBox.width,
      imageWidth: imageBox.width,
      naturalWidth: regular.naturalWidth,
      emojiWidth: emojiBox.width,
      emojiHeight: emojiBox.height,
      emojiDisplay: getComputedStyle(custom).display,
      tableClientWidth: table.clientWidth,
      tableScrollWidth: table.scrollWidth,
    };
  });
  expect(geometry.naturalWidth).toBeGreaterThan(0);
  expect(geometry.imageWidth).toBeLessThanOrEqual(geometry.bodyWidth + 1);
  expect(geometry.emojiWidth).toBeLessThanOrEqual(24);
  expect(geometry.emojiHeight).toBeLessThanOrEqual(24);
  expect(geometry.emojiDisplay).toBe('inline-block');
  expect(geometry.tableScrollWidth).toBeGreaterThan(geometry.tableClientWidth);
  const overflow = await pageOverflowReport(page);
  expect(overflow.scrollWidth, JSON.stringify(overflow, null, 2)).toBeLessThanOrEqual(overflow.clientWidth);

  await tableRegion.focus();
  await expect(tableRegion).toBeFocused();
  await page.keyboard.press('ArrowRight');
  await expect.poll(() => tableRegion.evaluate((element) => element.scrollLeft)).toBeGreaterThan(0);

  const axe = await new AxeBuilder({ page })
    .include('.post-op .formatted-content')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const blocking = axe.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);

  await tableRegion.evaluate((element) => { element.scrollLeft = 0; });
  await page.screenshot({
    path: path.join(evidenceDir, info.project.name, '84-rich-content-table.png'),
    fullPage: true,
    animations: 'disabled',
  });
  await page.evaluate(() => {
    window.scrollTo(0, 0);
    document.scrollingElement?.scrollTo(0, 0);
    document.querySelector('.main')?.scrollTo(0, 0);
    document.querySelector('.thread-scroll')?.scrollTo(0, 0);
  });
  await page.screenshot({
    path: path.join(evidenceDir, info.project.name, '83-rich-content.png'),
    fullPage: true,
    animations: 'disabled',
  });
});

test('rich content remains server-rendered with JavaScript disabled', async ({ browser }, info) => {
  test.skip(info.project.name !== 'desktop', 'one narrow no-JS context proves the progressive-enhancement contract');
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  try {
    await installImageFixtures(page);
    await login(page);
    await openRichTopic(page);
    const body = page.locator('.post-op .post-body.formatted-content');
    await expect(body.getByRole('heading', { level: 2, name: 'Rendering contract' })).toBeVisible();
    await expect(body.getByRole('region', { name: 'Scrollable table' })).toBeVisible();
    await expect(body.locator('img:not(.custom-emoji)')).toBeVisible();
    const overflow = await pageOverflowReport(page);
    expect(overflow.scrollWidth, JSON.stringify(overflow, null, 2)).toBeLessThanOrEqual(overflow.clientWidth);
  } finally {
    await context.close();
  }
});
