# Phase 5 — Invitation defaults and registration-mode policy (P5-13)

**Status:** Owner-accepted 2026-07-08 (Henry Perkins) — the PHASE_5_PLAN Milestone-0
"invitation defaults" decision, recorded at implementation time (Inc 9).
**Companions:** `docs/phase5/threat-models/invitation-privilege.md` (TM-IN-01..07),
`docs/runbooks/invitations.md`, decision #36 (invitations are onboarding evidence,
not authority), decision #40 (independent disable path).

## Registration modes (explicit, not overloaded)

`registration_mode` gains an explicit third value instead of redefining `closed`:

| Mode | Behavior |
|---|---|
| `open` | Normal public registration. A *present* valid invitation is honored (binding checks, use consumption, board grant); a *present invalid* one errors with the uniform message — never silently degraded to a plain signup, because the redeemer expected a grant they would not receive. An absent invitation is a plain signup. |
| `invite` | Account creation requires a currently-valid invitation AND `features.invitations = true`. |
| `closed` | No new account creation, ever — a valid invitation does not reopen it. |

Rationale for not redefining `closed` as "closed except valid invitations": existing
code and tests treat `closed` as an absolute operator guarantee (including the OAuth
provisioning channel), and making it conditional on a feature flag plus token validity
would make rollback/pause semantics murky.

- **Fail closed while dark:** `registration_mode = invite` with `features.invitations`
  off behaves as `closed` (`RegistrationPolicy::effectiveMode()`). A paused invitation
  subsystem must not silently reopen public registration, and a configured invite-only
  site must not admit uninvited members. Flipping the flag off is therefore also the
  **invitation pause** switch (decision #40).
- **One seam:** both the password form (`AuthController`) and OAuth provisioning
  (`OAuthService`, action `registration_invite_only`) read `RegistrationPolicy`, so the
  two account-creation channels cannot disagree. Returning OAuth logins and signed-in
  linking are unaffected (neither creates an account).

## Issuance defaults

- **Admin-only issuance** (TM-IN-07). Member-created invitations are a future,
  separately-gated policy decision; nothing in Inc 9 grants members an issuance path.
- Issuance is rate-limited per admin account (`invite_create`, default 30/hour) and
  audited (`moderation_log` action `invitation_created`, target `invitation`).
- **Binding is email XOR domain** (spec §9 "bound to an email or approved domain").
  Domain match is exact and case-insensitive — subdomains do NOT match.
- **Expiry is mandatory:** default 14 days, bounds 1–365 (spec: invitations are
  "expiring" — a non-expiring invitation cannot be issued).
- `max_uses` default 1, bounds 1–100.
- Tokens are `bin2hex(random_bytes(32))` (64 lowercase hex chars, 256-bit), stored
  **hash-only** (sha256), shown exactly once at creation by direct render (never via
  the cookie-backed flash), and never written to logs or audit rows (TM-IN-06).

## Redemption

- Atomic with account creation in one transaction, ordered *uniform validity check →
  binding check → guarded `used_count` consume → register → redemption row → board
  grant → audit* (TM-IN-02: a concurrent loser exits before creating anything; a
  registration validation failure rolls the consumed use back).
- Enumeration responses are uniform: missing, malformed, expired, revoked, and
  exhausted tokens are indistinguishable (TM-IN-01), and probing is rate-limited
  (`invite_redeem`, default 30/15min per client).
- Invitation cannot bypass email verification, account-state enforcement, or
  anti-abuse (decision #36): invited members still receive the verification email and
  are ordinary `user`-role accounts.

## No role grants through invitations (deferred capability, not a gap)

`invitations.onboarding_role_id` (0053) is **neither issued by the console nor applied
at redemption** in Inc 9. PHASE_5_PLAN §9 allows a *non-privileged onboarding role
"only under an approved policy"* — no such policy has been approved, so the safe cut
is: ordinary membership plus at most the stored `onboarding_board_id` board-membership
grant. Forged POST fields and DB-planted `onboarding_role_id` values are both pinned
by tests to yield ordinary membership only (TM-IN-05). Revisit only with an explicit
owner-approved onboarding-role policy; the schema affordance stays inert until then.
