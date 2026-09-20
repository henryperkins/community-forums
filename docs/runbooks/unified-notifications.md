# Unified notifications and account settings

The notification center at `/notifications` and the compatible `/?pane=notices`
pane use the same authorized history. Subscription settings remain at
`/settings/notifications`. Member security history, the full event/channel
matrix, quiet hours, preview/test-send, suppression recovery, and email editing
remain outside this repair; see ADR [0014](../adr/0014-member-notifications-and-email-change-carryover.md)
and [0035](../adr/0035-member-settings-completion-carryovers.md).

## Release sequence

1. Quiesce both notification cron commands, `worker:email` and `worker:digest`,
   and wait for running invocations to exit. Retain all outbox rows.
2. Run the read-only diagnostics in
   [Account lifecycle restrictions](account-lifecycle-restrictions.md).
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
message bodies. A member with no stored timezone uses UTC. A scheduler run at
or after the selected local hour can schedule that calendar day's digest;
repeated runs do not create another recipient/day job.

Both commands use the shared drainer. `worker:email` drains supported kinds;
`worker:digest` schedules and drains digests, including older due retries. An
unconfigured sender or blocked domain is a global transport condition: it does
not consume an attempt or overwrite a job's reason. Sender failures leave
durable work to retry.

Digest counters include decisions made while scheduling and draining. A member
whose new window is suppressed and whose older queued retry is suppressed can
contribute twice to that command's suppression count. Delivery-log status totals
count persisted jobs; they are the source for queue-size monitoring.

Each attempt reloads the recipient and current permissions, account state,
source settings, blocks, and suppression state. Purged/missing recipients do
not abort the batch. Only a successful transport call produces `sent`.
Privacy or preference suppression is terminal; invalid digest payloads are
permanent failures. Operator requeue is reserved for supported, valid failed
jobs. Do not use blanket SQL to requeue terminal rows.

An SMTP server can accept a message immediately before the process crashes
without recording success. A later retry can then duplicate that message.
The database and SMTP do not share a transaction; durable retries are not an
exactly-once delivery guarantee.

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
