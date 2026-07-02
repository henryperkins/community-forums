# Runbook: `package_registry` (Phase 5 Increment 2, Deploy-Dark)

**State:** default off. Enabling provides staff-only read-only catalogue browse,
the registry trust console, and the refresh worker. Install does not exist until
Inc 3.

## Enable / Disable / Rollback

- Enable (staged section 13.1 step 3): set `features.package_registry=true` in
  the settings `features` JSON. Surfaces: `/admin/packages`, `/admin/registries`;
  worker `worker:registry-refresh` starts acting.
- Rollback: set the flag false. Routes return 404, the worker no-ops, rows stay
  inert. A registry rollback never rewrites recorded digests (section 13.2).
- Independent of the flag: `package_registries.is_enabled=0` disables one source
  without a password. The local blocklist always works.

## Cron

```cron
*/30 * * * * php bin/console worker:registry-refresh
```

The freshness window is 24h (D2), so twice-hourly refresh gives headroom. The
worker is idempotent: unchanged snapshots are no-ops.

## Outage / Staleness

A stale snapshot (`Stale snapshot` banner on `/admin/packages`) blocks nothing in
Inc 2 except freshness checks; cached signed metadata stays browsable from
`registry_snapshots`. From Inc 3 onward, stale means no new install decisions.

Diagnose by running the worker manually, checking telemetry `registry.refresh`
events, and reading EgressGuard messages.

## Key Ceremonies

Custody, rotation, and revocation procedures live in
`docs/phase5/registry-signing-key-custody.md` section 5 (A4). Console mappings:

- Pin (initial setup section 5.1): `/admin/registries` -> "Pin a new public key"
  (password required).
- Rotate (section 5.3): paste the signed `rb-key-rotation.v1` envelope
  (password required).
- Revoke (section 5.4): per-key Revoke button (password and reason required).
  Everything signed by the key immediately fails closed.

## Emergency Response

1. Block first: `/admin/registries` -> Local blocklist -> digest or package uid.
   No password is required because the brake must not wait. Applies regardless
   of registry state.
2. Then escalate: ingest or acknowledge advisories, revoke keys, disable the
   registry source, or dark the flag in whatever order the incident needs.
3. Unblocking requires reauthentication and is audited.

## Repair

`php bin/console repair` also reconciles `packages.latest_release_id` and
package/release `advisory_status` from authoritative rows (`package_latest`,
`package_advisory` in the output).
