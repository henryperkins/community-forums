import { test, expect, type Page } from '@playwright/test';

async function login(page: Page, who: string) {
  await page.goto('/login');
  await page.locator('[name=email]').fill(`${who}@retro.test`);
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function post(page: Page, url: string, fields: Record<string, string>) {
  const token = await page.locator('input[name=_token]').first().inputValue();
  return page.request.post(url, { form: { ...fields, _token: token }, maxRedirects: 0 });
}

// The browser seed has no conversations, so every test that needs one starts
// its own; nothing may depend on an earlier test (each must pass alone with -g).
async function startCounsel(page: Page, body = 'Open the counsel.'): Promise<string> {
  await login(page, 'alice');
  await page.goto('/messages/new');
  const started = await post(page, '/messages', { to: 'bob', body });
  expect(started.status()).toBe(303);
  return started.headers().location!;
}

async function openTallCounsel(page: Page): Promise<string> {
  await login(page, 'alice');
  await page.goto('/messages/new');
  const started = await post(page, '/messages', {
    to: 'bob',
    body: Array.from({ length: 40 }, (_, i) => `Counsel line ${i + 1}. The record stays with the people named here.`).join('\n\n'),
  });
  expect(started.status()).toBe(303);
  const route = started.headers().location!;
  await page.goto(route);
  await expect(page.locator('[data-dm-scroll]')).toBeVisible();
  return route;
}

test('a late font load leaves a reader who scrolled away where they are', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await page.addInitScript(() => {
    let resolveReady: () => void = () => {};
    const ready = new Promise<void>(resolve => { resolveReady = resolve; });
    Object.defineProperty(Document.prototype, 'fonts', {
      configurable: true,
      get: () => ({ ready }),
    });
    (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts = () => resolveReady();
  });
  await openTallCounsel(page);
  await page.setViewportSize({ width: 800, height: 420 });
  const scroller = page.locator('[data-dm-scroll]');
  const overflow = await scroller.evaluate(node => node.scrollHeight - node.clientHeight);
  expect(overflow).toBeGreaterThan(120);
  await scroller.evaluate(node => { node.scrollTop = 0; });
  await page.evaluate(() => (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts());
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => resolve(undefined))));
  expect(await scroller.evaluate(node => node.scrollTop)).toBe(0);
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});

test('a late font load re-bottoms a reader who is still pinned', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await page.addInitScript(() => {
    let resolveReady: () => void = () => {};
    const ready = new Promise<void>(resolve => { resolveReady = resolve; });
    Object.defineProperty(Document.prototype, 'fonts', {
      configurable: true,
      get: () => ({ ready }),
    });
    (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts = () => resolveReady();
  });
  await openTallCounsel(page);
  await page.setViewportSize({ width: 800, height: 420 });
  const scroller = page.locator('[data-dm-scroll]');
  await scroller.evaluate(node => {
    const extra = document.createElement('div');
    extra.dataset.fontGrowth = '1';
    extra.style.height = '240px';
    node.querySelector('[data-dm-messages]')!.appendChild(extra);
  });
  await page.evaluate(() => (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts());
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => resolve(undefined))));
  const gap = await scroller.evaluate(node => node.scrollHeight - node.scrollTop - node.clientHeight);
  expect(gap).toBeLessThan(90);
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});
