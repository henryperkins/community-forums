import { test, expect, type Page, type BrowserContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const KEY = 'rb:dm-switch';
const sessions = new Map<string, Awaited<ReturnType<BrowserContext['cookies']>>>();
let paths: string[];

function php(code: string): string {
  if (!/^retroboards_e2e_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '')) {
    throw new Error('Conversation motion evidence requires an isolated browser database.');
  }
  return execFileSync('php', ['-r', `
require 'vendor/autoload.php';
\\App\\Core\\Env::load('.env');
$config = \\App\\Core\\Config::fromFile('config/config.php');
$db = new \\App\\Core\\Database($config->get('db'));
${code}
`], { cwd: ROOT, env: process.env }).toString().trim();
}

test.beforeAll(() => {
  paths = JSON.parse(php(`
$users = new \\App\\Repository\\UserRepository($db);
$alice = (int) $users->findByUsername('alice')['id'];
$bob = (int) $users->findByUsername('bob')['id'];
$conversations = new \\App\\Repository\\ConversationRepository($db);
$messages = new \\App\\Repository\\DmMessageRepository($db);
$paths = [];
foreach (['Motion first conversation', 'Motion second conversation'] as $title) {
    $row = $db->fetch('SELECT id FROM conversations WHERE title = ?', [$title]);
    $id = $row !== null ? (int) $row['id'] : $conversations->createGroup($alice, $title, [$bob]);
    if ($row === null) {
        for ($i = 1; $i <= 24; $i++) {
            $messages->create($id, $bob, 'Letter ' . $i . '. A durable discussion keeps its reading position.', '<p>Letter ' . $i . '. A durable discussion keeps its reading position.</p>');
        }
    }
    $paths[] = '/messages/' . $id;
}
echo json_encode($paths, JSON_THROW_ON_ERROR);
`));
});

async function login(page: Page, email = 'alice@retro.test'): Promise<void> {
  const cached = sessions.get(email);
  if (cached) {
    await page.context().addCookies(cached);
    return;
  }
  await page.goto('/login');
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill('password123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible().catch(() => false)) await skip.click();
  sessions.set(email, await page.context().cookies());
}

async function observeEntry(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const state = { stamps: [] as boolean[], animations: [] as Array<{ name: string; identity: boolean; messages: boolean; duration: string; delay: string; frames: Keyframe[] }> };
    (window as any).__motionEvidence = state;
    new MutationObserver(records => {
      if (records.some(record => record.attributeName === 'data-dm-switch') && document.documentElement.hasAttribute('data-dm-switch')) {
        state.stamps.push(Boolean(document.body));
      }
    }).observe(document, { subtree: true, attributes: true, attributeFilter: ['data-dm-switch'] });
    document.addEventListener('animationstart', event => {
      if (!event.animationName.startsWith('dm-switch-')) return;
      const node = event.target as HTMLElement;
      const style = getComputedStyle(node);
      const animation = node.getAnimations().find(item => (item as CSSAnimation).animationName === event.animationName);
      state.animations.push({
        name: event.animationName,
        identity: node.matches('.dm-thread-id'), messages: node.matches('[data-dm-messages]'),
        duration: style.animationDuration, delay: style.animationDelay,
        frames: (animation?.effect as KeyframeEffect)?.getKeyframes() ?? [],
      });
    }, true);
  });
}

async function evidence(page: Page) {
  return page.evaluate(() => (window as any).__motionEvidence as { stamps: boolean[]; animations: Array<{ name: string; identity: boolean; messages: boolean; duration: string; delay: string; frames: Array<{ transform?: string; opacity?: string }> }> });
}

async function marker(page: Page, value: object): Promise<void> {
  await page.evaluate(({ key, value }) => sessionStorage.setItem(key, JSON.stringify(value)), { key: KEY, value });
}

test('a row switch fades only identity and letters, stamps before body paint and keeps newest-letter position', async ({ page }) => {
  await login(page);
  await observeEntry(page);
  await page.goto('/messages');
  await page.locator(`a.dm-row.dm-link[href="${paths[1]}"]`).click();
  await page.waitForURL(url => url.pathname === paths[1]);
  await expect.poll(async () => (await evidence(page)).animations.length).toBe(2);
  const entry = await evidence(page);
  expect(entry.stamps).toEqual([false]);
  expect(entry.animations[0]).toMatchObject({ identity: true, messages: false, duration: '0.14s', delay: '0s' });
  expect(entry.animations[1]).toMatchObject({ identity: false, messages: true, duration: '0.24s', delay: '0.04s' });
  expect(entry.animations[1].frames[0]).toMatchObject({ opacity: '0', transform: 'translateY(8px)' });
  expect(entry.animations[1].frames.at(-1)).toMatchObject({ opacity: '1', transform: 'none' });
  await expect(page.locator('html')).not.toHaveAttribute('data-dm-switch');
  expect(await page.evaluate(key => sessionStorage.getItem(key), KEY)).toBeNull();
  const bottomGap = await page.locator('[data-dm-scroll]').evaluate(node => node.scrollHeight - node.scrollTop - node.clientHeight);
  expect(bottomGap).toBeLessThan(3);
  await page.reload();
  expect((await evidence(page)).animations).toEqual([]);
});

test('ordinary arrivals, stale or mismatched markers, reload and history navigation do not replay a switch', async ({ page }) => {
  await login(page);
  await observeEntry(page);
  await page.goto(paths[0]);
  expect((await evidence(page)).animations).toEqual([]);
  for (const signal of [
    { path: paths[1], at: Date.now() },
    { path: paths[0], at: Date.now() - 11000 },
    { path: paths[0], at: Date.now() + 11000 },
  ]) {
    await marker(page, signal);
    await page.goto(paths[0]);
    expect((await evidence(page)).animations).toEqual([]);
    expect(await page.evaluate(key => sessionStorage.getItem(key), KEY)).toBeNull();
  }
  await marker(page, { path: paths[0], at: Date.now() });
  await page.reload();
  expect((await evidence(page)).animations).toEqual([]);
  expect(await page.evaluate(key => sessionStorage.getItem(key), KEY)).toBeNull();
  await page.goto('/messages');
  await marker(page, { path: paths[0], at: Date.now() });
  await page.goBack();
  await expect(page.locator('html')).not.toHaveAttribute('data-dm-switch');
  expect(await page.evaluate(key => sessionStorage.getItem(key), KEY)).toBeNull();
});

test('same-row and modified, prevented, external, or non-conversation clicks never leave a switch signal', async ({ page }) => {
  await login(page);
  await page.goto(paths[0]);
  await page.evaluate(({ key, other }) => {
    const current = document.querySelector('a.dm-row.dm-link[aria-current="page"]')!;
    const row = document.querySelector(`a.dm-row.dm-link[href="${other}"]`)!;
    function click(node: Element, options: MouseEventInit) {
      sessionStorage.removeItem(key);
      node.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, ...options }));
      return sessionStorage.getItem(key);
    }
    const results = [];
    // Cancel the browser's default navigation after the document listener runs.
    window.addEventListener('click', event => event.preventDefault());
    results.push(click(current, { button: 0 }));
    for (const options of [{ button: 1 }, { button: 0, ctrlKey: true }, { button: 0, shiftKey: true }, { button: 0, metaKey: true }, { button: 0, altKey: true }]) {
      results.push(click(row, options));
    }
    row.addEventListener('click', event => event.preventDefault(), { once: true });
    results.push(click(row, { button: 0 }));
    const link = document.createElement('a'); link.className = 'dm-row dm-link'; document.body.appendChild(link);
    for (const href of ['https://example.com/messages/4', '/messages/new', '/messages']) {
      link.href = href; results.push(click(link, { button: 0 }));
    }
    (window as any).__signalResults = results;
  }, { key: KEY, other: paths[1] });
  expect(await page.evaluate(() => (window as any).__signalResults)).toEqual(Array(10).fill(null));
});

test('OS reduced motion suppresses the switch', async ({ page }) => {
  await login(page);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await observeEntry(page);
  await page.goto('/messages');
  await page.locator(`a.dm-row.dm-link[href="${paths[1]}"]`).click();
  await page.waitForURL(url => url.pathname === paths[1]);
  expect((await evidence(page)).animations).toEqual([]);
  expect((await evidence(page)).stamps).toEqual([]);
  expect(await page.evaluate(key => sessionStorage.getItem(key), KEY)).toBeNull();
});

test('account reduced motion suppresses the switch and consumes a previously written signal', async ({ page }) => {
  php(`$users = new \\App\\Repository\\UserRepository($db); $id = (int) $users->findByUsername('alice')['id']; (new \\App\\Repository\\UserPreferenceRepository($db))->merge($id, ['reduced_motion' => true]);`);
  try {
    await login(page);
    await observeEntry(page);
    await page.goto('/messages');
    await expect(page.locator('html')).toHaveAttribute('data-reduced-motion', '1');
    await marker(page, { path: paths[1], at: Date.now() });
    await page.goto(paths[1]);
    expect((await evidence(page)).animations).toEqual([]);
    expect((await evidence(page)).stamps).toEqual([]);
    expect(await page.evaluate(key => sessionStorage.getItem(key), KEY)).toBeNull();
  } finally {
    php(`$users = new \\App\\Repository\\UserRepository($db); $id = (int) $users->findByUsername('alice')['id']; (new \\App\\Repository\\UserPreferenceRepository($db))->merge($id, ['reduced_motion' => false]);`);
  }
});
