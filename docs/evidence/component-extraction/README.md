# Component extraction pass — before and after

Captures for ADR 0037. Each image stacks the **base commit** (top) over **this
branch** (bottom), taken from two servers reading one seeded database, so any
difference is the change and not the data. Captured at 1280px, 2× device scale,
signed in as `admin@retro.test`. The register captures are signed out.

The seed adds sixty members, reports and tags (so each pager has a next page),
an active and a revoked invitation, two badge rules, a `governance` override,
and invite-only registration. A live second factor is added after sign-in and
removed after its capture. The seed and the capture script are throwaway and
not committed; the pairs below are the record.

| file | change | expected |
|---|---|---|
| `01-badge-rules.png` | `.features-pill` → `.state state-done` / `state-muted` | identical |
| `02-link-previews.png` | `.features-pill` → `.state` | identical |
| `03-invitations.png` | `.member-invitations-status` → `.state state-active` / `state-revoked` | identical; the revoked tint is the console's 12%, not 10% |
| `04-feature-override.png` | `.features-override-pill` → `.state state-staff` | identical |
| `05-execution-brake.png` | `.pill packages-pill` / `.pill-danger` → `.state` | "LIVE" → "live": the console's token case, not `.pill` capitals |
| `06-totp-enabled.png` | `.totp-state-pill` → `.account-state-chip` | identical |
| `07-this-device.png` | `.account-state-chip` takes the done pair only | the fixed-ramp `--green-200` ring is gone, matching the TOTP chip |
| `08-back-link-role.png` | hand-rolled link → `partials/back_link.php` | identical, one 13px chevron |
| `09-directory-empty.png` | `.member-directory-empty` → `partials/empty_state.php` | the console empty state; a hair less padding |
| `10-directory-pager.png` | private pager → `partials/pager.php` (`has_next`) | the console pager: Previous · Page 1 · Next |
| `11-reports-pager.png` | a filled primary "Next" and no Previous → `partials/pager.php` | the console pager |
| `12-tags-pager.png` | hand-copied pager → `partials/pager.php` (`noun: tag`) | identical; each control is still named "Next tag page" |
| `13-search-error.png` | `.form-error` (no CSS) → `.field-error` | the error sits under its field in rust, not as loose body text |
| `14-register-invite.png` | `.notice` (no CSS) → `.callout` | an info plate where there was a bare paragraph |
| `14-register-closed.png` | `.notice` (no CSS) → `.callout callout-review` | a review plate where there was a bare paragraph |
