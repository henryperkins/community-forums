# Directory navigation in the global rail

This records the original evidence pass and its build boundary. Later review fixes and final release checks are recorded in [Inbox preview recovery](../inbox-preview-recovery-2026-10-08/README.md).

The browser evidence supports moving Boards, Tags and Connections into the global Explore group, ahead of personal shortcuts and board categories. Notifications remains available through the bell. The final build of this directory evidence pass was `a8b813712a571dff`.

**558/558 selected required browser assertions pass after reconciliation**, across Linux Chromium `155.0.8059.12` and WebKit `26.6`. This was not an uninterrupted green run: the helper/fixture repairs and the real enlarged-text defect are documented below. [BROWSER_SUMMARY.json](BROWSER_SUMMARY.json) lists the assertion lanes. [SERVER_VERIFICATION.md](SERVER_VERIFICATION.md) owns PHP, generated-style fidelity, build, developer health and delivery evidence.

## Independent assessment and confirmed behavior

[INITIAL_ASSESSMENT.md](INITIAL_ASSESSMENT.md) was written before application edits, from source and rendered Chromium. It identifies the original 320px subheader/creation overlap and recommends the new hierarchy. No detector output or score was used in that assessment.

The main confirmation covers Boards, Tags and Connections at 1440, 900, 393 and 320 CSS pixels in light and dark themes. It confirms the Explore order, 44px minimum targets, normalized current state, one content heading, absence of the old strip and containment of the shared heading and creation button. The linked identity's computed foreground against its first opaque ancestor gives 6.10:1 in light and 8.35:1 in dark; this is a token/ancestor contrast probe, not independent translucent-backdrop compositing measurement.

Native Explore navigation works from Inbox, a board, Compose and another member's profile to the viewer's own Connections. Canonical tag pages mark Tags. Profile Connections stays distinct from the viewer's directory destination. Legacy Notices and `/notifications` mark the bell, leave Explore neutral and retain three synchronized notification-count hooks. Followers/Following remains available.

Phone drawer checks cover initial focus to Close, first Tab to Boards, main-page inertness, Escape and scrim dismissal, route dismissal, focus restoration and enlarged reverse/forward Tab wrapping. Creation menus remain contained. The existing enhanced compose destination picker preserves the typed title; without JS it remains a native GET board picker. No-JS member/guest and JS guest checks confirm usable rail navigation, bounded native rail flow, meaningful headings and no empty guest creation row. Tags and Community were switched off independently, together, and separately from Notifications in the private fixture; disabled home panes normalize to Boards.

## The real defect and bounded repair confirmation

At 320px with 200% of the app's Large text (36px root), the original phone primary route row overflowed because it kept one flex line. Connections' Followers/Following links also overflowed their single flex line. The new heading, identity fragments and plus allocation were contained. Both engines reproduced the document overflow: 359px Chromium and 354px WebKit at a 320px viewport. The evidence remains in `*-before-repair-extremes.json`, the corresponding captures and `webkit-final-extreme-overflow-diagnosis.json`.

The repair allows the primary routes and Connections list links to wrap. Header height now follows its content; the measured phone header offset clears the drawer and releases its inline override on desktop. Build `73430ff743b90229` received **78/78 targeted assertions**, covering 320px Large and 200%-Large in both themes/engines, a 32-character username, a long emoji board name, heading/identity/plus containment, primary-route containment, drawer/header alignment, menu containment, focus wrapping, no-JS enlarged flow and shrinking text before 320→861→1440→393 viewport changes. The full main browser matrix was not repeated.

The subsequent `a8b813712a571dff` rebuild moved the Connections wrap declaration out of the protected generated bridge. Both engines received current asset-hash receipts and one previously failing 320px/36px heading/nav/header/drawer confirmation: **6/6 checks**. These are in `*-current-ownership-receipt.json`. The repair evidence was retained. Current normal member captures have their own loaded asset receipts in `*-current-delivery.json`.

## Helper and fixture provenance

The first Chromium main batch stopped when a helper clicked the reserved scrollbar gutter beside the scrim. `chromium-first-pass-helper-error.log` preserves it. The helper now derives a point between the actual drawer and scrim edges; the resumed Chromium main batch passes 171/171.

WebKit's first main batch passed 170 application checks but failed the tag-detail check because the evidence fixture tested an empty DB result against `null`; this DB helper returned `false`. The corrected fixture created the tag. `webkit-final.json` retains the original failed fixture assertion, and `webkit-final-tag-fixture-confirmation.json` records its passing replacement. The summary counts the reconciled required check once.

Initial drawer captures sampled the entrance transition; their geometry is superseded by the two `*-initial-*-drawer-settled.png` files and `chromium-initial-drawer-settled.json`. They did not establish an application defect.

## Environment and limits

All browser work used owned port 8484, private database `retroboards_e2e_directory_rail_20261008` and isolated `/tmp` rate-limit/package stores. Fixture accounts and content are synthetic. No application source was edited by the browser reviewer. The private fixture is restored to default, and only the reviewer's CLI sessions/private server are closed at handoff; main port 8000 and the developer tunnel remain running.

The initial assessment is Chromium only. Final confirmation uses headless engines on Linux, not a physical iPhone, macOS Safari, Firefox or assistive technology. Text enlargement uses CSSOM/app font tokens, not physical browser zoom. Current-build confirmation is bounded to the ownership repair's asset receipt and affected geometry; the earlier broader matrix remains attributed to its actual build. Browser evidence does not claim a production deployment.

Useful current captures:

- [Phone Connections](webkit-current-393-member-connections.png), [phone drawer](webkit-current-393-member-connections-drawer.png), [desktop Connections](chromium-current-1440-member-connections.png).
- [320px enlarged Connections](webkit-current-320-large-200-connections.png), [320px enlarged drawer](webkit-current-320-large-200-drawer.png).
- The capture helpers and guarded `fixture.php` are retained here; raw CLI scratch output lives outside the repository.
