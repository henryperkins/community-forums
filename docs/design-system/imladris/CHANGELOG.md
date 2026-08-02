# Changelog

## 2026-08-02 — Thread-view template reconciled to production
Audited `templates/thread-view/ThreadView.dc.html` against `thread.php`,
`partials/post.php`, `post_toolbar.php`, `thread_tools.php`, and
`living_brief.php` at main. Three design-layer inventions the backend cannot
populate were reconciled to production; the DS vocabulary cards are unchanged.
- **Tier chips** (Member/Veteran/Loremaster/Legend enum + per-tier palettes) →
  one chip rendering a single cosmetic `author_title_label` string in the
  neutral `.post-title-chip` style; `TIERS` removed from `thread-data.js`
  (sample titles remain as plain title values).
- **Named reactions** (Commend/Seconded/Illuminating with glyphs) → raw emoji
  from `ReactionService::ALLOWED` (👍 ❤️ 😂 🎉 🔥 💯 😮 😢 👀); pills render
  `{emoji} {count}` with "React" / "Remove your reaction" titles; the picker is
  a plain emoji row; the ✦ quick-commend hover button (a named-reaction
  artifact with no production counterpart) removed, so hover actions now match
  `post_toolbar.php` order: react ＋, quote, accept ✓, more.
- **Reveal states** ("Revealed · logged" chip, "Lindir (was anonymous)"
  byline) → reveal is a stateless audited action that flashes production's
  exact string ("Author of this anonymous post: lindir (this reveal has been
  logged)."); the byline and monogram stay `mask_author()`'s constant
  "Anonymous" identity (was "A quiet voice" / seeded monogram).
Minor string/anatomy alignments: "Your vote" chip; poll foot "Open to the
council" (voting form only, guest login line removed); vote toast "Vote
recorded."; guest joinbar "You're browsing…"; locked bar "This thread is
locked…"; added the "In council" participants label and the "(edited)" marker
(grouped and ungrouped headers).

An adversarial verify pass over the reconciliation caught a further round:
- **Deanonymisation leak**: the participants stack listed Lindir, whose only
  post is the anonymous one — production's `participantsForThread()` filters
  `is_anonymous = 0` exactly so this can't happen. Stack and `PARTICIPANTS`
  now carry only the four named authors (no +N).
- **Status enum**: `decision` → production's `decision_made`.
- Drawer labels to production: "Topic management" (was "Wardens' tools"),
  "Status history" (was "Status ledger"), "Living Brief" summary, snooze
  "Later today"; brief curation now uses "Refresh living brief" / "Publish
  summary" / "Retire summary" / "Restore summary" + an "Add related topic"
  row; on-page curate control is "Curate".
- Poll management is one-way "Close poll" only (reopen/remove/restore were
  invented); result rows read "14 votes" / "1 vote"; management gained the
  missing "Move to board" control ("Choose a board…" / "Move topic").
- Post menu: "Make wiki" only when not yet wiki (no "Remove wiki flag"); OP
  delete/remove variants "Delete topic" / "Remove topic (warden)"; delete
  toasts use production flashes (Your topic/post was deleted., Topic/Post
  removed., Thread moved./split./merged., Poll closed.).
- Composer: added the "Anonymous" chip + disclosure ("Your name is hidden
  from other members; moderators can still see it."), an anonymous send path
  producing the constant masked identity, and the production reply
  placeholder (Reply to “{topic title}”…).
- Breadcrumb shows the board name ("#The Archive", not the slug); the
  "Opened by" name is plain text (production deliberately doesn't link it);
  merge form is "Target topic ID" (numeric) with production's note — the
  invented "signpost" behavior is gone.

A third verify round caught state/gating divergences (states production
cannot produce):
- Participants ordered by first contribution (Elladan before Arwen), matching
  `participantsForThread`'s MIN(created_at) ordering.
- Star button hidden from guests; locked bar takes precedence over the guest
  joinbar (a guest on a locked thread is never invited to "log in to reply").
- Poll visibility mirrors PollService: results only after voting or close; a
  guest with an open poll gets production's fallback line ("Results are
  visible after voting or after the poll closes.").
- Ownership is user_id-based — an author keeps Edit/Delete on their own
  anonymous post (masking never strips owner affordances) and is never
  offered Report on it.
- Titles are total for non-anonymous authors (TitleService floors at a
  default rung) — every named post now carries a title chip, including
  prototype-sent replies.
- Pin/lock toasts use production flashes (Thread pinned./unpinned.,
  Thread locked./unlocked.); accept tooltip "Accept as answer"; day dividers
  only between days (none above the first post); post authors and head tags
  are links as in production; the on-page brief eyebrow is the provenance
  label ('AI-generated living brief', privacy-linked; flips to
  'AI-generated · curator edited' after curator publish).

A fourth pass finished the flash-string convention and residual gaps:
- Every prototype toast whose act has a fixed production flash now uses it
  verbatim: Topic status updated. · Subscription updated. · Topic snoozed. ·
  Assignment updated. · Tags updated. · Marked as the accepted answer. ·
  Cleared the accepted answer. · Thread starred. · Thanks — our moderators
  will review this. · Wiki editing enabled for that post. · Summary
  published. · Not enough eligible posts for automatic refresh · Choose at
  least one reply to split. · A split thread title is required.
- Guests now get the head "Status history" disclosure (thread.php renders it
  exactly when there is no Topic tools drawer).
- Quote hides on a locked thread (production gates it on can_reply).
- Ordering parity: post-menu staff actions (Remove → Reveal → Make wiki) and
  header badges (OP → Wiki → Staff) follow the partials.
- Drawer summaries undressed to production: raw lowercase watch frequency,
  plain "Solved" standing label (✓ is the H1 chip's treatment only), and the
  brief meta is the member-facing 'Updated automatically · Version N · time'
  (flipping to 'Curator edited by @user …' after publish) instead of the
  invented 'posts weighed' metric.

A fifth (dry-check) pass closed the remaining interaction-parity gaps:
- Poll before the memory slot (thread.php's section order); the brief card
  regained its fixed anatomy — 'Where the discussion stands' h2 and a
  Sources list (Post #102 by @glorfindel · Post #106 by @arwen) replacing the
  invented 'Drawn from…' sentence.
- 'Close poll' now also requires a poll to exist; unassign flashes
  'Assignment updated.'; split validates title before selection; merge
  validates its target ('Choose a valid target thread.').
- Accepting writes the literal history reason 'accepted_answer'; clearing the
  answer flips solved → open with reason 'accepted_answer_cleared' (mirrors
  syncSolvedStatus) instead of leaving a solved chip with no answer.
- Grouping now honors all of post.php's exceptions in the live prototype
  (accepted/OP/staff/wiki posts un-group when they gain that state).
- Prototype-sent replies carry the viewer's reputation (plinth is total for
  named posts, like titles); hover titles 'Add a reaction' / 'More post
  actions' match the toolbar's aria-labels.
- Final dry check: 'Clear accepted answer' precedes the pin/lock rows
  (thread_tools.php order), and guests see reactions as static pills
  (.reaction-static — no button, title, or hover), not disabled buttons.

## 2026-07-14 — RetroBoards runtime adoption (Part 2)
- Reconciled the imported `4efe4e33` inspection through application commit
  `6d81da5`: current `/admin/features` readiness classifications and the
  production `--gold-800` consumer are reflected in the local source mirror.
- Added an allowlisted generator for tokens, bundled fonts, and reusable
  component CSS. Preview JavaScript, UI kits, documentation CSS, uploads, and
  archived application snapshots remain design references only.
- Wrapped the runtime CSS in low-priority cascade layers beneath the unlayered
  application compatibility layer; WYSIWYG, package-theme, and branding CSS
  retain their existing later override order.
- Kept the authoring bundle's reduced-motion specimen intact but filtered its
  global `!important` timing rule from production. Important declarations
  reverse cascade-layer priority and had defeated the Study's explicit
  `animation: none`; RetroBoards already owns global and feature-specific
  reduced-motion behavior.
- Added generated-asset, feature-flag, composer-anatomy, token-definition, and
  reviewed application-surface drift gates. Any later member/admin/community/
  composer spec, template/browser asset, or feature-flag change now requires
  explicit parity review.

## 2026-07-14 — Modernization pass (Part 1 of the adoption plan)
Inspected RetroBoards `henryperkins/community-forums@4efe4e33` (main). Authority order per DECISIONS.md v1.6.

### Composer brought to the shared-shell contract (COMPOSER.md v0.8)
- `components.css`: old composer block replaced with the production shell CSS **verbatim** (box, engraved icon toolbar + overflow, upload tray, actions bar, meta row, suggestion/emoji/draft-sync surfaces, responsive + coarse-pointer + reduced-motion rules) + `.field-error`.
- `Composer.jsx`/`.d.ts` rewritten: four mounts, production toolbar order/labels/shortcuts/icon paths, Aa toggle, ＋ attach, 😊 emoji, "as *Name*" identity, Anonymous chip + disclosure, Preview, circular ✒ send, uploads, draft/counter meta, error/submitting/disabled states.
- All consumers migrated; the superseded "Posting as" strip / text-button toolbar / standalone-textarea anatomy removed everywhere (cards, both templates, kit, spec, prompt docs, thread-view dock).

### Architecture repairs
- `--text-body` collision fixed: it stays a semantic **color**; body size renamed `--text-size-body`.
- Fonts self-hosted: Google Fonts `@import` → bundled WOFF2 in `assets/fonts/` + OFL licenses; matches the app's CSP class.
- App CSS/JS snapshots moved out of usable source to `_archive/app-snapshots/2026-07-14-4efe4e33/`; 2026-06 design pull archived to `_archive/design-pull-2026-06/`. Archives are reference-only.
- Preview bundle regenerated from updated sources (`_ds_bundle.js`).

### Guidance corrections
- Emoji: decorative/status emoji in chrome stay prohibited; authored-content emoji + composer emoji tooling documented as supported product features (README, SKILL, vocabulary).
- `feature-ui/` statuses refreshed to flag truth at the commit: 13 of 14 GA default-on; `link_previews` implemented-dark.
- README provenance: inspected commit + archive rule recorded.

### Contracts
- Added `PRODUCTION_PARITY.md`, `RUNTIME_CONTRACT.md`, `production-contract.json`; `manifest.json` rewritten as the inspection manifest.

### Known gaps (tracked in `manifest.json → unresolved_gaps`)
Admin-kit platform sections, auth-kit passkeys/invites, and system pages (setup/error/privacy/unsubscribe/gated) — to be added before the Part 1 acceptance gate closes.
