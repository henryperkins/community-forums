# ADR 0038: Login and logout hardening — what a signed-in page leaves behind

**Date:** 2026-09-24
**Status:** Implemented on `harden/auth-login-logout`; verification below and in
`docs/evidence/auth-login-logout-hardening/notes.md`.
**Relates to:** the 2026-09-24 `/impeccable audit` of the login and logout screens;
ADR 0031 §12 (presence caching); ADR 0032 (the unified member chrome, where Log out
lives); PR #77 (one local-path rule for `next`); ADR 0024 obligation 4; AGENTS.md's
CSRF invariant; PRODUCT_DESIGN §13.

## Context

The audit reproduced, in Chromium:

- On `/inbox`, where every sign-in lands, Enter on a focused link or button was
  cancelled and the cursor topic opened instead. That included Log out, the account
  menu, the rail and the skip link.
- After Log out, Back re-showed the member's last page, `/settings/account` with its
  email, from the disk cache. No HTML response carried a `Cache-Control`, and neither
  `worker/index.js` nor `deploy/apache-vhost.conf` adds one.
- Log out pressed in a second tab, after the first had signed out, rendered a 403.
- A cancelled passkey prompt printed the browser's own DOMException, spec URL
  included. Nothing announced it, and nothing stopped a second press.
- A refused sign-in's message was tied to neither field, and focus stayed on the
  email.
- The topbar's Log in dropped the guest's place.

## Decisions

### 1. A signed-in response is not stored by the browser

`App::handleRequest` sets `Cache-Control: private, no-store` on every HTML page
rendered for a signed-in session that has not set its own policy.

It is HTML only. History never re-shows a JSON answer. A first version covered
every response, and the polish pass's browser regressions caught its cost: the
composer discards a server draft with a `fetch` whose body it never reads. Under
`no-store`, Chromium keeps that request open (`Network.loadingFinished` never
fires), so Playwright's `networkidle` never arrived and four `composer-expansion`
cases timed out. A cacheable response is drained by the HTTP cache, so JSON keeps
`main`'s behaviour. `AppAuthHardeningTest` pins both sides.

It is `no-store`, not `no-cache`, because Back and Forward may reuse a stored response
without revalidating it, and only `no-store` keeps the response out of storage. The
old cost was the back/forward cache. Since Chrome's March–April 2025 rollout, Chrome
keeps `no-store` pages in that cache for three minutes and evicts them when an
HttpOnly cookie changes. Log out changes `rb_session`. So in-session Back stays
instant in Chrome, and Log out still ends it. Engines without that behaviour re-fetch
on Back, which costs one request.

A route that sets its own `Cache-Control` keeps it: `/users-online` (`private,
no-cache`, ADR 0031 §12), media, theme builds and `/brand.css`.

**Open for ADR 0031's owner.** §12 chose `no-cache` because "no-store also kills the
bfcache". That no longer holds in Chrome, and a `no-cache` page is not evicted when a
cookie changes. So `/users-online` is the one signed-in page that Back can still
re-show after Log out. It is not changed here, because §12 is a recorded decision.

### 2. Log out from a stale tab confirms instead of failing

In the kernel's CSRF-failure branch, a `POST /logout` that arrives with no session
left redirects to `/` with "You are already signed out." It no longer renders the 403
card. This is **not** an exemption: the token still fails, the logout handler never
runs, and a live session with a bad token still gets the 403. What the request asked
for has already happened, so there is nothing left to protect.

### 3. The queue's shortcuts leave focused controls alone

Enter now belongs to the focused control (`activationTarget` in `app.js`), and no
shortcut fires from inside an open menu or dialog. A row's own topic link still opens
in the reading pane, because its native click reaches the list's click handler.

**Still open:** the single-key shortcuts (j, k, o, e, s, #) have no off switch, which
WCAG 2.1.4 asks for. That needs a member preference, so it is a separate change.

### 4. The account menu dismisses like the DM menus

It still works as a native `<details>` without JavaScript. With it, Escape closes the
menu and returns focus to the seat, a click outside closes it, and so does Tab-ing
past its last item.

### 5. Passkey errors are the product's words, announced, one ceremony at a time

- Only copy we wrote reaches the page (`productError`). A DOMException's message
  never does. A WebAuthnException answer carries a `code` and operator-facing text,
  so it falls back to the flow's own copy.
- The error lines are `role="alert"`. The login line is rendered empty from the
  start, because a live region must exist before its text arrives. The settings lines
  stay `hidden` until their first failure, because rendered empty they added 4px to
  the passkey panel.
- A control is `aria-busy` while its ceremony runs and ignores a second press. A
  restore from the back/forward cache releases it.
- `CANCELLED` now uses an em dash, like the rest of the product.

### 6. A refused sign-in is tied to its fields

The message names no field, which guards against account enumeration, so neither
input is marked `aria-invalid`. Both carry `aria-describedby` for the message, and
focus moves to the password. The email is kept.

### 7. The topbar's Log in brings a guest back

`App::loginReturnPath` gives a guest's GET page a `/login?next=<path>` link,
re-encoded as a URL path because `Request::path()` is decoded. `/` keeps the default
landing (the inbox), and the sign-in pages have nothing to return to. The login
controller's rule still decides which `next` values are honoured; PR #77 tightens
that rule, and these paths already satisfy it.

## Not in this pass

- **The two-factor step's URL.** The step is rendered as the response to
  `POST /login`, so reloading it asks to resend the password. Moving it to a GET step
  behind a short-lived cookie rewrites the handlers that PR #77 changes. Do it after
  #77 merges.
- **The audit's contrast, token and layout findings:** the twilight auth-link colour,
  the engraved field frames, the stage focus ring, and the passkey button's spacing.
  They belong to polish and layout passes. This pass changed no CSS.

## Verification

- **PHPUnit.** `AppAuthHardeningTest` has 12 tests: six fail on `main`, and all pass
  here. The other six guard behaviour that must not change, JSON caching among
  them. The full suite ran sharded four ways. On the final branch, with the polish
  pass's 6 contract tests, it has 3,061 tests and 3 failures. Two already fail on
  `main` (`ThreatModelIndexTest` and `AppAdminEmailTest`, both fixed by PR #76). The
  third is `ImladrisRuntimeAssetTest`, for the surface digest this change moves.
  Under ADR 0024 obligation 4, the merger refreshes
  `config/imladris-runtime-baseline.json` on `main`.
- **Browser.** `tests/browser/auth-hardening.spec.ts` fails all 16 cases on `main` and
  passes all 16 here. The regression groups and their `main` comparisons are in the
  evidence notes.
- **Assets.** `npm run build` and `npm run check:assets` report current. Only
  `app.js` and `passkeys.js` changed, and no CSS.
