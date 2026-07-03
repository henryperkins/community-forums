# Runbook — Profile Media (`profile_media`)

Release/operations runbook for the **profile_media** feature: member avatar
upload/removal, bounded plain-text signatures, and admin profile-media
moderation for uploaded avatars and signatures. **Default-ON as of 2026-07-03**
(the `profile_media` flag graduated out of deploy-dark); fully reversible via
the `features` override.

> **Golden rule:** for any avatar/signature defect or abuse spike, disable the
> `profile_media` flag first. The member avatar upload/remove routes and admin
> avatar/signature moderation routes return 404, while normal profile editing and
> profile rendering keep serving.

## What the flag gates

`profile_media` gates the mutation and moderation surfaces, not all profile
display. Already-stored `users.avatar_path` and `users.signature` values remain
ordinary profile data and may still render after rollback.

Routes gated by the flag:

- `POST /settings/avatar` — member uploads an avatar image through the existing
  attachment pipeline.
- `POST /settings/avatar/remove` — member reverts their uploaded avatar to the
  monogram fallback.
- `POST /admin/users/{id}/avatar/remove` — admin clears an uploaded avatar,
  reverting the subject to monogram and auditing `clear_avatar`.
- `POST /admin/users/{id}/signature/remove` — admin clears a signature and
  audits `clear_signature`.

The account settings page hides the avatar panel while the flag is off. The
admin user record hides the profile-media moderation card while the flag is off.

## Roll back / re-enable

The flag lives in the `features` setting. Merge the override rather than
clobbering other feature keys:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["profile_media"]=false; $r->set("features",$f);'
```

Re-enable by setting `profile_media` back to `true` or removing the key, since
the default is now `true`.

Rollback is non-destructive for profile rows. Existing avatar/signature values
are not cleared automatically; rollback only prevents new avatar uploads/removals
and admin profile-media moderation actions.

## Operating semantics

- **Uploads use the existing image pipeline.** `AttachmentService` size-checks,
  content-sniffs, dimension-checks, re-encodes with GD, stores under the
  attachment storage root, and finalizes the asset as public parentless media
  with purpose `avatar`.
- **Removal reverts to monogram.** Member removal and admin removal both set
  `users.avatar_source='monogram'`, clear `users.avatar_path`, record
  `avatar_removed_at/by`, and mark a local `/media/{id}` attachment row deleted
  when the avatar path points at local media.
- **Signatures stay bounded.** The account profile form enforces the existing
  three-line height cap. Signatures are escaped and displayed according to the
  viewer's `show_signatures` reading preference.
- **Admin actions are audited.** Avatar clears write `clear_avatar`; signature
  clears write `clear_signature`. Both are user-targeted moderation log entries.
- **Appeals.** `clear_avatar` and `clear_signature` appear in the member appeals
  list for 30 days. Resolving an avatar/signature appeal records the outcome and
  notification; it does not automatically restore the cleared avatar/signature.

## Monitoring & known limits

- There is no dedicated worker or counter for profile media. `RepairService` has
  nothing to reconcile.
- Deleted local avatar attachment rows are hidden immediately. The attachment
  cleanup worker remains responsible for physical file reclamation according to
  the existing attachment lifecycle.
- If an uploaded avatar is abusive and the flag is still on, prefer the admin
  record's **Remove avatar** action so the moderation log captures the operator
  action. If the flag has already been disabled, remediate by editing the user
  row directly only after preserving an operator note outside the app.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppProfileMediaTest.php` covers
  default-on availability, operator rollback, member upload/remove, admin avatar
  clear, signature clear, attachment deletion, and validation re-rendering;
  `tests/Integration/Core/AppFeatureFlagTest.php` covers default-on plus rollback
  isolation; `tests/Integration/Core/AppModerationAppealsTest.php` covers
  `clear_avatar` appeal eligibility.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/46-profile-media-avatar.png`
  captures a member profile with an uploaded avatar; `47-profile-media-moderation.png`
  captures the admin profile-media moderation controls. Both are driven by
  `tests/browser/gate-a.spec.ts`.
- **Accessibility:** `tests/browser/a11y.spec.ts` scans `.profile-media-panel`
  and `.profile-media-card` on desktop and mobile with no serious/critical axe
  violations.
