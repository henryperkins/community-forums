# Runbook — Server Drafts (`server_drafts`)

Release/operations runbook for the **server_drafts** feature (authenticated,
cross-device draft sync: the enhanced composer saves/loads drafts on the account
in addition to the browser-local `localStorage` fallback, with optimistic
revision conflict handling and a no-JS `/drafts` list/resume/discard surface for server
draft rows).
**Default-ON as of 2026-07-02** (the `server_drafts` flag graduated out of
deploy-dark); fully reversible via the `features` override. Scope is fixed by
ADR 0010; follows the same conventions as `docs/runbooks/operations.md` §2 and
mirrors `docs/runbooks/topic_workflow.md` / `docs/runbooks/polls.md`.

> **Golden rule:** for any logic defect (bad conflict resolution, sync loops,
> quota/retention surprises), **disable the `server_drafts` flag first** (the
> JSON endpoints and the no-JS `/drafts` list/discard 404; the composer silently
> falls back to browser-local drafts only; the rest of the app keeps serving),
> then investigate. Disabling is non-destructive — stored `server_drafts` rows
> are retained and reappear when the flag is re-enabled.

## What the flag gates

`server_drafts` gates the **server-owned** half of draft handling only. The
browser-local autosave (`drafts` flag) is independent and always works; when
`server_drafts` is off the composer just uses `localStorage`. Schema
(`server_drafts` keyed by `UNIQUE (user_id, context_key)` with `revision`,
`title`, `body`, `metadata`, `updated_at`, `expires_at`, FK `user_id → users`
`ON DELETE CASCADE`) ships in migration `0064_server_drafts.php`.

Routes (all authenticated; every POST is CSRF-protected; gated **in-controller**
via `DraftController::requireServerDrafts()`, which 404s when the flag is off):

- `GET  /drafts` — the account draft page. Gated by the `drafts` flag; when
  `server_drafts` is on it lists the member's server drafts with a per-row no-JS
  discard form, and JavaScript also renders a separate **Saved in this browser**
  section for browser-local drafts. (With `server_drafts` off it shows the
  local-only guidance.)
- `POST /drafts/{id}/discard` — the **no-JS** discard of one server draft
  (redirects back to `/drafts`).
- `GET  /api/drafts/{key}` — load the current server draft for a context key.
- `POST /api/drafts/{key}` — save/update (optimistic revision; see below).
- `POST /api/drafts/{key}/discard` — explicit discard by context key. An empty
  autosave supplies both `draft_id` and `revision` and can remove only that copy.
  Successful submissions use the server's identity/content-checked cleanup.

The enhanced composer (`public/assets/composer.js`, `wireServerDrafts`) is pure
progressive enhancement: it debounces saves (~800 ms), hydrates from the server
on open, and renders the conflict panel (`.composer-draft-sync.is-conflict`).
With JavaScript disabled the member can still use the server-rendered composer
and the no-JS server-draft list/resume/discard forms; browser-local draft listing itself
requires JavaScript because it reads `localStorage`. Nothing on the write path
depends on the JSON endpoints.

Account state applies to every server-draft write. Suspended or banned members
and admins may list and load their saved drafts, but save, revision-checked
discard, and explicit discard return 403 without changing the saved copy.
An expired suspension permits writes again. Browser-local recovery remains
available when a server save is rejected.

## Roll back / re-enable

The flag lives in the `features` setting (JSON `flag => bool`); see
`docs/runbooks/operations.md` §2 for the inspect/set snippets. Disabling is the
**first response** to any defect and is non-destructive (all `server_drafts`
rows are retained and reappear on re-enable):

```bash
# Roll back: take the server-draft surface offline (merge — do not clobber other flags)
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
$r=new App\Repository\SettingRepository(new Database($c->get("db")));
$f=$r->get("features",[]); $f["server_drafts"]=false; $r->set("features",$f);'
```

Re-enable by setting `server_drafts` back to `true` or removing the key (the
default is now `true`). **Always disable the flag before any data repair** on the
`server_drafts` table so the composer is not racing your maintenance writes; the
table has no denormalized counters, so `RepairService` has nothing to
reconcile.

## Operating semantics (what to tell operators)

- **Ownership + isolation** — every draft is scoped to the authenticated
  `user_id`; there is no cross-user visibility. Keyed by `(user_id, context_key)`
  where `context_key` is the composer context (e.g. a thread/reply target),
  trimmed, ≤191 chars, and must not contain `/` (else `422` "Draft context is
  invalid.").
- **Complete form state** — `metadata.state` is `{version: 1, fields: {...}}`.
  Only `title`, `board_id`, `is_anonymous` and `to` can become restored controls;
  strings and booleans are validated without collapsing explicit empty/false
  into missing. Disabled/absent controls are ignored in the actual composer.
  The browser keeps the raw body key plus a matching versioned companion; older
  body-only drafts remain readable. Title-only and wrapper-only edits save.
- **Optimistic revision / conflict resolution** — current clients send the
  `draft_id` and `revision` they loaded (both zero for creation). Stale identity
  or revision returns **`409`** with a current-read server copy, including after
  delete/recreate. Legacy clients without `draft_id` retain revision-only
  compatibility. A transaction keeps a successful acknowledgement tied to its
  own write. The comparison identifies changed body/wrapper fields and shows
  **In this browser** and **On the server** before three choices: **Keep local**
  (pause sync, server unchanged), **Keep server** (load the latest compared
  copy), or **Save local as next revision** (current form at `revision + 1`).
  Saves are serialized/coalesced; a second conflict refreshes the comparison.
  Save-owned transactions retry driver 1020/1213 at most twice with unchanged
  CAS inputs after rollback. Nested calls and exhausted retries propagate the
  error; the browser copy remains the recovery source.
- **Resume** — links are derived from the stored canonical context, never a
  metadata URL: `/compose?draft=id`, `/t/id?draft=id#reply`,
  `/messages/new?draft=id` or `/messages/id?draft=id`. GET prefill requires
  ownership, an unexpired matching context and the normal feature/read/write
  destination gates. A legacy topic with no board requires a board choice.
  Unavailable destinations keep their saved copy and return an explicit error;
  they never silently retarget. Unknown legacy contexts remain listed and
  discardable without an invented Resume destination.
  The permitted GET form carries `draft_source=id:revision`. After hydration,
  JavaScript removes only the `draft` query from the current history entry so
  a completed send cannot strand an uncertain retry at a deleted draft URL.
- **Successful-submit cleanup** — only the checked row ID/revision, exact body,
  recorded wrapper fields and matching submission identity or exact resumed
  `draft_source` can be deleted. Resume retains a fresh submission identity;
  the source is a row reference, not authorization, and ownership/context plus
  conditional deletion still apply. Another revision or recreated row survives.
  Versioned drafts with no recorded title field use `title` only as a list
  label; an empty hidden DM group-title input cannot prevent their cleanup.
  Legacy metadata keeps the existing exact body/title cleanup rule.
  Client completion receipts use the complete snapshot too, preserving another
  tab's newer title/anonymity/recipient choice even when the body is unchanged.
  A late autosave is obsolete only when its exact recorded snapshot has a real
  completion receipt or a same-user/context successor with one. Restoring the
  latest unresolved complete local copy also recovers its unchanged submission
  key if history lost it; a completed or different latest attempt blocks reuse.
- **Size limits** — body must be ≤ 20000 characters (`422` "Draft body must be
  20000 characters or fewer."); title is trimmed to 255 characters.
- **Retention: 90 days.** Every draft carries `expires_at = now + 90 days`,
  refreshed on each save. Expired drafts are purged **opportunistically** before
  any read/list/save for that user, and swept globally by `worker:drafts`.
- **Quota: 50 active drafts per user.** A save that would exceed the cap returns
  `422` ("You can keep up to 50 server drafts…"); members discard from `/drafts`
  or the composer.
- **Export / delete coverage (ADR 0010 / ADR 0006)** — server drafts are
  included in the account **export** (`AccountLifecycleService`) and deleted on
  account **purge** (both the explicit purge sweep and the `ON DELETE CASCADE`).
- **Offline fallback** — browser `localStorage` remains the offline copy. When
  `server_drafts` is on, the composer keeps both and reconciles via the conflict
  panel; when off, only the local copy is used.
- **No notifications, no hooks/webhooks** — saving/discarding a draft notifies
  no one and emits no domain hook/webhook.

## Monitoring & known limits

- **`worker:drafts` (cron).** `php bin/console worker:drafts` calls
  `ServerDraftRepository::purgeExpired()` and logs `Server drafts:
  purged_expired=<n>`. Retention also self-heals opportunistically on every
  read/list/save, so a missed cron run only delays cleanup, it does not leak
  drafts past use.
- **Not separately rate-limited.** There is no dedicated `RateLimitService`
  policy for draft saves; growth is bounded by the 50-draft quota and the
  client-side ~800 ms save debounce. If a client misbehaves and hammers the save
  endpoint, disable the flag for that release and add a policy before
  re-enabling.
- **No repair path.** The table is authoritative user content with no
  denormalized counters; `RepairService` intentionally has nothing to recompute.
  Corruption is not automatically reconstructable — restore from backup with the
  flag disabled.

## Acceptance evidence

- **Integrated release (2026-10-31):**
  `docs/evidence/draft-mobile-release-2026-10-31/README.md` qualifies the combined
  draft, mobile, and previously released theming sources, including account-state
  write restrictions. Earlier records below remain historical evidence.

- **Complete recovery remediation (ADR 0052):**
  `docs/evidence/draft-recovery-2026-10-23/README.md` records local Chromium and
  WebKit checks, no-JS Resume, the optional-field comparison matrix, startup and
  concurrency safeguards, and the final PHP/asset results. The original audit
  remains separate and unchanged. This local evidence does not assert a
  deployed build or physical-device qualification.

- **Resumed completion follow-up:**
  `docs/evidence/mobile-ux-remediation-2026-10-25/README.md` records unchanged
  source/rich Resume, local/server Resume, no-JS completion, uncertain-response
  retry, all four composer contexts, validation, legacy rows and preservation
  of newer/recreated/different copies. Physical-device checks remain separate.

- **PHPUnit:** `tests/Integration/Core/AppServerDraftsTest.php` —
  `test_server_draft_endpoints_are_available_by_default_and_can_be_disabled`
  (default-on plus operator rollback: routes 404 when disabled),
  `test_save_load_and_conflict_response` (revision bump + `409` conflict payload),
  `test_invalid_discard_context_returns_422_json`,
  `test_drafts_page_lists_and_discards_server_drafts_without_js` (the no-JS
  surface), and `test_expired_server_drafts_can_be_purged_by_worker`;
  `tests/Integration/Core/AppAccountLifecycleTest.php` — server drafts appear in
  the account export payload and are removed on account purge (ADR 0010 / ADR
  0006 coverage).
- **Browser:** `docs/evidence/browser/{desktop,mobile}/28-server-draft-conflict.png`
  — the real cross-device conflict flow (save → simulate another device →
  conflict panel → "Save local as next revision" → revision 3), driven by
  `tests/browser/server-drafts.spec.ts` (now part of the standard `npm run
  evidence` capture).
- **Accessibility:** `tests/browser/a11y.spec.ts` — axe scan of
  `.composer-draft-sync` (the conflict panel) and the `/drafts` list page,
  desktop + mobile, no serious/critical violations.
