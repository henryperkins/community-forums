# Imladris implementation spec (distilled from the canvases)

Source canvases staged in `.cache/`: `RetroBoards.dc.html` (single-viewport 3-pane SPA — the
primary blueprint), `Hall of Fire.dc.html` (multi-frame: Community Inbox, Guest, New-topic,
Profile, Vocabulary), `RetroBoards Mobile.dc.html` (6 iOS frames). Implemented as a **translation**
into the vanilla-PHP / strict-CSP app, not a copy. Tokens live in `public/assets/app.css :root`.

## Status taxonomy (label → left-rule → chip text/bg/border → icon)
| State | left-rule | chip text | chip bg | border | icon |
|---|---|---|---|---|---|
| Solved | `--success`/`--leaf` | `--on-done` | `--surface-done` | `--green-200` | circle-check |
| Needs answer | `--warning`/`--amber` | `--on-review` | `--surface-review` | `--gold-200` | circle-? |
| Decision | `--green-600` | `--green-800` | `--brand-subtle` | `--green-200` | megaphone |
| Hot | `--rust` | `--rust` | mix(rust 10% / parchment-50) | mix(rust 28%) | flame |
| Pinned / Staff notice | `--gold-400` | `--gold-700`/`--green-800` | `--gold-100`/`--brand-subtle` | `--gold-200`/`--green-200` | pin / shield |
| Locked / Archived | — | `--text-muted` | `--surface-sunken` | `--border-hair` | padlock / archive |
| Unread (marker) | `--accent-2` (gold) | dot `--accent-2` | — | — | dot |
| Plain | `--border-hair`/transparent | — | — | — | — |

Selected/open thread row: bg `--brand-subtle` + left-rule `--leaf` (RetroBoards) / `inset 3px 0 0 --accent-2` (rail).

## Monogram swatches `av(color,size,gilt)` — tinted ground + dark ink
green→`--green-100`/`--green-800`, river→`--river-100`/`--river-700`, gold→`--gold-100`/`--gold-700`.
"Precious" avatars (OP, accepted answer, profile, leaderboard top-3) add `.monogram-gilt` (`--gilt` ring).
Implemented in `.mono-0..9` (rotating green/river/gold/mist) + `.monogram-gilt`.

## Buttons
- primary: `--accent` (green-700) bg / `--accent-contrast` text, hover `--brand-hover`. `.btn`
- secondary: parchment outline (`--surface-raised` / `--border-soft`). `.btn-secondary`
- ghost: transparent, hover `--surface-sunken`. `.btn-ghost`
- accent (gold): `--gold-500` bg / `--ink-900` text. `.btn-accent`
- pill/segment toggle: active `--accent` fill; idle `--text-muted` on `--border-hair` shell.
All button labels: `--font-label` (Marcellus) tracked, sentence case.

## Reactions / reputation (sample labels — keep the repo's real reaction set; restyle chips)
Canvas names: **Commend** (4-pt star, RetroBoards), **Kindled** (flame), **Seconded** (check),
**Illuminating** (sparkle). Reactions read "Name · count". "on"/mine state = gold
(`--gold-soft` bg, `--gold-700` text/border). Reputation noun = "Regard"/"Commends"; star is
`--star` (gold-600); formats: post-gutter `4.2k`, leaderboard/profile/accepted `2,140`.

## Eight-pointed elven star SVGs (presentation attributes only — no inline style)
- Full (brand/watermark/empty), viewBox 0 0 100 100:
  outer `M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z`,
  inner `M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z` (opacity .5), center `circle cx50 cy50 r5`.
- Simple 8-point: `M50 6 L59 41 L94 50 L59 59 L50 94 L41 59 L6 50 L41 41 Z` (center r6).
- Filled 4-pt "commend" star: `M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z` (fill currentColor).
Brand mark uses the full star (in topbar.php). Faint watermark = same at `opacity .06`, `--green-400`.

## Per-surface blueprint (RetroBoards.dc.html)
- **Topbar** h62, parchment ~90% + `blur(10px)`, border-hair. Brand star+wordmark · pill search
  "Search the council…" (+ ⌘K hint only in Hall of Fire) · bell+gold dot · identity (avatar 30 green-700/parchment + leaf presence dot, name, gear, log out).  [DONE in Phase 1]
- **Rail** w248–272, `--surface-sunken` (HoF parchment-200). Quick filters w/ active inset gold rule;
  Marcellus uppercase gold-700 category headers; gold `#` board rows + count pills; DM list w/ presence dots (leaf/amber/ink-300).  [DONE in Phase 1 except DM list + counts]
- **Thread list** (mid pane / `board.php`): header (#name display + desc + Watching/New-topic + tabs All/Unread/Starred/Mine + sort), rows per status table above.  [Phase 2/3]
- **Conversation** (`thread.php`/`post.php`): header (faint star watermark, breadcrumb, action icons,
  display h2 30px, status pill, "Opened by X · N replies", participant avatar stack +N); day dividers;
  OP post (avatar 38–52, OP badge, body 16–17.5px EB measure 64ch, gold blockquote rule, reactions);
  **accepted answer** = `--surface-done` bg + `--green-200` border + radius-lg + gilt-ring avatar +
  "Marked as the answer" (check, `--on-done`) + reputation star.  [Phase 2]
- **Composer** (the shared shell — one contained box for reply / new-topic / DM / edit,
  COMPOSER.md v0.8): box `--surface-raised`/`--border-strong`/radius-lg/inset shadow,
  focus-within gold-400 + halo. Engraved ICON toolbar row (28px buttons, 17px stroke-1.8 SVGs:
  bold/italic/strike/code | quote/H2/bullet/numbered/codeblock/spoiler | link; `＋` overflow on
  narrow) toggled by the `Aa` action-bar button. Borderless serif textarea in-box (canonical
  Markdown; WYSIWYG mounts over it); upload tray; action bar = Aa · `＋` attach · 😊 emoji ·
  "as **Name**" identity · Anonymous chip | Preview · circular ✒ send. Meta row below:
  "Draft saved · Discard" · disclosure · mono counter (near-limit only).  [Live]
- **Leaderboard** (`leaderboard.php`): top-3 cards (roman numerals gold-600, gilt avatars, badge chips,
  big rep), then compact rows; footnote card (green-050, gold left-rule, italic).  [Phase 5]
- **Profile** (`profile/show.php`): twilight cover + faint gold star watermark; overhanging gilt avatar
  + leaf presence dot; tier pill; stats bar (Regard cell highlighted gold-100); badge chips (brand/
  accent/neutral/locked-dashed); Overview/Threads/Posts tabs; activity rows w/ icon tiles.  [Phase 5]
- **Settings** (`account/*`): left subnav with active inset gold rule; theme cards (Parchment/Twilight/
  Auto swatches); density cards; toggles (green track + parchment knob).  [Phase 5]
- **New-topic modal / compose**: overlay `rgba(22,29,36,.42)` + blur; dialog parchment-50 radius-lg
  shadow-xl rb-rise; board `<select>`; big display title input w/ focus-ring; serif body textarea.  [Phase 3/5]

## Mobile (RetroBoards Mobile.dc.html — 6 frames)
Inbox (filter chips row, list rows avatar40 + status pill + meta, FAB +), Nav **drawer** (off-canvas
322px + scrim `rgba(22,29,36,.5)`, ring-grouped boards, footer identity+gear), Thread+reply (back
chevron, sticky bottom composer pill + circular send), New topic (Cancel/Post header, board select,
title+body), Guest (read-only + bottom Log in/Sign up bar), Leaderboard (roman ranks, gilt). 44px tap
targets throughout.  [Phase 4]

## Layout deltas to honor
- Active rule: rail `inset 3px 0 0 --accent-2`; selected thread `--leaf`; pinned `--gold-400`.
- OP badge: green (RetroBoards) vs gold (Hall of Fire) — use green (`--brand-subtle`/`--green-800`).
- Wordmark: Cormorant display ("RetroBoards") vs tracked Marcellus caps ("THE HALL OF FIRE") — using
  the operator `site_name` in Cormorant (brand-name).
