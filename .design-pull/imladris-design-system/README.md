# Imladris Design System

**Imladris** is the design language of **RetroBoards** — self-hostable forum / community software that presents durable, Discourse-style topics through a Slack/email three-pane shell with Outlook-style triage. The product calls itself a **Community Inbox**.

Where most forum software looks cheap, RetroBoards is dressed in **Imladris**: a Rivendell register of **parchment-and-evergreen surfaces, a single mallorn-gold accent, and an eight-pointed elven star**, set entirely in serif type. It should feel like a councillor's hall — considered, literary, and quietly premium — not a toy social app.

> *Status is verified, not asserted; outcomes resolve into artifacts; testimony never outranks the work.*

This project is the **source-of-truth design system**: the tokens consumers link, the reusable React primitives, the foundation specimens, and a high-fidelity recreation of the product (the RetroBoards UI kit). Everything renders from CSS variables on `styles.css`; the components compile into a runtime bundle exposed as `window.ImladrisDesignSystem_c3e027`.

---

## Sources

This system was built by reading the product's own code and design artifacts. If you have access, explore them to design with higher fidelity:

- **`henryperkins/community-forums`** — the RetroBoards codebase (vanilla PHP + MySQL, server-rendered). The authoritative material lived here:
  - `DESIGN.md` — product & technical design (the "Community Inbox" thesis, IA, feature catalog, the tokenised-theme §6.14).
  - `public/assets/app.css` — the **authoritative Imladris token + component CSS**, transcribed into this system's `tokens/` and `components.css` (values unchanged).
  - `templates/partials/*.php` — the real markup (topbar, sidebar, thread_row, post, monogram) the React primitives recreate.
  - `.design-pull/imladris-council-forum-design/` — the *RetroBoards Engineering Handoff* (11 sections + figures), `TOKENS-REF.md`, `imladris-spec.md`, and rendered figures (`doc-figures/`). These drove the visual fidelity pass and the screens in the UI kit.
- **Related repos** (Imladris lineage, for deeper context): `henryperkins/hperkins-tokens` (the *Imladris* WordPress block theme — "theme.json is the source of truth"), `henryperkins/imladris-governance-theme`.
- **Mood references** (`assets/brand/`): `mood-hall.png` (a marble-and-gold hall with evergreen banners and engraved stars) and `mood-elements.png` (a flat-lay of engraved cards, badges, pills, seals). They set the register: warm stone, mallorn gold, evergreen, candlelight.

> The design-system project id in the handoff manifest (`imladris-design-system-89e0d236-…`) **is this project** — RetroBoards consumes what is authored here.

---

## Content fundamentals

The voice is **elevated, plain, and council-minded** — Tolkien-adjacent without cosplay. It treats members as peers keeping shared counsel.

- **Tone.** Warm but serious; considered, never breezy. Sentences can carry a little gravity (*"AI proposes; the council approves."*). Avoid hype, exclamation marks, and startup-speak.
- **Casing.** **Sentence case** everywhere — headings, buttons, chips-as-words. The only uppercase is the **Marcellus lapidary caps** used for eyebrows, labels, and meta lines (`FOR YOU`, `MARKS OF ESTEEM`, `COMMENDS EARNED`) — a typographic device, not shouting.
- **Person.** Address the reader as **you** ("You're browsing as a guest"); the community is **we / the council**. The product speaks plainly in the first person plural about shared norms.
- **The lexicon.** Forum concepts are renamed into the register — use these words:
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
- **Emoji.** **Not used.** The only "emoji-like" glyphs are the brand stars (✦ commend, the eight-point house mark) and **Lucide** line icons. Status is always carried by **a word + colour**, never an emoji.
- **Vibe.** Illuminated-manuscript meets a quiet productivity tool. Closing flourishes are allowed in ceremonial spots (a colophon, a leaderboard footnote) — e.g. the Quenya line *"Et Eärello Endorenna utúlien."* — but never in functional UI.

---

## Visual foundations

**Colour.** A warm, low-chroma palette — nothing neon.
- **Parchment** (`#FAF6EC → #DED2B8`) is the world: `--surface-raised` (cards/topbar), `--surface-page` (the ground), `--surface-sunken` (wells, pills), `--border-hair` (the default 1px line). **Mist** is a cooler neutral alternative.
- **Evergreen** is the brand (`--brand` = `green-700 #2E4A3A`): links, primary buttons, the OP badge, active-row washes (`--brand-subtle` = `green-050`).
- **Mallorn gold** is the **single accent** (`--accent-2` = `gold-500 #C29A44`), used sparingly: active/unread indicators, the star/commend, the gilt avatar ring, focus halos. Never a field of gold.
- **Bruinen river-blue** is **info** and a cool counterpoint (artifact links, avatar tints). **Ink** (`#1B231D → #94A095`) is the text scale — never pure black.
- **Status ledger** carries colour *and a word*: Solved (leaf/green-050), Needs answer (amber/gold-100), Decision (green/brand-subtle), Locked/Pinned (neutral), Archived (dashed + faded), Danger (rust).
- **Twilight** is the night register (`[data-theme="dark"]`): dark surfaces, parchment becomes the ink, **gold becomes the actionable colour**, evergreen the quiet brand.

**Type.** All serif, four families (real Google Fonts; fallback stacks keep the register before they load):
- **Cormorant Garamond** — display (headings, wordmark, thread titles, profile names). Set **medium (500)**, tight tracking, balanced.
- **Marcellus** — lapidary roman caps for eyebrows, **button labels**, chips, meta lines. Generous letterspacing, UPPERCASE.
- **EB Garamond** — body prose / posts at **17px / 1.62**, measure ≈ 64ch.
- **JetBrains Mono** — routes, counts, timestamps, breadcrumbs; tabular numerals.

**Space & shape.** Even rhythm on a 4px base (`--space-1…12`). Radii are **restrained**: `sm 4 / md 7 / lg 12 / xl 20 / pill 999`. Cards are `radius-lg` parchment with a **hairline border** and a soft shadow — *not* heavy rounding or coloured left-borders.

**Shadows.** Warm ink, layered soft (`rgba(27,35,29,…)`) — **never hard black**. Five steps (`xs→xl`) plus `--shadow-inset` for wells and the **`--gilt`** ring (a thin 38%-gold inner ring) that marks "precious" avatars (OP, accepted answer, profile, leaderboard top-3).

**Borders & rules.** 1px hairlines (`--border-hair`) divide; **a 3px coloured left-rule** on a card communicates topic status (gold = pinned, leaf = solved, amber = needs-answer, green = decision) and the active rail/row marker is an **inset 3px gold rule**. Gold blockquote rules; dashed borders mean "locked/empty/archived".

**Backgrounds.** Flat parchment — **no gradients** in functional UI. The one decorative move is the **faint eight-point star watermark** (`gold-500` at ~7–12% opacity) behind profile covers and topic headers. The profile cover is the only "dark slab" (twilight surface) in the day register. Mood imagery is warm stone, candlelight, evergreen — cinematic but restrained.

**Motion.** Calm and short. One easing — `--ease-calm` `cubic-bezier(.22,.61,.36,1)` — and three durations (`140 / 240 / 420ms`). Cards lift 1px on hover (`translateY(-1px)` + shadow step); buttons settle ~0.5px on press. The new-topic modal does a gentle `rb-rise`. **Nothing bounces; no infinite loops.** All motion respects `prefers-reduced-motion`.

**States.**
- *Hover* — surfaces warm to `--surface-sunken`; cards gain shadow + a `green-200` border + 1px lift; links warm their gold underline.
- *Focus* — a high-contrast `--accent` outline **plus** a 3px gold halo (`--focus-ring`).
- *Press* — a tiny scale-down (no colour flip).
- *Active/selected* — `--brand-subtle` wash + inset gold rule.
- *On/"mine"* (stars, reactions) — warm gold fill (`--gold-soft` bg, `gold-700` text/border).

**Transparency & blur.** Used once, deliberately: the **top bar** is parchment at ~90% opacity with a `blur(10px)` candlelit backdrop. Modals dim the hall behind them (`rgba(22,29,36,.42)` + slight blur).

**Cards, at a glance.** Parchment `--surface-raised`, `--border-hair` 1px, `--radius-lg`, `--shadow-xs` (lifting to `--shadow-md` on hover). Composer cards use a stronger border + inset shadow. The accepted-answer card is a `--surface-done` green plate with a leaf left-border.

---

## Iconography

- **Line icons: Lucide.** The product's SVGs are Lucide (stroke ~1.75–2, round caps/joins) — bell, gear, inbox, at-sign, eye, file, users, trophy, menu, check, lock, archive, flame, sparkles. Reuse Lucide (CDN or inline paths) for any new icon; match the stroke weight. Status glyphs map to Lucide names: Solved→`circle-check`, Needs answer→`circle-help`, Decision→`megaphone`, Pinned→`pin`, Staff→`shield`, Locked→`lock`, Archived→`archive`, Hot→`flame`.
- **Brand marks (custom, in `assets/` + as components).** The **eight-pointed elven star** is the house mark (`EightPointStar` / `assets/elven-star.svg`) — solid for the wordmark, thin + faint for watermarks. The **four-point commend star** is the esteem mark (`CommendStar` / `assets/commend-star.svg`, ✦) used for commends, the star button, reputation, and the accepted-answer flag. These are the brand's own defined paths — do **not** redraw them; use the components/assets.
- **No emoji**, ever, in UI. The only Unicode glyph used standalone is **✦** for the commend star where an inline SVG is impractical.
- **Avatars** are monogram initials on a tinted ground (`Monogram`), colour hashed deterministically from the username; real images replace them when present.

---

## Index — what's here

**Foundations**
- `styles.css` — the entry point consumers link; `@import`s the closure below (nothing inline).
- `tokens/fonts.css` · `tokens/colors.css` · `tokens/typography.css` · `tokens/spacing.css` — webfonts, colour primitives + semantics + twilight, the serif scale + base type, and space/radius/shadow/motion/layout.
- `components.css` — the reusable primitives' CSS (exact values from the live app), shipped in the closure.
- `assets/elven-star.svg`, `assets/commend-star.svg`, `assets/brand/` — brand marks and mood imagery.
- `guidelines/*.card.html` — 19 foundation specimens (Colors, Type, Spacing, Brand) shown on the Design System tab.

**Components** (`window.ImladrisDesignSystem_c3e027`; each has `.jsx` + `.d.ts` — the primitives also carry a `.prompt.md` — with one `@dsCard` per group)
- `components/brand/` — `EightPointStar`, `CommendStar`.
- `components/core/` — `Button`, `Pill`, `Tag`, `Badge`, `Chip`, `Card`.
- `components/identity/` — `Monogram`, `StarButton`, `Reaction`.
- `components/forms/` — `Input`, `Textarea`, `Switch`, `ChoiceCard`.
- `components/forum/` — `ThreadRow`, `Post`, `Composer`, `JoinBar`, `Tabs`, `ParticipantStack`.
- `components/doc/` — `DocCover`, `SectionHeader`, `Figure`, `Callout`, `SpecTable`: the **long-form document layer** (a printable engineering reference). Class-based, painted from the semantic tokens, and twilight-safe; `Figure` renders a fill-in slot when given no image. Styles ship in `components/doc.css` (imported by `styles.css`).

**UI kits** (`ui_kits/<product>/` — interactive, high-fidelity recreations of real product surfaces; each composes the primitives above)
- `ui_kits/retroboards/` — the **Council Inbox**: the three-pane shell (top bar · rail · inbox · conversation), a member/guest split, a twilight **Profile**, and the **Top contributors** leaderboard.
- `ui_kits/settings/` — the **account settings console**: a sticky left-rail subnav over 13 sections (Profile, Security with a 2FA enrolment flow, Privacy, Appearance, Reading, Composing, Drafts, Notifications, Connections, Sessions, Blocks, Boards, Account lifecycle). The natural home of the **lapidary forms register** (engraved scribe-panels, set-gem toggles, jewel checks).
- `ui_kits/auth/` — the **gate**: login, register, forgot, reset, two-factor (MFA), and email-verify, framed in the ceremonial twilight register with OAuth and a colophon.
- `ui_kits/admin/` — the **admin console** (operator's desk): dashboard (site name · trust & safety · audit log), boards & categories, users + a user-record drill-in, badge rules, tags, email delivery (queue + log + suppressions), webhooks, API tokens, announcements, extensions, and a live-preview branding editor. Utilitarian register — dense `.audit` tables, stat-cards, and config cards.
- `ui_kits/dm/` — **private counsel** (direct & group messages): a two-pane reading room (conversation list · open letter), mine/theirs message plates, the group-members panel with owner tools, in-message reference cards, the report affordance, and the new-message composer. Recreates `templates/dm/{index,new,show}.php`.
- `ui_kits/mod/` — **the warden's table** (moderation triage, distinct from admin config): the reports queue (post + DM targets, claim/resolve/dismiss), the approval hold (topics + replies), appeals review (outcome + resolution note), and the member's own appeal view — with live queue counts in the subnav. Recreates `templates/mod/{reports,approvals,appeals}.php` + `templates/appeals/index.php`.
- Each kit has its own `README.md`.

**Templates** (`templates/<slug>/` — starting folders a consumer copies; each is a `.dc.html` entry that loads the system via `ds-base.js`)
- `templates/council-topic/` — a single council topic (a thread with its counsel).
- `templates/engineering-handoff/` — a long-form **engineering handoff / product reference** assembled from the `components/doc/` set: cover, numbered sections, drop-in figure slots, callouts, and spec tables, with a Parchment / Twilight tweak.

---

## Using it

Link the one stylesheet and read components off the namespace:

```html
<link rel="stylesheet" href="styles.css">
<script src="_ds_bundle.js"></script>   <!-- compiled; exposes window.ImladrisDesignSystem_c3e027 -->
<script type="text/babel">
  const { Button, ThreadRow, Post } = window.ImladrisDesignSystem_c3e027;
</script>
```

Colour from the **semantic** tokens (`--surface-raised`, `--brand`, `--accent-2`, `--on-done`), not raw primitives, so the twilight register flips for free.

### Caveat — fonts
The Imladris families load from **Google Fonts** (`tokens/fonts.css`, via `@import`); the live product self-hosts none (its strict CSP forbids the CDN) and falls back to a system-serif stack. To ship offline, swap the `@import` for local `@font-face` rules under `assets/fonts/` — the family names and fallbacks don't change.
