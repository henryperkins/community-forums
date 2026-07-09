# Runbook — Slash Menu & GIPHY Picker (`slash_giphy`)

Release/operations runbook for the **slash_giphy** feature (a
progressive-enhancement "slash command" menu in the enhanced composer for
approved Markdown insert snippets — table, task list, poll, custom emoji — plus a
strictly **client-side** GIPHY picker that inserts direct GIPHY media URLs).
**Default-ON as of 2026-07-02** (the `slash_giphy` flag graduated out of
deploy-dark) but **inert until an operator configures `giphy_public_key`**: with
no key the picker config endpoint 404s and the composer never upgrades, so the
graduation ships zero behaviour change until an operator opts in. Follows the
same conventions as `docs/runbooks/operations.md` §2 and mirrors
`docs/runbooks/polls.md` / `docs/runbooks/server_drafts.md`.

> **Golden rule:** for any provider/privacy/logic defect (bad picker behaviour,
> rating leakage, CSP concern, GIPHY outage noise), **disable the `slash_giphy`
> flag first** (the `/composer/giphy-config` endpoint 404s, the composer's slash
> menu stops rendering, and the GIPHY CSP sources are dropped on the very next
> request; the rest of the app keeps serving), then investigate. Disabling is
> non-destructive — no stored rows are involved; already-inserted media are plain
> Markdown image links and are unaffected.

## What the flag gates

`slash_giphy` gates the **entire** enhanced-composer slash menu, not just GIPHY.
The menu (approved inserts *and* the GIPHY picker) only renders when
`GET /composer/giphy-config` returns `{ ok: true, enabled: true }`, which
requires **both** the flag on **and** a non-empty `giphy_public_key`. There is no
schema, no migration, and no server-stored state — this is a config endpoint plus
progressive-enhancement JavaScript.

Route (public GET; no CSRF because it is read-only; gated **in-controller** via
`SlashGiphyController::pickerConfig`, which 404s when the flag is off, and returns
`{ ok:false, enabled:false }` 404 when the flag is on but no key is set):

- `GET /composer/giphy-config` — returns the client picker config:
  `provider: "giphy"`, `public_key`, `rating` (`g` | `pg` | `pg-13`, clamped to
  `pg` if misconfigured), `attribution: "Powered by GIPHY"`,
  `direct_media_only: true`, `server_proxy: false`, and the `allowed_inserts`
  allowlist (`table`, `task_list`, `poll`, `custom_emoji`, `giphy`).

The enhanced composer (`public/assets/composer.js`, `wireSlashMenu`) is pure
progressive enhancement: typing `/` at a word boundary opens an APG-pattern
combobox (the textarea becomes `role=combobox`; the popup is a `role=listbox` of
`role=option` items with `aria-activedescendant` selection). GIPHY search/trending
requests go **directly from the browser to `api.giphy.com`** using the public key;
the server never proxies, fetches, or caches media. With JavaScript disabled the
composer is an ordinary Markdown textarea — nothing on the write path depends on
the slash menu or the config endpoint.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/runbooks/operations.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any defect and is non-destructive (no stored state):

```bash
# Roll back: take the slash menu + GIPHY picker offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["slash_giphy"]=false; $r->set("features",$f);'
```

Re-enable by setting `slash_giphy` back to `true` or removing the key (the default
is now `true`). **A second, independent kill switch is the provider key**: clear
`giphy_public_key` and the menu goes inert (config 404s, CSP sources drop) even
with the flag on — useful if you must revoke GIPHY access without touching flags.

## Operating semantics (what to tell operators)

- **Key custody** — `giphy_public_key` is a **public/browser** GIPHY API key
  (shipped to every client in the config JSON by design); it is not a secret and
  must not be a server/production key. Set it in the `settings` store (or
  `giphy.public_key` in config as a fallback). Rotate/revoke it in the GIPHY
  developer dashboard; clearing it here disables the picker immediately.
- **Content rating** — `giphy_rating` caps results at `g`, `pg`, or `pg-13`
  (default/clamp `pg`). This is passed to GIPHY on the client request; enforcement
  is on GIPHY's side, so pick conservatively for your community.
- **Attribution** — the picker surfaces the required "Powered by GIPHY"
  attribution; do not remove it (GIPHY API terms).
- **Data-flow disclosure** — because search runs **client → GIPHY directly**, a
  member's search terms and IP are visible to GIPHY (a third party) whenever they
  open the picker. There is **no server proxy and no caching** (`server_proxy:
  false`, `direct_media_only: true`). Disclose this in your privacy policy before
  enabling. Inserted results are stored only as ordinary Markdown image links to
  GIPHY-hosted media.
- **CSP relaxation** — the GIPHY sources (`connect-src https://api.giphy.com`,
  `img-src https://*.giphy.com`) are added to the strict CSP **only** when the
  flag is on **and** a key is set (`App::allowGiphyCsp`). No inline script/style
  is introduced; the strict `script-src 'self'; style-src 'self'` posture is
  preserved.
- **No notifications, no hooks/webhooks, no rate limit** — opening the menu or
  inserting media notifies no one and emits no domain hook/webhook. There is no
  dedicated `RateLimitService` policy (the request goes to GIPHY, not this app).

## Monitoring & known limits

- **No server-side footprint.** The app neither fetches nor logs GIPHY traffic,
  so there is nothing to monitor on the server for the media path itself; watch
  GIPHY's own dashboard for key usage/quota.
- **Provider outage is client-only.** If GIPHY is down or the key is rate-limited,
  the picker shows a transient status row and the rest of the composer (including
  the non-GIPHY inserts) keeps working; disable the flag only if the noise is
  disruptive.
- **No repair path / no backup concern.** There are no tables, counters, or
  denormalized state; `RepairService` has nothing to reconcile and backups are
  unaffected.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppCustomEmojiGiphyTest.php` —
  `test_giphy_config_is_public_key_only_when_slash_giphy_enabled` (public-key-only
  config payload, rating, attribution, `server_proxy:false`),
  `test_giphy_csp_sources_are_added_only_when_slash_giphy_is_configured` (CSP
  relaxed only when flag + key are set), and
  `test_slash_giphy_is_default_on_and_operator_rollback_regates_route_and_csp`
  (default-on with a key, plus operator rollback: disabling the flag re-gates the
  route to 404 and drops the CSP sources with the key still configured);
  `tests/Integration/Core/AppPhase4CarryoverFoundationTest.php` asserts
  `slash_giphy` is declared **default-on** after graduation.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/26-slash-menu.png`
  (the slash listbox with the first option highlighted) +
  `27-giphy-inserted.png` (a direct GIPHY media insert), driven by the phase-4
  slash journey in `tests/browser/gate-a.spec.ts` (now asserting the ARIA
  combobox roles, arrow-key selection, Enter-to-insert, and Escape-to-close).
- **Accessibility:** `tests/browser/a11y.spec.ts` —
  `phase 4 slash combobox has no serious axe violations and is keyboard operable`
  (axe scan scoped to `.composer-slash-menu`, plus the default-active option,
  `aria-activedescendant` arrow navigation, and Escape-to-close contract),
  desktop + mobile, no serious/critical violations.
