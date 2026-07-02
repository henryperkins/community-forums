# Design: WYSIWYG Composer and Slack-Style References

**Date:** 2026-07-02
**Status:** Approved design (brainstorming output) - pending implementation plan.
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

---

## 1. Locked Decisions

- **Editor model:** true WYSIWYG, not split-preview-only and not merely styled Markdown.
- **Engine recommendation:** Milkdown first, because it is Markdown-native and matches
  ADR 0001's revisit path. Tiptap remains fallback if Milkdown blocks mention/reference
  UX or mobile behavior. Raw ProseMirror is a last-resort fallback.
- **Canonical storage:** Markdown stays canonical. The enhanced editor serializes to the
  underlying `<textarea name="body">` on every change and before submit.
- **Fallback:** the server-rendered textarea remains usable with JS disabled or with the
  rich editor disabled.
- **Kill switches:** `rich_composer=false` disables all enhanced composer JS as today.
  A new `wysiwyg_composer` flag controls only the Milkdown layer; when it is false, the
  current Markdown-enhanced composer remains available.
- **Reference trigger:** `@` searches users. `#` is a unified reference search for boards,
  tags, topics, and posts.
- **Reference storage:** rich chips serialize to Markdown forms the server already
  understands where possible.

---

## 2. User Experience

### 2.1 Writing

The composer renders as a rich multiline editor. Users can use toolbar buttons and normal
shortcuts for bold, italic, strike, code, links, headings, lists, blockquotes, spoilers,
tables, images, and undo/redo.

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

Choosing a user inserts a mention chip displayed as `@username`. The Markdown remains the
raw token `@username` so `MentionParser` and existing mention notification behavior keep
working. The rendered post should link valid mentions to `/u/{username}` outside code
blocks while preserving the existing anti-spam cap and blocked-user notification rules.

### 2.3 Unified `#` References

Typing `#` opens a grouped reference picker. The same query can return:

| Type | Display in picker | Markdown serialization |
|---|---|---|
| Board | `#general` plus board name | `[#general](/c/general)` |
| Tag | `#release-notes` plus tag name | `[#release-notes](/tags/release-notes)` |
| Topic | topic title plus board | `[Topic title](/t/123-topic-title)` |
| Post | topic title plus excerpt, author, and time | `[Post in Topic title](/t/123-topic-title#p456)` |

The picker is keyboard-first: arrow keys move, Enter/Tab selects, Escape closes. It also
works as a bottom sheet on mobile.

### 2.4 Paste Behavior

Pasting an internal board, tag, topic, or post URL converts it into a chip in the rich
surface but leaves the Markdown as a normal link. Pasting external URLs remains normal
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
- `focus(): void`
- `onChange(callback): void`
- `setDisabled(disabled: boolean): void`
- `destroy(): void`

The existing textarea path becomes a `TextareaComposerAdapter`. Milkdown becomes a
`MilkdownComposerAdapter`. Shared composer behavior uses the bridge instead of directly
reading and writing `ta.value`.

### 3.3 Bundling

Milkdown requires a JavaScript build step. The production request path remains static:
the built bundle is committed under `public/assets/`, and deployment only serves static
assets as it does today.

The repo can add a root development-only package setup for the editor build. The browser
test package under `tests/browser` remains separate unless the implementation plan
chooses to consolidate package management deliberately.

### 3.4 CSS and Accessibility

The rich editor must fit the existing composer visual system in `public/assets/app.css`.
It should not introduce a decorative document editor look. It should feel like the forum's
input surface.

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

Add a CSRF-safe authenticated GET endpoint:

`GET /composer/suggest?trigger=@|#&q=...&context=thread|reply|dm|new_thread|edit&target_id=...`

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
- rate-limited by user,
- read-gated per result,
- capped to a small response, such as 8 users or 5 results per reference group,
- safe for short queries by returning board/tag/user results through indexed prefix/LIKE
  queries and using full-text search for thread/post results only when the query length
  meets the search service minimum.

### 4.3 Suggestion Service

Add `ComposerSuggestionService` to keep controller logic thin.

Responsibilities:

- user suggestions with visibility and block-aware mention eligibility,
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

### 5.2 Edit

1. User edits in the rich surface.
2. Milkdown serializes the document to Markdown on each debounced change.
3. The bridge writes that Markdown into the textarea.
4. Existing autosave, server drafts, counter, preview, and upload logic read the same
   Markdown through the bridge.

### 5.3 Suggest and Insert

1. User types `@` or `#`.
2. Client requests `/composer/suggest`.
3. Server returns read-gated suggestion rows with canonical Markdown.
4. Client inserts a rich chip and updates the hidden textarea with the serialized
   Markdown.

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
  stays off.
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
- mention rendering links valid users and ignores code/pre blocks.
- suggestion endpoint gates private boards/topics/posts.
- suggestion endpoint returns boards, tags, topics, and posts for `#`.
- `ContentReferenceService` extracts and resolves `/tags/{slug}`.
- tag reference card rendering respects feature flags and visibility.
- `rich_composer=false` keeps the textarea fallback and loads no WYSIWYG assets.

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
- `@` user picker keyboard flow,
- `#` picker with board, tag, topic, and post selections,
- pasted internal URL becoming a chip,
- no-JS or kill-switch textarea fallback,
- axe checks for editor toolbar and suggestion picker.

### 7.3 Acceptance Criteria

- A no-op load/save of supported Markdown does not mutate canonical Markdown fixtures.
- Users can create posts without knowing Markdown syntax.
- `@` mention chips notify the same recipients as raw `@username` text.
- `#` can search and insert boards, tags, topics, and posts.
- Inserted references remain useful when WYSIWYG is disabled because they are stored as
  Markdown links.
- The server preview and final rendered post match for supported syntax.
- Operators can roll back to the current Markdown-enhanced composer without data
  migration.

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

1. Suggestion API and tag reference backend support.
2. Mention link rendering.
3. Composer bridge extraction around the existing textarea behavior.
4. Milkdown bundle and adapter behind `wysiwyg_composer`.
5. Reference chips and pickers.
6. Browser/a11y evidence and rollout flag documentation.

Do not start with the editor bundle before the backend suggestion/reference contract is
tested. The editor should consume a stable Markdown insertion contract instead of baking
database and routing assumptions into client code.
