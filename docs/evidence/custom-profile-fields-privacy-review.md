# Custom Profile Fields — Privacy & Copy Review (`custom_profile_fields` graduation gate)

**Date:** 2026-07-04

The graduation-readiness ranking in `docs/evidence/deploy-dark-features.md` listed
`custom_profile_fields` as blocked on a **profile/privacy copy review** rather
than on engineering work. This is that review: an audit of what the feature
exposes, the visibility semantics, the user-facing disclosure copy, the abuse/
moderation posture, and the data lifecycle — with recommendations. It
accompanies the operator runbook `docs/runbooks/custom_profile_fields.md`.

## What the feature exposes

Members may add **up to three** free-text `label` / `value` pairs to their
profile (label ≤ 40 chars, value ≤ 160). Both are member-authored; there is no
operator-defined schema. Editing is on `/settings/account`; the saved facts
render on the public profile's *Profile details* section (`/u/{username}`,
Overview tab). Source of truth: `templates/account/settings.php`,
`templates/profile/show.php`, `src/Service/AccountService.php::customProfileFields()`,
`src/Repository/UserProfileFieldRepository.php`, table `user_profile_fields`
(migration `0062`).

## Visibility semantics

- Custom fields inherit the **whole-profile** visibility control
  `users.profile_visibility` — `ENUM('public','members')`, default `public`
  (migration `0011`). There is **no per-field visibility toggle**.
- A `members`-only profile hides the entire Overview (and therefore the custom
  fields) from signed-out visitors (`ProfileController::show` renders the gated
  view). The owner always sees their own fields.
- **Assessment:** acceptable for a bounded, opt-in, self-authored public profile
  fact list. Per-field visibility would be over-engineering for three short
  fields; whole-profile visibility is the right granularity and matches how bio /
  signature already behave.

## Disclosure copy audit

- **Editor helper (the disclosure):** *"Add up to three public profile facts.
  Labels are limited to 40 characters; values to 160."* — the word **"public"** is
  the privacy signal, shown directly above the inputs. It is accurate (fields are
  public within the profile's visibility envelope) and appears at the point of
  entry.
- **Public-side heading:** *"Profile details"* on `/u/{username}`.
- **Assessment:** the disclosure is present, plain-language, and correctly placed
  before the member types. It meets the bar for an opt-in public field.
- **Optional (non-blocking) recommendations:** (1) if `profile_visibility` is
  `members`, the "public" wording is slightly loose — a future refinement could
  say "public profile facts (shown per your profile visibility)"; (2) consider a
  one-line note near the public *Profile details* heading for symmetry. Neither is
  required for graduation.

## Safety / abuse posture

- **XSS-safe.** Values are stored raw but HTML-escaped at render on both `dt`
  (label) and `dd` (value); injected markup is neutralized. This is regression-
  pinned (`AppBoardFoldersSavedFeedsTest::test_limited_custom_profile_fields_render_on_public_profile`
  renders `Vim <script>…` as inert text).
- **Not auto-linked.** Values are plain text; URLs are not turned into links, so
  the fields are not a drive-by link surface.
- **Bounded.** Three fields, 40/160 char caps, both-or-neither validation — a
  small, capped surface with limited spam leverage.
- **Known gap (documented, accepted for graduation):** there is **no per-field
  moderation hook or takedown UI**. Abusive field text is remediated by an admin
  editing the user, by the member editing their own fields, or — at scale — by
  disabling the flag while triaging (runbook Golden rule). A future per-field
  moderation affordance is a reasonable follow-up if abuse materializes; it is not
  a blocker for a bounded, escaped, opt-in surface.

## Data lifecycle

- Rows live in `user_profile_fields`, foreign-keyed to the user with
  `ON DELETE CASCADE`.
- Treated as **PII on account deletion**: `AccountLifecycleService::purgePii()`
  includes `user_profile_fields` in the purge set, so the fields are removed when
  an account is purged/anonymized (not merely orphaned).

## Verdict

The privacy and disclosure posture is sound for a bounded, opt-in, self-authored,
escaped, public profile surface: visibility is correctly scoped to the existing
profile-visibility control, the "public" disclosure is present at the point of
entry, content is XSS-safe and non-linkified, and the fields are PII-purged on
deletion. The one gap (no per-field moderation UI) is documented and mitigated by
existing admin/self edit + the flag kill switch. **This clears the profile/privacy
copy-review gate** the deploy-dark inventory tracked for `custom_profile_fields`.
The optional copy refinements above are non-blocking; owner ratification of this
review can be recorded alongside the graduation entry.
