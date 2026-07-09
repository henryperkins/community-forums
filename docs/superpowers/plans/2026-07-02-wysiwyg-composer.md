> Archived design record — implementation plan + design spec(s) merged during the wysiwyg doc consolidation; see the ADR / runbook / PR referenced below for shipped status.

# WYSIWYG Composer — Consolidated Design & Implementation Record

This archive consolidates the WYSIWYG composer program into one document: the product/technical **design specs** (the "what & why"), the task-by-task **implementation plans** (the "how"), and the **design review notes**. Shipped status: the composer stream shipped deploy-dark behind `wysiwyg_composer` (ADR 0013) via **PR #33**, and `wysiwyg_composer` **graduated to default-ON on 2026-07-02** (runbook `docs/runbooks/wysiwyg_composer.md`).

**Sources merged (read in this consolidation):**
- `docs/superpowers/specs/2026-07-02-wysiwyg-composer-design.md` (design — Part I)
- `docs/superpowers/specs/2026-07-02-wysiwyg-default-on-design.md` (graduation design — Part II)
- `docs/superpowers/plans/2026-07-02-wysiwyg-composer.md` (implementation plan — Part III)
- `docs/superpowers/plans/2026-07-02-wysiwyg-default-on.md` (graduation plan — Part IV)
- `docs/superpowers/specs/2026-07-02-wysiwyg-composer-design.review.md` (review notes — Part V)

---

# Part I — Design: WYSIWYG Composer and Slack-Style References

**Date:** 2026-07-02
**Status:** Approved design (review rounds 1-2 folded in) - pending ADR and implementation plan.
**Scope:** True rich composer (`A`) with searchable user, board, tag, topic, and post
references.

> Precedence: `DECISIONS.md`, `COMPOSER.md`, `DESIGN.md`, `SCHEMA.md`, and accepted
> ADRs remain authoritative. Where this design conflicts, the authoritative source wins.

---

## 0. Context

ADR 0001 accepted a server-rendered Markdown `<textarea>` enhanced by
`public/assets/composer.js`. The current composer already adds a toolbar, server
preview, drafts, uploads, slash/GIPHY insertion, and a textarea fallback. Markdown is
canonical: write routes persist `posts.body` / `dm_messages.body` as Markdown and cache
sanitised HTML through `App\Support\Markdown`.

The approved product direction changes the editing surface from Markdown-first to true
WYSIWYG:

- Users should mostly not see Markdown while writing.
- The editor should feel Slack-like for references: type a trigger, search, select, and
  get an inline chip.
- Users must be able to tag users, boards, tags, topics, and individual posts.
- Markdown remains the canonical stored format. Editor JSON or HTML is never persisted as
  the source of truth.

This is an enhancement over the existing composer contract, not a replacement for the
fallback path.

Because this reverses ADR 0001's accepted textarea-first decision, the implementation
plan must include a new ADR that supersedes or amends ADR 0001 before the WYSIWYG editor
ships.

---

## 1. Locked Decisions

- **Editor model:** true WYSIWYG, not split-preview-only and not merely styled Markdown.
- **Engine recommendation:** Milkdown first, because it is Markdown-native and matches
  ADR 0001's revisit path. Tiptap or raw ProseMirror remain fallbacks if Milkdown blocks
  mention/reference UX or mobile behavior. If no true WYSIWYG engine can satisfy the
  repo's constraints, keep the Markdown-enhanced path and revisit CodeMirror/ink-mde per
  `DECISIONS.md` and `COMPOSER.md` rather than introducing a bespoke editor.
- **Canonical storage:** Markdown stays canonical. The enhanced editor serializes to the
  underlying `<textarea name="body">` on every change and before submit.
- **Round-trip guarantee:** WYSIWYG round trips are judged by semantic parity against the
  server-rendered, sanitized HTML. Byte-stable Markdown is desirable for editor-authored
  fixtures but is not a universal guarantee for legacy free-form Markdown.
- **Fallback:** the server-rendered textarea remains usable with JS disabled or with the
  rich editor disabled.
- **Kill switches:** `rich_composer=false` disables all enhanced composer JS as today.
  A new `wysiwyg_composer` flag controls only the Milkdown layer; when it is false, the
  current Markdown-enhanced composer remains available.
- **Reference trigger:** `@` searches users. `#` is a unified reference search for boards,
  tags, topics, and posts.
- **Reference storage:** rich chips serialize to Markdown forms the server already
  understands where possible.
- **CSP compatibility:** the editor must produce zero CSP violations under the existing
  strict `script-src 'self'; style-src 'self'` policy and must not add `'unsafe-inline'`.
  Blocked: inline scripts, runtime `<style>` injection, and parser/`setAttribute`
  `style=""` writes. CSP-legal CSSOM property writes are acceptable. Runtime-injected CSS
  rules (constructable/adopted stylesheets) are banned by repo policy even though CSP
  permits them: all editor CSS ships as committed static files.
- **Picker scope:** the `@` and `#` pickers are bridge-level composer features available
  on both the textarea and WYSIWYG adapters, discharging COMPOSER.md §6.1's
  mention-autocomplete item. Inline chips are WYSIWYG-only; on the textarea adapter a
  picker selection inserts the canonical Markdown directly.
- **Anonymity safety:** suggestion ranking and metadata must never reveal anonymous
  participation (see §4.3).

---

## 2. User Experience

### 2.1 Writing

The composer renders as a rich multiline editor. Users can use toolbar buttons and normal
shortcuts for bold, italic, strike, code, links, supported headings, lists, blockquotes,
spoilers, tables, images, and undo/redo.

Heading controls must match the server renderer's supported output instead of exposing
levels that will be clamped after submit. The rich surface should present the supported
H2/H3 set, with the server preview remaining the final truth view.

The editor keeps a small "Source" toggle. Source mode exposes the canonical Markdown in
the textarea for advanced edits, debugging, and recovery from serializer edge cases.
Switching back parses that Markdown into the rich surface.

The existing server preview remains available as the final truth view. The preview uses
`POST /composer/preview`, so users can compare the rich editor against the exact stored
render path.

### 2.2 User Mentions

Typing `@` opens a user picker. Results are ranked by relevance:

1. participants in the current thread or DM,
2. board moderators and recent visible participants for the current board,
3. other mentionable users.

Ranking must not become a deanonymization oracle: a user whose only participation in the
scoped thread, DM, or board is anonymous (`posts.is_anonymous`) is ranked as if they had
not participated there.

Choosing a user inserts a mention chip displayed as `@username`. The Markdown remains the
raw token `@username` so `MentionParser` and existing mention notification behavior keep
working. The rendered post should link valid mentions to `/u/{username}` outside code
blocks while preserving the existing anti-spam cap and blocked-user notification rules.
The rich surface should visually mark mentions beyond the per-post cap (currently 10) as
non-notifying instead of letting excess chips look identical to effective ones.

### 2.3 Unified `#` References

Typing `#` as a reference token opens a grouped reference picker. The trigger must not
fire for Markdown heading syntax such as `# `, `## `, or `### `, and should only open when
`#` starts a token and is followed by a non-space query character. It should not open
inside code, preformatted text, or an existing link.

The same query can return:

| Type | Display in picker | Markdown serialization |
|---|---|---|
| Board | `#general` plus board name | `[#general](/c/general)` |
| Tag | `#release-notes` plus tag name | `[#release-notes](/tags/release-notes)` |
| Topic | topic title plus board | `[Topic title](/t/123-topic-title)` |
| Post | topic title plus excerpt, author, and time | `[Post in Topic title](/t/123-topic-title#p456)` |

The picker is keyboard-first: arrow keys move, Enter/Tab selects, Escape closes. It also
works as a bottom sheet on mobile.

Both pickers are bridge-level composer features (§3.2): they also mount on the plain
textarea composer whenever `rich_composer` is on, where a selection inserts the canonical
Markdown directly and no chip is shown.

The inline chip is an editor affordance. Stored and rendered output remains Markdown. With
`content_references` disabled, inserted board, tag, topic, and post references render as
plain links; reference cards require the `content_references` flag, and tag cards also
require the tag enum migration and the `tags` feature.

### 2.4 Paste Behavior

Pasting an internal board, tag, topic, or post URL converts it into a chip in the rich
surface and rewrites the link to the same canonical Markdown form the picker inserts
(for example `[#general](/c/general)`), so the editor chip and the fallback-rendered link
text agree; undo restores the raw pasted URL. Pasting external URLs remains normal
Markdown/link behavior; link previews stay governed by the existing `link_previews` flag.

---

## 3. Architecture

### 3.1 Progressive Enhancement

Server templates keep rendering normal forms with `<textarea class="composer-input"
name="body">`. The WYSIWYG bundle mounts on each `form.composer` only when all of these
are true:

- `features.rich_composer` is enabled,
- `features.wysiwyg_composer` is enabled,
- the browser supports the editor requirements,
- the form is not explicitly opted out.

If mounting fails, the current textarea composer remains intact.

### 3.2 Composer Bridge

The implementation should introduce a small editor bridge so drafts, uploads, counters,
preview, and submit logic do not care whether the body is edited in a textarea or
Milkdown.

Required bridge interface:

- `getMarkdown(): string`
- `setMarkdown(markdown: string): void`
- `insertMarkdown(markdown: string): void`
- `replaceSelection(markdown: string): void`
- `replacePendingUpload(token: string, markdown: string): boolean`
- `focus(): void`
- `onChange(callback): void`
- `setDisabled(disabled: boolean): void`
- `destroy(): void`

The existing textarea path becomes a `TextareaComposerAdapter`. Milkdown becomes a
`MilkdownComposerAdapter`. Shared composer behavior uses the bridge instead of directly
reading and writing `ta.value`. The `@`/`#` pickers are built against this bridge so
they work on both adapters; only the chip presentation is adapter-specific.

`replacePendingUpload` preserves the current upload flow where the composer inserts a
temporary uploading image token and swaps it for final Markdown on completion. The
textarea adapter can replace the token substring; the rich adapter should find the
corresponding pending image/link node and replace that node.

### 3.3 Bundling

Milkdown requires a JavaScript build step. The production request path remains static:
the built bundle is committed under `public/assets/`, and deployment only serves static
assets as it does today. The build must be reproducible from committed inputs: exact
dependency versions pinned in a lockfile, the build command documented in-repo, and
ideally a check that rebuilding reproduces the committed bundle. The superseding ADR
records this committed-artifact policy. The bundle should load on demand (for example a
dynamic import on composer focus) so pages do not pay the editor's weight before a user
starts writing.

The repo can add a root development-only package setup for the editor build. The browser
test package under `tests/browser` remains separate unless the implementation plan
chooses to consolidate package management deliberately.

### 3.4 CSS and Accessibility

The rich editor must fit the existing composer visual system in `public/assets/app.css`.
It should not introduce a decorative document editor look. It should feel like the forum's
input surface.

The implementation must respect the existing CSP of `script-src 'self'` and
`style-src 'self'` and the repo rule against inline styles/scripts. All editor CSS must be
committed as static assets served from `'self'`. Do not use an editor theme that injects
runtime `<style>` elements or runtime CSS rules (constructable/adopted stylesheets pass
CSP but are still banned; committed static files only). Audit Milkdown/ProseMirror
plugins, especially tables, gapcursor, and decorations, by running them under the real
CSP header and asserting zero violation reports — not by grepping the DOM for `style`
attributes. CSP blocks parser- and `setAttribute`-written `style=""` and injected
`<style>` elements, but CSSOM property writes (how prosemirror-tables sets column widths)
are CSP-legal and acceptable. Table support is allowed only if it passes this enforcement
audit or can be patched to do so.

Accessibility requirements:

- editor exposes a labelled multiline textbox surface,
- toolbar buttons have labels and pressed states where applicable,
- `@` and `#` pickers use combobox/listbox semantics,
- active option is exposed with `aria-activedescendant`,
- upload and sync status continue using polite live regions,
- source mode is reachable by keyboard,
- no-JS fallback remains a plain labelled textarea.

---

## 4. Server Components

### 4.1 `wysiwyg_composer` Feature Flag

Add `wysiwyg_composer`, default `false` while the feature is being built. Operators can
enable it independently after the WYSIWYG flow has browser, accessibility, and
round-trip evidence.

`rich_composer=false` remains the broad kill switch. If `rich_composer` is false, the
layout does not load composer enhancement scripts even if `wysiwyg_composer` is true.

### 4.2 Suggestion API

Add an authenticated read-only GET endpoint. It must not require a CSRF token and must
not mutate state:

`GET /composer/suggest?trigger=@|#&q=...&context=thread|reply|dm|new_thread|edit&target_id=...`

`target_id` scopes the context: the thread id for `thread`/`reply`, the conversation id
for `dm`, the board id for `new_thread`, and the post id for `edit`. The server must
verify the requester can read the named target (threads/boards via `BoardPolicy` with
membership resolved, DMs via participant membership) before the context influences
ranking. An unreadable or unknown `target_id` silently degrades to context-free ranking,
so a forged id cannot turn result order into a participation oracle.

Response shape:

```json
{
  "ok": true,
  "items": [
    {
      "type": "user",
      "id": 12,
      "label": "Alice Example",
      "token": "@alice",
      "url": "/u/alice",
      "markdown": "@alice",
      "meta": "Moderator"
    }
  ]
}
```

For `trigger=@`, return users only. For `trigger=#`, return grouped board, tag, thread,
and post suggestions. Each item includes a ready-to-insert Markdown string and enough
display metadata for the chip/picker.

The endpoint is:

- authenticated,
- gated by `rich_composer`,
- rate-limited by user with a dedicated policy in `config/config.php`,
- read-gated per result,
- context-gated before ranking: `target_id` never influences ranking unless the requester
  can read it,
- capped to a small response, such as 8 users or 5 results per reference group,
- safe for short queries by returning board/tag/user results through indexed prefix/LIKE
  queries and using full-text search for thread/post results only when the query length
  meets the search service minimum.

The client should debounce suggestion requests. Because thread/post search uses the
existing full-text service and its minimum query length, topic and post suggestions are
whole-word-ish by design and should not promise single-character prefix matching.

### 4.3 Suggestion Service

Add `ComposerSuggestionService` to keep controller logic thin.

Responsibilities:

- user suggestions with visibility and block-aware mention eligibility,
- anonymity-safe ranking: participant and recency signals exclude anonymous posts
  (`posts.is_anonymous`), and post-suggestion author metadata goes through the same
  render-time masking as post display (`mask_author`) — the picker must never
  deanonymize,
- board suggestions using the same read gate as board listings,
- tag suggestions only when `tags` is enabled and tag visibility is public,
- topic/post suggestions by reusing `SearchService` where possible,
- shape all results into a common suggestion DTO.

Private-board content only appears when the current user can read it. Hidden, deleted,
pending, disabled, and inaccessible rows never appear.

### 4.4 Mention Rendering

Mention notifications already work from raw `@username` tokens. This increment should
also render valid mentions as links in displayed post/DM HTML, matching `COMPOSER.md`.

Implementation should walk the rendered HTML DOM and link mentions only in text nodes
outside `code` and `pre`. It should resolve usernames case-insensitively, preserve display
text as typed, and use `/u/{canonical_username}` as the link target. Unknown handles
remain plain text. The sanitizer allowlist already permits safe links.

The render-time linker must share the same mention grammar as `MentionParser`: `@`
followed by a 3-32 character `[A-Za-z0-9_]` handle, not after a word character or second
`@`, and with code/pre content excluded. Displayed mention links and notification fanout
must not diverge.

The linker runs in the write-time render pipeline that caches `body_html` (alongside the
emoji walker) and applies to posts, DM messages, and the composer preview only. Bios and
community-memory summaries share `Markdown::render()`, so the hook must be scoped (a
render option or a separate pass) rather than unconditional in the shared renderer.
Because `body_html` is a write-time cache, existing posts gain mention links only when
next re-rendered (edit, approval); no backfill pass ships with this increment. Links
resolve at write time, so a later username rename leaves a stale link — accepted, as the
raw token would be equally stale as text.

### 4.5 Content References and Tags

`ContentReferenceService` currently extracts `/c/{slug}`, `/t/{id}-{slug}`, and post
anchors from Markdown links. This increment should extend it to `/tags/{slug}`.

Because `content_references.target_type` currently allows only `board`, `thread`, and
`post`, add an additive migration that changes the enum to include `tag`. Rollback deletes
or rewrites tag reference rows before reverting the enum, following existing enum
migration patterns.

Tag reference cards use:

- `type`: `Tag`,
- `title`: tag name,
- `url`: `/tags/{slug}`,
- `meta`: short description or visible topic count.

Tag cards obey the `tags` feature flag and public tag visibility.

---

## 5. Data Flow

### 5.1 Load

1. Server renders the form with the textarea containing canonical Markdown.
2. Current composer JS initializes shared behavior.
3. If WYSIWYG is enabled, the Milkdown adapter parses the textarea Markdown.
4. The textarea remains in the form as the submit source, hidden visually but not removed.

Initial parse must not overwrite the textarea merely because the serializer would
normalize Markdown formatting. For an edit form, opening content and submitting without a
user change should not rewrite `body` solely due to parser/serializer normalization. Once
the user edits through the rich surface, legacy Markdown may be normalized, but the
required guarantee is that the resulting server-rendered sanitized HTML is semantically
equivalent for supported syntax.

### 5.2 Edit

1. User edits in the rich surface.
2. Milkdown serializes the document to Markdown on each debounced change.
3. The bridge writes that Markdown into the textarea.
4. Existing autosave, server drafts, counter, preview, and upload logic read the same
   Markdown through the bridge.
5. Upload completion uses `replacePendingUpload` so a pending upload node or token is
   swapped for final Markdown without relying on rich-editor substring replacement.

### 5.3 Suggest and Insert

1. User types `@` or `#`.
2. Client requests `/composer/suggest`.
3. Server returns read-gated suggestion rows with canonical Markdown.
4. Client inserts a rich chip (rich adapter) or the canonical Markdown directly
   (textarea adapter); either way the textarea ends up holding the serialized Markdown.

### 5.4 Submit

1. Submit forces a final editor-to-Markdown sync.
2. Existing form submit path posts `body`.
3. Server validation, anti-abuse, Markdown rendering, sanitizer, mentions, attachments,
   reference capture, notifications, and redirects run as they do today.

---

## 6. Error Handling

- **Editor bundle fails:** leave the textarea composer active and log nothing sensitive.
- **Markdown parse fails on load:** keep source mode open with the raw Markdown and show a
  non-blocking composer error.
- **Serializer produces unsupported Markdown:** do not submit silently. Switch to source
  mode and preserve text.
- **Suggestion endpoint unavailable:** close the picker and let the typed text remain.
- **Suggestion has become inaccessible:** server-side write/render/capture remains
  authoritative; inaccessible references render as normal links or no reference card.
- **Mention target disappears:** raw `@username` remains text; notification fanout ignores
  nonexistent users as it does today.
- **Tag feature disabled:** tag suggestions are omitted and tag reference cards do not
  render.
- **`content_references` disabled:** inserted links still work, but reference-card capture
  stays off; rendered output is a normal Markdown link rather than a card.
- **Draft conflict:** existing server-draft conflict panel remains the authority. It must
  update the rich editor through the bridge when loading server or local content.

---

## 7. Testing and Evidence

### 7.1 Unit and Integration Tests

- Markdown round-trip fixture corpus:
  - bold, italic, strike, inline code,
  - fenced code,
  - headings clamped to supported levels,
  - blockquote,
  - ordered and unordered lists,
  - tables and task lists,
  - spoilers,
  - uploaded images,
  - GIPHY images when allowed,
  - custom emoji shortcodes,
  - raw `@username` mentions,
  - board, tag, topic, and post reference links.
- `MentionParser` remains unchanged for notification extraction.
- mention rendering links valid users, ignores code/pre blocks, and uses the same grammar
  as notification extraction.
- suggestion endpoint gates private boards/topics/posts.
- suggestion ranking excludes anonymous participation and masks post-suggestion authors.
- an unreadable or forged `target_id` yields the same results as no context.
- `GET /composer/suggest` is absent (404) when `rich_composer` is off, in the
  `AppFeatureFlagTest` pattern.
- suggestion endpoint returns boards, tags, topics, and posts for `#`.
- `ContentReferenceService` extracts and resolves `/tags/{slug}`.
- tag reference card rendering respects feature flags and visibility.
- `rich_composer=false` keeps the textarea fallback and loads no WYSIWYG assets.
- `wysiwyg_composer=false` keeps the layout free of the editor bundle while the
  Markdown-enhanced composer still loads.
- parser/serializer tests compare server-rendered sanitized HTML for semantic parity, not
  byte-identical Markdown except for fixtures intentionally authored in editor output
  form.

### 7.2 Browser Tests

Use desktop and mobile Playwright coverage for:

- new topic WYSIWYG compose and submit,
- reply compose and submit,
- DM compose and submit,
- edit existing post,
- source-mode round trip,
- server preview parity,
- local draft restore,
- server draft conflict load-local/load-server behavior,
- image paste/drop and alt text,
- pending upload placeholder replacement,
- `@` user picker keyboard flow,
- `#` picker with board, tag, topic, and post selections,
- picker flow on the textarea adapter with `wysiwyg_composer` off,
- `#` reference trigger does not steal heading shortcuts,
- pasted internal URL becoming a chip,
- no-JS or kill-switch textarea fallback,
- strict-CSP smoke coverage with no inline style/script console violations,
- axe checks for editor toolbar and suggestion picker.

### 7.3 Acceptance Criteria

- A no-op edit session does not rewrite `body` solely due to editor serializer
  normalization.
- Supported Markdown fixtures preserve server-rendered sanitized HTML after
  parse/serialize; byte-stable Markdown is required only for fixtures authored in the
  editor's own canonical output form.
- Users can create posts without knowing Markdown syntax.
- `@` mention chips notify the same recipients as raw `@username` text.
- `#` can search and insert boards, tags, topics, and posts.
- Inserted references remain useful when WYSIWYG is disabled because they are stored as
  Markdown links.
- The server preview and final rendered post match for supported syntax.
- Operators can roll back to the current Markdown-enhanced composer without data
  migration.
- Suggestion ranking and metadata never reveal anonymous participation.
- The editor runs under the existing strict CSP without adding `'unsafe-inline'`.

---

## 8. Non-Goals

- No editor-specific JSON/HTML canonical storage.
- No collaborative editing.
- No Notion-style block database.
- No private tag visibility model beyond the current public/hidden tag behavior.
- No changing write-route validation or idempotency semantics.
- No making `content_references` default-on as part of this design.
- No full API search product beyond the composer suggestion endpoint.

---

## 9. Implementation Plan Handoff Notes

The implementation plan should likely split work into these increments:

1. ADR superseding or amending ADR 0001. It should also restate the actual
   supported-syntax set (the server renderer already enables tables and task lists,
   which ADR 0001's list omits) and record the committed-bundle policy.
2. CSP-compatibility spike for Milkdown/ProseMirror theme, table, gapcursor, and
   decoration behavior, asserting zero violation reports under the real
   `style-src 'self'` header.
3. Serializer-fidelity spike against real stored-post Markdown to define the semantic
   round-trip corpus and legacy-edit behavior.
4. Suggestion API and tag reference backend support.
5. Mention link rendering.
6. Composer bridge extraction around the existing textarea behavior.
7. Suggestion pickers on the bridge, serving the textarea adapter first.
8. Milkdown bundle and adapter behind `wysiwyg_composer`.
9. Reference and mention chips in the rich adapter.
10. Browser/a11y evidence and rollout flag documentation.

Do not start with the editor bundle before the backend suggestion/reference contract is
tested. The editor should consume a stable Markdown insertion contract instead of baking
database and routing assumptions into client code.

Follow-on documentation updates should be planned alongside implementation:

- `COMPOSER.md`, especially the reference roadmap sections.
- `SCHEMA.md`, including the tag reference enum shape, changelog entry, and version bump
  after the migration.
- a runbook for `wysiwyg_composer`, matching the existing feature-flag runbook style.
- migration numbering using the current tree, where the tag enum migration should follow
  the latest existing migration rather than the stale "next migration 0049" note.

---

# Part II — Design: Graduate `wysiwyg_composer` to default-ON

**Date:** 2026-07-02
**Owner request:** "I don't want Milkdown to be optional." Scope confirmed with Henry: **graduate the flag to default-true; the flag itself stays** (operator rollback preserved). Deeper variants (deleting the flag, removing source mode) were offered and not chosen.
**Pattern:** the established graduation ritual (`polls` 2026-06-30, `topic_workflow` 2026-07-01; see `docs/evidence/deploy-dark-features.md` "graduation pattern").

## Context

PR #33 shipped the Milkdown WYSIWYG layer deploy-dark behind `wysiwyg_composer` (ADR 0013), *with its acceptance evidence already landed*: `wysiwyg-composer.spec.ts` (CSP, submit, source-mode round trip, no-op edit, preview parity, chips, URL paste, mobile smoke, fallback), a11y scans, `AppComposerTest`/`AppComposerSuggestTest`/`MarkdownRoundTripTest`, and `npm run check:wysiwyg`. The "deploy-dark until evidence lands" condition is therefore already satisfied; graduation is a posture flip plus the documented ritual.

`rich_composer` is already default-ON, so flipping `wysiwyg_composer` alone makes Milkdown the out-of-the-box editor.

## What changes

### 1. Flag default — `src/Core/FeatureFlags.php`

`'wysiwyg_composer' => true` with the GA comment form: `// Milkdown WYSIWYG layer over canonical Markdown textarea — GA default-on (2026-07-02; reversible via features override)`.

No other code changes. `templates/layout.php` gating, the adapter, and the composer bridge are already flag-driven.

### 2. PHPUnit — flip the two tests that assume dark

- `tests/Integration/Core/AppFeatureFlagTest.php`: replace `test_wysiwyg_composer_defaults_dark_and_is_independently_reversible` with `test_wysiwyg_composer_is_available_by_default_and_can_be_disabled`, mirroring the `topic_workflow` graduated test. The flag is an asset/attribute layer (not route-gated), so the observable assertions are HTTP-body ones: a board page contains `data-wysiwyg-composer="1"` and the bundle tags by default; neighbour isolation (`group_dms` stays false); `wysiwyg_composer=false` override removes them; the `rich_composer=false` kill-switch interplay assertion is retained.
- `tests/Integration/Core/AppComposerTest.php` (`test_wysiwyg_flag_only_loads_editor_assets_when_rich_composer_is_enabled`): the default-state expectation inverts — default page now contains `/assets/wysiwyg-composer.js`/`.css`; explicit `wysiwyg_composer=false` omits them; `rich_composer=false` still omits them.

TDD order: rewrite tests first, observe red, flip the default, observe green.

### 3. Browser evidence

- `tests/browser/seed.php` `$evidenceFeatures`: add **`'wysiwyg_composer' => false`** with a comment. Rationale: `gate-a.spec.ts` (and the drafts journey) drive `textarea.composer-input` directly — six interactions including a `toBeVisible()` — which a mounted Milkdown hides (`is-wysiwyg-source-hidden`). Gate-a therefore continues to capture the progressive-enhancement textarea baseline; the rich surface's browser evidence lives in the dedicated `wysiwyg-composer.spec.ts` + a11y scans, which set the flag explicitly per test (`workers: 1`, no races). This deliberately deviates from the ritual's "seed `=> true`" step; the deviation is the documentation.
- `tests/browser/wysiwyg-composer.spec.ts`: extend the flag helper with an *unset* mode (remove the key from the features override) and use it in one mount test, proving in a real browser that Milkdown mounts under the **true default**, not a forced override.

### 4. Docs

- `docs/runbooks/wysiwyg_composer.md`: graduated banner ("Default-ON as of 2026-07-02"; golden rule: for any editor defect, disable `wysiwyg_composer` first — non-destructive, textarea composer keeps serving; `rich_composer=false` stays the broad emergency switch), mirroring `topic_workflow.md`.
- `docs/evidence/deploy-dark-features.md`: bump date; rewrite the `wysiwyg_composer` row to the "**Graduated 2026-07-02 — now default-ON**" form; add the Notes bullet.
- `PHASE_5_STATUS.md`: record the graduation (the WYSIWYG stream landed via PR #33 alongside Inc 4).
- `CLAUDE.md` flags paragraph: note the `wysiwyg_composer` graduation with runbook pointer.
- `COMPOSER.md`: the §"priority vs phase" note ("deploy-dark behind `wysiwyg_composer` per ADR 0013") becomes "default-ON as of 2026-07-02"; changelog gains v0.6.
- `DESIGN.md`/`DECISIONS.md`: audit for stale "deploy-dark wysiwyg" posture claims; update only if they assert the default.
- ADR 0013 is **not** edited: its consequences ("operators can roll back by setting `wysiwyg_composer=false`") remain true.

## Non-goals / deferred (recorded, not dropped)

- **Full evidence-set regeneration.** ~100 evidence PNGs are already dirty in the working tree from a concurrent admin/anti-abuse workstream; regenerating now would interleave churn. Also gate-a composer flows would need wysiwyg-aware interaction patterns before capturing Milkdown-on screenshots. Follow-up: after the admin workstream lands, decide whether gate-a should capture Milkdown-by-default (rewriting its composer interactions) or keep the textarea-baseline pin.
- Removing the flag, the source-mode toggle, or the textarea (the textarea is the submit source and no-JS baseline; removing it is a DECISIONS-level change nobody asked for).
- The four theme-related fail-dark follow-ups from the PR #33 review (unrelated).

## Risks

- Tests that silently assumed the flag dark → caught by the full-suite gate (the ritual's step 6).
- Future full evidence runs will show Milkdown in composer screenshots unless the gate-a pin is kept — intentional, documented in the seed comment.
- No schema, migrations, counters, CSP, or route changes.

## Verification gates

1. `composer test` full suite green.
2. `npm run check:wysiwyg` (deterministic bundle unchanged).
3. `cd tests/browser && npm run prepare-db && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"` green, including the new unset-mode default proof and the existing `wysiwyg_composer=false` fallback test.

## Process note

Henry was away when the sequencing question timed out; proceeding with the recommended "alongside" option: this branch stages **only graduation files**, the admin workstream's uncommitted edits are never touched, and full PNG regeneration is deferred. Gates auto-approved in his absence are listed here for review.

---

# Part III — Implementation Plan: WYSIWYG Composer

> **For agentic workers (applies to Part III and Part IV):** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement these plans task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the approved WYSIWYG composer direction behind `wysiwyg_composer`, with Markdown as canonical storage and Slack-style `@` and `#` references.

**Architecture:** Keep the server-rendered textarea as the submit source and fallback. Add backend suggestion/reference/mention contracts first, then extract a client-side composer bridge, then add Milkdown as an optional adapter that serializes back to the hidden textarea. The editor bundle is committed static output and is never required for the no-JS or kill-switch paths.

**Tech Stack:** PHP 8, MySQL, League CommonMark, vanilla server-rendered templates, vanilla enhanced composer JS, Milkdown `7.21.2`, Vite `8.1.3`, TypeScript `6.0.3`, PHPUnit, Playwright, axe.

## Scope Check

The approved design spans several subsystems: ADR/docs, backend suggestion APIs, mention rendering, content-reference schema, client bridge extraction, WYSIWYG bundling, browser evidence, and operations documentation. Keep this as one dependency-ordered program plan because the design explicitly requires the backend Markdown insertion contract before the editor bundle. Each task below is independently testable and should be committed before moving to the next task.

Do not start Tasks 7-9 before Tasks 1-6 are green. If Milkdown fails the CSP or mobile spike in Task 7, stop and record the fallback decision in the ADR instead of building a bespoke editor.

## File Structure

Create:
- `docs/adr/0013-wysiwyg-composer.md` - supersedes/amends ADR 0001 and records Milkdown-first, committed-bundle, CSP, fallback, and semantic round-trip policy.
- `database/migrations/0071_content_reference_tags.php` - widens `content_references.target_type` to include `tag`.
- `src/Service/ComposerSuggestion.php` - small immutable DTO for suggestion rows.
- `src/Service/ComposerSuggestionService.php` - read-gated, anonymity-safe suggestion service for `@` and `#`.
- `src/Support/MentionLinker.php` - DOM-based mention-link pass scoped to post, DM, and preview rendering.
- `src/client/wysiwyg/milkdown-adapter.ts` - Milkdown adapter and bridge implementation.
- `src/client/wysiwyg/index.ts` - lazy WYSIWYG entry point.
- `src/client/wysiwyg/styles.css` - committed static editor CSS source.
- `vite.config.mjs` - editor bundle build config.
- `package.json` and `package-lock.json` - root development-only editor build dependencies.
- `public/assets/wysiwyg-composer.js` - committed built WYSIWYG bundle.
- `public/assets/wysiwyg-composer.css` - committed built WYSIWYG CSS.
- `tests/Integration/Core/AppComposerSuggestTest.php` - suggestion endpoint, visibility, ranking, and anonymity tests.
- `tests/Integration/Core/AppMentionLinkRenderTest.php` - mention-link render tests.
- `tests/Unit/Composer/ComposerSuggestionServiceTest.php` - service-level ranking and response-shaping tests when no HTTP kernel is needed.
- `tests/browser/wysiwyg-composer.spec.ts` - WYSIWYG, source-mode, picker, paste, mobile, and CSP smoke coverage.
- `docs/runbooks/wysiwyg_composer.md` - rollout and rollback runbook.

Modify:
- `src/Core/FeatureFlags.php` - add `wysiwyg_composer` default false.
- `config/config.php` - add `composer_suggest` rate-limit policy.
- `src/Core/App.php` - bind suggestion service, add route, add script/style gating.
- `src/Controller/ComposerController.php` - add `suggest()` and render preview with mention links.
- `src/Support/Markdown.php` - add render options and scoped mention-link pass.
- `src/Service/PostingService.php` - render post HTML with mention links.
- `src/Service/DirectMessageService.php` - render DM HTML with mention links.
- `src/Service/ContentReferenceService.php` - extract, resolve, and card-render tag references.
- `src/Repository/UserRepository.php` - add active username prefix lookup for suggestions and mention linker.
- `src/Repository/BoardRepository.php` - add prefix lookup for readable board suggestions.
- `src/Repository/TagRepository.php` - add prefix lookup and visible topic count for tag suggestions/cards.
- `src/Repository/PostRepository.php` - add context helpers for non-anonymous participants and post suggestion metadata.
- `src/Repository/ThreadRepository.php` - add visible topic lookup helpers when `SearchService` cannot satisfy short queries.
- `templates/layout.php` - stamp `data-wysiwyg-composer`, load static WYSIWYG CSS and lazy entry only when both flags permit.
- `templates/partials/composer.php`, `templates/partials/new_thread_form.php`, `templates/compose.php`, `templates/dm/new.php`, `templates/dm/show.php`, `templates/partials/post.php` - add data context attributes and normalize composer form classes where needed.
- `public/assets/composer.js` - extract bridge adapters, make shared behavior adapter-based, add textarea pickers, lazy-load WYSIWYG adapter.
- `public/assets/app.css` - static styles for pickers, chips, source toggle, and WYSIWYG surface.
- `SCHEMA.md`, `COMPOSER.md`, `docs/evidence/deploy-dark-features.md` - document schema, composer behavior, rollout state.
- `tests/Integration/Core/AppFeatureFlagTest.php`, `tests/Integration/Core/AppComposerTest.php`, `tests/Integration/Core/AppContentReferenceTest.php`, `tests/Unit/Core/MigrationLedgerTest.php` - expand existing tests.

## Implementation Tasks

### Task 1: ADR 0013 and `wysiwyg_composer` Flag

**Files:**
- Create: `docs/adr/0013-wysiwyg-composer.md`
- Modify: `src/Core/FeatureFlags.php`
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`
- Modify: `templates/layout.php`

- [ ] **Step 1: Write failing feature-flag tests**

Add these assertions to `tests/Integration/Core/AppFeatureFlagTest.php`:

```php
public function test_wysiwyg_composer_defaults_dark_and_is_independently_reversible(): void
{
    $flags = new FeatureFlags(new SettingRepository($this->db));
    self::assertArrayHasKey('wysiwyg_composer', $flags->all());
    self::assertFalse($flags->enabled('wysiwyg_composer'));
    self::assertTrue($flags->enabled('rich_composer'));

    $this->setFlags(['wysiwyg_composer' => true]);
    $enabled = new FeatureFlags(new SettingRepository($this->db));
    self::assertTrue($enabled->enabled('wysiwyg_composer'));
    self::assertTrue($enabled->enabled('rich_composer'));

    $this->setFlags(['rich_composer' => false, 'wysiwyg_composer' => true]);
    $rolledBack = new FeatureFlags(new SettingRepository($this->db));
    self::assertFalse($rolledBack->enabled('rich_composer'));
    self::assertTrue($rolledBack->enabled('wysiwyg_composer'), 'the narrow flag may be true while the broad kill switch keeps assets dark');
}
```

Add this test to `tests/Integration/Core/AppComposerTest.php`:

```php
public function test_wysiwyg_flag_only_loads_editor_assets_when_rich_composer_is_enabled(): void
{
    $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-assets']);
    $user = $this->makeUser(['username' => 'wysiwygassets']);
    $this->actingAs($user);

    $defaultPage = $this->get('/c/wysiwyg-assets');
    self::assertStringContainsString('/assets/composer.js', $defaultPage->body());
    self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $defaultPage->body());

    (new SettingRepository($this->db))->set('features', ['wysiwyg_composer' => true]);
    $enabledPage = $this->get('/c/wysiwyg-assets');
    self::assertStringContainsString('/assets/composer.js', $enabledPage->body());
    self::assertStringContainsString('/assets/wysiwyg-composer.css', $enabledPage->body());
    self::assertStringContainsString('data-wysiwyg-composer="1"', $enabledPage->body());

    (new SettingRepository($this->db))->set('features', ['rich_composer' => false, 'wysiwyg_composer' => true]);
    $killedPage = $this->get('/c/wysiwyg-assets');
    self::assertStringNotContainsString('/assets/composer.js', $killedPage->body());
    self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $killedPage->body());
    self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
}
```

- [ ] **Step 2: Run focused tests and verify failure**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter wysiwyg
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php --filter wysiwyg
```

Expected: failures because `wysiwyg_composer` is absent and layout does not stamp/load editor assets.

- [ ] **Step 3: Add the flag and layout gate**

In `src/Core/FeatureFlags.php`, add the flag immediately after `rich_composer`:

```php
'rich_composer' => true,     // shared composer toolbar + server preview (P3-02); textarea always works
'wysiwyg_composer' => false, // Milkdown WYSIWYG layer over canonical Markdown textarea; deploy-dark until evidence lands
'drafts' => true,
```

In `templates/layout.php`, compute and stamp the narrow flag only when the broad kill switch is on:

```php
<?php
$richComposerOn = !empty($features['rich_composer']);
$wysiwygComposerOn = $richComposerOn && !empty($features['wysiwyg_composer']);
?>
```

Change the `<body>` tag to include:

```php
<?= $wysiwygComposerOn ? ' data-wysiwyg-composer="1"' : '' ?>
```

Add the static CSS in `<head>` after `app.css`:

```php
<?php if ($wysiwygComposerOn): ?><link rel="stylesheet" href="/assets/wysiwyg-composer.css"><?php endif; ?>
```

Add the editor bundle after `composer.js`:

```php
<?php if ($richComposerOn): ?><script src="/assets/composer.js" defer></script><?php endif; ?>
<?php if ($wysiwygComposerOn): ?><script src="/assets/wysiwyg-composer.js" defer></script><?php endif; ?>
```

Create temporary asset stubs so the integration test can assert paths before the real build lands. Add `public/assets/wysiwyg-composer.css` with:

```css
/* WYSIWYG composer CSS is built in Task 7. */
```

Add `public/assets/wysiwyg-composer.js` with:

```js
/* WYSIWYG composer bundle is built in Task 7. */
```

- [ ] **Step 4: Draft ADR 0013**

Create `docs/adr/0013-wysiwyg-composer.md`:

```markdown
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

## Consequences

- Operators can roll back to the current Markdown-enhanced composer by setting `wysiwyg_composer=false`.
- Operators can disable all enhanced composer JS by setting `rich_composer=false`.
- Suggestion pickers and canonical Markdown insertion are bridge-level behavior shared by textarea and Milkdown adapters.
- Mention links are baked into the cached `body_html` at write time (as a pass that runs *after* sanitisation) and are gated by the `mentions` flag; a later username change or deactivation leaves the previously cached link text/target unchanged, consistent with the rest of the `body_html` cache.
- If Milkdown cannot pass CSP, mobile, accessibility, and semantic round-trip evidence, the implementation stops at the Markdown-enhanced path and revisits CodeMirror/ink-mde rather than shipping a bespoke editor.
```

- [ ] **Step 5: Run tests and commit**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter wysiwyg
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php --filter wysiwyg
```

Expected: PASS.

Commit:

```bash
git add docs/adr/0013-wysiwyg-composer.md src/Core/FeatureFlags.php templates/layout.php public/assets/wysiwyg-composer.css public/assets/wysiwyg-composer.js tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "docs: accept wysiwyg composer ADR"
```

### Task 2: Tag Content References

**Files:**
- Create: `database/migrations/0071_content_reference_tags.php`
- Modify: `src/Service/ContentReferenceService.php`
- Modify: `src/Repository/TagRepository.php`
- Modify: `src/Core/App.php`
- Modify: `tests/Integration/Core/AppContentReferenceTest.php`
- Modify: `tests/Unit/Core/MigrationLedgerTest.php`
- Modify: `SCHEMA.md`

- [ ] **Step 1: Write failing tests for tag references**

Add to `tests/Integration/Core/AppContentReferenceTest.php`:

```php
public function test_tag_references_are_persisted_and_rendered_when_flags_allow(): void
{
    $this->makeAdmin();
    $this->setFlags(['content_references' => true, 'tags' => true]);
    $author = $this->makeUser(['username' => 'tagrefauthor']);
    $board = $this->makeBoard($this->makeCategory('Tag References'), ['slug' => 'tag-ref-board']);
    $tagId = (new \App\Repository\TagRepository($this->db))->create('release-notes', 'Release Notes', 'Shipping notes', (int) $author['id']);

    $this->actingAs($author);
    $this->assertRedirect($this->post('/threads', [
        'board_id' => (int) $board['id'],
        'title' => 'Tag source',
        'body' => 'See [#release-notes](/tags/release-notes).',
    ]));
    $threadId = (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Tag source' LIMIT 1");
    $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);

    self::assertSame(1, (int) $this->db->fetchValue(
        "SELECT COUNT(*) FROM content_references WHERE source_type = 'post' AND source_id = ? AND target_type = 'tag' AND target_id = ?",
        [$postId, $tagId],
    ));

    $page = $this->get('/t/' . $threadId);
    $this->assertStatus(200, $page);
    self::assertStringContainsString('Release Notes', $page->body());
    self::assertStringContainsString('Shipping notes', $page->body());
}

public function test_tag_reference_cards_stay_dark_when_tags_flag_is_disabled(): void
{
    $this->makeAdmin();
    $this->setFlags(['content_references' => true, 'tags' => false]);
    $author = $this->makeUser(['username' => 'tagrefdark']);
    $board = $this->makeBoard($this->makeCategory('Tag Dark'), ['slug' => 'tag-dark-board']);
    (new \App\Repository\TagRepository($this->db))->create('hidden-card', 'Hidden Card', 'Hidden description', (int) $author['id']);

    $this->actingAs($author);
    $this->assertRedirect($this->post('/threads', [
        'board_id' => (int) $board['id'],
        'title' => 'Tag dark source',
        'body' => 'See [#hidden-card](/tags/hidden-card).',
    ]));

    $page = $this->get('/t/' . (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Tag dark source' LIMIT 1"));
    $this->assertStatus(200, $page);
    self::assertStringNotContainsString('Hidden description', $page->body());
}
```

- [ ] **Step 2: Run failing tests**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppContentReferenceTest.php --filter tag_reference
```

Expected: migration/schema error or zero rows because `target_type='tag'` is not allowed and extraction ignores `/tags/{slug}`.

- [ ] **Step 3: Add migration `0071_content_reference_tags.php`**

Create:

```php
<?php

declare(strict_types=1);

/**
 * 0071 - Allow content reference cards for public tags.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE content_references
              MODIFY target_type ENUM('board','thread','post','tag') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM content_references WHERE target_type = 'tag'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE content_references
              MODIFY target_type ENUM('board','thread','post') NOT NULL
        SQL);
    }
};
```

- [ ] **Step 4: Add tag repository helpers**

In `src/Repository/TagRepository.php`, add:

```php
/** @return array<string,mixed>|null */
public function visiblePublicBySlug(string $slug): ?array
{
    return $this->db->fetch(
        "SELECT * FROM tags WHERE slug = ? AND is_enabled = 1 AND visibility = 'public'",
        [$slug],
    );
}

public function publicThreadCount(int $tagId): int
{
    return (int) $this->db->fetchValue(
        "SELECT COUNT(*)
         FROM thread_tags tt
         JOIN threads t ON t.id = tt.thread_id AND t.is_deleted = 0 AND t.is_pending = 0
         JOIN boards b ON b.id = t.board_id AND b.tags_enabled = 1 AND b.visibility = 'public'
         WHERE tt.tag_id = ?",
        [$tagId],
    );
}
```

- [ ] **Step 5: Extend `ContentReferenceService`**

Add `TagRepository` to the constructor and binding in `src/Core/App.php`:

```php
private TagRepository $tags,
```

Extend extraction:

```php
if (preg_match_all('~(?:https?://[^\s)\]]+)?/tags/([A-Za-z0-9-]+)~', $body, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
        $refs[] = ['target_type' => 'tag', 'token' => (string) $m[1]];
    }
}
```

Extend resolve:

```php
'tag' => ($row = $this->tags->visiblePublicBySlug($token)) !== null ? (int) $row['id'] : null,
```

Extend card dispatch:

```php
'tag' => $this->tagCard((int) $row['target_id']),
```

Add:

```php
/** @return array<string,mixed>|null */
private function tagCard(int $tagId): ?array
{
    $tag = $this->tags->find($tagId);
    if ($tag === null || (int) ($tag['is_enabled'] ?? 0) !== 1 || (string) ($tag['visibility'] ?? '') !== 'public') {
        return null;
    }
    $description = trim((string) ($tag['description'] ?? ''));
    $count = $this->tags->publicThreadCount($tagId);
    return [
        'type' => 'Tag',
        'title' => (string) $tag['name'],
        'url' => '/tags/' . (string) $tag['slug'],
        'meta' => $description !== '' ? $description : ($count . ' visible topic' . ($count === 1 ? '' : 's')),
    ];
}
```

In the `ContentReferenceService` container binding, pass `TagRepository`.

- [ ] **Step 6: Gate tag cards by `tags` flag**

In `src/Core/App.php`, only inject `ContentReferenceService` into `PostingService`, `DirectMessageService`, and `CommunityMemoryService` when `content_references` is enabled. Tag card rendering itself must receive `FeatureFlags` or a boolean `tagsEnabled`. Prefer constructor injection:

```php
private bool $tagsEnabled,
```

In `tagCard()`, start with:

```php
if (!$this->tagsEnabled) {
    return null;
}
```

Bind with:

```php
$c->get(FeatureFlags::class)->enabled('tags'),
```

- [ ] **Step 7: Update schema docs and run tests**

Update `SCHEMA.md` content-reference shape from `ENUM('board','thread','post')` to `ENUM('board','thread','post','tag')`, add migration `0071` to the migration ledger section, and add a changelog row:

```markdown
| v1.30 | 2026-07-02 | Added migration `0071_content_reference_tags`: widened `content_references.target_type` with `tag` so composer-inserted `/tags/{slug}` links can resolve to read-gated tag cards while `content_references` and `tags` are enabled. |
```

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppContentReferenceTest.php --filter tag_reference
./vendor/bin/phpunit tests/Unit/Core/MigrationLedgerTest.php
```

Expected: PASS.

Commit:

```bash
git add database/migrations/0071_content_reference_tags.php src/Core/App.php src/Repository/TagRepository.php src/Service/ContentReferenceService.php tests/Integration/Core/AppContentReferenceTest.php tests/Unit/Core/MigrationLedgerTest.php SCHEMA.md
git commit -m "feat: support tag content references"
```

### Task 3: Mention Link Rendering

**Files:**
- Create: `src/Support/MentionLinker.php`
- Modify: `src/Support/Markdown.php`
- Modify: `src/Service/PostingService.php`
- Modify: `src/Service/DirectMessageService.php`
- Modify: `src/Controller/ComposerController.php`
- Modify: `src/Core/App.php`
- Modify: `src/Repository/UserRepository.php`
- Create: `tests/Integration/Core/AppMentionLinkRenderTest.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`

- [ ] **Step 1: Write failing mention-link tests**

Create `tests/Integration/Core/AppMentionLinkRenderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppMentionLinkRenderTest extends TestCase
{
    public function test_post_render_links_valid_mentions_outside_code_only(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'mention-links']);
        $author = $this->makeUser(['username' => 'mentionauthor']);
        $this->makeUser(['username' => 'Alice']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Mention links',
            'body' => 'Hello @alice, ignore `@alice` and name@example.com.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('<a href="/u/Alice" class="mention">@alice</a>', $html);
        self::assertStringContainsString('<code>@alice</code>', $html);
        self::assertStringContainsString('name@example.com', $html);
    }

    public function test_unknown_mentions_remain_plain_text(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'unknown-mention']);
        $author = $this->makeUser(['username' => 'unknownauthor']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Unknown mention',
            'body' => 'Hello @nobodyhere.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('@nobodyhere', $html);
        self::assertStringNotContainsString('class="mention"', $html);
    }

    public function test_dm_and_preview_render_mentions(): void
    {
        $sender = $this->makeUser(['username' => 'dmmentioner']);
        $recipient = $this->makeUser(['username' => 'dmrecipient']);
        $this->actingAs($sender);

        $this->assertRedirect($this->post('/messages', ['to' => 'dmrecipient', 'body' => 'Hi @dmrecipient']));
        $dmHtml = (string) $this->db->fetchValue('SELECT body_html FROM dm_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $sender['id']]);
        self::assertStringContainsString('<a href="/u/dmrecipient" class="mention">@dmrecipient</a>', $dmHtml);

        $preview = $this->post('/composer/preview', ['body' => 'Preview @dmrecipient']);
        $this->assertStatus(200, $preview);
        // Response::json() uses JSON_UNESCAPED_SLASHES, so slashes stay literal
        // while double quotes are escaped (\"). The anchor therefore appears in
        // the JSON body with plain slashes and escaped quotes.
        self::assertStringContainsString('<a href=\"/u/dmrecipient\" class=\"mention\">@dmrecipient</a>', $preview->body());
    }

    public function test_mentions_are_not_linked_when_mentions_flag_is_disabled(): void
    {
        (new SettingRepository($this->db))->set('features', ['mentions' => false]);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'mentions-off']);
        $author = $this->makeUser(['username' => 'mentionsoffauthor']);
        $this->makeUser(['username' => 'Carol']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Mentions off',
            'body' => 'Hello @carol.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('@carol', $html);
        self::assertStringNotContainsString('class="mention"', $html);
    }
}
```

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppMentionLinkRenderTest.php
```

Expected: fails because rendered HTML contains plain `@username`.

- [ ] **Step 3: Add case-insensitive username lookup**

In `src/Repository/UserRepository.php`, add:

```php
/**
 * @param list<string> $usernames
 * @return array<string,array{id:int,username:string}> lower(username) => row
 */
public function activeMentionTargets(array $usernames): array
{
    $usernames = array_values(array_unique(array_filter($usernames, static fn ($u): bool => is_string($u) && $u !== '')));
    if ($usernames === []) {
        return [];
    }
    $place = implode(',', array_fill(0, count($usernames), '?'));
    $rows = $this->db->fetchAll(
        "SELECT id, username FROM users WHERE LOWER(username) IN ($place) AND status = 'active'",
        array_map('strtolower', $usernames),
    );
    $out = [];
    foreach ($rows as $row) {
        $out[strtolower((string) $row['username'])] = ['id' => (int) $row['id'], 'username' => (string) $row['username']];
    }
    return $out;
}
```

- [ ] **Step 4: Add `MentionLinker`**

`MentionLinker` runs as a post-sanitiser pass and must keep its handle grammar
identical to `App\Support\MentionParser` (the `(?<![\w@])@([A-Za-z0-9_]{3,32})\b`
token, plus `code`/`pre`/`a` exclusions) so a name that notifies also links, and
vice versa. If you change one grammar, change both and cover the parity in
`tests/Unit/MentionParserTest.php`.

Create `src/Support/MentionLinker.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\UserRepository;

final class MentionLinker
{
    // $enabled is bound from FeatureFlags::enabled('mentions') so the @mention
    // surface (notifications *and* rendered links) toggles as a unit. link() is
    // always invoked on already-sanitised HTML (see Markdown::render): the
    // sanitizer strips every <a> attribute except href, so a class="mention"
    // added before sanitisation would not survive.
    public function __construct(private UserRepository $users, private bool $enabled = true)
    {
    }

    public function link(string $html): string
    {
        if (!$this->enabled || $html === '' || !str_contains($html, '@')) {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if (!$ok) {
            return $html;
        }

        $handles = [];
        $textNodes = [];
        $walk = function (\DOMNode $node) use (&$walk, &$handles, &$textNodes): void {
            if ($node instanceof \DOMText) {
                if ($this->insideExcludedNode($node)) {
                    return;
                }
                $text = $node->nodeValue ?? '';
                if (preg_match_all('/(?<![\w@])@([A-Za-z0-9_]{3,32})\b/', $text, $m)) {
                    foreach ($m[1] as $handle) {
                        $handles[] = $handle;
                    }
                    $textNodes[] = $node;
                }
                return;
            }
            foreach (iterator_to_array($node->childNodes) as $child) {
                $walk($child);
            }
        };
        $walk($doc);

        $targets = $this->users->activeMentionTargets($handles);
        if ($targets === []) {
            return $html;
        }

        foreach ($textNodes as $textNode) {
            $this->replaceTextNode($doc, $textNode, $targets);
        }

        $out = $doc->saveHTML();
        if (!is_string($out)) {
            return $html;
        }
        return preg_replace('/^<\?xml encoding="UTF-8"\?>\s*/', '', $out) ?? $out;
    }

    private function insideExcludedNode(\DOMNode $node): bool
    {
        $parent = $node->parentNode;
        while ($parent !== null) {
            $name = strtolower($parent->nodeName);
            if ($name === 'code' || $name === 'pre' || $name === 'a') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /** @param array<string,array{id:int,username:string}> $targets */
    private function replaceTextNode(\DOMDocument $doc, \DOMText $textNode, array $targets): void
    {
        $text = $textNode->nodeValue ?? '';
        $parts = preg_split('/((?<![\w@])@[A-Za-z0-9_]{3,32}\b)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || count($parts) === 1) {
            return;
        }
        $frag = $doc->createDocumentFragment();
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '@') {
                $handle = substr($part, 1);
                $target = $targets[strtolower($handle)] ?? null;
                if ($target !== null) {
                    $a = $doc->createElement('a');
                    $a->setAttribute('href', '/u/' . $target['username']);
                    $a->setAttribute('class', 'mention');
                    $a->appendChild($doc->createTextNode($part));
                    $frag->appendChild($a);
                    continue;
                }
            }
            $frag->appendChild($doc->createTextNode($part));
        }
        $textNode->parentNode?->replaceChild($frag, $textNode);
    }
}
```

- [ ] **Step 5: Scope mention linking through Markdown render options**

Change `src/Support/Markdown.php` constructor and render signature. The mention
pass MUST run **after** `HtmlSanitizer::sanitize()`. The sanitizer removes every
attribute from `<a>` except `href` (and injects `rel="nofollow ugc noopener
noreferrer"`), so a `class="mention"` added *before* sanitisation is silently
stripped — which is why the earlier draft's assertions could never pass.
`MentionLinker` only injects same-origin `/u/{username}` anchors resolved from an
active-user allowlist, so appending them after the allowlist pass is safe and
needs no re-sanitising.

```php
public function __construct(
    private HtmlSanitizer $sanitizer,
    private ?CustomEmojiService $customEmoji = null,
    private ?MentionLinker $mentionLinker = null,
) {
}

/** @param array{link_mentions?:bool} $options */
public function render(string $markdown, array $options = []): string
{
    if (trim($markdown) === '') {
        return '';
    }
    $html = $this->converter->convert($markdown)->getContent();
    $html = $this->renderEmojiShortcodes($html);
    $html = $this->sanitizer->sanitize($html);
    // Post-sanitiser pass: preserves class="mention" (which the sanitizer would
    // otherwise strip) and only ever adds same-origin /u/{username} anchors.
    if (!empty($options['link_mentions'])) {
        $html = $this->mentionLinker?->link($html) ?? $html;
    }
    return $html;
}
```

Update `src/Core/App.php` binding. `MentionLinker` receives the `mentions`
feature-flag state so rendered links follow the same on/off switch as mention
notifications (an operator who disables `mentions` gets neither):

```php
$c->bind(MentionLinker::class, fn (Container $c) => new MentionLinker(
    $c->get(UserRepository::class),
    $c->get(FeatureFlags::class)->enabled('mentions'),
));
$c->bind(Markdown::class, fn (Container $c) => new Markdown(
    $c->get(HtmlSanitizer::class),
    $c->get(FeatureFlags::class)->enabled('custom_emoji') ? $c->get(CustomEmojiService::class) : null,
    $c->get(MentionLinker::class),
));
```

- [ ] **Step 6: Render posts, DMs, and preview with mention links**

In `PostingService`, replace each write-time render call:

```php
'body_html' => $this->markdown->render($body, ['link_mentions' => true]),
```

and:

```php
$this->posts->update($postId, $body, $this->markdown->render($body, ['link_mentions' => true]), $user->id());
```

In `DirectMessageService::deliver()`:

```php
$messageId = $this->messages->create($conversationId, $sender->id(), $body, $this->markdown->render($body, ['link_mentions' => true]));
```

In `ComposerController::preview()`:

```php
$html = $this->container->get(Markdown::class)->render($body, ['link_mentions' => true]);
```

Do not change profile bios or other shared `Markdown::render()` callers.

- [ ] **Step 7: Run tests and commit**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppMentionLinkRenderTest.php
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php --filter render
./vendor/bin/phpunit tests/Unit/MentionParserTest.php
```

Expected: PASS.

Commit:

```bash
git add src/Core/App.php src/Controller/ComposerController.php src/Repository/UserRepository.php src/Service/DirectMessageService.php src/Service/PostingService.php src/Support/Markdown.php src/Support/MentionLinker.php tests/Integration/Core/AppMentionLinkRenderTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "feat: link mentions in rendered composer output"
```

### Task 4: Suggestion API and Service

**Files:**
- Create: `src/Service/ComposerSuggestion.php`
- Create: `src/Service/ComposerSuggestionService.php`
- Create: `tests/Integration/Core/AppComposerSuggestTest.php`
- Create or modify: `tests/Unit/Composer/ComposerSuggestionServiceTest.php`
- Modify: `src/Controller/ComposerController.php`
- Modify: `src/Core/App.php`
- Modify: `config/config.php`
- Modify: `src/Repository/UserRepository.php`
- Modify: `src/Repository/BoardRepository.php`
- Modify: `src/Repository/TagRepository.php`
- Modify: `src/Repository/PostRepository.php`
- Modify: `src/Repository/ThreadRepository.php`

- [ ] **Step 1: Write failing endpoint tests**

Create `tests/Integration/Core/AppComposerSuggestTest.php` with these core cases.

> **Harness note:** `ComposerSuggestionService` reuses `MysqlSearchService` for
> topic/post (`#`) results, and InnoDB FULLTEXT does **not** index rows written
> inside an open transaction. So — exactly like `AppSearchTest` — this suite
> commits its fixtures (it skips the rolling-back `parent::setUp()`) and
> truncates everything in `tearDown`. Prefix-based `@`/board/tag lookups would
> pass either way; the committed harness is what lets the topic assertions see
> freshly-seeded threads. This means these tests are **not** isolated by
> transaction rollback — clean up via the shared `resetDatabase()`.

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Security\ArrayRateLimiter;
use PDO;
use Tests\Support\TestCase;

final class AppComposerSuggestTest extends TestCase
{
    protected function setUp(): void
    {
        // Deliberately NOT calling parent::setUp(): fixtures must be committed so
        // the FULLTEXT index (used for '#' topic/post suggestions) sees them.
        $this->pdo = $GLOBALS['__RB_TEST_PDO'];
        $this->config = $GLOBALS['__RB_TEST_CONFIG'];
        $this->db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $this->db->setPdo($this->pdo);
        $this->resetDatabase();
        $this->rateLimiter = new ArrayRateLimiter();
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
        $this->cookies = [];
        $this->csrfSecret = null;
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        // Preserve migration-seeded reference tables (see AppSearchTest): TRUNCATE
        // auto-commits, so wiping these would leak empty seeds into later tests.
        $preserve = [
            'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
            'capabilities', 'role_capabilities',
        ];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (!in_array($t, $preserve, true)) {
                $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $t) . '`');
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function enableSuggestions(): void
    {
        (new SettingRepository($this->db))->set('features', [
            'rich_composer' => true,
            'tags' => true,
            'content_references' => true,
        ]);
    }

    public function test_suggest_requires_auth_and_rich_composer(): void
    {
        // Guests hit requireUser() first, which redirects (302) to /login — it
        // does not 403 (mirrors how /composer/preview treats an anonymous caller).
        $this->assertRedirectContains($this->get('/composer/suggest', ['trigger' => '@', 'q' => 'a']), '/login');
        $user = $this->makeUser(['username' => 'suggestauth']);
        $this->actingAs($user);
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $this->assertStatus(404, $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'a']));
    }

    public function test_user_suggestions_return_mention_markdown(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'suggestviewer']);
        $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Example']);
        $this->actingAs($viewer);

        $res = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'ali']);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('user', $json['items'][0]['type']);
        self::assertSame('@alice', $json['items'][0]['token']);
        self::assertSame('@alice', $json['items'][0]['markdown']);
        self::assertSame('/u/alice', $json['items'][0]['url']);
    }

    public function test_hash_suggestions_are_read_gated_and_grouped(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'hashviewer']);
        $author = $this->makeUser(['username' => 'hashauthor']);
        $cat = $this->makeCategory('Hash Suggest');
        $public = $this->makeBoard($cat, ['slug' => 'general-suggest', 'name' => 'General Suggest']);
        $private = $this->makeBoard($cat, ['slug' => 'private-suggest', 'name' => 'Private Suggest', 'visibility' => 'private']);
        $thread = $this->makeThread($public, $author, 'Release planning topic', 'Planning body for release notes');
        (new TagRepository($this->db))->create('release-notes', 'Release Notes', 'Shipping notes', (int) $author['id']);

        $this->actingAs($viewer);
        $res = $this->get('/composer/suggest', ['trigger' => '#', 'q' => 'release']);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        $markdown = array_column($json['items'], 'markdown');
        self::assertContains('[#release-notes](/tags/release-notes)', $markdown);
        self::assertContains('[Release planning topic](/t/' . $thread['thread_id'] . '-' . $thread['slug'] . ')', $markdown);
        self::assertNotContains('[#private-suggest](/c/private-suggest)', $markdown);

        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $viewer['id'], null);
        $memberRes = $this->get('/composer/suggest', ['trigger' => '#', 'q' => 'private']);
        $this->assertStatus(200, $memberRes);
        self::assertStringContainsString('[#private-suggest](/c/private-suggest)', $memberRes->body());
    }

    public function test_forged_unreadable_target_id_matches_context_free_results(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'contextviewer']);
        $other = $this->makeUser(['username' => 'contextother']);
        $cat = $this->makeCategory('Context');
        $private = $this->makeBoard($cat, ['slug' => 'context-private', 'visibility' => 'private']);
        $thread = $this->makeThread($private, $other, 'Hidden context topic', 'hidden');
        $this->actingAs($viewer);

        $plain = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'context']);
        $forged = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'context', 'context' => 'thread', 'target_id' => (string) $thread['thread_id']]);
        self::assertSame($plain->body(), $forged->body());
    }

    public function test_anonymous_participation_does_not_boost_user_suggestion_rank(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'anonrankviewer']);
        $anon = $this->makeUser(['username' => 'anonrankalice']);
        $normal = $this->makeUser(['username' => 'anonrankbob']);
        $board = $this->makeBoard($this->makeCategory('Anon Rank'), ['slug' => 'anon-rank', 'allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $viewer, 'Anon ranking', 'opening');
        $this->actingAs($anon);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'secret', 'is_anonymous' => '1']);
        $this->actingAs($normal);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'visible']);

        $this->actingAs($viewer);
        $res = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'anonrank', 'context' => 'thread', 'target_id' => (string) $thread['thread_id']]);
        $json = json_decode($res->body(), true);
        $tokens = array_column($json['items'], 'token');
        self::assertLessThan(array_search('@anonrankalice', $tokens, true), array_search('@anonrankbob', $tokens, true));
    }
}
```

- [ ] **Step 2: Run failing tests**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerSuggestTest.php
```

Expected: 404 for missing route.

- [ ] **Step 3: Add rate-limit policy**

In `config/config.php`, add:

```php
'composer_suggest' => [120, 60],
```

near `composer_preview`.

- [ ] **Step 4: Add DTO**

Create `src/Service/ComposerSuggestion.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

final class ComposerSuggestion
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $label,
        public readonly string $token,
        public readonly string $url,
        public readonly string $markdown,
        public readonly string $meta = '',
        public readonly string $group = '',
        public readonly int $rank = 0,
    ) {
    }

    /** @return array{type:string,id:int,label:string,token:string,url:string,markdown:string,meta:string,group:string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'token' => $this->token,
            'url' => $this->url,
            'markdown' => $this->markdown,
            'meta' => $this->meta,
            'group' => $this->group,
        ];
    }
}
```

- [ ] **Step 5: Add repository helpers**

Add focused query helpers:

```php
// UserRepository
/** @return array<int,array{id:int,username:string,display_name:?string,role:string,status:string}> */
public function suggestByPrefix(string $query, int $limit): array
{
    return $this->db->fetchAll(
        "SELECT id, username, display_name, role, status
         FROM users
         WHERE status = 'active' AND (username LIKE ? OR display_name LIKE ?)
         ORDER BY username ASC
         LIMIT " . max(1, min(25, $limit)),
        [$query . '%', $query . '%'],
    );
}
```

```php
// BoardRepository
/** @return array<int,array<string,mixed>> */
public function suggestByPrefix(string $query, int $limit): array
{
    return $this->db->fetchAll(
        "SELECT * FROM boards
         WHERE is_archived = 0 AND (slug LIKE ? OR name LIKE ?)
         ORDER BY slug ASC
         LIMIT " . max(1, min(25, $limit)),
        [$query . '%', $query . '%'],
    );
}
```

```php
// TagRepository
/** @return array<int,array<string,mixed>> */
public function suggestByPrefix(string $query, int $limit): array
{
    return $this->db->fetchAll(
        "SELECT * FROM tags
         WHERE is_enabled = 1 AND visibility = 'public' AND (slug LIKE ? OR name LIKE ?)
         ORDER BY slug ASC
         LIMIT " . max(1, min(25, $limit)),
        [$query . '%', $query . '%'],
    );
}
```

```php
// PostRepository
/** @return array<int,int> user_id => rank boost */
public function nonAnonymousParticipantRanks(int $threadId): array
{
    $rows = $this->db->fetchAll(
        'SELECT user_id, MIN(created_at) AS first_at
         FROM posts
         WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 AND is_anonymous = 0
         GROUP BY user_id
         ORDER BY first_at ASC',
        [$threadId],
    );
    $rank = [];
    $boost = 300;
    foreach ($rows as $row) {
        $rank[(int) $row['user_id']] = $boost;
        $boost = max(100, $boost - 10);
    }
    return $rank;
}
```

- [ ] **Step 6: Add `ComposerSuggestionService`**

Create `src/Service/ComposerSuggestionService.php` with public API:

```php
/**
 * @return list<ComposerSuggestion>
 */
public function suggest(string $trigger, string $query, string $context, int $targetId, User $viewer): array
```

Implementation rules:
- Trim `q`, strip leading trigger, cap to 80 chars.
- For `@`, return users only with `markdown='@username'`.
- For `#`, return boards, tags, threads, and posts with canonical Markdown links.
- Context boosts apply only after `readableContext()` returns true.
- Participant boosts use `PostRepository::nonAnonymousParticipantRanks()`.
- Board suggestions filter each row through `BoardPolicy::canRead($board, $viewer, $members->isMember(...))`.
- Tags require `FeatureFlags::enabled('tags')`.
- Topic/post results reuse `SearchService::search()` when `mb_strlen($query) >= 3`; below 3 chars, return only board/tag prefix matches.
- Post suggestion metadata uses masked author text: `Anonymous` when `is_anonymous=1`, otherwise display name or username.

Use this result shape for items:

```php
new ComposerSuggestion(
    type: 'board',
    id: (int) $board['id'],
    label: '#' . (string) $board['slug'],
    token: '#' . (string) $board['slug'],
    url: '/c/' . (string) $board['slug'],
    markdown: '[#' . (string) $board['slug'] . '](/c/' . (string) $board['slug'] . ')',
    meta: (string) $board['name'],
    group: 'Boards',
    rank: 200,
);
```

- [ ] **Step 7: Add controller route**

In `src/Controller/ComposerController.php`, add:

```php
public function suggest(Request $request): Response
{
    $user = $this->requireUser();
    $flags = $this->container->get(FeatureFlags::class);
    if (!$flags->enabled('rich_composer')) {
        throw new \App\Core\NotFoundException('Not found.');
    }
    $this->container->get(RateLimitService::class)->enforce('composer_suggest', $request, $user);

    $trigger = (string) $request->query('trigger', '');
    $q = (string) $request->query('q', '');
    $context = (string) $request->query('context', '');
    $targetId = (int) $request->query('target_id', 0);

    if (!in_array($trigger, ['@', '#'], true)) {
        return Response::json(['ok' => false, 'error' => 'Unsupported trigger.'], 422);
    }

    $items = $this->container->get(ComposerSuggestionService::class)
        ->suggest($trigger, $q, $context, $targetId, $user);

    return Response::json([
        'ok' => true,
        'items' => array_map(static fn (ComposerSuggestion $item): array => $item->toArray(), $items),
    ]);
}
```

In `src/Core/App.php`, import/bind `ComposerSuggestionService` and add route:

```php
$r->get('/composer/suggest', [ComposerController::class, 'suggest']);
```

- [ ] **Step 8: Run tests and commit**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerSuggestTest.php
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter rich
```

Expected: PASS.

Commit:

```bash
git add config/config.php src/Core/App.php src/Controller/ComposerController.php src/Repository/BoardRepository.php src/Repository/PostRepository.php src/Repository/TagRepository.php src/Repository/ThreadRepository.php src/Repository/UserRepository.php src/Service/ComposerSuggestion.php src/Service/ComposerSuggestionService.php tests/Integration/Core/AppComposerSuggestTest.php tests/Unit/Composer/ComposerSuggestionServiceTest.php
git commit -m "feat: add composer suggestion API"
```

### Task 5: Composer Bridge Extraction on Textarea Adapter

**Files:**
- Modify: `public/assets/composer.js`
- Modify: `templates/partials/composer.php`
- Modify: `templates/partials/new_thread_form.php`
- Modify: `templates/compose.php`
- Modify: `templates/dm/new.php`
- Modify: `templates/dm/show.php`
- Modify: `templates/partials/post.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`
- Modify: `tests/browser/server-drafts.spec.ts`
- Modify: `tests/browser/gate-a.spec.ts`

- [ ] **Step 1: Write failing integration test for form context attributes**

Add to `AppComposerTest`:

```php
public function test_composer_forms_expose_bridge_context_metadata(): void
{
    $board = $this->makeBoard($this->makeCategory(), ['slug' => 'bridge-meta']);
    $author = $this->makeUser(['username' => 'bridgemeta']);
    $recipient = $this->makeUser(['username' => 'bridgedm']);
    $thread = $this->makeThread($board, $author, 'Bridge meta', 'Opening');
    $this->actingAs($author);

    $boardPage = $this->get('/c/bridge-meta');
    self::assertStringContainsString('data-composer-context="new_thread"', $boardPage->body());
    self::assertStringContainsString('data-composer-target-id="' . (int) $board['id'] . '"', $boardPage->body());

    $threadPage = $this->get('/t/' . $thread['thread_id']);
    self::assertStringContainsString('data-composer-context="reply"', $threadPage->body());
    self::assertStringContainsString('data-composer-target-id="' . $thread['thread_id'] . '"', $threadPage->body());
    self::assertStringContainsString('data-composer-context="edit"', $threadPage->body());

    $newDm = $this->get('/messages/new');
    self::assertStringContainsString('data-composer-context="dm"', $newDm->body());
}
```

- [ ] **Step 2: Add template metadata**

For each composer form, add these data attributes:

```php
data-composer-context="reply" data-composer-target-id="<?= (int) $thread['id'] ?>"
```

Use:
- New thread forms: `context="new_thread"`, `target_id=board.id`.
- Reply forms: `context="reply"`, `target_id=thread.id`.
- DM new and reply: `context="dm"`, `target_id=conversation_id` for existing conversations and `0` for new DM.
- Post edit: `context="edit"`, `target_id=post.id`.

Normalize DM forms so shared JS finds them:

```php
class="dm-composer composer"
```

Keep `data-no-draft` on inline edit forms.

- [ ] **Step 3: Extract adapter in `composer.js`**

Add near the top of `public/assets/composer.js`:

```js
function TextareaComposerAdapter(form, ta) {
    this.form = form;
    this.ta = ta;
    this.changeHandlers = [];
    var self = this;
    ta.addEventListener('input', function () {
        self.changeHandlers.forEach(function (cb) { cb(self.getMarkdown()); });
    });
}
TextareaComposerAdapter.prototype.getMarkdown = function () { return this.ta.value; };
TextareaComposerAdapter.prototype.setMarkdown = function (markdown) {
    this.ta.value = markdown || '';
    this.ta.dispatchEvent(new Event('input', { bubbles: true }));
};
TextareaComposerAdapter.prototype.insertMarkdown = function (markdown) {
    this.replaceSelection(markdown);
};
TextareaComposerAdapter.prototype.replaceSelection = function (markdown) {
    var ta = this.ta;
    var s = ta.selectionStart || 0;
    var e = ta.selectionEnd || s;
    ta.value = ta.value.slice(0, s) + markdown + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + markdown.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', { bubbles: true }));
};
TextareaComposerAdapter.prototype.replacePendingUpload = function (token, markdown) {
    return replaceOnce(this.ta, token, markdown);
};
TextareaComposerAdapter.prototype.focus = function () { this.ta.focus(); };
TextareaComposerAdapter.prototype.onChange = function (callback) { this.changeHandlers.push(callback); };
TextareaComposerAdapter.prototype.setDisabled = function (disabled) { this.ta.disabled = !!disabled; };
TextareaComposerAdapter.prototype.destroy = function () {};
```

Then refactor shared functions one at a time:
- `buildPreview(form, ta)` becomes `buildPreview(form, adapter)` and reads `adapter.getMarkdown()`.
- `wireDrafts(form, ta)` becomes `wireDrafts(form, adapter)` and calls `adapter.getMarkdown()` / `adapter.setMarkdown()`.
- Upload completion calls `adapter.replacePendingUpload(placeholder, markdown)`.
- Slash insertion calls `adapter.replaceSelection()` or a textarea-only range helper exposed by the textarea adapter.

Keep the textarea object available as `adapter.ta` for functions that still need selection state until Task 6 removes those direct reads.

- [ ] **Step 4: Keep behavior green on textarea path**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php
cd tests/browser && npx playwright test server-drafts.spec.ts -g "server drafts expose conflict"
cd tests/browser && npx playwright test gate-a.spec.ts -g "phase 4 slash"
```

Expected: PASS.

Commit:

```bash
git add public/assets/composer.js templates/partials/composer.php templates/partials/new_thread_form.php templates/compose.php templates/dm/new.php templates/dm/show.php templates/partials/post.php tests/Integration/Core/AppComposerTest.php tests/browser/server-drafts.spec.ts tests/browser/gate-a.spec.ts
git commit -m "refactor: introduce composer bridge"
```

### Task 6: `@` and `#` Pickers on the Textarea Adapter

**Files:**
- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.css`
- Modify: `tests/browser/gate-a.spec.ts`
- Create or modify: `tests/browser/wysiwyg-composer.spec.ts`

- [ ] **Step 1: Add browser tests for textarea picker flow**

Add Playwright tests with `wysiwyg_composer=false` and `rich_composer=true`:

```ts
test('textarea composer inserts @ mention from keyboard picker', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await body.fill('@ali');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await expect(body).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Enter');
  await expect(body).toHaveValue('@alice');
});

test('textarea # picker inserts board reference and does not steal headings', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await body.fill('# ');
  await expect(page.locator('.composer-reference-menu')).toHaveCount(0);
  await body.fill('#gen');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await page.keyboard.press('Enter');
  await expect(body).toHaveValue('[#general](/c/general)');
});
```

- [ ] **Step 2: Add picker CSS**

In `public/assets/app.css`, add static styles:

```css
.composer-reference-menu {
  border: 1px solid var(--border);
  background: var(--surface-raised);
  box-shadow: var(--shadow-pop);
  border-radius: 8px;
  margin-top: 6px;
  max-height: 280px;
  overflow: auto;
  padding: 4px;
}
.composer-reference-menu[hidden] { display: none; }
.composer-reference-option {
  width: 100%;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 8px;
  border: 0;
  background: transparent;
  color: var(--text);
  text-align: left;
  padding: 7px 9px;
  border-radius: 6px;
}
.composer-reference-option[aria-selected="true"],
.composer-reference-option:hover {
  background: var(--surface-3);
}
.composer-reference-option .badge { grid-row: span 2; align-self: center; }
.composer-reference-meta { color: var(--text-muted); font-size: .86rem; }
@media (max-width: 640px) {
  .composer-reference-menu {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    max-height: 50vh;
    border-radius: 8px 8px 0 0;
  }
}
```

- [ ] **Step 3: Add bridge-level picker code**

In `public/assets/composer.js`, add `wireReferencePickers(form, adapter)` after slash menu code. Core rules:

```js
function referenceState(ta) {
    if (ta.selectionStart !== ta.selectionEnd) { return null; }
    var pos = ta.selectionStart;
    var before = ta.value.slice(0, pos);
    var m = before.match(/(^|[\s(])([@#])([A-Za-z0-9_-]{1,80})$/);
    if (!m) { return null; }
    var trigger = m[2];
    var query = m[3];
    if (trigger === '#' && /^\s*#{1,3}\s?$/.test(before.slice(before.lastIndexOf('\n') + 1))) {
        return null;
    }
    if (inFence(ta)) {
        return null;
    }
    return { trigger: trigger, query: query, start: pos - trigger.length - query.length, end: pos };
}
```

Use `fetch('/composer/suggest?trigger=' + encodeURIComponent(state.trigger) + '&q=' + encodeURIComponent(state.query) + '&context=' + encodeURIComponent(form.getAttribute('data-composer-context') || '') + '&target_id=' + encodeURIComponent(form.getAttribute('data-composer-target-id') || '0'))`.

Render `button.composer-reference-option[role=option]` rows with:
- badge text from `item.type`
- main label from `item.label`
- meta from `item.meta`

Keyboard contract:
- ArrowDown/ArrowUp cycles.
- Enter and Tab select active item.
- Escape closes.
- Click selects item.

Selection on textarea adapter:

```js
replaceRange(adapter.ta, state.start, state.end, item.markdown);
```

Combobox attributes must mirror slash menu:

```js
ta.setAttribute('role', 'combobox');
ta.setAttribute('aria-controls', menuId);
ta.setAttribute('aria-haspopup', 'listbox');
ta.setAttribute('aria-autocomplete', 'list');
```

Call `wireReferencePickers(form, adapter)` in `enhance()` after `wireSlashMenu()` and before `wireKeys()`.

- [ ] **Step 4: Run browser tests and commit**

Run:

```bash
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "textarea"
cd tests/browser && npx playwright test a11y.spec.ts -g "slash combobox"
```

Expected: PASS.

Commit:

```bash
git add public/assets/composer.js public/assets/app.css tests/browser/wysiwyg-composer.spec.ts tests/browser/gate-a.spec.ts
git commit -m "feat: add composer reference pickers"
```

### Task 7: Milkdown Build Pipeline and CSP Spike

**Files:**
- Create: `package.json`
- Create: `package-lock.json`
- Create: `vite.config.mjs`
- Create: `src/client/wysiwyg/index.ts`
- Create: `src/client/wysiwyg/milkdown-adapter.ts`
- Create: `src/client/wysiwyg/styles.css`
- Modify: `public/assets/wysiwyg-composer.js`
- Modify: `public/assets/wysiwyg-composer.css`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Modify: `docs/adr/0013-wysiwyg-composer.md`

- [ ] **Step 1: Add package metadata with pinned versions**

Create root `package.json`:

```json
{
  "private": true,
  "scripts": {
    "build:wysiwyg": "vite build --config vite.config.mjs",
    "check:wysiwyg": "npm run build:wysiwyg && git diff --exit-code -- public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css"
  },
  "dependencies": {
    "@milkdown/core": "7.21.2",
    "@milkdown/plugin-history": "7.21.2",
    "@milkdown/plugin-listener": "7.21.2",
    "@milkdown/preset-commonmark": "7.21.2",
    "@milkdown/preset-gfm": "7.21.2",
    "@milkdown/prose": "7.21.2"
  },
  "devDependencies": {
    "typescript": "6.0.3",
    "vite": "8.1.3"
  }
}
```

Run:

```bash
npm install --package-lock-only
```

Expected: `package-lock.json` is created and contains the exact versions above.

- [ ] **Step 2: Add Vite static build config**

Create `vite.config.mjs`:

```js
import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    emptyOutDir: false,
    outDir: 'public/assets',
    assetsDir: '.',
    rollupOptions: {
      input: 'src/client/wysiwyg/index.ts',
      output: {
        entryFileNames: 'wysiwyg-composer.js',
        assetFileNames: (assetInfo) => {
          return assetInfo.name && assetInfo.name.endsWith('.css') ? 'wysiwyg-composer.css' : '[name][extname]';
        },
      },
    },
  },
});
```

- [ ] **Step 3: Add minimal adapter entry without runtime style injection**

Create `src/client/wysiwyg/styles.css` with static classes only:

```css
.wysiwyg-composer {
  border: 1px solid var(--border);
  background: var(--surface-raised);
  border-radius: 8px;
  min-height: 9rem;
}
.wysiwyg-composer .ProseMirror {
  min-height: 9rem;
  padding: 10px 12px;
  outline: none;
}
.wysiwyg-source-toggle {
  margin-top: 8px;
}
.composer-input.is-wysiwyg-source-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  clip-path: inset(50%);
  white-space: nowrap;
}
```

Create `src/client/wysiwyg/index.ts`:

```ts
import './styles.css';

const w = window as unknown as {
  RetroBoardsComposer?: {
    registerWysiwygAdapter(factory: unknown): void;
  };
};

if (document.body.getAttribute('data-wysiwyg-composer') === '1' && w.RetroBoardsComposer) {
  import('./milkdown-adapter').then((module) => {
    w.RetroBoardsComposer?.registerWysiwygAdapter(module.createMilkdownComposerAdapter);
  }).catch(() => {
    // Textarea adapter remains active.
  });
}
```

Create `src/client/wysiwyg/milkdown-adapter.ts` with a stub factory first:

```ts
export function createMilkdownComposerAdapter(): null {
  return null;
}
```

- [ ] **Step 4: Build committed assets**

Run:

```bash
npm run build:wysiwyg
```

Expected: `public/assets/wysiwyg-composer.js` and `public/assets/wysiwyg-composer.css` are generated with no inline `<style>` runtime in the source code.

- [ ] **Step 5: Add CSP browser smoke**

In `tests/browser/wysiwyg-composer.spec.ts`, add:

```ts
test('wysiwyg assets load under strict CSP without violations', async ({ page }) => {
  const violations: string[] = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if (/Content Security Policy|Refused to apply inline style|Refused to execute inline script/i.test(text)) {
      violations.push(text);
    }
  });
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();
  expect(violations).toEqual([]);
});
```

- [ ] **Step 6: Run checks and commit**

Run:

```bash
npm run build:wysiwyg
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "strict CSP"
```

Expected: PASS.

Commit:

```bash
git add package.json package-lock.json vite.config.mjs src/client/wysiwyg public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/browser/wysiwyg-composer.spec.ts docs/adr/0013-wysiwyg-composer.md
git commit -m "build: add wysiwyg composer asset pipeline"
```

### Task 8: Milkdown Adapter, Source Mode, and Round Trip

**Files:**
- Modify: `src/client/wysiwyg/milkdown-adapter.ts`
- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.css`
- Modify: `tests/Unit/Composer/MarkdownRoundTripTest.php`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`

- [ ] **Step 1: Add round-trip fixture tests**

Extend `tests/Unit/Composer/MarkdownRoundTripTest.php` with semantic parity cases:

```php
public function test_supported_markdown_fixtures_render_semantically_after_editor_round_trip(): void
{
    $markdown = implode("\n\n", [
        '## Heading',
        '**bold** *italic* ~~strike~~ `code`',
        "> quote",
        "- [x] task\n- item",
        "| A | B |\n| - | - |\n| 1 | 2 |",
        "||spoiler||",
        "@alice",
        "[#general](/c/general)",
    ]);

    $html = (new Markdown(new HtmlSanitizer()))->render($markdown);
    self::assertStringContainsString('<table>', $html);
    self::assertStringContainsString('class="spoiler"', $html);
}
```

Client-side parse/serialize parity is covered by Playwright after the adapter lands.

- [ ] **Step 2: Expose composer registration seam**

In `public/assets/composer.js`, expose a small global after `TextareaComposerAdapter` is defined:

```js
var wysiwygFactory = null;
window.RetroBoardsComposer = {
    registerWysiwygAdapter: function (factory) {
        wysiwygFactory = factory;
        document.querySelectorAll('form.composer').forEach(function (form) {
            if (form._rbComposerEnhance) { form._rbComposerEnhance(); }
        });
    }
};
```

In `enhance(form, prefs)`, choose adapter:

```js
var adapter = new TextareaComposerAdapter(form, ta);
if (document.body.getAttribute('data-wysiwyg-composer') === '1' && wysiwygFactory && !form.hasAttribute('data-no-wysiwyg')) {
    var rich = wysiwygFactory(form, ta, adapter);
    if (rich) { adapter = rich; }
}
form._rbComposerAdapter = adapter;
```

Ensure submit forces sync:

```js
form.addEventListener('submit', function () {
    if (adapter && typeof adapter.getMarkdown === 'function') {
        ta.value = adapter.getMarkdown();
    }
});
```

- [ ] **Step 3: Implement Milkdown adapter**

In `src/client/wysiwyg/milkdown-adapter.ts`, use Milkdown CommonMark/GFM presets, listener, and history. The adapter must:
- Mount a `.wysiwyg-composer` element before the textarea.
- Hide the textarea visually with `is-wysiwyg-source-hidden`.
- Add a Source toggle button.
- Keep initial Markdown untouched until a rich edit occurs.
- On rich edits, serialize Markdown into the textarea and dispatch `input`.
- On source toggle back to rich mode, parse textarea Markdown into Milkdown.
- Return `null` on mount failure.

Keep the factory shape:

```ts
export function createMilkdownComposerAdapter(form: HTMLFormElement, textarea: HTMLTextAreaElement, fallback: any) {
  try {
    return new MilkdownComposerAdapter(form, textarea, fallback);
  } catch {
    return null;
  }
}
```

Do not import a Milkdown theme that injects styles. All styles remain in `src/client/wysiwyg/styles.css`.

- [ ] **Step 4: Add Playwright coverage**

Add tests:
- `new topic WYSIWYG compose and submit`
- `source mode edits canonical Markdown and switches back`
- `no-op edit does not rewrite body`
- `server preview matches final rendered post for supported syntax`
- mobile viewport smoke

Use DB assertions through existing test pages or seed-only routes. The no-op edit test should read the post body before and after edit and assert byte equality.

- [ ] **Step 5: Run checks and commit**

Run:

```bash
npm run build:wysiwyg
npm run check:wysiwyg
./vendor/bin/phpunit tests/Unit/Composer/MarkdownRoundTripTest.php
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "WYSIWYG|source|no-op|preview"
```

Expected: PASS.

Commit:

```bash
git add public/assets/composer.js public/assets/app.css src/client/wysiwyg/milkdown-adapter.ts public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/Unit/Composer/MarkdownRoundTripTest.php tests/browser/wysiwyg-composer.spec.ts
git commit -m "feat: add milkdown composer adapter"
```

### Task 9: Rich Chips and Internal URL Paste

**Files:**
- Modify: `src/client/wysiwyg/milkdown-adapter.ts`
- Modify: `src/client/wysiwyg/styles.css`
- Modify: `public/assets/wysiwyg-composer.js`
- Modify: `public/assets/wysiwyg-composer.css`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`

- [ ] **Step 1: Add browser tests for chips and paste**

Add tests:

```ts
test('wysiwyg reference selections become chips and serialize to markdown', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const editor = page.locator('.wysiwyg-composer .ProseMirror').first();
  await editor.click();
  await page.keyboard.type('#gen');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await page.keyboard.press('Enter');
  await expect(page.locator('.composer-chip')).toContainText('#general');
  await page.getByRole('button', { name: 'Source' }).click();
  await expect(page.locator('textarea.composer-input').first()).toHaveValue('[#general](/c/general)');
});

test('pasted internal topic url becomes canonical markdown chip', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const firstTopic = await page.locator('a[href^="/t/"]').first().getAttribute('href');
  const editor = page.locator('.wysiwyg-composer .ProseMirror').first();
  await editor.click();
  await page.evaluate(async (text) => navigator.clipboard.writeText(`${location.origin}${text}`), firstTopic);
  await page.keyboard.press(process.platform === 'darwin' ? 'Meta+V' : 'Control+V');
  await expect(page.locator('.composer-chip')).toBeVisible();
  await page.getByRole('button', { name: 'Source' }).click();
  await expect(page.locator('textarea.composer-input').first()).toHaveValue(/\/t\/\d+-/);
});
```

- [ ] **Step 2: Add chip CSS**

In `src/client/wysiwyg/styles.css`:

```css
.composer-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface-3);
  color: var(--text-strong);
  padding: 1px 7px;
  line-height: 1.5;
}
.composer-chip.is-muted {
  color: var(--text-muted);
  border-style: dashed;
}
```

- [ ] **Step 3: Implement rich chip behavior**

In `MilkdownComposerAdapter`:
- On picker insertion, insert a Milkdown/ProseMirror inline node or mark that renders `.composer-chip`.
- Serialize chips to the `item.markdown` provided by the API.
- For `@`, chip text is `@username` and Markdown is raw `@username`.
- For `#` boards/tags/topics/posts, chip text is label and Markdown is the canonical link.
- Count mention chips from serialized Markdown; add `is-muted` beyond `MentionParser::MAX` equivalent count of 10 so non-notifying excess chips are visually distinct.

- [ ] **Step 4: Implement internal paste rewriting**

In the adapter paste handler:
- If pasted URL path matches `/c/{slug}`, request `#` suggestions with `q={slug}` and insert the exact board markdown when a matching URL is returned.
- If path matches `/tags/{slug}`, same tag behavior.
- If path matches `/t/{id}-{slug}` or `/t/{id}-{slug}#p{postId}`, request `#` suggestions with the topic slug/title token where possible, then fall back to canonical Markdown built from URL and visible text.
- External URLs fall through to default Milkdown paste.
- Undo should restore the raw pasted URL through the editor transaction history.

- [ ] **Step 5: Run checks and commit**

Run:

```bash
npm run build:wysiwyg
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "chip|pasted"
```

Expected: PASS.

Commit:

```bash
git add src/client/wysiwyg/milkdown-adapter.ts src/client/wysiwyg/styles.css public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/browser/wysiwyg-composer.spec.ts
git commit -m "feat: add rich composer chips"
```

### Task 10: Browser Evidence, Runbook, and Documentation Closeout

**Files:**
- Create: `docs/runbooks/wysiwyg_composer.md`
- Modify: `COMPOSER.md`
- Modify: `SCHEMA.md`
- Modify: `docs/evidence/deploy-dark-features.md`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Modify: `tests/browser/a11y.spec.ts`
- Modify: `tests/browser/README.md`

- [ ] **Step 1: Expand browser acceptance tests**

Cover:
- new topic WYSIWYG compose and submit
- reply compose and submit
- DM compose and submit
- edit existing post
- source-mode round trip
- server preview parity
- local draft restore
- server draft conflict load-local/load-server behavior
- image paste/drop and alt text
- pending upload placeholder replacement
- `@` picker keyboard flow
- `#` picker board/tag/topic/post selections
- textarea adapter picker with `wysiwyg_composer=false`
- `#` heading trigger is ignored
- pasted internal URL becomes chip
- no-JS or kill-switch fallback
- strict-CSP smoke with no violations
- axe checks for toolbar and picker

- [ ] **Step 2: Add runbook**

Create `docs/runbooks/wysiwyg_composer.md` with:

````markdown
# Runbook - WYSIWYG Composer (`wysiwyg_composer`)

`wysiwyg_composer` gates only the Milkdown editor layer. `rich_composer=false` remains the broad kill switch and prevents all enhanced composer assets from loading.

## Enable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=true; $r->set("features",$f);'
```

## Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=false; $r->set("features",$f);'
```

Existing posts and drafts remain Markdown and need no migration.

## Emergency Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["rich_composer"]=false; $r->set("features",$f);'
```

This disables `composer.js`, the suggestion picker, and the WYSIWYG bundle. Server-rendered textarea posting remains available.

## Verify

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppMentionLinkRenderTest.php
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"
```

## Known Limits

Markdown remains canonical. Legacy Markdown may normalize after a user edits through the rich surface, but a no-op edit must not rewrite the stored body.
````

- [ ] **Step 3: Update docs**

Update:
- `COMPOSER.md`: true WYSIWYG behavior, source mode, bridge, pickers, Markdown canonical storage, preview as final truth.
- `SCHEMA.md`: keep `0071` and `content_references.target_type='tag'` docs current.
- `docs/evidence/deploy-dark-features.md`: add `wysiwyg_composer` as deploy-dark with evidence links after browser/a11y runs.
- `tests/browser/README.md`: mention WYSIWYG evidence command.

- [ ] **Step 4: Run full verification**

Run:

```bash
composer test
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts
```

Expected: PASS.

Commit:

```bash
git add COMPOSER.md SCHEMA.md docs/evidence/deploy-dark-features.md docs/runbooks/wysiwyg_composer.md tests/browser/README.md tests/browser/wysiwyg-composer.spec.ts tests/browser/a11y.spec.ts
git commit -m "docs: document wysiwyg composer rollout"
```

## Final Verification

After all tasks:

```bash
composer test
npm run check:wysiwyg
cd tests/browser && npx playwright test
git status --short
```

Expected:
- PHPUnit full suite passes.
- WYSIWYG committed assets are reproducible.
- Playwright desktop and mobile projects pass.
- `git status --short` shows only intentional evidence screenshots if the evidence run writes new images.

## Self-Review

Spec coverage:
- ADR superseding/amending ADR 0001: Task 1.
- `wysiwyg_composer` flag and broad `rich_composer` kill switch: Task 1.
- Suggestion API, rate limit, read gates, context gates, short-query behavior, anonymous-safe ranking: Task 4.
- Tag reference enum, extraction, card rendering, feature gates: Task 2.
- Mention link rendering with shared grammar and code/pre exclusion: Task 3.
- Composer bridge and textarea adapter: Task 5.
- `@` and `#` picker on textarea bridge: Task 6.
- Milkdown build, committed artifact policy, exact dependency pins: Task 7.
- Milkdown adapter, source mode, no-op edit, semantic round trip: Task 8.
- Rich chips and internal URL paste: Task 9.
- Browser, CSP, axe, docs, runbook, rollout evidence: Task 10.

Execution notes:
- Milkdown plugin APIs may require small code-shape changes while preserving the adapter contract and tests above. If a required plugin injects runtime CSS or inline style attributes, stop Task 7 and record the fallback path in ADR 0013.
- Mention linking (Task 3) runs **after** `HtmlSanitizer::sanitize()` on purpose: the sanitizer keeps only `href` on `<a>` (and adds `rel`), so a `class="mention"` written before sanitisation would be stripped. The linker only emits same-origin `/u/{username}` anchors from an active-user allowlist, and it must keep its handle grammar in lockstep with `MentionParser`.
- Mention linking is bound to the `mentions` flag via `MentionLinker`'s `$enabled`, so links and notifications share one on/off switch.
- `AppComposerSuggestTest` (Task 4) commits fixtures and truncates in `tearDown` like `AppSearchTest`, because InnoDB FULLTEXT (used for `#` topic/post results) does not index rows inside an open transaction. An unauthenticated `/composer/suggest` returns a **302** redirect to `/login` (not 403), because `requireUser()` runs before the flag check.

Placeholder scan:
- No task depends on an unspecified endpoint, flag, table, or test file.
- Every planned new file has an exact path.
- Every migration number follows the current tree after `0070_phase5_publisher_review_security.php`.

---

# Part IV — Implementation Plan: Graduate `wysiwyg_composer` to Default-ON

> (The "For agentic workers" REQUIRED SUB-SKILL note from the top of Part III applies to this plan as well.)

**Goal:** Flip the `wysiwyg_composer` feature flag to default-true so Milkdown is the out-of-the-box editor, following the in-repo graduation ritual (`polls`, `topic_workflow` precedents), with tests, browser-evidence wiring, and docs updated in step.

**Architecture:** No production code changes beyond one line in `FeatureFlags::DEFAULTS` — the layout gating, adapter, and composer bridge are already flag-driven. The work is: rewrite the two PHPUnit tests that assume dark, pin the browser-evidence seed to the textarea baseline (gate-a journeys drive `textarea.composer-input` directly), add a no-override browser proof of the GA default, and sweep five docs.

**Tech Stack:** Vanilla PHP 8.2 + PHPUnit 10 (strict mode), Playwright (`tests/browser`, workers:1), no new dependencies.

**Spec:** `docs/superpowers/specs/2026-07-02-wysiwyg-default-on-design.md` (see Part II above).

## Global Constraints

- The working tree contains an **unrelated in-flight admin/anti-abuse workstream** (modified `src/Service/AdminService.php`, `src/Service/AntiAbuseService.php`, `templates/admin/dashboard.php`, `tests/Integration/Core/AppAdminModerationTest.php`, `tests/browser/a11y.spec.ts`, ~100 `docs/evidence/**/*.png`). **Never run `git add -A` / `git add .`** — stage only the exact paths listed in each commit step. Do not touch `tests/browser/a11y.spec.ts`.
- Branch: `graduate-wysiwyg-composer` (already created; spec committed as `590413d`).
- PHPUnit is strict: every test ≥1 assertion, no output, no warnings. Tests run against `DB_TEST_DATABASE` (default `retroboards_test`); the DB must be reachable before any phpunit run.
- Strict CSP posture unchanged: no inline `<script>`/`<style>` anywhere.
- All dates UTC; today is 2026-07-02.
- Full evidence-PNG regeneration is **out of scope** (deferred; recorded in the spec).

---

### Task 1: Flip the flag default with its two tests (one commit — the ritual requires source + tests land together)

**Files:**
- Modify: `src/Core/FeatureFlags.php:41`
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php:51-67`
- Modify: `tests/Integration/Core/AppComposerTest.php:172-194`

**Interfaces:**
- Produces: `FeatureFlags::DEFAULTS['wysiwyg_composer'] === true`; `templates/layout.php` (unchanged) consequently emits `data-wysiwyg-composer="1"`, `<link rel="stylesheet" href="/assets/wysiwyg-composer.css">`, and `<script type="module" src="/assets/wysiwyg-composer.js"></script>` on every page unless overridden.
- Consumes: existing `Tests\Support\TestCase` helpers `makeBoard`/`makeCategory`/`makeUser`/`actingAs`/`get`, and `SettingRepository::set('features', array)` which **replaces** the whole override map.

- [ ] **Step 1: Rewrite the AppFeatureFlagTest wysiwyg test to graduated expectations**

In `tests/Integration/Core/AppFeatureFlagTest.php`, replace the entire method `test_wysiwyg_composer_defaults_dark_and_is_independently_reversible` (lines 51-67) with:

```php
    public function test_wysiwyg_composer_is_available_by_default_and_can_be_disabled(): void
    {
        // wysiwyg_composer graduated to default-on (GA 2026-07-02): with no
        // features override, the Milkdown layer loads wherever the composer
        // renders (bundle tags + the body data attribute). An operator can
        // still roll the layer back via the features setting, and
        // rich_composer stays the broad kill switch (ADR 0013).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('wysiwyg_composer', $flags->all());
        self::assertTrue($flags->enabled('wysiwyg_composer'));
        self::assertTrue($flags->enabled('rich_composer'));

        // Isolation: graduating wysiwyg_composer must not enable a dark neighbour.
        self::assertFalse($flags->enabled('group_dms'));

        // Available by default on a real page for a signed-in member.
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-default']);
        $this->actingAs($this->makeUser(['username' => 'wysiwyg_default_user']));
        $page = $this->get('/c/wysiwyg-default');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-wysiwyg-composer="1"', $page->body());

        // Operator rollback: disabling the narrow flag removes the layer.
        $this->setFlags(['wysiwyg_composer' => false]);
        $disabled = $this->get('/c/wysiwyg-default');
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $disabled->body());

        // Kill-switch interplay: rich_composer=false keeps assets dark while
        // the narrow flag remains true by default (no wysiwyg key in the override).
        $this->setFlags(['rich_composer' => false]);
        $killed = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($killed->enabled('rich_composer'));
        self::assertTrue($killed->enabled('wysiwyg_composer'), 'the narrow flag stays true while the broad kill switch keeps assets dark');
        $killedPage = $this->get('/c/wysiwyg-default');
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
    }
```

Note: `$board` is used only for its slug; the literal `'wysiwyg-default'` is intentional (mirrors `AppComposerTest`'s style). Keep the `use` statements as-is (both `FeatureFlags` and `SettingRepository` are already imported).

- [ ] **Step 2: Rewrite the AppComposerTest asset-emission test**

In `tests/Integration/Core/AppComposerTest.php`, replace the entire method `test_wysiwyg_flag_only_loads_editor_assets_when_rich_composer_is_enabled` (lines 172-194) with:

```php
    public function test_wysiwyg_editor_assets_load_by_default_and_honor_flag_and_kill_switch(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-assets']);
        $user = $this->makeUser(['username' => 'wysiwygassets']);
        $this->actingAs($user);

        // GA default-on (2026-07-02): with no features override the Milkdown
        // bundle loads alongside the shared composer bridge.
        $defaultPage = $this->get('/c/wysiwyg-assets');
        self::assertStringContainsString('/assets/composer.js', $defaultPage->body());
        self::assertStringContainsString('/assets/wysiwyg-composer.css', $defaultPage->body());
        self::assertStringContainsString('<script type="module" src="/assets/wysiwyg-composer.js"></script>', $defaultPage->body());
        self::assertStringContainsString('data-wysiwyg-composer="1"', $defaultPage->body());

        // Operator rollback: the narrow flag removes only the WYSIWYG layer;
        // the enhanced Markdown composer keeps loading.
        (new SettingRepository($this->db))->set('features', ['wysiwyg_composer' => false]);
        $disabledPage = $this->get('/c/wysiwyg-assets');
        self::assertStringContainsString('/assets/composer.js', $disabledPage->body());
        self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $disabledPage->body());
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $disabledPage->body());

        // Broad kill switch: rich_composer=false keeps every enhanced asset
        // out even though wysiwyg_composer stays true by default.
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $killedPage = $this->get('/c/wysiwyg-assets');
        self::assertStringNotContainsString('/assets/composer.js', $killedPage->body());
        self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $killedPage->body());
        self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
    }
```

- [ ] **Step 3: Run both files — expect the two rewritten tests to FAIL (default still false)**

Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php 2>&1 | tail -15`
Expected: FAILURES — `test_wysiwyg_composer_is_available_by_default_and_can_be_disabled` fails on `assertTrue($flags->enabled('wysiwyg_composer'))`; `test_wysiwyg_editor_assets_load_by_default_and_honor_flag_and_kill_switch` fails on the default-page `wysiwyg-composer.css` containment. All other tests in both files pass. If the DB is unreachable, start it first (see Task 4 Step 1) — do not proceed on a bootstrap error.

- [ ] **Step 4: Flip the default**

In `src/Core/FeatureFlags.php` line 41, replace:

```php
        'wysiwyg_composer' => false, // Milkdown WYSIWYG layer over canonical Markdown textarea; deploy-dark until evidence lands
```

with:

```php
        'wysiwyg_composer' => true,  // Milkdown WYSIWYG layer over canonical Markdown textarea — GA default-on (2026-07-02; reversible via features override)
```

- [ ] **Step 5: Run both files again — expect PASS**

Run: `vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php 2>&1 | tail -5`
Expected: OK, all tests in both files pass (AppFeatureFlagTest ~9 tests, AppComposerTest ~its full count). If any *other* test in these files now fails, it assumed the flag dark — fix it in this task before committing.

- [ ] **Step 6: Commit (exact paths only)**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "feat(composer): graduate wysiwyg_composer to default-ON

Milkdown is now the out-of-the-box editor layer (GA 2026-07-02).
Operators can still disable via the features override; rich_composer
remains the broad kill switch (ADR 0013).

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: Browser-evidence wiring — pin the gate-a textarea baseline, prove the GA default with no override

**Files:**
- Modify: `tests/browser/seed.php:53-68` (the `$evidenceFeatures` array)
- Modify: `tests/browser/wysiwyg-composer.spec.ts:22-29` (flag helper) and `:117` (CSP/mount test)

**Interfaces:**
- Consumes: `setWysiwygComposer` writes the `features` override via `runPhp`; `prepare.sh` (also wrapped by `prepare-prodlike.sh`) seeds `$evidenceFeatures`, so one pin covers gate-a, a11y, and server-drafts runs.
- Produces: `setWysiwygComposer(enabled: boolean | null)` — `null` **removes** the `wysiwyg_composer` key so the `FeatureFlags` default applies. `a11y.spec.ts` is NOT touched (its wysiwyg scan already resets the flag to `false` in a `finally`, and the admin workstream has uncommitted edits there).

- [ ] **Step 1: Pin the evidence seed to the textarea baseline**

In `tests/browser/seed.php`, insert after the line `    'package_themes' => true, // Inc 4 (P5-03): package theme preview/activate/safe-mode/rollback evidence`:

```php
    'wysiwyg_composer' => false, // GA default-on (2026-07-02) but pinned OFF for the evidence baseline: gate-a + server-drafts journeys drive textarea.composer-input directly (fill/drop/toBeVisible), which a mounted Milkdown hides; the rich surface's browser evidence lives in wysiwyg-composer.spec.ts + the a11y.spec.ts scans, which toggle the flag per test
```

- [ ] **Step 2: Extend the flag helper with an unset mode**

In `tests/browser/wysiwyg-composer.spec.ts`, replace the helper (lines 22-29):

```ts
function setWysiwygComposer(enabled: boolean): void {
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};
$settings->set('features', $features);
`);
}
```

with:

```ts
function setWysiwygComposer(enabled: boolean | null): void {
  // null removes the override so the FeatureFlags DEFAULTS value applies —
  // used to prove the GA default mounts Milkdown without any features row.
  const mutation = enabled === null
    ? "unset($features['wysiwyg_composer']);"
    : `$features['wysiwyg_composer'] = ${enabled ? 'true' : 'false'};`;
  runPhp(`
$features = $settings->get('features', []);
if (!is_array($features)) { $features = []; }
${mutation}
$settings->set('features', $features);
`);
}
```

- [ ] **Step 3: Make the CSP/mount test prove the GA default**

In the same file, in `test('wysiwyg assets load under strict CSP without violations', ...)` replace line 117:

```ts
  setWysiwygComposer(true);
```

with:

```ts
  setWysiwygComposer(null); // no override: proves the GA default mounts Milkdown
```

All other tests keep their explicit `true`/`false` calls (the seed pin means earlier tests in the file have already written `false`; `null` here genuinely exercises the default).

- [ ] **Step 4: Commit (exact paths only)**

```bash
git add tests/browser/seed.php tests/browser/wysiwyg-composer.spec.ts
git commit -m "test(browser): pin gate-a textarea baseline; prove wysiwyg GA default with no override

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

(Playwright execution happens in Task 4 with the prepared e2e DB.)

---

### Task 3: Docs sweep — runbook, inventory, phase status, CLAUDE.md, COMPOSER.md

**Files:**
- Modify: `docs/runbooks/wysiwyg_composer.md` (full rewrite below)
- Modify: `docs/evidence/deploy-dark-features.md` (header date, wysiwyg row, Notes bullet)
- Modify: `PHASE_5_STATUS.md` (Status paragraph sentence)
- Modify: `CLAUDE.md` (feature-flags paragraph sentence)
- Modify: `COMPOSER.md` (§17.1 delivery note + changelog v0.6)

**Interfaces:**
- Consumes: exact current sentences quoted in each step (verify with grep before editing; if a sentence moved, match on content, not line number).
- Produces: no doc anywhere still claims `wysiwyg_composer` is deploy-dark.

- [ ] **Step 1: Rewrite the runbook banner and sections**

Replace the full contents of `docs/runbooks/wysiwyg_composer.md` with:

```markdown
# Runbook - WYSIWYG Composer (`wysiwyg_composer`)

`wysiwyg_composer` gates only the Milkdown editor layer. **Default-ON as of
2026-07-02** (graduated out of deploy-dark; fully reversible via the
`features` override). `rich_composer=false` remains the broad kill switch and
prevents all enhanced composer assets from loading. Follows the same
conventions as `docs/runbooks/polls.md` and `docs/runbooks/topic_workflow.md`.

> **Golden rule:** for any editor logic defect, **disable the
> `wysiwyg_composer` flag first** (the Milkdown bundle stops loading and the
> composer falls back to the enhanced Markdown textarea; posting keeps
> working), then investigate. Disabling is non-destructive - posts, drafts,
> and uploads are untouched because the Markdown `<textarea>` is the only
> submit source.

## Roll back (disable)

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=false; $r->set("features",$f);'
```

Existing posts and drafts remain Markdown and need no migration; the enhanced
Markdown textarea composer keeps serving.

## Re-enable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); unset($f["wysiwyg_composer"]); $r->set("features",$f);'
```

Removing the override restores the default (ON). Setting the key to `true`
explicitly is equivalent.

## Emergency Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["rich_composer"]=false; $r->set("features",$f);'
```

This disables `composer.js`, the suggestion picker, and the WYSIWYG bundle.
Server-rendered textarea posting remains available.

## Verify

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppMentionLinkRenderTest.php tests/Integration/Core/AppFeatureFlagTest.php
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"
```

Evidence covered by the browser gate:

- strict CSP asset load with no inline-script/style violations, exercised
  with **no features override** (proves the GA default mounts Milkdown)
- WYSIWYG new-topic submit, source-mode round trip, edit no-op preservation,
  preview parity, chips, internal URL paste, and mobile smoke
- textarea fallback for the `wysiwyg_composer=false` rollback
- axe scans for the enhanced toolbar, WYSIWYG surface, reference picker, and
  source-mode form

The gate-a screenshot suite intentionally pins `wysiwyg_composer=false` in
`tests/browser/seed.php`: those journeys capture the progressive-enhancement
textarea baseline (and drive `textarea.composer-input` directly), while this
spec owns the rich-surface evidence.

## Known Limits

Markdown remains canonical. Legacy Markdown may normalize after a user edits
through the rich surface, but a no-op edit must not rewrite the stored body.

Do not delete or hide the textarea in templates: it is the submit source,
source-mode editor, and no-JS fallback.
```

- [ ] **Step 2: Update the deploy-dark inventory**

In `docs/evidence/deploy-dark-features.md`:

(a) Replace the header date lines:

```markdown
**Date:** 2026-07-02 (graduation readiness ranking added; Phase 5 rows
reconciled with `PHASE_5_STATUS.md`)
```

with:

```markdown
**Date:** 2026-07-02 (`wysiwyg_composer` graduated; graduation readiness
ranking added; Phase 5 rows reconciled with `PHASE_5_STATUS.md`)
```

(b) Replace the `wysiwyg_composer` table row (currently beginning `| `wysiwyg_composer` | Optional Milkdown WYSIWYG layer`) with:

```markdown
| `wysiwyg_composer` | Milkdown WYSIWYG layer over the canonical Markdown textarea | **Graduated 2026-07-02 — now default-ON** (no longer deploy-dark; reversible via `features` override; `rich_composer=false` remains the emergency kill switch). Acceptance evidence: ADR 0013, runbook `docs/runbooks/wysiwyg_composer.md`, `AppComposerTest`, `AppComposerSuggestTest`, `AppMentionLinkRenderTest`, `MarkdownRoundTripTest`, `npm run check:wysiwyg`, browser `wysiwyg-composer.spec.ts` (CSP + GA-default mount with no override, source mode, no-op edit, preview parity, chips, internal URL paste, mobile smoke, textarea fallback), and `a11y.spec.ts` WYSIWYG toolbar/picker/source scans. Retained here for traceability. |
```

(c) In the Notes bullets (after the `tags`/`expanded_feeds`/`reputation_ledger` graduation bullet), add:

```markdown
- `wysiwyg_composer` graduated out of deploy-dark on 2026-07-02: its
  `FeatureFlags` default is now `true` (its acceptance evidence had already
  landed with PR #33). The browser-evidence seed intentionally pins it OFF so
  the gate-a screenshot journeys keep capturing the textarea
  progressive-enhancement baseline; `wysiwyg-composer.spec.ts` proves the GA
  default mounts Milkdown with no features override.
```

- [ ] **Step 3: Record the graduation in PHASE_5_STATUS.md**

Append to the end of the `**Status:**` paragraph (after `...remains gated until each workstream has release evidence.`):

```markdown
 The WYSIWYG composer stream that shipped alongside Inc 4 (PR #33) graduated on 2026-07-02: `wysiwyg_composer` is now default-ON (`docs/runbooks/wysiwyg_composer.md`); the gate-a browser-evidence seed pins the textarea baseline, and `wysiwyg-composer.spec.ts` proves the GA default with no override.
```

`**Last updated:**` already reads 2026-07-02 — leave it.

- [ ] **Step 4: Update the CLAUDE.md flags paragraph**

In `CLAUDE.md`, in the paragraph beginning `Every post-MVP subsystem is gated by a flag.`, replace:

```markdown
`group_dms`, `badge_rules`, `community_memory`, and `content_references` remain default OFF (deploy-dark).
```

with:

```markdown
`badge_rules` graduated to default-ON on 2026-07-02; `wysiwyg_composer` (Phase 5 composer stream) graduated to default-ON on 2026-07-02 (see `docs/runbooks/wysiwyg_composer.md`); `group_dms`, `community_memory`, and `content_references` remain default OFF (deploy-dark).
```

(The `badge_rules` clause fixes a pre-existing staleness: it graduated with PR #32 today — `AppFeatureFlagTest::test_phase4_gate_a_flags_have_expected_default_posture` asserts it default-ON. Verify with `grep -n 'badge_rules' docs/evidence/deploy-dark-features.md` that the inventory agrees; if its row says otherwise, drop the `badge_rules` clause from this edit and note the discrepancy in the final report instead.)

- [ ] **Step 5: Update COMPOSER.md §17.1 and changelog**

(a) In §17.1, replace:

```markdown
the optional Milkdown WYSIWYG adapter is deploy-dark behind `wysiwyg_composer` per ADR 0013.
```

with:

```markdown
the Milkdown WYSIWYG adapter (ADR 0013) shipped deploy-dark behind `wysiwyg_composer` and graduated to **default-ON on 2026-07-02**.
```

(b) In the changelog table (after the v0.5 row), add:

```markdown
| v0.6 | 2026-07-02 | `wysiwyg_composer` graduated to **default-ON** (GA 2026-07-02; reversible via `features` override; `rich_composer` remains the broad kill switch). §17.1 delivery note updated. Browser evidence split recorded: gate-a screenshots keep the textarea baseline via a seed pin; `wysiwyg-composer.spec.ts` proves the GA default mounts with no override. |
```

- [ ] **Step 6: Confirm no doc still claims deploy-dark**

Run: `grep -rn 'deploy-dark' --include='*.md' . 2>/dev/null | grep -i wysiwyg | grep -v superpowers | grep -v node_modules`
Expected: only historical/changelog phrasing ("shipped deploy-dark ... graduated") — no line asserting the flag *is* deploy-dark now.

- [ ] **Step 7: Commit (exact paths only)**

```bash
git add docs/runbooks/wysiwyg_composer.md docs/evidence/deploy-dark-features.md PHASE_5_STATUS.md CLAUDE.md COMPOSER.md
git commit -m "docs(composer): record wysiwyg_composer graduation to default-ON

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: Verification gates

**Files:** none modified (verification only; fixes discovered here belong to the task that owns the file).

**Interfaces:**
- Consumes: everything above; local MariaDB with `retroboards_test` (PHPUnit) and `retroboards_e2e` (Playwright); root `node_modules` for `check:wysiwyg`; `tests/browser/node_modules` for Playwright.

- [ ] **Step 1: Ensure the test DB is reachable**

Run: `mysql -e 'SELECT 1' 2>/dev/null || sudo systemctl start mariadb; php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); echo "db ok\n";'`
Expected: no connection error. (Local stack per dev-environment-setup: PHP 8.3 + MariaDB 10.11; there is no `rb-mariadb` docker container on this machine.)

- [ ] **Step 2: Full PHPUnit suite — catches any test that silently assumed the flag dark**

Run: `composer test 2>&1 | tail -6`
Expected: `OK (1268 tests, ~6619 assertions)` (assertion count may differ slightly from the rewritten tests; test count stays 1268 — both tests were rewritten in place, none added). Any failure = a test assuming the old default; fix it in Task 1's files and amend that commit.

- [ ] **Step 3: Deterministic bundle check (no bundle changes expected)**

Run: `npm run check:wysiwyg 2>&1 | tail -3`
Expected: vite build completes and `git diff --exit-code` passes (exit 0) — the graduation touches no client source.

- [ ] **Step 4: Targeted Playwright — wysiwyg spec (incl. new GA-default proof) + composer a11y scans**

Run:
```bash
cd tests/browser && npm run prepare-db && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer" 2>&1 | tail -6
```
Expected: all matched tests pass, including `wysiwyg assets load under strict CSP without violations` now running with **no** features override, and `wysiwyg kill switch keeps textarea composer fallback` proving the rollback path. (If `prepare-db` is not a defined script, use `bash prepare.sh` — check `tests/browser/package.json` scripts first.)

- [ ] **Step 5: Confirm nothing unrelated is staged and the branch is clean of admin-workstream files**

Run: `git status --porcelain | grep -v '^ M docs/evidence' | grep -v '^??' | grep '^[MARC]'`
Expected: empty output (no staged files remain; the admin workstream's unstaged modifications are untouched).

---

## Deferred follow-ups (recorded in the graduation spec; do not do them in this plan)

1. Full evidence-PNG regeneration once the admin/anti-abuse workstream lands (composer screenshots will then show Milkdown or the pinned baseline — decide which gate-a should capture, and make its `textarea.composer-input` interactions wysiwyg-aware if Milkdown-on is chosen).
2. The four theme-related fail-dark follow-ups from the PR #33 review (unrelated to this graduation).

---

# Part V — Review: WYSIWYG Composer and Slack-Style References

**Reviews:** `2026-07-02-wysiwyg-composer-design.md` (Part I above)
**Date:** 2026-07-02
**Method:** Cross-checked the design's claims against the authoritative docs
(`DECISIONS.md`, `COMPOSER.md`, ADR 0001) and the actual code/schema it references.

---

## Verdict

Solid, implementable direction that correctly preserves the canonical-Markdown
contract and kill switches, and whose factual claims about the current system
check out. Before an implementation plan, it needs to resolve **one critical
omission (strict CSP)** and **one over-stated acceptance criterion (byte-stable
round-trip)**, plus several medium interaction gaps.

---

## Claims verified as accurate

- Board `/c/{slug}`, topic `/t/{id}-{slug}`, user `/u/{username}`, tag
  `/tags/{slug}`, and `POST /composer/preview` all exist; `/composer/suggest`
  does not (`src/Core/App.php:1295-1317`).
- `content_references.target_type` is exactly `ENUM('board','thread','post')`
  (`database/migrations/0048_phase4_gate_a.php:374`).
- `ContentReferenceService::extract()` handles `/t/{id}`, `#p{id}`, `/c/{slug}`
  and nothing else — extending to `/tags/{slug}` is a clean additive change
  (`src/Service/ContentReferenceService.php:84-98`).
- The enum-migration + rollback pattern the design prescribes matches existing
  code exactly (e.g. `0068` `MODIFY target_type ENUM(...)`, `down()` deletes
  new-type rows then reverts the enum).
- `MentionParser` extracts raw `@username` for notifications and is unchanged by
  this work; mention **link-rendering does not exist today** (`src/Support/Markdown.php`
  does CommonMark + emoji only). The design is right that it is new work.
- Flags: `rich_composer`/`tags` on, `content_references`/`link_previews` off;
  `wysiwyg_composer` is genuinely new (`src/Core/FeatureFlags.php:40-62`).
- `SearchService` mandates a read gate; `MysqlSearchService` enforces it and has
  a 3-char FULLTEXT minimum (`src/Search/SearchService.php:11-12`,
  `src/Search/MysqlSearchService.php:29`).
- composer.js already provides toolbar/preview/drafts/uploads/GIPHY over a
  textarea, matching §0.

---

## High-priority issues

### 1. Strict CSP will fight the editor — and the design never mentions it

`src/Security/SecurityHeaders.php:41` emits `script-src 'self'; style-src 'self'`
with **no `'unsafe-inline'`**, and `CLAUDE.md` forbids inline styles/scripts
anywhere. Milkdown/ProseMirror routinely (a) inject runtime `<style>` elements
(Milkdown theme system, e.g. Crepe/Nord) and (b) set inline `style=""`
attributes — notably `prosemirror-tables` writes inline widths on `<col>`, and
the design lists **tables** as supported. Under `style-src 'self'` these are
blocked and the editor degrades with console violations.

**Action:** make this a first-class constraint in §3.4 — ship all editor CSS as
a committed stylesheet served from `'self'`, pick/patch a theme that does not
inject `<style>`, and audit table/gapcursor/decoration plugins for inline
styles. This is the biggest feasibility risk.

### 2. The "no-op load/save doesn't mutate canonical Markdown fixtures" criterion (§7.3) is unrealistic as written

Today storage is verbatim precisely because there is no editor
(`AppComposerTest`; `tests/Unit/Composer/MarkdownRoundTripTest.php` tests
render-fidelity, not editor serialization). A Markdown serializer normalizes
(`*`↔`_`, `-`↔`*` bullets, ATX vs setext, table whitespace, wrap width), so
byte-stability only holds for a corpus authored in Milkdown's own output form.
**Editing any pre-existing free-form post via WYSIWYG will rewrite its `body`
formatting**, inflating edit diffs and potentially flipping the "edited" marker
on an open-and-save no-op.

**Action:** reframe the guarantee as *semantic* (re-renders to identical
sanitized HTML) rather than byte-identical, and explicitly address editing
legacy Markdown in §5.1.

---

## Medium issues

### 3. `#` trigger collides with Markdown headings

`@` is unambiguous, but `#`/`## `/`### ` start headings. Slack has no headings,
so the analogy breaks. The design supports both headings *and* `#`-references
without specifying disambiguation (e.g. only trigger the picker when `#` is
immediately followed by a query char, not `# `). Call this out.

### 4. Heading clamping breaks WYSIWYG fidelity

The server clamps `#`→`<h2>` and `####`→`<h3>` at render
(`tests/Unit/Composer/MarkdownRoundTripTest.php:39-40`). A rich editor showing a
live H1 will post an H2 — a concrete "what you see isn't what you get." The
editor must clamp to the same H2/H3 set, or the design must accept the
discrepancy and lean on preview as the truth view (per §2.1).

### 5. The bridge interface can't express the upload placeholder swap

composer.js inserts `![uploading…](token)` then replaces it with the final URL
on completion (`public/assets/composer.js` ~811). In a rich surface that
placeholder is an image node, not a substring — `insertMarkdown` /
`replaceSelection` don't cover "find this pending token and replace it." The
bridge (§3.2) needs a replace-by-token/node method, or the upload flow needs
rethinking for the Milkdown adapter.

### 6. Two mention-detection code paths must stay in lockstep

Notifications use `MentionParser`'s grammar (`@` + 3–32 `[A-Za-z0-9_]`, not after
a word char/second `@`, code/pre stripped — `src/Support/MentionParser.php:24-49`).
The new render-time DOM linker (§4.4) must use the *same* grammar and exclusions,
or displayed links and notified users will diverge. Recommend sharing one grammar
definition. (Good news: the proposed DOM-walk mirrors the existing emoji walker
in `src/Support/Markdown.php:82-99`, so the pattern is proven.)

---

## Minor / clarifications

- **Default-off cards undercut the headline feature.** With `content_references`
  off (its default, per Non-Goals), `#`-inserted references render as **plain
  links, no cards** — the "inline chip" is editor-only. §6 hints at this; make it
  explicit that default rendered output is a link, cards require enabling the
  flag, and tag cards additionally require the new enum migration.
- **Typeahead responsiveness for topics/posts.** FULLTEXT `NATURAL LANGUAGE
  MODE` won't prefix-match and has the 3-char floor, so `#` topic/post results
  won't feel instant as-you-type. The prefix/LIKE plan for boards/tags/users
  (§4.2) is the right instinct; state that topic/post suggestions are
  whole-word-ish by design.
- **Suggestion endpoint:** specify a dedicated rate-limit policy in
  `config/config.php` (alongside `post`/`dm`/`upload`) plus client debounce.
  "CSRF-safe authenticated GET" is fine but redundant — a read-only GET needs no
  token; just ensure it never mutates.
- **Fallback-ladder divergence.** §1 lists Milkdown → Tiptap → **raw
  ProseMirror**, but `DECISIONS.md` §6 #1 and `COMPOSER.md` §14.2 lock Milkdown →
  Tiptap/ProseMirror → **CodeMirror/ink-mde**. Per the doc's own precedence note,
  DECISIONS wins — either keep the CodeMirror fallback or record the change.

---

## Process / doc gaps (this repo is spec-driven)

- This design reverses ADR 0001's *accepted* decision (it chose the textarea to
  avoid a build step, a client serializer, and round-trip drift — all three now
  reintroduced). ADR 0001 left a revisit trigger, so it is legitimate, but it
  should land as a **new ADR superseding/amending 0001**, not just a spec.
- Follow-on doc updates to enumerate in §9: **COMPOSER.md** (§6.3 currently
  frames references as P2 "nice-to-have"), **SCHEMA.md** after the enum migration
  (shape + §9 changelog + version bump, per `CLAUDE.md`), and a **runbook** for
  `wysiwyg_composer` like the existing flag runbooks.
- Heads-up for implementation: `CLAUDE.md` says "next migration 0049," but the
  tree is already at **0068** — the tag-enum migration is `0069`.

---

## Recommendation

The increment ordering in §9 is sound (backend contract before editor). Add two
explicit spikes ahead of the Milkdown adapter:

1. **CSP-compatibility spike** — can Milkdown run with zero inline styles/scripts
   under `style-src 'self'`?
2. **Serializer-fidelity spike** — run against the real stored-post corpus to
   define the round-trip guarantee as semantic-equality.

Those two answers most affect whether this ships as designed.

---
---

# Second-pass review (2026-07-02, after fold-in)

**Reviews:** the updated doc (Status: "review folded in").
**Method:** verified each first-round finding was folded faithfully, independently
re-verified the doc's factual claims against the code, then hunted for gaps the
first pass missed.

## Fold-in verification

All ten first-round findings are present and accurately stated in the doc (CSP as
locked decision + §3.4 + smoke test + acceptance criterion + spike; semantic
round-trip in §1/§5.1/§7.1/§7.3; `#`-trigger disambiguation §2.3; H2/H3 clamp
§2.1; `replacePendingUpload` §3.2/§5.2; mention-grammar lockstep §4.4; default-off
cards §2.3/§6; debounce + whole-word typeahead + dedicated rate policy §4.2;
fallback ladder now per DECISIONS §6 #1 / COMPOSER §14.2; ADR-first + doc
follow-ons §0/§9).

Spot-re-verified claims — all check out: flags (`src/Core/FeatureFlags.php:40-66`),
routes (`src/Core/App.php:1305-1317`), CSP string (`src/Security/SecurityHeaders.php:41`),
mention grammar + cap (`src/Support/MentionParser.php:17-24`; usernames are exactly
`[A-Za-z0-9_]` 3–32, `src/Service/AuthService.php:124`, so every valid handle is
mention-matchable), composer.js selectors/placeholder/conflict panel
(`public/assets/composer.js:438,811,311`), layout gating on `rich_composer`
(`templates/layout.php:75`), `composer_preview` rate-policy precedent
(`config/config.php:204`), tags `visibility ENUM('public','hidden')` + `is_enabled`
(`0048_phase4_gate_a.php:152-153`), and `/tags/` vs `/t/` regex non-collision in
`ContentReferenceService::extract()`.

Two notes for the implementation plan:

- Numbering as of today: the superseding ADR is **0013**; the tree is at
  migration **0070**, so the tag-enum migration is **0071+** (the first review's
  "0069" is already stale — §9's "follow the latest tree" wording is the right call).
- ADR 0001's "supported syntax" list omits tables/task lists, but the server
  renderer genuinely enables `TableExtension` + `TaskListExtension`
  (`src/Support/Markdown.php:39-40`). The doc's corpus is correct; the *ADR text*
  is stale — ADR 0013 should restate the real supported set.

## New high-priority issues

### A. Anonymous authorship can leak through the suggestion service

`posts.is_anonymous` exists (`database/migrations/0009_posts.php:18`) and masking
happens only at render time via `mask_author` (`src/Support/helpers.php:47`). The
design never mentions anonymity, and it has two leak paths:

- §2.2 ranks "participants in the current thread" first in the `@` picker. A user
  who participated only anonymously surfacing at the top of that thread's picker
  deanonymizes them. "Recent **visible** participants" (§2.2) does not obviously
  exclude anonymous posts — visible-but-masked is exactly the dangerous case.
- §4.2's post suggestions display "author". That metadata must pass through
  `mask_author` semantics, never the raw username.

**Action:** add to §4.3 — participant/recency ranking signals must exclude
anonymous posts, and post-suggestion author metadata must use masked authorship.
Add both to the §7.1 test list.

### B. `context`/`target_id` must be read-gated before shaping ranking, not just per result

§4.2's "read-gated per result" filters what is *returned*, but the *ranking
signal* is computed from `target_id`. A forged `target_id` naming a private
thread / hidden board / other people's DM turns result **order** into an oracle
for "who participates in {id}" — every ranked user is individually visible, so
per-result gating never fires.

**Action:** specify that the server verifies the requester can read the context
target (thread/board via `BoardPolicy` + membership, DM via participant check)
before applying contextual ranking, and silently falls back to global ranking
otherwise. Define `target_id` semantics per `context` value while at it. Test:
an inaccessible `target_id` returns results identical to no-context.

## New medium issues

### C. Pin down where the mention linker runs

§4.4's "displayed post/DM HTML" is ambiguous, and the natural implementation
over-applies: the cited emoji-walker precedent runs *inside* `Markdown::render()`
(`src/Support/Markdown.php:58-107`), whose consumers include not just
posts/DMs/preview but **bios** (`ProfileController`) and **community-memory
summaries**. A render()-level hook links mentions everywhere. Two more unstated
consequences: `body_html` is a write-time cache, so existing posts gain links
only when re-rendered (edit/approve) — there is no backfill mechanism (`repair`
recomputes counters, not `body_html`); and baked links go stale on username
rename.

**Action:** enumerate the surfaces (posts, DMs, and preview in — COMPOSER.md's
preview spec requires "mentions linked"; bios/summaries out, or explicitly in),
state the write-time/no-backfill consequence, and accept or address rename
staleness.

### D. Decide whether the pickers also serve the textarea adapter

The suggest endpoint is gated on `rich_composer`, not `wysiwyg_composer` — but
the doc only ever mounts pickers on the rich surface. COMPOSER.md §6.1/§17.1
promised mention **autocomplete** on the Phase 2/3 composer, and composer.js has
none today (verified: no mention/suggest code). The bridge's
`insertMarkdown`/`replaceSelection` makes a GitHub-style textarea picker cheap.

**Action:** state explicitly either (a) pickers are bridge-level and mount on
both adapters — discharges the outstanding COMPOSER.md promise, keeps UX parity
when `wysiwyg_composer` is off or killed, and lets increment 8 land independent
of the Milkdown adapter — or (b) pickers are WYSIWYG-only and the COMPOSER.md
autocomplete promise remains an open item. (a) is recommended.

### E. Make the CSP spike measure enforcement, not DOM shape

Under CSP, `style-src 'self'` blocks runtime `<style>` elements and
parser/`setAttribute` `style=""` writes — but **not** CSSOM property writes
(`el.style.width = …`) or constructable stylesheets, even though those serialize
as inline style attributes in the DOM. prosemirror-tables sets column widths via
CSSOM (`updateColumnsOnResize`), so tables may pass unpatched; conversely an
`adoptedStyleSheets` theme passes CSP while violating the repo's
committed-static-CSS rule. As folded, §1/§3.4's blanket ban on "inline `style=\"\"`
attributes" would wrongly condemn CSP-legal CSSOM styling.

**Action:** restate the constraint as (1) zero CSP violations under the real
header — which the §7.2 smoke test already asserts — and (2) no runtime-injected
CSS *rules* regardless of CSP legality. The spike should assert on violation
reports, not on grepping the DOM for style attributes.

## Minor

- **Pasted-URL chips (§2.4):** the chip displays `#general`/title while the
  stored link text stays the URL — with cards off, the rendered post shows a raw
  URL where the editor showed a chip. Either rewrite link text on conversion or
  render pasted chips with URL text; specify which.
- **Mention cap UX:** `MentionParser::MAX = 10` — an 11th mention chip looks
  identical but never notifies. Mark over-cap mentions in the editor, or state
  that the silent cap is accepted.
- **Flag-gating tests, named per repo convention:** `rich_composer=false` ⇒
  `GET /composer/suggest` 404s (AppFeatureFlagTest style); `wysiwyg_composer=false`
  ⇒ layout emits no editor bundle (server-side assertable; `templates/layout.php:75`
  pattern).
- **Committed-bundle provenance:** ADR 0013 should state how the committed
  `public/assets` bundle is reviewed and rebuilt (lockfile-pinned versions +
  documented build command; ideally a reproducible-rebuild check).
- **Bundle weight:** consider lazy-loading the editor (dynamic import on composer
  focus) so thread pages don't pay Milkdown's cost before the user writes.
- FYI, no change needed: `ContentReferenceService::capture()` already runs for
  `dm_message` sources (`src/Service/DirectMessageService.php:246`), so tag
  references will flow from DMs automatically once `extract()` learns `/tags/`.

## Second-pass verdict

The fold-in is faithful and the doc is implementable as sequenced. Fold **A and
B** in before writing the ADR/implementation plan — both are one-paragraph spec
changes but security-relevant. C–E are precision fixes that will keep the spikes
and the linker implementation from wrong turns.
