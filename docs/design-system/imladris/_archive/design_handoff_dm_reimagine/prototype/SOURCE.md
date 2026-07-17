# Prototype source — DM reimagine (integrated + private register)

Un-bundled source of the reference prototype. **Design reference, not code to ship** — recreate in the PHP templates + `app.css` + `app.js` per `../README.md`.

Load order (see `index.html`): React + Babel → the Imladris DS bundle → `data.js` → `DMTopbar` → `NavRail` → `Overlays` → `ConvoList` → `Thread` → `InfoRail` → `DMApp`.

The **private-counsel register** is the canonical warm register — **parchment surfaces + the single mallorn gold** (active · unread · focus · esteem), exactly like the forum. Messages is told apart by a **word, not a colour**: the lock + "Private counsel" signature (the `.dm-thread-eyebrow` / `.dm-day` rules), backed by the *"only those named here can read"* divider. The room tokens live in `kit.css` under the `.app-root` `--dm-*` variables (all pointed at parchment/gold — **no river**).

---

## index.html

````html
<!-- @dsCard group="Messages" viewport="1320x820" name="Private counsel" subtitle="Reimagined direct & group messages — one reading room (list · conversation · collapsible details rail), grouped letters, tucked-away controls, new-message dialog" -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RetroBoards — messages</title>
<link rel="stylesheet" href="../../styles.css">
<link rel="stylesheet" href="kit.css">
</head>
<body>
<div id="root"></div>
<template id="__bundler_thumbnail"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#2E4A3A"/><path d="M28 36h44a5 5 0 0 1 5 5v20a5 5 0 0 1-5 5H46l-12 9v-9h-6a5 5 0 0 1-5-5V41a5 5 0 0 1 5-5z" fill="#F4EBCF"/><circle cx="42" cy="51" r="3.4" fill="#2E4A3A"/><circle cx="54" cy="51" r="3.4" fill="#2E4A3A"/><circle cx="66" cy="51" r="3.4" fill="#C29A44"/></svg></template>

<template id="__bundler_thumbnail">
<svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
  <rect width="400" height="300" fill="#EDE6D6"/>
  <rect x="120" y="94" width="160" height="112" rx="14" fill="#2E4A3A"/>
  <path d="M200 108 L211 143 L246 150 L211 157 L200 192 L189 157 L154 150 L189 143 Z" fill="#C29A44"/>
</svg>
</template>

<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>

<!-- Imladris design-system bundle (compiled) -->
<script src="../../_ds_bundle.js"></script>

<!-- Seed data (plain JS) -->
<script src="data.js"></script>

<!-- Kit screens (JSX, transpiled in order; App is last) -->
<script type="text/babel" src="DMTopbar.jsx"></script>
<script type="text/babel" src="Overlays.jsx"></script>
<script type="text/babel" src="NavRail.jsx"></script>
<script type="text/babel" src="ConvoList.jsx"></script>
<script type="text/babel" src="Thread.jsx"></script>
<script type="text/babel" src="InfoRail.jsx"></script>
<script type="text/babel" src="DMApp.jsx"></script>
<script type="text/babel" data-presets="react">
  ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(window.DMApp));
</script>
</body>
</html>

````
## kit.css

````css
/* ──────────────────────────────────────────────────────────────────────────
   Messages UI kit — private counsel  ·  v2, reimagined
   ----------------------------------------------------------------------------
   The brief: the old DM UI felt "everywhere" — several near-identical screens
   (inbox / empty / thread / a full-pane composer) that all led to the same
   place, controls (mute · leave · report · members) shouting all at once, and
   *every* element boxed in its own bordered card.

   The fix keeps the full ceremonial Imladris register (Cormorant / Marcellus /
   EB Garamond / JetBrains Mono, parchment + evergreen + a single mallorn gold,
   gilt monograms, the eight-point star) but re-homes everything into ONE
   reading room:

     ┌──────────┬───────────────────────────┬──────────────┐
     │  list    │       the conversation     │  details rail │  ← collapsible
     └──────────┴───────────────────────────┴──────────────┘

   • One surface, not four. New message is a *dialog over* the room, never a
     co-equal screen. The right pane is always "the conversation".
   • Controls are tucked away: a single ··· overflow in the header, a ···
     revealed on hover per message. Nothing secondary is visible at rest.
   • De-boxed letters, not bubbles-as-cards. Consecutive messages group under
     one author line; theirs read as plain counsel on parchment, mine wear the
     one ceremonial gold plate. Members / owner tools / person info all move
     OUT of the message flow and INTO the details rail.

   Reuses the design-system primitives (styles.css + _ds_bundle.js); this file
   is only the product layout that composes them.
   ────────────────────────────────────────────────────────────────────────── */

html, body { height: 100%; }
body { margin: 0; background: var(--surface-page); }
.app-root {
    min-height: 100vh; display: flex; flex-direction: column;
    /* Private-counsel register — aligned to the canonical Imladris hall: warm
       parchment surfaces and the single mallorn-gold accent (active · unread ·
       focus · esteem), exactly like the forum and every other kit. Messages is
       still told apart from a public thread — but by a WORD, not a colour: the
       lock + "Private counsel" eyebrow and the "only those named here can read"
       divider (status is a word + colour, never colour alone). No cool
       counter-register here — river is reserved for its canonical use elsewhere
       (info flashes, the privacy gem, artifact links), never as a room tint. */
    --dm-ground:      var(--surface-page);
    --dm-raised:      var(--surface-raised);
    --dm-sunken:      var(--surface-sunken);
    --dm-accent:      var(--accent-2);        /* mallorn gold — the one accent  */
    --dm-accent-soft: var(--gold-soft);
    --dm-accent-line: var(--gold-200);
    --dm-accent-ink:  var(--gold-ink);
    --dm-active-wash: var(--brand-subtle);    /* green-050 wash + inset gold rule */
}
*, *::before, *::after { box-sizing: border-box; }

/* ── Top bar — parchment ~90% + candlelit blur (shared chrome) ──────────── */
.topbar {
    min-height: var(--topbar-h);
    background: color-mix(in srgb, var(--surface-raised) 90%, transparent);
    -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--border-hair);
    position: sticky; top: 0; z-index: 30;
}
.topbar-inner { max-width: var(--maxw); margin: 0 auto; min-height: var(--topbar-h); padding: 0 18px; display: flex; align-items: center; gap: 18px; }
.brand { display: inline-flex; align-items: center; gap: 11px; color: var(--brand); text-decoration: none; cursor: pointer; }
.brand-name { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.4rem; color: var(--text-strong); letter-spacing: .005em; line-height: 1; }
.topbar-search { flex: 1; display: flex; justify-content: center; }
.topbar-search .input { max-width: 420px; }
.topbar-right { display: flex; align-items: center; gap: 14px; }
.bell { position: relative; display: inline-flex; align-items: center; color: var(--text-muted); cursor: pointer; }
.bell:hover { color: var(--brand); }
.bell-ic { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; display: block; }
.bell-dot { position: absolute; top: -3px; right: -4px; width: 8px; height: 8px; border-radius: 50%; background: var(--accent-2); box-shadow: 0 0 0 2px var(--surface-raised); }
.bell-badge { position: absolute; top: -6px; right: -8px; min-width: 15px; height: 15px; padding: 0 3px; border-radius: 8px; background: var(--accent-2); color: var(--ink-900); font-family: var(--font-mono); font-size: 10px; line-height: 15px; text-align: center; font-weight: 700; box-shadow: 0 0 0 2px var(--surface-raised); }
.topbar-user { display: inline-flex; align-items: center; gap: 9px; color: var(--text-strong); cursor: pointer; }
.topbar-user .topbar-name { font-family: var(--font-body); font-weight: var(--weight-medium); font-size: .95rem; }
.topbar-ic { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; display: block; color: var(--text-muted); cursor: pointer; }
.topbar-logout { font-family: var(--font-label); letter-spacing: .03em; font-size: .85rem; color: var(--text-muted); background: none; border: 0; cursor: pointer; }
.topbar-logout:hover { color: var(--brand); }

/* ── The reading room — list · conversation · (collapsible) details rail ── */
.dm-shell {
    width: 100%; display: grid; flex: 1; min-height: 0;
    grid-template-columns: 212px minmax(288px, 336px) minmax(0, 1fr);
    height: calc(100vh - var(--topbar-h));
    transition: grid-template-columns var(--dur-2, 240ms) var(--ease-calm);
}
.dm-shell.has-rail { grid-template-columns: 212px minmax(288px, 320px) minmax(0, 1fr) minmax(280px, 320px); }

/* ── Product nav rail (left-most) — grounds Messages inside the forum ───── */
/* Adopted from the flagship inbox: DMs read as one place in the product, not a
   floating island. Quiet sunken column; the active item wears the gold rule. */
.dm-navrail { border-right: 1px solid var(--border-hair); background: var(--surface-sunken); padding: 14px 11px; display: flex; flex-direction: column; gap: 1px; min-height: 0; overflow-y: auto; }
.dm-nav-item { display: flex; align-items: center; gap: 9px; padding: 8px 11px; border-radius: var(--radius-sm); color: var(--text-body); font-family: var(--font-body); font-size: .95rem; text-decoration: none; cursor: pointer; }
.dm-nav-item:hover { background: var(--surface-raised); }
.dm-nav-item .dm-nav-ic { display: inline-flex; flex: 0 0 auto; }
.dm-nav-item .dm-nav-ic svg { width: 16px; height: 16px; fill: none; stroke: var(--text-faint); stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round; display: block; }
.dm-nav-item > span:nth-child(2) { flex: 1; }
.dm-nav-item.is-active { background: var(--green-050); box-shadow: inset 3px 0 0 var(--gold-500); color: var(--on-brand-subtle); font-weight: var(--weight-semibold); }
.dm-nav-item.is-active .dm-nav-ic svg { stroke: var(--brand); }
.dm-nav-count { font-family: var(--font-mono); font-size: .7rem; background: var(--brand); color: var(--accent-contrast); border-radius: 8px; padding: 0 6px; line-height: 16px; }
.dm-nav-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent-2); flex: 0 0 auto; }
.dm-nav-sec { margin-top: 16px; }
.dm-nav-sec-head { font-family: var(--font-label); font-size: .7rem; text-transform: uppercase; letter-spacing: var(--tracking-caps); color: var(--gold-ink); padding: 0 11px 8px; }
.dm-nav-compose { display: flex; align-items: center; gap: 8px; width: 100%; padding: 9px 11px; border-radius: var(--radius-md); border: 1px solid var(--green-200); background: var(--surface-raised); color: var(--brand); font-family: var(--font-label); letter-spacing: .05em; text-transform: uppercase; font-size: .72rem; cursor: pointer; box-shadow: var(--shadow-xs); }
.dm-nav-compose:hover { background: var(--brand-subtle); }
.dm-nav-compose svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ── List pane (left) ───────────────────────────────────────────────────── */
.dm-listpane { border-right: 1px solid var(--border-hair); background: var(--dm-ground); display: flex; flex-direction: column; min-height: 0; }
.dm-listpane-head { padding: 16px 16px 12px; border-bottom: 1px solid var(--border-hair); display: flex; flex-direction: column; gap: 12px; }
.dm-listpane-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
.dm-listpane-top > span:first-child { min-width: 0; }
.dm-listpane-head .eyebrow { margin: 0 0 2px; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; color: var(--dm-accent-ink); }
.dm-listpane-head h1 { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.5rem; margin: 0; line-height: 1.05; color: var(--text-strong); }
/* Round "new message" action — quiet gold, the one invitation in the header */
.dm-new-btn {
    flex: 0 0 auto; width: 34px; height: 34px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: var(--surface-raised); border: 1px solid var(--border-hair);
    color: var(--brand); cursor: pointer; box-shadow: var(--shadow-xs);
    transition: background var(--dur-1, 140ms) var(--ease-calm), border-color var(--dur-1, 140ms) var(--ease-calm), transform var(--dur-1, 140ms) var(--ease-calm);
}
.dm-new-btn:hover { background: var(--brand-subtle); border-color: var(--green-200); transform: translateY(-1px); }
.dm-new-btn svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Conversation search — findability, kept quiet */
.dm-search { position: relative; display: flex; align-items: center; }
.dm-search svg { position: absolute; left: 12px; width: 15px; height: 15px; fill: none; stroke: var(--text-faint); stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }
.dm-search input {
    width: 100%; font: inherit; font-family: var(--font-body); font-size: .92rem; color: var(--text-body);
    padding: 8px 12px 8px 34px; background: var(--dm-sunken);
    border: 1px solid transparent; border-radius: var(--radius-pill); outline: none;
}
.dm-search input::placeholder { color: var(--text-faint); }
.dm-search input:focus { background: var(--dm-raised); border-color: var(--dm-accent-line); box-shadow: 0 0 0 3px var(--focus-ring); }

.dm-filter { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.dm-count { font-family: var(--font-mono); font-size: .68rem; color: var(--text-faint); white-space: nowrap; }
/* Filter chips — small-caps Marcellus pills (from the flagship inbox) */
.dm-chips { display: flex; gap: 5px; }
.dm-chip { font-family: var(--font-label); font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); background: none; border: 1px solid transparent; border-radius: var(--radius-pill); padding: 4px 12px; cursor: pointer; transition: color var(--dur-1,140ms) var(--ease-calm), background var(--dur-1,140ms) var(--ease-calm); }
.dm-chip:hover { color: var(--on-brand-subtle); }
.dm-chip.is-active { color: var(--dm-accent-ink); background: var(--dm-accent-soft); border-color: var(--dm-accent-line); }

.dm-list { list-style: none; margin: 0; padding: 7px 8px 24px; overflow-y: auto; min-height: 0; display: flex; flex-direction: column; gap: 1px; }
.dm-row {
    display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 3px 11px; align-items: center;
    width: 100%; text-align: left; background: none; border: 0; cursor: pointer;
    padding: 10px 12px; border-radius: var(--radius-md); font: inherit; position: relative;
    transition: background var(--dur-1, 140ms) var(--ease-calm);
}
.dm-row:hover { background: var(--dm-sunken); }
.dm-row.active { background: var(--dm-active-wash); box-shadow: inset 3px 0 0 var(--dm-accent); }
.dm-row .monogram { grid-row: 1 / span 2; }
.dm-row-top { display: flex; align-items: baseline; gap: 7px; min-width: 0; grid-column: 2; grid-row: 1; }
.dm-other { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.06rem; color: var(--text-strong); line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dm-row.is-unread .dm-other { font-weight: var(--weight-semibold); }
.dm-time { grid-column: 3; grid-row: 1; font-family: var(--font-mono); font-size: .68rem; color: var(--text-faint); white-space: nowrap; align-self: baseline; }
.dm-row.is-unread .dm-time { color: var(--dm-accent-ink); }
.dm-preview { grid-column: 2 / span 2; grid-row: 2; font-family: var(--font-body); font-size: .9rem; color: var(--text-muted); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.dm-row.is-unread .dm-preview { color: var(--text-body); }
/* Read rows recede — dimmer title + preview (from the flagship inbox hierarchy) */
.dm-row:not(.is-unread) .dm-other { color: var(--text-body); }
.dm-row:not(.is-unread) .dm-preview { color: var(--text-faint); }
/* Unread marker — a single gold dot at the far right, nothing more */
.dm-unread-dot { grid-column: 3; grid-row: 2; justify-self: end; width: 7px; height: 7px; border-radius: 50%; background: var(--dm-accent); align-self: center; }
.dm-list-empty { padding: 40px 22px; color: var(--text-muted); text-align: center; font-family: var(--font-body); }

/* ── Conversation pane (centre) ─────────────────────────────────────────── */
.dm-threadpane { background: var(--dm-raised); display: flex; flex-direction: column; min-height: 0; min-width: 0; }
.dm-thread-head { padding: 13px 22px; border-bottom: 1px solid var(--border-hair); display: flex; align-items: center; gap: 12px; flex: 0 0 auto; }
.dm-back { display: none; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: var(--radius-md); background: none; border: 0; color: var(--text-muted); cursor: pointer; flex: 0 0 auto; }
.dm-back:hover { background: var(--surface-sunken); color: var(--brand); }
.dm-back svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.dm-thread-id { display: flex; align-items: center; gap: 11px; min-width: 0; flex: 1 1 auto; cursor: default; }
.dm-thread-id > div { min-width: 0; }
.dm-thread-title { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.28rem; line-height: 1.12; margin: 0; color: var(--text-strong); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dm-thread-sub { font-family: var(--font-mono); font-size: .72rem; color: var(--text-muted); margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 7px; }
.dm-presence-dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; }
.dm-presence-dot.online { background: var(--presence); }
.dm-presence-dot.away { background: var(--amber); }
.dm-presence-dot.offline { background: var(--ink-300, #94A095); }
.dm-thread-actions { display: flex; align-items: center; gap: 4px; flex: 0 0 auto; }
/* Icon-only header controls — details toggle + the single overflow */
.dm-iconbtn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: var(--radius-md); background: none; border: 1px solid transparent; color: var(--text-muted); cursor: pointer; transition: background var(--dur-1,140ms) var(--ease-calm), color var(--dur-1,140ms) var(--ease-calm); }
.dm-iconbtn:hover { background: var(--surface-sunken); color: var(--brand); }
.dm-iconbtn.is-active { background: var(--brand-subtle); color: var(--brand); border-color: var(--green-200); }
.dm-iconbtn svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* Message stream */
.dm-scroll { flex: 1 1 auto; overflow-y: auto; min-height: 0; padding: 20px 26px 22px; display: flex; flex-direction: column; gap: 3px; }
.dm-scroll-inner { max-width: 720px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 3px; }
/* Day / section divider — a hairline with a small-caps label, no pill box */
.dm-day { display: flex; align-items: center; gap: 14px; margin: 14px 2px 10px; color: var(--text-faint); font-family: var(--font-label); text-transform: uppercase; letter-spacing: var(--tracking-caps); font-size: .6rem; }
.dm-day::before, .dm-day::after { content: ""; height: 1px; flex: 1; background: var(--border-hair); }
.dm-day-label { display: inline-flex; align-items: center; gap: 5px; }
.dm-day svg { width: 11px; height: 11px; fill: none; stroke: var(--dm-accent-ink); stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
/* Privacy signature — the lock + "Private counsel" cue that tells this apart from
   a public forum thread at a glance. */
.dm-thread-eyebrow { display: inline-flex; align-items: center; gap: 6px; font-family: var(--font-label); text-transform: uppercase; letter-spacing: var(--tracking-caps); font-size: .6rem; color: var(--dm-accent-ink); margin: 0 0 3px; }
.dm-thread-eyebrow svg { width: 11px; height: 11px; fill: none; stroke: var(--dm-accent); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.dm-listpane-head .eyebrow svg { width: 11px; height: 11px; fill: none; stroke: var(--dm-accent); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

/* Grouped "letter" — one author line, then a run of messages */
.dm-group { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 0 12px; max-width: 82%; margin-top: 13px; }
.dm-group .dm-mono-col { grid-row: 1 / span 2; align-self: start; }
.dm-ghead { display: flex; align-items: baseline; gap: 9px; margin: 1px 0 5px; }
.dm-name { font-family: var(--font-label); letter-spacing: .04em; font-size: .72rem; text-transform: uppercase; color: var(--on-brand-subtle); }
.dm-ghead .dm-gtime { font-family: var(--font-mono); font-size: .66rem; color: var(--text-faint); }
.dm-msgs { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.dm-line { display: flex; align-items: flex-start; gap: 6px; position: relative; }
.dm-body { font-family: var(--font-body); font-size: 1.04rem; line-height: 1.6; color: var(--text-body); }
.dm-body p { margin: 0; }
/* Quoted reply — the ceremonial blockquote, a gold left-rule (from the forum) */
.dm-quote { margin: 0 0 7px; padding: 4px 0 4px 14px; border-left: 2px solid var(--rule-gold); color: var(--text-muted); font-style: italic; font-family: var(--font-body); font-size: .96rem; line-height: 1.45; }
.dm-quote .dm-quote-who { display: block; font-style: normal; font-family: var(--font-label); text-transform: uppercase; letter-spacing: .06em; font-size: .58rem; color: var(--gold-ink); margin-bottom: 2px; }
.dm-group.mine .dm-quote { border-left-color: var(--gold-400); }
/* Rank pill beside the author in group counsel (like the forum's “Steward”) */
.dm-rank { font-family: var(--font-label); text-transform: uppercase; letter-spacing: .07em; font-size: .56rem; color: var(--gold-ink); border: 1px solid var(--gold-200); background: var(--gold-100); border-radius: var(--radius-pill); padding: 1px 7px; }

/* Mine — right-aligned, wearing the one ceremonial gold plate */
.dm-group.mine { grid-template-columns: minmax(0, 1fr); margin-left: auto; justify-items: end; }
.dm-group.mine .dm-ghead { flex-direction: row-reverse; }
.dm-group.mine .dm-line { flex-direction: row-reverse; }
.dm-group.mine .dm-body {
    background: var(--gold-soft); border: 1px solid var(--gold-200);
    border-radius: var(--radius-lg); padding: 8px 14px; color: var(--text-body);
}
/* Theirs — plain counsel on parchment, a faint left tick at the run's start */
.dm-group:not(.mine) .dm-body { padding: 1px 0; }

/* Per-message hover ··· (copy / report) — nothing until you reach for it */
.dm-line-menu { flex: 0 0 auto; opacity: 0; margin-top: 1px; transition: opacity var(--dur-1,140ms) var(--ease-calm); }
.dm-line:hover .dm-line-menu, .dm-line-menu.forced { opacity: 1; }
.dm-dotbtn { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: var(--radius-sm); background: var(--surface-raised); border: 1px solid var(--border-hair); color: var(--text-muted); cursor: pointer; }
.dm-dotbtn:hover { color: var(--brand); border-color: var(--green-200); }
.dm-dotbtn svg { width: 15px; height: 15px; fill: currentColor; }

/* Inline report form (opened from a message's ··· → Report) */
.dm-report-form { grid-column: 2; display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0 2px; padding: 11px; background: var(--surface-sunken); border: 1px solid var(--border-hair); border-radius: var(--radius-md); max-width: 460px; }
.dm-report-form .input { font-size: .85rem; }
.dm-report-form select.input-small, .dm-report-form .input-small { font-family: var(--font-body); font-size: .85rem; padding: 6px 9px; border: 1px solid var(--border-hair); border-radius: var(--radius-sm); background: var(--surface-raised); color: var(--text-body); }

/* Reference cards (linked topics/posts inside a message) — lighter */
.reference-cards { display: flex; flex-direction: column; gap: 6px; margin-top: 7px; max-width: 100%; }
.reference-card {
    display: flex; flex-direction: column; gap: 2px; text-decoration: none;
    border: 1px solid var(--border-hair); border-left: 3px solid var(--artifact-link);
    border-radius: var(--radius-sm); padding: 8px 12px; background: var(--surface-raised);
    transition: border-color var(--dur-1,140ms) var(--ease-calm), box-shadow var(--dur-1,140ms) var(--ease-calm);
}
.dm-group.mine .reference-card { background: var(--surface-raised); }
.reference-card:hover { border-color: var(--green-200); box-shadow: var(--shadow-xs); }
.reference-card .ref-type { font-family: var(--font-label); text-transform: uppercase; letter-spacing: .08em; font-size: .56rem; color: var(--dm-accent-ink); }
.reference-card strong { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: .98rem; color: var(--text-strong); line-height: 1.2; }
.reference-card .ref-meta { font-family: var(--font-mono); font-size: .68rem; color: var(--text-muted); }

/* Read receipt — a quiet line under my last letter */
.dm-receipt { align-self: flex-end; font-family: var(--font-label); text-transform: uppercase; letter-spacing: .08em; font-size: .58rem; color: var(--text-faint); margin: 5px 2px 2px; display: inline-flex; align-items: center; gap: 5px; }
.dm-receipt svg { width: 12px; height: 12px; fill: none; stroke: var(--dm-accent); stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Composer (pinned) — one clean well, not a heavy card */
.dm-composer { flex: 0 0 auto; border-top: 1px solid var(--border-hair); padding: 12px 26px 16px; background: var(--dm-raised); }
.dm-composer-inner { max-width: 720px; margin: 0 auto; }
.dm-composer-row { display: flex; align-items: flex-start; gap: 11px; }
.dm-composer-row .monogram { margin-top: 5px; flex: 0 0 auto; }
.dm-composer-main { flex: 1; min-width: 0; }
.dm-composer-field { display: flex; align-items: flex-end; gap: 10px; background: var(--dm-ground); border: 1px solid var(--border-hair); border-radius: var(--radius-lg); padding: 8px 8px 8px 14px; transition: border-color var(--dur-1,140ms) var(--ease-calm), box-shadow var(--dur-1,140ms) var(--ease-calm); }
.dm-composer-field:focus-within { border-color: var(--dm-accent-line); box-shadow: 0 0 0 3px var(--focus-ring); }
.dm-composer-field textarea {
    flex: 1; resize: none; border: 0; outline: none; background: none; font: inherit;
    font-family: var(--font-body); font-size: 1.02rem; line-height: 1.55; color: var(--text-body);
    max-height: 148px; min-height: 24px; padding: 4px 0;
}
.dm-composer-field textarea::placeholder { color: var(--text-faint); }
.dm-send { flex: 0 0 auto; width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: var(--brand); border: 0; color: var(--accent-contrast); cursor: pointer; transition: background var(--dur-1,140ms) var(--ease-calm), transform var(--dur-1,140ms) var(--ease-calm); }
.dm-send:hover { background: var(--brand-hover); }
.dm-send:active { transform: scale(.96); }
.dm-send:disabled { background: var(--surface-sunken); color: var(--text-faint); cursor: default; }
.dm-send svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.dm-composer-meta { display: flex; align-items: center; justify-content: space-between; margin: 6px 4px 0; }
.dm-composer-hint { font-family: var(--font-body); font-size: .78rem; color: var(--text-faint); }
.dm-composer-count { font-family: var(--font-mono); font-size: .68rem; color: var(--text-faint); }

/* ── Details rail (right, collapsible) ──────────────────────────────────── */
.dm-inforail { background: var(--dm-ground); border-left: 1px solid var(--border-hair); display: flex; flex-direction: column; min-height: 0; min-width: 0; }
.dm-rail-head { padding: 13px 18px; border-bottom: 1px solid var(--border-hair); display: flex; align-items: center; justify-content: space-between; gap: 10px; flex: 0 0 auto; }
.dm-rail-head .eyebrow { margin: 0; }
.dm-rail-body { overflow-y: auto; min-height: 0; padding: 22px 18px 28px; display: flex; flex-direction: column; gap: 20px; }

/* Direct-convo identity block */
.dm-rail-id { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px; }
.dm-rail-id .monogram { margin-bottom: 6px; }
.dm-rail-name { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.4rem; color: var(--text-strong); line-height: 1.1; margin: 0; }
.dm-rail-handle { font-family: var(--font-mono); font-size: .76rem; color: var(--text-muted); }
.dm-tier-pill { margin-top: 4px; display: inline-flex; align-items: center; gap: 6px; padding: 3px 12px; border-radius: var(--radius-pill); background: var(--gold-soft); border: 1px solid var(--gold-200); font-family: var(--font-label); text-transform: uppercase; letter-spacing: .06em; font-size: .62rem; color: var(--gold-ink); }

/* Small titled sections in the rail */
.dm-rail-sec { display: flex; flex-direction: column; gap: 9px; }
.dm-rail-sec > h3 { font-family: var(--font-label); text-transform: uppercase; letter-spacing: var(--tracking-caps); font-size: .64rem; color: var(--gold-ink); margin: 0; }
.dm-rail-meta { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.dm-rail-meta li { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; font-family: var(--font-body); font-size: .9rem; color: var(--text-body); }
.dm-rail-meta li .k { color: var(--text-muted); }
.dm-rail-meta li .v { font-family: var(--font-mono); font-size: .78rem; color: var(--text-strong); text-align: right; }

/* Rail row actions (toggle / link / danger), all quiet */
.dm-rail-actions { display: flex; flex-direction: column; gap: 2px; }
.dm-rail-btn { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 9px 10px; border-radius: var(--radius-md); background: none; border: 0; cursor: pointer; font-family: var(--font-body); font-size: .95rem; color: var(--text-body); }
.dm-rail-btn:hover { background: var(--surface-sunken); }
.dm-rail-btn.danger { color: var(--danger); }
.dm-rail-btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }
.dm-rail-btn .switch { margin-left: auto; }
.dm-rail-toggle { display: flex; align-items: center; gap: 10px; padding: 5px 10px; }
.dm-rail-toggle .label { font-family: var(--font-body); font-size: .95rem; color: var(--text-body); }
.dm-rail-toggle .switch { margin-left: auto; }

/* Group members list in the rail */
.dm-members { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.dm-member { display: flex; align-items: center; gap: 10px; padding: 7px 8px; border-radius: var(--radius-md); position: relative; }
.dm-member:hover { background: var(--surface-sunken); }
.dm-member .m-id { min-width: 0; display: flex; flex-direction: column; }
.dm-member .m-name { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1rem; color: var(--text-strong); line-height: 1.15; }
.dm-member .m-handle { font-family: var(--font-mono); font-size: .68rem; color: var(--text-muted); }
.dm-member .m-role { margin-left: auto; font-family: var(--font-label); text-transform: uppercase; letter-spacing: .06em; font-size: .56rem; color: var(--gold-ink); flex: 0 0 auto; }
.dm-member.is-left { opacity: .55; }
.dm-member.is-left .m-name { text-decoration: line-through; }
.dm-member .m-role.left { color: var(--text-faint); }
/* owner tools reveal on member hover */
.dm-member-tools { margin-left: auto; display: flex; gap: 6px; opacity: 0; transition: opacity var(--dur-1,140ms) var(--ease-calm); }
.dm-member:hover .dm-member-tools { opacity: 1; }
.dm-linkbtn { background: none; border: 0; padding: 0; cursor: pointer; font-family: var(--font-label); letter-spacing: .03em; text-transform: uppercase; font-size: .58rem; color: var(--text-muted); white-space: nowrap; }
.dm-linkbtn:hover { color: var(--brand); }
.dm-linkbtn.danger:hover { color: var(--danger); }

/* Owner tools (add member / rename) */
.dm-owner-tool { display: flex; gap: 8px; align-items: center; }
.dm-owner-tool .input { flex: 1; min-width: 0; }

/* ── Empty state (nothing selected) ─────────────────────────────────────── */
.dm-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 60px 24px; background: var(--dm-raised); }
.dm-empty-inner { text-align: center; max-width: 42ch; color: var(--text-muted); }
.dm-empty-inner .star { color: var(--green-400); opacity: .55; }
.dm-empty-inner h2 { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.5rem; color: var(--text-strong); margin: 14px 0 6px; }
.dm-empty-inner p { margin: 0 0 18px; line-height: 1.55; font-family: var(--font-body); }

/* ── Shared popover menu (header ··· + message ···) ──────────────────────── */
.dm-menu-wrap { position: relative; display: inline-flex; }
.dm-menu-pop {
    position: absolute; z-index: 50; min-width: 210px; padding: 6px;
    background: var(--surface-raised); border: 1px solid var(--border-hair);
    border-radius: var(--radius-md); box-shadow: var(--shadow-lg);
    display: flex; flex-direction: column; animation: dm-pop 140ms var(--ease-calm) both;
}
.dm-menu-pop.to-right { right: 0; top: calc(100% + 6px); }
.dm-menu-pop.to-left { left: 0; top: calc(100% + 6px); }
@keyframes dm-pop { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
.dm-menu-item { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; padding: 8px 10px; border-radius: var(--radius-sm); background: none; border: 0; cursor: pointer; font-family: var(--font-body); font-size: .92rem; color: var(--text-body); }
.dm-menu-item:hover { background: var(--surface-sunken); }
.dm-menu-item.danger { color: var(--danger); }
.dm-menu-item svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; color: var(--text-muted); }
.dm-menu-item.danger svg { color: var(--danger); }
.dm-menu-sep { height: 1px; background: var(--border-hair); margin: 5px 4px; }

/* ── Overlay dialogs (new message / confirm) ────────────────────────────── */
.dm-scrim { position: fixed; inset: 0; z-index: 60; background: rgba(22,29,36,.42); -webkit-backdrop-filter: blur(3px); backdrop-filter: blur(3px); display: flex; align-items: flex-start; justify-content: center; padding: 8vh 20px 20px; animation: dm-fade 140ms var(--ease-calm) both; }
@keyframes dm-fade { from { opacity: 0; } to { opacity: 1; } }
.dm-dialog { width: 100%; max-width: 540px; background: var(--surface-raised); border: 1px solid var(--border-hair); border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); animation: dm-rise 240ms var(--ease-calm) both; overflow: hidden; }
@keyframes dm-rise { from { opacity: 0; transform: translateY(10px) scale(.99); } to { opacity: 1; transform: none; } }
.dm-dialog-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px 22px 12px; }
.dm-dialog-head h2 { font-family: var(--font-display); font-weight: var(--weight-medium); font-size: 1.4rem; color: var(--text-strong); margin: 0; }
.dm-dialog-head .eyebrow { margin: 0 0 2px; display: block; }
.dm-dialog-close { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: none; border: 0; color: var(--text-muted); cursor: pointer; }
.dm-dialog-close:hover { background: var(--surface-sunken); color: var(--brand); }
.dm-dialog-close svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.dm-dialog-body { padding: 6px 22px 8px; display: flex; flex-direction: column; gap: 14px; }
.dm-dialog-body .field-hint { font-family: var(--font-body); font-size: .85rem; color: var(--text-faint); margin: -8px 0 0; }
.dm-dialog-body p { font-family: var(--font-body); font-size: 1rem; line-height: 1.55; color: var(--text-body); margin: 0; }
.dm-dialog-foot { display: flex; align-items: center; gap: 10px; padding: 12px 22px 20px; }
.dm-dialog-foot .grow { flex: 1; }

/* ── Mobile bottom tab bar (hidden on desktop) ──────────────────────────── */
.dm-tabbar { display: none; }
.dm-tab { display: flex; flex-direction: column; align-items: center; gap: 3px; color: var(--text-faint); background: none; border: 0; cursor: pointer; font-family: var(--font-label); text-transform: uppercase; letter-spacing: .04em; font-size: .6rem; text-decoration: none; padding: 0; }
.dm-tab.is-active { color: var(--brand); }
.dm-tab svg { width: 21px; height: 21px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.dm-tab-fab span { width: 44px; height: 44px; border-radius: 50%; background: var(--brand); color: var(--accent-contrast); display: flex; align-items: center; justify-content: center; margin-top: -12px; box-shadow: var(--shadow-lg); }
.dm-tab-fab span svg { stroke-width: 2; }

/* ── Responsive — nav + list + conversation, collapsing to a single pane ── */
/* Medium widths: keep the conversation readable — float the details rail as a
   drawer over the right edge instead of squeezing a fourth grid column. */
@media (max-width: 1320px) and (min-width: 901px) {
    .dm-shell.has-rail { grid-template-columns: 212px minmax(288px, 336px) minmax(0, 1fr); }
    .dm-shell.has-rail .dm-inforail {
        position: fixed; top: var(--topbar-h); right: 0; bottom: 0; width: 320px; z-index: 40;
        box-shadow: var(--shadow-xl);
    }
}
@media (max-width: 900px) {
    .dm-shell, .dm-shell.has-rail { grid-template-columns: 1fr; }
    .dm-navrail, .dm-threadpane, .dm-inforail { display: none; }
    .dm-shell.reading .dm-listpane { display: none; }
    .dm-shell.reading .dm-threadpane { display: flex; }
    .dm-back { display: inline-flex; }
    .topbar-search { display: none; }
    .dm-group { max-width: 90%; }
    .dm-list { padding-bottom: 78px; }
    /* details rail becomes a full-screen overlay */
    .dm-shell.rail-open .dm-inforail {
        display: flex; position: fixed; inset: var(--topbar-h) 0 0 0; z-index: 40;
        border-left: 0; box-shadow: var(--shadow-xl);
    }
    /* the mobile product nav */
    .dm-tabbar {
        display: flex; align-items: center; justify-content: space-around;
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 45; height: 60px; padding-bottom: 4px;
        background: color-mix(in srgb, var(--surface-raised) 92%, transparent);
        -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
        border-top: 1px solid var(--border-hair);
    }
}
@media (max-width: 560px) {
    .dm-scroll { padding: 16px 16px 18px; }
    .dm-thread-head { padding: 11px 14px; }
    .dm-composer { padding: 10px 14px 14px; }
    .dm-group { max-width: 96%; }
}

/* ── Toast — a quiet acknowledgement of tucked-away actions ──────────────── */
.dm-toast {
    position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%); z-index: 80;
    background: var(--surface-inverse); color: var(--parchment-50);
    font-family: var(--font-body); font-size: .92rem; padding: 10px 18px;
    border-radius: var(--radius-pill); box-shadow: var(--shadow-lg);
    animation: dm-fade 160ms var(--ease-calm) both; max-width: 90vw; text-align: center;
}

````

## data.js

````js
/* Messages kit — seed data for private counsel (direct + group conversations).
   Same Imladris roster register as RetroBoards. Shared via window.RBDM.
   v2 (reimagine): users carry joined/tier for the details rail; a couple of
   threads carry same-author runs + a trailing message from me, so grouping and
   the read receipt read true. */
(function () {
  const users = {
    erestor:    { username: 'erestor',    name: 'Erestor',    presence: 'online',  joined: 'Third Age, 2018', tier: 'Loremaster' },
    galadriel:  { username: 'galadriel',  name: 'Galadriel',  presence: 'online',  joined: 'Third Age, 2012', tier: 'Legend'     },
    elrond:     { username: 'elrond',     name: 'Elrond',     presence: 'online',  joined: 'Third Age, 2009', tier: 'Legend'     },
    glorfindel: { username: 'glorfindel', name: 'Glorfindel', presence: 'away',    joined: 'Third Age, 2015', tier: 'Veteran'    },
    arwen:      { username: 'arwen',      name: 'Arwen',      presence: 'online',  joined: 'Third Age, 2016', tier: 'Veteran'    },
    lindir:     { username: 'lindir',     name: 'Lindir',     presence: 'offline', joined: 'Third Age, 2019', tier: 'Member'     },
  };

  const me = 'erestor';

  /* Each conversation: direct (with `other`) or group (with `title` + `members`).
     `messages` are ordered oldest→newest; `mine` is derived in the view. */
  const conversations = [
    {
      id: 1, kind: 'direct', other: 'galadriel', unread: true, time: '9m',
      preview: 'Send me the rollback drill — Glorfindel will want it for the wardens.',
      messages: [
        { id: 11, from: 'galadriel', time: 'Yesterday 18:40', body: 'Erestor — I read your note on audit trails before the council met. It holds. The three questions are the right ones.' },
        { id: 12, from: 'erestor',   time: 'Yesterday 19:02', body: 'Then it is ready to record. I will mark the accepted answer and link the written verdict from the topic.',
          refs: [{ type: 'Topic', title: 'Who changed what — and can you prove the rollback?', meta: '#audit-trails · 41 replies', url: '#' }] },
        { id: 13, from: 'erestor',   time: 'Yesterday 19:04', body: 'The rollback drill is drafted as well. I will attach it once Glorfindel names the day.',
          quote: { from: 'galadriel', text: 'The three questions are the right ones.' } },
        { id: 14, from: 'galadriel', time: '9m', body: 'Do that. And send me the rollback drill — Glorfindel will want it for the wardens.' },
      ],
    },
    {
      id: 2, kind: 'group', title: 'Vilya · wardens', unread: true, time: '1h',
      members: [
        { username: 'erestor',    role: 'owner' },
        { username: 'elrond',     role: 'member' },
        { username: 'glorfindel', role: 'member' },
        { username: 'arwen',      role: 'member' },
        { username: 'lindir',     role: 'member', left: true },
      ],
      preview: 'Glorfindel: the rollback drill is set for Tuesday. Bring the audit trail.',
      messages: [
        { id: 21, from: 'elrond',     time: '3h', body: 'Wardens — we keep counsel here on what does not yet belong in the open hall. Verify before you carry it further.' },
        { id: 22, from: 'arwen',      time: '2h', body: 'Understood. I have the eval verdicts ready to read; they resolve cleanly into artifacts now.',
          refs: [{ type: 'Topic', title: 'Eval verdicts — the eight that resolved this cycle', meta: '#evals · 12 replies', url: '#' }] },
        { id: 23, from: 'glorfindel', time: '1h', body: 'The rollback drill is set for Tuesday. Bring the audit trail — I want precedence recorded this time.',
          quote: { from: 'arwen', text: 'They resolve cleanly into artifacts now.' } },
      ],
    },
    {
      id: 3, kind: 'direct', other: 'elrond', unread: false, time: '2h', read: true,
      preview: 'Thank you. Send me the wording before it is entered into the charter.',
      messages: [
        { id: 31, from: 'erestor', time: 'Today 09:10', body: 'The charter should say that testimony never outranks the work. People keep forgetting the order.' },
        { id: 32, from: 'elrond',  time: '3h', body: 'Recorded. I will amend the charter to say so plainly.' },
        { id: 33, from: 'erestor', time: '2h', body: 'Thank you. Send me the wording before it is entered into the charter.' },
      ],
    },
    {
      id: 4, kind: 'direct', other: 'arwen', unread: false, time: 'Yesterday',
      preview: 'The accepted answer reads well now. Thank you for the gilt.',
      messages: [
        { id: 41, from: 'arwen',   time: 'Yesterday', body: 'The accepted answer reads well now. Thank you for the gilt.' },
      ],
    },
    {
      id: 5, kind: 'direct', other: 'glorfindel', unread: false, time: '2d',
      preview: 'Two actors could edit one setting with no record of precedence. Fixed.',
      messages: [
        { id: 51, from: 'glorfindel', time: '2 days ago', body: 'Two actors could edit one setting with no record of precedence. Fixed now — the warden log keeps order.' },
      ],
    },
    {
      id: 6, kind: 'direct', other: 'lindir', unread: false, time: '5d',
      preview: 'Thank you for the three topics. I have read all of them twice.',
      messages: [
        { id: 61, from: 'lindir',  time: '5 days ago', body: 'Thank you for the three topics. I have read all of them twice. The songs will keep them.' },
      ],
    },
  ];

  // Reasons offered when reporting a message (mapped to readable labels in the view).
  const reportReasons = ['spam', 'harassment', 'off_topic', 'other'];

  window.RBDM = { users, me, conversations, reportReasons };
})();

````

## DMTopbar.jsx

````jsx
/* Messages kit — top bar (member register, mirrors RetroBoards). Static chrome;
   brand returns to the inbox. */
(function () {
  function DMTopbar() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Input, Monogram } = DS;
    const me = window.RBDM.users[window.RBDM.me];
    return (
      <header className="topbar">
        <div className="topbar-inner">
          <a className="brand" href="../retroboards/index.html">
            <EightPointStar size={26} />
            <span className="brand-name">RetroBoards</span>
          </a>

          <form className="topbar-search" onSubmit={(e) => e.preventDefault()} role="search">
            <Input pill type="search" placeholder="Search the council…" aria-label="Search the council" />
          </form>

          <div className="topbar-right">
            <span className="bell" title="Notifications">
              <svg className="bell-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
              <span className="bell-badge" aria-hidden="true">3</span>
            </span>
            <span className="topbar-user">
              <Monogram name={me.name} username={me.username} size="sm" presence="online" />
              <span className="topbar-name">{me.name}</span>
            </span>
            <svg className="topbar-ic" viewBox="0 0 24 24" aria-hidden="true" title="Settings"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <button className="topbar-logout" type="button">Log out</button>
          </div>
        </div>
      </header>
    );
  }
  window.DMTopbar = DMTopbar;
})();

````

## NavRail.jsx

````jsx
/* Messages kit — product nav rail (left-most column). Grounds Messages INSIDE
   the forum chrome, mirroring the flagship inbox: Home / Inbox / Messages
   (active) / Following / Drafts, then a quiet "Direct" section. This is the
   consolidation move — DMs read as one place in the product, not a floating
   island. Static chrome; the active item is Messages. */
(function () {
  const item = (icon, label, opts) => ({ icon, label, ...(opts || {}) });

  function NavRail({ onNewMessage }) {
    // Lucide-register glyphs, matching the forum inbox nav exactly.
    const I = {
      home:      <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>,
      inbox:     <svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"/></svg>,
      messages:  <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>,
      following: <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
      drafts:    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>,
    };
    return (
      <nav className="dm-navrail" aria-label="Primary">
        <a className="dm-nav-item" href="../retroboards/index.html">
          <span className="dm-nav-ic">{I.home}</span><span>Home</span>
        </a>
        <a className="dm-nav-item" href="#inbox" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.inbox}</span><span>Inbox</span>
          <span className="dm-nav-count">7</span>
        </a>
        <a className="dm-nav-item is-active" href="#messages" aria-current="page" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.messages}</span><span>Messages</span>
          <span className="dm-nav-dot" aria-hidden="true" />
        </a>
        <a className="dm-nav-item" href="#following" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.following}</span><span>Following</span>
        </a>
        <a className="dm-nav-item" href="#drafts" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.drafts}</span><span>Drafts</span>
        </a>

        <div className="dm-nav-sec">
          <div className="dm-nav-sec-head">Direct</div>
          <button type="button" className="dm-nav-compose" onClick={onNewMessage}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            New message
          </button>
        </div>
      </nav>
    );
  }
  window.DMNavRail = NavRail;
})();

````

## Overlays.jsx

````jsx
/* Messages kit — shared overlay bits: the popover menu (header ··· and the
   per-message hover ···), the modal shell + its two bodies (new-message and a
   generic confirm), and a small Lucide-style icon set. Exposed on window. */
(function () {
  const { useState, useEffect, useRef } = React;
  const DS = window.ImladrisDesignSystem_c3e027;

  /* ── Icons (Lucide register, stroke ~1.8) ─────────────────────────────── */
  const svg = (children, extra) => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
      strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...extra}>{children}</svg>
  );
  const Icons = {
    Plus:    () => svg(<path d="M12 5v14M5 12h14" />),
    Search:  () => svg(<><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></>),
    Chevron: () => svg(<path d="M15 18l-6-6 6-6" />),
    More:    () => (<svg viewBox="0 0 24 24" width="16" height="16" style={{ fill: 'currentColor', stroke: 'none' }}><circle cx="5" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="19" cy="12" r="1.7" /></svg>),
    Panel:   () => svg(<><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M15 4v16" /></>),
    Mute:    () => svg(<><path d="M11 5 6 9H2v6h4l5 4z" /><path d="M22 9l-6 6M16 9l6 6" /></>),
    Bell:    () => svg(<><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></>),
    User:    () => svg(<><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" /><circle cx="12" cy="7" r="4" /></>),
    Users:   () => svg(<><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>),
    Rename:  () => svg(<><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></>),
    AddUser: () => svg(<><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M19 8v6M22 11h-6" /></>),
    Leave:   () => svg(<><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></>),
    Block:   () => svg(<><circle cx="12" cy="12" r="9" /><path d="M5.6 5.6l12.8 12.8" /></>),
    Flag:    () => svg(<><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" /><line x1="4" y1="22" x2="4" y2="15" /></>),
    Copy:    () => svg(<><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></>),
    Close:   () => svg(<path d="M18 6 6 18M6 6l12 12" />),
    Check:   () => svg(<path d="M20 6 9 17l-5-5" />),
    Lock:    () => svg(<><rect x="4.5" y="10.5" width="15" height="10" rx="2" /><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" /></>),
    Send:    () => svg(<><path d="M12 19V5" /><path d="M5 12l7-7 7 7" /></>),
  };

  /* ── Popover menu ─────────────────────────────────────────────────────── */
  /* `button` is a render-prop: ({ open, toggle }) => node.
     `items`: [{ label, icon, onClick, danger } | { sep:true }] */
  function Menu({ button, items, align }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
      const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
      document.addEventListener('mousedown', onDoc);
      document.addEventListener('keydown', onKey);
      return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey); };
    }, [open]);
    return (
      <span className="dm-menu-wrap" ref={ref}>
        {button({ open, toggle: () => setOpen((o) => !o) })}
        {open ? (
          <div className={'dm-menu-pop ' + (align === 'left' ? 'to-left' : 'to-right')} role="menu">
            {items.filter(Boolean).map((it, i) => it.sep ? (
              <div key={i} className="dm-menu-sep" />
            ) : (
              <button key={i} type="button" role="menuitem"
                className={'dm-menu-item' + (it.danger ? ' danger' : '')}
                onClick={() => { setOpen(false); it.onClick && it.onClick(); }}>
                {it.icon}{it.label}
              </button>
            ))}
          </div>
        ) : null}
      </span>
    );
  }

  /* ── Modal shell ──────────────────────────────────────────────────────── */
  function Modal({ onClose, children }) {
    useEffect(() => {
      const onKey = (e) => { if (e.key === 'Escape') onClose(); };
      document.addEventListener('keydown', onKey);
      return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);
    return (
      <div className="dm-scrim" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
        <div className="dm-dialog" role="dialog" aria-modal="true">{children}</div>
      </div>
    );
  }

  /* New-message body (mirrors dm/new.php: recipients → group, title, body) */
  function ComposeForm({ onClose, onSend }) {
    const { Input, Textarea, Button } = DS;
    const [to, setTo] = useState('');
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const isGroup = to.includes(',');
    return (
      <form onSubmit={(e) => { e.preventDefault(); onSend && onSend({ to, title, body }); }}>
        <div className="dm-dialog-head">
          <span><span className="eyebrow">Private counsel</span><h2>New message</h2></span>
          <button type="button" className="dm-dialog-close" onClick={onClose} aria-label="Close">{Icons.Close()}</button>
        </div>
        <div className="dm-dialog-body">
          <Input label="To" value={to} onChange={(e) => setTo(e.target.value)} placeholder="username, username" maxLength={255} autoFocus />
          <p className="field-hint">Separate usernames with commas to open a group counsel.</p>
          {isGroup ? (
            <Input label="Group title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Optional" maxLength={120} />
          ) : null}
          <Textarea label="Message" rows={5} value={body} onChange={(e) => setBody(e.target.value)} placeholder="Write your counsel…" maxLength={5000} />
        </div>
        <div className="dm-dialog-foot">
          <Button type="submit" disabled={!to.trim() || !body.trim()}>Send message</Button>
          <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
        </div>
      </form>
    );
  }

  /* Generic confirm (leave / block / report conversation) */
  function ConfirmBody({ title, body, confirmLabel, danger, onConfirm, onClose }) {
    const { Button } = DS;
    return (
      <>
        <div className="dm-dialog-head">
          <span><h2>{title}</h2></span>
          <button type="button" className="dm-dialog-close" onClick={onClose} aria-label="Close">{Icons.Close()}</button>
        </div>
        <div className="dm-dialog-body"><p>{body}</p></div>
        <div className="dm-dialog-foot">
          <Button variant={danger ? 'danger' : 'primary'} onClick={() => { onClose(); onConfirm && onConfirm(); }}>{confirmLabel || 'Confirm'}</Button>
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
        </div>
      </>
    );
  }

  window.DMIcons = Icons;
  window.DMMenu = Menu;
  window.DMModal = Modal;
  window.DMComposeForm = ComposeForm;
  window.DMConfirmBody = ConfirmBody;
})();

````

## ConvoList.jsx

````jsx
/* Messages kit — conversation list (left pane). One tidy header (title + the
   single round "new message" invitation), a quiet search, an All / Unread
   filter, then the rows: monogram, name, one-line preview, a lone gold unread
   dot. No stacked sub-headers, no per-row boxes. */
(function () {
  const Icons = window.DMIcons;

  function ConvoList({ conversations, activeId, onOpen, onNew, filter, onFilter, query, onQuery }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram } = DS;
    const RBDM = window.RBDM;

    const U = (n) => RBDM.users[n] || { name: n, presence: undefined };
    const q = query.trim().toLowerCase();
    const shown = conversations.filter((c) => {
      if (filter === 'Unread' && !c.unread) return false;
      if (!q) return true;
      const name = c.kind === 'group' ? c.title : U(c.other).name;
      return (name + ' ' + c.preview).toLowerCase().includes(q);
    });
    const unreadCount = conversations.filter((c) => c.unread).length;

    return (
      <aside className="dm-listpane">
        <div className="dm-listpane-head">
          <div className="dm-listpane-top">
            <span>
              <span className="eyebrow">{Icons.Lock()}Private counsel</span>
              <h1>Messages</h1>
            </span>
            <button type="button" className="dm-new-btn" onClick={onNew} title="New message" aria-label="New message">
              {Icons.Plus()}
            </button>
          </div>

          <div className="dm-search">
            {Icons.Search()}
            <input type="search" value={query} onChange={(e) => onQuery(e.target.value)}
              placeholder="Search messages…" aria-label="Search messages" />
          </div>

          <div className="dm-filter">
            <div className="dm-chips" role="tablist" aria-label="Filter conversations">
              {['All', 'Unread'].map((f) => (
                <button key={f} type="button" role="tab" aria-selected={filter === f}
                  className={'dm-chip' + (filter === f ? ' is-active' : '')} onClick={() => onFilter(f)}>{f}</button>
              ))}
            </div>
            <span className="dm-count">{unreadCount ? unreadCount + ' unread' : 'All read'}</span>
          </div>
        </div>

        {shown.length === 0 ? (
          <p className="dm-list-empty">{q ? 'No letters match your search.' : 'No conversations here yet.'}</p>
        ) : (
          <ul className="dm-list">
            {shown.map((c) => {
              const isGroup = c.kind === 'group';
              const other = isGroup ? c.title : U(c.other).name;
              const seed = isGroup ? ('group-' + c.id) : c.other;
              const presence = isGroup ? undefined : U(c.other).presence;
              return (
                <li key={c.id}>
                  <button type="button"
                    className={'dm-row' + (c.id === activeId ? ' active' : '') + (c.unread ? ' is-unread' : '')}
                    onClick={() => onOpen(c.id)}>
                    <Monogram name={other} username={seed} size="md" presence={presence} gilt={isGroup} />
                    <span className="dm-row-top">
                      <span className="dm-other">{other}</span>
                    </span>
                    <span className="dm-time">{c.time}</span>
                    <span className="dm-preview">{c.preview}</span>
                    {c.unread ? <span className="dm-unread-dot" aria-label="Unread" /> : null}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </aside>
    );
  }
  window.DMConvoList = ConvoList;
})();

````

## Thread.jsx

````jsx
/* Messages kit — the open conversation (centre pane). One header (identity +
   a details toggle + a single ··· overflow), the message stream as grouped
   "letters" (consecutive messages share an author line; theirs read plain,
   mine wear the one gold plate), a per-message hover ··· (copy / report), an
   inline report form, reference cards, a read receipt, and a calm composer.
   All secondary controls live in menus or the details rail — nothing shouts. */
(function () {
  const { useState, useRef, useEffect } = React;
  const Icons = window.DMIcons;
  const Menu = window.DMMenu;

  function groupRuns(messages) {
    const out = [];
    let cur = null;
    messages.forEach((m) => {
      if (cur && cur.from === m.from) cur.items.push(m);
      else { cur = { from: m.from, items: [m] }; out.push(cur); }
    });
    return out;
  }
  const label = (code) => code.charAt(0).toUpperCase() + code.slice(1).replace(/_/g, ' ');

  function Thread(props) {
    const { convo, onBack, railOpen, onToggleRail, onOpenRail, onUpdateConvo, onConfirm, onLeaveConvo, onToast, replyValue, onReplyChange, onSend } = props;
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram } = DS;
    const RBDM = window.RBDM;
    const me = RBDM.users[RBDM.me];
    const [reportingId, setReportingId] = useState(null);
    const scrollRef = useRef(null);
    const taRef = useRef(null);

    const U = (n) => RBDM.users[n] || { username: n, name: n, presence: 'offline' };
    const isGroup = convo.kind === 'group';
    const other = isGroup ? null : U(convo.other);
    const title = isGroup ? convo.title : other.name;
    const seed = isGroup ? ('group-' + convo.id) : convo.other;
    const active = isGroup ? convo.members.filter((m) => !m.left) : [];
    const isOwner = isGroup && (convo.members.find((m) => m.role === 'owner') || {}).username === RBDM.me;
    const muted = !!convo.muted;

    useEffect(() => { setReportingId(null); }, [convo.id]);

    // Pin to the newest letter.
    useEffect(() => {
      const el = scrollRef.current; if (!el) return;
      const pin = () => { el.scrollTop = el.scrollHeight; };
      pin(); requestAnimationFrame(pin);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(pin);
      const t = setTimeout(pin, 250);
      return () => clearTimeout(t);
    }, [convo.id, convo.messages.length]);

    // Auto-grow the composer.
    useEffect(() => {
      const el = taRef.current; if (!el) return;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 148) + 'px';
    }, [replyValue]);

    function copy(text) {
      try { navigator.clipboard && navigator.clipboard.writeText(text); } catch (e) { /* sandbox */ }
      onToast('Copied to clipboard.');
    }
    const toggleMute = () => onUpdateConvo((c) => ({ ...c, muted: !c.muted }));

    const menuItems = isGroup ? [
      { label: muted ? 'Unmute conversation' : 'Mute conversation', icon: Icons.Mute(), onClick: toggleMute },
      isOwner ? { sep: true } : null,
      isOwner ? { label: 'Rename group', icon: Icons.Rename(), onClick: onOpenRail } : null,
      isOwner ? { label: 'Add member', icon: Icons.AddUser(), onClick: onOpenRail } : null,
      { sep: true },
      { label: 'Leave group', icon: Icons.Leave(), danger: true, onClick: () => onConfirm({
          title: 'Leave ' + convo.title + '?',
          body: 'You will stop receiving this counsel. An owner can add you again later.',
          confirmLabel: 'Leave group', danger: true, onConfirm: () => onLeaveConvo(convo.id),
        }) },
    ] : [
      { label: muted ? 'Unmute conversation' : 'Mute conversation', icon: Icons.Mute(), onClick: toggleMute },
      { sep: true },
      { label: 'View profile', icon: Icons.User(), onClick: onOpenRail },
      { label: 'Block ' + other.name, icon: Icons.Block(), danger: true, onClick: () => onConfirm({
          title: 'Block ' + other.name + '?',
          body: other.name + ' will no longer be able to send you private counsel. You can undo this from settings.',
          confirmLabel: 'Block', danger: true, onConfirm: () => onToast(other.name + ' is blocked.'),
        }) },
      { label: 'Report conversation', icon: Icons.Flag(), danger: true, onClick: () => onConfirm({
          title: 'Report this conversation?',
          body: 'The wardens will review the recent messages in this counsel.',
          confirmLabel: 'Report', danger: true, onConfirm: () => onToast('Reported to the wardens.'),
        }) },
    ];

    const groups = groupRuns(convo.messages);
    const last = convo.messages[convo.messages.length - 1];
    const lastMine = last && last.from === RBDM.me;
    const receipt = lastMine ? (last.time === 'just now' ? 'Sent' : (convo.read ? 'Read' : 'Delivered')) : null;

    return (
      <section className="dm-threadpane">
        <header className="dm-thread-head">
          <button className="dm-back" onClick={onBack} aria-label="Back to messages">{Icons.Chevron()}</button>
          <div className="dm-thread-id">
            <Monogram name={title} username={seed} size="md" gilt presence={other ? other.presence : undefined} />
            <div>
              <div className="dm-thread-eyebrow">{Icons.Lock()}{isGroup ? 'Private group' : 'Private counsel'}</div>
              <h1 className="dm-thread-title">{title}</h1>
              <p className="dm-thread-sub">
                {isGroup ? (
                  <>{active.length} in counsel{muted ? ' · muted' : ''}</>
                ) : (
                  <>@{other.username} · {other.presence}{muted ? ' · muted' : ''}</>
                )}
              </p>
            </div>
          </div>
          <div className="dm-thread-actions">
            <button type="button" className={'dm-iconbtn' + (railOpen ? ' is-active' : '')}
              onClick={onToggleRail} title={isGroup ? 'Members & details' : 'Details'}
              aria-label={isGroup ? 'Members and details' : 'Details'} aria-pressed={railOpen}>
              {isGroup ? Icons.Users() : Icons.Panel()}
            </button>
            <Menu align="right"
              button={({ toggle, open }) => (
                <button type="button" className={'dm-iconbtn' + (open ? ' is-active' : '')} onClick={toggle} aria-label="More actions">{Icons.More()}</button>
              )}
              items={menuItems} />
          </div>
        </header>

        <div className="dm-scroll" ref={scrollRef}>
          <div className="dm-scroll-inner">
            <div className="dm-day"><span className="dm-day-label">{Icons.Lock()} Private — only those named here can read</span></div>
            {groups.map((g, gi) => {
              const mine = g.from === RBDM.me;
              const from = U(g.from);
              return (
                <div key={gi} className={'dm-group' + (mine ? ' mine' : '')}>
                  {!mine ? (
                    <span className="dm-mono-col"><Monogram name={from.name} username={from.username} size="sm" /></span>
                  ) : null}
                  <div className="dm-msgs">
                    <div className="dm-ghead">
                      <span className="dm-name">{mine ? 'You' : from.name}</span>
                      {isGroup && !mine && from.tier ? <span className="dm-rank">{from.tier}</span> : null}
                      <span className="dm-gtime">{g.items[0].time}</span>
                    </div>
                    {g.items.map((m) => (
                      <React.Fragment key={m.id}>
                        <div className="dm-line">
                          <div className="dm-body">
                            {m.quote ? (
                              <blockquote className="dm-quote">
                                <span className="dm-quote-who">{(RBDM.users[m.quote.from] || {}).name || m.quote.from}</span>
                                {m.quote.text}
                              </blockquote>
                            ) : null}
                            <p>{m.body}</p>
                          </div>
                          <span className="dm-line-menu">
                            <Menu align={mine ? 'left' : 'right'}
                              button={({ toggle }) => (
                                <button type="button" className="dm-dotbtn" onClick={toggle} aria-label="Message actions">{Icons.More()}</button>
                              )}
                              items={mine ? [
                                { label: 'Copy text', icon: Icons.Copy(), onClick: () => copy(m.body) },
                              ] : [
                                { label: 'Copy text', icon: Icons.Copy(), onClick: () => copy(m.body) },
                                { sep: true },
                                { label: 'Report message', icon: Icons.Flag(), danger: true, onClick: () => setReportingId(m.id) },
                              ]} />
                          </span>
                        </div>
                        {m.refs ? (
                          <div className="reference-cards" aria-label="Referenced content">
                            {m.refs.map((r, i) => (
                              <a key={i} className="reference-card" href={r.url} onClick={(e) => e.preventDefault()}>
                                <span className="ref-type">{r.type}</span>
                                <strong>{r.title}</strong>
                                {r.meta ? <span className="ref-meta">{r.meta}</span> : null}
                              </a>
                            ))}
                          </div>
                        ) : null}
                        {reportingId === m.id ? (
                          <form className="dm-report-form" onSubmit={(e) => { e.preventDefault(); setReportingId(null); onToast('Message reported to the wardens.'); }}>
                            <select className="input-small" aria-label="Reason">
                              {RBDM.reportReasons.map((rc) => <option key={rc} value={rc}>{label(rc)}</option>)}
                            </select>
                            <input className="input-small" style={{ flex: 1, minWidth: 120 }} placeholder="Details (optional)" maxLength={255} />
                            <DS.Button size="sm" variant="danger" type="submit">Report</DS.Button>
                            <DS.Button size="sm" variant="ghost" type="button" onClick={() => setReportingId(null)}>Cancel</DS.Button>
                          </form>
                        ) : null}
                      </React.Fragment>
                    ))}
                  </div>
                </div>
              );
            })}
            {receipt ? (
              <span className="dm-receipt">{receipt === 'Read' ? Icons.Check() : null}{receipt}</span>
            ) : null}
          </div>
        </div>

        <div className="dm-composer">
          <div className="dm-composer-inner">
            <div className="dm-composer-row">
              <Monogram name={me.name} username={me.username} size="sm" />
              <div className="dm-composer-main">
                <div className="dm-composer-field">
                  <textarea ref={taRef} rows={1} value={replyValue} maxLength={5000}
                    onChange={(e) => onReplyChange(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onSend(); } }}
                    placeholder="Write your counsel…" aria-label="Write a message" />
                  <button type="button" className="dm-send" disabled={!replyValue.trim()} onClick={onSend} aria-label="Send">{Icons.Send()}</button>
                </div>
                <div className="dm-composer-meta">
                  <span className="dm-composer-hint">Enter to send · Shift + Enter for a new line</span>
                  <span className="dm-composer-count">{(replyValue ? replyValue.length : 0)} / 5000</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    );
  }
  window.DMThread = Thread;
})();

````

## InfoRail.jsx

````jsx
/* Messages kit — details rail (right pane, collapsible). Everything that used
   to be scattered across the thread (the inline members card, mute / leave,
   owner tools, block / report) is re-homed here into one calm, titled column.
   Direct: the person (gilt monogram, tier, joined, presence) + quiet actions.
   Group: the members list with roles + owner tools, mute, leave. */
(function () {
  const { useState } = React;
  const Icons = window.DMIcons;

  function InfoRail({ convo, onClose, onUpdateConvo, onConfirm, onLeaveConvo, onToast }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram, Switch, Input, Button } = DS;
    const RBDM = window.RBDM;
    const isGroup = convo.kind === 'group';
    const muted = !!convo.muted;
    const u = (name) => RBDM.users[name] || { username: name, name: name, presence: 'offline', joined: '—', tier: 'Member' };
    const isOwner = isGroup && (convo.members.find((m) => m.role === 'owner') || {}).username === RBDM.me;

    const [newMember, setNewMember] = useState('');
    const [rename, setRename] = useState(convo.title || '');

    const toggleMute = () => onUpdateConvo((c) => ({ ...c, muted: !c.muted }));

    function addMember(e) {
      e.preventDefault();
      const name = newMember.trim().replace(/^@/, '');
      if (!name) return;
      if (convo.members.some((m) => m.username === name && !m.left)) { onToast('@' + name + ' is already in counsel.'); setNewMember(''); return; }
      onUpdateConvo((c) => ({ ...c, members: [...c.members.filter((m) => m.username !== name), { username: name, role: 'member' }] }));
      onToast('Added @' + name + ' to the counsel.');
      setNewMember('');
    }
    function doRename(e) {
      e.preventDefault();
      const t = rename.trim(); if (!t) return;
      onUpdateConvo((c) => ({ ...c, title: t }));
      onToast('Group renamed.');
    }
    const removeMember = (name) => onUpdateConvo((c) => ({ ...c, members: c.members.map((m) => m.username === name ? { ...m, left: true } : m) }));
    const makeOwner = (name) => onUpdateConvo((c) => ({ ...c, members: c.members.map((m) => (
      m.username === name ? { ...m, role: 'owner' } : (m.role === 'owner' ? { ...m, role: 'member' } : m)
    )) }));

    const other = isGroup ? null : u(convo.other);

    return (
      <aside className="dm-inforail">
        <div className="dm-rail-head">
          <span className="eyebrow">{isGroup ? 'Members & details' : 'Details'}</span>
          <button type="button" className="dm-iconbtn" onClick={onClose} aria-label="Close details">{Icons.Close()}</button>
        </div>

        <div className="dm-rail-body">
          {isGroup ? (
            <>
              <div className="dm-rail-id">
                <Monogram name={convo.title} username={'group-' + convo.id} size="xl" gilt />
                <h2 className="dm-rail-name">{convo.title}</h2>
                <span className="dm-rail-handle">{convo.members.filter((m) => !m.left).length} in counsel</span>
              </div>

              <div className="dm-rail-sec">
                <h3>Members</h3>
                <ul className="dm-members">
                  {convo.members.map((m) => {
                    const usr = u(m.username);
                    const meRow = m.username === RBDM.me;
                    const canManage = isOwner && !m.left && !meRow && m.role !== 'owner';
                    return (
                      <li key={m.username} className={'dm-member' + (m.left ? ' is-left' : '')}>
                        <Monogram name={usr.name} username={m.username} size="sm" presence={m.left ? undefined : usr.presence} />
                        <span className="m-id">
                          <span className="m-name">{usr.name}{meRow ? ' (you)' : ''}</span>
                          <span className="m-handle">@{m.username}</span>
                        </span>
                        {m.role === 'owner' ? <span className="m-role">Owner</span>
                          : m.left ? <span className="m-role left">Left</span> : null}
                        {canManage ? (
                          <span className="dm-member-tools">
                            <button type="button" className="dm-linkbtn" onClick={() => makeOwner(m.username)}>Make owner</button>
                            <button type="button" className="dm-linkbtn danger" onClick={() => removeMember(m.username)}>Remove</button>
                          </span>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              </div>

              {isOwner ? (
                <div className="dm-rail-sec">
                  <h3>Owner tools</h3>
                  <form className="dm-owner-tool" onSubmit={addMember}>
                    <Input value={newMember} onChange={(e) => setNewMember(e.target.value)} placeholder="username" maxLength={32} aria-label="Add member" />
                    <Button size="sm" variant="secondary" type="submit">Add</Button>
                  </form>
                  <form className="dm-owner-tool" onSubmit={doRename}>
                    <Input value={rename} onChange={(e) => setRename(e.target.value)} maxLength={120} aria-label="Rename group" />
                    <Button size="sm" variant="secondary" type="submit">Rename</Button>
                  </form>
                </div>
              ) : null}

              <div className="dm-rail-sec">
                <h3>This conversation</h3>
                <div className="dm-rail-toggle"><Switch label={muted ? 'Muted' : 'Mute conversation'} checked={muted} onChange={toggleMute} /></div>
                <div className="dm-rail-actions">
                  <button type="button" className="dm-rail-btn danger" onClick={() => onConfirm({
                    title: 'Leave ' + convo.title + '?',
                    body: 'You will stop receiving this counsel. An owner can add you again later.',
                    confirmLabel: 'Leave group', danger: true, onConfirm: () => onLeaveConvo(convo.id),
                  })}>{Icons.Leave()} Leave group</button>
                </div>
              </div>
            </>
          ) : (
            <>
              <div className="dm-rail-id">
                <Monogram name={other.name} username={other.username} size="xl" gilt presence={other.presence} />
                <h2 className="dm-rail-name">{other.name}</h2>
                <span className="dm-rail-handle">@{other.username}</span>
                <span className="dm-tier-pill">{other.tier}</span>
              </div>

              <div className="dm-rail-sec">
                <h3>About</h3>
                <ul className="dm-rail-meta">
                  <li><span className="k">Presence</span><span className="v">{other.presence}</span></li>
                  <li><span className="k">Joined</span><span className="v">{other.joined}</span></li>
                </ul>
              </div>

              <div className="dm-rail-sec">
                <h3>This conversation</h3>
                <div className="dm-rail-toggle"><Switch label={muted ? 'Muted' : 'Mute conversation'} checked={muted} onChange={toggleMute} /></div>
                <div className="dm-rail-actions">
                  <button type="button" className="dm-rail-btn danger" onClick={() => onConfirm({
                    title: 'Block ' + other.name + '?',
                    body: other.name + ' will no longer be able to send you private counsel. You can undo this from settings.',
                    confirmLabel: 'Block', danger: true, onConfirm: () => onToast(other.name + ' is blocked.'),
                  })}>{Icons.Block()} Block {other.name}</button>
                  <button type="button" className="dm-rail-btn danger" onClick={() => onConfirm({
                    title: 'Report this conversation?',
                    body: 'The wardens will review the recent messages in this counsel.',
                    confirmLabel: 'Report', danger: true, onConfirm: () => onToast('Reported to the wardens.'),
                  })}>{Icons.Flag()} Report conversation</button>
                </div>
              </div>
            </>
          )}
        </div>
      </aside>
    );
  }
  window.DMInfoRail = InfoRail;
})();

````

## DMApp.jsx

````jsx
/* Messages kit — app shell. ONE reading room: the conversation list, the open
   conversation, and a collapsible details rail. New message is a dialog OVER
   the room (never a co-equal screen); confirms (leave / block / report) reuse
   the same dialog; a small toast acknowledges quiet actions. Holds all state. */
(function () {
  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  const isMobile = () => !!(window.matchMedia && window.matchMedia('(max-width: 900px)').matches);

  function Empty({ onNew }) {
    const { EightPointStar, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <section className="dm-threadpane">
        <div className="dm-empty">
          <div className="dm-empty-inner">
            <span className="star"><EightPointStar size={54} /></span>
            <h2>Choose a letter to read</h2>
            <p>Your private counsel opens here, beside the list. Pick a conversation, or begin a new one.</p>
            <Button onClick={onNew}>New message</Button>
          </div>
        </div>
      </section>
    );
  }

  function DMApp() {
    const Topbar = window.DMTopbar;
    const NavRail = window.DMNavRail;
    const ConvoList = window.DMConvoList;
    const Thread = window.DMThread;
    const InfoRail = window.DMInfoRail;
    const Modal = window.DMModal;
    const ComposeForm = window.DMComposeForm;
    const ConfirmBody = window.DMConfirmBody;
    const RBDM = window.RBDM;

    const [convos, setConvos] = React.useState(() => RBDM.conversations.map(clone));
    const [activeId, setActiveId] = React.useState(RBDM.conversations[0].id);
    const [filter, setFilter] = React.useState('All');
    const [query, setQuery] = React.useState('');
    const [reply, setReply] = React.useState('');
    const [railOpen, setRailOpen] = React.useState(false);      // details rail — opens on demand (nav rail now grounds the view)
    const [railMobile, setRailMobile] = React.useState(false);  // mobile overlay
    const [reading, setReading] = React.useState(false);        // mobile single-pane
    const [overlay, setOverlay] = React.useState(null);
    const [toast, setToast] = React.useState(null);
    const toastTimer = React.useRef(null);

    // Mark the first conversation read on first paint.
    React.useEffect(() => {
      setConvos((prev) => prev.map((c) => c.id === RBDM.conversations[0].id ? { ...c, unread: false } : c));
    }, []);

    const active = convos.find((c) => c.id === activeId) || null;

    function showToast(msg) {
      setToast(msg);
      if (toastTimer.current) clearTimeout(toastTimer.current);
      toastTimer.current = setTimeout(() => setToast(null), 2600);
    }
    function open(id) {
      setActiveId(id); setReply(''); setReading(true); setRailMobile(false);
      setConvos((prev) => prev.map((c) => c.id === id ? { ...c, unread: false } : c));
    }
    function send() {
      const body = reply.trim();
      if (!body || !active) return;
      const msg = { id: Date.now(), from: RBDM.me, time: 'just now', body };
      setConvos((prev) => prev.map((c) => c.id === active.id
        ? { ...c, messages: [...c.messages, msg], preview: body, read: false, time: 'just now' }
        : c));
      setReply('');
    }
    function updateActive(fn) {
      setConvos((prev) => prev.map((c) => c.id === activeId ? fn(c) : c));
    }
    function leaveConvo(id) {
      setConvos((prev) => {
        const rest = prev.filter((c) => c.id !== id);
        if (id === activeId) { setActiveId(rest[0] ? rest[0].id : null); setReading(false); }
        return rest;
      });
      setRailMobile(false);
      showToast('You left the conversation.');
    }
    function toggleRail() { if (isMobile()) setRailMobile((v) => !v); else setRailOpen((v) => !v); }
    function openRail() { if (isMobile()) setRailMobile(true); else setRailOpen(true); }
    function closeRail() { setRailMobile(false); if (!isMobile()) setRailOpen(false); }
    const confirm = (spec) => setOverlay({ type: 'confirm', ...spec });

    function startConversation({ to, title, body }) {
      const names = to.split(',').map((s) => s.trim().replace(/^@/, '')).filter(Boolean);
      const id = Date.now();
      const first = { id: id + 1, from: RBDM.me, time: 'just now', body: body.trim() };
      let convo;
      if (names.length > 1) {
        convo = {
          id, kind: 'group', title: (title || '').trim() || names.join(', '), unread: false, time: 'just now', read: false,
          members: [{ username: RBDM.me, role: 'owner' }, ...names.map((n) => ({ username: n, role: 'member' }))],
          preview: body.trim(), messages: [first],
        };
      } else {
        convo = { id, kind: 'direct', other: names[0] || 'someone', unread: false, time: 'just now', read: false, preview: body.trim(), messages: [first] };
      }
      setConvos((prev) => [convo, ...prev]);
      setActiveId(id); setReading(true); setRailMobile(false); setOverlay(null);
      showToast('Your counsel has been sent.');
    }

    const railShown = !!active && (railOpen || railMobile);
    const shellClass = 'dm-shell'
      + (railOpen && active ? ' has-rail' : '')
      + (railMobile && active ? ' rail-open' : '')
      + (reading ? ' reading' : '');

    return (
      <div className="app-root">
        <Topbar />
        <div className={shellClass}>
          <NavRail onNewMessage={() => setOverlay({ type: 'compose' })} />
          <ConvoList
            conversations={convos} activeId={activeId} onOpen={open}
            onNew={() => setOverlay({ type: 'compose' })}
            filter={filter} onFilter={setFilter} query={query} onQuery={setQuery} />

          {active ? (
            <Thread
              convo={active} onBack={() => setReading(false)}
              railOpen={railOpen || railMobile} onToggleRail={toggleRail} onOpenRail={openRail}
              onUpdateConvo={updateActive} onConfirm={confirm} onLeaveConvo={leaveConvo} onToast={showToast}
              replyValue={reply} onReplyChange={setReply} onSend={send} />
          ) : (
            <Empty onNew={() => setOverlay({ type: 'compose' })} />
          )}

          {railShown ? (
            <InfoRail convo={active} onClose={closeRail} onUpdateConvo={updateActive}
              onConfirm={confirm} onLeaveConvo={leaveConvo} onToast={showToast} />
          ) : null}
        </div>

        {overlay && overlay.type === 'compose' ? (
          <Modal onClose={() => setOverlay(null)}>
            <ComposeForm onClose={() => setOverlay(null)} onSend={startConversation} />
          </Modal>
        ) : null}
        {overlay && overlay.type === 'confirm' ? (
          <Modal onClose={() => setOverlay(null)}>
            <ConfirmBody {...overlay} onClose={() => setOverlay(null)} />
          </Modal>
        ) : null}

        {toast ? <div className="dm-toast" role="status">{toast}</div> : null}

        {!reading ? (
          <nav className="dm-tabbar" aria-label="Primary">
            <a className="dm-tab" href="../retroboards/index.html">
              <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>Home
            </a>
            <button type="button" className="dm-tab">
              <svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"/></svg>Inbox
            </button>
            <button type="button" className="dm-tab dm-tab-fab" onClick={() => setOverlay({ type: 'compose' })} aria-label="New message">
              <span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
            </button>
            <button type="button" className="dm-tab is-active" aria-current="page">
              <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages
            </button>
            <button type="button" className="dm-tab">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/></svg>You
            </button>
          </nav>
        ) : null}
      </div>
    );
  }

  window.DMApp = DMApp;
})();

````
