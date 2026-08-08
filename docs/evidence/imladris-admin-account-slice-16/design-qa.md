# Slice 16 account B design QA — the eight remaining panes

Status: complete for the Slice 16 boundary (privacy, appearance, reading, composing, notifications,
connections, sessions, blocks).

References:

- `docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html` — **758 lines in
  this worktree.** Privacy `:259-273`, Appearance `:275-309`, Reading `:335-355`, Notifications
  `:358-386`, Connections `:388-406`, Sessions `:408-426`, Blocked members `:428-444`.
  **Read the worktree copy, not the main checkout's** — that one is 759 lines and pre-refresh.
- `docs/superpowers/plans/imladris-admin-account-stage1/R-account-settings.md` — the corrected
  authority; supersedes `D-account-settings.md`. `V-account-settings.md` folded in.
- `docs/design-system/imladris/components/forms/Switch.jsx` — read in full; it is what "copy the
  class names" resolves to, because the design file itself carries **zero** `class=` attributes.
- Ledger §1.1 (`C-17`, `C-18`, `C-50`, and the new `C-51`, `C-52`), §1.2 (`FA-04`, `FA-05`, and the
  new `FA-29`, `FA-30`), §1.3 (`FC-05`, `FC-06`, and the new `FC-26`, `FC-27`, `FC-28`).

Every design and production anchor cited here was re-read against the current file. The `D-`/`V-`/`R-`
line numbers are stale throughout, and so are several of the ledger's own — §3.1/§3.2 cite the shipped
`regard` string at `privacy.php:40`; it was at `:41`.

Captured 2026-08-08 against the real PHP application and a freshly seeded `retroboards_console_e2e`,
with `prepare.sh` re-seeding between spec groups exactly as `npm run evidence` does.

## Surfaces

| Template | Route | Rail item | Design pane |
|---|---|---|---|
| `account/privacy.php` | `/settings/privacy` | Privacy | `:259-273` |
| `account/appearance.php` | `/settings/appearance` | Appearance | `:275-309` |
| `account/preferences.php` | `/settings/preferences` | Reading | `:335-348` |
| `account/composing.php` | `/settings/composing` | Composing | `:349-354` |
| `account/notifications.php` | `/settings/notifications` | Notifications | `:358-386` |
| `account/connections.php` | `/settings/connections` | Connections | `:388-406` |
| `account/sessions.php` | `/settings/sessions` | Sessions | `:408-426` |
| `account/blocks.php` | `/settings/blocks` | Blocks | `:428-444` |

**Seven design panes serve eight production panes.** The design folds Composing into the Reading pane
as a third eyebrow-headed subsection (`:349-354`) and has no `composing` key in its own view state
machine. `FA-04` classifies the separate `/settings/composing` **rail item** as `feature-added` while
its *content* is modelled — so the route stays (it has a real GET+POST and a shareable URL,
DESIGN §5.3) and takes the design's section anatomy.

## The two primitives this slice is really about

**The section-with-eyebrow card.** Every design pane is the same shell — `--surface-raised`,
`--border-hair` hairline, `--radius-lg`, `--shadow-xs`, `20px 22px 22px` — under a gold eyebrow at
`.66rem`/`.16em`/uppercase/`--gold-ink`. That is production's shipped `.scribe-panel` +
`.scribe-panel-head`, which slices 4 and 15 already certified on the design's own Profile and Security
panes. Multi-section panes repeat the eyebrow over a `padding-top: 18px` + `--border-hair` rule.

**The ruled list row — four consumers.** Verified identical at `:377` (subscriptions), `:395`
(connections), `:418` (sessions) and `:435` (blocks): a hairline-ruled `<li>` holding an optional
leading element (a 34px provider mark, a 30px monogram, or nothing), a flexed column with a label over
a **mono `.74rem` meta line**, an optional state chip, and a trailing text button. Production had four
near-identical inline treatments with the meta on the same line. One `.account-ruled-*` primitive now
serves all four; `.board-pref-*` deliberately stays on the older `app.css:751` rule because
`boards.php` is Slice 17's file and migrates with it.

## Reviewed against the references

- **Panel heads are now real headings on every pane.** Four templates still emitted
  `<span class="scribe-panel-head">` — `privacy.php:22`, `appearance.php:23`, `preferences.php:24`,
  `composing.php:21` — so their panels sat outside the heading outline entirely. All four are now
  `<h2>`, the direction `C-17` permits (it forbids only the reverse). Second and third sections inside
  one panel are `<h3 class="account-subhead">`, so no level is skipped.
- **Eyebrow strings are the design's, not production's section names**: `Who can see you` (`:262`),
  `Theme` + `Density` (`:279`, `:290`), `Pagination` + `What appears in a thread` (`:338`, `:343`),
  `Blocked members` (`:431`).
- **Paired selects.** Privacy, Reading and the digest move onto the design's `1fr 1fr` grid — the
  already-pinned `.field-grid`, which is the design's own `1fr 1fr`/14px pair.
- **State chips take `--surface-done`/`--on-done`** (`:398`, `:419`), the AA-safe pair Slice 14 adopted
  for `C-49`, replacing the bare `.pill` production used for `Connected` and `This device`.
- **The two trailing Appearance cards merge into one** (`:301-307`): prose left, Export (secondary) and
  Reset (**ghost**) right, `justify-content: space-between`. Reset stays a POST form; Export stays a
  GET download.
- **New closing notes** the design has and production lacked: Connections `:404` (verified true —
  `unlink` removes the identity row only) and Sessions `:424` (rewritten, see `FC-27`).
- **Blocks gains the leading monogram** (`:436`, via the existing `partials/monogram`) and the
  `blocked {when}` meta the design shows (`:437`) — available all along, since
  `BlockRepository::listBlocked:71` already selects `b.created_at`.
- **No inline script/style/handlers.** The CSP scan over the eight templates returns nothing; the
  whole-tree scan returns only `layout.php`'s five permitted external `src` tags.

## Deviations recorded by this slice

- **`FC-26` — the account surface unifies on the design-system `Switch`.** The design models exactly
  one boolean control, in all 13 of its positions (`:268-270`, `:299`, `:345-347`, `:351-353`,
  `:368-370`; ten portable). Production shipped **three** idioms: `.switchline`/`.switch`,
  `.gem-check`/`.gem-field`/`.toggle-stack` with `gem-leaf`/`gem-gold`/`gem-river` colour coding, and a
  bare `.checkline` native checkbox. All eleven controls are now `.switchline`/`.switch`, and
  **`role="switch"` is emitted for the first time** because `Switch.jsx` renders it and production had
  it nowhere. Both `.gem-check` and `.switch` are real DS components, so this was never a substrate
  question — only which component the design specifies. The gem colour coding is decorative and is
  recorded as removed. `.checkline` is converted at its one account site only; its declaration is
  shared with 15 admin/partial sites and is **not** touched.
- **`FC-27` — the Sessions footnote is rewritten and server-rendered.** The design says *"Sessions
  expire after 30 days of inactivity."* (`:424`). **Both halves are false here.** `Session::login`
  (`:153-154, :162`) stamps `expires_at` once at sign-in and nothing ever updates it — there is no
  `UPDATE … expires_at` for sessions anywhere in `src/`, and `SessionRepository::touch:50` writes only
  `last_seen_at` — so expiry is **absolute from sign-in, not idle-based**; and the window is the
  operator-configurable `session.lifetime_days` (`config/config.php:43`). It now renders the configured
  count from the seam and describes it as running from sign-in, keeping the design's true second
  sentence verbatim.
- **`FC-28` — a real anti-draft-loss defect, fixed.** `OAuthController::setPassword` caught
  `ValidationException` and redirected with a flash, while `connections()` passed a permanently empty
  `'errors' => []`. So `connections.php`'s error slot **could never render**, and
  `AccountService::setInitialPassword:179-181` can throw `new_password_confirm` for which the template
  had **no slot at all**. Now re-rendered at **422** through a new `connectionsView(User, errors,
  status)`, both fields wired through `field_error()`/`field_attrs()`. `->old` is deliberately not
  replayed — these are password inputs.
- **`C-51` — five of the eight panes have no 422 path, so the standing anti-draft-loss obligation
  cannot apply to them.** `updatePrivacy`, `updateAppearance`, `updatePreferences`, `updateComposing`
  and `updateNotifications` each end in `redirectWithFlash`; none catches `ValidationException`; the
  services coerce rather than validate (`updateNotifications:146-151` blanks an unknown timezone and
  clamps the hour). Adding error slots to a form that cannot error is dead chrome; adding a 422 path is
  a behaviour change outside a restyle slice. **Recorded rather than quietly skipped.** The obligation
  binds exactly one Slice 16 form, and that form was the one violating it.
- **`FA-29` — the density preview bars are kept.** The design's density cards carry title and
  description only (`:292-295`). An earlier report proposed deleting production's 3-bar/4-bar
  miniatures to match; §1.2's own rule says the opposite, and removing a working informative affordance
  is not one of the four sanctioned deviations.
- **`FA-30` — production's composing labels are kept over the design's.** All three of the design's
  labels say less. `Press Enter to send — Shift+Enter for a new line` (`:351`) omits the desktop-only
  scoping, the list/quote/code exception, the `Ctrl`/`Cmd`+`Enter` contract and the touch fallback;
  `Show a live preview while composing` (`:352`) describes a live toggle where production sets the
  preview pane's *initial* state. Taking the design's copy would have shipped two labels describing
  behaviour production does not have.
- **`C-52` — the pane entry animation (`acRise`) is deferred, not adopted.** The design animates every
  pane on entry (`:16` keyframes; `:261`, `:277`, `:337`, `:360`, `:390`, `:410`, `:430`). Production's
  equivalent root is the shared `.settings-pane`, serving all thirteen account routes — declaring it
  there would change Slice 15's certified surfaces and Slice 17's untouched ones without re-certifying
  either. Motion gating is *not* the blocker: `app.css:899-912` already neutralises every animation
  under `[data-reduced-motion="1"]` and `@media (prefers-reduced-motion: reduce)`. Carried to Slice 19.
- **`Digest hour, local` (`:365`) is not taken.** Production's `Digest hour (selected timezone; UTC if
  unset)` states the actual fallback, which the controller implements by blanking an unrecognised zone.
- **The design's `Applies the moment you choose it — the rest of this page follows.` (`:280`) is not
  shipped.** Production requires Save; the sentence describes instant-apply it does not do.
- **One de-fiction in adopted copy.** The Blocks intro takes the design's richer wording (`:432`)
  including its genuinely useful *"blocking is not moderation"* clause, but **`Their public counsel
  stays readable` becomes `Their posts stay readable`** — `counsel` is design fiction vocabulary
  (`Private counsel` is one of the four test-pinned fiction strings).
- **`privacy.php`'s shipped `regard` string is deliberately left alone.** Ledger §3.2 lists *"You still
  earn regard; you just won't be ranked publicly."* as free-to-change with `reputation` proposed, but
  ADR 0024 obligation 5 says **fix both surfaces or neither**, and `profile/show.php:269,270,314` plus
  `ProfileController.php:78,137` ship `Regard` and are not in this slice's file set. Changing only this
  line creates the exact two-surfaces-disagreeing state the ledger warns about. **Deferred to the
  Slice 19 fiction decision.**
- **`C-50` is not touched, and one tempting deletion was declined.** After the Switch unification the
  `.gem-*`/`.toggle-stack` declarations in `app.css` have **zero** template consumers, so deleting them
  could not change any rendering. They are still left in place: they are a `C-50` instance, `C-50` is
  deferred with its evidence requirement intact, and the DS ships the same components regardless.
  `test_lapidary_toggle_css_covers_gem_variants_and_captions` therefore stays green untouched.

## The finding the evidence run produced, not the reading

`account-console.spec.ts`'s first slice-16 axe pass failed on the Appearance pane: the `Export
preferences` button measured **3.18:1** (`#ece4d2` on `#7c7f80`, 13.12px), a serious WCAG AA failure.

**It was a measurement bug in the new test, not a product defect, and it was established by
measurement rather than argument.** `.btn` transitions `background`, and the spec flips `data-theme`
from JavaScript, so for `--dur-fast` the button still holds the previous register's fill. A probe read
the export button's computed background as `rgb(250,246,236)` immediately after the flip and
`rgb(30,39,48)` — the correct `--twilight-800` — 600ms later. Enumerating every matching CSS rule
confirmed the winning declaration was the intended `.btn-secondary { background: var(--surface-raised) }`
and that the token resolved correctly at the element the whole time.

Fixed by settling animations before measuring, inside `shot()` and `expectAxeClean()` so **every**
existing call site gets it without changing what it asserts — only when it samples. The strict CSP
rules out the usual remedy of injecting a transition-killing `<style>` tag (`style-src 'self'`, no
`unsafe-inline`), so the helper waits on `document.getAnimations()` instead.

**This is a latent trap in the account axe gate generally**, not something this slice introduced: slice
4's and slice 15's axe tests flip the theme the same way and passed on timing. The helper now covers
them too.

## Test collisions — what changed and why

| Pin | Disposition |
|---|---|
| `AppImladrisFidelityTest:157` — `assertSeeText($privacy, 'gem-check')` | **Rewritten.** The class it named is deliberately gone (`FC-26`). Its substrate claim is re-pinned as `switchline`/`switch-text`, and all five form panes are now asserted to carry the Switch and **no** gem checkbox or checkline. |
| `AppImladrisFidelityTest:184-189` — real `<h2 class="scribe-panel-head">` pinned for security ×2 and notifications ×1 only | **Extended.** The test's name promised "real section headings" for twelve routes while its body checked three heads. Seven pane heads and the two `<h3>` subsection heads are now pinned, closing the gap between the name and the body. |
| `AppImladrisFidelityTest:189` — exact `<h2 class="scribe-panel-head">Daily digest</h2>` | **Untouched, byte-identical.** No id or extra attribute added to that heading. |
| `AppUserPreferencesTest:252` — `choice-card-desc">Match your device.</span>` | **Rewritten** to drop the terminal period: the design's card descriptions carry none (`:287`), and microcopy register is explicitly in scope for verbatim copy. |
| `AppImladrisFidelityTest:268-276` — five gem selectors inside `app.css` | **Green untouched** — it reads the stylesheet, and the declarations stay (see `C-50` above). |
| `AppImladrisFidelityTest:148-154`, `:160-182` | **Survive** — all eight panes keep `.scribe-panel` and one `<main>`. |
| `account-console.spec.ts:11-34` rail contract | **Survives** — this slice changes no rail. |

New: `AppOAuthTest::test_refused_set_password_re_renders_inline_against_the_failing_field` covers both
`setInitialPassword` failure modes, asserts 422, both error ids, the `aria-describedby` linkage, that
the whole pane is rebuilt, and that neither refusal set a password.

## Verification

**Browser.** Against a freshly seeded `retroboards_console_e2e`, at the 1280×800 and 390×844 projects:

| Group | Specs | Result |
|---|---|---|
| 1 | `account-console.spec.ts` (both projects) | **9 passed**, 9 skipped, 0 failed (1.1m) |
| 2 | `npm run a11y` | **35 passed**, 3 skipped, then **6 passed**, 0 failed (2.6m) |
| 3 | `gate-a.spec.ts` (both projects) | **55 passed**, 3 skipped, 0 failed (4.9m) |

Group 1 carries the axe passes — light, twilight and `data-theme="system"` under
`prefers-color-scheme: dark`, on all eight panes — plus the per-pane document-overflow check at both
widths and the single-boolean-idiom assertion.

**Backend.** Full suite on private `retroboards_test_s16`: **2,574 tests / 18,696 assertions /
2 skipped / 1 failure**. The pre-slice baseline, reproduced on the same database before any edit, was
**2,573 / 18,654 / 2 skipped / 1 failure**, so this slice adds **1 test and 42 assertions and
introduces no new red**. The one failure is
`ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_matches_the_allowlisted_design_system_sources`
— the application-surface digest, red by design on any slice branch (ADR 0024 obligation 4, ledger §6
rule 5). The focused set (`AppImladrisFidelityTest`, `AppUserPreferencesTest`, `AppOAuthTest`,
`AppAccountConsoleTest`, `AppSessionManagementTest`, `AppReadingPreferencesTest`,
`AppOidcProviderTest`) passes **132 tests / 919 assertions** on its own.

**Static gates.** The CSP scan over the eight templates returns nothing, and over `templates/` returns
only `layout.php`'s permitted external `src` tags. `php -l` passes on all eight templates and both
controllers. The class/CSS parity sweep — the slice-11 failure mode — reports **no unstyled class**
across the eight panes. No generated asset, mirror document or baseline file is modified:
`config/imladris-runtime-baseline.json` and `config/imladris-design-baseline.json` are both untouched,
and this slice syncs no mirror file.

## Known branch state, carried not hidden

- `ImladrisRuntimeAssetTest` is red on this branch **by design** — the runtime baseline is refreshed
  once per merge, on `main`, by the merger. The digest moved from `605f1a43…` to `7f40105f…` because
  this slice edits `app.css` and templates; that is the expected behaviour of the gate, not a defect.
- **The 422 browser capture did not happen, and the shared seed was deliberately not changed to force
  it.** The `set-password` form renders only for an account with no password, which
  `tests/browser/seed.php` does not create, so the browser case self-skips. Seeding one would alter the
  member directory that Slice 11's committed captures show, silently invalidating them. The behaviour
  is fully pinned by the new PHPUnit test — status, both field error ids, the aria linkage and the pane
  rebuild — so the gap is a missing PNG, not a missing assertion. Recorded for Slice 19, which owns the
  seed question.
- **Six of the eight panes had no browser coverage at all before this slice**, and the seed provisions
  no privacy/appearance/reading/composing data, so those captures legitimately show default state. The
  subscriptions, connections, sessions and blocks captures show the seeded fixture.
- The `thread-view-study.spec.ts:328` geometry red recorded by slices 13–15 is **not re-tested here** —
  it is not one of this slice's named gates. Note that `main` carries a commit (`058c4cb`, *"recover
  mobile thread height against the Imladris thread-view reference"*) that rewrites exactly those
  assertions and is **not** on this branch; the red may already be fixed upstream of the merge.
- Two `admin-remediation` board-composer tests remain pre-existing exclusions owned by Slice 19.

## Corrections to the record

Slice 15's `design-qa.md:50-53` states that `/settings/account` *"was the last page still emitting
`<span class="scribe-panel-head">`"*. It was not — four more did, all on Slice 16 panes, and the same
claim is baked into `AppImladrisFidelityTest`'s comment. The test survived because it fetches only
`/settings/account`. Slice 16 converts the four and pins them.

## Captures

70 PNGs. `comparisons/` holds the 24 register triples (`s16-<pane>-{light,twilight,system-dark}`) for
all eight panes; `desktop/` and `mobile/` hold the 16 light/twilight pairs each at 1280px and 390px.

The nine unprefixed `profile-*`, `security-*` and `connections-*` files are **not** Slice 16 work — they
are slice 4's and slice 15's own tests re-running under this slice's `RB_EVIDENCE_DIR`. They are kept
because they show those certified surfaces unchanged by this slice.

**Reading the captures:** the privacy, appearance, reading and composing panes show defaults because
the seed sets no preferences for them; the empty subscription and blocks states are the seed's real
state, not a rendering fault.

This evidence certifies only the eight pane bodies named above. The account rail and shell were
certified by Slice 2 and Slice 4, Profile and Security by Slice 15; Boards, Drafts and Lifecycle remain
Slice 17.
