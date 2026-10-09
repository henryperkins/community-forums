import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';
import { unstable_readConfig } from 'wrangler';

test('production deploy leaves the independently provisioned SaaS zone route untouched', () => {
  const config = unstable_readConfig({ config: fileURLToPath(new URL('../../wrangler.jsonc', import.meta.url)) });

  // Wrangler's bulk route PUT rejects the out-of-zone SaaS hostname with 10022
  // and replaces existing zone routes. No zone routes means it skips that PUT.
  assert.deepEqual(config.routes.filter(route => !route.custom_domain), []);
  assert.deepEqual(config.routes, [{ pattern: 'forum.candidary.online', custom_domain: true }]);
});

test('production retires the container without losing its migration history or active edge bindings', () => {
  const config = unstable_readConfig({ config: fileURLToPath(new URL('../../wrangler.jsonc', import.meta.url)) });

  // Durable Object migrations are append-only: the deletion must follow the
  // original SQLite class creation so both existing and fresh deploys converge.
  assert.deepEqual(config.migrations, [
    { tag: 'v1', new_sqlite_classes: ['ForumContainer'] },
    { tag: 'v2', deleted_classes: ['ForumContainer'] },
  ]);
  assert.equal(config.containers, undefined);
  assert.deepEqual(config.durable_objects.bindings, []);
  // App/database/mail/storage settings belong only to Cloud Run. Exact keys
  // catch a stale container secret/variable being reintroduced on the edge.
  assert.deepEqual(config.vars, {
    ORIGIN_URL: 'https://retroboards-616731728350.us-east4.run.app',
    APP_URL: 'https://forum.candidary.online',
  });
  assert.equal(config.assets.binding, 'ASSETS');
  assert.equal(config.assets.run_worker_first, true);
  assert.equal(config.assets.directory, './.build/static');
  // An omitted crons key leaves old deployed triggers running.
  assert.deepEqual(config.triggers, { crons: [] });
});
