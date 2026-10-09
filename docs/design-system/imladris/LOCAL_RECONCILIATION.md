# RetroBoards runtime reconciliation

## 2026-09-27 — retain recent delivery assets (ADR 0041)

The runtime digest includes two restored fingerprinted application stylesheets
from the preceding two asset releases. They retain their exact committed bytes
to support pages received before a deployment. The final baseline incorporates
the verified profile follow-up release `0f7b7486`; current source CSS, templates,
entrypoint URLs, and the authoring mirror match that release. The existing
mobile drawer, hidden skip link, and desktop shell contract remain authoritative.

Imported from `imladris-design-system.zip` with SHA-256
`2ee3201e3bfcaa82ed371af8709fd0737a54c69332119d006f6f0a51aa57dbeb`.

**Local tidy 2026-09-23:** `_scratch/production-inventory-notes.md` — the Stage-1
execution plan the changelog already records as "fully carried out", referenced
nowhere else — moved to `_archive/production-inventory-notes.md` and the empty
`_scratch/` removed. No synced or generated artifact changed.

The bundle inspected RetroBoards at `4efe4e33`. The consuming application was
at `6d81da590a12bd09bb8d0e282c042aa03d755a94`, whose only UI-contract delta was
the read-only readiness classification on `/admin/features`.

The local source mirror therefore carries two compatibility corrections before
runtime generation:

- The admin UI-kit seed and compiled preview use the production readiness
  classifications from `6d81da5`.
- `--gold-800` remains in the token ramp because the production staff badge and
  monogram variants consume it. It is now reached through `--on-staff` rather
  than directly.
- The status ledger carries a `--surface-staff` / `--on-staff` pair in both
  registers, and `.badge-staff` paints from it. The authoring bundle painted the
  badge from the numbered ramp (`--gold-700` ink on `--gold-100` ground), which
  measures 3.55:1 against a 4.5:1 requirement; because the twilight register
  remaps only the semantic gold tokens and never the numbered ones, that pair
  also rendered an unflipped light-register chip on a dark page. The semantic
  pair clears AA in both registers (6.25:1 light, 8.3:1 dark) and flips.

Neither preview JavaScript nor archived application snapshots are runtime
inputs. `resources/imladris/manifest.json` records the allowlisted closure.
The authoring bundle's global reduced-motion timing fallback is also filtered
from the generated layer: its `!important` declarations would invert cascade
layer priority, while the application already owns global and feature-specific
reduced-motion behavior.

The application-owned `config/imladris-runtime-baseline.json` records a
normalized digest across the server-rendered templates, browser CSS/JavaScript,
the `USER.md`, `ADMIN.md`, `COMMUNITY.md`, and `COMPOSER.md` surface specs, and
`FeatureFlags.php`. `composer verify:imladris` fails if that surface changes
after this reconciliation. Refreshing the digest is an explicit design-contract
review step, not an automatic part of the asset build.

## 2026-08-03 — refresh from the live design project (ADR 0024)

The mirror was one sync behind project `c3e02753-607c-40b6-994c-9ba1a65bb367`. It was
refreshed for the admin/account adoption: the four missing admin screens
(`admin-features`, `admin-integrations`, `admin-members`, `admin-packages`),
`components/admin/AdminNav`, `PRODUCTION.md`, `REDUNDANCY-AUDIT.md`, `github.md`,
and the six admin screens + `AccountSettings.dc.html`, whose per-screen topbar and
`Operator desk · <Area>` eyebrow were replaced upstream by a shared `AdminNav` import.
`ui_kits/admin/` and `feature-ui/{polls,tags,moderation}/` were deleted upstream and
are retained here as reference only — see `RETIRED.md`.

Three upstream states were **deliberately not taken**, because the mirror is ahead:

- **`tokens/colors.css`** — the semantic `--surface-staff` / `--on-staff` pair above
  stays. Upstream still paints `.badge-staff` from the numbered ramp (3.55:1, does not
  flip). Upstream's new `.presence-staff` rule reintroduces the identical numbered-ramp
  pairing; it is patched here on the same grounds and **raised upstream** rather than
  silently corrected a second time.
- **`production-contract.json`** — upstream regressed `group_dms` to `implemented_dark`
  (it graduated default-on 2026-07-18, ADR 0022) and dropped
  `reconciled_through_commit`, which `ImladrisRuntimeAssetTest` pins to the literal
  `6d81da590a12bd09bb8d0e282c042aa03d755a94`. Never bump that value.
- **`manifest.json`** — upstream correctly re-files the ADR 0021/0023 remediation gaps
  from the retired `ui_kits/admin` against `templates/admin-*`, but a non-empty
  `unresolved_gaps` makes `check:imladris` red. Those gaps are what ADR 0024 closes; the
  manifest adopts the upstream form at closeout, not before.

`components.css` gained three upstream sections (`.admin-bar`/`.admin-tier`,
`.thread-list.is-board`, `.presence-widget`). Taking it requires
`composer build:imladris`, which regenerates production assets, so it lands with the
console-chrome slice rather than with the documentation refresh.

## 2026-08-08 — two mirror facts corrected, and one artifact declared inert

Both found by the verification audit that followed Slice 13, and both are cases of the
mirror reading as more authoritative than it is.

- **`README.md` provenance corrected to `4efe4e33` (2026-07-14).** It named `3fa5704e`
  "(main, 2026-08-02 — see `manifest.json`)" while `manifest.json:6-7` records
  `4efe4e33db6475ce9c59190ba82c72cbd7d4b868` / `2026-07-14`. The README cites the
  manifest as the source of that fact, so the manifest wins; `3fa5704e` is a merge of an
  unrelated Fly DB-connection PR and is implausible as an inspection anchor, whereas
  `4efe4e33` matches the manifest's own `inspected_at` to the day. Now pinned by
  `ImladrisRuntimeAssetTest::test_design_mirror_provenance_is_self_consistent`, so the
  two cannot drift apart again. **Raise the correction upstream** rather than letting the
  next sync reintroduce it.
- **`_adherence.oxlintrc.json` is an upstream authoring aid, not a production gate.**
  608 lines of `react/*` and `no-restricted-imports` rules over `components/**`, i.e. it
  lints the design system's own JSX. Nothing in this repo references it (`git grep`
  returns only the file itself) and nothing should: production ships no JSX, and the
  design-system React is never built here. It is kept — deleting a mirrored file only
  creates sync drift, and it will be re-added on the next sync — but it is recorded here
   as **inert by design** so it stops reading as unenforced enforcement. The old
   `PRODUCTION_PARITY.md` and `RUNTIME_CONTRACT.md` contents are now compatibility
   pointers to `PRODUCTION.md`; current flag truth comes from `FeatureFlags::DEFAULTS`
   and runbooks, not the imported JSON snapshot. What *is* enforced lives in
   `ImladrisAssetBuilder` and `ImladrisRuntimeAssetTest`.

## 2026-08-08 — the design screens are now digested

`config/imladris-design-baseline.json` records a sha256 over
`docs/design-system/imladris/{templates,components}/**` (excluding the binary
`.thumbnail` previews). Only the five files in `ImladrisAssetBuilder::CSS_SOURCES` were
builder inputs before this, so the screens — the things every slice actually adopts
against — could change on a sync with no gate noticing.

**Whoever syncs the mirror refreshes this digest in the same commit:**

```bash
php bin/build-imladris-assets.php --print-design-digest
```

It is a change detector, not a fidelity proof: it says "the design you adopted against
has moved, go and re-review", nothing more. It deliberately does **not** live in
`config/imladris-runtime-baseline.json`, which is refreshed once per merge on `main` by
the merger (ADR 0024 obligation 4) and which no slice branch may contain a change to —
the design surface moves on the mirror's cadence, not the merge cadence.

## 2026-08-09 — chamfered frames taken, four upstream hunks held back

`HANDOFF-chamfer-corners.md` (project `c3e02753-607c-40b6-994c-9ba1a65bb367`) fixes the
"disconnected corners" report: the chamfer is cut with `clip-path`, but the rule was an
inset `box-shadow`, which follows the border **box**, so its straight runs shot past the
chamfer tangents and the clip sliced them into four stubs. Each frame now draws one
closed octagon as eight background layers — four corner tiles carrying the diagonal run,
four stretched layers carrying the straight runs at `calc(100% - 2 × chamfer)`. Taken for
all five frames: `.input-engraved` / `.textarea-engraved`, `.choice-card`, `.scribe-panel`
(outer octagon plus a `::before` inset 4.5px, its chamfer shortened to 12.1px because
offsetting an octagon inward by *d* shortens the chamfer by *d*(√2−1)), and `.field-row`.

The handoff describes its diff as "`components.css` only … no new selectors". That is
true of the design project's own history, not of the gap to this mirror, which is four
syncs behind. **Four further hunks were held back**, and the mirror is now a deliberate
partial sync:

- **The AdminNav operator-cluster rewrite** — `.admin-bar-right`, `-search`, `-bell`,
  `-bell-count`, `-user`, `-username`, `-signout`, plus reshaped `.admin-bar-brand`,
  `-wordmark`, `-mode` and `.admin-tier`. Upstream's version **drops** the
  `.admin-bar-wordmark` ellipsis truncation and both media blocks (900px `.admin-bar-id`
  height/padding and `.admin-tier` padding; 860px `.admin-bar-id { flex-wrap: wrap }`)
  that `3c5d096` added as ADR 0023 admin-UI-audit remediation. `app.css` styles neither
  `.admin-bar-id` nor `.admin-bar-wordmark`, so nothing downstream compensates and taking
  it would revert that remediation. The application already owns the cluster itself in
  `app.css` (ADR 0024 decision 3), so this hunk is the CSS half of a console-chrome sync
  that also needs `AdminNav.jsx` / `.d.ts` / `admin.card.html` — upstream's card already
  demos `viewer`, `notificationCount` and `role` props the mirrored `AdminNav.d.ts` does
  not declare. **Raise the two responsive rules upstream** rather than re-fixing them.
- **`.btn { white-space: nowrap }`** — the handoff reports this as `.admin-bar-signout`
  gaining `nowrap`; upstream actually put it on the base `.btn`, and `.admin-bar-signout`
  is a new selector inside the held-back cluster. `app.css`'s own `.btn` sets no
  `white-space`, so the layered rule would reach every button in the application. That is
  a global paint change, not a corner fix.
- **`.badge-staff` border → `color-mix(in srgb, var(--on-staff) 30%, transparent)`** —
  good news: upstream has adopted the semantic `--on-staff` / `--surface-staff` pair
  raised on 2026-08-03, and now derives the border from it so it flips with the register
  too. Worth taking, but it repaints every staff badge and belongs with a review of the
  staff chip, not with a paint-only corner fix.
- **Removal of the `.presence-staff` local-correction comment** — the companion to the
  above; upstream kept the corrected rule, so that comment is on its way to stale. It
  stays until the `.badge-staff` hunk is taken, so the two move together.

Upstream's `.choice-card` section carries the same comment twice, once as "Checked &
focus" and once as "Checked and focus". Taken verbatim — hand-editing the mirror only
creates drift — but worth raising upstream.

### The design layer does not paint these frames — `app.css` does

Worth recording, because the handoff assumes otherwise and the mirror sync alone changed
nothing on screen. `public/assets/app.css` hand-maintains its own copies of all five
chamfered frames, and it is **unlayered**, so it beats `@layer imladris.components` on
every property it declares no matter what `imladris.css` says. Syncing the mirror fixed
the design system; the running app kept its stubbed corners until the same eight-layer
construction was ported into `app.css`, which is where this slice actually lands the fix.

Two frames outside the design system's five carried the identical defect and were fixed
with it: **`.variant-auth .auth-card`** (16px chamfer, doubled rule at 5px inset, so the
inner ring uses 16 − 5(√2−1) ≈ 13.93px — it was the most visible instance, on every auth
screen) and **`.dm-form:not(.composer-shell) .composer-input`**, which is geometrically
the same 9px well as `.input-engraved` and is now carried by those rules rather than
restating the layers a third time.

Three cascade hazards found while porting, all now handled — worth knowing before editing
any of these rules:

- The superseded `.choice-card` block near the top of `app.css` still sets
  `box-shadow: inset 0 0 0 1px var(--accent)` on `:has(input:checked)` at the same
  (0,2,1) as the Imladris block ~6,900 lines below. It only loses the properties the
  later rule restates, so the checked card needs an explicit `box-shadow: none` or the
  border-box ring — the stubbed corners — comes straight back.
- The generic `.composer-input:focus, .input:focus, …` rule sets the outer focus ring
  alone at (0,2,0). `.input-engraved:focus` matches at the same specificity, so it has to
  restate `box-shadow: var(--shadow-inset)` or a focused engraved well silently loses its
  inset depth.
- Any `background:` shorthand on these selectors resets `background-image` and wipes the
  eight rule layers. Use `background-color:`.

Three outer rings were dropped rather than kept as dead declarations, on the grounds the
handoff gives for `.choice-card`: `clip-path` cuts everything outside the octagon, so
`0 0 0 3px var(--focus-ring)` on `.input-engraved:focus`, on the former
`.input-engraved:user-invalid:focus` (which then became identical to `:user-invalid` and
was merged into it), and on `.choice-card:focus-within` had never rendered. Focus is
carried by the rule going to `--gold-500` / 2px `--accent`. `.auth-card` keeps
`var(--shadow-xl)`, which the same clip has always cut — left in place as declared intent
rather than widening this slice further, but it is decorative and equally inert.

### Baselines

`config/imladris-design-baseline.json` is **unchanged** and needs no refresh:
`components.css` sits at the mirror root, outside `design_surface.roots` (`templates`,
`components`). The handoff says as much for a CSS-only sync, and the digest confirmed it
(`d7f5e616…` before and after).

`config/imladris-runtime-baseline.json` is deliberately **not** touched. The handoff
predicts `check:imladris` drift from regenerating `public/assets/imladris.css`; that
prediction pre-dates the exclusion — that file is now listed in
`application_surface.excluded`, so it no longer moves the digest. The drift this slice
does produce comes from `public/assets/app.css`, which is inside the surface. Per ADR 0024
obligation 4 the merger refreshes that baseline once per merge on `main`, and a slice
branch containing a change to it is a merge blocker, so `check:imladris` reporting
*"Production presentation changed after Imladris reconciliation"* is the expected and
correct state of this branch. `ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_…`
asserts `check()` is empty, so it carries the same drift as a red test — one failure in an
otherwise green suite, and the same thing the CLI is saying, not a second problem.

**For whoever merges this: refreshing the baseline is two steps, not one.**
`resources/imladris/manifest.json` embeds the digest as
`application_baseline.surface_sha256`, and `expectedFiles()` writes it from the *live*
digest while refusing to build unless that equals the baseline — so the two are always in
lockstep, and they are here, both at `d39ee5a0…`. Refresh the baseline and stop, and
`check()` gets past its early return only to report *"Generated file is stale:
resources/imladris/manifest.json"*. Refresh the baseline **and** re-run
`composer build:imladris`, then commit both. Rehearsed on this branch: that sequence takes
`check:imladris` to exit 0 and `verify:imladris` to 48/48 green.
## 2026-08-27 — thread-view round-2 sync; four hunks held back, one taken back from us

Imported from `CommunityForumDesignSystem.zip` (`design_handoff_thread_view/`), the
round-2 thread-view handoff against project `c3e02753-607c-40b6-994c-9ba1a65bb367`. It
ships `tokens/*.css`, `components.css`, the `templates/thread-view/` screen and its three
runtime JS files, and seven components inlined as text in `components/SOURCE.md` (the
bundle explains the inlining: loose `.jsx` in a design-system project is compiled and
collides with the real components on the same window namespace).

`config/imladris-design-baseline.json` is refreshed in the same commit as this sync:
`d7f5e616…` → `3155990f…`. The screen's three `.js` files move it on their own —
`design_surface` declares `"extensions": []`, which `ImladrisAssetBuilder::digestApplicationSurface()`
reads as *every* file under `templates/` and `components/`, and only the binary
`.thumbnail` previews are skipped by name. `config/imladris-runtime-baseline.json` is
**not** touched: the application surface is byte-unchanged by this commit, which is why
the sync had to land before the production slice at all (see the ordering note below).

### One local correction retired into upstream

`tokens/colors.css` — upstream has adopted the semantic `--surface-staff` / `--on-staff`
pair raised on 2026-08-03 and now carries our reasoning verbatim in its own comment
("gold-700 ink on gold-100 measures 3.55:1 … the twilight block remaps only semantic
tokens"). The replacement comment is taken; the correction is now upstream's.

### Four hunks held back

- **`components.css` — the `.tier-legend` / `.tier-loremaster` / `.tier-veteran` revert.**
  The loudest one. Upstream reverts all three tier chips from semantic pairs back to the
  numbered ramps (`--gold-700` on `--gold-100`, `--green-800` on `--brand-subtle`,
  `--river-700` on `--river-100`) and **deletes the eight-line comment explaining why they
  were changed**. Re-verified against `tokens/colors.css` rather than taken on the mirror's
  word: every numbered primitive in that set appears only inside `:root` and is never
  redeclared in the `[data-theme="dark"]` block, while every semantic token we substituted
  is declared in both. Taking it would put a light-register chip on a twilight page again,
  and put dark ink on the dark brand wash. Held, and **raised upstream** — this is the
  second time the same class of numbered-ramp pairing has come down from the authoring
  bundle (cf. `.badge-staff` 2026-08-03, `.presence-staff` 2026-08-09).
- **`tokens/colors.css` — the twilight `--surface-staff` / `--on-staff` re-tune**
  (`rgba(194,154,68,.16)` → `.18`, `var(--gold-200)` → `#EBDAAC`). Upstream keeps the
  semantic pair but de-tokenises the ink to an off-ramp literal that sits 1/1/4 from
  `--gold-200`, i.e. visually identical, and every measured ratio drops: 8.28 → 8.07 over
  `--surface-raised`, 9.34 → 9.09 over `--surface-page`, 7.03 → 6.88 over
  `--surface-sunken`. The `8.3:1` this file pins above is that 8.28. Held; the `--scrim`
  addition in the same hunk **is** taken.
- **`components.css` — the AdminNav operator-cluster rewrite** (`.admin-bar-right`,
  `-search`, `-bell`, `-bell-count`, `-user`, `-username`, `-signout`, plus reshaped
  `-brand` / `-wordmark` / `-mode`). Held for the third sync running, on the grounds
  recorded on 2026-08-09: upstream still drops `max-width: 100%` from `.admin-bar-brand`
  and still deletes both media blocks that `3c5d096` added as ADR 0023 remediation, and
  `app.css` styles neither `.admin-bar-id` nor `.admin-bar-wordmark`, so nothing
  compensates. The `.admin-tier` scrollbar sliver in the same diff hunk (`--border-hair` →
  `--border-soft`, twice) is **separable and taken**: it touches neither element and
  `--border-soft` flips.
- **`components.css` — removal of the `.presence-staff` local-correction comment.** Bound
  to `.badge-staff` by the 2026-08-09 entry ("it stays until the `.badge-staff` hunk is
  taken, so the two move together"). `.badge-staff`'s border → `color-mix(in srgb,
  var(--on-staff) 30%, transparent)` is still a staff-chip repaint that belongs with a
  review of the staff chip, and is inert anyway — `app.css:1631` declares the same
  selector unlayered — so both stay held.

Two further one-line hold-backs, both **inlining artifacts rather than upstream intent**:
`components/identity/Monogram.d.ts:4` and `components/forum/Post.d.ts:4` lose their
` * @startingPoint …` host directive in `SOURCE.md`'s text form, leaving an empty `/**` /
`*/` docblock. `_ds_manifest.json` still carries the generated `startingPoints` entries
with those exact `section` / `subtitle` / `viewport` strings, so taking the deletion would
desynchronise the manifest from the file it is generated out of and drop both components
from the gallery. Both lines restored; every other hunk in those two files taken.

`components/identity/StarButton.{jsx,d.ts}` needed no sync at all — after CRLF
normalisation the mirror was already byte-identical to the bundle, ✦ glyph and all. The
handoff's B3 offender was never the component; it was the **screen**, which hand-rolled a
`★` span, and taking `ThreadView.dc.html` is the whole of B3's mirror-side work. That file
also clears a real collision: the mirror carried `@template name="Council topic"` twice,
against `_ds_manifest.json`'s own `{"name": "Thread view"}` for this folder.

### Ordering: the sync cannot ride with the production slice

`build()` calls `expectedFiles()` on its first line, and `expectedFiles()` throws
*"Production presentation changed after Imladris reconciliation"* the moment `templates/`
or `public/assets/` drift from `config/imladris-runtime-baseline.json`. A slice branch may
not contain a change to that baseline, so on any branch that edits both the mirror and the
application, **`build:imladris` has to run before the application edits exist** — the
handoff's Part C says "do this first" without saying that the builder enforces it. This
commit is therefore the mirror sync alone, on a byte-clean application surface; the
thread-view production slice lands on top of it.

### Taken but currently inert, recorded so nobody hunts for the pixels

The four genuinely new tokens have no consumer anywhere: `--text-fine`, `--scrim`,
`--pane-w`, `--pane-min` are ledger additions, and `app.css` hard-codes the scrim colour in
six places rather than reading a token. `--text-chip: 0.62rem → 0.7rem` is likewise a
change to a **dead** custom property — `var(--text-chip)` has zero consumers in `app.css`,
in the generated `imladris.css`, and in the mirror's own `components.css`, all three of
which paint chips from a literal `.62rem`. Upstream's own `components.css:62` still says
`.62rem` too, so the "scale floor" the handoff describes does not yet exist in either
system. Moving it is a separate, deliberate slice against three literals — and note
`app.css`'s `.tag` already sets `.6rem`, below the proposed floor. Do **not** sed
`.62rem` → `.7rem`: `ImladrisRuntimeAssetTest` pins one of the eleven sites.

One taken hunk does change pixels downstream and is worth knowing about before the next
form slice: `.field-cell` gains `gap: 4px` from the layer, and `app.css:320` already gives
`.field-cell > .field-error` a `margin: 4px 0 0` that the layer cannot override. Left as
found here rather than widened into a form slice; it is 4px of extra air under a rejected
field, not a break.

## 2026-09-12 — presence handoff synced per hunk; the member chrome adopted, the bridge's shell section retired (ADR 0032)

Initial bundle `imladrisdesignsystem.zip` (`design_handoff_presence`), SHA-256
`e3abeeab808344186e2d38094b81c3468a4a0573323b78dca2a9c308d1dbce57`, authored against
`966a5b1c`. The design digest was refreshed in the same change,
`db7e73d6` → `548996ef`. The README asks
for a path-for-path copy of `design/`; the bundle's `components.css` is 1529 lines against
this mirror's 3207, so a copy would have deleted the production-transfer section and every
local correction below. It was reconciled hunk by hunk instead.

The current handoff is **`CommunitySystem.zip`**, SHA-256
`3f7636a701f447bbdc04f7d8fea29d9aab00d9445e3da783ebaa6e5cfde43c0e`.
Comparing both archives confirms that all 21 files under `design/` are byte-identical;
the current archive adds six reference screenshots and expands the README with their
descriptions and capture caveat. `_archive/design_handoff_presence/README.md` now
preserves that current README byte for byte, including the screenshot guidance. The
initial archive remains the provenance for the existing source reconciliation; the
current archive is the reference for this review. The supplied screenshots are design
references retained under `_archive/design_handoff_presence/screenshots/`,
not evidence of the PHP implementation.

The current mirror comparison accounts for every bundled design file: nineteen match
byte for byte after dropping the documented `.txt` suffix; only `components.css` and
`tokens/colors.css` retain the local differences itemized below. The mirror remains
source-only: prototype tags, React, and the bundled `support.js` are not application
runtime code. Shared chrome is rendered through PHP partials and external CSS/JS;
runtime adapters and the presence deferrals are recorded in ADR 0032 and ADR 0031.

### Taken

- **`tokens/colors.css`** — `--presence-away` (`--gold-700` / `--gold-400`) and
  `--presence-offline` (`--ink-300`), both registers: the point of the bundle. The app's own
  declarations were retired with them except the system-dark register, which only `app.css`
  answers — `tokens/colors.css` flips under `[data-theme="dark"]` alone, and the `system`
  preference stamps that word on `<html>` and leaves `prefers-color-scheme` to the app.
- **`tokens/typography.css`** whole: the `--measure-{prose,narrow,wide,column}` family is
  additive and has no consumer in `app.css`. **`components/doc.css`** whole (reads it with
  fallbacks; not a runtime source).
- **`components.css`** — the dot modifiers, `.presence-dot-bare` (+ `.is-away`),
  `a.presence-person:hover { text-decoration: none }`, the `.monogram` (not `.monogram-sm`)
  binding, the rail ring on `.board-rail` / `.sidebar`, compact density, `.presence-you`,
  `.presence-where` without its `#`, `.presence-all` with its hover, and the member chrome
  (`.forum-bar*`, `.board-rail*`) — the chrome inserted after this mirror's own admin media
  blocks, which upstream's held AdminNav rewrite would otherwise replace.
- **`templates/users-online/*`** whole (the roll in the shared shell; `ds-base.js` is now
  idempotent on re-mount). **`templates/user-profile/UserProfile.dc.html`** whole — the
  handoff says only the dot changed, but this mirror's copy predated upstream's 2026-08-03
  profile (the twilight cover, `--gold-800` labels, Copy link / Block, Remove follower, the
  empty-connections copy), all of which `github.md` already records as corrected against
  production; taking the file brings the mirror current and adds `role="img"` with the away
  state.
- New **`components/presence/{PresenceList.jsx,.d.ts,.prompt.md,presence.card.html}`**,
  **`components/forum/{ForumNav,BoardRail}.{jsx,d.ts}`**, **`chrome.card.html`**;
  **`components/identity/Monogram.jsx`** (offline → `--presence-offline`). The `.txt`
  suffix dropped, as the README instructs.

### Taken in a local form

- The `[hidden]` guard (`.presence-widget[hidden], .presence-row[hidden],
  .presence-more[hidden], .presence-empty[hidden]`) lands **without the bundle's
  `!important`**: `ImladrisAssetBuilder::runtimeCss()` refuses the flag anywhere in a
  runtime source — a comment included — and inside the layer the attribute selector already
  outranks `.presence-widget { display: block }`. `users-online-remediation.spec.ts` measures
  that `[hidden]` computes `display: none`.

### Held back — upstream regresses a local correction, or the change is owed its own evidence

- **`tokens/colors.css`** twilight `--surface-staff: rgba(194,154,68,.18); --on-staff: #EBDAAC`
  — the re-tune held on 2026-08-27, same grounds. Upstream's deletion of the twilight
  `--artifact-link: var(--river-200)` remap is ours from the forum-inbox remediation
  (`12d9d10b`): the inbox's `.7rem` board references need 4.5:1 and `river-500` measures
  3.08:1 on the page.
- **`components.css` `.badge-staff`** border → `color-mix(--on-staff 30%)` — held on
  2026-08-27 as a staff-chip repaint; inert anyway (`app.css` declares the selector
  unlayered).
- **`.field > .field-hint, .dm-form > .field-hint` → `.field-hint`** — ours, from `428f3cb7`;
  production emits `.field-hint` outside both scopes (`admin/link_previews.php`) and would
  newly pick the rule up.
- **The AdminNav operator-cluster rewrite** — fourth sync running. Upstream now carries its
  own media blocks, but `templates/admin/_console.php` already renders `.admin-bar-right` /
  `-search` / `-mode` under app-owned rules; taking the layered versions is an admin-console
  change with no evidence run behind it.
- **`.tier-legend` / `.tier-loremaster` / `.tier-veteran`** back to numbered ramps — refused
  on the grounds of 2026-08-09 and 2026-08-27.
- **The thread-row block** (`.thread-list.is-ruled`, `:is([data-density="compact"]
  .thread-list:not(.is-board), …)`, `.thread-star` on `--star`, `.thread-board .hash`
  removed) — upstream's FIDELITY-AUDIT §1/§2 stream. It would newly apply seventeen
  compact-density rules to production's `.thread-list` under `[data-density="compact"]`: a
  visible change on its own surface, so its own slice and its own evidence.
- **`.hash { color: var(--gold-ink); … }`** removed from shared bits — load-bearing: seven
  templates emit `<span class="hash">#</span>` and `app.css` has no rule for it.
- **`.link-preview-action`** restructure (`.linkbtn` descendant → the element itself) — ours,
  from `428f3cb7`; `partials/post.php` emits `<form class="inline link-preview-action">` with
  a `.linkbtn` inside.
- **The bundle's `@@ -1307,1901 +1519,11 @@`** — the 1901 lines after the link-preview block
  are this mirror's production-transfer section, which upstream never had. Refused; the
  fidelity test pins it.
- The composer region (mirror lines 832–1271) diffs as a 440-line hunk only because the
  bundle's file carries CRLF endings there; byte-identical after normalisation, which the
  builder performs anyway.

### The bridge's shell section (ADR 0032)

The implementation renders the member chrome in the design's vocabulary.
The "Shared shell" and presence-widget parts of the 2026-08-27 transfer section were
removed from this file **and** from `app.css`'s bridge — the two are pinned byte-for-byte by
`AppImladrisFidelityTest` — leaving the route-scoped `.app-shell` / `.main` rules and the
surfaces. They are superseded by upstream's own `.forum-bar*`, `.board-rail*` and
`.presence-*` rules, which the runtime now styles the shell with. The fidelity test's
contract list drops `.topbar-primary` and `.compose-board-picker` (the picker's `<button>`
reset is app-owned now) and gains a test that `app.css` restates none of the retired
vocabulary.

### Account-name overflow follow-up

At 1100px on Inbox, the valid display name “Elrond Peredhel Keeper of the Last
House” pushed the account control beyond the viewport. ForumNav's right cluster
and account control now permit flex shrinking, and only `.forum-bar-username`
clips with an ellipsis. The avatar keeps its size; the account control and its
focus ring remain unclipped. These rules live in the shared `components.css`,
so the design component and generated runtime receive the same correction.

Production's native `.identity-menu` wrapper also needs `min-width: 0`. Its
absolutely positioned panel stays outside the clipped label, and the summary's
accessible name includes the complete display name. On phones, where the name
already hides, the icon cluster does not shrink: the primary route group takes
the remaining space, preserving the account control's focus-ring gutter.
`unified-chrome.spec.ts` checks saved 40- and 64-character names around the
1080px breakpoint, at 1280px, and on a phone in both themes, including keyboard
navigation and restoration of the original display name after every test.

### Completion-review corrections

The name-hidden bar at 901–1024px exposed a second flex case: the account
control could shrink to zero width while the search field still had room.
Within the existing 1080px breakpoint, `.forum-bar-right` now has
`flex-shrink: 0`; the search field yields that space. The desktop geometry and
the 1080/900/720 breakpoints remain the handoff's. The earlier duplicate phone
shrink rule in `app.css` is retired into this shared correction.

In the retained production transfer block, the positioned Inbox scope and row
menus were still hidden: the visible selector had one fewer class than the
open-state hiding selector. Naming `.inbox-scope-menu` / `.inbox-row-menu` in
the visible selector restores equal specificity, so the later rule wins. This
correction is identical in `components.css` and `app.css`, preserving the
byte-for-byte transfer contract. Browser checks now assert actual visibility
before measuring menu bounds.

The bundled `components/presence/PresenceList.prompt.md` is preserved exactly,
including its older location/loading examples. The bundle README's explicit
deferrals and ADR 0031 supersede those examples for production; the discrepancy
is recorded in ADR 0032 rather than silently shipping the sample behavior.

## 2026-09-13 — the chamfer is removed; the mirror now diverges from upstream on six frames (ADR 0033)

This supersedes the geometry half of the 2026-08-09 entry above. That entry is
still the correct record of *why* the eight-layer construction existed — keep it
— but its construction no longer ships, and its three cascade hazards are gone
with it:

- "An inset box-shadow cannot draw this outline" — moot. There is no octagon, so
  the outline is a border again.
- "`background:` shorthand resets the eight layers" — moot for the frames, and
  it was not a theoretical hazard: `.compose-title-input` and
  `.compose-board-select` set the shorthand, `.input-engraved:focus` re-supplied
  `background-image` at equal specificity without restoring
  `background-position`/`background-size`, and both controls painted **solid
  `--gold-500` on focus** on `/compose`. Removing the layers fixes it.
- "An outer focus ring is impossible under `clip-path`" — moot, and this was the
  more serious of the two. `.input-engraved`, `.choice-card` and
  `.search-query-well` each declared `0 0 0 3px var(--focus-ring)` that had never
  once rendered.

**What changed in `components.css`.** Six frames, ink and padding unchanged,
geometry replaced by a border and a radius: `.input-engraved`/`.textarea-engraved`
(9px octagon → `1.5px var(--gold-200)` on `--radius-md`), `.scribe-panel` (14px
octagon + the `inset: 4.5px` doubled rule → `1.5px var(--gold-400)` on
`--radius-lg`, no shadow), `.field-row` (8px octagon → `1px var(--gold-200)` on
`--radius-md`), `.choice-card` (11px octagon → `1.5px var(--border-soft)` on
`--radius-lg`), and the bridge trio `.search-query-well, .compose-title-input,
.compose-board-select` (clip → `border-radius: var(--radius-md)`, the inset ring
untouched). `.variant-auth .auth-card` has no mirror copy and changed in
`app.css` only. The now-vestigial `clip-path: none` reset on
`.composer-box .composer-input` is dropped from both files.

**The bridge held.** The trio sits inside the 2026-08-27 production-transfer
markers, which `AppImladrisFidelityTest` pins byte-for-byte against `app.css`.
The same one-line substitution was made in both files and the substring check
passes. The block stays token-only — `color-mix()` is fine there, literal
colours are not.

**Deliberate divergence from upstream, for the next sync.** The design canvases
still carry the chamfer: nineteen inline `clip-path: polygon(8px …)` in
`templates/account-settings/AccountSettings.dc.html`, two in `templates/compose/`,
two in `templates/reading-rooms/` and one in `templates/search/` — twenty-four in
all — plus `templates/member-surfaces/README.md:228` and the `ui_kits/` copies.
All are left alone, for two different reasons, which is worth keeping straight:

- Everything under `templates/` is upstream's record of what was designed **and**
  sits inside `design_surface.roots`, so editing it moves
  `config/imladris-design-baseline.json` for a change that paints nothing.
- The `ui_kits/` copies are **outside** `design_surface.roots` (the roots are
  `templates` and `components` only), so they move no digest. They are left alone
  because `RETIRED.md` governs them: `ui_kits/settings` is retired into
  `templates/account-settings` and its binding rule is "do not hand-sync changes
  into them". `ui_kits/auth` is *not* retired, so its `kit.css:30` chamfer and its
  README's "lapidary engraved inputs" line are genuinely stale — a small, separate
  correction, deliberately not bundled into a diff that is otherwise about
  production CSS.
**Consequence: a future bundle will offer the eight-layer frames back. Refuse
those hunks.** The chamfer is a local removal, recorded here and in ADR 0033,
not an upstream one. Note also that the canvases specify an **8px** chamfer where
production used **9px** — the two were never reconciled, and now never need to be.

**A second, narrower divergence: the set-gem toggles.** `.gem-field`,
`.gem-check`, `.toggle-stack` and the four jewel tones are **deleted from
`public/assets/app.css`** and **kept here**. That is deliberate and is not a
retirement of the component. Slice 16 unified every boolean in production on the
design system's Switch, which left those declarations in the application
stylesheet with zero template consumers — ADR 0024's closeout recorded it as
C-50 and deferred it — and the gem glyph's `clip-path: polygon(50% 0, 60% 40%,
…)` was the last polygon in the file. The design system still documents the
component and renders it in `components/forms/forms.card.html`, so removing it
from `components.css` would leave a gallery card painting bare checkboxes for no
production gain. Production deletes dead CSS; the mirror keeps the component.

The same pass turned the two live rotated squares into dots in **both** files —
`.field-row .row-bullet` and `.choice-card::after` — because those do render, and
a register that forbids a cut corner while still pinning a diamond to one is not
a register. `.choice-card::after` also loses the offsets that were tuned to dodge
the 11px chamfer (`top: 11px; right: 12px` → `12px/13px`).

**Baselines.** `components.css` sits at the mirror root, outside
`design_surface.roots`, so the design digest did not move — verified unchanged at
`548996ef…` before and after. The application digest did move, because
`public/assets/app.css` changed; it was refreshed on `main` together with the
regenerated `resources/imladris/manifest.json`, per ADR 0024 obligation 4.

**Order of operations, since this bites every time.** `build()` calls
`expectedFiles()` first, and that throws the moment `templates/` or
`public/assets/` drift from the runtime baseline — and `check()` returns early on
that throw, masking any "Generated file is stale" error behind it. So the mirror
edit and `composer build:imladris` must both land **before** `app.css` is
touched. That order was followed here: mirror → build → check (green) → `app.css`
→ digest → rebuild → check (green).

## 2026-09-13 — the border tokens, and one bridge edit (ADR 0034)

The second half of the border audit. Almost all of it is application-only, but
two things touch this mirror:

**`components.css` token spellings.** Thirteen raw pixel radii become the tokens
they already equalled (`999px` → `--radius-pill`, `6px`/`7px` → `--radius-md`,
`4px` → `--radius-sm`), and `.theme-swatch` swaps an inset ring for the real
border `app.css` had always drawn there — a source/production divergence, closed
in the source's favour. Three of the thirteen carry a real +1px delta (6px → 7px,
on the composer's slash/GIF/reference popovers); they are taken deliberately so
the composer popovers sit on the scale.

**One bridge edit.** `.compose-board-select-wrap > .icon` was painted
`--gold-600`, a primitive that does not flip: **2.97:1** on `--surface-raised` in
the light register, against the 3:1 WCAG 2.2 asks of a control affordance. It
takes `--gold-ink` instead — 5.49:1 by day, 7.30:1 by night. This rule lives
inside the 2026-08-27 production-transfer block, so the identical bytes, comment
included, were written to both files and the substring gate re-checked. Keep hex
out of that comment: the tokens-only ban matches inside comments too.

**Not done, on purpose.** The blanket `appearance: none` + baked data-URI chevron
on `.admin-console select.input, .settings-pane select.input` is register-blind
for the same reason, but it is application-only and its removal would break
`admin-remediation.spec.ts` (which pins that element's computed style) and orphan
four padding gutters. ADR 0034 records it as a follow-up.

## 2026-09-20 — unified notification and account repair

The approved repair replaces the standalone notification rows and the Notices
pane rows with one presenter and shared partials. The compatible pane URL stays
`/?pane=notices`; its visible name is Notifications. The shared CSS preserves
the design tokens, unread marker, accessible state, focus treatment, wrapping
and secondary timestamps at narrow widths. Prose and history links retain
visible underlines. The production-transfer portion is kept identical in the
app stylesheet and source mirror before regenerating runtime assets.

The persistent primary bell and saved-feed/folder rail groups are deliberate
production adaptations recorded in ADR 0032. They complete existing navigation
and organization workflows; they do not claim a last-20 dropdown or other
deferred notification controls. Mobile account navigation and session labels
are covered by the same combined repair and browser gate.

The account-only no-JavaScript phone rail is capped at 176px and keyboard
scrollable, with all real destinations retained. Its companion native closed
settings chooser keeps the first form control in the initial viewport. This is
a documented production adaptation, not a claim that the upstream prototype
specified the same geometry; enhanced drawer and desktop behavior remain intact.

At 380px and narrower the member header uses two rows and a 108px height, with
primary routes on the second row and the drawer/scrim offset kept in sync. The
full route labels remain readable alongside the persistent bell. This explicit
ADR 0032 adaptation trades header height for navigation reachability.

For this approved implementation, the application digest is refreshed on the
isolated repair branch after explicit source review, together with the generated
manifest and styles. This is an exception to the historical merger-only slice
procedure above: the implementation plan requires a coherent build and passing
local verification before delivery. The literal `reconciled_through_commit`
remains `6d81da590a12bd09bb8d0e282c042aa03d755a94`. A later integration that changes
the application surface must refresh the digest and rebuild again.

The [combined evidence index](../../evidence/unified-notifications-and-settings/README.md)
owns the current implementation status, final build/test results, reviewed
screenshots and deliberate canonical Notices-image promotion. Earlier phase
or prototype captures do not stand in for this repair's evidence.

## 2026-09-23 — one topic row, one star (ADR 0036)

The queue row that the member-surfaces transfer added as
`templates/partials/inbox_thread_row.php` is gone. The inbox now renders the one
topic row, `templates/partials/thread_row.php`, in a third `presentation`
(`inbox`), which is what `components/forum/thread-row.card.html` already said
the row was. The card names the queue's presentation `default`; production names
it `inbox`, because the shipped queue row follows `ForumInbox.dc.html`'s triage
geometry (ADR 0029) rather than the card's comfortable one. That is a naming
difference, not a new component.

**What changed in `components.css`.** Only the 2026-08-27 production-transfer
block, and only its queue-row rules. Each `.inbox-thread-row` / `.inbox-row-*`
selector becomes the thread-row vocabulary under the queue's modifier
(`.thread-row.thread-row-inbox`, `.thread-title-line`, `.thread-title`,
`.thread-row-chips`, `.thread-snippet`, `.thread-meta`, `.thread-meta-commends`,
`.thread-row-select`, `.thread-row-star`, `.thread-row-menu`,
`.thread-row-menu-panel`). Every declared value is unchanged. The queue row now
carries `.thread-row`, so the block also restates what the generic row and its
compact register would otherwise paint on it: the status `::before` rule, the
`overflow: hidden` that cut the no-JavaScript row menu, the flex copy column, and
the one-line clipped title. `.star-toggle` is the old row-star button's rule
under the control's own name. Two rules are new: `.star-toggle .icon` sizes the
glyph to 18px, because the commend star fills less of its box than the
five-point star did, and `.icon-commend-star.is-outline` draws the unset star.
The identical bytes went to `public/assets/app.css`, and the block stays
token-only.

**Order of operations.** The mirror edit and `composer build:imladris` ran with
the application edits parked (`git stash push -u -- templates public/assets`), as
the 2026-09-13 entry requires, and `--check` was green before they were restored.
The design digest did not move: `components.css` sits outside
`design_surface.roots`.

**Deliberate divergence from upstream.** Production no longer prints ★ or ☆
anywhere. The design sources still do: `components/forum/ThreadRow.jsx:119`,
`templates/forum-inbox/ForumInbox.dc.html:196`,
`templates/account-settings/AccountSettings.dc.html:323`, and the retired
`ui_kits/reading/ReadingSurfaces.jsx:37` and `ui_kits/settings/SettingsSections.jsx:364`.
`ThreadView.dc.html:179` itself asks for "the four-point commend star for esteem
everywhere, never ★ here and ✦", and `components/identity/StarButton.jsx`
already draws it, so upstream disagrees with itself. All five are left alone for
the reasons the chamfer entry gives: the first three sit inside
`design_surface.roots` and paint nothing, and the `ui_kits/` copies are governed
by `RETIRED.md`. **A future bundle that offers a ★ back into a production-facing
source should have that hunk refused.**

## 2026-09-24 — production diverges on the engraved edge and the button hover (ADR 0039)

**Application-only; no mirror source changed.** Two rules the mirror's
`components.css` carries now paint differently in production. Both are overridden
by unlayered `public/assets/app.css`:

- **`.input-engraved` / `.textarea-engraved` edge.** The mirror draws it in
  `--gold-200`: 1.30:1 against the parchment card, where WCAG 1.4.11 asks 3:1 of a
  field's boundary, and a near-white 10.8:1 line in twilight. Production draws it
  in `--field-rule`, an application-owned token (`gold-700` by day, `gold-600` in
  twilight).
- **`.btn:hover`.** The mirror hovers on `--brand-hover`, which is evergreen in
  both registers and untouched by operator branding, so a gold twilight button
  turned green. Production deepens the button's own fill,
  `color-mix(in srgb, var(--accent) 82%, #000)`.

The auth stage's new `--stage-*` tokens are application-only too; the mirror's
screens carry no auth stage. The tokens live in `app.css` because on a branch
that already edits the application, `build:imladris` cannot run until the runtime
baseline matches (see the 2026-09-13 ordering note).

**A future bundle that offers `--gold-200` back on the engraved edge, or
`--brand-hover` back on `.btn:hover`, should have that hunk refused**, unless it
brings the token upstream with the same contrast.

## 2026-09-27 — User profile uses shared components

Imported the user-profile handoff's Tabs and Input preview sources/declarations,
their usage notes, the forum card, and `UserProfile.dc.html`. Applied **only** the
four component CSS hunks: disabled variant hover, input affixes, segmented link
hover, and ruled underline tabs. The thread-row, operator-cluster, `.hash`, and
field-hint/link-preview divergences remain held back; no whole-file stylesheet
replacement occurred.

Production uses the shared classes with its existing profile hooks. Application
CSS retains layout, cover actions, and the activity disclosure, plus scoped
`revert-layer` bridges for legacy unlayered rules. The 2026-08-27 member transfer
block is unchanged and still matches character for character. The mirror and
application digests and generated assets were refreshed after review.

ADR 0040 adopts the combined Recent activity list with server GET filters,
JavaScript disclosures, existing visibility/anonymity guards, and opening-post
deduplication. Full tabs keep 20-item pages. Relative timestamps retain exact UTC
in `datetime` and `title`. No client-only filter or upstream component runtime is
introduced. The source-only preview limitation still applies; evidence comes
from the PHP application (see `docs/evidence/profile-system-2026-09-27/`).

## 2026-09-28 — Inbox header and mobile route visibility

ADR 0042 replaces the inbox's persistent introduction, order pills, density
statement, and keyboard legend with a compact title, Show and Sort disclosures,
and an action/help disclosure. This is an approved application adaptation of
`ForumInbox.dc.html`; the imported preview stays historical reference material.
The existing 108px two-row topbar now applies at the 860px drawer breakpoint so
Boards, Inbox, and Messages are fully visible. The bounded member-surface block
in `components.css` carries the approved control styling into the generated
design layer and its application compatibility bridge together. The topbar
adaptation remains application-owned; Search compacts at 1080px to preserve
room for counts and controls. PHP and progressive enhancement retain behavior.

## 2026-10-06 — Chrome consistency

ADR 0032's follow-up and ADMIN §9.2/§9.4 close the member/operator chrome audit:
the desktop rail clears the sticky header on document-scrolling pages; phone
drawers own keyboard focus while the main page is covered; plain errors omit
controls for absent panes; Boards covers real tag routes and authorized topics;
and folder shortcuts share board state with category rows. The narrow operator
tier is a native disclosure headed by its current area, preserving the same
ordered, role- and flag-gated destinations without JavaScript. Narrow
subscription panels stack the title, delivery controls and off action based on
available pane width; long titles wrap, and long operator-header brands yield
space to controls. Messages waits for styles before restoring a saved column,
so WebKit does not reopen an overlay over a smaller conversation. These
application-owned geometry, interaction and content-resilience rules live in
`public/assets/app.css`, `public/assets/app.js` and the PHP shell, retaining the
imported Imladris vocabulary and semantic tokens. The
bounded member-surface compatibility bridge and imported preview sources are
unchanged. Evidence lives in `docs/evidence/chrome-consistency-2026-10-06/`.

## 2026-10-06 — shared-chrome handoff synced per hunk; production keeps its route contract

Imported from `CommunityForumsDesignSystem.zip` (`design_handoff_shared_chrome/`),
SHA-256 `b81803534204ef25f520eb397bc19cf40a262c9b11cc00fbeb659b22d47f2d0c`:
upstream's consistency review of the member bar, the board rail and its roster,
and the operator bar across its 23 screens. The README is preserved byte for byte
as `_archive/design_handoff_shared_chrome/README.md`. It asks for four component
changes, two production checks, and no work on five open decisions. Design
digest `ac4252ec…` → `bb91cb07…`.

The handoff read a checkout older than `34f13b00`. It quotes `topbar.php`'s
earlier Boards rule (`/`, `/c/*`, `/tag/*`) and a seat with Notifications only
inside the account menu. The standalone bell has rendered since `88d51dfe`
(2026-09-20). Where its route contract and production disagree, the accepted
ADR wins (the authority order in `production-contract.json`) and the difference
is raised upstream below rather than ported.

### Taken

- **Components, whole** (`.txt` suffix dropped). `ForumNav`: the bell beside the
  seat, no Inbox count without a viewer, 99+ caps with counted accessible names,
  and the active pill keeps its href. `BoardRail`: active rows keep their href,
  `tag` is documented as the visibility chip on every rail but Compose's picker,
  and a `commend` route glyph. `PresenceList`: `allHref`/`allLabel` render the
  widget's own `.presence-foot > a.presence-all`. `AdminNav`: eleven areas with
  Moderation at index 1, the operator cluster, `role`, a `viewer` seeded by
  username, `notificationsHref` and a counted bell. `Monogram.jsx` was already
  byte-identical. This also closes the JSX half of the AdminNav rewrite held
  since 2026-08-09: these files are reference source that does not execute here
  (`PREVIEW_STATUS.md`), and production already renders the cluster they
  describe (ADR 0024 decision 3). Its CSS half stays held (below).
- **Screens:** `account-settings`, `admin-overview`, `compose` and `forum-inbox`
  whole. `forum-inbox` stops printing ★/☆, which retires one of the five sources
  listed on 2026-09-23. Two screens are new. `board-page` was listed in
  `github.md`'s screen map but this mirror never had it. `leaderboard`'s rail is
  open decision 1 below. Their `ds-base.js`/`support.js` loaders were not shipped
  and are not reconstructed: the mirror's copies exist in four variants, and the
  screens are source-only either way.
- **`tokens/colors.css`, whole.** `--field-rule` (`gold-700` by day, `gold-600`
  in twilight) and the four `--stage-*` auth tokens are production's ADR 0039
  values, now upstream's. The twilight comments take back our `--artifact-link`
  remap and the on-ramp staff ink.
- **`components.css` — six of 24 hunks, two in a local form:**
  - `.btn:hover` deepens `--accent` (ADR 0039 #5). The 2026-09-24 entry's refusal
    condition is met: upstream brings production's exact rule.
  - `.input-engraved` takes `var(--field-rule)` (ADR 0039 #2). Local form: the
    border line only. Upstream's restated frame comments and its 22 → 28%
    invalid halo stay with the held forms stream.
  - `.forum-bar-surface.is-active` and `.board-rail-item.is-active` drop
    `cursor: default` (ADR 0032 #1: both keep their href).
  - `.forum-bar a:hover, .board-rail-item:hover, .board-rail-route:hover,
    .presence-all:hover { text-decoration: none }` (ADR 0032 #3), with
    upstream's comment on the 1080px step.
  - From the bell hunk, only the seat's comment, which credits our 2026-09-12
    correction. The bell rules are held (below), and a comment at their place
    says so.

### Measured rather than assumed

Each candidate layer was compared with the previous one in Chromium against a
seeded copy of the application. The comparison covered guest, member, private
board member, board moderator and admin views; 44 routes across the member
surfaces, settings, Messages, the console and a 404; 121 route and width cases
at 1280/1000/880/820/390px; parchment, twilight and system-dark. For each page,
the layer stylesheet was swapped in place four times (A, B, A, B), with fonts
held in a permanent sheet. Every computed property of every element and
pseudo-element, and the hover and focus states of the chrome's controls, was
read. Only differences that held in both passes count.

- **The bundle as shipped** (the previous and new fingerprinted bundles) differs
  in exactly one way: `cursor` on the current Boards pill and the current rail
  board, `default` → `pointer`, inherited by their count, name and visibility
  spans. Nothing else moved. The ADR 0039 tokens and rules, the hover-underline
  rule and every comment are inert in production, because `app.css` already
  declares the same values. The WebKit run of the same comparison is recorded in
  the evidence package.
- **The whole upstream file, for contrast** (everything except the refused tail,
  the `.hash`/`.field-hint` holds and the `!important`), moves 5,512 property
  values. These include: the console search basis (220 → 190px) and display; the
  console username (12.48 → 13.76px); the ≤900px identity row (58 → 52px tall,
  10 → 7px padding); the bell's hover wash; `.star-toggle`'s `flex-shrink`; and
  the composer send's glyph.

Evidence: `docs/evidence/shared-chrome-handoff-2026-10-06/`.

### Held back

- **ForumNav's bell CSS** (`.forum-bar-bell`, `-hover`, `-bell-count`).
  Production draws its bell from `app.css`'s ADR 0032 adaptation: a 40px target,
  `.bell-count[data-notification-count]` anchored at the corner, and `--brand`
  hover ink from the older `.bell:hover`. Upstream modelled the bell without
  seeing production's. The layer's hover wash would land on production's bell as
  a hybrid (wash plus brand ink), and `.forum-bar-bell-count` matches nothing.
  Whether production adopts the design's 30px/wash/`--text-body` bell is an
  owner call.
- **The AdminNav operator-cluster CSS**, for the sixth sync. Upstream now carries
  its own media blocks, so the 2026-08-09 objection is closed, but four others
  stand:
  1. `ImladrisRuntimeAssetTest::test_app_css_never_overrides_a_design_owned_console_class`
     would fail on `.admin-bar-right`, `.admin-bar-user` and
     `.admin-bar-username`, which `app.css` owns (ADR 0024 decision 3).
     Adopting the layered rules is an `app.css` pruning slice.
  2. Production's class names differ: `a.topbar-link.bell`, `.bell-count`,
     `button.linkbtn` and `.admin-bar-action-label`, against `.admin-bar-bell`,
     `-bell-count`, `-signout` and `-signout-label`.
  3. The measured values differ (above).
  4. Production's ≤860px tier is a native disclosure (`34f13b00`), which
     upstream's 860px step does not model. Upstream's own 900px
     `.admin-tier { padding: 0 16px 8px }` also sits before the base
     `.admin-tier` rule and so never applies. Raise both.
- **Unchanged holds:** the `.badge-staff` border with the `.presence-staff`
  comment; `.field-hint` and `.link-preview-action` (both pinned by
  `ImladrisRuntimeAssetTest`); `.hash`, which five production templates still
  emit (its upstream removal belongs with the thread-row stream's boards without
  a `#`); the thread-row FIDELITY-AUDIT block; the bundle's `!important` on the
  `[hidden]` guard (the local form stays); and the tail deletion of the
  production-transfer section, refused.
- **New upstream streams, owed their own review, each production-visible or a
  forms change:**
  - The star stream: the `.star-btn` header and a shared `.star-toggle` block
    that duplicates the bridge's.
  - Tier chips: now on semantic pairs, which settles the objection held three
    times, but upstream chose `review`/`done`/`info` with `color-mix` borders
    where this mirror uses `staff`/`brand-subtle`/`info`.
  - Status chips: `.chip-decision_made` on `--on-brand-subtle` is a twilight
    contrast fix worth taking; `.chip-archived` on `--ink-400` is a numbered
    primitive, and the twilight block does not remap the ramp.
  - The composer send with its word and mark.
  - The forms stream: the engraved-frame and lapidary comments, the halo, and
    the `.choice-card` restructure, which brings back `background:` shorthand
    and the checked inset ring.

### The two production deltas, verified

1. **The bell beside the seat** is present on every member route with
   notifications on: `a.forum-bar-bell[href=/notifications][data-bell]` before
   the seat, with the count in its accessible name. It has rendered since
   `88d51dfe`. Nothing is added.
2. **The exit label stays "Back to the forum".** ADMIN.md §9.2 specifies "an
   exit link back to the forum". ADR 0024's Stage-1 binding decisions (§3.2)
   translate "Back to the council" exactly so, and `PRODUCT.md`'s lexicon note
   keeps the consoles in plain words. Whether the council lexicon reaches the
   consoles is the open owner decision in ADR 0024 (obligation 5), not this
   handoff's call.

### Where production stands apart from the handoff's contract (raise upstream)

- `/t/{id}` lights **Boards** for an authorized canonical topic, and the topic's
  board is current in the rail (ADR 0032 follow-up, 2026-10-06). The handoff's
  thread view lights no pill.
- Tag routes are `/tags` and `/tags/{slug}`, not `/tag/{slug}`.
- The Messages pill carries an unread-conversations count (hidden at 0). The
  design draws none.
- The bell's geometry and the console's class names, as itemized under "Held
  back".
- The design-side gaps the handoff already owns: no 99+ cap in `BoardRail`, the
  roster title is not a link, and `AdminNav` does not model flag-off areas.
- Upstream disagrees with itself on route rows. `BoardRail.jsx` now keeps an
  active route row's href, while `.board-rail-route.is-active` still sets
  `cursor: default`. Production renders no route rows, so it is left as
  upstream wrote it.

### The five open decisions — none implemented

1. **Leaderboard's rail.** This is an owner decision. Production renders the
   standard rail with the roster on `/leaderboard`. The mirrored screen draws a
   routes block in its place.
2. **Guest pills.** The design follows production, which hides Inbox and
   Messages from a guest.
3. **Living brief** and 4. **the `<main>` landmark.** Both are design-side.
   Production renders the brief inside the topic page, and gives every app route
   `<main id="main">`.
5. **The active console area** is settled by ADMIN.md §9.2: "The active area is
   marked `aria-current="page"` and is not a link." ADR 0032 #1 governs the
   member chrome only.

### Not in this bundle

Upstream's pass touched all 23 screens; six shipped. The other screens keep
their earlier chrome here: `board-index`, `search`, `thread-view`, `user-profile`,
`users-online`, `living-brief` and nine `admin-*` screens. Among them are the
hand-rolled "See everyone" links that `allHref` replaces. `admin-moderation`, the
eleventh area `AdminNav` now lists, has no screen here yet. `admin.card.html`,
`chrome.card.html` and `presence.card.html` predate the new props.

### Baselines and order of operations

The edits went in this order:

1. Mirror edit.
2. `composer build:imladris`, green on a byte-clean application surface.
3. `npm run build`, which adds `public/assets/dist/imladris-style-DeGci5u_.css`
   and keeps every deployed release's files.
4. Application digest `283e30b8…` → `44c88956…`. That bundle is the surface's
   only change; `templates/` and the application CSS and JavaScript are
   untouched.
5. `composer build:imladris` again, for the manifest's `surface_sha256`.
6. `check:imladris` and `check:assets` green.

The digest is refreshed in this change because the work lands on `main`, as in
`34f13b00`. A branch would leave it to the merger (ADR 0024 obligation 4).
`reconciled_through_commit` stays `6d81da59…`.

## 2026-10-07 — production moves creation beneath ForumNav (ADR 0043)

The owner moved New topic out of the production member header and combined
topic/message creation in a shared content row. The mirrored
`components/forum/ForumNav.jsx` still defaults `showCompose` to true and draws
`.forum-bar-compose` in the bar. That component and the generated layer retain
their existing compose markup/rules; production's `partials/topbar.php` no
longer emits the class, and its application-only compose rules are removed.

`partials/subheader.php` renders the new row inside the app/plain main landmark,
ahead of flash messages. Board/topic breadcrumbs and Boards directory tabs
move to its leading side once; signed-in members get a quiet trailing create
control. It offers New topic and New message, showing "+ New" and a chevron
above 860px and only a named 44px plus at smaller widths. With `dms` off it is a
direct board-aware topic link. Guests get leading content only.

The row uses the existing Imladris semantic tokens and focus treatment. The
board slab, sticky echo, phone FAB and inline composer retain their existing
actions. Header heights, `--topbar-h` and sticky scroll offsets remain intact.
Messages and Inbox reserve the row's separate measured height (61px fallback)
above their rooms, preserving docked controls and existing short-screen
conversation growth. The create disclosure shares the existing Inbox menu
enhancement, and New message decorates its native link with the Messages
dialog only where that panel already exists.

This is a documented production adaptation for a future ForumNav handoff. No
mirror component or generated component rule is changed by this decision.
See [ADR 0043](../../adr/0043-create-menu-subheader.md) for the superseded clauses
and [the evidence record](../../evidence/create-menu-2026-10-07/README.md) for
verification and its limits.

## 2026-10-08 — conversation interactions, selective adoption

`CommunityForumsDesignSystem.zip` (SHA-256
`49fd7cedf13f1d59f9ee855aaa10859667668d286451eaeca65ffe7b5bc34200`)
supplies the conversation-interactions handoff. The complete `components.css`
would remove existing local contracts, so only reaction ticks/tips and mention
person/mirror primitives are taken into the authoring mirror. React components
and the DM preview remain source references; PHP and progressive JavaScript
implement the behavior through existing routes.

- Reaction counts use the calm 240ms rise/fall on the viewer's surviving chip.
  The proposed bump/spring alternative is held back under DESIGN's no-bounce
  rule. Member chips have the handoff's half-pixel hover lift and quiet press;
  guest spans stay static. Reactor tips show up to six names including "You",
  with aggregate residual counts, excluding inactive, blocked and former private
  board members. The current topic read gate applies before identity retrieval.
  Touch additions immediately create a visible chip; pending duplicates are
  blocked and uncertain responses offer a GET reload without replaying a toggle.
- Bare `@` offers named topic participants in readable topic/reply contexts.
  Person rows follow avatar preferences and use existing monogram colors.
  Mention insertion preserves canonical Markdown and adds appropriate spacing.
  Source highlighting uses a hidden, metric-matched mirror for known handles,
  excluding email and code. Rich mentions retain the muted over-cap cue; posted
  links retain underlines. Evergreen washes use semantic `--brand`, including
  twilight, and do not add padding or change source glyph widths.
- Messages identity fades over 140ms; letters rise 8px over 240ms after 40ms.
  A tiny external head script consumes a target-specific navigation signal
  before first paint. Only ordinary row switches offer it; reload/history,
  other navigation and reduced-motion paths do not replay it. The list, details,
  actions and composer stay still. The existing newest-letter scroll contract
  remains; the handoff's separate top-of-conversation proposal is held back.

Both account and OS reduced motion suppress decorative transforms/animations.
Native links, forms and the textarea remain the no-JS contract. Existing
`engagement`, `mentions`, `dms`, `rich_composer` and `wysiwyg_composer` flag roles
stay intact; this work introduces no subsystem flag. The generated compatibility
bridge is preserved, with application-only rules outside it.

The runtime digest is refreshed on `main` after contract review and the hashed
asset build; the inspected-mirror commit remains unchanged. Recent deployed
assets remain governed by ADR 0041. Validation and browser artifacts are recorded
in [the evidence record](../../evidence/conversation-interactions-2026-10-08/README.md).

### Conversation-interactions review fixes

The reviewed tooltip geometry now uses `display: none` at rest, a temporary
hidden measurement class, and viewport-clamped placement when shown. This closes
the document-width regression without changing its tokenized face or motion
preferences. The application bridge hands `.is-person > .monogram` width,
height and font size back with `revert-layer`, so the layer's 28px/.66rem row
contract wins over legacy `.monogram` rules. These are targeted corrections to
the same adopted primitives; generated resources and hashed delivery assets
are rebuilt from the authoring mirror.

Reaction names also suppress anonymous authors' self-reactions. Pending buttons
use `aria-disabled` with the submit lock to preserve focus, removed chips focus
the visible menu with `preventScroll`, and error recovery uses a canonical topic
GET while definite refusals retain their server reason. Source completion and
mention paint share a cached block-aware scanner; rich serialization preserves
intraword handle underscores and one final handoff space. Regression evidence
is indexed in the conversation evidence record's review-fixes section.

### Conversation-interactions follow-ups — 2026-10-09

Enhanced reaction requests are bounded at 15 seconds, the Inbox preview's bound.
A stalled toggle aborts, unlocks every copy of the chip and offers the same GET
reload as a lost response, because the server may still have committed it. The
thread renders one empty `role="status"` region before any failure; refusals and
uncertain toggles write into it (cleared first, so a repeat is still announced),
while the visible tray message carries the reload link without a second live
region. Reactor names follow `User::isActive()`, so a member whose timed
suspension has elapsed is named again.

The bare-`@` participant list omits the viewer and either-way blocks, matching
mention-notification eligibility; typed queries keep ordinary matches, as the
Messages refinement contract requires. Inbox rows and previews honor Show
avatars, including the preview's bylines, replies and composer person rows
(tag-page rows still ignore it). The source mirror takes
its size from the textarea's observed content box rather than the rounded
`clientWidth`/`clientHeight`: at fractional widths (90%/110% zoom, fluid columns)
half a pixel re-wrapped a line and every later highlight drifted. Glyph-level
comparisons now match in Chromium and WebKit at both zoom levels.
