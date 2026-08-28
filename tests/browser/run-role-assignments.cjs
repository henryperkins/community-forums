'use strict';

const { spawnSync } = require('node:child_process');

const result = spawnSync(
  process.execPath,
  [require.resolve('@playwright/test/cli'), 'test', 'role-assignments.spec.ts'],
  {
    stdio: 'inherit',
    env: { ...process.env, CAPABILITIES_MODE: 'enforce' },
  },
);

if (result.error) {
  console.error(result.error.message);
  process.exit(1);
}

process.exit(result.status ?? 1);
