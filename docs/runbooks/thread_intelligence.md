# Runbook - Thread Intelligence

This runbook covers the AI-assisted Living Brief worker, its public-only data
boundary, cost controls, recovery paths, evidence retention, and data-preserving
rollback. Run commands from the application root.

> **Pre-flip status:** the implementation and bounded live evaluation are
> complete, but `community_memory` and `automated_context` still default to
> `false`. Do not describe Thread Intelligence as default-on or remove explicit
> test pins until the remaining browser, accessibility, migration,
> backup/restore, security/privacy, concurrency, and rollback evidence is
> accepted. The selected live-evaluation contract is reasoning effort `low`
> with `THREAD_INTELLIGENCE_MAX_OUTPUT_TOKENS=16000`: 46/46 runs completed,
> 149/149 material claims were supported, and there were zero incomplete
> responses, private-sentinel transmissions, or fabricated decisions.

## 1. Safety model

Thread Intelligence may generate only for an eligible thread on a public board.
The enqueue path, pre-provider boundary, pre-moderation boundary, publication
transaction, and render path recheck current visibility and source eligibility.
Deleted or pending threads/posts and private or hidden boards are ineligible.
If a cited source becomes unsafe, the AI brief is suppressed immediately and a
fresh reconciliation is required. A failed attempt never replaces the last
safe publication.

The following data is outside the processor boundary: private/hidden board
content, DMs, reports, moderation notes, account/session data, email addresses,
IP addresses, and credentials. Anonymous public authors are pseudonymous in the
request. The provider receives bounded public post evidence, an optional
curator-authored baseline, and bounded public related-thread candidates. The
model has no tools and cannot mutate posts, moderation state, or other canonical
forum content.

The initial provider uses the OpenAI Responses API for generation and the
Moderations API for output review. Responses requests set `store: false`.
RetroBoards does not retain a raw prompt, raw provider response, duplicate post
body, or unvalidated generated text. It retains only bounded provenance and
usage metadata plus the validated canonical Living Brief. Provider policy still
governs transient processing, so the operator remains responsible for the
appropriate processor agreement and disclosure.

## 2. Environment configuration

The credential belongs in the deployment secret store and is injected through
the environment. It is never stored in the database, displayed by the admin
console, or written to logs. An invalid numeric/model/effort value is replaced
by the named conservative default, not clamped, and produces a redacted operator
warning.

| Environment value | Safe default | Accepted value / effect |
|---|---:|---|
| `OPENAI_API_KEY` | empty | Empty disables provider calls. Supply only from the deployment secret store. |
| `THREAD_INTELLIGENCE_MODEL` | `gpt-5.6-luna` | 1-128 characters from letters, digits, `.`, `_`, `:`, or `-`. There is no automatic model fallback. |
| `THREAD_INTELLIGENCE_REASONING_EFFORT` | `low` | `none`, `low`, `medium`, `high`, or `max`. The selected live contract is `low`. |
| `THREAD_INTELLIGENCE_DAILY_CALL_LIMIT` | `100` | Integer 1-10,000. Counts generation calls, not logical jobs. |
| `THREAD_INTELLIGENCE_DAILY_INPUT_TOKEN_LIMIT` | `1000000` | Integer 1,000-1,000,000,000 reserved/used input tokens per UTC day. |
| `THREAD_INTELLIGENCE_MAX_INPUT_TOKENS` | `32000` | Integer 1,000-1,000,000. The full value is reserved before each generation call. |
| `THREAD_INTELLIGENCE_MAX_OUTPUT_TOKENS` | `16000` | Integer 1,000-100,000. Bounds reasoning plus output at the API; visible output remains capped locally at 450 words. |
| `THREAD_INTELLIGENCE_CONNECT_TIMEOUT_SECONDS` | `5` | Integer 1-30. TCP connection timeout. |
| `THREAD_INTELLIGENCE_TIMEOUT_SECONDS` | `60` | Integer 5-300. Full generation request timeout. |

`THREAD_INTELLIGENCE_REVIEWER_CHANNEL` is optional and is used only by the live
evaluation command. When present it must be a loopback endpoint in the form
`tcp://127.0.0.1:<port>` with a port from 1024 through 65535. Otherwise the
command requires an interactive TTY. It is not a production worker setting.

### Credential rotation

1. Apply the canonical global pause in section 5.
2. Rotate the credential in the deployment secret manager and update every web
   and worker process without printing it to a shell transcript.
3. Restart/reload those processes so they share the new environment.
4. Run `php bin/console thread-intelligence:status` and confirm
   `credential_ready=yes`, the intended model/effort, and no configuration
   warning in `/admin/thread-intelligence`.
5. A model, effort, or credential change produces a new keyed configuration
   fingerprint and clears an old provider latch automatically. If configuration
   is unchanged after repair, use **Retry provider configuration** in the admin
   console.
6. Resume with the canonical write only after the health check passes.

Never paste a credential into a database setting, feature override, incident
note, command argument, or this runbook.

## 3. Feature posture and safe rollback pins

Both product gates are required for provider generation:

- `community_memory` owns Living Brief, summary history, source, related-topic,
  and wiki surfaces.
- `automated_context` owns AI generation and deterministic return/related
  automation.

At this pre-flip stage both code defaults are `false`. Controlled evidence must
enable them explicitly; production remains dark. After a later default-on
graduation, explicit `false` values are the independent rollback pins.

Inspect the override map before changing it:

```bash
php -r 'require "vendor/autoload.php";
\App\Core\Env::load(".env");
$c=\App\Core\Config::fromFile("config/config.php");
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db")));
$f=$s->get("features",[]);
if (!is_array($f)) { fwrite(STDERR,"settings.features is not an object; aborting\n"); exit(1); }
var_export($f); echo "\n";'
```

Pin `automated_context` off without changing any other override:

```bash
php -r 'require "vendor/autoload.php";
\App\Core\Env::load(".env");
$c=\App\Core\Config::fromFile("config/config.php");
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db")));
$f=$s->get("features",[]);
if (!is_array($f)) { fwrite(STDERR,"settings.features is not an object; aborting\n"); exit(1); }
$f["automated_context"]=false; $s->set("features",$f);'
```

Pin `community_memory` off independently:

```bash
php -r 'require "vendor/autoload.php";
\App\Core\Env::load(".env");
$c=\App\Core\Config::fromFile("config/config.php");
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db")));
$f=$s->get("features",[]);
if (!is_array($f)) { fwrite(STDERR,"settings.features is not an object; aborting\n"); exit(1); }
$f["community_memory"]=false; $s->set("features",$f);'
```

Do not replace the entire `features` object. A fresh object would discard
unrelated rollback pins and could re-enable another default-on subsystem.

After an approved restoration, remove only the named override. Run once per
flag, changing `$flag` between `automated_context` and `community_memory`:

```bash
php -r 'require "vendor/autoload.php";
\App\Core\Env::load(".env");
$c=\App\Core\Config::fromFile("config/config.php");
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db")));
$f=$s->get("features",[]); $flag="automated_context";
if (!is_array($f)) { fwrite(STDERR,"settings.features is not an object; aborting\n"); exit(1); }
unset($f[$flag]); $s->set("features",$f);'
```

Before the default flip, removing either override restores its current `false`
code default; it does not enable the feature. Explicit `true` pins are reserved
for controlled pre-flip testing.

## 4. Worker schedule and heartbeat

Schedule the bounded worker **at least once per minute**. One run processes 25
jobs by default; an optional limit is clamped to 1-100. Multiple workers are
safe because claims use `SKIP LOCKED`, random ten-minute leases, and
compare-and-set release.

```cron
* * * * * cd /srv/retroboards && /usr/bin/php bin/console worker:thread-intelligence 25 >> storage/logs/thread-intelligence-worker.log 2>&1
```

Each invocation first settles abandoned request metadata, then advances one
bounded board-visibility sweep batch, then claims due generation jobs when both
flags, credential, global pause, provider health, and budget permit. Keep the
schedule running during a feature rollback so heartbeats, abandoned-attempt
settlement, and board sweeps continue; generation remains gated.

`thread-intelligence:status` classifies the latest heartbeat as follows:

| Classification | Meaning / response |
|---|---|
| `never_run` | No worker heartbeat exists. Install or repair the schedule. |
| `running` | A run started within the ten-minute lease window. |
| `interrupted` | A `running` heartbeat is older than ten minutes. Inspect the process and transport timeout; expired leases are recoverable on a later run. |
| `healthy` | The latest run completed `ok` within five minutes. |
| `stale` | The latest `ok` completion is older than five minutes. Repair scheduling or worker startup. |
| `attention` | The latest run completed `error`. Inspect the bounded exception/log and admin warnings. |
| `invalid` | The stored heartbeat object is corrupt. Preserve it for incident review, then allow the next worker start to replace it. |

Worker logs and the admin ledger may include IDs, states, durations, token
counts, and safe failure codes. They must not include prompts, responses, post
bodies, generated text, credentials, or raw user identifiers.

## 5. Canonical global pause and resume

Prefer the audited **Pause generation** / **Resume generation** controls at
`/admin/thread-intelligence`. For console-only recovery, use the same typed
repository write. The only valid stored values are the JSON strings `"1"`
(paused) and `"0"` (resumed). Missing means resumed; JSON numbers, booleans, or
any other present value fail paused as corrupt.

Pause:

```bash
php -r 'require "vendor/autoload.php";
\App\Core\Env::load(".env");
$c=\App\Core\Config::fromFile("config/config.php");
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db")));
$s->set("thread_intelligence_generation_paused","1");'
php bin/console thread-intelligence:status
```

Resume (also repairs a corrupt pause value):

```bash
php -r 'require "vendor/autoload.php";
\App\Core\Env::load(".env");
$c=\App\Core\Config::fromFile("config/config.php");
$s=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db")));
$s->set("thread_intelligence_generation_paused","0");'
php bin/console thread-intelligence:status
```

The brake prevents new provider work and publication without hiding the last
good Living Brief. It does not delete jobs, attempts, summaries, citations, or
relationships. An already-running transport cannot be recalled, but the worker
rechecks the brake before the next external boundary and before publication.

## 6. Status and maintenance commands

```bash
php bin/console thread-intelligence:status
php bin/console worker:thread-intelligence [limit]
php bin/console thread-intelligence:retry <thread-id>
php bin/console thread-intelligence:reconcile <thread-id>
php bin/console thread-intelligence:prune-evidence [limit]
APP_ENV=testing php bin/console thread-intelligence:evaluate-live --confirm-live
```

- `status` is redacted: effective flags, credential readiness, pause/provider
  state, heartbeat classification, queue counts, model/effort/prompt version,
  and daily budget/next reset. It never prints a credential or fingerprint.
- `retry` recovers eligible `dead`, `review_required`, or interrupted work but
  still honors current visibility, flags, pause, provider health, budget, hourly
  cadence, and active leases.
- `reconcile` applies the same gates and additionally requests a full evidence
  rebuild. Use it after a source edit/delete, visibility repair, or lineage
  correction. Neither command bypasses a provider latch or per-thread pause.
- `prune-evidence` defaults to 500 and is capped at 500 rows. It is independent
  of flags, credential, provider latch, and global pause, and makes no provider
  call.
- `evaluate-live` refuses `APP_ENV=production`, requires the exact confirmation
  switch, a configured credential, a locked synthetic corpus, and an interactive
  reviewer or loopback reviewer channel. Run it only against a dedicated
  non-production database. It compares `none` and `low`, writes only the redacted
  report/rubric under `docs/evidence/phase4-closeout/`, and incurs real provider
  usage outside the worker's daily budget ledger.

The accepted 2026-07-12 UTC live result is `low` / `16000`, 46/46 completed,
149/149 supported, with zero incomplete responses, private-sentinel
transmissions, and fabricated decisions. Do not change effort or ceiling without
rerunning the complete guarded evaluation and recording a new accepted result.

## 7. Budget behavior

The worker enforces two independent capacity layers:

- **Hourly:** one successful generation per thread per hour. A curator refresh
  bypasses post-delta and quiet-window checks, but not this hourly limit. Status
  and the UI return the next eligible UTC time.
- **Daily:** each generation request atomically reserves one call plus the full
  configured `MAX_INPUT_TOKENS`. Reconciliation refunds unused input tokens from
  reported usage; unknown usage is charged at the full reservation. A logical
  reconciliation can require up to four generation calls. Moderation is a
  separate safety request but is not counted as a generation-call reservation.

Daily counters use the UTC date in
`settings.thread_intelligence_daily_budget`. Exhaustion defers work without
incrementing failure attempts. `thread-intelligence:status` prints
`budget_next_reset_at=<time> UTC`; the next reset is 00:00:00 UTC. A prior-day
canonical row rolls over on the next reservation.

If the budget object is corrupt, eligibility reports `budget_invalid` and
generation fails paused rather than silently resetting spend:

1. Apply the global pause and preserve the raw row with the incident evidence.
2. Do not zero counters mid-day unless provider-side usage proves the exact
   values. The safest repair is to keep generation exhausted for the rest of
   the UTC day by writing a canonical current-day object whose `used_calls` and
   `used_input_tokens` equal the configured limits, with both reserved counters
   zero.
3. Confirm the admin budget warning clears and the object remains exhausted.
4. After the displayed UTC reset, allow the next reservation to roll the row to
   fresh counters, then resume generation.

If exact same-day usage is known, an operator may reconstruct the canonical
five-field object with those conservative counts instead. Never delete or reset
an uncertain current-day row simply to make work run.

## 8. Evidence retention and pruning

- Published-generation metadata is retained for the lifetime of its source
  thread; physical thread deletion owns the cascade.
- Unpublished `succeeded`, `retry`, `failed`, `rejected`, and `stale` attempts
  become prune-eligible 90 days after completion.
- Evidence behind a current `dead` or `review_required` job is retained until
  resolution, then for at least 90 quiet days.
- `requested` attempts are not deleted by the prune command. The worker first
  settles abandoned requests after their lease can no longer be active.
- Pruning removes attempt-ledger rows only. It never deletes jobs, summaries,
  citations, or member content.

Run one bounded batch daily and repeat manually until `pruned=0` after a backlog:

```cron
17 3 * * * cd /srv/retroboards && /usr/bin/php bin/console thread-intelligence:prune-evidence 500 >> storage/logs/thread-intelligence-prune.log 2>&1
```

Keep this schedule active during provider outages and feature rollback; the
retention obligation is deliberately flag- and provider-independent.

## 9. Board visibility sweeps

A board visibility change sets a durable cursor:

- `NULL`: no sweep pending;
- `0`: start from the first thread; and
- positive ID: last committed thread.

Every worker invocation advances at most 250 threads for one marked board before
normal claims. Public transitions queue a full reconciliation after 15 minutes;
private/hidden transitions idle only queued/retry jobs. Running, paused,
`dead`, and `review_required` states are preserved, while render and pre-egress
checks suppress unsafe AI content immediately.

The batch owns one transaction. If the process is interrupted, both job changes
and cursor movement roll back; the next minutely worker resumes from the last
committed ID without skipping or duplicating work. Concurrent sweeps skip a
locked board. Do not clear or advance the cursor by hand. For a large board,
leave the worker scheduled until the cursor returns to `NULL`.

## 10. Failure recovery

### Missing credential

Expected state is `credential_ready=no`; generation defers as
`credentials_missing` (the retry policy also recognizes `missing_credential`),
no provider call is made, and queued work is preserved. Manual community memory
and deterministic return context can still operate when their owning flags are
enabled. Install/rotate the credential through the secret manager, restart
workers, verify status, and let the next run claim due work. Do not place the key
in the database.

### Provider latch

Authentication or invalid-model failures move the triggering job to
`review_required` and defer site-wide claims as `provider_blocked`. Corrupt
provider-health metadata reports `provider_health_invalid`. Correct the
credential, model, or effort first. A changed configuration fingerprint clears
the latch; otherwise use the audited **Retry provider configuration** action.
Then use `thread-intelligence:retry <thread-id>` for the triggering job. Never
clear the latch repeatedly without repairing the cause.

### Corrupt pause or provider-health setting

An invalid pause reports `generation_pause_invalid` and fails paused; repair it
with the canonical `"1"` write, inspect the incident, then use canonical `"0"`
to resume. Invalid provider-health data fails blocked. Preserve the row, verify
the deployment configuration, then use the audited provider retry action to
write the canonical cleared state.

### `dead` or `review_required`

Transient transport/rate-limit/unavailable failures retry at approximately 1,
5, and 30 minutes, then 2 and 6 hours, with deterministic jitter; a valid
`Retry-After` may extend a delay to 24 hours. After five transient retries the
job becomes `dead`. Authentication/model, repeated truncation/schema/validation,
moderation-flagged, and over-four-window evidence failures become
`review_required`. Inspect the safe failure code, correct its cause, then use
`retry`; use `reconcile` when all current evidence must be rebuilt. The last good
brief and relationships remain unchanged.

### Truncation or schema/validation failure

`output_truncated`, `schema_invalid`, and `validation_failed` publish nothing,
retry once after five minutes, and then require review. The accepted
`low`/`16000` contract had zero incomplete responses. Confirm the deployed model,
effort, output ceiling, and prompt version. Do not raise the ceiling or reasoning
effort ad hoc; rerun the full live gate before changing the production contract,
then retry or reconcile the affected thread.

### Moderation

A `moderation_transport` failure is transient. A `moderation_flagged` output is
recorded as `rejected`, moves the job to `review_required`, and is never
published. Do not bypass moderation. Review the public source material and forum
policy; a curator may publish a sourced manual brief or retire the last brief.
Reconcile only after the content/policy cause is resolved.

### Stale or removed source

Source edits/deletes and visibility changes mark reconciliation work;
`stale_evidence` requeues from current state. If any cited source is currently
deleted, pending, or unreadable, the AI brief and AI relationship overlay are
suppressed even when they were the last good result. Run `reconcile` if durable
work is stuck, then let the worker rebuild only from current eligible public
evidence. Do not restore an unsafe generated version to work around the render
gate.

## 11. Curator and administrator controls

Authorized curators (admins and in-scope board moderators) may publish/edit a
manual sourced brief, refresh, retire, restore a prior version, curate related
topics, and pause/resume automation for the thread. A human edit becomes the
baseline for the next generation. Retiring a brief also pauses that thread;
restoring a version does not silently resume automation. Resume is a separate,
explicit action that requeues current work. Curated related-topic rows outrank
AI overlays and are never overwritten by generation.

Administrators use `/admin/thread-intelligence` for the redacted health/budget
dashboard, global pause/resume, provider-latch retry, and per-thread
retry/reconcile/pause/resume. All mutations are POST + CSRF protected and
audited. Members see only attribution, version/update time, cited public source
links, safe related-topic links, and the processor disclosure; provider/model,
usage, response IDs, and failure details stay administrator-only.

## 12. Data-preserving rollback and restore

Production rollback does **not** run migration `0077` down. Nullable AI
authorship and published lineage cannot be losslessly reversed after data
exists. Keep the additive schema and use this order:

1. Set the canonical global pause. This stops new provider work while the last
   safe Living Brief remains readable.
2. Pin only `features.automated_context=false`. This stops AI and deterministic
   automation while manual memory remains available if `community_memory` is
   still enabled.
3. Pin only `features.community_memory=false`. This hides/freezes memory, wiki,
   source, and related-topic surfaces without deleting their rows.
4. Remove the provider credential from the deployment environment and restart
   web/worker processes. Keep evidence pruning and the worker schedule in place.

Verify after every step with `thread-intelligence:status`, the admin console, and
row counts for jobs, generations, summaries, citations, and relationships. None
should decrease as a result of rollback.

To restore after an approved release, reverse the environment/flag controls:
restore the credential and validated config, remove only the two named rollback
overrides when their code defaults are approved to be on, verify provider and
budget health, then write canonical resume. Run one worker pass and confirm the
same last-good version is still present before allowing new generation.

## 13. Evidence pointers

- Decision: `docs/adr/0019-thread-intelligence-auto-publication.md`
- Detailed design: `docs/superpowers/specs/2026-07-09-thread-intelligence-graduation-design.md`
- Live result: `docs/evidence/phase4-closeout/thread-intelligence-live-eval.md`
- Human rubric: `docs/evidence/phase4-closeout/thread-intelligence-live-rubric.json`
- Phase 3/4 carryover ledger:
  `docs/evidence/phase4-closeout/phase3-4-closeout-ledger.md`
