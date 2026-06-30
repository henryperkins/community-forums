# Imladris token reference (authoritative — from _ds_manifest.json) + app.css aliases

These canonical tokens are defined in public/assets/app.css :root. Use these names in fixes.

## Color
parchment-50 #FAF6EC · 100 #F5EFE1 · 200 #ECE4D2 · 300 #DED2B8
ink-900 #1B231D · 700 #313B33 · 500 #515C52 · 400 #6E7A6E · 300 #94A095
green-900..050 (brand evergreen); gold-700..100 (mallorn accent); river-* (Bruinen blue); twilight-900..700 (dark register)
leaf=green-500 (success) · amber #B7842F (warning) · rust #9C4A33 (danger) · slate=river-500 (info)

## Semantic aliases (app.css)
--surface (parchment-50) · --surface-sunken (parchment-200) · --surface-raised
--accent = green-700 (primary brand: links/buttons) · --accent-contrast = parchment-50
--accent-2 = gold-500 (indicator: active/unread/gold) · --brand = green-700 · --brand-subtle = green-050
--rule-gold = gold-500 · --gilt = inset 0 0 0 1px gold-500@38% (precious avatar ring)
status: --surface-done=green-050/--on-done=green-800 · --surface-review=gold-100/--on-review=gold-700 · --surface-pending=parchment-200

## Type
--font-display 'Cormorant Garamond' (headings/wordmark) · --font-label 'Marcellus' (eyebrows/labels/buttons, tracked caps)
--font-body 'EB Garamond' (prose) · --font-mono 'JetBrains Mono' (data/routes)

## Status taxonomy (chip → text/bg/border) — handoff §10
Solved: --success / green-050 / green-200
Needs answer: --warning / gold-100 / gold-200
Decision made: green-800 / brand-subtle / green-200
Staff notice: gold-700 / gold-100(accent-subtle) / gold-200
Pinned/Locked: ink-500 / surface-sunken / border-hair
Archived: ink-400 / transparent / dashed border-strong
Role badges (derived): OP green-800/brand-subtle; Staff gold-700/accent-subtle; Accepted check + green-200.

## Density (handoff §4)
Comfortable ("Hall"): card per thread w/ avatar+snippet+meta. Compact ("Watch"): one line per thread, status on a colored LEFT RULE, redundant status word. Saved account preference (settings appearance), not a per-visit toggle.
