# Reviewed combined-run captures

These images were opened and inspected after the final runtime correction
`eeb72a9c`. The browser tests also assert behavior, geometry and accessibility;
the images are supporting evidence, not substitutes for those assertions.

| Capture | Inspection |
|---|---|
| [Standalone, dark, 320px](notifications-unified/mobile/mobile/standalone-dark-320.png) | Full primary route labels, visible count/focus, wrapped 64-character actor, first-line unread dot, secondary time and underlined history/settings links. |
| [Compatible pane, dark, 320px](notifications-unified/mobile/mobile/pane-dark-320.png) | Same row model, action/state vocabulary and wrapping; the directory count and shared heading agree with the bell. |
| [Admin, no JS, 105 unread](notifications-unified/desktop/desktop/bell-nojs-admin-high-count.png) | Visible initial 99+ badge, bounded long account name, brand and Log out remain reachable. Tests pin full 105 accessible names. |
| [Initial Security](account-settings-repairs/mobile/mobile/08-mobile-security.png) | Closed native chooser and first password control visible in the unscrolled 390x844 viewport. |
| [Initial Profile](account-settings-repairs/mobile/mobile/08-mobile-profile.png) | Closed native chooser, avatar guidance and file control visible initially; chooser focus ring remains visible. |
| [Initial Notifications](account-settings-repairs/mobile/mobile/08-mobile-notifications.png) | Closed chooser, real Notifications link and UTC control visible initially. |
| [Avatar rejection](account-settings-repairs/desktop/desktop/05-avatar-error-retains-draft.png) | Error belongs to the file control; display name, bio, location, pronouns and custom-field drafts remain populated with a separate Save profile action. The long blank document tail predates this repair. |
| [Saved feed opened](account-settings-repairs/mobile/mobile/10-saved-feed-open.png) | Active owned rail shortcut, management link and scoped topic content are present; no-JS rail destinations remain available. |
| [Renamed folder](account-settings-repairs/desktop/desktop/12-folder-shortcut.png) | Updated folder group and actual board link appear alongside ordinary categories; management actions retain the board. |

The account-repair Sessions screenshot was inspected for readable labels,
escaped malicious raw text and the current-device marker. Its full-document
capture occurs after opening details and scrolling, so the sticky header appears
at the captured scroll position. The separate [account-console Sessions image](account-console/mobile/mobile/a5-session-raw-details.png)
was opened at the top and is the presentation reference; neither image replaces the
actual revoke-one/other-device assertions.

The final N5 correction's [320px no-JS capture](n5/correction/mobile/12-primary-routes-320-nojs.png)
was also opened: the compose plus, bell glyph and all three route labels are
visible. This supersedes the task review's earlier screenshot-only caveat.
The [before image](n5/primary-route-before.png) is retained unchanged.

200% cases exercise equivalent CSS reflow space (640x500 at device scale 2),
not native browser zoom. Visibility pause/resume tests control `document.hidden`
and its event; they do not claim an operating-system tab-switch experiment.

Final combined [320px native navigation](unified-chrome/mobile/mobile/12-primary-routes-320-nojs.png) was opened: all route labels and both compose/bell glyphs are visible. The final [desktop Notifications pane](board-index-remediation/desktop/05-pane-notices.png) was opened and deliberately promoted to `docs/evidence/imladris-board-index-remediation/05-pane-notices.png`; shared counts/actions/rows are present and the onboarding overlay is absent following the real Skip action.
