import assert from 'node:assert/strict';
import { copyFile, mkdir, mkdtemp, rm } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';
// These are already supplied by the pinned Wrangler toolchain.
import { build } from 'esbuild';
import { Miniflare } from 'miniflare';
import manifest from '../../config/assets.json' with { type: 'json' };

test('the real Workers Assets binding serves GET, conditional 304, HEAD and safe 404 responses', async () => {
  const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
  const directory = await mkdtemp(path.join(os.tmpdir(), 'retroboards-static-test-'));
  let runtime;
  try {
    for (const url of [manifest.urls['app.css'], '/assets/app.js']) {
      const target = path.join(directory, url.slice(1));
      await mkdir(path.dirname(target), { recursive: true });
      await copyFile(path.join(root, 'public', url), target);
    }
    const bundle = await build({
      stdin: {
        contents: `import { routeRequest } from './worker/assets.mjs';
          export default { fetch(request, env) {
            return routeRequest(request, env, () => new Response('dynamic origin', {
              status: 418, headers: { 'Cache-Control': 'private, no-store' }
            }));
          } };`,
        resolveDir: root,
        sourcefile: 'asset-test.mjs',
      },
      bundle: true, write: false, format: 'esm', platform: 'browser',
    });
    runtime = new Miniflare({
      modules: true,
      script: bundle.outputFiles[0].text,
      compatibilityDate: '2026-08-03',
      assets: {
        directory, binding: 'ASSETS',
        routerConfig: { invoke_user_worker_ahead_of_assets: true, has_user_worker: true },
        assetConfig: { html_handling: 'none', not_found_handling: 'none' },
      },
    });
    const url = `https://forum.example${manifest.urls['app.css']}`;
    const first = await runtime.dispatchFetch(url);
    assert.equal(first.status, 200, first.status !== 200 ? await first.text() : 'static CSS should be available');
    assert.equal(first.headers.get('Cache-Control'), 'public, max-age=31536000, immutable');
    assert.equal(first.headers.get('X-RetroBoards-Cache'), 'STATIC');
    const etag = first.headers.get('ETag');
    assert.ok(etag);
    assert.ok((await first.text()).length > 0);
    const conditional = await runtime.dispatchFetch(url, { headers: { 'If-None-Match': etag } });
    assert.equal(conditional.status, 304);
    assert.equal(conditional.headers.get('ETag'), etag);
    assert.equal(conditional.headers.get('Cache-Control'), 'public, max-age=31536000, immutable');
    assert.equal(await conditional.text(), '');
    const head = await runtime.dispatchFetch(url, { method: 'HEAD' });
    assert.equal(head.status, 200);
    assert.equal(head.headers.get('ETag'), etag);
    assert.equal(await head.text(), '');
    const unversioned = await runtime.dispatchFetch('https://forum.example/assets/app.js?v=not-a-version');
    assert.equal(unversioned.headers.get('Cache-Control'), 'public, max-age=0, must-revalidate');
    for (const suffix of ['/assets/nope', '/assets/index.php', '/assets/app.js/']) {
      const missing = await runtime.dispatchFetch(`https://forum.example${suffix}`);
      assert.equal(missing.status, 404, suffix);
      assert.equal(missing.headers.get('Cache-Control'), 'no-store');
    }
    for (const suffix of ['/brand.css', '/theme/preview.css', '/index.php']) {
      const dynamic = await runtime.dispatchFetch(`https://forum.example${suffix}`);
      assert.equal(dynamic.status, 418, suffix);
      assert.equal(dynamic.headers.get('Cache-Control'), 'private, no-store');
    }
  } finally {
    await runtime?.dispose();
    await rm(directory, { recursive: true, force: true });
  }
});
