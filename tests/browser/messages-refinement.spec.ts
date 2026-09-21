import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';

const evidence = path.resolve(__dirname, '../../docs/evidence/dm-reimagine/phase4');

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

async function shot(page: Page, name: string) {
  fs.mkdirSync(evidence, { recursive: true });
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => document.getAnimations().forEach(animation => {
    if (animation.effect?.getTiming().iterations !== Infinity) animation.finish();
  }));
  await page.evaluate(() => window.scrollTo(0, 0));
  if (name.includes('validation-no-js')) await page.locator('.dm-compose').evaluate(node => node.scrollTop = 0);
  await page.screenshot({ path: path.join(evidence, `${name}.png`), animations: 'disabled', fullPage: true });
}

test('refined Messages: responsive reading, picker, live incoming counsel, errors and no-JS', async ({ browser, baseURL }, info) => {
  test.skip(info.project.name !== 'desktop', 'This journey captures desktop, laptop and phone together.');
  test.setTimeout(120000);
  const seed = await browser.newContext({ baseURL, javaScriptEnabled: false });
  const sender = await seed.newPage();
  await login(sender, 'alice');
  await sender.goto('/messages/new');
  const initial = await post(sender, '/messages', { to: 'bob', body: 'Bob — the rollback drill is drafted. Please check the timing before it goes to the council.' });
  expect(initial.status()).toBe(303);
  const route = initial.headers().location;
  await sender.goto(route);
  const peerContext = await browser.newContext({ baseURL, javaScriptEnabled: false });
  const peer = await peerContext.newPage();
  await login(peer, 'bob');
  await peer.goto(route);
  expect((await post(peer, route, { body: 'Send it over. The `worker:packages` sweep should run after the flip.\n\n- Keep the digest window quiet.\n- Preserve the audit trail.' })).status()).toBe(303);
  for (let i = 0; i < 6; i++) {
    expect((await post(sender, route, { body: `Counsel ${i + 1}. The record remains available to everyone named here.\n\nThe rollback drill and the audit note must agree before we set the day.` })).status()).toBe(303);
  }
  const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 }, timezoneId: 'America/Los_Angeles' });
  const page = await context.newPage();
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await login(page, 'alice');
  await page.goto(route);
  await expect(page.locator('[data-dm-scroll]')).toBeVisible();
  await expect(page.locator('[data-rail-toggle]')).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.dm-inforail')).toBeHidden();
  await expect(page.locator('.dm-line')).toHaveCount(8);
  await expect(page.locator('.dm-group.mine')).toHaveCount(2);
  await expect(page.locator('[data-dm-presence]').first()).toContainText('Here now');
  await expect(page.locator('.dm-row.active')).not.toHaveClass(/is-unread/);
  for (const [width, height, theme, name] of [
    [1440, 1000, 'light', 'desktop-day'], [1280, 800, 'light', 'laptop-day'],
    [390, 844, 'light', 'phone-day'], [1440, 1000, 'dark', 'desktop-twilight'], [390, 844, 'dark', 'phone-twilight'],
  ] as const) {
    await page.setViewportSize({ width, height });
    await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
    await expect(page.locator('.dm-composer .composer-send')).toBeInViewport();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await shot(page, `conversation-${name}`);
  }
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.locator('[data-rail-toggle]').click();
  await expect(page.locator('.dm-inforail')).toBeVisible();
  await expect(page.locator('.dm-inforail')).toHaveCSS('position', 'fixed');
  await expect(page.locator('.dm-inforail')).toContainText('Regard');
  await shot(page, 'laptop-details-drawer');
  await page.keyboard.press('Escape');
  await expect(page.locator('[data-rail-toggle]')).toBeFocused();

  // Real incoming HTTP write and the real 20-second poll. Scrolling upwards
  // must keep both the reading position and the typed draft intact.
  await page.locator('.dm-composer textarea[name=body]').fill('Do not lose this unsent counsel.');
  await page.locator('[data-dm-scroll]').evaluate(node => node.scrollTop = 0);
  const oldTop = await page.locator('[data-dm-scroll]').evaluate(node => node.scrollTop);
  expect((await post(peer, route, { body: 'A live letter from Bob.' })).status()).toBe(303);
  await expect(page.getByText('A live letter from Bob.', { exact: true })).toBeVisible({ timeout: 25000 });
  await expect(page.locator('.dm-line', { hasText: 'A live letter from Bob.' }).locator('[data-copy-message]')).not.toHaveAttribute('hidden', '');
  await expect(page.locator('[data-dm-newpill]')).toBeVisible();
  expect(await page.locator('[data-dm-scroll]').evaluate(node => node.scrollTop)).toBe(oldTop);
  await expect(page.locator('.dm-composer textarea[name=body]')).toHaveValue('Do not lose this unsent counsel.');
  await shot(page, 'live-new-messages');
  await page.locator('[data-dm-newpill]').click();
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();

  await page.goto('/messages/new');
  await expect(page.locator('.dm-listpane .dm-row')).not.toHaveCount(0);
  const to = page.locator('.dm-compose .dm-to-input');
  await expect(page.getByRole('combobox', { name: 'To', exact: true })).toBeVisible();
  await to.fill('bo');
  await expect(page.locator('.dm-compose [role=option]').first()).toContainText('bob');
  await to.press('Enter');
  await expect(page.locator('.dm-compose [name=to]')).toHaveValue('bob');
  await to.fill('ca');
  await expect(page.locator('.dm-compose [role=option]').first()).toContainText('carol');
  await to.press('Enter');
  await expect(page.locator('.dm-compose [data-dm-group-title]')).toBeVisible();
  await expect(page.locator('.dm-compose [name=to]')).toHaveValue('bob, carol');
  await expect(page.getByRole('combobox', { name: 'To', exact: true })).toBeVisible();
  await shot(page, 'new-recipient-chips');
  await page.locator('.dm-compose button[aria-label^="Remove Bob"]').click();
  await expect(page.locator('.dm-compose [name=to]')).toHaveValue('carol');
  await page.goto('/messages');
  await page.locator('.dm-new-btn').click();
  await page.locator('.dm-dialog .dm-to-input').fill('unknown_member');
  await page.locator('.dm-dialog textarea[name=body]').fill('Preserve my letter after an eligibility error.');
  await page.locator('.dm-dialog .composer-send').click();
  await expect(page.locator('.dm-compose-details')).toHaveAttribute('open', '');
  await expect(page.locator('.dm-dialog')).toContainText('No member found');
  await expect(page.locator('.dm-dialog textarea[name=body]')).toHaveValue('Preserve my letter after an eligibility error.');
  await shot(page, 'dialog-validation-draft');

  // No-JS: full-width list, canonical recipient field, inline validation and
  // details opened and closed using the actual anchors.
  await sender.setViewportSize({ width: 390, height: 844 });
  await sender.goto('/messages');
  const listBox = await sender.locator('.dm-listpane').boundingBox();
  expect(listBox!.width).toBeGreaterThan(370);
  await shot(sender, 'phone-list-no-js');
  await sender.goto(route + '#dm-rail');
  await expect(sender.locator('.dm-inforail')).toBeInViewport();
  await sender.locator('[data-rail-close]').click();
  await expect(sender.locator('.dm-inforail')).toBeHidden();
  await sender.goto('/messages/new');
  await sender.locator('.dm-compose [name=to]').fill('nobody_here');
  await sender.locator('.dm-compose textarea[name=body]').fill('Keep the no-JS draft.');
  await sender.locator('.dm-compose .composer-send').click();
  await expect(sender.locator('.dm-compose textarea[name=body]')).toHaveValue('Keep the no-JS draft.');
  await expect(sender.locator('.dm-compose')).toContainText('No member found');
  await shot(sender, 'phone-validation-no-js');
  expect(errors).toEqual([]);
  await context.close(); await peerContext.close(); await seed.close();
});

test('Messages accessibility in day and twilight, including the recipient picker', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  for (const theme of ['light', 'dark']) {
    for (const route of ['/messages', '/messages/new']) {
      await page.goto(route);
      await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
      await page.evaluate(async () => Promise.all(document.getAnimations().filter(animation => animation.effect?.getTiming().iterations !== Infinity).map(animation => animation.finished)));
      if (route === '/messages/new') {
        await page.locator('.dm-compose .dm-to-input').fill('bo');
        await expect(page.locator('.dm-compose [role=option]').first()).toBeVisible();
      }
      const results = await new AxeBuilder({ page }).include('.dm-shell').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      expect(results.violations).toEqual([]);
    }
  }
});

test('reference states: group counsel, first visit and list', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages/new');
  const result = await post(page, '/messages', {
    to: 'bob, carol', title: 'Rollback drill — wardens',
    body: 'The rollback drill is ready for the wardens.\n\nPlease preserve the audit trail and the quiet digest window.',
  });
  expect(result.status()).toBe(303);
  await page.goto(result.headers().location);
  await expect(page.locator('.dm-thread-sub')).toContainText('3 in counsel');
  await expect(page.locator('.dm-receipt')).toHaveCount(0);
  for (const [width, height, theme, name] of [
    [1440, 1000, 'light', 'group-desktop-day'], [1280, 800, 'dark', 'group-laptop-twilight'], [390, 844, 'dark', 'group-phone-twilight'],
  ] as const) {
    await page.setViewportSize({ width, height });
    await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
    await shot(page, name);
    await page.locator('[data-rail-toggle]').click();
    await expect(page.locator('.dm-inforail .dm-owner-tool')).toHaveCount(2);
    await shot(page, name + '-details');
    const scan = await new AxeBuilder({ page }).include('.dm-inforail').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
    expect(scan.violations).toEqual([]);
    await page.locator('[data-rail-close]').click();
  }
  await page.goto('/messages');
  await shot(page, 'list-phone-twilight');
  await page.context().clearCookies();
  await login(page, 'nimrodel');
  await page.goto('/messages');
  await expect(page.locator('.dm-listpane')).toContainText('New accounts');
  for (const [width, height, theme, name] of [
    [1440, 1000, 'light', 'first-run-desktop-day'], [390, 844, 'dark', 'first-run-phone-twilight'],
  ] as const) {
    await page.setViewportSize({ width, height });
    await page.evaluate(theme => document.documentElement.dataset.theme = theme, theme);
    await shot(page, name);
    expect((await new AxeBuilder({ page }).include('.dm-shell').withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze()).violations).toEqual([]);
  }
});

test('Messages recipient commits discard delayed suggestions', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'The same picker is used at every viewport.');
  await page.addInitScript(() => {
    const originalFetch = window.fetch.bind(window);
    window.fetch = (resource, options) => {
      if (String(resource).includes('/composer/suggest?')) {
        // Complete even after cancellation: a response already being processed
        // must not reopen the picker after the member commits a raw username.
        return new Promise<Response>(resolve => {
          (window as any).resolveDmSuggestions = () => resolve(new Response(JSON.stringify({ items: [
            { token: '@bob', label: '@bob', meta: 'Bob Brooks' },
            { token: '@bobby', label: '@bobby', meta: 'Bobby' },
          ] }), { headers: { 'Content-Type': 'application/json' } }));
        });
      }
      return originalFetch(resource, options);
    };
  });
  await login(page, 'alice');
  for (const key of ['Enter', ',']) {
    await page.goto('/messages/new');
    const input = page.locator('.dm-compose .dm-to-input');
    const canonical = page.locator('.dm-compose [name=to]');
    await input.fill('bob');
    await expect.poll(() => page.evaluate(() => typeof (window as any).resolveDmSuggestions)).toBe('function');
    await input.press(key);
    await expect(canonical).toHaveValue('bob');
    await expect(input).toHaveValue('');
    await page.evaluate(() => (window as any).resolveDmSuggestions());
    await expect(input).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('.dm-compose .dm-suggest[role=listbox]')).toBeHidden();
    await input.press('Enter');
    await expect(canonical).toHaveValue('bob');
  }
});

test('conversation poll backs off, pauses while hidden, resumes immediately and stops on 404', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  const route = await page.locator('.dm-row').first().getAttribute('href');
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    expect(route.request().method()).toBe('POST');
    expect(new URLSearchParams(route.request().postData()!).get('_token')).toBeTruthy();
    await route.fulfill({ status: calls <= 2 ? 503 : calls === 4 ? 404 : 200,
      json: { html: '', presence: {}, dm_unread: 0, last_id: 0 } });
  });
  await page.clock.install();
  await page.goto(route!);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(20050);
  await expect.poll(() => calls).toBe(2);
  await page.clock.runFor(20050);
  expect(calls).toBe(2);
  await page.clock.runFor(20050);
  await expect.poll(() => calls).toBe(3);
  await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, value: true }); document.dispatchEvent(new Event('visibilitychange')); });
  await page.clock.runFor(60000);
  expect(calls).toBe(3);
  await page.evaluate(() => { Object.defineProperty(document, 'hidden', { configurable: true, value: false }); document.dispatchEvent(new Event('visibilitychange')); });
  await expect.poll(() => calls).toBe(4);
  await page.clock.runFor(120000);
  expect(calls).toBe(4);
});
