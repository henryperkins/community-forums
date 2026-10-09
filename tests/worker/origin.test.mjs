import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { fetchOrigin, originToken, resetOriginTokenCache } from '../../worker/origin.mjs';

const ORIGIN = 'https://retroboards-123.us-east4.run.app';
const TOKEN_URL = 'https://oauth2.googleapis.com/token';
const NOW_S = 1_800_000_000;

const keyPair = await crypto.subtle.generateKey(
  { name: 'RSASSA-PKCS1-v1_5', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
  true,
  ['sign', 'verify'],
);
const pkcs8 = Buffer.from(await crypto.subtle.exportKey('pkcs8', keyPair.privateKey)).toString('base64');
const env = {
  ORIGIN_URL: ORIGIN,
  GCP_INVOKER_KEY: JSON.stringify({
    type: 'service_account',
    client_email: 'retroboards-edge@project.iam.gserviceaccount.com',
    private_key_id: 'key-1',
    private_key: `-----BEGIN PRIVATE KEY-----\n${pkcs8.match(/.{1,64}/g).join('\n')}\n-----END PRIVATE KEY-----\n`,
  }),
};

/** A stand-in Google ID token: only its `exp` claim matters to the Worker. */
function idToken(exp, n = 1) {
  return `header.${Buffer.from(JSON.stringify({ exp })).toString('base64url')}.sig${n}`;
}

/** fetch() double for the token endpoint and the origin; records every call. */
function fakeNetwork({ exp = NOW_S + 3600, tokenStatus = 200, origin = () => new Response('ok') } = {}) {
  const calls = { token: [], origin: [] };
  const fetchImpl = async (input, init) => {
    if (String(input) === TOKEN_URL) {
      calls.token.push(init);
      if (tokenStatus !== 200) return new Response('nope', { status: tokenStatus });
      return Response.json({ id_token: idToken(exp, calls.token.length) });
    }
    calls.origin.push({ request: input, init });
    return origin(input);
  };
  return { calls, fetchImpl };
}

const at = (seconds) => () => seconds * 1000;

beforeEach(() => resetOriginTokenCache());

test('the assertion is an RS256 JWT signed by the service account, asking for an ID token for the service', async () => {
  const { calls, fetchImpl } = fakeNetwork();

  const token = await originToken(env, ORIGIN, { fetchImpl, now: at(NOW_S) });

  assert.equal(token, idToken(NOW_S + 3600));
  assert.equal(calls.token.length, 1);
  assert.equal(calls.token[0].method, 'POST');
  assert.equal(calls.token[0].headers['Content-Type'], 'application/x-www-form-urlencoded');
  const form = new URLSearchParams(calls.token[0].body);
  assert.equal(form.get('grant_type'), 'urn:ietf:params:oauth:grant-type:jwt-bearer');

  const [header, claims, signature] = form.get('assertion').split('.');
  assert.deepEqual(JSON.parse(Buffer.from(header, 'base64url')), { alg: 'RS256', typ: 'JWT', kid: 'key-1' });
  assert.deepEqual(JSON.parse(Buffer.from(claims, 'base64url')), {
    iss: 'retroboards-edge@project.iam.gserviceaccount.com',
    sub: 'retroboards-edge@project.iam.gserviceaccount.com',
    aud: TOKEN_URL,
    iat: NOW_S,
    exp: NOW_S + 3600,
    target_audience: ORIGIN,
  });
  assert.ok(
    await crypto.subtle.verify(
      'RSASSA-PKCS1-v1_5',
      keyPair.publicKey,
      Buffer.from(signature, 'base64url'),
      new TextEncoder().encode(`${header}.${claims}`),
    ),
    'signature verifies against the service account public key',
  );
});

test('a token is reused until five minutes before it expires, then renewed', async () => {
  const { calls, fetchImpl } = fakeNetwork({ exp: NOW_S + 3600 });

  await originToken(env, ORIGIN, { fetchImpl, now: at(NOW_S) });
  await originToken(env, ORIGIN, { fetchImpl, now: at(NOW_S + 3299) });
  assert.equal(calls.token.length, 1);

  await originToken(env, ORIGIN, { fetchImpl, now: at(NOW_S + 3301) });
  assert.equal(calls.token.length, 2);
});

test('a burst of requests shares one token exchange', async () => {
  const { calls, fetchImpl } = fakeNetwork();

  const tokens = await Promise.all([1, 2, 3].map(() => originToken(env, ORIGIN, { fetchImpl, now: at(NOW_S) })));

  assert.equal(calls.token.length, 1);
  assert.equal(new Set(tokens).size, 1);
});

test('a failed exchange rejects and is not cached', async () => {
  const failing = fakeNetwork({ tokenStatus: 503 });
  await assert.rejects(originToken(env, ORIGIN, { fetchImpl: failing.fetchImpl, now: at(NOW_S) }), /HTTP 503/);

  const working = fakeNetwork();
  assert.equal(await originToken(env, ORIGIN, { fetchImpl: working.fetchImpl, now: at(NOW_S) }), idToken(NOW_S + 3600));
  assert.equal(working.calls.token.length, 1);
});

test('a missing or malformed key fails before any network call', async () => {
  const { calls, fetchImpl } = fakeNetwork();

  await assert.rejects(originToken({}, ORIGIN, { fetchImpl, now: at(NOW_S) }), /invalid JSON/);
  await assert.rejects(
    originToken({ GCP_INVOKER_KEY: JSON.stringify({ client_email: 'x@y' }) }, ORIGIN, { fetchImpl, now: at(NOW_S) }),
    /missing private_key/,
  );
  assert.equal(calls.token.length, 0);
});

test('dynamic requests reach the service on the same path and query, carrying body, visitor IP and token', async () => {
  const { calls, fetchImpl } = fakeNetwork();
  const request = new Request('https://forum.example/t/7-title/reply?page=2', {
    method: 'POST',
    headers: {
      'CF-Connecting-IP': '198.51.100.23',
      'X-Forwarded-For': '6.6.6.6',
      'X-Serverless-Authorization': 'Bearer forged',
      Authorization: 'Bearer member-api-token',
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'body=hello',
  });

  await fetchOrigin(request, env, { fetchImpl, now: at(NOW_S) });

  assert.equal(calls.origin.length, 1);
  const { request: upstream, init } = calls.origin[0];
  assert.equal(upstream.url, `${ORIGIN}/t/7-title/reply?page=2`);
  assert.equal(upstream.method, 'POST');
  assert.equal(await upstream.text(), 'body=hello');
  // The client's forwarding header is replaced by the edge-verified address.
  assert.equal(upstream.headers.get('X-Forwarded-For'), '198.51.100.23');
  // Cloud Run authenticates X-Serverless-Authorization; the app's own API
  // bearer tokens keep travelling in Authorization.
  assert.equal(upstream.headers.get('X-Serverless-Authorization'), `Bearer ${idToken(NOW_S + 3600)}`);
  assert.equal(upstream.headers.get('Authorization'), 'Bearer member-api-token');
  assert.equal(upstream.headers.get('Content-Type'), 'application/x-www-form-urlencoded');
  assert.equal(init.redirect, 'manual');
});

// Regression (Codex security review on PR #84): the target was built with
// `new URL(path, origin)`, which reads `//host/x` as scheme-relative and sent
// the visitor's cookies, Authorization and the service token to that host.
test('paths that look like another authority stay on the service, with the token never leaving it', async () => {
  const { calls, fetchImpl } = fakeNetwork();

  for (const path of ['//attacker.example/collect?x=1', '/\\attacker.example/x', '/..//attacker.example/y', '/%2F%2Fattacker.example']) {
    await fetchOrigin(new Request(`https://forum.example${path}`, { headers: { Cookie: 'rb_session=secret' } }), env, {
      fetchImpl,
      now: at(NOW_S),
    });
  }

  assert.deepEqual(
    calls.origin.map(({ request }) => request.url),
    [
      `${ORIGIN}//attacker.example/collect?x=1`,
      `${ORIGIN}//attacker.example/x`,
      `${ORIGIN}//attacker.example/y`,
      `${ORIGIN}/%2F%2Fattacker.example`,
    ],
  );
  for (const { request } of calls.origin) {
    assert.equal(new URL(request.url).host, new URL(ORIGIN).host);
  }
});

test('/healthz, which Cloud Run reserves, reaches the app as /healthz/; other paths are untouched', async () => {
  const { calls, fetchImpl } = fakeNetwork();

  for (const path of ['/healthz', '/healthz?probe=1', '/t/9-quiz', '/healthz/']) {
    await fetchOrigin(new Request(`https://forum.example${path}`), env, { fetchImpl, now: at(NOW_S) });
  }

  assert.deepEqual(
    calls.origin.map(({ request }) => request.url),
    [`${ORIGIN}/healthz/`, `${ORIGIN}/healthz/?probe=1`, `${ORIGIN}/t/9-quiz`, `${ORIGIN}/healthz/`],
  );
});

test('without CF-Connecting-IP a client-sent X-Forwarded-For is dropped, not passed on', async () => {
  const { calls, fetchImpl } = fakeNetwork();

  await fetchOrigin(new Request('https://forum.example/', { headers: { 'X-Forwarded-For': '6.6.6.6' } }), env, {
    fetchImpl,
    now: at(NOW_S),
  });

  assert.equal(calls.origin[0].request.headers.get('X-Forwarded-For'), null);
});

test("the app's redirects and cookies go back to the browser unchanged", async () => {
  const { fetchImpl } = fakeNetwork({
    origin: () =>
      new Response(null, { status: 302, headers: { Location: '/setup', 'Set-Cookie': 'rb_session=abc; Secure; HttpOnly' } }),
  });

  const response = await fetchOrigin(new Request('https://forum.example/'), env, { fetchImpl, now: at(NOW_S) });

  assert.equal(response.status, 302);
  assert.equal(response.headers.get('Location'), '/setup');
  assert.equal(response.headers.get('Set-Cookie'), 'rb_session=abc; Secure; HttpOnly');
});
