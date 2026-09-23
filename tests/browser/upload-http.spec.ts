import { expect, test, type Page, type Locator } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { body, fixture, imageFile, login, reply, root, setBody } from './upload-support';

const baseline = process.env.RB_UPLOAD_BASELINE === '1';
test.skip(process.env.RB_UPLOAD_HTTP !== '1', 'Requires tests/uploads/run.sh and its actual production image.');
const bytes = (kind: string, size?: number) => execFileSync('php', ['tests/uploads/fixtures.php', kind, ...(size ? [String(size)] : [])], { cwd: root, maxBuffer: 16 * 1024 * 1024 });
const token = (page: Page) => page.locator('form.composer-shell [name=_token]').first().inputValue();
const postImage = (page: Page, csrf: string, buffer: Buffer, name = 'synthetic.png', mimeType = 'image/png', purpose = 'post') =>
  page.request.post('/upload', { multipart: { _token: csrf, purpose, image: { name, mimeType, buffer } } });

test('multipart size boundaries are enforced by the actual Apache parser and application', async ({ page }, info) => {
  await reply(page, 'source');
  const csrf = await token(page);
  const large = bytes('large');
  expect(large.length).toBeGreaterThan(2097152);
  expect(large.length).toBeLessThan(5242880);
  const response = await postImage(page, csrf, large);
  const result = await response.json();
  if (baseline) {
    expect(response.status()).toBe(422);
    expect(result.error).toBe('No image was uploaded.');
    return;
  }
  expect(response.status(), JSON.stringify(result)).toBe(200);
  const outcomes: any[] = [{ kind: 'large', bytes: large.length, status: response.status(), sha256: createHash('sha256').update(large).digest('hex') }];
  for (const [size, status, code] of [[2673378, 200, undefined], [5242880, 200, undefined],
    [5242881, 422, 'upload_rejected'], [9 * 1024 * 1024, 413, 'upload_too_large'],
    [11 * 1024 * 1024, 413, 'upload_request_too_large']] as const) {
    const input = bytes('padded', size);
    expect(input.length).toBe(size);
    const upload = await postImage(page, csrf, input);
    const json = await upload.json();
    expect(upload.status(), JSON.stringify(json)).toBe(status);
    expect(json.code).toBe(code);
    if (status !== 200) {
      expect(json.max_bytes).toBe(5242880);
      expect(json.error).toMatch(/5 MiB/);
    }
    outcomes.push({ kind: 'padded PNG', bytes: size, status, code: json.code });
  }
  const dir = path.join(root, 'docs/evidence/image-upload-reliability');
  mkdirSync(dir, { recursive: true });
  writeFileSync(path.join(dir, `${info.project.name}-multipart.json`), JSON.stringify(outcomes, null, 2) + '\n');
});

test('all supported formats are sniffed and invalid images fail safely', async ({ page }) => {
  await reply(page, 'source');
  const csrf = await token(page);
  for (const [kind, mime] of [['jpeg', 'image/jpeg'], ['gif', 'image/gif'], ['webp', 'image/webp'], ['png', 'image/png']]) {
    const upload = await postImage(page, csrf, bytes(kind), 'renamed.jpg', 'application/octet-stream');
    expect(upload.status()).toBe(200);
    const json = await upload.json();
    const delivery = await page.request.get(json.url);
    expect(delivery.status()).toBe(200);
    expect(delivery.headers()['content-type']).toBe(mime);
    expect((await delivery.body()).length).toBeGreaterThan(0);
    expect(delivery.headers()['cache-control']).toContain('no-store');
  }
  // An actual file part with no Content-Type header: browser file MIME can be empty.
  const boundary = 'retroboards-upload-empty-mime';
  const raw = Buffer.concat([
    Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="_token"\r\n\r\n${csrf}\r\n--${boundary}\r\nContent-Disposition: form-data; name="image"; filename="empty-mime.png"\r\n\r\n`),
    imageFile().buffer, Buffer.from(`\r\n--${boundary}--\r\n`),
  ]);
  expect((await page.request.post('/upload', { data: raw, headers: { 'Content-Type': `multipart/form-data; boundary=${boundary}` } })).status()).toBe(200);
  for (const buffer of [Buffer.from('not an image'), bytes('dimensions'), bytes('pixels'), bytes('heic'), bytes('avif')]) {
    const upload = await postImage(page, csrf, buffer);
    expect(upload.status()).toBe(422);
    expect((await upload.json()).code).toBe('upload_rejected');
  }
  const denied = await postImage(page, 'invalid-csrf', imageFile().buffer);
  expect(denied.status()).toBe(403);
});

test('low PHP limits and unavailable storage produce recoverable HTTP diagnostics', async ({ page }) => {
  const container = process.env.RB_UPLOAD_APP_CONTAINER!;
  const docker = (...args: string[]) => execFileSync('docker', ['exec', container, ...args]);
  const restart = async () => {
    execFileSync('docker', ['restart', container], { timeout: 30_000 });
    await expect.poll(async () => (await page.request.get('/healthz').catch(() => null))?.status(), { timeout: 30_000 }).toBe(200);
  };
  try {
    docker('php', '-r', 'file_put_contents("/usr/local/etc/php/conf.d/zz-upload-test.ini", "upload_max_filesize=2M\\n");');
    await restart();
    await reply(page, 'source');
    const csrf = await token(page);
    const capped = await postImage(page, csrf, bytes('large'));
    expect(capped.status()).toBe(413);
    expect((await capped.json()).code).toBe('upload_too_large');
    docker('chmod', '-R', 'a-w', '/data/media');
    const blocked = await postImage(page, csrf, imageFile().buffer);
    expect(blocked.status()).toBe(503);
    expect((await blocked.json()).code).toBe('upload_unavailable');
    docker('chmod', '-R', 'u+w', '/data/media');
    expect((await postImage(page, csrf, imageFile().buffer)).status()).toBe(200);
  } finally {
    docker('chmod', '-R', 'u+w', '/data/media');
    docker('rm', '-f', '/usr/local/etc/php/conf.d/zz-upload-test.ini');
    await restart();
  }
});

async function publishImage(page: Page, form: Locator, text: string, rich: boolean) {
  if (rich && !await form.locator('.ProseMirror').isVisible()) {
    if (await form.locator('.ProseMirror').count() === 0) await form.locator('textarea[name=body]').focus();
    await expect(form.locator('.wysiwyg-source-toggle')).toBeAttached();
    if (!await form.locator('.wysiwyg-source-toggle').isVisible()) await form.locator('.composer-box').click({ position: { x: 40, y: 20 } });
    if (await form.locator('.wysiwyg-source-toggle').textContent() === 'Rich text') await form.locator('.wysiwyg-source-toggle').click();
    await expect(form.locator('.ProseMirror')).toBeVisible();
  }
  await setBody(form, text);
  const response = page.waitForResponse(res => new URL(res.url()).pathname === '/upload' && res.request().method() === 'POST');
  await form.locator('[data-composer-upload-input]').setInputFiles(imageFile());
  const upload = await response;
  expect(upload.status()).toBe(200);
  const image = await upload.json();
  await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
  await form.locator('.composer-send').click();
  const rendered = page.locator(`.formatted-content img[src="${image.url}"]`).last();
  await expect(rendered).toBeVisible();
  await page.reload();
  await expect(rendered).toBeVisible();
  const row = fixture('attachment', String(image.id));
  expect(row.status).toBe('finalized');
  expect(row.body).toContain(image.url);
  expect(row.body).not.toContain('rbup-');
  return row;
}

for (const mode of ['rich', 'source']) {
  test(`${mode} new-topic, edit, DM and group-DM composers publish finalized images`, async ({ page }) => {
    test.setTimeout(90_000);
    fixture(mode);
    await login(page);
    await page.goto('/compose');
    let form = page.locator('form[data-composer-instance=new-thread-page]');
    await form.locator('[name=title]').fill(`Shared upload ${mode}`);
    const row = await publishImage(page, form, `New topic ${mode}.`, mode === 'rich');
    form = page.locator(`form[data-composer-instance="edit-post-${row.post_id}"]`);
    await form.evaluate(node => node.closest('details')?.setAttribute('open', ''));
    await publishImage(page, form, `Edited topic ${mode}.`, mode === 'rich');
    for (const recipients of ['bob', 'bob, admin']) {
      await page.goto('/messages/new');
      form = page.locator('form[data-composer-instance=dm-new-page]');
      await form.locator('.dm-to-input').fill(recipients);
      await form.locator('.dm-to-input').press(',');
      await publishImage(page, form, `Message to ${recipients}.`, mode === 'rich');
      form = page.locator('form[data-composer-instance^=dm-conversation-]');
      await publishImage(page, form, `Message reply ${mode}.`, mode === 'rich');
    }
  });
}

test('wiki editing preserves rejected text and publishes its own uploaded image', async ({ page }) => {
  const info = fixture('wiki');
  await login(page, 'admin@retro.test');
  await page.goto(info.thread);
  let form = page.locator(`form[data-composer-instance="wiki-post-${info.post_id}"]`);
  const csrf = await form.locator('[name=_token]').inputValue();
  const rejected = await page.request.post(`/posts/${info.post_id}/wiki/edit`, { form: { _token: csrf,
    body: 'Preserved wiki ![pending](rbup-abc123-def456)', reason: 'Preserved reason' } });
  expect(rejected.status()).toBe(422);
  expect(await rejected.text()).toContain('Preserved reason');
  await form.evaluate(node => node.closest('details')?.setAttribute('open', ''));
  await form.locator('[name=reason]').fill('Add a wiki image');
  const row = await publishImage(page, form, 'Wiki photo.', true);
  expect(Number(row.post_id)).toBe(info.post_id);
});

test('a large image is published, reloads, and survives a container restart', async ({ page }, info) => {
  const form = await reply(page);
  await setBody(form, `Synthetic upload proof ${info.project.name}.`);
  const response = page.waitForResponse(res => new URL(res.url()).pathname === '/upload' && res.request().method() === 'POST');
  await form.locator('[data-composer-upload-input]').setInputFiles({ name: 'large.png', mimeType: 'image/png', buffer: bytes('large') });
  const upload = await response;
  expect(upload.status()).toBe(200);
  const json = await upload.json();
  await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
  expect(await body(form)).toContain(json.url);
  await Promise.all([page.waitForNavigation(), form.locator('.composer-send').click()]);
  const image = page.locator(`.formatted-content img[src="${json.url}"]`).last();
  await expect(image).toBeVisible();
  await expect.poll(() => image.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBe(1024);
  await page.reload();
  await expect(image).toBeVisible();
  const attachment = fixture('attachment', String(json.id));
  expect(attachment.status).toBe('finalized');
  expect(Number(attachment.post_id)).toBeGreaterThan(0);
  expect(Number(attachment.user_id)).toBe(fixture().alice_id);
  expect(attachment.body).toContain(json.url);
  expect(attachment.body).not.toContain('rbup-');
  const before = await page.request.get(json.url);
  expect(before.headers()['cache-control']).toContain('public');
  const digest = createHash('sha256').update(await before.body()).digest('hex');
  execFileSync('docker', ['restart', process.env.RB_UPLOAD_APP_CONTAINER!], { timeout: 30_000 });
  await expect.poll(async () => (await page.request.get('/healthz').catch(() => null))?.status(), { timeout: 30_000 }).toBe(200);
  const after = await page.request.get(json.url);
  expect(after.status()).toBe(200);
  expect(createHash('sha256').update(await after.body()).digest('hex')).toBe(digest);
  await page.reload();
  await expect(image).toBeVisible();
  await page.screenshot({ path: path.join(root, `docs/evidence/image-upload-reliability/${info.project.name}-published.png`), fullPage: true });
});

test('private-board and DM media keep current reader permissions', async ({ browser, page }) => {
  const info = fixture('source');
  await login(page, 'bob@retro.test');
  await page.goto('/compose');
  const csrf = await token(page);
  const uploaded = await postImage(page, csrf, imageFile().buffer);
  expect(uploaded.status()).toBe(200);
  const image = await uploaded.json();
  const publish = await page.request.post('/threads', { form: { _token: csrf, board_id: String(info.private_board_id),
    title: `Private image ${Date.now()}`, body: `Synthetic private image ![](${image.url})` }, maxRedirects: 0 });
  expect(publish.status()).toBe(303);
  const other = await browser.newContext({ baseURL: process.env.E2E_BASE_URL });
  const reader = await other.newPage();
  expect((await other.request.get(image.url)).status()).toBe(404);
  await login(reader);
  fixture('revoke-reader');
  expect((await other.request.get(image.url)).status()).toBe(404);
  fixture('grant-reader');
  const allowed = await other.request.get(image.url);
  expect(allowed.status()).toBe(200);
  expect(allowed.headers()['cache-control']).toContain('no-store');
  fixture('revoke-reader');
  expect((await other.request.get(image.url)).status()).toBe(404);
  await reader.goto('/messages/new');
  const dmToken = await token(reader);
  const dmUpload = await postImage(reader, dmToken, imageFile().buffer, 'dm.png', 'image/png', 'dm');
  expect(dmUpload.status()).toBe(200);
  const dmImage = await dmUpload.json();
  const dm = await reader.request.post('/messages', { form: { _token: dmToken, to: 'bob', body: `Synthetic message ![](${dmImage.url})` }, maxRedirects: 0 });
  expect(dm.status()).toBe(303);
  expect(Number(fixture('attachment', String(dmImage.id)).dm_message_id)).toBeGreaterThan(0);
  expect((await page.request.get(dmImage.url)).status()).toBe(200);
  const guest = await browser.newContext({ baseURL: process.env.E2E_BASE_URL });
  expect((await guest.request.get(dmImage.url)).status()).toBe(404);
  await guest.close();
  await other.close();
});
