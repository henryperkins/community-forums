# Changelog

## 2026-08-03 — Decision: one owner per route, and boards are not "rooms"
Four templates and a kit all render a topbar, a rail and a list, so it had stopped
being obvious what any of them was *for*. Settled by route, against
`src/Core/App.php` and `HomeController` rather than our own naming. Recorded as
`guidelines/surface-map.card.html`.

**The root cause.** `templates/board-index/` existed once and was folded away on
2026-08-02, so route `/` lost its owner — its inbound links were repointed to
`board-page` and `reading-rooms` quietly took over the role under a different
noun. Two surfaces then described the same directory and neither said so.

### Renamed
- **`templates/reading-rooms/` → `templates/board-index/`** (`ReadingRooms.dc.html`
  → `BoardIndex.dc.html`). `HomeController` documents route `/` as "the
  category/board index (pane 1 + 2 of the three-pane shell)" — that is exactly
  what this template is. It now says so.
- **"Rooms" retired for "boards"** throughout it — rail label, headings, preview
  label, the ⌘B/⌘J tooltips, "Open this board", and the handler names. `room`
  appears **twice in the entire application**, both times as prose about the DM
  column: there is no room entity, no `/rooms` route, and no board→room mapping in
  our own vocabulary card. It was our invention sitting on top of the product's
  noun, which is the most expensive kind of drift to unpick later.

### Fixed
- **Two template descriptions contradicted the code.** `board-page` still claimed
  its rows "carry no board label, snippet, star, or inclusion cue" — the star and
  the cues went back in when `thread_row.php` disproved that, but the description
  did not follow. `forum-inbox` described "a reading pane" without saying it is a
  preview that hands off, which is the whole reason a topic is not rendered twice.
  Both now point at `guidelines/thread-row.card.html` for row anatomy instead of
  restating rules that live there.

### Added
- **`guidelines/surface-map.card.html`** — five reading routes, the question each
  answers, its owner, and what it must not do; then the four artifact kinds
  (primitives / rules / screens / surveys) so there is one place to look before
  changing anything; then five rules that keep them from drifting. The first is
  the one this system keeps relearning: **one object, one owner.** The profile
  cover and the topic row both drifted because something was rendered twice.

## 2026-08-03 — Decision: /c/{slug} is the index, /inbox is the queue, one row serves both
Settled what actually differs between a board's topic list and the inbox queue,
against `partials/thread_row.php` rather than against our two forks of it.
Recorded as a card: `guidelines/thread-row.card.html`.

**The finding.** Upstream has **one** row partial, not two. It varies on a
`presentation` axis (`default` for `/inbox` and `/tags/{tag}`, `board` for
`/c/{slug}`) plus a `show_board` flag, and the only structural change is
positional: on a board, reply count and last activity leave the inline meta run
for a right-hand rail, so the titles form one scannable column. Our system had
this forked into two hand-rolled template rows and a `ThreadRow` component with
no board presentation at all — which is how the two drifted.

**The decision.** One row, three densities, and a rule for what appears in each:

- **Topic-intrinsic** (author, title, status chips, pinned, locked, replies, last
  activity) reads **identically on both**. These are properties of the topic; if
  they differ, the two surfaces are describing different objects.
- **Viewer-relative** (unread dot, star, `assigned to @`, `snoozed until`) renders
  on **both** — loud in the queue, a quiet marker on the index. A topic you
  starred that reads unstarred on its own board is a bug, not a density choice.
- **Surface-specific** (the `#board` label, the snippet, the queue reason) appears
  only where it answers that surface's question. A board does not label itself;
  a snippet is a triage aid, not an index entry.

### Fixed
- **Board rows were dropping viewer state.** `templates/board-page` had no unread
  dot, no star, and no assigned/snooze cues — all three of which the partial
  renders regardless of presentation. Added at board weight: an 8px gutter dot,
  a trailing star, and the cues folded into the byline line. Seed data now
  exercises each.
- **The drift note in `github.md` was wrong on two counts.** It asserted board
  rows carry no star and no inclusion cue; the partial renders both. Corrected
  and marked resolved.

### Changed
- **`ThreadRow` gained the board presentation** — `presentation="default" | "board"`,
  plus `assignee`, `snoozedUntil` and `reason` props, and it now suppresses the
  snippet, the board label and the queue reason on `board`. `.d.ts` marks
  `snippet`, `authorTier`, `authorRep` and `commends` as **DS extensions** with no
  counterpart in the partial, so a reader can tell our additions from the product.
- **`components.css` gained `.thread-list.is-board`** — the ruled 64px index row:
  no card, no snippet, 32px monogram, chips inline with the title, activity in
  `.thread-row-activity`. Three densities now sit in one block: `thread-list`,
  `is-compact`, `is-board`.

## 2026-08-03 — Redundancy pass: four superseded artifacts out
Three families described one product — 19 `templates/`, 6 `ui_kits/`, 6
`feature-ui/`. The kits and activation surfaces were built while the templates
did not exist; as templates landed, most were superseded in fact but not in the
file tree. Full reasoning in `REDUNDANCY-AUDIT.md`.

**Nothing lost any coverage.** Every removal below had a template already owning
the surface; what went was a second description of it.

### Removed
- **`ui_kits/admin/`** (9 files) — its own README had said "SUPERSEDED" since the
  admin gap closed, and all ten destinations map to `templates/admin-*`. Two
  files still treated it as live: `PRODUCTION.md`'s OAuth/invitations/providers
  row named it as owner (providers belong to `templates/admin-integrations`,
  invitations to `templates/admin-members`), and `manifest.json` filed the ADR
  0021/0023 remediation gap **against it** — a gap that could never close, since
  the artifact was frozen by its own README. The gap is re-filed against the ten
  templates, per destination.
- **`feature-ui/polls/`, `feature-ui/tags/`, `feature-ui/moderation/`** —
  absorbed by `templates/thread-view`, which carries the poll end to end
  (choose-one → results → close), tag reads on the topic and in the composer,
  assign / snooze / escalate, and the split-or-merge modal. This is the call
  already made for `feature-ui/account` and `feature-ui/conversation` on
  2026-08-02; a flag belongs on the surface it changes, not in a gallery beside it.

### Changed
- **The activation index is two cards, not five.** Both are the rail
  (`rail/` + `organize/`) — the one surface in this system with **no template**,
  which the retired galleries were obscuring. Its closing note now names all five
  folded areas and their owning templates.
- **`ui_kits/retroboards/` marked superseded per screen.** Inbox, Conversation
  and Profile are owned by `templates/forum-inbox`, `templates/thread-view` and
  `templates/user-profile`; `Leaderboard.jsx` has no template and is the only
  reason the kit survives. **Not deleted** — the leaderboard needs the topbar and
  rail shell around it to read as a screen, and it has nowhere to go until a
  leaderboard template exists. Its README now says so, and records that its
  `.profile-*` block is an older divergent copy that must not be hand-synced.
- **`manifest.json` stopped claiming provenance.** It carried
  `inspected_commit: 3fa5704e2e42` while `github.md` recorded `3d317c770be4` —
  two files asserting the upstream state, three syncs apart. It now reads
  `"provenance": "github.md"`, the file the sync flow actually rewrites.
- **`PRODUCTION.md` rows repointed** — shell, rail, polls, topic workflow, OAuth,
  admin, and leaderboard now name the artifact that owns each surface, and say
  plainly where nothing does.

### Left alone
`ui_kits/auth`, `ui_kits/dm`, `ui_kits/mod`, `ui_kits/system` — no template
covers auth, DMs, moderation queues, or setup/errors/privacy. They are the next
four templates, not dead weight. `feature-ui/rail` vs `feature-ui/organize`
duplicate each other, but no template covers either; collapsing them is a
judgement call about the rail, not a cleanup (`REDUNDANCY-AUDIT.md` §3).

## 2026-08-03 — Sync `3d317c770be4`: the profile cover's gold gets a floor
Upstream reworked `templates/profile/show.php` and the profile layer of
`public/assets/app.css`. One token, one card, one template.

### Added
- **`--gold-800: #6B5120`** — a new darkest step on the mallorn ramp, and the
  reason for this sync. Small gold text sitting on a `--gold-100` wash (the
  tier chip, the regard plinth's label, the Commends eyebrow) was set in
  `--gold-700`, which is a *fill* colour; against gold-100 it reads thin.
  `--gold-800` is the ink counterpart, and it is register-independent — the
  plinth stays a light card even on the twilight cover, so the ink must not
  flip with it. `guidelines/gold.card.html` carries the swatch.

### Changed
- **The regard plinth is now a solid card, not a wash.** `--gold-100` ground,
  `--gold-200` hairline, `--ink-900` numerals, `--gold-800` label — where it
  was translucent gold with parchment numerals. Same for the tier chip, which
  gains the `--gold-200` hairline it was missing.
- **The cover's ink is inherited, not repeated.** The header sets
  `color: var(--parchment-50)` and the gilt border drops to
  `color-mix(--gold-500 16%)` from a hardcoded `rgba(…,.28)`; the star
  watermark settles to `.11`; the shadow goes to `--shadow-lg`. The
  member-since line is now a tracked label at `.76rem`/`--mist-200` rather
  than body copy, and the website link is gold, not river.
- **Follow's on-state flips with the register.** `--brand-subtle` /
  `--on-brand-subtle` / `--green-200` in place of three hardcoded rgba
  values; the error rule and the block action read `--danger`, not `--rust`
  (`--danger` lightens to `#DB8C73` on twilight — `--rust` does not).
- **The moderator strip states what it is.** A `Moderator context` label plus
  a sanction sentence, replacing one run-on line; the link is
  "Open member record". The `···` menu's labels shorten to Copy link / Block.
- **Connections rows say who, not when.** `@handle · N regard` in place of
  invented tenure strings, and the row action is **Remove follower** — shown
  only on your own seat, in the followers mode. There was never a follow-back
  button here. The Following mode gets its own empty line.
- **Rows lost a stat they never had.** Topic and post rows read `#board · time`;
  replies belonged to the board list, not the profile. Commend rows are the
  count and the title, nothing else. The regard note is one sentence.
- **Grid tracks made safe.** `minmax(0, 1fr)` on the overview, commends, and
  cover tracks; tabs are `flex: 0 0 auto` so the rail scrolls instead of
  crushing; the bio states its own 66ch measure.
- **`ui_kits/retroboards/`** takes the `--gold-800` tier fix only. The rest of
  its cover is an older survey treatment and is logged as drift, not corrected.

## 2026-08-03 — Cleanup: duplicates and dead weight out, docs consolidated
A housekeeping pass. No token, component, or template behaviour changed.

### Removed (288 files)
- **`_design_handoff_imladris/`** — a full second copy of the system (its own
  `styles.css`, `components.css`, `_ds_bundle.js`, `components/`, `guidelines/`,
  `ui_kits/`, `contracts/`) that had already drifted: 15 templates against the
  root's 19. A duplicate this large makes every grep ambiguous and invites
  editing the wrong file. Handoff bundles are regenerated on demand, not kept.
- **`_archive/`** — the app frontend snapshot at `4efe4e33`, the 2026-06 design
  pull, and two superseded handoff folders. Provenance now reads as a repo path
  plus commit rather than a vendored copy.
- **`_scratch/production-inventory-notes.md`** — an execution plan, fully carried
  out.
- **`uploads/`** — five unnamed screenshots and a web capture, referenced nowhere.

### Docs consolidated
- **`RUNTIME_CONTRACT.md` + `PRODUCTION_PARITY.md` → `PRODUCTION.md`** — one file,
  two parts: the contract a consumer must honour, then the parity matrix. Both
  were short and always read together. `manifest.json` and
  `production-contract.json` now carry a single `production` / `production_doc`
  key in place of two.
- **Parity matrix corrected.** Six rows pointed at `feature-ui/conversation/` and
  `feature-ui/account/`, neither of which exists. They now name the real
  surfaces — Thread Intelligence to `templates/living-brief`, badges and regard
  to `admin-features` and `user-profile`, platform config to `admin-packages`,
  `admin-integrations` and `admin-appearance`.
- **`README.md` trimmed and its index rebuilt.** It listed 7 templates; there are
  19. `user-profile`, `users-online`, and all ten `admin-*` screens were missing,
  as was the whole `feature-ui/` layer. Member and operator surfaces are now
  separated, and the Sources section no longer sends readers to a deleted folder.
- **`SKILL.md`** — the kit list named a `settings/` kit that doesn't exist and
  omitted `dm`, `mod`, and `system`; it now also points at `templates/`,
  `feature-ui/`, `PRODUCTION.md`, and `imladris-spec.md`.
- **`components.css`** — the composer block's provenance comment cited the
  archived snapshot path; it now cites `community-forums public/assets/app.css @
  4efe4e33`.

## 2026-08-03 — Board identity + density, and two parity corrections
Synced `3fa5704e2e42…92fd94a1f7ed` (47 commits). Three upstream specs landed the
board treatment this system had only sketched, and the production profile review
caught the source template contradicting our own written contract.

### Board page — the shipped treatment
- **Identity band.** The parchment masthead card (stat plinths, gold top rule) is
  retired for the approved **evergreen band**: `--green-700` field, parchment
  text, a 3px `--gold-500` bottom rule, square edges. Eyebrow *Board* · `#name` ·
  description · `topics · posts · visibility` facts with gold interpuncts, and the
  board-scoped actions inside the band — Follow board (parchment fill) and a gold
  New topic. The discovery-feed note moved beneath the band, right-aligned mono.
  This treatment belongs to `/c/{slug}` alone; it does not extend to the index,
  the inbox, messages, or a canonical topic.
- **Density remediated** (spec `2026-08-03-board-topic-density-remediation`). Rows
  were substantially taller and slower to scan than the rest of the forum. Now:
  **64px** min row height, **8px** vertical padding, **32px** monogram, **22px**
  above the topic section, transparent ground, hairline separators, and a
  right-aligned replies/activity rail. Growth is natural, not clipped — long
  titles and status chips still expand the row.
- The column-header strip and the gold-filled pinned group are gone; production
  renders **one** ruled list, so pinned topics lead it with a gold Pinned marker.
  Section labels are production's: *Latest activity* / *Topics* / *Pinned first,
  then last post*. Breadcrumb is *Forum index / #board*.

### Account settings — a retired preference
`PreferenceSchema` reached **v3**, which retires `thread_sort` from the managed
schema (legacy blobs keep it as inert unknown data). **Default sort** — and with
it **Most replies** — is removed from Reading; the section is now a two-column
grid. Board order is fixed: pinned first, then last post, never a toolbar.

### User profile — the cover was wrong
The production review rendered our `UserProfile.dc.html` beside the application
and found the light-theme cover painted from **parchment** tokens, contradicting
`imladris-spec.md` and this system's own rule that *the profile cover is the only
dark slab in the day register*. Production deliberately keeps the twilight cover
in **both** registers, so the source now does too: flat `--twilight-800` with a
gilt hairline, parchment/mist text, a gold-wash Regard plinth, and gold as the
actionable colour (Follow) per the twilight register.
Also removed as flows that do not exist: profile-level **Report to the wardens**,
and the gated profile's **Send a message** / **Request access** (the private-seat
copy now simply says what stays hidden). *Warden* → **moderator** in the
moderator strip, matching authoritative application vocabulary.

### `Monogram` — numeric `size` was a silent no-op
Fixing the board row surfaced a latent API bug: `size` was matched against the
named scale (`sm` 28 · `md` 36 · `lg` 44 · `xl` 64), so any numeric value fell
through to the 36px default without warning — there is no 32px rung, which is
exactly why production reaches 32px with a board-scoped CSS override. `size` now
also accepts a **number** for an exact pixel box, with ink scaled at the app's own
0.3 ratio (so `32` → 32px/.6rem, matching `.board-view .thread-row-board >
.monogram`). Caller `style` still wins over the computed box.

### Not touched`/inbox` is unchanged upstream and is the density spec's *comparison* surface, not
an implementation target — board rows stay structurally unlike inbox rows. Drift
in `templates/{leaderboard,home}.php` and `partials/{badges,icon}.php` is logged
in `github.md → Open drift` rather than guessed at.

## 2026-08-02 — Board page rebuilt as a board, and the handoff bundle caught up
Two follow-ons to the split.
- **`templates/board-page/`** was still the inbox's anatomy with a pane removed —
  same rail, same monogram-snippet-star row, same header weight. Rebuilt as an
  actual board list: a raised **masthead** (gold top rule, breadcrumb, `#name`,
  Topics / Posts / Last post stat block, action bar) over a **Topic · Replies ·
  Last post** column list, with pinned topics in their own gold-tinted group.
  Rows now read *title · started by X · date* with a right-hand last-**replier**
  cell; the snippet, star, unread dot, and left monogram are gone — that anatomy
  belongs to the queue. Order is stated by marking the Last post column, not by a
  toolbar. Column is wider (1080 vs 820) and rows are tighter.
- **`_design_handoff_imladris/`** still shipped the retired `board-index` screen
  as section 2, so an engineer reading the bundle would have built the merged
  frame the decision rejected. The folder is deleted and replaced with copies of
  the two live templates; README section 2 is now **2. Forum inbox** and
  **3. Board page** (later screens renumbered), and the bundle tree, screen map,
  build order, and both contract docs follow. Build order now starts with the
  board — it is the simpler surface and it settles the row anatomy
  `thread_row.php` must serve through explicit inputs rather than route inference.
- Seven dead links to `../board-index/BoardIndex.dc.html` in **Reading rooms**
  (live and bundle copies) now resolve: board names and "Open this room" → board
  page, "Open the inbox" → forum inbox, "Lately" rows → thread view.

## 2026-08-02 — Board index split into two role-coded surfaces
Per the approved *Forum Inbox and Board Identity* decision, the one flexible
`board-index` screen was retired and replaced by two templates. The merged screen
invited a literal translation that would have made `/inbox` and `/c/{slug}` look
and behave alike — it carried a cross-board topic composer with a board picker,
an Active / Newest / Unanswered board toolbar, and four client-side filters in
place of the fifteen server-backed inbox filters. All three contradict the
decision, so the file is gone rather than deprecated.
- **`templates/forum-inbox/`** — `/inbox`. Three panes (shared rail, personalized
  queue, reading pane). Eyebrow **Your personal forum view**, title **Forum
  inbox**, lede naming the cross-board scope, unread count when non-zero. All
  fifteen filters are present as links: six primary, nine behind an accessible
  disclosure that auto-opens when the active filter lives there. Every row carries
  board identity; the **For You** filter shows the server's `for_you_reason` as a
  plain-language inclusion cue, status filters are cued by their own chip, and
  ordering filters (Active, Newest, Unanswered) carry no redundant per-row chip.
  The reading pane repeats the board breadcrumb. Per-filter empty states, with a
  route back to For You from any narrowed scope. **No composer, no board picker.**
- **`templates/board-page/`** — `/c/{slug}`. Shared rail plus one primary column,
  no reading pane. Header carries `#board-name`, description, topic and post
  counts, visibility and archive state, follow, and **New topic** with the board
  fixed by the route. Order is pinned-first then Last post, stated as a caption
  and with **no sort toolbar**. Rows omit the board label and inclusion cue.
  Tweaks cover the policy matrix: guest, cannot-post, archived, private/hidden,
  and the empty board.
- Both keep one Imladris visual family — same topbar, rail, row anatomy, chips,
  and monograms — so the surfaces read as one product with two jobs. Topbar nav
  now labels the routes **Forum inbox** and **Messages** distinctly.

## 2026-08-02 — One engineering handoff bundle, not three
The two handoff folders were merged into a single `_design_handoff_imladris/` and
the old ones deleted.
- **`README.md`** — the master handoff: overview, fidelity, target environment
  (RetroBoards as-is, PHP + `app.css`), the complete token reference (primitives,
  semantics, twilight, type, space / radius / shadow / motion / layout), shared
  anatomy, and full per-screen specs for the six copyable templates.
- **`SCREENS-account-and-admin.md`** — user profile, users online, and the six
  operator-desk screens, carried over from the deleted profile/presence bundle.
- **`SCREENS-feature-activation.md`** — the five feature surfaces and their flags.
- The bundle keeps the leading underscore so the compiler skips it; without the
  prefix its copied `components/` and `ui_kits/` collide with the live sources on
  `window.ImladrisDesignSystem_c3e027`.

## 2026-08-02 — Settings and reading kits folded into their templates
The two reference kits duplicated surfaces their templates already owned, so the
templates absorbed what was missing and the kits were deleted.
- `ui_kits/settings/` → **Account settings template**. Missing sections added:
  **Boards** (favourite / mute, grouped by category) under Reading & writing, and
  **Blocks** (blocked members, unblock) under Council. The kit's Composing pane
  was already folded into Reading, so nothing else was outstanding.
- `ui_kits/reading/` → **Reading rooms template**. Missing surfaces added: a
  **single tag** view (tag chips now open it — follow control, topics carrying the
  tag) and **Connections** (Followers / Following tabs with remove and unfollow),
  added to the rail and to the `route` tweak.
- Reconciled against production after an adversarial verify pass: the tag page
  now renders the shared **ThreadRow** (as `tags/show.php` does via
  `partials/thread_row` with `show_board`), with per-tag topic sets, plain tag
  name, "Tags" breadcrumb, "Follow tag" / "Unfollow tag" + "Discovery feed only",
  and the empty state "No visible topics use this tag."; Connections follows
  `profile/connections.php` — **Remove** exists only on your own followers list
  (production has no per-row unfollow), rows read "@user · N rep", and the empty
  states are "No followers yet." / "Not following anyone yet."
- Both `@dsCard` entries are gone from the Design System tab; README and
  github.md's screen map updated, and the README template list — which still
  named the retired `council-topic` folder — now lists all six templates.

## 2026-08-02 — Engineering handoff template reconciled to production
Audited `templates/engineering-handoff/EngineeringHandoff.dc.html` against the
mounted `community-forums` checkout (README, `src/Core/App.php` routes,
`FeatureFlags.php`, `SCHEMA.md`, `AuthorityGate.php`, `ReactionService.php`).
It was the last artifact still describing the Phase 4 product.
- **Status**: "Phase 4 closeout · 456 tests / 1,635 assertions" → Phase 5 Gate A
  accepted and default-on / Gate B reserved, 1,831 tests / 9,396 assertions.
  Deferral list corrected: polls and custom emoji shipped; the dark flags are
  `link_previews`, `expanded_files`, `custom_css`.
- **Landing routes**: `/` is the contents page (categories → boards, via
  `HomeController::index`), not the inbox; `/inbox` is the Community Inbox. The
  route table grew to the ten real member/operator surfaces (`/messages`,
  `/notifications`, `/search`, `/admin`, …).
- **New §4 Thread intelligence** — `community_memory` / `automated_context`,
  the bounded worker and `POST /t/{id}/summary*` curation routes, the three
  provenance postures the Living brief template models, and the
  public-posts-only boundary. Sections renumbered; a third figure slot added.
- **Data model** to `SCHEMA.md`: `accepted_answer_post_id`, `is_pending`,
  `view_count`, `last_post_user_id`, `is_anonymous` (with `user_id` always the
  real author); `thread_user` added; reactions described as the fixed
  nine-emoji set with self-reactions excluded in app logic.
- **Permissions**: added the capability-resolver caveat — DB-backed roles ship
  but run in `shadow` by default; the legacy ladder decides until
  `CAPABILITIES_MODE=enforce`. Matrix gained brief curation, split, and audited
  reveal rows.
- Also: anonymous-post flow callout, composer-shell subsection (§3.2), and a
  `figures` tweak that hides the drop-in image slots for a text-only handoff.

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
