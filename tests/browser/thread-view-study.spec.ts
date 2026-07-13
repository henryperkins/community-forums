import { expect, test, type Page, type TestInfo } from '@playwright/test';
import path from 'node:path';

const EVIDENCE_DIR = path.resolve(__dirname, '..', '..', 'docs/evidence/browser');

async function shot(page: Page, info: TestInfo, name: '80-thread-study' | '81-thread-tools'): Promise<void> {
  await expect(page.locator('.error-card')).toHaveCount(0);
  await page.screenshot({
    path: path.join(EVIDENCE_DIR, info.project.name, `${name}.png`),
    fullPage: true,
    animations: 'disabled',
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

  if (info.project.name === 'desktop') {
    await openRestructure();
    await page.locator('[data-thread-restructure-scrim]').click({ position: { x: 5, y: 5 } });
    await expect(dialog).toBeHidden();
    await expect(topicTrigger).toBeFocused();
  }
});

test('post menus are exclusive, dismiss outside, and open real disclosures safely', async ({ page }) => {
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
  await reading.locator('article[data-post]').nth(1).hover();
  await reading.locator('article[data-post]').nth(1).getByRole('button', { name: 'Quote in your reply' }).click();
  const quotedValue = await reply.inputValue();
  expect(quotedValue.match(/^> /gm) ?? []).toHaveLength(1);
  expect(quotedValue).toMatch(/^> [^\n]+\n\n$/);
  await expect(reply).toBeFocused();
  await expect(page.locator('html')).toHaveCSS('--keyboard-inset', /\d+px/);
});

test('Study layout matches desktop and mobile geometry', async ({ page }, info) => {
  if (info.project.name === 'desktop') {
    await page.setViewportSize({ width: 1280, height: 1200 });
  }
  await login(page);
  await openSeedTopic(page);

  const thread = page.locator('[data-thread-study]');
  const box = await thread.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.width).toBeLessThanOrEqual(860);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  await shot(page, info, '80-thread-study');

  if (info.project.name === 'desktop') {
    await page.setViewportSize({ width: 1280, height: 800 });
  }

  await page.getByRole('button', { name: 'Topic tools' }).click();
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
    const viewport = page.viewportSize();
    expect(viewport).not.toBeNull();
    expect(Math.abs((toolsBox!.x + toolsBox!.width) - viewport!.width)).toBeLessThanOrEqual(2);
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
  const toolbar = page.locator('article[data-post]').first().locator('[data-post-toolbar]');
  await expect(toolbar).toBeVisible();
  await expect(toolbar).toHaveCSS('opacity', '1');
  const targetBoxes = await toolbar.locator('button:visible, summary:visible').evaluateAll((items) => items.map((item) => {
    const box = item.getBoundingClientRect();
    return { width: box.width, height: box.height };
  }));
  expect(targetBoxes.length).toBeGreaterThan(0);
  expect(targetBoxes.every((item) => item.width >= 44 && item.height >= 44)).toBe(true);
});

test('reduced motion removes Study animations', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await login(page);
  await openSeedTopic(page);
  await page.getByRole('button', { name: 'Topic tools' }).click();
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
    const toolsContrast = await page.getByRole('button', { name: 'Topic tools' }).evaluate((element) => {
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
