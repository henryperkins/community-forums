// Focused evidence runner using Playwright CLI, the real PHP app and a private DB.
// Prepare the migrated disposable database first; see README.md beside this file.
const assert = require('node:assert/strict');
const { spawn, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');

const root = path.resolve(__dirname, '../../..');
const scratch = path.join(root, 'output/playwright/email-dkim-selector-20261018');
const database = process.env.DB_DATABASE || 'retroboards_e2e_dkim_20261018';
assert.match(database, /^retroboards_e2e_dkim_[a-z0-9_]+$/);
const port = Number(process.env.E2E_PORT || 8078);
assert(Number.isInteger(port) && port > 1024 && port < 65536);
const baseURL = `http://localhost:${port}`;
const env = {
  ...process.env, APP_ENV: 'test', DB_DATABASE: database,
  APP_KEY: '0'.repeat(64), APP_URL: baseURL, SESSION_SECURE: 'false',
  MAIL_DRIVER: 'array', MAIL_FROM: 'dkim-evidence@example.test', MAIL_DKIM_SELECTOR: 'cf2024-1',
  RATELIMIT_PATH: path.join(root, 'storage/ratelimit-e2e-dkim-20261018'),
  PACKAGES_STORAGE_PATH: path.join(root, 'storage/packages-e2e-dkim-20261018'),
};
const cli = process.env.PLAYWRIGHT_CLI_WRAPPER
  || path.join(process.env.CODEX_HOME || path.join(process.env.HOME, '.codex'), 'skills/playwright/scripts/playwright_cli.sh');
const browserCache = path.join(process.env.HOME, '.cache/ms-playwright');
const chromiumDir = fs.readdirSync(browserCache).filter(name => /^chromium-\d+$/.test(name))
  .sort((a, b) => Number(b.split('-')[1]) - Number(a.split('-')[1]))[0];
const chromiumPath = process.env.E2E_CHROMIUM_PATH || path.join(browserCache, chromiumDir, 'chrome-linux64/chrome');
assert(fs.existsSync(chromiumPath), 'Set E2E_CHROMIUM_PATH to an installed Chromium executable.');
fs.mkdirSync(scratch, { recursive: true });
fs.mkdirSync(path.join(__dirname, '.artifacts'), { recursive: true });

function fixture(command) {
  const result = spawnSync('php', [path.join(__dirname, 'fixture.php'), command], { cwd: root, env, encoding: 'utf8' });
  assert.equal(result.status, 0, result.stderr);
  return JSON.parse(result.stdout);
}

function command(session, args) {
  const result = spawnSync(cli, [`-s=${session}`, '--json', ...args], { cwd: root, env, encoding: 'utf8', timeout: 45000 });
  assert.equal(result.status, 0, result.stderr || result.stdout);
  const output = JSON.parse(result.stdout);
  assert(!output.error && !output.isError, JSON.stringify(output));
  return output;
}

function snapshot(session, savePath) {
  const source = path.join(scratch, `${session}.yml`);
  command(session, ['snapshot', '--filename', source]);
  assert(fs.existsSync(source), 'Playwright CLI did not save the requested snapshot.');
  if (savePath) fs.copyFileSync(source, savePath);
  return fs.readFileSync(source, 'utf8');
}

function ref(snapshotText, label) {
  const line = snapshotText.split('\n').find(line => line.includes(label));
  assert(line, `Missing snapshot control: ${label}`);
  return line.match(/\[ref=([a-z0-9]+)\]/)[1];
}

function readPage(session) {
  const output = command(session, ['eval', `() => {
    const card = document.querySelector('.notification-domain-card');
    return {
      path: location.pathname,
      selector: card?.querySelector('.notification-domain-name code')?.textContent,
      chips: Array.from(card?.querySelectorAll('.email-domain-chip') || []).map(el => el.textContent),
      checked: card?.querySelector('.notification-domain-checked')?.textContent || null,
      readiness: document.querySelector('[aria-label="Email readiness"]')?.textContent.replace(/\\s+/g, ' ').trim(),
      blocking: card?.textContent.includes('Verified-domain send blocking is enabled.'),
      refresh_action: card?.querySelector('form')?.getAttribute('action'),
      overflow: document.documentElement.scrollWidth > innerWidth,
      viewport: {width: innerWidth, height: innerHeight},
    };
  }`]);
  return JSON.parse(output.result);
}

function assertUnknown(state) {
  assert.equal(state.path, '/admin/email');
  assert.equal(state.selector, 'cf2024-1');
  assert.deepEqual(state.chips, ['SPF unknown', 'DKIM unknown']);
  assert.equal(state.checked, null);
  assert.match(state.readiness, /blocked until SPF and DKIM pass/);
  assert(!state.readiness.includes('— verified'));
  assert.equal(state.blocking, true);
  assert.equal(state.refresh_action, '/admin/email/domain/verify');
  assert.equal(state.overflow, false);
}

async function main() {
  // An occupied port is an error: never attach the evidence session to another app.
  await new Promise((resolve, reject) => {
    const probe = net.createServer();
    probe.once('error', reject);
    probe.listen(port, 'localhost', () => probe.close(resolve));
  });
  fixture('inspect'); // Validates safe database/mail configuration before boot.
  const serverLog = fs.openSync(path.join(__dirname, '.artifacts/php-server.log'), 'w');
  const server = spawn('php', ['-S', `localhost:${port}`, '-t', 'public', 'public/index.php'], {
    cwd: root, env, stdio: ['ignore', serverLog, serverLog],
  });
  const results = [];
  try {
    let ready = false;
    for (let attempt = 0; attempt < 50; attempt++) {
      try { ready = (await fetch(baseURL + '/healthz')).ok; } catch {}
      if (ready) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert(ready, 'Private PHP server failed to start.');
    for (const [name, contextOptions] of [
      ['desktop', { viewport: { width: 1280, height: 800 } }],
      ['mobile', { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true }],
    ]) {
      const session = `dkim-${name}-evidence-20261018`;
      const directory = path.join(__dirname, name);
      fs.mkdirSync(directory, { recursive: true });
      const configPath = path.join(scratch, `${name}.config.json`);
      fs.writeFileSync(configPath, JSON.stringify({ browser: {
        browserName: 'chromium', isolated: true,
        launchOptions: { executablePath: chromiumPath, headless: true },
        contextOptions: { ...contextOptions, reducedMotion: 'reduce' },
      } }, null, 2));
      const before = fixture('seed-failed');
      try {
        command(session, ['open', baseURL + '/login', '--config', configPath]);
        let tree = snapshot(session);
        command(session, ['fill', ref(tree, 'textbox "Email"'), 'dkim-admin@retro.test']);
        command(session, ['fill', ref(tree, 'textbox "Password"'), 'password123']);
        tree = snapshot(session);
        command(session, ['click', ref(tree, 'button "Log in"')]);
        command(session, ['goto', baseURL + '/admin/email']);
        tree = snapshot(session, path.join(directory, 'stale-failed-unknown.yml'));
        const unknown = readPage(session);
        assertUnknown(unknown);
        assert.deepEqual(fixture('inspect'), before, 'GET must not refresh or mutate the cached status.');
        command(session, ['screenshot', '--filename', path.join(directory, 'stale-failed-unknown.png'), '--full-page']);
        command(session, ['click', ref(tree, 'button "Refresh SPF/DKIM status"')]);
        snapshot(session, path.join(directory, 'refreshed-verified.yml'));
        const refreshed = readPage(session);
        assert.equal(refreshed.selector, 'cf2024-1');
        assert.deepEqual(refreshed.chips, ['SPF pass', 'DKIM pass']);
        assert.match(refreshed.readiness, /verified \(SPF and DKIM pass\)/);
        assert(refreshed.checked);
        assert.equal(refreshed.overflow, false);
        const persisted = fixture('inspect');
        assert.equal(persisted.cached_status.dkim_selector, 'cf2024-1');
        assert.equal(persisted.cached_status.details.dkim_host, 'cf2024-1._domainkey.example.test');
        assert.equal(persisted.refresh_audit_count, before.refresh_audit_count + 1);
        assert.equal(persisted.email_delivery_count, 0);
        command(session, ['screenshot', '--filename', path.join(directory, 'refreshed-verified.png'), '--full-page']);
        command(session, ['reload']);
        snapshot(session);
        assert.deepEqual(readPage(session), refreshed, 'Fresh GET must retain the refreshed selector result.');
        const wrongPass = fixture('seed-passed');
        assert.equal(wrongPass.cached_status.dkim_status, 'pass');
        assert.equal(wrongPass.cached_status.dkim_selector, 'default');
        command(session, ['reload']);
        snapshot(session, path.join(directory, 'stale-passed-blocked.yml'));
        const stalePass = readPage(session);
        assertUnknown(stalePass);
        assert.deepEqual(fixture('inspect'), wrongPass, 'GET must leave the old passing cache untouched.');
        command(session, ['screenshot', '--filename', path.join(directory, 'stale-passed-blocked.png'), '--full-page']);
        results.push({ project: name, initial_cache: before, before_refresh: unknown,
          after_refresh: refreshed, persisted_after_refresh: persisted, wrong_selector_pass: wrongPass,
          stale_pass_render: stalePass, outcome: 'passed' });
        console.log(`${name}: stale failure ignored, refresh verified cf2024-1, stale pass blocked; no deliveries`);
      } finally {
        command(session, ['close']);
      }
    }
    fs.writeFileSync(path.join(__dirname, 'browser-results.json'), JSON.stringify({
      captured_at: new Date().toISOString(), database, base_url: baseURL,
      browser_executable: chromiumPath, browser_cli: 'Playwright CLI', results,
    }, null, 2) + '\n');
  } finally {
    server.kill('SIGTERM');
    await new Promise(resolve => server.once('exit', resolve));
    fs.closeSync(serverLog);
  }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
