# Messages phone adaptation (320–430px) — 2026-10-19

Scope: `/impeccable adapt` for Messages on mobile phones, fixing the broken /
cramped layout measured at 320–390px. Refinement of the incumbent Bruinen
room; no new breakpoint (the surface's existing 900px single-pane step), no
route, markup, or behaviour change.

## Before (Chromium, private e2e DB, long group title + direct counsel)

- Conversation header at 320px measured **163px** tall (group) / **145px**
  (direct): the lock eyebrow wrapped to two lines in a 69px copy column, the
  title truncated to “Bob B…” / “Rollba…”, and “Here now” broke mid-phrase.
- At 360px the eyebrow still wrapped; at 390px the group sub-line wrapped
  mid-phrase. List, new-message page, details rail, and compose dialog were
  measured and already fit at 320px.

## Change (`public/assets/app.css`, `@media (max-width: 900px)`)

- `.dm-thread-head` becomes a grid (`back | monogram | copy | actions`) whose
  eyebrow area spans the monogram + copy columns, so the eyebrow sets on one
  line; `var(--space-3)` padding, `var(--space-2)` gaps.
- `.dm-presence` stays one unbreakable unit; the sub-line wraps only between
  phrases.
- `.dm-composer` takes `var(--space-3)` inline padding (dock box 268→296px
  at 320px).

Pinned by `MessagesStylesContractTest::test_phone_header_spans_the_eyebrow_across_the_monogram_column`.

## After (same harness)

- Header: **107px** (group) / **88px** (direct) at 320px; eyebrow one line,
  “Bob Brooks” in full, “@bob · Away” on one line, group sub wraps between
  phrases (“3 in counsel ·” / “1 here now”). No horizontal overflow at
  320/360/390/430px; twilight and no-JS (including the `#dm-rail` anchor)
  verified at 320px.

Captures: `list-320`, `conversation-320`, `group-320`, `group-390`,
`new-320`, `group-320-twilight`, `group-320-nojs`, `rail-320-nojs`.

Not verified: a physical iOS/Android device and the safe-area inset under a
notched phone (the global viewport meta has no `viewport-fit=cover`; that is
a shell-wide question, left untouched here). Gesture behaviour is unchanged
— no custom drag/slider control is in scope.
