import { expect, test } from '@playwright/test';

for (const account of ['carol', 'alice', 'admin']) test(`${account} account actions fit and scroll at narrow and short viewports`, async ({ page, browser }, info) => {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill(account + '@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
  const results = [];
  for (const [width, height, font] of [[320, 740, 16], [844, 390, 16], [320, 740, 32], [844, 390, 32]]) {
    await page.setViewportSize({ width, height }); await page.goto('/t/2-share-your-favourite-keyboard-shortcuts');
    if (font === 32) await page.evaluate(() => {
      const sheet = Array.from(document.styleSheets).find(s => s.href?.includes('app-style'))!;
      sheet.insertRule(':root { font-size: 32px !important; }', sheet.cssRules.length);
    });
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const trigger = page.locator('.identity-menu > summary');
    const box = (await trigger.boundingBox())!;
    const point = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
    const hit = await page.evaluate(p => !!document.elementFromPoint(p.x, p.y)?.closest('.identity-menu > summary'), point);
    expect(hit).toBe(true);
    expect(await page.evaluate(p => document.elementFromPoint(p.x, p.y)?.matches('.identity-menu > summary'), point)).toBe(true);
    await page.mouse.click(point.x, point.y);
    const panel = page.locator('.identity-menu-panel');
    await expect(panel).toBeVisible();
    const bounds = (await panel.boundingBox())!;
    expect(bounds.y).toBeGreaterThanOrEqual(0);
    expect(bounds.x).toBeGreaterThanOrEqual(0);
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(width + 0.5);
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(height + 0.5);
    const logout = panel.getByRole('button', { name: 'Log out', exact: true });
    // WebKit pointer activation need not focus a summary. Start the independent
    // keyboard traversal from the same declared trigger in both engines.
    await trigger.focus();
    for (let i = 0; i < 12 && !(await logout.evaluate(el => document.activeElement === el)); i++) await page.keyboard.press('Tab');
    await expect(logout).toBeFocused();
    const last = (await logout.boundingBox())!;
    expect(last.y).toBeGreaterThanOrEqual(bounds.y);
    expect(last.y + last.height).toBeLessThanOrEqual(Math.min(height, bounds.y + bounds.height) + 0.5);
    results.push({ width, height, font, bounds, logout: last, directPointerHit: hit, scroll: await panel.evaluate(el => ({ top: el.scrollTop, height: el.clientHeight, content: el.scrollHeight })) });
    if (width === 844) await info.attach(`account-${font}px`, { body: await page.screenshot(), contentType: 'image/png' });
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
  }
  await info.attach('measurements', { body: JSON.stringify({ browser: browser.version(), account, results }, null, 2), contentType: 'application/json' });
});

test('native account menu remains keyboard reachable after pointer opening without JavaScript', async ({ page, browser }, info) => {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 844, height: 390 }, isMobile: true, hasTouch: true });
  await context.addCookies(await page.context().cookies());
  const plain = await context.newPage();
  await plain.goto(new URL('/t/2-share-your-favourite-keyboard-shortcuts', page.url()).href);
  const trigger = plain.locator('.identity-menu > summary');
  await trigger.click(); await trigger.focus(); await plain.keyboard.press('Tab');
  await expect(plain.locator('.identity-menu-panel a').first()).toBeFocused();
  const logout = plain.locator('.identity-menu-panel').getByRole('button', { name: 'Log out', exact: true });
  for (let i = 0; i < 12 && !(await logout.evaluate(el => document.activeElement === el)); i++) await plain.keyboard.press('Tab');
  await expect(logout).toBeFocused();
  const box = (await logout.boundingBox())!;
  expect(box.y).toBeGreaterThanOrEqual(0); expect(box.y + box.height).toBeLessThanOrEqual(390);
  await info.attach('native-menu', { body: await plain.screenshot(), contentType: 'image/png' });
  await context.close();
});

test('Create topic target is at least 44px in both axes without narrow overflow', async ({ page, browser }, info) => {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('carol@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  const results = [];
  for (const width of [320, 360, 390, 430]) {
    await page.setViewportSize({ width, height: 844 }); await page.goto('/compose?board=general');
    const button = page.locator('.compose-topic-form .composer-send');
    const box = (await button.boundingBox())!;
    expect(box.width).toBeGreaterThanOrEqual(44);
    expect(box.height).toBeGreaterThanOrEqual(44);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBe(width);
    results.push({ width, box });
  }
  await info.attach('target-measurements', { body: JSON.stringify({ browser: browser.version(), results }, null, 2), contentType: 'application/json' });
});
