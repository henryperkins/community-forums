# ADR 0013 - WYSIWYG composer over canonical Markdown

**Status:** Accepted
**Date:** 2026-07-02
**Supersedes:** ADR 0001 for the enhanced editor surface only. ADR 0001 remains authoritative for the textarea fallback, server preview, and canonical Markdown storage.

## Context

The approved WYSIWYG composer design changes the enhanced editing surface from Markdown-first textarea controls to a true rich editor. Markdown remains the source of truth in `posts.body` and `dm_messages.body`; cached HTML is still produced by the server renderer and sanitizer.

The current renderer supports CommonMark core, strikethrough, autolinks, tables, task lists, and the custom `||spoiler||` extension. The current composer already provides a fully working no-JS textarea path plus toolbar, preview, drafts, uploads, slash inserts, and GIPHY.

## Decision

Adopt Milkdown first for the optional WYSIWYG layer because it is Markdown-native and aligns with ADR 0001's revisit trigger. The enhanced editor mounts only when `rich_composer` and `wysiwyg_composer` are both enabled, the browser supports the adapter, and the form is not opted out.

The underlying `<textarea name="body">` remains in the form and is the only submit source. Milkdown serializes to that textarea after user edits and before submit. Opening an edit form and submitting without user changes must not rewrite `body` solely because the serializer normalized legacy Markdown.

Editor round-trip acceptance is semantic parity against the server-rendered sanitized HTML. Byte-stable Markdown is required only for fixtures intentionally authored in the editor's canonical output form.

All editor JavaScript and CSS are committed static assets served from `/assets`. The root `package-lock.json` pins exact development dependency versions. Deployment serves static files only and does not run npm.

The strict CSP remains `script-src 'self'; style-src 'self'`. Inline scripts, inline styles, runtime `<style>` injection, parser or `setAttribute` `style=""` writes, and constructable/adopted stylesheet rule injection are not allowed by repository policy. CSSOM property writes are acceptable.

The editor build pipeline uses Vite only at development/build time and emits a deterministic `wysiwyg-composer.js` plus `wysiwyg-composer.css` pair into `public/assets`. The initial Task 7 adapter factory is intentionally no-op while the pipeline and strict-CSP loading path are proven.

## Consequences

- Operators can roll back to the current Markdown-enhanced composer by setting `wysiwyg_composer=false`.
- Operators can disable all enhanced composer JS by setting `rich_composer=false`.
- Suggestion pickers and canonical Markdown insertion are bridge-level behavior shared by textarea and Milkdown adapters.
- Mention links are baked into the cached `body_html` at write time (as a pass that runs *after* sanitisation) and are gated by the `mentions` flag; a later username change or deactivation leaves the previously cached link text/target unchanged, consistent with the rest of the `body_html` cache.
- If Milkdown cannot pass CSP, mobile, accessibility, and semantic round-trip evidence, the implementation stops at the Markdown-enhanced path and revisits CodeMirror/ink-mde rather than shipping a bespoke editor.
