# Workspace evidence cleanup — 2026-09-23

All **86 files** from the original untracked-file inventory are accounted for:
**50 retained** as documentation or evidence, and **36 archived** as scratch.
This cleanup followed the verified Messages merge and push to main.

The [machine-readable inventory](inventory.json) records every original path,
byte count, original SHA-256, disposition, destination, destination SHA-256 and
reason. All 86 original versions also have a verified recovery copy under
`/home/ubuntu/community-forums-archives/2026-09-23-messages/untracked-originals/`.
No original content was discarded.

## Retained documentation and evidence

- The [completed Messages plan](../../../superpowers/plans/2026-09-21-messages-poll-focus-fixes.md)
  is integrated into the release, with results in the
  [Messages verification report](../../messages-release/2026-09-23/README.md).
- The [performance evidence index](../../performance/2026-09-20/README.md)
  organizes the diagnosis, browser/server attribution reports, measured
  aggregates, screenshots, cleanup records and historical production release.
- The page-load performance prompt is retained as a
  [historical session brief](../../performance/2026-09-20/session-brief.md).
  Moving it out of `.github/prompts/` prevents its old build assumptions and
  session-specific authorization text from acting as current instructions.

The 2026-09-20 records were organized, not rerun. Reports now state their
historical scope, and links to archived raw artifacts lead to this inventory.
Original versions remain available for comparison. Published text was checked
for credential URLs, authorization values, GitHub tokens, session-cookie values
and private-key blocks; none were found.

## Archived scratch

The 36 scratch files live under
`/home/ubuntu/community-forums-archives/2026-09-23-messages/scratch/`, preserving
their original relative paths. This local recovery archive has restricted
directory permissions and is not committed to the repository.

It contains raw browser/network/query traces, uncorrelated Worker samples,
one-off measurement and analysis scripts, and `.grok/config.toml` with its
machine-specific skill path. Maintained, already tracked measurement harnesses
remain in place. Find the exact location and checksum of any referenced raw
artifact in [inventory.json](inventory.json).

To recover a file, copy its `destination` from the inventory to a scratch
directory and verify its SHA-256 against `sha256`. Retained documents may have a
different `destination_sha256` because they gained historical notes, archive
links, or completion status; the `original_backup_root` holds their exact
original bytes.

## Messages workspace cleanup

All three original Messages worktrees and the temporary release worktree were
removed after push. Their dirty source changes, unique files, and ignored local
state were backed up before removal; dependency directories can be recreated.
The temporary test databases and their specific grants were removed. The
pre-existing Messages database and main PHP server were preserved. See the
[cleanup receipt](../../messages-release/2026-09-23/cleanup.json).

Hostname-route PR #72 remains open and draft, with its branch and worktree
retained. This cleanup does not verify its Cloudflare prerequisites.
