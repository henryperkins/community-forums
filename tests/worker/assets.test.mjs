import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import { createHash } from 'node:crypto';
import { routeRequest } from '../../worker/assets.mjs';
import manifest from '../../config/assets.json' with { type: 'json' };

const immutable = 'public, max-age=31536000, immutable';
const revalidate = 'public, max-age=0, must-revalidate';
const request = (path, init) => new Request(`https://forum.example${path}`, init);
const noContainer = () => { throw new Error('A static request reached the container'); };
const staticEnv = (fetch = () => new Response('asset', { headers: { ETag: '"content"', 'Content-Type': 'text/css' } })) => ({ ASSETS: { fetch } });

test('fingerprinted CSS, scripts, lazy chunks and fonts bypass the container and are immutable', async () => {
  for (const path of Object.keys(manifest.files).filter((path) => path.startsWith('/assets/dist/'))) {
    const response = await routeRequest(request(path), staticEnv(), noContainer);
    assert.equal(response.status, 200);
    assert.equal(response.headers.get('Cache-Control'), immutable, path);
    assert.equal(response.headers.get('ETag'), '"content"');
  }
});

test('only the genuine current version makes source asset URLs immutable', async () => {
  for (const query of ['', '?v=', '?v=anything', '?v=0123456789abcdef', `?v=${manifest.version}&v=wrong`]) {
    const response = await routeRequest(request(`/assets/app.js${query}`), staticEnv(), noContainer);
    assert.equal(response.headers.get('Cache-Control'), revalidate, query);
  }
  const valid = await routeRequest(request(`/assets/app.js?v=${manifest.version}`), staticEnv(), noContainer);
  assert.equal(valid.headers.get('Cache-Control'), immutable);
  const font = await routeRequest(request('/assets/fonts/imladris/eb-garamond-latin-400-normal.woff2'), staticEnv(), noContainer);
  assert.equal(font.headers.get('Cache-Control'), revalidate);
});

test('conditional requests retain 304 and validators, and HEAD retains its empty body', async () => {
  const path = manifest.urls['app.css'];
  const conditional = await routeRequest(request(path, { headers: { 'If-None-Match': '"content"' } }), staticEnv((received) => {
    assert.equal(received.headers.get('If-None-Match'), '"content"');
    return new Response(null, { status: 304, headers: { ETag: '"content"' } });
  }), noContainer);
  assert.equal(conditional.status, 304);
  assert.equal(conditional.headers.get('ETag'), '"content"');
  assert.equal(conditional.headers.get('Cache-Control'), immutable);
  assert.equal(await conditional.text(), '');

  const head = await routeRequest(request(path, { method: 'HEAD' }), staticEnv((received) => {
    assert.equal(received.method, 'HEAD');
    return new Response(null, { headers: { 'Content-Length': '42' } });
  }), noContainer);
  assert.equal(head.headers.get('Content-Length'), '42');
  assert.equal(await head.text(), '');
});

test('range responses and their status survive without exposing Set-Cookie', async () => {
  const response = await routeRequest(request(manifest.urls['app.js'], { headers: { Range: 'bytes=0-4' } }), staticEnv((received) => {
    assert.equal(received.headers.get('Range'), 'bytes=0-4');
    return new Response('asset', { status: 206, headers: { 'Content-Range': 'bytes 0-4/10', 'Set-Cookie': 'unexpected=1' } });
  }), noContainer);
  assert.equal(response.status, 206);
  assert.equal(response.headers.get('Content-Range'), 'bytes 0-4/10');
  assert.equal(response.headers.get('Set-Cookie'), null);
});

test('missing/private assets and unsupported methods never fall through to PHP or an SPA', async () => {
  const neverFetch = staticEnv(() => { throw new Error('A non-allowlisted asset reached the binding'); });
  for (const path of ['/assets/index.php', '/assets/.env', '/assets/private.json', '/assets/dist/missing.js']) {
    const response = await routeRequest(request(path), neverFetch, noContainer);
    assert.equal(response.status, 404, path);
    assert.equal(response.headers.get('Cache-Control'), 'no-store');
  }
  const post = await routeRequest(request(manifest.urls['app.js'], { method: 'POST' }), neverFetch, noContainer);
  assert.equal(post.status, 405);
  assert.equal(post.headers.get('Allow'), 'GET, HEAD');
  const absent = await routeRequest(request(manifest.urls['app.js']), staticEnv(() => new Response(null, { status: 404 })), noContainer);
  assert.equal(absent.status, 404);
  assert.equal(absent.headers.get('Cache-Control'), 'no-store');
});

test('branding, package themes and application routes retain dynamic response semantics', async () => {
  for (const path of ['/brand.css?v=1', '/theme/preview.css', '/theme/abcdef.css', '/', '/index.php', '/ping/ping.txt']) {
    const origin = new Response('dynamic', { headers: { 'Cache-Control': 'private, no-store', 'Set-Cookie': 'session=opaque' } });
    const response = await routeRequest(request(path), staticEnv(() => { throw new Error('Dynamic route reached assets'); }), () => origin);
    assert.equal(response, origin);
    assert.equal(response.headers.get('Set-Cookie'), 'session=opaque');
  }
});

test('published manifest names only verified asset bytes and font preload matches emitted CSS', async () => {
  for (const [url, metadata] of Object.entries(manifest.files)) {
    assert.match(url, /^\/assets\/[A-Za-z0-9_./-]+\.(?:js|css|woff2|svg|txt)$/);
    assert.ok(!url.includes('..'));
    const bytes = await readFile(new URL(`../../public${url}`, import.meta.url));
    assert.equal(createHash('sha256').update(bytes).digest('hex'), metadata.sha256, url);
  }
  const css = await readFile(new URL(`../../public${manifest.urls['imladris.css']}`, import.meta.url), 'utf8');
  assert.ok(css.includes(manifest.urls['fonts/imladris/eb-garamond-latin-400-normal.woff2']));
  for (const name of ['app.js', 'composer.js', 'passkeys.js', 'tour.js']) {
    const script = await readFile(new URL(`../../public${manifest.urls[name]}`, import.meta.url), 'utf8');
    assert.match(script, /^['"]use strict['"];\s/, `${name} must preserve strict semantics when loaded as a classic script`);
  }
  const entry = await readFile(new URL(`../../public${manifest.urls['wysiwyg-composer.js']}`, import.meta.url), 'utf8');
  assert.ok(Buffer.byteLength(entry) < 5000, 'The initializer must not embed Milkdown');
  assert.match(entry, /import\(["'`]\.\/milkdown-adapter-/);
});
