# Uploaded avatars on every member surface — 2026-09-28

A member who uploaded an avatar still appeared as a monogram everywhere except
their own profile header. `partials/monogram.php` could already draw
`users.avatar_path`, but only `profile/show.php` passed it, and no other query
selected the column. This record covers the correction. It verifies the
working tree on `main` at `7cc156a5` and does not claim a deployment.

## What changed

| Surface | Source of the avatar |
| --- | --- |
| Posts, council stack, inbox preview | `PostRepository::listByThread` / `participantsForThread` → `mask_author()` |
| Topic rows (board, tag, inbox) | `ThreadRepository::listBoardRows`, `TagRepository::threadsForTag`, `ThreadUserRepository::inbox` → `mask_author()` |
| Top bar, admin bar, composer identities | `User::avatarPath()` on the signed-in member |
| Presence rail and `/presence` poll | `UserRepository::presenceRoster` → `PresenceService` members → `app.js` row builder |
| Messages list, letter head, letters, details rail | `ConversationRepository`, `DmMessageRepository`, the direct counterpart's row |
| Leaderboard, follower lists, blocks | `UserRepository::leaderboard`, `ReputationLedgerService::leaderboard`, `FollowRepository`, `BlockRepository` |
| Admin members, bulk confirm, badge preview, provider disable | `UserRepository::directory`, `find`, `BadgeRuleService`, `OAuthIdentityRepository` |

These rules still hold:

- **Anonymity.** `mask_author()` nulls the avatar with the rest of the identity, so an
  anonymous post never wears its author's picture (ADMIN §1.3). The anonymity
  test fails when the mask is broken on purpose.
- **"Show avatars".** It hides uploaded avatars wherever it already hid monograms.
  The preference's coverage is unchanged.
- **The poller mirrors the server.** The rail container states
  `data-presence-avatars`, and a row rebuilt by `app.js` shows the picture, or a
  bare dot when avatars are off. Before this change a rebuilt row always drew a
  monogram, even with avatars off.
- **Same-response redraw.** The settings page re-renders in place after an
  avatar upload or removal to keep the draft. `Session::refreshUser()` re-reads
  the member first, so the top bar shows the new state in that same response.

## Verification

| Check | Result |
| --- | --- |
| PHPUnit, four shards on private databases | 3,121 tests, no failures, one expected skip (the dedicated-database 0077 migration rehearsal) |
| `AppAvatarDisplayTest` | 8 tests. Against the pre-fix sources, 7 fail. The eighth, the anonymity guard, fails once `mask_author()` is made to leak the avatar |
| `AppProfileMediaTest` | 14 tests. The same-response redraw test fails without the `AccountController` refresh |
| `avatar-display.spec.ts`, desktop and phone | 2 passed |
| `users-online-remediation.spec.ts` | 32 passed |
| `profile-surface.spec.ts` | 28 passed |
| `unified-chrome.spec.ts` | 23 passed, 5 skipped. 2 failed: `:358` (`.forum-bar-count` strict mode), which fails identically on an unmodified `HEAD` export |
| `account-settings-repairs.spec.ts` | 56 passed, including all three avatar tests. 10 failed (TOTP, set-password, settings navigation, Security axe), each failing identically on `HEAD` |
| `npm run check:assets` | Current after `npm run build` (new candidate `app-da2b715df2ea1a09.js`; deployed releases retained, ADR 0041) |
| `composer check:imladris` | Current after refreshing the application digest to `5721a05c92004ccb777ffaaae057addf24a84def665309fe7d49fea6d9d35814` |

Browser environment: PHP 8.4.25, MariaDB 11.8, and Chromium through Playwright
1.61.1, on isolated databases and ports, with uploads written outside the
checkout. No WebKit, Firefox, or physical-device verification is claimed.

## Capture method

`expectAvatar()` places a probe span beside each picture. The probe carries the
picture's classes without `avatar-img`, so the context's `.monogram` sizing and
visibility rules apply to it. The picture must then take the probe's exact box,
be round and `object-fit: cover`, and have loaded. Where the probe is hidden,
the picture must be hidden too, as the phone inbox row does. The captures are
viewport shots, because a full-page capture drops touch emulation.

## Captures

| | Desktop 1280×800 | Phone 390×844 |
| --- | --- | --- |
| Settings after upload, with the top bar redrawn | [desktop](desktop/01-settings-upload-and-seat.png) | [mobile](mobile/01-settings-upload-and-seat.png) |
| Topic: opening post and council stack | [desktop](desktop/02-topic-post-and-council.png) | [mobile](mobile/02-topic-post-and-council.png) |
| Board rows | [desktop](desktop/03-board-rows.png) | [mobile](mobile/03-board-rows.png) |
| Inbox row (hidden on phones, like the monogram) | [desktop](desktop/04-inbox-row.png) | [mobile](mobile/04-inbox-row.png) |
| Presence rail | [desktop](desktop/05-presence-rail.png) | [mobile](mobile/05-presence-rail.png) |
| Messages | [desktop](desktop/06-direct-message.png) | [mobile](mobile/06-direct-message.png) |

## Reproduction

From `tests/browser`, with a private database and port:

```bash
DB_DATABASE=retroboards_e2e_<tag> E2E_PORT=80xx UPLOADS_PATH=<scratch>/media npm run evidence:avatars
```

`account-settings-repairs.spec.ts` only runs against a database named
`retroboards_unified_e2e*`, because its fixture refuses any other name.

## Not changed here

- The OAuth avatar import only sets `avatar_source = 'oauth'`. Nothing copies
  the provider picture into `avatar_path`, so those members keep the monogram,
  as before. Gravatar is still unbuilt.
- Uploads are stored at the size they were uploaded, up to 4096 px and 5 MB.
  USER §5.2's resize and thumbnail step does not exist, so a list of large
  avatars downloads them at full size. The pictures are lazy-loaded and served
  `immutable`.
- `--gilt` is an inset ring, which an opaque picture paints over. The OP,
  accepted-answer, profile, and top-three leaderboard avatars lose that ring when
  they are uploaded pictures. That was already true on the profile header.
