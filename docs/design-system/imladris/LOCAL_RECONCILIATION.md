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
