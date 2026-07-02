# RetroBoards — Composer (Unified Input) Design

**Status:** v0.5 · **Owner:** Henry (lakefrontdigital.io) · **Last updated:** 2026-07-02
**Companion to [DESIGN.md](DESIGN.md), [ADMIN.md](ADMIN.md), [USER.md](USER.md).** This doc owns **the composer** — the single text-input component used to write content. Same conventions (P0/P1/P2; `Done (mockup)` / `Planned` / `Live`; PHP/MySQL, server-rendered + progressive enhancement).

## Scope

One component, three mounts. The composer is used in exactly three places, and **all three expose an identical feature surface**:

1. **New Thread** — start a topic in a board.
2. **Reply** — post in an existing thread.
3. **Message User** — write a direct message.

Everything the input can do — formatting, shortcuts, mentions, media, drafts, validation — is **the same in all three**. The only differences are the thin wrapper around the input (a title field for New Thread, a recipient for DM) and a few context-scoped limits. This doc specifies the input itself, exhaustively, and defines that unified contract (§15).

> **Editing model decision:** **WYSIWYG over canonical Markdown** — the enhanced surface is Milkdown, mounted only when `rich_composer` and `wysiwyg_composer` are both enabled. The server-rendered `<textarea>` remains the submit source, source-mode editor, and no-JS/kill-switch fallback. This resolves DESIGN.md open question #2 and is recorded in ADR 0013.

## Contents

1. Overview & Principles
2. The Three Contexts (one component, three mounts)
3. Editing Model — WYSIWYG over Canonical Markdown
4. Toolbar & Formatting Controls
5. Keyboard Shortcuts
6. Mentions, Emoji & References
7. Attachments, Images & Media
8. Drafts & Autosave
9. Submission & Feedback
10. Preview
11. Validation, Limits & Safety
12. Accessibility & i18n
13. Responsive & Mobile
14. Architecture & Implementation
15. Unified Feature-Surface Matrix
16. Cross-Doc Deltas & Schema
17. Phasing & Open Questions
18. Changelog

---

## 1. Overview & Principles

The composer is the most-used surface in the product — every thread, reply, and DM passes through it. Getting it right (and identical everywhere) means one mental model for users and one code path for us.

**Principles**

1. **One surface, learned once.** Whatever you can do in a reply, you can do when starting a thread or DMing someone. No "this box is different."
2. **Rich for the eye, Markdown for the record.** Formatting appears live as you type; what's stored is portable Markdown (DESIGN.md §9.5 render/sanitise pipeline).
3. **Fast for keyboards, discoverable for mice.** Every action has a shortcut *and* a visible control; the toolbar shows active state.
4. **Never lose work.** Drafts autosave continuously and survive reloads, navigation, and crashes.
5. **Safe by construction.** Stored Markdown is rendered through an allowlist sanitiser; no raw HTML or scripts; new-user and per-board limits curb spam.
6. **Resilient / progressively enhanced.** Without JS it degrades to a plain `<textarea>` that accepts Markdown and renders server-side; with JS it upgrades to the rich surface.
7. **Accessible.** Fully keyboard-operable, screen-reader labelled, motion-respecting.

## 2. The Three Contexts (one component, three mounts)

A single `Composer` component is mounted with a small **context config**; the input and its entire feature set are identical. Only the wrapper and a few limits vary.

| Aspect | New Thread | Reply | Message User (DM) |
|---|---|---|---|
| **The input box & all its features** | **Identical** | **Identical** | **Identical** |
| Wrapper adds | A **Title** field above + **board picker** | Sticky-to-bottom; optional "replying to" / quote chip | **Recipient** (to whom) + conversation header |
| Placeholder | "Start a new topic in #board…" | "Reply to {thread}…" / "Message #board…" | "Message @user…" |
| Submit target | `POST /threads` (creates thread; first post = OP) | `POST /t/{id}/reply` | `POST /dm/{conversationId}/messages` |
| On success | Navigate to the new thread | Optimistic insert into the stream | Optimistic insert into the DM |
| Who can use it | Members (board `post_min_role`); guests see join-bar | Members (thread not locked); guests see join-bar | Members; gated by recipient's "Allow DMs" + block list (USER.md §4.7) |
| Context-scoped limits | Title required; board-level image/limit settings | Locked thread disables it | DM length cap; no thread-only affordances |

Everything in §3–§13 applies to **all three** unless a row above says otherwise. The same component is also reused for **editing** an existing post/message (§9.6).

## 3. Editing Model — WYSIWYG over Canonical Markdown

### 3.1 How it behaves

- With `wysiwyg_composer` enabled, you type in a **Milkdown WYSIWYG surface**. Markdown shortcuts still work (`## ` for headings, `- ` for lists, `> ` for quotes), but the visible editor is rich text rather than a raw Markdown textarea.
- The underlying `<textarea name="body">` stays in the form. Milkdown serializes back to that textarea after edits and before submit; source mode exposes the textarea directly and can switch back to rich text.
- With no JavaScript, an unsupported adapter, `wysiwyg_composer=false`, or `rich_composer=false`, the same form remains a plain Markdown `<textarea>`. No post, draft, or upload path depends on the WYSIWYG bundle.
- On submit we store Markdown (`posts.body`) and a cached sanitised HTML render (`posts.body_html`). Editing later re-hydrates from stored Markdown. A no-op edit must not rewrite legacy Markdown solely because an editor serializer would normalize it.
- The round-trip contract is semantic parity against the server-rendered sanitized HTML. Byte-stable Markdown is required only for fixtures intentionally authored in the editor's canonical output form.

### 3.2 Supported syntax (the canonical Markdown set)

| Element | Markdown | Live trigger | Phase |
|---|---|---|---|
| Bold / Italic / Strikethrough | `**`, `*`, `~~` | inline + toolbar + shortcut | P0 |
| Inline code | `` `code` `` | inline | P0 |
| Code block (+ language) | ```` ```lang ```` | ``` at line start | P1 |
| Blockquote | `> ` | `> ` at line start | P0 |
| Bulleted / numbered list | `- ` / `1. ` | at line start; auto-continue | P0 |
| Task list | `- [ ] ` | at line start | P2 |
| Link | `[text](url)` | toolbar / `Cmd/Ctrl+K`; auto-link on paste | P0 |
| Image | `![alt](url)` | upload / paste / drag (§7) | P1 |
| Headings (limited) | `## `, `### ` | at line start | P1 |
| Horizontal rule | `---` | at line start | P2 |
| Spoiler | `::spoiler …::` | toolbar | P1 |
| Mention | `@username` | `@` picker (§6) | P1 |
| Emoji | `:shortcode:` | `:` picker (§6) | P1 |
| Table | pipes | toolbar / paste | P2 |

> Heading levels are intentionally limited in posts (e.g. only `##`/`###`) so a post can't impersonate page chrome. Configurable per ADMIN settings.

### 3.3 Paste handling

- **Rich text / HTML paste** → converted to Markdown (so pasting from a doc or webpage yields clean Markdown, not raw HTML).
- **Image paste** (clipboard) → uploaded and inserted as an image (§7).
- **URL paste over a selection** → wraps the selection in a link.
- **Plain URL paste** → auto-links; optional unfurl/embed at render time (§7).
- **Code paste** (multi-line, looks like code) → offered as a fenced code block.

### 3.4 Smart typing

Auto-continue lists and blockquotes on Enter (empty item exits the list); auto-close code fences; never apply "smart quotes"/typographic substitutions inside code; `Tab`/`Shift+Tab` indent/outdent list items (and never traps focus — `Esc` then `Tab` to leave). Undo/redo is grouped sensibly (per word / per transform).

## 4. Toolbar & Formatting Controls

A compact toolbar above (desktop) or in a bar above the keyboard (mobile). Every control mirrors a shortcut (§5) and shows **active state** when the selection already has that format.

| Group | Controls |
|---|---|
| Text | **Bold**, *Italic*, ~~Strikethrough~~, `Inline code` |
| Blocks | Code block, Blockquote, Bulleted list, Numbered list, Heading, Spoiler |
| Insert | Link, Image/Attach, Emoji, **@** Mention, Table (P2) |
| Right side | Preview toggle, Send |

- **Responsive overflow:** on narrow screens the toolbar shows an essential set (Bold, Italic, Link, Image, Emoji, @) and tucks the rest behind a **“+”** overflow menu.
- **Active-state reflection:** toggling is idempotent (B on bold text un-bolds it).
- Controls are real buttons (keyboard-focusable, labelled), not icon-only without `aria-label`.

## 5. Keyboard Shortcuts

Identical in all three contexts. `Cmd` on macOS = `Ctrl` on Windows/Linux.

| Action | Shortcut |
|---|---|
| **Send** | `Enter` (default) — or `Cmd/Ctrl+Enter` if the user prefers Enter-inserts-newline (USER.md §4.5) |
| New line | `Shift+Enter` (always) |
| Blur / cancel focus | `Esc` (with unsaved text, `Esc` blurs but the draft is kept; an explicit **Discard** is required to delete) |
| Bold / Italic / Strikethrough | `Cmd/Ctrl+B` · `Cmd/Ctrl+I` · `Cmd/Ctrl+Shift+X` |
| Inline code / Code block | `Cmd/Ctrl+Shift+C` · `Cmd/Ctrl+Alt+C` |
| Link (insert/edit) | `Cmd/Ctrl+K` |
| Bulleted / Numbered list | `Cmd/Ctrl+Shift+8` · `Cmd/Ctrl+Shift+7` |
| Blockquote | `Cmd/Ctrl+Shift+9` |
| Indent / outdent list item | `Tab` / `Shift+Tab` |
| Mention / Emoji pickers | type `@` / `:` |
| Preview toggle | `Cmd/Ctrl+Shift+P` |
| Undo / Redo | `Cmd/Ctrl+Z` · `Cmd/Ctrl+Shift+Z` |
| Edit your last post (when composer empty, in a thread) | `↑` |

**Global (when not typing in the composer):** `c` / `n` starts a New Thread; `r` focuses the Reply composer on the open thread; `Cmd/Ctrl+K` opens **search** (command palette).

> **Reconciliation:** DESIGN.md §6.1/§6.5 earlier listed `Cmd/Ctrl+K` as "focus the composer/search." This doc makes it precise: **inside** the composer `Cmd/Ctrl+K` inserts a link (editor standard); **outside** it, `Cmd/Ctrl+K` is search and `r`/`c` focus the composer. DESIGN.md is updated to match (§17 of this doc).

## 6. Mentions, Emoji & References

### 6.1 @mentions

- **Trigger `@`** → an autocomplete popover. Fuzzy-matches username and display name; **ranked**: participants in this thread/board first, then recent contacts, then everyone. Keyboard: `↑/↓` move, `Enter`/`Tab` accept, `Esc` dismiss.
- Inserts a **mention token** that renders as a link to `/u/{username}`. On submit, mentions are parsed server-side and create a **notification** for each mentioned user — respecting their notification prefs and **block list** (a blocked user is not notifiable; USER.md §4.7).
- **Visibility-gated:** you can only mention users who can see the context (e.g. not someone outside a private board).
- **Anti-spam:** a per-post mention cap (e.g. ≤10); excess mentions are stripped or flagged (ADMIN.md §3.8).
- *Priority:* **P1**; *delivery:* **Phase 2** (DESIGN.md had @mentions deferred; the composer is where they live — autocomplete + parse-on-submit notifications land in Phase 2, then ride the unified rich composer in Phase 3). _(P1 is a priority tier, not Phase 1 — DECISIONS §2.)_

### 6.2 Emoji

- **Trigger `:`** → shortcode autocomplete (`:smile:`), plus a full **emoji picker** button. Shows recent/frequent first. Inserts a unicode emoji (canonical) or shortcode.
- **Custom/server emoji** (admin-managed) are **P2**, via the plugin/integration system (ADMIN.md §8).

### 6.3 References (P2)

- **`@`** opens the user suggestion picker and inserts canonical `@username` text. Server rendering links mentions when the `mentions` flag is enabled, respecting the cached HTML model.
- **`#`** opens the reference picker for readable boards, visible tags, topics, and posts. Accepted suggestions insert canonical Markdown links such as `[#general](/c/general)`, `[#release-notes](/tags/release-notes)`, topic links, or post anchors.
- In WYSIWYG mode, accepted `@`/`#` suggestions display as inline chips while still serializing to canonical Markdown/text in the textarea. The textarea adapter uses the same suggestion API and insertion contract.
- Pasting a same-origin board/tag/topic/post URL into the WYSIWYG surface rewrites it to the same canonical Markdown link when it can be resolved; otherwise it remains a normal URL.
- The `#` trigger intentionally ignores Markdown heading starts such as `# `.

## 7. Attachments, Images & Media

Identical across all three contexts (DMs can attach too); a board may tighten limits.

| Capability | Behaviour | Phase |
|---|---|---|
| **Image upload** | Drag-drop onto the composer, paste from clipboard, or file-picker. Multiple images; per-image **upload progress**, thumbnail, **alt-text** field, remove/reorder. Inserts `![alt](url)`. | P1 |
| Limits | Allowed types (png/jpg/webp/gif); max file size; **max images per post** (board-level + new-user gate, ADMIN.md §3.8 / §4.2). | P1 |
| Storage & processing | Auto-resize + thumbnails; stored via the media/CDN integration (ADMIN.md §8.7); served from a non-executable path. | P1 |
| Moderation | Uploaded media is content — reportable and mod-removable (ADMIN.md §3). | P1 |
| **File attachments** (non-image) | pdf/zip/etc. with type/size validation, served safely. | P2 |
| **Link unfurl / embeds** | Pasting a URL can render a rich preview (oEmbed/link card) at **render time** via the `post.render` filter (ADMIN.md §8.2). Server-side fetch is **SSRF-guarded** and privacy-noted; per-board toggle; author can remove the embed. | P2 |

All media references live in the Markdown as standard image/link syntax; an `attachments` table (§16) tracks the uploaded files for ownership, limits, and moderation.

## 8. Drafts & Autosave

- **Continuous, debounced autosave** to `localStorage`, keyed per context so multiple drafts coexist:
  - Reply → `draft:thread:{threadId}:{userId}`
  - New thread → `draft:newthread:{boardId}:{userId}` (body **and** title)
  - DM → `draft:dm:{conversationId}:{userId}`
  - Edit → `draft:edit:{postId}:{userId}` (kept separate so editing never clobbers a fresh reply draft)
- **Restored on mount**, **cleared on successful send.** Survives reload, navigation, and crashes. A subtle "Draft saved" indicator confirms.
- The **"Drafts"** sidebar quick-filter (DESIGN §5.2/§6.5) lists active drafts with their context + a preview; click to resume in the right composer.
- **Signed-out** users' drafts still save locally; after sign-in, offer to restore the text into the composer.
- **Server-side draft sync** across devices (a `drafts` table) is **P2**; v1 is local-only. When it lands, a local/remote divergence prompts a choose-which.

## 9. Submission & Feedback

### 9.1 Pre-submit validation

Before a send is allowed: non-empty after trim (or has an attachment); within length limits (§11); required wrapper fields present (title + board for New Thread; recipient for DM); passes content filters; the user is authed, not banned/suspended, allowed to post here (board `post_min_role`, thread not locked, DM permitted), and not rate-limited.

### 9.2 States

`empty` (Send disabled) → `typing` → `sending` (disabled + spinner, **double-submit guarded** via a transient idempotency key) → `success` (clear input, clear draft) or `error` (keep text, surface the reason, offer retry).

### 9.3 Optimistic flow

- **Reply / DM:** the message inserts immediately as "sending", reconciles on server ack, and on failure **rolls back with a toast** and restores the text (nothing lost).
- **New Thread:** show progress, then **navigate to the created thread** on success (its first post is the OP).

### 9.4 Error taxonomy

| Error | What the user sees |
|---|---|
| Network | Inline "couldn't send — retry"; text preserved. |
| Not authed | The **join-bar** / sign-in CTA (no redirect). |
| Banned / suspended | Read-only state with a clear message (ADMIN.md §1.2). |
| No permission | "You can't post in this board/thread." |
| Rate-limited | "You're posting too fast — try again in {n}s." |
| Content blocked (word/link filter) | Inline explanation of what's blocked (ADMIN.md §3.8). |
| Held for approval | "Your post will appear after a moderator approves it." |
| Too large | Length/attachment-size message with the limit. |
| Thread locked mid-compose | Composer disables with a notice; the draft is kept. |

### 9.5 Signed-out

The composer slot renders the **join-bar** ("Sign in to reply/post") for New Thread & Reply — no redirect, draft preserved. DMs require auth to open at all.

### 9.6 Edit mode (same composer, reused)

Editing an existing post or message mounts the **same** composer, pre-hydrated from the stored Markdown, with **"Save changes"** instead of Send. It respects the board **edit window** (USER.md §3.2 / ADMIN.md §4.2), stamps an **"edited"** marker, and preserves edit history for moderation (ADMIN.md §3.3). Same validation and sanitisation path as a new post.

## 10. Preview

The WYSIWYG surface is the inline editing view, not the source of truth for final rendering. The explicit **Preview toggle** renders the content **exactly as it will appear** once posted — final sanitised HTML, spoilers collapsed, mentions linked, emoji rendered, embeds shown. It uses the same server render path as live posts and remains the final truth for parity checks. Desktop: a toggle (optionally split-view); mobile: a full-screen preview.

## 11. Validation, Limits & Safety

| Control | Default | Configured in |
|---|---|---|
| Min length | non-empty (or has attachment) | core |
| Max length | ~20,000 chars (post) / ~4,000 (DM) | ADMIN settings |
| Live character counter | appears near the limit | core |
| Max images / attachments per post | board-level + new-user gate | ADMIN.md §4.2 / §3.8 |
| Link limit for new accounts | capped/blocked until a threshold | ADMIN.md §3.8 |
| Word & link filters | block / flag / hold per rule | ADMIN.md §3.8 |
| Rate limit / new-user throttle | posting-frequency caps | ADMIN.md §3.8 |
| Sanitisation | allowlist render; no raw HTML/scripts | DESIGN.md §9.5 |
| Banned / suspended | composer read-only / replaced | ADMIN.md §2.4 |
| Idempotency | one logical submit = one post (short-lived/transient dedupe; §14.3) | core (§14) |

Safety notes: the rich surface **never** stores HTML — Markdown only, rendered server-side through the allowlist; pasted content is sanitised on the way in; spoilers/mentions/embeds are resolved at render time, not stored as live markup.

## 12. Accessibility & i18n

- **Fully keyboard-operable:** every toolbar action has a shortcut and is tab-reachable; the rich surface never traps focus (`Esc` then `Tab` leaves).
- **Screen readers:** the editor exposes a labelled multiline textbox role; formatting changes and upload progress/errors announce via polite live regions; the mention/emoji popovers are ARIA comboboxes (`aria-expanded`, `aria-activedescendant`). A **plain-`<textarea>` fallback** mode is always available (and is what no-JS users get).
- **Focus management:** opening a picker preserves the caret; closing returns focus; toolbar buttons don't steal the selection.
- **Visible focus, AA contrast** (theme tokens), and **`prefers-reduced-motion`** respected (no animated inserts).
- **Paste as plain text:** `Cmd/Ctrl+Shift+V`.
- **i18n / RTL:** placeholders and labels localisable; right-to-left text supported; shortcuts adapt to the platform modifier.

## 13. Responsive & Mobile

- **Reply / DM:** sticky to the bottom, sitting **above the software keyboard** (keyboard-aware inset); auto-grows to a max height, then scrolls.
- **New Thread on mobile:** a **full-screen** composer (title + body), launched by the FAB (DESIGN.md §6.1).
- **Toolbar:** an essential set + a **“+”** overflow; tap targets ≥44px; formatting is toolbar-driven (no reliance on shortcuts).
- **Send vs Enter on mobile:** soft-keyboard `Enter` inserts a newline; sending is the explicit button (override of the desktop Enter-to-send default).
- **Attachments:** camera / photo-library picker; clipboard image paste supported.
- **Pickers** (mention, emoji) present as **bottom sheets** on mobile.

## 14. Architecture & Implementation

### 14.1 One component, three mount configs

A single `Composer` (client) takes a small config and nothing else changes:

```js
Composer({
  context:    'thread' | 'reply' | 'dm' | 'edit',
  targetId,                 // boardId | threadId | conversationId | postId
  hasTitle:   boolean,      // New Thread only
  recipient,                // DM only
  placeholder,
  submitUrl,                // the context's endpoint (§2)
  permissions,              // canPost, locked, allowImages, allowAnonymous…
  limits                    // maxLen, maxImages, linkPolicy…
})
```

All feature logic (editing, toolbar, shortcuts, mentions, media, drafts, validation) lives **in the component** — contexts differ only by config. The component is also what powers **edit mode**.

### 14.2 The WYSIWYG editor — engine decision

ADR 0013 selects **Milkdown** for the optional WYSIWYG layer because it is Markdown-native while still providing a rich ProseMirror editing surface. The fallback ladder remains Tiptap/ProseMirror, then a CodeMirror/ink-mde-style live-Markdown surface, but those are no longer active implementation paths unless Milkdown fails future acceptance gates.

Avoid heavy block-document editors — **the composer is an input system, not a mini document editor.**

**Non-negotiables:**

- **Markdown is the canonical document.** Serialize editor state → Markdown on every change (drafts) and on submit. **Reject editor-specific canonical storage** (never persist ProseMirror/Tiptap JSON as the source of truth) — storage stays portable.
- **Semantic round-trip fixtures** are part of the acceptance tests: authored Markdown loads in the editor, preview renders through the server pipeline, and submitted output matches final rendered HTML. No-op edit tests protect legacy Markdown from accidental serializer rewrites.
- The shared component, the no-JS `<textarea>` fallback, the source-mode textarea, the server-side render/sanitise path, and idempotent submit (§14.1, §14.3) hold regardless of engine.
- The committed `/assets/wysiwyg-composer.js` and `/assets/wysiwyg-composer.css` bundles are built from `src/client/wysiwyg/*` by `npm run build:wysiwyg`; deployment serves static files only.

### 14.3 Progressive enhancement & the submit path

- Server renders a `<form>` with a `<textarea>` holding the Markdown. `public/assets/composer.js` builds the shared bridge for toolbar, preview, drafts, uploads, slash inserts, and `@`/`#` suggestions. When both `rich_composer` and `wysiwyg_composer` are enabled, the committed Milkdown bundle registers a bridge adapter and hides the textarea behind the WYSIWYG surface.
- **No JS, `wysiwyg_composer=false`, or adapter failure → the textarea posts the form**; the server renders Markdown → sanitised HTML. **`rich_composer=false`** is the broad emergency kill switch and prevents all enhanced composer assets from loading.
- **Server submit:** validate → run content filters → persist `body` (Markdown) + cached `body_html` (sanitised) → update counters → fan out notifications (DESIGN.md §9.6) → parse mentions → notify. Wrapped in a transaction; returns the rendered post for the client to reconcile.
- **Idempotency:** the client sends an idempotency key; the server applies a **short-lived/transient dedupe** (covers double-submit + brief client retries, not durable persistence). A durable post idempotency column is **foreshadowed in SCHEMA §8**, not yet committed.

### 14.4 The payoff

Because all three contexts (plus edit) share one component and one server pipeline, there is **one validation module, one sanitiser, one renderer, one test suite**. A feature added to the composer is automatically present, and consistent, everywhere.

## 15. Unified Feature-Surface Matrix

The contract: the **input** is identical everywhere. ✓ = present and behaves the same.

| Feature | New Thread | Reply | DM | Edit |
|---|:--:|:--:|:--:|:--:|
| WYSIWYG/source-mode Markdown editing | ✓ | ✓ | ✓ | ✓ |
| Full toolbar + active states | ✓ | ✓ | ✓ | ✓ |
| All keyboard shortcuts | ✓ | ✓ | ✓ | ✓ |
| @mentions / emoji | ✓ | ✓ | ✓ | ✓ |
| Image upload / paste / drag | ✓ | ✓ | ✓ | ✓ |
| Drafts & autosave | ✓ | ✓ | ✓ | ✓ |
| Preview toggle | ✓ | ✓ | ✓ | ✓ |
| Validation / limits / filters | ✓ | ✓ | ✓ | ✓ |
| Optimistic send + rollback | n/a (navigates) | ✓ | ✓ | ✓ |
| Accessibility surface | ✓ | ✓ | ✓ | ✓ |
| **Wrapper differences (not the input)** | + Title + board picker | sticky; quote chip | + recipient | "Save changes" + edit window |

If a future feature can't be offered identically in all three, that's a signal to reconsider it — the unified surface is a deliberate constraint, not an accident.

## 16. Cross-Doc Deltas & Schema

### 16.1 Changes to the other docs

- **DESIGN.md** — this doc is the authoritative composer spec referenced by §6.5. Open question **#2 (post markup) is resolved: WYSIWYG/source-mode editing over canonical Markdown.** **@mentions** are promoted from "deferred" to **P1** (they live in the composer). The `Cmd/Ctrl+K` shortcut is reconciled (link inside the composer; search / `r` / `c` outside).
- **ADMIN.md** — the composer consumes ADMIN-owned controls: word/link **filters**, **attachment limits**, **edit window**, **approval hold**, and **new-user gates**; board settings (`allow images`, `post_min_role`, `allow_anonymous`, `edit_window`) gate it.
- **USER.md** — composing preferences (§4.5: **Enter-to-send vs Cmd/Ctrl+Enter**, attach-signature default, draft behaviour) drive the composer's per-user defaults. The **Anonymous** posting mode (USER.md §2 / ADMIN.md §1.3) is a composer affordance where the board allows it.

### 16.2 Schema additions

```sql
-- Uploaded files referenced from Markdown; tracks ownership, limits, moderation.
CREATE TABLE attachments (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  post_id        BIGINT UNSIGNED NULL,      -- set when attached to a post
  dm_message_id  BIGINT UNSIGNED NULL,      -- set when attached to a DM
  kind           ENUM('image','file') NOT NULL DEFAULT 'image',
  path           VARCHAR(512)    NOT NULL,
  mime           VARCHAR(100)    NOT NULL,
  size_bytes     INT UNSIGNED    NOT NULL,
  width          INT UNSIGNED    NULL,
  height         INT UNSIGNED    NULL,
  alt            VARCHAR(255)    NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attach_post (post_id),
  KEY idx_attach_dm (dm_message_id),
  CONSTRAINT fk_attach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- **`posts.body` already stores Markdown** (canonical) and **`posts.body_html`** the cached sanitised render (DESIGN.md §8) — no change needed; this doc just fixes the markup *flavour* as Markdown.
- **Drafts** are local (`localStorage`) in v1; an optional **`drafts`** table (`user_id`, `context_type`, `context_id`, `title`, `body`, `updated_at`) backs cross-device sync at **P2**.
- **Mentions** are parsed at submit; an optional `post_mentions` lookup table can speed "who was mentioned" queries if needed (P2).
- **Content references** use `content_references.target_type ENUM('board','thread','post','tag')`; migration `0071_content_reference_tags` added `tag` so WYSIWYG `#` tag suggestions and `/tags/{slug}` links can resolve through the same read-gated reference-card path.

## 17. Phasing & Open Questions

### 17.1 Phasing

> **Priority tier ≠ delivery phase (DECISIONS §2).** The P0/P1/P2 below are *priority* tiers. In **delivery** terms: **Phase 1** ships the no-JS `<textarea>` Markdown baseline (server-rendered + sanitised render, edit reuse); **Phase 2** adds **@mentions** (parse-on-submit notifications + autocomplete) and the **DM** mount; the **unified rich `Composer`** (toolbar, source mode, optimistic send, localStorage Drafts/recovery, preview, and the §15 "identical everywhere" surface) is delivered in **Phase 3 Gate A** (PHASE_3_PLAN); **server-side draft sync** is Phase 3 Gate B; the Milkdown WYSIWYG adapter (ADR 0013) shipped deploy-dark behind `wysiwyg_composer` and graduated to **default-ON on 2026-07-02**. A P0-tier composer feature is therefore MVP-critical *in priority* but may be delivered through staged flags rather than Phase 1.

- **P0** — the shared `Composer` + Markdown editing core (bold/italic/strike/inline-code/quote/lists/links), Enter-to-send + core shortcuts, **drafts** (localStorage), validation + **optimistic send** + rollback, signed-out join-bar, **edit reuse**, the no-JS/source-mode `<textarea>` fallback, and the accessibility baseline. (Resolves the markup decision.)
- **P1** — @mentions + emoji picker, **image upload/paste/drag**, code blocks (+language), limited headings, spoilers, **preview toggle**, toolbar overflow, board-aware limits, character counter, content-filter integration.
- **P2** — file attachments, link unfurl/embeds, tables, task lists, `#board` references, **server draft sync**, custom emoji, a slash-command (`/`) menu, GIFs/polls.

### 17.2 Open questions

> **Resolved in [DECISIONS.md](DECISIONS.md) §6.** Retained below for context.

| # | Question | Owner | Blocking? |
|---|---|---|---|
| 1 | Editor library. **Resolved:** Milkdown selected in ADR 0013 for the optional WYSIWYG layer; keep Tiptap/ProseMirror and CodeMirror/ink-mde as fallback options only if future acceptance gates fail. | Eng | No |
| 2 | Global send default: Enter-to-send vs `Cmd/Ctrl+Enter` (user-overridable either way). | Product | P0 |
| 3 | Heading levels allowed in posts: none / `##`–`###` only / all. | Product | P1 |
| 4 | Tables in v1 or P2. | Product / Eng | P1 |
| 5 | Embeds/unfurl: fetch server-side (SSRF/privacy), which providers, opt-in per board? | Eng / Henry | P2 |
| 6 | Attachment storage/CDN + max sizes/types. | Henry / Eng | P1 |
| 7 | Slash-command (`/`) insert menu — include or skip? | Product | P2 |
| 8 | Per-post mention cap value. | Product | P1 |
| 9 | Server-side draft sync — when? | Eng | P2 |

## 18. Changelog

| Version | Date | Notes |
|---|---|---|
| v0.1 | 2026-06-19 | Initial composer design. One shared component across New Thread / Reply / DM (+ edit) with an identical feature surface; **hybrid live-Markdown** editing model (resolves DESIGN.md markup question); toolbar; full keyboard shortcuts (Cmd/Ctrl+K reconciled); mentions/emoji/references; attachments/images/embeds; drafts & autosave; submission/feedback + edit mode + error taxonomy; preview; validation/limits/safety; accessibility & i18n; responsive/mobile; architecture (one component + mount config, hybrid editor, progressive enhancement); the unified feature-surface matrix; `attachments` schema; phasing & open questions. |
| v0.2 | 2026-06-19 | Framework integration: resolved the editor engine to a **spike ladder — Milkdown first**, then Tiptap/ProseMirror, then CodeMirror/ink-mde (§14.2). Added non-negotiables: **reject editor-specific canonical storage**, **Markdown round-trip fixtures** in acceptance tests, and "the composer is an input system, not a mini document editor." |
| v0.3 | 2026-06-26 | Wording/citation fixes: corrected the **Drafts** sidebar quick-filter cross-ref **§6.2 → §5.2/§6.5** (§8); reframed post **idempotency** as a **short-lived/transient dedupe** (double-submit + brief client retries, not durable persistence), with a durable post-idempotency column **foreshadowed in SCHEMA §8, not yet committed** (§9.2/§11/§14.3). |
| v0.4 | 2026-06-26 | Consistency fix: §17.1 now maps the P0/P1/P2 *priority* tiers to *delivery* phases — Phase 1 = no-JS Markdown baseline, Phase 2 = @mentions + DM, the unified rich `Composer` = Phase 3 Gate A, server draft sync = Phase 3 Gate B — resolving the "P0 composer vs Phase 3 delivery" ambiguity (DESIGN §13.1 / PHASE_3_PLAN). §6.1 @mentions clarified as priority P1 / delivery Phase 2. |
| v0.5 | 2026-07-02 | WYSIWYG closeout: recorded ADR 0013's Milkdown selection, the `wysiwyg_composer` narrow flag under the `rich_composer` kill switch, source-mode textarea contract, adapter-based `@`/`#` pickers and chips, internal URL paste normalization, server-preview parity, no-op edit protection, committed static bundle, and `content_references.target_type='tag'` schema follow-up. |
| v0.6 | 2026-07-02 | `wysiwyg_composer` graduated to **default-ON** (GA 2026-07-02; reversible via `features` override; `rich_composer` remains the broad kill switch). §17.1 delivery note updated. Browser evidence split recorded: gate-a screenshots keep the textarea baseline via a seed pin; `wysiwyg-composer.spec.ts` proves the GA default mounts with no override. |
