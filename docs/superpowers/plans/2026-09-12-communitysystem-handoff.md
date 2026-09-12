# CommunitySystem handoff completion plan

**Goal:** Complete the supplied presence handoff and shared member chrome with precise design values, functional controls, and explicit deviations.

**Architecture:** PHP partials render ForumNav, BoardRail, and PresenceList using the generated Imladris component layer. Application CSS carries documented adaptations for persistent forms, the mobile drawer, branding, and accessibility. Preserve the existing uncommitted port while reviewing it.

**Spec:** `CommunitySystem.zip`, archived at `docs/design-system/imladris/_archive/design_handoff_presence/README.md`; `DECISIONS.md`, `PRODUCT_DESIGN.md`, ADRs 0031 and 0032.

**Constraints:** Preserve strict CSP, feature/read/privacy gates, server-rendered forms, and token-based light/dark styling. Do not implement the explicitly deferred directory, loading/error states, board-presence subline, or Warden chip. Browser checks use the repository's Playwright harness because the Browser plugin is not available. Keep existing user changes and test fixture cleanup intact.

- [x] Compare every archive design file with its mirror and account for local CSS reconciliation; preserve exact README and archive provenance.
- [x] Close the Compose destination-note gap in `public/assets/app.js`, verified by the destination-picker journey in `tests/browser/member-surfaces.spec.ts`.
- [x] Keep Messages reachable on phones and propagate the authorized topic board into `templates/partials/sidebar.php`; verify browser navigation and `AppMemberShellTest`.
- [x] Harden `unified-chrome.spec.ts` fixture cleanup and add no-JavaScript navigation/persistence coverage; settle fonts and entry animations before presence captures.
- [x] Close completion-review findings: synchronized unread counts and accessible labels, account-control width at narrow desktop sizes, and visible Inbox menus after positioning. Add browser regressions that reproduce each failure before the fix.
- [x] Run `composer test`, `composer verify:imladris`, and the chrome, presence, and member-surface browser checks. Record exact results and any reproduced unrelated limitations.
- [x] Update ADR 0032 and evidence documentation, refresh runtime digests after final edits, and verify the final diff.

The existing template design and implementation scope are authorized by the user's request; execute this completion pass in place, preserving existing work and using isolated browser fixtures.
