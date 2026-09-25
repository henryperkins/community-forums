---
target: topic reading surface
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 3
target_identity: "file:/workspace/templates/thread.php"
target_fingerprint: "sha256:d747c3402edf8e85d3ec87d07659285aea1b6ecf9f1dd38ed7060c80e75f3d60"
target_path: /workspace/templates/thread.php
timestamp: 2026-09-25T11-38-22Z
slug: templates-thread-php
---
Method: dual-agent (A: bc-48d66a54-51a4-52e3-a643-1f6014bb24f9 · B: bc-e5af4805-f8c7-51c0-aed3-dd2650493b50)

Target: the topic reading surface, `templates/thread.php`, live at `/t/17-ratified-decisions`. Mode: Operate.

Assessment A read the thread template, its partials, and the thread-study rules, then viewed the live topic in a fresh Playwright browser at 1440×900 and 390×844 as a guest, as Elladan, and as Elrond. computerUse was not available inside that subagent, so the pixels came from Playwright. No reply was submitted. Twilight was not switched. Catch-up, a deleted-post stub, pagination, and a failed reply were not on this topic.

Assessment B scanned the thread markup with `impeccable detect`. The CLI returned exit 0 and `[]`. A separate broken-image probe returned exit 2, so the empty result is a real PHP scan. Browser mutation worked. Injecting `detect.js` from the live server did not: `script-src 'self'` blocked `http://localhost:8400/detect.js`. No `impeccable` console messages. The live server was stopped afterward.

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Solved, Starred, and "Marked as the answer" are clear. The guest poll describes a vote the guest cannot cast, and the send control is only an arrow. |
| 2 | Match System / Real World | 3 | The reading column speaks the hall. The reaction tray and the formatting desk speak a generic chat app. |
| 3 | User Control and Freedom | 3 | Escape closes Topic tools and returns focus to Close. "Clear accepted answer" and "Close poll" have no cancel, and the desktop composer has no visible minimize. |
| 4 | Consistency and Standards | 2 | The watch pill says "every reply" while the sheet's selected segment says "Instant." Esteem is a commend count beside a thumb. |
| 5 | Error Prevention | 2 | Pin and lock are switches. Clearing the accepted answer and closing the poll submit on one click. |
| 6 | Recognition Rather Than Recall | 2 | The roster is four initials. With scripting on, the post toolbar is invisible until hover or focus, and Send has no visible word. |
| 7 | Flexibility and Efficiency | 2 | The composer exposes Ctrl+B, Ctrl+I, Ctrl+E, and Ctrl+K, and tools expose Escape. Reply, star, and quote have no accelerator. |
| 8 | Aesthetic and Minimalist Design | 3 | The study is quiet. Above 860px the resting dock is a full formatting desk under the first post. |
| 9 | Error Recovery | 2 | The failed-reply state was not on this topic. The warden buttons that were visible do not say how to take the act back. |
| 10 | Help and Documentation | 2 | The join bar, "Where this came from," and the tools footer help in place. The poll lockout and the arrow-send do not. |
| **Total** | | **24/40** | **Acceptable** |

## Design Specificity Verdict

**LLM assessment.** The reading column is authored for this council. Parchment, a 646px measure, a Cormorant title, "Solved" as a word above the `h1`, regard under the monogram, Loremaster / Veteran / Member / Legend, and an accepted answer that says "Marked as the answer." The guest dock says "log in to add your counsel." That split from the Reply control is the shipped lexicon, used on purpose.

The contribution chrome could move to another forum unchanged. The desktop dock is a Slack formatting desk: eleven icon buttons, an emoji face, an up-arrow send. Reactions on the posts are 👍 ❤️ 😂 🎉 🔥 💯 😮 😢 👀, while the same posts print regard as commends. `templates/partials/post.php` says production reactions are raw emoji from `ReactionService::ALLOWED`, with no name to render. `PRODUCT.md` still names the reaction set Commend, Kindled, Seconded, Illuminating. The page currently shows the emoji tray.

**Deterministic scan.** `impeccable detect` on `templates/thread.php` and the eleven partials that compose it: 0 findings, exit 0. The scan does not see CSS hover, the 860px dock rule, poll copy, or one-click forms. Those issues are from the live page and the styles, and the detector did not catch them. No false positives to clear.

**Visual overlays.** No reliable user-visible overlay is available. Mutation of `document.title` and a script node succeeded. The browser then refused `http://localhost:8400/detect.js` because `script-src 'self'` does not allow that origin. The same block occurred for the guest page at 1280 and at 390, and for Elladan's signed-in page. `window.impeccableScan` stayed undefined.

## Overall Impression

This is a reading room with a chat app bolted to the bottom. The title, the byline, and Arwen's accepted card are the hall. The eye then lands on a formatting desk that never rests on desktop, a poll that withholds its choices from a guest, and a thumb where the page has been speaking of commends.

Cognitive load is high: 4 of 8 checklist items fail. The title still wins, related tools stay closed until asked, and the reader does not have to remember a previous screen. What fails is focus and choice. At 1440 the signed-in dock is about 254px and the study scroll is about 524px, so reading and composing share the screen before anyone has chosen to answer. The format row is eleven controls in one group. Topic management, once opened, offers six actions: Unassign, Clear accepted answer, Pinned above the board, Locked to replies, Close poll, Split or merge. With Topic tools open, the composer stays visible under the scrim. On Elrond's first paint a six-step tour ("This is your community home") covered that composer. The phone already rests the same form at about 52px until focus. That rest is inside `@media (max-width: 860px) and (scripting: enabled)` in `public/assets/app.css`. Above 860px the format row is always on.

## What's Working

- The study head keeps its promise at both widths. "Solved" sits above the `h1`, so a screen reader does not announce the chips as part of the title. At 390px the byline "Opened by Erestor · Sep 23 · 5 replies" stays whole, and the monograms wrap under it.
- The accepted answer is a different object from a normal reply: a wash, a check, the words "Marked as the answer," and a gilt monogram. Status is a word and a colour together.
- Topic tools is a real sheet. Watch is a three-way segment, snooze is separate, Standing, Tags, Living Brief, and Topic management stay collapsed, focus moves to Close, and Escape returns the reader to the page. The footer changes from "yours alone" for a member to "Warden acts are recorded in the ledger" for Elrond.

## Priority Issues

### [P1] The desktop reply dock is open before anyone is replying

At 1440×900, `#reply` rests near 254px and cuts the opening post off at the format row. The same form at 390px rests as one line, placeholder plus arrow, and grows on focus. The phone already has the manners the desktop reading column needs. The product still wants a sticky composer. The change is the resting height above 860px: one line until focus, with the format row arriving when someone is actually answering.

**Why it matters.** The primary job on this page is to read the topic. The dock spends the first screen on Bold, Italic, Strike, and an arrow.

**Fix.** Apply the existing rested-dock rule from the 860px block to the desktop thread dock, and keep `.is-expanded` for a draft or a validation error so typed counsel is not hidden.

**Suggested command.** `/impeccable layout`

### [P1] A guest can read the poll question and none of the choices

Under the opening post the card says "Poll · choose one," then "Where should ratified decisions live?", then only "Results are visible after voting or after the poll closes." `templates/partials/poll.php` renders options when `can_vote` or `results_visible` is set. A guest matches neither, so the choices never appear. There is no "Log in to vote" on the card. The dock's Log in does not say that signing in reveals the choices.

**Why it matters.** Guests are here to read. A poll whose answers are hidden is a locked box in the middle of the argument.

**Fix.** Show the option text. Keep the tallies hidden while the policy says so. Put "Log in to vote" on the card.

**Suggested command.** `/impeccable clarify`

### [P1] "Clear accepted answer" and "Close poll" submit on one click

In Elrond's Topic management they sit in the same list as the pin and lock switches, as quiet text buttons. `templates/partials/thread_tools.php` posts `/t/{id}/unaccept` and `/polls/{id}/close` with no confirm. A mis-tap changes the public standing of the topic. "Warden acts are recorded in the ledger" is a record, not a way back.

**Why it matters.** These two controls rewrite what the hall says the council decided.

**Fix.** Ask once, in the sheet, and name the consequence ("The solved mark comes off this topic"). Leave the reverse action in the same list.

**Suggested command.** `/impeccable harden`

### [P2] The esteem gesture is a generic emoji tray

The opening post shows 👍 6. Glorfindel's reply shows 💯 3. Arwen's answer shows 👍 18. The add-reaction menu is 👍 ❤️ 😂 🎉 🔥 💯 😮 😢 👀. The byline beside those posts is regard, counted as commends. The code comment in `post.php` is explicit that these reactions have no names. The brand lexicon names Commend, Kindled, Seconded, and Illuminating.

**Why it matters.** The page teaches two languages of esteem at once. A new member cannot tell whether the thumb and the commend count are the same act.

**Fix.** Render the named set as a word plus the commend star. Retire the emoji tray from this surface, or label each emoji with the council word it stands for until that set exists.

**Suggested command.** `/impeccable clarify`

### [P2] Desktop post actions are invisible, and Send has no visible name

With scripting on, `.post-toolbar` is `opacity: 0` and `pointer-events: none` until hover or focus, and each button is 28×28. Quote, react, and More are guesses. `.composer-send` is a 36×36 arrow whose accessible name is "Reply" and whose visible content is `partials/icon` `arrow-up`.

**Why it matters.** The acts a member came to do — answer, quote, react — are either unlabeled or absent until the pointer finds them. Keyboard focus is specified to reveal the toolbar; the buttons are still 28px, and that ring was not verified by tabbing onto them.

**Fix.** Keep one quiet, always-present action on the post, at least More. Put the word Reply on the send control while the dock is at rest.

**Suggested command.** `/impeccable polish`

## Persona Red Flags

**Jordan (guest from search).** The poll card is the break. She can read "Where should ratified decisions live?" and cannot see the choices. The only next step is the dock's Log in, which does not mention the poll. The roster `.thread-participants` is ER, GL, EL, AR, with names only in `title` tooltips. The opening reaction is an unlabeled thumb.

**Alex (returning member).** He cannot put the composer away. "Minimize reply" is in the form and `display: none` on this desktop. The post toolbar does not exist until he hovers the card. Opening Topic tools shows "every reply" on the button and "Instant" selected inside. On Elrond's first paint, a tour card ("Welcome. This is your community home. 1 of 6. Skip / Next") covered the composer. Skip was visible. The card's "home" claim is false on a topic.

**Sam (keyboard and screen reader).** Topic tools is in good shape: `role="dialog"`, `aria-modal="true"`, focus moved to "Close Topic tools," Escape closed it. The reply field is announced as a popup menu: `composer.js` and `app.js` set `role="combobox"`, `aria-haspopup="listbox"`, and `aria-autocomplete="list"` on `#composer-body-reply-thread-17` even when the suggestion list is closed. `.composer-send` has no visible text. At desktop rest the post toolbar is opacity 0. Participant names sit on `title`, which is not a reliable accessible name.

## Minor Observations

- At 390px the top bar clips "Inbox" to "Inbo" between Boards and the search icon.
- The byline date is "Sep 23" with no year. A day rule in the stream says "September 24, 2026."
- The watch summary prints "instant" at the right and again as the selected "Instant" segment. The sheet foot repeats the "yours alone" sentence that already sits under the snooze chips.
- The small esteem marks beside Starred, Topic tools, and the regard count read as plus signs at chip size.
- Related is one quiet chip after the stream. That placement is right.
- Guest "Status history" is a closed disclosure under the tags. It was not opened.

## Questions to Consider

- The living brief sits between the opening post and the first reply, and it already states the council's conclusion. Should the hall's memory speak before anyone has heard the council, or only after the record?
- The phone already rests the reply as one line. What would change if the desktop dock had the same manners, and the formatting desk appeared only when someone actually answered?
- Regard on this page is a commend count. The gesture under the post is a thumb. If the reaction set is Commend, Kindled, Seconded, and Illuminating, what is the thumb still doing in the room?
