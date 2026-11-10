# Recovered asset-evidence checks

Verified 2026-11-10 against `main` at `ffa85a3a`. The two evidence scripts were
recovered byte-for-byte from the uncommitted work in `rb-wt-pr1` in commit
`55e7f4dd`. Final review additionally made the live check's `immutable` and
`no-store` directive comparisons case-insensitive; the synthetic fixture now
uses mixed-case directives to verify that behavior.

## Executed checks

- `TMPDIR=/tmp/opencode python3 -I tests/diagnostics/test_asset_live_check.py -v`:
  **9 tests passed**. The black-box HTTP fixture covers successful current and
  retained files, case-insensitive and repeated headers, missing/mismatched assets,
  cache policy, wrong page/preload references, the unlisted control, unhealthy or
  malformed health responses, truncated responses, connection failures and CLI
   usage errors. It makes no production requests. The final run after the
   directive-casing correction is retained in `diagnostics-final.log`.
- `bash -n docs/evidence/asset-retention-check-2026-11-08/repro.sh`: passed;
  missing arguments return 2.
- The recovered `repro.sh` completed at its default historical revision
  `eeceed780aa9ec92e2a85e9554020a6c6d85d503`. The unrecorded release lost one
  file after the app-CSS edit and all four exclusive files after the four-source
  edit; recording the release retained every file. See `repro.log` and
  `repro-results/`.
- A synthetic builder failure returned **23** from the whole reproducer,
  recorded `exit=23` and stopped before scenario 1a. Selecting the already-pinned
  revision `b0038b5b` returned **1** at the first expectation and stopped before
  scenario 1b. See `negative-checks.json` and the corresponding logs.
- The recovered live checker verified production at **06:15:37 UTC**:
  release `276fd01648589411`, **11/11 current and 7/7 retained files** matched;
  the page and preload references belonged to the release, the unlisted control
  was 404/no-store, and health was 200/ok. See `live-before.json`.

The historical reproduction runs in throwaway archive copies and reads its
comparison manifest from the selected revision. These results qualify the
recovered diagnostics; deployment of application changes is recorded separately.

## Repeat

```sh
python3 -I tests/diagnostics/test_asset_live_check.py -v
bash docs/evidence/asset-retention-check-2026-11-08/repro.sh . <output-directory>
```

For the negative reproduction, select `b0038b5b` as the optional third argument
(expected exit 1), or put a `node` wrapper on PATH that returns 23 only for
`bin/build-assets.mjs` and delegates all other calls (expected exit 23).
