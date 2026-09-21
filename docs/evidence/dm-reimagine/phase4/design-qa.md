# Design QA: Messages

Reference: `.impeccable/mocks/messages/` at `31968be5`, not the older hosted
artifact. Surface contract: `.impeccable/surfaces/messages.md`.

## Rendered comparison

The PHP implementation retains the reference’s narrow index, expanded reading
column, river register, right-aligned own-letter gold plates, quiet header,
docked composer, and on-demand details. The phone list fills the available
width; reading uses one pane and the existing back link. The laptop details
drawer overlays the room instead of squeezing the message measure.

| Reference | Application evidence | Inspection |
| --- | --- | --- |
| `reference-conversation-1440-light.png` | `conversation-desktop-day.png` | Column proportions, header, letter measure, dock and receipt placement |
| `reference-conversation-390-dark.png` | `conversation-phone-twilight.png` | Single-pane reading, theme surfaces, wrapping and composer visibility |
| `reference-group-1280-dark.png` | `group-laptop-twilight-details.png` | Drawer/scrim, member identities, presence and owner tools |
| `reference-first-run-1440-light.png` | `first-run-desktop-day.png` | Empty-room hierarchy and eligibility notice |
| `reference-list-390-light.png` | `phone-list-no-js.png`, `list-phone-twilight.png` | Full-width list and usable progressive-enhancement fallback |
| `reference-compose-1280-dark.png` | `new-recipient-chips.png`, `dialog-validation-draft.png` | Engraved recipient field, chips, group title, retained draft/error |

Content is real seeded application data, not a pixel-diff fixture. The shared
composer’s actual toolbar, preview and draft status account for its additional
height. The application screenshots use full-page capture after settling motion;
the reference uses the named viewport. See README for the production adaptations.

## Behavioral checks

- Real HTTP writes and real 20-second polling: appended incoming message,
  unchanged scrolled-up position and unsent draft, working new-message pill.
- Clock-controlled failures and visibility changes: retry/backoff, hidden pause,
  immediate resume and permanent 404 stop.
- Recipient keyboard selection/removal and exact canonical submitted value;
  hidden group title until needed; suggestions filtered server-side; delayed
  results cannot reopen a field after an Enter/comma recipient commit.
- Details default closed, explicit persistence, Escape/focus return, laptop
  drawer and no-JavaScript anchor close.
- First-run notices, originating-dialog 422, plain-field no-JavaScript 422,
  full group owner/member/report journey and shared rich-editor mounts.
- Axe WCAG 2 A/AA and 2.1 checks on Messages list, compose with suggestions,
  first-run, group details and report states in the tested themes/viewports.
  Theme transitions are settled before contrast measurement.

## Detector and documentation

Impeccable detector ran once across the changed templates and the shared CSS/JS.
Its broad-file output includes pre-existing styles; the added Messages CSS has
font-ramp advisories for sizes inherited from the approved mock, not new warning
findings. Those sizes are retained for fidelity. Unrelated system drift is not
rewritten: the existing surface brief’s primary target is the label `messages`,
while its `related_targets` identify the real production files.

The approved own-letter gold exception is recorded at the One Gold Rule in
DESIGN.md and in the design manifest. Independent finish review is recorded
alongside the final verification results in README.
