import { test, expect, type BrowserContext, type Locator, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const LONG_NAME = 'Unbroken'.repeat(10);
const TAG_PATH = '/tags/shared-subheader-long-context';
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/shared-subheader-2026-10-07/long-contexts');
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });
let fixture: { user: number; tag: number; feed: number; previousFlags: Record<string, unknown> };
let memberCookies: Awaited<ReturnType<BrowserContext['cookies']>> | undefined;

function php(code: string) {
  if (!/^retroboards_e2e_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '')) {
    throw new Error('Use an explicit private retroboards_e2e_* database for long-context fixtures.');
  }
  return execFileSync('php', ['-r', `require 'vendor/autoload.php';
\\App\\Core\\Env::load(getcwd().'/.env'); $config=\\App\\Core\\Config::fromFile(getcwd().'/config/config.php');
if (!preg_match('/^retroboards_e2e_[a-z0-9_]+$/',$config->get('db.database'))) throw new RuntimeException('Use a private browser database.');
$db=new \\App\\Core\\Database($config->get('db')); ${code}`], { cwd: ROOT, env: process.env }).toString().trim();
}

test.beforeAll(() => {
  fixture = JSON.parse(php(`
$settings=new \\App\\Repository\\SettingRepository($db);
$previousFlags=$settings->get('features',[]); if (!is_array($previousFlags)) $previousFlags=[];
$settings->set('features',array_merge($previousFlags,['tags'=>true,'expanded_feeds'=>true,'saved_feeds'=>true]));
$users=new \\App\\Repository\\UserRepository($db);
$member=$users->findByUsername('shared_long_context_member');
$user=$member['id'] ?? $users->create(['username'=>'shared_long_context_member','email'=>'shared_long_context_member@retro.test','display_name'=>'Long context member','password_hash'=>(new \\App\\Security\\PasswordHasher())->hash('password123')]);
$db->run("UPDATE users SET status='active',role='user',onboarded_at=UTC_TIMESTAMP(),email_verified_at=UTC_TIMESTAMP(),post_count=1 WHERE id=?",[$user]);
$tags=new \\App\\Repository\\TagRepository($db); $name=str_repeat('Unbroken',10);
$tag=$tags->findBySlug('shared-subheader-long-context');
if (!$tag) { $id=$tags->create('shared-subheader-long-context',$name,'Long-name shared row regression.',(int)$user); $tag=$tags->find($id); }
$tags->update((int)$tag['id'],'shared-subheader-long-context',$name,'Long-name shared row regression.',true,'public');
$feeds=new \\App\\Repository\\SavedFeedRepository($db); $owned=$feeds->forUser((int)$user);
$feed=$owned[0]['id'] ?? $feeds->create((int)$user,$name,json_encode(['board_ids'=>[],'sort'=>'latest']),false);
$feeds->update((int)$user,(int)$feed,$name,json_encode(['board_ids'=>[],'sort'=>'latest']),false);
(new \\App\\Repository\\FollowRepository($db))->unfollowTarget((int)$user,'tag',(int)$tag['id']);
echo json_encode(['user'=>(int)$user,'tag'=>(int)$tag['id'],'feed'=>(int)$feed,'previousFlags'=>$previousFlags],JSON_THROW_ON_ERROR);`));
});

test.afterAll(() => {
  if (fixture) {
    const flags = Buffer.from(JSON.stringify(fixture.previousFlags)).toString('base64');
    php(`(new \\App\\Repository\\SettingRepository($db))->set('features',json_decode(base64_decode('${flags}'),true,512,JSON_THROW_ON_ERROR));`);
  }
});

async function login(page: Page) {
  if (memberCookies) {
    await page.context().addCookies(memberCookies);
    return;
  }
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('shared_long_context_member@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  memberCookies = await page.context().cookies();
}

async function largeAppearance(page: Page, theme: string) {
  await page.goto('/settings/appearance');
  await page.locator(`input[name="theme"][value="${theme}"]`).check({ force: true });
  await page.locator('select[name="font_size"]').selectOption('large');
  await page.getByRole('button', { name: 'Save appearance' }).click();
  await page.waitForURL(/\/settings\/appearance/);
}

async function expectTarget(target: Locator) {
  await expect(target).toBeVisible();
  const box = (await target.boundingBox())!;
  expect(box.width).toBeGreaterThanOrEqual(44);
  expect(box.height).toBeGreaterThanOrEqual(44);
}

async function expectLongContext(page: Page, action: Locator) {
  const row = page.locator('[data-subheader]');
  await expect(row).toHaveCount(1);
  await expect(page.locator('main h1')).toHaveCount(1);
  const heading = row.locator('h1');
  await expect(heading).toHaveText(LONG_NAME);
  await expect(heading).toBeVisible();
  await expectTarget(action);
  await expectTarget(row.locator('[data-create-trigger]'));
  const bounds = await row.evaluate(element => {
    const row = element.getBoundingClientRect();
    const heading = element.querySelector('h1')!;
    const title = heading.getBoundingClientRect();
    const range = document.createRange();
    range.selectNodeContents(heading);
    return {
      viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth,
      client: element.clientWidth, scroll: element.scrollWidth,
      row: { x: row.left, right: row.right, y: row.top, bottom: row.bottom },
      heading: { x: title.left, right: title.right, y: title.top, bottom: title.bottom },
      text: Array.from(range.getClientRects()).map(rect => ({ x: rect.left, right: rect.right, y: rect.top, bottom: rect.bottom })),
    };
  });
  expect(bounds.page, JSON.stringify(bounds)).toBeLessThanOrEqual(bounds.viewport);
  expect(bounds.scroll, JSON.stringify(bounds)).toBeLessThanOrEqual(bounds.client + 1);
  for (const text of bounds.text) {
    expect(text.x).toBeGreaterThanOrEqual(bounds.heading.x - 1);
    expect(text.right).toBeLessThanOrEqual(bounds.heading.right + 1);
    expect(text.y).toBeGreaterThanOrEqual(bounds.heading.y - 1);
    expect(text.bottom).toBeLessThanOrEqual(bounds.heading.bottom + 1);
  }
  const actionBox = (await action.boundingBox())!;
  const createBox = (await row.locator('[data-create-trigger]').boundingBox())!;
  for (const box of [actionBox, createBox]) {
    expect(box.x).toBeGreaterThanOrEqual(bounds.row.x - 1);
    expect(box.y).toBeGreaterThanOrEqual(bounds.row.y - 1);
    expect(box.y + box.height).toBeLessThanOrEqual(bounds.row.bottom + 1);
  }
  expect(bounds.heading.right).toBeLessThanOrEqual(actionBox.x + 1);
  expect(actionBox.x + actionBox.width).toBeLessThanOrEqual(createBox.x + 1);
  expect(createBox.x + createBox.width).toBeLessThanOrEqual(bounds.row.right + 1);
}

async function capture(page: Page, project: string, name: string) {
  const directory = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', project);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), animations: 'disabled' });
}

for (const javaScriptEnabled of [true, false]) {
  test(`80-character tag names keep Follow and Unfollow reachable ${javaScriptEnabled ? 'enhanced' : 'native'}`, async ({ browser, baseURL }, info) => {
    test.setTimeout(90000);
    php(`(new \\App\\Repository\\FollowRepository($db))->unfollowTarget(${fixture.user},'tag',${fixture.tag});`);
    const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport, isMobile, hasTouch, deviceScaleFactor });
    try {
      const page = await context.newPage();
      await login(page);
      for (const theme of ['light', 'dark']) {
        await largeAppearance(page, theme);
        for (const width of [320, 390]) {
          await page.setViewportSize({ width, height: 844 });
          await page.goto(TAG_PATH);
          await expect(page.locator('html')).toHaveAttribute('data-font-size', 'large');
          await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
          const form = page.locator(`[data-subheader] form[action="${TAG_PATH}/follow"]`);
          await expect(form.locator('input[name="_token"]')).toHaveCount(1);
          expect(await form.locator('input[name="_token"]').inputValue()).not.toBe('');
          for (const [current, next] of [['Follow tag', 'Unfollow tag'], ['Unfollow tag', 'Follow tag']]) {
            const button = form.getByRole('button', { name: current, exact: true });
            await expectLongContext(page, button);
            const token = await form.locator('input[name="_token"]').inputValue();
            const submitted = page.waitForResponse(response => response.request().method() === 'POST' && new URL(response.url()).pathname === `${TAG_PATH}/follow`);
            await button.click();
            const response = await submitted;
            expect(new URLSearchParams(response.request().postData()!).get('_token')).toBe(token);
            expect([302, 303]).toContain(response.status());
            await expect(form.getByRole('button', { name: next, exact: true })).toBeVisible();
            expect(new URL(page.url()).pathname).toBe(TAG_PATH);
          }
          await expectLongContext(page, form.getByRole('button', { name: 'Follow tag', exact: true }));
          await capture(page, info.project.name, `tag-${width}-${theme}-large-${javaScriptEnabled ? 'js' : 'native'}`);
        }
      }
    } finally {
      await context.close();
    }
  });

  test(`80-character saved-feed names retain a native Manage destination ${javaScriptEnabled ? 'enhanced' : 'native'}`, async ({ browser, baseURL }, info) => {
    test.setTimeout(90000);
    const { viewport, isMobile, hasTouch, deviceScaleFactor } = info.project.use;
    const context = await browser.newContext({ baseURL, javaScriptEnabled, viewport, isMobile, hasTouch, deviceScaleFactor });
    try {
      const page = await context.newPage();
      await login(page);
      for (const theme of ['light', 'dark']) {
        await largeAppearance(page, theme);
        for (const width of [320, 390]) {
          await page.setViewportSize({ width, height: 844 });
          await page.goto(`/feeds/saved/${fixture.feed}`);
          await expect(page.locator('html')).toHaveAttribute('data-font-size', 'large');
          await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
          const manage = page.locator('[data-subheader]').getByRole('link', { name: 'Manage saved feeds', exact: true });
          await expect(manage).toHaveAttribute('href', '/settings/boards');
          await expectLongContext(page, manage);
          await capture(page, info.project.name, `saved-feed-${width}-${theme}-large-${javaScriptEnabled ? 'js' : 'native'}`);
          await manage.click();
          await expect(page).toHaveURL(/\/settings\/boards$/);
          await expect(page.locator('main h1')).toHaveText('Account settings');
        }
      }
    } finally {
      await context.close();
    }
  });
}

test('a guest retains the original tag heading and no empty shared row', async ({ browser, baseURL }, info) => {
  const { isMobile, hasTouch, deviceScaleFactor } = info.project.use;
  const context = await browser.newContext({ baseURL, javaScriptEnabled: false, viewport: { width: 320, height: 844 }, isMobile, hasTouch, deviceScaleFactor });
  try {
    const page = await context.newPage();
    await page.goto(TAG_PATH);
    await expect(page.locator('[data-subheader]')).toHaveCount(0);
    await expect(page.locator('[data-create-trigger]')).toHaveCount(0);
    await expect(page.locator('main h1')).toHaveCount(1);
    await expect(page.locator('.tag-view .board-header h1')).toHaveText(LONG_NAME);
    await expect(page.locator('.tag-view .board-header h1')).toBeVisible();
    await expect(page.locator(`form[action="${TAG_PATH}/follow"]`)).toHaveCount(0);
  } finally {
    await context.close();
  }
});
