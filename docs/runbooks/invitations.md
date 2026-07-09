# Runbook — Invitations / invite-only registration (P5-13)

## What the flag gates

`features.invitations` (default **OFF** — deploy-dark) gates:

- `/admin/invitations` — the operator console (issue / list / revoke).
- `/invite/{token}` — the public landing link (redirects into `/register`).
- Invite handling on `/register` (banner, hidden token field, redemption).
- The `invite` registration mode being *effective*: while the flag is dark,
  `registration_mode = invite` behaves as `closed`
  (`RegistrationPolicy::effectiveMode()` — fail closed), and the OAuth
  provisioning channel follows the same seam.

The 0053 tables (`invitations`, `invitation_redemptions`) and the 0076
`moderation_log` audit target exist regardless of the flag; rows are inert
while dark (pinned by `AppFeatureFlagTest`).

## Enable (staged)

1. `features` setting: `{"invitations": true}` (Admin → Features, or settings row).
2. Decide the mode on the dashboard (Trust & safety → Registration):
   - keep `open` to hand out invitations that carry a board grant while public
     signup stays available, or
   - switch to `invite` for invite-only registration (the dashboard warns if
     you set `invite` while the flag is still dark).
3. Issue the first invitation from **Admin → Invitations**: optional email or
   domain binding (one or the other), max uses (default 1), expiry days
   (default 14), optional board-membership grant. **Copy the link from the
   green panel immediately — it is shown exactly once and stored hash-only.**
4. Verify end-to-end: open the `/invite/<token>` link in a private window →
   register → confirm the member landed, the row's Uses column incremented,
   and (if set) board membership was granted.

## Invitation pause (decision #40 — independent disable path)

Set `features.invitations` to `false`. Effects, immediately and reversibly:

| Surface | While paused |
|---|---|
| `/admin/invitations`, `/invite/*` | 404 |
| Outstanding invitation links | Inert (register ignores tokens; nothing is consumed) |
| `registration_mode = open` | Plain public registration continues |
| `registration_mode = invite` | Registration is **fully closed** (fail closed) |
| `registration_mode = closed` | Unchanged (closed is closed) |
| Existing invited members | Unaffected (they are ordinary accounts) |

No data change is needed to pause or resume; rows and audit history persist.

## Revoke

- One invitation: Admin → Invitations → Revoke (audited, idempotent).
- Everything at once (incident response):
  `UPDATE invitations SET revoked_at = UTC_TIMESTAMP(), revoked_by = <admin id> WHERE revoked_at IS NULL;`
  then note the sweep in the moderation log (the console writes one audit row
  per console revoke; a bulk SQL sweep should be recorded in the incident notes).

## Token hygiene

- Raw tokens exist only in the one-time create response and the URLs you share.
  The DB stores sha256 only; audit rows carry constraints, never tokens
  (TM-IN-06 pins this).
- A leaked-link suspicion is handled by revoking that invitation — enumeration
  of other tokens is impractical (256-bit) and probing is rate-limited.

## Rate limits (config `rate_limits`)

| Policy | Default | Covers |
|---|---|---|
| `invite_create` | 30 / hour per admin | console issuance bursts (TM-IN-07) |
| `invite_redeem` | 30 / 15 min per client | invite-bearing `/register` GET/POST — the single verdict endpoint. `/invite/*` is a pure redirect and charges nothing, so a journey pays once per token evaluation (TM-IN-01) |
| `register` | 5 / hour per client | uninvited public `/register` POSTs only; invite-bearing POSTs use `invite_redeem` instead to avoid shared-NAT lockout |

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| "This invitation link is invalid or no longer active." | Deliberately uniform: unknown, malformed, expired, revoked, or exhausted. Check the console row's Status/Uses columns. |
| "This invitation is for a different email address." | Email-bound invitation; the redeemer must register with the bound address. |
| "…requires an email address at example.com" | Domain-bound; exact match only — subdomains do not match. |
| Invite link 404s | Flag is dark (see pause table above). |
| "Registration is by invitation only." | Mode is `invite` and the POST carried no valid token. |
| Mode set to `invite` but nobody can register | Flag is dark → effective `closed` (the dashboard warns about this state). |
| 429 on issuance or redemption | `invite_create` / `invite_redeem` policies above. |

## Monitoring & audit

- `moderation_log` actions: `invitation_created`, `invitation_revoked`,
  `invitation_redeemed` (target_type `invitation` since 0076).
- `invitation_redemptions` rows tie each account to its invitation (packed IP,
  UTC timestamp). `worker:purge-ips` anonymises `invitation_redemptions.ip`
  once it is older than the retention window — same policy and window as
  `sessions.ip` / `posts.ip` (ADMIN §5.5), audited with the system actor.
- Budget: `invitation.redemption_p95` = 461.79 ms (target 500 ms; 60 samples
  with a production-cost Argon2id hash inside the timed region) —
  `docs/evidence/phase5/performance-budgets.md`, re-measured by
  `php bin/console verify:phase5-budgets`.

## Acceptance evidence

- PHPUnit: `tests/Integration/Service/InvitationServiceTest.php`,
  `tests/Integration/Core/AppInvitationsTest.php`,
  `tests/Integration/Core/AppFeatureFlagTest.php` (dark pin),
  `tests/Integration/Service/InvitationRedemptionBudgetTest.php`.
- Threat fixtures TM-IN-01..07: `docs/phase5/threat-models/fixtures.json`.
- Browser evidence: `tests/browser/invitations.spec.ts` →
  `docs/evidence/phase5/invitations.md`.
- Policy decisions: `docs/phase5/invitation-defaults.md`.
