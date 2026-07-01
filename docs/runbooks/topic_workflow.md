# Runbook — Topic Workflow (`topic_workflow`)

Release/operations runbook for the **topic_workflow** feature (canonical thread
status, status history, per-thread assignment, and per-user snooze).
**Default-ON as of 2026-07-01** (the `topic_workflow` flag graduated out of
deploy-dark); fully reversible via the `features` override. Follows the same
conventions as `docs/PHASE_2_RUNBOOK.md` §2 and mirrors `docs/runbooks/polls.md`.

> **Golden rule:** for any logic defect, **disable the `topic_workflow` flag
> first** (the three POST routes 404, the workflow bar disappears from the thread
> page, the rest of the thread keeps serving), then investigate. Disabling is
> non-destructive — status, history, assignment, and snooze rows are retained and
> reappear when the flag is re-enabled.

## What the flag gates

`topic_workflow` gates the entire per-thread workflow surface. It now defaults
**on** (`FeatureFlags`), so the workflow bar is live on every thread unless an
operator disables it. Schema (`threads.status/status_changed_at/status_changed_by`,
`thread_status_history`, `thread_user.snoozed_until/inbox_note`,
`boards.assignment_mode`, `thread_assignments`, `thread_assignment_history`)
ships in migration `0048_phase4_gate_a.php`.

Routes (all POST, CSRF-protected; gated **in-controller** via
`ThreadWorkflowController::requireWorkflow()`, which 404s when the flag is off):

- `POST /t/{id}/status` — set canonical status (writes `threads.status` +
  `thread_status_history` + a `moderation_log` audit row).
- `POST /t/{id}/snooze` — set/clear a **personal** snooze (`thread_user.snoozed_until`).
- `POST /t/{id}/assign` — assign/unassign the topic (writes `thread_assignments`
  + `thread_assignment_history` + a `moderation_log` audit row).

The read surface — the workflow bar, the action forms, and a collapsible
**status-history** audit list (`.wf-history`) — renders on `GET /t/{id}-{slug}`
only when the flag is on (`ThreadController::show` guards every workflow query
behind `$workflowOn`).

> **Cross-flag behavior (solved-status projection):** `threads.status='solved'`
> is a *projection* of the `community` accepted-answer marker
> (`accepted_answer_post_id`). `SolvedController::accept`/`unaccept` →
> `SolvedAnswerService` → `ThreadWorkflowService::syncSolvedStatus()`, which is
> **gated on `topic_workflow`**: while the flag is dark the sync is a no-op, so
> disabling `topic_workflow` **freezes all workflow status writes** (the ✓
> accepted-answer badge still shows — that is the community solved indicator). If
> answers are accepted/cleared while the flag is off, the status column lags the
> marker; **run `php bin/console repair` after re-enabling** to reconcile it
> (`RepairService::repairThreadStatuses()` maps `open`/`needs_answer` ⇄ `solved`
> from the marker and never clobbers a staff-set `decision_made`/`archived`).

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/PHASE_2_RUNBOOK.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any defect and is non-destructive (all workflow rows are
retained and reappear on re-enable):

```bash
# Roll back: take the workflow surface offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["topic_workflow"]=false; $r->set("features",$f);'
```

Re-enable by setting `topic_workflow` back to `true` or removing the key (the
default is now `true`). If answers were accepted or cleared while the flag was
off, run `php bin/console repair` afterwards to reconcile the `solved` status
projection from the accepted-answer marker (see the solved-status note above).

## Operating semantics (what to tell operators)

- **Status catalogue** — `open`, `needs_answer`, `solved`, `decision_made`,
  `archived` (`ThreadWorkflowService::STATUSES`).
- **Who can change status** — the thread author (OP) may set
  `open`/`needs_answer`/`solved`; `decision_made` and `archived` are **staff-only**
  (admin OR an in-scope board moderator), and once a topic is in
  `decision_made`/`archived` only staff can move it again. Banned/suspended/
  deactivated accounts are blocked by `WriteGate` on **all** workflow writes —
  status, assignment, **and** snooze ("state beats role").
- **Assignment is opt-in per board** — keyed on `boards.assignment_mode`
  (`off` | `self` | `staff`, **default `off`**). While `off`, the assign control
  is inert (a disabled "Assign" affordance) and the route rejects all attempts;
  set a board to `self` (members self-assign) or `staff` (admins/board mods
  assign anyone) to activate it. Assignees must be active and, on private boards,
  a member.
- **Snooze is personal** — it sets a per-user `thread_user.snoozed_until` and has
  **no cross-user effect**; it does not change the topic's status or assignment.
- **No notifications, no hooks/webhooks** — changing status, assigning, or
  snoozing sends the affected users nothing and emits no domain hook/webhook.
  Set expectations accordingly; assignee notification is a possible follow-up.
- **Counters / reconciliation** — there are no `boards.*_count` / `users.*`
  counters for workflow state (single status column, single-row assignment). The
  one reconcilable value is the `solved` status *projection*:
  `RepairService::repairThreadStatuses()` (folded into `repairAll`) rebuilds it
  from the accepted-answer marker, mapping only `open`/`needs_answer` ⇄ `solved`.
- **Progressive enhancement** — the workflow bar is plain server-rendered
  `<select>`/`<input>`/`<button>` forms with no inline script; it works fully with
  JavaScript disabled and under the strict CSP.

## Monitoring & known limits

- **Not separately rate-limited.** Status/snooze/assign have no dedicated
  `RateLimitService` policy. If a board sees workflow-write abuse, disable the
  flag for that release and add a policy before re-enabling.
- **Status history surfaces the last five changes.** Every status change appends
  a `thread_status_history` audit row; `ThreadController` fetches the last five and
  the thread template renders them in the collapsible `.wf-history` list (the
  transition, actor, timestamp, and reason). Older history remains in the table
  but is not shown inline.
- **No repair path for history corruption.** History and assignment history are
  append-only audit; `RepairService` intentionally has no recompute for them, so
  manual corruption is not automatically reconstructable.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppPhase4GateATest.php`
  (`testTopicWorkflowStatusSnoozeAssignmentAndInboxFilters`,
  `testTopicAuthorCannotReopenStaffSetStatus`) — the status/snooze/assignment
  authority matrix and inbox filters; `testSuspendedMemberCannotSnooze` — snooze
  is `WriteGate`-gated; `testStatusHistoryRendersOnThreadPage` — the history list
  surfaces on the thread page;
  `tests/Integration/Core/AppImladrisFidelityTest.php::test_topic_workflow_renders_the_warden_bar_controls`
  — the rendered controls; and
  `tests/Integration/Core/AppFeatureFlagTest.php::test_topic_workflow_is_available_by_default_and_can_be_disabled`
  — default-on plus operator rollback (route 404s when the flag is disabled).
- **Browser:** `docs/evidence/browser/{desktop,mobile}/29-topic-workflow.png` —
  the real server-rendered status → snooze → assign flow (with the status-history
  list expanded) driven as a board moderator (`tests/browser/gate-a.spec.ts`).
- **Accessibility:** `tests/browser/a11y.spec.ts` — axe scan of `.wf-actions`
  (the action forms), `.wf-bar` (the summary), and `.wf-history` (the history
  list), desktop + mobile, no serious/critical violations.
