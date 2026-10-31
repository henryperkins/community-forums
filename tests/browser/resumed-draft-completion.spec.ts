import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const root = path.resolve(__dirname, '../..');
const thread = '/t/2-share-your-favourite-keyboard-shortcuts';
const api = '/api/drafts/~2Ft~2F2~2Freply';
const reply = (page: Page) => page.locator('#reply');

function flags(rich: boolean) {
  execFileSync('php', ['-r', `require 'vendor/autoload.php';
    \\App\\Core\\Env::load('.env');$c=\\App\\Core\\Config::fromFile('config/config.php');
    if (!str_starts_with($c->get('db')['database'],'retroboards_draftfix_')) throw new RuntimeException('Private fixture required');
    $s=new \\App\\Repository\\SettingRepository(new \\App\\Core\\Database($c->get('db')));
    $f=$s->get('features',[]);$f['wysiwyg_composer']=${rich ? 'true' : 'false'};$f['server_drafts']=true;$s->set('features',$f);`], { cwd: root, env: process.env });
}
async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('carol@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await page.waitForURL(u => u.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}
async function ready(page: Page, rich: boolean) {
  await expect(reply(page).locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  if (rich) await expect(reply(page).locator('.ProseMirror')).toBeVisible();
}
async function remote(page: Page) { return (await (await page.request.get(api)).json()).draft; }
async function prepare(page: Page, rich: boolean, body: string, kind = 'server') {
  flags(rich); await login(page); await page.goto(thread);
  await expect(reply(page).locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  const token = await reply(page).locator('[name=_token]').inputValue();
  expect((await page.request.post(api + '/discard', { form: { _token: token } })).status()).toBe(200);
  await page.evaluate(() => localStorage.clear());
  await page.reload(); await ready(page, rich);
  await reply(page).locator(rich ? '.ProseMirror' : 'textarea').fill(body);
  await expect.poll(async () => (await remote(page))?.body).toBe(body);
  const saved = await remote(page);
  await page.goto('/drafts');
  const link = kind === 'server'
    ? page.locator(`a[href*="?draft=${saved.id}"]`)
    : page.locator('[data-local-drafts-list]').getByRole('link', { name: 'Resume', exact: true });
  await link.click(); await ready(page, rich);
  await expect(reply(page).locator('textarea')).toHaveValue(body);
  await expect(reply(page).locator('[name=draft_source]')).toHaveValue(`${saved.id}:${saved.revision}`);
  return saved;
}
async function assertCompleted(page: Page, body: string) {
  await expect(page.locator('.post-body').filter({ hasText: body })).toHaveCount(1);
  await expect.poll(() => remote(page)).toBeNull();
  await expect.poll(() => page.evaluate(() => localStorage.getItem('rb-draft:carol:/t/2/reply'))).toBeNull();
  await page.goto(thread);
  await expect(reply(page).locator('textarea')).toHaveAttribute('data-rb-enhanced', '1');
  await expect(reply(page).locator('textarea')).toHaveValue('');
  await expect(page.getByRole('region', { name: 'Draft conflict', exact: true })).toHaveCount(0);
}

test.afterEach(() => flags(false));

for (const rich of [false, true]) for (const kind of ['server', 'local']) {
  test(`${rich ? 'rich' : 'source'} unchanged ${kind} Resume sends once and removes the completed copy`, async ({ page }, info) => {
    const body = `Resume completion ${info.project.name} ${rich} ${kind} ${Date.now()} — café 東京.`;
    const saved = await prepare(page, rich, body, kind);
    expect(await reply(page).locator('[name=idempotency_key]').inputValue()).not.toBe(saved.metadata.submission);
    await reply(page).getByRole('button', { name: 'Reply', exact: true }).click();
    await assertCompleted(page, body);
    await info.attach('completed-composer', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
  });
}

test('resumed send preserves a newer server revision with identical text', async ({ page }, info) => {
  const body = `Newer copy survives ${info.project.name} ${Date.now()}.`;
  const saved = await prepare(page, true, body);
  const token = await reply(page).locator('[name=_token]').inputValue();
  const updated = await page.request.post(api, { form: {
    _token: token, draft_id: String(saved.id), revision: String(saved.revision), title: saved.title, body,
    metadata: JSON.stringify({ ...saved.metadata, submission: 'independent-newer-copy' }),
  } });
  expect(updated.status()).toBe(200);
  const newer = (await updated.json()).draft;
  await reply(page).getByRole('button', { name: 'Reply', exact: true }).click();
  await expect(page.locator('.post-body').filter({ hasText: body })).toHaveCount(1);
  await expect.poll(async () => (await remote(page))?.revision).toBe(newer.revision);
  expect((await remote(page)).metadata.submission).toBe('independent-newer-copy');
});

test('unchanged resumed retry after a lost committed response retains identity and posts once', async ({ page, browserName }, info) => {
  const body = `Resumed uncertain response ${info.project.name} ${Date.now()}.`;
  await prepare(page, true, body);
  const key = await reply(page).locator('[name=idempotency_key]').inputValue();
  await page.route('**/t/2/reply', async route => {
    const response = await route.fetch({ maxRedirects: 0 });
    expect(response.status()).toBe(303);
    await page.context().clearCookies({ name: 'rb_composer_completed' });
    await route.abort('internetdisconnected');
  });
  const failed = page.waitForEvent('requestfailed', r => r.isNavigationRequest() && r.method() === 'POST');
  await reply(page).getByRole('button', { name: 'Reply', exact: true }).click({ noWaitAfter: true });
  await failed; await page.unroute('**/t/2/reply');
  if (browserName === 'chromium') await page.goBack();
  else await page.reload(); // WebKit keeps the aborted document; reload restores its controls.
  await ready(page, true);
  await expect(reply(page).locator('textarea')).toHaveValue(body);
  await expect(reply(page).locator('[name=idempotency_key]')).toHaveValue(key);
  await expect(reply(page).getByRole('button', { name: 'Reply', exact: true })).toBeEnabled();
  await reply(page).getByRole('button', { name: 'Reply', exact: true }).click();
  await assertCompleted(page, body);
});

test('server Resume completes without JavaScript', async ({ page, browser }, info) => {
  const body = `No JavaScript completion ${info.project.name} ${Date.now()}.`;
  const saved = await prepare(page, false, body);
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 844 } });
  await context.addCookies(await page.context().cookies());
  const plain = await context.newPage();
  await plain.goto(new URL(thread + '?draft=' + saved.id, page.url()).href);
  await expect(reply(plain).locator('[name=draft_source]')).toHaveValue(`${saved.id}:${saved.revision}`);
  await reply(plain).getByRole('button', { name: 'Reply', exact: true }).click();
  await expect(plain.locator('.post-body').filter({ hasText: body })).toHaveCount(1);
  await expect.poll(() => remote(plain)).toBeNull();
  await context.close();
});
