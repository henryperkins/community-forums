# Imladris theme — browser evidence

The "Imladris" visual skin (Rivendell register: warm parchment surfaces, evergreen
brand, a single mallorn-gold accent, lyrical serif typography, the eight-pointed
elven-star mark) translated from two claude.ai/design projects into the real
vanilla-PHP / strict-CSP app. Captured live in Chromium by driving the running
server (`php -S 127.0.0.1:8000`), logged in as a seeded admin, at 1440×900
(desktop) and 390×844 (mobile).

These satisfy DESIGN §13 (UI-visible work needs browser evidence): every surface
is the **real server-rendered HTML + external `app.css` + PE `app.js`** under the
strict CSP (`script-src 'self'; style-src 'self'`, no inline) — no canvas markup,
no React, no CDN fonts (system-serif fallback stacks).

| File | Surface | Shows |
|---|---|---|
| `imladris-01-home-desktop.png` | Board index (guest) | Parchment shell, elven-star brand, sidebar rail, gold `#` boards, board cards |
| `imladris-02-login.png` | Auth (plain variant) | Parchment auth card, serif heading, evergreen button, gold-underlined link |
| `imladris-03-board.png` | Board thread list | Status left-rules + Marcellus chips (Pinned/Needs answer/Solved), serif titles, New Topic |
| `imladris-04-thread.png` | Conversation | OP/Staff/✓ Accepted-answer chips, green accepted-answer block, gold blockquote, markdown composer |
| `imladris-05-inbox-3pane.png` | **True 3-pane Community Inbox** | sidebar + thread list (scrollable filter pills, active row) + reading pane with a topic loaded via PE-JS (`?t=`) |
| `imladris-06-mobile-board.png` | Mobile board | Hamburger, wrapped topbar, status cards, gilt "+" FAB |
| `imladris-07-mobile-drawer.png` | Mobile nav drawer | Off-canvas rail over a scrim (JS-only; no-JS keeps a stacked rail) |
| `imladris-08-leaderboard.png` | Top Contributors | Gold roman-numeral ranks, gilt top-3 avatars, comma rep + gold stars |
| `imladris-09-inbox-twilight.png` | 3-pane inbox, **dark** | The twilight (night) register — verified legible after the adversarial review fixed a source-order bug that had clobbered the dark text tokens |

An adversarial 5-dimension review (CSP · token/dark-parity · accessibility · spec-fidelity · PE)
was run against the diff; CSP came back clean, and the high/medium findings were applied —
notably **twilight (dark) parity** (new components had no dark text overrides; one base-token
definition sat *after* the dark blocks and won by source order — caught via computed-style check),
**focus-ring contrast** (kept a ≥3:1 outline instead of the gold-only ring), **gold-on-parchment
small-text contrast** (a darker `--gold-ink`), **44px touch targets**, and PE gaps (Back-button
restore, redirect-to-login guard, focus-not-aria-live on the reading pane).

Demo data was seeded with `scratchpad/seed.php` (admin `arwen@imladris.test` /
`password123`). Carryover: fold these surfaces into the canonical `tests/browser`
Playwright harness (`npm run evidence`) so they regenerate in CI.
