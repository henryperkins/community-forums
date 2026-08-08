# V — adversarial verification of D-account-settings.md

**Screen:** account-settings
**Design:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html`
(markup 9–495, `<script type="text/x-dc">` 496–756)
**Report under review:** `.../stage1/D-account-settings.md`

## Method

I read the design markup in full (with inline `style`/`style-hover`/`style-focus` stripped) and the entire
x-dc script block, then opened every production file the report cites: all 13 `templates/account/*.php`,
`templates/partials/settings_nav.php`, `templates/layout.php`, `templates/partials/icon.php`,
`templates/profile/show.php`, `src/Controller/{Account,Settings,OAuth}Controller.php`,
`src/Service/{Account,Mfa,EmailPreference}Service.php`, `src/Support/PreferenceSchema.php`,
`src/Security/Session.php`, `src/Repository/{Session,Block,UserProfileField}Repository.php`,
`src/Core/{App,FeatureFlags}.php`, `config/config.php`, `public/assets/{app.css,app.js,composer.js}`,
`resources/imladris/components.css`, `docs/adr/0021`, `docs/adr/0023`, and
`tests/Integration/Core/AppImladrisFidelityTest.php`.

**Overall verdict: the report is substantially correct and unusually well-cited** — I spot-checked ~40
`path:line` citations (app.css 159 / 256-263 / 2020 / 2028-2033 / 2034-2036 / 2054-2061 / 2487-2499 /
2602-2607 / 2615-2618 / 2641-2663 / 2771-2775 / 2859-2878 / 3193-3210; AccountService 143-146;
BlockRepository 71; SessionRepository 48-51; config 35; MfaService 228; FeatureFlags 70-73, 86;
OAuthController 121; SettingsController 137; App.php 2089/2095-2142; composer.js 1044/1050;
components.css 184-201/266-297; icon.php 46) and all but a handful land exactly. Every
`feature-removed` call survives a grep. But there are **one materially false claim, one binding
test contract the report never opened, five classification problems, and eleven real misses** —
several of which would turn the proposed slices red on the first `phpunit` run.

---

## 1. Refuted claims

### R1 — "`.scribe-panel` … is used across admin" is FALSE (H10 and §2.1 #16)

> H10: "Production's settings panes use `.scribe-panel` … **Do not redefine `.scribe-panel`
> globally** (it is used across admin)"
> S2 test: "confirm `.scribe-panel` is untouched on `/admin/*`"

`grep -rn "scribe-panel" --include=*.php templates/` returns **only** `templates/account/` — seven
files: `settings.php`, `security.php`, `privacy.php`, `appearance.php`, `preferences.php`,
`composing.php`, `notifications.php`. **Zero** occurrences anywhere under `templates/admin/` or
`templates/mod/`. The only other hits repo-wide are the CSS definitions
(`public/assets/app.css:2626,2641,2648,2659`, `public/assets/imladris.css:600-611`,
`resources/imladris/components.css:237-248`) and the fidelity test (see M1).

Consequences: the stated *reason* for authoring a settings-scoped class is fictitious; the S2
regression check ("`.scribe-panel` untouched on /admin/*") tests nothing; and the report never
confronts the fact that `.scribe-panel` / `.scribe-panel-head` are **Imladris design-system
components** shipped in `resources/imladris/components.css:237-248` and mirrored into
`public/assets/imladris.css`. Deleting them from every settings pane is a change to the DS component
inventory, and the report's own S10 runs `composer check:imladris` / `verify:imladris`. That tension
must be resolved explicitly (retire the component in the DS, or scope the new card), not asserted away.

Sub-error: "**six** of the thirteen" panes use `.scribe-panel` — it is **seven** templates
(and `security.php` uses it three times, `settings.php` twice).

### R2 — ADR 0021 deferral #3 does not say what the report says it says (§2.3 #34)

> "Do not build. **ADR 0021 deferral #3 binds**: a password policy ships only with its enforcement"

`docs/adr/0021-admin-console-remediation-and-deferrals.md:47-52` reads: *"Registration approval mode,
verification-requirement toggle, password policy, rate-limit editor (**ADMIN §9.3 Settings →
Registration/Security**; §5.6 approval queue) … The UI ships only with the enforcement (inert settings
are not evidence)."* That is an **operator-console** deferral about an admin-side policy *editor*. It
does not govern a member-facing strength meter on `/settings/security`. The *principle* transfers; the
*binding* does not. And the premise "no policy exists" is overstated — `limits.password_min` (default
8) is enforced server-side, so tiers 0/1 of the design meter would in fact reflect a real rule.
Conclusion (don't ship the 5-tier meter with its fiction top label) is fine; the justification needs
rewriting or it will be challenged in review.

Citation error in the same row: `src/Service/AccountService.php:176-178` is inside
**`setInitialPassword()`** (the OAuth-only set-a-password path). The change-password minimum lives at
`AccountService.php:202-206` (`'new_password_confirm' => 'The new passwords do not match.'` at :206,
which is the row's other claim).

### R3 — The "Authenticator URI is feature-added, design: —" framing is wrong at the source (§2.3 #38/#39)

The design **does** render a readonly enrollment-secret field:
`AccountSettings.dc.html:143` — `<label><span>Authenticator secret</span><input readonly value="IMLA
DRIS 7K2F 9QD4 H1PB"></label>`. Production renders the identical field at `security.php:65-67`
(`<span>Authenticator secret</span>`), *plus* a second `Authenticator URI` field at `:68-71`. The
report's own fiction table row 12 cites `:143`, so it saw the field — yet §2.3 has **no row recording
the 1:1 match**, and instead splits one concept across a `feature-removed` (#38 QR) and a
`feature-added` (#39 URI). See MC1.

### R4 — layout.php / app.js citation drift

- "`templates/layout.php:53-55` renders `partials/topbar`" — the topbar partial is at
  **`layout.php:50-52`**; 53-55 is the announcement-banner block.
- "`.flash` banner … (`layout.php:60`)" — `partials/flash` is **line 61**; line 60 is `<main …>`.
- "brand name from `$brand['name']` (`layout.php:27`)" — line 27 is the `<title>` fallback, not a
  topbar wordmark; the topbar's own brand render is inside `partials/topbar`.
- "server stamps `data-theme` on `<html>` (`layout.php:19`)" — line 19 is `<html lang="en"`,
  `data-theme=` is **line 20**.
- "`composer.js:903` 'Draft saved · '" — the text node is **line 902**.

None of these change a conclusion, but the brief demands `path:line` precision and these are the
citations a reviewer will click first.

---

## 2. Misclassifications

### MC1 — QR (#38 feature-removed) + Authenticator URI (#39 feature-added) = one **feature-changed**

Same step in the same flow (get the enrollment secret into the authenticator), different mechanics
(design: scan a code; production: copy a secret / `otpauth://` URI). Splitting it records a gap that
is not a gap — production fully implements the step. Correct form: **feature-changed** — design wins
on card layout and the 88×88 slot's *position*, production wins on mechanics; the copy is reworded
away from "Scan the cipher" and no empty QR box ships.

### MC2 — Per-event email switches (#70 feature-removed) + pause-all (#71 feature-added) = one **feature-changed**

Identical double-count. The concept — member control over outbound email — exists in production
(`EmailPreferenceService::pauseAllEmail/setPauseAllEmail`, `notifications.php:38-42`) at coarser
granularity, plus per-subscription `email_enabled` (`notifications.php:63`). Recording half as
"removed" and half as "added" hides the actual finding: production's control is **coarser**, not
absent. Classify feature-changed and record the granularity reduction against USER §4.6.

### MC3 — DM scope "Members I have replied to" (#49) is **feature-changed**, not feature-removed

Design `:265` and production `privacy.php:32-36` both render a three-option `<select>` in the same
slot: Everyone / *middle* / No one. Only the middle predicate differs (design: "Members I have replied
to"; production: `members`, labelled "Members"). Nothing is missing — one option's semantics and label
differ. As a side effect of the wrong bucket the report never records the **label** difference on the
option that does exist, nor that the first select's two option strings ("Public — anyone can view",
"Members only — signed-in members") are already **verbatim identical** design↔production.

### MC4 — Success toast (#25 constraint) is mostly a **copy** difference

The constraint is real but narrow: the *trigger* cannot be client-only (a toast fired from client
state lies when the POST fails; PE requires the server round-trip). The pill's geometry, `--green-800`
on `--parchment-50`, `border-radius: 999px`, `role="status"`, position and copy are all reproducible
verbatim on the existing server flash. The report's action — "Adopt the toast skin **if desired**" —
is exactly the aesthetic-preference escape hatch the brief forbids. Split the row: constraint (trigger
= server flash, no client-only confirmation) + copy (the pill, mandatory).

### MC5 — Topbar (#1 constraint) is partly an unrecorded difference

Fiction strings ("Imladris", "Back to the council") are legitimately constraint per brief rule 3. But
"Back to the council" is also an **affordance** — a back-out link from the settings console that
production's settings screen does not offer (production relies on the global sidebar). "Do not port;
out of this screen's scope" waves away a real comparison. Record it: either copy (add "Back to the
forum") or feature-removed with a reason — not "out of scope".

---

## 3. Missed differences

### M1 — (constraint / binding) `AppImladrisFidelityTest` pins exactly what S2/S3/S5 propose to delete

`tests/Integration/Core/AppImladrisFidelityTest.php` (introduced by commit **b40095d "Complete
Imladris fidelity closeout"**) asserts:

- `:69-70` — `/settings/account` contains `scribe-panel` **and** `field-grid`
- `:138` — `/settings/privacy`, `/settings/appearance`, `/settings/preferences`, `/settings/composing`
  each contain `scribe-panel`
- `:143` — `/settings/privacy` contains **`gem-check`**
- `:170-171` — `/settings/security` contains the literal
  `<h2 class="scribe-panel-head">Password</h2>` and `<h2 class="scribe-panel-head">Two-factor
  authentication</h2>`
- `:174` — `/settings/notifications` contains `<h2 class="scribe-panel-head">Daily digest</h2>`
- `:145-167` — every one of the 13 settings pages renders exactly one `<main ` landmark

S2 ("replaces `.scribe-panel` … on settings panes"), S3 ("move from `.gem-*` to
`.switchline`/`.switch`") and S5/#84/#87/#110 (h2 → eyebrow span) each break one or more of these, and
**no slice's test plan mentions this file**. This is the single most likely cause of a red suite. It
is also a prior decision in the report's own sense — the report itself invokes "already shipped and
test-pinned" as a reason to leave `commends` alone at `templates/partials/post.php:33`; the same
standard applies to `gem-check` and `scribe-panel` here.

### M2 — (constraint) "Convert the h2 to the panel eyebrow" is a heading-semantics regression

#84 (Sessions), #87 (Blocks), #110 (Lifecycle) and #17 all propose replacing `<h2>` with the design's
`<span>` eyebrow. The test in M1 is literally named
`test_settings_pages_keep_one_main_landmark_and_real_section_headings`. Production already reconciles
design and a11y by styling the heading — `<h2 class="scribe-panel-head">Password</h2>` — which is the
correct pattern. The action must read "keep the `<h2>`/`<h3>`, restyle it into the eyebrow register",
never "convert to a span".

### M3 — (copy) A **third** boolean idiom the report never counted: `.checkline`

H9/#22 assert production runs "two incompatible boolean-control idioms" and enumerate `.gem-*` and
`.switchline`. There is a third: `<label class="checkline">` at **`notifications.php:38-42`** (the
pause-all-email control the report separately restyles in #71) and again at **`boards.php:143`**
(saved-feed "Digest"). A "unify on `.switchline`" slice that misses `.checkline` leaves the
inconsistency alive on the very control #71 touches.

### M4 — (constraint) The custom-profile-fields block is feature-flagged

`settings.php:95` renders the three `custom_label_N`/`custom_value_N` rows only
`if (!empty($custom_profile_fields))`, and `AccountController.php:91` feeds that from
`FeatureFlags::enabled('custom_profile_fields')` (`FeatureFlags.php:73`, GA default-on 2026-07-03,
"reversible via features override"). #30 describes the 3-row mechanic with no mention of the gate;
brief rule 6 requires the restyled section to stay gated and the flag-off render to stay clean.

### M5 — (copy) The rail has no accessible name

Design `:49` is `<nav aria-label="Settings sections">`. Production `settings_nav.php:29` is
`<nav class="subnav settings-rail">` — no `aria-label`, so the landmark is unnamed and (with the
sidebar nav and topbar nav also present) ambiguous. The report caught the missing `aria-current` (#10)
but not this.

### M6 — (copy, with a reduced-motion rider) Every pane animates in; production has none

Design declares `@keyframes acRise` / `acFade` (`:16-17`) and applies `animation: acRise 220ms
ease-out` to **every** pane wrapper — `:89, :109, :139, :153, :173, :214, :261, :277, :313, :337,
:360, :390, :410, :430, :448` — plus the unsaved bar (`:477`) and toast (`:487`), and sets
`html { scrollbar-gutter: stable; }` (`:14`). Production has no entry animation on any settings pane.
Not mentioned anywhere in the report. Must be honoured against the existing `reduced_motion`
preference (`layout.php:23` stamps `data-reduced-motion`) and `prefers-reduced-motion`.

### M7 — (copy) Typographic apostrophes: the design uses `’`, production uses `'` everywhere

`grep -rn $'\u2019' templates/account/` returns **zero** hits. The design register is consistently
curly: "Show when I’m online" (`:268`), "The two new passwords don’t match yet." (`:126`), "you just
won’t be ranked publicly." (`:269`), "A blocked member cannot…" set. Production:
`privacy.php:39` "Show when I'm online", `:40` "won't", `blocks.php:14` "can't", `:16` "haven't",
`notifications.php:49` "aren't". The report quotes these strings as matches without noting the
character difference — for a brief that demands verbatim microcopy this is a real, systematic delta.
(Note it interacts with #91: the report adopts the design's uncontracted "You have not blocked
anyone.", which sidesteps the apostrophe there but not elsewhere.)

### M8 — (copy) Three security strings never compared

- design `:145` "**Six-digit code**" vs production `security.php:79` "**6-digit code**" (the report
  covers the input *skin* in #40 but not the label)
- design `:163` "**Disable two-factor**" vs production `security.php:120` "**Disable two-factor
  authentication**"
- design `:155` renders "Recovery codes" as a label-register `<p>`; production `security.php:90` uses
  `<h3>Recovery codes</h3>` (heading level, not just chip styling — and see M2)

### M9 — (copy) Three panes' headings never scheduled for the eyebrow register

§2.9/§2.10/§2.13 each carry a row converting a heading; §2.7/§2.8/§2.11 do not, although the same
mismatch exists:

| Design eyebrow | Production |
|---|---|
| "Your subscriptions" (`:374`) | `<h2>Your subscriptions</h2>` in a bare `.card` (`notifications.php:47`) |
| "Connected accounts" (`:391`) | `<h2>Connected accounts</h2>` in a bare `.card` (`connections.php:13`) |
| "Organize your boards" (`:314`) | `<h2>Organize your boards</h2>` in a bare `.card` (`boards.php:21`) |
| *(no design analogue)* | `<h2>Set a password</h2>` (`connections.php:42`) — needs a register decision |

Note "Organize your boards" and "Connected accounts" are **verbatim identical** strings already;
only the element/register differs.

### M10 — (constraint / fiction) Missed fiction strings

- `:140` "**Scan the cipher** with your authenticator, then enter the six digits it shows." —
  "cipher" is design-register fiction for a QR code. The report handles the *box* (#38) but never
  lists the string in §3. Production equivalent: "Add this secret to your authenticator app, then
  enter the six digits it shows."
- `:539-541` subscription sample rows ("Evaluations as ritual, not gate", "#audit-trails", "Where
  should ratified decisions live?") — sample fiction of the same class as the drafts samples the
  report *did* list at §3 row 30.

### M11 — (constraint / consistency) "Regard" already ships in production beyond `privacy.php:40`

§3 row 20 says the fiction "regard" is "already shipped … at `templates/account/privacy.php:40` — fix
in place", and S10 changes only that line. But `templates/profile/show.php` ships it as **user-visible
chrome**: `:269` `<p class="profile-regard-label">Regard</p>`, `:270` "Regard recognises contribution;
it grants no powers.", `:314` "… `number_format(reputation)` regard", plus `.profile-regard-card` /
`.profile-regard-value` / `.profile-regard-note` class names, and `ProfileController.php:78,137` route
the `?tab=commends` surface that renders them. Changing only `privacy.php:40` to "reputation" leaves
the member console saying *reputation* while the public profile says *Regard* two clicks away. Either
scope the de-fiction repo-wide or record the inconsistency deliberately — the report does neither.
(This also nuances H4: production's nearest analogue to the design's "Regard" pane is that Commends
tab — a total plus `topCommendedByUser(…, 5)`, not a ledger. H4's core claim survives: `grep -rn
reputation_events src/ templates/` hits only `ReputationLedgerService` and `BadgeRuleService:157,222`,
so the per-event ledger genuinely does not exist.)

---

## 4. Claims I tried to break and could not

- **#5 Regard pane feature-removed** — confirmed: no route, no repository read, no template.
- **#26 profile field schema feature-removed** — confirmed: `user_profile_fields` is per-user
  `(label,value,position)` (`0062_bookmark_folders_profile_fields.php:40-45`,
  `UserProfileFieldRepository.php:25`); `grep profile_field templates/admin/` → 0.
- **#34 no strength meter / #38 no QR encoder** — confirmed: `grep -rni "strength"` and
  `grep -rni "qrcode|qr_code"` over `src/` + `public/assets/*.js` → 0.
- **#42 no 2FA cancel route** — confirmed: `MfaService` public methods are
  `enabledForUser/status/startEnrollment/confirmEnrollment/beginLoginChallenge/challengeUser/
  completeLoginChallenge/rotateRecoveryCodes/disable`; no cancel, and no route in `App.php`.
- **#48 no "Hidden" visibility** — confirmed `AccountService.php:143-144`.
- **#62 `thread_sort` retired** — confirmed: `PreferenceSchema::VERSION = 3` (:30), the only
  remaining mentions are the v3 comments at :141 and :180; no template or controller reads it.
- **#70 no per-event email preference** — confirmed: `EmailPreferenceService` has exactly
  `pauseAllEmail`/`setPauseAllEmail`; `grep email_on|mention_email|reply_email|notify_email` → 0.
- **#83 sessions expire absolutely** — confirmed `Session.php:153-154` (`expires_at = now + lifetime`),
  `SessionRepository::touch` (:48-51) writes only `last_seen_at`, `config/config.php:35` default 30.
- **#43 recovery codes are HMAC-hashed** — confirmed `MfaService::recoveryHash` at :225-229.
- **#53 zero client theme code** — confirmed: `grep documentElement public/assets/app.js` returns only
  `has-js`, focus-restore and `--keyboard-inset`; `app.js:112-125` is the brand-preview block.
- **#37/#45/#103 password gates** — confirmed on all four TOTP forms and on deactivate *and* delete.
- **H3 Composing is a separate route** — confirmed `App.php:2127-2128`, `settings_nav.php:10`.
- **Design-side geometry claims** (input clip 8px / focus 2px+5px halo, select chevron 11×7
  `#B08A3A` at `right 13px`, rail 232px / sticky 84px / 2px gap, container 1064px / `30px 28px 132px`,
  swatch chips, `1fr 1fr` grids, 11px gaps) — all verified against the raw inline styles.
- **The "Boards sits in the Reading & writing group" reading** — confirmed: `:68-69` precede the
  "Council" heading at `:71`.
- Additional true-but-unremarked detail: `/settings` (`App.php:2095`) is a redirect to
  `/settings/account` (`AccountController::index:28-32`), so "13 routes" is accurate.

---

## 5. What the next revision must change

1. Delete the "used across admin" premise (R1); decide explicitly what happens to the
   `.scribe-panel` **DS component** and to `AppImladrisFidelityTest` (M1) before S2/S3/S5 are scheduled.
2. Rewrite #84/#87/#110/#17 as "keep the heading element, restyle it" (M2).
3. Re-bucket #38+#39 and #70+#71 as feature-changed pairs; re-bucket #49 as feature-changed; split #25
   into constraint(trigger)+copy(pill) (MC1–MC4).
4. Re-cite ADR 0021 #3 honestly or drop it, and fix `AccountService.php:176-178` → `:202-206` (R2).
5. Add rows for: `.checkline`, the `custom_profile_fields` flag, `nav aria-label`, the pane entry
   animation + reduced-motion, the apostrophe register, the three security strings, the three
   unconverted `<h2>` eyebrows, "Scan the cipher", and the profile-page "Regard" spill (M3–M11).
