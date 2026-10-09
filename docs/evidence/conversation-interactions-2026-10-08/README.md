# Conversation interaction evidence — 2026-10-08

Implements the missing production behavior from
`CommunityForumsDesignSystem.zip` (SHA-256
`49fd7cedf13f1d59f9ee855aaa10859667668d286451eaeca65ffe7b5bc34200`).
The existing reactions, mention endpoints, rich/source editors and Messages
routes were already available locally. This slice adds their missing feedback,
identity and presentation behavior; it does not enable new feature flags.

## Verified behavior

- Reactions keep aggregate counts while member name tips exclude inactive,
  either-way blocked and former private-board members. Deleted/held targets
  apply the canonical topic read gate before toggling or returning identities.
  One capped batch query supplies names; empty reaction pages retain their
  existing query budget. Guest spans have no reactor tips.
- Surviving counts rise/fall calmly. Touch additions/removals update the visible
  tray and both picker copies, without replacing surviving buttons. A per-post,
  per-emoji lock prevents duplicate pending submissions. Focus survives a
  completed toggle, respects deliberate focus moves and returns to the menu
  when a focused chip disappears. A committed but lost response offers a GET
  reload, with no automatic POST replay.
- Bare `@` lists active named participants in readable topic/reply contexts.
  Matching and private-board candidate eligibility apply before the cap.
  Source and rich insertion preserve canonical text, appropriate spacing and
  caret placement. Person rows use safe avatar URLs and canonical monograms.
- Source highlights match textarea geometry and scroll, including trailing
  newlines, and exclude unknown handles, emails, inline code, indented code and
  ordinary/container fences. Emphasized prose still highlights. Rich over-cap
  mentions keep the muted dashed cue. Actual server previews verify semantic
  mention ink, wash, medium weight, underline and unchanged padding in both
  themes.
- Ordinary Messages row switches stamp entry state before body parsing,
  fade identity for 140ms and reveal letters with an 8px rise over 240ms after
  40ms. Other room regions stay still. Latest-letter positioning remains.
  Reload/history, modified clicks, stale/wrong targets and both reduced-motion
  settings suppress replay. The marker is consumed once and cleaned up.

## Validation

- Full local PHPUnit: **3,188 tests, 25,264 assertions, one skip**.
- Scoped mention/DM checks: **27 tests, 169 assertions**; native prepares:
  **14 tests, 94 assertions**.
- Scoped reaction/flags/performance checks: **63 tests, 534 assertions**.
- `composer verify:imladris`: **24 tests, 305 assertions**, current runtime assets.
- `npm run check:assets` passes. `npm run test:assets`: **24 passed**, including
  immutable delivery and hash verification of the new external head script,
  classic strict-mode semantics and retained deployed asset dependencies.
- Chromium desktop/mobile conversation specs: **51 passed, one desktop-only
  touch-case skip**. Includes native no-JS reactions, keyboard and edge-clamped
  tips, both reduced-motion paths, real lost-response recovery and a scoped
  serious/critical accessibility scan of the twilight post.
- WebKit desktop/mobile runs the same conversation specs: **51 passed, one
  desktop-only touch-case skip**. The temporary configuration only switches the
  repository harness's browser engine; it uses the same real routes and fixtures.
- Existing composer-shell, expansion, WYSIWYG and Messages polling specs:
  **105 passed, 46 intentional skips**, with one mobile new-topic test timing out
  at `networkidle` in the accumulated browser fixtures. The exact untouched spec
  then passed against verified `HEAD` `8206122d` (2.4s) and the current app (3.2s)
  using identical fresh isolated fixtures. The current trace confirms the new
  hashed entry script loaded. No test was weakened; the timeout remains
  unattributed rather than claimed as a product regression or a baseline defect.

The new regression specs are `tests/browser/conversation-{mentions,reactions,motion}.spec.ts`.
The browser harness used the real PHP kernel and an isolated migrated/seeded
database, with self-reaction fixtures and preference restoration. Server tests
also used private databases. Captures in `desktop/` and `mobile/` show person
rows, source wash, posted preview, keyboard tips, twilight and touch additions.
The captures were inspected at their rendered sizes; the source mirror adds no
glyph padding/borders and keeps the textarea as the submitted value.

The runtime digest was explicitly reconciled after reviewing the handoff's
selective CSS adoption and generated asset graph. The generated compatibility
bridge and inspected-mirror commit remain intact. Impeccable's detector flags
existing whole-file conventions and the handoff's explicit 3px input-mark
radius; these do not override the supplied treatment.

## Review fixes — 2026-10-08

The 15 confirmed review findings are corrected and pinned by regression checks:

| Finding | Correction and proof |
| --- | --- |
| Anonymous self-reaction identity leak | The reactor query excludes an anonymous post's author; integration checks cover other viewers' JSON/HTML and the author's viewer-relative `You` label without changing counts. |
| Rich line-final `&#x20;` | Paragraph boundaries supply their own separator; the text serializer keeps a single final handoff space as whitespace. Browser checks accept mentions after `_`, `*` and `\`, submit, and reopen Source on edit. |
| Escaped handle truncation | Intraword underscores serialize literally, including repeated underscores. The server parser also resolves existing escaped handles in full; the notification regression proves `@alice\_w` notifies `alice_w`, never `alice`. |
| Picker reopening after accept | Acceptance and its resulting caret/query are tracked across synthetic textarea synchronization. A subsequent edit resumes discovery. |
| Unmatched ticks and container fences | Source completion and paint share a cached scanner with paragraph/block boundaries and closing/exited quote/list fences, including mixed/nested containers. |
| Space before closing formatting | Source insertion preserves `*`, `_`, `~` and `\|` delimiters and caret placement. |
| List continuation mistaken for code | Nested items and paragraph-continuation indentation remain prose; genuinely indented code remains excluded. |
| Quadratic email scan | A local-part boundary prevents repeated suffix scans. Painting is frame-coalesced and unchanged text/known identities are retained; a 19,000-character no-`@` run paints promptly once despite autosize. |
| Hidden tooltip overflow | Tips have no box at rest and are measured only for viewport-clamped display. A right-edge chip cannot widen the page before interaction. |
| Lost refusal reasons | Enhanced reactions check HTTP status and retain JSON or kernel-rendered HTML refusal reasons. Browser checks exercise real archived-board, not-found and CSRF refusals. |
| Broken recovery navigation | Recovery links use the server-stamped topic GET, without a fragment; browser checks cover fragment arrivals and a real failed-reply POST render. |
| Reaction focus and scrolling | `aria-disabled` plus the submit lock preserves focus while pending. Removed chips focus the actually visible menu with `preventScroll`; narrow fine-pointer and touch paths are covered. |
| Accent-sensitive display names | `LIKE` retains collation-aware matching; regex only normalizes word separators. Emulated/native-prepare integration checks cover accented first/interior words and literal punctuation. |
| Assigned private-board moderators omitted | Candidate filtering includes assignments and context uses `ThreadReadService`; tests cover non-member assigned moderators as both candidate and viewer. |
| Person-row avatar cascade | Scoped `revert-layer` restores layer-owned width, height and font size. Browser computed styles assert 28px × 28px and .66rem. |

An outstanding suggestion query is also reused without stale insertion ranges:
the response uses the current caret, and the delayed-response regression proves
one request replaces the currently selected bare mention.

### Final verification for the review fixes

- Full `composer test` on a private database: **3,193 tests, 25,289 assertions,
  one skip**, passing.
- Targeted reaction/suggestion/notification/parser checks: **44 tests,
  218 assertions**, passing under both emulated and native prepares.
- Chromium desktop/mobile conversation regressions: **71 passed, one
  desktop-only touch-case skip**. Existing WYSIWYG regressions: **51 passed,
  11 intentional viewport skips**; combined run **122 passed, 12 skipped**.
- WebKit desktop/mobile conversation regressions on a separate freshly seeded
  database: **71 passed, one desktop-only touch-case skip**.
- `composer check:imladris`, `npm run check:assets`, TypeScript checking and
  `git diff --check` pass. Worker/asset checks: **24 passed**.
- `composer verify:imladris`: **24 tests, 305 assertions**, passing against
  the final regenerated assets.

Updated captures are under [`review-fixes/`](review-fixes/) (Chromium) and
[`review-fixes-webkit/`](review-fixes-webkit/) (WebKit), each with desktop/mobile
person rows, source paint, preview links, reaction tips, twilight and touch
behavior. The person-row and mobile right-edge captures were inspected in
addition to the computed-style and document-width assertions. The earlier
validation section records the initial implementation run; this section owns
the reviewed version's results. Private verification databases are disposable
and are dropped after the runs.

### Pre-commit re-verification

Before commit, the reviewed tree was re-run independently on fresh private
databases: full PHPUnit **3,193 tests, 25,289 assertions, one skip**
([`review-fixes-phpunit.txt`](verification/review-fixes-phpunit.txt)); Chromium
conversation regressions **71 passed, one skip**
([`review-fixes-chromium.txt`](verification/review-fixes-chromium.txt));
`npm run check:assets`, `composer check:imladris`, worker/asset tests (24), strict
TypeScript and `git diff --check` all pass. Captures from that run were not kept.

This build supersedes local candidate `5de73df06f287ae9`, which is the build
deployed from `8206122d` but was never recorded in `deployedReleases`. Before
this batch is deployed, verify and record that release from an export of
`8206122d` (runbook §14, as `inbox-header` did for `a160f1a21c51fcb2`) and
rebuild so this candidate retains its files.

## Limits

This is local implementation and verification, without a push or deployment. Production feature overrides were not queried. Operating-system
motion preferences were emulated in browsers; physical touch devices and
assistive-technology sessions were not exercised. The spring/bump and proposed
top-of-conversation scroll alternatives were not adopted.
