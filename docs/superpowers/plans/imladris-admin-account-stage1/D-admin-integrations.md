# Stage 1 diff — `admin-integrations` (Imladris) vs production

**Design source:** `C:/Users/htper/community-forums/docs/design-system/imladris/templates/admin-integrations/AdminIntegrations.dc.html` (600 lines; markup lines 1–373, `x-dc` script 374–597)
**Template name:** `Admin — tokens, webhooks & sign-in`
**Tab strip (verbatim, in order):** `API tokens` · `Webhooks` · `Sign-in providers`

**Production surfaces in scope**

| Route | Template | Controller |
|---|---|---|
| `GET/POST /admin/api-tokens`, `POST /admin/api-tokens/{id}/revoke` | `templates/admin/api_tokens.php` | `src/Controller/AdminApiTokenController.php` |
| `GET/POST /admin/webhooks` | `templates/admin/webhooks.php` | `src/Controller/AdminWebhookController.php` |
| `GET/POST /admin/webhooks/{id}` (+ `/toggle` `/rotate` `/test` `/delete` `/deliveries/{deliveryId}/replay`) | `templates/admin/webhook_detail.php` | `src/Controller/AdminWebhookController.php` |
| `GET/POST /admin/providers`, `POST /admin/providers/{id}/test|enable` | `templates/admin/providers.php` | `src/Controller/AdminProviderController.php` |
| `GET/POST /admin/providers/{id}/disable` | `templates/admin/provider_disable.php` | `src/Controller/AdminProviderController.php` |
| `GET /admin/extensions` | `templates/admin/extensions.php` | `src/Controller/AdminExtensionController.php` |

All routes verified in `src/Core/App.php:2211–2312`.

---

## 0. Headline findings

1. **The design does NOT show an extensions surface.** The tab strip is exactly three tabs
   (`AdminIntegrations.dc.html:29–34`) and there is no extensions panel, no sandbox probe, no
   handler table anywhere in the file. Per the sync-phase peer context, `Extensions` is a tab of
   **`admin-packages`** (`Packages` / `Registry trust` / `Extensions`). So the reserved-dark rule is
   satisfied here by omission: **build nothing for `server_extensions` in this slice**, and leave
   `templates/admin/extensions.php` untouched — it is `admin-packages`' problem. The one production
   artefact that belongs to this screen's area model is the `Extensions` entry sitting in
   `_nav.php:42`'s `Integrations` group, which the design's area model puts under Packages.
2. **ADR 0023 / ADR 0021 webhook delete re-auth survives the design.** Production reauths delete
   (`WebhookService::delete()` at `src/Service/WebhookService.php:147–152`, comment at 142–146:
   *"Deleting an endpoint discards its delivery history and revokes its signing secret, so — like
   rotateSecret, and unlike the reversible pause — it is password-reauthed"*; UI at
   `templates/admin/webhook_detail.php:69–78`). The design models `deleteHook` as a bare danger
   Button with **no** password field (`AdminIntegrations.dc.html:208`, handler 518–521). **Do not
   drop the re-auth.** Classified `feature-changed`.
3. **The token reveal-once state is modelled correctly by the design** and already matches
   production almost verbatim. Design `secretLabel` = `'Copy this token now — it will not be shown
   again:'` (line 464); production `templates/admin/api_tokens.php:22` = `Copy this token now — it
   will not be shown again:`. Exact match including the em dash. The *webhook* secret label diverges
   only by a hyphen-vs-em-dash typo in production.
4. **The design has no idempotency/409 state.** Production's replay-safe mint
   (`ApiTokenService::mintPage()` → 409 model, `src/Service/ApiTokenService.php:242–247`) renders a
   `flash-error` at `api_tokens.php:15–19`. `feature-added` — keep it, restyle it.
5. **The tab strip is `<button onClick=…>` over one client-side `view` state.** Production has three
   distinct routes. This is the mandated PE constraint: `<a href>` links with `aria-current="page"`,
   plus flag-gating the strip (all three flags are default-ON but individually rollback-able:
   `api_tokens`, `webhooks`, `provider_registry` — `src/Core/FeatureFlags.php:94,95,87`).

---

## 1. Section-order comparison

### Design order (top to bottom of markup)

| # | Design section (verbatim heading / label) | Line |
|---|---|---|
| 1 | `AdminNav` shared chrome — `<x-import … AdminNav area="integrations">` | 22 |
| 2 | `h1` — **Tokens, webhooks & sign-in** | 26 |
| 3 | `nav aria-label="Integration sections"` — tab strip `API tokens` / `Webhooks` / `Sign-in providers` | 28–35 |
| 4 | Flash banner, `role="status"`, green + check icon (`sc-if flash`) | 37–42 |
| 5 | One-time secret reveal, `role="status"`, gold (`sc-if secret`) — `{{ secretLabel }}` + `<code>{{ secretText }}</code>` | 44–49 |
| 6 | `<!-- ═══ API tokens ═══ -->` panel (`sc-if showTokens`) | 51–119 |
| 6a | `h2` — **Create a token** + intro *"A token carries only the scopes you tick. The secret is shown once, on creation, and never again."* + inline `role="alert"` + `Name` / `<legend>Scopes</legend>` / `Expires in days (optional)` / `Confirm your password` (`sc-if reauthOn`) / Button **Create token** | 55–85 |
| 6b | Tokens table (unheaded card) — `Name` · `Scopes` · `Created` · `Last used` · `Status` · *(sr-only)* `Actions` | 87–116 |
| 7 | `<!-- ═══ Webhooks — list ═══ -->` panel (`sc-if showHooks`) | 121–246 |
| 7a | `h2` — **Register an endpoint** + intro *"Deliveries are signed with a per-endpoint secret and retried three times before they go dead."* + inline alert + `Name` / `URL` / `<legend>Events</legend>` / `Confirm your password` / Button **Register endpoint** | 125–155 |
| 7b | Endpoints table (unheaded card, `sc-if noHookDetail`) — `Name` · `URL` · `Status` · `Last status` *(right)* · *(sr-only)* `Actions` → **Manage** outline button | 157–183 |
| 7c | Webhook detail (`sc-if hasHookDetail`) — back button **All endpoints** (chevron); `h2 {{ hookTitle }}`; action row `{{ pauseLabel }}` (Pause/Resume, secondary) · **Send test event** (secondary, `sc-if testOn`) · **Rotate signing secret** (ghost); `Name` + `URL` two-up grid; **Save** + **Delete endpoint** (danger) | 186–210 |
| 7d | `h3` — **Recent deliveries** — `Event` · `Status` · `Attempts` *(right)* · `Response` *(right)* · `Error` (+ inline **Replay** on `dead`) | 212–241 |
| 8 | `<!-- ═══ Sign-in providers ═══ -->` panel (`sc-if showProviders`) | 248–368 |
| 8a | Intro paragraph (`sc-if noDisable`) — *"Generic OIDC providers are configuration, not code…"* | 254 |
| 8b | Providers table (unheaded card) — `Provider` · `Type` · `Issuer` · `Health` · `Sole-method` *(right)* · `Status` · `Actions` *(right)* | 256–293 |
| 8c | `h2` — **Add an OIDC provider** + inline alert + `Provider key` (+hint) / `Display name` / `Issuer (pinned)` (+hint) / `Client ID` + `Client secret` two-up / vault hint / Button **Add provider** | 295–326 |
| 8d | Disable drill-in (`sc-if hasDisable`) — back button **Sign-in providers**; `h2 {{ disableTitle }}` (= *"Before you disable ‹Name›"*); blurb; `role="alert"` sole warning + sole-account `<ul>` (Monogram / `@username` / email) **or** *"No accounts rely on this provider as their only sign-in method."*; inline alert; `Your password (re-authentication)` (`sc-if reauthOn`); **Disable ‹Name›** (danger) + **Cancel** (text) | 330–366 |

### Production order

| # | Production section | Path:line |
|---|---|---|
| P1 | `h1` **API tokens** + `<span class="pill pill-admin">Admin mode</span>` | `api_tokens.php:8–11` |
| P2 | `admin/_nav` — grouped rail (8 groups, `Integrations` holds 6 links) | `api_tokens.php:12`; `_nav.php:7–51` |
| P3 | 409 conflict `flash flash-error` | `api_tokens.php:15–19` |
| P4 | `new_token` secret `flash` (green) | `api_tokens.php:20–25` |
| P5 | `card` `h2` **Create a token** — form | `api_tokens.php:27–58` |
| P6 | `card` `h2` **Tokens** — table + empty state | `api_tokens.php:60–89` |
| P7 | `h1` **Webhooks** + pill | `webhooks.php:7–10` |
| P8 | `admin/_nav` | `webhooks.php:11` |
| P9 | `new_secret` `flash` (green) | `webhooks.php:14–19` |
| P10 | `first_party_hooks` status note | `webhooks.php:21–25` |
| P11 | `card` `h2` **Register an endpoint** | `webhooks.php:27–57` |
| P12 | `card` `h2` **Endpoints** — table + empty state | `webhooks.php:59–80` |
| P13 | `h1` **Webhook: ‹name›** + pill | `webhook_detail.php:10–13` |
| P14 | `new_secret` `flash` | `webhook_detail.php:17–22` |
| P15 | `card` `h2` **Configuration** — Name / URL / Events / **Save** | `webhook_detail.php:24–45` |
| P16 | `card` `h2` **Actions** — Pause/Resume + Send test event; `h3` **Rotate signing secret** (password); `h3` **Delete endpoint** (prose + password + **Delete webhook**) | `webhook_detail.php:47–79` |
| P17 | `card` `h2` **Recent deliveries** — table + empty state | `webhook_detail.php:81–110` |
| P18 | `h1` **Sign-in providers** + pill | `providers.php:7–10` |
| P19 | Intro paragraph | `providers.php:14–18` |
| P20 | Provider-level `field_error(..., alert: true)` | `providers.php:20` |
| P21 | `card` `h2` **Providers** — table | `providers.php:22–79` |
| P22 | `card` `h2` **Add an OIDC provider** — form (incl. Claim map + re-auth) | `providers.php:81–129` |
| P23 | `h1` **Disable ‹Name›** + pill | `provider_disable.php:7–10` |
| P24 | `card` `h2` **Before you disable** — blurb / sole warning + table / password / Disable + Cancel | `provider_disable.php:14–55` |
| P25 | `/admin/extensions` — `h1` **Server extensions**; `Sandbox probe`; `Global emergency disable`; `Handlers`; `Run history` | `extensions.php:1–65` |

**Structural verdict:** production is three (four with the disable page, five with extensions)
independent pages with per-page `h1`s and per-page card headings; the design is **one** page with
one `h1`, one tab strip, and two in-tab drill-ins (webhook detail, provider disable). The card
`h2`s that only label a table (`Tokens`, `Endpoints`, `Providers`, `Configuration`, `Actions`) do
not exist in the design — its tables sit in unheaded cards.

---

## 2. Difference table

| # | Section | Classification | Design | Production (path:line) | Action | Risk |
|---|---|---|---|---|---|---|
| 1 | Page chrome | copy | `<x-import … AdminNav area="integrations">` — flat 10-area bar, no per-area link lists | `api_tokens.php:12`, `webhooks.php:11`, `webhook_detail.php:14`, `providers.php:11`, `provider_disable.php:11` → `_nav.php` grouped 8-group rail | Cross-cutting. Owned by the `admin-overview` slice (grouped nav is an ADR 0023 §6 deliverable — do **not** unilaterally flatten it here). Mount whatever the shared-nav slice lands. | high |
| 2 | Page header | copy | Single `h1` **Tokens, webhooks & sign-in**; no `Admin mode` pill on the page (it lives in AdminNav `modeLabel`) | Three per-page `h1`s (`API tokens`, `Webhooks`, `Sign-in providers`) + `pill pill-admin` at `api_tokens.php:9–10`, `webhooks.php:8–9`, `providers.php:8–9` | Unify to one `h1` across the three routes; keep per-route `$this->section('title', …)` distinct for the browser tab. Remove the page-level pill only when AdminNav lands (item 1). | medium |
| 3 | Tab strip | constraint | `<nav aria-label="Integration sections">` of `<button onClick="{{ goTokens }}">` etc., `aria-current="page"` on the active one, 2px `--gold-500` bottom border, `--font-label` `.84rem` `.03em` | No per-screen tab strip exists at all | Render as `<a href="/admin/api-tokens">` / `/admin/webhooks` / `/admin/providers` with `aria-current="page"`. CSP + PE forbid `onClick`. `.subnav` (`app.css:295–297`) is close but is the nav-rail idiom — add a dedicated `.admin-tabs` class matching the design's metrics exactly. | low |
| 4 | Tab strip | constraint | Three tabs always rendered | Each route 404s when its flag is off — `AdminApiTokenController::gate()` `:14–19` (`api_tokens`), `AdminWebhookController::gate()` `:18–23` (`webhooks`), `AdminProviderController::gate()` `:132–137` (`provider_registry`) | Omit a tab whose flag is off (never render a link to a 404). Flags default-ON (`FeatureFlags.php:87,94,95`) but are rollback-able. Design models no flag handling. | low |
| 5 | Flash banner | copy | `role="status"`, `--surface-done` / `--green-200` bg + `border-left: 3px solid var(--success)` + 16px check `<svg>` + `aiRise 200ms` entry; sits **inside** the page container, below the tab strip | `.flash` at `app.css:189–197` (no left rule, no icon, no animation); `partials/flash.php` renders it in `<main>` **above** `.admin` (`layout.php:61`) | Add the left rule + inline SVG check + `@keyframes aiRise` to `.flash`. Move the flash slot inside `.admin-pane` for these screens (or give `layout.php` an opt-out and render `partials/flash` per-screen). | medium |
| 6 | Secret reveal | copy | Distinct **gold** banner: `--gold-050` bg, `--gold-200` border, `border-left: 3px solid var(--gold-500)`, `{{ secretLabel }}` in `--gold-700` `--font-label` + `<code>` on `--surface-raised` | Reuses the **green** `.flash` — `api_tokens.php:21–24`, `webhooks.php:15–18`, `webhook_detail.php:18–21` | Add a `.flash-secret` gold variant and use it for `new_token` / `new_secret`. A minted credential is not a success message. | low |
| 7 | Secret reveal — token label | copy | `Copy this token now — it will not be shown again:` (line 464) | `api_tokens.php:22` — identical, em dash | None. Already verbatim. | low |
| 8 | Secret reveal — webhook label | copy | `Copy this signing secret now — it will not be shown again:` (lines 489, 511) | `webhooks.php:16` and `webhook_detail.php:19` use an ASCII hyphen: `Copy this signing secret now - it will not be shown again:` | Replace `-` with `—` in both. | low |
| 9 | API tokens — create card | copy | Intro paragraph *"A token carries only the scopes you tick. The secret is shown once, on creation, and never again."* (line 57) | No intro copy — `api_tokens.php:28` jumps straight from `h2` to the form | Add verbatim. | low |
| 10 | API tokens — create card | copy | Two-column layout: create card `minmax(320px,400px)` left, table `1fr` right, `gap: 18px`, `align-items: start` (line 54) | Two stacked full-width `.card` sections | Adopt the two-up grid. | low |
| 11 | API tokens — scopes | feature-changed | `SCOPES` = `read:threads` / `read:posts` / `write:posts` / `admin:users` (lines 375–380) | `ApiScopes::SCOPES` = only `read:boards` → `List public boards`, `read:threads` → `Read threads in a public board` (`src/Security/ApiScopes.php:11–14`); rendered from `$scopes_catalogue` at `api_tokens.php:40–42` | Keep the production catalogue — it is the enforced scope set. Design demo data is fiction. Style the fieldset per design (`<code>` key + em dash + description). | low |
| 12 | API tokens — expiry field | copy | `Expires in days (optional)`, `type=number min=1`, no max | `Expires in days (optional)`, `min="1" max="365"` (`api_tokens.php:46–48`); validated `Expiry must be 1–365 days.` (`ApiTokenService.php:78`) | Keep `max="365"` (behaviour). Label already matches. | low |
| 13 | API tokens — re-auth | copy | `Confirm your password` under `sc-if reauthOn` (lines 77–82) | `Confirm your password` + `required` (`api_tokens.php:51–53`); enforced at `ApiTokenService::mint()` `:55` **before** field validation | Label matches. `reauthOn` is a design prop defaulting `true`; production is unconditional — no gap. | low |
| 14 | API tokens — table headings | copy | `Name` · `Scopes` · `Created` · `Last used` · `Status` · sr-only `Actions`; sits in an **unheaded** card | Identical 6 columns (`api_tokens.php:64`) but under `h2` **Tokens** (`:61`) | Drop the `h2 Tokens`. | low |
| 15 | API tokens — date rendering | copy | `4 Mar 2024`, `2 hours ago`, `—` | Raw DB datetimes: `<?= $e((string) $t['created_at']) ?>` and `$t['last_used_at'] ?? '—'` (`api_tokens.php:70–71`) | Run both through `human_datetime()` (already used at `providers.php:38`, `extensions.php:53`). | low |
| 16 | API tokens — status cell | copy | Dot + label, `active` green `--leaf` / `revoked` rust `--rust`, `--font-label .72rem` | `<span class="state state-active|state-revoked">` (`api_tokens.php:72`); `.state` + `::before` dot at `app.css:3465–3497` | Already the same anatomy. Verify `.state` font-size (`.78rem`) against design `.72rem`. | low |
| 17 | API tokens — revoked row action | copy | Renders an em-dash placeholder `—` when revoked (line 110) | Empty `<td>` (`api_tokens.php:74–79` — the `if` has no `else`) | Add the `—`. | low |
| 18 | API tokens — empty state | feature-added | No empty branch on `sc-for` at all | `No tokens yet. Tokens are shown once at creation and stored only as hashes.` (`api_tokens.php:84`) | Keep. Design gap. Style as the design's muted table row. | low |
| 19 | API tokens — idempotency 409 | feature-added | Not modelled | `That token request was already processed. No new token was minted — the original was shown once. Start again if you still need one.` in `flash flash-error` (`api_tokens.php:15–19`), fed by `ApiTokenService::mintPage()` 409 branch (`:242–247`); hidden `idempotency_key` at `api_tokens.php:31–32` | Keep. Design has no error-banner affordance — reuse `.flash-error` (`app.css:200–204`) and give it the same left-rule + icon treatment as the success flash. | low |
| 20 | API tokens — validation messages | feature-changed | `Give the token a name you will recognise in the audit log.` / `Choose at least one scope — a token with no scopes can do nothing.` / `Creating a token is a reauthenticated action — confirm your password.` (lines 459–461); rendered as **one** `role="alert"` paragraph above the form | Per-field via `field_error()`: `Name must be 1–80 characters.` (`ApiTokenService.php:59`), `Select at least one scope.` (`:73`), `Unknown scope.` (`:64`), `Duplicate scope.` (`:68`), `Expiry must be 1–365 days.` (`:78`), `Your current password is incorrect.` (`ReauthGate.php:43`) | Keep per-field errors (a11y + anti-draft-loss; `field_attrs`/`field_error` wire `aria-describedby`). Adopt the design's *register* — rewrite the production strings toward the design's causal voice. Do **not** collapse to a single banner. | medium |
| 21 | Webhooks — register card | copy | Intro *"Deliveries are signed with a per-endpoint secret and retried three times before they go dead."* (line 127) | No intro copy | Add — but corrected: production `webhooks.max_attempts` is **6** (`config/config.php:237`), not three. Ship *"…and retried up to six times before they go dead."* | low |
| 22 | Webhooks — register card | copy | Two-up grid, register card left / table right (line 124) | Stacked full-width cards | Adopt the two-up grid. | low |
| 23 | Webhooks — register card persistence | copy | The register card is **outside** both `sc-if`s (lines 125–155), so it stays visible in the detail view; only the right column swaps | `/admin/webhooks/{id}` is a separate page with no register form | Render the register card in the left column of the detail page too. | medium |
| 24 | Webhooks — event catalogue | feature-changed | `post.created` / `thread.created` / `thread.solved` / `user.registered` / `ping` (lines 382–388) | `WebhookEvents::EVENTS` — 11 events: `ping`, `topic.created`, `reply.created`, `post.edited`, `post.deleted`, `thread.solved`, `report.created`, `report.resolved`, `member.registered`, `member.banned`, `moderation.auto_action` (`src/Security/WebhookEvents.php:11–23`) | Keep production's catalogue (it is the wire contract). Adopt the design's row anatomy: `<code>{key}</code> — {description}`. Production already renders `key - desc` with an ASCII hyphen at `webhooks.php:45` → change to `—` and wrap the key in `<code>`. | low |
| 25 | Webhooks — URL validation copy | feature-changed | `The URL must be an absolute https:// address.` (line 484) | `Enter a valid URL.` / `URL must be http or https.` / `URL must not contain credentials.` / `URL is too long.` / `That URL is not an allowed destination.` (`WebhookService.php:364–380`); http is permitted when `WEBHOOK_ALLOW_HTTP` is set (`config/config.php:241`) | Production wins on behavior (http is legal under an explicit env opt-in, and SSRF egress validation is a real check the design omits). Keep the strings; align register. | low |
| 26 | Webhooks — `service_secrets` dependency | feature-added | Not modelled for webhooks (only mentioned in the provider form hint) | `Enable the service-secret store before creating webhooks.` on the `name` field (`WebhookService::assertSecretStoreEnabled()` `:341–346`) | Keep. Surface it above the form as a disabled-state notice rather than a stray field error when `service_secrets` is off. | low |
| 27 | Webhooks — `first_party_hooks` note | feature-added | Not modelled | `Domain events are inactive while first_party_hooks is off. The ping test event remains available.` / `Domain events are active for public-board content. The ping test event remains admin-only.` (`webhooks.php:21–25`) | Keep — it is honest flag-state reporting. Style as the design's muted intro paragraph, merged with the design's own intro copy. | low |
| 28 | Webhooks — endpoints table | copy | Unheaded card; `Name` · `URL` · `Status` · `Last status` *(right-aligned)* · sr-only `Actions`; action is a **Manage** outline button (`padding 4px 12px`, `1.5px solid var(--border-soft)`, `--radius-md`) | `h2 Endpoints` (`webhooks.php:60`); same 5 columns (`:63`); `Last status` left-aligned; action is a bare `<a href>Manage</a>` (`:71`) | Drop the `h2`; right-align `Last status`; render `Manage` as `.btn.btn-secondary.btn-small` (`imladris.css:386–391`). | low |
| 29 | Webhooks — last status placeholder | copy | `—` (em dash, from `last: '—'`) | `-` ASCII hyphen (`webhooks.php:70`) | Change to `—`. | low |
| 30 | Webhooks — empty state | feature-added | Not modelled | `No endpoints yet.` (`webhooks.php:75`) | Keep. | low |
| 31 | Webhook detail — back affordance | copy | Chevron button **All endpoints** above the detail card (line 188) | None — navigation is via `_nav` only | Add `<a href="/admin/webhooks">` with the chevron SVG. | low |
| 32 | Webhook detail — heading | copy | `h2 {{ hookTitle }}` = the endpoint's name alone, `1.5rem` display | `h1 Webhook: ‹name›` (`webhook_detail.php:11`) and `h2 Configuration` (`:25`) | Collapse to a single `h2` carrying just the escaped endpoint name. | low |
| 33 | Webhook detail — action row placement | copy | Action row (`Pause`/`Resume`, `Send test event`, `Rotate signing secret`) sits **above** the Name/URL fields inside the same card (lines 191–195) | Split across two cards: `Configuration` (fields + Save) then `Actions` (`webhook_detail.php:47–79`) | Merge into one card in the design order: actions row → fields → Save/Delete. | medium |
| 34 | Webhook detail — Rotate secret | feature-changed | Ghost button **Rotate signing secret**, one click, no confirmation (line 194, handler 511) | `h3 Rotate signing secret` + `Confirm your password` + **Rotate secret** button (`webhook_detail.php:60–68`); enforced at `WebhookService::rotateSecret()` `:80` | Keep the re-auth (production wins behavior). Present it in the design idiom — a ghost `Rotate signing secret…` that reveals/anchors the password confirm, not a bare form. | medium |
| 35 | Webhook detail — Delete | feature-changed | Danger button **Delete endpoint**, no confirmation (line 208, handler 518–521) | `h3 Delete endpoint` + prose *"Deleting removes the endpoint and its delivery history and revokes its signing secret. This cannot be undone."* + `Confirm your password` + **Delete webhook** (`webhook_detail.php:69–78`); enforced by `WebhookService::delete()` `:147–152` | **Binding — ADR 0021 ("reauthed webhook delete", `docs/adr/0021-…md:30`).** Keep the re-auth and the warning prose. Only the button label changes: `Delete webhook` → **Delete endpoint**. | high |
| 36 | Webhook detail — Events editing | feature-added | The detail card has only `Name` and `URL` (lines 197–205); `saveHook` touches only those two (514–517) | `Events` fieldset in the detail form (`webhook_detail.php:36–42`), persisted by `WebhookService::update()` `:102–123` | Keep. Style the fieldset per the design's register-card fieldset (`<code>key</code> — desc`); production's detail fieldset currently drops the description entirely (`:39` prints only `$event`). | low |
| 37 | Webhook detail — Save button | copy | **Save** (primary) beside **Delete endpoint** (danger) in one row (lines 206–209) | **Save** alone in `Configuration`; **Delete webhook** in a separate card | Pair them per design (respecting item 35's password field). | low |
| 38 | Webhook detail — pause label | copy | `{{ pauseLabel }}` = `Pause` when active, `Resume` when paused (line 500) | Identical logic (`webhook_detail.php:53`) | None. | low |
| 39 | Webhook detail — pause/resume flash | feature-changed | `Endpoint paused — queued deliveries are held, not dropped.` / `Endpoint resumed.` (line 503) | `Webhook paused — no deliveries will be attempted.` / `Webhook resumed — deliveries will flow on the next worker run.` (`AdminWebhookController.php:135`) | Production wins — the flashes are honest about the cron worker and were an ADR 0021 deliverable ("distinct pause/resume flashes"). Rename `Webhook` → `Endpoint` for register consistency; keep the worker clause. | low |
| 40 | Webhook detail — Send test event | feature-changed | `Test event sent — the endpoint answered 200.` and the delivery lands `ok` with response `200` immediately (lines 505–510) | `Test event queued. Run the webhook worker to deliver it.` (`AdminWebhookController.php:174`); `WebhookService::sendTestEvent()` only enqueues (`:254–264`) | Production wins — RetroBoards delivery is asynchronous (`worker:webhooks`). Keep the copy. | low |
| 41 | Webhook detail — test rate limit | feature-added | Not modelled | `RateLimitService::enforce('webhook_test', …)` (`AdminWebhookController.php:167`) | Keep. Design has no 429 state; ensure the 429 renders in the design's alert idiom. | low |
| 42 | Webhook detail — deliveries table | copy | `h3` **Recent deliveries** in its own card; columns `Event` · `Status` · `Attempts` *(right)* · `Response` *(right)* · `Error`; **5** columns, Replay is inline inside the Error cell | `h2` **Recent deliveries**; columns `Event` · `Status` · `Attempts` · `Last response` · `Error` · sr-only `Actions`; **6** columns, Replay in its own cell (`webhook_detail.php:85–101`) | Demote to `h3`; rename `Last response` → `Response`; right-align Attempts + Response; fold the Replay button into the Error cell and drop the 6th column. | low |
| 43 | Webhook detail — delivery status vocabulary | feature-changed | `delivered` / `dead` / `pending` (lines 227–229) | DB enum is `queued` / `delivered` / `dead` (`database/migrations/0057_phase5_webhooks.php:43`); rendered raw at `webhook_detail.php:90` | Keep the three production statuses; map `queued` to the design's amber `--on-review` treatment (design calls it `pending`). Wrap in `.state state-delivered|state-dead|state-queued` so the colour is token-driven. | low |
| 44 | Webhook detail — attempts format | copy | `1 / 3` (spaces around the slash) | `<?= (int) $d['attempt_count'] ?>/<?= (int) $d['max_attempts'] ?>` → `1/6` (`webhook_detail.php:91`) | Add the spaces. The `3` is design demo data; the real denominator is `max_attempts` (default 6). | low |
| 45 | Webhook detail — empty error cell | copy | `{{ d.error }}` falls back to `'—'` (line 523) | `<?= $e((string) ($d['error'] ?? '')) ?>` → blank cell (`webhook_detail.php:93`) | Emit `—` when empty. | low |
| 46 | Webhook detail — deliveries empty state | feature-added | Not modelled | `No deliveries yet.` (`webhook_detail.php:105`) | Keep. | low |
| 47 | Webhook detail — replay flash | feature-changed | `Delivery replayed — the endpoint answered 200.` (line 529) | `Delivery re-queued.` (`AdminWebhookController.php:201`); `WebhookService::replay()` only requeues (`:267–282`) | Production wins (async). Extend the copy to the design's causal register: *"Delivery re-queued — the worker will retry it on its next run."* | low |
| 48 | Providers — intro paragraph | copy | *"Generic OIDC providers are configuration, not code: a pinned HTTPS issuer, a client id, and a client secret stored only in the encrypted vault. New providers land **disabled** — run "Test connection", then enable. Builtin providers are configured through environment variables and shown here for visibility. Disabling never deletes linked identities."* (line 254) | Same paragraph except: *"Builtin providers **(Google, Apple, GitHub)** are configured through environment variables and **only** shown here for visibility."* (`providers.php:14–18`) | Adopt the design's shorter clause (the parenthetical hard-codes a registry that `ProviderRegistry` owns). Also add `max-width: 92ch` + `text-wrap: pretty`. | low |
| 49 | Providers — table columns | copy | 7 columns: `Provider` (name + `<code>key</code>` in one cell) · `Type` · `Issuer` · `Health` · `Sole-method` *(right)* · `Status` · `Actions` *(right)*; unheaded card | 8 columns: `Provider` · `Key` (separate) · `Type` · `Issuer` · `Health` · `Sole-method accounts` · `Status` · `Actions`, all left-aligned (`providers.php:26`); under `h2 Providers` (`:23`) | Merge `Key` into the `Provider` cell as a muted `<code>`; rename the header to `Sole-method` and right-align it; right-align `Actions`; drop `h2 Providers`. **Keep `data-sole-count`** (`providers.php:41–44`) — ADR 0023 records it as the `AppAdminProvidersTest` anchor, not dead chrome. | medium |
| 50 | Providers — health cell | copy | One string: `reachable · 3h ago`, `never checked`, `n/a` | `health_status` + a separate muted `human_datetime(health_checked_at)` span (`providers.php:35–40`) | Compose one `status · relative-time` string with a middot; keep `never checked` when `health_checked_at` is null. | low |
| 51 | Providers — status cell | copy | Rounded pill: enabled → `--surface-done`/`--on-done`, disabled → `--surface-sunken`/`--text-muted`; text `Configured`/`Not configured`/`Enabled`/`Disabled` (lines 276–277) | Bare text, same four words (`providers.php:46–50`) | Wrap in the pill. Text already matches verbatim. | low |
| 52 | Providers — builtin actions | copy | `Set OAUTH_‹KEY›_* env vars` in `--text-faint .82rem` (line 537) | `<span class="muted">Set <code>OAUTH_‹KEY›_*</code> env vars</span>` (`providers.php:71`) | Already matches. | low |
| 53 | Providers — Test connection | copy | Bare link-button, accent, hover underline (line 283) | `<button class="btn btn-small">` inside an inline form (`providers.php:54–57`) | Restyle to the design's link-button (`.linkbtn`, `imladris.css:402`). POST + CSRF stay. | low |
| 54 | Providers — Enable | feature-changed | Bare accent link-button **Enable**, no re-auth (line 285) | Inline form with a `Your password` field embedded in the table cell, plus `enable_error_id` routing so the error lands beside the row and not under the add-provider form (`providers.php:58–66`; controller `AdminProviderController.php:86–95` with the explanatory comment) | Production wins on behavior — enabling a sign-in provider is reauthed and the error-routing was a round-2 remediation. Keep both. Present the trigger as a design-idiom **Enable…** link-button that reveals the password confirm, mirroring the `Disable…` drill-in. | high |
| 55 | Providers — Disable | copy | `Disable…` danger link-button opening the in-tab drill-in (line 284) | `<a href="/admin/providers/{id}/disable">Disable…</a>` (`providers.php:68`) — separate page | Ellipsis and register already match. Restyle as a danger link-button. Keep the separate route (PE constraint — the design's drill-in is client state). | low |
| 56 | Providers — Add form heading/width | copy | `h2` **Add an OIDC provider**, card capped at `max-width: 560px`, `margin-top: 18px` | Same `h2` (`providers.php:82`), full-width card | Cap at 560px. | low |
| 57 | Providers — Provider key hint | copy | *"Stable slug used in `/auth/{key}/…` URLs — it cannot be changed later."* (line 302) | *"Stable slug used in `/auth/{key}/…` URLs and identity rows — it cannot be changed later. Lowercase letters, digits, hyphens, underscores."* (`providers.php:90`) | Production is strictly more informative and carries the `aria-describedby` wiring (`:88`). Keep production's text; adopt the design's `--text-faint .82rem` treatment. | low |
| 58 | Providers — Issuer hint | copy | *"Discovery resolves from `{issuer}/.well-known/openid-configuration`; a trailing slash is significant."* (line 311) | *"Discovery is resolved from `{issuer}/.well-known/openid-configuration`; the JWKS URL must be same-origin with this issuer. Enter the issuer exactly as the IdP publishes it — a trailing slash is significant."* (`providers.php:102`) | Keep production (the same-origin JWKS rule is a real enforced constraint). Adopt the design's typography. | low |
| 59 | Providers — Client ID / secret layout | copy | Two-up `grid-template-columns: 1fr 1fr` (line 313) | Stacked full-width | Adopt the two-up grid. | low |
| 60 | Providers — vault hint | copy | *"The secret is stored write-only in the encrypted vault (`service_secrets` must be enabled first)."* (line 323) | *"Stored write-only in the encrypted service-secret vault (`service_secrets` must be enabled first); rotate it from the vault, not here."* (`providers.php:113`) | Keep production's clause about rotation. Adopt the design's placement (after the two-up grid, before the button). | low |
| 61 | Providers — Claim map field | feature-added | Not modelled | `Claim map (optional JSON)` textarea + hint *"Renames the cosmetic claims only (email, email_verified, name, username, picture). The subject claim is always sub."* (`providers.php:116–120`); validated `Claim map must be a JSON object of at most 64 KB (or left empty).` (`IdentityProviderService.php:85`) | Keep. Place after `Client secret`, styled in the design idiom. | low |
| 62 | Providers — add-form re-auth | copy | `Confirm your password` is **not** present on the add-provider form (the design only gates create-token / register-endpoint / disable) | `Your password (re-authentication)` + `required` (`providers.php:122–124`); enforced by `IdentityProviderService::create()` | Keep the re-auth (behavior). Use the design's *disable-drill-in* label `Your password (re-authentication)` — production already does. | low |
| 63 | Providers — validation messages | feature-changed | Single `role="alert"` line: `The provider key must be 2–32 lowercase characters: letters, numbers, underscore or hyphen.` / `That provider key is already in use.` / `Give the provider a display name — members see it on the sign-in page.` / `The issuer must be an https:// URL.` / `A client id and secret are both required.` (lines 582–586) | Per-field: `Use 2–32 lowercase letters, digits, hyphens, or underscores.` (`IdentityProviderService.php:52`), `That key is reserved for a builtin provider.` (`:54`), `That provider key already exists.` (`:56`), `Display name is required (up to 190 characters).` (`:61`), `Issuer must be a clean HTTPS URL (up to 512 characters) — no query string or fragment.` (`:70`), `Client ID is required.` (`:75`), `Client secret is required.` (`:80`), `Enable the service_secrets flag first — provider client secrets are stored only in the encrypted vault.` (`:90`) | Keep per-field errors + `field_attrs`/`field_error`. Design's `role="alert"` summary line maps to production's `field_error(..., 'provider', alert: true)` at `providers.php:20`. | medium |
| 64 | Providers — anti-draft-loss | constraint | Design keeps typed values in client state trivially | `AdminProviderController::create()` `:47–52` re-renders 422 with `$request->allInput()` minus `client_secret`/`current_password`/`_token` | Preserve exactly. Any restructure of the add form must keep the `$old[…]` round-trip on every input. | high |
| 65 | Providers — empty state | feature-removed | No empty branch | No empty branch either (`providers.php:28–75`) — a fresh install with no `identity_providers` rows renders an empty `<tbody>` | Neither side models it. Add a muted empty row in the design idiom (this is a real production gap, not design chrome). | low |
| 66 | Providers — `noindex` | feature-added | Not modelled | Every provider view is wrapped in `$this->noindex(...)` (`AdminProviderController.php:53,67,96,124,156,173`) | Keep. | low |
| 67 | Disable — page vs drill-in | constraint | In-tab drill-in on the same URL (`sc-if hasDisable`), entered by `p.askDisable` | Separate route `GET /admin/providers/{id}/disable` → `provider_disable.php` (`App.php:2233`) | Keep the route (PE + the TM-ID-09-clause-2 "confirm before anything changes" contract, `AdminProviderController.php:17–24`). Render it with the same `h1` + tab strip so it reads as the drill-in the design shows. | medium |
| 68 | Disable — back affordance | copy | Chevron button **Sign-in providers** above the card (line 332) | None | Add `<a href="/admin/providers">` with the chevron SVG. | low |
| 69 | Disable — heading | copy | One `h2` = `Before you disable ‹Name›` (line 553) | `h1 Disable ‹Name›` (`provider_disable.php:8`) + `h2 Before you disable` (`:15`) | Collapse to `h2 Before you disable ‹Name›` under the shared `h1`. | low |
| 70 | Disable — blurb | copy | *"Disabling removes ‹Name› from sign-in and blocks its `/auth/‹key›/…` flow. Linked identities are retained — re-enabling restores sign-in unchanged."* (lines 554–556) | Same sentence, with `<strong>` on the name and on `retained`, and `<code>` on the path (`provider_disable.php:16–18`) | Already verbatim. Keep the emphasis; cap at `70ch` + `text-wrap: pretty`. | low |
| 71 | Disable — sole-method warning | copy | *"N account(s) can sign in only through this provider (no password, no passkey, no other provider). They will be locked out until they use password reset on their listed email, or you re-enable the provider. Contact them first."*, `role="alert"`, `color-mix(in srgb, var(--rust) 9%, …)` plate + 3px rust left rule (lines 337, 558–560) | Same sentence with `<strong>only</strong>`, `role="alert"`, class `field-error` (`provider_disable.php:23–28`) | Copy verbatim already; upgrade the presentation from a bare red paragraph to the design's rust plate. | low |
| 72 | Disable — sole-account list | copy | `<ul>` of ruled rows: `Monogram` + `{{ a.username }}` in `--accent` + right-aligned mono email (lines 338–346) | `<table class="audit">` with headers `Account` / `Email`; account is a link `/admin/users?q=‹username›` (`provider_disable.php:29–41`) | Adopt the `<ul>` row anatomy + monogram (`monogram_*` helpers exist in `src/Support/helpers.php`). | low |
| 73 | Disable — account drill-in | feature-added | Rows are inert text | Username links to the member record search (`provider_disable.php:35`) | Keep — "Contact them first" needs a path to the record. Design gap. | low |
| 74 | Disable — no-sole state | copy | *"No accounts rely on this provider as their only sign-in method."* (line 349) | Identical (`provider_disable.php:21`) | None. | low |
| 75 | Disable — confirm/cancel row | copy | **Disable ‹Name›** danger button + **Cancel** as a bare text button (lines 360–361) | `<button class="btn">Disable ‹Name›</button>` (not `.btn-danger`) + `<a class="btn btn-secondary">Cancel</a>` (`provider_disable.php:52–53`) | Make the confirm `.btn-danger`; demote Cancel to a text link. | low |
| 76 | Disable — re-auth label | copy | `Your password (re-authentication)` (line 355) | Identical (`provider_disable.php:48`) | None. | low |
| 77 | Disable — flash | copy | `‹Name› is disabled. Linked identities were retained.` (line 571) | `‹Name› disabled. Linked identities are retained; members keep their password/passkey fallbacks.` (`AdminProviderController.php:126`) | Production is more informative; keep it. Register already matches. | low |
| 78 | Providers — create flash | copy | `‹Name› was added, disabled. Run "Test connection", then enable it.` (line 590) | `Provider added (disabled). Run "Test connection", then enable it.` (`AdminProviderController.php:53`) | Interpolate the display name per design. | low |
| 79 | Providers — test flash | feature-changed | `Discovery succeeded for ‹Name› — the issuer answered with a valid configuration.` (line 543) | `Provider health: ‹status› — ‹detail›`, e.g. `Discovery and JWKS verified; caches primed.` / `Refused: ‹reason›` / `Provider unreachable.` (`AdminProviderController.php:69`; `IdentityProviderService.php:176–182`) | Production wins — it reports failures too, which the design never models. Reword to the design's register: *"Discovery succeeded for ‹Name› — ‹detail›."* on ok, keep a distinct failure string. | low |
| 80 | Providers — enable flash | copy | `‹Name› is enabled and now offered on the sign-in page.` (line 547) | `‹Name› is now offered at sign-in.` (`AdminProviderController.php:96`) | Adopt the design string. | low |
| 81 | API tokens — revoke flash | copy | `"‹Name›" was revoked — calls with it now fail closed.` (line 473) | `API token revoked.` (`AdminApiTokenController.php:53`) | Adopt the design string (name-interpolated, curly quotes). | low |
| 82 | Webhooks — update flash | copy | `Endpoint configuration saved.` (line 516) | `Webhook updated.` (`AdminWebhookController.php:106`) | Adopt the design string. | low |
| 83 | Webhooks — delete flash | copy | `Endpoint deleted — its delivery history and signing secret are gone with it.` (line 520) | `Webhook deleted.` (`AdminWebhookController.php:191`) | Adopt the design string. | low |
| 84 | Extensions surface | copy | Absent from this screen — the tab strip is exactly three tabs (lines 29–34). The live project puts `Extensions` under `admin-packages` | `templates/admin/extensions.php:1–65` (`Sandbox probe` / `Global emergency disable` / `Handlers` / `Run history`) reached from `_nav.php:42`'s `Integrations` group | **Build nothing here.** Do not touch `extensions.php` in this slice — it belongs to the `admin-packages` diff. When the area model lands, the nav entry moves from `Integrations` to `Packages`. Reserved-dark rule is satisfied: `server_extensions` is default-OFF (`FeatureFlags.php:100`) and the controller 404s (`AdminExtensionController.php:20–22`). | low |
| 85 | Token expiry visibility | feature-removed | Neither the design table nor production shows an `Expires`/expired state | `api_tokens` selects `expires_at` (`ApiTokenRepository.php:52`) but the template never renders it; an expired token still shows `active` (`api_tokens.php:72` keys only on `revoked_at`) | Not a design deviation — flag it as an honesty gap for a follow-up (out of scope for a verbatim-adoption slice). | medium |
| 86 | Inline styles → CSS | constraint | Every visual property is an inline `style="…"` attribute plus `style-hover` / `style-focus` pseudo-attributes; behavior is `<script type="text/x-dc">` | Strict CSP `script-src 'self'; style-src 'self'` — no inline anything (`src/Security/SecurityHeaders.php`) | Translate every inline rule into `public/assets/imladris.css` classes; the rendered result must be pixel-identical. No new JS is required for this screen once the tabs are links and the drill-ins are routes. | low |

---

## 3. Fiction strings

| # | Design string (verbatim) | Where | Proposed production string |
|---|---|---|---|
| 1 | `Imladris` (admin-bar wordmark) | `components/admin/AdminNav.jsx:53` (mounted at `AdminIntegrations.dc.html:22`) | `<?= $e($brand['name']) ?>` — the operator's configured site name |
| 2 | `Back to the council` | `AdminNav.jsx:44` (`backLabel` default) | `Back to the forum` |
| 3 | `Admin mode` | `AdminNav.jsx:45` (`modeLabel` default) | `Admin mode` — already neutral, keep verbatim |
| 4 | `https://ops.imladris.council/hooks/forum` | line 397 (demo endpoint URL) | Demo data only — production renders `$w['url']`. Any fixture/screenshot should use `https://ops.example.com/hooks/forum` |
| 5 | `https://id.imladris.council/realms/council` | line 412 (demo issuer) | Fixture: `https://id.example.com/realms/main` |
| 6 | `Council Keycloak` | line 412 (demo provider display name) | Fixture: `Corporate Keycloak` |
| 7 | `mellon@imladris.council` | line 411 (demo sole-method email) | Fixture: `first@example.com` |
| 8 | `haldir@lorien.test` / `Haldir` / `mellon` / `Mellon` | line 411 (demo account names) | Fixture: neutral usernames (`avery`, `blake`) |
| 9 | `iml_pat_` (minted token prefix) | line 464 handler | Production already mints `rbt_` (`ApiTokenService.php:96`) — no change |
| 10 | `whsec_iml_` (webhook secret prefix) | lines 489, 511 | Production mints bare hex (`WebhookService.php:57`) — no change, or adopt a neutral `whsec_` prefix if a prefix is wanted |
| 11 | `Ops bridge` (name placeholder) | line 132 `placeholder="Ops bridge"` | Neutral enough — keep verbatim |
| 12 | `Read-only mirror` (name placeholder) | line 62 `placeholder="Read-only mirror"` | Neutral — keep verbatim |
| 13 | `gitlab` / `GitLab` / `https://gitlab.com` (form placeholders) | lines 301, 306, 310 | Neutral vendor examples — keep verbatim; production already uses `https://gitlab.com` at `providers.php:100` |

No `council` / `warden` / `counsel` / `regard` / `commend` / `the hall` / `Third Age` string appears in
this screen's own markup or copy. The only lexicon leakage is via the shared `AdminNav` (rows 1–2)
and demo-data hostnames.

---

## 4. State inventory

| Design state | Verbatim string / condition | Production equivalent | Verdict |
|---|---|---|---|
| Tab active | `aria-current="page"` + 2px `--gold-500` underline | none | **gap** — add with `<a>` tabs |
| Flash (success) | `{{ flashText }}` in green plate | `partials/flash.php` + `redirectWithFlash` | present, restyle |
| Secret reveal (token) | `Copy this token now — it will not be shown again:` | `api_tokens.php:22` | **match** |
| Secret reveal (webhook, create) | `Copy this signing secret now — it will not be shown again:` | `webhooks.php:16` (hyphen) | near-match, fix dash |
| Secret reveal (webhook, rotate) | same string | `webhook_detail.php:19` (hyphen) | near-match, fix dash |
| Token form error | `Give the token a name you will recognise in the audit log.` | `Name must be 1–80 characters.` (`ApiTokenService.php:59`) | present, reword |
| Token form error | `Choose at least one scope — a token with no scopes can do nothing.` | `Select at least one scope.` (`:73`) | present, reword |
| Token form error | `Creating a token is a reauthenticated action — confirm your password.` | `Your current password is incorrect.` (`ReauthGate.php:43`) | present, reword |
| Token status | `active` / `revoked` (dot + label) | `.state state-active` / `state-revoked` (`api_tokens.php:72`) | **match** |
| Token empty | *(none)* | `No tokens yet. Tokens are shown once at creation and stored only as hashes.` | production-only, keep |
| Token 409 | *(none)* | `That token request was already processed. No new token was minted — the original was shown once. Start again if you still need one.` | production-only, keep |
| Hook form error | `Name the endpoint.` | `Name must be 1-80 characters.` (`WebhookService.php:356`) | present, reword (also fix the ASCII hyphen → `–`) |
| Hook form error | `The URL must be an absolute https:// address.` | five URL messages (`:364–380`) | present, production wins |
| Hook form error | `Subscribe the endpoint to at least one event.` | `Select at least one event.` (`:400`) | present, reword |
| Hook form error | `Registering an endpoint is a reauthenticated action — confirm your password.` | `Your current password is incorrect.` | present, reword |
| Hook secret-store gate | *(none)* | `Enable the service-secret store before creating webhooks.` (`:344`) | production-only, keep |
| Hook status | `active` / `paused` | `.state state-active` / `state-paused` (`webhooks.php:69`) | **match** |
| Hook empty | *(none)* | `No endpoints yet.` | production-only, keep |
| Hook flash (pause) | `Endpoint paused — queued deliveries are held, not dropped.` | `Webhook paused — no deliveries will be attempted.` | present, production wins |
| Hook flash (resume) | `Endpoint resumed.` | `Webhook resumed — deliveries will flow on the next worker run.` | present, production wins |
| Hook flash (test) | `Test event sent — the endpoint answered 200.` | `Test event queued. Run the webhook worker to deliver it.` | present, production wins |
| Hook flash (save) | `Endpoint configuration saved.` | `Webhook updated.` | present, adopt design |
| Hook flash (delete) | `Endpoint deleted — its delivery history and signing secret are gone with it.` | `Webhook deleted.` | present, adopt design |
| Hook flash (replay) | `Delivery replayed — the endpoint answered 200.` | `Delivery re-queued.` | present, production wins |
| Delivery status | `delivered` / `dead` / `pending` | `delivered` / `dead` / `queued` (enum, `0057_phase5_webhooks.php:43`) | map `pending`→`queued` |
| Delivery empty | *(none)* | `No deliveries yet.` | production-only, keep |
| Delivery replay affordance | inline **Replay** only when `dead` | same condition (`webhook_detail.php:95`) | **match** |
| Provider type | `Builtin (env config)` / `Generic OIDC` | identical (`providers.php:33`) | **match** |
| Provider status | `Configured` / `Not configured` / `Enabled` / `Disabled` | identical (`providers.php:46–50`) | **match**, needs pill |
| Provider health | `reachable · 3h ago` / `never checked` / `n/a` | `health_status` + `human_datetime` (`providers.php:35–40`) | compose one string |
| Provider builtin hint | `Set OAUTH_‹KEY›_* env vars` | identical (`providers.php:71`) | **match** |
| Provider form error | `The provider key must be 2–32 lowercase characters: …` | `Use 2–32 lowercase letters, digits, hyphens, or underscores.` | present, reword |
| Provider form error | `That provider key is already in use.` | `That provider key already exists.` + `That key is reserved for a builtin provider.` | present, production richer |
| Provider form error | `Give the provider a display name — members see it on the sign-in page.` | `Display name is required (up to 190 characters).` | present, reword |
| Provider form error | `The issuer must be an https:// URL.` | `Issuer must be a clean HTTPS URL (up to 512 characters) — no query string or fragment.` | present, production wins |
| Provider form error | `A client id and secret are both required.` | `Client ID is required.` / `Client secret is required.` | present, production wins (per-field) |
| Provider builtin write guard | *(none)* | `Builtin providers are configured through environment variables, not the console.` (`IdentityProviderService.php:131,167`) | production-only, keep |
| Provider vault gate | *(none)* | `Enable the service_secrets flag first — provider client secrets are stored only in the encrypted vault.` (`:90`) | production-only, keep |
| Provider table empty | *(none)* | *(none)* | **gap on both sides** |
| Disable — sole warning | `N account(s) can sign in only through this provider (no password, no passkey, no other provider). They will be locked out until they use password reset on their listed email, or you re-enable the provider. Contact them first.` | identical (`provider_disable.php:24–27`) | **match** |
| Disable — no sole | `No accounts rely on this provider as their only sign-in method.` | identical (`provider_disable.php:21`) | **match** |
| Disable — error | `Confirm your password to disable a sign-in provider.` | `Your current password is incorrect.` | present, reword |
| Disable — confirm label | `Disable ‹Name›` | identical (`provider_disable.php:52`) | **match** |
| Loading | *(none anywhere)* | server-rendered, none needed | n/a |
| `showBuiltinProviders` prop | default `true` — can filter builtins out | production always lists all (`AdminProviderController::providersView()` `:147–154`) | prop default matches production; no gap |
| `allowTestEvents` prop | default `true` — hides **Send test event** | production always shows it (rate-limited) | no gap |
| `requireReauth` prop | default `true` | production is unconditional | no gap |

---

## 5. Slice proposal

Each slice is independently shippable, independently testable, and leaves the console green.

**S1 — Integration tab strip + unified header (PE + CSP foundation).**
Add a `templates/admin/_integration_tabs.php` partial rendering `<a href>` links to
`/admin/api-tokens`, `/admin/webhooks`, `/admin/providers` with `aria-current="page"`, omitting any
tab whose flag is off. Unify the four templates on one `h1` **Tokens, webhooks & sign-in** (keep
per-route `section('title')`). Add `.admin-tabs` to `imladris.css` with the design's exact metrics.
*Tests:* new integration assertions for tab presence/omission per flag in
`tests/Integration/Core/AppFeatureFlagTest.php` + `AppAdminProvidersTest`; Playwright screenshots via
`tests/browser/{api-tokens,webhooks,providers}.spec.ts`.

**S2 — Flash + one-time-secret banner treatment.**
Add the left rule, check SVG and `aiRise` to `.flash`; add `.flash-secret` (gold) and use it for
`new_token` / `new_secret`; fix the two hyphen→em-dash secret labels; move the flash slot inside
`.admin-pane` for these routes. No behavior change.
*Tests:* assert the em-dash label in `AdminApiTokenTest` / `AdminWebhookTest`; browser screenshot of
the reveal-once state.

**S3 — API tokens screen.**
Two-up grid, intro copy, drop `h2 Tokens`, `human_datetime()` on Created/Last used, `—` in the
revoked action cell, restyled 409 flash-error, reworded per-field validation messages. Keep the
idempotency key, the empty state, and `max="365"`.
*Tests:* `tests/Integration/Api/AdminApiTokenTest.php` (409 replay, 422 draft-preservation),
`tests/browser/api-tokens.spec.ts`.

**S4 — Webhooks list screen.**
Two-up grid, intro copy corrected to six attempts, `<code>` event keys with em-dash descriptions,
drop `h2 Endpoints`, right-align `Last status`, `—` placeholder, `Manage` as a secondary button,
merge the `first_party_hooks` note into the intro. Adopt the `Endpoint …` flash strings for
save/delete.
*Tests:* `tests/Integration/Admin/AdminWebhookTest.php`, `tests/browser/webhooks.spec.ts`.

**S5 — Webhook detail screen.**
Back link **All endpoints**; single `h2` = endpoint name; merge Configuration + Actions into one card
in the design order (action row → fields → Save/Delete); **keep** the delete and rotate re-auth
(ADR 0021) and the delete warning prose; relabel `Delete webhook` → `Delete endpoint`; add event
descriptions to the detail fieldset; render the register card in the left column; demote
`Recent deliveries` to `h3`, 5 columns, `Response`, right-aligned Attempts/Response, `1 / 6`
spacing, `—` for empty errors, Replay folded into the Error cell, `.state` classes for
`delivered|dead|queued`.
*Tests:* `AdminWebhookTest` must keep asserting the 422 re-render on a wrong delete/rotate password
(`error_context` routing at `webhook_detail.php:64,66,74,76`); browser evidence for the merged card.

**S6 — Sign-in providers screen.**
Merge `Key` into `Provider`; rename/right-align `Sole-method`; right-align `Actions`; drop
`h2 Providers`; keep `data-sole-count`; compose the health string; pill the status; `.linkbtn` for
Test connection / Enable… / Disable…; cap the add-form at 560px; two-up Client ID/secret; adopt the
design's intro paragraph; keep Claim map + the add-form re-auth + `noindex` + the `enable_error_id`
routing; add a providers-table empty state; interpolate the display name into the create flash;
adopt the enable flash string.
*Tests:* `tests/Integration/Admin/AppAdminProvidersTest.php` (sole-count anchor, enable-error
routing, 422 draft preservation), `tests/browser/providers.spec.ts`.

**S7 — Provider disable drill-in.**
Back link **Sign-in providers**; single `h2 Before you disable ‹Name›`; rust plate for the sole
warning; `<ul>` + Monogram sole-account rows (keep the `/admin/users?q=` drill-in); `.btn-danger`
confirm + text-link Cancel; render under the shared `h1` + tab strip so it reads as the design's
in-tab drill-in.
*Tests:* `AppAdminProvidersTest` disable-confirm assertions; browser screenshot of both the
sole-accounts and no-sole states.

**Deferred / not in this screen**
- Shared `AdminNav` adoption (row 1) — belongs to the `admin-overview` slice; ADR 0023 §6's grouped
  nav must not be regressed on the way.
- `/admin/extensions` (row 84) — belongs to the `admin-packages` slice.
- Token expiry honesty (row 85) — separate follow-up; needs an `expired` state in `ApiTokenService`
  before any UI.
