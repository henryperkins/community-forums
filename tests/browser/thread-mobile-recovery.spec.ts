import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { mkdirSync } from 'node:fs';

const topic = '/t/30-ratified-decisions';
const body = 'A failed reply must survive Back, navigation, and reload.';
const captures = path.resolve(__dirname, '../../docs/evidence/thread-mobile-ux-2026-10-22/browser');

async function capture(page: Page, name: string): Promise<void> {
  mkdirSync(captures, { recursive: true });
  await page.screenshot({ path: path.join(captures, name + '.png') });
}

function setRich(enabled: boolean): void {
  execFileSync('php', ['-r', String.raw`
require 'vendor/autoload.php';
\App\Core\Env::load(getcwd() . '/.env');
$config = \App\Core\Config::fromFile(getcwd() . '/config/config.php');
$settings = new \App\Repository\SettingRepository(new \App\Core\Database($config->get('db')));
$features = $settings->get('features', []);
$features['rich_composer'] = true;
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`], { cwd: path.resolve(__dirname, '../..'), env: process.env });
}

test.beforeEach(() => {
  setRich(false);
  // The fixture account/topic is shared across this serial evidence file. Each
  // scenario starts without a remote draft left by the previous scenario.
  execFileSync('php', ['-r', String.raw`
require 'vendor/autoload.php';
\App\Core\Env::load(getcwd() . '/.env');
$config = \App\Core\Config::fromFile(getcwd() . '/config/config.php');
$db = new \App\Core\Database($config->get('db'));
$db->run('DELETE FROM server_drafts WHERE user_id = (SELECT id FROM users WHERE username = ?) AND context_key = ?', ['elladan', '~2Ft~2F30~2Freply']);
$limiter = new \App\Security\FileRateLimiter((string) $config->get('paths.ratelimit'));
$user = (new \App\Repository\UserRepository($db))->findByUsername('elladan');
if ($user) { $limiter->clear('rl:post:u' . $user['id']); }
`], { cwd: path.resolve(__dirname, '../..'), env: process.env });
});

async function openThread(page: Page): Promise<void> {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('elladan@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => !url.pathname.endsWith('/login'));
  await page.goto(topic);
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
}

test('failed navigation keeps the draft and restores a usable submit button', async ({ page, context }) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  await input.fill(body);
  const key = await page.locator('#reply [name=idempotency_key]').inputValue();
  const failed = page.waitForEvent('requestfailed', request => request.isNavigationRequest() && request.method() === 'POST');
  await context.setOffline(true);
  await page.locator('#reply .composer-send').click({ noWaitAfter: true });
  await failed;
  await context.setOffline(false);
  await page.goBack();
  await expect(input).toHaveValue(body);
  await expect(page.locator('#reply .composer-send')).toBeEnabled();
  await expect(page.locator('#reply')).not.toHaveAttribute('aria-busy', 'true');
  await expect(page.locator('#reply [name=idempotency_key]')).toHaveValue(key);
  await page.goto('/c/the-archive');
  await page.goto(topic);
  await expect(input).toHaveValue(body);
  await page.reload();
  await expect(input).toHaveValue(body);
});

test('confirmation clears only the submitted version and Back permits a new reply', async ({ page }) => {
  await openThread(page);
  const text = 'Confirmed mobile regression ' + Date.now();
  const input = page.locator('#reply textarea');
  await input.fill(text);
  const key = await page.locator('#reply [name=idempotency_key]').inputValue();
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('.post-body').filter({ hasText: text })).toHaveCount(1);
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(input).toHaveValue('');
  await expect.poll(() => page.evaluate(() => JSON.parse(sessionStorage.getItem('rb-draft:pending-submits') || '[]').some((item: { completed?: boolean }) => item.completed))).toBe(true);
  await page.goBack();
  await expect(page.locator('#reply [name=idempotency_key]')).not.toHaveValue(key);
  await expect(input).toHaveValue('');
  await input.fill('A new draft after confirmed send');
  await expect(page.locator('#reply .composer-send')).toBeEnabled();
  await page.goto('/c/the-archive');
  await page.goto(topic);
  await expect(input).toHaveValue('A new draft after confirmed send');
});

test('a later edit in another tab survives confirmation of the older draft', async ({ page, context }) => {
  await openThread(page);
  await page.locator('#reply textarea').fill('Older submitted version ' + Date.now());
  const sibling = await context.newPage();
  await sibling.goto(topic);
  await sibling.locator('#reply textarea').fill('Newer work in another tab');
  await page.locator('#reply .composer-send').click();
  await page.goto(topic);
  await expect(page.locator('#reply textarea')).toHaveValue('Newer work in another tab');
  await sibling.close();
});

test('enlarged header labels reflow and mobile prose spans beneath the byline', async ({ page }) => {
  await openThread(page);
  for (const width of [320, 390]) {
    await page.setViewportSize({ width, height: 844 });
    const before = await page.locator('.post-body').first().boundingBox();
    const post = await page.locator('.post').first().boundingBox();
    expect(before!.width).toBeGreaterThan(post!.width - 35);
    // Root-font emulation is a desktop proxy, not OS text scaling or page zoom.
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('DOM.enable');
    await cdp.send('CSS.enable');
    const { root } = await cdp.send('DOM.getDocument');
    const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: 'html' });
    await cdp.send('CSS.setEffectivePropertyValueForNode', { nodeId, propertyName: 'font-size', value: '32px' });
    expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(1);
    await page.locator('.thread-facts-actions').scrollIntoViewIfNeeded();
    await capture(page, 'header-enlarged-' + width);
    await cdp.send('CSS.setEffectivePropertyValueForNode', { nodeId, propertyName: 'font-size', value: '16px' });
    await cdp.detach();
    await page.locator('.post').first().scrollIntoViewIfNeeded();
    await capture(page, 'compact-byline-' + width);
  }
});

test('landscape preview leaves Minimize and Reply reachable below the header', async ({ page }, info) => {
  await page.setViewportSize({ width: 844, height: 390 });
  await openThread(page);
  await page.locator('#reply textarea').fill('Landscape reply with preview\n\n' + 'A paragraph of useful detail. '.repeat(30));
  const preview = page.locator('#reply .composer-preview-toggle');
  if (await preview.getAttribute('aria-expanded') !== 'true') await preview.click();
  await expect(page.locator('#reply .composer-preview')).toBeVisible();
  for (const selector of ['#reply [data-composer-minimize]', '#reply .composer-send']) {
    const result = await page.locator(selector).evaluate(element => {
      const rect = element.getBoundingClientRect();
      const header = document.querySelector('.forum-bar')!.getBoundingClientRect();
      const hit = document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
      return { top: rect.top, bottom: rect.bottom, headerBottom: header.bottom, visibleHeight: innerHeight, hit: element === hit || element.contains(hit), hitClass: hit?.className };
    });
    expect(result.top).toBeGreaterThanOrEqual(result.headerBottom);
    expect(result.bottom).toBeLessThanOrEqual(result.visibleHeight);
    expect(result.hit, JSON.stringify({ selector, result })).toBe(true);
  }
  await capture(page, 'landscape-preview-' + info.project.name);
  await page.locator('.identity-menu > summary').click();
  const accountItem = page.locator('.identity-menu-panel a[href="/drafts"]');
  await expect(accountItem).toBeVisible();
  expect(await accountItem.evaluate(element => {
    const r = element.getBoundingClientRect();
    const hit = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
    return element === hit || element.contains(hit);
  }), 'Account menu remains above the expanded dock').toBe(true);
  await page.locator('.identity-menu > summary').click();
  await page.locator('#reply [data-composer-minimize]').click();
  await expect(page.locator('#reply')).not.toHaveClass(/is-expanded/);
});

test('reaction choices have visible names before activation', async ({ page }, info) => {
  await openThread(page);
  const post = page.locator('.post').first();
  if (info.project.name === 'desktop') await post.hover();
  const menu = post.locator(info.project.name === 'desktop' ? '.post-toolbar-reactions' : '[data-post-menu]');
  await menu.locator('summary').click();
  for (const name of ['Commend', 'Heart', 'Laugh']) {
    const label = menu.locator('.reaction-name').filter({ hasText: name }).first();
    await expect(label).toBeVisible();
    expect(await label.evaluate(element => element.classList.contains('sr-only'))).toBe(false);
  }
  await capture(page, 'reaction-names-' + info.project.name);
});

test('rich editor recovers a failed send and submits the retained draft exactly once', async ({ page, context }) => {
  setRich(true);
  await openThread(page);
  const editor = page.locator('#reply .ProseMirror');
  await expect(editor).toBeVisible();
  const text = 'Rich recovery ' + Date.now();
  await editor.fill(text);
  const failed = page.waitForEvent('requestfailed', request => request.isNavigationRequest() && request.method() === 'POST');
  await context.setOffline(true);
  await page.locator('#reply .composer-send').click({ noWaitAfter: true });
  await failed;
  await context.setOffline(false);
  await page.goBack();
  await expect(editor).toHaveText(text);
  await expect(page.locator('#reply .composer-send')).toBeEnabled();
  await page.goto('/c/the-archive');
  await page.goto(topic);
  await expect(editor).toHaveText(text);
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(editor).toHaveText('');
  await expect(page.locator('.post-body').filter({ hasText: text })).toHaveCount(1);
});

test('a later draft may deliberately repeat a confirmed reply', async ({ page }) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  const text = 'Same words ' + Date.now();
  await input.fill(text);
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(input).toHaveValue('');
  await input.fill(text);
  await page.goto('/c/the-archive');
  await page.goto(topic);
  await expect(input).toHaveValue(text);
});

test('editing after a committed response is lost uses a fresh submission key', async ({ page }) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  const first = 'Committed before disconnect ' + Date.now();
  const edited = first + ' with new text';
  await input.fill(first);
  const firstKey = await page.locator('#reply [name=idempotency_key]').inputValue();
  await page.route('**/t/30/reply', async route => {
    // The app commits the real POST; only delivery of its response is lost.
    const response = await route.fetch({ maxRedirects: 0 });
    expect([302, 303]).toContain(response.status());
    // route.fetch shares the context cookie jar; a genuinely lost response
    // would not deliver its completion cookie to the browser.
    await page.context().clearCookies({ name: 'rb_composer_completed' });
    await route.abort('internetdisconnected');
  });
  const failed = page.waitForEvent('requestfailed', request => request.isNavigationRequest() && request.method() === 'POST');
  await page.locator('#reply .composer-send').click({ noWaitAfter: true });
  await failed;
  await page.unroute('**/t/30/reply');
  await page.goBack();
  await expect(input).toHaveValue(first);
  await input.fill(edited);
  await expect(page.locator('#reply [name=idempotency_key]')).not.toHaveValue(firstKey);
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('.post-body').filter({ hasText: edited })).toHaveCount(1);
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(input).toHaveValue('');
});

test('rich history cleanup preserves a newer draft in another tab after settling', async ({ page, context }) => {
  setRich(true);
  await openThread(page);
  const editor = page.locator('#reply .ProseMirror');
  await editor.fill('Rich confirmed version ' + Date.now());
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(editor).toHaveText('');
  const sibling = await context.newPage();
  await sibling.goto(topic);
  await sibling.locator('#reply .ProseMirror').fill('Newer sibling rich draft');
  await page.goBack();
  await expect(editor).toHaveText('');
  // A live server-draft acknowledgement observes editor debounce/settlement too.
  await expect(sibling.locator('.composer-draft-sync')).toContainText('Saved to server drafts.');
  await page.goto('/c/the-archive');
  await page.goto(topic);
  await expect(editor).toHaveText('Newer sibling rich draft');
  await sibling.close();
});

test('multiple completed history entries cannot reuse an older consumed key', async ({ page }) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  const keys: string[] = [];
  for (const label of ['First history send', 'Second history send']) {
    await input.fill(label + ' ' + Date.now());
    keys.push(await page.locator('#reply [name=idempotency_key]').inputValue());
    await page.locator('#reply .composer-send').click();
    await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
    await expect(input).toHaveValue('');
  }
  await page.goBack();
  await expect(input).toHaveValue('');
  await page.goBack();
  await expect(input).toHaveValue('');
  const latest = 'New work after two Back steps ' + Date.now();
  await input.fill(latest);
  const current = await page.locator('#reply [name=idempotency_key]').inputValue();
  expect(keys).not.toContain(current);
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('.post-body').filter({ hasText: latest })).toHaveCount(1);
});

test('a late multiline autosave from the confirmed submission is not restored', async ({ page }) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  const text = 'Late autosave ' + Date.now() + '\n\nSecond paragraph';
  await input.fill(text);
  const key = await page.locator('#reply [name=idempotency_key]').inputValue();
  let committed = false;
  page.on('response', response => {
    if (response.request().method() === 'POST' && new URL(response.url()).pathname === '/t/30/reply') committed = true;
  });
  await page.route('**/api/drafts/~2Ft~2F30~2Freply', async route => {
    if (!committed || route.request().method() !== 'GET') return route.continue();
    // Isolate the delayed autosave mechanism, including multipart newline transport.
    await route.fulfill({ json: { draft: { revision: 1, body: text.replace(/\n/g, '\r\n'), metadata: { submission: key } } } });
  });
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(input).toHaveValue('');
  await expect(page.locator('#reply .composer-draft-sync')).not.toContainText('Loaded server draft.');
});
