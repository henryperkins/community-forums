# Adversarial verification — `admin-members`

**Report under review:** `C:/Users/htper/AppData/Local/Temp/claude/C--Users-htper-community-forums/a8b108e7-e73e-48f7-8916-0ecfed356d65/scratchpad/stage1/D-admin-members.md`
**Verdict:** substantially sound, **not shippable as written**. Two proposed actions are behaviourally
false or spec-conflicting (R1, R4), one safety-relevant design toggle is unflagged (N1), and one
proposed action would duplicate an existing accessible name while silently dropping the region that
carries it (R3).

Everything I re-read independently: the full design file (862 lines, markup 9–515, x-dc script
516–859), all four production templates, both controllers, `UserModerationService`,
`InvitationService`, `_nav.php`, `moderation.php`, `helpers.php`, `app.js`, `App::buildRouter()`,
ADR 0021, ADR 0023, `ADMIN.md` §9.2–§9.4, and the design mirror's `components/admin/AdminNav.jsx`
and `PRODUCTION.md`.

---

## 0. What held up

Almost all of the report's citation work survives adversarial re-reading, and that is worth saying
plainly before the refutations:

- **Every route citation is exactly right.** `App.php:2208-2210`, `2216-2218`, `2362-2376`, `2379`
  verified line-for-line. The `changeRole` comment at `App.php:2377-2378` really does say
  "flag-INDEPENDENT — users.role exists regardless of Phase 5, so this works when `capabilities`
  is rolled back", so row #58's "verified clean" is correct.
- **Every line count in the report's file table is right** (`users.php` 175, `user_record.php` 337,
  `users_bulk_confirm.php` 67, `invitations.php` 102, `AdminUserController` 469,
  `AdminInvitationController` 115, `moderation.php` 56).
- **The report's paths are right and the task prompt's are wrong**: the namespace directory is
  `src/Controller/` (singular), not `src/Controllers/`.
- **The safety-critical defences are correct and correctly argued.** Row #54 (server-enforced
  `confirm_username`, `AdminUserController.php:292-296`), row #27 (`bulk_selected` re-tick), row
  #64 (warn `idempotency_key`), row #21 (`.table-scroll` region), and the §2 ADR audit table are
  all verified against the source and against ADR 0021/0023.
- **`total` really is computed and never rendered** — `grep -n total templates/admin/users.php`
  returns nothing; `UserModerationService.php:637,644`. Row #9 stands.
- **`app.js:1178-1189`** is exactly the `[data-bulk-toggle]` IIFE, carrying the comment
  "Admin console remediation (2026-07-18)". Row #23's insertion point is correct.
- **No relative-time formatter exists.** `grep -rln "time_ago|human_relative|relative_time|timeAgo"`
  over `src/ templates/ public/assets/` returns nothing. `human_duration()`
  (`helpers.php:138-158`) is a *wait-length* formatter for rate-limit copy, not a timestamp
  formatter — so row #94's "do not build it" conclusion holds, though the report should have
  named `human_duration` when it asserted only two time helpers exist.

---

## 1. REFUTED claims

### R1 — Rows #34 / #38 and slice S3: the "skipped — administrator" pre-flight marker is **false for the warn path**, and the proposed test bakes the falsehood in

The report says (row #34) "Mark admin/self rows and label the button with the actionable count …
Presentation only; the service stays the authority on what is refused", and S3 proposes the test:
"select an admin + a user, assert the marker and 'Warn 1 member'; assert the apply-time skip flash
still names the admin".

**Production does not skip administrators on a bulk warn.** Only `suspend()` routes through the
governability guard:

- `UserModerationService.php:296-301` — `suspend()` calls `$this->requireGovernable($actor, $subjectId)`.
- `UserModerationService.php:337-341` — `ban()` calls it too.
- `UserModerationService.php:69-76` — **`warn()` does not.** It calls `assertStaff()`,
  `requireReason()`, `requireSubject()` and then only refuses *self*:
  ```php
  if ((int) $subject['id'] === $actor->id()) {
      throw new ValidationException(['reason' => 'You cannot warn your own account.']);
  }
  ```
  There is no admin-target refusal anywhere in `warn()`.

Consequences of shipping row #34/#38 as written:

1. On a bulk **warn** including an admin, the confirmation page would render "skipped —
   administrator" next to a member production is about to warn, and the button would read
   "Warn 1 member" while `bulkApply()` warns 2. The screen would lie about a moderation action.
2. The S3 assertion "the apply-time skip flash still names the admin" **cannot pass** —
   `AdminUserController.php:99-104` would produce `Warned 2 members.` with an empty
   `$result['skipped']`. The only ways to make that test green are to change `warn()` to refuse
   admins (a real behaviour change on a moderation path, unrecorded in any ADR) or to weaken the
   assertion. Either way S3 as written pressures an implementer into a behaviour regression.
3. Self on a bulk warn is worse than "skipped": the refusal is
   `ValidationException(['reason' => …])`, and `bulkApply()` at `UserModerationService.php:695-699`
   rethrows any `reason`/`until` error while `$done === 0`. So bulk-warning `[self, other]` in that
   order **aborts the entire batch at 422 with nothing written**, whereas `[other, self]` warns one
   and reports one skip. A static "skipped" marker cannot represent order-dependent abort.

**Correct scope:** the pre-flight marker is honest for **suspend only**. If it ships at all it must
be conditioned on `$action === 'suspend'`, and its predicate must be `role === 'admin' || id === actor`
(see N10 — the design's own predicate omits self). For warn, the only honest pre-flight marker is
"you cannot warn yourself".

### R2 — Row #95 / slice S9: "per the settled ownership list its natural home is admin-settings" is unsupported and contradicted by the only available evidence

The report asserts an owner for `/admin/moderation` (Anti-abuse) without citing anything. Three
independent checks say otherwise:

- `components/admin/AdminNav.jsx:8-19` enumerates the ten areas: Overview, Content, People,
  Members, Appearance, Notifications, Integrations, Packages, Features, Settings. **There is no
  Moderation area.**
- `templates/admin-settings/AdminSettings.dc.html` declares itself
  `name="Admin — settings & thread intelligence"` — community name, registration mode, Thread
  Intelligence. Nothing about content scoring.
- `grep -rli "anti-abuse|antiabuse|blocked word" templates/ components/` inside the mirror hits only
  `admin-overview` (an audit-log fixture row `Anti-abuse: first post` and a triage line
  "2 topics held by anti-abuse awaiting review"), `admin-packages`, and `user-profile` — **no
  console owns the mode select or the blocked-word list.**
- The mirror's own `PRODUCTION.md:51` maps it elsewhere entirely:
  `| Moderation: reports, approvals, appeals, anti-abuse | mod/*, appeals/index; moderation_queue appeals anti_abuse GA | GA | kit mod screens |`

The report's fallback ("if no design screen claims it, raise an ownership gap upstream") is the
right answer; the named guess should be struck. The gap is larger than one screen: **four
production destinations have no design home** — `/mod/reports`, `/mod/approvals`, `/mod/appeals`,
`/admin/moderation`.

### R3 — Row #88's action would duplicate an accessible name and silently drop an ADR 0021 region

Row #88: "Drop the `h2`; give the `<section>` an `aria-label="Issued invitations"` so it keeps an
accessible name."

That name already exists. `invitations.php:68`:

```php
<div class="table-scroll" tabindex="0" role="region" aria-label="Issued invitations">
```

This is the same ADR 0021 "a11y/label/scroll-region sweep" artifact the report protects on the
directory (row #21, `users.php:106`). Adding a second `aria-label="Issued invitations"` to the
wrapping `<section>` yields two nested landmarks with an identical name. Worse, the row and S8
never say to **keep** the region — and S8's stated goal ("two-column grid, … bare right-aligned
revoke") plus the design's bare `overflow-x: auto` section (design line 477) points straight at
deleting it. The row must instead read: keep `.table-scroll[tabindex=0][role=region]` verbatim;
drop the `h2` only.

### R4 — Row #1 is classified `copy`, but adopting AdminNav conflicts with `ADMIN.md` §9.2 / §9.4 and reverts ADR 0023 shipped item 6

Row #1: "Replace all four per-template headers with the shared AdminNav partial; adopt the page
frame", classification `copy`, risk `medium`.

`ADMIN.md:561-572` §9.2 is authoritative (precedence tier 4, above any design mock) and mandates:

> Left-nav, grouped:
> | **Dashboard** … | **Moderation** | Reports queue · Audit log · Automation rules … |
> | **People** | Users directory · Roles & Moderators · Bans · Approval queue. |

`ADMIN.md` §9.4 additionally requires: "the console collapses to one column with the section nav in
a drawer (mirrors the app's mobile pattern)".

ADR 0023 §Shipped item 6 records that this was *built on purpose* on 2026-07-18:

> "Console IA per ADMIN §9.2: grouped admin nav (Dashboard · Moderation · Content · People ·
> Appearance · Notifications · Integrations · Settings) with real Moderation entries…"

Production implements both: `_nav.php:7-50` is the eight-group left rail; `_nav.php:52-59,92` is the
`data-admin-nav-toggle` / `data-admin-nav-close` / `data-admin-nav-scrim` mobile drawer.

The design's `AdminNav` is a **flat top tier of ten areas** (`AdminNav.jsx:8-19`, `<nav
className="admin-tier" aria-label="Admin areas">`), has **no Moderation group**, and has **no
drawer/collapse affordance**. Swapping the rail for it therefore (a) contradicts a spec section that
outranks the design, (b) reverts an ADR-recorded shipped decision that governing rule #8 forbids
reverting silently, and (c) drops the §9.4 mobile pattern.

`copy` is the wrong classification. This is at minimum a `constraint` (spec + ADR-bound IA) and
arguably `feature-changed`, and the row must carry the §9.2 / §9.4 / ADR 0023 citation so the
admin-overview slice does not execute it blind. The report's *deferral* of the row to that slice is
fine; its unqualified "replace" instruction is not.

Note this does **not** invalidate slice S1 — a Members sub-tab strip layered *inside* the existing
rail's People group is compatible with §9.2 and is the right shape.

### R5 — Slice S5 drops the `State` term from the record's Status list

S5: "Status dl reordered to `Role · Reputation · Posts · Joined · Last seen` with `Suspended until`
and `View public profile` pulled out of the dl".

`State` is missing. The design keeps it as the **second** dl item — `AdminMembers.dc.html:221`:

```html
<div><dt …>State</dt><dd …>{{ rec.status }}</dd></div>
```

Row #42 gets the order right (`Role · State · Regard · Posts · Joined · Last seen`); the slice text
contradicts it. An implementer following S5 deletes the account-state term from the user record —
`user_record.php:38` — while the record's whole purpose is moderation. The slice text must be
corrected to the six-term design order.

### R6 — Citation errors (all minor; condition-line vs message-line drift)

| Report says | Actually at | String |
| --- | --- | --- |
| `UserModerationService.php:404` | **:406** | `The member already has this role.` |
| `UserModerationService.php:402` | **:403** | `No such member.` |
| `InvitationService.php:101` | **:102** | `Enter a valid email address to bind, or leave blank.` |
| `InvitationService.php:107` | **:108** | `Enter a bare domain like example.com, or leave blank.` |
| `InvitationService.php:119` | **:120** | `That board does not exist.` |

One structural mis-statement: report §1 row **P8** says `nav.pager` is "conditionally rendered". The
`<nav class="pager" aria-label="Pagination">` element at `users.php:165` is **unconditional**; only
its two `<a>` links are conditional (`:166`, `:169`). Row #28's body gets this right; the
section-order table does not.

---

## 2. MISCLASSIFIED

| # | Row | Was | Should be | Why |
| --- | --- | --- | --- | --- |
| M1 | #56 — `required` attributes | `constraint` | **feature-added** | No hard production constraint compels `required`. The design simply never modelled it; production added it (`user_record.php:92,109,114,143,159,229`; `users_bulk_confirm.php:48`). "Production has functionality the design never modeled. Keep it. Record it." is the feature-added definition verbatim. Also incomplete: the report's list omits `current_password` (`user_record.php:143`). |
| M2 | #66 — Save/Clear split into two forms | `constraint` | **feature-changed** | A single form with two named submit buttons (`<button name="op" value="clear">`) is fully PE-valid — a submit button's name/value is submitted with no JS. Nothing forbids the design's one-control-group shape, and the side-by-side visual is achievable either way. Production's split is a deliberate legibility choice ("so a no-JS submit is unambiguous"), i.e. same concept, different mechanics. Keeping it is still right — the classification is what is wrong. |
| M3 | #58 — role-change flag coupling | `constraint (verified clean)` | **not a difference at all** | The row records *agreement* between design and production and prescribes "No change." It is a guard note, not a deviation, and it inflates the `constraint: 7` count. Move it to the ADR-audit table in §2 where its three siblings already live. |
| M4 | #23 — live selected count | `constraint` | **copy** (with the standard external-JS mechanism translation) | The governing rules already state that inline-behaviour → `public/assets/*.js` "is a MECHANISM constraint — the rendered result must still match exactly", i.e. the translation is *not itself* a deviation. With JS on, the rendered result matches the design exactly. The only genuine constraint is the no-JS fallback, which the report's own action already handles. Borderline, low stakes. |
| M5 | #18 — board-mod chip condition | `feature-changed` | **copy** | Nothing mechanical differs: both sides render a chip from a boolean. Production's predicate is `role === 'user' && moderated_boards > 0` (`users.php:132`); the design's is `u.boardMod` alone (line 117). That is a render predicate production must simply change to match — the report's own action says so ("Design wins on presentation"). `feature-changed` reserves for genuinely different mechanics (row #84's dynamic ceilings is the correct use of the label). |

---

## 3. MISSED differences

### N1 — the `piiGate` prop is an editor toggle that disables the audited PII gate, and the report never flags it (SAFETY)

The report correctly hunts down `banRequiresUsername` (row #54) — "The design's toggle is an editor
affordance, not a product option — never expose it". There are **three** such props, declared at
`AdminMembers.dc.html:516`:

```
"bulkActions":{…"default":true}, "piiGate":{…"default":true}, "banRequiresUsername":{…"default":true}
```

`piiGate` is the dangerous one. Design line 610 / 716:

```js
const piiGate = this.props.piiGate !== false;
…
piiHidden: piiGate && !s.pii, piiShown: !piiGate || s.pii,
```

With `piiGate=false` the card renders `rec.email`, `rec.sessionIps` and `rec.postIps` immediately,
with **no reveal event and no `view_pii` log line** (`revealPii` at line 717 is never invoked).
Production's equivalent is unconditional and audited: `AdminUserController.php:151-158` →
`UserModerationService::revealPii()` writes exactly one row at `:470-476`
(`'action' => 'view_pii', 'reason' => 'admin_record_reveal'`), and this is an ADR 0021 shipped item
("audited PII reveal (email + recent IPs) on the user record"). The report needs a row saying
exactly what row #54 says, for `piiGate`.

`bulkActions` is benign by comparison (it only hides the bulk bar) and the report does note it in
§4's gating table.

### N2 — the progressive-enhancement constraint is recorded for the tab strip only; it applies to nine more controls, two of them mutating (SAFETY)

Row #4 records `<button onClick>` → `<a href>` for the tab strip. The design uses the same idiom
throughout, and the report's actions for the mutating ones say "adopt the bare **link-button**"
without a constraint note:

| Design control | Line | Production mechanism that must survive |
| --- | --- | --- |
| member name in the directory cell | 109 | `<a href="/admin/users/{id}">` (`users.php:127`) |
| back link "All members" (bulk) | 163 | must be `<a href="/admin/users">` |
| back link "All members" (record) | 205 | must be `<a href="/admin/users">` |
| "Cancel" | 195 | `<a class="linkbtn" href="/admin/users">` (`users_bulk_confirm.php:62`) |
| "Reveal email & IPs (audited)" | 237 | **POST form + `csrfField()`** (`user_record.php:61-64`) |
| "Lift restriction" | 255 | **POST form + `csrfField()`** (`user_record.php:79-83`) |
| badge "Revoke" | 364 | **POST form + `csrfField()`** (`user_record.php:252-256`) |
| invitation "Revoke" | 500 | **POST form + `csrfField()`** (`invitations.php:88-91`) |
| sort headers | 94-100 | `<a href>` (`users.php:20`) |

Rows #72 and #91 tell an implementer to "adopt the bare danger **link-button**". Read literally that
is an `<a>` — a GET that mutates state, with no CSRF token. The report is scrupulous about this
elsewhere ("keep the server check exactly as-is"); here it needs one constraint row: *these controls
are bare `<button onClick>` in the design; in production they are `<button type="submit">` inside
`<form method="post">` carrying `$this->csrfField()`. The visual treatment changes; the mechanism
never does.*

### N3 — the invitations table's scroll region is unprotected

Covered under R3. Recording separately because it is a distinct ADR 0021 artifact
(`invitations.php:68`) that S8 would delete.

### N4 — the one-time invitation link must stay on the POST response and must never move to the Flash (SAFETY)

Rows #7 and #81 sit adjacent: #7 says the flash may be "restyle[d] … or render[ed] locally on this
screen", #81 says restyle the one-time-link banner "to the gold register". Neither records the
binding reason the two must stay separate. `AdminInvitationController.php:21-24`:

> "The raw token is rendered DIRECTLY in the create response — exactly once, never via the
> cookie-backed Flash (which would leak it into a Set-Cookie header; AdminApiTokenController
> precedent). A reload re-POSTs (issues a fresh invitation) — the accepted minor wart of that
> pattern."

`create()` accordingly returns `consoleView([], [], 200, ['token'=>…,'url'=>…])` at `:59-62` — a
**200 render, not a redirect**. The design agrees structurally (`inviteLink` is separate state from
`flash`, lines 436-441 vs 35-40), so this is a constraint to *record*, not a difference to fix — but
an unrecorded one is exactly how a token ends up in a `Set-Cookie` header during a "restyle the
flash" slice.

### N5 — select-all checkbox label

Design line 93: `aria-label="Select all members on this page"`.
Production `users.php:110`: `aria-label="Select all users on this page"`.
Unrecorded `copy`. (The per-row label *does* match: design `'Select ' + u.username` line 643 vs
production `aria-label="Select <?= $e($u['username']) ?>"` line 124.)

### N6 — numeric column typography and thousands separators

Design right-aligns and monos both numeric columns — `th` `text-align: right` (lines 97, 98), `td`
`text-align: right; font-family: var(--font-mono)` (lines 125, 126) — and formats reputation through
`u.rep.toLocaleString()` (script line 642; record line 707), rendering `8,740`.
Production renders `<td data-label="Reputation"><?= (int) $u['reputation'] ?></td>` (`users.php:137-138`)
and `<dd><?= (int) $subject['reputation'] ?></dd>` (`user_record.php:44-45`) — left-aligned, body
font, `8740`. Row #16 covers only the role pills; nothing covers this. Two separate `copy` items
(alignment/font, and the grouped-thousands format).

### N7 — the record's Status list drops the pills in the design

Design lines 220-221 render role and state as **plain text** `<dd>`s (`font-size: .98rem; color:
var(--text-strong)`). Production renders `<span class="role-pill role-{role}">` and
`<span class="state state-{status}">` (`user_record.php:37-38`). Row #42 addresses term *order*
only. This is a real `copy` difference and it interacts with row #43 (the suspended-until strip
becomes the only coloured state cue on the card).

### N8 — the design's second card grid has four cells; row #67 inserts a fifth with no guidance

Design line 296 opens `grid-template-columns: repeat(auto-fit, minmax(330px, 1fr))` and closes at
371, containing exactly Role (298), Staff actions (318), Cosmetic title (334), Badges (349). Row #67
says to keep the flag-gated Profile media card "between Cosmetic title and Badges" — i.e. inside
that grid, changing its cell count when `profile_media` is on (default-on,
`FeatureFlags.php:69`) and off. The row needs to say where the fifth cell goes.

### N9 — bulk-confirm error placement, and the `field_attrs` wiring it would cost

The design has **one** form-level `<p role="alert">` above the fields (lines 178-180). Production
renders per-field errors *after* each control — `users_bulk_confirm.php:50` and `:57` — via
`field_error()` / `field_attrs()`, which supply `id` + `aria-describedby` + `aria-invalid` +
autofocus-on-first-error (`helpers.php:100-135`). That wiring is ADR 0023 shipped item 5
("Accessible field errors: shared `field_error()`/`field_attrs()` helpers … wired across the
operator-frequent admin forms"). Row #36 addresses only the *wording* of the shared-reason message.
Collapsing to a single form-level alert would revert item 5 on this form; the row must either keep
per-field errors or state explicitly that the design's alert is additive.

### N10 — the design's own skip predicate omits self

Row #38 asks for "the actionable count" without defining it, inheriting the design's
`skipped: u.role === 'admin'` (script line 672) and `actionable = subjects.filter(u => u.role !== 'admin')`
(line 612). Production's `requireGovernable()` refuses **both** admin targets *and* self
(`UserModerationService.php:786-791`), and the design's own suspend blurb says "Your own account and
other administrators are skipped automatically" — so the design's predicate contradicts its own
copy. Any adopted predicate must be `role === 'admin' || id === actor->id()`, and (per R1) must be
suspend-only.

### N11 — cross-screen IA fallout of the Members area is not raised

`_nav.php:22-27` puts Users, Roles, Invitations and Badge rules in one **People** group. The design
splits them across three areas — Members (`admin-members`), People (`admin-people` =
"roles & capabilities"), Features (`admin-features` = "features & badges"). Combined with R2's four
orphaned Moderation destinations, adopting the ten-area map is a console-wide IA migration, not a
per-screen restyle. The report should raise it once, here, since this is the screen that first
requires a "Members" area to exist.

---

## 4. Safety and binding-decision sweep — result

| Check | Result |
| --- | --- |
| Confirmation step dropped? | No. The `/admin/users/bulk` → confirm → apply step survives (row #30 drops a redundant `h2` only). |
| Typed guard dropped? | No — row #54 defends `AdminUserController.php:292-296` explicitly and correctly. |
| Re-auth dropped? | No — row #57 keeps `current_password`; row #58 correctly forbids gating the Role form on `capabilities`. |
| Audit write dropped? | **At risk, unflagged** — see N1 (`piiGate`). The `view_pii` row itself is defended (row #46, §2 table). |
| Kill switch dropped? | No — row #5 handles the `invitations` flag; row #67 keeps the `profile_media` gate. |
| Reveal-once state dropped? | **At risk, unrecorded** — see N4 (invite token must stay off the Flash). |
| CSRF / GET-mutation | **At risk, unrecorded** — see N2 (rows #72, #91 say "link-button"). |
| ADR 0021 reverted? | **Yes, in two places**: R3 / N3 (invitations scroll region, "a11y/label/scroll-region sweep"). |
| ADR 0023 reverted? | **Yes, in two places**: R4 (item 6, grouped nav IA) and N9 (item 5, `field_attrs` wiring on the bulk form). Items 1/4 (255-char cap, bulk-selection preservation, warn idempotency) are correctly defended. |
| ADR 0021 deferrals #4 / #9 re-introduced? | No — verified independently against `docs/adr/0021…md:54-58, 74-76`. The design shows only indefinite full ban + suspension-with-expiry, and only email/session-IP/post-IP signals. The report's §2 audit is right. |

---

## 5. Recommended disposition

1. **Rewrite rows #34 / #38 and slice S3.** Scope the pre-flight marker to `suspend`, predicate
   `admin || self`, and delete the S3 assertion about a warn-path admin skip. Do not touch
   `warn()`.
2. **Reclassify row #1** to `constraint`, cite `ADMIN.md:561-572` §9.2, §9.4 and ADR 0023 item 6,
   and change the action to "the AdminNav adoption is an IA change that needs its own ADR; S1's
   sub-tab strip layers inside the existing rail and ships independently."
3. **Fix row #88 / S8** — keep `invitations.php:68` verbatim, drop only the `h2`, no second
   `aria-label`.
4. **Fix S5's dl order** to the six design terms including `State`.
5. **Add four rows**: `piiGate` (N1, mirroring row #54), the PE/CSRF constraint for the nine
   `<button onClick>` controls (N2), the invite-token-off-the-Flash constraint (N4), and the
   bulk-confirm error-placement / `field_attrs` interaction (N9).
6. **Strike the "admin-settings" guess in row #95 / S9**; replace with the evidenced statement that
   no design area owns Moderation and four production destinations are orphaned.
7. Apply M1–M5 and the R6 citation corrections.
