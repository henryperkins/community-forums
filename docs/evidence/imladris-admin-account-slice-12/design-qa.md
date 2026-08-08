# Slice 12 admin integrations design QA

Status: complete for the Slice 12 Tokens, webhooks & sign-in boundary.

References:

- `docs/design-system/imladris/templates/admin-integrations/AdminIntegrations.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-integrations.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-integrations.md`
- `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` §1.1 (`C-05`, `C-09`,
  `C-11`, `C-13`, `C-14`, `C-25`, and the new `C-47`), §1.2 (`FA-11`, `FA-12`), §1.3
  (`FC-15`–`FC-18`)

Captured 2026-08-07 against the real PHP application and a freshly seeded browser database
(`retroboards_e2e_imladris_slice12`), with `prepare.sh` re-seeding between spec groups exactly as
`npm run evidence` does.

## Surfaces

Five production templates under the one Integrations area heading and the three-tab strip
(`API tokens` · `Webhooks` · `Sign-in providers`):

| Template | Route | Tab lit |
|---|---|---|
| `api_tokens.php` | `/admin/api-tokens` | API tokens |
| `webhooks.php` | `/admin/webhooks` | Webhooks |
| `webhook_detail.php` | `/admin/webhooks/{id}` | Webhooks |
| `providers.php` | `/admin/providers` | Sign-in providers |
| `provider_disable.php` | `/admin/providers/{id}/disable` | Sign-in providers |

## Reviewed against the references

- **Chrome.** All five surfaces render the one area heading `Tokens, webhooks & sign-in` and the one
  `Integration sections` tab strip. Drill-ins keep their parent tab lit. Document titles name the tab
  (or the action on a confirm), matching every other adopted console page.
- **API tokens.** Two-up layout: create card (name, scope fieldset with `code` key + em-dash
  description over the real catalogue, optional expiry, always-visible re-auth) beside the unheaded
  table card. The shipped scroll region (`role="region"` / `tabindex="0"` / `aria-label="API tokens"`)
  survives (`C-05`). Minted credentials render in the gold `.flash-secret` plate on the POST
  response, never through the cookie-backed Flash (`FA-12`). The 409 idempotency replay is the error
  plate with the list still behind it. Revoke keeps the differentiated `aria-label` (`FA-11`).
- **Webhooks list.** Register card + unheaded endpoint table. Intro says *six* attempts
  (`webhooks.max_attempts = 6`), not the design's three (`FC-16`). Manage stays an `<a href>` wearing
  the design's outline button (`C-11`). Last status is right-aligned mono.
- **Webhook detail.** One card carrying the endpoint name as `h2.admin-record-title`, the config
  fields, the action row, and Recent deliveries. Save reaches `#webhook-config` via `form=` so Delete
  keeps its own re-auth form — HTML forms cannot nest, and a bare `formaction` would drop the
  password (`FC-17`). Rotate and Delete re-auth render inline and always (`C-14`). Delivery is
  asynchronous; pause/resume/replay flashes keep the worker clause the design's copy implies away.
  Register-on-detail is not adopted (`FC-18`) — create still lands on the list on both success and
  422.
- **Providers.** Unheaded roster with Key merged into the Provider cell, Sole-method right-aligned,
  and the test-anchored `data-sole-count` surviving. Health is the real four-value DB enum composed
  into one middot-joined string — production never emits `never checked` (`FC-15`). Add-provider is
  its own card with always-visible re-auth. Enable errors stay row-scoped.
- **Disable drill-in.** Single heading `Before you disable {name}`, rust impact plate, sole-method
  listing, text-link Cancel back to the roster, danger confirm. Disable stays a link to a real
  confirm route (PE + TM-ID-09 clause 2).
- **Service-secret gate copy is surface-true (`C-25`).** Rotate attaches
  *"Enable the service-secret store before rotating a signing secret."* to the password field; the
  register form keeps *"…before creating webhooks."*
- **Flash / secret entry animation (`C-47`).** The design's `aiRise` fades opacity 0→1 over 200 ms,
  which alpha-composites an otherwise AA-safe gold label below 4.5:1 during the entry (measured
  4.06:1 desktop / 3.33:1 mobile in flight). Production keeps the 200 ms, 6px rise and omits opacity
  so every frame uses the full semantic pair. Scoped to `.admin-console .flash`.
- **No inline script/style/handlers** (`C-09`). Anti-draft-loss 422 paths keep `->errors` + `->old`
  (`C-13`). Every mutating control is a `<form method="post">` carrying `csrfField()`; navigation is
  `<a href>` (`C-11`).

## Deviations recorded by this slice

- **`C-47` — status-banner opacity fade.** New constraint row; application keyframe in `app.css`.
- **`C-25` — rotate gate copy.** Surface-correct message on the rotate path.
- **`FC-15`–`FC-18`** — health vocabulary, async delivery, Save/Delete form pairing, no
  register-on-detail. Production behaviour wins; design register (endpoint language, unheaded cards,
  gold secret plate) is adopted where it does not invert a PE/CSRF/authz contract.
- **Flash microcopy** names the thing it closed (`"{name}" was revoked…`, `Endpoint deleted — …`,
  `{name} was added, disabled.`, `{name} is enabled and now offered on the sign-in page.`) so a
  multi-row console never claims a generic outcome.

## New spec

`tests/browser/integrations-console.spec.ts` — the per-area harness this migration already uses for
content, members and account. It owns copied body metrics and register parity for all five surfaces:
axe in light, twilight and `data-theme="system"` under a dark OS; light and twilight captures at
1280px and 390px; a `javaScriptEnabled: false` walk of every route (including the `form=` Save on
webhook detail); document-overflow at each width; and the 409 conflict capture on tokens. Behavioural
contracts (mint-once, rotate/delete re-auth, enable-error routing, sole-method listing, live delivery)
remain in `api-tokens`, `webhooks`, `providers`, `admin-remediation` and `gate-a`.

Its axe helper pins the sticky console bar to `position: static` for the duration of the scan — the
same compositor caveat as `members-console` / `admin-dashboard`.

## Known branch state, carried not hidden

- `bin/build-imladris-assets.php --check` reports the `application_surface.sha256` drift, so
  `ImladrisRuntimeAssetTest` is red on this branch **by design**: the runtime baseline is refreshed
  exactly once per merge, on `main`, by the merger, as the immediately-following commit (ADR 0024
  obligation 4, ledger §6 rule 5). No slice branch may carry that file. No generated asset, mirror
  document or baseline file is touched by this commit.
- Two `admin-remediation` board-composer tests remain pre-existing exclusions owned by Slice 19
  closeout (see slice-11 `design-qa.md`).

## Verification

**Browser.** Three groups against a freshly seeded `retroboards_e2e_imladris_slice12` database, all
at both the 1280px and 390px projects:

| Group | Specs | Result |
|---|---|---|
| 1 | `integrations-console.spec.ts` | **12 passed**, 0 failed (47.9s) |
| 2 | `api-tokens.spec.ts`, `providers.spec.ts`, `webhooks.spec.ts` | **8 passed**, 0 failed (41.9s) |
| 3 | `admin-remediation` webhook flow + `gate-a` webhook/token/provider flows | **5 passed**, 1 expected mobile skip, 0 failed |

25 passed, 1 skipped, **zero failures**. Group 1 carries the axe passes: light, twilight and
`data-theme="system"` under `prefers-color-scheme: dark`, on all five surfaces at both widths, plus
document-overflow and the JavaScript-disabled walk.

**Backend.** Full suite on private `retroboards_test_s12`: **2,558 tests / 18,577 assertions /
2 skipped / 1 failure**, and that one failure is the application-surface digest described above.
Focused integrations files (`AdminWebhookTest`, `AdminApiTokenTest`, `AppAdminProvidersTest`,
`ApiTokenServiceTest`, `WebhookServiceTest`) pass **74 tests / 273 assertions** on their own.

**Static gates.** The CSP template scan (`rg -n "<script|<style| on[a-z]+=" templates/ -S`) returns
only `layout.php`'s permitted external `src` tags on the five touched templates. `php -l` passes on
every touched template. No generated asset, mirror document or baseline file is modified by this
commit.

## Captures

Every surface is captured in both registers at both widths. `desktop/` and `mobile/` hold the light
register plus the JavaScript-disabled walk and the tokens 409 conflict; `twilight/{desktop,mobile}/`
hold the same five surfaces in the twilight register. Side-by-side light/twilight pairs live under
`comparisons/` when the host can compose them.

- `integrations-tokens` · `integrations-webhooks` · `integrations-webhook-detail` ·
  `integrations-providers` · `integrations-provider-disable` — the register set, from
  `integrations-console.spec.ts`.
- `integrations-*-no-js` — tokens, webhooks list, webhook detail and providers with
  `javaScriptEnabled: false`.
- `integrations-tokens-conflict` — the 409 idempotency replay banner.
- Gate A / behavioural set under `docs/evidence/browser/{desktop,mobile}/`:
  `api-token-minted`, `api-token-revoked`, `20-admin-api-token-*`, `webhook-0{1-4}-*`,
  `22-admin-webhook-*`, `23-admin-webhook-delivery-log`, `66-admin-providers-console`,
  `67-login-generic-provider-button`, `68-provider-disable-confirm`,
  `remediation-webhook-flashes`, `remediation-webhook-delete-reauth`.

This evidence certifies only the Tokens, webhooks & sign-in bodies. Shared admin chrome was
certified by Slice 2; later admin and account areas remain separate slice work.
