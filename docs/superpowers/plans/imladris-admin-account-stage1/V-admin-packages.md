# Stage 1 — Adversarial verification: `admin-packages`

**Peer report:** `.../stage1/D-admin-packages.md` (101 diff rows; counts copy 56 / feature-added 36 / feature-removed 0 / feature-changed 5 / constraint 4)
**Design source read in full:** `docs/design-system/imladris/templates/admin-packages/AdminPackages.dc.html` (765 lines — markup 1–465, `x-dc` 466–762)
**Production read in full:** the 9 templates, `templates/admin/extensions.php`, `templates/admin/_nav.php`, `templates/partials/flash.php`, and all 6 controllers.

## Verdict

**Sound but incomplete.** The citation discipline is unusually good — I spot-checked ~60 `path:line` claims and all but four resolve exactly, including hard ones (`PackageLifecycleService.php:256/:261`, `ReauthGate.php:43`, `PackageSecurityResponseService.php:138/:140`, `RegistryCatalogService.php:53`, `PackageRepository` `ORDER BY p.package_uid ASC` at `:59`, `_nav.php:38,39,43` and `:80–84`, `FeatureFlags.php:100`). Every reauth, kill switch, confirmation, race guard and reveal-once state on this screen is preserved by the proposed actions — I found **no safety regression** in diffs #33/#34/#37/#45/#51/#61/#65/#68/#80/#82–85/#87/#89/#100/#101.

What it gets wrong is at the edges: **one false "feature-removed: none found"** (the Extensions-tab-while-dark state), **an un-shippable copy adoption built on it**, **three production-only elements mislabelled `copy` and slated for deletion** (two of which lose real facts, one on a reauth-gated install confirmation), and **~10 unlisted differences**, mostly empty/fallback states and the design's two-column card grid.

---

## 1. Refuted claims

### R1 — "Feature-removed sweep: **None found.**" (report §2, line 206) is false

The design renders the Extensions tab as a **live tab in every state** (`:33–34`, `offExtensions: s.view !== 'extensions'` at x-dc:585) and its body asserts the page is viewable *while the flag is dark*:

> `:414` — "Server extension execution is controlled by the `server_extensions` flag, which is **reserved and dark under Gate B (ADR 0018)**. This page is a read-only probe: handlers are listed, nothing runs."
> `:421` — "The runtime answers, but **the flag is dark** — no handler is dispatched."

Production does not implement that state. `src/Controller/AdminExtensionController.php:20-22`:

```php
if (!$this->container->get(FeatureFlags::class)->enabled('server_extensions')) {
    throw new NotFoundException('Not found.');
}
```

with `'server_extensions' => false` at `src/Core/FeatureFlags.php:100`. **`templates/admin/extensions.php` can only ever render while the flag is ON.** "A read-only extensions probe visible with the flag dark" is exactly the taxonomy's `feature-removed`: the design shows something production does not implement. The report's own #4 row is the evidence, but it is filed as `constraint` and the sweep still reports zero. The gap must be *recorded as a gap*, and the disabled-tab treatment #4 proposes is the mitigation, not the classification.

### R2 — #92: "Replace the card with the design's info callout, **using the design's wording**. Both statements are true per ADR 0011." — the wording is false in the only renderable state

Follows from R1. Ship `:414` verbatim and `/admin/extensions` tells the operator the flag "is reserved and dark" on a page that provably could not have rendered unless the flag was on. Same for the `:421` caption. This is not a style choice: it is a factual lie in production, so #92/#93's copy must be **rewritten**, not adopted.

The ADR citation is also load-bearing for the wrong proposition. `docs/adr/0011-public-plugin-runtime-scope.md:33` says "…closed: **the admin page reports the failed probe** and the worker leaves jobs" — that supports #93's "keep the `unavailable` branch and the dynamic reason" (correct, and `extensions.php:14,17` implements it). It says nothing about the page being reachable while the flag is dark, which is what #92 needs.

### R3 — Header: "31 routes (`src/Core/App.php:2242–2286`, `:2312`)"

There are **43** route registrations in that span: 2242, 2244–2245, 2247–2253, 2254–2269, 2271–2276, 2277–2286, 2312. (The "9 templates + 5 controllers" half of the same sentence is correct.)

### R4 — Headline table: the emergency-brake blurb is **not** "verbatim identical"

The report lists "Emergency-brake blurb (both engaged/released variants) | lines 702–704 | `package_security.php:19`" under "Whole paragraphs are already **verbatim identical**". They differ:

| | Design (x-dc:703/704) | Production (`package_security.php:19`) |
|---|---|---|
| engaged | "…: 2 integration installs **are paused**." | "…: `N` integration **install(s) paused**." |
| released | "…live for 2 integration **installs**. The brake applies regardless of the package flag." | "…live for `N` integration **install(s)**." (trailing sentence lives in the page intro, `:14`) |

The copula and the `(s)` pluralisation are real copy diffs the report's own #64 half-acknowledges. Nine of the ten headline rows do check out verbatim.

### R5 — Four wrong line cites

| Report says | Actual |
|---|---|
| #35: `not_consented` "surfaced as a 422 re-render on the detail page (`AdminPackageLifecycleController.php:349`)" | `:349` is the **`ValidationException`** catch. `PackagePolicyException` lands at **`:351`** (`return $this->detailView($packageId, [$errorKey => $this->policyMessage($e)], 422);`). The behavioral claim is right; the anchor points at the wrong catch. |
| §4: `'Package uninstalled.'` — `:253` | `:252` |
| §4 error table: `AdminRegistryController.php:…,223` | `:222` |
| #86: "ADR 0023 §'Deep-admin field-error wiring residue'" | It is **deferral item 4** in `docs/adr/0023-admin-console-audit-round-2.md:30`, not a titled section. Substance confirmed: "`registries.php` and `role_edit.php` render field errors legibly but are not yet wired to their inputs via the new helpers (their duplicated error keys need per-form scoping first to avoid duplicate element ids)." |

---

## 2. Misclassified

### M1 — #70 "Security: 'Advisories & blocklist' card": `copy` → **`feature-added`**

Classified `copy` with design column "none" and action "**Drop the card** once the tab strip lands." A production-only element deleted because the design lacks it is never `copy` — under the governing taxonomy it is `feature-added`, and feature-added is "**Keep it**; style it in the design idiom." The deletion is not cosmetic: `package_security.php:56` renders `<?= count($advisories) ?> advisory record(s), <?= count($blocklist) ?> local block(s).` — two live counts that exist nowhere else on the security console. The report's own §4 concedes it ("dropped with diff #70") without re-classifying.

### M2 — #54 "Plan: fact list": `copy` → **`feature-added`** on the dropped row (safety-adjacent)

Action: "drop the redundant Package row (it is in the title)". It is **not** in the title. `package_plan.php:11` is `Install plan - {name} {version}`; the Package row at `:37` is `{name} <code>{package_uid}</code>` — the **only** place `package_uid` appears on the reauth-gated install confirmation. The design's plan `<dl>` (`:221–226`) never modelled the uid, so the row is feature-added and must survive. Dropping the canonical supply-chain identifier from the screen where an operator types their password to install is not a presentational change.

### M3 — #73 "Registry: no-registries empty state": `copy` → **`feature-added`** (internally inconsistent)

Design column reads "none"; production has `registries.php:16-21`. That is structurally identical to #16 (catalogue empty state), which the report classified `feature-added`, and to #69/#91/#95/#97, all `feature-added`. Only #73 got `copy`. The action ("keep the copy, restyle as the italic-muted empty paragraph") is the correct *feature-added* action, so this is a label error, not an analysis error.

### M4 — #86 "Registry: field-error wiring": `constraint` → not a diff row at all

Design column is literally "n/a". The rules define `constraint` as "cannot be copied verbatim because of a **hard production constraint** (CSP, progressive enhancement, feature flags, authz, escaping)". An ADR 0023 deferral pending per-form id scoping is none of those — it is a *binding prior decision*, which the rules enumerate separately (rule 8). The caution is correct and valuable; the classification inflates `constraint` from 3 to 4.

### M5 — #98 "Nav placement of the area": `constraint` → **`feature-changed`** (binding-decision conflict)

I verified both sides: `docs/design-system/imladris/components/admin/AdminNav.jsx:16` has `{ key: 'packages', label: 'Packages', … }` as a top-level `ADMIN_AREAS` entry, peer to `integrations` (`:15`); `_nav.php:37-44` nests Packages / Registry trust / Extensions inside `Integrations`. Same concept, different mechanics — and the reason not to flip it is ADR 0023 item 6, a binding decision, not CSP/PE/flags/authz/escaping.

### M6 — #24 "Detail: advisory status": `feature-added` → **`feature-changed`**

Design column reads "none". The design **does** model local blocking — it just conflates it into the advisory string. `x-dc:505`: `{ id: 4, name: 'Archive exporter', …, advisory: 'locally blocked' }`, which flows into `advisoryStatus: pkg.advisory || 'none recorded'` (x-dc:607) and the catalogue chip `advisoryText: p.advisory` (x-dc:600). Production keeps `advisory_status` and `blocked` as two independent facts and concatenates (`package_detail.php:52`). That is the same concept with different mechanics. (The report's #15 handles the catalogue side correctly; only #24 mislabels.)

---

## 3. Missed differences

### X1 — The design's detail page is a **two-column card grid**; production stacks

`:107` wraps Provenance ‖ Installation in `grid-template-columns: repeat(auto-fit, minmax(330px, 1fr))`, and `:176` does the same for Permissions ‖ History. Production renders four full-width `<section class="card">`s (`package_detail.php:44, 57, 83, 240`). #21 handles the *order* swap and #47/#49 the list anatomy, but the pairing — which is why the design's order is Provenance→Installation→Releases→Permissions→History in the first place — is never stated. Classification: **copy**.

### X2 — No back affordance on the security console; plan/consent back buttons are top-of-page in the design

The design renders a chevron back button on **four** views: detail `:103` ("Package catalogue"), plan `:217` (`{{ det.name }}`), consent `:251` (`{{ det.name }}`), security `:279` ("Package catalogue"). The report's own design section-order table lists all four, but only #19 (detail) becomes a diff row.
- `package_security.php` has **no** back affordance at all — unlisted gap.
- `package_plan.php:77` and `package_consent.php:101` have bottom-of-form `Cancel` links, not top-of-page back buttons; #57/#62 say "keep the Cancel link" without noting the design places the affordance above the title.
Classification: **copy** (×3).

### X3 — Catalogue "Latest" empty state

`packages.php:50` renders `<span class="muted">none stable</span>` when `$p['latest'] === null`; the design's `{{ p.latest }}` (`:82`) has no empty state. The report enumerated the Install em-dash (#12) and the Compatibility `n/a` (#13) from the same row but skipped this third one. Same row: `packages.php:40` also carries `?? 'local'` / `?? 'unknown publisher'` fallbacks the design never modelled. Classification: **feature-added**.

### X4 — Per-release compatibility pill inside the Core range cell

`package_detail.php:71-72` renders the core range **and** a `compatible`/`incompatible` pill in the same cell; the design's Core range cell (`:164`) is mono text only. #41 rewrites exactly this column ("adopt the design's column order (Core range before Local review)") without mentioning the pill, so a literal application drops the per-release compatibility signal. Classification: **feature-added**. Related unlisted fallbacks in the same neighbourhood: `package_detail.php:109` `<span class="muted">unknown</span>` for an unresolvable version, `:253` `<span class="muted">n/a</span>` for a null history digest.

### X5 — The design's label-less inputs would undo an ADR 0023 a11y fix

The design's brake input is placeholder-only with **no label**: `:286` `<input value="{{ brakeReason }}" … placeholder="Reason (optional)">`. Production ships visually-hidden labels for exactly these controls — `package_security.php:24` (`<label class="sr-only" for="brake-reason">`), `:26` (`for="brake-password"`), `registries.php:46,48` (revoke reason + password, id-scoped per key), `registries.php:150` (unblock password), plus `aria-label` on every `package_publisher.php` inline input. `docs/adr/0023-admin-console-audit-round-2.md:16` lists "**unlabeled password inputs**" among the fixed pockets. #65/#80/#87 correctly keep the password *fields* but never state that the sr-only labels must survive the restyle — the obvious failure mode of "adopt the design's compact inline group verbatim". Classification: **constraint** (a11y remediation under a binding decision).

### X6 — Stale-snapshot alert is scoped differently

Design: `staleSnapshot: s.registries.some((r) => r.sourceId === 'community.imladris' && **r.enabled**)` (x-dc:593) — the alert is suppressed for disabled registries. Production: `RegistryCatalogService::overview()` loops `$this->registries->all()` with **no enabled filter** and sets `$registry['fresh']` on every row (`src/Service/Registry/RegistryCatalogService.php:44-47`), and `packages.php:16-17` renders the alert for every `!$registry['fresh']`. Consequence: the moment you add a registry through `registries.php:120-134` ("starts disabled"), the catalogue shows a red `Stale snapshot: … never fetched` for a source you have deliberately not enabled. Neither #8 nor #9 mentions the gating. Classification: **feature-changed** (the `sourceId ===` half of the design predicate is fixture-bound; the `&& r.enabled` half is a real product rule).

### X7 — The review form has no old-value round-trip, and #45's `<details>` makes it worse

`AdminPackageSecurityController::reviewErrorView` (`:243-251`) re-renders `admin/package_detail` with `$detail + ['errors' => $errors]` and **no `old`**; `_package_review_form.php` reads no `$old` anywhere. A 422 therefore discards the operator's typed note *and* their chosen decision — an anti-draft-loss gap (governing rule 7) sitting next to the defect #46 already found, and worth folding into the same fix.
Compounding it: #45 proposes "in-row select plus a compact `<details>` carrying note/password/submit". `_package_review_form.php:8` (`<select … required>`) and `:18` (`<input type="password" … required>`) are both `required`; a `required` control inside a **closed** `<details>` is not focusable, so Chromium aborts the submit with a console-only "An invalid form control … is not focusable" and the operator sees nothing happen. If #45 lands, the `<details>` must be forced open whenever `$errors` is non-empty (and after X7's `old` round-trip, whenever a draft exists). Classification: **constraint** on the proposed mechanism.

### X8 — `$this->section('title', …)` inventory is untouched

Eight distinct `<title>` strings: `'Package catalogue'`, `'Package: {name}'`, `'Install plan: {name}'`, `'Consent: {name}'` / `'Approve update: {name}'`, `'Package security response'`, `'Publisher trust'`, `'Registry trust'`, `'Server extensions'`. #2 unifies five `h1`s to "Packages & registries" and never says what happens to the titles. Classification: **copy**.

### X9 — The design's AdminNav already carries "Admin mode"

`components/admin/AdminNav.jsx:45`: `modeLabel = 'Admin mode'`. So production's `<span class="pill pill-admin">Admin mode</span>` is not an invention — it is the same chip rendered **eight times** (once per page header) instead of once in the shared bar. #1 mentions the pill in its production column but never states its fate. Classification: **copy** (de-duplicate into AdminNav), and it belongs in the S0 note.

### X10 — #64 and #70 together strand the security console

#64 deletes the security page's intro (`package_security.php:14`), which also removes "Advisory ingest, acknowledgement, and the local blocklist live on the **registry trust console**." #70 deletes the card that carries the second pointer. Applied together, `/admin/packages/security` ends with **zero** links to `/admin/registries` and zero advisory/block counts, on the argument that "the tab strip makes Registry trust one click away" — but the security console is a *drill-in below* the Packages tab, not a tab, so a tab strip does not obviously restore the path. Additionally, #64's "move the sentence into the blurb" only works in the *released* state: the design's engaged blurb (x-dc:703) contains no "applies regardless of the package flag" sentence, so the fact vanishes whenever the brake is actually on.

---

## 4. Clean bills of health (checked, no finding)

- **Safety sweep:** every reauth (`enable`/`uninstall`/`install`/`consent`/brake/publisher suspend-reinstate-verify/key pin-rotate-revoke/registry create-enable/unblock/review) is retained by the proposed actions; the no-reauth asymmetries (`disable`, `pin`, `update-policy`, `cancelUpdate`, `ackAdvisory`, blocklist `block`) are correctly identified as deliberate (`AdminPackageLifecycleController` `simpleAction` vs `passwordAction`, `AdminRegistryController::setEnabled:73-78` passing `null` when disabling). The staged-update race guard (`:91-97`), the reveal-once credential block (`_package_integration.php:84-90`), the quarantine re-verify path, rollback, export, and the execution-brake kill switch are all kept.
- **Binding decisions:** #18 (`table-scroll` focusable region) is genuinely ADR 0021 — `docs/adr/0021-…:36` "a11y/label/scroll-region sweep". #1/#98 correctly refuse to collapse the ADR 0023 item-6 grouped drawer here. #86's deferral is real (ADR 0023 item 4).
- **Route verification:** every href the report cites resolves in `buildRouter()` — `/admin/packages/security` (2244), `/admin/packages/{id}` (2254), `/admin/packages/{id}/consent` GET (2257), `/admin/packages/{id}/update/cancel` (2264), `/admin/packages/{id}/review` (2267), integration (2271–2276), registry trust (2277–2286), extensions (2312).
- **Fiction sweep:** correct. Every Imladris-lexicon token in this file lives in the `x-dc` fixture arrays (486–553); the only one reaching markup is `<strong>community.imladris</strong>` at `:53`, and `packages.php:18` already renders that position from `$e($registry['source_id'])`. `Imladris` / `Back to the council` are AdminNav-owned (`AdminNav.jsx:53,44`) and correctly deferred.
- **`x-dc` re-read for hidden anatomy:** `emergencyBrake` / `strictConsent` / `showTransparency` (`:466`) are storybook props with no product analogue — the report's dismissal holds. No feature-added row is refuted by script-only state except #24 (see M6).

## 5. Effect on the slice proposal

- **S8** must not ship the design's callout/probe copy verbatim (R1/R2) and must record the feature-removed gap; it also needs the flag flipped locally for evidence, which the report already notes.
- **S6** should keep the counts card content (M1) and re-check X10 before deleting the intro.
- **S5** must keep the `package_uid` row (M2) and add the plan/consent back affordances (X2).
- **S4** should absorb X7 (`old` round-trip + forced-open `<details>` on error) alongside #46.
- **S7** must carry the sr-only labels through the restyle (X5) and decide X6.
- **S3** should state the two-column grid (X1).
