# Adversarial verification — `admin-features`

Verifier pass over `stage1/D-admin-features.md`. Every claim below was read from source.

**Overall:** the report's *ownership verdict is correct and independently confirmed*, its path:line
citations are accurate to within one line in ~60 checks, and none of its five `constraint`
classifications is aesthetic preference in disguise. But it has **four substantively wrong claims**,
**four misclassifications** (one of which silently overrides an authoritative spec section), and it
**never opened the test suite** — which is where most of the damage in its slice plan lives.

---

## 0. What holds up

- Ownership: `admin-features` is the sole design owner of feature flags + badge rules + custom
  emoji. Verified by exclusion — `grep -ln "emoji\|Emoji" */*.dc.html` and
  `grep -ln "Badge rules\|badge_rules" */*.dc.html` across all ten `templates/admin-*` return
  `admin-features` only. `PRODUCTION.md:47` does map badge rules here.
- Route verification: `src/Core/App.php:2303, 2315-2321, 2324-2327` — all correct.
- Flag inventory: 57 declared / 50 default-on / 7 default-off — correct
  (`FeatureFlags.php:26-104`, independently pinned by `AppAdminFeaturesTest.php:31-33`).
  `federation` and `analytics_export` are genuinely absent.
- Readiness data (#26): production wins, correctly called. Now double-pinned —
  `AppAdminFeaturesTest.php:95-96` and `tests/browser/admin-features.spec.ts:96-97`.
- Per-field emoji errors (#58): correctly protected. The guard has a name —
  `AppFieldErrorA11yTest::test_custom_emoji_owner_wires_each_validation_error` (:194).
- AdminNav fiction, checked by reference and confirmed: `components/admin/AdminNav.jsx:44-45,53`
  carries `backLabel = 'Back to the council'`, `modeLabel = 'Admin mode'`, wordmark `Imladris`.
- The report is right that the brief's `src/Controllers/` path does not exist (it is
  `src/Controller/`, singular).

---

## 1. Refuted claims

### R1 — "Ready for acceptance … has no production equivalent" (#27, fiction row 3) — FALSE

The report writes: *"production's `READINESS` map holds exactly five statuses and deliberately omits
any 'all clear' label"* and, in the fiction table, *"the whole 'Ready for acceptance' status is
design-only and is not being built."*

`Ready for acceptance` **was a shipped production readiness status.** It was retired by a binding
decision:

- `docs/adr/0022-group-dms-enablement.md:71` — the inventory's *"Ready for acceptance"* readiness
  category **retires with its last** row.
- `docs/evidence/deploy-dark-features.md:33` — *"…now **50/7**, and the *Ready for acceptance*
  readiness category retired with…"*
- `src/Controller/AdminFeatureController.php:55-58` says so in its own docblock.

And it is pinned by **two negative regression guards**:
- `tests/Integration/Admin/AppAdminFeaturesTest.php:89`
  `assertStringNotContainsString('Ready for acceptance', $body)`
- `tests/browser/admin-features.spec.ts:91-92`
  `expect(page.getByText('Ready for acceptance')).toHaveCount(0)`

The action ("do not build") is right; the reasoning and the `medium` risk are wrong. This is
**ADR-locked and test-guarded — risk HIGH**, and the ADR is not cited anywhere in the report. An
implementer told "no production concept" could reasonably conclude adding it is harmless.

### R2 — "Append the second sentence … accurate given `validStaticPath`" (#51) — FALSE

Design string: *"Assets are served from the media root; nothing is uploaded here."*

The justification is a non-sequitur — which path *shapes* are accepted says nothing about where the
bytes are served from — and the sentence is substantively wrong for the `/emoji/` branch:

- `CustomEmojiService::validStaticPath` (`:212-216`) accepts `^/emoji/…\.(png|webp)$` **or**
  `^/media/\d+$`.
- Only `/media/{id}` is a route (`src/Core/App.php:2030`, `MediaController::show`).
- There is **no `/emoji/{file}` route**, and `public/` contains only `assets/` and `index.php` —
  `public/emoji/` does not exist in the repo. `/emoji/*` resolves from the **web root**, and the
  operator has to create that directory by hand.

Shipping "served from the media root" points an operator at the wrong place for the path shape the
production placeholder itself suggests (`/emoji/party.webp`, `custom_emoji.php:41`). Reject the
sentence or rewrite it to name both roots honestly.

### R3 — "production requires lowercase" (#56) — FALSE

`CustomEmojiService.php:33` lowercases before validating:
`$shortcode = strtolower(trim((string) ($input['shortcode'] ?? '')));` — the regex at `:41`
(`/^[a-z0-9_+-]{2,40}$/`) therefore **never rejects uppercase**; it is silently normalized. The
template's client-side pattern is `[A-Za-z0-9_+\-]{2,40}` (`custom_emoji.php:31`), which also
permits uppercase.

So production's own error string — *"Use 2-40 lowercase letters, numbers, underscores, plus, or
hyphen."* (`:42`) — misdescribes its behavior at least as badly as the design's *"Shortcodes are
2–40 characters: letters, numbers, underscore, plus or hyphen."* "Production wins on all four" is
not established for the shortcode string; that one is an open copy question, not a settled win.
(The name-cap and asset-path strings *are* production wins — those parts stand.)

### R4 — "Render the lead using the already-computed `total`" (#46, S4) — UNSAFE

`BadgeRuleService::preview(int $ruleId, int $limit = 100)` (`:67`) returns
`'total' => count($users)` (`:71`) where `$users` is the **LIMITed page**. `total` is therefore
capped at 100 — it is not a total. Rendering *"{N} member(s) meet this rule today."* would state
`100` when 5,000 qualify. Worse, `backfill()` runs at `limit 1000` (`:96`), so preview already
under-reports what Backfill will do; surfacing a count makes that discrepancy operator-visible and
load-bearing for a destructive-ish bulk grant.

Second problem with the same line: `eligibleUsers` excludes members who already hold the badge
(`NOT EXISTS (SELECT 1 FROM user_badges …)`, `:167-170`). "meet this rule today" is the wrong
semantics; the correct register is the design's own empty-state verb — *would receive*.

If the lead ships at all it needs a real `COUNT(*)` and honest wording, e.g.
"N members would receive this badge" plus a "showing first 100" qualifier. The S4 test
*"lead count matches `total`"* would green-light the bug.

### R5 — citation errors (minor but load-bearing for reviewers)

- `templates/layout.php:69` for `$brand['name']` → actual **`:70`**.
- **"ADR 0023 §4"** (used three times) → there is no §4. It is bullet **4 of the "Shipped" list**,
  `docs/adr/0023-admin-console-audit-round-2.md:15`. And the per-field emoji error wiring is
  covered by **Shipped #5** (`:16`, the `field_error()`/`field_attrs()` rollout), not #4.

### R6 — "omits `governance`, `service_principals`, `verified_links`" (#17) — incomplete

True but misleading: the design omits **33** further declared flags (`mentions`,
`moderation_queue`, `community`, `oauth`, `presence`, `announcements`, `rich_composer`,
`wysiwyg_composer`, `drafts`, `uploads`, `anti_abuse`, `appeals`, `branding`, `seo`,
`product_tour`, `topic_workflow`, `tags`, `expanded_feeds`, `reputation_ledger`, `badge_rules`,
`content_references`, `polls`, `split_merge`, `profile_media`, `board_folders`,
`bookmark_folders`, `saved_feeds`, `custom_profile_fields`, `account_lifecycle`, `api_tokens`,
`webhooks`, `first_party_hooks`, `passkeys`…). The conclusion (take zero rows) is right.

---

## 2. Misclassified

### M1 — #3 Navigation: `copy` → **constraint**, and it silently overrides an authoritative spec

The report proposes *"Re-home all three under one 'features' area; owned by the shared AdminNav
slice"* at risk `medium`, and never mentions that production's grouped rail implements a spec:

- **`ADMIN.md` §9.2 "Console information architecture — Left-nav, grouped"** mandates exactly the
  eight sections production renders (Dashboard · Moderation · Content · People · Appearance ·
  Notifications · Integrations · Settings), and places **Custom CSS under Appearance** and
  **Roles/Users under People**.
- **`docs/adr/0023-admin-console-audit-round-2.md:17` (Shipped #6)** records the grouped nav as a
  landed remediation *"per ADMIN §9.2."*
- `tests/Integration/Admin/AppAdminNavIaTest.php:31` pins the eight group labels.

Under the governing precedence chain, `ADMIN.md` outranks the design system. The design's flat
10-area tier **contradicts** it. That is a hard production constraint plus a binding prior decision
— not a "copy" difference an implementer may apply. It requires an explicitly recorded decision
(new ADR or DECISIONS entry) before any re-homing. **The report's own rule-7 check missed its own
finding.**

### M2 — #1 Page identity: `copy`/low → **feature-changed**/medium-high

The design is one client-tabbed document; production is three routes. The report never mentions
`<title>` even once, though all four templates set it (`features.php:2`, `badge_rules.php:4`,
`badge_rule_preview.php:4`, `custom_emoji.php:4`). Collapsing to one `<h1>Features & badges</h1>`
without a per-tab title decision gives three URLs an identical accessible page name.

It also breaks three live browser assertions in the repo's **only** CI workflow:
- `tests/browser/admin-features.spec.ts:83` — `getByRole('heading', { name: 'Feature flags' })`
- `tests/browser/gate-a.spec.ts:1084` — `getByRole('heading', { name: 'Badge rules' })`
- `tests/browser/gate-a.spec.ts:1112` — `getByRole('heading', { name: 'Badge rule preview' })`

Under the report's plan `Badge rule preview` disappears from the document entirely (h1 becomes
"Features & badges", h2 becomes the badge name). None of this is in slice S1's test list.

### M3 — #67 badge-rule flashes: `copy`/low → **feature-changed**, and the create flash must be rejected

The report missed the rule lifecycle entirely. `BadgeRepository::createRule` inserts
`is_enabled = 0` (`src/Repository/BadgeRepository.php:141-143`) — **production rules are created
disabled and award nothing until an explicit Enable and an explicit Backfill.** Pinned by
`gate-a.spec.ts:1106` (*"The new rule lists as Disabled (new rules start disabled)"*) and by
`AppAdminBadgeRulesTest` throughout (every test must POST `/enable` before `/backfill` produces an
award).

The design's `createRule` sets `enabled: true` (`AdminFeatures.dc.html:428`) and flashes
*"Rule created — {badge} awards at {rule} ≥ {n}."* Adopting that sentence tells an operator the
rule is live and awarding when it is inert. That is an accuracy regression on an audited grant
path, not a register change. Only the *disable/enable* flashes are safely adoptable; the create
flash must state the disabled state.

### M4 — #69 emoji save: the protected branch is right, the changed branch is untested

The report correctly ring-fences `Custom emoji replaced — :{code}: already existed.` (ADR 0023
Shipped #4). But it proposes changing the **create** branch `Custom emoji saved.` → `:{code}: added
to the catalogue.` That exact string is asserted verbatim in two browser specs:
- `tests/browser/a11y.spec.ts:487`
- `tests/browser/gate-a.spec.ts:727`

Slice S5's test list guards only the replace string. Same failure shape for `#67`:
`gate-a.spec.ts:1103` (`Badge rule created.`), `:1127` (`/Badge rule backfilled \d+ awards\./`),
`:1137` (`/Badge rule revoked \d+ awards\./`).

### M5 — #11 Recovery drill: `feature-removed` is the wrong bucket

Per the governing rubric, `feature-removed` means "record it as a gap." This is prototype
instrumentation (`toggleCorrupt`, `AdminFeatures.dc.html:393`) with no product meaning. Recording
it as a *gap* invites someone to build it later. Record it as design-only scaffolding, explicitly
out of scope. (The report's prose says the right thing; the label doesn't.)

---

## 3. Missed differences and hazards

1. **`.sr-only` in a table needs a containing block** — `public/assets/app.css:3223-3227` carries a
   landed-bugfix comment: `.admin .table-scroll` is `position: relative` because otherwise
   absolutely-positioned `.sr-only` column headers *"escape this clip at the table's unscrolled
   x-offset, and stretch the page — mobile Chrome then zooms the whole layout viewport out."*
   The report's #60 (visually-hide the `Action` header) combined with the design's bare
   `overflow-x: auto` section (`design:250`, no `position: relative`) reintroduces exactly that
   bug. Production already has the primitive (`.sr-only`, `app.css:692`); the design's inline
   `position:absolute; clip:rect(0,0,0,0)` at `design:258` must not be copied. Classification:
   **constraint**, not the report's `copy`/low.

2. **`Override on`/`Override off` are not presentational** — `AppAdminFeaturesTest.php:44-57`
   (`test_console_override_column_matches_runtime_for_string_shapes`) asserts `Override off`
   present **and `Override on` absent** to prove a hand-written `{"passkeys":"false"}` reads as a
   rollback rather than `(bool)`-truthy on. Shortening to a bare `on`/`off` pill (#23) destroys the
   negative assertion — you cannot assert the absence of the substring `on` in an HTML body. Same
   for `Effective on` (#21, asserted at `:36`). Any shortening must land with a replacement
   assertion anchored on a stable class or `data-*`. This is a verification regression on a
   **fail-dark** invariant (`FeatureFlags::normalizeOverride`, `FeatureFlags.php:137-146`). Not in
   slice S2's test list.

3. **Legend relocation vs. the CI count** — `admin-features.spec.ts:110` asserts
   `page.locator('table .state').filter({ hasText: 'Reserved (ADR 0018)' })` has count **4**, with
   the comment *"(the fifth match is the legend copy in the pane intro)"*. The report's #10
   relocates the legend "below the tables" — it must stay **outside any `<table>`** or the count
   becomes 5. Also `expectAxeClean(page, info, '.admin-pane')` scopes the axe certification to
   `.admin-pane`; moving the h1/tab strip out of `.admin-pane` narrows what is certified.

4. **`.custom-emoji-panel` is a test anchor and an axe scope** — `a11y.spec.ts:476,478,488` and
   `gate-a.spec.ts:718`. The two-column-grid change (#52) must keep the class *and* keep both the
   form and the catalogue inside it.

5. **Duplicate accessible name** — #59 proposes moving the catalogue's name onto the section as
   `aria-label="Custom emoji catalogue"`, but `custom_emoji.php:69` **already uses that exact
   string** as the `.table-scroll` region's `aria-label`. Two nested regions with an identical
   accessible name, inside an axe-scanned scope. Needs distinct names.

6. **`/admin/features` is admin-only but NOT feature-gated** —
   `AppAdminFeaturesTest.php:133-164` proves it, and asserts `href="/admin/features"` survives with
   all 57 flags off. Slice S1 must keep the Feature flags tab live in that state; its test list
   doesn't cover it.

7. **Flag-gate ordering asymmetry breaks a proposed S1 test** —
   `AdminBadgeRuleController::index` calls `requireEnabled()` **before** `requireAdmin()`
   (`:20-21`), so a guest hitting a dark `/admin/badge-rules` gets **404**.
   `AdminCustomEmojiController::requireEmojiAdmin` calls `requireAdmin()` **first** (`:68`) then the
   flag (`:69-71`), so a guest gets a **302 to /login**. S1's "the route still 404s; same for
   `custom_emoji`" will fail unauthenticated.

8. **Rule meta string** — design renders `rule_type ≥ threshold · #board-slug`
   (`AdminFeatures.dc.html:372`, `' · #' + r.board`); production renders
   `rule_type ≥ threshold · {board_name}` — no `#`, and the board **name**, not slug
   (`badge_rules.php:65`, `badge_rule_preview.php:16`). Copy difference, unlisted.

9. **Fiction table incompleteness** — `design:162` board options also include `introductions`;
   `design:336` badge catalogue also carries `Welcome`, `First Thread`, `Trusted Answerer`,
   `Problem Solver`, `Anniversary` as sample data.

10. **#15 overclaims parity** — the design's Overrides tile detail is the hardcoded
    `'1 unknown override'` while its own count is `2` (`design:296`); it has no plural handling at
    all. Production (`features.php:40`) is correct. "identical detail grammar incl. singular/plural"
    is wrong; production is simply better.

11. **The mirror's own `PRODUCTION.md` is stale in the same direction as the design** — its row
    *"Link previews · expanded files · group DMs · custom CSS | behind flags | **dark**"* still
    lists `group_dms` as dark although it graduated 2026-07-18 (ADR 0022,
    `FeatureFlags.php:54`). Corroborates #17: the design's flag data must not be trusted.

---

## 4. Safety / binding-decision sweep (task items 6 and 7)

| Check | Result |
|---|---|
| Confirmation dropped | No. Neither design nor production confirms Revoke awards / emoji disable; no regression either way. |
| Typed guard / re-auth dropped | No. None on this screen. (`/admin/themes/safe-mode` re-auth is a different screen.) |
| Audit write dropped | No. `BadgeRuleService::audit()` (`:238-247`) writes `badge_rule.{create,enable,disable,backfill,revoke}` on every mutation; nothing proposed touches it. |
| Kill switch dropped | No — **but** the read-only posture is the kill-switch story. Nothing proposed adds a toggle; the design agrees (`design:47`). Hold that line. |
| Reveal-once state | N/A. |
| CSRF preserved | Yes — #41, #54, #64 all keep the POST forms + `csrfField()`. |
| Anti-draft-loss (rule 7) | Preserved by #37 and #58; both now have named guards (`AppFieldErrorA11yTest:50`, `:194`). |
| **ADR 0021 §7** (`link_previews` = Missing admin operations) | Protected by #26. Correct. |
| **ADR 0022** (`Ready for acceptance` retired) | **Not cited anywhere.** See R1. |
| **ADR 0023 Shipped #4** (honest emoji replace copy) | Protected by #69. Correct. |
| **ADR 0023 Shipped #5** (field-error helpers) | Protected by #58/#37, but miscited as "§4". |
| **ADR 0023 Shipped #6 + ADMIN §9.2** (grouped admin nav) | **Silently overridden by #3.** See M1. |

---

## 5. Verdict

**Directionally sound, not safe to execute as written.**

The difference inventory is thorough and the high-risk calls (#16, #17, #26, #58, #69) are correct.
But four claims are false (R1–R4), one proposal silently overrides `ADMIN.md` §9.2 + ADR 0023 #6
(M1), and the slice plan was written without opening `tests/` — so S1, S2, S3, S5 each propose
changes that break assertions in the repo's only CI workflow without listing them.

**Required before this becomes an implementation plan:**
1. Fix R1 (cite ADR 0022; raise to risk HIGH), R2 (drop or rewrite the media-root sentence),
   R3 (reopen the shortcode string), R4 (do not render `total` as a total).
2. Escalate #3 from "copy" to a recorded spec conflict; it cannot ship inside a restyle slice.
3. Reject the design's badge-rule **create** flash outright (M3 — rules are created disabled).
4. Add the `position: relative` / `.sr-only` constraint (missed #1) to the `Action` header item.
5. Rewrite every slice's test list against the four specs that actually pin this screen:
   `AppAdminFeaturesTest`, `AppAdminBadgeRulesTest`, `AppFieldErrorA11yTest`,
   `tests/browser/admin-features.spec.ts`, `tests/browser/gate-a.spec.ts`, `tests/browser/a11y.spec.ts`.
