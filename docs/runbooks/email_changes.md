# Verified email changes

`email_change` is available and **defaults off**. It adds the Change email address
link under Settings → Account and gates every new route and the service. It is
independent of the notification `email` flag. USER §3.2 and ADR 0055 define the
behavior; ADR 0014 retains the other notification carryovers.

## Enable and roll back

Before enabling, configure a real sender and check its domain readiness using
the existing email operations runbook/console. A request fails with a usable
error while transport is unconfigured or domain checks block sending. Array
mail captures prove local behavior only.

In `/admin/features`, find **Verified email changes** (`email_change`), choose
Enable, review the consequences and confirm. This is an ordinary reversible
feature control. A targeted `settings.features` override is also supported under
[operations §2](operations.md#2-feature-flag-rollback); preserve other overrides.
OAuth-only members need an enabled, configured provider already connected to
their account. Passkeys need the existing `passkeys` flag, HTTPS and matching RP
configuration. Password and connected-provider alternatives work without JS.

Disable the same flag to hide the account entry and reject all email-change
routes, including pending confirmation links. Completed address changes remain.
Pending links retain their expiry and can work again only if re-enabled before
expiry and their credential binding is still current. Existing old-address
security notices remain queued for `worker:email`; rollback does not undo a
completed address change or clear a suppression.

## Member flow

1. The member enters a different address and confirms their current password,
   passkey, or exact connected-provider account. Provider returns use a native
   same-site POST step; they never link/provision a different identity.
2. A one-hour link is sent to the new inbox. The current login/contact remains
   active. The settings page shows the pending destination and UTC expiry with
   a Cancel action. Requesting again replaces the prior link.
3. Opening the link is read-only. A signed-out owner signs in and returns to the
   same confirmation page. A different member cannot preview the destination or
   use the link.
4. Submitting confirmation verifies and switches the new contact, revokes other
   sessions and recovery/registration links, suppresses ordinary queued mail for
   the old contact, queues a security notice to the old address and audits the
   change. The current valid session stays signed in with the new contact.

`AUTH_EMAIL_CHANGE_TTL=3600` configures the lifetime, clamped to 300–86400 seconds.
Named rate policies in `config/config.php`: `email_change` 5/hour/account,
`email_change_destination` 3/hour/hashed destination shared across accounts/IPs,
`email_change_auth` 10 provider setups/15 minutes/account, and
`email_change_confirm` 10/15 minutes/account. Provider delivery budgets are charged
on the verified return immediately before issuing mail, so retaining a signed
start intent cannot bypass them and a normal round trip is not counted twice.
Shared enforcement is as strong as the configured rate-limiter store across
deployed processes.

## Delivery, diagnosis and recovery

- Verification mail uses the existing configured Mailer. A failed send retires
  only that issuance; the form keeps the new address and never repeats a secret
  factor. A later replacement is unaffected.
- The old-address notice is an outbox `system` row with payload
  `{"type":"email_changed"}` and key `email-change:<verification-id>`. It carries
  no new address or token. `worker:email` retries normal transport failures.
  Member pause-all and the notification email flag do not suppress this notice;
  address suppression, domain blocking and recipient eligibility still apply.
- Examine outbox state through the established email tools. A queued notice is
  not proof of sending, provider acceptance or inbox receipt. A send already in
  progress cannot be recalled; normal outbox retry semantics remain.
- `moderation_log.action=email_changed` records actor, target user and verification
  ID without addresses/tokens. Do not add raw verification URLs to evidence or
  application diagnostics. Web-server access logs may contain query tokens;
  restrict their access and apply the deployment's normal retention policy.
- Expired, replaced, cancelled, wrong-owner and credential-invalidated links must
  be requested again. Never edit token hashes, restore a retired recovery token,
  bypass a suppression or change an account contact directly to make a link work.
- After a credential change, a stale pending row can remain visible until expiry
  or cancellation even though its bound token is no longer usable.

Qualification and deployment references are in the
[evidence index](../evidence/email-change-2026-11-01/README.md). Local fixtures use
throwaway accounts and synthetic mail; production default-off proof does not
claim a real member contact change or recipient mailbox receipt.
