import { expect, test, type Route } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'node:path';
import { accepted, body, choose, fixture, imageFile, login, media, reply, root, setBody } from './upload-support';

test.skip(!['retroboards_upload_http', 'retroboards_upload_browser'].includes(process.env.DB_DATABASE || ''),
  'Upload recovery fixtures require their dedicated disposable database. Use tests/uploads/run.sh.');

for (const mode of ['rich', 'source']) {
  test.describe(mode, () => {
    test('pending and rejected images block every send while preserving the draft', async ({ page }) => {
      const held: Route[] = [];
      await page.route('**/upload', route => { held.push(route); });
      const form = await reply(page, mode);
      await setBody(form, 'Keep this reply.');
      const key = await form.locator('[name=idempotency_key]').inputValue();
      let posts = 0;
      page.on('request', request => { if (request.method() === 'POST' && /\/reply$/.test(request.url())) posts++; });
      await choose(form);
      await expect.poll(() => held.length).toBe(1);
      await expect(form.locator('.composer-send')).toBeDisabled();
      const blocked = await form.evaluate((el: HTMLFormElement) => {
        const event = new Event('submit', { cancelable: true, bubbles: true });
        el.dispatchEvent(event);
        el.requestSubmit();
        return event.defaultPrevented;
      });
      expect(blocked).toBe(true);
      await held[0].fulfill({ status: 422, json: { ok: false, error: 'This image could not be processed.' } });
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await expect(form.locator('.composer-send')).toBeDisabled();
      await form.getByRole('button', { name: 'Remove photo.png', exact: true }).click();
      await expect(form.locator('.composer-send')).toBeEnabled();
      expect(await body(form)).toContain('Keep this reply.');
      expect(await body(form)).not.toContain('rbup-');
      expect(await form.locator('[name=idempotency_key]').inputValue()).toBe(key);
      expect(posts).toBe(0);
    });

    test('only a committed image and loaded preview become ready without temporary URL requests', async ({ page }) => {
      const pendingRequests: string[] = [];
      page.on('request', request => { if (/\/rbup-/.test(request.url())) pendingRequests.push(request.url()); });
      await media(page);
      await page.route('**/upload', route => route.fulfill({ json: accepted() }));
      const form = await reply(page, mode);
      await setBody(form, 'An image follows.');
      await choose(form);
      await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
      await expect(form.locator('.composer-send')).toBeEnabled();
      expect(await body(form)).toContain('![](/media/9001)');
      expect(await body(form)).not.toContain('rbup-');
      expect(pendingRequests).toEqual([]);
      await form.locator('.composer-upload-card input').fill('A [photo]');
      await expect.poll(() => body(form)).toContain('A \\[photo\\]');
    });

    test('failed preview retry reuses the accepted upload', async ({ page }) => {
      let uploads = 0;
      let broken = true;
      await page.route('**/upload', route => { uploads++; return route.fulfill({ json: accepted() }); });
      await page.route('**/media/9001', route => broken
        ? route.fulfill({ status: 404, body: 'Unavailable' }) : route.fulfill({ status: 200, contentType: 'image/png', body: imageFile().buffer }));
      const form = await reply(page, mode);
      await setBody(form, 'Keep this text.');
      await choose(form);
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await expect(form.locator('.composer-send')).toBeDisabled();
      broken = false;
      await form.getByRole('button', { name: 'Retry photo.png', exact: true }).click();
      await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
      expect(uploads).toBe(1);
      expect((await body(form)).match(/\/media\/9001/g)).toHaveLength(1);
    });

    for (const preview of ['failed', 'pending']) {
      test(`${preview} preview intent survives draft reload and still blocks sending`, async ({ page }) => {
        await page.route('**/upload', route => route.fulfill({ json: accepted() }));
        await page.route('**/media/9001', route => preview === 'failed'
          ? route.fulfill({ status: 404, body: 'Unavailable' }) : undefined);
        const form = await reply(page, mode);
        await setBody(form, 'Keep this unverified image intent.');
        await choose(form);
        await expect(form.locator(preview === 'failed' ? '[data-upload-state=failed]' : '[data-upload-state=verifying]')).toBeVisible();
        await page.reload({ waitUntil: 'domcontentloaded' });
        const restored = page.locator('form.reply-composer.composer-shell');
        await expect(restored.locator('.composer-upload-card.is-failed')).toBeVisible();
        await expect(restored.locator('.composer-send')).toBeDisabled();
        expect(await body(restored)).toContain('Keep this unverified image intent.');
        await expect(restored.getByRole('button', { name: /Choose image/ })).toBeVisible();
        await restored.getByRole('button', { name: /Remove interrupted image/ }).click();
        await expect(restored.locator('.composer-send')).toBeEnabled();
        expect(await body(restored)).not.toMatch(/rbup-|\/media\//);
      });
    }

    test('blockquoted code examples remain literal and never create interrupted uploads', async ({ page }) => {
      const form = await reply(page, mode);
      for (const markdown of [
        '>     ![example](rbup-abc123-def456)',
        '> ~~~markdown\n> ![example](rbup-abc123-def456)\n> ~~~',
        '> > ```markdown\n> > ![example](rbup-abc123-def456)\n> > ```',
        '- ~~~markdown\n  ![example](rbup-abc123-def456)\n  ~~~',
      ]) {
        if (mode === 'rich') await form.locator('.wysiwyg-source-toggle').click();
        await form.locator('textarea[name=body]').fill(markdown);
        if (mode === 'rich') await form.locator('.wysiwyg-source-toggle').click();
        await expect(form.locator('.composer-upload-card')).toHaveCount(0);
        await expect(form.locator('.composer-send')).toBeEnabled();
        expect(await body(form)).toContain('rbup-abc123-def456');
      }
      // A quote's unclosed fence cannot mask a real image outside its container.
      await form.evaluate(node => (node as any)._rbComposerAdapter.setMarkdown('> ~~~markdown\n> literal code\n\n![pending](rbup-abc123-def456)'));
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await expect(form.locator('.composer-send')).toBeDisabled();
    });

    test('remove aborts a pending image without inserting a late result', async ({ page }) => {
      const held: Route[] = [];
      await page.route('**/upload', route => { held.push(route); });
      const form = await reply(page, mode);
      await setBody(form, 'No image wanted.');
      await choose(form);
      await expect.poll(() => held.length).toBe(1);
      await form.getByRole('button', { name: 'Remove photo.png', exact: true }).click();
      await held[0].fulfill({ json: accepted() }).catch(() => {});
      await expect(form.locator('.composer-upload-card')).toHaveCount(0);
      await expect(form.locator('.composer-send')).toBeEnabled();
      expect(await body(form)).not.toMatch(/rbup-|\/media\//);
    });

    test('reverse completion preserves identically named images and failure recovery', async ({ page }) => {
      const held: Route[] = [];
      await page.route('**/upload', route => { held.push(route); });
      await media(page);
      const form = await reply(page, mode);
      await setBody(form, 'Two photos.');
      await form.locator('[data-composer-upload-input]').setInputFiles([imageFile('photo.png'), imageFile('photo.png')]);
      await expect.poll(() => held.length).toBe(2);
      await held[1].fulfill({ json: accepted(9002) });
      await held[0].fulfill({ status: 503, json: { ok: false, error: 'Temporarily unavailable.' } });
      await expect(form.locator('.composer-upload-card.is-complete')).toHaveCount(1);
      await expect(form.locator('.composer-send')).toBeDisabled();
      await form.getByRole('button', { name: 'Retry photo.png', exact: true }).click();
      await expect.poll(() => held.length).toBe(3);
      await held[2].fulfill({ json: accepted(9001) });
      await expect(form.locator('.composer-upload-card.is-complete')).toHaveCount(2);
      await expect(form.getByRole('button', { name: 'Retry photo.png', exact: true })).toHaveCount(0);
      expect(await body(form)).toMatch(/\/media\/9001[\s\S]*\/media\/9002/);
    });

    test('unfinished drafts recover while literal code examples remain publishable', async ({ page }) => {
      const form = await reply(page, mode);
      await setBody(form, 'Draft ![uploading](rbup-abc123-def456)');
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await expect(form.locator('.composer-send')).toBeDisabled();
      await expect(form.getByRole('button', { name: /Choose image/ })).toBeVisible();
      await form.getByRole('button', { name: /Remove interrupted image/ }).click();
      await setBody(form, '`![uploading](rbup-abc123-def456)`');
      await expect(form.locator('.composer-send')).toBeEnabled();
      await expect(form.locator('.composer-upload-card')).toHaveCount(0);
    });

    test('removal stays blocked while an insertion is queued and cancels its late transaction', async ({ page }) => {
      await media(page);
      await page.route('**/upload', route => route.fulfill({ json: accepted() }));
      const form = await reply(page, mode);
      await setBody(form, 'Keep surrounding text.');
      await form.evaluate((node: HTMLFormElement) => {
        const adapter = (node as any)._rbComposerAdapter;
        const original = adapter.replacePendingUpload.bind(adapter);
        const gate = new Promise<void>(resolve => { (window as any).releaseUploadEdits = resolve; });
        adapter.replacePendingUpload = (...args: any[]) => gate.then(() => original(...args));
      });
      await choose(form);
      await expect(form.locator('[data-upload-state=inserting]')).toBeVisible();
      await form.getByRole('button', { name: 'Remove photo.png', exact: true }).click();
      await expect(form.locator('[data-upload-state=removing]')).toBeVisible();
      await expect(form.locator('.composer-send')).toBeDisabled();
      await page.evaluate(() => (window as any).releaseUploadEdits());
      await expect(form.locator('.composer-upload-card')).toHaveCount(0);
      await expect(form.locator('.composer-send')).toBeEnabled();
      expect(await body(form)).not.toMatch(/rbup-|\/media\//);
      expect(await body(form)).toContain('Keep surrounding text.');
    });

    test('failed local size validation survives reload and offers replacement selection', async ({ page }) => {
      const form = await reply(page, mode);
      await setBody(form, 'Keep the intended attachment.');
      let uploads = 0;
      await page.route('**/upload', route => { uploads++; return route.fulfill({ json: accepted() }); });
      await form.locator('[data-composer-upload-input]').setInputFiles({ name: 'large.png', mimeType: 'image/png', buffer: Buffer.alloc(5242881) });
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      expect(uploads).toBe(0);
      await page.reload();
      const restored = page.locator('form.reply-composer.composer-shell');
      await expect(restored.locator('.composer-upload-card.is-failed')).toBeVisible();
      await expect(restored.locator('.composer-send')).toBeDisabled();
      const chooser = page.waitForEvent('filechooser');
      await restored.getByRole('button', { name: /Choose image/ }).click();
      await media(page);
      await (await chooser).setFiles(imageFile('replacement.png'));
      await expect(restored.locator('.composer-upload-card.is-complete')).toHaveCount(1);
      expect(await body(restored)).toContain('Keep the intended attachment.');
    });

    test('HTTP errors and malformed success remain visible and block keyboard send', async ({ page }) => {
      const form = await reply(page, mode);
      await setBody(form, 'Keep my reply.');
      let response: any;
      await page.route('**/upload', route => route.fulfill(response));
      for (response of [
        { status: 413, contentType: 'text/html', body: 'Too large' },
        { status: 403, contentType: 'text/html', body: 'Expired' },
        { status: 503, json: { ok: false, error: 'Temporarily unavailable.' } },
        { status: 200, json: { ...accepted(), url: 'https://example.com/media/9001' } },
        { status: 200, json: { ...accepted(), id: '9001' } },
        { status: 200, contentType: 'text/html', body: '<h1>Sign in</h1>' },
      ]) {
        await choose(form);
        await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
        await expect(form.locator('.composer-send')).toBeDisabled();
        const input = mode === 'rich' ? form.locator('.ProseMirror') : form.locator('textarea[name=body]');
        await input.press('Control+Enter');
        await expect(form.locator('[data-composer-submit-status]')).toContainText('image');
        await form.getByRole('button', { name: 'Remove photo.png', exact: true }).click();
        await expect(form.locator('.composer-upload-card')).toHaveCount(0);
      }
      expect(await body(form)).toContain('Keep my reply.');
      expect(await form.getAttribute('aria-busy')).toBeNull();
    });

    test('deleted markers fail insertion and explicit retry reuses the accepted image', async ({ page }) => {
      const held: Route[] = [];
      await page.route('**/upload', route => { held.push(route); });
      await media(page);
      const form = await reply(page, mode);
      await setBody(form, 'Keep this surrounding text.');
      await choose(form);
      await expect.poll(() => held.length).toBe(1);
      await setBody(form, 'Keep this surrounding text.');
      await held[0].fulfill({ json: accepted() });
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      expect(await body(form)).not.toContain('/media/');
      await form.getByRole('button', { name: 'Retry photo.png', exact: true }).click();
      await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
      expect(held).toHaveLength(1);
      expect((await body(form)).match(/\/media\/9001/g)).toHaveLength(1);
      const results = await form.evaluate(async node => {
        const adapter = (node as any)._rbComposerAdapter;
        const controller = new AbortController(); controller.abort();
        return [await adapter.replacePendingUpload('![](rbup-missing-token)', '![](/media/9002)'),
          await adapter.replacePendingUpload('![](/media/9001)', '', { signal: controller.signal })];
      });
      expect(results).toEqual([false, false]);
      expect(await body(form)).toContain('/media/9001');
    });

    test('queued alt edits preserve the latest value and ready image deletion stays deleted', async ({ page }) => {
      await media(page);
      await page.route('**/upload', route => route.fulfill({ json: accepted() }));
      const form = await reply(page, mode);
      await setBody(form, 'Preserve this text.');
      await choose(form);
      await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
      await form.evaluate(node => {
        const adapter = (node as any)._rbComposerAdapter;
        const original = adapter.replacePendingUpload.bind(adapter);
        const gate = new Promise<void>(resolve => { (window as any).releaseAltEdits = resolve; });
        adapter.replacePendingUpload = (...args: any[]) => gate.then(() => original(...args));
      });
      await form.getByRole('textbox', { name: 'Image alt text' }).fill('First');
      await form.getByRole('textbox', { name: 'Image alt text' }).fill('Latest [description]');
      await expect(form.locator('.composer-send')).toBeDisabled();
      await page.evaluate(() => (window as any).releaseAltEdits());
      await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
      expect(await body(form)).toContain('Latest \\[description\\]');
      expect(await body(form)).not.toContain('First');
      await setBody(form, 'Preserve this text.');
      await expect(form.locator('.composer-upload-card')).toHaveCount(0);
      await expect(form.locator('.composer-send')).toBeEnabled();
    });

    test('network failure and timeout handling allow repeated retry without stale callbacks', async ({ page, browserName }) => {
      await page.addInitScript(() => {
        const Original = window.XMLHttpRequest;
        (window as any).uploadRequests = [];
        window.XMLHttpRequest = class extends Original {
          send(data?: Document | XMLHttpRequestBodyInit | null) {
            if (data instanceof FormData && data.has('image')) {
              (window as any).uploadRequests.push(this);
              if ((window as any).uploadRequests.length === 1) this.timeout = 75;
            }
            super.send(data);
          }
        };
      });
      const held: Route[] = [];
      await page.route('**/upload', route => { held.push(route); });
      await media(page);
      const form = await reply(page, mode);
      await setBody(form, 'Keep my draft through retries.');
      await choose(form);
      await expect.poll(() => held.length).toBe(1);
      if (browserName === 'webkit') {
        // WebKit's intercepted request pauses its native timeout clock. Exercise
        // the timeout event boundary here; Chromium above uses a real 75ms timeout.
        await page.evaluate(() => (window as any).uploadRequests[0].dispatchEvent(new ProgressEvent('timeout')));
      }
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await form.getByRole('button', { name: 'Retry photo.png', exact: true }).click();
      await expect.poll(() => held.length).toBe(2);
      await held[1].abort('failed');
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await form.getByRole('button', { name: 'Retry photo.png', exact: true }).click();
      await expect.poll(() => held.length).toBe(3);
      await page.evaluate(() => {
        for (const request of (window as any).uploadRequests.slice(0, 2)) request.onload?.(new Event('load'));
      });
      await held[2].fulfill({ json: accepted() });
      await held[0].fulfill({ json: accepted(9002) }).catch(() => {});
      await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
      expect(await body(form)).toContain('Keep my draft through retries.');
      expect(await body(form)).toContain('/media/9001');
      expect(await body(form)).not.toContain('/media/9002');
    });

    test('preview timeout is recoverable and removing a pending preview prevents late success', async ({ page }) => {
      const held: Route[] = [];
      await page.route('**/upload', route => route.fulfill({ json: accepted() }));
      await page.route('**/media/9001', route => { held.push(route); });
      const form = await reply(page, mode);
      await setBody(form, 'Keep text without this image.');
      await page.clock.install();
      await choose(form);
      await expect(form.locator('[data-upload-state=verifying]')).toBeVisible();
      await page.clock.fastForward(30001);
      await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
      await form.getByRole('button', { name: 'Retry photo.png', exact: true }).click();
      await expect(form.locator('[data-upload-state=verifying]')).toBeVisible();
      await form.getByRole('button', { name: 'Remove photo.png', exact: true }).click();
      await expect(form.locator('.composer-upload-card')).toHaveCount(0);
      for (const route of held) await route.fulfill({ contentType: 'image/png', body: imageFile().buffer }).catch(() => {});
      await expect(form.locator('.composer-send')).toBeEnabled();
      expect(await body(form)).not.toMatch(/rbup-|\/media\//);
      const focusInEditor = await form.evaluate(node => node.querySelector('.ProseMirror, textarea[name=body]') === document.activeElement
        || !!node.querySelector('.ProseMirror')?.contains(document.activeElement));
      expect(focusInEditor).toBe(true);
    });

    test('clipboard and drop inputs share readiness and empty MIME handling', async ({ page }) => {
      await media(page);
      let id = 9000;
      await page.route('**/upload', route => route.fulfill({ json: accepted(++id) }));
      const form = await reply(page, mode);
      await setBody(form, 'Paste and drop.');
      const target = mode === 'rich' ? form.locator('.ProseMirror') : form.locator('textarea[name=body]');
      await target.evaluate((node, bytes) => {
        for (const event of ['paste', 'drop']) {
          const transfer = new DataTransfer();
          transfer.items.add(new File([new Uint8Array(bytes)], event + '.png', { type: event === 'paste' ? 'image/png' : '' }));
          node.dispatchEvent(event === 'paste'
            ? new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true })
            : new DragEvent('drop', { dataTransfer: transfer, bubbles: true, cancelable: true }));
        }
      }, [...imageFile().buffer]);
      await expect(form.locator('.composer-upload-card.is-complete')).toHaveCount(2);
      expect(await body(form)).toMatch(/\/media\/9001[\s\S]*\/media\/9002/);
    });
  });
}

test('source mode switching during transfer preserves the current text and image', async ({ page }) => {
  const held: Route[] = [];
  await page.route('**/upload', route => { held.push(route); });
  await media(page);
  const form = await reply(page);
  await setBody(form, 'Before switching.');
  await choose(form);
  await expect.poll(() => held.length).toBe(1);
  await form.locator('.wysiwyg-source-toggle').click();
  await form.locator('textarea[name=body]').fill((await body(form)) + '\nEdited in source.');
  await held[0].fulfill({ json: accepted() });
  await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
  await form.locator('.wysiwyg-source-toggle').click();
  await expect(form.locator('.ProseMirror img[src="/media/9001"]')).toBeVisible();
  expect(await body(form)).toContain('Edited in source.');
});

test('retry honors Retry-After and does not send automatically', async ({ page }) => {
  const form = await reply(page, 'source');
  await setBody(form, 'Wait for my decision.');
  await page.clock.install();
  let count = 0;
  await page.route('**/upload', route => ++count === 1
    ? route.fulfill({ status: 429, headers: { 'Retry-After': '2' }, json: { ok: false, error: 'Wait before retrying.' } })
    : route.fulfill({ json: accepted() }));
  await media(page);
  await choose(form);
  const retry = form.getByRole('button', { name: 'Retry photo.png', exact: true });
  await expect(retry).toBeDisabled();
  await page.clock.fastForward(2100);
  await expect(retry).toBeEnabled();
  expect(count).toBe(1);
  await retry.click();
  await expect(form.locator('.composer-upload-card.is-complete')).toBeVisible();
  expect(count).toBe(2);
  expect(await form.getAttribute('aria-busy')).toBeNull();
});

test('separate forms and fragment destruction cannot share upload state', async ({ page }) => {
  const held: Route[] = [];
  await page.route('**/upload', route => { held.push(route); });
  const form = await reply(page, 'source');
  await setBody(form, 'Original form.');
  const markup = await (await page.request.get('/compose')).text();
  await page.evaluate(html => {
    const documentCopy = new DOMParser().parseFromString(html, 'text/html');
    const second = documentCopy.querySelector('form.composer-shell')!;
    document.body.append(second);
    (window as any).RetroBoardsComposer.enhanceWithin(second);
  }, markup);
  const second = page.locator('form[data-composer-instance=new-thread-page]');
  await setBody(second, 'Independent form.');
  await choose(form);
  await expect.poll(() => held.length).toBe(1);
  await expect(form.locator('.composer-send')).toBeDisabled();
  await expect(second.locator('.composer-send')).toBeEnabled();
  await form.evaluate(node => { (window as any).RetroBoardsComposer.destroyWithin(node); node.remove(); });
  await held[0].fulfill({ json: accepted() }).catch(() => {});
  await expect(second.locator('.composer-send')).toBeEnabled();
  expect(await body(second)).toContain('Independent form.');
  expect(await body(second)).not.toMatch(/\/media\/|rbup-/);
});

test('failed attachment feedback is accessible in both themes and fits the viewport', async ({ page }, info) => {
  await page.route('**/upload', route => route.fulfill({ status: 503, json: { ok: false, error: 'Uploads are temporarily unavailable.' } }));
  const form = await reply(page);
  await setBody(form, 'Preserved text while the image needs attention.');
  await choose(form, 'A-photo-with-a-very-long-filename-that-must-remain-readable.png');
  await expect(form.locator('.composer-upload-card.is-failed')).toBeVisible();
  for (const theme of ['light', 'dark']) {
    await page.locator('html').evaluate((el, theme) => el.setAttribute('data-theme', theme), theme);
    await form.evaluate(async el => { await Promise.all(el.getAnimations({ subtree: true }).map(animation => animation.finished.catch(() => {}))); });
    const result = await new AxeBuilder({ page }).include('form.reply-composer').analyze();
    expect(result.violations.filter(v => ['serious', 'critical'].includes(v.impact || ''))).toEqual([]);
    expect(await form.evaluate(node => node.scrollWidth <= node.clientWidth)).toBe(true);
    await form.screenshot({ path: path.join(root, `docs/evidence/image-upload-reliability/${info.project.name}-${theme}-failed.png`) });
  }
});

test('ordinary Markdown and existing image references remain usable without JavaScript', async ({ browser }) => {
  const info = fixture('rich');
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  // Use the configured origin even when running against the built-in server.
  const origin = process.env.E2E_BASE_URL || `http://localhost:${process.env.E2E_PORT || 8011}`;
  await page.goto(origin + '/login');
  await page.locator('[name=email]').fill('alice@retro.test');
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.goto(origin + info.thread);
  const form = page.locator('form.reply-composer');
  const csrf = await form.locator('[name=_token]').inputValue();
  const uploaded = await context.request.post(origin + '/upload', { multipart: { _token: csrf, image: imageFile() } });
  expect(uploaded.status()).toBe(200);
  const uploadedImage = await uploaded.json();
  await form.locator('textarea[name=body]').fill(`No-JS example: \`![uploading](rbup-abc123-def456)\` ![](${uploadedImage.url})`);
  await Promise.all([page.waitForNavigation(), form.locator('.composer-send').click()]);
  await expect(page.locator('.formatted-content').filter({ hasText: 'No-JS example:' }).last()).toBeVisible();
  await expect(page.locator(`.formatted-content img[src="${uploadedImage.url}"]`).last()).toBeVisible();
  await context.close();
});
