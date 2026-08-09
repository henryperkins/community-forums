# Runbook — Link previews (`link_previews`)

Release/operations runbook for the **link_previews** feature: server-side
unfurling of URLs posted on public boards into a small card under the post,
with a per-board opt-in, an SSRF host allowlist, a kill switch, and author
removal. **Default-ON as of 2026-08-09** (ADR 0025; the `link_previews` flag
graduated out of deploy-dark); fully reversible via the `features` override.

> **Golden rule:** a default-on flag does **not** mean your server is fetching
> anything. Three gates must all be open, and a fresh or upgraded install opens
> only the first. If you have never touched the console, nothing has ever been
> requested.

## The three gates

| Gate | Where it lives | Default | Effect when closed |
|---|---|---|---|
| `link_previews` feature flag | `settings.features` | **on** | routes 404, nothing renders, nothing queues, and `worker:previews` fetches nothing |
| Per-board opt-in | `boards.link_previews_enabled` | **off** | that board's posts never queue, and any row already queued is held (`queued`, reported `skipped`) instead of being fetched |
| Host allowlist | `settings.link_preview_allowed_hosts` (falls back to `LINK_PREVIEW_ALLOWED_HOSTS`) | **empty** | every fetch is refused as `blocked` |

Two further constraints are not operator-tunable:

- **Public boards only.** A hidden or private board never unfurls, even if it
  is opted in — the console labels that state *Inert*.
- **Direct messages are never unfurled**, at all, by anyone. A server-side
  fetch would tell the URL's operator that a private message contains that
  URL, with timing that correlates to when it was sent.

## Turning it on

Everything below is at **Admin → Features → Link previews**
(`GET /admin/link-previews`).

1. **Allowlist the hosts you trust.** One per line (commas also work).
   `example.com` matches that host exactly; `*.example.com` matches its
   sub-domains. Anything that is not a hostname or a `*.` pattern is rejected
   with the offending entries named — a mistyped entry that silently matches
   nothing is indistinguishable from a working one, so the form refuses rather
   than normalising. Saving stores an explicit list that takes precedence over
   `LINK_PREVIEW_ALLOWED_HOSTS`.
2. **Opt boards in.** Toggle each board in *Per-board opt-in*, or use the
   *Unfurl link previews on this board* checkbox on the board's own edit form.
   Both write an audit row.
3. **Run the worker.** Discovery happens on write; fetching happens on cron:

   ```cron
   */5 * * * *  cd /srv/retroboards && php bin/console worker:previews 25
   ```

   The argument is the batch size (clamped to 1–100).

Until step 1 and step 2 are both done the console shows a *Nothing is being
fetched right now* callout naming exactly what is missing, and `/admin/features`
shows the `link_previews` row as **Operational configuration required** until
both clear.

## Reading the console

**Tiles** — Queued (waiting for `worker:previews`), Rendering (fetched rows,
the only status that shows a card), Blocked (refused by the allowlist or the
egress guard), Boards opted in.

**Statuses**

| Status | Meaning | Next step |
|---|---|---|
| `queued` | discovered in a body, not fetched yet — also where a row waits while its board is opted out, the kill switch is on, or the flag is rolled back | run/inspect `worker:previews` |
| `fetched` | metadata stored; renders a card | — |
| `blocked` | refused before or during egress, **or** its source is permanently ineligible (post **or its thread** deleted, approval-held, moved off a public board); the reason is on the row | allowlist the host, or leave it blocked |
| `failed` | transport, size or parse error | Refresh to retry once the cause is fixed |
| `purged` | an operator wiped the metadata | re-queued automatically the next time its post is saved |
| `removed` | **the author took it off their own post** | leave it alone — see below |

**Row actions** — *Refresh* re-queues a row for a fresh fetch. *Purge* wipes
the stored metadata immediately. Both are audited against the post the row
belongs to, so `Admin → Overview → Audit log` shows them beside every other
action on that post.

## Author removal, and why refresh cannot undo it

A post's author — and anyone with board-scoped `core.post.delete_any` — can
remove a card from that post with a single button under it. The row goes to
`removed`, its metadata is wiped, and it stays removed through edits and
re-saves. **Both operator row actions refuse a `removed` row** and the console
renders neither button for it. Refresh is the obvious one. Purge matters more:
`purged` is re-queued by design on the next save, so purging a removed row would
quietly bring the card back the next time its author edited the post. A removed
row has no stored metadata left to wipe anyway. If you disagree with a removal,
use the moderation surfaces, which are audited as moderation. The author can
restore it themselves, which puts the row back in the fetch queue.

## Incident response

**Something is being fetched that should not be.**
Engage the **kill switch** on the console (`link_preview_kill_switch`). The
worker then skips every queued row on each run and reports them as `skipped`;
nothing is lost and nothing is fetched. Then fix the allowlist or the board
opt-in and release the switch.

**A board should stop unfurling.**
Switch its opt-in off. Rows already queued for that board are **held, not
retired** — the worker reports them as `skipped` and leaves them `queued`, so
switching the board back on drains the backlog by itself with no per-row
console work. Only a permanently ineligible source (deleted post, approval
hold, a deleted thread, a move off a public board) is retired as `blocked`.

**A specific host is misbehaving (slow, huge, or hostile).**
Remove it from the allowlist and save. In-flight rows for that host fail their
next validation and land in `blocked` with the reason. Existing `fetched` cards
for that host keep rendering their stored metadata — purge them if the stored
text itself is the problem.

**A single card is wrong or abusive.**
Purge the row. It is re-queued the next time its post is saved, so if the
content itself is the problem, take the post through the normal moderation path
as well.

**Something is wrong and you want it all gone, now.**
Roll the flag back (below). Every card stops rendering immediately.

## Roll back / re-enable

The flag lives in the `features` setting. Merge the override rather than
clobbering other feature keys:

```bash
php -r '
require "vendor/autoload.php";
App\Core\Env::load(getcwd()."/.env");
$c = App\Core\Config::fromFile(getcwd()."/config/config.php");
$s = new App\Repository\SettingRepository(new App\Core\Database($c->get("db")));
$f = $s->get("features", []); $f = is_array($f) ? $f : [];
$f["link_previews"] = false;          // true (or unset the key) to restore the default
$s->set("features", $f);
echo json_encode($f), PHP_EOL;'
```

**What rollback does:** `/admin/link-previews` and its POST routes 404, the
member remove/restore routes 404, the board edit form drops the opt-in
checkbox, no card renders, and nothing new is queued. **`worker:previews` stops
fetching too** — the service reads the flag itself, so the cron job reports
every queued row as `skipped` and makes no outbound request. (Cron does not need
to be unscheduled, though pausing it is harmless.)

**What rollback preserves:** every `link_previews` row, every board opt-in, the
allowlist, and the kill-switch state. Re-enabling restores exactly the posture
you left — including which boards were opted in. A board edit made while the
flag is off does **not** silently revoke a stored opt-in, because the service
keeps the stored value when the field is absent from the submission
(pinned by `test_link_previews_defaults_on_and_is_operator_reversible`).

## Environment settings

These are `.env` values, not console settings, because they are transport
policy rather than product configuration:

| Variable | Default | Notes |
|---|---|---|
| `LINK_PREVIEW_TIMEOUT_SECONDS` | `4` | connect and total timeout |
| `LINK_PREVIEW_MAX_BYTES` | `262144` | hard read cap; a larger response fails the row |
| `LINK_PREVIEW_MAX_PARSE_BYTES` | `131072` | how much of the body is parsed for metadata |
| `LINK_PREVIEW_ALLOW_HTTP` | `false` | plaintext HTTP is refused unless set |
| `LINK_PREVIEW_ALLOWED_HOSTS` | *(empty)* | fallback allowlist when none is stored |
| `LINK_PREVIEW_ALLOWED_PRIVATE_CIDRS` | *(empty)* | egress-guard exceptions; leave empty in production |

## Safety properties worth knowing

- The fetch **pins the connection to the IP the `EgressGuard` resolved**
  (`CURLOPT_RESOLVE`), so a DNS-rebinding answer cannot move the request to a
  private address between the check and the connect.
- Redirects are followed manually, at most three, and **every hop is
  re-validated** against the allowlist and the guard.
- The response is truncated at `LINK_PREVIEW_MAX_BYTES` during transfer, not
  after.
- Extracted metadata is stripped of tags and length-bounded before storage, and
  escaped again at render.
- `link_previews.image_url` is captured but **never rendered** — displaying it
  would make every reader's browser fetch a third-party asset on page load,
  which is exactly what the server-side fetch exists to avoid (ADR 0025).

## Related

- ADR 0025 — the enablement decision and its evidence.
- DECISIONS §6 #5 — the locked "opt-in per board; server-side fetch with SSRF
  allowlist" decision this implements.
- `docs/runbooks/operations.md` §2 — general feature-flag rollback mechanics.
