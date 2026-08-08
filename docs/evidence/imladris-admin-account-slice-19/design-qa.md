# Slice 19 closeout — de-fiction, the evidence sweep, and merge readiness

Status: **complete except the decisions that are the owner's.** The migration is code-complete;
what remains is recorded here and in ADR 0024's new "Closeout status" section rather than left implicit.

This slice ships no design adoption. It closes the de-fiction pass, runs the aggregate evidence sweep,
and writes down the state of the branch honestly enough that the merge can be done deliberately.

## De-fiction — the full accounting

Ledger §3.3 (new) is the authoritative table. Summary:

**Discharged during the migration**, not in one final sweep — the admin page-head eyebrows (slices
5–13, deleted with the eyebrow the design itself drops), `admin/branding.php`'s *"…before the council
sees the updated hall"* (slice 8), `Vilya · Expose` (slice 17), the four `Warden's table` eyebrows
(slice 18, discharged in one move by the chrome swap exactly as the ledger predicted), and
`Council record` / *"The council record keeps…"* (slice 18).

**Discharged by this slice**, all previously unpinned:

| String | Where | Now |
|---|---|---|
| `Welcome back to the council` | `auth/login.php` | `Welcome back` |
| `Your seat at the council is ready.` | `auth/verify.php` | `Your account is ready.` |
| `Et Eärello Endorenna utúlien.` | `layout.php` auth colophon | **deleted** |

The colophon needed a judgement: a decorative quotation has no truthful replacement, and inventing a
tagline would substitute one fiction for another. The ledger's first listed remedy is "Delete", so the
element goes. Its `auth-colophon` class was pinned by `AppImladrisFidelityTest`, so the assertion was
inverted to a negative pin and the now-dead CSS rule removed with it — the rest of the auth-stage
composition is untouched and still pinned.

## The correction this slice is really about

**Ledger §3.2 was wrong about `leaderboard.php`.** It listed `The council` in the *"FREE to change —
unpinned"* table with the note "Outside admin/account scope; listed for completeness". It is **not**
unpinned: `AppLeaderboardFidelityTest::test_header_has_the_council_eyebrow_and_a_sentence_case_title`
pins it in the test **name** as well as the body.

Slice 19 changed it, the suite went red, and the change was reverted. The row is corrected in place and
the string moved into §3.2's TEST-PINNED group, where it needs the same owner decision as the other
four. It is doubly out of scope: the leaderboard is neither an admin nor an account surface.

This is worth recording as a method point. Grepping the *strings* a template renders is not sufficient
to prove a string is unpinned — a test can pin a string in its own name, and `assertSeeText` on a
neighbouring literal will not show up in a search for the string itself. The only reliable check is
running the suite, which is what caught it.

## Newly found, not fixed

`templates/thread.php:57` (`In council`) and `:171` (`Open to the council`) are fiction that **no
earlier inventory recorded**. Stage 1 inventoried the eleven admin/account screens, so the thread
surface was never swept. Both are recorded in ledger §3.3 so the next thread-surface slice inherits
them rather than rediscovering them. Not changed here: this migration has no mandate over the thread
surface, and one of the two sits beside a poll label whose wording may be pinned.

## The evidence sweep

`npm run evidence` — the repository's only CI — **cannot complete on this branch**, and the reason is
not this migration:

```
Running 34 tests using 1 worker
  ✘ 12 [desktop] › thread-view-study.spec.ts:328 › Study layout matches desktop and mobile geometry
  2 failed, 25 passed (1.7m)
```

The script chains its five groups with `&&`, so that one failure aborts groups 2–5.

- **`:328` is pre-existing, isolated not assumed.** Re-run with `public/assets/app.css`,
  `public/assets/composer.js`, `templates/` and `src/Controller/` stashed back to `HEAD`, it fails
  identically (1 failed / 19 passed). Slices 13 and 14 recorded the same red by the same method.
- **The second failure was a flake.** Mobile `thread-view-study.spec.ts:100` ("split or merge closes by
  every dismissal path") timed out at 30.1s inside the 34-test serial run; run alone with every change
  applied it passes in **3.2s**.
- **The fix for `:328` already exists on `main` and is not on this branch.** Commit `058c4cb`
  *"recover mobile thread height against the Imladris thread-view reference"* rewrites exactly those
  geometry assertions (`thread-view-study.spec.ts` +58 lines; the `:328` block moves to `:335`).
  Verified main-only with `git merge-base --is-ancestor`. **So the sweep should be re-run after the
  merge, where it has a real chance of being green — running it again on this branch cannot help.**

Per-slice gates were run and are recorded in each slice's own `design-qa.md`; slices 16, 17 and 18 are
green on their named specs (`account-console`, `server-drafts`, `mod-console`, `appeals`, `gate-a`,
`a11y`) apart from the isolated pre-existing reds listed there.

## An environment trap worth inheriting

`prepare.sh` resets the database but deliberately does **not** clear a private rate-limit store ("not
clearing outside `storage/ratelimit-e2e`"). Running several browser suites in one session against the
same `RATELIMIT_PATH` accumulates counters until they trip 429s early, which manufactures phantom
failures in the announcement and content-reference tests — four `a11y` failures and one
`admin-remediation` failure during slice 18, all of which cleared on
`rm -rf storage/ratelimit-console-e2e`. Slice 14 recorded the database half of this ("seed pollution,
not regressions"); the rate-limit half is new here.

## Verification

**Backend.** Full suite on private `retroboards_test_s16`: **2,577 tests / 18,735 assertions /
2 skipped / 1 failure** — the application-surface digest, red by design on any slice branch, and the
same count slice 18 left. The transient second failure during this slice
(`AppLeaderboardFidelityTest`) was the leaderboard de-fiction described above and is gone with the
revert; the intermediate run that carried it read 18,733.

**Static gates.** The CSP scan over `templates/` returns only `layout.php`'s five permitted external
`src` tags. `php -l` clean on every touched file. **No baseline, mirror or generated file is modified by
slices 16–19** — `git diff --name-only d58ed42..HEAD` over `config/`, `docs/design-system/`,
`resources/imladris/` and `public/assets/imladris.css` is empty.

## What the merge still needs — and why it is not done here

1. **`config/imladris-runtime-baseline.json`.** Four earlier commits (`8cc3894`, `b474e45`, `a8a6da6`,
   `bdbacd7`) rewrite it against ledger §6 rule 5. It is a genuine three-way divergence — merge-base
   `f8a09441…`, `main` `79d99fbb…`, branch `749c0de1…` — so picking either side is wrong in both
   directions. Take `main`'s side in the merge, then refresh the digest on `main` as the
   immediately-following commit.
2. **The fiction decision.** Five test-pinned strings now (`Removed by a warden`, `Commends`,
   `Private counsel`, `sort=commends`, and `leaderboard.php`'s `The council`) plus the `Regard` chrome
   on `profile/show.php`. ADR 0024 obligation 5 says **fix both surfaces or neither**, which is why
   `account/privacy.php`'s `regard` sentence was deliberately left alone.
3. **`main` has moved and slices 16–19 were built without it.** `main` is at `2ac40df` and carries
   `composer.js` +206, `app.css` +156, and changes to `gate-a`, `a11y`, `server-drafts`,
   `admin-remediation` and `thread-view-study` — every gate spec these slices ran. The per-slice
   evidence therefore certifies against this branch's spec versions, not `main`'s. Re-run the gates
   after the merge.
4. Three lesser items — the `*-console` specs' CI membership, `registries.php` field errors (ADR 0023
   deferral 4), and `FR-31`'s unique index — are itemised in ADR 0024's closeout section.

No captures accompany this slice: it changes two auth strings and deletes one element, and the
aggregate sweep it ran aborts on a pre-existing red whose fix lives on `main`. Committing a partial
sweep's screenshots would misrepresent it as a completed one.
