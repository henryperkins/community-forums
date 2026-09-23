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

const messageHtml = (id: number) =>
  `<div class="dm-group" data-dm-author="2" data-dm-date="2026-09-21">` +
  `<div class="dm-msgs"><div class="dm-ghead"><span class="dm-name">Bob</span>` +
  `<time datetime="2026-09-21T12:00:00Z">12:00</time></div>` +
  `<div class="dm-line" id="m${id}" data-message-id="${id}" data-created-at="2026-09-21T12:00:00Z">` +
  `<div class="dm-body formatted-content"><p>Page row ${id}</p></div></div></div></div>`;

test('a has_more page requests the next page without waiting for the interval', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages/new');
  const started = await post(page, '/messages', { to: 'bob', body: 'Open the counsel.' });
  expect(started.status()).toBe(303);
  const route = started.headers().location!;
  let calls = 0;
  const afters: string[] = [];
  await page.route('**/messages/*/poll', async route => {
    calls++;
    afters.push(new URLSearchParams(route.request().postData() || '').get('after') || '');
    if (calls === 1) {
      await route.fulfill({ json: {
        html: messageHtml(9001), last_id: 9001, has_more: true,
        presence: {}, dm_unread: 1, other_last_read_message_id: null,
      } });
      return;
    }
    await route.fulfill({ json: {
      html: messageHtml(9002), last_id: 9002, has_more: false,
      presence: {}, dm_unread: 0, other_last_read_message_id: null,
    } });
  });
  await page.goto(route);
  await expect.poll(() => calls, { timeout: 3000 }).toBe(2);
  expect(afters[1]).toBe('9001');
  await expect(page.locator('#m9001')).toBeAttached();
  await expect(page.locator('#m9002')).toBeAttached();
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});

test('a has_more page that does not advance the cursor waits for the interval', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    const after = new URLSearchParams(route.request().postData() || '').get('after') || '0';
    await route.fulfill({ json: {
      html: '', last_id: Number(after), has_more: true,
      presence: {}, dm_unread: 0, other_last_read_message_id: null,
    } });
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(3000);
  expect(calls).toBe(1);
  await page.clock.runFor(20050);
  await expect.poll(() => calls).toBe(2);
});

test('catch-up yields after twenty immediate follow-ups', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  const cursors: number[] = [];
  await page.route('**/messages/*/poll', async route => {
    calls++;
    const after = Number(new URLSearchParams(route.request().postData() || '').get('after'));
    cursors.push(after);
    await route.fulfill({ json: {
      html: '', last_id: after + 1, has_more: calls <= 21,
      presence: {}, dm_unread: 0, other_last_read_message_id: null,
    } });
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls, { timeout: 3000 }).toBe(21);
  await page.clock.runFor(1000);
  expect(calls).toBe(21);
  await page.clock.runFor(20000);
  await expect.poll(() => calls).toBe(22);
  expect(cursors.slice(1).every((cursor, index) => cursor === cursors[index] + 1)).toBe(true);
});

