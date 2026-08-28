# Imladris thread view — remediation evidence

Captured for **ADR 0030**. Every frame here is written by an assertion in
`tests/browser/thread-view-remediation.spec.ts` that **measures** rather than
looks, against `tests/browser/thread-view-fixture.php` — the design's own topic
(`thread-data.js`) reproduced row for row.

```bash
DB_DATABASE=retroboards_e2e bash tests/browser/prepare.sh
cd tests/browser
npx playwright test thread-view-remediation.spec.ts --project=desktop
npx playwright test thread-view-remediation.spec.ts --project=mobile
```

## What the dataset carries

Workflow status (`solved`) and its two history entries · an assignment to a
warden · two tags · a three-option poll with 27 votes · a living brief with
three versions and two sources, published by a real generation row · an accepted
answer · a grouped same-author run · an anonymous post · reactions on four posts
including one the viewer cast · two signatures · a referenced topic · a link
preview. Five people, six posts, one unread reply.

A topic with three plain replies and nothing attached cannot disagree with its
design, and most of ADR 0030's findings were hiding in exactly the states such a
topic never reaches.

## Frames

| File | What it is written by |
|---|---|
| `01-topic-head.png` | The byline's `scrollWidth ≤ clientWidth` and its height is one line; the facts row is under 40px; no `.tag` is inside `.thread-facts`; the roster's `aria-label` is `In council`. |
| `02-poll.png` | The panel's computed background is `--surface-sunken` and an option's is `--surface-raised`; radius is `--radius-md`; the eyebrow is one line reading `Poll· choose one`; there is no second status pill. |
| `03-living-brief-open.png` | Border `--gold-200`, radius `7px`, padding `15px 17px`; `Version 3` and the source list are absent until the disclosure is opened; the stamp carries no `UTC`; the source link clears 4.5:1 against its own panel. |
| `04-catch-up-closed.png` | The strip is under 48px closed and its points list is hidden; the summary line reads `1 reply — Arwen`. |
| `05-catch-up-open.png` | The same strip after one click — and the same again in a `javaScriptEnabled: false` context, because it is a `<details>`. |
| `06-unread-boundary.png` | The rule reads `New since you last read · 1 reply`; `::before` has `flex-basis: 14px` and `::after` grows. |
| `07-opening-post.png` | No `.badge` inside a `.link-preview`; the host line is not uppercased; the reference card's eyebrow is `#interpretability · referenced` and its left rule is `--gold-500`. Also the post stamp: under 24px from the last byline badge. |
| `08-topic-tools-watch.png` | `Instant` carries `aria-pressed="true"`; pressing `Daily` commits in one POST and comes back pressed. |
| `09-topic-tools-management.png` | Both switches report `aria-checked="false"`; the foot note is the warden variant. |
| `10-twilight.png` | The source link still clears 4.5:1 against the brief's panel in the night register. |
| `11-thread-view-desktop.png` | The whole surface at 1440×2400, as a member meets it. |
| `12-thread-view-mobile.png` | At 390px: the byline does not elide, the strip survives, and the tags keep their row. |

## Measured, before and after

| | Design | Before | After |
|---|---|---|---|
| Reading column | 646px | 860px | 646px |
| Header byline | `Opened by Erestor · Jul 10 · 5 replies` | `Opened by Erestor · 5 repl` (ellipsised) | `Opened by Erestor · Aug 25 · 5 replies` |
| Identity group | 333px | 313px | 336px |
| Post stamp ← last badge | inline | 456px | 8px |
| `h1` size / measure | 36px / 36ch | 34.4px / 28ch | 36px / 36ch |
| Standing chip | 11.2px | 9.6px | 11.2px |
| Poll panel / option | sunken / raised | raised / sunken | sunken / raised |
| Brief border / radius | `--gold-200` / 7px | `--border-hair` / 12px | `--gold-200` / 7px |
| Regions before the first sentence | 1 (the strip) | 3 (poll, brief, panel) | 1 (the strip) |

Two of the design's strings are **not** adopted — the breadcrumb's `Home` and the
brief's pause sentence — because a dated product spec pins each. See ADR 0030,
*Deliberate keeps* C and C2.
