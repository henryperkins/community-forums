# Admin console UX audit round 2 — remediation record (2026-07-18)

Companion evidence trail for the round-2 audit's 13 new findings and the one wrong
prior disposition (F24). Deferrals and the two spec-silent decisions live in
**ADR 0023**; the implementation plan is
`docs/superpowers/plans/2026-07-18-admin-audit-round2-remediation.md`.
Numbers below are the round-2 audit's own finding numbers; "F24" refers to the
2026-07 review numbering.

## Finding → disposition

| # | Finding | Disposition |
|---|---|---|
| 1 | Over-length warn/suspend/ban reason or note → 500 | **Fixed** — `requireReason` caps at 255 chars (all reason sinks incl. `moderation_log.reason` are `VARCHAR(255)`), `addNote` caps at 65,535 bytes; 422 with input preserved on `/admin/users/{id}/*` and `/mod/u/{id}/warn`. Tests: `AppAdminUserRecordTest` (`*_over_255_chars_*`, `test_note_body_over_64kb_*`), `AppModUserPanelTest::test_warn_reason_over_255_chars_is_422_not_500` |
| 2 | Deleted posts vanish for staff; Restore UI-orphaned | **Fixed** — staff with delete/restore capability on the board read the with-deleted list variant; soft-deleted replies render as stubs (masked byline, preserved body behind a disclosure, Restore form gated on `core.post.restore`). Member/guest render unchanged. OP/whole-thread restore is out of scope → ADR 0023 deferral #2. Tests: `AppDeletedPostStubTest` (4) |
| 3 | Admin-record warn double-submit stacks warnings | **Fixed** — `idempotency_key` seam ported from the `/mod/u` form; duplicate replays the success redirect, one `warnings` row. Tests: `AppAdminUserRecordTest::test_admin_warn_double_submit_*`, `test_admin_warn_form_carries_an_idempotency_key` |
| 4 | Bulk-users 422 clears row selection | **Fixed** — "Choose a bulk action" re-render re-ticks the selection (`bulk_selected`). Test: `AdminUserBulkTest::test_bulk_422_preserves_the_row_selection` |
| 5 | Explicit identifiers silently rewritten (board slug suffixed, tag slug truncated) | **Fixed** — an explicitly typed taken/reserved board slug → 422 `That slug is already in use or reserved.` (blank slug keeps derive-and-suffix); explicit tag slug > 64 chars → 422. Tests: `AppAdminTest` (2), `AppTagAdminTest::test_explicit_tag_slug_over_64_chars_*` |
| 6 | `dir=sideways` performs a real move | **Fixed** — direction whitelisted to `up`/`down` for board and category moves → 422 `reorder_error`, order untouched. Tests: `AppAdminStructureReorderTest` (2) |
| 7 | /mod posture inconsistent; `/mod/approvals` un-gated | **Fixed** — posture rule (ADR 0023 D1): zero-authority browse → 404 on reports/approvals/appeals/`/mod/u`; actions keep 403. `/mod/approvals` (GET + all four actions) gated on `moderation_queue`; dashboard Approval-hold card/attention follow the flag. Audit-premise correction: pre-fix the un-gated Approval-hold card kept a live pointer with the flag off — "all nav pointers disappear" was not literally true. Tests: `AppModPostureTest` (4); pins updated in `AppModQueueDiscoveryTest`, `AppModerationAppealsTest`, `AppModUserPanelTest` |
| 8 | Residual draft-loss redirects + dead PE hook | **Fixed** (custom-emoji create → dashboard 422 re-render with typed values; email suppress/unsuppress → 422 re-renders, typed address preserved). **Reclassified** (deputy roster: no deputy UI form exists anywhere — flash already carries the error; building the surface is ADR 0023 deferral #3). **Reclassified** (`data-sole-count`: not a PE hook but the lockout-count test anchor — retained with a comment). Tests: `AppCustomEmojiGiphyTest::test_invalid_emoji_input_*`, `AppAdminEmailTest::test_suppress_validation_failure_*` |
| 9 | Custom-emoji silent upsert on duplicate shortcode | **Fixed** — replace stays possible (no separate edit flow) but is reported: "Custom emoji replaced — :x: already existed." Test: `AppCustomEmojiGiphyTest::test_duplicate_shortcode_says_replaced_*` |
| 10 | Dashboard copy (grammar, unlabeled Users number, raw 429 seconds) | **Fixed** — "1 … warning **needs** operator review"; Users card titled "New users today"; central 429 copy now uses `human_duration` ("wait about 58 minutes"), covering announce and every other rate-limited surface. Tests: `HumanDurationTest`, `AppAdminTest::test_dashboard_users_card_*`, grammar-shape assertion in `AppAdminThreadIntelligenceTest` |
| 11 | Field errors unwired console-wide + a11y pockets | **Fixed (largest slice)** — shared `field_error()`/`field_attrs()` helpers (id + `aria-describedby` + `aria-invalid` + autofocus-on-first-error) wired across user_record, tags, structure, board_edit, audit, users_bulk_confirm, email, dashboard settings + emoji, announcements, api_tokens, webhooks, webhook_detail, invitations, branding, badge_rules, roles, providers, provider_disable, package_publisher, package_security, packages. Pockets: aria-labels on placeholder-only password/reason inputs, `scope="col"` + `sr-only` text on empty `<th>`s, provider_disable table wrapped in a labeled scroll region, `role="alert"` on structure/board_edit inline error flashes, `aria-label` on the five bespoke pagers, differentiated row-button labels in all three mod queues (reports labels respect `mask_author`). **Residue (owned, ADR 0023 #4):** `registries.php` + `role_edit.php` inputs not yet helper-wired (duplicate error keys need per-form scoping first). Tests: `AppFieldErrorA11yTest` (2) |
| 12 | Orphan consoles (roles simulator, packages security) | **Fixed** — inbound links from the roles and packages pages. Test: `AppAdminNavIaTest::test_parent_pages_link_their_orphan_consoles` |
| 13 | Console IA vs §9.2 (flat nav, no Moderation entries, no appeals card, bulk actions unowned) | **Fixed** — grouped nav per §9.2 with Moderation entries (Reports queue / Approvals / Appeals, flag-gated to the disabled-span pattern), Appeals dashboard card + attention line. §9.2 "Approval queue" placement decision: ADR 0023 D2. **Recorded** — reports-queue bulk actions deferred with an owner at last: ADR 0023 deferral #1. Tests: `AppAdminNavIaTest` (3) |
| F24 | Email status copy ("Sending is configured…" beside "Set a From address…") | **Fixed — prior disposition was wrong.** The 2026-07-18 remediation record's "Already fixed pre-remediation" row did not hold: the contradiction still rendered (empirically pinned before the fix — the array transport reports configured while email fails closed without a From). `/admin/email` now states one fact per line: transport / From address / sending domain. Tests: `AppAdminEmailTest` (2 new + updated unconfigured-state copy assertion) |

## Post-review hardening (same session, 8-angle diff review)

An eight-angle review of the remediation diff surfaced and fixed, pre-merge:

1. **Staff refocus pagination bug (correctness)** — staff paginate the with-deleted
   stream, but the failed-inline-edit refocus (`PostRepository::pageOfPost`) still
   ranked among non-deleted rows, so a moderator's rejected edit could re-render on
   a page not containing the post. `listByThread`/`countByThread`/`pageOfPost` were
   consolidated behind one `bool $includeDeleted` flag (the `…WithDeleted` clones
   removed) and `ThreadController` passes the same stream everywhere. Test:
   `AppDeletedPostStubTest::test_page_of_post_matches_the_staff_stream_when_deleted_rows_precede`.
2. **Email not-ready live region restored** — the F24 rewrite had dropped the old
   flash's `role="alert"` for the unconfigured-transport state; a flash now precedes
   the facts list when the transport is unconfigured (asserted in `AppAdminEmailTest`).
3. **Emoji form wired like every other form** — the dashboard emoji inputs gained
   `field_attrs` (`err-emoji-*`) to match their `field_error` lines (asserted in
   `AppCustomEmojiGiphyTest`).
4. **`moderation_queue` gate deduplicated** — `Controller::requireModerationQueue()`
   now serves both `ReportController` and `ApprovalController`; the rollback lever
   exists in one place.
5. Minor: duplicate `$domain` alias removed in `admin/email.php`; dead grouping
   writes trimmed in `thread.php`; `field_error(..., alert: true)` replaces the two
   hand-rolled `role="alert"` field errors in `admin/providers.php`.

Reviewer observations accepted without change (recorded here): the thread render's
per-capability authority checks grow by one (`POST_RESTORE`) per staff-relevant view —
consistent with the page's deliberate per-action-capability pattern, with request-scoped
memoization in `BoardAuthority` noted as the future win; `field_attrs` autofocus keys
off error-array order, degrading gracefully when it mismatches DOM order; the
zero-authority-browse rule is expressed per-surface (each site carries an ADR 0023
comment) rather than through one shared helper.

## Browser evidence (DESIGN §13)

Captured live against a private stack (`DB_DATABASE=retroboards_audit`, `:8012` — never
the shared `retroboards_e2e`), Playwright-driven; the deleted-reply pair replays the
audit's own probe (the reply the auditor deleted in `/t/2` was still stubbed, and Restore
returned it):

- `docs/evidence/browser/desktop/r2-01-admin-dashboard-grouped-nav.png` — §9.2 grouped nav, Appeals card, "New users today", singular "warning needs operator review"
- `docs/evidence/browser/desktop/r2-02-email-status-facts.png` — F24 one-fact-per-line status
- `docs/evidence/browser/desktop/r2-03-deleted-reply-stub.png` — staff view of a soft-deleted reply (masked byline · "Removed by a warden" · disclosure · Restore)
- `docs/evidence/browser/desktop/r2-04-reply-restored.png` — the same reply after clicking Restore
- `docs/evidence/browser/mobile/r2-390-05-admin-grouped-nav.png` — 390×844 grouped nav (stacked groups, no horizontal scroll, 44px targets)

## Verification & environment notes

- Suite runs used a session-private test DB (`retroboards_test_r2/r3`): the shared
  `retroboards_test` DB was being reused concurrently by a parallel session, which
  poisoned an initial full-suite run (77 errors) — a clean re-run on the private DB
  was green before any change landed. Related doc fix: `tests/bootstrap.php` reuses
  the schema via a migration fingerprint (`RB_TEST_FRESH=1` forces a rebuild);
  CLAUDE.md previously claimed a drop + re-migrate on every run.
- No schema changes; SCHEMA.md untouched.
