# Account lifecycle restriction reconciliation

Before releasing the lifecycle repair, run these read-only diagnostics against
its deployment database. Save the counts and reviewed IDs with the release
record. An empty test schema is not evidence about existing member accounts.

```sql
-- Accounts whose cached active state disagrees with durable restrictions.
SELECT u.id, u.status, u.suspended_until,
       EXISTS (
           SELECT 1 FROM bans b
           WHERE b.user_id = u.id AND b.scope = 'site' AND b.lifted_at IS NULL
             AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())
       ) AS live_site_restriction,
       EXISTS (
           SELECT 1 FROM account_deletion_requests d
           WHERE d.user_id = u.id AND d.status = 'pending'
       ) AS pending_deletion
FROM users u
WHERE (u.status = 'active' OR (
           u.status = 'suspended' AND u.suspended_until <= UTC_TIMESTAMP()
      ))
  AND (
      EXISTS (
          SELECT 1 FROM bans b
          WHERE b.user_id = u.id AND b.scope = 'site' AND b.lifted_at IS NULL
            AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())
      )
      OR EXISTS (
          SELECT 1 FROM account_deletion_requests d
          WHERE d.user_id = u.id AND d.status = 'pending'
      )
  )
ORDER BY u.id;

-- Pending requests outside the ordinary deletion/moderation states need review.
SELECT d.id AS request_id, d.user_id, d.purge_after, u.status
FROM account_deletion_requests d
JOIN users u ON u.id = d.user_id
WHERE d.status = 'pending'
  AND u.status NOT IN ('pending_deletion', 'banned', 'suspended')
ORDER BY d.user_id, d.id;
```

Every returned row requires targeted reconciliation before release. Inspect the
member's moderation history, live site restrictions, and deletion request audit
history. Preserve the independent restriction: full site bans remain banned;
post restrictions retain their actual expiry (NULL means indefinite). Restore a
deletion hold only when its durable request is confirmed to be intended. A
canceled request must remain canceled. Record the reviewed IDs, justification,
and resulting status in an operator audit entry. Perform any correction within
a transaction, locking shared protected-owner and active-admin rowsets first
when an owner could be lost, then the target user, pending requests, and site
restrictions, each in ascending ID order. Never blanket-reactivate accounts.

The repair prevents new self-service bypasses. It deliberately does not rewrite
legacy inconsistencies automatically: normal writes still use the cached
account state. Recovery refuses a live site restriction even when that cache
says active, and cancellation reconciles any live restriction while removing
only the deletion request.

Purge uses the durable pending request under lock, including its current deadline.
A later ban or suspension cannot strand an otherwise due request. As an explicit
conservative ruling, legacy cached-active or deactivated accounts are not
anonymized automatically; reconcile their request/history first. Canceling a
request always prevents its purge, regardless of cached account status. The
30-day grace and existing anonymization/content-preservation policy remain.

## Suspension and lifecycle precedence

When a site suspension is imposed during deletion grace or self-deactivation,
the cached status stays `pending_deletion` or `deactivated`. The independent
`bans` row and suspension expiry continue to block lifecycle recovery while
live. Expiry never restores ordinary writes through those lifecycle holds:
the member must explicitly cancel deletion or reactivate when permitted. A
moderation lift likewise leaves self-deactivation in place. Ordinary accounts
with only a timed suspension retain automatic expiry behavior.

An existing full site ban takes precedence over a new suspension, including
when the full ban exists only in the durable restriction record and the cached
status is stale. Its cached status remains `banned` for normal write checks and
queued-mail recipient eligibility. This is deliberately not a rule putting
pending deletion above full bans.

## Rehearsal

Use an empty, explicitly provisioned schema named
`retroboards_unified_lifecycle_race` accessible by the configured DB user:

```bash
DB_LIFECYCLE_RACE_DATABASE=retroboards_unified_lifecycle_race \
  MAIL_DRIVER=sendmail MAIL_FROM='' php tests/concurrency/account-lifecycle.php
```

The standalone test applies additive migrations only to that fixed schema,
creates committed uniquely named members, starts a separate PHP process for the
competing service call, observes its `FOR UPDATE` query before committing the
first transaction, and records child exit codes and final write-gate outcomes.
It runs both commit orders for deactivate/ban and cancellation/ban, plus two
owner deactivations. Cleanup deletes only IDs created by that invocation. The
ordinary PHPUnit transaction harness cannot provide this concurrency evidence.

Notification integration must additionally prove that restricted signed-in
members can still reduce owned delivery preferences (N2) and that a real purge's
NULL-recipient outbox row is suppressed as `recipient_missing` while a subsequent
valid row reaches the shared `ArrayMailer` (N3). Never drain these fixtures into
a real mail transport.
