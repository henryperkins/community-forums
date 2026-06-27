# Gate A browser evidence

Full-page screenshots of the key Gate A surfaces, captured by driving the real
server-rendered app in Chromium at two viewports:

- `desktop/` — 1280×800
- `mobile/` — 390×844 (deviceScaleFactor 2)

Both sets cover the same 14 pages (`01-home` … `14-private-board-member`),
including the admin **board-roster UI** (`09-admin-board-roster`), the no-JS login
path, and a member's view of a private board.

These are generated, not hand-made — regenerate with `cd tests/browser && npm run
evidence`, or download the `gate-a-browser-evidence` artifact from the
**Browser evidence** GitHub Actions workflow. See `tests/browser/README.md`.
