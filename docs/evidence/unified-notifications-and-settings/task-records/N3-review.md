# N3 independent review

**Reviewed commit:** `b211ea363aa91290a249d15a3683353ccd4a72b2` (20 owned paths).  
**Result:** Approved after correction `92bbe65b70486a846e138b411e991e4f2a4291c6`; the one P2 malformed-payload defect is resolved. No residual grounded N3 finding.

Read N3-brief.md, N3-report.md, the approved combined design, applicable repository instructions, the full runtime/repository/admin/docs diff, and focused regression cases. Excluded parent N6 uncommitted documentation and unfinished A2/A3/A4/N4/N5/A5 work. Saved-feed payload support remains intentionally reserved for A4. No runtime source edits or commits were made.

## Original finding (resolved by `92bbe65b`)

### P2: Make digest payload validation return false for malformed dates instead of throwing

**Primary location:** `src/Service/DigestService.php:47` (date parsing within `validPayload`).  
**Affected callers:** `src/Worker/NotificationEmailWorker.php:86,111-114`; `src/Repository/EmailDeliveryRepository.php:245`; `templates/admin/email.php:185`.

A JSON string containing an escaped null byte is a valid stored JSON value, but PHP's `DateTimeImmutable::createFromFormat()` throws `ValueError` for that string. `validPayload()` currently passes it through without guarding the exception. Consequently, a malformed digest with `window_start_utc` equal to `"2026-09-19 09:15:00\u0000"` is treated as a transient failure and remains Queued with a retry schedule rather than becoming permanently Failed with `invalid_digest_payload`. After retries are exhausted (or with `max_attempts=1`), the Failed row retains the parser error; rendering `/admin/email` calls `canRequeue()`, which calls the same throwing validator and produces HTTP 500. A direct admin requeue POST has the same unguarded validation path.

This violates N3's explicit malformed-job classification and disables the operator log needed to inspect the offending delivery. It requires a malformed stored job; normal scheduler-created dates do not contain null bytes. It is not a claim of an attacker-accessible injection path.

**Reproduction independently executed:**

1. Create an active recipient with `digest_hour=9` and enqueue a digest with the payload below.
2. Queue a valid later system announcement, then run the shared drainer using `ArrayMailer`.
3. The later job sends once, but the malformed digest remains `queued`; returned counts are `sent=1,retrying=1,failed=0` instead of a permanent invalid-payload failure.
4. Repeat with `max_attempts=1`; after draining, log in as an administrator and GET `/admin/email`. Actual status is **500**, expected **200**. Error log identifies `DigestService.php:47` and the null-byte `ValueError`.

```json
{
  "version": 1,
  "max_post_id": 1,
  "window_start_utc": "2026-09-19 09:15:00\u0000",
  "window_end_utc": "2026-09-20 09:15:00",
  "sources": {"subscriptions": [], "saved_feeds": []}
}
```

Make the boolean validator total for malformed JSON-decoded values, for example by rejecting null bytes before date parsing or catching the parser's `ValueError` and returning false. Add regression coverage for permanent worker classification, later-job progress, and non-throwing admin GET/requeue classification of an already failed malformed payload.

## Verification and otherwise satisfactory contracts

Independently reran:

```sh
flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'DailyDigestWorkerTest|NotificationEmailWorkerTest|EmailOpsRepositoryTest|EmailOpsServiceTest|AppAdminEmailTest|AppAccountLifecycleTest|AppNotificationPreferencesRepairTest'
```

**114 tests / 1,029 assertions passed**, 2.304 seconds. Commit diff whitespace check also passed. A temporary two-test malformed-date regression produced **two expected failures / five assertions** (both outcomes above). Temporary test file was removed; its fixtures used the normal rolled-back harness transaction. No real mail or SMTP/network transport was used.

No other concrete N3 defect found. Source inspection plus focused tests substantiate original subscription IDs and fixed UTC/post-ID bounds; fresh read/block/state gates; original explicit/legacy actor provenance; NULL-zone fallback, late cron and DST; transport blocks preserving queue/error/attempts; actual scheduling rollback; batch continuation after missing/banned/malformed ordinary rows; permanent suppressed outcomes; valid admin requeue; and the documented external transport crash boundary. Existing operator diagnostic pause/suppression bypass is preserved intentionally. Container service bindings and both CLI constructions are wired; purge CLI's pre-existing missing required collaborator is corrected within the authorized scope. Browser/actual CLI outcomes reported by the parent were treated as supplied evidence, not independently rerun here.


## Bounded correction re-review

Reviewed the complete four-path correction commit `92bbe65b` without examining unrelated A2/A3 changes. `validPayload()` now catches `ValueError` from either date parser and returns false, so worker classification uses permanent `invalid_digest_payload` and repository/admin replay classification remains non-throwing. The catch is narrow and does not hide unrelated renderer or infrastructure exceptions.

The new tests exercise NUL in each bound, an impossible calendar date, terminal failure metadata, continued delivery of a later valid row, and a historical Failed malformed row carrying an old parser error. The historical row's admin GET returns 200, its Requeue control is absent, and direct POST leaves the complete row unchanged without an audit event. These address both observed failure paths.

Independently reran the same seven-class command above after the correction: **116 tests / 1,060 assertions passed**, 2.481 seconds. Correction commit whitespace check passed. Reviewed the sanitized `n3-review-fix-summary.json` and report addendum; the counts and scope are consistent. No runtime edits, temporary tests, or real mail were needed for this re-review. The parent's additional browser rerun remains separately owned evidence.

**N3 approved at `92bbe65b`.** Combined release/full-suite evidence remains the parent's release gate.
