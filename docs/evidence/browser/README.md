# Gate A browser evidence

Full-page screenshots of the key Gate A surfaces, captured by driving the real
server-rendered app in Chromium at two viewports:

- `desktop/` — 1280×800
- `mobile/` — 390×844 (deviceScaleFactor 2)

Both sets cover the Gate A surfaces (`01-home` … `23-admin-webhook-delivery-log`), including
the admin **board-roster UI** (`09-admin-board-roster`), the no-JS login path, a
member's view of a private board, and the Phase 3 composer/drafts/upload,
preferences, branding, product-tour, API-token, and webhook paths.

They also cover the **Phase 2 operator-surface closeout** (2026-06-29): the per-user
admin record (`14-admin-users`, `15-admin-user-record`), board reorder + archive
(`20-structure-before`, `21-structure-after-move`, `22-board-archived-readonly`,
`23-board-unarchived`), the announcement banner (`20-announcement-banner`,
`21-announcement-dismissed`), and the email-ops dashboard (`22-admin-email-dashboard`,
`23-admin-email-suppressed`, `24-admin-email-test-sent`). (Numeric prefixes repeat
across these independently-authored specs; the full filenames are distinct.)

These are generated, not hand-made — regenerate with `cd tests/browser && npm run
evidence`, or download the `gate-a-browser-evidence` artifact from the
**Browser evidence** GitHub Actions workflow. See `tests/browser/README.md`.

The current carryover branch also includes `25-poll-voted`, which proves the
deploy-dark poll UI through the real server-rendered vote POST/redirect/result
flow on desktop and mobile.
