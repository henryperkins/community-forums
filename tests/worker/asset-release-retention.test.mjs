import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { copyFile, mkdir, mkdtemp, readFile, readdir, rm, symlink, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { promisify } from 'node:util';
import { test } from 'node:test';
import { createHash } from 'node:crypto';

const run = promisify(execFile);
const root = new URL('../../', import.meta.url);

async function fixture(t) {
  const directory = await mkdtemp(path.join(os.tmpdir(), 'retroboards-asset-releases-'));
  t.after(() => rm(directory, { recursive: true, force: true }));
  const put = async (name, content) => {
    const file = path.join(directory, name);
    await mkdir(path.dirname(file), { recursive: true });
    await writeFile(file, content);
  };
  const build = (...args) => run(process.execPath, ['bin/build-assets.mjs', ...args], { cwd: directory });
  const manifest = async () => JSON.parse(await readFile(path.join(directory, 'config/assets.json'), 'utf8'));
  await mkdir(path.join(directory, 'bin'));
  await mkdir(path.join(directory, 'config'));
  await copyFile(new URL('bin/build-assets.mjs', root), path.join(directory, 'bin/build-assets.mjs'));
  await copyFile(new URL('vite.config.mjs', root), path.join(directory, 'vite.config.mjs'));
  await symlink(new URL('node_modules', root).pathname, path.join(directory, 'node_modules'), 'dir');
  await put('public/assets/imladris.css', '@font-face{font-family:fixture;src:url(./fonts/fixture.woff2)}');
  await put('public/assets/fonts/fixture.woff2', 'font fixture');
  await put('public/assets/elven-star.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
  for (const name of ['app', 'composer', 'passkeys', 'tour']) {
    await put(`public/assets/${name}.js`, `window.${name}Loaded = true;`);
  }
  await put('src/client/wysiwyg/index.ts', 'import "./editor.css"; export const load = () => import("./milkdown-adapter");');
  await put('src/client/wysiwyg/editor.css', '.editor{display:block}');

  const changeSources = async revision => {
    await put('public/assets/app.css', `.app-shell{display:grid;--release:${revision}}`);
    await put('src/client/wysiwyg/milkdown-adapter.ts', `export const release = ${revision};`);
    await put('public/assets/fonts/fixture.woff2', `font fixture ${revision}`);
  };
  const snapshot = async current => new Map(await Promise.all(current.releases[0].files.map(async url => [
    url, await readFile(path.join(directory, 'public', url)),
  ])));
  const assertAvailable = async (current, files) => {
    for (const [url, bytes] of files) {
      assert.ok(current.files[url], `Build dropped a deployed page dependency: ${url}`);
      assert.deepEqual(await readFile(path.join(directory, 'public', url)), bytes, url);
      assert.deepEqual(await readFile(path.join(directory, '.build/static', url)), bytes, url);
    }
  };
  return { directory, put, build, manifest, changeSources, snapshot, assertAvailable };
}

test('confirmed releases keep recent pages and lazy imports working, then retire the oldest release', async t => {
  const { directory, put, build, manifest, changeSources, snapshot, assertAvailable } = await fixture(t);

  const snapshots = [];
  for (let release = 1; release <= 4; release++) {
    await changeSources(release);
    await build();
    await build('--record-release', (await manifest()).version);
    const current = await manifest();
    for (const previous of snapshots.slice(-2)) {
      // A saved HTML page must still get its exact stylesheet and the entire
      // editor module graph, even if its first import happens after release.
      await assertAvailable(current, previous);
    }
    snapshots.push(await snapshot(current));
    if (release === 1) {
      // Upgrade existing installations whose manifests predate retention.
      delete current.releases;
      delete current.deployedReleases;
      await put('config/assets.json', JSON.stringify(current));
    }
    if (release === 4) {
      const firstCss = [...snapshots[0].keys()].find(url => url.includes('/app-style-'));
      assert.ok(!current.files[firstCss], 'The expired stylesheet must leave the public allowlist');
      await assert.rejects(readFile(path.join(directory, 'public', firstCss)), { code: 'ENOENT' });
      await assert.rejects(readFile(path.join(directory, '.build/static', firstCss)), { code: 'ENOENT' });
    }
  }

  const before = await readFile(path.join(directory, 'config/assets.json'), 'utf8');
  await build();
  assert.equal(await readFile(path.join(directory, 'config/assets.json'), 'utf8'), before,
    'Rebuilding the same release must not consume the retention window');
  await build('--check');

  const current = await manifest();
  for (const [url, file] of Object.entries(current.files)) {
    const bytes = await readFile(path.join(directory, 'public', url));
    assert.equal(createHash('sha256').update(bytes).digest('hex'), file.sha256, url);
  }
  assert.equal((await readdir(path.join(directory, 'public/assets/dist'))).length,
    Object.keys(current.files).filter(url => url.startsWith('/assets/dist/')).length);

  const retained = [...snapshots[2].keys()].find(url => url.includes('/app-style-'));
  await put(`public${retained}`, 'corrupted stylesheet');
  await assert.rejects(build(), /Retained asset digest mismatch/,
    'Immutable URLs must never be republished with altered bytes');
  assert.equal(await readFile(path.join(directory, 'config/assets.json'), 'utf8'), before,
    'A failed build must preserve the last valid manifest');
  await put(`public${retained}`, snapshots[2].get(retained));
  current.deployedReleases[1].files.push('/assets/private.env');
  current.files['/assets/private.env'] = { immutable: true, sha256: '0'.repeat(64) };
  await put('config/assets.json', JSON.stringify(current));
  await assert.rejects(build(), /Invalid retained asset/,
    'History must not widen the public asset closure to private or source files');
});

test('repeated local builds and source reverts preserve the deployed baseline', async t => {
  const { directory, put, build, manifest, changeSources, snapshot, assertAvailable } = await fixture(t);
  const deployed = [];
  for (let revision = 1; revision <= 3; revision++) {
    await changeSources(revision);
    await build();
    await build('--record-release', (await manifest()).version);
    deployed.push(await snapshot(await manifest()));
  }
  const baseline = await manifest();
  // Upgrade the intermediate retention format by pinning its full window once.
  const legacy = { ...baseline };
  delete legacy.deployedReleases;
  await put('config/assets.json', JSON.stringify(legacy));
  let firstDraftCss;
  for (let revision = 4; revision <= 7; revision++) {
    await changeSources(revision);
    await build();
    const current = await manifest();
    // No deployment occurs between these edits. Even the fourth local build
    // must still serve the exact CSS, lazy modules and fonts of the live page.
    await assertAvailable(current, deployed.at(-1));
    if (revision === 4) firstDraftCss = current.urls['app.css'];
  }
  const candidate = await manifest();
  for (const files of deployed) await assertAvailable(candidate, files);
  assert.deepEqual(candidate.deployedReleases, baseline.deployedReleases);
  assert.equal(candidate.releases.length, 4, 'Only the candidate and three deployed releases are retained');
  assert.ok(!candidate.files[firstDraftCss], 'Superseded local drafts must not accumulate');
  await assert.rejects(readFile(path.join(directory, 'public', firstDraftCss)), { code: 'ENOENT' });
  await assert.rejects(readFile(path.join(directory, '.build/static', firstDraftCss)), { code: 'ENOENT' });
  await build('--check');

  await changeSources(3);
  await build();
  assert.deepEqual(await manifest(), baseline, 'A source revert must reproduce the unchanged deployed manifest');
  for (const files of deployed) await assertAvailable(await manifest(), files);
});

test('recording a release requires the exact previously built version and never happens during checks', async t => {
  const { directory, build, manifest, changeSources } = await fixture(t);
  await changeSources(1);
  await build();
  const first = await manifest();
  await build('--record-release', first.version);
  await changeSources(2);
  await build();
  const candidate = await manifest();
  const before = await readFile(path.join(directory, 'config/assets.json'), 'utf8');

  await assert.rejects(build('--record-release', first.version), /does not match/);
  await assert.rejects(build('--record-release'), /Usage/);
  await assert.rejects(build('--check', '--record-release', candidate.version), /Usage/);
  await changeSources(3);
  await assert.rejects(build('--record-release', candidate.version), /does not match/);
  assert.equal(await readFile(path.join(directory, 'config/assets.json'), 'utf8'), before,
    'Rejected promotions must not change the deployed baseline or manifest');
  await changeSources(2);
  await build('--check');
  assert.equal(await readFile(path.join(directory, 'config/assets.json'), 'utf8'), before);

  await build('--record-release', candidate.version);
  assert.equal((await manifest()).deployedReleases[0].version, candidate.version);
  const recorded = await readFile(path.join(directory, 'config/assets.json'), 'utf8');
  await build('--record-release', candidate.version);
  assert.equal(await readFile(path.join(directory, 'config/assets.json'), 'utf8'), recorded,
    'Recording the same deployment twice must be idempotent');
});

for (const failure of ['write', 'static-install', 'manifest-install', 'overlay-static-install']) {
  test(`a ${failure} failure preserves published assets and permits a normal retry`, async t => {
    const { directory, put, build, manifest, changeSources, snapshot, assertAvailable } = await fixture(t);
    await changeSources(1);
    await build();
    await build('--record-release', (await manifest()).version);
    const deployed = await snapshot(await manifest());
    await changeSources(2);
    await build();
    const previous = await manifest();
    const candidate = await snapshot(previous);
    const before = await readFile(path.join(directory, 'config/assets.json'), 'utf8');
    const staticFiles = new Map(await Promise.all(Object.keys(previous.files).map(async url => [
      url, await readFile(path.join(directory, '.build/static', url)),
    ])));
    await changeSources(3);
    await put('fail-publication.mjs', `
      import fs from 'node:fs';
      import { syncBuiltinESMExports } from 'node:module';
      const writeFile = fs.promises.writeFile;
      const rename = fs.promises.rename;
      let failed = false;
      const fail = () => { failed = true; throw Object.assign(new Error('simulated ENOSPC'), { code: 'ENOSPC' }); };
      fs.promises.writeFile = async (file, ...args) => {
        if (${JSON.stringify(failure)} === 'write' && String(file).includes('/public/assets/')) fail();
        return writeFile(file, ...args);
      };
      fs.promises.rename = async (source, destination) => {
        if (${JSON.stringify(failure)} === 'overlay-static-install' && String(destination).endsWith('/previous')) {
          throw Object.assign(new Error('simulated overlayfs EXDEV'), { code: 'EXDEV' });
        }
        if (!failed && ((['static-install', 'overlay-static-install'].includes(${JSON.stringify(failure)}) && String(destination).endsWith('/.build/static'))
          || (${JSON.stringify(failure)} === 'manifest-install' && String(destination).endsWith('/config/assets.json')))) fail();
        return rename(source, destination);
      };
      syncBuiltinESMExports();
    `);
    await assert.rejects(run(process.execPath, ['--import', './fail-publication.mjs', 'bin/build-assets.mjs'],
      { cwd: directory }), /simulated ENOSPC/);
    assert.equal(await readFile(path.join(directory, 'config/assets.json'), 'utf8'), before);
    await assertAvailable(previous, deployed);
    await assertAvailable(previous, candidate);
    for (const [url, bytes] of staticFiles) {
      assert.deepEqual(await readFile(path.join(directory, '.build/static', url)), bytes, url);
    }
    await build();
    await assertAvailable(await manifest(), deployed);
    await build('--check');
  });
}

test('overlayfs directory backups permit publication without losing deployed files', async t => {
  const { directory, put, build, manifest, changeSources, snapshot, assertAvailable } = await fixture(t);
  await changeSources(1);
  await build();
  await build('--record-release', (await manifest()).version);
  const deployed = await snapshot(await manifest());
  await changeSources(2);
  await put('overlayfs.mjs', `
    import fs from 'node:fs';
    import { syncBuiltinESMExports } from 'node:module';
    const rename = fs.promises.rename;
    fs.promises.rename = async (source, destination) => {
      if (String(destination).endsWith('/previous')) {
        throw Object.assign(new Error('simulated overlayfs EXDEV'), { code: 'EXDEV' });
      }
      return rename(source, destination);
    };
    syncBuiltinESMExports();
  `);
  await assert.doesNotReject(run(process.execPath, ['--import', './overlayfs.mjs', 'bin/build-assets.mjs'],
    { cwd: directory }));
  await assertAvailable(await manifest(), deployed);
  await build('--check');
});
