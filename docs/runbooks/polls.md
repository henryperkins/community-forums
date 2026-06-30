# Runbook — Polls (`polls`)

Release/operations runbook for the **polls** feature (one-poll-per-thread,
no-JS create / vote / close / result). **Default-ON as of 2026-06-30** (the
`polls` flag graduated out of deploy-dark); fully reversible via the `features`
override. Follows the same conventions as `docs/PHASE_2_RUNBOOK.md` §2.

> **Golden rule:** for any logic defect, **disable the `polls` flag first**
> (routes 404, the panel disappears, the rest of the thread keeps serving), then
> investigate. Disabling is non-destructive — poll rows and votes are retained
> and reappear when the flag is re-enabled.

## What the flag gates

`polls` gates the entire poll surface. It now defaults **on** (`FeatureFlags`),
so the panel is live wherever a poll exists unless an operator disables it.
Setting `polls` to `false` returns `404` on all three POST routes and renders no
poll panel. Schema (`polls`, `poll_options`, `poll_votes`) ships in migration
`0058_phase4_carryover_foundation.php`.

Routes (all POST, CSRF-protected): `/t/{id}/poll` (create),
`/polls/{id}/vote`, `/polls/{id}/close`.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/PHASE_2_RUNBOOK.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any defect and is non-destructive (poll rows and votes are
retained and reappear on re-enable):

```bash
# Roll back: take polls offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["polls"]=false; $r->set("features",$f);'
```

Re-enable by setting `polls` back to `true` or removing the key (the default is
now `true`).

## Operating semantics (what to tell operators)

- **One poll per thread** — enforced both by a pre-check and the
  `uq_poll_thread` unique key. A second create attempt is rejected with a
  validation flash.
- **Who can create / close** — the thread author, an admin, or an in-scope board
  moderator (`canManageThread`). Out-of-scope moderators and ordinary members
  get `403`. Banned/suspended accounts are blocked by `WriteGate` on every write
  ("state beats role").
- **Read-gated** — create/vote/close all run `BoardPolicy::canRead`; a member
  removed from a private board can no longer reach the poll routes (they `404`),
  and no vote is recorded.
- **Results visibility** (`results_policy = after_vote_or_close`) — a voter sees
  results once they vote; everyone sees them once the poll is closed. Enforced
  server-side, so the no-JS panel honors it too.
- **No PII** — votes are aggregate-only (counts via a live `LEFT JOIN`, so there
  is **no denormalized counter and no `RepairService` entry to keep in sync**).
  No per-user vote disclosure, no IP capture.
- **Progressive enhancement** — the panel is a plain server-rendered radio/
  checkbox form with no inline script; it works fully with JavaScript disabled
  and under the strict CSP.

## Monitoring & known limits

- **Not separately rate-limited.** Create/vote/close have no dedicated
  `RateLimitService` policy; abuse is bounded structurally by one-vote-per-user
  (DELETE+INSERT inside a transaction) and one-poll-per-thread. If a board sees
  vote spam, disable the flag for that release and add a policy before re-enabling.
- **Concurrent double-create race.** The pre-check handles the common case; a
  true race between two simultaneous creates is caught by `uq_poll_thread` and
  can surface as a `500` for the loser (no duplicate poll is created). Low
  severity; a follow-up can map the unique-violation to the same validation
  flash as the pre-check.

## Acceptance evidence

- **PHPUnit:** `tests/Integration/Core/AppPollTest.php` — dark-by-default `404`,
  voter-sees-results-after-vote / non-voter-after-close, removed-private-member
  `404` on all three routes, and the full create/close `403` authority matrix.
- **Browser:** `docs/evidence/browser/{desktop,mobile}/25-poll-voted.png` — the
  real server-rendered vote → redirect → results flow (`tests/browser/gate-a.spec.ts`).
- **Accessibility:** `tests/browser/a11y.spec.ts` — axe scan of the `.poll-panel`
  in both the vote-form and post-vote results states, desktop + mobile, no
  serious/critical violations.
