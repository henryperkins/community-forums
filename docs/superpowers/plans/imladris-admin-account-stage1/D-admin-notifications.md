# D — admin-notifications: design vs production

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-notifications/AdminNotifications.dc.html` (453 lines; markup 1–279, `<script type="text/x-dc">` 280–450)

**Production targets (paths corrected — the repo namespace is `src/Controller/`, not `src/Controllers/`):**
- `C:/Users/htper/community-forums/templates/admin/email.php` (201 lines)
- `C:/Users/htper/community-forums/templates/admin/announcements.php` (81 lines)
- `C:/Users/htper/community-forums/src/Controller/AdminEmailController.php` (202 lines)
- `C:/Users/htper/community-forums/src/Controller/AdminAnnouncementController.php` (51 lines)
- Read in addition (behavioural ground truth): `src/Service/EmailOpsService.php`, `src/Service/AnnouncementService.php`, `src/Service/EmailDomainVerifier.php`, `src/Repository/EmailDeliveryRepository.php`, `src/Repository/EmailSuppressionRepository.php`, `templates/admin/_nav.php`, `src/Core/App.php:2296-2314`, `src/Core/FeatureFlags.php:29,37`, `docs/adr/0008-email-domain-send-blocking-policy.md`, `docs/adr/0023-admin-console-audit-round-2.md:14`, `ADMIN.md §7.4–§7.6`, `tests/Integration/Admin/AppAdminEmailTest.php`, `tests/Integration/Admin/AdminAnnouncementTest.php`, `tests/browser/admin-remediation.spec.ts:200-223`.

---

## 0. Headline answers to the brief's three questions

**"Which filters, which stats, which broadcast channels does production actually have?"**

| Design claim | Verified production reality |
|---|---|
| Status filter `queued / sent / failed / suppressed / bounced / complained` (design:95-100) | **Exact match.** `AdminEmailController::STATUSES` (`AdminEmailController.php:20`), rendered `email.php:97`. Only the *order* differs. |
| Kind filter `verify / reset / digest / mention / broadcast / test` (design:107-112) | **Wrong vocabulary.** Production's real enum is `instant / digest / test / system` (`AdminEmailController.php:21`, `email.php:105`). Confirmed by grepping every `enqueue()` caller: `'test'` (`EmailOpsService.php:93`), `'instant'` (`NotificationService.php:260`), `'digest'` (`DailyDigestWorker.php:90`), `'system'` (`EmailDeliveryRepository.php:55`). **`verify` and `reset` do not exist at all** — verification and password-reset mail bypasses `email_deliveries` and calls `Mailer::send()` directly (`EmailVerificationService.php:120`, `PasswordResetService.php:162`), so it never appears in the log. `mention` ≈ `instant`; `broadcast` ≈ `system`. |
| Six stat cards, one per status (design:78-85) | **Exact match** in set *and order* — `email.php:76` iterates `['queued','sent','failed','suppressed','bounced','complained']` against `EmailDeliveryRepository::statusCounts()` (`:167-175`). |
| "Broadcast channels" (design:233-234, 264, 306) | **NOT design invention — production has both.** `announcements.php:44-45` posts `broadcast` (in-app) and `broadcast_email`; `AnnouncementService::setBanner()` fans out via `NotificationRepository::broadcastAnnouncement()` and `EmailDeliveryRepository::enqueueSystemForActiveUsers()` (`AnnouncementService.php:65-76`). The Channels column string `Banner · in-app · email` is **already produced verbatim** at `announcements.php:67-71`. |
| "Recent history" (design:247-273) | **NOT design invention.** `AnnouncementService::recentHistory()` derives it from `moderation_log` rows `set_announcement`/`clear_announcement` (`AnnouncementService.php:179-206`); rendered `announcements.php:51-79`. Capped at 10. |

**"Does the design model the unconfigured / fails-closed state?"** — **No. Not at all.** The design renders a healthy `council.imladris.example` with a live selector and an SPF-pass chip unconditionally (design:52-64). It has no transport-missing state, no From-missing state, no send-blocked state and no disabled test button. Production has all four, and they are **ADR-locked**: ADR 0023 item 3 (F24) — *"`/admin/email` states one fact per line (transport / From / sending domain); 'Sending is configured' can no longer render beside 'Set a From address…'"* — implemented at `email.php:20-49` and pinned by `AppAdminEmailTest.php:68-72` and `:93-97`. This is the single largest `feature-added` on the screen and **must survive verbatim**.

**ADR 0008 (email domain send-blocking):** production implements it exactly as decided — opt-in blocking (`EmailDomainVerifier::requiresVerifiedDomain()`), SPF via `v=spf1` TXT, DKIM via `v=DKIM1` at `<selector>._domainkey.<domain>`, cached with `checked_at`, manual refresh, blocking never deletes queued mail, and test-send reports domain-blocked separately from transport failure (`EmailOpsService.php:83-88` throws two *different* ValidationExceptions). The design flattens all of that to one hard-coded sentence, "Verified-domain send blocking is enabled." (design:62).

**Two honesty defects the design would import:** `bounced` and `complained` are schema enum values (`database/migrations/0023_email_deliveries.php:21`) that **nothing in `src/` ever writes** — there is no bounce/complaint webhook ingestion (ADMIN §7.6 lists it as spec, gated on ESP selection). And there is **no `email_deliveries` retention purge anywhere** (`src/Worker/` has `IpRetentionPurger`, `OrphanAttachmentCleaner`, `ServerDraftRepository::purgeExpired` — no email purge; `bin/console` has no such worker), so the design's "The log keeps thirty days" is false.

---

## 1. Section-order comparison

### Design order (verbatim headings / eyebrows, top to bottom)

| # | Design section | Line | Heading text (verbatim) |
|---|---|---|---|
| 1 | Sticky topbar | 22-28 | `Imladris` wordmark + eight-point star SVG + `Back to the council` |
| 2 | Page head | 32-38 | eyebrow `Operator desk · Notifications`; h1 `Email & announcements`; chip `Admin mode` |
| 3 | Section tabs (`aria-label="Notification sections"`) | 40-45 | `Email` · `Announcements` |
| 4 | **[Email tab]** two-up card grid, left | 52-64 | h2 `Sending domain` |
| 5 | two-up card grid, right | 66-73 | h2 `Send a test email` |
| 6 | unboxed section, caps eyebrow h2 | 76-86 | h2 `Queue status` (6 stat cards) |
| 7 | raised card + `--shadow-sm` | 88-169 | h2 `Delivery log` (filters → table → empty → pager) |
| 8 | raised card | 171-202 | h2 `Suppressed addresses` (add form → table → empty) |
| 9 | **[Announcements tab]** raised card | 209-221 | h2 `Current banner` |
| 10 | raised card + `--shadow-sm` | 223-244 | h2 `Publish a banner` |
| 11 | raised card | 246-273 | h2 `Recent history` |

### Production order

`templates/admin/email.php`

| # | Section | Line | Heading / content |
|---|---|---|---|
| 1 | `header.admin-head` | 13-16 | h1 `Email delivery` + `<span class="pill pill-admin">Admin mode</span>` — **no eyebrow** |
| 2 | grouped admin rail | 17 | `admin/_nav` partial, `active => 'email'` |
| 3 | not-ready flash (conditional) | 24-26 | `Email is not ready to send.` |
| 4 | `ul.email-status-facts` | 27-43 | `Transport:` / `From address:` / `Sending domain:` |
| 5 | send-blocked flash (conditional) | 44-49 | `Email sending is blocked until SPF and DKIM pass.` |
| 6 | `section.card` | 51-71 | h2 `Sending domain` |
| 7 | `section.card` | 73-80 | h2 `Queue status` |
| 8 | `section.card` | 82-89 | h2 `Send a test email` |
| 9 | `section.card` | 91-161 | h2 `Delivery log` |
| 10 | `section.card` | 163-199 | h2 `Suppressed addresses` |

`templates/admin/announcements.php`

| # | Section | Line | Heading |
|---|---|---|---|
| 1 | `header.admin-head` | 9-12 | h1 `Announcements` + `Admin mode` pill — **no eyebrow** |
| 2 | grouped admin rail | 13 | `admin/_nav`, `active => 'announcements'` |
| 3 | `section.card` | 16-32 | h2 `Current banner` |
| 4 | `section.card` | 34-49 | h2 `Publish a banner` |
| 5 | `section.card` | 51-79 | h2 `Recent history` |

### Order deltas

1. **The design's Announcements order is an exact match** (Current banner → Publish a banner → Recent history). No change.
2. **Email:** production has `Queue status` between `Sending domain` and `Send a test email`. The design pairs `Sending domain` + `Send a test email` in a 2-column grid and puts `Queue status` third. → **copy** (D9).
3. **Production has three sections before `Sending domain` that the design has none of** (not-ready flash, the F24 three-fact list, send-blocked flash). These are ADR-locked and stay at the top. → **feature-added** (D8).
4. Production ships each tab as its own route (`GET /admin/email`, `GET /admin/announcements` — `App.php:2296,2313`) reached from the grouped rail (`_nav.php:33-36`), not as client-tabbed panes of one screen. → **constraint** (D2, D4).

---

## 2. Difference table

Legend — Risk = risk of getting this wrong during adoption (high = breaks a locked decision, a green test, or an honesty guarantee).

| ID | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| D1 | Topbar | constraint | `Imladris` wordmark + eight-point elven star SVG + `Back to the council` link (design:22-28) | `templates/layout.php` renders the operator shell from `$brand['name']` / `$brand['logo_path']` | Do not port. The screen begins at the page head. | low |
| D2 | Section nav | constraint | Client tab strip `<button onClick="{{ goEmail }}">` / `goAnnouncements` (design:40-45) | Grouped 224px sticky rail, `Notifications` group (`templates/admin/_nav.php:33-36`), locked by ADR 0023 item 6 + ADMIN §9.2 | Keep the rail. Render the two-item strip inside `.admin-pane` as real `<a href="/admin/email">` / `<a href="/admin/announcements">` anchors with `aria-current="page"`, styled `.text-tabs`. Gate the Announcements anchor on the `announcements` flag. | medium |
| D3 | Page head | copy | eyebrow `Operator desk · Notifications` (design:34) | No eyebrow on either page (`email.php:13-16`, `announcements.php:9-12`) | Add `<span class="eyebrow">Operator desk · Notifications</span>` above both h1s. | low |
| D4 | Page head | constraint | One h1 `Email & announcements` for both tabs (design:35) | Two real routes, two h1s: `Email delivery` (`email.php:14`), `Announcements` (`announcements.php:10`) | Keep per-page h1s (PRODUCT_DESIGN §5.3: every view has a real shareable URL). | low |
| D5 | Page head | copy | `Admin mode` chip: `--surface-review`/`--on-review`, 4px 12px, 999px, `.72rem`, `.08em`, uppercase (design:37) | `<span class="pill pill-admin">Admin mode</span>` (`email.php:15`, `announcements.php:11`) | Restyle `.pill-admin` to the design chip spec. | low |
| D6 | Shell | constraint | Single centred column, `max-width:1140px; padding:26px 28px 110px` (design:30) | `.admin` grid with the sticky rail (`app.css:2839`), content in `.admin-pane` | Apply the design's card/type/spacing *inside* `.admin-pane`; never demolish the rail. | medium |
| D7 | Whole screen | constraint | ~350 inline `style="…"` attrs + `style-hover=` + `<helmet><style>` with `@keyframes anRise` (design:11-18) + terminal `<script type="text/x-dc">` | Zero inline styles in `templates/` (CSP `style-src 'self'`, no `style-src-attr`) | Author every rule as an external class in `public/assets/app.css` (unlayered — it wins over `imladris.css`). No inline style, no `<style>`, no inline `<script>`. Rendered pixels must still match. | low |
| D8 | Email status | feature-added | Nothing. The design renders a healthy domain unconditionally (design:52-64) | Not-ready flash (`email.php:24-26`), three-fact `ul.email-status-facts` Transport/From/Sending domain (`:27-43`), send-blocked flash (`:44-49`) | **KEEP VERBATIM.** ADR 0023 item 3 (F24); pinned by `AppAdminEmailTest.php:68-72,93-97`. Restyle only: render as a spec list (label caps + value), keep `role="alert"` on both flashes. | high |
| D9 | Email layout | copy | 2-col grid: Sending domain ‖ Send a test email, then Queue status (design:51-86) | Sending domain → Queue status → Send a test email (`email.php:51,73,82`) | Reorder to the design: 2-up grid, then Queue status, then Delivery log, then Suppressed addresses. | low |
| D10 | Sending domain | copy | SPF/DKIM as status chips — `SPF pass` on `--surface-done`/`--on-done`, `DKIM pending` on `--surface-review`/`--on-review` (design:56-57) | Plain muted text `SPF: pass · DKIM: pass` (`email.php:60-62`) | Render each of `spf_status`/`dkim_status` as a chip: `pass`→done, `fail`→danger, `unknown`→pending. **`AppAdminEmailTest.php:236-237` pins the substrings `SPF: pass` / `DKIM: pass`** — either keep that label form inside the chip or update the assertion in the same commit. | medium |
| D11 | Sending domain | copy | `checked {{ domainChecked }}` as a mono span in the chip row (design:58) | Appended to the SPF/DKIM line (`email.php:63`) | Move into the chip row, `var(--font-mono)` `.76rem` `--text-faint`. Keep `human_datetime()`. | low |
| D12 | Sending domain | copy | `<strong>domain</strong>` + label-font caption `selector` + `<code>rb2026</code>` (design:54) | Same structure (`email.php:57-59`) | Skin only: caption to `var(--font-label)` `.76rem` `--text-faint`; `<code>` to `var(--font-mono)` `.8rem`. | low |
| D13 | Sending domain | feature-changed | Hard-coded `Verified-domain send blocking is enabled.` (design:62) | Conditional on `$domain['required']` → `enabled`/`disabled` (`email.php:69`), from `EmailDomainVerifier::requiresVerifiedDomain()` (`:78-85`) | Production behaviour wins; adopt the design's placement (inline, right of the Refresh button, `.74rem` `--text-faint`). | low |
| D14 | Sending domain | feature-added | No empty-domain branch | `Set a From address before verifying SPF and DKIM.` when the domain is empty (`email.php:53-54`) | Keep. It is the honest state when `mail.from` is unset. | medium |
| D15 | Sending domain | constraint | `<button onClick="{{ refreshDomain }}">Refresh SPF/DKIM status</button>` (design:61) | Real `POST /admin/email/domain/verify` form with `$this->csrfField()` (`email.php:65-68`) | Keep the form + CSRF. Style the button to the design's ghost spec (7px 15px, `--radius-md`, 1.5px `--border-soft`). Never propose a CSRF exemption. | low |
| D16 | Send a test email | copy | Intro paragraph above the button (design:68 → :70) | Button above the paragraph (`email.php:84-88`) — same string verbatim: *"Sends a one-off message to your own account address and records it in the log below."* | Swap the order to match the design. String needs no change. | low |
| D17 | Send a test email | feature-added | Button always enabled | `<button … <?= empty($mailer_configured) ? ' disabled' : '' ?>>` (`email.php:86`) | Keep the disabled attribute; author a disabled style in the design idiom (reduced-contrast ground, `cursor:not-allowed`, no hover). | medium |
| D18 | Send a test email | constraint | Inline `<span role="status">Queued — it is at the top of the log.</span>` (design:71) | POST→redirect flash (`AdminEmailController.php:66-68`) | PE/PRG: the flash *is* the confirmation. Do not add an inline status span; do not introduce a client success state. | low |
| D19 | Send a test email | feature-changed | Enqueues only; reports "Queued" (design:379-383) | Enqueues **and synchronously sends**, marking `sent`/`failed`, surfacing the real SMTP error as `Test send failed: {msg}` (`EmailOpsService.php:104-117`); flash `Test email sent to {email}.` (`AdminEmailController.php:68`) | Production behaviour wins. The design's "Queued" copy must not ship — it would be a lie about what the button does. | low |
| D20 | Queue status | copy | h2 is a lapidary caps eyebrow (`--font-label` `.68rem` `.16em` uppercase `--text-faint`), section is **unboxed** (design:76-77) | Normal `<h2>` inside `section.card` (`email.php:73-74`) | Adopt: unbox the section, restyle the h2 as a caps eyebrow. | low |
| D21 | Queue status | copy | 6 cards, `repeat(6, 1fr)`, `--surface-raised` + 1px `--border-hair` + `--radius-lg`, numeral `var(--font-mono)` 1.5rem (design:78-85) | `.stat-cards` = `repeat(auto-fit, minmax(120px,1fr))`, `.stat-card` on `--surface-sunken`, `.stat-num` display-font 1.7rem (`app.css:3436-3464`); markup `email.php:75-79` | Restyle `.stat-card` (raised + hairline + `--radius-lg`) and `.stat-num` (mono 1.5rem). **Keep `auto-fit`** — the design is desktop-only and a hard 6-col grid breaks the ADMIN §9.4 mobile contract. | low |
| D22 | Delivery log | copy | Filter row: stacked `<label>` with an uppercase caption above each control, flex-wrap, 12px gap, baseline-aligned (design:90-121) | Flat `.inline-form` with inline label text (`email.php:93-115`) | Restructure to the design's captioned filter grid. | low |
| D23 | Delivery log | constraint | Filters apply on `onChange`/`onInput`, no submit (design:93,105,117) | `<form method="get" action="/admin/email">` + `Filter` submit (`email.php:93,113`) | PE: keep the visible submit so filters work with JS off. JS may auto-submit on change as a decoration only. | low |
| D24 | Delivery log | copy | `Reset` button clearing all three filters (design:119) | No reset control | Add `<a class="btn" href="/admin/email">Reset</a>` — a plain GET link, no CSRF needed. | low |
| D25 | Delivery log | copy | Right-aligned in the filter row: `{{ logResultLabel }}` = `N messages` / `1 message` (design:120, 392) | `<p class="muted">N total matching deliveries.</p>` *below* the table (`email.php:152`), from the real filtered `count()` (`EmailOpsService.php:55`) | Move into the filter row, adopt the singular/plural form. Number source unchanged. | low |
| D26 | Delivery log | feature-changed | Kind options `verify / reset / digest / mention / broadcast / test` (design:107-112) | `instant / digest / test / system` (`AdminEmailController.php:21`, `email.php:105`) — the only kinds ever written | Production vocabulary wins. Do **not** add `verify`/`reset` — those mails never enter `email_deliveries` (`EmailVerificationService.php:120`, `PasswordResetService.php:162` call `Mailer::send()` directly), so the option would always return zero rows. | low |
| D27 | Delivery log | copy | Status option order `queued, sent, failed, suppressed, bounced, complained` (design:95-100) | `queued, sent, bounced, complained, suppressed, failed` (`email.php:97`) | Reorder the select to match — production's own stat-card order (`email.php:76`) already matches the design, so the page is currently self-inconsistent. | low |
| D28 | Delivery log | copy | Email input: `var(--font-mono)` `.86rem`, placeholder `address@example.com` (design:117) | `type=text`, no placeholder, no mono (`email.php:111`) | Add the placeholder + mono face. | low |
| D29 | Delivery log | feature-added | 7 columns: When, To, Kind, Status, Attempts, Subject, Action (design:125-131) | 8 columns — adds **Detail** = `error ?? message_id` (`email.php:118,133`) | Keep Detail; it is the only place a failure reason is visible. Style as a mono, `overflow-wrap:anywhere` cell. | medium |
| D30 | Delivery log | feature-added | Attempts is a bare number (design:145) | `attempt_count / max_attempts` + a `Next retry {datetime}` sub-line (`email.php:126-131`), pinned by `AppAdminEmailTest.php:186-187` | Keep both. Style the sub-line as `.74rem --text-faint`. | medium |
| D31 | Delivery log | copy | Kind cell is a `<code>` chip on `--surface-sunken`, `--radius-sm`, `.74rem` (design:138) | Bare text (`email.php:124`) | Adopt the chip. | low |
| D32 | Delivery log | copy | Status cell is a filled pill: Sent→done, Queued→info, failed/bounced/complained→rust wash + `--danger`, suppressed→pending; label capitalised (design:140-143, 395) | `.state.state-{status}` — a coloured dot + lowercase text (`email.php:125`, `app.css:3466-3499`) | Adopt the pill and the capitalised label; keep the existing `.state-{status}` class hooks so the CSS map stays one place. | low |
| D33 | Delivery log | feature-changed | Requeue offered for `failed`, `bounced` **and** `complained` (design:312, 400) | Requeue offered only for `failed` (`email.php:135`) and the SQL is `WHERE id = ? AND status = 'failed'` (`EmailDeliveryRepository.php:243`) | Production rule wins. Do not widen eligibility — a bounced address should be suppressed, not retried. | low |
| D34 | Delivery log | copy | Non-actionable rows render an empty action cell (design:147-149) | `<span class="muted">—</span>` (`email.php:141`) | Adopt the empty cell; the `Action` column header already names it. | low |
| D35 | Delivery log / stats | feature-removed | Leans on `bounced`/`complained` throughout: seed rows (design:284,291,294), requeue eligibility (:312), empty-state copy (:200) | The enum exists (`database/migrations/0023_email_deliveries.php:21`) but **nothing in `src/` ever writes those statuses** — no bounce/complaint webhook ingestion. ADMIN §7.6 lists it as spec, gated on ESP selection. | Do **not** build ingestion in this migration. Keep the filter options and stat cards (the enum is real and the columns will simply read 0), but ship **no copy that promises automatic bounce/complaint capture**. Record the gap as an owned deferral in a new ADR. | medium |
| D36 | Delivery log | copy | Empty state: centred 38px block, h3 `Nothing matches these filters` + body sentence (design:156-159) | One muted table row `No deliveries match.` (`email.php:146-148`) | Adopt the centred two-part empty state (heading + explanatory line), rendered inside the card below the table. | low |
| D37 | Delivery log | feature-removed | `The log keeps thirty days. Widen the filters to see more of it.` (design:158) | **No `email_deliveries` retention purge exists** — `src/Worker/` has `IpRetentionPurger`, `OrphanAttachmentCleaner`, `ServerDraftRepository::purgeExpired`; nothing purges deliveries, and `bin/console` has no such worker. | Do not ship the retention sentence. Replace with a filter-scoped line, e.g. `Widen the filters to see more of the log.` Either implement retention as its own decided change or record the absence. | medium |
| D38 | Delivery log | copy | Pager: `Previous` / `Page N of M` / `Next`, ends rendered `disabled` (design:162-168); page size 10 | Bare `Previous`/`Next` anchors rendered only when they exist, no page label (`email.php:153-160`); page size 50 (`EmailOpsService.php:49`) | Add the `Page N of M` label (M = `ceil(total/per_page)`, new model key). Render the unavailable direction as a **disabled-looking `<span>` with no `href`** so the control never jumps — `AppAdminEmailTest.php:315` asserts `page=2` is absent when there is no next page, so a disabled control must not emit the URL. Keep page size 50. | low |
| D39 | Suppressed addresses | copy | Stacked label with a visible uppercase `Email` caption above the input (design:174-177) | `sr-only` label + placeholder (`email.php:167-168`) | Make the caption visible per the design; keep the `for`/`id` pairing. | low |
| D40 | Suppressed addresses | copy | `Enter a full email address.` (design:179) | `Enter a valid email address.` (`EmailOpsService.php:126`), pinned by `AppAdminEmailTest.php:82` | **Keep production's string** — same register, already test-pinned, and "valid" is the truthful predicate (`str_contains($email,'@')`). | low |
| D41 | Suppressed addresses | feature-added | Bare `<span role="alert">` (design:179) | `field_attrs()` + `field_error()` (aria-describedby / aria-invalid / autofocus-on-first-error) at `email.php:168,171`, plus a separate `unsuppress_error` alert at `:172-174`, both fed by the 422 re-render (`AdminEmailController.php:79-82,95`) | Keep both. ADR 0023 item 5 accessibility wiring; the 422 path is the anti-draft-loss contract (`AppAdminEmailTest.php:75-84`). | medium |
| D42 | Suppressed addresses | copy | Humanised reasons: `Hard bounce — mailbox does not exist`, `Spam complaint`, `Listed by the provider`, `Added by an operator` (design:300-302, 349) | Raw token echoed (`email.php:182`); the only tokens ever written are `manual` (`EmailOpsService.php:129`) and `unsubscribe` (`UnsubscribeController.php:37`) | Add a two-entry label map — `manual` → `Added by an operator`, `unsubscribe` → `Unsubscribed by the member` — with the raw token as the fallback. Do not invent bounce/complaint labels (see D35). | low |
| D43 | Suppressed addresses | copy | Action verb `Release` (design:194) | `Remove` (`email.php:188`); flash `Address removed from the suppression list.` (`AdminEmailController.php:97`) | Adopt `Release` and align the flash to `Address released from the suppression list.` Update `AppAdminEmailTest.php:124-133` in the same commit. | low |
| D44 | Suppressed addresses | copy | `No addresses are suppressed. Bounces and complaints land here automatically.` (design:200) | `No suppressed addresses.` (`email.php:194`) | Adopt the two-sentence shape but **rewrite the second sentence** — it is false (D35). Ship: `No addresses are suppressed. Unsubscribes and operator additions land here.` | medium |
| D45 | Both tables | feature-added | Bare `<table>` | `.table-scroll` wrappers with `tabindex="0" role="region" aria-label="…"` (`email.php:116,175`; `announcements.php:56`) | Keep. ADR 0023 item 5 + ADMIN §9.4 mobile scroll contract. | medium |
| D46 | Delivery log | feature-added | No export affordance | `Download CSV` link → `GET /admin/email/export` carrying the active filters (`email.php:114`, `AdminEmailController.php:137-192`) | Keep. Style as a secondary ghost button at the end of the filter row. | medium |
| D47 | Suppressed addresses | feature-added | No count | `suppression_count` is computed (`EmailOpsService.php:62`) but **never rendered** by `email.php` | Render it beside the heading in the design's result-label idiom, or drop the unused model key. Currently dead data. | low |
| D48 | Current banner | copy | Message as a warning callout: `--surface-review` ground, 3px `--warning` left rule, `--radius-md`, `--on-review` ink, 12px 16px (design:213) | `<p class="site-announcement-current">` (`announcements.php:19`) — **and that class has no rule anywhere in `public/assets/app.css`** (only `.site-announcement`, `-message`, `-dismiss`, `[hidden]` exist, `app.css:1423-1440`). It renders as an unstyled paragraph today. | Author the callout rule. Fixes a live dead-class defect as a side effect. | low |
| D49 | Current banner | copy | `{{ bannerDismissLabel }} · version {{ bannerVersion }}`, `.74rem` `--text-faint` (design:214) | Same semantics, `.muted` (`announcements.php:20-23`) | Skin only. | low |
| D50 | Current banner | copy | Ghost button with `--rust` ink, 1.5px `--border-soft` (design:215) | `.btn.btn-small.danger` inside a POST form with hidden `action=clear` (`announcements.php:24-28`) | Skin only; keep the form + CSRF. | low |
| D51 | Current banner | copy | `No banner is currently shown.` (design:219) | `No banner is currently shown.` (`announcements.php:30`) | **Verbatim match — no change.** | low |
| D52 | Publish a banner | copy | Uppercase caption `Message` above the textarea (design:227); `rows="3" maxlength="500"` | Inline `<label>Message` (`announcements.php:38-40`); same rows/maxlength | Adopt the caption treatment. | low |
| D53 | Publish a banner | copy | Right-aligned mono counter `{{ draftCount }} / 500` (design:230) | No counter | Server-render `mb_strlen($old['message'] ?? '') . ' / 500'` (correct at 422/429 re-render) and let `app.js` update it on `input` via a `data-*` hook. PE-safe. | low |
| D54 | Publish a banner | copy | Three checkboxes in a 9px column, 15px boxes with `accent-color: var(--accent)` (design:232-234) | Same three, same labels **verbatim** — `Members can dismiss this banner`, `Also send an in-app broadcast notification to all members`, `Also queue an email broadcast to active members` (`announcements.php:43-45`) | Skin only. Copy needs no change. | low |
| D55 | Publish a banner | copy | `draftDismissible: true` — dismissible defaults **on** (design:325) | `checked` comes only from `$old['dismissible']`, which is empty on a fresh GET (`announcements.php:43`) → fresh form is **unchecked** | Adopt default-on **for a fresh GET only**. Hazard: `$old['dismissible']` is set from `$request->post('dismissible') !== null` (`AnnouncementService.php:136`), so a naive `checked` default would silently re-check a box the admin deliberately cleared on the 422/429 re-render. Distinguish "no submission yet" from "submitted unchecked". Needs owner sign-off — it changes what gets published. | medium |
| D56 | Publish a banner | copy | Warning callout when the email box is ticked: `This will reach {{ broadcastReach }} by email. Broadcasts cannot be recalled once the queue starts.` (design:236-238) | No warning at all | Add it. The claim is **true**: `enqueueSystemForActiveUsers()` inserts immediately inside the publish transaction (`AnnouncementService.php:70-75`, `EmailDeliveryRepository.php:51-65`) and there is no recall route. PE: render it unconditionally beneath the checkbox; JS may add an `is-armed` emphasis when the box is ticked. Never JS-only. | low |
| D57 | Publish a banner | copy | `broadcastReach` = `1,204 active members` (design:440) | Not computed anywhere. `UserRepository` has only a total `count()` (`UserRepository.php:180`) — no active count | Add one repository method (`SELECT COUNT(*) FROM users WHERE status = 'active'`) and surface it through `AnnouncementService::consoleModel()`. The only new query in the whole screen. Must exclude the acting admin to match the enqueue's `u.id <> :actor` (`EmailDeliveryRepository.php:57`), or the number will overstate by one. | low |
| D58 | Publish a banner | copy | `A banner needs a message.` (design:241) | 422 re-render with `Announcement message must be 1–500 characters.` (`AnnouncementService.php:51`, rendered via `field_error` at `announcements.php:41`), pinned by `AdminAnnouncementTest.php:78` | Keep production's string — it carries the real bound and is test-pinned. | low |
| D59 | Publish a banner | feature-added | No rate limit | `announce` policy → 429 re-render preserving the typed message (`AnnouncementService.php:141-151`), proven by `tests/browser/admin-remediation.spec.ts:200-217` | Keep. The 429 message renders through the same `field_error` slot, so any form restructure must preserve that slot. | high |
| D60 | Recent history | copy | Columns When \| By \| Event \| Message \| Channels (design:251-255) | Identical set and order (`announcements.php:58`) | Skin only (caps `.66rem` `.12em` headers, `--border-soft` rule). | low |
| D61 | Recent history | feature-changed | Event is the raw token in a `<code>` chip: `publish` / `clear` (design:262) | `Cleared` / `Published v4` (`announcements.php:64`) — carries the version, which the design's model has no field for. Pinned by `admin-remediation.spec.ts:219` (`hasText: 'Published v'`) | Keep production's labels (behaviour/information wins); adopt the design's chip skin around them. | medium |
| D62 | Recent history | copy | `Banner · in-app · email` (design:306, 333-336) | Built identically at `announcements.php:67-71`, with `—` for clear rows | **Verbatim match — no change** beyond the `.74rem --text-faint` skin. | low |
| D63 | Recent history | feature-added | No null-message case | Legacy pre-payload rows render `<span class="muted">—</span>` (`announcements.php:65`, `AnnouncementService.php:200`) | Keep. | low |
| D64 | Recent history | copy | `No announcements have been published yet.` (design:271) | `No announcements have been published yet.` (`announcements.php:54`) | **Verbatim match — no change.** | low |
| D65 | Both consoles | feature-added | No flags | `email` and `announcements` flags 404 the routes when off (`AdminEmailController.php:23-28`, `AdminAnnouncementController.php:20-25`; `FeatureFlags.php:29,37` both default `true`), and the rail renders a disabled span with the pinned copy `Disabled until the feature flag is enabled` (`_nav.php:5,34-35,81-84`) | Keep. The new section tab strip (D2) must apply the same gating — never link a dark route. | high |
| D66 | Send a test email | feature-added | No rate limit | `email_test` policy → 429 (`AdminEmailController.php:62`), asserted by `AppAdminEmailTest.php:207-216` | Keep. Note the 429 currently renders as the kernel error page, not an in-place re-render — acceptable (no typed draft to lose). | medium |
| D67 | Delivery log | constraint | `r.requeue` mutates the row in place (design:401-403) | POST → redirect to `/admin/email?status=failed` with an honest no-op message when the row was not failed (`AdminEmailController.php:106-111`, `EmailOpsService.php:173-189`) | PRG + the honest message stay. Do not turn Requeue into a JSON/fetch action. | low |
| D68 | Time cells | copy | Literal `2 Aug 09:41`, mono `.78rem` `--text-faint`, `white-space:nowrap` (design:136, 193, 260) | `human_datetime()` (`email.php:122,183`; `announcements.php:62`) in default table type | Adopt the mono/faint/nowrap treatment for all When/Since cells; the helper already produces the right register. | low |

### Counts

| Classification | Count |
|---|---|
| copy | 39 |
| feature-added | 13 |
| feature-removed | 2 |
| feature-changed | 5 |
| constraint | 9 |
| **Total** | **68** |

---

## 3. Fiction strings

**Important scoping note:** unusually for this design set, almost all of this screen's fiction lives in the `x-dc` **seed arrays** (design:281-310, 323, 381), not in the static markup. Only four fiction strings appear in the markup that a transcriber would actually be looking at. Everything else is sample data that must simply not be transcribed.

| # | Design string (verbatim) | Where | Proposed production string |
|---|---|---|---|
| F1 | `Imladris` (wordmark) | design:25 | Do not port. Production renders the operator site name from `$brand['name']` (`templates/layout.php:27`). |
| F2 | eight-point elven star SVG | design:24 | Do not port. Not a RetroBoards mark; production uses `$brand['logo_path']` / the favicon (`layout.php:37-40`). |
| F3 | `Back to the council` | design:27 | `Back to the forum` — but the whole topbar is out of scope; production's shell already provides the return path. |
| F4 | `council.imladris.example` (sending domain sample) | design:54 | `mail.example.com`. Production renders the real `$domain['domain']`; only the *placeholder* register matters here. |
| F5 | `Your weekly council digest` (delivery-log subject, ×6) | design:282,283,284,288,294,295 | `Your weekly digest` — sample only; production renders the real `subject` column. |
| F6 | `Confirm your seat at the council` (×2) | design:285, 293 | `Confirm your email address` — and note this row could never appear: verification mail bypasses `email_deliveries` entirely (`EmailVerificationService.php:120`). |
| F7 | `The council stays readable throughout.` (seeded banner text) | design:323 | `The site stays readable throughout.` |
| F8 | `erestor@`, `lindir@`, `gildor@old.example`, `nimrodel@`, `rumil@`, `voronwe@`, `haldir@`, `melian@`, `orophin@`, `idril@`, `aegnor@`, `beleg@`, `elrond@` — all `@imladris.example` | design:282-296 | Neutral placeholders at `@example.com` / `@example.test` (production's own test fixtures already use `@example.test`). |
| F9 | `elrond`, `erestor` (history actor usernames) | design:306-309, 339, 430 | Neutral sample usernames; production renders the real `actor_username` (`AnnouncementService.php:197`). |
| F10 | `Galadriel mentioned you in #practice` | design:286 | `A member mentioned you in #general` |
| F11 | `Erestor replied to your topic` | design:292 | `A member replied to your topic` |
| F12 | `The lexicon has moved to #lore. Old links still resolve.` | design:309 | `The FAQ has moved to #help. Old links still resolve.` |

**Non-fiction check:** `Operator desk` (design:34), `Test message from the operator desk` (design:296) and `Admin mode` (design:37) are *already-adopted* production register — `Operator desk` appears verbatim at `templates/admin/dashboard.php`, `settings.php`, `branding.php`. Keep them. (Production's real test subject is `RetroBoards email delivery test`, `EmailOpsService.php:91` — a naming difference, not fiction.)

---

## 4. State inventory

### Email tab

| Design state | Design string (verbatim) | Line | Production equivalent |
|---|---|---|---|
| `testSent` | `Queued — it is at the top of the log.` | 71 | **Different mechanism (PRG).** `Test email sent to {email}.` (`AdminEmailController.php:68`). |
| test-send failure | *(not modelled)* | — | `Test send failed: {error}` (`EmailOpsService.php:116`) — real SMTP error surfaced. |
| test-send transport gate | *(not modelled)* | — | `Configure your sending domain first.` (`EmailOpsService.php:84`). |
| test-send domain gate | *(not modelled)* | — | `Email sending is blocked until SPF and DKIM pass.` (`EmailDomainVerifier::BLOCKED_REASON`, `:14`), also rendered as a page-level flash (`email.php:46`). ADR 0008. |
| `refreshDomain` | `domainChecked → 'just now'` (client) | 378 | `Email domain status refreshed.` flash + a real `checked_at` (`AdminEmailController.php:133`, `EmailDomainVerifier::verify()`). |
| SPF/DKIM chip states | `SPF pass` / `DKIM pending` | 56-57 | Real tri-state `pass` / `fail` / `unknown` (`EmailDomainVerifier.php:29-30,56-57`). Design models only two. |
| `noLogRows` | `Nothing matches these filters` + `The log keeps thirty days. Widen the filters to see more of it.` | 157-158 | `No deliveries match.` (`email.php:147`). **GAP** on the two-part shape; **retention sentence unsupported** (D37). |
| `logResultLabel` | `N messages` / `1 message` | 392 | `N total matching deliveries.` (`email.php:152`). |
| `showLogPager` / `pageLabel` | `Page N of M` | 165, 407 | **GAP.** Production has no page label (`email.php:153-160`). |
| `atFirstPage` / `atLastPage` | disabled Previous/Next | 164, 166 | **GAP.** Production omits the control entirely rather than disabling it. |
| `r.statusLabel` | `Sent` / `Queued` / `Failed` / `Bounced` / `Complained` / `Suppressed` (capitalised) | 395 | Lowercase raw token in `.state` (`email.php:125`). |
| `r.canRequeue` | failed + bounced + complained | 400 | `failed` only (`email.php:135`, `EmailDeliveryRepository.php:243`). |
| requeue no-op | *(not modelled)* | — | `That delivery is not in a failed state — nothing was requeued.` (`AdminEmailController.php:110`). |
| `suppressError` | `Enter a full email address.` | 179 | 422 re-render + `Enter a valid email address.` (`EmailOpsService.php:126`), test-pinned. |
| `noSuppressions` | `No addresses are suppressed. Bounces and complaints land here automatically.` | 200 | `No suppressed addresses.` (`email.php:194`). **Second sentence untrue** (D35). |
| statCards ×6 | live counts per status | 386 | `$status_counts` from `statusCounts()` (`email.php:77`). `bounced`/`complained` are structurally always 0. |
| **loading skeleton** | *(none on this screen)* | — | Nothing to refuse. Unlike `AdminOverview`, this screen has no pulsing placeholder. |
| — | *(not modelled)* | — | **Production-only:** not-ready flash; From-not-set fact; `Set a From address before verifying SPF and DKIM.`; send-blocked flash; disabled test button; `unsuppress_error`; test-send 429; CSV export. |

### Announcements tab

| Design state | Design string (verbatim) | Line | Production equivalent |
|---|---|---|---|
| `hasBanner` / `noBanner` | `No banner is currently shown.` | 219 | **Verbatim match** (`announcements.php:30`). |
| `bannerDismissLabel` | `Dismissible` / `Not dismissible` | 427 | **Verbatim match** (`announcements.php:21`). |
| `publishError` | `A banner needs a message.` | 241 | 422 + `Announcement message must be 1–500 characters.` (`AnnouncementService.php:51`), test-pinned. |
| `broadcastWarning` | `This will reach 1,204 active members by email. Broadcasts cannot be recalled once the queue starts.` | 237, 440 | **GAP.** No warning, no active-member count. |
| `draftCount` | `N / 500` | 230 | **GAP.** |
| `hasHistory` / `noHistory` | `No announcements have been published yet.` | 271 | **Verbatim match** (`announcements.php:54`). |
| `h.event` | `publish` / `clear` (code chip) | 262 | `Published v{n}` / `Cleared` (`announcements.php:64`), browser-spec-pinned. |
| `h.channels` | `Banner · in-app · email` | 306 | **Verbatim match** (`announcements.php:67-71`), plus `—` for clear rows. |
| — | *(not modelled)* | — | **Production-only:** 429 rate-limit re-render preserving the typed message; legacy null-message history row; flashes `Announcement published.` / `Announcement cleared.` (`AnnouncementService.php:131,169`). |

---

## 5. Slice proposal

Each slice is independently shippable, independently testable, and leaves the console green. **Every slice** ends with `php bin/build-imladris-assets.php --print-application-digest` → paste into `config/imladris-runtime-baseline.json` → `composer check:imladris` → `composer verify:imladris`, because each touches `templates/**` or `public/assets/**`.

### N1 — Notifications section chrome
**Touches:** `templates/admin/email.php` (head), `templates/admin/announcements.php` (head), `public/assets/app.css` (`.eyebrow` size/colour, `.pill-admin`, new `.text-tabs`/`.text-tab`), `config/imladris-runtime-baseline.json`.
**Delivers:** D3, D5, D2 (anchor tab strip), D6 (pane spacing).
**Tests:** PHPUnit — both pages carry `Operator desk · Notifications`; the tab strip emits two real `<a>` with `aria-current="page"` on the active one; with `announcements` flagged off the Announcements tab is absent (not a dead link) and `GET /admin/announcements` still 404s. Playwright desktop + mobile screenshots; `npm run a11y`; CSP scan `rg -n "<script|<style| on[a-z]+=" templates/admin -S` clean; `javaScriptEnabled:false` context proving both tabs navigate.
**Risk:** the grouped rail must not regress — `_nav.php` is untouched by this slice.

### N2 — Email status + sending domain
**Touches:** `templates/admin/email.php:20-89`, `public/assets/app.css` (`.email-status-facts` spec-list skin, status chips, 2-up grid, disabled button), `tests/Integration/Admin/AppAdminEmailTest.php` (D10 assertion).
**Delivers:** D8 (preserve + restyle), D9, D10, D11, D12, D13, D14, D15, D16, D17.
**Tests:** the four existing F24 tests (`AppAdminEmailTest.php:58-109`) must stay green **unmodified** except the `SPF: pass` substring; add a chip-variant test covering `unknown` and `fail`; `javaScriptEnabled:false` proof that Refresh SPF/DKIM posts and that the disabled test button cannot be submitted.
**Risk:** high-value — this is the ADR 0023 F24 surface. Any diff that removes a fact line is a revert.

### N3 — Queue status + delivery log
**Touches:** `templates/admin/email.php:73-161`, `src/Service/EmailOpsService.php` (add `total_pages`), `public/assets/app.css` (`.stat-card`, filter grid, status pills, kind chips, empty state, pager).
**Delivers:** D20, D21, D22, D23, D24, D25, D26 (confirm no change), D27, D28, D29, D30, D31, D32, D33 (confirm no change), D34, D36, D37, D38, D46, D68.
**Tests:** new — `Page 1 of N` renders and the disabled direction emits no `href`; the exact-multiple test (`AppAdminEmailTest.php:301-320`) stays green; empty-state text asserted and the string `thirty days` asserted **absent**; `test_delivery_log_filters_by_kind` (`:146`) stays green; no-JS filter submit + Reset link. Playwright screenshot of the populated and empty log.

### N4 — Suppressed addresses
**Touches:** `templates/admin/email.php:163-199`, `src/Controller/AdminEmailController.php:97` (flash wording), `public/assets/app.css`.
**Delivers:** D39, D40 (confirm no change), D41 (preserve), D42, D43, D44, D45 (preserve), D47.
**Tests:** the 422 anti-draft-loss test (`AppAdminEmailTest.php:75-84`) stays green; the round-trip test (`:124-133`) updated for `Release`; new test asserting a `manual` row renders `Added by an operator` and an `unsubscribe` row renders `Unsubscribed by the member`; empty-state text asserted with `Bounces and complaints` **absent**.

### N5 — Announcements console
**Touches:** `templates/admin/announcements.php`, `src/Service/AnnouncementService.php::consoleModel()` (add `active_member_count`), `src/Repository/UserRepository.php` (new `activeCountExcluding(int $userId): int`), `public/assets/app.css` (banner callout — fixes the dead `.site-announcement-current`), `public/assets/app.js` (character-counter decoration).
**Delivers:** D48, D49, D50, D51/D54/D62/D64 (confirm verbatim), D52, D53, D55, D56, D57, D58 (confirm no change), D59 (preserve), D60, D61, D63 (preserve).
**Tests:** `AdminAnnouncementTest.php` 422 (`:77-78`) and 429 (`:102`) stay green; `admin-remediation.spec.ts:200-223` (`Published v`, `Recent history`, `Clear banner`) stays green; new test for the reach warning text and its count; new test that the 422 re-render **preserves an intentionally-unchecked `dismissible`** (the D55 hazard); `javaScriptEnabled:false` proof that the warning and the counter both render server-side.

### N6 — Honesty ledger (doc-only, no template diff)
**Touches:** `docs/adr/0024-imladris-notifications-adoption.md` (next free number after 0023), `PHASE_5_STATUS.md`.
**Delivers:** records D35 (no bounce/complaint ingestion; `bounced`/`complained` remain inert enum values pending ESP selection per ADMIN §7.6), D37 (no delivery-log retention policy), D26 (the design's `verify`/`reset` kinds are structurally unreachable), D47/D57 decisions, and the D55 default-dismissible sign-off.
**Tests:** none — the ADR *is* the evidence, per PRODUCT_DESIGN §13 deferral discipline ("never silently dropped").

**Suggested order:** N1 → N2 → N3 → N4 → N5, with N6 landing alongside N3 (it records N3's and N4's copy rewrites). N5 is fully independent of N2–N4 and can be parallelised.
