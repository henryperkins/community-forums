# Slice 6 admin content design QA

Status: complete for the Slice 6 Boards & tags boundary.

References:

- `docs/design-system/imladris/templates/admin-content/AdminContent.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-content.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/R-admin-content.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-content.md`

Captured 2026-08-04 by `tests/browser/content-console.spec.ts`: 9 passed and 1 expected cross-project skip against the real PHP application and a freshly seeded `retroboards_admin_s6_browser_final` database. The companion `tests/browser/admin-remediation.spec.ts` regression run used a separate freshly seeded `retroboards_admin_s6_remediation_final` database: 15 passed and 13 expected cross-project skips after excluding only the operator-verified pre-existing `board delete previews the authoritative count including hidden content` case.

Reviewed against the references:

- Boards & categories preserves the area-owned `Boards & tags` heading, exact tabs and explanatory copy, category/board hierarchy, edit/archive/delete actions, two-column creation forms, and the design's compact border-and-rule treatment.
- Board edit preserves every production setting, moderator/member roster action, CSRF protection, and scoped 422 anti-draft-loss while adopting the reference labels, measures, and responsive arrangement.
- Tags adopts the design's search, sort, count, paged table, enabled-state controls, and destructive merge language. Usage totals include hidden, held, and deleted-thread associations because merge impact is authoritative rather than visibility-filtered.
- Confirmation pages keep real POST forms and server-trusted counts. Delete-with-move preserves the selected destination on a typed-slug failure; the evidence journey uses only reversible confirmation and validation paths for seeded content.
- With JavaScript disabled, category and board creation, settings validation, roster add/remove, archive/unarchive, delete confirmation, and category deletion remain functional. The no-JS journey cleans up every temporary structure and roster record it creates.
- At 390px, the tier remains independently scrollable, cards and forms collapse to one column, action rows wrap without document overflow, and roster controls retain usable field heights.
- Structure and tags were scanned in light, twilight, and system-dark registers. Board edit and confirmation surfaces were scanned in their captured register, with companion app-script-blocked scans for every no-JS route. No scan reported a serious or critical axe finding.

Representative captures:

- `desktop/01-content-structure-light.png`
- `desktop/01-content-structure-twilight.png`
- `desktop/02-content-tags-light.png`
- `desktop/03-content-board-edit.png`
- `desktop/04-content-board-delete-confirmation.png`
- `desktop/05-content-board-archive-confirmation.png`
- `desktop/06-content-tag-merge-confirmation.png`
- `desktop/07-content-no-js.png`
- `mobile/01-content-structure-light.png`
- `mobile/02-content-tags-twilight.png`
- `mobile/03-content-board-edit.png`

Adjudicated deviations in scope remain governed by the central ledger, notably:

- `C-39` — rust remains available for destructive washes and borders, while destructive small text uses the existing `--danger` token because rust is only 2.47:1 in twilight.
- `FA-09` — production-only board settings and moderator/member roster controls remain as an extrapolation; roster action links stay underlined because colour alone provided insufficient distinction.
- The design's sample content is not imported. Production categories, boards, tags, counts, permissions, and form semantics remain authoritative.

Final non-browser gates on the same Slice 6 source were green: PHPUnit 2,498 tests / 18,051 assertions / 2 skips; `composer verify:imladris` 18 tests / 254 assertions; PHP syntax checks, `git diff --check`, and the strict CSP template scan all passed.

This evidence certifies only the Content area Boards & categories and Tags bodies. Shared admin chrome was certified by Slice 2; later admin areas remain separate slice work.
