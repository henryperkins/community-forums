# Verified email-change review

Code review and focused execution found and corrected these failure paths before
publication:

| Boundary | Finding and correction | Evidence |
| --- | --- | --- |
| Provider cookies / GET writes | Apple form_post omits the Lax member session. Initial provider returns now bind the code into signed state and render a private native same-origin POST. Only that POST validates the original actor/session and exchanges/issues. GET never issues a change token. | `AppEmailChangeOAuthTest`; [focused receipt](checks/oauth-rate-green.log), browser provider captures and final provider receipts |
| Recovery issuance | A stale old-address lookup could create a fresh reset token after email confirmation invalidated existing links. Issuance now takes the account lock and rechecks current contact before creating proof. | Real stale-PDO-lookup regression in `RecoveryEmailChangeRaceTest`; [RED](checks/domain-recovery-red.log), [GREEN](checks/domain-recovery-green.log) |
| Recovery completion | A reset could consume its token, wait on a user update, and then change credentials after the email switch. Completion now takes the account lock first, performs a current locked token lookup, validates issuance contact and expiry, and consumes/updates/revokes in one transaction. Registration verification follows the same ordering. | Real subprocess/account-lock regressions; [native prepares](checks/domain-recovery-native-green.log) |
| Destination ceiling | The original subject limiter included actor/IP in its scope. A global normalized destination hash now shares the three-per-hour ceiling across members and IPs in the configured store. | Cross-account/IP regression in `AppEmailChangeTest`; [RED](checks/destination-red.log), final full suite |
| Retained provider intent | A member can retain signed start state and authorize fresh codes during its TTL. Setup now has its own limit, while actual request/destination budgets are charged at the verified return before issuing mail. | A retained-cookie test uses distinct one-use codes, allows three captures and rejects the fourth with 429; [RED](checks/oauth-rate-red.log), [GREEN](checks/oauth-rate-green.log) |
| Privacy headers | Global headers replaced explicit no-referrer. Sensitive pages now retain that stricter policy; weaker values still receive the baseline. | `SecurityHeadersTest` and HTTP/browser header assertions |

The follow-up read-only code review found these paths addressed and no remaining
concrete blocker. It did not claim real-provider execution. Other checks cover
factor/session binding, exact existing OAuth identity, no linking/provisioning,
token ownership, credential changes, expiry/replacement/cancellation, unique-index
rollback, safe validation, CSRF, account suspension, mail failure and outbox retries.

Queued-mail suppression cannot recall a transport send already in progress.
The existing outbox does not guarantee exactly-once SMTP delivery. Address
suppression is preserved, and a queued notice is not proof of inbox receipt.
