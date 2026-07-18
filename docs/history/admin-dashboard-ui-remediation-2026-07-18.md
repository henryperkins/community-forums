# Admin dashboard UI remediation — 2026-07-18

This note closes the admin-dashboard findings approved after the July admin UX
review. It is intentionally separate from `admin-ux-remediation-2026-07-18.md`:
that earlier ledger records PR #44 safety work, while this change refocuses the
admin landing page and moves configuration into domain-owned pages.

No schema, dependency, JSON API, feature-default, global member-navigation, or
Imladris visual-system change is part of this remediation.

## Finding → fix → evidence

| Finding | Fix | Evidence |
|---|---|---|
| `/admin` mixed operational status with site, registration, anti-abuse, and custom-emoji forms. | Reduced the dashboard to introduction, queue health, Needs attention, Community today, and Recent activity. Queue cards now expose `attention`, `clear`, or `unavailable`; the Reports/Approval-hold and Appeals cards respect their route feature gates; activity cards contain only new users today and active now. The audit total and ambiguous Users cards were removed. | `AppAdminDashboardRemediationTest`; `AppAdminNavIaTest`; `admin-dashboard.spec.ts`; `docs/evidence/browser/desktop/07-admin-dashboard.png`; `docs/evidence/browser/mobile/07-admin-dashboard.png` |
| One combined settings POST could overwrite unrelated keys through defaulted fields. | Added `AdminSettingsService` and owning general/moderation pages. Site name, registration, and anti-abuse writes validate and persist only their own keys; successful writes return to their owner. The obsolete `POST /admin/settings` is a 404 tombstone, while stable `POST /admin/site` remains. Registration and anti-abuse changes have separate, precise before/after audits. | `AppAdminDashboardRemediationTest` ownership, redirect, feature-gate, and audit cases; focused PHP suite |
| Failed site, registration, anti-abuse, and custom-emoji writes discarded typed input or reported only a flash. | Every owner now re-renders at 422 with field errors, typed values, and programmatic `aria-invalid` / `aria-describedby` / first-error focus wiring. `CustomEmojiService::pageModel()` supplies the catalogue and form overlay for the dedicated `/admin/custom-emoji` page, and replacement writes report that they replaced an existing shortcode. | `AppAdminDashboardRemediationTest`; `AppFieldErrorA11yTest`; `AppAdminModerationTest`; `AppCustomEmojiGiphyTest`; `/admin/settings` Playwright 422 journey |
| The flat admin link strip obscured information architecture and did not explain unavailable feature-owned destinations. | Replaced it with one shared eight-group navigation model: Dashboard, Moderation, Content, People, Appearance, Notifications, Integrations, and Settings. Active state, existing URLs, and feature-gated disabled explanations are preserved. | Integration navigation-contract cases; desktop grouped-destination Playwright case |
| Small screens had no bounded, accessible admin navigation workspace. | Above 860 px, the shared `.admin-head → navigation → .admin-pane` structure forms a persistent 224 px rail and fluid pane. At or below 860 px, JavaScript enhances that same navigation into a drawer with a dedicated scrim and close control, focus containment/restoration, Escape/scrim/link closing, body-scroll lock, and resize cleanup. Without JavaScript the groups remain expanded above the page. | `admin-dashboard.spec.ts` desktop, mobile, and no-JS projects; `docs/evidence/browser/mobile/07-admin-dashboard-drawer.png`; `docs/evidence/browser/mobile/07-admin-dashboard-no-js.png` |
| Recent activity was semantically sound but gave mobile users no indication that Target and Reason were off-screen. | Added the visible “Scroll for Target and Reason” cue, a right-edge fade, and scroll-aware removal at the horizontal end while retaining the labelled, keyboard-focusable region and semantic table. | Mobile overflow Playwright assertions and `docs/evidence/browser/mobile/07-admin-dashboard.png` |

## Review and integration hardening

PR #47 was reviewed against the newer admin-audit behavior merged through PR
#46 before publication. The sibling branches were integrated with a merge from
`main`, preserving the dedicated dashboard redesign while carrying forward:

- Appeals navigation, queue card, count, attention copy, and feature gating.
- The shared `moderation_queue` gate for both Reports and Approval hold.
- The corrected singular “1 Thread Intelligence warning needs…” copy.
- Honest custom-emoji replacement feedback and the shared accessible field-error
  contract on the new settings and emoji owner pages.
- The round-two authorization, draft-loss, pagination, restore, and slug
  validation fixes outside the dashboard.

The review also reproduced PR #47's failed browser-evidence selector: the
`.custom-emoji-panel` scope covered only the creation card while the test
expected the catalogue too. The wrapper now owns both cards, and the original
desktop/mobile custom-emoji evidence case passes.

## Fidelity ledger

- **Information hierarchy:** the operational sequence is queue health → Needs
  attention → Community today → Recent activity; configuration forms no longer
  appear on the dashboard.
- **Rail and drawer geometry:** the desktop rail is 224 px at the approved
  860 px breakpoint; mobile controls meet the 44 px target and the drawer uses
  a separate scrim rather than treating the navigation itself as the overlay.
- **Visual system:** the implementation reuses the existing typography, color,
  spacing, density, surface, button, status, and Admin-mode conventions. The
  generated concepts informed hierarchy and geometry, not a replacement theme.
- **Queue priority:** attention, clear, and unavailable states remain legible
  through labels and structure as well as color.
- **Responsive behavior:** the content pane is explicitly shrinkable, so the
  activity table scrolls inside its labelled region instead of widening the
  mobile document. The cue and fade disappear at the end of that region.
- **Progressive enhancement:** the server renders all groups and links; the
  drawer is an enhancement only, and the no-JS browser journey reaches General
  & registration directly.

## Verification record

- Red-first integration checkpoint: 20 tests, 49 assertions, with 14 failures
  attributable to the intentionally missing routes/models/UI.
- Focused green PHP checkpoint: 44 tests, 286 assertions.
- Post-review integration checkpoint: 217 tests, 1,049 assertions, including
  the merged Appeals/gating behavior and accessible field-error wiring.
- Focused admin-dashboard Playwright checkpoint: 3 passed with the remaining
  project entries intentionally skipped by project guards; console assertions
  and serious/critical axe checks are included, plus reduced-motion and no-JS
  reachability.
- Full PHP gate: direct `php vendor/bin/phpunit` passed with 2,401 tests,
  17,049 assertions, and 2 intentional skips.
- Imladris contract gate: `composer verify:imladris` passed with 11 tests and
  66 assertions.
- The all-surface `npm run evidence` gate passed after all three fresh-database
  batches. The remediation-specific suite supplies desktop, mobile, no-JS,
  console, keyboard, reduced-motion, and axe evidence; the settings 422 proof is
  captured at `docs/evidence/browser/{desktop,mobile}/remediation-settings-422-draft.png`.
- `npm run a11y` was not repeated as a separate command; the dashboard evidence
  test itself runs the serious/critical WCAG 2.0/2.1 axe rules in both target
  viewports.
