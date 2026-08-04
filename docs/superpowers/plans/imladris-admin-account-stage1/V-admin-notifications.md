# V — admin-notifications: adversarial verification of `D-admin-notifications.md`

**Verdict:** the report is **substantially correct on production** and **materially stale on the design**.

I opened every file it cites. Its production `path:line` citations are, without exaggeration, near-perfect —
I spot-checked 40+ and found one off-by-a-few (`app.css:2839`). Both `feature-removed` calls hold under
grep. Both flag gates, both rate limits, the F24 block, the dead `.site-announcement-current` class, the dead
`suppression_count` key, and the absent retention purge are all real and correctly described.

The failures are concentrated in four places:

1. **The design file changed under the peer mid-flight.** Two difference rows (D3, D5) are now refuted
   outright, and the whole topbar/nav framing (D1, D2) is stale.
2. **One wrong test-impact claim (D43)** that, followed as written, lands a green PHPUnit run and a **red
   browser-evidence run** — the repo's only CI workflow.
3. **One soft `constraint` (D18)** that is really a `copy` decision.
4. **A set of missed anatomy differences**, one of which (the empty `<th>`) is an ADR 0023 item 5 revert trap.

---

## 0. The design source moved under the report

`docs/design-system/imladris/templates/admin-notifications/AdminNotifications.dc.html` is **modified in the
working tree** (mtime 2026-08-03 20:36, `git status` → ` M`). The peer read the committed version.

| | HEAD (`44bfd8a`, what the peer read) | Working tree (current) |
|---|---|---|
| Lines | 452 | 441 |
| Markup / script split | 20–279 / 280–450 | 20–266 / 268–438 |
| Topbar | inline 58px sticky bar: star SVG + `Imladris` + `Back to the council` | `<x-import …AdminNav area="notifications">` (line 22) |
| Page-head eyebrow | `Operator desk · Notifications` | **deleted** |
| `Admin mode` chip | in the page head, right of the h1 | **moved into AdminNav** (`modeLabel`) |
| h1 | 2.4rem, `margin: 7px 0 0` | 2.1rem, `margin: 0` |
| Container padding | `26px 28px 110px` | `22px 28px 110px` |
| Tab-strip margin | `22px 0 0` | `16px 0 0` |

`git diff HEAD` confirms exactly this. **The report's design line numbers are therefore correct against HEAD
and uniformly ~12 lines high against the current file.** I am not scoring the offset itself as an error — but
the *substance* of three rows changes, below.

This is a system-wide design change, not a per-screen quirk. Across all ten `admin-*/…dc.html` screens:
`grep -rn "Operator desk" admin-*/` → **zero hits**; `grep -rln "Admin mode" admin-*/*.dc.html` → **zero
hits**; every one of the ten now carries exactly one `AdminNav` x-import.

---

## 1. Refuted

### R1 — D3 "add the eyebrow `Operator desk · Notifications`" is refuted by the current design
**Claim (D3, `copy`, risk low):** *"design:34 eyebrow `Operator desk · Notifications`; production has no
eyebrow; Add `<span class="eyebrow">Operator desk · Notifications</span>` above both h1s."* Delivered by
slice N1 and PHPUnit-asserted there (*"both pages carry `Operator desk · Notifications`"*).

**Why wrong:** the current design has **no page-head eyebrow on this screen or on any of the other nine admin
screens**. The `<div>` holding it was deleted in the resync; line 26 is now the bare h1. Shipping D3 would add
chrome the design system just removed, and pin it with a test.

**Nuance worth keeping:** production *is* internally inconsistent — `templates/admin/dashboard.php:6`,
`settings.php:14` and `branding.php:11` each render `<span class="eyebrow">Operator desk</span>` (rule at
`public/assets/app.css:2822`), while `email.php` and `announcements.php` do not. But that is a
production-internal consistency question, not a design-vs-production difference, and under the current design
the resolution points the *other* way (remove the three, not add two).

### R2 — D5 puts the `Admin mode` chip in the wrong place
**Claim (D5, `copy`):** *"design:37 `Admin mode` chip on `--surface-review`/`--on-review`, 4px 12px, 999px,
`.72rem`, `.08em`, uppercase … Restyle `.pill-admin` to the design chip spec."*

**Why wrong:** that element no longer exists in the page head. It is now
`components/admin/AdminNav.jsx:45` (`modeLabel = 'Admin mode'`) rendered at `:58` as
`<span className="admin-bar-mode">` inside the AdminNav **identity row** — i.e. it is admin-bar chrome, one
level up from the page. Production's `<span class="pill pill-admin">` sits inside `.admin-head`
(`email.php:15`, `announcements.php:11`). The correct row is a *placement* difference against shared chrome,
not "restyle the pill in place". The quoted 4px/12px/`.72rem` spec is HEAD's, and no longer authoritative.

### R3 — D43's test-impact claim is wrong in both directions, and would redden the only CI workflow
**Claim (D43, `copy`):** *"Adopt `Release` … Update `AppAdminEmailTest.php:124-133` in the same commit."*
Repeated in slice N4: *"the round-trip test (`:124-133`) updated for `Release`"*.

**What that test actually asserts** (`tests/Integration/Admin/AppAdminEmailTest.php:124-133`,
`test_suppress_then_remove_round_trip_via_http`): a redirect containing `/admin/email`, that the address
appears, and that after the remove POST it does not. **It never asserts the button label or the flash
string.** No update is needed there, and the rename would go unverified by PHPUnit.

**The real pin the report missed:** `tests/browser/gate-a.spec.ts:1281`

```ts
await row.getByRole('button', { name: 'Remove' }).click({ force: true });
```

Renaming the button to `Release` breaks that locator. Grepping the whole suite for the label/flash
(`grep -rn "suppression list\|'Remove'\|Address removed" tests/`) returns no other email hit — this is the
single test that pins it, and it lives in `tests/browser/`, which is what
`.github/workflows/browser-evidence.yml` runs. Per CLAUDE.md there is no PHPUnit CI. Following D43 as written
produces a green local `composer test` and a red CI.

### R4 — D22's filter-row alignment spec is wrong
**Claim (D22):** *"flex-wrap, 12px gap, **baseline-aligned**"*. The design is
`align-items: flex-end` (current design:78; HEAD design:90). With a mixed-height row (two selects, a text
input, a `Reset` button, a right-aligned result label) `baseline` and `flex-end` render visibly differently.
Copy the attribute, not the paraphrase.

---

## 2. Misclassified

### M1 — D18 `constraint` → the mechanism is constrained, the **placement** is `copy`
**Claim (D18, `constraint`):** design's inline `<span role="status">Queued — it is at the top of the log.</span>`
vs production's PRG flash → *"PE/PRG: the flash **is** the confirmation. Do not add an inline status span."*

The PRG mechanism is a genuine constraint (client success state can't ship). The *placement* is not. Production
already redirects with a flash message (`AdminEmailController.php:68`); rendering that message server-side as
an inline `role="status"` beside the Send button — instead of only at page top — is entirely possible with
zero JS and no CSRF change. "No verbatim copy is possible" is not established for the placement, so per the
brief it is a `copy` difference production must change (or consciously decline, in writing).

The *string* is correctly handled elsewhere: D19 (`feature-changed`) rightly kills `"Queued"` because
`EmailOpsService.php:104-117` sends synchronously. Keep D19; split D18 into constraint(mechanism) +
copy(placement).

All eight other `constraint` calls hold and name a real constraint: D1/F-strings (fiction lexicon), D2 (PE +
ADR 0023 item 6 — verified: *"Console IA per ADMIN §9.2: grouped admin nav (Dashboard · Moderation · Content ·
People · Appearance · Notifications · Integrations · Settings)"*), D4 (DESIGN.md §5.3 verified line 131:
*"every view has a real, shareable, crawlable URL rendered by the server"*), D6, D7 (CSP), D15 (CSRF), D23
(PE), D67 (PE + CSRF).

---

## 3. Missed

### X1 — the design now models an admin nav, and it is nothing like production's (`copy` / `constraint`)
D1 says *"Do not port; the screen begins at the page head"* and D2 treats the design's nav as only a two-item
client tab strip. In the current source the screen imports **`AdminNav`**, a *two-row sticky block*
(`components/admin/AdminNav.jsx:50-75`): an identity row (mark · exit · mode pill) **plus an "area tier"
listing all ten admin areas** as pills — `Overview, Content, People, Members, Appearance, Notifications,
Integrations, Packages, Features, Settings` (`AdminNav.jsx:8-19`), `<nav aria-label="Admin areas">` at `:60`.

Production is an eight-group **vertical** 224px rail — `Dashboard · Moderation · Content · People · Appearance
· Notifications · Integrations · Settings` (`templates/admin/_nav.php:7-50`), browser-pinned by
`tests/browser/admin-dashboard.spec.ts:60-70` (`expect(page.locator('.admin-nav-group-title')).toHaveText(GROUPS)`
plus a `toHaveCount(1)` per href). No row in the report compares these. The rail stays (ADR 0023 item 6), so
this is a `constraint` — but it must be *recorded*, because the design's IA (10 flat areas, horizontal) is a
different information architecture from production's (8 groups, vertical), and the report's ledger currently
implies the design has no opinion.

### X2 — the empty `<th>` is an ADR 0023 item 5 revert trap (`feature-added`; the report guards neither)
Design suppression table, 4th header (current design:174 / HEAD :177):

```html
<th scope="col" style="…border-bottom: 1px solid var(--border-soft);"></th>
```

Production ships `<th scope="col"><span class="sr-only">Actions</span></th>` (`templates/admin/email.php:177`).
**ADR 0023 shipped item 5 explicitly lists `empty <th>s` among the enumerated accessibility pockets it
fixed.** A verbatim transcription of the design reverts it. D45 covers only `.table-scroll`; nothing in the
report mentions the header. This is the one place where "copy the design verbatim" is a binding-decision
violation, and it needs an explicit `feature-added — keep` row.

### X3 — column alignment (`copy`)
The design right-aligns **Attempts** and **Action**, header *and* cell: `text-align: right` at design:117,
:119, :133, :135. Production's `table.audit` is left-aligned throughout. Not recorded.

### X4 — mono/ink treatment on data cells beyond time (`copy`)
D68 covers only When/Since. The design also sets:
- **To** cell → `var(--font-mono)` `.78rem` `--text-body` (design:125)
- **Attempts** cell → `var(--font-mono)` `.8rem` `--text-muted` (design:133)
- **Subject** cell → `--text-muted` + `text-wrap: pretty` (design:134)
- suppression **Email** cell → `var(--font-mono)` `.8rem` (design:179)

Production renders all four in default table ink/face.

### X5 — inline error placement, both forms (`copy`)
- Suppression: the design puts `suppressError` **inside the form**, right of the `Suppress` button
  (design:167, `padding-bottom: 9px`). Production renders `field_error()` **after** the form as a block
  (`email.php:171`). D41 discusses only the a11y wiring and the string.
- Announcements: the design puts `publishError` **beside the `Publish banner` button** (design:229, in the
  same flex row at :227). Production renders `field_error()` **under the textarea** (`announcements.php:41`).
  D58 discusses only the string.

Both are restructures that must not break the 422/429 `->errors`/`->old` round-trip (anti-draft-loss;
`AnnouncementService.php:161-167`, `AdminEmailController.php:79-82`).

### X6 — empty-state placement in the suppression table (`copy`)
The design renders the `<table>` and then a sibling `<p>` below it (design:187-189). Production replaces the
tbody with a `colspan` row (`email.php:193-195`). D36 gets this right for the delivery log ("rendered inside
the card below the table"); D44 addresses only the copy and leaves the structure unstated.

### X7 — the card and heading specs are never enumerated (`copy`)
D6/D7 say "restyle inside `.admin-pane`" but no row records the actual deltas, so a slice can ship without
them:
- Section card: `padding: 18px 20px` (`18px 20px 10px` where a table is last), `background: var(--surface-raised)`,
  `border: 1px solid var(--border-hair)`, `border-radius: var(--radius-lg)`; `box-shadow: var(--shadow-sm)` on
  **exactly two** sections — Delivery log (design:76) and Publish a banner (design:211).
- h2: `var(--font-display)`, weight 500, `1.25rem`, `--text-strong`, `margin: 0 0 10px|12px|14px`.
- Stat card padding `14px 16px` (production `.stat-card` is `13px 15px`, `app.css:3444`) and `.stat-label`
  `.68rem`/`.1em` (production `.62rem`/`.08em`, `app.css:3459`) — D21 covers the surface, radius and numeral
  but not these two.

### X8 — a browser pin D20 must not trip
`tests/browser/gate-a.spec.ts:1266-1269` asserts `getByRole('heading', { name: … })` for `Email delivery`,
`Queue status`, `Delivery log`, `Suppressed addresses`. D20 ("unbox the section, restyle the h2 as a caps
eyebrow") is safe **only** because the design keeps it an `<h2>` (design:65) — production's `.eyebrow` idiom is
a `<span>`. If the slice swaps the tag, that spec goes red. Nothing in N3 guards it.

### X9 — fiction strings: right substance, dead citations
F1/F2/F3 (`Imladris` wordmark, the eight-point star, `Back to the council`) are no longer at design:24/25/27.
They are real design fiction, but they now live in `components/admin/AdminNav.jsx` — `backLabel = 'Back to the
council'` (`:44`), `<span className="admin-bar-wordmark">Imladris</span>` (`:53`), `<Star size={24} />` (`:29`).
Anyone re-deriving the topbar from the cited lines will find nothing there. The remaining nine fiction rows
(F4–F12) are all real and correctly transcribed; I found no fiction string the report missed.

### X10 — latent duplication that D27 deepens (note, not a difference)
`email.php:97` hard-codes `['queued','sent','bounced','complained','suppressed','failed']` rather than reading
`AdminEmailController::STATUSES` (`:20`), which is the list used for validation *and* the CSV export
(`:142-143`). Reordering the template list (D27) is behaviourally safe but widens an existing two-copy drift.
Worth one line in the slice.

---

## 4. Claims I attacked and could not break

Every one of these I confirmed by opening the file:

| Report claim | Verified |
|---|---|
| D35 `feature-removed` — nothing writes `bounced`/`complained` | ✔ `grep -rn "'bounced'\|'complained'" src/ bin/ database/` returns exactly two hits: `AdminEmailController.php:20` (filter list) and `0023_email_deliveries.php:21` (enum). ADMIN §7.6 verbatim: *"Requires the chosen provider to emit these — a selection criterion when the SMTP/ESP provider is picked"* |
| D37 `feature-removed` — no 30-day retention | ✔ no purge/delete of `email_deliveries` anywhere in `src/` or `bin/console` |
| D26 — `verify`/`reset` never enter the log | ✔ `EmailVerificationService.php:120` and `PasswordResetService.php:162` both call `$this->mailer->send(…)`; neither enqueues. The only four `enqueue` kinds are `test` (`EmailOpsService.php:93`), `instant` (`NotificationService.php:260`), `digest` (`DailyDigestWorker.php:90`), `system` (`EmailDeliveryRepository::enqueueSystemForActiveUsers`) |
| D33 — requeue is `failed`-only | ✔ `EmailDeliveryRepository.php:243` `WHERE id = ? AND status = 'failed'` (exact line) |
| D8 — the design models no fails-closed state | ✔ design:40-52 is unconditional; SPF/DKIM chips are hard-coded literals, not state-bound. ADR 0023 item 3 verbatim: *"F24 fixed for real: `/admin/email` states one fact per line (transport / From / sending domain)"*. Pins at `AppAdminEmailTest.php:67-72` and `:93-97` — both exact |
| D48 — `.site-announcement-current` is dead | ✔ zero hits across `public/assets/` (all three CSS files); only `.site-announcement`, `-message`, `-dismiss`, `[hidden]` exist at `app.css:1423-1440` |
| D47 — `suppression_count` is dead | ✔ single hit repo-wide: `EmailOpsService.php:62` |
| D57 — no active-user count | ✔ `UserRepository::count()` (`:180`) is unfiltered; `enqueueSystemForActiveUsers` scopes `u.status = "active" AND u.id <> :actor` |
| D27 — stat-card order already matches, filter order doesn't | ✔ `email.php:76` = design `statCards` exactly; `email.php:97` = `STATUSES` order |
| D65 / flags | ✔ `FeatureFlags.php:29,37` both `true`; gates at `AdminEmailController.php:23-28` and `AdminAnnouncementController.php:20-25` (both exact); `_nav.php:5,34-35,81-84` exact |
| Routes | ✔ `App.php:2296` `GET /admin/email`, `:2313` `GET /admin/announcements` |
| Test cites | ✔ `AppAdminEmailTest.php` :82, :124-133, :146, :186-187, :207-216, :236-237, :301-320, **:315** (`assertDontSeeText($first, 'page=2')`) — all exact. `AdminAnnouncementTest.php:78, :102` exact. `admin-remediation.spec.ts:219` (`hasText: 'Published v'`) exact |
| D55 hazard | ✔ real: `AnnouncementService.php:136` sets `$old['dismissible']` from `post() !== null`, so a naive `checked` default re-checks a deliberately cleared box on 422/429. (Also: `gate-a.spec.ts:1232` uses `page.check()`, which is idempotent — that spec survives the change.) |
| D56 truth claim | ✔ `AnnouncementService.php:70-75` enqueues inside the publish transaction; no recall route exists |
| Register note | ✔ `Operator desk` really is production register — `dashboard.php:6`, `settings.php:14`, `branding.php:11` |

One trivial citation nit: D6/D21 cite `public/assets/app.css:2839` as "`.admin` grid with sticky rail" —
2839 is `.admin .subnav {` (the rail's own rule), not the `.admin` grid declaration. Substantively right.

---

## 5. Binding-decision check

No proposed action *silently reverts* a recorded deferral. ADR 0021's owned deferrals (email-template editing,
§7.4/§7.5 preview + test-send templates) are untouched by both the design and the report — correctly, since
the design does not model template editing either. ADR 0023's four deferrals are unrelated to this screen.

Two hazards the report does not guard:
- **X2** — verbatim-copying the design's empty `<th>` reverts ADR 0023 item 5.
- **D2's action** — an anchor tab strip inside `.admin-pane` would be a *third* rendering of the same two
  destinations (rail group heading + rail links + strip). There is no existing sub-tab pattern anywhere in
  `templates/` (`grep -rn "text-tabs\|sub-tab\|tablist" templates/ public/assets/app.css` → zero hits), so this
  is net-new IA on top of an ADR-locked, browser-pinned nav. It does not break
  `admin-dashboard.spec.ts:69` (scoped to `[data-admin-nav]`) or `gate-a.spec.ts:1261` (scoped to
  `getByRole('navigation', { name: 'Admin navigation' })`) — but it needs an explicit IA sign-off, not a
  low-risk `copy` row.

---

## 6. Recommended fixes to the report

1. Delete D3. Re-derive it only if the eyebrow returns to the design.
2. Rewrite D5 as a shared-chrome placement row against `AdminNav.jsx:45,58`.
3. Fix D43: no `AppAdminEmailTest` change; the pin is `tests/browser/gate-a.spec.ts:1281` and it must be
   updated in the same commit. Add the same check to slice N4's test list.
4. Split D18 into constraint(PRG mechanism) + copy(inline placement).
5. Add rows X1–X7; add the X8 guard to N3 and the X2 guard to N4.
6. Re-derive every `design:NNN` citation against the working-tree file, and note in the header that the source
   is uncommitted (` M`) — a reviewer diffing against HEAD will see different lines.
7. Fix D22's `baseline` → `flex-end`.
