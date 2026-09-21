# Messages refinement — 2026-09-21

Implemented from the critique-fixed source committed at `31968be5` in
`.impeccable/mocks/messages/`, with `.impeccable/surfaces/messages.md` as the
approved surface brief. No content was recovered from the older Claude URL.

## Implementation

| Slice | Production integration | Evidence |
| --- | --- | --- |
| Reading room | Narrow list, widest conversation, docked shared composer, theme-aware river surfaces, details closed until explicitly opened | `conversation-*.png`, `laptop-details-drawer.png` |
| Reading history | Local day/clock labels with exact UTC attributes; author runs; beginning/join boundary; earlier/latest navigation; direct receipt beneath the last own letter, including without JS | `AppDirectMessageTest`, `AppMessagesRefinementTest`, conversation captures |
| Live letters | Participant-only, CSRF-protected POST poll every 20 seconds; after-id catch-up capped at 50; no scroll or draft loss; new-message pill; pause/resume/backoff/404 lifecycle | `live-new-messages.png`, polling browser test |
| Navigation and presence | Messages unread badge travels with the existing bell poll; header and member presence use the existing privacy ladder | List/conversation captures, privacy and unread integration tests |
| Recipients | Keyboard-accessible chips and eligible suggestions; canonical comma-separated field; title appears for multiple recipients; plain field without JS | `new-recipient-chips.png`, axe checks, picker journey |
| Feedback and compatibility | First-run invitation and eligibility notice; originating dialog/page preserved at 422; group tools, reports, account write restrictions and rollback history retained | `first-run-*.png`, `dialog-validation-draft.png`, `phone-validation-no-js.png`, group browser suite |

## Reference adaptations

- The existing shared composer remains intact, including its formatting, preview,
  drafts, uploads, and standalone rich-editor behavior. The mock's stripped-down
  composer was a visual fixture, not a replacement editor.
- Polling is POST, not GET, because acknowledging returned messages updates a
  read watermark. It uses the existing CSRF gate.
- Existing groups remain readable and replyable after `group_dms` rollback
  (ADR 0022). New group polling stops on 404; manual reload remains available.
- The newer mock's 1400–1699px correction is retained: with the board rail open,
  details remain a drawer so the conversation does not collapse.
- Real names, membership, unread state, timestamps and notification counts replace
  fixture values. Neither the mismatched Nimrodel/@alice identity nor an unread
  active conversation was copied.
- Small list/rail labels use stronger semantic ink where the mock's tint caused
  a WCAG AA contrast miss. Own-letter gold wash stays the approved DM exception.
- Without JavaScript, the established board navigation is in document flow;
  full-page captures include it above the full-width Messages surface.

## Reproduce

Use a dedicated disposable database and run `tests/browser/prepare.sh` first.
The evidence run used `retroboards_messages_e2e`, isolated rate-limit/package
directories, and the Playwright-managed local server at `localhost:8013`.

```sh
APP_ENV=test MAIL_DRIVER=sendmail MAIL_FROM='' COMPOSER_PROCESS_TIMEOUT=0 composer test
composer verify:imladris
npm run check:assets
npm run test:assets

DB_DATABASE=retroboards_messages_e2e \
RATELIMIT_PATH=storage/ratelimit-e2e-messages \
PACKAGES_STORAGE_PATH=storage/packages-e2e-messages \
E2E_BASE_URL=http://localhost:8013 E2E_SKIP_WEBSERVER=0 \
npx --prefix tests/browser playwright test --config tests/browser/playwright.config.ts \
  messages-refinement.spec.ts group-dms.spec.ts a11y.spec.ts \
  --grep 'Messages|reference states|conversation poll|group DM|group DMs' \
  --project=desktop --project=mobile
```

The mail environment keeps the default fail-closed, unconfigured transport in
PHPUnit; it avoids inheriting a workstation's configured outbound mail service.
Rich-editor regression coverage is the `DM and edit` case in
`composer-expansion.spec.ts`, run on desktop and mobile. Run `prepare.sh` again
before the separate legacy or rich-editor commands: the legacy journey expects
an empty direct conversation, and the rich-editor fixture changes feature flags.

`reference-*.png` are local renders of the committed mock, not production.
Other PNGs are captures of the actual PHP application. Group workflow captures
are refreshed in `docs/evidence/browser/{desktop,mobile}/group-dms-*.png`.

## Verified results

Executed on 2026-09-21 against the implementation in this working tree:

| Gate | Result |
| --- | --- |
| Full `composer test` | 2,978 tests, 22,476 assertions; no failures; six existing PHP 8.5 deprecations and one skip |
| `composer verify:imladris` | Runtime assets current; 24 tests, 296 assertions |
| `npm run check:assets` | Generated assets and manifest current |
| `npm run test:assets` | Eight passed |
| Messages refinement + group workflows + group axe | Ten passed in 56.1 seconds; six intentional duplicate viewport skips |
| `dm-reimagine.spec.ts` | Two passed; captures under `legacy-regression/` |
| `composer-expansion.spec.ts --grep 'DM and edit'` | Two passed, desktop and mobile |
| `git diff --check` | Clean |

The refinement spec runs once in the desktop project; its visual journeys
explicitly exercise desktop, laptop and phone widths. Its five duplicate mobile
project instances are skipped. The sixth skip is the already-covered
no-JavaScript group journey. This is focused browser regression coverage, not
a claim that the entire browser suite ran.

The PHP deprecations concern existing Reflection/PDO-MySQL/GD API usage, outside
the Messages changes. No new schema migration or feature flag was introduced.

After capture, `prepare.sh` restored the dedicated `retroboards_messages_e2e`
schema and fixtures and cleared its isolated rate-limit/package stores. The
managed application server and local mock server were stopped. No production
data or developer application database was changed.

## Independent review

The Impeccable finish review returned **ship** after the approved DM own-letter
gold-wash exception was recorded in `DESIGN.md` and `.impeccable/design.json`.
Type, material, theme grounds, list/conversation proportions, details drawer,
presence, validation and first-run hierarchy matched the selected reference.
The shared composer and real fixture data remain the documented adaptations.
No visual material fixes remain.

The separate code review checked participant/privacy gates, membership bounds,
read watermarks, receipts, eligibility and draft preservation. Its one important
finding was a delayed recipient lookup reopening an already-committed field.
The browser regression reproduced this before the fix (`aria-expanded` became
`true` after committing `bob`); after cancellation and sequence invalidation,
both Enter and comma commits reject that stale response and a second Enter
leaves the canonical recipient list unchanged. No other material findings were
reported.

Final delivery assets: `app-cf5e17b75386c087.js`, `app-style-D3jpe2kz.css`.
Application-surface baseline:
`9487b34ff667d61d95e8be4940d11769ecc30afaf2021663cb9733b87b9b67c8`.

## Release preflight

Before releasing on 2026-09-21, fast-forwarded to upstream `d866d994` (the
independent canonical-host redirect change) and re-ran the full PHP suite:
2,979 tests, 22,482 assertions, no failures, the same six deprecations and one
skip. The integrated asset/Worker suite passed all 15 cases. Asset reproducibility
and the 24-test Imladris gate also passed. The Messages commit adds no migrations
and does not change the Worker, deployment configuration or container entrypoint.
