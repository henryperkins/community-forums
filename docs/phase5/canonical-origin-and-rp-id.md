# A5 — Canonical origin & WebAuthn RP ID (Phase 5 entry-gate artifact)

**Date:** 2026-06-30
**Status:** Recorded as the A5 entry-gate artifact required by `PHASE_5_PLAN.md`
§2/§7 (and §5 #28); operationalizes ADR 0004 **D6**. **Pending product-owner
sign-off** on the self-host framing (§6).
**Precedence:** subordinate to `DECISIONS.md` → `DESIGN.md` → ADR 0004 (**D6**).

> **Self-hostable framing.** RetroBoards runs on a single VPS per operator, so
> there is no one global production domain to pin. This artifact records the
> **derivation rule, the origin-validation requirement, and the domain-change/DR
> runbook** — each operator sets their own canonical origin via `APP_URL`. That
> is exactly what D6 asks for: a rule + a runbook, not a branding toggle.

---

## 1. The rule (from D6)

- The **single canonical HTTPS origin** of a deployment is its **`APP_URL`**
  (scheme + host + optional port), read from config at
  `config/config.php:16` → `Env::get('APP_URL', 'http://localhost:8000')`
  (`.env` key `APP_URL`).
- The **WebAuthn RP ID** is the **registrable domain** (eTLD+1) of that
  `APP_URL` host.
- Passkey ceremonies validate the authenticator's reported **origin for exact
  equality** with the `APP_URL` origin (no wildcards, no multi-origin lists), and
  the **RP ID** must be a registrable suffix of that origin's host.
- Changing the canonical origin / RP ID is a **runbook migration** (§5), not a
  cosmetic setting.

## 2. Derivation examples

| `APP_URL` | Canonical origin (exact-match) | RP ID (registrable domain) |
|---|---|---|
| `https://forum.example.com` | `https://forum.example.com` | `example.com` |
| `https://community.example.co.uk` | `https://community.example.co.uk` | `example.co.uk` |
| `https://example.org` | `https://example.org` | `example.org` |
| `http://localhost:8000` *(dev only)* | `http://localhost:8000` | `localhost` |

Using the **registrable domain** as RP ID (per D6) lets the operator move the
service between subdomains of the same registrable domain (e.g. `forum.` →
`community.`) **without** invalidating existing passkeys. Moving to a *different*
registrable domain is the breaking case (§5).

## 3. Production requirements

- **HTTPS is mandatory in production** (`APP_ENV=production`). WebAuthn requires a
  secure context; an `http://` non-localhost `APP_URL` in production is a
  misconfiguration and passkey enrollment/sign-in must refuse rather than bind to
  an insecure origin.
- **`localhost` dev exception:** browsers treat `http://localhost` as a secure
  context, so passkeys work over plain HTTP in local dev with RP ID `localhost`.
  This path is for development only.
- **No wildcard / multi-origin.** Exactly one origin per deployment; reverse
  proxies must present the canonical host. `X-Forwarded-*` is honored only via
  `ClientIdentifier` when `TRUSTED_PROXIES` is set (CLAUDE.md), and must not be
  allowed to spoof the WebAuthn origin — origin validation uses the configured
  `APP_URL`, never a request-supplied host header.

## 4. Where this is consumed

- **WebAuthn (P5-11, Increment 7):** `src/Security/WebAuthn/*` derives RP ID +
  expected origin from `APP_URL` for register/login/step-up challenge creation and
  response verification (`webauthn_challenges`, `webauthn_credentials` in `0051`;
  RP ID/origins are deliberately *not* stored in the DB per the `0051` header —
  they come from config so a domain move is a single config change + this runbook).
- **OAuth callbacks** already derive their redirect origin from `APP_URL`; the
  signed `state` cookie (the one CSRF-exempt path, `App.php:260`) is scoped to the
  canonical host. A domain change affects OAuth redirect URIs too (§5 step 5).
- **Absolute URLs** (emails, sitemap, canonical links) use `APP_URL`; consistency
  across all of these is part of "single approved origin".

## 5. Domain-change / DR runbook

Changing the **registrable domain** (and therefore the RP ID) invalidates every
existing passkey, because credentials are cryptographically bound to the old RP
ID. Treat as a planned migration:

1. **Pre-announce** to members: passkeys will need re-enrollment after the move;
   confirm everyone has a working fallback (password and/or TOTP/recovery codes —
   the `0054` path). Passkeys *augment*, never replace, password/OAuth (D7), so no
   one is locked out by design.
2. **Freeze** passkey enrollment shortly before cutover (avoid binding new
   credentials to the soon-dead RP ID).
3. **Cut over `APP_URL`** to the new origin; update TLS, reverse-proxy host, and
   any `TRUSTED_PROXIES`.
4. **Re-enroll:** existing passkeys fail validation under the new RP ID; users
   sign in via fallback and re-enroll passkeys. Optionally bulk-revoke stale
   `webauthn_credentials` (they are inert under the new RP ID regardless).
5. **Update OAuth provider redirect URIs** and any provider-registry callback
   origins (P5-12) to the new origin; re-verify each provider in test mode.
6. **Re-issue absolute-URL artifacts** (re-send/verify email links, sitemap,
   canonical tags) and confirm the safe-mode/health paths answer on the new host.

**Subdomain-only move** (same registrable domain, RP ID unchanged): passkeys keep
working; still do steps 3, 5, 6.

**Lost-domain DR:** if the registrable domain itself is lost, RP ID changes
unavoidably → same as steps 1–6 on the recovery domain; owner/admin recovery
follows the `protected_owners` break-glass path (A1 §4.5) if owner sign-in is
affected.

## 6. Product-owner sign-off (approved 2026-06-30)

- [x] **Framing** — recorded as the *rule + runbook* (each operator sets `APP_URL`);
      **no single canonical production domain is pinned in-spec**.
- [x] **Production-HTTPS enforcement** — passkey flows **hard-refuse** a non-secure
      non-localhost production origin (not a warning).

Increment 7 (passkeys) derives RP ID + expected origin from `APP_URL` exactly as
above, and the domain-change runbook becomes part of `docs/PHASE_5_RUNBOOK.md`
(Increment 10).
