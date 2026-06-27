# ADR 0001 — Composer engine: server-rendered textarea + progressive enhancement

**Status:** Accepted (Phase 3 Gate A)
**Date:** 2026-06-27
**Workstream:** P3-02

## Context

PHASE_3_PLAN §5 #5 names **Milkdown the first spike, not a predetermined result**, and allows a "CodeMirror/live-Markdown fallback" to win if it meets the round-trip, accessibility, mobile, mention, upload, and progressive-enhancement criteria (§5 #7: *"Disabling the rich editor must reveal a usable Markdown `<textarea>`, not remove the ability to post."*). DECISIONS makes **Markdown canonical** and editor-specific JSON/HTML never the source of truth.

The stack is **vanilla PHP + MySQL on a single VPS, server-rendered with no JS build step** (no bundler, no npm in the request path; the only `node_modules` is the Playwright dev-dependency for evidence). A WYSIWYG ProseMirror/Milkdown integration would introduce a build toolchain, a client-side Markdown serializer that must be kept byte-compatible with the server CommonMark renderer, and a large surface for round-trip drift — the exact risk §12 calls out ("Rich editor corrupts canonical Markdown").

## Decision

Ship the **server-rendered `<textarea>` as the canonical composer**, enhanced progressively by one small vanilla script (`public/assets/composer.js`):

- **One pipeline, four contexts.** New thread, reply, DM, and edit all post Markdown to the same server validation + `League\CommonMark` render + `HtmlSanitizer` allowlist. The **live preview** (`POST /composer/preview`) renders through that *same* server pipeline, so preview == stored == displayed; there is no second client-side Markdown engine to drift.
- **Round-trip safety by construction.** Canonical Markdown is stored byte-for-byte (`AppComposerTest::test_canonical_markdown_is_stored_verbatim`); there is no editor model to serialize, so a no-op load/save cannot mutate content.
- **Enhancement, not dependency.** The toolbar, counter, preview, image paste/drop, and local draft autosave are added on top of a fully working textarea. With JS disabled or `features.rich_composer = false`, posting still works (the kill switch is the absence of the script).
- **Supported syntax** via CommonMark core + Strikethrough + Autolink + a custom `||spoiler||` delimiter extension, plus `/media/{id}` images — all funneled through the allowlist sanitizer (img `src` restricted to same-origin `/media/`).

## Consequences

- **Pro:** no build step, no serializer-drift class of bugs, smallest XSS surface, instant kill switch, full no-JS path, trivially testable server-side (unit round-trip corpus + integration parity).
- **Con:** not a true WYSIWYG; rich affordances (drag-to-reorder images, inline mention autocomplete) are lighter than a ProseMirror editor would give.
- **Revisit trigger:** if measured demand justifies WYSIWYG, a Milkdown/ProseMirror layer can be added *as further enhancement over this same textarea + server pipeline* — the canonical-Markdown contract and the kill switch stay intact, so adopting it later is non-breaking.
