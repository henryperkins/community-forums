# Phase 4 Carryover Partial Stopping Point

Date: 2026-06-29
Branch: `phase4-carryover-completion`
Base: `origin/main`

This branch is a partial implementation of ADR 0003 carryovers. It does not
replace the existing Phase 4 "accepted with deferrals" status, and it does not
claim full Phase 4 carryover acceptance.

## Landed Deploy-Dark Slices

- Added independent dark feature flags for Phase 4 carryovers: link previews,
  expanded files, polls, custom emoji, slash/GIPHY, split/merge, profile media,
  board folders, saved feeds, automated context, and content references.
- Added additive foundation migration `0058_phase4_carryover_foundation.php` for
  link previews, expanded-file scan/quarantine state, polls/votes, custom emoji,
  board folders, saved feed filters, since-last-read context, profile moderation
  markers, and wider custom-emoji reaction storage.
- Implemented badge-rule admin/service coverage for preview, enable, disable,
  backfill, and revoke using the constrained vocabulary `post_count`,
  `thread_count`, `reputation`, and `solved_count`.
- Implemented persisted post reference extraction and read-gated reference cards
  for board/thread/post references.
- Implemented link-preview queueing/fetch metadata service with operator host
  allowlist, egress validation, metadata sanitization, kill-switch behavior, and
  admin purge/refresh routes. Private-board posts and DMs are not fetched.
- Implemented expanded non-image file upload/download gates for PDF and
  text-family files, with content sniffing, scan-pending default, quarantine
  helpers, stale-scan cleanup, download-only delivery, and `nosniff` headers.
- Implemented one-poll-per-thread creation, no-JS vote/close flows, and aggregate
  result visibility after vote or close.
- Implemented operator-managed custom emoji records, shortcode rendering through
  the Markdown sanitizer, and optional custom-emoji reactions.
- Added GIPHY picker configuration endpoint for client-side Search/Trending use
  with public key, rating, attribution, direct-media expectation, and no server
  proxy/cache.
- Implemented private board folders and saved feed filters for personal
  organization only.

## Unfinished Or Partial Items

- Moderator split/merge operations are not implemented in this branch. The flag
  and pre-existing `0048` schema remain available, but there is no dry-run/apply
  service, route, verification, redirect repair, or counter/read-state repair
  flow yet.
- Automated since-last-read context is schema/flag only. No viewer-readable
  assembly service, route, or UI was completed.
- Scheduled related-topic refresh is not implemented. Existing human-curated
  related topics remain the only public path.
- Avatar upload management is not implemented. Existing media/avatar columns and
  new moderation marker columns are present, but there is no upload/remove UI or
  service.
- Signature hardening is incomplete. Existing signatures are already plain-text
  rendered, but new-user threshold, height enforcement, and moderator removal
  flow were not finished.
- Persisted reference extraction currently covers posts. DM-message and summary
  capture/render paths still need wiring.
- Slash menu JavaScript itself is not implemented. This branch only exposes the
  approved insert vocabulary indirectly and the GIPHY configuration endpoint.
- Link preview browser evidence, a11y evidence, crawler evidence, load probes,
  and operator runbook rehearsal are not complete.
- Full Phase 4 closeout docs (`SCHEMA.md`, ADR 0003 full acceptance wording, and
  final evidence bundle) were intentionally not converted to acceptance wording
  because the carryover scope is still incomplete.

## Verification Boundary

Full `./vendor/bin/phpunit` was intentionally skipped at the user's request.
This branch should be treated as focused-test verified only until the remaining
carryovers and release evidence are completed.
