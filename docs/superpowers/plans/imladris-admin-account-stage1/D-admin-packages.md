# Stage 1 — Screen diff: `admin-packages`

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-packages/AdminPackages.dc.html` (764 lines; markup 1–465, `x-dc` logic 466–762)
**Live PRODUCTION.md mapping:** `templates/admin-packages` — "catalogue → plan → consent → enable, registry trust"
**Production surface:** 9 templates + 5 controllers + `AdminExtensionController` / `templates/admin/extensions.php`, 31 routes (`src/Core/App.php:2242–2286`, `:2312`)

## Headline

This is the *closest* production/design pair in the whole console. Whole paragraphs are already **verbatim identical**:

| String | Design | Production |
|---|---|---|
| "Create an install plan before any local state is written. Enabling happens only after install and permission consent." | line 121 | `templates/admin/package_detail.php:89` |
| "Installing records provenance and permissions; nothing executes until you consent and enable." | line 220 | `templates/admin/package_plan.php:34` |
| "The private signing root lives offline with the operator; this console pins, rotates, and revokes public keys only. Trust changes require your password. The local blocklist works regardless of registry state." | line 335 | `templates/admin/registries.php:14` |
| "Local blocklist (registry-independent)" | line 375 | `templates/admin/registries.php:137` |
| "No lifecycle history recorded for this package." | line 208 | `templates/admin/package_detail.php:243` |
| "No pending grants." | line 263 | `templates/admin/package_consent.php:71` |
| "trust is never implied by being listed" | line 113 | `templates/admin/package_detail.php:51` |
| "Immutable: any changed byte is a new release." | line 147 | `templates/admin/package_detail.php:58` (as a parenthetical in the h2) |
| Stale-snapshot alert incl. `php bin/console worker:registry-refresh` | line 53 | `templates/admin/packages.php:18–21` |
| Emergency-brake blurb (both engaged/released variants) | lines 702–704 | `templates/admin/package_security.php:19` |

The gap is therefore **structural, not editorial**: production spreads one design screen across five h1-bearing pages with no tab strip, and the design compresses four production pages (`packages` / `package_security` / `registries` / `extensions`) into three tabs plus a drill-in stack.

**Fiction load is near zero.** Every Imladris-lexicon token in this file lives in the `x-dc` fixture arrays (`PACKAGES`, `PUBLISHERS`, `REGISTRIES`, `ADVISORIES`, `TRANSPARENCY`, lines 486–553) — sample data, not chrome. The only fiction string that reaches rendered markup is `<strong>community.imladris</strong>` at line 53, and production already renders that position dynamically from `$registry['source_id']`.

---

## 1. Section-order comparison

### Design order (verbatim headings / eyebrows / comments)

| # | Design landmark | Line |
|---|---|---|
| 1 | `<x-import … AdminNav area="packages">` | 22 |
| 2 | h1 — **Packages &amp; registries** | 26 |
| 3 | `nav aria-label="Supply chain sections"` — tabs **Packages · Registry trust · Extensions** | 28–35 |
| 4 | Flash banner `role="status"` (sc-if `flash`) → `{{ flashText }}` | 37–42 |
| 5 | `<!-- ═══ Catalogue ═══ -->` (sc-if `showCatalogue`) | 44–98 |
| 5a | intro p — "A staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks." + right-aligned **Package security response →** | 48–49 |
| 5b | `role="alert"` — **Stale snapshot:** … (sc-if `staleSnapshot`) | 52–54 |
| 5c | catalogue table — *Package / Type / Install / Trust class / Latest / Compatibility / Advisory / (sr-only) Actions*, row action **Details** | 56–96 |
| 6 | `<!-- ═══ Package detail ═══ -->` (sc-if `showDetail`) | 100–212 |
| 6a | back button **Package catalogue** | 103 |
| 6b | h2 `{{ det.name }}` + mono `{{ det.uid }}` | 104–105 |
| 6c | h3 eyebrow — **Provenance** (Pinned source / Type / Trust class / Advisory status) | 109–115 |
| 6d | h3 eyebrow — **Installation** (not-installed → **Install plan**; installed → State / Health / Version / Update policy, pending-consent notice, **Enable · Disable · {{ pinLabel }} · {{ policyLabel }} · Uninstall**) | 119–141 |
| 6e | h3 — **Releases** + "Immutable: any changed byte is a new release." — *Version / Channel / Digest / Signed by / Core range / Local review* | 146–173 |
| 6f | h3 eyebrow — **Permissions** | 178–191 |
| 6g | h3 eyebrow — **History** (+ empty "No lifecycle history recorded for this package.") | 195–208 |
| 7 | `<!-- ═══ Install plan ═══ -->` (sc-if `showPlan`) | 214–246 |
| 7a | back button `{{ det.name }}` | 217 |
| 7b | h2 `{{ planTitle }}` = "Install plan — {name} {version}" | 219 |
| 7c | blurb + dl *Version / Digest / Registry / Review* | 220–226 |
| 7d | h3 — **Permission preview** | 227–235 |
| 7e | `role="alert"` `{{ planErrText }}` | 236 |
| 7f | label **Current password** + **Install** | 238–243 |
| 8 | `<!-- ═══ Consent ═══ -->` (sc-if `showConsent`) | 248–274 |
| 8a | back button; h2 — **Consent to permissions**; blurb "Granting is per-permission and audited. A package cannot be enabled while any grant is pending." | 251–254 |
| 8b | pending list; empty **No pending grants.**; `{{ consentErrText }}` | 255–264 |
| 8c | **Current password** + **Grant and continue** | 266–271 |
| 9 | `<!-- ═══ Security response ═══ -->` (sc-if `showSecurity`) | 276–330 |
| 9a | back button **Package catalogue** | 279 |
| 9b | h2 — **Emergency execution brake** + `{{ brakeBlurb }}` + `Reason (optional)` + **Emergency-disable all packages** / **Resume package execution** | 281–291 |
| 9c | h2 — **Publishers** — *Publisher / Status / Verified / (sr-only) Actions*, action `{{ pb.actionLabel }}` (Suspend/Reinstate) | 293–313 |
| 9d | h2 — **Transparency log** (sc-if `transparencyOn`) | 315–328 |
| 10 | `<!-- ═══ Registry trust ═══ -->` (sc-if `showRegistry`) | 332–407 |
| 10a | intro p (verbatim match to prod) | 335 |
| 10b | per-registry `<section>`: h2 `{{ reg.displayName }}` + `{{ reg.sourceId }}` + enabled/disabled chip + `{{ reg.toggleLabel }}`; `{{ reg.snapshotText }}`; keys table *Key id / Status / Window / Fingerprint / Actions(**Revoke**)* | 337–371 |
| 10c | h3 eyebrow — **Local blocklist (registry-independent)** + **Remove**; empty "Nothing is locally blocked." | 374–388 |
| 10d | h3 eyebrow — **Advisories** + **Acknowledge** / `{{ a.ackText }}` | 390–404 |
| 11 | `<!-- ═══ Extensions ═══ -->` (sc-if `showExtensions`) | 409–460 |
| 11a | info callout — "Server extension execution is controlled by the `server_extensions` flag, which is reserved and dark under Gate B (ADR 0018). This page is a read-only probe: handlers are listed, nothing runs." | 412–415 |
| 11b | h3 eyebrow — **Sandbox probe** | 418–422 |
| 11c | h3 eyebrow — **Handlers** — *Package / Handler / Entrypoint* | 424–442 |
| 11d | h3 eyebrow — **Run history** | 445–458 |

### Production order (five separate pages, no tab strip)

| Page | Order |
|---|---|
| `templates/admin/packages.php` | h1 **Package catalogue** + `Admin mode` pill (:8–9) → `admin/_nav` (:11) → intro p + link to security console (:14) → per-registry stale alert (:16–23) → card h2 **Packages** (:26) → empty state (:28) → catalogue table (:31–65) |
| `templates/admin/package_detail.php` | h1 `{name}` + pill (:34–35) → `_nav` (:37) → `$errors` loop (:40–42) → card **Provenance** (:45) → card **Releases (immutable: any changed byte is a new release)** (:58) → card **Installation** (:84) [plan form / install table / pending / staged / action grid / h3 **Permissions** :218] → card **History** (:241) → card **Advisories** (:266) → `_package_integration` partial (:289) |
| `templates/admin/package_plan.php` | h1 **Install plan - {name} {version}** (:11) → `_nav` (:14) → errors (:17–19) → refusal (:21–26) → warnings (:28–30) → card **Install plan** (:33) → card **Permission preview** (:48) → card **Install** (:70) |
| `templates/admin/package_consent.php` | h1 **Consent to permissions** \| **Approve update to {v}** (:12) → `_nav` (:15) → errors (:18–20) → staged refusal (:22–24) → card **Pending grants** \| **Permission changes** (h3 New permissions / Removed / Unchanged) (:27–88) → card **Grant** (:91) |
| `templates/admin/package_security.php` | h1 **Package security response** (:8) → `_nav` (:11) → intro p (:14) → card **Emergency execution brake** (:17) → card **Publishers** (:33) → card **Advisories &amp; blocklist** (:55) → card **Transparency log** (:60) |
| `templates/admin/package_publisher.php` | h1 `{display_name}` + status/verified pills (:8–11) → `_nav` active `registries` (:14) → intro p (:17) → card **Status** (:20) → card **Signing keys** (:50) + `<details>` **Pin a new public key** (:79) / **Apply a signed key rotation** (:94) → card **Packages &amp; review decisions** (:107) |
| `templates/admin/registries.php` | h1 **Registry trust &amp; security response** (:8) → `_nav` (:11) → intro p (:14) → empty-registries card (:16–21) → per-registry card (keys table :33, `<details>` **Pin a new public key** :61 / **Apply a signed key rotation** :79 / **Ingest a signed advisory manually** :93, enable/disable form :105) → card **Add a registry source** (:121) → card **Local blocklist (registry-independent)** (:137) → card **Advisories** (:175) |
| `templates/admin/extensions.php` | h1 **Server extensions** (:5) → `_nav` (:8) → card **Sandbox probe** (:12) → card **Global emergency disable** (:21) → card **Handlers** (:26) → card **Run history** (:46) |

**Order verdict:** the design's *within-page* order matches production almost everywhere except one swap on the detail page — design is **Provenance → Installation → Releases → Permissions → History**; production is **Provenance → Releases → Installation (Permissions nested inside) → History → Advisories**.

---

## 2. Difference table

Legend: `copy` = production must simply change to match. Risk = risk of the change, not of the gap.

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Page chrome | constraint | `<x-import … AdminNav area="packages">` shared bar; per-screen h1 below it | Per-page `<header class="admin-head">` + `Admin mode` pill, then `admin/_nav` grouped drawer — `packages.php:7–11`, `package_detail.php:33–37`, `package_plan.php:10–14`, `package_consent.php:10–15`, `package_security.php:7–11`, `package_publisher.php:7–14`, `registries.php:7–11`, `extensions.php:4–8` | Adopt AdminNav in the shared `admin-overview` slice; this screen only consumes it. `_nav.php` is a flag-aware grouped drawer (ADR 0023 #6, `_nav.php:37–44`) — do **not** collapse it into a flat strip here. | medium |
| 2 | Page title | copy | h1 **Packages &amp; registries** (:26) for the whole area | Five different h1s: "Package catalogue", "Registry trust &amp; security response" (`registries.php:8`), "Package security response" (`package_security.php:8`), "Server extensions" (`extensions.php:5`), `{package name}` | Unify the three tab roots under h1 "Packages & registries"; keep drill-in h1/h2s (`{name}`, "Install plan — …", "Consent to permissions", "Emergency…") as sub-headings. | low |
| 3 | Tab strip | copy + constraint | `nav aria-label="Supply chain sections"` — 3 underline tabs (:28–35) rendered as `<button onClick>` | No tab strip anywhere; the three roots are three sibling entries in the `Integrations` nav group (`_nav.php:38,39,43`) | Add the tab strip; **mechanism constraint**: `<a href="/admin/packages">`, `/admin/registries`, `/admin/extensions` with `aria-current="page"`, never `<button onClick>` (PE rule). | low |
| 4 | Tab: Extensions availability | constraint | Extensions tab always rendered (:33–34) | `server_extensions` defaults **false** (`FeatureFlags.php:100`); `/admin/extensions` throws `NotFoundException` (`AdminExtensionController.php:20–22`); nav shows a disabled `<span class="admin-nav-link is-disabled">` (`_nav.php:80–84`) | Render the Extensions tab as a disabled, non-link item carrying the existing "Disabled until the feature flag is enabled" note when the flag is dark. Never a live link to a 404. | low |
| 5 | Flash banner | copy | `role="status"` gold-check banner, `--surface-done`/`--green-200`, 200 ms `apkRise` (:38–41) | `templates/partials/flash.php` — bare `<div class="flash" role="status">`, rendered by `layout.php:61,72,78` | Restyle the shared flash to the design's success-banner anatomy. Cross-cutting; owned by the shared-chrome slice, not this screen. | low |
| 6 | Catalogue intro | copy | "A staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks." (:48) | "Staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks. Emergency controls live in the <a>package security console</a>." (`packages.php:14`) | Change the lead to "A staff browse…"; move the security link out of the sentence into the right-aligned "Package security response →" affordance. | low |
| 7 | Catalogue → security link | copy | Right-aligned accent link **Package security response →** on the intro row (:49) | Inline prose link "package security console" (`packages.php:14`) | Adopt the trailing-arrow accent link at the row's right edge. Keep `href="/admin/packages/security"` — verified at `App.php:2244`. | low |
| 8 | Stale-snapshot alert | copy | Single `role="alert"` block, rust left rule, `--surface-raised` mix (:52–54) | `<p class="field-error">` per unfresh registry, no `role="alert"` (`packages.php:17–22`) | Adopt the alert anatomy + `role="alert"`. Production's per-registry loop is correct — keep it (a site may have several registries). | low |
| 9 | Stale-alert wording | copy | "(expired 2026-07-29 04:00 UTC)" only | Also renders "never fetched" when `snapshot_expires_at === null` (`packages.php:20`) | Keep production's `never fetched` branch — the design never modeled a registry with no snapshot at all. Design's alert body otherwise adopted verbatim. | low |
| 10 | Catalogue table columns | copy | Package / Type / Install / Trust class / Latest / Compatibility / Advisory / sr-only Actions (:59–66) | Identical set, sr-only header reads "Details" not "Actions" (`packages.php:32`) | Rename the sr-only header to "Actions". | low |
| 11 | Catalogue package cell | copy | `<strong>name</strong>` block, `<code>uid</code>`, then a **block** faint line "via {registry} · {publisher}" (:72–74) | Same three facts but the "via …" span is inline `class="muted"` after `<br><code>` (`packages.php:38–40`) | Make the "via" line a block at `.82rem`/`--text-faint`. | low |
| 12 | Catalogue "Install" cell | copy | Brand-subtle pill for any install state; em-dash `—` when not installed (:78–79) | `<span class="muted">-</span>` (ASCII hyphen) / `<span class="pill">` (`packages.php:44–47`) | Use `—`; adopt the brand-subtle pill token. | low |
| 13 | Catalogue "Compatibility" | feature-added | `compatible` / `incompatible` only (:84–85) | Third state `n/a` when the package has no latest release (`packages.php:52`, fed by `RegistryCatalogService::overview()` — `compatible` is `null` when `$latest === null`) | Keep the `n/a` state; style it as the muted variant of the chip. | low |
| 14 | Catalogue "Incompatible" label | copy | `incompatible` (:85) | `incompatible with this core` (`packages.php:54`) | Shorten to `incompatible` (the design puts the core range on the detail page, where production already shows it at `package_detail.php:71`). | low |
| 15 | Catalogue "Advisory" cell | copy | Single rust chip `{{ p.advisoryText }}` else faint "none" (:88–89) | Two independent pills: `locally blocked` and `advisory_status`, plus muted "none" (`packages.php:57–59`) | Keep both signals (they are independent facts — blocklist is registry-independent), but render each in the design's rust chip; keep "none" at `--text-faint`. | low |
| 16 | Catalogue empty state | feature-added | none | "No packages yet. Pin a trust key, enable the registry, and run the refresh worker." (`packages.php:28`) | Keep. Style as the design's italic-muted empty paragraph (cf. :208, :387). | low |
| 17 | Catalogue row action | copy | Outlined `Details` button, right-aligned (:91) | Bare `<a href="/admin/packages/{id}">Details</a>` in `.action-cell` (`packages.php:61`) | Keep the `<a>`; give it the design's outlined-button skin. Route verified `App.php:2254`. | low |
| 18 | Catalogue table wrapper | feature-added | plain `overflow-x:auto` section (:56) | `<div class="table-scroll" tabindex="0" role="region" aria-label="Package catalogue">` (`packages.php:30`) | **Keep** — the focusable scroll region is an a11y remediation shipped under ADR 0021 ("a11y/label/scroll-region sweep"). Do not drop it for the design's bare `overflow-x`. | medium |
| 19 | Detail: back link | copy | Chevron + **Package catalogue** (:103) | none — you navigate back via `_nav` | Add the back affordance above the h2 on `package_detail.php`. | low |
| 20 | Detail: identity | copy | h2 `{{ det.name }}` (2 rem display) + mono uid beneath (:104–105) | h1 `{name}` in `admin-head`; uid buried as a **Provenance** table row "Package identity" (`package_detail.php:48`) | Promote the uid to the mono sub-line under the title; drop the "Package identity" row from Provenance. | low |
| 21 | Detail: section order | copy | Provenance → Installation → Releases → Permissions → History (:107–210) | Provenance → Releases → Installation (Permissions nested) → History → Advisories (`package_detail.php:44,57,83,218,240,265`) | Reorder to the design; lift **Permissions** out of the Installation card into its own peer card. | medium |
| 22 | Detail: Provenance anatomy | copy | `<dl>` in a raised card, gold-ink eyebrow h3, 4 rows (:108–116) | `<table class="audit">` with `<th scope="row">`, 5 rows (`package_detail.php:46–54`) | Convert to the `<dl>` + eyebrow anatomy; keep the `base_url` in "Pinned source" (`package_detail.php:49`) — design's `{{ det.source }}` is `registry + " (pinned)"`, production is strictly more informative. | low |
| 23 | Detail: Trust-class separator | copy | "`{{ det.trustClass }}` — trust is never implied by being listed" (:113) | "`{trust_class}`; trust is never implied by being listed" (`package_detail.php:51`) | Change `;` → ` — `. | low |
| 24 | Detail: Advisory status | feature-added | `{{ det.advisoryStatus }}` or "none recorded" (x-dc:607) | Appends " · locally blocked" when blocked (`package_detail.php:52`) | Keep the blocked suffix. | low |
| 25 | Detail: Installation (not installed) | copy | blurb + single **Install plan** button (:121–122) | Same blurb verbatim, plus a `<select name="release_id">` release picker (`package_detail.php:90–103`) | **Keep the release picker** — the design hardcodes `pkg.releases[0]` and cannot express "plan an older release". Style the select in the design idiom, keep the button label "Install plan". | low |
| 26 | Detail: uninstalled-retention notice | feature-added | none | `<span class="pill">Uninstalled</span> Retention ends {retain_until} UTC.` (`package_detail.php:87`) | Keep; render as the design's review-surface notice (cf. :132). | low |
| 27 | Detail: install facts | copy | `<dl>` grid State / Health / Version / Update policy (:125–130) | `<table class="audit">` State / Health / Version / **Digest** / **Pinned** / Update policy (`package_detail.php:105–114`) | Convert to the `<dl>` grid; **keep Digest and Pinned** as two more `<dl>` cells (design encodes "pinned" only in a button label, which loses the fact at a glance). | low |
| 28 | Detail: quarantine reason | feature-added | none | Health cell appends " · {quarantine_reason}" (`package_detail.php:108`); `stateLabel` includes `Quarantined` (`:13`) | Keep both. | low |
| 29 | Detail: pending-consent notice | copy | Review-surface panel "N permissions await consent. **Review consent**." (:132) | `<p class="field-error">` with the same sentence and link (`package_detail.php:117`) | Restyle as the design's `--surface-review` notice, not an error. Route verified `App.php:2257`. | low |
| 30 | Detail: pending pluralization | copy | "1 permission awaits consent." / "N permissions await consent." (x-dc:616) | Always plural: "1 permissions await consent." (`package_detail.php:117`) | Fix the singular. | low |
| 31 | Detail: staged-update notice + cancel | feature-added | none | "Staged, awaiting re-consent: {version}. Review and approve." + **Cancel staged update** form (`package_detail.php:119–125`; routes `App.php:2264`) | Keep; style as a review-surface notice with a ghost button. | low |
| 32 | Detail: action row | copy | Flat button row: **Enable · Disable · {{ pinLabel }} · {{ policyLabel }} · Uninstall** (:134–140) | `.form-grid` of six-to-nine separate `<form>`s (`package_detail.php:127–216`) | Adopt the single flex button row visually; each control stays its own POST form (CSRF + PE). Sizes/variants per design: Enable = primary sm, Disable/Pin = secondary sm, Policy/Uninstall = ghost sm. | medium |
| 33 | Detail: Enable reauth | feature-added | plain button, no password (:135) | `<label>Current password <input … required>` inside the enable form (`package_detail.php:131`); enforced at `PackageLifecycleService::enable` via `reauth->requirePassword` (`:256`) | **Keep the password.** Safety-critical. Render as the design's stacked password field inside a small disclosure or inline group. | high |
| 34 | Detail: Uninstall reauth | feature-added | plain ghost button (:139) | password field, `danger` button (`package_detail.php:211–215`) | **Keep.** | high |
| 35 | Detail: Enable pre-condition | feature-changed | Client-side: pending grants redirect to the consent view with an inline error (x-dc:666) | Server refuses: `PackagePolicyException('not_consented', 'Declared permissions are not consented yet; review and grant them first.')` (`PackageLifecycleService.php:261`), surfaced as a 422 re-render on the detail page (`AdminPackageLifecycleController.php:349`) | Production behavior wins. Present the refusal in the design's `role="alert"` idiom on the detail page; do **not** invent a redirect. | medium |
| 36 | Detail: update policy control | feature-changed | Ghost button cycling `Policy: manual ⇄ notify` (:138) | `<select name="policy">` + **Save policy** button (`package_detail.php:156–166`) | Keep the select+submit (a cycling button is a stateful JS control and breaks with JS off). Style it as a compact inline select in the action row. | low |
| 37 | Detail: Re-verify | feature-added | none | `Re-verify` form shown only when `state === 'quarantined'` (`package_detail.php:143–148`); `AdminPackageLifecycleController::reverify` :276 | Keep. Safety-critical recovery path. | medium |
| 38 | Detail: Update / target-release picker | feature-added | none | Update form with target-release select + password (`package_detail.php:168–188`); staged/applied branch in `AdminPackageLifecycleController::update` :173 | Keep. | medium |
| 39 | Detail: Rollback | feature-added | none | Rollback form over `$rollback_targets` (`package_detail.php:190–204`; `AdminPackagesController.php:50`) | Keep. | medium |
| 40 | Detail: Export | feature-added | none | **Export** POST → JSON attachment (`package_detail.php:206–209`; `AdminPackageLifecycleController::export` :257) | Keep. | low |
| 41 | Detail: Releases columns | copy | Version / Channel / Digest / Signed by / Core range / Local review (:150–155) | Version / Channel / Digest (sha256) / Signed by / **Review** / Core range / **Advisory** / Local review (`package_detail.php:61`) | Adopt the design's column order (Core range before Local review); **keep the Review and Advisory columns** — they are distinct per-release facts the design collapsed into the plan screen only. | low |
| 42 | Detail: Releases sub-heading | copy | h3 **Releases** + separate muted p "Immutable: any changed byte is a new release." (:146–147) | Single h2 "Releases (immutable: any changed byte is a new release)" (`package_detail.php:58`) | Split into heading + caption. | low |
| 43 | Detail: signed-by fallback | feature-added | mono key id only (:163) | `<span class="muted">snapshot-listed</span>` when `signed_key_id === null` (`package_detail.php:68`) | Keep. | low |
| 44 | Detail: blocked-release pill | feature-added | none | `<span class="pill">blocked</span>` beside the digest (`package_detail.php:67`) | Keep — registry-independent blocklist signal. | low |
| 45 | Detail: local-review control | feature-changed | Bare `<select>` with `onChange` auto-save, 3 options (:166–168) | `_package_review_form.php` — select + note textarea + **required** password + **Record decision** button, one form per release row (`:4–21`); reauth in `PackageReviewConsoleService::recordDecision` (`AdminPackageSecurityController.php:225`) | Design's presentation (compact in-row select) is adopted; **the note + password + explicit submit stay** (no-JS + reauth). Best fit: in-row select + a compact `<details>` carrying note/password/submit. | high |
| 46 | Detail: review select preselect | copy | `value="{{ r.review }}"` preselects the current decision (:166) | No `selected` attribute — always defaults to `approved` (`_package_review_form.php:9`) | Preselect the release's current `review_status`. Genuine defect: today the control misrepresents state. | medium |
| 47 | Detail: Permissions anatomy | copy | Ruled `<ul>` — label + mono id, risk, `granted`/`pending` chip (:179–191) | `<table class="audit">` — Permission / Risk / Granted, values `yes`/`pending` (`package_detail.php:223–234`) | Convert to the ruled list + chips; `yes` → `granted`. | low |
| 48 | Detail: Permissions empty state | feature-added | none | "No permissions declared." (`package_detail.php:220`) | Keep. | low |
| 49 | Detail: History anatomy | copy | Ruled `<ul>` — event / digest / right-aligned `when` / detail line (:196–207) | `<table class="audit">` Event / Versions / Digest / Stage / Detail / When (`package_detail.php:246–260`) | Convert to the ruled list; fold **Versions** ("a -> b") and **Stage** into the detail line so no fact is lost. | medium |
| 50 | Detail: Advisories card | feature-added | none on the detail view (design only lists advisories under Registry trust) | Full per-package advisory table Advisory / Severity / Action / Affected / Acknowledged + empty state "No advisories recorded for this package." (`package_detail.php:265–287`) | **Keep.** Safety-critical: per-package advisory visibility at the point of decision. Style with the design's card + eyebrow. | medium |
| 51 | Detail: Integration panel | feature-added | none | `_package_integration.php` (145 lines): granted scopes/events/outbound hosts/data classes/jobs, settings form with secret fields, one-time credential reveal, credential rotate/revoke, provision, pause delivery, export (`package_detail.php:288–296`; routes `App.php:2271–2276`) | **Keep in full.** Largest single feature-added on this screen. Restyle to the design's card/eyebrow/table idiom; do not restructure the forms. | high |
| 52 | Integration: execution-disabled banner | feature-added | none | "Package execution is emergency-disabled site-wide. Credentials cannot authenticate and delivery is paused until an operator re-enables execution." (`_package_integration.php:34`) | Keep. Safety-critical cross-link to the brake. | medium |
| 53 | Plan: heading | copy | h2 "Install plan — {name} {version}" (x-dc:634), em dash | h1 "Install plan - {name} {version}" (`package_plan.php:11`), ASCII hyphen | Em dash; demote to h2 under the area h1. | low |
| 54 | Plan: fact list | copy | `<dl>` Version / Digest / Registry / Review (:221–226) | `<table class="audit">` Package / Version / Digest / Registry / Review / **Compatibility** (`package_plan.php:35–44`) | Convert to `<dl>`; drop the redundant Package row (it is in the title); **keep Compatibility**. | low |
| 55 | Plan: refusal + warnings | feature-added | single `planErr` line (:236) | Structured refusal `{code}: {message}` + "Matched local blocklist." suffix, plus a `$plan['warnings']` loop (`package_plan.php:21–30`) | Keep both; render as the design's `role="alert"` paragraph(s). | medium |
| 56 | Plan: permission-preview empty | feature-added | none | "No permissions declared." (`package_plan.php:50`) | Keep. | low |
| 57 | Plan: install CTA label | copy | **Install** (:242) | **Record install** + `Cancel` link + trailing reassurance p "Nothing runs yet: the next step asks you to review and consent…" (`package_plan.php:76–79`) | Adopt "Install" as the button label; **keep the Cancel link and the reassurance line** — both are real affordances the mock lacks. | low |
| 58 | Plan: gating | copy | Password field + button always rendered (:238–243) | Whole Install card suppressed when `refusal !== null` or already installed (`package_plan.php:68`) | Keep the production gating. | low |
| 59 | Consent: heading + blurb | copy | h2 "Consent to permissions" + "Granting is per-permission and audited. A package cannot be enabled while any grant is pending." (:253–254) | h1 "Consent to permissions", **no blurb** (`package_consent.php:12`) | Add the blurb verbatim. | low |
| 60 | Consent: pending list anatomy | copy | Ruled `<ul>` label + mono id + risk (:255–262) | `<table class="audit">` Permission / Risk (`package_consent.php:74–85`) | Convert to the ruled list. | low |
| 61 | Consent: staged-update mode | feature-added | none | Entire `$isUpdate` branch: h1 "Approve update to {v}", h2 "Permission changes", h3 **New permissions** / **Removed** / **Unchanged** with per-bucket empty states, hidden `staged_release_id`, intent `approve_update`, and the race guard "The staged update changed after this page was rendered; review it again before approving." (`package_consent.php:12,27–68,94–96`; `AdminPackageLifecycleController.php:91–97`) | **Keep in full.** Style the three buckets with the design's ruled lists + eyebrows. | high |
| 62 | Consent: CTA | copy | **Grant and continue** (:270) | **Grant and continue** + `Cancel — back to the package` link (`package_consent.php:100–101`) | Already matching; keep the Cancel link. | low |
| 63 | Security: brake status chip | feature-added | none | `disabled` / `live` pill in the h2 (`package_security.php:18`) | Keep; render as the design's done/review chip. | low |
| 64 | Security: brake blurb | copy | Design's released-state text ends "…for 2 integration installs. **The brake applies regardless of the package flag.**" (x-dc:704) | Production puts that sentence in the page intro instead (`package_security.php:14`) and omits it from the blurb (`:19`) | Move the sentence into the blurb; the design has no page-intro paragraph on this view. | low |
| 65 | Security: brake reauth | feature-added | Reason field only (:286) | Reason **and** a required `current_password` (`package_security.php:25–27`); enforced in `PackageSecurityResponseService::setExecutionDisabled` (`AdminPackageSecurityController.php:49`) | **Keep the password.** Safety-critical kill switch. | high |
| 66 | Security: brake error slots | feature-added | none | `field_error(...)` for `execution` and `current_password` (`package_security.php:20`) | Keep; style per the design's `role="alert"` line. | low |
| 67 | Security: brake button variant | copy | `variant="danger"` when releasing the brake; primary when resuming (:287–288) | `class="btn danger"` / `class="btn"` — same polarity (`package_security.php:28`) | Already matching; adopt the design's Button token skin. | low |
| 68 | Security: publisher row action | feature-changed | Inline `Suspend`/`Reinstate` danger text-button per row (:308) | `<a class="btn" href="/admin/packages/publishers/{id}">Manage</a>` → dedicated page (`package_security.php:43`) that carries **Suspend (with required reason)**, **Reinstate**, **Verify**, signing keys (pin/rotate/revoke), and review decisions (`package_publisher.php`) | **Keep the drill-in.** Suspension requires a reason + password (`package_publisher.php:25–27`) and force-disables installs; a one-click row action cannot carry that. Style the "Manage" cell as the design's right-aligned text action. | high |
| 69 | Security: publishers empty state | feature-added | none | "No publishers recorded yet." (`package_security.php:47`) | Keep. | low |
| 70 | Security: "Advisories & blocklist" card | copy | none — the tab strip makes Registry trust one click away | Count summary card + link (`package_security.php:54–57`) | Drop the card once the tab strip lands. | low |
| 71 | Security: transparency-log anatomy | copy | Ruled `<ul>` — mono `when` / mono `event` code / muted detail (:318–326) | `<table class="audit">` When / Event / Detail (`package_security.php:62–69`) | Convert to the ruled list. Consider adding an empty state (neither side has one). | low |
| 72 | Registry: page identity | copy | Second tab of "Packages & registries", no page intro beyond the trust paragraph | h1 "Registry trust &amp; security response" (`registries.php:8`) | Rename per #2; the "& security response" half is now the Packages-tab drill-in. | low |
| 73 | Registry: no-registries empty state | copy | none | Dedicated card "No registry sources are configured yet. Add one below — it starts disabled until you enable it with your password." (`registries.php:16–21`) | **Keep** the copy (it teaches the add flow) but render as the design's italic-muted empty paragraph rather than a full card. | low |
| 74 | Registry: card header | copy | h2 name + mono sourceId + enabled/disabled chip + **right-aligned toggle button** on the same row (:339–345) | h2 name + code + chip; the enable/disable form sits at the **bottom** of the card (`registries.php:25–26`, `:105–116`) | Move the toggle to the header row. Keep the two branches: disable takes no password, enable requires one (`AdminRegistryController::setEnabled` :73–78 passes `null` when disabling). | medium |
| 75 | Registry: toggle labels | copy | **Disable registry** / **Enable registry** (x-dc:723) | "Disable registry (no password)" / "Enable registry" (`registries.php:109,114`) | Drop the "(no password)" parenthetical from the label; the asymmetry is already evident from the absent field. | low |
| 76 | Registry: snapshot line | copy | "{base_url}. {snapshot}" where snapshot is "Last verified snapshot … UTC; expired … UTC." or "No verified snapshot yet." (x-dc:721) | Same composition; production says "expires" not "expired" (`registries.php:29`) | Production's "expires" is correct for a future timestamp — **keep production wording**; the design's "expired" is fixture-specific. | low |
| 77 | Registry: keys table | copy | Key id / Status / Window / Fingerprint / sr-only Actions (:349–353) | Identical columns (`registries.php:34`) | Adopt the design's cell typography only. | low |
| 78 | Registry: key status text | copy | "revoked — {reason}" / "no reason recorded" (x-dc:730) | "revoked - {revoked_reason}" ASCII hyphen, no fallback (`registries.php:39`) | Em dash + "no reason recorded" fallback. | low |
| 79 | Registry: key window | copy | "2026-01-01 → 2026-12-31" | "{valid_from|inf} to {valid_until|inf}" (`registries.php:40`) | Adopt the `→` separator; keep `inf` for open-ended bounds (the design never modeled them). | low |
| 80 | Registry: revoke control | feature-changed | Bare danger text-button **Revoke** (:363) | Inline form: **required** reason + **required** password + Revoke (`registries.php:44–51`) | Keep reason+password (`RegistryTrustService::revokeKey` reauths — `AdminRegistryController.php:138`). Present as a compact `<details>`-style inline group under the design's danger text-button. | high |
| 81 | Registry: revoked-key cell | copy | `<span style="color:var(--text-faint)">—</span>` (:364) | empty cell (`registries.php:52`) | Render the em dash. | low |
| 82 | Registry: pin a key | feature-added | none | `<details><summary>Pin a new public key</summary>` — key id / base64 public key / valid from / valid until / password (`registries.php:60–76`) | **Keep.** Safety-critical trust-pinning path. Style the disclosure in the design idiom. | high |
| 83 | Registry: signed rotation | feature-added | none | `<details><summary>Apply a signed key rotation</summary>` — envelope JSON + password (`registries.php:78–90`); `AdminRegistryController::rotate` :108 | **Keep.** | high |
| 84 | Registry: manual advisory ingest | feature-added | none | `<details><summary>Ingest a signed advisory manually</summary>` — envelope JSON + password (`registries.php:92–103`); `AdminRegistryController::ingestAdvisory` :151 | **Keep.** | high |
| 85 | Registry: add a source | feature-added | none | Card "Add a registry source" — source id / display name / base URL / password, button "Add registry (starts disabled)" (`registries.php:120–134`) | **Keep.** Without it a fresh install can never reach the design's populated state. | high |
| 86 | Registry: field-error wiring | constraint | n/a | `registries.php` renders field errors as bare `<p class="field-error">` (`:65,67,70,71,73,…`) and is **explicitly deferred** from the `field_error()`/`field_attrs()` helper wiring pending per-form id scoping — ADR 0023 §"Deep-admin field-error wiring residue" | Do not silently "fix" this as part of a visual pass. Either land the per-form scoping deliberately (and note it) or leave the deferral intact. | medium |
| 87 | Blocklist: anatomy | copy | Ruled `<ul>` — mono uid + muted reason, right-aligned accent **Remove** (:377–385) | `<table class="audit">` Digest / Package uid / Reason / Actions, with an inline **required** password and button "Remove (re-enables)" (`registries.php:139–155`) | Convert to the ruled list; **keep the digest** (fold into the mono line when `package_uid` is null) and **keep the password** — `LocalBlocklistService::unblock` reauths (`AdminRegistryController.php:215–219`). Button label → **Remove**. | high |
| 88 | Blocklist: empty state | copy | "Nothing is locally blocked." (:387) | "No local blocks. Blocking takes effect immediately, with or without a registry." (`registries.php:158`) | Adopt the design string — the "with or without a registry" fact is already in the tab intro (`registries.php:14`) verbatim. | low |
| 89 | Blocklist: add form | feature-added | none | digest / package_uid / reason, no password, button "Block now (no password)" (`registries.php:163–171`) | **Keep** — the deliberate no-reauth asymmetry (blocking is safe, unblocking is not) is a security design choice. Shorten the label to **Block now** and state the asymmetry in helper text. | high |
| 90 | Advisories: anatomy | copy | Ruled `<ul>` — mono uid + composed detail "{pkg} · {severity} · action: {action}", right-aligned **Acknowledge** or "acknowledged {date}" (:393–402) | `<table class="audit">` Advisory / Package / Severity / Action / Acknowledged / Actions (`registries.php:178–199`) | Convert to the ruled list with the composed detail line; carry the `unresolved` package fallback (`registries.php:184`) into that line. | low |
| 91 | Advisories: empty state | feature-added | none | "None ingested." (`registries.php:176`) | Keep; restyle as the italic-muted empty paragraph. | low |
| 92 | Extensions: intro | copy | Info callout, river/info tokens, with icon: "Server extension execution is controlled by the `server_extensions` flag, which is reserved and dark under Gate B (ADR 0018). This page is a read-only probe: handlers are listed, nothing runs." (:412–415) | Separate card h2 "Global emergency disable" + "Server extension execution is controlled by the server-side `server_extensions` feature flag. Turning it off leaves core forum routes independent of extension code." (`extensions.php:20–23`) | Replace the card with the design's info callout, using the design's wording. Both statements are true per ADR 0011. | low |
| 93 | Extensions: probe | copy | h3 eyebrow **Sandbox probe**; "**available** `wasm-runtime`" then muted "The runtime answers, but the flag is dark — no handler is dispatched." (:418–422) | h2 **Sandbox probe**; `available`/`unavailable` + adapter + optional `$probe['reason']` (`extensions.php:12–18`) | Adopt the eyebrow + typography. **Keep** the `unavailable` branch and the dynamic `reason` — ADR 0011 requires the failed probe to be reported ("Unsupported hosts fail closed: the admin page reports the failed probe"). | medium |
| 94 | Extensions: handlers columns | copy | Package / Handler / Entrypoint (:428–430) | Package / Handler / **Status** / Entrypoint (`extensions.php:29`) | **Keep the Status column** (handler quarantine state is operator-relevant); adopt the design's mono cell typography. | low |
| 95 | Extensions: handlers empty | feature-added | none | "No server extension handlers installed." (`extensions.php:39`) | Keep. | low |
| 96 | Extensions: run history | copy | Ruled `<ul>` — mono when / mono handler / `ok`(success) or `error`(danger) / muted detail, `—` when empty (:448–456) | `<table class="audit">` When / Handler / Status / Detail (`extensions.php:49–58`) | Convert to the ruled list with the coloured status token. | low |
| 97 | Extensions: run-history empty | feature-added | none | "No extension runs yet." (`extensions.php:59`) | Keep. | low |
| 98 | Nav placement of the area | constraint | AdminNav `ADMIN_AREAS` has **Packages** as its own top-level area, separate from **Integrations** | `_nav.php:37–44` puts Packages / Registry trust / Extensions inside the `Integrations` group alongside Webhooks / API tokens / Sign-in providers | This is an AdminNav-level IA change (grouped nav is an ADR 0023 #6 deliverable). Resolve it in the `admin-overview`/AdminNav slice, not here — but note that the design splits what ADR 0023 grouped. | medium |
| 99 | Publisher page: chrome | copy | no equivalent — publisher trust is a drill-in of the Packages tab (Security response) | `package_publisher.php:14` mounts `_nav` with `'active' => 'registries'` | When the tab strip lands, the publisher drill-in must highlight **Packages**, not Registry trust, to match the design's ownership (`goSecurity` is reached from the catalogue tab, x-dc:582). | low |
| 100 | Publisher page: verify action | feature-added | none | "Verify publisher" form, shown only while `verified_at === null` (`package_publisher.php:36–42`) | Keep. | medium |
| 101 | Publisher page: keys + review decisions | feature-added | none (design's publisher row shows status/verified only) | Full signing-key table + pin/rotate/revoke disclosures + "Packages & review decisions" card (`package_publisher.php:49–118`) | Keep; style with the design's card/eyebrow/table anatomy borrowed from Registry trust. | high |

### Feature-removed sweep

**None found.** Every element the design renders has a production implementation:

- catalogue table, stale alert, security link → `packages.php`
- provenance / installation / releases / permissions / history → `package_detail.php`
- plan + permission preview + reauth install → `package_plan.php`
- consent + pending grants + reauth grant → `package_consent.php`
- brake / publishers / transparency → `package_security.php`
- registries / keys / revoke / blocklist / advisories → `registries.php`
- probe / handlers / runs → `extensions.php` (flag-dark, `FeatureFlags.php:100`)

The design's `emergencyBrake`, `strictConsent`, `showTransparency` props (line 466) are storybook toggles, not product states, and need no production analogue.

---

## 3. Fiction-string table

Every fiction token in this file is **fixture data inside the `x-dc` block** (lines 486–553) except one. None of it ships. Listed for completeness so nothing is transcribed by accident.

| Design string | Where | Proposed production string |
|---|---|---|
| `community.imladris` | **markup**, line 53 (`<strong>`) | `<?= $e($registry['source_id']) ?>` — already dynamic at `packages.php:18` |
| `community.imladris` / `lorien.registry` | x-dc:487,501 (`registry`) | dynamic `registry_source_id` |
| `Council registry` / `Lórien mirror` | x-dc:524,530 (`displayName`) | dynamic `display_name` |
| `https://packages.imladris.council` / `https://registry.lorien.example` | x-dc:524,530 | dynamic `base_url` |
| `Imladris Council` / `imladris-council` | x-dc:487,512 | dynamic `publisher_name` / `publisher_uid` |
| `Lórien Works` / `lorien-works` | x-dc:501,513 | dynamic |
| `Orthanc Labs` / `orthanc-labs` | x-dc:505,514 | dynamic |
| `imladris/anti-abuse`, `imladris/digest`, `lorien/twilight-theme`, `orthanc/exporter` | x-dc:487–508, 536, 545–546 | dynamic `package_uid` |
| `Anti-abuse scanner`, `Daily digest`, `Twilight theme`, `Archive exporter` | x-dc:487–505 | dynamic `packages.name` |
| `imladris-2026-a`, `imladris-2025-b`, `lorien-2026-a`, `orthanc-2026-a` | x-dc:490–532 | dynamic `key_id` |
| `IML-2026-0031`, `IML-2026-0018` | x-dc:519,536,540–541 | dynamic `advisory_uid` |
| `@elrond` | x-dc:495,520,646,669,677,694,734 | actor display name from the audit row |

**Cross-cutting (not owned by this screen):** the AdminNav identity row still carries `Imladris` (wordmark) and `Back to the council`. Neutral proposals: the operator's site name from `settings`, and **"Back to the forum"**. Resolve in the AdminNav slice.

No lexicon fiction ("council", "wardens", "counsel", "regard", "commend", "the hall", "Third Age") appears in this screen's chrome.

---

## 4. State inventory

Design state (verbatim) → production equivalent.

### Flash / success strings

| Design state string (x-dc line) | Production equivalent | Verdict |
|---|---|---|
| `'Local review for ' + name + ' ' + version + ' recorded as ' + v + '.'` (627) | `'Local review decision recorded.'` — `AdminPackageSecurityController.php:234` | **copy** — enrich with package/version/decision |
| `'Installing is a reauthenticated action — confirm your password.'` (642) | `'Your current password is incorrect.'` — `ReauthGate.php:43` | **feature-changed** — production distinguishes "wrong" from "missing"; keep the honest message, adopt the design's register for a *blank* submit |
| `'This release declares a core range that excludes the running core. Installing it would fail closed.'` (643) | `{code}: {message}` from `PackagePolicyException`, rendered at `package_plan.php:23` | **copy** — adopt the design sentence as the human message for the incompatibility refusal, keeping the code prefix |
| `name + ' is installed. Nothing runs until consent is granted and it is enabled.'` (649) | `'Install recorded — review and grant the permissions below.'` — `AdminPackageLifecycleController.php:61` | **copy** — production's is arguably better (it tells you what to do next); merge: "{name} is installed. Nothing runs until you grant the permissions below and enable it." |
| `'Granting permissions is a reauthenticated action — confirm your password.'` (657) | `ReauthGate.php:43` | same as above |
| `'All permissions granted for ' + name + '. Each grant is audited.'` (661) | `'Permissions granted. Enable the package when you are ready.'` — `:107` | **copy** — name the package |
| `'Grant the pending permissions before enabling ' + name + '.'` (666) | `'not_consented: Declared permissions are not consented yet; review and grant them first.'` — `PackageLifecycleService.php:261` | **copy** — adopt the design sentence as the exception message |
| `name + ' is enabled.'` (672) | `'Package enabled.'` — `:125` | **copy** |
| `name + ' is disabled. Its install and grants are retained.'` (680) | `'Package disabled.'` — `:139` | **copy** |
| `name + ' pinned to ' + version + '.'` / `name + ' unpinned — updates may be offered again.'` (684) | `'Version pinned — updates will not be staged.'` / `'Version unpinned.'` — `:153` | **copy** — production's pinned message states the mechanism better; merge both |
| `'Update policy for ' + name + ' set to ' + next + '.'` (689) | `'Update policy saved.'` — `:169` | **copy** |
| `name + ' was uninstalled. Its provenance record is kept.'` (697) | `'Package uninstalled.'` — `:253` | **copy** |
| `'Emergency brake engaged — every package install is paused.'` / `'Package execution resumed.'` (706) | `'Package execution disabled: package-owned webhooks paused and credentials denied.'` / `'Package execution resumed.'` — `AdminPackageSecurityController.php:59–60` | **copy** on the resume string (identical); **keep production's** engage string — it names the actual consequence |
| `name + ' suspended — every install of its packages was force-disabled.'` (715) | `'Publisher suspended; ' + N + ' install(s) force-disabled.'` — `:125` | **copy** — production has the count; adopt the design's sentence shape around it |
| `name + ' reinstated — its packages stay disabled until you enable them individually.'` (714) | `'Publisher reinstated. Re-enable each install explicitly.'` — `:140` | **copy** |
| `name + ' disabled — catalogue reads are frozen; installs are untouched.'` / `name + ' enabled.'` (726) | `'Registry disabled.'` / `'Registry enabled.'` — `AdminRegistryController.php:79` | **copy** |
| `'Key ' + keyId + ' revoked. Releases signed under it no longer verify.'` (736) | `'Trust key revoked; everything it signed now fails closed.'` — `:144` | **copy** — merge: name the key, keep "fails closed" |
| `uid + ' removed from the blocklist — it can be installed again.'` (743) | `'Local block removed.'` — `:220` | **copy** |
| `uid + ' acknowledged — the acknowledgement is recorded against your account.'` (752) | `'Advisory acknowledged.'` — `:183` | **copy** |
| — | `'Registry added (disabled until you enable it).'` — `:59` | **feature-added** (add-registry flow) |
| — | `'Trust key pinned.'` — `:101`; `'Key rotation applied: successor pinned, old key retired.'` — `:123`; `'Advisory ingested (action: {a}).'` — `:167`; `'Local block added; it applies regardless of registry state.'` — `:202` | **feature-added** |
| — | `'Update staged — approve the permission changes below.'` / `'Package updated.'` / `'Rollback staged — …'` / `'Rollback applied.'` / `'Staged update discarded.'` / `'Update approved and applied.'` / `'Digest re-verified — the installed bytes match the review.'` — `AdminPackageLifecycleController.php:190,193,232,235,210,101,289` | **feature-added** |
| — | `'Publisher verified.'` / `'Publisher signing key pinned.'` / `'Publisher key rotation applied: successor pinned, old key retired.'` / the long publisher-key-revoke flash — `AdminPackageSecurityController.php:105,163,186,210` | **feature-added** |

### Empty states

| Design empty state | Production |
|---|---|
| "No lifecycle history recorded for this package." (:208) | identical — `package_detail.php:243` |
| "No pending grants." (:263) | identical — `package_consent.php:71` |
| "Nothing is locally blocked." (:387) | "No local blocks. Blocking takes effect immediately, with or without a registry." — `registries.php:158` |
| *(none for catalogue)* | "No packages yet. Pin a trust key, enable the registry, and run the refresh worker." — `packages.php:28` |
| *(none for publishers)* | "No publishers recorded yet." — `package_security.php:47` |
| *(none for registries)* | "No registry sources are configured yet. Add one below — it starts disabled until you enable it with your password." — `registries.php:19` |
| *(none for advisories)* | "None ingested." — `registries.php:176` / "No advisories recorded for this package." — `package_detail.php:268` |
| *(none for permissions)* | "No permissions declared." — `package_detail.php:220`, `package_plan.php:50` |
| *(none for handlers/runs)* | "No server extension handlers installed." / "No extension runs yet." — `extensions.php:39,59` |
| *(none for keys)* | "No signing keys pinned." — `package_publisher.php:73` |
| *(none for transparency log)* | *(none)* — **gap on both sides**; add one |

### Error / alert states

| Design | Production |
|---|---|
| `role="alert"` stale-snapshot block (:53) | `<p class="field-error">`, no role — `packages.php:18` |
| `role="alert"` `{{ planErrText }}` (:236) | `$errors` loop + refusal + warnings, no role — `package_plan.php:17–30` |
| `role="alert"` `{{ consentErrText }}` (:264) | `$errors` loop + staged refusal, no role — `package_consent.php:18–24` |
| *(none)* | 422 re-renders on **every** failed write, carrying `->errors` and `->old` — `AdminPackageLifecycleController.php:349,405,434`, `AdminRegistryController.php:61,81,103,125,146,169,185,204,223`, `AdminPackageSecurityController.php:63,107,127,142,165,188,212` | **feature-added / anti-draft-loss** — must survive any restructure |
| *(none)* | staged-update race guard: "The staged update changed after this page was rendered; review it again before approving." — `AdminPackageLifecycleController.php:95` | keep |
| *(none)* | re-verify failure: "Re-verification failed: the installed bytes still do not match the reviewed digest." — `:286` | keep |
| *(none)* | envelope parse: `'Paste the JSON envelope: {"document": "...", "signature": "<base64>", "key_id": "..."}'` — `AdminRegistryController.php:232`, `AdminPackageSecurityController.php:266` | keep |

### Loading / disabled / pending states

| Design | Production |
|---|---|
| `apkRise` 200 ms entrance animation on flash + drill-ins (:16, 102, 216, 250, 278) | none | **copy** — CSS-only, safe under CSP as an external class |
| `p.installed` / `p.notInstalled` chips (:78–79) | `installed_states` map — `packages.php:35,44–47` |
| `det.canEnable` / `det.canDisable` (:135–136) | `in_array($installedState, ['installed','disabled'])` / `=== 'enabled'` — `package_detail.php:128,136` |
| `pm.granted` / `pm.pending` chips (:187–188) | `yes` / `pending` — `package_detail.php:230` |
| `k.revocable` / `k.revoked` (:363–364) | `$key['status'] !== 'revoked'` — `registries.php:43` |
| `a.pending` / `a.acked` (:399–400) | `acknowledged_at === null` — `registries.php:189` |
| `reg.enabled` / `reg.disabled` chips (:342–343) | `(int) $reg['is_enabled'] === 1` — `registries.php:26` |
| `r.ok` / `r.failed` (:452–453) | `$run['status']` string — `extensions.php:55` |
| *(none)* | `quarantined` install state + re-verify affordance — `package_detail.php:13,143` | **feature-added** |
| *(none)* | `uninstalled` retention state — `package_detail.php:86–88` | **feature-added** |
| *(none)* | flag-dark 404 for the whole area when `package_registry` is rolled back — `gate()` in all five controllers | **constraint** |

### Counts / filters / sort

| Design | Production |
|---|---|
| `pending.length` in "N permissions await consent." (x-dc:616) | `$pendingCount` — `package_detail.php:25–30,117` |
| "2 integration installs" in the brake blurb (x-dc:703–704) | `$affected_installs` from `activeIntegrationInstalls()` — `PackageSecurityResponseService.php:140`, rendered `package_security.php:19` |
| *(no filters, no sort controls anywhere)* | none either; catalogue sorted `ORDER BY p.package_uid ASC` (`PackageRepository::catalog()` :59) | matched |
| *(none)* | `count($advisories)` / `count($blocklist)` summary — `package_security.php:56` | dropped with diff #70 |
| *(none)* | transparency log capped at 50 rows (`PackageSecurityResponseService.php:138`); run history capped at 25 (`AdminExtensionController.php:27`) | keep |

---

## 5. Slice proposal

Independently shippable and independently testable, ordered by dependency.

**S0 — AdminNav + flash chrome (prerequisite, owned elsewhere).**
Shared `AdminNav` bar and the design's `role="status"` flash banner. This screen consumes both. Blocked on the `admin-overview` slice. Note the IA tension in diff #98 before landing.

**S1 — Supply-chain tab strip + area title.**
Add `nav aria-label="Supply chain sections"` with three `<a href>` tabs to `packages.php`, `registries.php`, `extensions.php`; unify the h1 to "Packages & registries"; render the Extensions tab disabled when `server_extensions` is dark. Test: three integration tests asserting `aria-current="page"` per root, plus one asserting the Extensions tab is a non-link when the flag is off.

**S2 — Catalogue tab (`packages.php`).**
Diffs 6–18. Intro rewrite + the "Package security response →" affordance, `role="alert"` stale block, table anatomy, `n/a` compatibility state, `—` for not-installed, empty state kept, `table-scroll` region kept. Test: existing catalogue integration test + one asserting the empty-state copy.

**S3 — Package detail: section reorder + card anatomy.**
Diffs 19–24, 27, 41–44, 47, 49. Provenance→Installation→Releases→Permissions→History; `<dl>` cards; ruled lists for Permissions and History. Purely presentational — no route or form changes. Test: Playwright screenshot + one integration test asserting heading order.

**S4 — Package detail: action row + reauth preservation.**
Diffs 25–26, 28–40, 45–46. The flex button row over the existing separate forms, the release picker kept, all reauth fields kept, plus the real bug fix at #46 (preselect the current review decision). Test: unit-ish integration coverage that the review select renders `selected` on the current status; regression tests that enable/uninstall still 422 without a password.

**S5 — Plan + consent.**
Diffs 53–62. Includes preserving the whole staged-update branch and the race guard. Test: existing consent/update tests must stay green; add one asserting the new blurb renders on the plain-consent path.

**S6 — Security response (`package_security.php`) + publisher drill-in.**
Diffs 63–71, 99–101. Brake card restyle with the password kept, publishers table, transparency ruled list, drop the "Advisories & blocklist" pointer card, flip the publisher page's `_nav` active key to `packages`. Test: brake still refuses without a password; the publisher page highlights Packages.

**S7 — Registry trust tab (`registries.php`).**
Diffs 72–91. Header-row toggle, keys table typography, blocklist and advisories as ruled lists, all four disclosures (pin / rotate / ingest / add-registry) and both blocklist forms preserved. **Explicitly decide** the ADR 0023 field-error deferral (#86) in this slice rather than drifting into it. Test: reauth regression tests for revoke/unblock; a test that the no-password block-add still works.

**S8 — Extensions tab (`extensions.php`).**
Diffs 92–97. Info callout replaces the "Global emergency disable" card; ruled run-history list; Status column and both empty states kept; the `unavailable` probe branch kept per ADR 0011. Ships behind the dark flag, so it needs the flag flipped locally for evidence.

**S9 — Microcopy pass.**
The flash-string merges in §4. One commit, no structural change, easy to review and easy to revert. Do last so it does not collide with S2–S8.
