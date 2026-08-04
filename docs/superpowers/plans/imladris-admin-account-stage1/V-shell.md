# V-shell — adversarial verification of D-shell.md

Verified against the design system and production as they stand on 2026-08-03.
Every file below was opened. The report is **substantially right on production**
(its `templates/` and `src/` citations are almost all correct) and
**substantially wrong on the design**: its central premise and its highest-risk
blocker were both invalidated by design-system files rewritten at 20:36–20:37,
after the report was drafted.

---

## A. REFUTED

### R1 — The "two generations" premise is dead. All ten admin screens now mount `AdminNav`.

The report's §0 headline, §1a, the first six rows of the §2 container table, S18,
and the structured summary's entire `design_section_order` rest on the claim that
six admin screens plus `AccountSettings` "dated `2026-08-03 06:01`" still carry a
58px topbar + `Operator desk · <Area>` eyebrow + 2.4rem H1 + right-hand mode chip.

Actual mtimes: the six "old" admin screens are **20:36**, i.e. *later* than the
four "new" ones (20:20–20:23). All six were rewritten. Verified head of each:

| File | line 22 | container | H1 | eyebrow | mode chip | tab margin |
|---|---|---|---|---|---|---|
| `AdminOverview.dc.html` | `<x-import … AdminNav" area="overview" hint-size="100%,101px">` | `1160px` / `22px 28px 110px` | **2.1rem** | none | none | `16px 0 0` |
| `AdminContent.dc.html` | same, `area="content"` | `1100px` / `22px 28px 110px` | 2.1rem | none | none | `16px 0 0` |
| `AdminPeople.dc.html` | same, `area="people"` | `1100px` / `22px 28px 110px` | 2.1rem | none | none | `16px 0 0` |
| `AdminAppearance.dc.html` | same, `area="appearance"` | `1100px` / `22px 28px 110px` | 2.1rem | none | none | `16px 0 0` |
| `AdminNotifications.dc.html` | same, `area="notifications"` | `1140px` / `22px 28px 110px` | 2.1rem | none | none | `16px 0 0` |
| `AdminSettings.dc.html` | same, `area="settings"` | `1100px` / `22px 28px 110px` | 2.1rem | none | none | `16px 0 0` |

Corroboration: `grep -rn "Operator desk" docs/design-system/imladris` returns
**one** hit — `components/admin/admin.card.html:43`, the note describing the
kicker as *removed*. `grep -rn "Back to the council" templates/` returns two
member screens (`account-settings`, `user-profile`) and `AdminNav.jsx:44`; **no
admin screen**. `grep -rln "height: 58px" templates/` returns only
`account-settings`, `thread-view`, `user-profile`, `users-online`.
`git status` shows the six admin screens + `AccountSettings` + `components.css` +
`README.md` + `CHANGELOG.md` as modified-uncommitted.

Consequence: the whole "old gen vs new gen" apparatus, the `26px 28px 110px`
padding, the `22px 0 0` tab margin, the per-screen `Operator desk · Area` eyebrow
strings, and the right-hand chip placement question (S7) are gone. **Only
`AccountSettings` still carries the 58px hand-rolled topbar** — and it is
unchanged in the shell region (its diff is one Reading-pane select).

### R2 — S41 is dead. `.admin-bar*` / `.admin-tier*` all have CSS. This was the report's #1 blocker.

The report (S41, §0 "Blocker to record for Stage 2", Slice 0 item (e), risk
**high**) states `grep -rn "admin-bar" docs/design-system/imladris` returns
"exactly one file, the JSX", and that `components.css` predates `AdminNav.jsx`.

`components.css` was written at **20:37** (after `AdminNav.jsx` at 20:22) and
carries the complete skin at **`components.css:324–342`**, including a comment
block explaining the two-rank intent:

```
.admin-bar { position: sticky; top: 0; z-index: 20; background: color-mix(in srgb, var(--surface-raised) 92%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border-hair); }
.admin-bar-id { display: flex; align-items: center; gap: 16px; height: 58px; padding: 0 26px; box-sizing: border-box; }
.admin-bar-brand { display: inline-flex; align-items: center; gap: 10px; flex: none; color: var(--accent); }
.admin-bar-wordmark { font-family: var(--font-display); font-weight: var(--weight-semibold); font-size: 1.25rem; color: var(--text-strong); letter-spacing: .01em; }
.admin-bar-exit { … font-size: .78rem; letter-spacing: .03em; color: var(--text-muted); text-decoration: none; }
.admin-bar-exit:hover { color: var(--accent); }
.admin-bar-mode { margin-left: auto; flex: none; padding: 4px 12px; border-radius: var(--radius-pill); background: var(--surface-review); color: var(--on-review); font-family: var(--font-label); font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; }
.admin-tier { display: flex; gap: 4px; padding: 0 26px 9px; overflow-x: auto; scrollbar-width: thin; }
.admin-tier::-webkit-scrollbar { height: 4px; }
.admin-tier::-webkit-scrollbar-thumb { background: var(--border-hair); border-radius: 999px; }
.admin-tier-item { flex: none; white-space: nowrap; padding: 6px 10px; border: 0; border-radius: var(--radius-md); background: transparent; font-family: var(--font-label); font-size: .8rem; letter-spacing: .03em; color: var(--text-muted); text-decoration: none; cursor: pointer; }
.admin-tier-item:hover { color: var(--text-strong); background: var(--surface-sunken); }
.admin-tier-item.is-active { background: var(--brand-subtle); color: var(--on-brand-subtle); font-weight: var(--weight-medium); cursor: default; }
```

There is nothing to escalate, nothing to derive, and no DesignSync pull to wait
for. Slice 0 item (e) should be struck.

### R3 — The "dead chrome" caption span at `AdminOverview.dc.html:49` no longer exists.

The report's fiction table and §3 C3 cite a non-interactive
`<span>Moderation · Content · People · Appearance · Notifications · Integrations
· Settings</span>`, and S10 uses it as evidence that "the design knows about"
production's groups. `grep -n "Moderation" AdminOverview.dc.html` → no hits; the
only `·` strings are card sublines (`3 unclaimed · 1 harassment`, line 56). The
intro paragraph the report cites at `:55` is now `:42`. S10's supporting argument
is unsupported; the fiction-table row is stale.

### R4 — S11 is factually false: production already ships the design's underline tab strip.

Report (S11, table and structured summary): "**No production page has any local
nav.** … This is a genuinely new rank of navigation, not a restyle." Risk med;
Slice 2 is scoped as new authoring.

`templates/mod/reports.php:23-27`, `mod/approvals.php:18`, `mod/appeals.php:18`,
`mod/user.php:33` all render `<nav class="mod-subnav" aria-label="Moderation
queues">` with Reports / Approval hold / Appeals. Its CSS at
**`public/assets/app.css:4522-4553`** is the design's strip within a rounding
error:

| | design (all 10 screens) | production `.mod-subnav` |
|---|---|---|
| nav | `display:flex; flex-wrap:wrap; gap:2px; border-bottom:1px solid var(--border-hair)` | identical |
| nav margin | `16px 0 0` | `14px 0 22px` |
| item | `padding:9px 15px; margin-bottom:-1px; border:0; border-bottom:2px solid transparent; font-family:var(--font-label); letter-spacing:.03em; color:var(--text-muted)` | identical except `padding:9px 13px` |
| item size | `.84rem` | `.82rem` |
| active | `border-bottom-color: var(--gold-500); color: var(--text-strong)` | `border-bottom-color: var(--accent-2); color: var(--text-strong)` |

`--accent-2` **is** `var(--gold-500)` (`imladris.css:160`, `:182`). So the active
rule is already byte-equivalent. The real delta is 2px of padding, .02rem of type,
and the margin. Slice 2 is a rename + retune of an existing partial-less idiom,
not a new rank. (The narrower claim — no local nav in `templates/admin/*` or
`templates/account/*` — is true: `grep -rn "<nav" templates/admin templates/account`
returns only `_nav.php:56` and three `class="pager"`.)

### R5 — S17 inverts the eyebrow spec. The design's canonical `.eyebrow` is already production's.

Report: design eyebrow is `.68rem / var(--gold-ink) / .18em`; production
`.eyebrow` is `.72rem / --text-muted / .16em`; "Change size and colour".

The design system's own class is `docs/design-system/imladris/tokens/typography.css:71-77`:

```
.eyebrow { font-family: var(--font-label); text-transform: uppercase;
           letter-spacing: var(--tracking-caps); font-size: var(--text-eyebrow);
           color: var(--text-muted); }
```
with `--text-eyebrow: 0.72rem` (`tokens/typography.css:40`) and
`--tracking-caps: 0.16em`. Production `imladris.css:279-285` and `:234`, `:249`
are the **generated copies of exactly that**. Production already matches the
design system verbatim.

The `.68rem / --gold-ink / .18em` values exist in exactly one place: the inline
style on `AccountSettings.dc.html:41`, a single page-head one-off — and no admin
screen has an eyebrow at all any more. Applying S17 globally would fork the token
contract away from the design system it claims to adopt.

### R6 — S16 contradicts the current design and the report's own S18.

S16 (risk low, and the only eyebrow row surviving into the structured summary):
"Add eyebrow + intro to the ~31 admin pages without one."
S18, three rows later: "The new generation **removes** the kicker entirely …
Follow the new generation: drop the eyebrow on admin."

Post-R1 there is no admin eyebrow anywhere in the design. S16 is wrong; S18 is
right. As written, Slice 3 ("the ~31 admin templates missing an eyebrow or
intro", plus a PHPUnit assertion that "every admin route renders exactly one h1
**and one `.eyebrow`**") would build the very chrome the design deleted.

### R7 — S21 overstates the flash delta and mis-cites it.

Report: production is a "plain `<div class="flash" role="status">` (`app.css:189`)";
action "Move the flash into the pane below the tab strip **and restyle**".

`public/assets/app.css:190-197` (the rule starts at 190, not 189):
```
.flash { background: var(--surface-done); border: 1px solid var(--green-200);
         color: var(--on-done); padding: 10px 14px;
         border-radius: var(--radius); margin-bottom: 16px; }
```
The design's flash (`AdminMembers.dc.html:38-40` and the same block in Features /
Integrations / Packages) is `background: var(--surface-done); border: 1px solid
var(--green-200); border-left: 3px solid var(--success); … color: var(--on-done)`.
Three of the four declarations already match. The genuine deltas are the 3px
`--success` left rule, the 16px check-circle SVG, `padding: 12px 16px`,
`border-radius: var(--radius-md)` and `margin-top: 20px`. "Restyle" is
three declarations, not a skin change.

### R8 — Slice 3's `--surface-staff` rationale does not apply to the tokens being changed.

Slice 3 requires a contrast check "specifically on `data-theme="system"` under
`prefers-color-scheme: dark`, given the known `--surface-staff`/`--on-staff` gap".
`app.css:863` — inside `@media (prefers-color-scheme: dark) { [data-theme="system"] }` —
declares `--surface-review: rgba(194,154,68,.16); --on-review: #DCC68A;`. The
review pair **does** flip. The gap is real only for `--surface-staff`/`--on-staff`
(present at `imladris.css:200` under `[data-theme="dark"]`, absent from the system
block), which `.pill-admin` does not use. The rider is a non-sequitur.

### R9 — S40 "Zero real hrefs in any screen" is no longer true.

`AdminNav.jsx:70` renders `href={active ? undefined : `../${a.dir}/${a.file}`}` —
real relative hrefs to sibling templates, documented at `README.md:109` ("it
renders real hrefs to its sibling templates unless you pass `onNavigate`") and at
`AdminNav.jsx:4-6`. The tier navigates; only the per-screen tab strip is buttons.

### R10 — S15's "no breakpoint" premise is false at the component level.

`components.css:335-339` is an explicit narrow-viewport spec with a rationale
comment: *"Overflow stays visible on purpose: below ~900px the tier scrolls, and a
thin scrollbar is the only honest signal that Settings is off-edge."* —
`.admin-tier { overflow-x: auto; scrollbar-width: thin }` plus styled webkit
scrollbar. The design has a responsive answer for the tier and it is **not** a
drawer. S15's classification (production has a drawer the design lacks) survives,
but its action ("Preserve every behaviour when porting to the horizontal tier")
now collides with the design's own answer and must be decided, not assumed.

### R11 — "ADR 0023 item 6 locks the group list, not its orientation" is wrong on the text.

`ADMIN.md:561-563`, §9.2 "Console information architecture", opens: **"Left-nav,
grouped:"** followed by the eight-row table. ADR 0023 item 6 says the shipped nav
is "Console IA **per ADMIN §9.2**". Orientation is written into the authoritative
operator spec, which outranks a design-system pull in the precedence chain
(DECISIONS > DESIGN > SCHEMA > ADMIN/USER/…). Replacing the left-nav with a
horizontal tier is a spec amendment, not a design-adoption decision. Slice 0 must
be framed that way (it currently proposes only "adopt the tier … Reviewed against
… ADMIN.md 9.2/9.4").

Same section, §9.4: "**Same look, distinct mode** — reuse the app shell and
tokens" — which S9's proposed `variant=console` (drop `partials/sidebar.php`)
also contradicts. S8/S9 are labelled plain "copy"; they are spec conflicts.

### R12 — The `--topbar-h` blast radius is undercounted.

S2 lists 13 sites (":78, :88, :115, :1669, :1732, :1759, :1768, :1877, :1897,
:2057, :2846, :3330, :3377"). `grep -n -- "--topbar-h" public/assets/app.css`
returns 22 occurrences on 21 lines; the report omits **:1878, :1898, :1903, :1904,
:2847, :3591, :5716, :5731**. For a row marked risk **high** whose whole content
is the blast radius, the list must be complete.

### R13 — `components.css` is cited in Production columns; there is no such production file.

S6 gives production `.pill` as "(`app.css:106`, `components.css:44-50`)", and
S37's action says "never `imladris.css`, never `components.css`".
`ls public/assets/*.css` → `app.css`, `imladris.css`, `wysiwyg-composer.css`.
`components.css` is the *design system's* file. The substantive claim survives
(`app.css:1490` gives `.pill { font-family: var(--font-label); letter-spacing:
.04em; text-transform: uppercase; font-size: .72rem }`) but the citation is wrong.

Minor citation drift: in the `app.css` 2900s block every cite is +3 lines
(hover is 2903-2907 not 2906-2910; `.active` is 2908-2912 not 2911-2915;
`.is-disabled` is 2913-2919 not 2916-2922; `.admin-pane` is 2920-2929 not
2923-2935; `.pane-intro` is 2934-2938 not 2936-2940). Design cites are also stale
post-R1 (skeleton bars are `AdminOverview.dc.html:200-205`, not 212-219).

---

## B. MISCLASSIFIED

### M1 — S11: `feature-added` → **copy**.

Two errors. (a) Direction: the brief defines `feature-added` as *production has
functionality the design never modelled*; S11 describes the design having
something production lacks, which would be `feature-removed`. (b) Substance: per
R4 production *does* have it (`.mod-subnav`), so it is neither — it is a **copy**
difference of ~2px padding, .02rem type and one margin, generalised from `/mod`
to the admin areas.

### M2 — S32 (`Composing`): `feature-added` → **copy** (or `feature-changed`).

Report: "No design tab … production has functionality the design never modeled.
Keep. Style in the idiom, place in `Reading & writing`."

`AccountSettings.dc.html:349-354` — inside the **Reading** pane —
`<span>Composing</span>` heading followed by all three of production's composing
switches, verbatim in concept:
- `label="Press Enter to send — Shift+Enter for a new line"` (`prefEnterSend`)
- `label="Show a live preview while composing"` (`prefPreview`)
- `label="Continue lists and quotes on the next line"` (`prefContLists`)

Those are exactly `enter_to_send` / `show_preview` / `smart_lists`
(`layout.php:5`, `templates/account/composing.php`, `/settings/composing` →
`SettingsController::composingForm`, `App.php:2125-2126`). The design modelled
the feature and folded it into Reading; production splits it onto its own route.
That is a placement difference (copy) or a route-decomposition difference
(feature-changed), not "never modeled". As written, S32/Slice 4 would ship a rail
item the design does not have, justified by a false premise.

### M3 — S13 (`feature-added`, flag-disabled nav state): narrowly right, premise overstated.

"No flag concept" is true of the ten `.dc.html` screens, but the design system
does model it: `_ds_bundle.js:2589` (compiled from `ui_kits/admin/AdminApp.jsx`)
carries `DISABLED = [{ key:'extensions', label:'Extensions', note:'Disabled until
the feature flag is enabled' }]` — production's pinned string, byte-for-byte, with
the source comment "*The reserved 'Extensions' entry renders in its production
disabled state*". `ui_kits/*` is reference-only per the brief, so the
classification stands, but Stage 2 has a design precedent for the idiom and should
be told so rather than inventing one.

### M4 — S8 / S9 (`copy`, risk high): these are spec conflicts, not copies.

Per R11: dropping the member sidebar contradicts `ADMIN.md:588` "reuse the app
shell and tokens". A difference that cannot be copied without amending an
authoritative spec is not a plain "production changes to match" — Slice 0 must
name the spec text and produce the amendment, and `PHASE_5_STATUS.md` /
ADMIN.md's changelog must record it.

### M5 — S6 (`copy`, risk **low**): the blast radius is wrong.

`.pill-admin` is not only the head chip. `grep -rn "pill-admin" templates/admin`
shows three distinct labels: `Admin mode` (37 pages), `Recovery`
(`theme_safe_mode.php:11`), and — crucially —
`package_security.php:18`: `<span class="pill pill-admin">disabled</span>`, the
**execution-disabled emergency status pill**. Recolouring `.pill-admin` to
`--surface-review`/`--on-review` repaints an emergency kill-switch indicator in
the "needs review" amber. Risk is not low; the head chip needs its own class.

---

## C. MISSED

### N1 — Adopting `AdminNav` deletes the bell, search, identity and log-out from the admin surface.

`AdminNav.jsx:51-59` — `.admin-bar-id` contains exactly three children: brand
mark + wordmark, exit link, mode pill. `.admin-bar-mode { margin-left: auto }` is
the only right-hand element. There is **no** search form, no notification bell, no
user monogram/link, no settings gear, no log-out form — all of which
`templates/partials/topbar.php:12-52` renders today on every admin page. (Contrast
`AccountSettings.dc.html:30-34`, which *does* keep a right cluster: 30px monogram,
member name, `Log out`.) The report's S1 treats the topbar as retained and adopts
"only the design metrics", never noticing the design deletes the whole right
cluster on admin. This must be recorded as a deliberate `feature-added` retention
(keep them, style them in the idiom) or an accepted removal — it cannot be silent.

### N2 — Two sticky bars, one `top: 0`.

`.admin-bar` is `position: sticky; top: 0; z-index: 20` (`components.css:328`).
Production `.topbar` is `position: sticky; top: 0; z-index: 20` (`app.css:81-83`).
The report never states whether the admin bar *replaces* `partials/topbar.php` on
admin routes or stacks beneath it. If it stacks, it must become
`top: var(--topbar-h)` and every `calc(100vh - var(--topbar-h))` in R12's list
becomes `calc(100vh - var(--topbar-h) - 101px)`. Slice 1 cannot be scoped until
this is answered.

### N3 — `variant=console` leaves a dead hamburger.

`layout.php:58` (`<div class="nav-scrim" data-nav-scrim hidden>`) and
`layout.php:59` (`partials/sidebar`, which carries `[data-sidebar]`) are **inside**
the `variant === 'app'` branch. `partials/topbar.php:8-10` renders
`<button class="nav-toggle" … data-nav-toggle>` for every variant except `auth`.
`app.js:741-763` binds on `navToggle` alone (`if (navToggle) {`), so under
`variant=console` the hamburger would still bind, still toggle `body.nav-open`,
still flip `aria-expanded`, and open nothing. (`app.js:806` and `:866` correctly
guard `setNav`/`navToggle` for the admin drawer, so that path is safe.) Slice 1
must suppress the toggle in the console branch.

### N4 — Putting the console chrome on `/mod/*` collides with authz and with ADMIN §9.1.

S49 says the four `/mod` templates "must wear the console chrome". But
`ReportController::queue` (`src/Controller/ReportController.php:38-40, 76-80`)
gates on `requireModerationQueue()` + `requireUser()`, **not** `requireAdmin()`;
`ADMIN.md:559` states "Moderators see a **reduced Console** scoped to their boards
(Reports, Audit, limited People); Admins see everything"; ADR 0023 D1 makes
browsing a staff surface without authority a **404**. A board moderator wearing
the 10-area admin tier would be shown ten destinations that all `403` via
`Controller::requireAdmin()`, which also violates ADMIN §9.4 "least privilege in
the UI — hide what a role can't do rather than show-and-deny". The tier must be
role-filtered. S49 says none of this.

### N5 — The account rail is missing `aria-current` and an accessible name.

`templates/partials/settings_nav.php:29` is `<nav class="subnav settings-rail">` —
**no `aria-label`**. Line 31 is
`<a class="<?= $here === $href ? 'active' : '' ?>" href="…">` — **no
`aria-current="page"`** (and an empty `class=""` when inactive).
The design has both: `AccountSettings.dc.html:49`
`<nav aria-label="Settings sections">` and `aria-current="page"` on every active
rail item. Production's *admin* nav does emit `aria-current` (`_nav.php:79`), so
this is a production self-inconsistency the report's §6 state inventory scored as
"present — restyle only". It is a real copy difference and an a11y defect inside
Slice 4's blast radius.

### N6 — Fiction already shipped in production, inside the report's own declared scope.

The report asserts "No other fiction appears in the shell region." Its own
production targets and slice file lists contain:

| Production string (verbatim) | Location | In which slice |
|---|---|---|
| `Et Eärello Endorenna utúlien.` | `templates/layout.php:74` (`variant=auth` colophon) | Slice 1/5 touch `layout.php` |
| `Warden's table` (×4) | `templates/mod/reports.php:18`, `approvals.php:12`, `appeals.php:12`, `user.php:27` | Slice 1 rewrites all four heads |
| `Council record` | `templates/appeals/index.php:5` | Slice 4 names this file |
| `…before the council sees the updated hall.` | `templates/admin/branding.php:20` | Slice 3 touches admin heads |
| `Welcome back to the council` / `Your seat at the council is ready.` | `templates/auth/login.php:4`, `auth/verify.php:7` | shell-adjacent |
| `The council` | `templates/leaderboard.php:5` | out of scope |
| `Private counsel` / `in counsel` | `templates/dm/*` and `partials/dm_list.php`, `dm_rail.php` | out of scope |

Recommending "Council" → "Community" for one rail heading while Slice 1 and
Slice 4 carry `Warden's table` and `Council record` forward unremarked is
internally inconsistent with the brief's #1 rule.

### N7 — Design-side fiction and sanctioned non-fiction alternatives the report missed.

- `Europe / Rivendell` — `AccountSettings.dc.html` Notifications pane, digest
  timezone `<option selected>`. Proposed: a real IANA zone.
- `admin.card.html:46` demonstrates the exit label and mode pill as **parameters**,
  with a non-fiction variant already authored:
  `<AdminNav area="settings" modeLabel={null} backLabel="Leave admin" …>`.
  "Leave admin" is a design-sanctioned production string; the report proposes
  "Back to the forum" without noticing one already exists in-system.
- `ui_kits/admin/AdminApp.jsx` (via `_ds_bundle.js:2610-2617`) uses
  `Back to the inbox` and the `RetroBoards` wordmark — a second in-system
  non-fiction precedent.

### N8 — `.eyebrow` also lives in the *generated* `imladris.css`; the build guards it.

`public/assets/imladris.css:279-285` is the generated `.eyebrow`, driven by
`--text-eyebrow` (`:249`) and `--tracking-caps` (`:234`); `public/assets/app.css:37-43`
is a hand-written duplicate. `composer check:imladris` / `verify:imladris`
(`composer.json:43-46`) and `config/imladris-runtime-baseline.json` fingerprint the
generated file. Slice 3's action ("Change size and colour" in `app.css`) never
names `imladris.css`, the token, or the digest refresh — Slice 1 refreshes the
digest, Slice 3 does not, and Slice 3 is declared independent of Slice 1.

### N9 — The active tier pill is not a link.

`AdminNav.jsx:70`: `href={active ? undefined : …}`; `components.css:342`:
`.admin-tier-item.is-active { … cursor: default; }`. Production's active nav item
is a live `<a href>` with `aria-current="page"` (`_nav.php:79`). Small copy
difference, and it interacts with S36's "render anchors, not buttons": the *active*
item must be an anchor **without** href, or a span.

Related: the report's S28 prescribes "3px `--accent-2` → 2px `--gold-500`;
asymmetric radius. **Same change applies to `.admin-nav-link.active`**". Under the
current design the tier item has *no* left rule at all — it is a pill with
`border-radius: var(--radius-md)`, `background: var(--brand-subtle)`,
`color: var(--on-brand-subtle)`, `font-weight: var(--weight-medium)`. Production's
`.admin .subnav .admin-nav-link.active` (`app.css:2908-2912`) already sets exactly
that background and colour; the change is to *drop* the inset rule and add the
weight, not to restyle it as a 2px gold rule. S28's second sentence is wrong.

### N10 — Accessible-name collision across the two ranks.

Design: tier is `aria-label="Admin areas"` (`AdminNav.jsx:60`); the /admin tab
strip is `aria-label="Admin sections"`. Production already uses
`aria-label="Admin navigation"` (`_nav.php:56`) and the literal text
`Admin sections` twice — on the drawer toggle (`_nav.php:53`) and the drawer head
(`_nav.php:58`). Landing both design labels leaves three navs and two controls
competing for near-identical names on one page.

### N11 — The container max-widths did *not* standardise on 1160px.

The report: "the current generation standardises on `1160px` / `22px 28px 110px`.
Stage 2 should ship one metric". Post-rewrite the padding did standardise, but the
widths did not: overview/members/features/integrations/packages = **1160px**;
content/people/appearance/settings = **1100px**; notifications = **1140px**.
Either the spread is deliberate (per-screen density) or it is drift; Stage 2 must
ask rather than assume.

### N12 — The announcement banner has no home in the design shell.

`layout.php:53-55` renders `partials/announcement_banner` between the topbar and
the shell on every non-auth variant. The design shell has no such band. A
`variant=console` branch has to place it explicitly.

### N13 — The locked no-JS mobile fallback is not in the drawer inventory.

S15 lists "44px control, `inert`, Tab containment, Escape/scrim/link close, focus
restore, `body { overflow: hidden }`, resize cleanup". It omits the PE half:
`app.css:3292-3301` turns `.admin .admin-subnav` into a static two-column grid
above the pane when JS is unavailable (`.has-js` gates every drawer rule; comment
at `:3291`: *"Without JS the grouped directory stays expanded above the page."*).
A horizontal tier changes what "expanded above the page" means; that is the part
of the contract most at risk in the port.

### N14 — The design's error/empty copy is unquoted.

§6 names the states but quotes none of the strings a Stage-2 author would need:
`AdminOverview.dc.html:211` `The log could not be read` /
`:212` `The audit trail is append-only and intact — this is a read failure, not a
gap in the record.` / `:213` `Try again`; `:243` `Nothing matches these filters` /
`:244` `The record is complete; this slice of it is simply empty.` / `:245`
`Reset filters`. (Screen-body, so arguably F-agent territory — but §6 claims to be
the state inventory.)

---

## D. What survives — verified correct

Opened and confirmed: `layout.php` branch structure and every cited line (`:20`,
`:23`, `:51`, `:53-55`, `:56-64`, `:61`, `:76-81`); `partials/topbar.php:11`
brand fallback and `:29-52` right cluster; `admin/_nav.php` 8 groups / 26 items,
the `$disabledNote` at `:5`, the disabled span at `:81-84`, drawer markup at
`:52-59, 92`; the pinned note is genuinely regression-locked at
`tests/Integration/Core/AppAdminDashboardRemediationTest.php:137`;
`partials/settings_nav.php` item set and order; `app.css` `.admin` 1260px/`:2808`,
padding `:2811`, `.admin-head` `:2813-2821`, h1 1.9rem, `.pill-admin{margin-left:auto}`
`:2832-2834`, `.settings` 188px + `calc(var(--topbar-h)+22px)` `:2054-2057`
(= 84px, an exact match with the design), `.settings-screen` 1000px/`26px 28px 64px`
`:2602-2606`, mobile h1 1.65rem `:3289`, drawer block `:3278-3387`;
`imladris.css:348-351` tokens; `app.js:765-875` drawer behaviour;
zero inline `style=` in `templates/`; `SecurityHeaders.php:41`
`script-src 'self'; style-src 'self'`; `FeatureFlags.php:100`
`server_extensions => false`; `PRODUCTION_PARITY.md:25` "disabled nav entry only —
by rule"; every route cite (`App.php:2210` `/admin/audit`, `:2216`
`/admin/invitations`, `:2221` `/admin/roles/simulator`, `:2303` `/admin/features`,
`:2324` `/admin/custom-emoji`); `theme_safe_mode.php:5` `variant=plain` + `_nav` at
`:13`; nav-key inconsistency `package_publisher.php:14` vs
`package_security.php:11`; the "8 of 39 / 7 of 39" counts (42 files minus three
`_`-prefixed partials = 39 pages — correct); "all 13 account pages carry an
eyebrow" — correct; `--gold-050` exists nowhere; `AdminPeople.dc.html:146`
`All roles` back link; `grep "Back to" templates/admin templates/account` → exactly
two in-section hits.

**S50 confirmed and worth escalating.** `AdminThreadIntelligenceController::index`
(`src/Controller/AdminThreadIntelligenceController.php:14-20`) and all seven POST
handlers (`:23`, `:31`, `:39`, `:47`, `:56`, and two below) call only
`requireAdmin()`. Routes are registered unconditionally (`App.php:2303-2311`).
Peers all throw: `AdminApiTokenController.php:16-17`,
`AdminWebhookController.php:20-21`, `AdminInvitationController.php:78-79`,
`AdminRoleController.php:29-30`, `AdminExtensionController.php:20-21`. The nav
entry is gated (`_nav.php:48`) but the route answers 200 with `community_memory`
and `automated_context` both dark. Brief constraint 6. File separately.

**No binding deferral is silently reverted** by the report's proposals. ADR 0021's
ten deferrals and ADR 0023's four are all behavioural/feature deferrals untouched
by shell work; ADR 0023 shipped item 6 (the Moderation group) is explicitly
preserved by S12. The conflicts found are with **ADMIN.md §9.2 and §9.4**, not
with the ADRs — see R11 / M4.

---

## E. Net effect on the slice plan

- **Slice 0**: drop item (e) (R2 — the CSS exists). Add: (g) does `AdminNav`
  replace or stack under `partials/topbar.php` (N2); (h) does the admin surface
  keep bell/search/identity/log-out (N1); (i) the ADMIN §9.2/§9.4 amendment for
  tier-over-left-nav and for dropping the member rail (R11/M4); (j) role-filtered
  tier for non-admin moderators on `/mod/*` (N4); (k) the 1100/1140/1160 spread
  (N11).
- **Slice 1**: add suppression of `[data-nav-toggle]` under `variant=console` (N3)
  and the no-JS grid fallback to the test list (N13).
- **Slice 2**: re-scope from "author a new rank" to "generalise `.mod-subnav`"
  (R4/M1) — 2px padding, .02rem type, one margin.
- **Slice 3**: strike the eyebrow-coverage work and the `one .eyebrow` assertion
  (R6); route any `.eyebrow` change through `--text-eyebrow` + the digest (N8);
  give the head chip its own class so `package_security.php:18` is not repainted
  (M5); drop the `--surface-staff` rider (R8).
- **Slice 4**: re-derive the `Composing` placement from
  `AccountSettings.dc.html:349-354` (M2); add `aria-label` + `aria-current` to the
  rail (N5); resolve `Council record` at `appeals/index.php:5` (N6).
- **Slice 5**: three declarations, not a restyle (R7).
