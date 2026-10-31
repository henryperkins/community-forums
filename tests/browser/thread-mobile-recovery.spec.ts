import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { mkdirSync, writeFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { draftFailureEvidence } from './draft-failure-evidence';

test.afterEach(async ({ page }, info) => draftFailureEvidence(page, info));

const topic = '/t/30-ratified-decisions';
const body = 'A failed reply must survive Back, navigation, and reload.';
const captures = process.env.RB_EVIDENCE_DIR
  ? path.resolve(__dirname, '../..', process.env.RB_EVIDENCE_DIR)
  : path.resolve(__dirname, '../../docs/evidence/thread-mobile-ux-2026-10-22/browser');

async function capture(page: Page, name: string): Promise<void> {
  mkdirSync(captures, { recursive: true });
  await page.screenshot({ path: path.join(captures, page.context().browser()?.browserType().name() ?? 'browser', name + '.png') });
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
if ($user) {
    $limiter->clear('rl:post:u' . $user['id']);
    (new \App\Repository\UserPreferenceRepository($db))->merge((int) $user['id'], ['theme' => 'system']);
}
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

test('failed navigation keeps the draft and restores a usable submit button', async ({ page, context, browserName }) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  await input.fill(body);
  const key = await page.locator('#reply [name=idempotency_key]').inputValue();
  const failed = page.waitForEvent('requestfailed', request => request.isNavigationRequest() && request.method() === 'POST');
  await context.setOffline(true);
  await page.locator('#reply .composer-send').click({ noWaitAfter: true });
  await failed;
  await context.setOffline(false);
  // WebKit retains the original document after this cancelled navigation;
  // Chromium adds an error history entry. Exercise the corresponding recovery.
  if (browserName === 'webkit') await page.reload();
  else await page.goBack();
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

test('enlarged header labels reflow and mobile prose spans beneath the byline', async ({ page, browserName }) => {
  test.skip(browserName !== 'chromium', 'This existing root-font proxy uses Chromium CDP; the separate WebKit baseline probe is retained in the remediation evidence.');
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

test('rich editor recovers a failed send and submits the retained draft exactly once', async ({ page, context, browserName }) => {
  setRich(true);
  await openThread(page);
  const editor = page.locator('#reply .ProseMirror');
  await expect(editor).toBeVisible();
  const text = 'Rich recovery ' + Date.now();
  await editor.fill(text);
  const originalKey = await page.locator('#reply [name=idempotency_key]').inputValue();
  const failed = page.waitForEvent('requestfailed', request => request.isNavigationRequest() && request.method() === 'POST');
  await context.setOffline(true);
  await page.locator('#reply .composer-send').click({ noWaitAfter: true });
  await failed;
  await context.setOffline(false);
  if (browserName === 'webkit') await page.reload();
  else await page.goBack();
  await expect(editor).toHaveText(text);
  await expect(page.locator('#reply [name=idempotency_key]')).toHaveValue(originalKey);
  await expect(page.locator('#reply .composer-send')).toBeEnabled();
  await page.goto('/c/the-archive');
  await page.goto(topic);
  await expect(editor).toHaveText(text);
  await expect(page.locator('#reply [name=idempotency_key]')).toHaveValue(originalKey);
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

test('editing after a committed response is lost uses a fresh submission key', async ({ page, browserName }) => {
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
  // WebKit's aborted navigation retains the original document; Chromium
  // creates an error history entry that the member must leave with Back.
  if (browserName === 'chromium') await page.goBack();
  await expect(input).toHaveValue(first);
  await input.fill(edited);
  await expect(page.locator('#reply [name=idempotency_key]')).not.toHaveValue(firstKey);
  await page.locator('#reply .composer-send').click();
  await expect(page.locator('.post-body').filter({ hasText: edited })).toHaveCount(1);
  await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
  try {
    await expect(input).toHaveValue('');
  } catch (error) {
    const client = await page.evaluate(() => ({
      pending: JSON.parse(sessionStorage.getItem('rb-draft:pending-submits') || '[]'),
      body: localStorage.getItem('rb-draft:elladan:/t/30/reply'),
      state: localStorage.getItem('rb-draft-state:elladan:/t/30/reply'),
    }));
    const server = await (await page.request.get('/api/drafts/~2Ft~2F30~2Freply')).json();
    mkdirSync(captures, { recursive: true });
    writeFileSync(path.join(captures, 'retry-cleanup-state-' + page.context().browser()?.browserType().name() + '.json'), JSON.stringify({ client, server }, null, 2));
    throw error;
  }
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

for (const theme of ['light', 'dark']) {
  test('active thread labels meet normal-text contrast in ' + theme, async ({ page }, info) => {
    await openThread(page);
    // Use the real saved appearance and a fresh render. Switching the root
    // attribute mid-page leaves offscreen WebKit background transitions pending.
    await page.goto('/settings/appearance');
    await page.locator(`input[name="theme"][value="${theme}"]`).check({ force: true });
    await page.getByRole('button', { name: 'Save appearance' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    await page.goto(topic);
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    const themeState = await page.locator('.star-btn.star-on, .reaction-on').evaluateAll(elements =>
      elements.map(element => ({
        control: element.className,
        background: getComputedStyle(element).backgroundColor,
        color: getComputedStyle(element.querySelector('.reaction-n') || element).color,
      })));
    const results = await new AxeBuilder({ page })
      .include('.star-btn.star-on')
      .include('.reaction-on .reaction-n')
      .withRules(['color-contrast'])
      .analyze();
    expect(await page.locator('.star-btn.star-on').count()).toBeGreaterThan(0);
    expect(await page.locator('.reaction-on .reaction-n').count()).toBeGreaterThan(0);
    await info.attach('contrast-' + theme, { body: JSON.stringify(results), contentType: 'application/json' });
    mkdirSync(captures, { recursive: true });
    writeFileSync(path.join(captures, 'contrast-' + theme + '-' + info.project.name + '.json'), JSON.stringify({
      themeState, violations: results.violations, incomplete: results.incomplete,
      passes: results.passes.filter(rule => rule.id === 'color-contrast'),
    }, null, 2));
    await capture(page, 'active-labels-' + theme + '-' + info.project.name);
    expect(results.violations).toEqual([]);
    expect(results.incomplete).toEqual([]);
  });
}

test('keyboard reaches and scrolls the complete plain reply preview', async ({ page }, info) => {
  await openThread(page);
  const input = page.locator('#reply textarea');
  const text = Array.from({ length: 18 }, (_, i) => 'Preview paragraph ' + (i + 1) + ': A complete review must be possible using the keyboard.').join('\n\n');
  await input.fill(text);
  await expect(page.locator('#reply .composer-preview')).toContainText('Preview paragraph 18:');
  const box = page.locator('#reply [data-composer-after-box]');
  expect(await box.evaluate(e => e.scrollHeight > e.clientHeight)).toBe(true);
  await input.focus();
  let reached = false;
  for (let i = 0; i < 18; i++) {
    await page.keyboard.press('Tab');
    if (await box.evaluate(e => e === document.activeElement)) { reached = true; break; }
  }
  expect(reached, 'Tab must enter the overflow region without requiring an incidental link or conflict button').toBe(true);
  await expect(box).toHaveAccessibleName(/preview/i);
  const before = await box.evaluate(e => e.scrollTop);
  await page.keyboard.press('PageDown');
  await expect.poll(() => box.evaluate(e => e.scrollTop)).toBeGreaterThan(before);
  const focusStyle = await box.evaluate(e => ({ style: getComputedStyle(e).outlineStyle, width: parseFloat(getComputedStyle(e).outlineWidth) }));
  expect(focusStyle.style).not.toBe('none');
  expect(focusStyle.width).toBeGreaterThanOrEqual(2);
  await capture(page, 'keyboard-preview-' + info.project.name);
  await page.locator('#reply .composer-preview-toggle').click();
  await expect(box).not.toHaveAttribute('tabindex', '0');
});

async function currentRemoteDraft(page: Page) {
  const response = await page.request.get('/api/drafts/~2Ft~2F30~2Freply', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  expect(response.ok()).toBe(true);
  return (await response.json()).draft;
}

async function makeRichConflict(page: Page) {
  setRich(true);
  await openThread(page);
  const editor = page.locator('#reply .ProseMirror');
  await editor.fill('Initial local rich revision.');
  await expect(page.locator('#reply .composer-draft-sync')).toContainText('Saved to server drafts.');
  const draft = await currentRemoteDraft(page);
  const token = await page.locator('#reply [name=_token]').inputValue();
  const remote = await page.request.post('/api/drafts/~2Ft~2F30~2Freply', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    multipart: { _token: token, revision: String(draft.revision), body: 'A different remote revision.', title: 'Remote fixture', metadata: JSON.stringify({ context: '/t/30/reply' }) },
  });
  expect(remote.ok()).toBe(true);
  await editor.fill('Local text when the conflict appeared.');
  await expect(page.getByRole('button', { name: 'Save local as next revision', exact: true })).toBeVisible();
  return editor;
}

test('saving a rich conflict uses the latest local edits', async ({ page }, info) => {
  const editor = await makeRichConflict(page);
  const latest = 'Newest local revision after the conflict — café, 你好.';
  await editor.fill(latest);
  const saved = page.waitForResponse(r => r.request().method() === 'POST' && new URL(r.url()).pathname === '/api/drafts/~2Ft~2F30~2Freply' && r.status() === 200);
  await page.getByRole('button', { name: 'Save local as next revision', exact: true }).click();
  const response = await saved;
  const result = await response.json();
  await info.attach('saved-conflict', { body: JSON.stringify({ sent: latest, response: result, visible: await editor.innerText(), remote: await currentRemoteDraft(page) }), contentType: 'application/json' });
  expect(result.draft.body).toBe(latest);
  await expect(editor).toHaveText(latest);
});

test('rapid rich edit after resolving a conflict survives offline Back', async ({ page, context, browserName }, info) => {
  test.skip(browserName !== 'chromium', 'This scenario requires Chromium navigation to an offline error history entry.');
  const editor = await makeRichConflict(page);
  await page.getByRole('button', { name: 'Save local as next revision', exact: true }).click();
  const latest = 'Rapid rich draft after conflict resolution — café, 你好.';
  await editor.fill(latest);
  await expect(page.locator('#reply textarea')).toHaveValue(latest);
  const key = await page.locator('#reply [name=idempotency_key]').inputValue();
  const failed = page.waitForEvent('requestfailed', r => r.isNavigationRequest() && r.method() === 'POST');
  await context.setOffline(true);
  await page.locator('#reply .composer-send').click({ noWaitAfter: true });
  await failed;
  await context.setOffline(false);
  await page.goBack();
  await editor.waitFor();
  const immediate = await page.locator('#reply').evaluate((form: HTMLFormElement) => ({
    rich: (form.querySelector('.ProseMirror') as HTMLElement)?.innerText.trim(),
    textarea: (form.querySelector('textarea') as HTMLTextAreaElement)?.value,
    key: (form.querySelector('[name=idempotency_key]') as HTMLInputElement)?.value,
    local: localStorage.getItem('rb-draft:elladan:/t/30/reply'),
    busy: form.getAttribute('aria-busy'),
  }));
  await info.attach('immediate-back-state', { body: JSON.stringify({ expected: latest, expectedKey: key, immediate }), contentType: 'application/json' });
  expect(immediate.rich).toBe(latest);
  await expect(page.locator('#reply .composer-send')).toBeEnabled();
  await expect(page.locator('#reply [name=idempotency_key]')).toHaveValue(key);
  await expect(editor).toHaveText(latest);
});

for (const editWhileLoading of [false, true]) {
  test('delayed rich recovery ' + (editWhileLoading ? 'preserves new typing' : 'replaces a stale native snapshot'), async ({ page, context, browserName }) => {
    test.skip(browserName !== 'chromium', 'This scenario requires Chromium navigation to an offline error history entry.');
    const editor = await makeRichConflict(page);
    await page.getByRole('button', { name: 'Save local as next revision', exact: true }).click();
    const latest = 'The latest submitted rich draft — café, 你好.';
    await editor.fill(latest);
    const key = await page.locator('#reply [name=idempotency_key]').inputValue();
    const moduleUrl = new URL((await page.locator('body').getAttribute('data-wysiwyg-src'))!, page.url()).href;
    const failed = page.waitForEvent('requestfailed', r => r.isNavigationRequest() && r.method() === 'POST');
    await context.setOffline(true);
    await page.locator('#reply .composer-send').click({ noWaitAfter: true });
    await failed;
    await context.setOffline(false);

    let release!: () => void;
    let requested!: () => void;
    const gate = new Promise<void>(resolve => { release = resolve; });
    const requestStarted = new Promise<void>(resolve => { requested = resolve; });
    await page.route(moduleUrl, async route => { requested(); await gate; await route.continue(); });
    const newer = 'New typing while the rich editor is loading.';
    try {
      await page.goBack();
      await requestStarted;
      const input = page.locator('#reply textarea');
      await expect(input).toBeVisible();
      // Reproduce the stale native form snapshot captured in the failing Back
      // trace. Keep real local storage, history and the failed-submit journal.
      await input.evaluate((element: HTMLTextAreaElement) => { element.value = 'Local text when the conflict appeared.'; });
      if (editWhileLoading) await input.fill(newer);
    } finally {
      release();
    }
    await expect(page.locator('#reply textarea')).toHaveAttribute('data-rb-enhanced', '1');
    if (editWhileLoading) {
      // Enhancement keeps a focused native textarea in source mode.
      await expect(page.locator('#reply textarea')).toHaveValue(newer);
      await page.getByRole('button', { name: 'Rich text', exact: true }).click();
    }
    await editor.waitFor();
    expect(await editor.innerText()).toBe(editWhileLoading ? newer : latest);
    await expect(page.locator('#reply .composer-send')).toBeEnabled();
    if (editWhileLoading) await expect(page.locator('#reply [name=idempotency_key]')).not.toHaveValue(key);
    else await expect(page.locator('#reply [name=idempotency_key]')).toHaveValue(key);
  });
}
