# Thread Intelligence Security and Privacy Evidence

**Scope:** pre-flip graduation evidence for `community_memory` and
`automated_context`.

**Status:** implementation evidence collected; focused command result pending.

## Boundary

- Only eligible post text from live public threads can enter a generation
  request. Private, hidden, pending, deleted, and unreadable posts are excluded
  before the provider call and rechecked before moderation and publication.
- Request-local speaker labels replace account identity. Requests contain no
  username, display name, email, role, IP address, or stable member identifier.
- Related-topic candidates are bounded local public-thread records. Output may
  select only supplied candidate IDs; citations may name only supplied eligible
  post IDs.
- Generated output passes strict structural and Markdown validation, then the
  separate moderation boundary. Unsafe, truncated, stale, malformed, or
  unsupported output never publishes.
- Source visibility and state are checked again at read time. Removing,
  holding, or making a cited source unreadable suppresses the AI brief and AI
  relationship overlay while leaving deterministic public fallback links.
- Durable evidence contains IDs, bounded safe codes, contract metadata, token
  counts, and timestamps. It omits request bodies, response bodies, generated
  text, credentials, fingerprints, and provider response content.
- The OpenAI credential remains environment-only. Provider storage is disabled
  by the request contract. Browser evidence injects deterministic repository
  fakes and performs no provider network call.

## Acceptance Evidence

The accepted live review is
`docs/evidence/phase4-closeout/thread-intelligence-live-eval.md` with its
machine-readable human rubric at
`docs/evidence/phase4-closeout/thread-intelligence-live-rubric.json`:

- selected reasoning effort `low` and output ceiling `16000`;
- 46/46 completed runs;
- 149/149 supported material claims;
- zero incomplete responses, private-sentinel transmissions, ineligible
  citations/relationships, and fabricated decisions.

## Focused Verification

The focused command covers adversarial output, private-sentinel exclusion,
stale publication, concurrent leases/visibility changes, evidence retention,
member-safe rendering, redacted admin evidence, provider/transport pinning,
prompt authority, and output moderation/validation.

Result will be recorded after the fresh command completes. No raw content or
credential material is copied into this evidence record.

## Leakage Review

The final review scans every `thread-intelligence-*` closeout artifact and the
committed browser index for credential-like tokens and prohibited raw-content
field names. The machine gate repeats that scan in
`tests/Unit/Core/ThreadIntelligenceEvidenceMapTest.php`.
