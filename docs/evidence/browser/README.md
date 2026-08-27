# Gate A browser evidence

Full-page screenshots of the key Gate A surfaces, captured by driving the real
server-rendered app in Chromium at two viewports:

- `desktop/` — 1280×800
- `mobile/` — 390×844 (deviceScaleFactor 2)

Both sets cover the Gate A surfaces (`01-home` … `23-admin-webhook-delivery-log`), including
the admin **board-roster UI** (`09-admin-board-roster`), the no-JS login path, a
member's view of a private board, and the Phase 3 composer/drafts/upload,
preferences, branding, product-tour, API-token, and webhook paths.

The composer set includes `17-composer-upload` for the visible file picker and
compact in-box chip, `26-slash-menu` for the floating non-reflowing insert menu,
`80-thread-study` for the new reply shell in its reading context, and
`82-composer-emoji` for the accessible server-backed dialog/grid picker. The
shared-content closeout adds `85`/`86` for grouped and removed reply states and
`87`/`88` for the real light/dark composer preview. Focused
coverage spans inline axe scans, JavaScript-disabled and reduced-motion modes,
source/rich Enter behavior, attach, and the `rich_composer=false` Inbox path.

They also cover the **Phase 2 operator-surface closeout** (2026-06-29): the per-user
admin record (`14-admin-users`, `15-admin-user-record`), board reorder + archive
(`20-structure-before`, `21-structure-after-move`, `22-board-archived-readonly`,
`23-board-unarchived`), the announcement banner (`20-announcement-banner`,
`21-announcement-dismissed`), and the email-ops dashboard (`22-admin-email-dashboard`,
`23-admin-email-suppressed`, `24-admin-email-test-sent`). (Numeric prefixes repeat
across these independently-authored specs; the full filenames are distinct.)

The mobile-composer refinement adds a before/after pair at the same 390×844
viewport and the two states the reply dock's expansion model introduces:
`90-composer-reply-expanded-before/after` (the nested editor card and the
standalone Source shelf, then the single framed surface),
`91-composer-new-topic-before/after` (the detached title above a framed
component, then the title as the composer's header field),
`92-composer-reply-collapsed`, and
`93-composer-reply-folded-after-outside-tap` (an empty dock folding back up).
The `-before` frames are the only hand-staged files here — captured from the
pre-change tree for comparison — and are not reproduced by `npm run evidence`.

The rest are generated, not hand-made — regenerate with `cd tests/browser && npm
run evidence`, or download the `gate-a-browser-evidence` artifact from the
**Browser evidence** GitHub Actions workflow. See `tests/browser/README.md`.

The current carryover branch also includes `25-poll-voted`, which proves the
deploy-dark poll UI through the real server-rendered vote POST/redirect/result
flow on desktop and mobile, plus `26-slash-menu` and `27-giphy-inserted`, which
prove the deploy-dark slash insertion menu and direct GIPHY media insert path on
desktop and mobile.

The Study thread-view evidence adds `80-thread-study` for the closed reading
surface including its reply shell and `81-thread-tools` for the open Topic tools drawer/sheet. Existing
journeys now capture their controls in the Study locations: `29-topic-workflow`
uses Standing, Watch, and Topic management; `50-split-merge-panel` records the
restructure modal; `51-thread-merged` records its merged result; and
`77-living-brief-curator-controls` records the curator footer at the foot of the
brief itself — the Memory section of the drawer now holds only the anchor that
jumps to it — with its Amend composer open and its More disclosure open on the
version history, the related-topic form, Pause, and the Retire confirm, and
`75-thread-intelligence-fallback` records the curator-only empty panel beside
the deterministic fallback. `75`–`78` are captures of the surface itself rather
than of the page: the Study's thread column is its own scroll container, so a
full-page shot frames only what the pane has not scrolled past, and what the
pane clips is never painted. See `tests/browser/README.md`.
