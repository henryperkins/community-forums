# Registry layout: retention dependency and credential response

This follow-up updates PR #100 without changing its layout behavior or adding
PR #99's registry form-error correction. Original layout regression tests,
failing captures and proof remain in
[registry-blocklist-layout-2026-11-08](../registry-blocklist-layout-2026-11-08/README.md).
All browser evidence here is **Chromium only**, not physical-device, Firefox,
Safari or manual assistive-technology validation.

## Dependency and merge order

1. [PR #96](https://github.com/henryperkins/community-forums/pull/96),
   `b0038b5b9ae5f48e4213312ed29f76c46b690ebc`, records the verified live release
   `0ab71f5ca65e7fa0` and advances the deployed retention window.
2. [PR #100](https://github.com/henryperkins/community-forums/pull/100) stacks
   on that commit. Merge #96 to main **before** the asset-changing layout
   release. Then retarget #100 to main and review its final diff before any
   separately authorized merge. Do not merge #100 into the retention branch
   first, which would mix the two changes before publication.

Both PRs remain unmerged. No deployment, production credential change or live
email/registry/provider workload is part of this task. PRs #98 and #99, and
audit options 4–5, remain separate.

The retention worktree had newer uncommitted checker/reproduction edits. Those
files were preserved; [preserved-work.json](preserved-work.json) records their
hashes. The refreshed read-only checker was copied and inspected before use;
its exact source is [live-check-current.py](live-check-current.py).

## Refreshed live evidence and normal generation

[live-refresh.json](live-refresh.json) records public **GET-only** verification
against `https://forum.candidary.online`: 11/11 files of live release
`0ab71f5ca65e7fa0` and 26/26 other retained immutable files matched their
manifest SHA-256. HTML and preload references belong to that release, the
unlisted control answered 404/no-store, and `/healthz/` was healthy. No runtime
configuration or production secret value was read through that public check.

The [original delivered-head comparison](original-layout-retention-gap.json)
confirms that `a308e1b1` omitted
`/assets/dist/forum-theme-style-CNIV-nj8.css` from both its allowlist and Git
tree. That file is required by the freshly verified live release.

The stack was regenerated with `composer build:forum` and `npm run build`.
The generator received a composed build seed: the layout's current candidate
inputs plus PR #96's deployed pins and exact retained bytes. Retained immutable
metadata was checked for consistency, and the missing live stylesheet was
recovered from the dependency's Git blob and checked against its SHA-256.
The generator then wrote the complete directories and manifest. No manifest
conflict was resolved by choosing one side, and no unverified layout release
was recorded as deployed.

The candidate remains `c0ff610c8c690556`, with the same current URLs and bytes
as the original tested layout. Its delivery set is:

| Role | Release | Fingerprinted files |
| --- | --- | ---: |
| Current candidate | `c0ff610c8c690556` | 11 |
| Verified live deployed pin | `0ab71f5ca65e7fa0` | 11 |
| Previous deployed pin | `5dc1b40f3b23d46d` | 11 |
| Oldest retained deployed pin | `c66e3b55dba6da68` | 25 |

Files overlap across releases. [asset-closure.json](asset-closure.json) records
all **42 allowlist entries / 34 unique immutable files**, including every file
in all four releases. For each entry the local check compared `public` bytes,
`.build/static` bytes, and the real Worker asset route's returned bytes to the
manifest SHA-256. Responses were 200/STATIC, with immutable caching for
fingerprinted files. All eleven live-release files match the fresh public
receipts, and all eleven candidate files match the original layout candidate.
The local asset handler was injected with the actual staged files and an
origin callback that throws; it contacted no live origin or provider.

**Expected eviction in PR #96:** `3630440af0d45598` retires these four exclusive
files from the generated manifest and both output trees:

- `app-e5669eff1f842115.js`
- `app-style-D7aglA01.css`
- `composer-c1ff478070e3a1d8.js`
- `tour-f068bdb2d74e6d21.js`

The local Worker returns 404/no-store for each. These are expected future
production responses after the separately authorized release; this task did
not deploy them. If `c0ff610c8c690556` is eventually deployed, verified and
recorded, the next oldest eviction would be `c66e3b55dba6da68` and its eighteen
exclusive files listed in the closure report. That future record was not run.

Reproduce the local closure check after normal generation:

```sh
node docs/evidence/registry-blocklist-retention-2026-11-08/verify-assets.mjs \
  docs/evidence/registry-blocklist-retention-2026-11-08/live-refresh.json \
  docs/evidence/registry-blocklist-retention-2026-11-08/prior-main-assets.json \
  docs/evidence/registry-blocklist-retention-2026-11-08/prior-layout-assets.json
```

## GitGuardian finding and credential scope

GitGuardian check `128686008024` on `a308e1b1` reported incident **34291610**,
a Username Password detector at the committed evidence runner's DB settings.
[The check record](gitguardian-check.json) and
[credential-scope.json](credential-scope.json) preserve metadata and match
locations without the credential value or a password fingerprint.

The value was **not generated solely for these disposable databases**. It is
the pre-existing development default in `.env.example`, `config/config.php`
and `.cursor/install.sh`, and appears in older development documentation. An
in-memory comparison and local authentication established that the shared
`retro@%` account uses it and can access multiple existing development/test
databases. The container's port is bound to host loopback only. This is a
reused local development credential, so the finding was not dismissed as a
one-off fixture or falsely declared revoked.

The same literal occurs in evidence runners on PR #98 (two files) and PR #99
(one file); those branches were inspected but not modified or incorporated
here. The bounded search covered all tracked non-binary files on main and
the three affected branch heads. Compressed patch artifacts in this layout
change were also checked. Existing `.env` files were absent in the three
worktrees inspected. This is not an exhaustive search of all machines or
historical Git objects.

A read-only Cloud Run service-metadata request was attempted, but the current
identity lacks `run.services.get`. Consequently production credential reuse
is **unverified**; no production secret value was accessed or changed. The
[access-limit record](production-credential-metadata.json) states that limit.

**Remediation in this PR:** the embedded DB credential was replaced with
explicit runtime inputs. The runner reads a private password file, validates
the disposable browser and PHP database names before executing a child, forces
loopback/test configuration, and filters inherited provider/host settings.
The active PR history is rewritten so its commit range does not introduce the
old runner literal. Original layout behavior/captures remain preserved. No
secret-scanning configuration, skip directive, broad exclusion or incident
false-positive suppression is added. The fresh GitGuardian result on the
delivered head is linked in the PR verification notes.

**Credential response still required outside this branch:** coordinate
rotation/revocation of the shared local development account and update its
consumers, including the other affected evidence runners, without disrupting
ongoing work. Treat the development default as public. Any non-disposable
environment using it also needs a coordinated credential rotation and a check
that the old credential no longer authenticates. If production reuse is found,
the owner must authorize its DB/Secret Manager/runtime-consumer response;
production changes are outside this task. Removing the latest literal or
rewriting this PR does not erase main, other PRs, cached GitHub commits or
clones. No global purge or shared-account revocation is claimed.

The fresh verification used a **new generated account**, restricted to the two
task databases. Its password lived only in a mode-0600 scratch file, was never
printed or committed, and is removed with the account after the checks.

## Runtime inputs and regression

Copy the sanitized [runner](../registry-blocklist-layout-2026-11-08/logs/run-env.py)
to a private scratch directory before running evidence. Provision a generated
local account with grants only on the chosen disposable databases, store its
password in a mode-0600 file outside Git, and set:

- `RB_BLOCKLIST_DB_USERNAME` — the restricted local account.
- `RB_BLOCKLIST_DB_PASSWORD_FILE` — the private password-file path.
- `RB_BLOCKLIST_BROWSER_DB` — `retroboards_e2e_blocklist_layout_retention_20261108`
  for the recorded run, or another name with the guarded browser prefix.
- `RB_BLOCKLIST_TEST_DB` — `retroboards_test_blocklist_layout_retention_20261108`
  for the recorded run, or another name with the guarded PHP prefix.

Do not source live environment files, provide secrets as command-line
arguments, or copy password values into scripts, logs or screenshots.
Run migrations through the runner, start its localhost PHP server on 8032,
and run the existing browser spec from `tests/browser`. Its fixture independently
requires `APP_ENV=test` and the disposable browser-name guard.

The retained runner regression executes actual child processes without a DB
or provider call. On the legacy runner it produced five assertion failures
across four tests: missing credential input, unreadable input, both unsafe DB
names, and ignored runtime credentials. The corrected runner passes all four
tests and preserves test-only host/provider isolation. The exact failure test
and original runner Git-blob identity are retained; the credential-bearing
legacy runner is not copied into this evidence.

## Completed verification and exact content

Tested base: `b0038b5b9ae5f48e4213312ed29f76c46b690ebc`.
[tested-files.json](tested-files.json) records source, test, runner, diagnostic
and generated-asset hashes, including the retained live stylesheet.
[tested.patch.gz](tested.patch.gz) preserves their exact binary Git diff against
that dependency. Uncompressed SHA-256:
`a65293a10ef9682d6fc92792e2580d9015c2ea5f0a5e2f98d6ff1c9f3b57cca2`.
The delivered Git blobs and diff are checked against those records before push;
committing identical content does not require a duplicate full run.

The execution prefix `runtime` below was
`python3 /tmp/rb-layout-retention-20261108-jdo10h3c/with-runtime.py`. That scratch
wrapper supplied the four runtime inputs listed above to the sanitized runner;
the password remained in its private file. Application commands ran from the
worktree and browser commands from `tests/browser`. Original output hashes
are retained; text-log trailing whitespace is normalized for Git checks.

| Check | Command | Result |
| --- | --- | --- |
| Runner regression | `python3 tests/diagnostics/test_blocklist_evidence_runner.py` | 4 passed; unchanged baseline had 5 expected assertion failures |
| Focused PHP | `runtime php vendor/bin/phpunit tests/Integration/Core/AppRegistryAdminTest.php tests/Integration/Security/PackageSecurityGateTest.php tests/Integration/Repository/RegistryRepositoriesTest.php tests/Unit/Core/ForumRuntimeAssetTest.php tests/Integration/Core/AppForumRuntimeTest.php --colors=never` | 34 tests / 205 assertions passed |
| Full PHP | `runtime composer test -- --colors=never --display-deprecations --display-skipped` | Exit 0; 3,576 tests / 33,934 assertions in 5:50.630 |
| Focused browser | `runtime node node_modules/@playwright/test/cli.js test registry-blocklist-layout.spec.ts --project=desktop --reporter=line` | 12 passed, 0 skipped, 12.3s; Chromium only |
| Forum generation/check | `composer build:forum`, `composer check:forum` | Both exit 0 |
| Asset generation/check | `npm run build`, `npm run check:assets` | Both exit 0 |
| Worker/assets | `npm run test:assets` | 35 passed, 0 skipped |
| Live and candidate closure | `node .../verify-assets.mjs` with the three retained inputs above | 42/42 allowlisted files match public/staged/Worker bytes; 11/11 live and 11/11 candidate files preserved |
| Evidence credential scan | Raw files plus decompressed gzip patches in both blocklist evidence directories and the diagnostic tests | No occurrence of the reused development credential |
| Diff | `git diff --check`, staged equivalent | Passed |

Broad PHP verification was repeated once because replacing the shared account
with a narrowly granted account can expose schema/permission assumptions
throughout the suite. The browser rerun is the existing focused twelve-case
layout matrix, with the new manifest and runtime credential path; no unrelated
browser suite was repeated. Chromium-only remains explicit.

The fresh full PHP run retains eight existing PHP 8.5 deprecation sites in
unchanged TLS reflection/constants (`DatabaseTlsTest` / `Database`),
`curl_close` (`CloudflareSmtpMailer`) and `imagedestroy` (`ThemeAssetScanner`).
The [full log](logs/full-php.log) lists all eight locations and triggering tests.
There is one guarded skip:
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
Recorded reason: **direct down/up rehearsal runs only on its dedicated throwaway
database**. Recorded command:
`DB_TEST_DATABASE=retroboards_thread_intelligence_clean vendor/bin/phpunit tests/Integration/Core/AppThreadIntelligenceMigrationTest.php --filter test_0077_down_and_up_rehearsal_on_fixture_free_schema`.
That unchanged specialized destructive rehearsal was not run. No assertion
failures or unresolved browser skips remain.

[results.json](results.json), [cleanup.json](cleanup.json) and the ownership
records retain completed results and resource disposition. The private server,
two databases, generated account, password file and runtime stores were removed;
the pre-existing shared account and other worktrees were preserved. No merge,
publication, deployment or production credential change is claimed.
