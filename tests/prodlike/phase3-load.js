import { check, group, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import encoding from 'k6/encoding';
import http from 'k6/http';

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8021';
const USER_EMAIL = __ENV.RB_USER_EMAIL || 'bob@retro.test';
const USER_PASSWORD = __ENV.RB_USER_PASSWORD || 'password123';
const tinyPng = encoding.b64decode(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC',
);

export const options = {
  scenarios: {
    closeout: {
      executor: 'constant-vus',
      vus: Number(__ENV.K6_VUS || 20),
      duration: __ENV.K6_DURATION || '15m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
    http_5xx: ['count==0'],
    'read_latency': ['p(95)<750'],
    'write_latency': ['p(95)<1500'],
  },
};

const readLatency = new Trend('read_latency', true);
const writeLatency = new Trend('write_latency', true);
const fiveXx = new Counter('http_5xx');

function record(res, trend) {
  trend.add(res.timings.duration);
  if (res.status >= 500) {
    fiveXx.add(1);
  }
  check(res, {
    'status is not 5xx': (r) => r.status < 500,
  }, { status_class: res.status >= 500 ? '5xx' : 'non5xx' });
  return res;
}

function csrf(html) {
  const match = html.match(/name="_token" value="([^"]+)"/);
  return match ? match[1] : '';
}

function login() {
  const jar = http.cookieJar();
  const loginPage = record(http.get(`${BASE_URL}/login`, { jar }), readLatency);
  const token = csrf(loginPage.body);
  check(token, { 'login csrf found': (t) => t.length > 20 });
  const res = record(http.post(`${BASE_URL}/login`, {
    _token: token,
    email: USER_EMAIL,
    password: USER_PASSWORD,
    next: '/',
  }, { jar, redirects: 0 }), writeLatency);
  check(res, { 'login redirects': (r) => [302, 303].includes(r.status) });
  return jar;
}

function authenticatedRead(jar) {
  for (const path of ['/settings/preferences', '/drafts', '/c/general']) {
    const res = record(http.get(`${BASE_URL}${path}`, { jar }), readLatency);
    check(res, { [`GET ${path} ok`]: (r) => r.status === 200 });
  }
}

function anonymousRead() {
  for (const path of ['/', '/c/general', '/search?q=keyboard']) {
    const res = record(http.get(`${BASE_URL}${path}`), readLatency);
    check(res, { [`GET ${path} ok`]: (r) => r.status === 200 });
  }
}

function composerPreview(jar) {
  if (__ITER % 80 !== 0) {
    return;
  }
  const page = record(http.get(`${BASE_URL}/c/general`, { jar }), readLatency);
  const token = csrf(page.body);
  const res = record(http.post(`${BASE_URL}/composer/preview`, {
    _token: token,
    body: 'k6 **preview** :smile:',
  }, {
    jar,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  }), writeLatency);
  check(res, {
    'preview ok': (r) => r.status === 200 && r.json('ok') === true,
  });
}

function serverDraftCycle(jar) {
  const page = record(http.get(`${BASE_URL}/c/general`, { jar }), readLatency);
  const token = csrf(page.body);
  const key = encodeURIComponent(`/threads-k6-${__VU}`).replace(/%/g, '~');
  const save = record(http.post(`${BASE_URL}/api/drafts/${key}`, {
    _token: token,
    revision: '0',
    title: `k6 draft ${__VU}`,
    body: `k6 draft body ${Date.now()}`,
    metadata: JSON.stringify({ source: 'k6' }),
  }, {
    jar,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  }), writeLatency);
  check(save, { 'draft save ok': (r) => r.status === 200 });

  const load = record(http.get(`${BASE_URL}/api/drafts/${key}`, {
    jar,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  }), readLatency);
  check(load, { 'draft load ok': (r) => r.status === 200 });

  const discard = record(http.post(`${BASE_URL}/api/drafts/${key}/discard`, {
    _token: token,
  }, {
    jar,
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  }), writeLatency);
  check(discard, { 'draft discard ok': (r) => r.status === 200 });
}

function tinyUpload(jar) {
  if (__VU !== 1 || __ITER % 75 !== 0) {
    return;
  }
  const page = record(http.get(`${BASE_URL}/c/general`, { jar }), readLatency);
  const token = csrf(page.body);
  const res = record(http.post(`${BASE_URL}/upload`, {
    _token: token,
    purpose: 'post',
    image: http.file(tinyPng, `tiny-${__VU}-${__ITER}.png`, 'image/png'),
  }, {
    jar,
    timeout: '10s',
  }), writeLatency);
  check(res, { 'tiny upload accepted': (r) => r.status === 200 && r.json('ok') === true });
}

export default function () {
  group('anonymous reads', anonymousRead);
  const jar = login();
  group('authenticated reads', () => authenticatedRead(jar));
  group('composer preview', () => composerPreview(jar));
  group('server draft save/load/discard', () => serverDraftCycle(jar));
  group('low-rate tiny image upload', () => tinyUpload(jar));
  sleep(1);
}

export function handleSummary(data) {
  return {
    stdout: textSummary(data),
    '/evidence/phase3-load-summary.json': JSON.stringify(data, null, 2),
  };
}

function textSummary(data) {
  const metrics = data.metrics;
  const lines = [
    'Phase 3 prodlike load summary',
    `http_req_failed rate: ${metrics.http_req_failed?.values?.rate ?? 'n/a'}`,
    `read_latency p95: ${metrics.read_latency?.values?.['p(95)'] ?? 'n/a'} ms`,
    `write_latency p95: ${metrics.write_latency?.values?.['p(95)'] ?? 'n/a'} ms`,
    `checks rate: ${metrics.checks?.values?.rate ?? 'n/a'}`,
    '',
  ];
  return lines.join('\n');
}
