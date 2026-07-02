# Runbook: `package_themes` (Phase 5 Increment 4, Deploy-Dark)

**State:** default off. Enabling exposes the admin theme surface and public
theme-serving routes for verified declarative theme packages. It does not make
any installed package active by itself; activation is a separate password
reauthenticated operator action.

This runbook assumes `package_registry` is also enabled for catalogue, install,
consent, enable, update, rollback, and health-worker operations. The recovery
page `/admin/themes/safe-mode` remains reachable even when `package_themes` is
off.

## Enable / Disable / Rollback

- Enable: set `features.package_registry=true` and
  `features.package_themes=true` in the settings `features` JSON.
- Disable package themes only: set `features.package_themes=false`. `/admin/themes`
  and `/theme/*` return 404, the shell links no package theme CSS, and the
  system theme serves. Existing build, active, and last-known-good rows are
  preserved for investigation or later resume.
- Emergency brake: enter theme safe mode. Safe mode is independent of the
  feature flag and makes all public theme routes fail dark.
- Package rollback and theme rollback are separate controls. Package rollback
  changes the installed release. Theme rollback swaps the active build with the
  last-known-good build from `/admin/themes`.

## Staged Staff Preview Rollout

1. Enable `package_registry`, refresh registries, install the candidate theme,
   grant any package consent, and enable the installed package.
2. Enable `package_themes` for staff. Open `/admin/themes` and use **Preview**.
   Preview stores a build id in the current admin session and serves
   `/theme/preview.css` with `Cache-Control: private, no-store`.
3. Confirm a second browser/session does not receive the preview stylesheet.
   Capture visual evidence on desktop and mobile before activation.
4. Activate during a low-traffic window from `/admin/themes` with password
   reauthentication. Monitor the page shell, `/theme/{digest}.css`, telemetry
   event `theme.lifecycle`, moderation log actions, and package history.
5. Keep `/admin/themes/safe-mode` open or bookmarked during rollout. If visual
   regressions appear, enter safe mode first, then decide whether to roll back,
   disable the package, or disable `package_themes`.

## Safe Mode

Safe mode forces the built-in system theme and suppresses all package theme CSS
and assets. Entry is deliberately low-friction: admin session + CSRF only.
Exit requires current password reauthentication.

Use the UI:

1. Open `/admin/themes/safe-mode`.
2. Click **Enter safe mode**.
3. Confirm the public site has no `/theme/` stylesheet link.
4. To exit, return to the same page and submit **Exit safe mode** with the
   current password.

Use the environment override when the UI is unreachable:

```bash
THEME_SAFE_MODE=1
```

The environment override cannot be exited from the UI. Remove it from the
process environment and restart the app before using the normal exit form.

Last-resort SQL entry, if neither UI nor environment can be changed:

```sql
INSERT INTO settings (`key`, `value`, updated_at)
VALUES ('theme_safe_mode', '"1"', UTC_TIMESTAMP())
ON DUPLICATE KEY UPDATE `value` = '"1"', updated_at = UTC_TIMESTAMP();
```

To clear that DB flag manually after recovery:

```sql
UPDATE settings
SET `value` = '""', updated_at = UTC_TIMESTAMP()
WHERE `key` = 'theme_safe_mode';
```

## Activate / Roll Back / Deactivate

- Activation validates the installed package is still enabled, verifies cached
  release bytes, validates the theme block, builds deterministic CSS and
  neutralized assets, captures last-known-good, then swaps the active pointer in
  one transaction.
- Rollback from `/admin/themes` requires password reauthentication and swaps the
  active build with the last-known-good build. If the LKG build is no longer
  serveable, rollback refuses with `theme_lkg_invalid`.
- Disable or uninstall the installed package from the package lifecycle surface
  when the package itself should no longer be eligible. That also makes its
  theme build ineligible for serving.

## Quarantine / Advisory Interplay

`worker:packages` still owns package health. If cached release bytes are missing
or tampered, or an advisory/blocklist makes an enabled install ineligible, the
worker disables or quarantines the package and the theme state service clears or
replaces affected pointers:

- If the active build belongs to the ineligible install, it is deactivated.
- If the last-known-good build belongs to a different still-serveable install,
  it can become active.
- If active and LKG both point at the ineligible install, both pointers are
  cleared and the system theme serves.

After restoring exact release bytes or clearing a false-positive policy state,
use package **Re-verify**, then activate deliberately from `/admin/themes`.
Re-verify never auto-activates a theme.

## Repair

Run:

```bash
php bin/console repair
```

Repair reconciles theme state in addition to package latest/advisory repair. It
clears active or LKG pointers whose build digest no longer matches stored CSS,
whose installed package disappeared, or whose install is no longer enabled. It
does not build, activate, or fetch theme packages.

## Telemetry / Audit

Telemetry event:

- `theme.lifecycle` with actions `build`, `activate`, `rollback`, `deactivate`,
  `safe_mode_enter`, and `safe_mode_exit`.

Moderation log actions:

- `theme_preview`
- `theme_activate`
- `theme_rollback`
- `theme_deactivate`
- `theme_safe_mode_enter`
- `theme_safe_mode_exit`

Package history events:

- `theme_activate`
- `theme_rollback`
- `theme_deactivate`
