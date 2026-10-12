# Member accessibility review follow-up — 2026-10-12

Local evidence for the reviewed label, touch-target and accessible-name changes,
including remediation of the stylesheet tracking omission. The source is the
uncommitted candidate based on `e8285dbbea31a2793f8e692d6aa440f50f7e5da7`.
Exact source and generated file hashes are recorded in `source-identity.json`.

## Result and scope

- Inbox reason cues use `--text-eyebrow` (0.72rem), above adjacent status pills
  at `--text-chip` (0.7rem), while retaining sentence case and gold ink. The
  overrides stay after the protected member-surface transfer.
- Recipient removal buttons reserve a real 44×44px box on coarse pointers.
  Wrapping places each target inside its own chip, with no extended pseudo
  element. The 12px icon stays small. Fine-pointer buttons remain 24×24px.
- Coarse-pointer profile activity lines reserve 52px for a 44px disclosure and
  their existing 4px top/bottom padding. Fine-pointer lines remain 40px with
  28px disclosures. Long titles can grow the row further.
- Upload controls and the empty-board action meet the 44px touch floor. The
  existing removal/report field names and their PHPUnit assertions are retained.
- The typography scope is the selectors already changed in the reviewed diff,
  consistent with DESIGN.md's floor for new work. This evidence does not certify
  a product-wide migration of legacy labels.

## Checks

| Check | Result |
| --- | --- |
| `DB_TEST_DATABASE=retroboards_review_test_20261012 composer test` | 3,233 tests; 25,651 assertions; zero failures; one skip. See `phpunit-summary.txt`. |
| `node bin/build-assets.mjs --check` | Passed against the final source and manifest. |
| `php bin/build-imladris-assets.php --check` | Passed; runtime and mirror carry digest `fe858c9895e0ba4414003f5d31623e51d56eecfaea0c58770c8766ed06d0cdfc`. |
| PHP syntax checks | `post_toolbar.php` and this evidence's `fixture.php` passed. |
| Playwright CLI checks | 29 passing scenarios and 42 captures, with measurements in the four JSON receipts. |
| Impeccable detector | No findings on changed lines. The file-wide scan reported 223 existing findings outside this change. |
| Manifest closure | All 56 files exist on disk and in the Git index, matching their recorded SHA-256 in both. |

The initial PHP run used the baseline from before the final hashed asset rebuild
and failed its digest guard. The baseline was refreshed after rebuilding; the
complete final suite above passed. The two PHP runs are separate evidence runs.

## Browser evidence

Headless Chromium 155; 1280px desktop with a fine pointer and 320/390px emulated
touch layouts. Inbox checks exercise a 14-topic queue in both comfortable and
compact density, in day and twilight. Every chip meets the applicable type floor,
fits its wrapping group without clipping, and every reason cue remains larger
than the status pills. Recorded row heights show natural growth on narrow screens.

Recipient checks select four real members, measure adjacent and wrapped chips,
check disjoint target rectangles and their containment, then tap/click the top
and trailing edges. The canonical recipient field retains exactly the intended
remaining members. Profile checks open two independent disclosures and verify
that their targets fit their rows. Board, upload, thread, session and draft
captures cover the other sampled labels and controls, with no page-wide
horizontal overflow. The controls runs report zero browser errors and use
separate accounts to avoid competing draft revisions.

Representative captures:

- [320px compact Inbox](touch-inbox-320-light-compact.png)
- [Desktop compact Inbox in twilight](mouse-inbox-1280-dark-compact.png)
- [Wrapping recipient chips](touch-recipients-390-light.png)
- [Profile disclosures in twilight](touch-profile-dark.png)
- [Board labels and marks](touch-board-light.png)
- [Touch upload controls](touch-upload-dark.png)
- [Topic tag and reference card](mouse-thread-light.png)
- [Draft revision chips](touch-drafts-dark.png)

## Asset delivery

The final manifest version is `be1d396948b3adcc` and serves
`/assets/dist/app-style-DxGgpq3E.css`, SHA-256
`7515d1850e190c891e658389eb24159dcf5179d8847d9fd72a53da202fb83aa7`.
The browser verifies that this exact hashed URL is present and returns HTTP 200.
The generated file is explicitly staged alongside the source, manifest and
evidence. Previously deployed hashes remain in the retained release window;
the superseded untracked `app-style-D4-Cu3Gp.css` is not a deployed release.

## Reproduction

Use two newly created disposable local databases and private rate-limit/package
stores. The fixture refuses databases outside the `retroboards_review_*` prefix.
The recorded run used PHP 8.4.26 and Node 24.21.0.

```bash
DB_DATABASE=retroboards_review_20261012 \
  RATELIMIT_PATH=storage/ratelimit-e2e-review-20261012 \
  PACKAGES_STORAGE_PATH=storage/packages-e2e-review-20261012 \
  bash tests/browser/prepare.sh
DB_DATABASE=retroboards_review_20261012 php tests/browser/forum-inbox-fixture.php
DB_DATABASE=retroboards_review_20261012 php tests/browser/profile-surface-fixture.php
DB_DATABASE=retroboards_review_20261012 php docs/evidence/accessibility-followup-2026-10-12/fixture.php
```

Launch the local PHP server on `localhost:8027` using the same database and stores,
the browser harness's non-production APP_KEY, `SESSION_SECURE=false`, and the
upload transport settings. Open separate Playwright CLI mouse/touch sessions
against `/login`, configuring the installed Chromium executable. Run each
single-function script through `run-code --filename=...` and save the `--raw`
result as its receipt. `inspect.js` covers Inbox, recipient, profile and session
surfaces; `controls.js` covers board, upload, thread and draft surfaces. It pins
this candidate's stylesheet hash and seeded topic URL deliberately.

## Local evidence boundaries

These are local Chromium checks, including emulated touch and DOM hit testing;
physical devices, WebKit, Firefox and production deployment were not checked
as part of the local UI pass. Production verification follows below.
Upload responses and the 1px image are controlled fixtures for layout and control
behavior, not evidence of storage/provider delivery. Recipient checks do not send
messages. Accessible-name checks inspect the rendered removal/report controls
without submitting moderation actions. This is focused UI evidence in addition
to the full PHP suite; the independent full browser matrix was not run.

## Production release — 2026-10-12

The source candidate was committed and pushed to `main` as
`c86a835613527509ef3d83bf9edd8bd0e36caa9e`. Both automatic production deployments
succeeded for that exact SHA:

- Cloudflare build `712932eb-02ab-4b9b-82c2-ac96b9a110e5` deployed Worker version
  `50beae2a-e851-4e2d-8a5f-e4c47c6e1c5b` at 100% traffic (`worker-release.json`).
- Cloud Build `2cf11e17-4b7a-4818-ad8e-d6b1b8320a8c` completed every origin and
  job deployment step. Cloud Run revision `retroboards-00022-lmf` serves 100%;
  the four cron jobs and monitoring job reference this SHA's image
  (`origin-release.json` and `cloud-build-release.json`).
- All 56 originally published asset bodies match the source manifest's SHA-256
  (`live-assets.json`). Public `/healthz` returns HTTP 200 with both status and
  database `ok`; `/login` returns HTTP 200 and references the rebuilt stylesheet.
  Direct unauthenticated origin access remains HTTP 403 (`production-release.json`).

After these checks, the asset runbook's record command pins `be1d396948b3adcc`
and its two predecessors. It retires the old `app-64d622826ba32cb9.js` and
`app-style-kNCnSn5Q.css`, leaving 54 manifest files whose live hashes were already
verified. The application/mirror baseline is refreshed for this generated-file
retirement; the original local receipt remains a snapshot of the pre-record
candidate. Authenticated production UI was not exercised.

The recording change passes `composer verify:imladris` (24 tests, 305
assertions), all 35 Worker tests, both generated-asset checks and the staged
whitespace check (`recording-checks.txt`). Its runtime and mirror digest is
`e31eca32352c7d96c160fa22f544e80ff9d46493c6c52f69d5c43c184144730b`.
