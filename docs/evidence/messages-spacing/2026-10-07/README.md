# Messages text and spacing refinement

Local implementation and browser evidence for the 2026-10-07 request to optimize the Messages surface shown in an iPhone screenshot.

The existing Imladris private-counsel register, routes, shared composer, conversation stream, polling and details behavior remain the design authority. Changes are confined to Messages templates and its scoped presentation rules, with rebuilt delivery assets and refreshed application-presentation checksums.

## Changes

- Align the list header and empty state to the same 16px inset. Use 24px above the empty state, 8px between its heading and explanation, and 16px before its action.
- Replace the awkward first-run explanation with “Your conversations with other members will appear here.” Give the empty-state heading its own semantic h2.
- Name search “Search conversations”, use sentence case for All/Unread, and make search, filters and New message reach 44px. The search input renders at 16px.
- Use the same no-result phrase for server search and instant filtering. Server-rendered Clear search preserves Unread; View all messages returns to the unfiltered list.
- Remove accumulated field margins from the new-message form, give To and Group title consistent labels, and keep the title’s optional status visible after entry. Preserve validation focus and drafts.

## Rendered evidence

| Capture | State |
| --- | --- |
| [empty-phone-day.png](empty-phone-day.png) | First-run list, 390 × 844, light |
| [empty-phone-twilight.png](empty-phone-twilight.png) | First-run list, 390 × 844, dark |
| [empty-small-phone.png](empty-small-phone.png) | First-run list, 320 × 740 |
| [empty-desktop-day.png](empty-desktop-day.png) | First-run list and reading pane, 1440 × 1000 |
| [compose-phone-day.png](compose-phone-day.png) | Enhanced recipient form, 390 × 844 |
| [compose-phone-validation-no-js.png](compose-phone-validation-no-js.png) | Native form validation, draft preserved, optional group title, 390 × 844 |
| [conversation-390-touch-rest.png](conversation-390-touch-rest.png) | Populated conversation and resting shared dock, Chromium mobile |
| [compose-dialog-1440-day.png](compose-dialog-1440-day.png) | Compose dialog, desktop keyboard/focus coverage |
| [empty-phone-webkit-light.png](empty-phone-webkit-light.png) | First-run list, WebKit mobile, light |
| [empty-phone-webkit-dark.png](empty-phone-webkit-dark.png) | First-run list, WebKit mobile, dark |

Browser measurements confirmed a 16px gap between the first-run explanation and action, matching 16px left edges for header and empty-state content, 44px search/filter heights, 16px search type, and no horizontal overflow at 320, 390, 640 and 1440px. The 640 × 400 CSS viewport exercises the layout equivalent of a 1280 × 800 viewport at 200% zoom.

A separate no-JavaScript browser check followed Clear search with Unread active, returned to All, submitted an unknown recipient, and verified the typed message survived the validation response.

## Validation

- Full `composer test`: 3,134 tests, 23,831 assertions, exit 0; one intentional skip for the migration down/up rehearsal that requires its separate `retroboards_thread_intelligence_clean` database.
- `composer verify:imladris`: generated runtime assets current; 24 tests, 305 assertions, exit 0.
- `npm run check:assets`: generated delivery assets and manifest current, exit 0.
- `npm run test:assets`: 24 passed, no failures/skips, exit 0.
- Existing `messages-audit-regressions.spec.ts`, desktop project with test-owned viewports: Chromium 16/16 passed; WebKit 15/16 passed. These cover keyboard/modal focus, touch targets, short/zoomed screens, dark/system-dark themes, recipient fields, labels, receipt display, native replies, and axe checks.
- Focused WebKit first-run capture: 390 × 844 in light and dark; 16px action gap, 44px search/filter heights, no horizontal overflow, zero scoped WCAG A/AA axe violations in either theme.
- Impeccable scoped layout detector: no findings. `git diff --check`: clean.

**WebKit limitation:** P1-2’s outside-tap step navigates from the 422 conversation to Inbox, after the validation error and expanded-dock assertions have passed. The following assertions fail because the conversation elements are absent. A separate run served the original committed fingerprinted stylesheet (`git show HEAD:public/assets/dist/app-style-CfRfJrUW.css`) through a temporary route override and reproduced the same failure and Inbox snapshot. The active mobile conversation template, dock and JavaScript are unchanged by this refinement; the changed list is hidden at that viewport. This establishes that the tap/navigation failure also occurs with the baseline stylesheet. It remains a separate WebKit interaction/test issue; this evidence does not claim the full WebKit suite is green.

## Limits and execution notes

Evidence uses the real local PHP kernel with isolated browser and PHPUnit databases. Chromium and WebKit automation are desktop engines with mobile viewport/touch emulation; this is not physical iPhone Safari verification. These artifacts record local verification. Production deployment has not been verified.

An initial browser capture revealed the old fingerprinted delivery stylesheet was still served; assets were rebuilt before the screenshots above. A repeat of the mutation-heavy browser spec without fresh fixtures encountered two copies of its fixed no-JS test message. Final browser runs reset the isolated database before each engine. The initial full PHPUnit run’s sole failure was the expected presentation-checksum gate; the reviewed baseline and generated runtime manifest were then refreshed before the final suite.
