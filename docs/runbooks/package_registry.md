# Runbook: `package_registry` (Phase 5 Increment 3)

**State:** graduated to default-ON on 2026-07-09 — operator-reversible via
`features.package_registry=false`. The default-on surface provides staff-only catalogue browse, registry
trust controls, signed release acquisition, install/consent/enable/update/
rollback/uninstall/export flows, and the registry/package workers. Package rows
remain inert: an enabled package is eligibility only and no theme/integration
runtime executes until later increments.

## Enable / Disable / Rollback

- Default-on since 2026-07-09: `/admin/packages`, `/admin/registries`, and the
  `worker:registry-refresh` / `worker:packages` workers are active unless the
  flag is rolled back (only installs that previously wrote
  `features.package_registry=false` need the enable step).
- Rollback: set the flag false (merge into the `features` JSON per
  `docs/runbooks/operations.md` §2 — do not clobber other overrides). Routes return 404, both workers no-op, verified
  release bytes and install rows remain inert for investigation or later resume.
- Independent of the flag: `package_registries.is_enabled=0` disables one source
  without a password. The local blocklist and package disable/pin/export actions
  stay the low-friction emergency controls.

## Cron

```cron
*/30 * * * * php bin/console worker:registry-refresh
0 * * * * php bin/console worker:packages
```

`worker:registry-refresh` fetches signed snapshots/advisories. `worker:packages`
verifies installed release bytes, enforces local blocklist/advisories, reports
notify-policy updates, and purges uninstalled rows after retention lapses. Both
commands no-op when `package_registry` is rolled back.

## Install / Consent / Enable

1. Open `/admin/packages`, choose a package, and review the pinned registry,
   trust class, latest release, advisory state, and stale-snapshot banner.
2. Choose **Review install plan**. This verifies or fetches the signed
   `rb-release.v1` document, checks the snapshot-pinned digest, validates the
   embedded `rb-manifest.v2`, and renders the permission/risk summary.
3. Submit **Install** with password reauthentication. The row is written only
   after validation succeeds; refusal tests depend on that validate-first rule.
4. Review the consent page and submit **Grant permissions** with password
   reauthentication.
5. Submit **Enable** with password reauthentication. In Inc 3 this records
   eligibility only; nothing executes.

`install`, `consent`, `enable`, `update`, `rollback`, and `uninstall` require
password reauthentication. `disable`, `pin`, `export`, update-policy changes,
cancel staged update, and `reverify` are reauth-free by design for emergency or
low-friction operation.

## Updates

- Update policy is `manual` or `notify` only; there is no auto-update in Gate A.
- **Check for update / Update** verifies the target release. If permissions only
  shrink, the update applies immediately. If permissions are added or risk
  increases, the update is staged and the consent page shows the added/removed/
  unchanged diff.
- **Approve staged update** requires password reauthentication and re-checks the
  digest before switching. **Cancel staged update** clears the stage without
  reauth.
- **Pin** blocks updates until unpinned.

## Rollback

Rollback targets are only previously activated releases whose verified
content-addressed bytes still exist locally. The service refuses a rollback to
an unknown digest or a missing/tampered artifact. If rollback adds permissions it
is staged for re-consent; otherwise it applies immediately after password
reauthentication.

## Uninstall / Export / Retention

- **Export** downloads the install snapshot, permissions, package metadata, and
  history as JSON without password reauthentication.
- **Uninstall** requires password reauthentication, disables first, records an
  export snapshot, removes permission grants, marks the row uninstalled, and sets
  `retention_until` from manifest `install.retention_days` or
  `PACKAGES_RETENTION_DAYS` (default 30).
- `worker:packages` purges retained rows and removes cached release bytes after
  the retention window lapses.

## Quarantine Response

1. Inspect the package detail page and history for the quarantine reason.
2. Restore the exact signed release bytes at
   `PACKAGES_STORAGE_PATH/{digest}.json` from the registry or backup.
3. Use **Re-verify**. It only marks health OK when the cached bytes hash to the
   installed digest.
4. Re-enable manually if appropriate. Re-verify never auto-enables a package.
   For theme packages, quarantine also deactivates or clears affected theme
   pointers; see `docs/runbooks/package_themes.md`.

## Emergency Response

1. Disable the installed package, pin it, or add a local blocklist row first.
   These controls are reauth-free so the brake does not wait.
   If the package is an active theme, enter theme safe mode or deactivate/roll
   back from `/admin/themes` as described in `docs/runbooks/package_themes.md`.
2. Escalate as needed: acknowledge or ingest advisories, revoke keys, disable
   the registry source, or roll back the `package_registry` flag.
3. `force_disable` and `revoke` advisories are enforced by `worker:packages` for
   enabled installs. `block_new`, `force_disable`, and `revoke` cancel blocked
   staged updates.
4. Unblocking requires deliberate operator action and audited state changes.

Webhook SSRF/idempotency evidence of record: the delivery-idempotency report at
`docs/evidence/phase5/webhook-idempotency.md` and the SSRF/egress adversarial
suite `tests/Unit/Security/EgressGuardAdversarialTest.php` document the
registration + delivery egress denial corpus and the at-least-once delivery
guarantees underpinning package-owned webhooks.

## Repair

`php bin/console repair` reconciles `packages.latest_release_id` and
package/release `advisory_status` from authoritative rows (`package_latest`,
`package_advisory` in the output). Use it after manual DB repair or restored
snapshots, then run `worker:packages` once to verify cached artifacts and policy.

## Package Integrations & Security-Response Console (Inc 5)

The `remote_app`/`automation` integration runtime (install-scoped settings,
read-only API tokens, package-owned webhooks) and the operator security-response
console (publisher trust, exact-digest review, advisories, emergency disable,
transparency) are documented in **`docs/runbooks/package_integrations.md`**.
Both consume this same `package_registry` flag; the flag-
independent `package_execution_disabled` brake is the kill switch for package
execution specifically.
