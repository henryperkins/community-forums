// Each suite/project starts with a clean disposable database and its own captures.
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const database = process.env.DB_DATABASE ?? 'retroboards_unified_e2e';
if (!/^retroboards_unified_e2e(?:_[a-z0-9]+)*$/.test(database)) {
  throw new Error('Select a disposable retroboards_unified_e2e database.');
}
const baseURL = new URL(process.env.E2E_BASE_URL ?? `http://localhost:${process.env.E2E_PORT ?? '8034'}`);
if (baseURL.protocol !== 'http:' || !['localhost', '127.0.0.1', '[::1]'].includes(baseURL.hostname)
    || baseURL.username || baseURL.password || baseURL.pathname !== '/' || baseURL.search || baseURL.hash) {
  throw new Error('Browser evidence requires a local HTTP origin.');
}
const suites = [
  'notifications-unified', 'account-settings-repairs', 'account-console', 'totp',
  'profile-surface', 'chamfer-removal', 'unified-chrome', 'board-index-remediation',
];
for (const suite of suites) {
  if (!fs.existsSync(path.join(__dirname, `${suite}.spec.ts`))) {
    throw new Error(`Missing evidence spec: ${suite}.spec.ts`);
  }
}

const evidenceRoot = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/unified-notifications-and-settings');
const scratch = fs.mkdtempSync(path.join(root, 'storage/notifications-settings-e2e-'));
const results = [];
let completed = false;
fs.mkdirSync(evidenceRoot, { recursive: true });

function run(command, args, env, suite, project) {
  const started = Date.now();
  const result = spawnSync(command, args, { cwd: __dirname, env, stdio: 'inherit' });
  results.push({ suite, project, command, args, exit_code: result.status, signal: result.signal, elapsed_ms: Date.now() - started });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`${command} exited ${result.status ?? result.signal ?? 'without a status'}`);
}

try {
  for (const suite of suites) {
    for (const project of ['desktop', 'mobile']) {
      const runScratch = path.join(scratch, suite, project);
      const capturePath = path.join(evidenceRoot, suite, project);
      fs.mkdirSync(capturePath, { recursive: true });
      const env = {
        ...process.env,
        DB_DATABASE: database, APP_ENV: 'test',
        MAIL_DRIVER: 'array', MAIL_FROM: 'notification-evidence@example.test',
        APP_URL: baseURL.origin, E2E_BASE_URL: baseURL.origin, RB_BASE_URL: baseURL.origin,
        E2E_PORT: baseURL.port || '80', WEBAUTHN_RP_ID: baseURL.hostname,
        // CI disables Playwright's reuseExistingServer: an occupied port must fail.
        CI: 'true', E2E_SKIP_WEBSERVER: '0',
        RATELIMIT_PATH: path.join(runScratch, 'ratelimit'),
        PACKAGES_STORAGE_PATH: path.join(runScratch, 'packages'),
        UPLOADS_PATH: path.join(runScratch, 'media'),
        RB_EVIDENCE_DIR: capturePath,
        PLAYWRIGHT_JSON_OUTPUT_NAME: path.join(capturePath, 'playwright-results.json'),
      };
      run('bash', ['prepare.sh'], env, suite, project);
      run('npx', ['playwright', 'test', `${suite}.spec.ts`, `--project=${project}`, '--reporter=list,json'], env, suite, project);
    }
  }
  completed = true;
} finally {
  try {
    fs.rmSync(scratch, { recursive: true, force: true });
  } finally {
    fs.writeFileSync(path.join(evidenceRoot, 'browser-results.json'), `${JSON.stringify({
      database, origin: baseURL.origin, completed, scratch_removed: !fs.existsSync(scratch), results,
    }, null, 2)}\n`);
  }
}
