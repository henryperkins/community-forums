# Login and logout hardening — browser evidence

Status: complete for the harden pass on `harden/auth-login-logout` (ADR 0038).

Captured 2026-09-24 against the real PHP application and a freshly seeded browser
database (`retroboards_e2e_harden`), with `prepare.sh` seeding exactly as `npm run
evidence` does. Spec: `tests/browser/auth-hardening.spec.ts` (desktop 1280×800 and
mobile 390×844, Chromium). PHPUnit owns the server contract
(`tests/Integration/Core/AppAuthHardeningTest.php`, 12 tests). These captures cover
what the markup alone cannot prove.

## Before and after

The spec ran unchanged against `main` (a detached worktree with its own database and
port). All 16 cases failed there, each at the assertion that names its defect:

| Case | On `main` |
|---|---|
| Keyboard Log out from `/inbox` | Enter on the seat did not open the account menu: the queue's Enter shortcut took it |
| Enter on a focused link on `/inbox` | the skip link landed on `/inbox?t=29`, the cursor topic |
| Account menu dismissal | Escape left it open |
| Back after Log out | `/settings/account` carried no `Cache-Control` at all |
| Log out from a stale second tab | a 403, "That form has expired…" |
| A refused sign-in | focus stayed on the email field |
| A cancelled passkey prompt | the error line had no live-region role |
| Topbar Log in | `href="/login"`, no way back to the board |

On the branch all 16 pass.

## Captures

### `inbox-keyboard-logout.png`

Signed in as alice, who lands on `/inbox` with topics in her queue. Focus on the
seat, Enter opens the account menu, Tab to Log out, Enter. The capture is the result:
the forum index with "You have been signed out." and the guest topbar. The queue's
shortcut handler used to cancel Enter wherever focus was, so the same keys opened the
cursor topic and left her signed in. Space and a pointer worked; Enter did not.

### `login-refused-password-focus.png`

A wrong password, submitted with Enter. On the 422 re-render, focus is **on the
password**, the email is kept, and both inputs carry
`aria-describedby="login-error"`. The message still names no field (it guards against
account enumeration), so neither input is marked invalid. axe on the card: no
WCAG 2.0/2.1 A or AA violations.

### `passkey-cancelled.png`

A CDP virtual authenticator that holds no credential, so the ceremony fails the way a
cancelled OS prompt does (`NotAllowedError`). The line reads the product's copy,
"Passkey step was cancelled or unavailable — your other sign-in methods still work."
It used to print the browser's DOMException, which ends in a `w3.org` spec URL. The
button was double-clicked: one challenge request went out, and `aria-busy` was gone
after the failure. A retry clears the line first, so a repeated message is still a
change a screen reader hears.

### `stale-tab-logout.png`

Two tabs, both signed in. Tab A logs out, then tab B presses Log out. Tab B lands on
the index with "You are already signed out." instead of the 403 error card. The CSRF
check still fails there and the logout handler never runs. PHPUnit pins the other
half: a live session that presents a forged token is still refused with 403 and stays
signed in.

### `back-after-logout-http-cache.png`, `back-after-logout-bfcache.png`

Log out from `/settings/account`, then Back, once with Playwright's default launch
(back/forward cache off, so the HTTP cache path) and once in a separate Chromium with
`--disable-back-forward-cache` removed. Both land on
`/login?next=%2Fsettings%2Faccount`, and the page holds no trace of the member: no
account menu, no email. Before, Back served the member's settings page from the disk
cache (`fromDiskCache: true` over CDP), email and all.

### `account-menu-escape-focus.png`

After Escape the account menu is closed and focus is back on the seat, with its ring
drawn. The spec also closes the menu with a click outside it, and by Tab-ing past Log
out.

## Layout parity

The login page's passkey error line is now rendered empty from the start, because a
live region has to exist before its text arrives. Element boxes were measured on
`/login` and `/settings/security` at both widths, on the branch and on `main`, over one
database. The card, the passkey button, the links, and the settings passkey panel are
identical to the pixel. The empty line collapses into margins that were already there.

The settings lines are the exception, deliberately. Rendered empty they added 4px to
the passkey panel, so they stay `hidden` until a first failure shows them with their
text, and they keep `role="alert"`.

## Regressions run

Each group ran on its own fresh `prepare.sh`, and every failure was re-run on `main`:

| Group | Branch | `main` |
|---|---|---|
| `auth-hardening` (new) | 16 passed | 16 failed (above) |
| `member-surfaces` | 12 passed, 2 skipped | — |
| `forum-inbox-remediation` | 24 passed | — |
| `topic-row-consolidation` | 15 passed, 1 skipped | — |
| `account-console` | 18 passed, 12 skipped | — |
| `passkeys` + `totp` | 4 + 2 passed | `passkeys.spec.ts:102` fails at its sign-out step on `main` too |
| `unified-chrome` | 23 passed, 2 failed | the same 2 fail (`.forum-bar-count` strict-mode) |
| `field-error-a11y` | 4 passed, 2 failed | the same 2 fail (settings submit strict-mode) |
| `a11y` (dark surfaces seeded) | 27 passed, 1 failed | the same 1 fails (admin dark-surface axe) |

`passkeys.spec.ts` clicked Log out without opening the account menu, which has been
where Log out lives since ADR 0032. It had failed there on `main` ever since, before
it reached the passkey sign-in. The step now opens the menu, so the passkey
enrol → sign in → revoke path runs again, and it passes.

## Not covered

- **Real screen readers.** What is announced is inferred from the roles and
  attributes (`role="alert"` regions present before their text, `aria-describedby`).
  No NVDA, JAWS or VoiceOver run.
- **Firefox and WebKit.** Chromium only. Engines that keep `no-store` pages out of the
  back/forward cache simply re-fetch on Back, which is the safe direction.
- **`/users-online`.** It keeps its own `private, no-cache` (ADR 0031 §12), so it is
  the one signed-in page that Back may still show after Log out. See ADR 0038.
