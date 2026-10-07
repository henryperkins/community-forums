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
