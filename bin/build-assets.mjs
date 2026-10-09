import { createHash } from 'node:crypto';
import { cp, lstat, mkdir, mkdtemp, readFile, readdir, rename, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Script } from 'node:vm';
import { build, minify } from 'vite';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const check = args.length === 1 && args[0] === '--check';
const recordVersion = args.length === 2 && args[0] === '--record-release' && /^[a-f0-9]{16}$/.test(args[1])
  ? args[1] : null;
if (args.length && !check && !recordVersion) {
  throw new Error('Usage: node bin/build-assets.mjs [--check | --record-release <deployed-version>]');
}
const temporary = await mkdtemp(path.join(os.tmpdir(), 'retroboards-assets-'));
const sourceNames = ['app.css', 'imladris.css', 'conversation-entry.js', 'app.js', 'composer.js', 'passkeys.js', 'tour.js'];
const sha256 = (content) => createHash('sha256').update(content).digest('hex');

async function filesWithin(directory, prefix = '') {
  const files = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const relative = prefix + entry.name;
    if (entry.isSymbolicLink()) throw new Error(`Refusing to publish a symlink: ${relative}`);
    if (entry.isDirectory()) files.push(...await filesWithin(path.join(directory, entry.name), `${relative}/`));
    else if (entry.isFile()) files.push(relative);
  }
  return files.sort();
}

async function readSource(relative) {
  const file = path.join(root, 'public', relative);
  if (!(await lstat(file)).isFile()) throw new Error(`Not an asset file: ${relative}`);
  return readFile(file);
}

async function publishAssets(generated, published, manifest) {
  const stagingDirectories = [];
  const replacements = [];
  let preserveBackups = false;
  const stageBeside = async target => {
    await mkdir(path.dirname(target), { recursive: true });
    const directory = await mkdtemp(path.join(path.dirname(target), '.retroboards-assets-'));
    stagingDirectories.push(directory);
    return directory;
  };
  try {
    // Retained files are build inputs. Stage complete replacements beside their
    // targets before publication, and keep backups until the manifest commits.
    for (const [target, files] of [
      [path.join(root, 'public/assets/dist'), generated],
      [path.join(root, '.build/static'), new Map([...published].map(([url, content]) => [url.slice(1), content]))],
    ]) {
      const staging = await stageBeside(target);
      const ready = path.join(staging, 'next');
      await mkdir(ready);
      for (const [file, content] of files) {
        const destination = path.join(ready, file);
        await mkdir(path.dirname(destination), { recursive: true });
        await writeFile(destination, content);
      }
      replacements.push({ target, ready, backup: path.join(staging, 'previous'), backedUp: false, installed: false });
    }
    const manifestTarget = path.join(root, 'config/assets.json');
    const manifestReady = path.join(await stageBeside(manifestTarget), 'next');
    await writeFile(manifestReady, manifest);

    for (const replacement of replacements) {
      try {
        await rename(replacement.target, replacement.backup);
        replacement.backedUp = true;
      } catch (error) {
        if (error.code === 'EXDEV') {
          // Docker's overlayfs can refuse renaming a directory from a lower
          // image layer even beside itself. Complete the backup before removal.
          await cp(replacement.target, replacement.backup, { recursive: true, verbatimSymlinks: true });
          replacement.backedUp = true;
          await rm(replacement.target, { recursive: true, force: true });
        } else if (error.code !== 'ENOENT') throw error;
      }
      await rename(replacement.ready, replacement.target);
      replacement.installed = true;
    }
    // Commit the manifest only after both complete asset trees are installed.
    await rename(manifestReady, manifestTarget);
  } catch (error) {
    const recoveryErrors = [];
    for (const replacement of replacements.toReversed()) {
      try {
        if (replacement.installed || replacement.backedUp) {
          await rm(replacement.target, { recursive: true, force: true });
        }
        if (replacement.backedUp) await rename(replacement.backup, replacement.target);
      } catch (recoveryError) {
        recoveryErrors.push(recoveryError);
      }
    }
    if (recoveryErrors.length) {
      preserveBackups = true;
      throw new AggregateError([error, ...recoveryErrors],
        `Asset publication rollback failed; preserve and inspect backups in ${stagingDirectories.join(', ')}`);
    }
    throw error;
  } finally {
    if (!preserveBackups) {
      for (const directory of stagingDirectories) await rm(directory, { recursive: true, force: true });
    }
  }
}

try {
  await build({
    root,
    configFile: path.join(root, 'vite.config.mjs'),
    build: { outDir: temporary, emptyOutDir: true },
  });
  const vite = JSON.parse(await readFile(path.join(temporary, '.vite/manifest.json'), 'utf8'));
  const urls = {};
  for (const name of sourceNames) {
    if (name.endsWith('.js')) {
      // Minify the classic scripts without Vite's module graph transform. That
      // transform can remove strict directives or inject shared static imports
      // for a native dynamic import, neither valid for a deferred classic script.
      const source = (await readSource(`assets/${name}`)).toString();
      const result = await minify(name, source, { module: false });
      if (result.errors.length) throw new Error(`Cannot minify ${name}: ${JSON.stringify(result.errors)}`);
      const code = `'use strict';\n${result.code}\n`;
      new Script(code, { filename: name });
      const file = `${name.slice(0, -3)}-${sha256(code).slice(0, 16)}.js`;
      await writeFile(path.join(temporary, file), code);
      urls[name] = `/assets/dist/${file}`;
    } else {
      urls[name] = `/assets/dist/${vite[`public/assets/${name}`].file}`;
    }
  }
  const editor = vite['src/client/wysiwyg/index.ts'];
  if (editor.dynamicImports?.length !== 1 || !editor.css?.[0]) {
    throw new Error('The editor must retain its separate lazy adapter and stylesheet');
  }
  urls['wysiwyg-composer.js'] = `/assets/dist/${editor.file}`;
  urls['wysiwyg-composer.css'] = `/assets/dist/${editor.css[0]}`;
  for (const [source, entry] of Object.entries(vite)) {
    if (source.startsWith('public/assets/fonts/')) {
      urls[source.slice('public/assets/'.length)] = `/assets/dist/${entry.file}`;
    }
  }

  // Only this reviewed closure is published. In particular, never stage all of
  // public/: it contains index.php, readiness files, and may contain uploads.
  const published = new Map();
  for (const name of [...sourceNames, 'elven-star.svg']) {
    published.set(`/assets/${name}`, await readSource(`assets/${name}`));
  }
  for (const font of await filesWithin(path.join(root, 'public/assets/fonts'))) {
    if (font.endsWith('.woff2') || /\/LICENSES\/[^/]+\.txt$/.test(font)) {
      published.set(`/assets/fonts/${font}`, await readSource(`assets/fonts/${font}`));
    }
  }
  const generated = new Map();
  for (const file of await filesWithin(temporary)) {
    if (file.startsWith('.vite/')) continue;
    if (!/\.(?:js|css|woff2)$/.test(file)) throw new Error(`Unexpected build output: ${file}`);
    const content = await readFile(path.join(temporary, file));
    generated.set(file, content);
    published.set(`/assets/dist/${file}`, content);
  }
  const describeFiles = () => Object.fromEntries([...published].sort(([a], [b]) => a.localeCompare(b, 'en')).map(([url, content]) => [
    url, { sha256: sha256(content), immutable: url.startsWith('/assets/dist/') },
  ]));
  // HTML and the Worker can straddle a deployment; an open page can also defer
  // its editor import. Pin deployed releases separately from the current local
  // candidate: edits, previews and failed deployments must not consume history.
  // Hash only the current build, independently of those retained files.
  const version = sha256(JSON.stringify(describeFiles())).slice(0, 16);
  const previous = await readFile(path.join(root, 'config/assets.json'), 'utf8')
    .then(JSON.parse).catch(error => { if (error.code === 'ENOENT') return null; throw error; });
  // Upgrade old manifests once by conservatively pinning their existing window.
  const history = previous?.deployedReleases ?? previous?.releases ?? (previous ? [{
    version: previous.version,
    files: Object.keys(previous.files).filter(url => url.startsWith('/assets/dist/')),
  }] : []);
  if (!Array.isArray(history) || history.length > 3) throw new Error('Invalid deployed asset release baseline');
  const currentRelease = { version, files: [...generated.keys()].sort().map(file => `/assets/dist/${file}`) };
  if (recordVersion && (recordVersion !== version || recordVersion !== previous?.version)) {
    throw new Error('Confirmed release version does not match the current sources and previously built manifest');
  }
  // Only an explicit post-deployment record advances the baseline. Keep all
  // three pins during local builds so reverting sources can also reproduce it.
  const deployedReleases = recordVersion
    ? [currentRelease, ...history.filter(release => release.version !== version)].slice(0, 3)
    : history;
  const releases = [currentRelease, ...deployedReleases.filter(release => release.version !== version)];
  for (const release of releases.slice(1)) {
    for (const url of release.files) {
      if (!/^\/assets\/dist\/[A-Za-z0-9_-]+\.(?:js|css|woff2)$/.test(url)
        || previous.files[url]?.immutable !== true) {
        throw new Error(`Invalid retained asset: ${url}`);
      }
      const content = published.get(url) ?? await readSource(url.slice(1));
      if (sha256(content) !== previous.files[url].sha256) {
        throw new Error(`Retained asset digest mismatch: ${url}`);
      }
      generated.set(path.basename(url), content);
      published.set(url, content);
    }
  }
  const manifest = JSON.stringify({
    version,
    urls,
    files: describeFiles(),
    releases,
    deployedReleases,
  }, null, 2) + '\n';
  const outputDirectory = path.join(root, 'public/assets/dist');

  if (check) {
    const stale = [];
    const actualFiles = await filesWithin(outputDirectory).catch(() => []);
    for (const file of new Set([...actualFiles, ...generated.keys()])) {
      const actual = await readFile(path.join(outputDirectory, file)).catch(() => null);
      const expected = generated.get(file);
      if (!actual || !expected || !actual.equals(expected)) stale.push(`public/assets/dist/${file}`);
    }
    if (await readFile(path.join(root, 'config/assets.json'), 'utf8').catch(() => '') !== manifest) stale.push('config/assets.json');
    if (stale.length) throw new Error(`Generated assets are stale; run npm run build:\n${stale.join('\n')}`);
    console.log('Generated assets and manifest are current.');
  } else {
    // Superseded local candidates and explicitly retired releases leave both
    // delivery directories; every pinned deployment remains available.
    await publishAssets(generated, published, manifest);
    console.log(`Built ${generated.size} delivery files; staged ${published.size} public assets for Workers.`);
    if (recordVersion) console.log(`Recorded deployed asset release ${recordVersion}.`);
  }
} finally {
  await rm(temporary, { recursive: true, force: true });
}
