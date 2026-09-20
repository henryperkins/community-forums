// Rehearse the real CLI against a disposable schema with captured mail only.
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const database = process.env.DB_DATABASE ?? 'retroboards_unified_workers';
if (!/^retroboards_unified_workers(?:_[a-z0-9]+)*$/.test(database)) {
  throw new Error('Select a disposable retroboards_unified_workers database.');
}
const evidenceRoot = path.resolve(root, process.env.RB_EVIDENCE_DIR ?? 'docs/evidence/unified-notifications-and-settings');
const env = {
  ...process.env, APP_ENV: 'test', DB_DATABASE: database,
  MAIL_DRIVER: 'array', MAIL_FROM: 'notification-evidence@example.test',
};
const report = { database, transport: 'array', started_at: new Date().toISOString(), commands: [], verified: false };
fs.mkdirSync(evidenceRoot, { recursive: true });
function run(args, structured = false) {
  const started = Date.now();
  const result = spawnSync('php', args, { cwd: root, env, encoding: 'utf8' });
  let stdout = result.stdout;
  if (structured) {
    try { stdout = JSON.parse(stdout); } catch { /* Preserve diagnostics if the fixture could not emit JSON. */ }
  }
  const record = {
    command: ['php', ...args], exit_code: result.status, signal: result.signal,
    elapsed_ms: Date.now() - started, stderr: result.stderr,
    stdout,
  };
  report.commands.push(record);
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`${args.join(' ')} failed with exit ${result.status}: ${result.stderr}`);
  return record.stdout;
}
try {
  run(['tests/notification-workers/fixture.php', 'seed'], true);
  run(['bin/console', 'worker:purge-accounts']);
  run(['tests/notification-workers/fixture.php', 'inspect'], true);
  run(['bin/console', 'worker:digest']);
  run(['bin/console', 'worker:email', '100']);
  // Repeat both entry points to prove durable scheduling does not create duplicates.
  run(['bin/console', 'worker:digest']);
  run(['bin/console', 'worker:email', '100']);
  const verification = run(['tests/notification-workers/fixture.php', 'verify'], true);
  report.verified = Object.values(verification.checks).every((value) => value === true);
  if (!report.verified) throw new Error('Worker outcome verification failed.');
  process.stdout.write(`Worker CLI rehearsal passed (${Object.keys(verification.checks).length} outcomes).\n`);
} finally {
  report.finished_at = new Date().toISOString();
  fs.writeFileSync(path.join(evidenceRoot, 'worker-cli.json'), `${JSON.stringify(report, null, 2)}\n`);
}
