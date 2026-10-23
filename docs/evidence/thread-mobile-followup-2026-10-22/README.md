# Thread mobile follow-up — 2026-10-22

F-07 and F-08 are fixed locally. F-09 was reproduced, traced and repaired. The
verification ceiling remains local PHP/MariaDB, browser mechanisms and desktop
viewport emulation. Physical phones, software keyboards and assistive technology
have not been tested. No commit, push or deployment was performed.

## Changes and cause

- **F-07, P2 — active-label contrast:** Starred and reaction counts now use
  `--gold-ink` on `--gold-soft`. Legacy unlayered color overrides defer to the
  component layer with `revert-layer`. Final scoped axe checks found no contrast
  violations or incomplete checks: light 4.97:1; dark 5.15:1–6.30:1.
- **F-08, P2 — keyboard preview access:** the populated open preview has a named
  focusable scroll region and an inset focus ring. Tab enters it, PageDown scrolls
  it, and closing Preview removes its extra tab stop. Landscape checks keep Reply,
  Minimize and the account menu reachable.
- **F-09, P1 — older rich draft after Back:** the captured native failure shows
  the latest body in local storage and the pending-submit journal, but an older
  hidden textarea restored at `pageshow`, before the lazy editor initialized.
  The nonempty old value bypassed draft restoration; comparing it to the pending
  submission also rotated the key. Initialization now restores a matching
  unconfirmed user/context/history body only while it still matches local storage,
  and preserves text typed during loading. The conflict's **Save local as next
  revision** action also reads the current body/title instead of a stale closure.

The failure was an incorrect recovered editor state, not established destruction
of local draft storage. See `rich-recovery-failure.json` for the captured boundary.
The deterministic control holds the real lazy module and changes only the native
textarea to that observed stale value; a separate control types newer text before
releasing the module. These injected conditions are distinguished from native
offline/Back and real PHP/SQL persistence tests.

## Verification

| Check | Result | Evidence |
| --- | --- | --- |
| Focused composer, drafts, reactions and Imladris PHP tests on final application code | 161 passed, 1,280 assertions | `phpunit-focused.txt` |
| Complete thread browser suite, Edge desktop/mobile | 38 passed | `browser-edge-verified.txt` |
| Native rapid Back, controlled stale snapshot and typing during lazy loading, three repetitions each | 9 passed | `recovery-controlled-after.txt` |
| Final saved-appearance contrast test, Edge desktop/mobile | 4 passed | `browser-edge-appearance.txt` |
| Final affected WebKit checks: landscape, both themes, keyboard preview, latest conflict save | 5 passed | `browser-webkit-appearance.txt` |
| Locked-toolchain asset retention, publication and real Workers binding tests | 35 passed | `test-assets-locked.txt` |
| Generated assets and Imladris integrity | Passed | `check-assets-locked.txt`, `check-imladris-recovery.txt` |
| Full native Windows `composer test` | 3,337 tests, 29,465 assertions; 12 failures, 2 warnings, 2 skipped | `phpunit-full-1g.txt` |

The full PHP run used a temporary `memory_limit=1024M` override after the first
attempt exhausted the default 128 MiB allocation in `ThemeAssetScannerTest`.
Its 12 failures belong to unchanged Cloud Run deployment/browser-CI contracts:
Windows/Bash path handling, archive separators, unavailable `os.O_NOFOLLOW`,
symlink permission, CRLF parsing and Unix process invocation. These remain
failures; the full suite is not green. The final focused run followed the rich
history fix. No application PHP source changed.

The complete 38-case Edge run preceded the test-only change to measure themes
through saved appearance and fresh navigation. The four final Edge contrast
checks and five WebKit checks cover that change on the same application build.
Earlier direct-DOM theme switching sampled a light background with dark text
colors in WebKit; waiting for the offscreen transition did not complete. Both
failed attempts are retained. The final test uses the actual appearance-settings
flow, with no additional product styling change.

The original browser failures, the first 16/17 Edge run, and diagnostic 2/3 native
recovery run remain available. They were used to isolate causes, not discarded
in favor of passing counts. The new stale-snapshot control failed before the
history initialization fix. An early typing control initially assumed enhancement
always showed rich mode; it now checks the existing behavior of retaining a
focused native textarea until the user selects Rich text.

## Build and workspace boundaries

`build-identity-final.json` identifies HEAD
`35cacebb7b07fde20c4b5d59f462a0d9b7c00dae` plus the dirty source/asset bytes, with
candidate manifest **`1c24a3765cdd43f9`**. The deployed-release pins are unchanged.
`implementation.diff` contains the reviewed application/test changes.

The primary installed tools differ from the lockfile: Vite 8.1.3 and Wrangler
4.118.0. Primary asset tests were 34/35, with the real Workers binding test failing
under the older Miniflare API. Final build/check/tests used an isolated source
copy and the existing matching toolchain from the earlier managed worktree:
Vite 8.3.1, Wrangler 4.149.0, Miniflare 5.20261006.1-alpha. Generated outputs were
copied back and compared byte-for-byte. Package edits and primary dependencies
were preserved. This is isolated toolchain validation, not a repair of installed
dependencies.

All browser writes used the disposable fixture and a private local MariaDB
container. Email delivery was disabled. The PHP preview, container and temporary
toolchain junctions were removed after identity checks; see `cleanup-final.json`.
Unrelated checkout work was preserved; see `source-preservation-final.json`.

## Remaining device evidence

The Runner continuation preserves the original F-07/F-08/F-09 observations and
adds changed-build check IDs. All three fixes are **implemented**, not certified
on physical devices. `device-script.md` reserves nine of ten entries, with four
for applied preview/recovery regressions within the five-entry cap. Every entry
is **NOT READY** until an exact, reachable fixed build, disposable access and the
specified device are provisioned. Desktop WebKit is not iOS Safari proof.

The helper validates the ledger with exit 3: structurally valid but incomplete.
It does not authenticate test artifacts or replace device evidence. The original
audit remains in `output/playwright/mobile-web-audit-2026-10-22/20261022T203208Z-61589551/`;
its historical references are retained as local paths. Full investigation output
is in `output/mobile-ux-fixes-2026-10-22/`. Earlier release evidence under
`docs/evidence/thread-mobile-ux-2026-10-22/` is unchanged.
