# Complete draft recovery and mobile account release — 2026-10-31

This qualification integrates ADR 0052's complete draft recovery and resumed-send
follow-up, mobile account actions, and the previously released admin theming
changes. The isolated candidate starts from `241dd534`; the dirty primary checkout
and original stash remain protected. Exact application/test inputs are in
[qualified-files.json](qualified-files.json).

## Behavior and scope

- Complete body/wrapper snapshots preserve title, destination, anonymity and
  enabled message fields, including explicit clears and edits during startup.
- Conflict recovery shows both copies and uses the current form/latest compared
  server copy. Saves and completion cleanup use row/revision and complete-state
  checks; newer or recreated drafts survive.
- Local and owned server Resume open the correct composer. A successful unchanged
  resumed send clears its exact source while an uncertain response retains a
  usable retry identity and URL. No-JavaScript server forms remain functional.
- Server-draft save, revision discard and explicit discard now enforce account
  state for members and admins. Suspended/banned accounts retain list/load access
  and their saved content. Expired suspensions allow writes; feature-off behavior
  stays 404. Twenty failing HTTP regressions reproduced the missing gate before
  the fix; the full draft test file then passed 49 tests and 403 assertions.
- Account menus scroll within short viewports, decorative avatar descendants
  leave pointer ownership to the native summary, and Create topic has a 44px
  minimum hit area on narrow/coarse-pointer layouts.

No database migrations or dependency updates are part of this release. The
combined assets retain verified deployed version `573379a015e11e60` for open-page
and lazy-import compatibility; current candidate version is `108dd69071d95f7a`.

## Verification

| Check | Result |
|---|---|
| Full final PHP suite | [3,428 tests, 30,578 assertions](checks/php-full.log), zero failures, one intentional skip |
| Generated-asset freshness | [Passed](checks/assets-check.log) |
| Asset/Worker suite | [35 passed](checks/assets-tests.log), zero failures or skips |
| Focused browser checks | [54 passed](browser-results.json): 50 draft/mobile and four theming cases; zero failures, skips or flakes |

The PHP skip is the existing destructive migration-0077 rehearsal, reserved for
its separately named fixture-free database. No migration inputs changed.

[Browser run details and representative captures](BROWSER.md) describe the final rendered evidence.

The browser selection is recorded in [selection.json](selection.json) and
[selected-tests.txt](selected-tests.txt). It covers both engines, source/rich
recovery, conflicts, board/recipient synchronization, early edits, resumed
completion, lost-response retry, newer drafts, native no-JavaScript forms, mobile
account menus and the shared branding preview/removal surface.

[Prior input comparison](prior-input-comparison.json) identifies which earlier
proof still applies: 20 of 23 relevant source/spec/config files match the October
25 integrated candidate. Differences are the shared app CSS/JS (theming
integration) and DraftController (the new write gate). Historical passes remain
historical; they are not counted as new cases. The prior complete matrix and
limits are summarized in the [mobile evidence capsule](../mobile-ux-remediation-2026-10-25/README.md).

## Evidence handling and boundaries

The [original draft record](../draft-recovery-2026-10-23/README.md) and
[mobile follow-up record](../mobile-ux-remediation-2026-10-25/README.md) are curated
historical evidence capsules. Raw browser reports included inherited execution
environment values, so they are retained only in the private recovery archive.
Published results omit environment/configuration objects and diagnostic payloads;
selected screenshots use synthetic fixture accounts and content.

Browser results are desktop-engine emulation, not physical-phone qualification.
The ten reserved device checks remain incomplete, including native keyboard,
safe-area, screen-reader and release-profile performance coverage. This release
does not upgrade those claims or require the optional hosted browser matrix.

Publication and live deployment receipts are retained separately under
`/home/ubuntu/.local/state/community-forums/draft-mobile-release-20261031/` so
recording deployment proof does not create another release. Local tests use
throwaway databases and do not alter live member drafts or branding settings.
