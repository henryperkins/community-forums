import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const repoRoot = path.resolve(__dirname, '..', '..');
const evidenceDir = path.join(repoRoot, 'docs', 'evidence', 'browser');
let threadPath = '';

function contrastRatio(first: string, second: string): number {
  const luminance = (color: string): number => {
    const channels = color.match(/[\d.]+/g)?.slice(0, 3).map(Number);
    if (!channels || channels.length !== 3) throw new Error(`Unsupported CSS color: ${color}`);
    const [red, green, blue] = channels.map((channel) => {
      const normalized = channel / 255;
      return normalized <= 0.04045
        ? normalized / 12.92
        : ((normalized + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
  };
  const a = luminance(first);
  const b = luminance(second);
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

function colorAlpha(color: string): number {
  const channels = color.match(/[\d.]+/g)?.map(Number) ?? [];
  return channels.length >= 4 ? channels[3] : 1;
}

test.beforeAll(() => {
  threadPath = execFileSync('php', ['tests/browser/thread-content-presentation-fixture.php'], {
    cwd: repoRoot,
    env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e' },
  }).toString().trim();
  expect(threadPath).toMatch(/^\/t\/\d+-/);
});

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email').fill('admin@retro.test');
  await page.getByLabel('Password').fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible({ timeout: 1000 }).catch(() => false)) await skip.click();
}

async function capture(page: Page, info: TestInfo, name: string): Promise<void> {
  await page.locator('.post-stream').screenshot({
    path: path.join(evidenceDir, info.project.name, name),
    animations: 'disabled',
  });
}

test('grouped and removed replies keep their final presentation at every viewport', async ({ page }, info) => {
  const messages: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'warning' || message.type() === 'error') messages.push(`${message.type()}: ${message.text()}`);
  });
  page.on('pageerror', (error) => messages.push(`pageerror: ${error.message}`));
  page.on('response', (response) => {
    if (response.status() >= 400) messages.push(`http ${response.status()}: ${response.url()}`);
  });

  await loginAsAdmin(page);
  const response = await page.goto(threadPath, { waitUntil: 'load' });
  expect(response?.status()).toBe(200);
  await expect(page.getByRole('heading', { level: 1, name: /Thread content presentation states/ })).toBeVisible();

  const grouped = page.locator('.post-grouped');
  const removed = page.locator('.post-deleted');
  await expect(grouped).toHaveCount(1);
  await expect(removed).toHaveCount(1);
  await expect(removed.getByText('Removed by a warden')).toBeVisible();
  await expect(removed.getByRole('button', { name: /Restore removed reply/ })).toBeVisible();
  await expect(grouped).toHaveCSS('padding-top', '0px');

  for (const [theme, screenshot] of [
    ['light', '85-thread-content-states-light.png'],
    ['dark', '86-thread-content-states-dark.png'],
  ] as const) {
    await page.locator('html').evaluate((element, nextTheme) => element.setAttribute('data-theme', nextTheme), theme);
    const frame = await removed.evaluate((element) => {
      const style = getComputedStyle(element);
      return {
        background: style.backgroundColor,
        borderColor: style.borderTopColor,
        borderStyle: style.borderTopStyle,
        borderWidth: style.borderTopWidth,
      };
    });
    expect(colorAlpha(frame.background), `${theme} removed post needs its own surface`).toBeGreaterThan(0);
    expect(colorAlpha(frame.borderColor), `${theme} removed post needs a visible frame`).toBeGreaterThan(0);
    expect(frame.borderStyle).toBe('dashed');
    expect(frame.borderWidth).toBe('1px');
    expect(
      contrastRatio(frame.borderColor, frame.background),
      `${theme} removed frame ${frame.borderColor} should be distinct from ${frame.background}`,
    ).toBeGreaterThanOrEqual(1.9);
    await capture(page, info, screenshot);
  }

  const dimensions = await page.evaluate(() => ({
    clientWidth: document.documentElement.clientWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(dimensions.scrollWidth, JSON.stringify(dimensions)).toBeLessThanOrEqual(dimensions.clientWidth);
  const axe = await new AxeBuilder({ page })
    .include('.post-stream')
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const blocking = axe.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''));
  expect(blocking, JSON.stringify(blocking, null, 2)).toEqual([]);
  expect(messages, 'unexpected console warnings/errors or HTTP failures').toEqual([]);
});
