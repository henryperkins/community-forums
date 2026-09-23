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

async function delayDmFonts(page: Page) {
  await page.addInitScript(() => {
    let resolveReady: () => void = () => {};
    const ready = new Promise<void>(resolve => { resolveReady = resolve; });
    Object.defineProperty(Document.prototype, 'fonts', {
      configurable: true,
      get: () => ({ ready }),
    });
    (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts = () => resolveReady();
  });
}

test('a late font load leaves a reader who scrolled away where they are', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await delayDmFonts(page);
  await openTallCounsel(page);
  await page.setViewportSize({ width: 800, height: 420 });
  const scroller = page.locator('[data-dm-scroll]');
  const overflow = await scroller.evaluate(node => node.scrollHeight - node.clientHeight);
  expect(overflow).toBeGreaterThan(120);
  // Resolve in the same task: the scroll event has not been delivered yet.
  await scroller.evaluate(node => {
    node.scrollTop = 0;
    (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts();
  });
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => resolve(undefined))));
  expect(await scroller.evaluate(node => node.scrollTop)).toBe(0);
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});

test('a late font load re-bottoms a reader who is still pinned', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await delayDmFonts(page);
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

test('an unknown recipient focuses the combobox', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  await page.locator('.dm-new-btn').click();
  await page.locator('.dm-dialog .dm-to-input').fill('unknown_member');
  await page.locator('.dm-dialog textarea[name=body]').fill('Preserve my letter after an eligibility error.');
  await page.locator('.dm-dialog .composer-send').click();
  await expect(page.locator('.dm-compose-details')).toHaveAttribute('open', '');
  await expect(page.locator('.dm-dialog')).toContainText('No member found');
  await expect(page.locator('.dm-dialog textarea[name=body]')).toHaveValue('Preserve my letter after an eligibility error.');
  await expect(page.locator('.dm-dialog .dm-to-input')).toBeFocused();
  await expect(page.locator('.dm-dialog input[name=to]')).toHaveAttribute('type', 'hidden');
  await expect(page.locator('.dm-dialog input[name=to]')).not.toHaveAttribute('autofocus', '');
});

test('a body error keeps focus on the textarea', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  await page.locator('.dm-new-btn').click();
  const to = page.locator('.dm-dialog .dm-to-input');
  await to.fill('bob');
  await to.press('Enter');
  await page.locator('.dm-dialog textarea[name=body]').evaluate(element => {
    element.removeAttribute('maxlength');
    (element as HTMLTextAreaElement).value = 'x'.repeat(5001);
  });
  // The client disables overlong submissions; submit directly to exercise the
  // server-rendered 422 and its focus target after enhancement.
  await page.locator('.dm-dialog form').evaluate(form => (form as HTMLFormElement).submit());
  await expect(page.locator('.dm-dialog')).toContainText('Your message is too long.');
  await expect(page.locator('.dm-dialog textarea[name=body]')).toBeFocused();
});

test('a closed compose dialog is not focused', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  await expect(page.locator('details.dm-compose-details')).not.toHaveAttribute('open', '');
  await expect(page.locator('.dm-dialog .dm-to-input')).not.toBeFocused();
});

test('the conversation poll waits for Retry-After on a 429', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) {
      await route.fulfill({
        status: 429,
        headers: { 'Retry-After': '30', 'Content-Type': 'application/json' },
        body: JSON.stringify({ error: 'rate_limited', retry_after: 30 }),
      });
      return;
    }
    await route.fulfill({ json: { html: '', presence: {}, dm_unread: 0, last_id: 0, has_more: false } });
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(20050);
  expect(calls).toBe(1);
  await page.clock.runFor(10000);
  await expect.poll(() => calls).toBe(2);
});

test('a tab return inside a Retry-After wait does not poll early', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) {
      await route.fulfill({
        status: 429,
        headers: { 'Retry-After': '30', 'Content-Type': 'application/json' },
        body: JSON.stringify({ error: 'rate_limited', retry_after: 30 }),
      });
      return;
    }
    await route.fulfill({ json: { html: '', presence: {}, dm_unread: 0, last_id: 0, has_more: false } });
  });
  const setHidden = (hidden: boolean) => page.evaluate(value => {
    Object.defineProperty(document, 'hidden', { configurable: true, value });
    document.dispatchEvent(new Event('visibilitychange'));
  }, hidden);
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(1000);
  await setHidden(true);
  await setHidden(false);
  await page.clock.runFor(1000);
  // Real time for a stray request to reach the route before asserting its absence.
  await page.waitForTimeout(250);
  expect(calls).toBe(1);
  await page.clock.runFor(27000);
  await page.waitForTimeout(250);
  expect(calls).toBe(1);
  await page.clock.runFor(1500);
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

test('a return while the throttled request is in flight obeys the deadline', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  let release!: () => void;
  const responseGate = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) {
      await responseGate;
      await route.fulfill({ status: 429, headers: { 'Retry-After': '30' }, json: { error: 'rate_limited', retry_after: 30 } });
    } else {
      await route.fulfill({ json: { html: '', presence: {}, dm_unread: 0, last_id: 0, has_more: false } });
    }
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.evaluate(() => {
    Object.defineProperty(document, 'hidden', { configurable: true, value: true });
    document.dispatchEvent(new Event('visibilitychange'));
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    document.dispatchEvent(new Event('visibilitychange'));
  });
  const response = page.waitForResponse(res => res.url().endsWith('/poll') && res.status() === 429);
  release();
  await response;
  await page.clock.runFor(1000);
  expect(calls).toBe(1);
  await page.clock.runFor(28000);
  expect(calls).toBe(1);
  await page.clock.runFor(1500);
  await expect.poll(() => calls).toBe(2);
});

test('catch-up and late fonts preserve a parked reader and the new-message pill', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await delayDmFonts(page);
  let release!: () => void;
  const responseGate = new Promise<void>(resolve => { release = resolve; });
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) await responseGate;
    const id = 900000 + calls;
    await route.fulfill({ json: {
      html: messageHtml(id), last_id: id, has_more: calls === 1,
      presence: {}, dm_unread: calls === 1 ? 1 : 0, other_last_read_message_id: null,
    } });
  });
  await openTallCounsel(page);
  const scroller = page.locator('[data-dm-scroll]');
  await scroller.evaluate(node => { node.scrollTop = 0; });
  release();
  await expect(page.locator('#m900002')).toBeAttached();
  await expect(page.locator('[data-dm-newpill]')).toHaveText(/2 new messages/);
  const top = await scroller.evaluate(node => node.scrollTop);
  expect(top).toBe(0);
  await page.evaluate(() => (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts());
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
  expect(await scroller.evaluate(node => node.scrollTop)).toBe(top);
  await expect(page.locator('[data-dm-newpill]')).toBeVisible();
});

test('a poll can receive its Retry-After deadline from the JSON body', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) {
      await route.fulfill({ status: 429, json: { error: 'rate_limited', retry_after: 30 } });
    } else {
      await route.fulfill({ json: { html: '', presence: {}, dm_unread: 0, last_id: 0, has_more: false } });
    }
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(20050);
  expect(calls).toBe(1);
  await page.clock.runFor(10000);
  await expect.poll(() => calls).toBe(2);
});
