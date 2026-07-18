# Runbook — Group DMs (`group_dms`)

Release/operations and **abuse-handling** runbook for the **group_dms** feature:
bounded member-created group conversations over the existing DM substrate —
creation with a title, an owner roster with add/remove/rename/transfer,
per-member mute, leave, membership-interval history, and per-message reporting.
**Default-ON as of 2026-07-18** (ADR 0022; the `group_dms` flag graduated out of
deploy-dark); fully reversible via the `features` override.

> **Golden rule:** for any group-DM abuse wave, moderation defect, or spam
> report you cannot triage fast enough, disable the `group_dms` flag first.
> Group creation is refused server-side again and every group-management route
> returns 404, while existing group conversations stay readable and replyable
> and 1:1 direct messages keep working untouched. Rollback never deletes or
> reverses anything.

## What the flag gates

`group_dms` rides on top of the base `dms` flag — both must be on
(`ConversationController::requireGroupDms()`), so disabling **either** takes the
group surface dark. The narrow flag gates:

- **Creation** — `POST /messages` with more than one recipient or a group title
  is refused with a validation error (422, typed draft preserved) while the
  flag is off; only 1:1 conversations may be started. The group-title field and
  the "separate multiple usernames" hint disappear from the compose forms.
- **Management routes** — all four 404 while the flag is off (the gate fires
  before any lookup):
  - `POST /messages/{id}/members` — owner adds a member by username
  - `POST /messages/{id}/members/remove` — owner removes a member; any member
    removes themselves (leave)
  - `POST /messages/{id}/rename` — owner renames the group
  - `POST /messages/{id}/transfer` — owner hands ownership to a member

**What rollback preserves:** conversations, membership rows, messages, events,
and reports are ordinary data and stay intact. Members can still open an
existing group, read their interval of history, reply, mute, and report — the
flag only stops *new* groups and roster/title changes. This is the
data-preserving rollback posture the acceptance test pins
(`test_group_dms_defaults_on_and_is_operator_reversible`).

## Roll back / re-enable

The flag lives in the `features` setting. Merge the override rather than
clobbering other feature keys:

```bash
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["group_dms"]=false; $r->set("features",$f);'
```

Re-enable by setting `group_dms` back to `true` or removing the key, since the
default is now `true`. `features.dms=false` remains the broader kill switch for
the whole messaging surface (1:1 included) — reach for it only when direct
messages themselves are the problem.

## Operating semantics

- **Bounded rooms.** Participant cap is `dm.group_participant_cap`
  (config key; default 12, clamped to at least 3), enforced at creation and on
  every add. Titles are ≤120 characters (auto-derived from the first
  participants when blank); message bodies ≤5000.
- **Recipient eligibility is checked exactly like a 1:1** at creation and on
  every add: blocks in either direction refuse the pairing, banned/suspended
  accounts cannot be added ("inactive-account rejection"), and a recipient with
  `allow_dms = none` is refused (admins excepted). The sender passes
  `WriteGate` (suspended/banned accounts cannot create groups) and the
  new-account throttle (`dm.new_user_min_posts`, default 1, or
  `dm.new_user_min_age_minutes`, default 1440).
- **Blocks do not silently rewrite an existing group.** Replies inside a group
  are *not* re-checked against later block changes (1:1 conversations keep the
  strict Phase 2 recheck). This is deliberate: a group is a shared room, so the
  affected member's tools are mute, leave, and report — not invisible message
  suppression. Explain exactly this to members who ask why a blocked account is
  still visible in a group.
- **Membership intervals bound what each member can ever read.** Each
  participant row carries `joined_after_message_id` and `left_at`; the message
  view, the conversation-list **preview** (2026-07-18 hardening — the preview
  and list search honour the join boundary like the message view), report
  eligibility, and the departed read-only view all respect the interval. A
  member added today cannot read yesterday's messages; a member who left keeps
  read access only up to departure (and the conversation drops off their
  `/messages` list).
- **One owner, always.** Owner-only actions: add, remove, rename, transfer.
  The owner must transfer before leaving while other members remain (the guard
  flashes "Transfer ownership before the owner leaves."). Every roster/title
  change appends a `conversation_events` row (`created`, `member_added`,
  `member_removed`, `member_left`, `renamed`, `owner_transferred`) rendered as
  the member-visible "Group history".
- **Mute is per-member** (`notification_mode = muted`) and only silences
  notifications; muted members still see the conversation and its unread state
  when they open it.

## Abuse handling (staff)

- **Reports are the only staff window into a group.** A member reports a
  specific message; the report row stores `dm_message_id` and the queue at
  `/mod/reports` shows *that message* with sender/conversation metadata. There
  is **no private-message browser**: staff who are not participants get 404 on
  the conversation itself — by design (ADR 0022), do not "fix" this. Duplicate
  reports by the same reporter against the same message are coalesced while one
  is open.
- **Act on the account, not the room.** From the report, use the normal
  user-moderation tools (warn / suspend / ban). `WriteGate` immediately stops a
  suspended or banned member from posting into any group, and suspended
  accounts cannot be added to new ones. There is no staff route to delete a
  single DM message or dissolve a group; for a hostile room, suspend the
  accounts driving it (members can mute/leave on their own).
- **Rate limits:** message sends share the `dm` policy (default 20 per 10
  minutes per account) and reports the `dm_report` policy (default 10 per 10
  minutes) — both in `config/config.php` `rate_limits`. Tighten `dm` during a
  spam wave before reaching for the flag.
- **Escalation ladder:** tighten `rate_limits.dm` → suspend the abusive
  accounts → `features.group_dms=false` (stops new rooms/roster changes,
  preserves evidence) → `features.dms=false` (all messaging dark) as the last
  resort.

## Monitoring & known limits

- No dedicated worker, queue, or denormalized counter ships with this flag —
  `php bin/console repair` is unaffected. Sends are idempotent
  (`dm_start`/`dm_reply` idempotency contexts), so double-submits replay
  instead of duplicating.
- Notification fan-out is per non-muted member per message; the cap
  (`dm.group_participant_cap`) is the blast-radius bound. Raise it only with
  the notification volume in mind.
- Group email/digest delivery follows the existing DM notification path; there
  is no group-specific email template.
- A member who leaves loses the conversation from their `/messages` list but
  retains the direct URL view of their interval — this is intended (their own
  history), not a leak: content after `left_at` is never shown.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppFeatureFlagTest.php`
  (`test_group_dms_defaults_on_and_is_operator_reversible`) — default-on
  liveness with zero override, owner-route availability, dark-neighbour
  isolation, and the data-preserving rollback (creation 422 + management 404
  while the existing group stays readable/replyable);
  `tests/Integration/Core/AppDirectMessageTest.php`
  (`test_group_list_preview_respects_the_join_boundary`) — the 2026-07-18
  list-preview/search boundary hardening;
  `tests/Integration/Core/AppPhase4GateATest.php` — the Gate A group
  regression set (creation, intervals, owner actions, reports, throttles);
  `tests/Integration/Admin/AppAdminFeaturesTest.php` — the 50/7 defaults
  canary and the readiness declassification.
- **Browser:** `tests/browser/group-dms.spec.ts` (in the standard
  `npm run evidence` set) captures
  `docs/evidence/browser/{desktop,mobile}/group-dms-01…06` — creation with the
  details rail, 422 draft preservation, owner tools + group history,
  the membership-interval view of a late joiner, the departed read-only view,
  and the staff report queue (plus the 404 proof that staff cannot browse the
  room) — and `desktop/group-dms-07-no-js.png` for the JavaScript-disabled
  pass (create, :target rail, mute, report).
- **Accessibility:** `tests/browser/a11y.spec.ts` scans `.dm-compose`,
  `.dm-inforail` (owner tools), and `.dm-threadpane` (report form open) on
  desktop and mobile with no serious/critical axe violations.
- **Decision record:** ADR 0022 (intentional enablement, report-only staff
  access constraint).
