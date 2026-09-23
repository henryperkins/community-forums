# Unified notifications and account settings

The notification center at `/notifications` and the compatible `/?pane=notices`
pane use the same authorized history. Subscription settings remain at
`/settings/notifications`. Member security history, the full event/channel
matrix, quiet hours, preview/test-send, suppression recovery, and email editing
remain outside this repair; see ADR [0014](../adr/0014-member-notifications-and-email-change-carryover.md)
and [0035](../adr/0035-member-settings-completion-carryovers.md).

## Release sequence

1. Quiesce both notification cron commands, `worker:email` and `worker:digest`,
   and wait for running invocations to exit. Retain all outbox rows. Disabling
   the email feature instead can make queued content ineligible and terminally
   suppressed on drain; it is not a transport pause.
2. Run the read-only diagnostics in
   [Account lifecycle: pre-release restriction reconciliation](account_lifecycle.md#pre-release-restriction-reconciliation).
   Reconcile each inconsistent account deliberately before release; never
   blanket-reactivate accounts with live restrictions or deletion requests.
3. Deploy the coherent services, command bindings, templates, JavaScript, and
   generated Imladris assets together. Do not mix old and new worker code.
4. Run the captured-mail rehearsal below on a disposable local database and
   confirm the HTTP/browser gates in the [evidence index](../evidence/unified-notifications-and-settings/README.md).
5. Resume both cron commands. Observe queue state, retry outcomes, and the
   commands' counters without logging message bodies or recipient secrets.

If delivery fails after release, pause mail draining while retaining queued
jobs and the fixed in-app behavior. Do not roll back to the known private-title
or lifecycle-state bypasses. A pause is not permission to mark queued jobs sent
or to bulk requeue suppressed jobs.

## Delivery behavior

Digest scheduling records a fixed UTC window, source IDs/filters, and a maximum
post ID. It commits the outbox row and scheduling watermark together before
checking transport readiness. Payloads contain no rendered private titles or
message bodies. NULL/empty timezones use UTC; an invalid timezone increments
`invalid_timezone` without consuming the window. A scheduler run at or after
the selected local hour can schedule that calendar day's digest (after a DST
gap, or once in a repeated hour). Paused, suppressed, banned and empty windows
are consumed without catch-up mail; repeated runs do not create another
recipient/day job.

Both commands use the same advisory-locked drainer.
`php bin/console worker:email [limit]` drains due instant, digest,
announcement (`system`), and operator test jobs in bounded batches;
`worker:digest` schedules and drains digests, including older due retries. A
transient send failure leaves the row queued until attempts are exhausted:
five attempts by default, with delays of 5 minutes, 15 minutes, 1 hour, then
6 hours. The CLI reports sent, suppressed, retrying, failed and skipped
counts; the digest command also reports scheduled and empty windows.

An unconfigured sender (`MAIL_FROM` absent, `blocked_reason=sender_unconfigured`)
or a required-but-unverified SPF/DKIM domain (`domain_unverified`) is a global
transport block: it consumes no attempt and preserves queued jobs, payloads,
and prior errors. `/admin/email` reports domain status; use **Refresh SPF/DKIM
status** after DNS changes. Configure the sender/domain, then drain again.

Digest counters include decisions made while scheduling and draining. A member
whose new window is suppressed and whose older queued retry is suppressed can
contribute twice to that command's suppression count. Delivery-log status totals
count persisted jobs; they are the source for queue-size monitoring.

Each attempt reloads the recipient and current permissions, account state,
source settings, blocks, and suppression state. Purged/missing recipients do
not abort the batch. Only a successful transport call produces `sent`,
`sent_at`, and a message ID; suppression keeps a reason without a sent
timestamp or message ID. Privacy or preference suppression is terminal;
`invalid_digest_payload`, `unreplayable_legacy_digest`, and `unsupported_kind`
are permanent failures. `/admin/email` and its CSV report attempts, last
attempt, next retry and reason. After fixing a transient failure, requeue
individual replayable Failed jobs there and drain again. Do not use blanket
SQL to requeue terminal rows. Clearing address suppression allows future
eligible mail, not replay of opted-out activity. Operator test sends retain
their separate contract: member pause and address suppression do not gate
them, but missing or banned recipients still do.

Idempotency keys prevent duplicate scheduling, and the advisory lock prevents
concurrent drains. An SMTP server can accept a message immediately before the
process crashes without recording success; a later retry can then duplicate
that message. The database and SMTP do not share a transaction; durable
retries are not an exactly-once delivery guarantee.

## Member controls and recovery

- Authenticated owners can reduce delivery in every account state: turn a
  subscription Off, disable a channel, turn the digest Off, pause email, or
  disable a saved-feed digest. Increasing delivery or changing an active source
  requires normal write permission. Unknown/foreign record IDs return 404.
- An inaccessible subscription keeps a neutral label and an owned opt-out.
  Turning a thread Off persists its override of a board subscription.
- Saved feeds are private owned views. A selected board that becomes
  inaccessible does not expand the feed to all boards. Empty selections retain
  Latest discovery; explicit selections use current read permission within
  those board IDs. Disabled/deleted sources
  do not reappear in a queued digest retry; overlapping eligible sources are
  deduplicated.
- An invalid TOTP confirmation retains pending enrollment. Reloading does not
  disclose the setup secret; explicit restart requires reauthentication.
- Avatar upload/removal preserves the ordinary profile draft. Those fields are
  persisted only by Save profile; a rejected file must be selected again.
- Sessions shows a browser/OS label and retains escaped raw identification in
  details. This device list is not a security-event history.

## Local evidence commands

Use independently provisioned disposable schemas. The browser runner refuses
database names outside `retroboards_unified_e2e` and its suffixed variants,
uses a dedicated local port, resets between suites/projects, captures mail,
and partitions output beneath the evidence root.

```bash
composer build:imladris
composer check:imladris
composer verify:imladris
DB_TEST_DATABASE=retroboards_unified_test MAIL_DRIVER=sendmail MAIL_FROM='' \
  COMPOSER_PROCESS_TIMEOUT=0 composer test

cd tests/browser
DB_DATABASE=retroboards_unified_e2e E2E_PORT=8034 \
  E2E_BASE_URL=http://localhost:8034 npm run evidence:notifications-settings
```

Rehearse workers only against the separately seeded disposable schema; pin
the captured transport on **each** command:

```bash
APP_ENV=test DB_DATABASE=retroboards_unified_workers MAIL_DRIVER=array \
  MAIL_FROM=notification-evidence@example.test php bin/console worker:digest
APP_ENV=test DB_DATABASE=retroboards_unified_workers MAIL_DRIVER=array \
  MAIL_FROM=notification-evidence@example.test php bin/console worker:email 100
```

The reproducible rehearsal resets only an explicitly named disposable worker
schema, seeds due/retry/purged/legacy/suppressed and saved-feed cases, runs the
actual purge/digest/email commands, repeats both delivery commands and verifies
row outcomes:

```bash
DB_DATABASE=retroboards_unified_workers \
  node tests/notification-workers/rehearse.cjs
```

The runner pins `APP_ENV=test`, `MAIL_DRIVER=array` and the dummy From address
for every child process. It writes sanitized commands, exit codes, counters,
rows and outcome assertions to `worker-cli.json` under `RB_EVIDENCE_DIR` or the
combined evidence directory. Provision the schema and its grants first, avoid
running another outbox suite concurrently, and remove this disposable schema
after collecting the report.

Separate CLI processes cannot retain `ArrayMailer` message bodies. Shared
in-process worker tests assert bodies and privacy; CLI evidence records exit
codes, counters, and delivery-row transitions. External OAuth authorization and
real email delivery are not established by these local checks.
