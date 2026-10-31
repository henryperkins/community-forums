# Branding public theme coverage — combined local qualification

This uncommitted candidate on `fix/admin-branding-recovery`, based on f0bbfc03,
implements the approved [propagation findings](before/report.md) and retains the
five earlier keyboard-recovery/appearance-guidance fixes. The primary checkout
remains clean. No production setting, commit, push or deployment is part of this
work; this record qualifies the isolated local application.

Implementation plans: [public theme coverage](../../superpowers/plans/2026-10-31-branding-public-theme-coverage.md)
and [earlier recovery fixes](../../superpowers/plans/2026-10-31-admin-branding-recovery.md).
The runtime contract is in [operator branding](../../runbooks/branding.md) and
[ADR 0053](../../adr/0053-branding-contrast-preview-and-theme-recovery.md).

## Result

Saved primary colours now govern actions, selected header/board/feed states,
Messages filters, focus and hover/pressed fills. Saved accents govern highlights,
unread dots/counts, reaction highlights and selection rules. Soft fills and their
foregrounds are resolved from the serving palette; warning/success, decision,
moderator, presence and constant-ground families retain separate roles.

The preview uses public component classes, with a persistently visible feed-menu
selection and actual hover/focus states. PHP and JavaScript resolve the same
candidate tokens, including clearing saved overrides and active-package bases.
The UI explains that changes appear on the next page load, that members retain
personal light/dark preferences, and that packages supply the base ahead of the
stored Classic/Retro preset. Private package previews remain session-only.

## Verification

| Check | Result |
|---|---|
| Focused PHP branding/palette tests | 40 tests / 977 assertions passed |
| Updated existing style contracts | 31 tests / 305 assertions passed |
| Final full PHP | 3,437 tests / 31,139 assertions, zero failures, one intentional migration-rehearsal skip |
| Chromium | 64 distinct cases qualified: 60 passed in the combined run, then the two corrected old preview assertions passed; the final eight-case Retro/parity follow-up passed, including two new default-restoration cases |
| WebKit | 64 distinct cases qualified: 59 passed in the combined run; the three failed cases passed in a four-case synchronization follow-up; the final eight-case Retro/parity follow-up passed, including two new default-restoration cases |
| Existing branding/product-tour journey | Both desktop/mobile cases passed |
| Generated assets | Built and current; `check:assets` passed |
| Asset/Worker checks | 35 passed, zero failures/skips |
| Independent review | One P2 found and fixed; see [review and resolution](independent-review.md) |

These are reconciled results from the combined runs and focused corrections,
not a claim that either engine completed one uninterrupted 64-case passing run.
Commands, original failures and passing follow-ups are retained in the
[log index](logs/README.md). The final full PHP run follows the Retro fallback fix;
the asset inputs were unchanged by that PHP-only correction.

The new propagation suite contributes 22 viewport cases per engine. It exercises
Classic, Retro and active packages in Light/Dark, actual admin saves followed by
fresh guest/member responses, opposite member/device preferences, native no-JS
saving/clearing, content hover, field/navigation focus, selected navigation/rail/
feed, unread counts, and Messages filled states. It also compares all PHP/JS
palette tokens across 36 combinations per viewport, including extreme and
midpoint colours, each individual override cleared and both cleared, then checks
System/device changes. Status-chip and admin-page appearance remain independent
of candidate colours.

A final built-in fallback check exposed 2.216:1 selected-menu contrast in Retro
dark after clearing custom colours. A failing palette regression pinned it;
Retro dark now supplies its own dark foreground, producing 7.315:1 in the
rendered preview and public feed. The new no-JavaScript regression checks both
Dark and System defaults, at desktop and mobile viewports. The final focused
browser follow-up also rechecks Retro saves in both modes and all PHP/JS palette
combinations after this change.

The existing appearance/theming suites cover upload draft preservation, asset
removal and local feedback, reset/Enter safety, mobile preview-return focus at
390/320px without JS, field boundaries, forced colours, enlarged text, private
package preview, activation, safe mode, rollback and contrast acknowledgement.

The first full PHP run exposed two stale literal style-contract assertions
(previous hover expression and primitive gold rail border); these were updated
to require the new semantic roles with the original unbranded fallback. The final
full run passed. The skip is the separate-database migration rehearsal in
`AppThreadIntelligenceMigrationTest::test_0077_down_and_up_rehearsal_on_fixture_free_schema`;
it intentionally requires `retroboards_thread_intelligence_clean`.

Browser setup corrections are retained in the plan/logs. WebKit recovery checks
wait for the real activation-form entrance animation before ordinary clicks and
for the preview stylesheet link to reflect a completed transition before private
asset requests. No force-click, timeout increase or application change was used.
These cases also pass in a focused Chromium follow-up. Other fixture corrections: the guest mobile bar
intentionally hides its sole Boards link, so rendered checks use the visible
guest rail and member header. The reader fixture explicitly marks a topic unread
and creates a direct conversation. The Messages new-message element is revealed
for style qualification; polling/message-arrival behaviour is not claimed.

## Representative renders

- [Expanded desktop preview](chromium/desktop/classic-light-preview.png)
- [Expanded mobile preview](chromium/mobile/classic-light-preview.png)
- [Public navigation after saving blue/brown](chromium/desktop/classic-light-guest-no-js.png)
- [Member inbox with its independent dark preference](chromium/desktop/classic-light-member-inbox-no-js.png)
- [Opened member feed with the saved primary colour](chromium/desktop/classic-light-member-feed-no-js.png)
- [Messages with an extreme light primary](chromium/desktop/messages-light-selected.png)
- [Messages hover with an extreme dark primary](chromium/desktop/messages-dark-new-message-hover.png)
- [Cleared Retro System default on a dark mobile viewport](chromium/mobile/cleared-retro-system-feed-no-js.png)

Per-view `*-pairs.json` files retain measured visible foreground/background pairs.
Chromium's recorded selected/filled pairs all meet 4.5:1 in these fixtures; the
lowest recorded ratio is about 4.506:1. The Messages regression improved from
1.079:1 (white primary) / 1.388:1 (black primary hover) to 18.750:1 / 21:1.
Each engine retains 136 measured pairs, including the eight Retro-default pairs.
Ordinary chosen link/indicator colours may still be risky on one appearance mode;
the exact-candidate warning/acknowledgement flow remains in place.

## Limits and preservation

Evidence is local PHP plus Chromium/WebKit rendering at desktop and mobile
viewports, not physical-device or screen-reader qualification. It does not
certify arbitrary custom CSS/packages or production deployment. The real admin
save path, guest/member delivery and default/package clearing are exercised
against an isolated database, with mail captured in memory.

The pre-follow-up candidate was archived and verified by SHA-256 before editing
(`/tmp/rb-theme-coverage-20261031/prior-candidate.tar.gz`). Earlier evidence remains
in `admin-branding-recovery-2026-10-31`. This record is the qualification for the
combined candidate; `qualified-files.json` records its final file identities.

Cleanup verified: both task databases and their four grants were removed, as
were the two temporary dependency symlinks. Shared dependencies and the existing
MariaDB service were preserved. No task server remains on port 8040.
