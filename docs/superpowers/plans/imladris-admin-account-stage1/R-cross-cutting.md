# R — Cross-cutting decision resolutions

Settles the twelve unassigned items from `S-synthesis.md` §1.5–§1.6. Every resolution below is
backed by a file I opened or a command I ran in this session; commands and their exact output are
quoted. Nothing is deferred.

Read in full first: `S-synthesis.md`, `S-admin-ia.md`, `F1-design-foundations.md`,
`F5-mirror-drift.md`.

---

## 0. A blocking live defect found while resolving item 1 — read this before anything else

**`composer build:imladris`, `composer check:imladris` and `composer verify:imladris` are all red
right now, on a clean checkout, before any Stage-2 edit.** The mid-pass DesignSync refresh
(F5 follow-up 3, "concurrent writer detected") rewrote `manifest.json` and
`production-contract.json` and broke three independent gate conditions in
`ImladrisAssetBuilder::expectedFiles()`.

```
$ php bin/build-imladris-assets.php --check
The imported Imladris manifest has unresolved parity gaps.
exit=1
```

| # | Gate | Builder line | HEAD | Working tree |
|---|---|---|---|---|
| 1 | `manifest.json → unresolved_gaps` must be `[]` | `:109-111` | `[]` | 3 entries (`templates/admin-*`, `ui_kits/mod`, `ui_kits/dm`) |
| 2 | `production-contract.json → reconciled_through_commit` must equal the baseline's | `:133-136` | `6d81da590a12bd09bb8d0e282c042aa03d755a94` | **key deleted** → `null` |
| 3 | every `production-contract.json → surface_specs` entry must be in `application_surface.files` | `:140-149` | `["USER.md","ADMIN.md","COMMUNITY.md","COMPOSER.md"]` | **key deleted** → throws "surface-spec contract is incomplete" |

Verified with `git show HEAD:docs/design-system/imladris/{manifest,production-contract}.json`.
The sync also dropped `surfaces_doc` and `contract_doc` from `production-contract.json`.

Note the three `unresolved_gaps` entries all name paths the builder **already excludes** from the
runtime closure (`templates`, `ui_kits` — `ImladrisAssetBuilder.php:215-222`), so none of them can
affect a single byte of generated CSS. They are a provenance regression, not a CSS regression.

`--print-application-digest` is unaffected — it reads `config/imladris-runtime-baseline.json`
directly via `applicationSurfaceDigest()` and never calls `expectedFiles()`:

```
$ php bin/build-imladris-assets.php --print-application-digest
f8a09441fadaef32a10332cf4c3fa51c6a694e72bd0a08c3ac6f3144bfe9249d      # == baseline, unchanged
```

**This must be repaired as commit 1 of Slice 1, before any slice runs.** See item 1 for the exact
sequence.

---

## 1. Where the `.admin-bar` / `.admin-tier` CSS comes from

### The mechanism, verified

`ImladrisAssetBuilder::expectedFiles()` sets `$sourceRoot = $this->path('docs/design-system/imladris')`
(`ImladrisAssetBuilder.php:106`) and reads exactly `CSS_SOURCES` (`:19-25`) from it:
`tokens/fonts.css`, `tokens/colors.css`, `tokens/typography.css`, `tokens/spacing.css`,
`components.css`.

**The builder reads `docs/design-system/imladris/`. It never reads `resources/imladris/` —
`resources/imladris/` is an OUTPUT** (`:159` `$expected['resources/imladris/' . $relative]`, and
`removeUnexpectedFiles()` at `:44` deletes anything there that is not expected). So F5 follow-up 1
is correct that `resources/imladris/components.css` is behind, but the remedy is not a copy — it is
a rebuild. Nothing is ever hand-copied into `resources/`.

Current drift, measured:

```
$ diff --strip-trailing-cr docs/design-system/imladris/components.css resources/imladris/components.css
324,343d323   # the whole .admin-bar / .admin-tier block
432,462d411   # .thread-list.is-board / .thread-row-activity
509c458       # composer provenance comment reworded
964,1018d912  # the .presence-widget block
```

And `grep -c` over the CSS tree:

| File | `admin-bar|admin-tier|presence-staff|presence-widget|thread-list.is-board` |
|---|---|
| `docs/design-system/imladris/components.css` | 28 |
| `resources/imladris/components.css` | **0** |
| `public/assets/imladris.css` | **0** |
| `public/assets/app.css` | 1 — and it is `.presence-widget` at `:751`, production's own unrelated sidebar rule |

### Decision

**Split ownership. The design-system primitives ship from the build; the application complement is
hand-authored in `app.css`.**

- `.admin-bar`, `.admin-bar-id`, `.admin-bar-brand`, `.admin-bar-wordmark`, `.admin-bar-exit`,
  `.admin-bar-mode`, `.admin-tier`, `.admin-tier::-webkit-scrollbar(-thumb)`, `.admin-tier-item`,
  `.admin-tier-item:hover`, `.admin-tier-item.is-active`
  (`docs/design-system/imladris/components.css:328-342`) **ship via `composer build:imladris`.**
  S-admin-ia §6.4 Step 1 is right. Crucially, none of these eleven class names exists in `app.css`
  (0 hits), so F1's §5 layering hazard does not apply — they are **uncontested**, and a layered
  uncontested rule renders exactly as authored. Re-authoring them in `app.css` would duplicate 15
  lines that the build already delivers and would drift the moment the mirror changes.
- Everything the `.dc.html` screens carry as inline `style=""` — `.admin-console` (per-area
  max-widths), `.admin-title`, `.admin-tabs` / `.admin-tab` / `.admin-tab.is-active` /
  `.admin-tab.is-disabled`, `.admin-tier-item.is-disabled`, `.admin-pane`, and every ≤860px rule —
  goes in **`public/assets/app.css`, unlayered** (S-admin-ia §6.4 Step 2; F1 §4.1(b), §5.4.1).
  These are application anatomy, are not in `components.css`, and cannot reach production through
  the builder because `templates/` and `ui_kits/` are excluded (`ImladrisAssetBuilder.php:215-222`).
- `.admin-tier-item.is-disabled` is application-only on purpose: `AdminNav.jsx` has no concept of a
  flag-dark area (S-admin-ia §6.2 departure 3), and `AppAdminNavIaTest:39-46` pins the disabled-span
  contract.

### Exact command sequence

```bash
# ── Commit 1 of Slice 1: repair the mirror contract (§0). A reviewed act, not a chore. ──
git checkout HEAD -- docs/design-system/imladris/manifest.json \
                     docs/design-system/imladris/production-contract.json
#   then hand-merge only genuinely new provenance from the sync back in, keeping
#   unresolved_gaps: [], reconciled_through_commit, surface_specs, surfaces_doc, contract_doc.
#   DO NOT bump reconciled_through_commit: ImladrisRuntimeAssetTest pins the literal
#   6d81da590a12bd09bb8d0e282c042aa03d755a94, so a bump also edits that test.
#   Record the decision in docs/design-system/imladris/LOCAL_RECONCILIATION.md.

# ── Commit 2: apply the .presence-staff correction in the mirror (item 2) ──

# ── Commit 3: regenerate. This is the whole delivery mechanism for the tier CSS. ──
composer build:imladris          # rewrites resources/imladris/** + public/assets/imladris.css
                                 #          + public/assets/fonts/imladris/**
composer check:imladris          # must print "Imladris runtime assets are current."

# ── Then Stage-2 slices edit templates/ + app.css + app.js ──

# ── Last commit before merge (item 4): refresh the digest ──
php bin/build-imladris-assets.php --print-application-digest
#   → paste hex into config/imladris-runtime-baseline.json → application_surface.sha256
composer check:imladris && composer verify:imladris
```

### What must be copied where

**Nothing.** `resources/imladris/components.css` and `public/assets/imladris.css` are both
regenerated from `docs/design-system/imladris/components.css` by step "Commit 3". Never hand-edit
either — `public/assets/imladris.css` carries the header "do not edit this file directly", and
`check:imladris` reports "Generated file is stale" on any manual write.

**Correcting `D-admin-integrations` row 86 / slice S1:** those instruct new CSS into
`public/assets/imladris.css`. That is the generated file. Redirect to `app.css`, as the other nine
D reports say.

**Standing caveat for the ADR:** `composer build:imladris` is *all-or-nothing per source file*. It
does not take rules; it takes whole files. Building to get `.admin-bar` also ships
`.thread-list.is-board`, `.thread-row-activity` and the entire `.presence-widget` block into
`@layer imladris.components`. All three are inert against current production markup
(production emits neither `.thread-list.is-board` nor `.presence-person`), so this is safe — but it
is the reason item 2 cannot be answered with "exclude it from the build".

---

## 2. `.presence-staff` and the reintroduced AA regression

### Is it even in scope for the admin/account migration? — **No.**

- `.presence-staff` belongs to the `.presence-widget` block, which renders at
  `templates/partials/sidebar.php:77` (`<section class="presence-widget" data-presence hidden
  aria-live="polite">`) — the **member forum rail**, not an admin or account surface.
- Under the design's console the admin surface stops rendering `partials/sidebar.php` altogether
  (S-admin-ia §1). Account panes keep the sidebar, but the migration does not touch it.
- Production does not render the staff tag at all today: `app.js:92-103` builds each roster row as
  `<li><a href="/u/…"><span class="dot"></span>NAME</a></li>` — no `.presence-person`, no
  `.monogram-sm`, no `.presence-staff`. So the rule is **inert against current markup**.

### Decision: **patch locally in the mirror, raise upstream, do not exclude from the build, do not assign it to a Stage-2 slice.**

Reasoning, in order:

1. **"Exclude from the build" is not available.** `CSS_SOURCES` takes whole files
   (`ImladrisAssetBuilder.php:19-25`); there is no rule-level filter. The only two runtime filters
   that exist are the fonts path rewrite and the reduced-motion excision (`runtimeCss()`, `:250-280`),
   and adding a third would require changing the builder and its guard test.
2. **"Raise upstream only" ships the regression.** The moment Slice 1's `composer build:imladris`
   runs (item 1), `.presence-staff` enters `public/assets/imladris.css` with
   `background: var(--gold-100); color: var(--gold-700);` — the exact pairing
   `LOCAL_RECONCILIATION.md:18-24` measured at **3.55:1 against a 4.5:1 requirement**, and which does
   not flip because `[data-theme="dark"]` remaps only the semantic aliases, never the numbered ramp
   (F1 §1.4).
3. **The precedent is already established and is the mirror's documented job.** `LOCAL_RECONCILIATION.md`
   states the mirror carries exactly these corrections "before runtime generation"; commit `8ffefce`
   ("fix: … flip the staff badge …") did precisely this for `.badge-staff`; and
   `tests/Unit/Core/ImladrisRuntimeAssetTest.php:185-186` pins the corrected form. F5 already
   re-applied the one `.badge-staff` line by hand for the same reason.

### The patch (one line, in `docs/design-system/imladris/components.css:993-997`)

```css
.presence-staff {
    flex: 0 0 auto; padding: 0 5px; border-radius: var(--radius-sm);
-   background: var(--gold-100); color: var(--gold-700);
+   background: var(--surface-staff); color: var(--on-staff);
    font-family: var(--font-label); font-size: .56rem; letter-spacing: .1em; text-transform: uppercase;
}
```

Identical substitution to the `.badge-staff` fix at `components.css:70`. Both tokens exist in both
registers (`tokens/colors.css:98` light, `:156` twilight), so no new token is created and
`test_every_required_runtime_variable_has_a_definition` stays green.

**Also do:** raise it in the live design project (`c3e02753-607c-40b6-994c-9ba1a65bb367`) so the
next sync does not revert it a third time, and append the row to `LOCAL_RECONCILIATION.md` in the
same commit. **Owner:** Slice 1, commit 2 — bundled with the mirror-contract repair, *not* an
admin/account slice, because it is mirror hygiene on a forum-shell component.

---

## 3. `--surface-staff` / `--on-staff` missing from `app.css`'s `[data-theme="system"]` dark block

### Confirmed by reading source. **Yes — this is a live defect today.**

| Fact | Source |
|---|---|
| `layout.php:4` defaults `$appearance['theme']` to `'system'`; `:19` stamps `data-theme="<theme>"` on `<html>` | `templates/layout.php` |
| `imladris.css` defines the pair at `:root` (`:142`, light) and `[data-theme="dark"]` (`:200`, twilight) | `public/assets/imladris.css` |
| `imladris.css` has **no** `@media (prefers-color-scheme: dark)` block at all — `grep -n 'data-theme\|prefers-color-scheme'` returns only `:167 [data-theme="dark"]` | `public/assets/imladris.css` |
| `app.css` has exactly two token blocks: `[data-theme="dark"]` at `:789-829` and `@media (prefers-color-scheme: dark) { [data-theme="system"] }` at `:830-872`. I read both in full. **Neither declares `--surface-staff` or `--on-staff`.** `grep -n "surface-staff\|on-staff\|badge-staff" public/assets/app.css` → only `:1571` (a comment) and `:1572` (the `.badge-staff` rule itself) | `public/assets/app.css` |
| `.badge-staff` is rendered live: `templates/partials/post.php:55` — `<?php if ($a['is_staff']): ?><span class="badge badge-staff">Staff</span><?php endif; ?>`, i.e. on every thread where an admin has posted | `templates/partials/post.php` |

**Consequence:** a user on the default theme (`system`) whose OS prefers dark gets a page where
`app.css`'s system block flips 43 tokens to twilight while `--surface-staff` still resolves through
`imladris.css`'s `:root` to `var(--gold-100)` and `--on-staff` to `var(--gold-800)` — the **light
register** chip painted on a `--twilight-800` surface. Chip-internal contrast is fine (~6.25:1); the
defect is the failure to flip, which is exactly the bug commit `8ffefce` claimed to fix. The fix
landed only on the explicit-`dark` path.

Nothing catches it: `test_status_ledger_pairs_are_defined_in_both_colour_registers` inspects
`imladris.css` only (F1 §4.6).

### Fix (Slice 1, item (ii) — the assignment already exists in S-synthesis §3; §1.5 item 3's "no slice owns it" is stale)

Add to **both** `app.css` blocks, so the two dark registers stop being asymmetric (43 vs 45) and no
future reader assumes the omission is deliberate:

```css
/* app.css :789 [data-theme="dark"]  and  :831 [data-theme="system"] — both */
--surface-staff: rgba(194,154,68,.16); --on-staff: var(--gold-200);
```

Values copied verbatim from `tokens/colors.css:156` — **not** new tokens, so
`test_application_css_does_not_redeclare_design_system_foundations` (which forbids re-declaring
foundations in `app.css`'s `:root`) is untouched: these are inside theme blocks, exactly as the
other 43 already are.

**Add the missing guard in the same commit:** a unit test asserting `app.css`'s `[data-theme="dark"]`
block and its `[data-theme="system"]` dark block declare an identical token set. This is the class
of bug that will otherwise recur every time a semantic token is added (F1 §5.4.3: three places, not
one).

---

## 4. The one rule for `config/imladris-runtime-baseline.json`

### Why a rule is mandatory rather than a convention

`digestApplicationSurface()` hashes the **whole** `templates/**` + `public/assets/**` tree
(`.php`/`.css`/`.js`) plus five named files (`ImladrisAssetBuilder.php:315-375`). The output is one
64-char hex on line 24 of a 26-line JSON file. It is therefore **not mergeable**: two branches each
produce a different hex, git conflicts on that line, and — the real trap — *resolving the conflict
by picking either side is wrong*, because the merged tree hashes to neither value. A "successfully
merged" baseline is silently incorrect and only surfaces as
`Production presentation changed after Imladris reconciliation…` from `expectedFiles()`, which
takes out `build`, `check` **and** `verify` together.

### THE RULE

> **`config/imladris-runtime-baseline.json` is refreshed exactly once per merge, on `main`, by
> whoever performs the merge, as the immediately-following commit. No slice branch may contain a
> change to that file.**

Mechanics:

| Question | Answer |
|---|---|
| **Who** | The author merging the slice — not the slice author writing it into their branch. |
| **When** | Immediately after the merge commit lands on `main`. Never mid-slice, never twice. |
| **Command** | `php bin/build-imladris-assets.php --print-application-digest` → paste the hex into `application_surface.sha256` → `composer check:imladris && composer verify:imladris` → commit as `chore: refresh imladris application baseline`. |
| **Collision avoidance** | Slices land with the baseline deliberately stale. Their branch is expected to fail `check:imladris`; the PR body must say so. Because the file is only ever written on `main`, by one author, at one point in time, there is no concurrent write and no merge conflict is possible. |
| **Proving green pre-merge** | A slice author may refresh locally to run `verify:imladris`, but **must revert the baseline hunk before pushing** (`git checkout -- config/imladris-runtime-baseline.json`). |
| **Never touch** | `reconciled_through_commit` (pinned to the literal `6d81da590a12bd09bb8d0e282c042aa03d755a94` by `ImladrisRuntimeAssetTest`) and `composer_contract` (must equal `production-contract.json → composer.spec`, currently `COMPOSER.md v0.8`). Only `application_surface.sha256` is refreshable. |
| **Accepted cost** | `main` fails `composer verify:imladris` for the length of one follow-up commit. Acceptable: `verify:imladris` is not in CI at all — `.github/workflows/browser-evidence.yml:85` runs only `npm run evidence`. Push the merge and the refresh together. |

`LOCAL_RECONCILIATION.md` calls the paste "an explicit design-contract review step, not an automatic
part of the asset build" — the rule preserves that: one reviewer, one act, one line in the PR body,
per merge.

---

## 5. ADR and plan-doc naming

`ls docs/adr/` → `0001…0023` present, `0015-number-skipped.md` is a documented skip. **Next free
number is 0024** (confirmed; the synthesis's claim was right).

`ls docs/superpowers/plans/` → the convention is `YYYY-MM-DD-<slug>.md`, most recently
`2026-08-03-thread-content-presentation-remediation.md`.

### Decision — one ADR, one plan doc, one owner

| Artefact | Path |
|---|---|
| ADR | `docs/adr/0024-imladris-admin-account-adoption.md` |
| Plan | `docs/superpowers/plans/2026-08-03-imladris-admin-account-adoption.md` |
| Local mirror deltas | appended to the existing `docs/design-system/imladris/LOCAL_RECONCILIATION.md` — **not** a new file |

Rules:

1. **Eleven proposed `0024-*.md` files collapse into the single ADR above.** Every per-screen
   decision becomes a numbered *section* of 0024, never its own ADR. This is what keeps DESIGN §13's
   "deferrals are never silently dropped" true — one ledger, not eleven.
2. **The `feature-removed` ledger is one table in 0024 §"Design-side sections with no production
   home"**, seeded from S-synthesis §4's list.
3. **Superseding is explicit, never silent.** 0024 supersedes *in part* the IA clause of ADR 0023
   (`:17`, grouped rail per ADMIN §9.2). 0023 gains a line "Superseded in part by ADR 0024 §N", and
   0024 states verbatim which 0023 findings survive: real Moderation entries reachable, the Appeals
   dashboard card, inbound links for the two orphan consoles, and deferrals #1–#4.
4. **No slice may open a second ADR.** If a slice discovers a decision that genuinely warrants its
   own ADR (e.g. a real `GET /admin/themes/{id}/activate` confirmation route,
   `D-admin-appearance`'s out-of-scope note), it is filed as `0025+` *after* 0024 lands, with 0024
   naming it as a forward reference.

---

## 6. `human_relative()` — add or reuse?

### What exists, read in full (`src/Support/helpers.php`, 159 lines)

`slugify`, `monogram_initials`, `monogram_class`, `mask_author`, **`human_datetime`** (`:64-76`,
returns `gmdate('M j, Y \a\t H:i', $ts) . ' UTC'`), **`human_date`** (`:78-87`,
`gmdate('M j, Y')`), `field_error`, `field_attrs`, **`human_duration`** (`:138-159`, a *duration*:
`"12 seconds"` / `"about 58 minutes"`). No relative formatter.

`grep -rn "ago\b" templates/ src/Support/ --include=*.php` → **zero hits**. `grep -n "ago"
public/assets/app.js` → **zero hits**. There is no `<time>` element in any admin template
(`grep -rn "<time" templates/admin/*.php` → none). **Production has no relative time anywhere, by
consistent practice.**

### Decision: **do not add `human_relative()`. Use `human_datetime()`.**

1. **One convention already exists and is universal.** `human_datetime()` is used in 20 templates
   (`admin/{announcements,audit,dashboard,email×4,extensions,invitations×2,providers,user_record×5}`,
   `account/{drafts,security,sessions}`, `appeals/index×3`, `mod/{appeals,reports,user×5}`,
   `dm/show×2`, `feed`, `notifications`, `partials/dm_{list,rail}`). Adding a second register for
   three screens fragments it and creates a "which one does this table use?" question forever after.
2. **Relative time is unverifiable on a server-rendered page.** It is computed once at render and is
   correct only at render; PE forbids requiring JS to re-tick it, and there is no `<time datetime>`
   scaffold to hydrate. On `/admin/audit` — an *accountability* surface — a stale "6 minutes ago" is
   worse than a correct absolute instant.
3. **The design's relative strings are sample data, not contract.** "Last run 6 minutes ago" lives
   in the `<script type="text/x-dc">` behaviour blocks, the same blocks that carry `Erestor` and
   `Europe / Rivendell` (F1 §3.3). Nobody proposes porting those.
4. `V-admin-settings` R4 already caught that `human_datetime()` cannot produce it, and
   `D-admin-members` #94 already refused it. Two of the three requesting screens are on the record
   against.

### If the ADR nevertheless authorises it (operator preference overrides me)

Then exactly one helper, in `src/Support/helpers.php`, with this shape:

```php
if (!function_exists('human_relative')) {
    /**
     * A recent instant, in words. Degrades to the absolute UTC string beyond 24h
     * and on any negative delta, so a stale render can never read as fresh.
     * Always render the absolute value alongside it (adjacent cell or title).
     */
    function human_relative(?string $utcDateTime, ?int $nowTs = null): string
    {
        if ($utcDateTime === null || $utcDateTime === '') { return ''; }
        $ts = strtotime($utcDateTime . ' UTC');          // same idiom as human_datetime
        if ($ts === false) { return ''; }
        $now   = $nowTs ?? time();                        // time() is a UTC epoch: TZ-independent
        $delta = $now - $ts;
        if ($delta < 0)      { return human_datetime($utcDateTime); }   // clock skew → absolute
        if ($delta < 60)     { return 'just now'; }
        if ($delta >= 86400) { return human_datetime($utcDateTime); }   // > 24h → absolute
        return human_duration($delta) . ' ago';                          // reuses the rounding rule
    }
}
```

**UTC handling, precisely:** parse with `strtotime($v . ' UTC')` — never bare `strtotime()`, never
`new DateTime($v)` without an explicit `UTC` zone, because the process timezone is not pinned.
Compare against `time()`, which is a UTC epoch and therefore also timezone-independent; never
round-trip through `gmdate()` to get "now". The `$nowTs` parameter exists solely so the unit test
can be deterministic.

Note `src/Support/helpers.php` is **outside** the baseline digest scope (roots are `templates` and
`public/assets`; the file list is the four spec docs + `FeatureFlags.php`), so adding it is cheap —
that is not a reason to add it.

---

## 7. Timestamp formatter conflict: `audit_datetime()` vs `human_datetime()`

### Decision: **`human_datetime()`. Reject `audit_datetime()`.** `D-admin-integrations` #15 is right; `D-admin-overview` S5 is wrong.

- `templates/admin/audit.php:80` already renders `human_datetime((string) $row['created_at'])`.
  A second, audit-only formatter would make `/admin/audit`'s timestamps disagree with
  `/admin/email`'s (`email.php:122,183`), `/admin/announcements`'s (`:62`),
  `/admin/invitations`'s (`:76,:84`) and `/admin/users/{id}`'s (`user_record.php` ×5) — pages that
  under the new IA sit two clicks apart inside the same console.
- The design's difference is **presentational, not textual**: `--font-mono`, `.78rem`,
  `--text-faint`, `white-space: nowrap` (S-synthesis §2 block 5). That is achievable entirely in
  `app.css` with zero PHP change. Presentation belongs in CSS; the string stays `human_datetime()`.
- `human_date()` stays where a date without a time is the honest granularity — `Joined`,
  `Last seen`, ban `lifted`/`until` (`user_record.php:42,43,292,294`, `users.php:139,140`). Do not
  collapse those into `human_datetime()`; a `last_seen_at` precise to the minute in a directory is
  a privacy regression, not a fidelity win.

**One formatter for every admin and account timestamp: `human_datetime()`.** Record in ADR 0024 §"n".

---

## 8. The eyebrow decision

### Confirmed by grep (`grep -rn 'class="eyebrow"' templates/admin/ templates/mod/`)

**Page-head eyebrows (inside `<header class="admin-head">` / `.mod-head`) — 12, all to be deleted:**

| Template:line | String |
|---|---|
| `templates/admin/audit.php:12` | `Accountability` |
| `templates/admin/branding.php:11` | `Operator desk` |
| `templates/admin/custom_emoji.php:12` | `Appearance` |
| `templates/admin/dashboard.php:6` | `Operator desk` |
| `templates/admin/features.php:6` | `Runtime controls` |
| `templates/admin/moderation.php:16` | `Moderation` |
| `templates/admin/settings.php:14` | `Operator desk` |
| `templates/admin/thread_intelligence.php:6` | `Operations` |
| `templates/mod/appeals.php:12` | `Warden's table` |
| `templates/mod/approvals.php:12` | `Warden's table` |
| `templates/mod/reports.php:18` | `Warden's table` |
| `templates/mod/user.php:27` | `Warden's table` |

Head shape verified by reading `dashboard.php:1-12`, `settings.php:10-20`, `moderation.php:12-22`:
each is `<header class="admin-head"><span><span class="eyebrow">X</span><h1>Y</h1></span><span
class="pill pill-admin">Admin mode</span></header>`.

**In-pane SECTION eyebrows — 5, all KEPT:** `dashboard.php:20` `Live operations`, `:41` `Triage`,
`:66` `Community pulse`, `:86` `Audit trail`; `branding.php:99` `Live preview`. These are inside
the pane, not the page head, and the design preserves them (`AdminOverview.dc.html` keeps
`Live operations`).

### Do any tests pin them? — **No.**

`grep -rn "Operator desk\|Accountability\|Runtime controls\|Warden's table\|Live operations\|Community pulse\|Audit trail\|Live preview\|eyebrow" tests/` returns 10 hits, none of which pins a page-head eyebrow:

| Hit | What it actually is | Impact |
|---|---|---|
| `tests/browser/admin-remediation.spec.ts:298` | `await expect(page.locator('body')).not.toContainText('Audit trail')` on **`/mod/u/{id}`** — a NEGATIVE assertion proving the scoped moderator panel has no audit trail | **None** on `dashboard.php:86`. But it means the string must never be introduced into `templates/mod/user.php`. |
| `tests/browser/imladris-forum-surfaces.spec.ts:127` | `.eyebrow` visible on a **board** page `/c/{slug}` | Out of scope |
| `tests/browser/group-dms.spec.ts:82,211` | `.dm-thread-eyebrow` | Different class, out of scope |
| `AppImladrisFidelityTest.php:53` | `auth-eyebrow` (auth stage) | Out of scope |
| `AppLeaderboardFidelityTest.php:50,55` | the leaderboard's "The council" eyebrow | Out of scope |
| `AppImladrisFidelityTest.php:265` / `AppImladrisFidelityHighImpactTest.php:13` | a board named "Audit trails"; an "In council" eyebrow over the participant stack | Out of scope |

### Decision: **DELETE all 12 page-head eyebrows. KEEP the 5 in-pane section eyebrows. Classification: `copy`.**

- The current design has **none**. `grep -rn "Operator desk" docs/design-system/imladris/` returns
  one hit — `components/admin/admin.card.html:43` — and it is the *obituary*: "the redundant
  'Operator desk · Area' kicker is gone, the mode pill moved into the identity row, and the heading
  drops from 2.4rem to 2.1rem." In every current `.dc.html` the `<h1>` is the first child after the
  `<x-import …AdminNav…>`.
- **This inverts `D-admin-people` C1, `D-admin-appearance` #2/#41, `D-admin-notifications` D3, and
  `D-admin-content` R1** (whose quoted eyebrow string is fabricated — S-synthesis §1.2). Those four
  rows must be rewritten as deletions before Stage 2 reads them.
- It is not aesthetic preference: the eyebrow's only information content is *which area you are in*,
  and that is precisely what the tier's active pill now carries. Keeping both is the duplication the
  design removed and named.
- **Bonus discharge:** the four `Warden's table` strings are *also* fiction (F1 §3.3, S-admin-ia
  §5.4). Deleting the eyebrow deletes them. Record that in ADR 0024 so Slice 19's fiction ledger does
  not double-count them and so nobody "de-fictions" a string that no longer exists.
- **Add a positive guard in the same slice** — an integration assertion that no `admin-head` /
  `mod-head` emits an `.eyebrow`. Without it, nothing pins the new state either, and the eight will
  creep back the next time a template is copy-pasted.
- Zero test edits required. The baseline-digest refresh (item 4) still applies, because
  `templates/**` changed.

---

## 9. `.pill-admin` blast radius and the scoping rule

### Confirmed by reading source

```
public/assets/app.css:106   .pill-admin { background: var(--accent); color: var(--accent-contrast); }   ← GLOBAL, unscoped
public/assets/app.css:2832  .admin-head .pill-admin { margin-left: auto; }                              ← layout only
```

41 call sites (`grep -rn "pill-admin" templates/`):

| Kind | Count | Sites |
|---|---|---|
| Console **mode** chip `Admin mode`, inside `<header class="admin-head">` | 39 | every `templates/admin/*.php` except `theme_safe_mode.php` |
| Console **mode** chip `Recovery`, inside `.admin-head` on a `variant=plain` page | 1 | `templates/admin/theme_safe_mode.php:11` (verified: `$this->section('variant','plain')` at `:5`, wrapped in `<div class="container">`) |
| **State** chip `disabled` — the emergency execution brake | 1 | `templates/admin/package_security.php:18`, inside `<h2>Emergency execution brake …</h2>` in a `.card`, i.e. **inside `.admin-pane`, not `.admin-head`** |

`package_security.php:18` reads `<?= $execution_disabled ? '<span class="pill pill-admin">disabled</span>'
: '<span class="pill">live</span>' ?>`. It is true exactly when package execution is **halted** — a
kill-switch indicator that gates whether package-owned webhooks and credentials run at all.

### The scoping rule

> **Never recolour `.pill-admin`. Introduce a new single-purpose class for the console mode chip and
> leave `.pill-admin` untouched. A state indicator and a mode indicator may never share a class.**

Concretely, in Slice 2:

1. The console mode chip becomes **`<span class="admin-bar-mode">Admin mode</span>`** — the design's
   own class, already authored at `components.css:334` with `--surface-review` / `--on-review`, and
   shipping via the build (item 1). It carries **neither `pill` nor `pill-admin`**. This decouples by
   construction rather than by selector scoping, which is why it is safe: no descendant selector can
   accidentally reach the brake pill, because the two share no class at all.
2. `theme_safe_mode.php:11` (`Recovery`) **keeps `pill pill-admin` unchanged.** That page is
   `variant=plain` by deliberate design (safe mode must render without theme chrome) and does not
   receive the tier — S-admin-ia §2.1 area 5 records this as a `constraint`.
3. `package_security.php:18` **keeps `pill pill-admin` in Slice 2, and is reclassified in Slice 14**
   to its own semantic class — `pill pill-danger`, mapped to the danger pair (`--danger` ink over the
   `color-mix(in srgb, var(--rust) 9%, var(--surface-raised))` wash). "Execution halted" is a danger
   state, not an "admin mode" state. Painting it `--surface-review` / `--on-review` — the amber
   *"needs review"* register — would turn a kill-switch indicator into a soft advisory. That is the
   exact failure `V-shell` M5 warned about; **until the reclassification lands, it must not be
   repainted at all.**
4. **PR-body requirement:** any Stage-2 diff that touches the `.pill-admin` *declaration* must
   enumerate all 41 call sites in the PR body. The safe default is always "add a class", never
   "change one".
5. **Cheap guard, Slice 14:** an integration assertion that `GET /admin/packages/security` with
   `$execution_disabled = true` renders the brake pill with a class that is **not** the console mode
   chip's class.

---

## 10. The responsive contract, and the ADMIN.md amendment it needs

### The three positions, all verified

| Side | Mechanism | Source |
|---|---|---|
| **Design** | `.admin-tier { display:flex; gap:4px; padding:0 26px 9px; overflow-x:auto; scrollbar-width:thin; }` + `::-webkit-scrollbar{height:4px}` + `.admin-tier-item{flex:none;white-space:nowrap}`. Authored comment: *"Overflow stays visible on purpose: below ~900px the tier scrolls, and a thin scrollbar is the only honest signal that Settings is off-edge."* **Zero JS.** | `docs/design-system/imladris/components.css:335-342` |
| **Production, JS path** | `_nav.php:52-55` emits `[data-admin-nav-toggle]` (hidden until JS unhides), `<nav id="admin-navigation" class="subnav admin-subnav" data-admin-nav>`, `[data-admin-nav-close]`, `[data-admin-nav-scrim]`. `app.js:769-~875`, guarded by `if (adminNavToggle && adminNav && adminNavScrim)`: `matchMedia('(max-width:860px)')`, `inert`, focus trap, scrim, Escape, focus restore, body scroll lock. | `templates/admin/_nav.php`, `public/assets/app.js` |
| **Production, no-JS path** | `app.css:3290-3301` — `@media (max-width:860px) { .admin .admin-subnav { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); position:static; order:2; max-height:none; width:100%; margin:0 0 22px; padding:14px 10px; overflow:visible; } }`, commented *"Without JS the grouped directory stays expanded above the page."* Drawer chrome starts at `:3309` under `.has-js`. | `public/assets/app.css` |
| **Authority** | `ADMIN.md:594` — *"**Responsive** — urgent actions (handle a report, ban) work on mobile; the console collapses to one column with the section nav in a drawer (mirrors the app's mobile pattern)."* | `ADMIN.md` §9.4 |

### Decision: **adopt the design's scrolling tier, delete the drawer AND its no-JS fallback together, amend ADMIN.md §9.4 (and §9.2, same ADR).**

Why this is not a downgrade:

1. **The drawer exists for an object that ceases to exist.** It was built because 26 leaves in 8
   groups cannot fit on a phone (S-admin-ia §0.3 counts 26 from `_nav.php:7-50`). Under the tier
   there are 10–11 two-word pills plus 2–3 tabs for the *active area only*. The justification goes
   with the rail.
2. **Its own stated rationale evaporates.** ADMIN §9.4 justifies the drawer as *"mirrors the app's
   mobile pattern"* — and the app's mobile pattern is the **member board sidebar**, which admin
   pages stop rendering under the console variant (S-admin-ia §1). Keeping the drawer would make the
   console the only surface with one, mirroring nothing.
3. **It is strictly better against two hard production constraints.** Zero JS means the no-JS path
   and the JS path are the *same* path — no `has-js` fork, no second contract, no `inert`/focus-trap/
   scroll-lock/resize-cleanup surface to test. That is the strongest CSP/PE outcome available here.
4. **The no-JS grid at `app.css:3290-3301` is deleted *with* the drawer, not orphaned.** It exists
   only to compensate for the drawer's absence without scripting; under the tier the same markup
   serves both. Leaving it would be dead chrome (S-admin-ia §6.5).
5. `app.js:869-873` couples the admin drawer to the member sidebar toggle; that coupling disappears
   with the sidebar, so nothing is left dangling.

### Non-negotiable carry-overs — do NOT drop these with the drawer

- **44px minimum touch target** on every tier pill and tab at ≤860px. `app.css:3278` already
  guarantees this for `.subnav a`; the design's `padding: 6px 10px` yields ~30px and **must be
  raised on mobile**. This is a production constraint (ADMIN §9.4 *"urgent actions work on mobile"*),
  not a design deviation — do not copy the design's metric at small widths.
- The flag-dark contract: `is-disabled` + `aria-disabled="true"` + `data-destination="…"` +
  the verbatim `Disabled until the feature flag is enabled` (`_nav.php:78-86`, pinned by
  `AppAdminNavIaTest:39-46`).
- axe with three `<nav>` elements on the page — they need distinct accessible names
  (`V-shell` N10).

### The ADMIN.md amendment, in full

**§9.4, replace the final bullet (`ADMIN.md:594`):**

> ~~**Responsive** — urgent actions (handle a report, ban) work on mobile; the console collapses to
> one column with the section nav in a drawer (mirrors the app's mobile pattern).~~
>
> **Responsive** — urgent actions (handle a report, ban) work on mobile; the console is a single
> column at every width. The area tier scrolls horizontally with a visible thin scrollbar, so
> off-edge areas are signalled rather than hidden, and the per-area section tabs wrap. There is no
> drawer and no script: mobile navigation is identical with scripting off. Tier pills and section
> tabs keep a 44px minimum touch target below 860px.

**§9.2 must be amended in the SAME ADR.** Its heading is *"Left-nav, grouped:"* followed by the
eight-group table (`ADMIN.md:559-573`). The tier decision and the responsive decision are one
decision; amending only §9.4 would leave §9.2 mandating a rail that no longer exists. Under the
precedence chain ADMIN.md outranks a design-system pull, so this is an **authoritative-spec
amendment recorded in ADR 0024**, not a restyle (S-synthesis R9, S-admin-ia §1).

**Also §9.4 bullet 1** — *"Same look, distinct mode — reuse the app shell and tokens"* — needs the
qualifier that the console reuses the *tokens and register* but replaces the member shell with its
own sticky bar (`V-shell` M4).

---

## 11. Browser evidence coverage

### `tests/browser/package.json` — every script, read in full (14 + `test`)

| Script | Runs |
|---|---|
| `prepare-db` | `bash prepare.sh` |
| **`evidence`** | three batches, **15 specs**: (1) `thread-view-study`, `rich-content`, `thread-content-presentation`; (2) `gate-a`, `server-drafts`, `appeals`, `group-dms`, `api-tokens`, `providers`, `invitations`, `thread-intelligence`, `composer-shell`, `admin-features`; (3) `admin-remediation`, `admin-dashboard` |
| `evidence:passkeys` | `passkeys`, `totp` |
| `evidence:dark` | `server-drafts` with `RB_BROWSER_DARK_SURFACES=1` |
| `a11y` | dark: `a11y`, `package-review`, `admin-dashboard`; then `thread-intelligence --grep 'no-JS|axe'` |
| `evidence:packages` | dark: `package-security` |
| `evidence:packages:prodlike` | `package-security`, prod-like env |
| `evidence:webhooks` | `webhooks` |
| `evidence:profiles` | `profile-surface` |
| `evidence:integrations` | dark: `package-integrations`, `api-tokens`, `webhooks`, `package-security` |
| `prepare-db:prodlike` | `bash prepare-prodlike.sh` |
| `evidence:prodlike` | `gate-a`, `server-drafts`, `appeals` (prod-like) |
| `evidence:dark:prodlike` | `server-drafts` (prod-like, dark) |
| `a11y:prodlike` | `a11y` (prod-like, dark) |
| `test` | `playwright test` — all 28 specs |

**28 spec files exist.** `.github/workflows/browser-evidence.yml:85` runs **`npm run evidence` and
nothing else** — that is the only CI.

**Outside `evidence` but reachable from a named script (8):** `a11y`, `package-review`,
`package-security`, `package-integrations`, `webhooks`, `passkeys`, `totp`, `profile-surface`.

**Reachable from NO named script at all (5)** — only by `npm test` or by filename:
`community-inbox-theme`, `dm-reimagine`, `imladris-forum-surfaces`, **`role-assignments`**,
`wysiwyg-composer`. `role-assignments.spec.ts` is the one that matters here: Slice 7 depends on it
and it is orphaned. **Add it to `evidence` in Slice 7's commit.**

### In-scope surfaces with NO spec at all

Established by inventorying every `goto(...)` / `visit(...)` target across all 28 specs. Two traps
corrected while doing so:

- `admin-dashboard.spec.ts` appears to reference ~26 admin routes. It does not visit them.
  `expectGroupedDirectory()` (`:60-70`) asserts `[data-admin-nav] :is(a[href="…"], [data-destination="…"])`
  `toHaveCount(1)` **on `/admin`**. Its only `goto` targets are `/login` and `/admin` (`:33,79,120,172,218`).
- `role-assignments.spec.ts`'s `/admin/boards` hits are `form[action="/admin/boards"]` /
  `form[action="/admin/boards/${boardId}"]` **on `/admin/structure`** (`:180,189`) — `board_edit.php`
  is never opened.

**Admin pages never visited by any spec (3):**

| Surface | Route | Note |
|---|---|---|
| `templates/admin/tags.php` + `tag_merge_confirm.php` | `/admin/tags`, `/admin/tags/{id}/merge` | nav-destination assertion only |
| `templates/admin/moderation.php` (Anti-abuse) | `/admin/moderation` | nav-destination assertion only; also the only admin page with zero design representation (S-synthesis §4) |
| `templates/admin/board_edit.php` | `/admin/boards/{id}/edit` | never opened |

**Account panes never visited by any spec (7):** `/settings/privacy`, `/settings/appearance`,
`/settings/notifications`, `/settings/connections`, `/settings/sessions`, `/settings/blocks`,
`/settings/boards`.

Covered account panes, for the record: `/settings` and `/settings/account` (`gate-a`, `a11y`),
`/settings/preferences` — the Reading pane — (`gate-a.spec.ts:529`, a real `visit`),
`/settings/composing` (`gate-a`), `/drafts` (`server-drafts`; note `templates/account/drafts.php` is
served at `/drafts`, not `/settings/drafts` — `App.php:2037`), `/appeals` (`appeals`, `a11y`),
`/settings/account/lifecycle` (`a11y` only), `/settings/security` (`passkeys`, `totp` only).

**Two new specs must be authored, and named in ADR 0024 as a delivery obligation:**
`content-console.spec.ts` (structure / tags / board edit / confirmations / anti-abuse) and
`account-console.spec.ts` (the seven uncovered panes).

### Per-area evidence command each Stage-2 slice must run

Every entry below is *in addition to* `vendor/bin/phpunit` and the standing gates (CSP scan,
`javaScriptEnabled:false` pass, desktop + mobile, `docs/evidence/<slice>/`).

| Slice | Command(s) |
|---|---|
| 1 defect pre-fixes | `npm run evidence` + `npm run a11y` (staff-pair contrast under `data-theme="system"` + `prefers-color-scheme: dark`) |
| 2 shared console chrome | `npm run evidence` **and** `npm run a11y` — the tier rewrites all 39 admin heads; `a11y` is where the three-nav accessible-name check lands |
| 3 shared component CSS | `npm run evidence` + `npm run a11y` + `npm run evidence:integrations` |
| 4 account rail | `npm run evidence` (`gate-a`) + `npm run a11y` + **new `account-console.spec.ts`** |
| 5 overview | `npx playwright test admin-dashboard.spec.ts admin-remediation.spec.ts` (both inside `evidence`) |
| 6 content | **`content-console.spec.ts` (new — nothing exists)** + `npx playwright test admin-remediation.spec.ts` |
| 7 people | `npx playwright test role-assignments.spec.ts gate-a.spec.ts admin-features.spec.ts a11y.spec.ts` — **and add `role-assignments.spec.ts` to `npm run evidence` in this commit** |
| 8 appearance | `npx playwright test admin-features.spec.ts gate-a.spec.ts a11y.spec.ts` + the new branding-preview case from Slice 1 |
| 9 notifications | `npx playwright test admin-remediation.spec.ts gate-a.spec.ts a11y.spec.ts` (`a11y` covers `/admin/email`) |
| 10 settings + TI | `npx playwright test thread-intelligence.spec.ts admin-remediation.spec.ts` + `npm run a11y` (runs the TI `no-JS|axe` grep) |
| 11 members | `npx playwright test admin-remediation.spec.ts invitations.spec.ts gate-a.spec.ts a11y.spec.ts` |
| 12 integrations | `npm run evidence:integrations` + `npx playwright test providers.spec.ts` |
| 13 features | `npx playwright test admin-features.spec.ts gate-a.spec.ts a11y.spec.ts` |
| 14 packages | `npm run evidence:packages` + `npm run evidence:integrations` + `npm run a11y` (includes `package-review`) |
| 15 account A (profile, security) | `npm run evidence:passkeys` + `npm run a11y` + **`account-console.spec.ts`** |
| 16 account B (8 panes) | **`account-console.spec.ts`** + `npx playwright test gate-a.spec.ts` (`/settings/preferences`, `/settings/composing`) + `npm run a11y` |
| 17 account C (boards, drafts, lifecycle) | `npx playwright test server-drafts.spec.ts` + `npm run a11y` (`/settings/account/lifecycle`) + **`account-console.spec.ts`** |
| 18 `/mod/*` | `npx playwright test appeals.spec.ts role-assignments.spec.ts admin-remediation.spec.ts a11y.spec.ts` + a new `/mod/reports`, `/mod/approvals`, `/admin/moderation` case in `content-console.spec.ts` |
| 19 closeout | `npm run evidence && npm run a11y && npm run evidence:integrations && npm run evidence:packages && npm run evidence:passkeys && npm run evidence:profiles && npm run evidence:webhooks` + both new specs |

---

## 12. `/admin/thread-intelligence` answering 200 with both flags off

### Definitively: **deliberate. F3's headline finding is wrong; `D-shell` S50 must be struck. Change nothing.**

Four independent confirmations:

**1 — The controller has no gate, and that is visibly a choice, not an omission.**
`src/Controller/AdminThreadIntelligenceController.php:14-20`:

```php
public function index(Request $request, array $params): Response
{
    $this->requireAdmin();
    return $this->noindex($this->view('admin/thread_intelligence', [
        'dashboard' => $this->container->get(ThreadIntelligenceAdminService::class)->dashboard(),
    ]));
}
```

No `gate()`, no `FeatureFlags::enabled()`, no `NotFoundException`. Compare
`src/Controller/AdminExtensionController.php:8,21`, which imports `NotFoundException` and throws it
when its flag is dark. The two controllers were written to different, deliberate specifications.

**2 — A test pins it, including the exact rendered string.**
`tests/Integration/Admin/AppAdminThreadIntelligenceTest.php:29-71`,
`test_dashboard_is_admin_only_readable_with_flags_off_and_never_discloses_credentials_or_evidence_text`:
sets `['community_memory' => false, 'automated_context' => false]` (`:31-34`); asserts **200**
(`:57`); asserts `Both product flags are off` renders (`:59` — the string is
`ThreadIntelligenceAdminService.php:154`, *"Both product flags are off; generation remains dark."*);
asserts the historical ledger still renders `admin-safe-model`, `prompt-v1` and
`Post #<id>` (`:60-62`); asserts the API secret and the `request_fingerprint` do **not** render
(`:63-64`); and asserts a non-admin gets **403** (`:70`). The test name states the intent outright.

**3 — The nav IS gated; only the route is open.**
`templates/admin/_nav.php:48`:
`['key' => 'thread_intelligence', …, 'flags_any' => ['community_memory','automated_context']]` — with
both dark the entry renders as the disabled `<span class="admin-nav-link is-disabled"
aria-disabled="true" data-destination="/admin/thread-intelligence">` plus the pinned note
(`_nav.php:80-84`). The console stops *advertising* the page while the URL keeps working. That
asymmetry is the design, not a leak.

**4 — A binding operator procedure requires the 200.**
`docs/runbooks/thread_intelligence.md` §12 "Data-preserving rollback and restore" pins
`automated_context=false` at step 2 and `community_memory=false` at step 3, then instructs:
*"Verify after every step with `thread-intelligence:status`, **the admin console**, and row counts
for jobs, generations, summaries, citations, and relationships. None should decrease as a result of
rollback."* If the route 404'd with both flags off, step 3's own verification instruction would be
unexecutable. ADR 0019 decision 1 makes each flag independently rollback-able, and its closing
section makes data-preserving runtime rollback a release blocker.

"Fixing" it would turn `AppAdminThreadIntelligenceTest` red, break the documented rollback
verification loop, and contradict ADR 0019 — which outranks a design report in the precedence chain.

### The one Stage-2 obligation, which is the opposite of "fix it"

When the area tier replaces `_nav.php` (Slice 2), the **Settings** area's `Thread Intelligence` tab
must keep the `flags_any` disabled-tab treatment **while the route continues to answer 200**. This
is the single place in the console where a disabled tab does *not* mean "the route 404s" — contrast
roles (`AdminRoleController:27-31`), tokens, webhooks, providers, extensions
(`AdminExtensionController:21`) and tags, all of which throw `NotFoundException`. Record the
asymmetry explicitly in ADR 0024, or the tier partial's author will "simplify" it away.

Related trap to carry alongside it (S-synthesis R5): `/admin/features` is admin-only but **not**
flag-gated and must stay reachable with all flags off; and `/admin/badge-rules` gates the flag
*before* auth (guest → 404) while `/admin/custom-emoji` gates auth first (guest → 302).

---

## Summary of decisions

| # | Item | Decision |
|---|---|---|
| 0 | Mirror contract | **BLOCKING live defect** — `build/check/verify:imladris` all red today; repair `manifest.json` + `production-contract.json` as Slice 1 commit 1 |
| 1 | `.admin-bar`/`.admin-tier` CSS | Ships via `composer build:imladris` from `docs/design-system/imladris/components.css:328-342` (uncontested, so the layer wins). Application complement in `app.css`. Nothing hand-copied. `D-admin-integrations`'s `imladris.css` instruction is wrong. |
| 2 | `.presence-staff` AA | Patch the mirror to `--surface-staff`/`--on-staff`, raise upstream, record in `LOCAL_RECONCILIATION.md`. Out of scope for admin/account; owned by Slice 1 commit 2. Exclusion from the build is not possible. |
| 3 | Staff pair in the `system` dark block | **Live defect confirmed.** Add the pair to both `app.css` theme blocks (`:789`, `:831`) + a register-parity test. Slice 1 (already assigned in §3). |
| 4 | Baseline digest | One refresh per merge, on `main`, by the merger, as the following commit. Slice branches never touch the file. |
| 5 | ADR / plan | `docs/adr/0024-imladris-admin-account-adoption.md` + `docs/superpowers/plans/2026-08-03-imladris-admin-account-adoption.md`. One of each. 0023's IA clause superseded in part, explicitly. |
| 6 | `human_relative()` | **Do not add it.** Use `human_datetime()`. Signature + UTC handling given only as a conditional fallback. |
| 7 | Formatter conflict | `human_datetime()` everywhere. Reject `audit_datetime()`. The design's difference is CSS, not PHP. |
| 8 | Eyebrows | **Delete all 12 page-head eyebrows** (8 admin + 4 mod `Warden's table`); keep the 5 in-pane section eyebrows. **No test pins any of them.** Four D-report rows invert. |
| 9 | `.pill-admin` | Never recolour it. New `.admin-bar-mode` class for the mode chip (no shared class at all); `package_security.php:18` reclassified to `pill-danger` in Slice 14; `theme_safe_mode.php:11` unchanged. |
| 10 | Responsive | Adopt the scrolling tier; delete the drawer **and** its no-JS grid together; keep 44px targets and the disabled-span contract. Amend `ADMIN.md` §9.4 (text supplied) **and** §9.2 in ADR 0024. |
| 11 | Evidence | 14 scripts + `test`; `evidence` runs 15 of 28 specs; CI runs only `evidence`. 3 admin pages and 7 account panes have zero coverage; `role-assignments.spec.ts` is orphaned. Two new specs required. Per-slice command table supplied. |
| 12 | TI flags-off 200 | **Deliberate.** Four confirmations. Change nothing; instead pin the disabled-tab-with-live-route asymmetry in ADR 0024. |
