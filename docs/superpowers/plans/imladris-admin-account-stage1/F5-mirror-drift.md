# F5 — Imladris mirror drift check (7 admin/account screens + tokens + guidelines)

Live source: Claude Design project `c3e02753-607c-40b6-994c-9ba1a65bb367`
Mirror: `C:/Users/htper/community-forums/docs/design-system/imladris/`
Date: 2026-08-03. **Read-only against the design project** — no `finalize_plan`/`write_files`/`delete_files`/`register_assets` were called.

## Method

Every live file was fetched with `DesignSync.get_file`, written byte-for-byte to
`…/scratchpad/stage1/live/<same-relative-path>`, and compared with `diff --strip-trailing-cr`.

The `--strip-trailing-cr` is required and is **not** hiding real drift: the repo runs
`core.autocrlf=true`, so the mirror's working-tree copies are CRLF while git's index (and the
live project) hold LF. `git ls-files --eol` confirms `i/lf w/crlf` for these paths. Line endings
therefore carry no information here.

For the four largest files the tool result exceeded the inline cap and was persisted to disk as
complete JSON; those were decoded mechanically with `scratchpad/stage1/extract.js` (`JSON.parse`
→ write `content`), eliminating transcription risk entirely. The hand-transcribed files are
corroborated by their diffs, which showed *only* the expected header region and nothing else.

## Result summary

| File | Verdict | Mirror overwritten |
|---|---|---|
| `templates/admin-overview/AdminOverview.dc.html` | stale | yes |
| `templates/admin-people/AdminPeople.dc.html` | stale | yes |
| `templates/admin-content/AdminContent.dc.html` | stale | yes |
| `templates/admin-appearance/AdminAppearance.dc.html` | stale | yes |
| `templates/admin-notifications/AdminNotifications.dc.html` | stale | yes |
| `templates/admin-settings/AdminSettings.dc.html` | stale | yes |
| `templates/account-settings/AccountSettings.dc.html` | stale | yes |
| `components.css` | stale (3 new sections) **+ 1 local-ahead line** | yes, with one line re-applied |
| `tokens/colors.css` | **local ahead of live** — not stale | **no** (deliberate) |
| `styles.css` | identical | — |
| `tokens/typography.css` | identical | — |
| `tokens/spacing.css` | identical | — |
| `tokens/fonts.css` | identical | — |
| `guidelines/voice.card.html` | identical | — |
| `guidelines/vocabulary.card.html` | identical | — |

Every requested path exists upstream. **Nothing was renamed or removed upstream**, so no mirror
file needed to be preserved as an orphan.

## What changed

### 1. All six admin screens — the page header became a shared component

Identical structural edit in `AdminOverview`, `AdminPeople`, `AdminContent`, `AdminAppearance`,
`AdminNotifications`, `AdminSettings`. Live replaces ~16 lines of per-screen inline chrome with a
single import:

```html
<x-import component-from-global-scope="ImladrisDesignSystem_c3e027.AdminNav"
          area="overview|people|content|appearance|notifications|settings"
          hint-size="100%,101px"></x-import>
```

Removed from each screen:

- the hand-rolled sticky 58px topbar (`position: sticky; … backdrop-filter: blur(10px)`) with its
  inline eight-point-star SVG, the "Imladris" wordmark, and the "Back to the council" link;
- the two-column head block: the `Operator desk · <Area>` gold eyebrow, and the
  `Admin mode` pill (`--surface-review` / `--on-review`).

Also changed on all six:

- page padding `26px 28px 110px` → `22px 28px 110px`;
- `<h1>` drops from `2.4rem` (with `margin: 7px 0 0`) to `2.1rem` (with `margin: 0`);
- sub-nav top margin `22px` → `16px`.

`AdminOverview` has one extra removal: the right-aligned
`Moderation · Content · People · Appe…` breadcrumb-ish span at the end of its sub-nav.

The `area="…"` attribute is what now drives which tier item renders active, so the active-state
logic moved out of the screens and into `AdminNav` — the screens no longer encode their own place
in the console.

### 2. `AccountSettings.dc.html` — one control removed from Pagination

In the Reading section's Pagination group, live **removes the "Default sort" `<select>`**
(options: Last post / Newest / Most replies) and collapses the grid from
`grid-template-columns: 1fr 1fr 1fr` to `1fr 1fr`. "Threads per page" and "Posts per page"
remain. That is the entire diff for this 120 KB file.

### 3. `components.css` — three new sections arrived; one local line held back

Live **adds** (mirror had none of these):

- **`.admin-bar` / `.admin-tier` block** — the CSS backing the new `AdminNav` from change (1).
  Two rows in one sticky block; the area tier uses the pill register deliberately so it does not
  read as a duplicate of a page's own underline sub-tabs. `.admin-tier` keeps `overflow-x: auto`
  with a thin scrollbar on purpose, as the honest signal that Settings is off-edge below ~900px.
- **`.thread-list.is-board` / `.thread-row-activity` block** — board-index row presentation
  (`/c/{slug}`): ruled 64px-floor entries instead of cards, snippet and board label suppressed,
  activity moved to a right-hand rail. Mirrors `presentation:'board'` on `partials/thread_row.php`.
- **`.presence-widget` block** — the full "Online" widget: title, count pill, rows, staff tag,
  skeleton loading states, `@keyframes presencePulse`, and the wide `.presence-grid` directory
  layout.

Live also **rewords one comment**: the composer anatomy provenance changes from
`_archive/app-snapshots/2026-07-14-4efe4e33/app.css` to
`community-forums public/assets/app.css @ 4efe4e33`.

I took all of the above. I did **not** take live's `.badge-staff` line — see the next section.

### 4. `tokens/colors.css` and `.badge-staff` — the mirror is ahead of live, not behind

This is the one place the drift runs the other way, and it is the reason two of the fifteen
verdicts are not a straight overwrite.

The mirror carries a semantic staff pair that **live does not have**:

```css
/* light */ --surface-staff: var(--gold-100);          --on-staff: var(--gold-800);
/* dark  */ --surface-staff: rgba(194,154,68,.16);     --on-staff: var(--gold-200);
```

and paints `.badge-staff` from it, where live still paints from the numbered ramp
(`--gold-700` ink on `--gold-100` ground).

This is **not** stale mirror content. It is a deliberate, documented, test-backed local correction:

- `docs/design-system/imladris/LOCAL_RECONCILIATION.md` lines 18–24 record it explicitly: the
  authoring bundle's numbered-ramp pair measures **3.55:1 against a 4.5:1 requirement**, and
  because the twilight register remaps only the *semantic* gold tokens and never the numbered
  ones, that pair also rendered an unflipped light-register chip on a dark page. The semantic pair
  clears AA in both registers (6.25:1 light, 8.3:1 dark) **and flips**.
- It shipped in commit `8ffefce "fix: … flip the staff badge …"`.
- `tests/Unit/Core/ImladrisRuntimeAssetTest.php:185-186` asserts the generated CSS contains
  `.badge-staff { … color: var(--on-staff) … background: var(--surface-staff) … }`.
- The docs mirror and the runtime input `resources/imladris/` were byte-identical on both files
  before this pass, and `LOCAL_RECONCILIATION.md` states the mirror's job is to carry exactly
  these corrections "before runtime generation".

So I left `tokens/colors.css` untouched (its *only* delta is this correction — live has nothing
new to offer there), and for `components.css` I took every live addition and then re-applied the
one `.badge-staff` line. Both files are confirmed back in lockstep with `resources/imladris/`
on that rule.

Blindly overwriting would have reverted a WCAG AA fix, broken dark-mode flipping of the staff
badge, and desynced the docs mirror from the runtime copy.

## Follow-ups for whoever owns the next stage

1. **`resources/imladris/components.css` is now behind the docs mirror.** The three new sections
   (`admin-bar`/`admin-tier`, `thread-list.is-board`, `presence-widget`) exist only in
   `docs/design-system/imladris/`. The runtime copy and the generated `public/assets/imladris.css`
   need regeneration if those styles are meant to ship. Note `config/imladris-runtime-baseline.json`
   + `composer verify:imladris` gate that surface deliberately — refreshing the digest is an
   explicit design-contract review step, not automatic.

2. **Live's new `.presence-staff` rule reintroduces the exact pattern the AA fix removed:**
   `background: var(--gold-100); color: var(--gold-700);`. That is the same numbered-ramp pairing
   that `LOCAL_RECONCILIATION.md` measured at 3.55:1 and that will not flip in the twilight
   register. I did **not** silently change it — that is an operator/design call, and it should
   probably be raised upstream in the design project rather than patched locally a second time.

3. **Concurrent writer detected in the same mirror.** `CHANGELOG.md` (20:29), `README.md` (20:26),
   `manifest.json` (20:23) and `production-contract.json` (20:23) were modified during this pass
   by something other than me — I never opened them for writing. If sibling stages are syncing
   the same directory in parallel, the combined `git diff` will contain their work as well as
   mine.

4. **`AdminNav` itself was not in scope.** The six admin screens now depend on
   `components/admin/AdminNav.jsx` / `.d.ts` and `components/admin/admin.card.html` upstream.
   Those exist in the live project but are not part of this mirror pass — worth syncing before the
   templates are rendered locally, or the `x-import` will resolve to nothing.
