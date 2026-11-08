/* Local byte and Worker-route checks; never contacts an origin or provider. */
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile, access } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
if (process.argv.length !== 5) throw new Error('Pass the live report, original main manifest and original layout manifest.');
const readJson = async file => JSON.parse(await readFile(file, 'utf8'));
const [live, original, layout, manifest] = await Promise.all([
  ...process.argv.slice(2).map(readJson), readJson(path.join(root, 'config/assets.json')),
]);
const { routeRequest } = await import(pathToFileURL(path.join(root, 'worker/assets.mjs')).href);
const sha = bytes => createHash('sha256').update(bytes).digest('hex');
assert.equal(live.ok, true);
assert.equal(live.version, '0ab71f5ca65e7fa0');
assert.equal(live.release_files_checked, 11);
assert.equal(live.release_files_matched, 11);
assert.equal(manifest.version, layout.version, 'retention must not change the current candidate');
assert.deepEqual(manifest.urls, layout.urls);
assert.deepEqual(manifest.deployedReleases.map(r => r.version), [
  '0ab71f5ca65e7fa0', '5dc1b40f3b23d46d', 'c66e3b55dba6da68',
]);
const noOrigin = () => { throw new Error('A local asset check reached the origin'); };
const env = { ASSETS: { fetch: async request => {
  const url = new URL(request.url);
  const bytes = await readFile(path.join(root, '.build/static', url.pathname));
  return new Response(bytes);
} } };
const verified = [];
for (const [url, metadata] of Object.entries(manifest.files)) {
  const bytes = await readFile(path.join(root, 'public', url));
  const staged = await readFile(path.join(root, '.build/static', url));
  assert.equal(sha(bytes), metadata.sha256, url);
  assert.equal(sha(staged), metadata.sha256, url);
  const response = await routeRequest(new Request(`https://forum.example${url}`), env, noOrigin);
  assert.equal(response.status, 200, url);
  assert.equal(sha(Buffer.from(await response.arrayBuffer())), metadata.sha256, url);
  assert.equal(response.headers.get('X-RetroBoards-Cache'), 'STATIC', url);
  if (metadata.immutable) assert.match(response.headers.get('Cache-Control'), /immutable/, url);
  verified.push({ url, sha256: metadata.sha256, public_match: true, worker_static_match: true, worker_status: 200 });
}
for (const file of live.release_files) {
  assert.equal(file.match, true);
  assert.equal(manifest.files[file.url]?.sha256, file.sha256, file.url);
}
const candidate = manifest.releases.find(r => r.version === manifest.version);
assert.ok(candidate);
for (const url of candidate.files) assert.equal(manifest.files[url]?.sha256, layout.files[url]?.sha256, url);
for (const release of manifest.releases) {
  for (const url of release.files) assert.ok(verified.find(row => row.url === url), url);
}
const retired = original.deployedReleases.find(r => r.version === '3630440af0d45598');
assert.ok(retired);
const removed = retired.files.filter(url => !manifest.files[url]).sort();
assert.deepEqual(removed, [
  '/assets/dist/app-e5669eff1f842115.js', '/assets/dist/app-style-D7aglA01.css',
  '/assets/dist/composer-c1ff478070e3a1d8.js', '/assets/dist/tour-f068bdb2d74e6d21.js',
].sort());
for (const url of removed) {
  for (const base of ['public', '.build/static']) await assert.rejects(access(path.join(root, base, url)));
  const response = await routeRequest(new Request(`https://forum.example${url}`), {
    ASSETS: { fetch: () => { throw new Error('Retired URL reached the static binding'); } },
  }, noOrigin);
  assert.equal(response.status, 404, url);
  assert.equal(response.headers.get('Cache-Control'), 'no-store', url);
}
const futurePins = [candidate, ...manifest.deployedReleases.slice(0, 2)];
const futureClosure = new Set(futurePins.flatMap(r => r.files));
const nextOldest = manifest.deployedReleases.at(-1);
console.log(JSON.stringify({
  ok: true, candidate: manifest.version, candidate_files: candidate.files.length,
  candidate_urls_and_bytes_unchanged_from_original_layout: true,
  verified_live_release: live.version, live_files_preserved: live.release_files.length,
  deployed_pins: manifest.deployedReleases.map(r => ({ version: r.version, files: r.files.length })),
  delivery_releases: manifest.releases.map(r => r.version),
  allowlist_files: verified.length, immutable_files: verified.filter(r => manifest.files[r.url].immutable).length,
  all_files: verified,
  retired_by_pr_96: { version: retired.version, exclusive_files: removed, local_worker_status: 404 },
  future_record_only_after_verified_deployment: {
    would_retire: nextOldest.version,
    exclusive_files: nextOldest.files.filter(url => !futureClosure.has(url)).sort(),
    performed: false,
  },
}, null, 2));
