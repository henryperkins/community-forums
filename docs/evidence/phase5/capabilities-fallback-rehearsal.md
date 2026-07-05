# Phase 5 — Capabilities Fallback Rehearsal (Increment 6, P5-08/09)

**Date:** 2026-07-05
**Commit (branch HEAD at rehearsal time):** `628ef4d14133f3db4c38a79f0ea5cb8607fed80e`
**Environment:** local dev instance, dev database (`retroboards`), **not production, not the
test database**. Migrated to `0073_phase5_package_integrations` (current) before the drill.

> Port note: the brief's drill script names ports `8000`/`8001`. Port `8000` was already bound
> by an unrelated, pre-existing `php -S` process rooted at `/home/henry/community-forums` (the
> main checkout, PPID 1, started 2026-07-03 — not started by this session and out of scope to
> touch). This rehearsal used **`8090`/`8091`** instead; the ports are not load-bearing, only the
> shadow-vs-enforce comparison is.

## Setup

```
$ php bin/console migrate
migrated: 0073_phase5_package_integrations
Applied 1 migration(s).
```

Fixture helper (`rehearsal-fixtures.php`, throwaway, not committed) merged `capabilities: true`
into the existing `features` settings override (read-merge-write — the dev DB already carried
other operator overrides and this must not clobber them) and created two fresh users via the
real `UserRepository`/`PasswordHasher`:

```
$ php bin/console migrate   # already current, see above
$ php rehearsal-fixtures.php setup
features merged: {"wysiwyg_composer":true,"rich_composer":true,"slash_giphy":true,"drafts":false,
"server_drafts":false,"service_secrets":true,"api_tokens":true,"webhooks":true,
"first_party_hooks":true,"package_registry":true,"capabilities":true}
created rehearsal_admin id=295
created rehearsal_mod id=296       # role=moderator, no board_moderators row (vestigial global mod)
```

Servers:

```
$ php -S 127.0.0.1:8090 -t public public/index.php &            # no CAPABILITIES_MODE -> shadow (default)
$ CAPABILITIES_MODE=enforce php -S 127.0.0.1:8091 -t public public/index.php &
$ curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/healthz   -> 200
$ curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8091/healthz   -> 200
```

Confirmed via `/proc/<pid>/environ`: the `:8091` process carries `CAPABILITIES_MODE=enforce`;
the `:8090` process carries no such variable (defaults to `shadow`).

## Exercise 1 — suspended-staff pending-view tightening (ADR 0016 decision 1)

Logged in as `rehearsal_mod` (still active) on `:8090`, confirmed the CSRF-secret rotation
that happens on login (a token scraped *before* `POST /login` is stale immediately after — a
fresh token must be scraped from any page fetched *after* login), then sanity-checked the
approval queue while still active on both ports:

```
$ curl (login as rehearsal_mod on :8090)          -> 303 -> /
$ curl GET /mod/approvals on :8090 (active)        -> 200
$ curl GET /mod/approvals on :8091 (active)        -> 200   # same DB-backed session, both ports
```

Suspended the account mid-session (mirrors an admin suspending a logged-in moderator — the
session is not proactively revoked by suspension, only by a credential change):

```
$ php rehearsal-fixtures.php suspend
rehearsal_mod status now: suspended
```

Re-probed the **same** session cookie against both ports:

```
$ curl -c jar-mod.txt -b jar-mod.txt -o body-8090-suspended.html -w 'http_code=%{http_code}\n' \
    http://127.0.0.1:8090/mod/approvals
http_code=200                                      # SHADOW/legacy: quirk preserved
$ grep -o '<title>[^<]*</title>' body-8090-suspended.html
<title>Approval queue</title>

$ curl -c jar-mod.txt -b jar-mod.txt -o body-8091-suspended.html -w 'http_code=%{http_code}\n' \
    http://127.0.0.1:8091/mod/approvals
http_code=403                                      # ENFORCE: ADR 0016 decision 1 tightening
$ grep -o '<title>[^<]*</title>' body-8091-suspended.html
<title>Error 403</title>
```

**Result: exactly as designed.** The identical session, the identical suspended account, two
different answers depending only on `CAPABILITIES_MODE` — legacy/shadow preserves the documented
quirk (a suspended global moderator can still *view* pending content), enforce applies "state
beats role" uniformly and denies it. This is the live behavior delta ADR 0016 decision 1 records.

## Exercise 2 — ordinary admin moderation is unaffected by the cutover

Logged in as `rehearsal_admin` on `:8090`. Confirmed thread `1` ("Testing Topic", board 45)
started unlocked (`is_locked=0`):

```
$ curl POST /mod/t/1/lock on :8090 (shadow), fresh post-login token -> 303 -> /t/1-testing-topic
$ mariadb ... SELECT id,is_locked FROM threads WHERE id=1;   -> is_locked=1
$ curl POST /mod/t/1/lock on :8091 (enforce), same session/token -> 303 -> /t/1-testing-topic
$ mariadb ... SELECT id,is_locked FROM threads WHERE id=1;   -> is_locked=0   # toggled back
```

**Result:** the same admin lock/unlock action succeeds (302/303) under both shadow and enforce —
proving the cutover changes nothing for an ordinary board-moderating admin — and, as a
side-effect of the toggle, the thread was left exactly as found (`is_locked=0`).

## Exercise 3 — Lever 1 (`CAPABILITIES_MODE=shadow`)

Killed the `:8091` (enforce) process and restarted the **same port** with no
`CAPABILITIES_MODE` set (defaults `shadow`) — the operational drill for "an operator needs to
revert a bad enforce-mode decision right now":

```
$ kill <enforce-pid>
$ php -S 127.0.0.1:8091 -t public public/index.php &     # no env var this time
$ /proc/<newpid>/environ | grep CAPABILITIES               -> (none — will default to shadow)
```

Re-probed the **same still-suspended** `rehearsal_mod` session (DB-backed sessions survive a
server restart) against the now-shadow `:8091`:

```
$ curl -c jar-mod.txt -b jar-mod.txt http://127.0.0.1:8091/    -> 200   (session still valid)
$ curl -c jar-mod.txt -b jar-mod.txt -o body-8091-lever1.html -w 'http_code=%{http_code}\n' \
    http://127.0.0.1:8091/mod/approvals
http_code=200
$ grep -o '<title>[^<]*</title>' body-8091-lever1.html
<title>Approval queue</title>
```

**Lever 1 verified:** one environment-variable flip (no code change, no restart of the flag
setting) reverted the exact decision that enforce mode had changed — the suspended moderator
regained the pending-view the moment the process came back up without `CAPABILITIES_MODE=enforce`.

## Exercise 4 — Lever 2 (`features.capabilities=false`)

```
$ php rehearsal-fixtures.php lever2-off
features merged (capabilities=false): {..., "capabilities":false}
```

Probed the admin-only capabilities routes as the still-logged-in `rehearsal_admin`, on both
ports:

```
$ curl (admin session) GET /admin/roles on :8090            -> 404  <title>Error 404</title>
$ curl (admin session) GET /admin/roles on :8091            -> 404  <title>Error 404</title>
$ curl (admin session) POST /admin/role-assignments/1/revoke on :8090 -> 404
```

**Lever 2 verified:** with the flag off, every capabilities-gated route 404s on both ports
regardless of `CAPABILITIES_MODE` — matching the design's "gate() 404-dark" contract.

## Bonus — `changeRole` is flag-independent (ADR 0016 decision 5)

While `features.capabilities=false` was still in effect from Exercise 4, probed
`POST /admin/users/{id}/role` (targeting the harmless `rehearsal_mod` fixture, about to be
deleted anyway) with only a CSRF token and no `current_password`/`newRole`:

```
$ curl (admin session) POST /admin/users/296/role on :8090   -> 422   <title>User · rehearsal_mod</title>
```

**422, not 404** — confirms the route is reachable (a validation error, not a dark route) even
with the `capabilities` flag fully off, exactly as ADR 0016 decision 5 records ("flag-independent
— it manages `users.role`, which exists regardless of Phase 5"). This is the evidence behind the
deploy-dark inventory's new "+1 flag-independent admin route" line.

## Cleanup

```
$ kill <8090-pid> <8091-pid>                 # both rehearsal servers stopped
$ php rehearsal-fixtures.php teardown
features restored to original
removed rehearsal_admin (id=295) + sessions
removed rehearsal_mod (id=296) + sessions
```

Post-cleanup verification:

```
$ mariadb ... SELECT value FROM settings WHERE `key`='features';
{"wysiwyg_composer":true,"rich_composer":true,"slash_giphy":true,"drafts":false,
"server_drafts":false,"service_secrets":true,"api_tokens":true,"webhooks":true,
"first_party_hooks":true,"package_registry":true}                 # byte-identical to pre-rehearsal
$ mariadb ... SELECT id,username FROM users WHERE username IN ('rehearsal_admin','rehearsal_mod');
(empty)
$ mariadb ... SELECT id,is_locked FROM threads WHERE id=1;
id=1, is_locked=0                                                  # restored to starting state
$ ss -ltnp | grep -E ':(8090|8091)'
(nothing — both ports free)
$ git status --short   (in the worktree)
?? docs/adr/0016-inc6-enforcement-cutover-decisions.md
?? docs/runbooks/capabilities.md
```

The dev database, its `settings`/`users`/`threads` rows, and the worktree's tracked files are
all left exactly as found; the drill produced no residue beyond this evidence file and the two
docs already staged for this task.

## Summary

| Drill | Expected | Observed | Result |
|---|---|---|---|
| Suspended-mod pending view, shadow (`:8090`) | non-403 (legacy quirk) | `200` | PASS |
| Suspended-mod pending view, enforce (`:8091`) | `403` (ADR 0016 tightening) | `403` | PASS |
| Admin lock, shadow (`:8090`) | `302`/`303` | `303` | PASS |
| Admin lock (toggle back), enforce (`:8091`) | `302`/`303` | `303` | PASS |
| Lever 1 — restart `:8091` without `CAPABILITIES_MODE` | suspended-mod probe reverts to non-403 | `200` | PASS |
| Lever 2 — `features.capabilities=false` | `/admin/roles` and `/admin/role-assignments/*` → `404` on both ports | `404`/`404`/`404` | PASS |
| Bonus — `changeRole` under lever 2 | reachable (not `404`) | `422` | PASS |

All six rehearsed behaviors matched the runbook's documented contract exactly. No deviation, no
fabrication — every response code above was observed via `curl -w '%{http_code}'` against the
live local servers described above.
