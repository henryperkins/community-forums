# ADR 0031: Presence — one rule set, an away state, and the roll nobody had ever looked at

**Date:** 2026-09-12
**Status:** Accepted and implemented (branch `worktree-presence-remediation`, baseline `002cee64`).
**Relates to:** `docs/design-system/imladris/templates/users-online/UsersOnline.dc.html`;
PRODUCT_DESIGN §6.15 (presence) and §13 (completion evidence); USER.md §4.7 (the
privacy toggle); ADMIN.md §3.9 (moderating presence); DECISIONS §3 #4
(short-polling, not SSE/WebSockets); ADR 0024 obligation 4 (the Imladris runtime
baseline); ADR 0028/0029/0030, whose remediation method this follows;
CLAUDE.md's rule that deferrals and reversals are recorded in an ADR and never
silently dropped.

## Context

A review of the presence surface produced nineteen findings. The privacy core
held up: `show_presence = 0`, stale members, banned accounts and blocks in both
directions were all filtered in one place, the rail and the JSON shared that
path, heartbeat writes were throttled and wrapped, and the flag-dark regression
test existed. Everything below sits on top of that.

Two facts explain most of the rest.

**Presence was re-derived by four surfaces with four different rule sets.**
`PresenceService` honoured the feature flag, blocks, banned status and the
window. `ProfileController::presenceOnline()` honoured only the toggle and the
window — so with the subsystem rolled back, `/presence` 404'd, `/users-online`
404'd, the rail vanished, and `/u/elrond` still painted a green leaf; a member
Alice had blocked disappeared from her rail and lit up one click away on his
profile. The topbar's leaf honoured nothing at all, and stayed lit for a member
who had switched presence off — contradicting the very control that says "A leaf
marks your presence beside your name".

**The surface had never been looked at.** `tests/browser/seed.php` never wrote
`last_seen_at` or `show_presence`, and the roster excluded the viewer, so every
evidence run captured an empty rail and `docs/evidence/browser/` held no
`/users-online` frame at all. That page shipped using five classes —
`users-online-surface|hero|total|list|privacy` — which had **zero rules in either
stylesheet**: a default bulleted list, `<strong>name</strong><small>@handle</small>`
colliding into "Elrond Peredhel@elrond", and a `<span class="presence-dot">` that
measured 0×0, because every `.presence-dot` rule in the app is descendant-scoped
to `.avatar-wrap` / `.topbar-avatar` / `.profile-avatar`. The presence page's only
presence indicator was invisible. Meanwhile eleven presence classes shipped in
the design system and were emitted by no template and no script; design QA had
even spent a contrast fix on `.presence-staff`, a selector nothing rendered.

Under PRODUCT_DESIGN §13 this surface's UI had never been visually verified.
That is the finding; the rest are its symptoms.

## Decisions

### 1. One ladder, in the service, consulted by every surface

`PresenceService::state()` is now the only place presence is decided, and its
order is load-bearing:

```
flag → id → status → show_presence → profile_visibility → recency → blocks
```

Blocks are last because they can only downgrade an otherwise-visible state, so
asking earlier spends a query on subjects that were already invisible.
`ProfileController::presenceOnline()` is **deleted, not patched** — a second,
weaker copy of the rules living in a controller is exactly how the fourth
surface drifted. The route 404s stay in `PresenceController` (route availability
is routing) and `!empty($features['presence'])` stays in the two templates (those
are about markup and must work when the service is unreachable).

### 2. `profile_visibility = 'members'` is honoured by presence — a live leak

Not in the review. `ProfileController::show()` refuses a members-only profile to
a guest, but `onlineSince()` filtered on `show_presence` and `status` and nothing
else, so a member who had restricted their profile to signed-in members had their
display name, handle and a link to a page the reader cannot open published in the
**guest-visible rail** and the **unauthenticated `/presence` JSON**. This
remediation would have made it strictly worse in three ways at once: the away
window triples the exposure window, the page gains pagination, and it gains name
search — so a guest could have enumerated members-only accounts by name.

### 3. The viewer counts themselves

Previously a signed-in member read "Online 15" at the same instant a guest read
16 — the same rail disagreeing with itself about the same moment. The viewer is
now on their own roster, marked `is_self`, and rendered with a "you" chip.

**Accepted consequence.** Both counts are now exact, so a member can read the
guest count in a private window, subtract their own, and learn how many
currently-online members are in a block relationship with them — and with name
search, narrow down which. The previous off-by-one blurred this by accident.
There is no clean mitigation: you cannot publish an honest count and also hide
the difference between two honest counts. We accept it.

### 4. Away ships; the windows are reconciled

`presence.{online, away, offline}` has been in the token families since
PRODUCT_DESIGN §6.15 and presence was binary. Now: seen within
`presence.online_window_seconds` (300) is **here now**; seen within
`presence.away_window_seconds` (900) but outside it is **stepped away**; outside
both is off the roll.

The design is internally inconsistent about the number — its guidance card says
"Here now · Seen in the last fifteen minutes", while its hero says "Members
active in the last fifteen minutes … A leaf means here now; amber means stepped
away", i.e. fifteen minutes is the outer band containing both. We take the hero's
reading, and the guidance card's copy changes.

900s also reconciles `AdminDashboardService`, which hardcoded `INTERVAL 15
MINUTE` while presence used 300s, so the tile and the roster counted different
populations with no setting to explain the gap. The **window** is now shared; the
**population** stays deliberately different — the tile is an operator metric over
every member, and must not be narrowed by `show_presence`, blocks or the flag.

### 5. Away is painted with gold, not the design's `--amber`

`--amber` is a light-register primitive with **no twilight remap**, so an away dot
would have stayed light-register amber on a dark page — the same class of bug
`LOCAL_RECONCILIATION.md` already records for `.badge-staff`. `--presence-away`
is `--gold-700` / `--gold-400`, which flips the way `--warning` does. It is
declared on bare `:root` in `app.css`, because `--presence` itself is only
declared in that file's dark blocks and takes its light value from the layered
`imladris.css`.

### 6. The rail and the poller render one anatomy, at one cap

The server rendered six rows and the poller rendered twenty, so about a second
after load the rail reflowed from 264px to 595px under the reader; with JS off
the badge read "9" over six names with no affordance at all.
`templates/partials/presence_person.php` is now the single row anatomy, the cap
is published as `data-presence-limit` so both sides read the same number, and
"+N more" is server-rendered.

The cap is **5**, not the design's `railMax: 6` on this one screen: four other
design screens specify `rosterMax: 5` and `member-surfaces/README.md` asserts the
rail is byte-identical on every route. One rail, one number.

### 7. `count` means here-now

The badge would have lied the moment away members entered the roster. `here` is
the permanent name and `count` ships one release as the alias a cached script
still reads. Emptiness is decided by `total`, never by `count` — a rail holding
five away members and nobody here is not empty.

### 8. `last_seen_at` comes off the wire

Every response carried second-precision activity timestamps for every opted-in
member, to anonymous clients, and no template or script read the field. "Show
when I'm online" is not "publish my activity timeline". The consequence is
absorbed rather than worked around: there is no per-row "away 12 minutes ago",
because a minute-rounded label still needs the second-precision field on the
wire. The sub-line is `@username · Here now` / `@username · Away`.

### 9. The widget is never hidden; `[hidden]` is made to work anyway

`app.js` set `presence.hidden` on an empty roster, but
`.presence-widget { display: block }` beat the user-agent `[hidden]` rule, so the
element reported `hidden === true` and went on painting 90px of roster. Eight
other components in the same stylesheet already carry an explicit guard; this one
was missed.

Fixing only the CSS would have deleted the empty state **and the shell's only
link to `/users-online`**. So: the guard ships, and nothing sets the attribute on
the widget. With JS off it is never hidden; hiding it with JS would be a
progressive-enhancement divergence.

### 10. The live region announces a summary, and rows reconcile

`aria-live="polite"` wrapped the whole section while the poller replaced the
list's `innerHTML` every cycle, so a screen reader re-read the entire roster once
a minute whether or not anything had changed. There is now exactly one live
region — an `sr-only` summary — and rows carry a `data-presence-sig` the poller
diffs, so an unchanged row keeps its node (and its server-rendered monogram
palette). The dot is `aria-hidden` everywhere and the state is always in text.
`/u/{name}` is the one exception: it has no sub-line, so it keeps a named dot,
with `role="img"` — an `aria-label` on a bare `<span>` is not exposed at all.

### 11. Polling has a lifecycle

Both pollers were a bare `setInterval` that ran forever. `shortPoll()` now pauses
on `visibilitychange` and fetches once immediately on return, backs off
exponentially on failure, and **stops permanently on 404** — the one status that
means "gone" rather than "later", which is what a viewer with an open tab sees
when an operator rolls the feature back mid-session. Cadence is 60s for both,
matching the heartbeat: a 45s poll cannot surface data a 60s heartbeat has not
written.

### 12. The roster is lazy, bounded, rate-limited and uncacheable

`shareViewGlobals()` built the full roster on **every** request — including
`variant=plain` pages with no rail, every admin and auth page, and every JSON
endpoint (`/notifications/bell` polls once a minute per open tab). It is now a
closure with only two consumers, so the work happens only in the templates that
render it, and it carries its own `try/catch` because `View::renderTemplate`
rethrows.

`presence.roster_max` (200) bounds the query, which had no `LIMIT` at all.
`/presence` and `/users-online` share one `presence` rate-limit policy
(`[120, 300]`) — throttling the poll while leaving paginated enumeration open
would be theatre. The JSON route answers 429 **in JSON** with `Retry-After`,
because the kernel's HTML error page reads as a failed request to a `fetch()`
loop, which then keeps hammering at its normal cadence. `/presence` is
`private, no-store`; `/users-online` is `private, no-cache` — not `no-store`,
because that also kills the bfcache and roster → profile → Back is this page's
primary journey. Both carry `Vary: Cookie`.

Both routes are now in `robots.txt`. An indexed copy of a live roll of member
names would outlive the member's own presence toggle.

### 13. Configuration is validated, not cast

The six knobs were read with a bare `(int)`, so any typo became a silent zero —
an empty `PRESENCE_ONLINE_WINDOW_SECONDS` meant "seen within 0 seconds", i.e.
nobody is ever online, with nothing anywhere saying why. `PresenceConfig` follows
`ThreadIntelligenceConfig`: an invalid value is **replaced with its named default,
not clamped**, and surfaces as an operator warning on the admin dashboard that
names the setting without echoing the value. It also enforces the cross-field
rules nothing checked: a heartbeat longer than the online window lets a row go
staler than the window that reads it, so an actively browsing member flickers out
of the roster between writes.

## Deferrals

Recorded, not dropped.

1. **Everyone / Wardens / New this week.** The design draws `/users-online` as a
   full member directory. Shipping those three filters would create a public,
   paginated, searchable roll of **every** member including people who never
   opted into presence — a disclosure decision, not a styling one. The pool stays
   the presence roster; filters reduce to Here now / Away. Revisit alongside a
   deliberate decision about whether this product has a public member directory
   at all, and how it relates to `hide_from_leaderboard`.
2. **`hide_from_leaderboard` and `show_presence` are two opt-outs, two switches
   apart in the same form**, and `/users-online` is now the nearest thing this
   product has to a leaderboard. They are not reconciled. At minimum the privacy
   copy should say what each one covers.
3. **`.presence-where`** (the "#board" sub-line) — no column exists to populate
   it. The class stays unused.
4. **The loading skeleton and error state** (`.presence-skeleton-*`, the design's
   "Presence is not answering" card). In a progressively-enhanced page the roll
   is already present on first paint, so a skeleton has nothing to cover; the
   error state needs a client-side failure mode the current poller does not
   surface. Reduced motion for `presencePulse` is, for whoever un-defers this,
   **already handled** by the global clamps at `app.css` — do not write a third.
5. **Staff chip is admin-only**, matching `mask_author()`'s existing rule.
   Widening it to moderators on a public roll is a disclosure decision. The label
   is "Staff", not the design's "Warden", because the post bit already says
   Staff and shipping two words for one concept in one release is worse than
   either word.
6. **Bidi in display names.** `$e()` escapes HTML, not U+202E; a name containing
   an RLO reverses the rest of the row, and the row now puts a name and a handle
   on adjacent lines. Pre-existing across the app — named here because this
   surface amplifies it, not fixed here.
7. **A capped roster's counts are a floor for a heavy blocker.** Block filtering
   runs in PHP after the 200-row cap, so a member who has blocked many people
   sees a slightly thinner roll. The count renders `N+` when the cap bites.
8. **A row rebuilt by the poller wears the neutral monogram.** `monogram_class()`
   is a server-side md5 hash of the username and cannot be reproduced in the
   client, so a member whose state changes between polls has their avatar repaint
   from their palette colour to the neutral one until the next full page load.
   The initials are mirrored faithfully (`Str::initials()`'s separators, two code
   points, astral-safe); only the colour is lost. Fixing it properly means
   putting the palette class on the wire, which is a payload decision rather than
   a rendering one.

## Consequences

- Rolling the `presence` flag back now has a documented operator cost: the
  heartbeat is flag-gated and is the **only** writer of `users.last_seen_at`, so
  "Last seen" on `/admin/users`, the single-member record and `/mod/u/{id}`
  freezes, and the Active-members tile drops to zero. This is in `.env.example`,
  ADMIN.md and the runbook.
- A member who turned presence off no longer sees their own leaf anywhere,
  including their own profile and the topbar. This is a deliberate behaviour
  change: the previous `$isSelf` bypass made the toggle lie to the one person who
  set it.
- `AppPresenceTest`'s "self excluded" assertions are inverted, and its
  `presenceMarkup()` regex is loosened — it pinned `class="presence-widget"` as
  the section's first and only attribute and would break on any markup change.

## Evidence

- `tests/Unit/Service/PresenceConfigTest.php` — 9 tests, covering each knob's
  fallback, the "empty string is not zero" case, and the three-knob
  misconfiguration where the away repair could otherwise lower the online window
  behind the heartbeat rule's back and ship the very pair that rule rejects.
- `tests/Integration/Core/AppPresenceTest.php` (amended) and
  `tests/Integration/Core/AppPresenceDirectoryTest.php` (new), covering: the
  flag-off / blocked / banned / opted-out-self profile dot; the topbar leaf
  against the viewer's own toggle and the flag; the members-only leak in all four
  channels; the here/away split at the window boundaries; `count` vs `total`; the
  absent timestamp; filters; search that cannot bypass `show_presence`; wildcards
  treated as literal text; pagination clamping; the **rail limit** and the "+N
  more" arithmetic (asserted as rendered text — `assertStringContainsString('more')`
  would have been satisfied by the class name the template emits unconditionally);
  both cache headers; robots.txt; a functional 429 with its JSON body and
  `Retry-After`; that both routes share one bucket; and that the `presence`
  policy is actually declared — `RateLimitService` silently no-ops on an unknown
  policy name, so a typo would ship an unthrottled guest-reachable endpoint.
- `tests/browser/users-online-remediation.spec.ts` — 16 tests × desktop and
  mobile. These **measure** rather than match markup, because a test asserting
  "the class is present" would have passed against the broken page: the dot has a
  non-zero box, `[hidden]` computes `display: none`, the rail's height does not
  change when the poll lands, an unchanged row keeps its DOM node, a row whose
  signature *changes* is replaced rather than duplicated (verified by reverting
  each of the two guards in turn and watching it fail 2 ≠ 1), the away colour
  differs from here-now **and** changes between registers **and** does not
  collapse onto here-now in dark, and the filters/search/paging work with
  JavaScript disabled.

**Known coverage gaps**, stated rather than implied: `capped` / the "N+" floor
and `presence.roster_max` have no test at any level (reaching them needs >200
seeded members); the admin dashboard's presence-derived window and its
`Presence config:` attention row are untested; and the monogram divergence in a
JS-rebuilt row (neutral palette, see below) is asserted nowhere.
- `tests/browser/seed.php` grows a presence fixture — the root cause. Captures in
  `docs/evidence/imladris-users-online-remediation/{desktop,mobile}/`, written
  per project, because a single path let the mobile run overwrite the desktop
  frames with 390px-wide ones filed under the desktop name.
