import { createHash } from 'node:crypto';
import { lstat, mkdir, mkdtemp, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Script } from 'node:vm';
import { build, minify } from 'vite';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const check = process.argv.includes('--check');
const temporary = await mkdtemp(path.join(os.tmpdir(), 'retroboards-assets-'));
const sourceNames = ['app.css', 'imladris.css', 'app.js', 'composer.js', 'passkeys.js', 'tour.js'];
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
  const files = Object.fromEntries([...published].sort(([a], [b]) => a.localeCompare(b, 'en')).map(([url, content]) => [
    url, { sha256: sha256(content), immutable: url.startsWith('/assets/dist/') },
  ]));
  const manifest = JSON.stringify({
    version: sha256(JSON.stringify(files)).slice(0, 16),
    urls,
    files,
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
    // Clear the managed output first, including old hashed chunks.
    await rm(outputDirectory, { recursive: true, force: true });
    await mkdir(outputDirectory, { recursive: true });
    for (const [file, content] of generated) await writeFile(path.join(outputDirectory, file), content);
    await writeFile(path.join(root, 'config/assets.json'), manifest);
    const staticDirectory = path.join(root, '.build/static');
    await rm(staticDirectory, { recursive: true, force: true });
    for (const [url, content] of published) {
      const target = path.join(staticDirectory, url.slice(1));
      await mkdir(path.dirname(target), { recursive: true });
      await writeFile(target, content);
    }
    console.log(`Built ${generated.size} delivery files; staged ${published.size} public assets for Workers.`);
  }
} finally {
  await rm(temporary, { recursive: true, force: true });
}
