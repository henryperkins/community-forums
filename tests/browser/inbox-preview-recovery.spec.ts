import { test, expect, type Page, type Route, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(__dirname, '../..');
const OUT = path.resolve(ROOT, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/inbox-preview-recovery-2026-10-08');
const TOPIC = 'Share your favourite keyboard shortcuts';
test.use({ browserName: process.env.E2E_LAYOUT_BROWSER === 'webkit' ? 'webkit' : 'chromium' });

test.beforeAll(async ({ request }, info) => {
  const manifest = JSON.parse(fs.readFileSync(path.join(ROOT, 'config/assets.json'), 'utf8'));
  const served = [];
  for (const entry of ['app.css', 'app.js']) {
    const url = manifest.urls[entry];
    const response = await request.get(url);
    expect(response.status(), `${entry} must serve the recorded build`).toBe(200);
    const sha256 = createHash('sha256').update(await response.body()).digest('hex');
    expect(sha256, `${entry} must match its manifest fingerprint`).toBe(manifest.files[url].sha256);
    served.push({ entry, url, sha256 });
  }
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, `served-assets-${process.env.E2E_LAYOUT_BROWSER ?? 'chromium'}-${info.project.name}.json`), JSON.stringify({ version: manifest.version, served }, null, 2));
});

test.beforeEach(() => {
  if (!/^retroboards_e2e_[a-z0-9_]+$/.test(process.env.DB_DATABASE ?? '')) {
    throw new Error('Use an explicit private retroboards_e2e_* database for Inbox preview evidence.');
  }
  execFileSync('php', ['tests/browser/member-surfaces-fixture.php'], { cwd: ROOT, env: process.env });
  execFileSync('php', ['-r', `
    require 'vendor/autoload.php';
    \\App\\Core\\Env::load(getcwd() . '/.env');
    $config = \\App\\Core\\Config::fromFile(getcwd() . '/config/config.php');
    $db = new \\App\\Core\\Database($config->get('db'));
    $alice = (new \\App\\Repository\\UserRepository($db))->findByUsername('alice');
    $state = new \\App\\Repository\\ThreadUserRepository($db);
    foreach ($db->fetchAll('SELECT id, last_post_id FROM threads') as $topic) {
      $state->setSnooze((int) $alice['id'], (int) $topic['id'], null);
      $state->markRead((int) $alice['id'], (int) $topic['id'], (int) $topic['last_post_id']);
    }
    foreach ($db->fetchAll("SELECT id FROM threads WHERE title IN ('${TOPIC}', 'Mobile layout looks great', 'Welcome to RetroBoards')") as $topic) {
      $state->markUnread((int) $alice['id'], (int) $topic['id']);
    }
  `], { cwd: ROOT, env: process.env });
});

async function login(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill('alice@retro.test');
  await page.getByLabel('Password', { exact: true }).fill('password123');
  await page.getByRole('button', { name: /log in/i }).click();
  await page.waitForURL(url => !url.pathname.startsWith('/login'));
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

function viewport(info: TestInfo) {
  return info.project.name === 'mobile' ? { width: 393, height: 844 } : { width: 1440, height: 900 };
}

async function capture(page: Page, info: TestInfo, name: string) {
  const directory = path.join(OUT, process.env.E2E_LAYOUT_BROWSER ?? 'chromium', info.project.name);
  fs.mkdirSync(directory, { recursive: true });
  await page.screenshot({ path: path.join(directory, `${name}.png`), animations: 'disabled' });
}

async function afterResponse(page: Page) {
  // Let the application consume the response and paint before asserting that
  // a cancelled or stale response did not replace the current preview.
  await page.evaluate(() => new Promise<void>(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve()))));
}

async function openDraft(page: Page, info: TestInfo) {
  await page.setViewportSize(viewport(info));
  await login(page);
  await page.goto('/inbox?scope=unread&order=active');
  await expect(page.locator('[data-inbox-row]')).toHaveCount(3);
  const oldLink = page.getByRole('link', { name: TOPIC, exact: true });
  const oldId = await oldLink.evaluate(link => link.closest('[data-inbox-row]')!.getAttribute('data-thread-id'));
  await oldLink.click();
  const preview = page.locator(`[data-inbox-preview="${oldId}"]`);
  await expect(preview).toBeVisible();
  await expect(page.locator(`[data-inbox-row][data-thread-id="${oldId}"]`)).toHaveCount(0);
  const draft = preview.locator('textarea[name="body"]');
  const text = 'Keep this reply محفوظ 🪴 while another topic fails.';
  await draft.fill(text);
  await draft.evaluate(node => { (window as any).inboxRecoveryDraft = node; });
  const heading = preview.locator(':scope > header > h2');
  await heading.focus();
  return { oldId: oldId!, preview, draft, heading, text, url: page.url(), historyLength: await page.evaluate(() => history.length) };
}

async function beginNextPreview(page: Page) {
  const link = page.locator('[data-inbox-preview-url]').first();
  const endpoint = (await link.getAttribute('data-inbox-preview-url'))!;
  const canonical = (await link.getAttribute('href'))!;
  // The removed Unread row leaves the cursor at the first remaining topic.
  // j also reaches the queue while its phone preview occupies the only pane.
  await page.keyboard.press('j');
  return { endpoint, canonical };
}

async function returnToDraft(page: Page, old: Awaited<ReturnType<typeof openDraft>>) {
  const returnPreview = page.getByRole('button', { name: 'Return to previous topic', exact: true });
  await expect(returnPreview).toBeVisible();
  await returnPreview.click();
  await expect(old.preview).toBeVisible();
  await expect(old.draft).toBeVisible();
  await expect(old.draft).toHaveValue(old.text);
  expect(await old.draft.evaluate(node => node === (window as any).inboxRecoveryDraft), 'Return must reuse the existing draft DOM').toBe(true);
  await expect(old.heading).toBeFocused();
  expect(page.url(), 'Return is local to the retained preview').toBe(old.url);
  expect(await page.evaluate(() => history.length), 'Return must not add a history entry').toBe(old.historyLength);
}

test('failed preview retries keep a removed Unread topic and its exact reply draft resumable', async ({ page }, info) => {
  const old = await openDraft(page, info);
  let requests = 0;
  await page.route('**/inbox/preview/*', route => {
    requests++;
    return route.fulfill({ status: 500, body: 'Temporary preview failure' });
  });
  const next = await beginNextPreview(page);
  await expect(page.locator('[data-inbox-recovery]')).toBeVisible();
  await expect(page.locator('[data-inbox-full-topic]')).toHaveAttribute('href', next.canonical);
  await expect(page.locator('[data-inbox-return-preview]')).toBeVisible();
  await expect(old.draft).toHaveValue(old.text);
  for (let retry = 0; retry < 2; retry++) {
    const response = page.waitForResponse(response => new URL(response.url()).pathname === next.endpoint);
    await page.getByRole('button', { name: 'Try again', exact: true }).click();
    await response;
    await expect(page.locator('[data-inbox-recovery]')).toBeVisible();
    await expect(page.locator('[data-inbox-return-preview]')).toBeVisible();
    await expect(old.draft).toHaveValue(old.text);
  }
  expect(requests).toBe(3);
  await capture(page, info, 'failed-next-topic-retains-return');
  await returnToDraft(page, old);
  expect(requests, 'Returning must not request or reconstruct the old topic').toBe(3);
  await capture(page, info, 'returned-existing-reply-draft');
});

test('an initial preview failure offers retry and full topic without a previous-topic command', async ({ page }, info) => {
  await page.setViewportSize(viewport(info));
  await login(page);
  await page.goto('/inbox?scope=starred&order=active');
  const link = page.locator('[data-inbox-preview-url]').first();
  const canonical = (await link.getAttribute('href'))!;
  await page.route('**/inbox/preview/*', route => route.fulfill({ status: 500, body: 'Temporary preview failure' }));
  await link.click();
  await expect(page.locator('[data-inbox-recovery]')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Try again', exact: true })).toBeVisible();
  await expect(page.locator('[data-inbox-full-topic]')).toHaveAttribute('href', canonical);
  await expect(page.locator('[data-inbox-return-preview]')).toBeHidden();
  await expect(page.locator('[data-inbox-preview]')).toHaveCount(0);
});

test('return after failed history restoration aligns the URL with the retained topic without adding an entry', async ({ page }, info) => {
  await page.setViewportSize(viewport(info));
  await login(page);
  await page.goto('/inbox?scope=starred&order=newest#recovery-history');
  const linkA = page.getByRole('link', { name: 'Welcome to RetroBoards', exact: true });
  const idA = await linkA.evaluate(node => node.closest('[data-inbox-row]')!.getAttribute('data-thread-id'));
  const endpointA = (await linkA.getAttribute('data-inbox-preview-url'))!;
  await linkA.click();
  await expect(page.locator(`[data-inbox-preview="${idA}"]`)).toBeVisible();
  const linkB = page.locator('[data-inbox-preview-url]').filter({ hasText: TOPIC });
  const idB = await linkB.evaluate(node => node.closest('[data-inbox-row]')!.getAttribute('data-thread-id'));
  // Dispatch the same native click through the delegated Inbox handler while
  // the one-column reading pane covers its queue.
  await linkB.evaluate(node => (node as HTMLElement).click());
  const previewB = page.locator(`[data-inbox-preview="${idB}"]`);
  await expect(previewB).toBeVisible();
  const draft = previewB.locator('textarea[name="body"]');
  const text = 'Keep the newer topic reply when browser Back fails.';
  await draft.fill(text);
  await draft.evaluate(node => { (window as any).inboxRecoveryDraft = node; });
  const urlB = page.url();
  const historyLength = await page.evaluate(() => history.length);
  await page.route(`**${endpointA}`, route => route.fulfill({ status: 500, body: 'History preview unavailable' }));
  const response = page.waitForResponse(response => new URL(response.url()).pathname === endpointA);
  await page.goBack();
  await response;
  await expect(page.locator('[data-inbox-recovery]')).toBeVisible();
  expect(new URL(page.url()).searchParams.get('t')).toBe(idA);
  await expect(page.locator('[data-inbox-return-preview]')).toBeVisible();
  await page.getByRole('button', { name: 'Return to previous topic', exact: true }).click();
  await expect(previewB).toBeVisible();
  await expect(draft).toHaveValue(text);
  expect(await draft.evaluate(node => node === (window as any).inboxRecoveryDraft)).toBe(true);
  await expect(previewB.locator(':scope > header > h2')).toBeFocused();
  expect(page.url(), 'A failed popstate must not leave the URL pointing to a different article').toBe(urlB);
  expect(await page.evaluate(() => history.length)).toBe(historyLength);
  expect(await page.evaluate(() => history.state.rbInboxTopic)).toBe(true);
  await capture(page, info, 'returned-after-failed-history-restoration');
});

test('timeout without AbortController keeps the old draft and ignores a late preview', async ({ page }, info) => {
  await page.addInitScript(() => { (window as any).AbortController = undefined; });
  const old = await openDraft(page, info);
  await page.clock.install();
  let held: Route | undefined;
  await page.route('**/inbox/preview/*', route => { held = route; });
  const next = await beginNextPreview(page);
  await expect.poll(() => !!held).toBe(true);
  await expect(page.locator('[data-inbox-reading]')).toHaveAttribute('aria-busy', 'true');
  await page.clock.fastForward(15001);
  await expect(page.locator('[data-inbox-status]')).toContainText('taking longer');
  await expect(page.locator('[data-inbox-return-preview]')).toBeVisible();
  const staleId = next.canonical.match(/\/t\/(\d+)/)![1];
  const response = page.waitForResponse(response => new URL(response.url()).pathname === next.endpoint);
  await held!.fulfill({ status: 200, contentType: 'text/html', body: `<article data-inbox-preview="${staleId}"><h2>Late topic</h2></article>` });
  await response;
  await afterResponse(page);
  await expect(page.locator(`[data-inbox-preview="${staleId}"]`)).toHaveCount(0);
  await expect(page.locator('[data-inbox-status]')).toContainText('taking longer');
  await returnToDraft(page, old);
});

for (const noAbort of [false, true]) {
  for (const panel of ['rail', 'reading']) {
    test(`${panel} preference submission preserves a pending preview ${noAbort ? 'without' : 'with'} AbortController`, async ({ page }, info) => {
      test.skip(info.project.name !== 'desktop', 'Pane preference toggles are desktop controls.');
      if (noAbort) await page.addInitScript(() => { (window as any).AbortController = undefined; });
      await page.setViewportSize({ width: 1440, height: 900 });
      await login(page);
      await page.goto('/inbox?scope=starred&order=active');
      let held: Route | undefined;
      await page.route('**/inbox/preview/*', route => { held = route; });
      const link = page.locator('[data-inbox-preview-url]').first();
      const endpoint = (await link.getAttribute('data-inbox-preview-url'))!;
      const id = await link.evaluate(node => node.closest('[data-inbox-row]')!.getAttribute('data-thread-id'));
      await link.click();
      await expect.poll(() => !!held).toBe(true);
      const preference = page.waitForResponse(response => new URL(response.url()).pathname === '/settings/member-surfaces' && response.request().method() === 'POST');
      await page.locator(`[data-panel-form="${panel}"] button[type="submit"]`).click();
      expect((await preference).status()).toBe(303);
      expect(new URL(page.url()).pathname).toBe('/inbox');
      await expect(page.locator('[data-inbox-reading]')).toHaveAttribute('aria-busy', 'true');
      await expect(page.locator('[data-inbox-status]')).toContainText('Loading topic');
      const response = page.waitForResponse(response => new URL(response.url()).pathname === endpoint);
      await held!.fulfill({ status: 200, contentType: 'text/html', body: `<article data-inbox-preview="${id}"><h2>Loaded after pane preference</h2></article>` });
      await response;
      await expect(page.locator(`[data-inbox-preview="${id}"]`)).toBeVisible();
      await expect(page.locator('[data-inbox-reading]')).not.toHaveAttribute('aria-busy', 'true');
      await expect(page.locator('[data-inbox-recovery]')).toBeHidden();
      await expect(page).toHaveURL(new RegExp(`[?&]t=${id}(?:&|$)`));
    });
  }
}

test('pane preferences retain failed-preview recovery and its return command', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'Pane preference toggles are desktop controls.');
  const old = await openDraft(page, info);
  await page.route('**/inbox/preview/*', route => route.fulfill({ status: 500, body: 'Temporary preview failure' }));
  await beginNextPreview(page);
  await expect(page.locator('[data-inbox-recovery]')).toBeVisible();
  for (const panel of ['rail', 'reading']) {
    const preference = page.waitForResponse(response => new URL(response.url()).pathname === '/settings/member-surfaces' && response.request().method() === 'POST');
    await page.locator(`[data-panel-form="${panel}"] button[type="submit"]`).click();
    expect((await preference).status()).toBe(303);
    expect(new URL(page.url()).pathname).toBe('/inbox');
    await expect(page.locator('[data-inbox-recovery]')).toBeVisible();
    await expect(page.locator('[data-inbox-return-preview]')).toBeVisible();
  }
  await returnToDraft(page, old);
});

test('a prevented form submission leaves the requested preview in progress', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop', 'One desktop case covers the generic submit event contract.');
  await page.addInitScript(() => { (window as any).AbortController = undefined; });
  await page.setViewportSize({ width: 1440, height: 900 });
  await login(page);
  await page.goto('/inbox?scope=starred&order=active');
  let held: Route | undefined;
  await page.route('**/inbox/preview/*', route => { held = route; });
  const link = page.locator('[data-inbox-preview-url]').first();
  const id = await link.evaluate(node => node.closest('[data-inbox-row]')!.getAttribute('data-thread-id'));
  const endpoint = (await link.getAttribute('data-inbox-preview-url'))!;
  await link.click();
  await expect.poll(() => !!held).toBe(true);
  await page.evaluate(() => {
    const form = document.createElement('form');
    form.addEventListener('submit', event => event.preventDefault());
    document.querySelector('[data-inbox-list]')!.appendChild(form);
    form.requestSubmit();
    form.remove();
  });
  await expect(page.locator('[data-inbox-reading]')).toHaveAttribute('aria-busy', 'true');
  const response = page.waitForResponse(response => new URL(response.url()).pathname === endpoint);
  await held!.fulfill({ status: 200, contentType: 'text/html', body: `<article data-inbox-preview="${id}"><h2>Still requested topic</h2></article>` });
  await response;
  await expect(page.locator(`[data-inbox-preview="${id}"]`)).toBeVisible();
});

for (const action of ['row', 'bulk']) {
  test(`native ${action} submission owns navigation when an old preview answers with an access error`, async ({ page }, info) => {
    await page.addInitScript(() => {
      (window as any).AbortController = undefined;
      const nativeFetch = window.fetch.bind(window);
      window.fetch = (input, init) => {
        const url = input instanceof Request ? input.url : String(input);
        const endpoint = new URL(url, location.href).pathname;
        if (/^\/inbox\/preview\/\d+$/.test(endpoint)) {
          // Native navigation cancels browser transport before a held response
          // can arrive. Keep the application promise independently observable
          // when the submit handler invalidates this preview request.
          (window as any).inboxNativePreviewEndpoint = endpoint;
          return new Promise<Response>(resolve => {
            (window as any).inboxNativePreviewResolve = resolve;
          });
        }
        return nativeFetch(input, init);
      };
    });
    await page.setViewportSize(viewport(info));
    await login(page);
    await page.goto('/inbox?scope=starred&order=active');
    const originalUrl = page.url();
    const row = page.locator('[data-inbox-row]').first();
    const link = row.locator('[data-inbox-preview-url]');
    const endpoint = (await link.getAttribute('data-inbox-preview-url'))!;
    const canonical = (await link.getAttribute('href'))!;
    const canonicalNavigations: string[] = [];
    page.on('request', request => {
      if (request.isNavigationRequest() && new URL(request.url()).pathname === canonical) canonicalNavigations.push(request.url());
    });
    await page.evaluate(() => {
      // The application's document listener runs first and cancels the preview.
      // Hold this first submit at window so the document stays observable, then
      // resubmit the same form and submitter through the real native POST below.
      window.addEventListener('submit', event => {
        event.preventDefault();
        (window as any).inboxNativeSubmission = {
          form: event.target,
          submitter: (event as SubmitEvent).submitter,
        };
      }, { once: true });
    });
    await link.focus();
    await page.keyboard.press('j');
    await expect.poll(() => page.evaluate(() => (window as any).inboxNativePreviewEndpoint)).toBe(endpoint);
    await expect(page.locator('[data-inbox-reading]')).toHaveAttribute('aria-busy', 'true');
    if (action === 'row') await row.locator('.star-toggle').click({ noWaitAfter: true });
    else {
      await row.locator('[data-inbox-select]').check();
      await page.locator('[data-inbox-sweep] > button[value="read"]').click({ noWaitAfter: true });
    }
    await expect.poll(() => page.evaluate(() => !!(window as any).inboxNativeSubmission)).toBe(true);
    await expect(page.locator('[data-inbox-reading]')).not.toHaveAttribute('aria-busy', 'true');
    await page.evaluate(() => {
      (window as any).inboxNativePreviewResolve(new Response('The superseded preview requires sign in', { status: 401 }));
    });
    await afterResponse(page);
    expect(canonicalNavigations, 'A superseded access error must not compete with native form navigation').toEqual([]);
    expect(page.url()).toBe(originalUrl);
    await expect(page.locator('[data-inbox-reading]')).not.toHaveAttribute('aria-busy', 'true');
    const postPath = await page.evaluate(() => new URL((window as any).inboxNativeSubmission.form.getAttribute('action'), location.href).pathname);
    const nativeResponse = page.waitForResponse(response => response.request().method() === 'POST'
      && response.request().isNavigationRequest() && new URL(response.url()).pathname === postPath);
    const navigation = page.waitForNavigation({ waitUntil: 'domcontentloaded' });
    await page.evaluate(() => {
      const { form, submitter } = (window as any).inboxNativeSubmission;
      form.requestSubmit(submitter);
    });
    expect((await nativeResponse).status()).toBe(303);
    await navigation;
    expect(new URL(page.url()).pathname).toBe('/inbox');
    expect(canonicalNavigations).toEqual([]);
    await expect(page.locator('[data-inbox-recovery]')).toBeHidden();
  });
}
