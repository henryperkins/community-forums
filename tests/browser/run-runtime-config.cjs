const { spawnSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '../..');
const database = process.env.RB_RUNTIME_CONFIG_DATABASE ?? 'retroboards_runtime_config_browser';
const port = process.env.RB_RUNTIME_CONFIG_PORT ?? '8037';
if (!/^retroboards_[a-z0-9_]*browser[a-z0-9_]*$/.test(database)) {
  throw new Error('RB_RUNTIME_CONFIG_DATABASE must name a throwaway retroboards_*browser* database.');
}
if (!/^\d+$/.test(port) || Number(port) < 1 || Number(port) > 65535) {
  throw new Error('RB_RUNTIME_CONFIG_PORT must be a TCP port from 1 to 65535.');
}
const env = {
  ...process.env,
  CI: '1',
  DB_DATABASE: database,
  APP_KEY: '0'.repeat(64),
  OPENAI_API_KEY: 'browser-thread-intelligence-dummy-credential',
  E2E_PORT: port,
  E2E_BASE_URL: `http://localhost:${port}`,
  E2E_SKIP_WEBSERVER: '0',
  RB_RUNTIME_CONFIG_EVIDENCE: '1',
  ANTIABUSE_MODE: 'hold',
};
function run(command, args, cwd, childEnv) {
  const result = spawnSync(command, args, { cwd, env: childEnv, stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}

// Seed in observe mode so the shared corpus is not held by content scoring;
// only the application under test inherits hold without a stored override.
run('bash', ['tests/browser/prepare.sh'], root, { ...env, ANTIABUSE_MODE: 'observe' });
run(process.execPath, [require.resolve('@playwright/test/cli'), 'test', 'runtime-configuration.spec.ts'], __dirname, env);
