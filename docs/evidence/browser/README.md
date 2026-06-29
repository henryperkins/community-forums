# Gate A browser evidence

Full-page screenshots of the key Gate A surfaces, captured by driving the real
server-rendered app in Chromium at two viewports:

- `desktop/` — 1280×800
- `mobile/` — 390×844 (deviceScaleFactor 2)

Both sets cover the same 23 screenshots (`01-home` … `23-admin-webhook-delivery-log`), including
the admin **board-roster UI** (`09-admin-board-roster`), the no-JS login path, a
member's view of a private board, and the Phase 3 composer/drafts/upload,
preferences, branding, product-tour, API-token, and webhook paths.

These are generated, not hand-made — regenerate with `cd tests/browser && npm run
evidence`, or download the `gate-a-browser-evidence` artifact from the
**Browser evidence** GitHub Actions workflow. See `tests/browser/README.md`.
