# Slice 14 admin packages design QA

Status: complete for the Slice 14 Packages & registries boundary.

References:

- `docs/design-system/imladris/templates/admin-packages/AdminPackages.dc.html` (764 lines)
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-packages.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-packages.md`
  — there is **no** `R-admin-packages.md`. Like `admin-integrations`, `admin-members` and
  `admin-features`, this screen has only the `D-`/`V-` pair; every production anchor was
  re-verified against the current files before this slice was written, because the `D-`/`V-`
  line numbers are stale throughout.
- `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` §1.1 (`C-03`, `C-05`,
  `C-06`, `C-07`, `C-09`, `C-11`, `C-13`, `C-14`, `C-32`, and the new `C-49`), §1.2 (`FA-20`,
  `FA-21`, `FA-22`), §1.3 (`FC-22`, `FC-23`), §1.4 (`FR-30`)

Captured 2026-08-08 against the real PHP application and a freshly seeded browser database
(`retroboards_console_e2e`), with `prepare.sh` re-seeding between spec groups exactly as
`npm run evidence` does.

## Surfaces

Ten files — eight page templates and two partials — under the one Packages area heading and the
three-tab strip (`Packages` · `Registry trust` · `Extensions`):

| Template | Route | Tab lit |
|---|---|---|
| `packages.php` | `/admin/packages` | Packages |
| `package_detail.php` | `/admin/packages/{id}` | Packages |
| `package_plan.php` | `/admin/packages/{id}/plan` | Packages |
| `package_consent.php` | `/admin/packages/{id}/consent` | Packages |
| `package_security.php` | `/admin/packages/security` | Packages |
| `package_publisher.php` | `/admin/packages/publishers/{id}` | Packages |
| `registries.php` | `/admin/registries` | Registry trust |
| `extensions.php` | `/admin/extensions` | Extensions |
| `_package_review_form.php` | partial, from `package_detail.php` | — |
| `_package_integration.php` | partial, from `package_detail.php` | — |

**The plan's "9 templates + 2 partials" is wrong.** The tree holds 8 + 2; no ninth
package/registry/extension view is rendered by any controller. `themes.php` /
`theme_safe_mode.php` belong to the Appearance slice.

## Reviewed against the references

- **Chrome.** All eight surfaces render the one area heading `Packages & registries` and the one
  `Supply chain sections` tab strip. Five drill-ins keep their parent tab lit and their own
  `h2.admin-record-title`. No leaf emits an `<h1>`.
- **Catalogue.** Headless table card (the area heading and lit tab already name it), intro row with
  the security-console link kept as a real `<a>` (`C-11`), design chip vocabulary, and the em-dash
  `—` for no-install. The table stays `<table class="audit …">` with a `<tbody>` and a link named
  exactly `Details` — four specs navigate through it.
- **Package record.** The design's pairing — Provenance ‖ Installation, Releases full-width between,
  Permissions ‖ History — on the existing `.admin-split`, with Provenance and the install facts as
  `<dl>` fact lists and Permissions/History as ruled lists. Releases splits into an `<h3>` section
  title plus a caption. Permissions is lifted out of the Installation card into its own peer card.
- **The ruled list is the slice's highest-leverage primitive** — nine consumers: detail Permissions
  and History, plan permission preview, consent's three diff buckets and pending grants, the
  security transparency log, registry blocklist and advisories, publisher decisions, extensions run
  history.
- **Security response.** The brake chip moves out of the `<h2>` (it was polluting the accessible
  heading name) and is reclassified to `.pill-danger`; the counts card and transparency log become
  panel cards; the log gains the empty state neither side had.
- **Registry trust.** The enable/disable toggle moves into the card head, right-aligned, as the
  design has it; blocklist and advisories become ruled lists; all three disclosures survive and now
  re-open when they own an error.
- **Extensions.** Two-up probe ‖ handlers, the honest flag callout, and the run-history ladder.
- **No inline script/style/handlers (`C-09`).** Anti-draft-loss 422 paths keep `->errors` + `->old`
  (`C-13`); every mutating control is a `<form method="post">` carrying `csrfField()`; navigation is
  `<a href>` (`C-11`); every re-auth field renders inline and always, never behind a disclosure
  (`C-14`); every scroll region, sr-only label and `aria-label` survives (`C-05`, `C-06`).

## Deviations recorded by this slice

- **`C-49` — two more fixed-ramp AA failures, both measured.** The install-state chip
  (`--green-800` on the flipping `--brand-subtle`: **10.08:1 light / 1.10:1 twilight**) and the
  extensions `ok` label (`--success` at `.72rem`: **4.91:1 / 4.45:1**). Both take
  `--surface-done`/`--on-done` (**10.08:1 / 7.71:1**), the pair the design itself uses eight lines
  later for the sibling compatibility chip. The rust wash is fixed at 9% for banners, 10% for chips.
- **A third AA defect, found by the evidence run rather than by reading.** axe reported 70
  `link-in-text-block` violations on the security console: the new prose classes replace `.muted`,
  and the existing underline hook (`app.css:154`) is scoped to `.muted`/`.audit-flags`, so links
  inside them were distinguishable by colour alone. Fixed by extending the underline treatment to
  the packages prose classes.
- **`C-07` — the brake chip is `.pill-danger`, and `.pill-admin` is untouched.** `.pill-admin` is
  the accent-filled operator chip carrying three distinct meanings across 41 call sites; recolouring
  it here would change all of them. After this slice exactly one template call site remains
  (`theme_safe_mode.php`), which `a11y.spec.ts` pins.
- **`FC-23` — the stale-snapshot alert is scoped to enabled registries.** `registries.php` tells the
  operator a new source "starts disabled until you enable it", and production then painted a red
  `never fetched` alarm for exactly that source. The freshness fact is still computed for every
  registry; only the alert is gated. This changed an existing assertion, so both halves are now
  pinned: `test_admin_catalogue_lists_packages_with_badges_and_noindex` enables the fixture registry
  and still requires the banner, and `test_stale_alert_is_suppressed_for_a_disabled_registry` covers
  the suppression.
- **`FA-20`/`FA-21`/`FA-22` kept against the D report.** The advisories-and-blocklist counts card
  survives (two live counts that exist nowhere else); the install plan keeps its `Package` row (the
  only place the canonical `package_uid` appears on the re-auth-gated confirmation); every
  `unknown publisher` / `none stable` / `n/a` fallback stays.
- **`FR-30` / `C-32` — the extensions flag note is rewritten, not transcribed.** The design's copy
  says the surface is "reserved and dark under Gate B" and that "no handler is dispatched". Both are
  false in the only state production can render: `AdminExtensionController` 404s while the flag is
  off, so reaching the page means the flag is on. The design also renders the Extensions tab live in
  every state; production cannot, and ships no dead chrome for it.
- **Production wording kept over the design's** in three places, each pinned and each more truthful:
  `Record install` (nothing executes), `expires` on a future snapshot timestamp, and the retention
  of both the local-block and advisory signals in the catalogue's Advisory cell (`FC-22`).
- **Two anti-draft-loss defects fixed in passing.** A refused package review discarded both the
  typed note and the chosen decision, silently reverting the row to `approved` — the most dangerous
  default on a supply-chain review form. And on the publisher record a refused key revoke
  repopulated the *suspend* form's reason, because both post a field called `reason`; a hidden form
  discriminator now scopes the replay.
- **`registries.php` keeps its 14 bare `<p class="field-error">` lines.** ADR 0023 deferral item 4 is
  still open for this file: wiring `field_error()`/`field_attrs()` honestly first needs
  `AdminRegistryController::consoleView()` to stop broadcasting one flat `$errors`/`$old` bag to
  every registry card, which is a behaviour change, not a restyle. Restyled visually only; the
  deferral is carried, not closed.
- **`Local blocklist` stays an `<h2>`** where the design uses an `<h3>` eyebrow — it is a top-level
  section of the tab, and preserving the level costs nothing and avoids spec churn.
- **Five browser assertions were rewritten** because they described replaced markup, not because
  they failed: the catalogue `<h2>`, the `Releases (immutable…)` heading, the install-facts
  `getByRole('row')`, the install-plan em dash, and the consent pluralisation. A sixth
  (`getByText('api.example.com')`) was scoped to the permission's machine key, because the design's
  row renders the human label and the key as separate elements so the host string now appears twice
  by design.

## New spec

`tests/browser/packages-console.spec.ts` — the per-area harness this migration already uses for
content, members, integrations, features and account. It owns copied body metrics and register
parity for six surfaces: axe in light, twilight and `data-theme="system"` under a dark OS; light and
twilight captures at 1280px and 390px; a `javaScriptEnabled: false` walk; document-overflow at each
width; and three anti-draft-loss 422 captures (brake, registry key pin, publisher suspend).

Its overflow helper names the offending elements in the failure message — that is how slice 13's two
layout defects were found, and it is why the `link-in-text-block` regression above surfaced as a
precise finding rather than a vague red.

The whole file is skipped without `RB_BROWSER_DARK_SURFACES=1`: `/admin/extensions` 404s otherwise,
and the publisher and security fixtures exist only in that seed. **`packages-extensions*` is
therefore a seed-only capture — not a state a default install can render.** Behavioural contracts
(install/consent/enable journey, brake re-auth, review refusal, credential provisioning) remain in
`gate-a`, `package-security`, `package-review` and `package-integrations`.

Kept **out** of `npm run evidence`, as slices 12 and 13 did; whether the per-area consoles join the
aggregate script is a Slice 19 closeout decision.

## Known branch state, carried not hidden

- `bin/build-imladris-assets.php --check` reports the `application_surface.sha256` drift, so
  `ImladrisRuntimeAssetTest` is red on this branch **by design**: the runtime baseline is refreshed
  exactly once per merge, on `main`, by the merger (ADR 0024 obligation 4, ledger §6 rule 5). No
  slice branch may carry that file. The design-surface digest added in `e3eada0` is **green** — this
  slice touches no mirror file.
- Two `admin-remediation` board-composer tests remain pre-existing exclusions owned by Slice 19.
- The gate-a package tests are **stateful and share one database**: they must run on a freshly
  seeded DB. Two apparent failures during this slice's development were seed pollution, not
  regressions, and cleared on a clean `prepare.sh`.
- **`npm run evidence` still has one red test, and it is not this slice's.**
  `thread-view-study.spec.ts:328` ("Study layout matches desktop and mobile geometry") fails a
  tolerance, expected ≤ 2 and received 15. Because the script chains its groups with `&&`, that one
  failure aborts the rest of the sweep. **Verified, not assumed**: the test was re-run with this
  slice's production changes (`app.css`, `templates/admin/`, `AdminPackageSecurityController`)
  stashed back to `HEAD` and failed with the identical numbers. It is a thread surface; every class
  this slice adds is either scoped `.admin-console` or prefixed `packages-`/`registry-`/`extension-`
  and occurs nowhere outside the ten files above. Slice 13 recorded the same test as pre-existing;
  the two `rich-content` failures it also recorded did not reproduce this time. Carried for Slice 19
  closeout.

## Verification

**Browser.** Five groups against a freshly seeded `retroboards_console_e2e`, at both the 1280px and
390px projects:

| Group | Specs | Result |
|---|---|---|
| 1 | `packages-console.spec.ts` (dark seed) | **14 passed**, 0 failed (1.4m) |
| 2 | `gate-a.spec.ts --grep package` (non-dark seed) | **6 passed**, 0 failed (1.7m) |
| 3 | `package-security` · `package-review` · `package-integrations` (dark seed) | **12 passed**, 0 failed (35.9s) |
| 4 | `npm run a11y` | **35 passed**, 3 skipped, then **6 passed**, 0 failed |

Group 1 carries the axe passes: light, twilight and `data-theme="system"` under
`prefers-color-scheme: dark`, on all six surfaces at both widths, plus document-overflow and the
JavaScript-disabled walk.

**Backend.** Full suite on private `retroboards_test_s14`: **2,572 tests / 18,652 assertions /
2 skipped / 1 failure** (07:48), and that one failure is the application-surface digest described
above. The pre-slice baseline on this branch was 2,566 / 18,629 with the same single failure, so
this slice adds 6 tests and 23 assertions and introduces no new red. The focused package suite
(`AppRegistryCatalogTest`, `AppRegistryAdminTest`, `AppPackageLifecycleTest`, `AppPackageReviewTest`,
`AppPackageSecurityConsoleTest`, `AppPackageIntegrationTest`, `AppPackagePublisherConsoleTest`,
`AppAdminExtensionsTest`, `AppThemePackageTest`) passes **63 tests / 381 assertions** on its own.

**Static gates.** The CSP template scan returns only `layout.php`'s permitted external `src` tags.
`php -l` passes on every touched template and both controllers. The class/CSS parity sweep — the
slice-11 failure mode — reports no unstyled class. No generated asset, mirror document or baseline
file is modified by this commit.

## Captures

52 PNGs. `desktop/` and `mobile/` hold the light register plus the JavaScript-disabled walk and the
three 422 states; `twilight/{desktop,mobile}/` hold the same six surfaces in the twilight register;
`comparisons/` holds the twelve side-by-side register pairs.

- `packages-catalogue` · `packages-detail` · `packages-security` · `packages-publisher` ·
  `packages-registries` · `packages-extensions` — the register set.
- `packages-*-no-js` — five routes with `javaScriptEnabled: false`.
- `packages-brake-422` — wrong brake password, typed reason replayed, `err-brake-current_password`
  visible.
- `packages-registries-422` — refused key pin, typed `root-2` replayed, disclosure re-opened.
- `packages-publisher-422` — refused suspend, its own reason replayed and not the revoke form's.
- Gate A / behavioural set stays under `docs/evidence/browser/{desktop,mobile}/`.

**Reading the captures:** the five identically-named `Acme Themes` publishers and the repeated
stale-snapshot banners are the fixture, not the chrome — `RegistryFixtures` seeds one publisher per
registry. The transparency log is empty until the brake is toggled.

This evidence certifies only the packages/registries/extensions pane bodies. Shared admin chrome was
certified by Slice 2; later admin and account areas remain separate slice work.
