# RetroBoards runtime reconciliation

Imported from `imladris-design-system.zip` with SHA-256
`2ee3201e3bfcaa82ed371af8709fd0737a54c69332119d006f6f0a51aa57dbeb`.

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
  as **inert by design** so it stops reading as unenforced enforcement. The same applies
  to `PRODUCTION_PARITY.md` and `RUNTIME_CONTRACT.md`: prose contracts, no enforcing code.
  What *is* enforced lives in `ImladrisAssetBuilder` and `ImladrisRuntimeAssetTest`.

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
