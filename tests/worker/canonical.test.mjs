import assert from 'node:assert/strict';
import { test } from 'node:test';
import { canonicalRedirect } from '../../worker/canonical.mjs';

const env = { APP_URL: 'https://forum.example' };

test('requests on the canonical origin pass through untouched', () => {
  for (const url of ['https://forum.example/', 'https://forum.example/t/1-slug?page=2', 'https://FORUM.example/healthz']) {
    assert.equal(canonicalRedirect(new Request(url), env), null, url);
  }
});

test('any other hostname is redirected to the same path and query on the canonical origin', () => {
  const response = canonicalRedirect(new Request('https://boards.example/t/7-title?page=3#p9'), env);
  assert.equal(response.status, 301);
  assert.equal(response.headers.get('Location'), 'https://forum.example/t/7-title?page=3');
  // Never cached: after APP_URL flips, a cached redirect in the old direction
  // would loop against the new one.
  assert.equal(response.headers.get('Cache-Control'), 'no-store');
  assert.equal(response.body, null);
});

test('plain http on the canonical host is upgraded to the canonical scheme', () => {
  const response = canonicalRedirect(new Request('http://forum.example/x?y=1'), env);
  assert.equal(response.status, 301);
  assert.equal(response.headers.get('Location'), 'https://forum.example/x?y=1');
});

test('unsafe methods keep their method and body through a 308', () => {
  for (const method of ['POST', 'PUT', 'DELETE']) {
    const response = canonicalRedirect(new Request('https://boards.example/reply', { method, body: 'x' }), env);
    assert.equal(response.status, 308, method);
    assert.equal(response.headers.get('Location'), 'https://forum.example/reply');
  }
  assert.equal(canonicalRedirect(new Request('https://boards.example/reply', { method: 'HEAD' }), env).status, 301);
});

test('a stray request port is dropped and a non-default canonical port is carried over', () => {
  assert.equal(
    canonicalRedirect(new Request('https://boards.example:8443/x'), env).headers.get('Location'),
    'https://forum.example/x',
  );
  assert.equal(
    canonicalRedirect(new Request('https://boards.example/x'), { APP_URL: 'https://forum.example:8443' }).headers.get('Location'),
    'https://forum.example:8443/x',
  );
  assert.equal(canonicalRedirect(new Request('https://forum.example:8443/x'), { APP_URL: 'https://forum.example:8443' }), null);
});

test('loopback hosts (wrangler dev) and an unusable APP_URL fail open', () => {
  for (const url of ['http://localhost:8787/x', 'http://127.0.0.1:8787/x', 'http://[::1]:8787/x', 'http://forum.localhost/x']) {
    assert.equal(canonicalRedirect(new Request(url), env), null, url);
  }
  for (const APP_URL of [undefined, '', 'forum.example', 'not a url', 'ftp://forum.example']) {
    assert.equal(canonicalRedirect(new Request('https://boards.example/x'), { APP_URL }), null, String(APP_URL));
  }
  assert.equal(canonicalRedirect(new Request('https://boards.example/x'), {}), null);
});

test('certificate-validation paths on a non-canonical host go to the origin instead of redirecting', async () => {
  const neverFetch = () => { throw new Error('origin fetch not expected'); };
  for (const path of ['/.well-known/pki-validation/ca3-token.txt', '/.well-known/acme-challenge/abc', '/.well-known/cf-custom-hostname-challenge/id']) {
    const seen = [];
    const response = await canonicalRedirect(new Request(`https://boards.example${path}?x=1`), env, async (forwarded) => {
      seen.push(forwarded);
      return new Response('token', { status: 200 });
    });
    assert.equal(response.status, 200, path);
    assert.equal(seen.length, 1);
    assert.equal(seen[0].url, `https://boards.example${path}?x=1`);
    // Marked so that a Custom Domain re-entering this Worker lets the app answer.
    assert.equal(seen[0].headers.get('X-RetroBoards-DCV-Passthrough'), '1');
    assert.equal(canonicalRedirect(seen[0], env, neverFetch), null, 'second pass falls through to the app');
  }
  // Only the validation prefixes qualify; a look-alike path is still redirected.
  assert.equal(canonicalRedirect(new Request('https://boards.example/.well-known/other'), env, neverFetch).status, 301);
  // The canonical host is unaffected: the app answers these paths as before.
  assert.equal(canonicalRedirect(new Request('https://forum.example/.well-known/pki-validation/x'), env, neverFetch), null);
});
