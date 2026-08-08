# Adversarial verification — `admin-integrations` (D-admin-integrations.md)

**Verdict: ACCEPT WITH CORRECTIONS.** The peer's citation discipline is unusually good — I
re-read the full design file (600 lines, markup + `x-dc` script), all six production templates,
all four controllers, `WebhookService`, `ApiTokenService`, `IdentityProviderService`,
`ReauthGate`, `ApiScopes`, `WebhookEvents`, both migrations, `App.php` routing, `FeatureFlags`,
`config/config.php`, `app.css`, `imladris.css`, ADR 0021, ADR 0023, the round-2 plan, and the
existing test suites. **Roughly 60 of ~63 verifiable path:line citations are exact.** No
fabricated controller behavior. No aesthetic preference dressed as a constraint (all 5
`constraint` rows name a real CSP/PE/flag/anti-draft-loss mechanism).

But the report has **two systematic defects**:

1. **Both `feature-removed` rows are misclassified**, and one of them prescribes an action that
   directly violates the `feature-removed` rule.
2. **It is blind to the a11y scaffolding layer.** `.table-scroll` / `role="region"` /
   `tabindex="0"` / `aria-label` / the revoke `aria-label` appear **nowhere** in the report, yet
   they wrap every table in scope and are a shipped ADR 0023 §5 deliverable with an explicit
   round-2-plan line item for `provider_disable.php`. Row 72 and row 86 would silently revert them.

It is also blind to the **existing browser evidence**, which already asserts the exact headings and
flash strings it proposes to change.

---

## 0. Verified — the peer's headline findings hold

- **Extensions really is an `admin-packages` tab, not an integrations tab.** Independently
  confirmed: `docs/design-system/imladris/templates/admin-packages/AdminPackages.dc.html:33-34`
  renders an `Extensions` tab in its `nav aria-label="Supply chain sections"` strip, with the panel
  at `:409-…` and `goExtensions`/`showExtensions` at `:588,591`. The peer inferred this from peer
  context without opening the file; the inference is correct. "Build nothing here" stands.
- **The webhook-delete re-auth is binding.** `docs/adr/0021-…md:30` reads "…branding upload
  failures surfaced; **reauthed webhook delete**; distinct pause/resume flashes…" — line 30 exactly
  as cited. `WebhookService.php:142-146` carries the matching comment and `:147-152` the
  enforcement; the controller routes through `deleteConsole()` (`:160-181`), which returns the 422
  detail model with `'error_context' => 'delete'` at `:175`.
- **The grouped nav is an ADR 0023 §6 deliverable.** ADR 0023 "Shipped" item 6: "Console IA per
  ADMIN §9.2: grouped admin nav (Dashboard · Moderation · Content · People · Appearance ·
  Notifications · Integrations · Settings)". Eight groups, matching `_nav.php:7-50`. Deferring the
  nav to the `admin-overview` slice is correct.
- **`data-sole-count` is anchored.** ADR 0023 "Reclassified" section, verbatim; the anchor asserts
  at `tests/Integration/Admin/AppAdminProvidersTest.php:321-322,329-330`.
- **Every catalogue claim is exact.** `ApiScopes::SCOPES` = `read:boards`/`read:threads` only
  (`src/Security/ApiScopes.php:11-14`); `WebhookEvents::EVENTS` = the 11 listed events
  (`src/Security/WebhookEvents.php:11-23`); delivery enum `('queued','delivered','dead')` at
  `database/migrations/0057_phase5_webhooks.php:43`; `webhooks.max_attempts = 6` at
  `config/config.php:237`; `allow_http` env opt-in at `:241`; flags at
  `FeatureFlags.php:87` (`provider_registry`), `:94` (`api_tokens`), `:95` (`webhooks`),
  `:100` (`server_extensions` → false).
- **Fiction-string inventory is complete.** I grepped the design file case-insensitively for
  `council|warden|counsel|regard|commend|the hall|Third Age|Imladris|lorien|mellon|Haldir` — every
  hit is either an `ImladrisDesignSystem_c3e027.*` component namespace (not user-visible) or one of
  the demo-data strings the report already lists (lines 397, 411, 412). `AdminNav.jsx:44,45,53` are
  exact (`'Back to the council'`, `'Admin mode'`, `<span className="admin-bar-wordmark">Imladris</span>`).

*(Side note, not a report defect: the local mirror now holds **ten** `templates/admin-*` dirs, not
six as the pass brief states. The brief's staleness premise is itself stale.)*

---

## 1. REFUTED — factual errors

| # | Report claim | Why it is wrong |
|---|---|---|
| R1 | Row 84 / headline 1: Extensions "reached from the Integrations group at `templates/admin/_nav.php:42`" | `_nav.php:42` is **Sign-in providers**. Extensions is `_nav.php:43`. |
| R2 | Row 50 action: "keep `never checked` when `health_checked_at` is null" | Production **never emits `never checked`**. `providers.php:36` prints `$e($r['health_status'])` raw, and that column is `ENUM('unknown','ok','degraded','down')` (`database/migrations/0052_phase5_provider_registry.php:47`). The live evidence proves it: `tests/browser/providers.spec.ts:106` asserts `toContainText('unknown')` and `:111` asserts `toContainText('down')`. There is no "reachable", no "never checked", no "n/a" anywhere in production. |
| R3 | Row 24 / row 11 actions imply the em dash must be added to the scope/event rows | `templates/admin/api_tokens.php:41` **already** emits `<?= $e($scope) ?> — <?= $e($desc) ?>` with a true em dash. Only `<code>` is missing there. The ASCII-hyphen defect is confined to `webhooks.php:45`. |
| R4 | Row 26: the `service_secrets` gate message lands "on the `name` field" | True only for `register()` (`WebhookService.php:48` → `assertSecretStoreEnabled()` defaulting `$field = 'name'` at `:341`). `rotateSecret()` calls `assertSecretStoreEnabled('current_password')` at **`:79`**, so with the flag off the *rotate* form shows "Enable the service-secret store before creating **webhooks**." attached to a password input — copy that is wrong on that surface. The report's remedy ("a disabled-state notice above the form") does not cover the rotate path. |
| R5 | S1/S3/S4/S6 present `tests/browser/{api-tokens,webhooks,providers}.spec.ts` as the place to add Playwright evidence | All three **already exist** (`tests/browser/api-tokens.spec.ts`, `webhooks.spec.ts`, `providers.spec.ts`) and already assert the exact strings the report proposes changing. See M8. |

---

## 2. MISCLASSIFIED

### MC1 — Row 65 "Providers — table empty state": `feature-removed` → **not a deviation at all**

The report's own Design column reads "No empty branch" and its Production column reads "No empty
branch either". `feature-removed` is defined as *"the design shows something production does not
implement"*. The design shows **nothing**. Worse, the sanctioned action for `feature-removed` is
*"Do NOT build it and do NOT ship dead chrome"* — and the report's action is **"Add a muted empty
row in the design idiom."** The classification and the action contradict each other, and an
implementer following the table will build UI the design never modelled while believing they are
adopting it verbatim.

Correct disposition: drop from the difference table; record as an out-of-scope production
robustness note (same bucket as row 85).

### MC2 — Row 85 "Token expiry visibility": `feature-removed` → **out-of-scope production gap**

Same defect. The report's own Action column says *"Not a design deviation"* while the
Classification column says `feature-removed`. The finding itself is real and correctly evidenced
(`ApiTokenRepository.php:52` selects `expires_at`; `api_tokens.php:72` keys status only on
`revoked_at`, so an expired token still renders `active`) — it just is not one of the four
sanctioned deviations. It belongs in the deferral list it already appears in, not in the counts.

**Net effect: `feature_removed: 2` in the counts should be `0`.**

### MC3 — Row 50 "Providers — health cell": `copy` → **`feature-changed` + `copy`**

Filed as pure layout `copy`. It is two differences:
- *Vocabulary* (`feature-changed`): production's 4-value DB enum `unknown|ok|degraded|down` vs the
  design's 3 prose strings `reachable · 3h ago` / `never checked` / `n/a`. `degraded` has **no**
  design counterpart at all; `unknown` is what a builtin row renders where the design shows `n/a`.
  Production wins on behavior (it is a real enum written by `IdentityProviderRepository::updateHealth`),
  the design wins on register — but the mapping has to be authored, and the report never says so.
- *Composition* (`copy`): one `status · relative-time` string instead of two spans.

### MC4 — Rows 28 / 55: element-type is a `constraint`, not a styling `copy`

Row 28 ("render Manage as `.btn.btn-secondary.btn-small`") and row 55 ("restyle as a danger
link-button") both describe design controls that are `<button onClick>` in the design and must
remain `<a href>` in production for PE. Filed as `copy`, so the PE requirement is invisible.
Concretely: `tests/browser/webhooks.spec.ts:88,142` do
`getByRole('link', { name: 'Manage' })` and `providers.spec.ts:132` does
`getByRole('link', { name: 'Disable…' })`. Turning either into a `<button>` breaks both PE and the
evidence. Low severity, but it needs saying in the row, not left to inference.

### MC5 — Row 3 "Tab strip": `constraint` conflates two differences

The `onClick` → `<a href>` mechanism swap is a genuine CSP/PE `constraint` (correctly identified).
But *production has no tab strip at all* — that absence is a plain `copy` gap and is now hidden
inside a `constraint` row, so it does not appear in the `copy` count. Cosmetic bookkeeping issue.

---

## 3. MISSED DIFFERENCES

### M1 — The entire `.table-scroll` a11y layer is absent from the report (HIGH)

Every production table in scope is wrapped:

- `api_tokens.php:62` — `<div class="table-scroll" tabindex="0" role="region" aria-label="API tokens">`
- `webhooks.php:61` — `aria-label="Webhook endpoints"`
- `webhook_detail.php:83` — `aria-label="Recent webhook deliveries"`
- `providers.php:24` — `aria-label="Sign-in providers"`
- `provider_disable.php:29` — `aria-label="Sole sign-in accounts"`

The design has no equivalent — its tables sit in a bare `<section style="…overflow-x: auto;">`
(lines 87, 158, 212, 256). The word `table-scroll` does not appear once in
`D-admin-integrations.md`, and row 86's action ("translate every inline rule into
`public/assets/imladris.css` classes; the rendered result must be pixel-identical") would delete it.

This is load-bearing twice over:
- **ADR 0023 "Shipped" item 5** lists "table scopes/**regions**" among the enumerated a11y pockets
  fixed. ADR 0021 lists "a11y/label/**scroll-region** sweep".
- `public/assets/app.css:3217-3228` gives `.admin .table-scroll` a `position: relative` with a
  comment that without it, the absolutely-positioned `.sr-only` column headers "escape this clip at
  the table's unscrolled x-offset, and stretch the page — mobile Chrome then zooms the whole layout
  viewport out." The design's sr-only `<th>` spans (lines 95, 165) use exactly that
  `position:absolute; clip:rect(0,0,0,0)` pattern, so copying the design's containerless
  `overflow-x:auto` reintroduces the bug.

**Classification: `feature-added` — production has an a11y/layout affordance the design never
modelled. Keep it; the design's section becomes the outer card, the `.table-scroll` div stays
inside it.** Every axe assertion in `api-tokens.spec.ts:98,110,120`, `webhooks.spec.ts:123` and
`providers.spec.ts:82` runs against these pages.

### M2 — Row 72 silently reverts a named round-2 plan deliverable (HIGH)

`docs/superpowers/plans/2026-07-18-admin-audit-round2-remediation.md:426` reads, verbatim:

> `provider_disable.php:29-35` — wrap table in `<div class="table-scroll" role="region" aria-label="Sole-login accounts">` (pattern: `packages.php:30`) + `scope="col"`.

Row 72 proposes replacing that table wholesale with a `<ul>` of monogram rows and never mentions
the plan item. That deletes the region, the `aria-label`, and the `scope="col"` headers — a
deferral-adjacent revert of the exact kind rule 8 forbids. The `<ul>` anatomy is adoptable, but
only with an explicit note that this supersedes plan line 426 (and preferably with the list itself
given `role="region"`/`aria-label`).

### M3 — `aria-label="Revoke the ‹name› token"` (feature-added, unrecorded)

`api_tokens.php:77`:
`<button class="linkbtn danger" type="submit" aria-label="Revoke the <?= $e($t['name']) ?> token">Revoke</button>`.
The design's revoke is a bare `Revoke` text button (line 109) with no accessible name
disambiguation across rows — precisely the "differentiated row buttons" pocket ADR 0023 §5 closed
elsewhere. Row 17 covers only the em dash and never records this. It must survive the restyle.

### M4 — Health vocabulary (see MC3) — production emits raw DB enum tokens

Covered above; listing here because it is a *missing difference row*, not only a misclassification.

### M5 — `webhook_detail.php:92` ASCII hyphen

`<?= $e((string) ($d['response_status'] ?? '-')) ?>` — an ASCII `-` placeholder. The report catches
the identical defect at `webhooks.php:70` (row 29) and the empty-error cell (row 45) but misses
this one. Every design placeholder is `—`.

### M6 — Form-field anatomy is a markup change, not a CSS change

The design wraps every field label in a `<span>` carrying the uppercase micro-label
(`--font-label; font-size:.66rem; letter-spacing:.11em; text-transform:uppercase; color:var(--text-faint)`)
— lines 61, 74, 79, 131, 135, 149, 198, 202, 300, 305, 309, 315, 319, 355. Production emits **bare
text nodes** inside `<label>`: `api_tokens.php:33,46,51`, `webhooks.php:31,36,50`,
`webhook_detail.php:28,32,63,73`, `providers.php:85,93,98,105,110,116,122`,
`provider_disable.php:48`. A CSS class cannot style a bare text node — every one of these labels
needs a `<span>` inserted. Row 86's "translate every inline rule into CSS classes" understates the
work and hides a real `copy` difference.

### M7 — Placeholders

Design: `placeholder="Read-only mirror"` (62), `"Ops bridge"` (132), `"https://"` (136),
`"gitlab"` (301), `"GitLab"` (306). Production has only `placeholder="https://gitlab.com"`
(`providers.php:100`) and `placeholder='{"email":"upn"}'` (`providers.php:117`). The report
disposes of these in the *fiction-string* table ("keep verbatim") but never records
"production renders no placeholders" as a `copy` difference in the difference table, so the work
is unscheduled in the slice plan.

### M8 — The report's proposed string changes break existing, shipped browser evidence (HIGH)

Not one of these is mentioned:

| Proposed change | Evidence it breaks |
|---|---|
| Row 2: one `h1 Tokens, webhooks & sign-in` replacing three per-page `h1`s | `tests/browser/api-tokens.spec.ts:97` `getByRole('heading', { name: 'API tokens' })`; `webhooks.spec.ts:76,129` `heading 'Webhooks'`; `providers.spec.ts:94` `heading 'Sign-in providers'` |
| Row 69: collapse `h1 Disable ‹Name›` into `h2 Before you disable ‹Name›` | `providers.spec.ts:134` `getByRole('heading', { name: \`Disable ${label}\` })` |
| Row 83: `Webhook deleted.` → `Endpoint deleted — …` | `tests/browser/gate-a.spec.ts:1037` |
| Row 78: `Provider added (disabled). Run "Test connection", then enable it.` → name-interpolated | `providers.spec.ts:102` (asserts the string verbatim) |
| Row 80: `‹Name› is now offered at sign-in.` → `‹Name› is enabled and now offered on the sign-in page.` | `providers.spec.ts:117` |
| Row 50: recompose the health cell | `providers.spec.ts:106` `toContainText('unknown')`, `:111` `toContainText('down')` |
| Rows 28/55: restyle Manage / Disable… | `webhooks.spec.ts:88,142`, `providers.spec.ts:132` (role=link) |

A slice plan that changes these strings must update the specs in the same commit, or the
`browser-evidence.yml` workflow — the repo's only CI — goes red.

### M9 — `AdminApiTokenTest.php:54` depends on the `h2 Tokens` row 14 proposes dropping

`self::assertSeeText($second, 'Tokens');` — and `assertSeeText` is a raw-body
`assertStringContainsString` (`tests/Support/TestCase.php:439-442`). The only capital-`T`
`Tokens` on that page today is `<h2>Tokens</h2>` (`api_tokens.php:61`): the `h1` is `API tokens`,
the empty state is `No tokens yet.`, and `_nav.php:41`'s label is `API tokens` — all lowercase.
Dropping the `h2` **before** the `h1` becomes `Tokens, webhooks & sign-in` turns that test red.
S1 and S3 are ordered such that this is survivable, but the coupling is unstated.

### M10 — Row 23 / S5 ("render the register card on the detail page") has two unmodelled consequences

1. **Three password fields on one page.** The detail page already carries
   `<input type="password" name="current_password" autocomplete="current-password">` twice
   (`webhook_detail.php:64` rotate, `:74` delete). Adding the register form's
   (`webhooks.php:51`) makes three identically-named, identically-autocompleted inputs on one
   document. Password managers fill all three; the operator now has three ways to submit the wrong
   form. Not a server-side hole (each handler reauths independently) but a real usability/safety
   degradation the report does not weigh.
2. **The design's "only the right column swaps" is not reproducible without a controller change.**
   `AdminWebhookController::create()` renders `admin/webhooks` on the success path (`:58-64`) *and*
   on the `ValidationException` path (`:66-76`). A register submitted from `/admin/webhooks/{id}`
   therefore navigates the operator off the detail view in both outcomes. The report classifies the
   row `copy` and prescribes the markup without touching the controller.

### M11 — Rows 35/37 ("pair Save and Delete in one row") is not expressible as written

The delete is its own `<form method="post" action="…/delete">` with a required password
(`webhook_detail.php:71-78`). HTML forms cannot nest, so a single flex row containing a primary
`Save` (posting `/admin/webhooks/{id}`) and a danger `Delete endpoint` (posting
`/admin/webhooks/{id}/delete` with `current_password`) requires either `form="…"` attributes on the
buttons or a visual-only pairing of two sibling forms. The report says only "pair them per design,
respecting item 35's password field". The obvious naive implementation —
`<button formaction="…/delete">` inside the Save form — would post `/delete` with **no**
`current_password`, producing a permanent 422 dead end. Under-specified, high blast radius.

### M12 — Rows 34 / 54 "reveals the password confirm" is a PE + CSP hazard

Both actions describe a trigger that *reveals* a hidden password confirm. With JS off there is no
reveal, and CSP forbids inline handlers, so the confirm must be present and submittable in plain
HTML — i.e. the "reveal" can only ever be a `<details>`/CSS affordance or nothing. Row 54's target
is doubly sensitive: `providers.php:58-66`'s inline `Your password` field plus the
`enable_error_id` routing (`AdminProviderController.php:86-95`, with its explanatory comment) is an
ADR 0023 round-2 remediation. The report correctly says "keep both" — but "reveals" as the
prescribed presentation is the mechanism most likely to break it during implementation. Should be
restated as a `constraint`: the confirm renders inline, always, with JS off.

### M13 — `provider_disable.php:44` is a hand-rolled alert the report never maps

`<?php if (!empty($errors['provider'])): ?><p class="field-error" role="alert"><?= $e($errors['provider']) ?></p><?php endif; ?>`
— a hand-rolled paragraph that bypasses `field_error()`. The round-2 plan's Step-3 sweep lists
`provider_disable.php` as "(:42,49 field ones)", i.e. the *provider-level* error was deliberately
left un-helpered. Row 63 maps the design's `role="alert"` summary to `providers.php:20` only; the
disable page's equivalent is unmapped, so a restructure could quietly drop it. This is the sink for
`IdentityProviderService.php:131` ("Builtin providers are configured through environment variables,
not the console.") on the disable route.

---

## 4. SAFETY AUDIT of the proposed actions — clean, with two caveats

Checked every action against: dropped confirmations, typed guards, re-auth, audit writes, kill
switches, reveal-once states.

**Preserved correctly (credit where due):**
- Webhook delete re-auth + warning prose (row 35, ADR 0021).
- Webhook rotate re-auth (row 34, `WebhookService.php:79-80`).
- Provider enable re-auth + `enable_error_id` routing (row 54).
- Provider add-form re-auth (row 62) and disable-page re-auth (row 76).
- The disable **confirm-before-anything-changes** route (row 67, `AdminProviderController.php:17-24`).
- Reveal-once secret states — rows 6/7/8 keep the one-shot semantics; the reveal-once invariant is
  regression-tested at `AdminWebhookTest.php:34,37`.
- The 409 idempotency model (row 19) and the hidden `idempotency_key` round-trip.
- Anti-draft-loss on the provider add form (row 64) and the api-token 422 model.
- Audit writes — none of the proposed changes touch a `moderation_log` sink.
- `noindex` on every provider view (row 66).
- `data-sole-count` (row 49).

**Caveats:** M10.1 (three co-located password fields), M11 (`formaction` foot-gun), M12
(JS-dependent "reveal" of a re-auth field). None is a proposed *removal* of a guard — all three are
implementation hazards created by the proposed layout.

---

## 5. BINDING-DECISION AUDIT

| Decision | Report's handling |
|---|---|
| ADR 0021 — reauthed webhook delete | ✅ flagged binding, `:30` verified |
| ADR 0021 — distinct pause/resume flashes | ✅ production wins (row 39) |
| ADR 0023 §6 — grouped nav | ✅ deferred to `admin-overview`, not flattened |
| ADR 0023 — `data-sole-count` reclassification | ✅ kept |
| ADR 0023 §5 — `field_error`/`field_attrs` a11y wiring | ✅ kept (rows 20, 63) |
| **ADR 0023 §5 — table scopes/regions** | ❌ **never mentioned; rows 72 + 86 would revert it (M1, M2)** |
| Round-2 plan `:426` — `provider_disable.php` scroll region | ❌ **silently superseded by row 72 (M2)** |
| ADR 0021 — a11y/label/scroll-region sweep | ❌ same as above |
| TM-ID-09 clause 2 — confirm before change | ✅ kept (row 67) |
| ADR 0018 / reserved-dark `server_extensions` | ✅ correctly left alone (row 84) |

---

## 6. Required amendments before this diff drives implementation

1. Re-file rows 65 and 85 out of `feature-removed`; correct the counts to
   `copy 55+, feature_added 12+, feature_removed 0, feature_changed 14+, constraint 6+`.
2. Add a `feature-added` row for the `.table-scroll` / `role="region"` / `tabindex="0"` /
   `aria-label` wrapper on all five tables, marked **binding (ADR 0023 §5 + ADR 0021)**, and amend
   row 86 to say the translation must preserve it.
3. Amend row 72 to state that the `<ul>` conversion supersedes round-2 plan line 426 and must carry
   the region + accessible name forward, or drop the conversion.
4. Add a `feature-added` row for the revoke `aria-label` (`api_tokens.php:77`).
5. Split row 50 into a `feature-changed` (health vocabulary: `unknown|ok|degraded|down` vs
   `reachable|never checked|n/a`, `degraded` unmodelled) and a `copy` (single middot-joined string);
   delete the false "keep `never checked`" instruction.
6. Fix `_nav.php:42` → `:43`; fix row 26's field claim for the rotate path
   (`WebhookService.php:79`); drop the implied "add the em dash" for `api_tokens.php:41`.
7. Add a `copy` row for the missing `<span>` label wrappers (M6) and one for the missing
   placeholders (M7).
8. Add an evidence-impact section to the slice plan naming `tests/browser/api-tokens.spec.ts:97`,
   `webhooks.spec.ts:76,88,129,142`, `providers.spec.ts:94,102,106,111,117,132,134`,
   `gate-a.spec.ts:1037`, and `AdminApiTokenTest.php:54` as assertions the string/heading changes
   invalidate. Note that all three specs already exist.
9. Specify the mechanism for M11 (Save/Delete pairing) and restate M12 as a constraint: the re-auth
   confirm renders inline with JS off, never behind a JS disclosure.
10. Note M10's controller coupling before scheduling the detail-page register card.
