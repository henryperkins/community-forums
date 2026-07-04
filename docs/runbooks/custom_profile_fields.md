# Runbook — Custom Profile Fields (`custom_profile_fields`)

Release/operations runbook for the **custom_profile_fields** feature: up to three
member-authored `label`/`value` profile facts, editable on `/settings/account`
and rendered on the member's public profile. **Default-ON as of 2026-07-03** (the
`custom_profile_fields` flag graduated out of deploy-dark); fully reversible via
the `features` override. The privacy/copy posture is reviewed in
`docs/evidence/custom-profile-fields-privacy-review.md`.

> **Golden rule:** for any abuse of the custom fields (spam, harassment via
> label/value text) or a privacy concern, disable the `custom_profile_fields`
> flag first. The editor panel disappears from `/settings/account` and the fields
> stop rendering on public profiles, while normal profile editing and profile
> rendering keep serving. Disabling is non-destructive — stored `user_profile_fields`
> rows are retained and reappear when the flag is re-enabled.

## What the flag gates

`custom_profile_fields` is **render-gated** — there is no dedicated route. It
gates whether the fields are read/rendered on two shared surfaces:

- `GET /settings/account` — renders the *Custom profile fields* fieldset (three
  `custom_label_N` / `custom_value_N` input pairs).
- `POST /settings/account` — the shared account-profile save; the
  `custom_label_*` / `custom_value_*` inputs are only read and persisted while the
  flag is on. Core profile editing (display name, bio, signature, …) is
  unaffected either way.
- `GET /u/{username}` — the public profile's *Profile details* section
  (`section.profile-fields`) renders the saved facts only while the flag is on.

While the flag is off, the fieldset and the public *Profile details* section both
disappear; the rest of the settings form and profile page render normally.

## Roll back / re-enable

The flag lives in the `features` setting. Merge the override rather than
clobbering other feature keys:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["custom_profile_fields"]=false; $r->set("features",$f);'
```

Re-enable by setting `custom_profile_fields` back to `true` or removing the key,
since the default is now `true`.

Rollback is non-destructive. Stored `user_profile_fields` rows are not deleted;
rollback only stops the editor and the public rendering. Re-enabling shows the
previously saved facts again unchanged.

## Operating semantics

- **Member-authored, bounded to three.** Each member enters up to three
  `label`/`value` pairs themselves; there is no operator-defined field schema.
  Labels are capped at 40 characters, values at 160 (mirrored by the
  `user_profile_fields.label`/`value` column widths).
- **Both-or-neither, empty rows dropped.** A row with only a label or only a value
  is rejected with *"Custom profile fields need both a label and a value."*; a row
  where both are blank is silently skipped. Over-length input re-renders the form
  `422` with the field preserved (anti-draft-loss).
- **Stored raw, escaped at render.** Values are plain text stored verbatim (no
  Markdown, no HTML sanitizer at write time) and HTML-escaped on output, so markup
  in a value is neutralized (regression-pinned against `<script>` injection). They
  are not auto-linked.
- **Public within the profile's visibility envelope.** Custom fields inherit
  `users.profile_visibility` (`public` | `members`); there is **no per-field
  privacy toggle**. A members-only profile hides the whole overview (and thus the
  fields) from guests. The helper copy on the editor states the fields are
  *public* — see the privacy review for the disclosure audit.
- **Atomic with the profile row.** Saving replaces the member's fields
  (`replaceForUser` = delete-all-then-reinsert, capped at three) inside the same
  transaction as the core profile update. `WriteGate` (banned/suspended) is the
  only write gate.

## Monitoring & known limits

- There is no dedicated worker or counter for custom profile fields.
  `RepairService` has nothing to reconcile. There is no separate rate-limit policy
  — writes ride the normal account-update path.
- **No per-field moderation surface.** There is no moderation hook or per-field
  takedown UI. To remediate abusive field content while the flag is on, an admin
  edits the offending user (or the member edits their own fields); the general
  user-report flow applies to the profile as a whole. If abuse is widespread,
  disable the flag (Golden rule) while triaging.
- Rows are foreign-keyed to the user (`ON DELETE CASCADE`), so account deletion
  removes them with the account.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppBoardFoldersSavedFeedsTest.php` covers
  the `0062` slice — `test_limited_custom_profile_fields_render_on_public_profile`
  (persist two fields, render on `/u/{username}`, escape injected markup) and
  `test_custom_profile_fields_are_bounded` (over-length label → `422`, no rows
  written); `tests/Integration/Core/AppFeatureFlagTest.php`
  (`test_custom_profile_fields_is_available_by_default_and_can_be_disabled`)
  covers default-on rendering plus operator rollback hiding the panel while core
  profile editing (`signature`) survives.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/52-custom-profile-fields-edit.png`
  captures the member editing the fieldset on `/settings/account`;
  `53-custom-profile-fields-profile.png` captures the saved facts in the public
  profile's *Profile details* section. Both are driven by
  `tests/browser/gate-a.spec.ts`.
- **Accessibility:** `tests/browser/a11y.spec.ts` scans `.custom-profile-fields`
  (the settings fieldset) on desktop and mobile with no serious/critical axe
  violations.
- **Privacy/copy review:** `docs/evidence/custom-profile-fields-privacy-review.md`.
