# A3 implementation report

Status: implementation and focused PHP verification complete in commit 3a25b7af; browser/release gates owned by parent. Source ownership released after the exact-path implementation commit.

## Implemented

- One multipart profile form includes avatar controls, ordinary fields, and all three custom label/value pairs. Upload/remove buttons retain their existing POST routes via formaction and use formnovalidate. File selection is optional for profile save/removal. The visible save button says Save profile; normal successful save retains PRG.
- accountView rebuilds common account data from persistence and merges only allowlisted profile draft fields. Avatar paths, email, identity, role, and other account metadata cannot be overridden by submitted data. Custom drafts are rendered only while their existing feature is enabled.
- Upload/remove success returns 200 with the saved avatar state, unsaved text, and accurate inline status. Avatar validation returns 422 with a field-linked error, file-reselection guidance, and the complete draft. Attachment image errors are normalized to the avatar field at the controller boundary. No profile update happens during avatar operations.
- A hidden, non-focusable default submit preserves native Enter-to-save behavior; the visible Save profile button remains the only submit without formaction. No JS or CSS changes.
- Tests clean their temporary uploads and generated attachment files; database rows remain under the harness transaction cleanup. Existing attachment deletion/finalization behavior remains unchanged.

## Tests and evidence

Initial RED command:

```
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppProfileMediaTest|AppUserSettingsTest|AppProfileFidelityTest'
```

Result: 19 tests / 69 assertions / 7 expected failures, exit 1 (old 303 redirects and separate forms). Initial GREEN: 19 tests / 260 assertions, exit 0.

Additional native-submit RED:

```
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter test_profile_and_avatar_controls_share_one_multipart_form
```

Result: 1 test / 4 assertions / 1 expected failure, exit 1: first submit targeted avatar instead of profile save.

Final GREEN command:

```
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppProfileMediaTest|AppUserSettingsTest|AppProfileFidelityTest|AppFeatureFlagTest|AppAccountConsoleTest|AppMfaTest|AppAccountLifecycle'
```

Result: 130 tests / 1321 assertions, exit 0. No PHPUnit warnings, failures, deprecations, or skips. The feature-flag malformed-JSON test emits its expected application diagnostic. Exact owned-path git diff --check passed.

Saved sanitized evidence: a3-phpunit-red.log, a3-implicit-submit-red.log, a3-phpunit-green.log in docs/evidence/unified-notifications-and-settings. No real mail configured or sent.

Coverage includes invalid/missing/oversized files, valid upload/removal, persisted-avatar trust on both avatar and profile validation errors, no incidental profile writes, three custom field pairs, final profile-save persistence, escaping, overlength/incomplete drafts, restricted states, CSRF, profile-media/custom-field rollback, and retained security/lifecycle behavior.

## Self-review and limits

Reviewed source diff for allowlist ownership, validation errors, write gates, feature gates, HTML form nesting, strict CSP, escaped drafts, profile PRG, and existing A1/A2 behavior. No migrations, runtime service changes, shared CSS, browser files, or parent documentation touched. Full suite, browser evidence, and Imladris reconciliation remain parent-owned release gates. Existing ProfileMediaService attachment finalization/removal lifecycle is retained. A request body discarded by PHP before dispatch (post_max_size) cannot supply a recoverable draft; ordinary application-level file rejection is covered.

## Ownership release

Owned files: src/Controller/AccountController.php, templates/account/settings.php, tests/Integration/Core/AppProfileMediaTest.php, and the three a3-* evidence logs. The orchestration report remains in the existing ignored .superpowers directory, consistent with adjacent task reports. Parent may proceed with independent review and later tasks after the commit.
