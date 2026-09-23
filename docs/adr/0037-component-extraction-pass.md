# ADR 0037: Component extraction pass — what was folded, and what waits

**Date:** 2026-09-23
**Status:** Implemented; verification results below.
**Relates to:** the component-reuse critique
(`.impeccable/critique/2026-09-23T12-17-10Z__templates.md`); ADR 0036 (the topic
row and the star, the first extraction); ADR 0024 (the operator console);
ADR 0028/0029 (a class the template emits with no rules behind it); root
`DESIGN.md` "Components"; PRODUCT_DESIGN §13.

## Context

After ADR 0036, three read-only surveys went looking for components that do
the same job under different names: controls and actions; rows, cards, panels
and tables; and status tokens, empty states, pagers, notices, navigation and
form fields. They found about twenty such families. The rule for this pass is
the extract rule: fold only what is used three or more times **with the same
intent**, prefer a shared partial or rule the system already has, and prove
parity or name the change. Two things that look alike and serve different
purposes stay apart.

## Decisions — folded in this pass

### 1. One console status pill

Inside the console, `.state` already was the status pill (1px 9px, pill
radius, label face, `.66rem`, `.06em`), keyed by lifecycle words. Four more
classes restated that box under their own names — `.features-pill`,
`.packages-pill`, `.member-invitations-status`, `.features-override-pill` — and
the execution brake borrowed the uppercase `.pill` plus a one-off
`.pill-danger`. A comment on `.features-pill` said title-case labels made
`.state` "the wrong primitive". That was about modifier names, not the box.

`.state` now takes **named tones** beside its lifecycle words: `state-done`,
`state-review`, `state-danger`, `state-muted`, `state-staff`. The dot register
outside the console learns the same names. The five copies are deleted, and a
test holds them deleted. The contrast rationales that lived on the copies
(C-45, C-49) now sit on the one rule.

What changed on screen: the brake reads "live" / "disabled" in the console's
token case instead of `.pill` capitals. The package and invitation danger
tint is the console's 12% rather than 10%.

### 2. One account-state chip

`.totp-state-pill` and `.account-state-chip` were the same chip, one with a
border and one without. The TOTP chip had dropped the design's `--green-200`
ring on purpose, because it is a fixed ramp value that does not flip in the
twilight register. The one chip now follows that reasoning and takes the done
pair only. "Connected" and "This device" lose the ring.

### 3. One back link

`partials/back_link.php` existed and three screens used it. Twelve more
hand-rolled the same link with an inline 13px chevron. All fifteen now render
the partial, and `.admin-back .icon` sets the one chevron size (13px, the size
twelve of the fifteen already showed).

### 4. One console pager

`partials/pager.php` served the audit log and the email log. The tag catalogue
hand-copied it, while the member directory and the reports queue each wrote
their own. The member directory used left-aligned small buttons and no label.
The reports queue used a filled primary `.btn` for "Next" and no "Previous" at
all when the move was unavailable.

The partial now also serves lists that only know whether another page exists
(`has_next`, labelled "Page N"). It takes a URL builder (`href`) for routes that
count from 0 or carry their own query, and an optional `noun` that names each
control ("Next tag page"). The three pagers render it. Their private CSS is
deleted, and the 44px phone touch floor, which the tag pager and the audit
overview had each added for themselves, moves onto the pager.

### 5. Console empty states use the partial

`partials/empty_state.php` had two callers. The member directory rebuilt the
same heading and sentence with a few pixels of drift, and the Thread
Intelligence evidence table borrowed `.state-empty` and re-tuned three sizes.
Both now render the partial, and their drift CSS is deleted.

### 6. One alert plate

`.callout` (info, review, danger) was the console's alert plate. Two alert
classes elsewhere had **no rules at all** — the class of defect ADR 0028 and
ADR 0029 each found:

- The register page's four `.notice` messages ("New sign-ups are currently
  closed", "Registration is by invitation only", the invitation welcome, and an
  invitation error) rendered as plain paragraphs.
- The search query error and the passkey sign-in error (`.form-error`) rendered
  as loose body text, detached from their field.

`.callout` is now available everywhere. Each console selector stays in its
rule's list, so console rules keep the weight they had. The register notices
are callouts: closed in the review tone, an invitation error in danger, the
rest in info. The two field-level errors take `.field-error`, as every other
form error does. Links inside a callout join the shared underlined-link rule
for tinted prose.

## Deferred — ranked by value, with the reason each waits

Recorded so none is lost. Each is a real duplicate. Each waits because it
changes pixels on high-traffic member surfaces, or because many browser specs
pin its class names. Either way it deserves its own evidence.

1. **Overflow menus.** The post menu, the DM header and profile menu, the inbox
   row menu and the identity menu share one skeleton: `<details>`, an icon
   summary and a panel of actions. Each has its own panel-row styles, and only
   the inbox repositions its panel with JavaScript. Target: one `ActionMenu`
   partial. The topic-tools drawer stays separate, because it is a workspace
   rather than a menu. About 25 browser assertions pin `post-menu`.
2. **Icon-only buttons.** `.post-toolbar-button` (28px pill), `.dm-iconbtn`
   (34px), `.dm-dotbtn` (24px, bordered), the inbox row controls (28px), the
   admin reorder buttons (27/30px) and the composer toolbar (built in
   JavaScript) need an `IconButton` with size and shape parameters.
   `.composer-send` and `.unread-toggle` stay separate.
3. **Person rows.** About twelve screens draw a member as monogram, name and
   handle, with an optional action, and only the presence rail shares a
   partial. The connections trio comes first: the home pane, the profile tab,
   and the legacy `/u/{username}/followers` and `/following` page. The
   leaderboard, DM
   conversation rows and the participant stack stay separate by intent.
4. **Member pagers.** The feed ("Newer" / "Older"), the profile tabs,
   who's-online and the board's `pagination-board` are four implementations
   of one previous/next job on member surfaces. The console pager above is the
   pattern. The numbered strip for long topics stays.
5. **The dashed empty frame, and sentence-only empties.** The structure page,
   profile panels, organisation lists and the Living Brief share one dashed
   frame in CSS; it should be the partial's `framed` variant. The one-line
   empties (`.muted.empty`, `.packages-empty`, `.features-empty`,
   `.member-record-empty`) should be the partial without a heading.
6. **Admin row actions.** `.content-board-action`, `.packages-rowbtn`,
   `.features-rowbtn`, `.integrations-rowbtn` and `.packages-textbtn` are one
   secondary row action in three looks. Target: one class with `ghost`,
   `outlined` and `link` variants.
7. **Toggle buttons.** Follow board carries `aria-pressed`; follow tag, follow
   person and the board favourite do not, and board mute has its own class.
   Target: one `ToggleButton` that always states its pressed state.
8. **Copy to clipboard.** `data-copy-post`, `data-copy-link` and
   `data-copy-message` should become one `data-copy` contract (URL or text).
9. **Danger naming.** The danger modifier has three names: `.btn.danger`,
   `.linkbtn.is-danger` (post removal preview) and a bare `.danger` on menu
   rows. The design layer's `.btn-danger` is unused.
10. **Facts lists.** Package, member-record, theme, email-readiness and impact
    lists are one labelled register. The topic head's facts and the board
    identity facts stay separate as page chrome.
11. **Panels.** `.org-card` repeats the console card. The global legacy `.card`
    still differs from the console and design card, so a global alias would
    change about 40 screens at once.
12. **Content list rows.** Search results, feed items, profile lists and drafts
    re-implement title, meta and excerpt. `.approval-item` is `.report-row`
    under another name.
13. **Tabs.** The feed and the leaderboard still wear the inbox's retired tabs.
14. **Topic-head chips.** `.thread-status-chip` / `.thread-state-chip` state the
    same facts as the row's `.chip-*`.
15. **A field partial.** The label, control and `field_error()` triad is one
    system already (about 167 uses). A partial would cut verbosity, not a
    competing style, so it is optional.

## Kept separate, by intent

Page headers (three registers: forum, console, account), data tables (already
one `.audit` inside `.table-scroll`), the topic-tools drawer, reactions, the
unread gutter control, notification rows, the poll submit, the Living Brief's
curator empty state, and choice cards.

## Dead code the surveys found

- `.board-row` has CSS and no template.
- The member directory emits `data-label` attributes that no CSS reads.

Both are left for their own pass.

## Verification

- Before/after captures of every changed element, taken from the base commit
  and this branch against the same seeded database, are in
  `docs/evidence/component-extraction/`.
- PHPUnit: `SharedConsolePartialsTest` covers the pager's new modes.
  `ImladrisRuntimeAssetTest` pins the named tones and holds the five retired
  pill classes at zero. The invitation, brake, directory and Thread
  Intelligence tests are updated to the shared markup.
