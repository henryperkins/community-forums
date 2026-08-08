# R — correction addendum for D-account-settings.md

**Screen:** account-settings
**Design file (current):** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html`
**Corrects:** `.../stage1/D-account-settings.md` (line anchors stale) and folds in `.../stage1/V-account-settings.md`
**This addendum is the single corrected source for this screen.** Where it conflicts with D, this file wins.
Where it is silent, D stands as written with its anchors remapped by the rule in §B.

---

## 0. What actually changed upstream on THIS screen — and what did NOT

Verified against `git diff -- docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html`
(`1 insertion(+), 2 deletions(-)`, hunk `@@ -337,8 +337,7 @@`):

1. The Reading pane's Pagination `<label>` wrapping the **"Default sort"** `<select>`
   (options `Last post` / `Newest` / `Most replies`) was **deleted** — it was old line 342.
2. The row that held it collapsed `grid-template-columns: 1fr 1fr 1fr` → **`1fr 1fr`** (line 339).

**Nothing else changed.** `grep -c "Default sort|Last post|Most replies|thread_sort" → 0`;
`grep -o "1fr 1fr 1fr" → 0 hits` file-wide.

### 0.1 The AdminNav chrome refactor DOES NOT APPLY to this screen — do not invert the page head

`AccountSettings.dc.html` is a **member** screen, not one of the six admin screens. It still carries, verbatim
and unchanged:

- the per-screen sticky 58px topbar (`:24-35`) with the eight-point star SVG (`:26`), the **"Imladris"**
  wordmark (`:27`), the **"Back to the council"** link (`:29`), the initials chip / member name / "Log out"
  cluster (`:31-33`);
- the page-head block (`:40-44`) with the gold eyebrow **"Your seat at the council"** (`:41`), the
  **`font-size: 2.4rem; margin: 7px 0 0`** `<h1>Account settings</h1>` (`:42`), and the 62ch intro ¶ (`:43`).

There is **no `<x-import … AdminNav>` on this screen**, the eyebrow was **not** removed, and the h1 was **not**
dropped to 2.1rem. Therefore:

> **No row in D-account-settings.md is inverted by the AdminNav chrome change.** Any instruction to "delete
> the production eyebrow", "the h1 is the first child", or "the chrome now belongs to a shared nav import"
> that is being applied to the six admin screens must **not** be carried across to this screen. D row **#13**
> ("add the intro ¶; raise h1 to 2.4rem; eyebrow string is fiction → §3") stands exactly as written.

Cross-screen note for whoever consolidates: the design system is now internally inconsistent —
admin screens render no eyebrow at h1 2.1rem via shared chrome, member account settings renders an eyebrow at
h1 2.4rem via per-screen chrome. That is an upstream DS question, not a production action, and must not be
resolved by silently changing this screen's spec.

---

## A. True file facts (supersede D's header)

| Fact | D said | **Truth (current file)** |
|---|---|---|
| Total lines | 760 | **758** (`wc -l` = 758; the Read tool reports 759 by counting the trailing newline) |
| `<x-dc>` opens | 9 | **9** |
| Markup ends | 496 | **495** (`</x-dc>`) |
| `<script type="text/x-dc">` | 497–757 | **496–756** |
| Tail | — | `</body>` **757**, `</html>` **758** |

D's header ("760 lines; markup 9–496, script 497–757") described the **pre-refresh** file (759 lines) and is
off by one on the total. **V's header ("markup 9–495, script 496–756") is correct** — V was written against
the post-refresh file, so **every design-side line number in V is already correct** and needs no remapping.

---

## B. Line-anchor remap rule (apply to every design citation in D)

```
design line N in D          →  current line
N ≤ 341                     →  N            (unchanged)
N = 342                     →  DELETED      (the "Default sort" label/select)
N ≥ 343                     →  N − 1
```

Spot-verified at ten points across markup and script: `:344→343` ("What appears in a thread"),
`:350→349` ("Composing"), `:365→364` (Timezone / "Europe / Rivendell"), `:384→383` (no-subscriptions),
`:433→432` (blocks intro), `:477→476` (unsaved-bar `<sc-if>`), `:490→489` ("Saved to your seat."),
`:571→570` (`go(k)`), `:645→644` (`otpIncomplete`), `:751→750` (`deleteBlocked`).

### B.1 Every changed anchor, by D section

| D location | D cited | **Current** |
|---|---|---|
| H1 | `:571`, `:621-634`, `:704-707` | `:570`, `:620-633`, `:703-706` |
| H3 | `:350-355` | `:349-354` |
| H5 | `:619` | `:618` |
| H7 | `:477-484`, `:487-492` | `:476-483`, `:486-491` |
| H10 / #16 | `:362`, `:391`, `:411`, `:431`, `:450` | `:361`, `:390`, `:410`, `:430`, `:449` |
| §1 table | `:350` (Composing folded), `:477`, `:487` | `:349`, `:476`, `:486` |
| #2 | `:571`, `:621-634` | `:570`, `:620-633` |
| #22 | `:346-354`, `:369-371` | `:345-353`, `:368-370` |
| #24 | `:477-484`, `:580-586` | `:476-483`, `:579-585` |
| #25 | `:487-492`, `:584` | `:486-491`, `:583` |
| #34 | `:595-604` | `:594-603` |
| #41 | `:645` | `:644` |
| #42 | `:648` | `:647` |
| #53 | `:619` | `:618` |
| #62 | `:342` | **DELETED — row struck, see §C** |
| #63 | `:339` | `:339` (same number, **content changed** to `1fr 1fr`) |
| #64 | `:344` | `:343` |
| #65 | `:350` | `:349` |
| #66 | `:352` | `:351` |
| #67 | `:353` | `:352` |
| #68 | `:354` | `:353` |
| #70 | `:369-371` | `:368-370` |
| #72 | `:364-367` | `:363-366` |
| #73 | `:366` | `:365` |
| #74 | `:365` | `:364` |
| #75 | `:376-383` | `:375-382` |
| #76 | `:384` | `:383` |
| #77 | `:393` | `:392` |
| #78 | `:397` | `:396` |
| #79 | `:398`, `:691` | `:397`, `:690` |
| #81 | `:405` | `:404` |
| #82 | `:399` | `:398` |
| #83 / #86 | `:425` | `:424` |
| #84 | `:412-416` | `:411-415` |
| #85 | `:420-421` | `:419-420` |
| #87 | `:432` | `:431` |
| #88 | `:437` | `:436` |
| #89 | `:438` | `:437` |
| #90 | `:433` | `:432` |
| #91 | `:443` | `:442` |
| #103 | `:465-466`, `:751` | `:464-465`, `:750` |
| #104 | `:462-463` | `:461-462` |
| #107 | `:452` | `:451` |
| #109 | `:461`, `:750` | `:460`, `:749` |
| #110 | `:451`, `:456`, `:463` | `:450`, `:455`, `:462` |
| §3 row 7 | `:704` | `:703` (and see §D-4 — it is not a "pane title") |
| §3 row 13 | `:544` | `:543` |
| §3 row 14 | `:604` | `:603` |
| §3 row 17 | `:502` | `:501` |
| §3 row 18 | `:499-504` | `:498-503` |
| §3 row 21 | `:365` | `:364` |
| §3 row 22 | `:371` | `:370` |
| §3 row 23 | `:433` | `:432` |
| §3 row 24 | `:457` | `:456` |
| §3 row 25 | `:464` | `:463` |
| §3 row 26 | `:490` | `:489` |
| §3 row 27 | `:550`, `:555` | `:549`, `:554` |
| §3 row 28 | `:546-547` | `:545-546` |
| §3 row 29 | `:610`, `:617` | `:609`, `:616` |
| §3 row 30 | `:248`, `:508-510` | `:248` (unchanged), `:507-509` |
| §4 inventory | `:595-604`, `:645`, `:384`, `:443`, `:401`, `:691`, `:420`, `:477-484`, `:487-492` | `:594-603`, `:644`, `:383`, `:442`, `:400`, `:690`, `:419`, `:476-483`, `:486-491` |

**Every other design citation in D is ≤ 341 and is correct as printed** — including all of §2.1 #1/#3/#9/#10/#11/#13/#15/#17-#21/#23, all of §2.2, §2.3 #35-#40/#43-#46, all of §2.4, §2.5 #54-#61, §2.6 #64's `:338`, all of §2.11, all of §2.12, and §2.13's `:465`→ see above.

---

## C. Corrected top-to-bottom section order (verbatim headings, current anchors)

DOM order of the current markup. Headings are quoted **verbatim** from the file.

| # | Section | Verbatim heading / marker | Current lines |
|---|---|---|---|
| 1 | Topbar | *(no heading)* wordmark **"Imladris"** `:27`; link **"Back to the council"** `:29`; **"Log out"** `:33` | `:24-35` |
| 2 | Container | `max-width: 1064px; margin: 0 auto; padding: 30px 28px 132px;` | `:37` |
| 3 | Page head | eyebrow **"Your seat at the council"** `:41`; `<h1>` **"Account settings"** (2.4rem) `:42`; intro ¶ `:43` | `:40-44` |
| 4 | Two-column grid | `grid-template-columns: 232px 1fr; gap: 30px` | `:46` |
| 5 | Rail | `<nav aria-label="Settings sections">`, `position: sticky; top: 84px; gap: 2px` | `:49-82` |
| 5a | Rail group | **"Account"** `:50` → Profile `:51-52`, Security `:53-54`, Privacy `:55-56`, Regard `:57-58` | `:50-58` |
| 5b | Rail group | **"Reading & writing"** `:60` → Appearance `:61-62`, Reading `:63-64`, Drafts `:65-66`, Boards `:68-69` | `:60-69` |
| 5c | Rail group | **"Council"** `:71` → Notifications `:72-73`, Connections `:74-75`, Blocks `:76-77`, Sessions `:78-79`, Account `:80-81` | `:71-81` |
| 6 | Pane: Profile | **"Profile details"** `:92`; **"Fields defined by the wardens"** `:94` | `:88-105` |
| 7 | Pane: Security | **"Password"** `:111`; **"Two-factor authentication"** `:131` | `:108-169` |
| 8 | Pane: Regard | *(no heading; unit label "Commends" `:179`; footnote `:207`)* | `:172-210` |
| 9 | Pane: Drafts | **"Drafts"** `:218`; **"Autosave"** `:247` | `:213-257` |
| 10 | Pane: Privacy | **"Who can see you"** `:262` | `:260-273` |
| 11 | Pane: Appearance | **"Theme"** `:279`; **"Density"** `:290`; export/reset card `:301-307` | `:276-309` |
| 12 | Pane: Boards | **"Organize your boards"** `:314` | `:312-333` |
| 13 | Pane: Reading | **"Pagination"** `:338`; **"What appears in a thread"** `:343`; **"Composing"** `:349` | `:336-356` |
| 14 | Pane: Notifications | **"Daily digest"** `:362`; **"Your subscriptions"** `:374` | `:359-386` |
| 15 | Pane: Connections | **"Connected accounts"** `:391` | `:389-406` |
| 16 | Pane: Sessions | **"Active sessions & devices"** `:412` | `:409-426` |
| 17 | Pane: Blocks | **"Blocked members"** `:431` | `:429-444` |
| 18 | Pane: Lifecycle | **"Export account data"** `:450`; **"Deactivate account"** `:455`; **"Delete account"** `:462` | `:447-469` |
| 19 | Unsaved-changes bar | **"You have unsaved changes."** `:478`; "Discard" `:480`; "Save changes" `:481` | `:476-483` |
| 20 | Saved toast | **"Saved to your seat."** `:489` (`role="status"`) | `:486-491` |

D's §1 comparison used the **rail** order as authoritative. That is still correct and **none of the rail
anchors moved** — D §1's design column is valid as printed except the three entries remapped in §B.1.

---

## D. Inverted rows

### D-1 (the only genuine inversion on this screen) — §2.6 row **#62**, "Default sort"

| | |
|---|---|
| **Was** | *feature-removed.* "Design shows a Default sort select — Last post / Newest / Most replies (`:342`); `thread_sort` was retired at `PreferenceSchema::VERSION = 3`. **Do not re-add.** Binding: 2026-08-02 plan Task 1 Step 5 + USER §4.2 fixed order. **This is F2 conflict C1.**" |
| **Now** | **STRUCK — not a deviation of any class.** The current design file contains no "Default sort" control and no `Last post` / `Newest` / `Most replies` options (grep: 0 hits). The design has converged on production. There is nothing to refuse, nothing to record as a gap, and **no F2 conflict C1 on this screen** — close it at source rather than carrying it into a slice or an ADR. |

Knock-ons that must be applied with it:

- **§2.6 #63** — premise inverted, action unchanged. Was: "Pagination row is a `1fr 1fr 1fr` grid (`:339`) —
  *becomes* `1fr 1fr` once Default sort is dropped." Now: the design **ships `1fr 1fr` directly** at `:339`
  with exactly the two selects production already has (**"Threads per page"** `:340`, **"Posts per page"**
  `:341`). This is now an unconditional **copy** row with no derivation and no risk: adopt the 2-col grid.
- **§4 state inventory** and **§5 S6** — drop "Does **not** add Default sort" from S6's scope line and drop
  the corresponding PHPUnit assertion framing ("still exposes exactly the v3 reading keys and **no
  `thread_sort`**" can stay as a regression guard, but it is no longer a design-conflict mitigation).
- **§0 headline set** — no headline claimed C1, so no headline changes.

### D-2 — Not inverted, explicitly confirmed

For the record, because the sibling screens **are** inverted: rows **#1** (topbar), **#13** (page head
eyebrow + 2.4rem h1 + intro ¶), and §3 fiction rows **1-5** (star SVG, "Imladris", "Back to the council",
"Your seat at the council", the intro sentence) are **NOT** inverted. Every one of those elements is still
present in the current file at the anchors D cites. Do not delete production eyebrows on this screen.

---

## E. Fabricated / no-longer-present quoted design content

Each quoted design string in D was checked with a literal grep against the current file.

| # | Where | Quoted as design content | Verdict |
|---|---|---|---|
| E1 | §2.6 #62, §5 S6 | `"Default sort"` + options `Last post` / `Newest` / `Most replies` at `:342` | **No longer present.** 0 grep hits file-wide. Row struck (§D-1). |
| E2 | §2.6 #63 | `grid-template-columns: 1fr 1fr 1fr` at `:339` | **No longer present.** 0 hits file-wide. The line now reads `1fr 1fr`. |
| E3 | H7 heading | `"You have unsaved changes · Discard · Save changes"` | **Mis-quoted.** The design string at `:478` is **"You have unsaved changes."** — with a terminal period. D's own row #24 quotes it correctly; the H7 heading drops it. Use the period. |
| E4 | §3 row 7 | `Regard` described as "rail item **+ pane title**", cited `:704` | **Half fabricated.** `Regard` appears as rendered chrome only at `:57`/`:58` (rail button label) and inside the footnote at `:207`. `:171` is an HTML comment, `:172` is an `sc-if` attribute, and current `:703` is the script state-key line `atRegard: … goRegard: this.go('regard')`. **There is no rendered "Regard" pane title anywhere in the file.** The row's *action* ("do not add the item at all") is unaffected. |
| E5 | H10 + §2.1 #16 | `padding: 20px 22px 22px` presented as the single panel recipe across the 12 cited sites | **Partly fabricated.** Only 10 sections use that padding. Four of the twelve cited lines do not: current `:313` and `:430` are `20px 22px 16px`; current `:373`, `:390`, `:410` are `20px 22px 8px`. The file also carries `padding: 0; overflow: hidden` list panels (`:174`, `:215`), `16px 20px 15px` (`:246`) and `18px 22px` (`:301`). **A settings card class that hardcodes one bottom padding will be wrong on every list-style panel.** Author the class with the surface/border/radius/shadow only and let padding vary by panel type. |
| E6 | H9 + §2.1 #22 | "the DS `Switch` component in **every** boolean position (**11 uses**)" | **Count wrong.** `grep -c "…\.Switch"` = **13**: `:268`, `:269`, `:270`, `:299`, `:345`, `:346`, `:347`, `:351`, `:352`, `:353`, `:368`, `:369`, `:370`. Note three of the 13 (`:368-370`) are the per-event email switches production does not implement, so the *portable* count is **10**. |
| E7 | D header | "760 lines; markup 9–496, script 497–757" | **Stale.** See §A. |

No other quoted design string in D failed a literal grep. Every string in §3 rows 1-6, 8-30 and every
verbatim string quoted in §2.1-§2.13 and §4 was located at its remapped anchor.

---

## F. V-report findings, folded in (this section supersedes the corresponding D rows)

### F.1 Refutations

- **R1 — strike the "used across admin" premise.** `grep -rln scribe-panel templates/` returns **only seven
  files, all under `templates/account/`** (`settings.php`, `security.php`, `privacy.php`, `appearance.php`,
  `preferences.php`, `composing.php`, `notifications.php`). **Zero** hits under `templates/admin/` or
  `templates/mod/`. Confirmed. Consequences: (a) D's stated reason for a settings-scoped class is false —
  the real reason is that `.scribe-panel` / `.scribe-panel-head` are **Imladris DS components** shipped in
  `resources/imladris/components.css:237-248` and mirrored to `public/assets/imladris.css`, so removing them
  from settings changes the DS component inventory and must be decided explicitly (retire in the DS, or
  scope the new card); (b) S2's regression check "confirm `.scribe-panel` is untouched on `/admin/*`" tests
  nothing — delete it; (c) it is **seven** templates, not "six of the thirteen".
- **R2 — re-cite or drop ADR 0021.** `docs/adr/0021…:47-52` defers an **operator-console** password-policy
  *editor* (ADMIN §9.3), not a member-facing strength meter. The principle transfers; the binding does not.
  Also fix the citation in #34: `AccountService.php:176-178` is inside `setInitialPassword()`; the
  change-password rules are at **`:202-206`**. #34's conclusion (don't ship the 5-tier meter, whose top tier
  is fiction) survives on its own merits.
- **R3/MC1 — see F.2.**
- **R4 — citation drift in production refs:** topbar partial is `layout.php:50-52` (not 53-55);
  `partials/flash` is `layout.php:61` (not 60); `data-theme` is stamped at `layout.php:20` (not 19);
  `layout.php:27` is the `<title>` fallback, not a topbar wordmark; the composer draft-saved text node is
  `composer.js:902` (not 903).

### F.2 Reclassifications

| D rows | Was | **Now** |
|---|---|---|
| **#38 + #39** | feature-removed (QR box) + feature-added (Authenticator URI) | **one `feature-changed`.** Both sides implement the same step — get the enrollment secret into the authenticator. Design `:143` already renders a readonly **"Authenticator secret"** field, identical to `security.php:65-67`; production adds an `otpauth://` URI field at `:68-71` instead of a QR. Design wins on card layout and the 88×88 slot's position; production wins on mechanics; reword away from "Scan the cipher"; **ship no empty QR box**. |
| **#70 + #71** | feature-removed (3 per-event email switches) + feature-added (pause-all) | **one `feature-changed`.** Member control over outbound email exists in production at coarser granularity — `EmailPreferenceService::pauseAllEmail/setPauseAllEmail` + `notifications.php:38-42`, plus per-subscription `email_enabled` (`notifications.php:63`). Record the **granularity reduction** against USER §4.6; do not build the three per-event switches (`:368-370`) whose third label is fiction. |
| **#49** | feature-removed ("Members I have replied to") | **`feature-changed`.** Design `:265` and `privacy.php:32-36` both render a three-option select in the same slot (Everyone / *middle* / No one); only the middle predicate and its label differ. Side effects D never recorded: the **label** delta on the option that does exist, and that the first select's options **"Public — anyone can view"** and **"Members only — signed-in members"** (`:264`) are already **verbatim identical** design↔production. |
| **#25** | one constraint | **split into two rows.** `constraint`: the *trigger* must stay the server flash — a client-fired toast lies when the POST fails. `copy` (**mandatory, not "if desired"**): the pill geometry, `--green-800` on `--parchment-50`, `border-radius: 999px`, `role="status"`, fixed centre-bottom position. D's "adopt the toast skin if desired" is the aesthetic escape hatch the brief forbids. |
| **#1** | one constraint, "out of this screen's scope" | **split into two rows.** `constraint`: the star SVG, the "Imladris" wordmark and the "Back to the council" lexicon are fiction — do not port. `feature-removed` (**new row #1b**): the back-out *affordance* itself. Production's settings surface offers no in-page back-out; the persistent three-pane sidebar (`layout.php`, `variant=app`) is that affordance, so a second in-page back link would be redundant chrome. **Recorded with a reason** — not waved away as out of scope. |

### F.3 Action rewrites (no class change)

- **M2 — never "convert the `<h2>` to a span".** Rows **#17**, **#84**, **#87**, **#110** must read *"keep
  the `<h2>`/`<h3>` element, restyle it into the eyebrow register."* Production already reconciles design
  and a11y this way (`<h2 class="scribe-panel-head">Password</h2>`), and
  `AppImladrisFidelityTest::test_settings_pages_keep_one_main_landmark_and_real_section_headings` pins it.
- **#16 / H10** — replace the "used across admin" justification (F.1 R1) and the single-padding claim
  (§E5).
- **#34** — replace the ADR 0021 justification (F.1 R2) and fix the `AccountService` citation.

### F.4 New rows added by V (and by this pass)

| New # | Section | Class | Finding |
|---|---|---|---|
| **111** | Test contract | `constraint` | `tests/Integration/Core/AppImladrisFidelityTest.php` pins exactly what S2/S3/S5 propose to delete: `:69-70` `/settings/account` contains `scribe-panel` **and** `field-grid`; `:138` four more pages contain `scribe-panel`; `:142` `/settings/privacy` contains **`gem-check`**; `:170-171`/`:174` the literal `<h2 class="scribe-panel-head">Password</h2>`, `…Two-factor authentication</h2>`, `…Daily digest</h2>`; `:166` exactly one `<main ` per page. **All verified present.** No slice's test plan mentions this file — this is the single most likely cause of a red suite. Same "already shipped and test-pinned" standard D applied to `commends` at `post.php:33`. |
| **112** | Boolean controls | `copy` | **A third idiom D never counted: `.checkline`** — `notifications.php:38-42` (the pause-all control row #71 restyles) and `boards.php:143` (saved-feed "Digest"). Both verified. A "unify on `.switchline`" slice that misses `.checkline` leaves the inconsistency alive on the very control it touches. |
| **113** | Profile custom fields | `constraint` | `settings.php:95` renders the three `custom_label_N`/`custom_value_N` rows only `if (!empty($custom_profile_fields))`, fed from `FeatureFlags::enabled('custom_profile_fields')` (`AccountController.php:91`, `FeatureFlags.php:73`). Row #30 describes the mechanic with no mention of the gate. The restyled section must stay gated and the flag-off render must stay clean. |
| **114** | Rail | `copy` | Design `:49` is `<nav aria-label="Settings sections">`; production `settings_nav.php:29` is `<nav class="subnav settings-rail">` — **no accessible name**, so the landmark is ambiguous alongside the sidebar and topbar navs. D caught the missing `aria-current` (#10) but not this. |
| **115** | Motion | `copy` | Design declares `@keyframes acRise` / `acFade` (`:16-17`) and applies an entry animation to **every** pane wrapper — `:89, :109, :139, :153, :173, :214, :261, :277, :313, :337, :360, :390, :410, :430, :448` — plus the unsaved bar (`:477`) and the toast (`:487`); it also sets `html { scrollbar-gutter: stable; }` (`:14`). Production has no entry animation on any settings pane. Must be honoured against the existing `reduced_motion` preference (`layout.php:23` stamps `data-reduced-motion`) **and** `prefers-reduced-motion`. |
| **116** | Microcopy register | `copy` | The design uses typographic apostrophes throughout (`’` at `:126`, `:268`, `:269`); `grep -rn $'\u2019' templates/account/` returns **zero** hits — production is straight `'` everywhere (`privacy.php:39,40`, `blocks.php:14,16`, `notifications.php:49`). D quotes several of these as matches without noting the character delta. Systematic; decide once, repo-wide. |
| **117** | Security strings | `copy` | Three never compared: design `:145` **"Six-digit code"** vs `security.php:79` "6-digit code"; design `:163` **"Disable two-factor"** vs `security.php:120` "Disable two-factor authentication"; design `:155` renders **"Recovery codes"** as a label-register `<p>` while `security.php:90` uses `<h3>Recovery codes</h3>` (heading level, not just chip styling — apply F.3 M2). |
| **118** | Headings | `copy` | Three panes carry the same heading/register mismatch D scheduled only for Sessions/Blocks/Lifecycle: **"Your subscriptions"** (`:374`) vs `<h2>` in a bare `.card` (`notifications.php:47`); **"Connected accounts"** (`:391`) vs `<h2>` (`connections.php:13`); **"Organize your boards"** (`:314`) vs `<h2>` (`boards.php:21`). The latter two are **verbatim identical strings already** — only the element/register differs. Plus `<h2>Set a password</h2>` (`connections.php:42`) has no design analogue and needs a register decision. |
| **119** | Already-matching strings | `copy` (no change) | **Recorded so no slice churns them.** Verbatim identical design↔production today: `security.php:36` "Change password" (`:127`), `:58` "Start setup" (`:135`), `:83` "Verify and enable" (`:147`), `:106` "Rotate recovery codes" (`:162`); `sessions.php:14` "Active sessions & devices" (`:412`), `:17` "Log out of all other devices" (`:414`), `:33` "Sign out" (`:420`); `connections.php:33` "Not available" (`:400`); `privacy.php` "Public — anyone can view" / "Members only — signed-in members" (`:264`). |

---

## G. §3 fiction table — corrections and additions

Apply the §B.1 anchor remap to rows 7, 13, 14, 17, 18, 21-30. Then:

- **Row 7 (`Regard`)** — fix the description per §E4: rail item at `:57`/`:58` and the footnote sentence at
  `:207`; there is **no pane title**. Action ("do not add the item") unchanged.
- **Row 20 (`You still earn regard…`) — scope is wrong.** D says fix `templates/account/privacy.php:40` in
  place. But "Regard" ships as **user-visible chrome** on the public profile too: `templates/profile/show.php:269`
  `<p class="profile-regard-label">Regard</p>`, `:270` "Regard recognises contribution; it grants no powers.",
  `:314` "… regard", plus `.profile-regard-card` / `.profile-regard-value` / `.profile-regard-note` and the
  `?tab=commends` route (`ProfileController.php:78,137`). Changing only `privacy.php:40` leaves the member
  console saying *reputation* while the profile two clicks away says *Regard*. **Either scope the de-fiction
  repo-wide or record the inconsistency deliberately — S10 as written does neither.**
- **New row 31 — `:140`** `Scan the cipher with your authenticator, then enter the six digits it shows.`
  ("cipher" is design-register fiction for a QR code). Production string: *"Add this secret to your
  authenticator app, then enter the six digits it shows."* D handled the *box* (#38) but never listed the
  *string*.
- **New row 32 — `:539-541`** subscription sample rows (`Evaluations as ritual, not gate`, `#audit-trails`,
  `Where should ratified decisions live?`) — same class as the drafts samples already listed at row 30. Real
  rows from `SubscriptionRepository`; neutral sample board names.

H4's core claim survives V's nuance: `grep -rn reputation_events src/ templates/` hits only
`ReputationLedgerService` and `BadgeRuleService:157,222`, so the **per-event ledger** genuinely does not
exist — production's nearest analogue is the profile Commends tab (a total plus
`topCommendedByUser(…, 5)`), not a ledger. Keep #5 / H4 as **feature-removed**.

---

## H. Corrected classification counts for this screen

D's §2 table shipped **110 rows**: copy 65, feature-added 15, feature-removed 10, feature-changed 11,
constraint 9.

| Operation | Δ |
|---|---|
| Strike #62 (design no longer contains Default sort) | feature-removed −1 |
| Merge #38+#39 → one feature-changed (MC1) | fr −1, fa −1, fc +1 |
| Merge #70+#71 → one feature-changed (MC2) | fr −1, fa −1, fc +1 |
| Re-bucket #49 → feature-changed (MC3) | fr −1, fc +1 |
| Split #25 → constraint(trigger) + copy(pill) (MC4) | copy +1 |
| Split #1 → constraint(fiction) + #1b feature-removed(back-out, with reason) (MC5) | fr +1 |
| New #111 fidelity-test contract (M1) | constraint +1 |
| New #112 `.checkline` (M3) | copy +1 |
| New #113 `custom_profile_fields` flag (M4) | constraint +1 |
| New #114 `nav aria-label` (M5) | copy +1 |
| New #115 pane entry animation + reduced motion (M6) | copy +1 |
| New #116 apostrophe register (M7) | copy +1 |
| New #117 three security strings (M8) | copy +1 |
| New #118 three unconverted headings (M9) | copy +1 |
| New #119 already-verbatim strings (this pass) | copy +1 |

**Corrected totals — 118 surviving rows:**

| Class | Count |
|---|---|
| copy | **73** |
| feature-added | **13** |
| feature-removed | **7** |
| feature-changed | **14** |
| constraint | **11** |
| **total** | **118** |

Surviving `feature-removed` set (7): #5 Regard rail item + ledger pane · #26 operator-defined profile-field
schema · #34 password strength meter · #42 2FA cancel/abandon · #48 "Hidden — wardens only" · #98 Drafts
autosave composer card · #1b topbar back-out affordance.
(M10 and M11 are §3 fiction-table items, not §2 difference rows, so they do not enter the counts.)

---

## I. Slice-plan corrections that follow

1. **S6** — drop "Does not add Default sort" and the C1 framing (§D-1). Keep the v3 reading-keys regression
   assertion as a plain guard.
2. **S2** — delete the check "confirm `.scribe-panel` is untouched on `/admin/*`" (it tests nothing, F.1 R1);
   add the DS-component decision (retire `.scribe-panel`/`.scribe-panel-head` in
   `resources/imladris/components.css:237-248` **or** scope a new settings card) and the padding-variance
   constraint (§E5).
3. **S2 / S3 / S5** — must each name `tests/Integration/Core/AppImladrisFidelityTest.php` and state how its
   `scribe-panel` / `field-grid` / `gem-check` / `scribe-panel-head` assertions are updated in the same
   commit. Without this the suite goes red on the first run (row #111).
4. **S3** — extend to `.checkline` (`notifications.php:38`, `boards.php:143`), row #112.
5. **S4** — add the `custom_profile_fields` flag gate to scope and to the test plan (row #113).
6. **S9** — the toast pill is **mandatory copy**, not optional; only the trigger is a constraint (F.2 #25).
7. **S10** — decide the `Regard` de-fiction scope (privacy.php only vs. `templates/profile/show.php` +
   `ProfileController` + the `.profile-regard-*` classes) before touching `privacy.php:40` (§G row 20).
8. **New cross-cutting slice item** — the pane entry animation + `scrollbar-gutter: stable`, gated on
   `data-reduced-motion` and `prefers-reduced-motion` (row #115); and the apostrophe register decision
   (row #116).
