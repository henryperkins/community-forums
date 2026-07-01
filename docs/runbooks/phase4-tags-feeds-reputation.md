# Runbook - Tags, Expanded Feeds, and Reputation Ledger

Release/operations runbook for three Phase 4 Gate A features that graduated to
default-on availability on 2026-07-01:

- `tags` - public tag directory, tag detail pages, thread tag editing, and the
  admin tag catalogue.
- `expanded_feeds` - board/tag follows, expanded Following composition, and the
  Latest feed tab.
- `reputation_ledger` - idempotent `reputation_events`, week/month/all-time
  leaderboard windows, and board-scoped leaderboard filtering.

All three remain reversible through the `features` setting. The Imladris design
reference for these activated surfaces lives in
`docs/design-system/imladris/ACTIVATED_FEATURES.md`.

> Golden rule: for a logic defect, disable the smallest affected flag first,
> confirm the rest of the forum still serves, then investigate. These rollbacks
> are non-destructive: tag rows, follow rows, and reputation events are retained.

## What the flags gate

`tags` gates:

- `GET /tags`
- `GET /tags/{slug}`
- `POST /tags/{slug}/follow` (also requires `expanded_feeds`)
- `POST /t/{id}/tags`
- `GET|POST /admin/tags`
- `POST /admin/tags/{id}`
- `POST /admin/tags/{id}/merge`

When disabled, the routes return `404`, thread tag controls disappear, and the
admin catalogue is unreachable. Existing `tags`, `thread_tags`, `tag_aliases`,
and tag-follow rows remain stored.

`expanded_feeds` gates:

- board follow writes (`POST /b/{id}/follow`)
- tag follow writes (`POST /tags/{slug}/follow`, with `tags` also on)
- Following feed expansion to people, boards, and tags
- the Latest feed view (`GET /feed?view=latest`)

When disabled, the base `/feed` page remains available under the broader
`community` flag, but it falls back to people-only Following semantics and hides
the Latest tab.

`reputation_ledger` gates:

- ledger-backed leaderboard windows (`week`, `month`)
- board-scoped leaderboard filtering (`?board_id=...`)
- the leaderboard window tabs

When disabled, `/leaderboard` remains available under `community`, but it uses
the legacy all-time `users.reputation` ranking and ignores `window`/`board_id`.
The ledger rows remain available for repair/re-enable.

## Roll back / re-enable

The flags live in the `features` setting (JSON `flag => bool`). Always merge
with the existing object; do not clobber unrelated flags.

Disable `tags`:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["tags"]=false; $r->set("features",$f);'
```

Disable `expanded_feeds`:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["expanded_feeds"]=false; $r->set("features",$f);'
```

Disable `reputation_ledger`:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["reputation_ledger"]=false; $r->set("features",$f);'
```

Re-enable by setting the affected key back to `true` or by removing the key from
the `features` JSON object; the defaults are now `true`.

## Verification

Focused regression commands:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php
./vendor/bin/phpunit tests/Integration/Core/AppPhase4GateATest.php
./vendor/bin/phpunit tests/Integration/Core/AppFollowFeedTest.php
./vendor/bin/phpunit tests/Integration/Core/AppLeaderboardTest.php
```

Expected behavior checks:

- `tags` on: `/tags` and `/admin/tags` return `200`; `tags` off: those routes
  and tag writes return `404`.
- `expanded_feeds` on: `/feed?view=latest` renders Latest and board/tag follow
  writes redirect; off: Latest is hidden and board/tag follow writes return
  `404`.
- `reputation_ledger` on: `/leaderboard?window=week` renders Week/Month/All time
  tabs; off: the route still returns `200` but the window tabs are absent.

Full release checks:

```bash
composer test
cd tests/browser && npm run evidence
cd tests/browser && npm run a11y
```

Run `php bin/console repair:reputation-ledger` or `php bin/console repair` after
manual reputation-event manipulation or if `users.reputation` and
`reputation_events` drift.
