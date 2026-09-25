# Overlapping site suspension evidence (2026-09-25)

The no-JavaScript Playwright case `overlapping site suspensions keep the member write gate closed` submitted an indefinite site suspension followed by an already elapsed shorter suspension through the admin form. The member's subsequent account write returned `403`; the account page also withheld the deactivation form and showed the site restriction state.

The case passed in Chromium at desktop (1280 px) and mobile (390 px) with `APP_ENV=test`, the disposable `retroboards_unified_e2e_susp_20260925` database, and `RB_EVIDENCE_DIR=docs/evidence/suspension-overlap`. Captures: [desktop](desktop/01-overlapping-suspensions-retained.png), [mobile](mobile/01-overlapping-suspensions-retained.png).

PHPUnit `AppUserModerationTest` also covers indefinite then shorter, longer then shorter, shorter then longer, and explicit lift. The first two cases failed before the service fix and passed after it.
