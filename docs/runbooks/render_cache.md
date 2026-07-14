# Runbook — Markdown render cache

Operational procedure for inspecting and rebuilding the sanitised HTML derived
from canonical Markdown. This is useful after renderer or allowlist changes, a
restore/import with missing cache values, or an investigation of stale output.

> **Golden rule:** `body` is canonical. This command changes only derived
> `body_html`; it does not edit authored Markdown, timestamps, revisions,
> counters, notifications, or mention events.

## Scope and render context

`repair:render-cache` scans these caches in primary-key order:

- `posts` — mentions linked when the `mentions` feature is enabled.
- `dm_messages` — the same mention-link context as posts.
- `thread_summaries` — rendered without mention links; these are living briefs.
- `post_revisions` — the same mention-link context as posts.

The command builds the same configured renderer as the application. In
particular, the current `mentions`, `custom_emoji`, and `slash_giphy` feature
settings affect output; Giphy images also require the configured public key.
Confirm those settings represent the intended production state before an
execute run. A later feature-setting change can be applied to existing caches by
running the command again.

## Preflight and dry run

Use the normal database-backup procedure before any broad production rewrite,
deploy the new application code first, and check the candidate counts:

```bash
php bin/console repair:render-cache --dry-run --batch=500
```

The batch size may be `1` through `5000`; `500` is the default. Each table prints
`scanned` and `changed`. During a dry run, `changed` means rows whose derived HTML
would differ, and no row is written. Unexpectedly broad counts usually mean the
deployed renderer or feature settings differ from the version that created the
existing cache; investigate before continuing.

## Execute and verify

```bash
php bin/console repair:render-cache --batch=500
php bin/console repair:render-cache --dry-run --batch=500
```

The execute run commits one bounded batch at a time. It compares both canonical
Markdown and the previous cache value before each update, so an edit made after
the batch was read is not overwritten. Such a concurrent row is left for the
next run. The follow-up dry run should report `changed=0` for every table once
writers are quiet.

After execution, inspect representative rich content on desktop and at a narrow
viewport: headings/lists, a fenced code block, a wide table, ordinary images,
custom emoji, spoilers, and mentions. Also check a DM and a published living
brief. The no-JavaScript thread page should present the same server-rendered
content.

## Failures and resuming

Invalid options or a render/write exception exit non-zero. Row-level failures
identify the table and primary-key ID, for example:

```text
Render-cache rebuild failed at posts row 123: ...
```

Fix or quarantine the underlying canonical row, then rerun the same command.
Completed batches remain committed, and unchanged rows are byte-compared and
skipped, so resuming is idempotent. Lower `--batch` if database load or lock
duration is higher than expected.

## Rollback

No canonical content or schema changes need reversing. If the new renderer has a
defect, redeploy the prior application version; its frontend can still consume
the regenerated safe HTML. If exact prior-version output is required, run
`repair:render-cache` again from that deployed version. Missing/blank cache rows
remain readable because member-facing reads render canonical Markdown in memory
without writing on GET.
