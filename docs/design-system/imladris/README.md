# Imladris Design System

**Imladris** is the design language of **RetroBoards** — self-hostable forum software that presents durable, Discourse-style topics through a three-pane shell with Outlook-style triage. The product calls itself a **Community Inbox**.

Where most forum software looks cheap, RetroBoards is dressed in Imladris: **parchment-and-evergreen surfaces, a single mallorn-gold accent, an eight-pointed elven star**, set entirely in serif type. It should feel like a councillor's hall — considered, literary, quietly premium — not a toy social app.

> *Status is verified, not asserted; outcomes resolve into artifacts; testimony never outranks the work.*

This project is the **source of truth** for tokens, component source, foundation specimens, and high-fidelity product references. Everything visual derives from CSS variables on `styles.css`. The formerly checked-in `_ds_bundle.js` preview output was retired on 2026-08-27 because this repository has no reproducible compiler for it and its compiled code had drifted from the JSX. Production never loaded it. See `PREVIEW_STATUS.md` before trying to execute an imported React preview.

**Also at the root:** `PRODUCTION.md` (the runtime contract consumers must honour + the production parity matrix) · `production-contract.json` (feature-flag truth) · `manifest.json` (inspected commit, open gaps) · `imladris-spec.md` (the distilled implementation spec: status taxonomy, button and monogram anatomy) · `SKILL.md` · `CHANGELOG.md`.

---

## Sources

Built by reading the product's own code. The authoritative material, in **`henryperkins/community-forums`** (vanilla PHP + MySQL, server-rendered) at commit **`4efe4e33`** (main, 2026-07-14 — see `manifest.json`):

- `public/assets/app.css` — the **authoritative token + component CSS**, transcribed into `tokens/` and `components.css`, values unchanged.
- `templates/partials/*.php` — the real markup (topbar, sidebar, thread_row, post, monogram) the React primitives recreate.
- `PRODUCT_DESIGN.md` — the "Community Inbox" thesis, IA, feature catalog, the tokenised-theme section.
- The *RetroBoards Engineering Handoff* + `TOKENS-REF.md` — drove the fidelity pass and the UI-kit screens.

**Related repos** (Imladris lineage): `henryperkins/hperkins-tokens` (the Imladris WordPress block theme), `henryperkins/imladris-governance-theme`. **Mood references** in `assets/brand/`: `mood-hall.png`, `mood-elements.png` — warm stone, mallorn gold, evergreen, candlelight.

---

## Content fundamentals

The voice is **elevated, plain, and council-minded** — Tolkien-adjacent without cosplay. It treats members as peers keeping shared counsel.

- **Tone.** Warm but serious; considered, never breezy. Sentences can carry a little gravity (*"AI proposes; the council approves."*). No hype, no exclamation marks, no startup-speak.
- **Casing.** **Sentence case** everywhere. The only uppercase is the **Marcellus lapidary caps** for eyebrows, labels, and meta lines (`FOR YOU`, `MARKS OF ESTEEM`) — a typographic device, not shouting.
- **Person.** The reader is **you**; the community is **we / the council**.
- **The lexicon.** Forum concepts are renamed into the register:
  | Generic | Imladris |
  |---|---|
  | reply | **counsel** ("log in to add your counsel") |
  | community | **the council** |
  | like / upvote | **commend** (the gold four-point star) |
  | reputation | **regard** ("Commends earned") |
  | badges | **marks of esteem** |
  | leaderboard | **top contributors** |
  | tiers | **Member · Veteran · Loremaster · Legend** |
  | join date | **"Joined Third Age, 2021"** |
  | search field | *"Search the council…"* |
  - Reaction set: **Commend** (star), **Kindled** (flame), **Seconded** (check), **Illuminating** (sparkle).
- **Emoji.** **Not in UI chrome** — status is always a word + colour, never an emoji. **Authored content is different:** members use emoji in posts, and the composer ships emoji tooling as product features (`:` autocomplete, the picker dialog, custom emoji, GIPHY via slash where configured). The only standalone glyphs are the brand stars.
- **Vibe.** Illuminated manuscript meets a quiet productivity tool. Ceremonial flourishes are allowed in colophons and footnotes — never in functional UI.

---

## Visual foundations

**Colour** — warm, low-chroma, nothing neon. Always from the **semantic** tokens, never raw primitives, so twilight flips for free.
- **Parchment** (`#FAF6EC → #DED2B8`) is the world: `--surface-raised` (cards/topbar), `--surface-page` (the ground), `--surface-sunken` (wells, pills), `--border-hair` (the default 1px line). **Mist** is the cooler neutral alternative.
- **Evergreen** is the brand (`--brand` = green-700 `#2E4A3A`): links, primary buttons, the OP badge, active-row washes (`--brand-subtle`).
- **Mallorn gold** is the **single accent** (`--accent-2` = gold-500 `#C29A44`), used sparingly: unread and active indicators, the commend star, the gilt avatar ring, focus halos. Never a field of gold.
- **Bruinen river-blue** is **info** and the cool counterpoint (artifact links, avatar tints; the DM register). **Ink** (`#1B231D → #94A095`) is the text scale — never pure black.
- **Status carries colour *and* a word**: Solved (leaf), Needs answer (amber), Decision (green), Locked/Pinned (neutral), Archived (dashed + faded), Danger (rust). Full taxonomy in `imladris-spec.md`.
- **Twilight** is the night register (`[data-theme="dark"]`): dark surfaces, parchment becomes the ink, **gold becomes the actionable colour**, evergreen the quiet brand.

**Type** — all serif, four families, self-hosted WOFF2 in `assets/fonts/` with OFL licenses.
- **Cormorant Garamond** — display: headings, wordmark, thread titles, profile names. Medium (500), tight tracking.
- **Marcellus** — lapidary roman caps for eyebrows, **button labels**, chips, meta. Generous letterspacing, uppercase.
- **EB Garamond** — body prose at **17px / 1.62**, measure ≈ 64ch.
- **JetBrains Mono** — routes, counts, timestamps, breadcrumbs; tabular numerals.

**Space & shape.** 4px base (`--space-1…12`). Radii restrained: `sm 4 / md 7 / lg 12 / xl 20 / pill 999`. Cards are `radius-lg` parchment with a hairline border and a soft shadow — not heavy rounding, not coloured left-borders as decoration.

**Shadows.** Warm ink (`rgba(27,35,29,…)`), layered soft, **never hard black**. Five steps (`xs→xl`), plus `--shadow-inset` for wells and **`--gilt`** — the thin 38%-gold inner ring marking "precious" avatars (OP, accepted answer, profile, leaderboard top-3).

**Borders & rules.** 1px hairlines divide. A **3px coloured left-rule** on a card states topic status (gold pinned, leaf solved, amber needs-answer, green decision); the active rail/row marker is an **inset 3px gold rule**. Gold blockquote rules. Dashed borders mean locked, empty, or archived.

**Backgrounds.** Flat parchment — **no gradients** in functional UI. The one decorative move is the faint **eight-point star watermark** (gold-500 at 7–12%) behind profile covers and topic headers. The profile cover is the only dark slab in the day register.

**Motion.** Calm and short: one easing (`--ease-calm` `cubic-bezier(.22,.61,.36,1)`), three durations (140 / 240 / 420ms). Cards lift 1px on hover; buttons settle ~0.5px on press; the new-topic modal does a gentle `rb-rise`. **Nothing bounces, nothing loops.** All motion respects `prefers-reduced-motion`.

**States.** *Hover* — surfaces warm to `--surface-sunken`; cards gain shadow, a green-200 border, 1px lift. *Focus* — an `--accent` outline **plus** a 3px gold halo (`--focus-ring`). *Press* — a tiny scale-down, no colour flip. *Active* — `--brand-subtle` wash + inset gold rule. *On / "mine"* — warm gold fill (`--gold-soft` bg, gold-700 text).

**Transparency.** Used twice, deliberately: the top bar is parchment at ~90% with `blur(10px)`; modals dim the hall behind them.

---

## Iconography

- **Line icons: Lucide** (stroke 1.75–2, round caps). Status glyphs map to Lucide names: Solved→`circle-check`, Needs answer→`circle-help`, Decision→`megaphone`, Pinned→`pin`, Staff→`shield`, Locked→`lock`, Archived→`archive`, Hot→`flame`.
- **Brand marks — custom, do not redraw.** The **eight-pointed elven star** is the house mark (`EightPointStar` / `assets/elven-star.svg`) — solid for the wordmark, thin and faint for watermarks. The **four-point commend star** is the esteem mark (`CommendStar` / `assets/commend-star.svg`, ✦) for commends, the star button, regard, and the accepted-answer flag.
- **Avatars** are monogram initials on a tinted ground (`Monogram`), colour hashed deterministically from the username; real images replace them when present.

---

## Index

**Foundations**
- `styles.css` — the entry point consumers link; `@import`s the closure below.
- `tokens/fonts.css` · `colors.css` · `typography.css` · `spacing.css` — webfonts; colour primitives, semantics and twilight; the serif scale; space, radius, shadow, motion, layout.
- `components.css` — the primitives' CSS, exact values from the live app. `components/doc.css` — the document layer.
- `assets/` — `elven-star.svg`, `commend-star.svg`, `brand/` mood imagery, `fonts/` (WOFF2 + OFL).
- `guidelines/` — 19 foundation specimens shown on the Design System tab: parchment, evergreen, gold, river, ink, twilight, status; type-display / label / body / mono / scale; spacing, radii, shadows; star, motifs, voice, vocabulary.

**Components** — 8 source groups; each has `.jsx` + `.d.ts`, the primitives also a `.prompt.md`, and one `@dsCard` per group. Treat the JSX as reference source unless it has been compiled by the upstream authoring environment.
- `brand/` — `EightPointStar`, `CommendStar`.
- `core/` — `Button`, `Pill`, `Tag`, `Badge`, `Chip`, `Card`.
- `identity/` — `Monogram`, `StarButton`, `Reaction`.
- `forms/` — `Input`, `Textarea`, `Switch`, `ChoiceCard`.
- `forum/` — `ThreadRow`, `Post`, `Composer`, `JoinBar`, `Tabs`, `ParticipantStack`.
- `presence/` — `PresenceList`, `PresenceRow`: the Online roster (leaf dots, live count, `+n more`).
- `admin/` — `AdminNav`, `ADMIN_AREAS`: the admin chrome every `Admin —` template mounts. Pass `area` and nothing else; it renders real hrefs to its sibling templates unless you pass `onNavigate`.
- `doc/` — `DocCover`, `SectionHeader`, `Figure`, `Callout`, `SpecTable`: the printable long-form document layer, twilight-safe; `Figure` renders a fill-in slot with no image.

**Templates** (`templates/<slug>/` — source handoffs a consumer adapts; their `ds-base.js` loaders target the retired upstream preview namespace and are not an executable runtime in this mirror)
- **Member surfaces** — one per route, in the order you meet them: `board-index` (`/`: every board by category, plus the digest / tags / search / notices / compose panes that share this shell) · `forum-inbox` (`/inbox`: the cross-board queue, all fifteen server-backed filters, reading pane) · `board-page` (`/c/{slug}`: board masthead over a compact ruled topic list) · `thread-view` (the council topic: post stream, poll, living-brief slot, composer, warden's tools) · `living-brief` (the evidence-bound brief in its three provenance postures) · `user-profile` (gilt cover, regard, marks of esteem, activity tabs) · `users-online` (sidebar roster beside the member directory) · `account-settings` (grouped rail, engraved forms, live two-factor, sessions, connections, guarded delete).
- **Operator surfaces** — ten `admin-*` templates, all wearing `AdminNav`: `admin-overview` (dashboard & audit) · `admin-content` (boards & tags) · `admin-members` (members & invitations) · `admin-people` (roles & capabilities) · `admin-features` (features & badges) · `admin-settings` (settings & Thread Intelligence) · `admin-appearance` (branding & themes) · `admin-notifications` (email & announcements) · `admin-packages` (packages & registries) · `admin-integrations` (tokens, webhooks & sign-in).
- **Document** — `engineering-handoff`: a long-form reference built from `components/doc/` — cover, numbered sections, figure slots, callouts, spec tables, with a Parchment / Twilight tweak.

**Feature activation** (`feature-ui/` — one designed surface per GA flag, indexed at `feature-ui/index.html`)
- `polls/` · `tags/` · `rail/` (board folders, saved feeds, expanded feeds, bookmark folders) · `organize/` (the same rail features gathered into the one surface a member works in) · `moderation/` (workflow bar, split & merge). `shared/` holds their common chrome.

**UI kits** (`ui_kits/<product>/` — imported authoring references; source remains inspectable, but bundle-dependent interaction requires the upstream compiler)
- `retroboards/` — the **Council Inbox**: the three-pane shell, member/guest split, twilight Profile, Top contributors.
- `auth/` — the **gate**: login, passkeys, register, forgot, reset, MFA, email-verify, OAuth, colophon.
- `dm/` — **private counsel**: one reading room in the cool Bruinen register with a lock signature — grouped letters, overflow controls, the new-message dialog, read receipts.
- `mod/` — **the warden's table**: reports queue, approval hold, appeals review, and the member's own appeal view, with live counts in the subnav.
- `admin/` — the **operator's console**, scoped to drill-ins with no template of their own (user records, API tokens, webhooks, registry trust, providers, the reserved extensions entry). The `admin-*` templates own the rest; the console links out rather than keeping a second copy.
- `system/` — setup wizard, error states (including database-down), privacy, unsubscribe, profile-gated.

---

## Using it

For runnable artifacts in this repository, link `styles.css` and write static HTML with the documented class names (`.thread-row`, `.chip`, `.btn`, `.monogram`, …); see `components.css`. For React work, compile the `.jsx` sources in the consuming application or in the upstream design authoring environment. Do not recreate or hand-edit `_ds_bundle.js` here.

**Fonts are self-hosted.** All four families ship as WOFF2 in `assets/fonts/` with their OFL licenses; `tokens/fonts.css` declares plain `@font-face` — no CDN, no `@import`, matching the app's `style-src 'self'` CSP. The app itself ships no webfonts and falls back to a system serif stack; these files exist so design artifacts render the true register.
