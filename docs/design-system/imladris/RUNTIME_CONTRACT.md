# Runtime contract — what consuming the Imladris system means

Governing rule: **the design system owns presentation; RetroBoards owns behavior.** Conflicts resolve in this order: DECISIONS.md → product/surface specs → accepted ADRs → application contracts/tests → Imladris references. The system never removes, downgrades, enables, or redefines a forum feature; missing design coverage is added *here* before application adoption.

## Constraint class (bundle inspection `4efe4e33`, locally reconciled through `6d81da5`)
- **CSP**: `default-src 'self'; base-uri 'self'; form-action 'self'; script-src 'self'; style-src 'self'` (+ `img-src 'self' data:`). No CDN of any kind. Previews and artifacts must work self-hosted; `tokens/fonts.css` therefore declares `@font-face` over bundled WOFF2 (`assets/fonts/`, OFL licenses alongside) — never `@import` from a font CDN.
- **Progressive enhancement**: every surface works with no JavaScript; a `has-js` class gates enhancements. Designs must include the no-JS state (e.g. the composer's plain Markdown textarea) — never JS-only anatomy.
- **Server-rendered**: vanilla PHP templates; the React primitives in `components/` are **design previews only**, never production implementation guidance.

## The composer (COMPOSER.md v0.8, ADR 0013/0020)
One shared shell, four mounts (reply / new_thread / dm / edit), identical feature surface. Canonical content is **Markdown in the textarea**; WYSIWYG mounts over it when `rich_composer` + `wysiwyg_composer` are on. Every form carries a CSRF `_token` and a fresh server-rendered `idempotency_key`. Send is a full navigation (no optimistic send — ADR 0020). Desktop Enter-to-send is context-aware (off in list/quote/code); Cmd/Ctrl+Enter always sends; touch soft-Enter = newline. Drafts persist locally per context key + `server_drafts` sync. The former "Posting as" strip / text-button toolbar anatomy is superseded (v0.7) and must not reappear.

## Theming
Light (parchment) is default; twilight is `[data-theme="dark"]`; system theme follows `prefers-color-scheme`. Consume **semantic tokens** (`--surface-raised`, `--brand`, `--accent-2`, `--on-done`, `--text-body`…), never raw primitives, so the register flips for free. `--text-body` is a **color**; the body font size is `--text-size-body`.

## Emoji
Decorative/status emoji in UI chrome: prohibited (status = word + colour). Authored-content emoji and the composer's emoji tooling (`:` autocomplete, picker dialog, custom emoji, GIPHY slash where configured): supported product features.

## Flags
Feature-flag truth lives in `production-contract.json`. Reserved-dark features (`server_extensions`, `governance`, `service_principals`, `verified_links`) receive **no invented UI** — only the disabled admin-nav entry that exists in production.

## Archives
`_archive/` holds reference snapshots (app frontend assets at the inspected commit, the 2026-06 design pull). They are provenance, never imported, never canonical.
