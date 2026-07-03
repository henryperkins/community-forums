# Webhook delivery-idempotency report (SLICE-WEBHOOKS SP0)

The outbound webhook engine is an at-least-once durable ledger
(`webhook_deliveries`) drained under a single MySQL advisory lock. This report
records the idempotency guarantees and the automated evidence for each.

| Guarantee | Mechanism | Proof |
|---|---|---|
| Enqueue dedup on `(webhook_id, event_type, event_id)` | `INSERT IGNORE` on `uq_delivery_idem` (migration `0057`) | `WebhookIdempotencyTest::test_enqueue_dedups_on_the_webhook_event_id_triple` |
| Effectively-once on success | `claim()` selects only `status='queued'`; `markDelivered` is terminal | `WebhookIdempotencyTest::test_delivered_row_is_not_reclaimed_on_a_second_worker_run` |
| Dead-letter terminality + explicit replay | `recordFailure(dead=true)` → `status='dead'`; `requeue()` is the only path back | `WebhookIdempotencyTest::test_dead_letter_is_terminal_until_replay_then_delivers_once` |
| No delivery for an SSRF target | `WebhookService::assertValidUrl` → `EgressGuard::validateStatic` at registration; `EgressGuard::validate` at delivery | `WebhookIdempotencyTest::test_registration_rejects_ssrf_url_via_static_egress_guard`; `EgressGuardAdversarialTest` |
| No provisioning while dark | `package_registry`-independent `webhooks` flag gate → 404 | `WebhookIdempotencyTest::test_admin_webhook_surface_is_404_while_flag_dark` |

## Mechanics covered elsewhere (not duplicated here)

Retry/backoff, the consecutive-failure circuit breaker (auto-pause at
`webhooks.circuit_breaker_threshold`), the snapshot `max_attempts` dead-letter
boundary, breaker skip of remaining same-endpoint rows in one run, and
dual-secret rotation signing are owned by
`tests/Integration/Worker/WebhookDeliveryWorkerTest.php`.

## Signature integrity

Delivery signs the exact byte body with `X-RetroBoards-Signature`
(`sha256=HMAC(timestamp . '.' . body)`); rotation emits two comma-separated
signatures during the overlap window. See `WebhookDeliveryWorkerTest`.
