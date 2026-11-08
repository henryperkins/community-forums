# ADR 0059 — APP_KEY rotation through a key ring

**Date:** 2026-11-08
**Status:** Proposed. The key ring core, `key:check`, signed-in resubscription and
send-time link attribution are implemented on `feat/app-key-keyring`. Status
reporting, re-encryption, the retirement and notice commands, the compromise
opt-out route, the container gate and the runbook rehearsal are the next change.
Operator two-factor recovery is a separate change after that. No real key has
been changed.

## Context

`APP_KEY` protects two kinds of data, with two different derivations:

- **Encrypted records** (`SecretBox`, AES-256-GCM): TOTP secrets in
  `user_totp_credentials` and vault versions in `service_secret_versions`.
- **HMAC verifiers:** recovery-code hashes, email-change confirmation hashes, the
  OAuth state cookie and unsubscribe links.
- **Derived identifiers:** passkey sign-in decoys, the Thread Intelligence
  provider latch, the provider safety identifier and the generation request
  fingerprint.

Before this change a new `APP_KEY` made every TOTP secret unreadable and every
verifier unverifiable at once, and there was no way to overlap two keys. PR #97
made the unreadable-secret case fail closed instead of answering 500; it recovers
nothing.

Not affected by `APP_KEY`: CSRF tokens and session receipts (per-session
secret), webhook signatures (vault secrets), and password-reset,
email-verification and invitation tokens (unkeyed SHA-256).

## Decision

### 1. Derivations are preserved exactly

For a configured value `R`:

- **Encryption key `E(R)`:** `trim(R)`. If that is all hex digits and at least 64
  characters long, `E` is `hex2bin` of its first 64 characters; otherwise `E` is
  `sha256(trim(R))` as raw bytes. A value that trims to nothing has no
  encryption key.
- **HMAC key `H(R)`:** `R` itself, byte for byte: no trimming, no case folding.

`.env` values are trimmed by `Env::load`; real environment variables (Cloud Run,
Secret Manager) are not. So one 64-hex key can have several spellings that share
`E` but differ in `H`: uppercase hex, hex digits past the first 64, a trailing
newline, surrounding spaces. A deployment that changed only the spelling kept its
TOTP secrets readable but silently broke every recovery code and link.

Each spelling is handled as its own previous key: keep the historical string,
byte for byte, in a previous-key slot with verification permission. Byte-identical
duplicates are refused. Compatibility tests cover all four spellings end to end
(`AppKeyRingRotationTest`) and the derivations directly (`AppKeyRingTest`).

### 2. Configuration and permissions

| Variable | Meaning |
|---|---|
| `APP_KEY` | The active key. It encrypts, signs, decrypts and verifies. |
| `APP_PREVIOUS_KEY_1` … `_3` | Earlier keys, or during staging an upcoming one. Each is used byte for byte. Every configured previous key may **decrypt**. |
| `APP_PREVIOUS_KEY_VERIFY_IDS` | Comma-separated HMAC key ids (not secrets) of previous keys that may also **verify**. |

A previous key never encrypts or signs, and resubscription is verified only with
the active key. A compromised key or a key restored only to read a backup is
configured as a previous key and left off the verify list, so it decrypts while
another previous key keeps verifying during a planned overlap.

Validation (`AppKeyRing::build`) refuses:

- a slot outside 1–3;
- a blank previous key;
- a previous key without an `APP_KEY`;
- a byte-identical duplicate;
- an HMAC id collision;
- a verify-list entry that is not a 16-character lowercase hex id, names the
  active key, or names no configured previous key.

Errors never contain key material; a key pasted into the verify list is reported
by its position. `php bin/console key:check` prints each key's ids and
permissions and exits 1 on any error or a missing `APP_KEY`. At runtime an
invalid entry is ignored and reported once per process, so a mistake can only
withhold a key or a permission, never grant one.

On Cloud Run each slot maps to an explicit Secret Manager version, never `latest`.

### 3. Key ids

- **Encryption id:** the first 16 hex digits of
  `HMAC-SHA256(key = E(R), "retroboards/app-key-id/encryption/v1")`. It names
  the AES key, so all spellings of one key share it.
- **HMAC id:** the first 16 hex digits of
  `HMAC-SHA256(key = H(R), "retroboards/app-key-id/hmac/v1")`. It names the exact
  HMAC key.

Ids are keyed by the secret, so they can be stored, logged and printed without
revealing it.

### 4. Attribution (migration `0087`)

All five columns are `CHAR(16) NULL`. `NULL` means the row was written before
this release, so its key is unknown.

| Column | Identifies | Written |
|---|---|---|
| `user_totp_credentials.secret_key_id` | encryption id that sealed the secret | every enrollment start |
| `service_secret_versions.key_id` | encryption id that sealed the version | every store and rotate |
| `user_recovery_codes.hmac_key_id` | HMAC id that keyed `code_hash` | every batch issue; legacy batches only after proof (below) |
| `verifications.hmac_key_id` | HMAC id that keyed an `email_change` `token_hash` | every email-change request |
| `email_deliveries.unsubscribe_key_id` | HMAC id that signed the message's unsubscribe link | when the message is handed to the transport |

**Reading:**

- A record with an id is opened or verified only with that key. An id that no
  configured key has raises `SecretUnavailableException`, which the MFA paths
  turn into the recovery-code fallback from PR #97.
- A `NULL` encrypted record is tried against every decrypting key, active first;
  GCM authentication rejects every wrong key.
- A `NULL` verifier is checked under every verifying key.

**Legacy recovery codes keep unknown attribution.** They are never assigned the
current key's id by assumption. When a legacy code verifies under a key, that
proves the key of its whole batch, because one issue hashes the whole batch with
one key. The batch's unknown rows are then attributed to that key. Unused legacy
batches stay `NULL`.

`service_secret_versions.key_version` (always 1, never read) is left as is.
`email_deliveries` rows are never deleted; account purge anonymizes them.

### 5. Consumers across a rotation

| Consumer | Writes with | Accepts | Lifetime of a K1 artifact |
|---|---|---|---|
| TOTP secret, vault version | active key | attributed key, or any decrypting key when `NULL` | until re-encrypted |
| Recovery codes | active key | any verifying key (attributed key only, once known) | until used or reissued |
| Email-change confirmation | active key | any verifying key | `AUTH_EMAIL_CHANGE_TTL`, 3600 s by default |
| OAuth state cookie | active key | any verifying key | 600 s |
| Unsubscribe opt-out | active key, at send time | any verifying key | no expiry; governed by §8 |
| Resubscribe link | active key | **active key only** | as above |

Derived identifiers stay on the active key only, with no overlap. The checked
consequences of a key change:

- **Passkey sign-in decoys.** Decoys and slot order change while real credential
  ids do not. Comparing one email's challenge response from before and after a
  key change therefore separates real credential ids from decoys, revealing
  whether and how many passkeys the account has (pinned by
  `test_passkey_login_decoys_change_with_the_key_while_real_credential_ids_do_not`).
  This is not harmless. Each rotation is one such disclosure event, for an
  observer holding an earlier response. Deriving decoys from a stored,
  rotation-independent seed is an open option (§10).
- **Thread Intelligence latch.** The provider-health latch reads as clear under
  a new key. If the provider failure persists, the next worker run makes exactly
  one request, which latches again under the new key, and later runs make none
  (`ThreadIntelligenceWorkerTest`). The stored latch row is unchanged.
- **Safety identifier.** The OpenAI `safety_identifier` changes, so the provider
  sees a new site identifier. The forum does not store it.
- **Request fingerprint.** `thread_intelligence_generations.request_fingerprint`
  is checked only for presence, so old and new values coexist.

### 6. Stages and runtime transitions

| Stage | `APP_KEY` | Previous keys | Verify list |
|---|---|---|---|
| S0 | K1 | — | — |
| S1a | K1 | K2 | K2 |
| S1 planned | K2 | K1 | K1 |
| S1 compromise | K2 | K1 | — |
| S3 | K2 | — | — |

Two ordering rules, each including every serving revision, every Cloud Run job
execution already started, and queued work:

1. **Before any runtime writes with K2**, every runtime that can still be reached
   must read K2: all are on S1a or later, or have drained. Changing a job
   definition does not change an execution that has already started; wait for
   it to finish or cancel it.
2. **Before migration is declared complete**, every runtime that can still write
   must write with K2, so no runtime still on S0 or S1a remains.

Queued mail does not pin K1: links are signed when a message is actually sent, so
a message queued or retried after the switch carries a K2 link
(`test_messages_record_the_key_that_signed_their_unsubscribe_link_when_actually_sent`).
Confirmations, state cookies and recovery codes keep the key they were made with
until they expire, are used or are reissued.

### 7. Validation, preflight and readability

- **Configuration validity** (`key:check`) says the ring is well formed. It is
  not evidence that stored records are readable, and says so.
- **Readability and obligations** come from `key:status` (next change). It
  separates:
  - encrypted records per key, unreadable records and unknown ids;
  - recovery codes per key: attributed versus unknown;
  - outstanding verifier obligations: unexpired confirmations and the
    unsubscribe overlap boundary.

  A zero count of K1-encrypted rows is one retirement condition, not the only one.
- **Deployment preflight:**
  1. `key:check` passes with the target configuration.
  2. `key:status` shows no unreadable record under it.
  3. Before S1, every reachable runtime reports S1a.

  The container entrypoint will refuse to start on a failing `key:check`
  (next change).

### 8. Retirement of K1 (S3) and the unsubscribe overlap

K1 leaves the ring only when all of the following hold:

1. **Encrypted data.** No encrypted record needs K1, including vault versions
   still in their rotation grace.
2. **Recovery codes.** Codes attributed to K1, and codes of unknown attribution,
   have been handled under the recovery-code policy below.
3. **Expiring artifacts.** At least the email-change TTL and the OAuth state TTL
   have passed since rule 2 of §6 was met.
4. **Unsubscribe overlap.** At least **30 days** have passed since the
   **last-send boundary** for K1 links.

The boundary is the later of:

- the recorded time at which rule 2 of §6 was met for every sender (the
  `worker:email` and `worker:digest` executions included);
- the latest `sent_at` among `email_deliveries` rows with
  `unsubscribe_key_id = K1`, or with `NULL` and `sent_at` set: rows sent before
  this release used the then-active key.

A failed attempt records no key, because nothing is known to have been handed
over. Its time is bounded by the first term, because K1 signs only while K1 is
active somewhere.

**Planned overlap.** K1 opt-out links keep working throughout. Resubscription is
a separate permission:

- A link verified under a previous key opts out but offers no Re-subscribe
  button; it points to sign in instead.
- `POST /resubscribe` accepts only active-key links.
- The new signed-in route `POST /settings/notifications/email/resubscribe`
  undoes only the member's own `unsubscribe` suppression. Bounces and complaints
  stay with the operator.

**Compromise.** Remove K1 from `APP_PREVIOUS_KEY_VERIFY_IDS` immediately. Every
K1 link, cookie, confirmation and recovery code then stops verifying, while K1
still decrypts so records can be re-encrypted. Required opt-outs stay available:

- through signed-in notification settings;
- through a fresh-link request: the unsubscribe page offers to email a new
  active-key link to the address (next change).

Neither route depends on the compromised key or on resubscribe capability.

### 9. Recovery codes, compromise and two-factor recovery

**Recovery codes.**

- Notify affected members before the planned retirement and require
  regeneration by a deadline. Notices distinguish codes known to use the retiring
  key from legacy codes whose key is unknown.
- At retirement an explicit, audited command invalidates the remaining unused
  codes attributed to K1 and those of unknown attribution.
- K1 is not kept online for recovery codes.
- Members who cannot regenerate use their authenticator, whose secret is
  re-encrypted, or the operator-verified two-factor recovery route (separate
  change).

**TOTP after suspected key-plus-database exposure.** Re-encrypting an unchanged
seed does not invalidate a leaked copy. Accounts in the exposure scope must
replace the seed and re-enroll, through a trusted process that keeps two-factor
protection required; never a blanket reset to password-only access. There is no
mandatory-MFA policy today (privileged-MFA enforcement is Gate B, A7 off). The
recovery process still must not leave a protected account on password-only
access.

**Rollback floor.** This release, run with the current stage's key
configuration, is the floor from S1a onward.

- Pre-keyring code reads only `APP_KEY` and ignores the id columns. At S0, where
  every runtime uses K1, rolling back to pre-keyring code is safe in both
  directions (`test_pre_keyring_code_reads_these_writes_only_while_the_key_is_unchanged`).
- Once K2 is in use, pre-keyring code cannot read K2 records. It also restarts
  enrollments without updating `secret_key_id`, which leaves a TOTP row whose id
  names a different key than the one that sealed it. The same test pins that
  hazard.
- After retirement the floor is unchanged: the floor release with the S3
  configuration.

## Consequences

- Planned rotations overlap without downtime for TOTP, vault secrets,
  recovery codes, confirmations, OAuth sign-ins and unsubscribe links.
- A compromised key can be confined to decryption with one variable.
- Pre-keyring readers and the key ring agree on every record written under an
  unchanged key, so this release can roll out and back at S0.
- Recovery codes still cannot be re-keyed (only hashes exist); they need members
  to regenerate. Lost keys remain unrecoverable: nothing here restores material
  whose key is gone.
- Each rotation is a passkey-count disclosure event for observers holding older
  challenge responses until the decoy seed is decided.

## Alternatives considered

- **Key id inside the ciphertext blob.** Rejected: pre-keyring readers in the
  rollout window could not read it.
- **Assigning the current key to legacy recovery codes.** Rejected: unproven, and
  wrong for any member whose codes came from an earlier key.
- **A verify list keyed by slot position.** Rejected: slot reshuffles during an
  incident would silently grant verification. Ids are explicit and checked.
- **Keeping K1 indefinitely for recovery codes.** Rejected by policy (§9).

## Open decisions

- **Compromise opt-out route** for links that no longer verify: the default is
  emailing a fresh active-key link to the address. The alternative is a
  one-page opt-out of the address in the link without verification: same abuse
  surface as the compromised key, never resubscribes.
- **Passkey decoy seed:** keep decoys on `APP_KEY` (each rotation is a disclosure
  event), or derive them from a stored random seed so rotations stop changing
  them. The seed change itself would be a one-time disclosure event.

## Evidence

`docs/evidence/app-key-keyring-2026-11-08/`; review evidence in
`docs/evidence/app-key-rotation-review-2026-11-08/`.
