# Phase 5 Inc 9 — Invitations (P5-13) browser evidence

**Captured:** 2026-07-08 by `tests/browser/invitations.spec.ts` (desktop + mobile
projects, one shared seeded `retroboards_e2e` DB, axe-checked). Reproduce with:

```bash
cd tests/browser && npm run evidence            # part of the standard set
# or just this spec:
bash prepare.sh && npx playwright test invitations.spec.ts
```

`features.invitations` is seeded true in `seed.php`; the spec flips
`registration_mode` to `invite` mid-journey **and restores `open`** (plus theme
safe mode in/out) so the shared evidence DB is left as the other specs expect.

## Scenes (`docs/evidence/browser/{desktop,mobile}/`)

| PNG | What it certifies |
|---|---|
| `69-admin-invitations-show-once.png` | Console after issuing: the raw `/invite/<64-hex>` link rendered exactly once in the green panel (direct render, never cookie flash); issue form (email/domain binding, max uses, expiry, board grant); nav entry behind the flag. Axe: no serious/critical violations. |
| `70-admin-invitations-list.png` | List with an Active and a Revoked row after a console revoke — and the earlier raw token nowhere in the page (show-once, hash-only at rest; asserted, not just eyeballed). |
| `71-register-invite-mode-blocked.png` | `registration_mode = invite`, logged out: `/register` shows "Registration is by invitation only." and the form is suppressed (no username input — asserted). |
| `72-register-invite-banner.png` | `/invite/<token>` landing redirected into `/register?invite=…`: invited banner, plain server-rendered form (no-JS path), "Accept invitation" submit. Axe: no serious/critical violations. |
| `73-invited-member-home.png` | Post-submit: signed-in landing with the welcome flash — redemption, session, and (in PHPUnit) board grant + `used_count` are one atomic unit. |
| `74-invite-invalid-uniform.png` | A bogus 64-hex token: the deliberately uniform "This invitation link is invalid or no longer active." banner (same message for missing/expired/revoked/exhausted — TM-IN-01), form still suppressed in invite mode. |

## Notes

- The first axe scan ever pointed at `/register` surfaced a pre-existing
  `link-in-text-block` violation (the auth-card "Log in" link relied on color
  alone, 1.39:1 against the muted text). Fixed in this increment by underlining
  `.auth-links a` (`public/assets/app.css`) — the fix also covers `/login`.
- Mobile assertion nuance: the shell username lives in the collapsed menu on
  mobile, so the signed-in proof asserts the welcome flash, not the shell.
- No-JS: every step in the journey is a plain server-rendered form/redirect;
  the spec drives them without relying on any PE JavaScript behavior.
