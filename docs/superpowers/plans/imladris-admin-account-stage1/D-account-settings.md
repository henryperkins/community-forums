# D — account-settings: design vs production

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html`
(760 lines; markup `<x-dc>` runs 9–496, `<script type="text/x-dc">` runs 497–757)

**Production home:** 13 templates under `templates/account/` + `templates/partials/settings_nav.php`, driven by
`src/Controller/AccountController.php`, `SettingsController.php`, `OAuthController.php`, `PasskeyController.php`,
`BlockController.php`, `PersonalOrganizationController.php`, `DraftController.php`.

> Note on the brief: production controllers live in `src/Controller/` (singular), not `src/Controllers/`.
> Second note: production's settings rail is **already vertical and sticky above 720px**
> (`public/assets/app.css:2053-2062`); it degrades to a horizontal wrapped subnav only below 720px.
> The brief's "horizontal subnav" framing is only true on mobile.

---

## 0. Headline findings

**H1 — The IA difference is real but shallower than it looks, and a single-page-with-anchors model is
not compatible with production.** The design is one DOM with 13 panes switched by `go(k)` client state
(`AccountSettings.dc.html:571`, `:621-634`, `:704-707`). Production is 13 URLs, each rendering the same
shell + rail + one pane. Collapsing to one page would break three binding contracts at once:
(a) PRODUCT_DESIGN §5.3 "every view has a real, shareable, crawlable URL"; (b) the anti-draft-loss 422 contract
— `AccountController::updateAccount` (`src/Controller/AccountController.php:85-92`) and nine sibling
methods re-render **their own template** at 422 carrying `->errors` + `->old`, which a single page with
13 concurrently-mounted forms cannot express; (c) progressive enhancement — with JS off, `go(k)` does
nothing and 12 of 13 panes are unreachable. **Verdict: keep 13 routes; adopt the design's rail
grouping, geometry and pane anatomy onto each of them.** The design's rail is not a router, it is a
*grouped index*; production already renders it on every page.

**H2 — The design rail introduces three group headings production does not have.**
`Account` (`:50`), `Reading & writing` (`:60`), `Council` (`:71`). Production's
`templates/partials/settings_nav.php:4-27` is a flat, ungrouped list. Production already ships the exact
grouped-nav substrate on the admin side — `.admin-nav-group` / `.admin-nav-group-title` /
`.admin-nav-group-list` (`public/assets/app.css:2859-2878`) and the group array idiom at
`templates/admin/_nav.php:7-51`. The settings rail should copy that mechanism. `Council` is fiction →
`Community`.

**H3 — The design's Reading pane absorbs Composing; production has a separate `/settings/composing`
page and rail item.** Design `AccountSettings.dc.html:350-355` puts a "Composing" sub-section
(three switches) as the third block of the Reading pane, and the rail has **no** Composing entry.
Production has `templates/account/composing.php` + `GET/POST /settings/composing`
(`src/Core/App.php:2125-2126`) + rail item (`settings_nav.php:10`). Merging would delete a real URL
(H1(a)) and a real POST target. **Recommendation: keep the separate page and rail item; do not adopt
the design's fold-in.** Recorded as a copy difference in the other direction — the design elides it.

**H4 — "Regard" is a whole pane with no production counterpart and must not be built.**
`AccountSettings.dc.html:172-210` is a per-event reputation ledger (total, trend chip, three filters
All/Commends/Milestones, event rows with glyph/delta/milestone wash). Grep confirms: **no controller or
template anywhere reads `reputation_events`** — the only readers are
`src/Service/ReputationLedgerService.php` and `src/Service/BadgeRuleService.php:157,222`, both write/rank
paths. Production surfaces reputation as a *total* on the public profile
(`templates/profile/show.php:265-275`, `?tab=commends`) and the leaderboard. Building the pane means
building a repository, a route, a controller and a paginator that the design never specified.
**feature-removed. Do not build, do not link, do not ship a stub tab.**

**H5 — The design's theme toggle "actually flips the page"; production has *no* client theme code at
all.** `grep -rn "theme" public/assets/*.js` returns only the *branding preview* hooks at
`app.js:119-121`. Production stamps `data-theme` server-side on `<html>` (`templates/layout.php:19`)
from the persisted pref, which is what makes it flash-free. The design flips `data-theme` on its own
root wrapper from client state (`:21` `data-theme="{{ themeAttr }}"`, `:619`). USER §4.1 (per F2)
sanctions instant application **as a JS decoration over a real POST**, and
`document.documentElement.dataset.theme = …` from external `app.js` is CSP-legal (it is a DOM property
write, not an inline style attribute). So this is a **constraint**, not a blocker: add a decoration in
`app.js` that mirrors the radio choice onto `<html>` and leave `POST /settings/appearance` as the sole
persistence path. The design's copy line "Applies the moment you choose it — the rest of this page
follows" (`:280`) must be qualified — with JS off it does not apply until you save.

**H6 — The two-factor flow is the same four server steps but the design drops the password gate at
every one of them.** Production requires `current_password` on enroll, confirm, rotate **and** disable
(`templates/account/security.php:52-121`, `AccountController::startTotpEnrollment/confirm/rotate/disable`
`:220-287`). The design has no password field anywhere in the 2FA card (`:132-166`). Production wins on
behaviour (feature-changed). Two further hard constraints: the design's persistent recovery-code grid in
the *enabled* state (`:156-160`) **cannot be reproduced** — codes are stored as
`hash_hmac('sha256', …)` (`src/Service/MfaService.php:225-228`) and plaintext exists only in the
response that generated them; and the "cipher" QR square (`:142`) is a placeholder glyph, not a QR —
production has no QR encoder and shows the `otpauth://` URI instead (`security.php:68-71`).

**H7 — The sticky "You have unsaved changes · Discard · Save changes" bar (`:477-484`) plus the toast
"Saved to your seat." (`:487-492`) are a client dirty-buffer over all 13 panes.** This is the exact
pattern F2 records as head-on conflict C6. Every production pane is its own `<form method="post">` with
its own submit button and its own 422 re-render. The bar may ship **only** as a JS decoration bound to
one form at a time; it must never be the only save affordance, and the per-form submit buttons stay.
The toast maps to the existing server flash (`templates/partials/flash.php`,
`redirectWithFlash('/settings/privacy', 'Your privacy settings were saved.')` etc.) — adopt the toast
*skin*, keep the server round-trip.

**H8 — Production has ten whole capabilities the design never modeled.** Passkeys panel
(`security.php:125-192`), avatar upload/remove (`settings.php:22-54`), email-verification notice
(`settings.php:12-20`), display name / bio / signature, "Set a password" for OAuth-only accounts
(`connections.php:40-58`), pause-all-email (`notifications.php:38-42`), board folders + saved feeds +
bookmark folders (`boards.php:52-205`), server-drafts vs browser-local split (`drafts.php:13-46`),
reactivate + pending-deletion-cancel branches (`lifecycle.php:36-41,58-63`), Appeals rail entry and
Replay-tour button (`settings_nav.php:25-34`). All keep; all must be styled in the design idiom.

**H9 — Production runs two incompatible boolean-control idioms inside the same rail.**
`.gem-field`/`.gem-check` 24px gem checkboxes in `privacy.php:38-42` and `preferences.php:42-46`;
`.switchline`/`.switch` track switches in `appearance.php:57` and `composing.php:23-25`. The design uses
the DS `Switch` component in **every** boolean position (11 uses). Both classes exist in the DS
(`docs/design-system/imladris/components.css:184-201` and `:266-297`), so this is not a token problem —
it is an unforced inconsistency. Unify on `.switchline`/`.switch`.

**H10 — Panel substrate conflict.** Every design panel is a plain `--surface-raised` card:
`border: 1px solid var(--border-hair); border-radius: var(--radius-lg); box-shadow: var(--shadow-xs);
padding: 20px 22px 22px` (`:90`, `:110`, `:130`, `:261`, `:278`, `:313`, `:337`, `:362`, `:391`, `:411`,
`:431`, `:450`). Production's settings panes use `.scribe-panel` — an octagonal `clip-path` with a
double gold inset frame (`app.css:2641-2647`) — for six of the thirteen and bare `.card` for the rest.
The design is authoritative: settings panels become the plain card. **Do not redefine `.scribe-panel`
globally** (it is used across admin); author a settings-scoped class instead. This is the single
largest visual delta on the screen.

---

## 1. Section-order comparison

The design's authoritative order is the **rail** order (only one pane is mounted at a time). Its DOM
pane order differs and is irrelevant to production.

| # | Design rail (source line) | Production rail (`settings_nav.php`) | Production URL / template |
|---|---|---|---|
| — | Page head: eyebrow "Your seat at the council" (`:41`), h1 "Account settings" (`:42`), intro ¶ (`:43`) | eyebrow "Account", h1 "Account settings", **no intro** | every `templates/account/*.php` head |
| — | **group "Account"** (`:50`) | *(no groups)* | — |
| 1 | Profile (`:51`) | Profile (`:5`) | `GET /settings/account` → `account/settings.php` |
| 2 | Security (`:53`) | Security (`:6`) | `GET /settings/security` → `account/security.php` |
| 3 | Privacy (`:55`) | Privacy (`:7`) | `GET /settings/privacy` → `account/privacy.php` |
| 4 | **Regard** (`:57`) | *(absent)* | *(no route — feature-removed)* |
| — | **group "Reading & writing"** (`:60`) | *(no groups)* | — |
| 5 | Appearance (`:61`) | Appearance (`:8`) | `GET /settings/appearance` → `account/appearance.php` |
| 6 | Reading (`:63`) | Reading (`:9`) | `GET /settings/preferences` → `account/preferences.php` |
| — | *(folded into Reading, `:350`)* | **Composing** (`:10`) | `GET /settings/composing` → `account/composing.php` |
| 7 | Drafts (`:65`) | Drafts (`:12-14`, flag `drafts`) | `GET /drafts` → `account/drafts.php` |
| 8 | Boards (`:68`) | *(moved to position 12)* | `GET /settings/boards` → `account/boards.php` |
| — | **group "Council"** (`:71`) | *(no groups)* | — |
| 9 | Notifications (`:72`) | Notifications (`:15`) | `GET /settings/notifications` → `account/notifications.php` |
| 10 | Connections (`:74`) | Connections (`:16-18`, flag `oauth`) | `GET /settings/connections` → `account/connections.php` |
| 11 | Blocks (`:76`) | *(position 11)* Sessions (`:19`) | — |
| 12 | Sessions (`:78`) | Blocks (`:20`) | `GET /settings/blocks` → `account/blocks.php` |
| 13 | Account (`:80`) | Boards (`:21`) | — |
| — | — | Account (`:22-24`, flag `account_lifecycle`) | `GET /settings/account/lifecycle` → `account/lifecycle.php` |
| — | *(absent)* | **Appeals** (`:25-27`, flag `appeals`) | `GET /appeals` → `appeals/index.php` |
| — | *(absent)* | **Replay tour** button (`:33-35`, flag `product_tour`) | JS decoration, `data-tour-replay` |
| — | Unsaved-changes bar, fixed bottom (`:477`) | *(absent)* | — |
| — | "Saved to your seat." toast, fixed (`:487`) | `.flash` banner top of `<main>` | `templates/partials/flash.php` |

**Net ordering change for production:** Blocks and Sessions swap; Boards moves from 12 → 8 (into the
Reading & writing group); Composing stays as its own rail item (H3) but is placed after Reading;
Appeals and Replay-tour join the Community group at the end; Regard is never added.

---

## 2. Difference table

Classification key: **copy** = production simply changes to match. **feature-added** = production has
it, design never modeled it — keep and style. **feature-removed** = design shows it, production does not
implement it — do not build. **feature-changed** = same concept, different mechanics — design wins on
presentation, production on behaviour. **constraint** = design cannot be copied verbatim because of a
hard production constraint.

### 2.1 Shell, rail, page head

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Topbar | constraint | 58px sticky bar: elven-star SVG + "Imladris" wordmark (`:26-27`), "Back to the council" (`:29`), initials chip, member name, "Log out" (`:33`) | `templates/layout.php:53-55` renders `partials/topbar`; brand name from `$brand['name']` (`layout.php:27`) | Do not port. Production topbar is out of this screen's scope; the mark and wordmark are fiction. | low |
| 2 | Whole screen | constraint | One DOM, 13 panes, `go(k)` client state (`:571`, `:621-634`) | 13 routes, `src/Core/App.php:2095-2142` | Keep 13 URLs. Rail is a grouped index, not a router. | high |
| 3 | Rail | copy | 3 group headings: "Account" (`:50`), "Reading & writing" (`:60`), "Council" (`:71`); `.62rem`, `.18em`, uppercase, `--text-faint`, `padding: 0 0 6px 12px` / `14px 0 6px 12px` | flat ungrouped list, `templates/partials/settings_nav.php:29-32` | Add groups using the admin idiom (`app.css:2859-2878`, `templates/admin/_nav.php:7-51`). "Council" → "Community". | med |
| 4 | Rail | copy | Order: Profile, Security, Privacy, Regard \| Appearance, Reading, Drafts, Boards \| Notifications, Connections, Blocks, Sessions, Account | `settings_nav.php:4-27` order differs (Boards at 12; Sessions before Blocks) | Reorder to the design; move Boards into Reading & writing; swap Sessions/Blocks. | low |
| 5 | Rail | feature-removed | "Regard" item + a full reputation-ledger pane (`:57-58`, `:172-210`) | no route, no repository; `reputation_events` read only by `ReputationLedgerService`/`BadgeRuleService.php:157,222` | Do not add the item. Do not build the pane. Record as a gap. | low |
| 6 | Rail | feature-added | — | "Composing" (`settings_nav.php:10`) → `/settings/composing` | Keep the item and the page (H3). Style in idiom. | low |
| 7 | Rail | feature-added | — | "Appeals" (`settings_nav.php:25-27`, flag `appeals`) | Keep, in the Community group. | low |
| 8 | Rail | feature-added | — | "Replay tour" `<button class="linkbtn subnav-action" data-tour-replay>` (`settings_nav.php:33-35`) | Keep. It is a button, not a link — style as a rail action, visually subordinate. | low |
| 9 | Rail | copy | Every item carries a 15px stroke icon (`:51-81`) | text-only anchors (`settings_nav.php:31`) | Add icons via `templates/partials/icon.php`. | med |
| 10 | Rail | copy | Active item: `border-left: 2px solid var(--gold-500)`, `background: var(--brand-subtle)`, `color: var(--on-brand-subtle)`, `border-radius: 0 md md 0`, `aria-current="page"` (`:51`) | `box-shadow: inset 3px 0 0 var(--accent-2)` + brand-subtle (`app.css:2061`); **no `aria-current`** (`settings_nav.php:31`) | Switch to the 2px gold-500 left rule; add `aria-current="page"`. | low |
| 11 | Rail | copy | 232px column, `gap: 30px`, `position: sticky; top: 84px`, `gap: 2px` between items (`:46`, `:49`) | 188px, `gap: 2px 30px`, `top: calc(var(--topbar-h) + 22px)` (`app.css:2054-2058`) | Widen to 232px; keep the topbar-relative sticky offset. | low |
| 12 | Rail | constraint | No feature-flag concept at all | flags hide items silently (`settings_nav.php:12,16,22,25,33`) | The silent-omit idiom must survive. Do **not** import the admin "Disabled until the feature flag is enabled" span here — that string is pinned to the admin nav. | low |
| 13 | Page head | copy | eyebrow "Your seat at the council" (`:41`) + h1 2.4rem `--font-display` w500 (`:42`) + intro ¶ 62ch, 1rem/1.55, `--text-muted`, `text-wrap: pretty` (`:43`) | eyebrow "Account", h1 1.95rem (`app.css:2615-2618`), **no intro ¶** — all 13 templates, e.g. `templates/account/settings.php:4-7` | Add the intro ¶; raise h1 to 2.4rem. Eyebrow string is fiction → §3. | low |
| 14 | Page head | copy | single h1 "Account settings" for all panes | `templates/account/drafts.php:7` writes `<h1>Drafts</h1>`; the other 12 write "Account settings" | Make drafts.php consistent. | low |
| 15 | Container | copy | `max-width: 1064px; padding: 30px 28px 132px` (`:37`) | `max-width: 1000px; padding: 26px 28px 64px` (`app.css:2602-2607`) | Match. The 132px bottom pad is headroom for the unsaved bar (#20). | low |
| 16 | Panel substrate | copy | plain card: `--surface-raised`, `1px solid var(--border-hair)`, `var(--radius-lg)`, `var(--shadow-xs)`, `padding: 20px 22px 22px` (12 sites) | `.scribe-panel` octagon + double gold inset frame (`app.css:2641-2647`) on 6 panes; bare `.card` (`app.css:159`) on the rest | Author a settings-scoped card class matching the design. Do **not** redefine `.scribe-panel` (admin depends on it). | high |
| 17 | Panel eyebrow | copy | `.66rem`, `.16em`, uppercase, `--gold-ink`, plain span, `margin-bottom: 15px`, **no rule** (`:92`, `:111`, `:131`, …) | `.scribe-panel-head` `.72rem`, `.14em`, plus a `::after` hairline filling the row (`app.css:2648-2663`) | Resize to `.66rem`/`.16em`; drop the hairline. | med |
| 18 | Field label | copy | `.8rem`, `--font-label`, `.02em`, `--text-muted`, `margin-bottom: 6px` (`:98` etc.) | `.settings-pane .field > span:first-child` = `.82rem`, `.02em`, `margin-bottom: 5px` (`app.css:3193-3201`) | Align to `.8rem` / 6px. | low |
| 19 | Input skin | copy | engraved octagon `clip-path` **8px**, `--shadow-inset` + `inset 0 0 0 1.5px var(--gold-200)`; hover → `gold-400`; focus → `2px gold-500` + `5px color-mix(gold-100 60%)`; `caret-color: var(--gold-600)`; `padding: 10px 13px`; `font-size: 1rem` (`:98`) | `.input-engraved` clip **9px**, no hover rule, focus `1.5px gold-500 + 3px var(--focus-ring)` (`app.css:2487-2499`); base `.input` `padding: 9px 11px` (`app.css:256-263`) | Match clip 8px, add the hover rule, widen focus to 2px + 5px halo, `padding: 10px 13px`, add the gold caret. | med |
| 20 | Select skin | copy | chevron `11×7`, stroke `#B08A3A` (gold), `right 13px center`, `padding: 10px 32px 10px 13px` (`:100`) | chevron `12×12`, stroke `%235C685D` (green), `right 12px center`, `padding-right: 32px` (`app.css:3202-3210`) | Match the gold 11×7 chevron and geometry. | low |
| 21 | Security inputs | copy | all password inputs use the engraved skin (`:112-115`) | `templates/account/security.php:23,28,33` use bare `class="input"`, not `input-engraved` | Apply the engraved skin consistently across all 13 templates. | low |
| 22 | Boolean controls | copy | DS `Switch` in all 11 boolean positions (`:268-270`, `:299`, `:346-354`, `:369-371`) | `.gem-field`/`.gem-check` in `privacy.php:38-42` and `preferences.php:42-46`; `.switchline`/`.switch` in `appearance.php:57`, `composing.php:23-25` | Unify on `.switchline`/`.switch` (H9). | med |
| 23 | Switch sub-note | copy | separate `<p style="margin: 4px 0 0 53px">` aligned past the switch (`:268-269`) | `.gem-sub` nested inside the label span (`privacy.php:39-40`), rendered as a stacked flex child (`app.css:2771-2775`) | Move the note out of the label; indent 53px. | low |
| 24 | Save affordance | constraint | one fixed bar "You have unsaved changes. · Discard · Save changes" over all panes (`:477-484`) driven by a client dirty flag (`:580-586`) | per-form submit buttons: "Save changes" (`settings.php:110`), "Save privacy settings" (`privacy.php:43`), "Save appearance" (`appearance.php:58`), "Save reading preferences" (`preferences.php:47`), "Save composing preferences" (`composing.php:26`), "Save digest settings" (`notifications.php:43`) | Keep every per-form button. The bar may ship only as a JS decoration scoped to one form. Anti-draft-loss 422 depends on the real submit. | high |
| 25 | Success confirmation | constraint | centred fixed pill toast `--green-800` on `--parchment-50`, `border-radius: 999px`, `role="status"`, auto-dismiss 2400ms (`:487-492`, `:584`) | `.flash` banner at top of `<main>` (`templates/partials/flash.php`, `layout.php:60`) from `redirectWithFlash` | Adopt the toast skin for `.flash` if desired, but keep the server round-trip — client-only confirmation would lie when the POST fails. | med |

### 2.2 Profile

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 26 | Profile | feature-removed | Operator-defined **typed field schema**: fields carry mono type chips `text` / `text` / `select` / `url` (`:98-101`), one is a real `<select>` with four options, and the panel says the fields are defined by staff | `user_profile_fields` is per-user `(label, value, position)` — `database/migrations/0062_bookmark_folders_profile_fields.php:40`, `src/Repository/UserProfileFieldRepository.php:25`. No admin surface defines a field schema (grep `profile_field` over `templates/admin/` → 0). | Do not build a schema. Do not ship type chips or a `select` custom field — they would be dead chrome. | med |
| 27 | Profile | feature-added | — | Email (disabled) `settings.php:59-62`, Display name `:64-68`, Bio `:85-89`, Signature `:90-94` | Keep all four. Style in the design's engraved register. | low |
| 28 | Profile | feature-added | — | Avatar panel with upload + remove, behind `profile_media` (`settings.php:22-54`, `AccountController::uploadAvatar/removeAvatar:178-195`) | Keep. Give it its own design card above Identity. | low |
| 29 | Profile | feature-added | — | Email-verification notice card + "Resend verification email" (`settings.php:12-20`) | Keep. Style as a `--gold-soft` status callout above the panels. | low |
| 30 | Profile | feature-changed | Four labelled fields drawn from a schema | three fixed `custom_label_N` / `custom_value_N` row pairs inside a `<fieldset>` with `<legend>Custom profile fields</legend>` and the cap copy "Add up to three public profile facts…" (`settings.php:95-109`) | Keep the 3-row mechanic and the cap copy; restyle the row to the design's label+chip+input rhythm minus the type chip. | med |
| 31 | Profile | feature-changed | Homepage as a split prefix group: static `https://` chip on `--surface-sunken` + input, placeholder `example.com` (`:101`) | single `<input type="url" placeholder="https://example.com">` storing the full URL (`settings.php:81`) | Keep `type="url"` and the full stored value (the split prefix would silently drop the scheme). Adopt the visual prefix as a decorative, non-submitting affix only if the value round-trip is preserved; otherwise keep the plain field with the engraved skin. | med |
| 32 | Profile | copy | Helper under Homepage: "Shown as a link on your profile." (`:101`) | no helper text on any Identity field | Add per-field helpers in the design's `.84rem` / `--text-faint` register. | low |
| 33 | Profile | copy | Panel intro ¶ "These read as a tidy Details block on your profile." + a right-aligned italic annotation on the eyebrow row (`:94-96`) | no intro ¶ on the Identity panel | Add the intro (fiction-free half); the italic annotation is fiction (§3). | low |

### 2.3 Security

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 34 | Password | feature-removed | 96×5px strength meter with four tiers (`:118-124`) and labels "Not set" / "Too easily guessed" / "Passable" / "Strong" / "Worthy of the council" (`:595-604`) | no meter; the only rule is `limits.password_min` (default 8), enforced server-side (`src/Service/AccountService.php:176-178`) | Do not build. ADR 0021 deferral #3 binds: a password policy ships only with its enforcement — a client meter with no policy is dead chrome. | low |
| 35 | Password | feature-changed | live client hint "The two new passwords don't match yet." (`:126`) | server-side mismatch validation surfacing as `errors['new_password_confirm']` at 422 (`security.php:34`, `AccountController::updateSecurity:211-213`) | Keep the server error as the source of truth; the live hint may be added as a JS decoration in the same `--danger` `.86rem` register. | low |
| 36 | Password | copy | Current password `max-width: 340px` on its own row; New + Confirm in a `1fr 1fr` grid (`:112-116`) | three stacked full-width `.field`s (`security.php:21-35`) | Adopt the 340px + 2-col layout. | low |
| 37 | 2FA | feature-changed | No `current_password` on Start setup / Verify / Rotate / Disable (`:135`, `:147`, `:162-163`) | `current_password` required on all four (`security.php:52-59, 72-84, 100-107, 109-121`) | Keep every password gate. Design wins only on the card layout. | med |
| 38 | 2FA | feature-removed | 88×88 "cipher" square with a QR glyph, copy "Scan the cipher with your authenticator" (`:140-142`) | no QR encoder anywhere; production shows the `otpauth://` URI in a second readonly field (`security.php:68-71`) | Do not ship an empty QR box. Reword to reference the secret/URI. | low |
| 39 | 2FA | feature-added | — | "Authenticator URI" readonly field (`security.php:68-71`) | Keep — it is what makes the flow work without a QR. Style as a second readonly mono field beside the secret. | low |
| 40 | 2FA | copy | Six-digit input: mono `1.05rem`, `letter-spacing: .3em`, centred, `maxlength=6`, `placeholder="000000"`, `inputmode=numeric`, `autocomplete=one-time-code`, `max-width: 170px` (`:145`) | plain `.input`, `inputmode=numeric`, `autocomplete=one-time-code`, no maxlength/placeholder/centring (`security.php:80`) | Adopt the design's OTP skin verbatim. | low |
| 41 | 2FA | constraint | "Verify and enable" disabled until 6 digits (`:147`, `otpIncomplete` `:645`) | server validates; button always enabled | A JS `disabled` is decoration only — the server check at `MfaService::confirmEnrollment` stays authoritative and the button must be enabled with JS off. | low |
| 42 | 2FA | feature-removed | "Cancel" (ghost) beside Verify, abandoning a started enrolment (`:148`, `:648`) | no cancel route; a started enrolment shows "Enrollment started. Verify a code to finish…" (`security.php:44`) and persists | Do not add a dead Cancel. Record the gap: there is no way to abandon a pending TOTP enrolment. | low |
| 43 | 2FA | constraint | Persistent 3-col grid of the six recovery codes in the *enabled* state (`:156-160`) | codes are `hash_hmac('sha256', …)` (`MfaService.php:225-228`); plaintext exists only in the response that generated them, rendered once via `new_recovery_codes` (`security.php:88-97`) | Cannot be reproduced. Keep the show-once grid; keep the remaining-count line. | low |
| 44 | 2FA | copy | "Enabled" pill (`--surface-done` / `--on-done` / `1px --green-200`, `999px`, `.6rem`, `.14em`) + "{n} recovery codes remaining — each works once." (`:154`) | `<p class="muted">Enabled. N recovery codes remaining.</p>` (`security.php:42`) | Adopt the pill + the "each works once" clause. | low |
| 45 | 2FA | feature-changed | Rotate (secondary sm) and Disable (danger sm) sit in a button row with no fields (`:161-164`) | each is its own `<form>` with a password field, and Disable also needs `disable_code` (`security.php:100-121`) | Keep both forms and both gates; adopt the button sizing and the danger variant. | med |
| 46 | 2FA | copy | Recovery codes as `--surface-sunken` mono chips, `padding: 7px 11px`, `shadow-inset`, 3-col grid, centred (`:156-158`) | `<ul class="code-list"><li><code>` (`security.php:91-95`) | Adopt the chip grid. | low |
| 47 | Passkeys | feature-added | — | Whole panel behind `passkeys` (default ON, `FeatureFlags.php:86`): list with nickname/added/last-used/synced, rename form, revoke form with password-or-step-up, JS-gated add form, `<noscript>` fallback (`security.php:125-192`) | Keep entirely. Style the credential rows on the design's session-row rhythm; keep the `<noscript>` copy. | med |

### 2.4 Privacy

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 48 | Privacy | feature-removed | Third profile-visibility option "Hidden — wardens only" (`:264`) | only `public` \| `members`, enforced at `src/Service/AccountService.php:143-144` and rendered at `privacy.php:26-27` | Do not add. Also fiction (§3). | low |
| 49 | Privacy | feature-removed | DM scope "Members I have replied to" (`:265`) | only `everyone` \| `members` \| `none` (`AccountService.php:145-146`, `privacy.php:33-35`) | Do not add — there is no such predicate in the DM gate. | low |
| 50 | Privacy | copy | Eyebrow "Who can see you"; the two selects in a `1fr 1fr` grid with a 20px gap and an 18px-padded hairline before the toggles (`:262-267`) | eyebrow "Privacy"; selects stacked full-width; no divider (`privacy.php:21-38`) | Adopt the eyebrow, the 2-col grid and the divider. | low |
| 51 | Privacy | copy | Select label "Direct messages from" (`:265`) | "Allow direct messages from" (`privacy.php:31`) | Adopt the shorter label. | low |
| 52 | Privacy | copy | Toggle notes: "A leaf marks your presence beside your name." (`:268`) — identical to production; "You still earn regard…" (`:269`) — fiction; third toggle has no note (`:270`) | same three toggles, same first note (`privacy.php:39-41`) | Keep the three toggles; de-fiction the second note (§3). | low |

### 2.5 Appearance

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 53 | Theme | constraint | "Applies the moment you choose it — the rest of this page follows." (`:280`); client sets `data-theme` from state (`:21`, `:619`) | server stamps `data-theme` on `<html>` (`layout.php:19`); **zero client theme code** (`grep theme public/assets/*.js` → only the branding preview at `app.js:119-121`) | Add a JS decoration mirroring the radio onto `document.documentElement`; keep `POST /settings/appearance` as the only persistence. Qualify the copy so it is not false with JS off. | med |
| 54 | Theme | copy | Swatch = three separated rounded chips `22×26`, `22×26`, `10×26`, `gap: 3px`, `radius: 3px`, hardcoded hexes (`:282-287`) | `.theme-swatch` = one 30px strip, `overflow: hidden`, no gaps, `.sw-accent` `flex: 0 0 8px`, painted from tokens (`app.css:2028-2033`) | Adopt the design's chip geometry; keep painting from tokens, not hexes. | low |
| 55 | Theme | copy | Twilight accent chip is gold `#D2B062` (`:284`) | `.swatch-twilight .sw-accent { background: var(--green-500) }` (`app.css:2032`) | Repaint the twilight accent gold (`--gold-400`). | low |
| 56 | Theme / Density | copy | Theme grid `repeat(3, 1fr)`, gap 11px; Density grid `1fr 1fr`, gap 11px (`:281`, `:291`) | both `repeat(auto-fit, minmax(150px, 1fr))`, gap 12px (`app.css:2020`) | Match the fixed column counts and the 11px gap. | low |
| 57 | Density | copy | Density cards are title + description only — **no preview bars** (`:292-295`) | `.density-prev` three/four bar preview (`appearance.php:42,45`, `app.css:2034-2036`) | Remove the preview bars to match. | low |
| 58 | Theme / Density | copy | Card descriptions have no trailing period: "Warm paper — daylight", "Evergreen night", "Match your device", "A card per topic — for reading", "One line per topic — for triage" (`:282-295`) | "Warm paper — daylight register.", "Evergreen night register.", "Match your device.", "A card per topic — for reading.", "One line per topic — for triage." (`appearance.php:29-46`) | Match exactly: drop "register" and the trailing periods. | low |
| 59 | Prefs export/reset | copy | One card, `space-between`, a single 44ch sentence "Download a copy of your appearance, reading, and composing preferences, or reset them to defaults." + two sm buttons (`:301-307`) | two separate cards with two sentences (`appearance.php:61-70`) | Merge into one card with the design's single sentence. Keep the export as a GET `<a download>` (`/settings/preferences/export`) and the reset as a POST form. | low |
| 60 | Font size | copy | `max-width: 200px` select, options Small / Medium / Large (`:298`) | full-width select, same options (`appearance.php:49-56`) | Constrain to 200px. | low |
| 61 | Appearance | copy | Eyebrows "Theme" and "Density" are panel eyebrows (`.66rem` uppercase gold-ink); reduce-motion is a bare switch with no eyebrow (`:279`, `:290`, `:299`) | one panel eyebrow "Appearance"; Theme/Density are `.field > span` labels (`appearance.php:22,25,39`) | Promote Theme and Density to panel eyebrows. | low |

### 2.6 Reading (+ the design's folded-in Composing)

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 62 | Reading | feature-removed | "Default sort" select — Last post / Newest / Most replies (`:342`) | `thread_sort` retired from the managed schema at `PreferenceSchema::VERSION = 3` (`src/Support/PreferenceSchema.php:30,141`); the reading section is `threads_per_page`, `posts_per_page`, `show_signatures`, `show_avatars`, `show_reactions` (`:47-53`) | Do not re-add. Binding: 2026-08-02 plan Task 1 Step 5 + USER §4.2 fixed order. This is F2 conflict C1. | low |
| 63 | Reading | copy | Pagination row is a `1fr 1fr 1fr` grid (`:339`) — becomes `1fr 1fr` once Default sort is dropped | two stacked full-width selects (`preferences.php:25-41`) | Adopt a 2-col grid. | low |
| 64 | Reading | copy | Sub-section eyebrows "Pagination" (`:338`) and "What appears in a thread" (`:344`), each preceded by an 18px-padded hairline | one panel eyebrow "Reading"; no sub-headings, no dividers (`preferences.php:23,42`) | Adopt both eyebrows and the dividers. | low |
| 65 | Composing | copy | Folded in as the Reading pane's third block under eyebrow "Composing" (`:350`) | separate page + rail item (H3) | Keep the separate page; adopt the eyebrow and switch rhythm there. Do not fold. | low |
| 66 | Composing | feature-changed | "Press Enter to send — Shift+Enter for a new line" (`:352`) | "Press <kbd>Enter</kbd> to send outside lists, quotes, and code on desktop. <kbd>Ctrl</kbd>/<kbd>Cmd</kbd>+<kbd>Enter</kbd> always sends; <kbd>Shift</kbd>+<kbd>Enter</kbd> inserts a new line. On touch devices, use Send." (`composing.php:23`) | Keep production's string — the design's is factually incomplete. Adopt the switch skin. | low |
| 67 | Composing | feature-changed | "Show a live preview while composing" (`:353`) | "Start with the preview pane open (source mode)" (`composing.php:24`) — `show_preview` is a start-state, not a visibility toggle | Keep production's string. | low |
| 68 | Composing | copy | "Continue lists and quotes on the next line" (`:354`) | identical (`composing.php:25`) | No change. | low |
| 69 | Composing | copy | no panel intro | "These control how the shared Markdown composer behaves for new topics, replies, direct messages, and edits." (`composing.php:22`) | Keep production's intro — the design's pane has one implicitly via the Reading intro. Low-risk retention. | low |

### 2.7 Notifications

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 70 | Notifications | feature-removed | Three per-event email switches: "@mentions me", "replies to threads I opened", "the weekly council summary" (`:369-371`) | no per-event preference exists — `EmailPreferenceService` has only `pauseAllEmail`/`setPauseAllEmail` (`src/Service/EmailPreferenceService.php:20-32`) | Do not build. Record the gap against USER §4.6 (the full per-event × per-channel matrix is a spec promise the design also under-serves). | med |
| 71 | Notifications | feature-added | — | "Pause all email" checkbox + note "In-app notifications still arrive." (`notifications.php:38-42`) | Keep. Restyle as a Switch with a `53px` sub-note. | low |
| 72 | Notifications | copy | Timezone + Digest hour in a `1fr 1fr` grid (`:364-367`) | stacked full-width (`notifications.php:20-37`) | Adopt the 2-col grid. | low |
| 73 | Notifications | copy | Digest-hour label "Digest hour, local" (`:366`) | "Digest hour (selected timezone; UTC if unset)" (`notifications.php:30`) | Adopt the design's shorter label only if the "Not set (UTC)" timezone option keeps the fallback legible; otherwise keep production's. Prefer: label "Digest hour, local" + a `.84rem` helper carrying the UTC fallback. | low |
| 74 | Notifications | copy | Timezone shows three sample options (`:365`) | full `DateTimeZone::listIdentifiers()` plus "Not set (UTC)" (`notifications.php:23-26`, `SettingsController.php:137`) | Keep the full list — the design's three are placeholders. | low |
| 75 | Subscriptions | copy | Row: link + mono `.74rem` meta on a second line + "Unsubscribe" text button on the right; hairline between rows (`:376-383`) | link, then inline `<span class="muted">· Frequency · email</span>`, then an inline form (`notifications.php:61-71`) | Adopt the two-line row with mono meta and a right-aligned Unsubscribe. | low |
| 76 | Subscriptions | copy | Empty: "No subscriptions. Watch a thread and it will appear here." (`:384`) | "You aren't subscribed to any threads or boards yet." (`notifications.php:49`) | Adopt the design string. | low |

### 2.8 Connections

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 77 | Connections | feature-changed | "Link a provider to sign in faster. Email and password always stay available." (`:393`) | "Link a sign-in provider to sign in faster. Email/password always stays available." (`connections.php:14`) — **false for OAuth-only accounts**, which is exactly why `has_password` gates a Set-a-password panel (`OAuthController.php:121`, `connections.php:40-58`) | Rewrite both. F2 conflict C10. Proposed: "Link a provider to sign in faster. You always keep at least one way to sign in." | low |
| 78 | Connections | copy | 34px rounded-square provider mark with initials on `--surface-sunken` (`:397`) | text-only `.connection-name` (`connections.php:18`) | Add the mark tile. | low |
| 79 | Connections | copy | Two-line row: name (`.96rem` label) + mono `.74rem` sub — the linked email, "Not connected", or "Provider not configured" (`:398`, `:691`) | single line; email rendered as a loose `<span class="muted">` only when linked (`connections.php:22-24`); unconfigured shows "Not available" (`:33`) | Adopt the two-line row and the three sub-line states. | low |
| 80 | Connections | feature-added | — | "Set a password" panel for OAuth-only accounts, `POST /settings/connections/set-password` (`connections.php:40-58`, `App.php:2089`) | Keep. Style as a second design card. | low |
| 81 | Connections | copy | Footnote "Disconnecting a provider does not delete anything — it only removes that sign-in route." (`:405`) | none | Add. | low |
| 82 | Connections | copy | "Connected" as a `999px` pill with `--green-200` border on `--surface-done` (`:399`) | `<span class="pill">Connected</span>` (`connections.php:20`) | Match the done-register pill. | low |

### 2.9 Sessions

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 83 | Sessions | feature-changed | "Sessions expire after 30 days of inactivity." (`:425`) | expiry is **absolute**: `expires_at = now + lifetime_days` at login (`src/Security/Session.php:153-155`), and `touch()` updates only `last_seen_at` (`src/Repository/SessionRepository.php:48-51`). `SESSION_LIFETIME_DAYS` defaults to 30 (`config/config.php:35`). | Adopt the footnote position and register; rewrite: "Sessions expire 30 days after you sign in." | low |
| 84 | Sessions | copy | Header row: eyebrow + spacer + "Log out of all other devices" (secondary sm) (`:412-416`) | `.sessions-head` with `<h2>` + inline form (`sessions.php:13-19`) | Convert the h2 to the panel eyebrow; keep the form. | low |
| 85 | Sessions | copy | Row: UA (`.96rem` label) with an inline "This device" pill, then mono `.74rem` meta; "Sign out" text button right (`:420-421`) | `.session-ua` + pill + `<span class="muted">IP … · last active …</span>` all in one `.session-meta` div (`sessions.php:24-28`) | Adopt the two-line row and the mono meta. | low |
| 86 | Sessions | copy | Footnote "Signing out a device revokes its token immediately." (`:425`) | none | Add (merged with #83's rewritten first clause). | low |

### 2.10 Blocks

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 87 | Blocks | copy | Eyebrow "Blocked members" (`:432`) | `<h2>Blocked users</h2>` (`blocks.php:13`) | Adopt "Blocked members" as a panel eyebrow. | low |
| 88 | Blocks | copy | 30px Monogram per row (`:437`) | no avatar (`blocks.php:21-29`) | Add the monogram (`monogram_*` helpers exist in `src/Support/helpers.php`). | low |
| 89 | Blocks | copy | Sub-line "@{username} · blocked {when}" in mono `.74rem` (`:438`) | `@username` only; `blocks.created_at` **is** selected (`src/Repository/BlockRepository.php:71`) but never rendered | Render the blocked-at date via `human_datetime`. | low |
| 90 | Blocks | copy | Intro: "A blocked member cannot open a conversation with you or @mention you, and their notifications to you are suppressed. Their public counsel stays readable — blocking is not moderation." (`:433`) | "Blocked members can't message or @mention you, and their notifications to you are suppressed." (`blocks.php:14`) | Adopt the longer intro with the fiction removed (§3). | low |
| 91 | Blocks | copy | Empty: "You have not blocked anyone." (`:443`) | "You haven't blocked anyone." (`blocks.php:16`) | Adopt the uncontracted form (design register). | low |

### 2.11 Boards

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 92 | Boards | feature-added | — | Board folders / Saved feeds / Bookmark folders grid, three independent flags (all default ON: `FeatureFlags.php:70-72`), `boards.php:52-205`, `PersonalOrganizationController` | Keep all three. Style the `.org-card`s on the design card substrate; keep each behind its own flag. | med |
| 93 | Boards | copy | Category heading as a `.62rem` `.18em` uppercase `--text-faint` eyebrow, `margin: 20px 0 2px` (`:318`) | `<h3 class="board-cat">` (`boards.php:27`) | Convert to the eyebrow register. | low |
| 94 | Boards | copy | Row: name with a `--gold-600` `#`, then Favourite and Mute controls, `padding: 12px 0`, hairline below (`:321-327`) | `.board-pref-row` with plain `#` and two inline forms (`boards.php:31-45`) | Adopt the gold `#` and the row rhythm. | low |
| 95 | Boards | copy | Mute is a `999px` pill button (filled `--surface-sunken` when muted, transparent when not); Favourite is a text button with a star glyph (`:323-326`) | both are `.linkbtn` with a `.btn-on` modifier (`boards.php:37,43`) | Adopt the pill for Mute, keep the text button for Favourite. | low |
| 96 | Boards | copy | Labels "Favourited" / "Favourite" (British); star glyphs `★` / `☆` (`:323-324`) | "★ Favorited" / "☆ Favorite" (American), same glyphs | The design mixes registers ("Favourite" but "Organize"). Pick one spelling repo-wide; per F1 replace the raw `★`/`☆` with the `commend-star` icon partial (`templates/partials/icon.php:45-46`) on both sides. | low |
| 97 | Boards | copy | Intro "Favourited boards rise to the top of your rail. Muted boards are hidden from it, and their threads stop counting towards your unread." (`:315`) | "Favorite boards rise to the top; muted boards are hidden from your sidebar and unread counts." (`boards.php:22`) | Adopt the design's fuller sentence. | low |

### 2.12 Drafts

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 98 | Drafts | feature-removed | An "Autosave" card containing a live `<textarea>`, a "Draft saved · on your account" / "Saving…" indicator and a "Post reply" button, i.e. a composer embedded in the settings screen (`:246-255`) | no composer on `/drafts`; the autosave indicator lives on the real composer (`public/assets/composer.js:903`) | Do not build a composer inside settings. Record the gap. | low |
| 99 | Drafts | copy | Card head: eyebrow "Drafts" + a `--success` mono status line with a check glyph "Saved to your account · syncs across devices" + a right-aligned count (`:216-222`) | no head, no count (`drafts.php:13`) | Add the head and the count. The status line is conditional on `server_drafts` — render the browser-local variant when the flag is off. | low |
| 100 | Drafts | copy | Row: mono `.71rem` "{dest} {board}" (board in gold-ink), display-font title, 2-line clamped snippet, mono "saved {time}", a `--surface-info` device pill, then Resume (secondary sm) + Discard (text) (`:225-239`) | `.report-row` with a revision `.badge`, muted `context_key · updated_at`, `<h2>` title, `<blockquote>` excerpt, Discard only (`drafts.php:20-31`); the browser-local rows are built in JS (`composer.js:1007-1060`) with Resume + "Remove local copy" | Adopt the row anatomy for both the server list and the JS-built local list. Keep the revision badge as the design's device-pill slot. | med |
| 101 | Drafts | feature-added | one flat list | server drafts vs "Saved in this browser" split, gated on `server_drafts` (`drafts.php:13-46`), plus two `<noscript>` fallbacks | Keep the split and both `<noscript>` blocks. | low |
| 102 | Drafts | copy | Empty: "No drafts. Anything you start writing is kept here automatically." — centred, italic, `padding: 40px 22px` (`:243`) | "No server drafts yet." / "No browser-local drafts in this browser." / "Your saved drafts are stored in this browser…" (`drafts.php:16,39,44`) | Adopt the centred-italic empty-state skin; keep the three distinct, accurate strings. | low |

### 2.13 Account (lifecycle)

| # | Section | Class | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 103 | Delete | feature-changed | Typed `DELETE` confirmation, button disabled until it matches; **no password** (`:465-466`, `:751`) | `current_password` required (`lifecycle.php:66-73`, `AccountController::requestDeletion:149-163`) | Keep the password gate — a JS-disabled button is not a guard with JS off. The typed-confirm may be added as an *additional* server-checked field, but that is a behaviour change needing its own decision. | med |
| 104 | Delete | copy | Danger card: `border-left: 3px solid var(--danger)`, eyebrow in `--danger` (`:462-463`) | `.card.stacked.danger-zone` (`lifecycle.php:56`) | Adopt the 3px rust left rule and the danger-coloured eyebrow. | low |
| 105 | Delete | feature-added | single "request deletion" state | pending-deletion branch showing `purge_after` UTC + "Cancel deletion request" (`lifecycle.php:58-63`, `AccountController::cancelDeletion:166-176`) | Keep. Give it the design's status-callout treatment. | low |
| 106 | Deactivate | feature-added | single "deactivate" state | deactivated branch with "Reactivate account" (`lifecycle.php:36-41`) | Keep. | low |
| 107 | Export | feature-changed | "A JSON archive of your profile, preferences, subscriptions, notifications, posts, direct messages, and the audit rows attached to them." (`:452`) | "…your profile, preferences, sessions metadata, subscriptions, notifications, reports, posts, direct messages, and related audit rows." (`lifecycle.php:27`) | Keep production's enumeration — it is the accurate one (adds sessions metadata and reports). Adopt the design's leading-sentence form. | low |
| 108 | Lifecycle | feature-added | — | `<div class="card error-list" role="alert">` listing `$errors` at the top of the pane (`lifecycle.php:17-23`) | Keep. It is the ADR 0023 item-5 accessibility wiring. Style as a rust callout. | low |
| 109 | Lifecycle | constraint | Whole pane rendered unconditionally; `showDangerZone` is a design-time prop (`:461`, `:750`) | entire pane + all five routes gated on `account_lifecycle` (`AccountController::requireAccountLifecycle:321-326`), rail item hidden when off (`settings_nav.php:22-24`) | Keep the flag gate. The design's `showDangerZone` prop has no production analogue. | low |
| 110 | Lifecycle | copy | Three panel eyebrows: "Export account data", "Deactivate account", "Delete account" (`:451`, `:456`, `:463`) | three `<h2>`s with the same words (`lifecycle.php:26,35,57`) | Convert to panel eyebrows. | low |

---

## 3. Fiction strings

Every string below is Tolkien-register design fiction and must never be pasted into production.

| # | Line | Design string | Proposed production string |
|---|---|---|---|
| 1 | `:27` | `Imladris` (topbar wordmark) | Do not port. Production renders `$brand['name']` (`templates/layout.php:27`). |
| 2 | `:26` | Eight-point elven star SVG (topbar mark) | Do not port. Production uses `$brand['logo_path']` / favicon. |
| 3 | `:29` | `Back to the council` | `Back to the forum` |
| 4 | `:41` | `Your seat at the council` (page eyebrow) | `Account` |
| 5 | `:43` | `Everything the council knows about you, and everything it does on your behalf. Changes are held until you save them.` | `Everything this community knows about you, and everything it does on your behalf. Each section saves on its own.` |
| 6 | `:71` | `Council` (rail group heading) | `Community` |
| 7 | `:57`,`:58`,`:704` | `Regard` (rail item + pane title) | Do not add the item at all (§2.1 #5). If a reputation surface is ever built: `Reputation`. |
| 8 | `:94` | `Fields defined by the wardens` | Do not port — no field schema exists (§2.2 #26). If it ever ships: `Fields defined by the operators`. |
| 9 | `:96` | `The wardens choose which fields exist; you choose what goes in them.` | Do not port. Production copy: `Add up to three public profile facts.` (already at `settings.php:98`). |
| 10 | `:99` | `Rivendell` (Location sample value) | Real placeholder, e.g. `Bristol` — or leave the field empty. |
| 11 | `:134` | `Not enabled. A second factor keeps your seat at the council secure even if your password is lost.` | `Not enabled. A second factor keeps your account secure even if your password is lost.` |
| 12 | `:143` | `IMLA DRIS 7K2F 9QD4 H1PB` (authenticator secret sample) | Real generated secret from `MfaService::startEnrollment`. |
| 13 | `:544` | `imla-3kf9-2a`, `imla-77qd-h1`, … (recovery-code samples) | Real generated codes; the `imla-` prefix must not appear. |
| 14 | `:604` | `Worthy of the council` (top password-strength tier) | Do not port — the meter is feature-removed (§2.3 #34). |
| 15 | `:179` | `Commends` (reputation unit) | Not on this screen. Note: already shipped and test-pinned at `templates/partials/post.php:33` — de-fictionalising it is F2 escalation C12, out of scope here. |
| 16 | `:207` | `Regard is earned, never granted. Only you can see this ledger; others see the total.` | Do not port. |
| 17 | `:502` | `Reached` / `Loremaster` / `crossed 3,500 commends` | Do not port. |
| 18 | `:499-504` | `Arwen`, `Galadriel`, `Glorfindel`, `Elrond` (ledger sample actors) | Do not port. |
| 19 | `:264` | `Hidden — wardens only` (profile-visibility option) | Do not port — the option does not exist (§2.4 #48). If ever built: `Hidden — staff only`. |
| 20 | `:269` | `You still earn regard; you just won't be ranked publicly.` | `You still earn reputation; you just won't be ranked publicly.` **Already shipped as fiction at `templates/account/privacy.php:40` — fix in place.** |
| 21 | `:365` | `Europe / Rivendell` (timezone sample) | Real IANA identifiers — production already lists them all (`SettingsController.php:137`). |
| 22 | `:371` | `Email me the weekly council summary` | Do not port — no per-event email preference exists (§2.7 #70). If ever built: `Email me the weekly digest`. |
| 23 | `:433` | `Their public counsel stays readable — blocking is not moderation.` | `Their public posts stay readable — blocking is not moderation.` |
| 24 | `:457` | `Reversible. Your seat stays sign-in capable, but counsel and posting are blocked until you reactivate.` | `Reversible. Your account stays sign-in capable, but replying and posting are blocked until you reactivate.` |
| 25 | `:464` | `…Public counsel is preserved under a deleted-member identity; everything that identifies you is purged.` | `…Public posts are preserved under a deleted-member identity; everything that identifies you is purged.` |
| 26 | `:490` | `Saved to your seat.` (success toast) | `Saved.` |
| 27 | `:550`,`:555` | `The Commons`, `Vilya · Expose` (board category samples) | Real categories from `CategoryRepository::all()`. Note `Vilya · Expose` is also hardcoded as a placeholder at `templates/account/boards.php:84` — replace. |
| 28 | `:546-547` | `Saruman`, `Gríma` (blocked-member samples) | Real rows from `BlockRepository::listBlocked`. |
| 29 | `:610`,`:617` | `Erestor`, `erestor@imladris.council` | Real session user; sample addresses use `@example.com`. |
| 30 | `:248`,`:508-510` | `The rite framing lands for me…`, `#evaluations`, `#audit-trails`, `#interpretability` | Real drafts; sample boards use neutral names. |

**Non-fiction but flagged:** `★` / `☆` at `:323-324` violate the design system's own no-emoji-in-chrome
rule (F1). Production has the same violation at `templates/account/boards.php:37`. Replace both with the
`commend-star` icon partial.

---

## 4. State inventory

| Design state (line) | Verbatim string / behaviour | Production equivalent | Verdict |
|---|---|---|---|
| Password strength, 5 tiers (`:595-604`) | "Not set" / "Too easily guessed" / "Passable" / "Strong" / "Worthy of the council" | none | **gap — do not build** (ADR 0021 deferral #3) |
| Password mismatch (`:126`) | "The two new passwords don't match yet." | server 422 → `errors['new_password_confirm']` (`security.php:34`) | feature-changed; optional JS mirror |
| 2FA off (`:132-136`) | "Not enabled. A second factor keeps your seat at the council secure even if your password is lost." | "Not enabled." (`security.php:46`) | copy, de-fictioned |
| 2FA pending (`:138-151`) | "Scan the cipher with your authenticator, then enter the six digits it shows." | "Enrollment started. Verify a code to finish enabling two-factor authentication." (`security.php:44`) | copy, reworded (no QR) |
| 2FA verify disabled (`:147`, `:645`) | button disabled until 6 digits | none | constraint — decoration only |
| 2FA cancel (`:148`) | "Cancel" | none | **gap — no abandon route** |
| 2FA on (`:152-166`) | "Enabled" pill + "{n} recovery codes remaining — each works once." | "Enabled. N recovery code(s) remaining." (`security.php:42`) | copy |
| Recovery codes persistent (`:156-160`) | 6 codes always visible | shown once after confirm/rotate (`security.php:88-97`); stored HMAC-hashed (`MfaService.php:225-228`) | **constraint** |
| Passkey errors | — | `passkey_errors` (`security.php:128-130`), `data-passkey-add-error`, `data-passkey-revoke-error` | feature-added |
| Passkey no-JS (`—`) | — | `<noscript>`: "Adding a passkey needs JavaScript and a supported browser. Password, authenticator code, and recovery sign-in keep working without it." (`security.php:188-190`) | feature-added — keep verbatim |
| Drafts empty (`:242-244`) | "No drafts. Anything you start writing is kept here automatically." | "No server drafts yet." / "No browser-local drafts in this browser." / "Your saved drafts are stored in this browser. They will appear here when JavaScript is enabled." (`drafts.php:16,39,44`) | copy (skin) + feature-added (three states) |
| Draft saving (`:250-251`) | "Draft saved · on your account" / "Saving…" | `composer.js:903` "Draft saved · " on the real composer | **gap on this screen** — feature-removed |
| Subscriptions empty (`:384`) | "No subscriptions. Watch a thread and it will appear here." | "You aren't subscribed to any threads or boards yet." (`notifications.php:49`) | copy |
| Blocks empty (`:443`) | "You have not blocked anyone." | "You haven't blocked anyone." (`blocks.php:16`) | copy |
| Boards empty (`—`) | *(none)* | "No boards available." (`boards.php:24`) | feature-added — keep |
| Board-folder empties (`—`) | *(none)* | "No board folders yet." / "No saved feeds yet." / "No bookmark folders yet." / "Create a folder, then add boards to it." / "Star a topic, then file it in a bookmark folder." (`boards.php:64,105,120,159,200`) | feature-added — keep |
| Provider unavailable (`:401`) | "Not available" | "Not available" (`connections.php:33`) | match |
| Provider sub-line (`:691`) | linked email / "Not connected" / "Provider not configured" | email only when linked (`connections.php:22-24`) | copy |
| OAuth-only account (`—`) | *(none)* | "Set a password" panel (`connections.php:40-58`) | feature-added |
| Session current (`:420`) | "This device" pill | "This device" pill (`sessions.php:26`) | match |
| Deletion pending (`—`) | *(none)* | "Deletion is scheduled after the grace period on {purge_after} UTC…" + Cancel (`lifecycle.php:58-63`) | feature-added |
| Deactivated (`—`) | *(none)* | "Your account is deactivated. You can reactivate it to restore write access." (`lifecycle.php:37`) | feature-added |
| Lifecycle errors (`—`) | *(none)* | `role="alert"` error-list card (`lifecycle.php:17-23`) | feature-added |
| Email unverified (`—`) | *(none)* | "Verify your email address. We've sent a confirmation link…" + resend (`settings.php:12-20`) | feature-added |
| Dirty buffer (`:477-484`) | "You have unsaved changes." · "Discard" · "Save changes" | none | **constraint** — decoration only |
| Saved toast (`:487-492`) | "Saved to your seat." (auto-dismiss 2400ms) | `.flash` banner from `redirectWithFlash` (6 distinct strings) | constraint — keep the server round-trip |
| Loading / skeleton | *(none in this screen)* | n/a | no conflict |

---

## 5. Slice proposal

Each slice is independently shippable, independently testable, and leaves the surface green.

**S1 — Rail: grouping, order, icons, active state.**
Touches `templates/partials/settings_nav.php`, `public/assets/app.css` (`.settings-rail` block ~2053-2062, 2629-2632).
Adds three group headings reusing the `.admin-nav-group*` idiom, reorders items, adds icons via
`partials/icon.php`, switches the active marker to a 2px `--gold-500` left rule and adds
`aria-current="page"`. Keeps every flag gate and the Replay-tour button.
*Tests:* new integration test asserting group headings, item order and `aria-current` on each of the 13
routes; a JS-disabled Playwright context proving every rail item is reachable; axe on `/settings/account`.

**S2 — Shell: page head, container, panel substrate, eyebrow.**
Touches all 13 `templates/account/*.php` heads + a new settings-scoped card class in `app.css`.
Adds the intro paragraph, raises h1 to 2.4rem, widens the container to 1064px, replaces `.scribe-panel`
and bare `.card` on settings panes with the design's plain `--radius-lg` card, and retunes the panel
eyebrow to `.66rem`/`.16em` without the hairline. Fixes `drafts.php`'s h1.
*Tests:* PHPUnit assertion that all 13 pages render the same `<h1>` and carry the intro; visual
comparison sheets desktop + mobile; confirm `.scribe-panel` is untouched on `/admin/*`.

**S3 — Form substrate: inputs, selects, labels, one switch idiom.**
Touches `app.css` (`.input-engraved`, `.settings-pane select.input`, `.field > span`), plus
`privacy.php` and `preferences.php` to move from `.gem-*` to `.switchline`/`.switch`, plus
`security.php` to adopt `.input-engraved`.
*Tests:* PHPUnit posting each converted form with checkbox on and off to prove the value round-trip
survives the control swap; axe on privacy + preferences; keyboard-focus screenshots.

**S4 — Profile pane.**
Touches `templates/account/settings.php`, `AccountController::accountForm/updateAccount`.
Restyles Identity + Avatar + verification notice into design cards; adds per-field helpers; keeps the
3-row custom-field mechanic; drops nothing.
*Tests:* the existing 422 anti-draft-loss path — post an invalid `website` and assert the re-render
carries `->old` for every field including `custom_label_N`/`custom_value_N`; screenshot with and
without `profile_media`.

**S5 — Security pane.**
Touches `templates/account/security.php` only (no controller change).
Adopts the 340px + 2-col password layout, the OTP input skin, the "Enabled" pill, the recovery-code chip
grid, and restyles the passkeys panel. Keeps every `current_password` gate, the URI field, and the
`<noscript>`.
*Tests:* full TOTP enroll → confirm → rotate → disable cycle in PHPUnit asserting each still 422s
without a password; Playwright with JS off proving enroll and confirm work; passkeys `<noscript>` render.

**S6 — Privacy, Appearance, Reading, Composing.**
Touches those four templates plus `app.css` swatch/density rules and a new theme-preview decoration in
`public/assets/app.js`.
Adopts the eyebrow structure, 2-col grids, dividers, swatch chip geometry, the gold twilight accent, the
merged export/reset card, and the exact card descriptions. Adds the JS theme mirror. Does **not** add
Default sort, "Hidden — wardens only" or "Members I have replied to".
*Tests:* PHPUnit asserting `/settings/preferences` still exposes exactly the v3 reading keys and no
`thread_sort`; a JS-disabled test proving theme still applies after POST; a JS-enabled test proving
`document.documentElement.dataset.theme` flips on radio change **and** that a page reload without saving
returns to the persisted theme.

**S7 — Notifications, Connections, Sessions, Blocks.**
Touches those four templates; `BlockController::index` unchanged (`created_at` already selected).
Adopts the 2-col digest grid, the two-line subscription/provider/session/block rows, the provider mark
tile, the monogram, the blocked-at date, and the corrected footnotes (#77, #83).
*Tests:* PHPUnit asserting the connections page never claims email/password is always available when
`has_password` is false; a test asserting the sessions footnote text; axe on all four.

**S8 — Boards, Drafts, Lifecycle.**
Touches `boards.php`, `drafts.php`, `lifecycle.php`, plus the JS-built local-draft rows in
`public/assets/composer.js:1007-1060`.
Adopts the category eyebrow, the gold `#`, the Mute pill, the draft row anatomy (server and local), the
centred-italic empty state, the danger card's 3px rust rule, and the three lifecycle eyebrows. Keeps
folders/feeds/bookmarks behind their flags, keeps the reactivate and cancel-deletion branches, keeps the
password gate on delete.
*Tests:* PHPUnit with each of `board_folders` / `saved_feeds` / `bookmark_folders` rolled back
individually; a JS-disabled test of deactivate → reactivate and delete-request → cancel; drafts page
with `server_drafts` on and off.

**S9 — Save-affordance decoration (optional, ships last).**
Touches `public/assets/app.js` + a fixed-bar partial + the `.flash` toast skin.
Adds a JS-only unsaved-changes bar bound to the single form on the current page, and restyles the flash
as the design's centred pill. Per-form buttons stay.
*Tests:* JS-disabled context proving every section still saves with no bar present; JS-enabled test
proving the bar's Save submits the real form and the Discard resets it; assert the bar never appears on
a page with zero forms.

**S10 — De-fiction + baseline refresh.**
Touches `templates/account/privacy.php:40` ("regard" → "reputation") and `templates/account/boards.php:84`
(`Vilya · Expose` placeholder), plus `config/imladris-runtime-baseline.json` →
`application_surface.sha256` refreshed once from
`php bin/build-imladris-assets.php --print-application-digest`.
*Tests:* `composer check:imladris` + `composer verify:imladris` green; CSP scan
`rg -n "<script|<style| on[a-z]+=" templates/account templates/partials/settings_nav.php -S` clean;
full `vendor/bin/phpunit` read to completion; `npm run evidence` + `npm run a11y` on desktop and mobile.

**Cross-slice evidence gate (PRODUCT_DESIGN §13):** every slice above is UI-visible, so PHPUnit alone is never
sufficient — each needs Playwright screenshots filed under `docs/evidence/<slice>/{desktop,mobile}/`
plus a prototype-vs-production comparison sheet, and any deferral (the Regard pane, the strength meter,
the per-event email matrix, the 2FA cancel route, the drafts autosave card) must be recorded in a new
ADR rather than silently dropped.
