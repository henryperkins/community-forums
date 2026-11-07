# Forum typography audit (ADR 0057)

Reproduces the before/after figures in
`docs/evidence/forum-refinement/README.md`. These are measurement scripts, not
pass/fail specs; `forum-refinement.spec.ts` is the regression gate.

Prepare a disposable fixture (`bash prepare.sh`), serve it, then from
`tests/browser`:

```bash
E2E_BASE_URL=http://localhost:8011 OUT=/tmp/audit-after node forum-type-audit/run.mjs
node forum-type-audit/metrics.mjs /tmp/audit-after
E2E_BASE_URL=http://localhost:8011 node forum-type-audit/targets.mjs
```

- `run.mjs` visits 22 routes (9 guest, 8 member, 5 operator) in light and dark
  at 1280 × 800 and 390 × 844: 88 states. For each it saves the measurements
  from `measure.js` (every visible text node's size, face, case, tracking and
  contrast; targets; button label centring; prose measure), an axe WCAG 2.2 AA
  scan and a screenshot.
- `metrics.mjs` summarises an output directory. Text inside `code`, `pre`,
  `kbd` or `samp`, or on an exact-identifier class, does not count as code face
  outside code. `aria-hidden` text is excluded from the counts.
- `targets.mjs` checks WCAG 2.5.8: each non-inline target under 24 × 24 must
  meet the spacing exception. It exits non-zero on a failure.

Set `E2E_BROWSER_CHANNEL=chrome` or `CHROME=/path/to/chrome` when Playwright's
bundled Chromium does not match the installed library.
