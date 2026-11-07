import { expect, test, type Locator, type Page } from '@playwright/test';

// Uses the standard disposable browser fixture. Each case owns one page and
// can run separately per project; no database helpers or feature overrides.
test.use({ reducedMotion: 'reduce' });
test.beforeEach(({ baseURL }) => {
  if (!baseURL || !['localhost', '127.0.0.1', '[::1]'].includes(new URL(baseURL).hostname)) {
    throw new Error('Forum control evidence requires the local browser fixture.');
  }
});

async function visit(page: Page, route: string): Promise<void> {
  const response = await page.goto(route);
  expect(response?.status(), route).toBeLessThan(400);
}

async function login(page: Page): Promise<void> {
  await visit(page, '/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function expectKeyboardFocus(control: Locator): Promise<void> {
  await expect(control).toBeFocused();
  const outline = await control.evaluate(element => {
    const style = getComputedStyle(element);
    return { style: style.outlineStyle, width: parseFloat(style.outlineWidth) };
  });
  expect(outline.style).not.toBe('none');
  expect(outline.width).toBeGreaterThanOrEqual(2);
}

test('Forum account menu and mobile board drawer restore keyboard focus', async ({ page }, info) => {
  await login(page);
  await visit(page, '/');
  await expect(page.locator('body')).toHaveAttribute('data-nav-ready', '1');

  if (page.viewportSize()!.width <= 860) {
    const opener = page.locator('[data-nav-toggle]');
    const rail = page.getByRole('navigation', { name: 'Boards', exact: true });
    const close = rail.getByRole('link', { name: 'Close board rail', exact: true });
    const main = page.locator('#main');
    await expect(opener).toBeVisible();
    await expect(rail).toBeHidden();
    await opener.focus();
    await page.keyboard.press('Enter');
    await expect(opener).toHaveAttribute('aria-expanded', 'true');
    await expect(main).toHaveJSProperty('inert', true);
    await expectKeyboardFocus(close);

    // The home rail consists of links. Check both ends of its Tab loop while
    // the covered main content is inert, including the final presence link.
    const lastLink = rail.locator('a[href]:visible').last();
    await page.keyboard.press('Shift+Tab');
    await expect(lastLink).toBeFocused();
    await page.keyboard.press('Tab');
    await expectKeyboardFocus(close);
    await page.screenshot({ path: info.outputPath('forum-mobile-drawer.png'), animations: 'disabled' });
    await page.keyboard.press('Escape');
    await expect(rail).toBeHidden();
    await expect(opener).toHaveAttribute('aria-expanded', 'false');
    await expect(main).toHaveJSProperty('inert', false);
    await expectKeyboardFocus(opener);

    await page.keyboard.press('Space');
    await expect(close).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(rail).toBeHidden();
    await expect(main).toHaveJSProperty('inert', false);
    await expectKeyboardFocus(opener);
  }

  const account = page.locator('details.identity-menu');
  const summary = account.locator(':scope > summary');
  await summary.focus();
  await page.keyboard.press('Space');
  await expect(account).toHaveJSProperty('open', true);
  await page.keyboard.press('Tab');
  await expectKeyboardFocus(account.getByRole('link', { name: 'Profile', exact: true }));
  await page.keyboard.press('Escape');
  await expect(account).toHaveJSProperty('open', false);
  await expectKeyboardFocus(summary);
});

test('Forum emoji dialog contains keyboard focus and returns it to the right control', async ({ page }, info) => {
  await login(page);
  await visit(page, '/compose?board=general');
  const form = page.locator('form.compose-topic-form');
  const editor = form.locator('textarea[name="body"]');
  const trigger = form.getByRole('button', { name: 'Emoji', exact: true });
  await expect(trigger).toBeVisible();
  await expect(editor).toBeVisible();
  await editor.fill('Local keyboard focus fixture.');
  await trigger.focus();
  await page.keyboard.press('Enter');

  const dialog = form.getByRole('dialog', { name: 'Emoji', exact: true });
  const search = dialog.getByRole('searchbox', { name: 'Search emoji', exact: true });
  const close = dialog.getByRole('button', { name: 'Close emoji', exact: true });
  await expect(dialog).toBeVisible();
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  await expect(trigger).toHaveAttribute('aria-expanded', 'true');
  await expect(trigger).toHaveAttribute('aria-controls', (await dialog.getAttribute('id'))!);
  await expectKeyboardFocus(search);
  await expect(dialog.getByRole('grid', { name: 'Smileys & emotion', exact: true })).toBeVisible();
  const bounds = await dialog.boundingBox();
  expect(bounds).not.toBeNull();
  expect(bounds!.x).toBeGreaterThanOrEqual(0);
  expect(bounds!.y).toBeGreaterThanOrEqual(0);
  expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(page.viewportSize()!.width);
  expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(page.viewportSize()!.height);
  await page.screenshot({ path: info.outputPath('forum-emoji-dialog.png'), animations: 'disabled' });

  await page.keyboard.press('Shift+Tab');
  await expectKeyboardFocus(close);
  await page.keyboard.press('Shift+Tab');
  await expect(dialog.locator('.composer-emoji-cell[tabindex="0"]').last()).toBeFocused();
  await page.keyboard.press('Tab');
  await expect(close).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(trigger).toHaveAttribute('aria-expanded', 'false');
  await expectKeyboardFocus(trigger);

  // The explicit close button also returns to the trigger.
  await page.keyboard.press('Space');
  await expect(search).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  await expect(close).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(dialog).toBeHidden();
  await expectKeyboardFocus(trigger);

  // Choosing an emoji returns to the editing task, preserving the draft.
  await page.keyboard.press('Enter');
  await search.fill('tada');
  const party = dialog.getByRole('gridcell', { name: 'Party popper', exact: true });
  await expect(party).toBeVisible();
  await party.focus();
  await page.keyboard.press('Enter');
  await expect(dialog).toBeHidden();
  await expect(editor).toBeFocused();
  await expect(editor).toHaveValue('Local keyboard focus fixture.🎉');
});
