# Assessment B — isolated detector and browser evidence

Target: `templates/inbox.php`, including `templates/partials/thread_row.php` and the final `public/assets/app.css` / `app.js` cascade. Revision: `573f905d71f3c146072cffd5fecb2d2ffaca995b`.

## Provenance and limits

- Source read-only; initial and final source status clean. No app, source, tests, or fixture edits. The only browser POST was `/login`; all other interactions were disclosures and client selection. No topic preview, read, star, snooze, bulk action, or appearance save was submitted.
- Fresh independent Chromium context, separate new tab for each of six variants: 1440×1000, 393×852, 320×760; explicit DOM-local light/dark and medium font-size. Headless Playwright comes from the repository's installed browser package. No generic native authenticated browser/mutable injection/custom viewport tool exists.
- Standard prepared Alice fixture: 20 loaded rows from 30 For You topics; many TI topic names are fixture content, not interface copy.
- Single bounded batched pass, seven capture states per variant (42 screenshots): closed, scope menu, sort menu, actions with Help closed, actions with Help open, first row menu, first row selected.
- Firefox, WebKit, no-JS, enlarged text, persisted appearance, keyboard action execution, empty queue and snoozed-row state were not tested by this batch. This is a critique evidence pass, not a release qualification run.
- Full measurements: `browser-evidence.json`. Full screenshots and script are beside this report.
- IMPORTANT measurement limitation: `elements` / `outsideViewport` used an element-only visibility filter. Chromium gives layout boxes to descendants of closed native `<details>`, so raw outside counts (4 at393,56 at320) include unpainted menus and must NOT be reported as real overflow. Use the explicit `panels` entries (open menus only) and screenshots. Screenshots show no text cut off in the captured baseline, toolbar, selected, Help or row action states.

## Bundled deterministic scan

Ran exact bundled CLI, once each, from the project cwd:

`impeccable detect --json templates/inbox.php` → exit0, `[]`.

`impeccable detect --json templates/partials/thread_row.php` → exit0, `[]`.

Total: 0 findings, 0 advisories, no rule names or locations. Full JSON is stored in `inbox-detector.json` and `thread-row-detector.json`. This does not prove the visual surface is clean: CLI help says non-HTML inputs use regex matching; PHP source does not provide the fully rendered linked stylesheet cascade. No extra scan was substituted or repeated.

False positives: none from the CLI. Pinned identity (all serif, parchment/evergreen/mallorn gold, restrained radii, flat ruled rows and semantic day/twilight) remains the reference; it should not be classified as blandness or an unsupported serif anti-pattern. DESIGN.md was read live; prior memory only helped identify the final application cascade as the right authority.

## Required visualization attempt

Mutable preflight succeeded: changed `document.title` and appended a script element of type `application/json` to the page head.

Started the bundled live-server in `/tmp/inbox-critique-evidence` with `impeccable live-server --background --port=8481`; raw connection metadata in `live-server-start.json`. Labeled headless tabs `[Human] Inbox · Assessment B`, scrolled top and appended `http://localhost:8481/detect.js` on desktop,393 and320 light variants, waiting2.5seconds per attempt.

All three injection attempts failed: load=false,error=true. Exact browser error:

`Loading the script 'http://localhost:8481/detect.js' violates the following Content Security Policy directive: "script-src 'self'". Note that 'script-src-elem' was not explicitly set, so 'script-src' is used as a fallback. The action has been blocked.`

No detector ran in the page. No reliable user-visible overlay exists. No browser presentation capability was exposed; the `[Human]` label belongs to headless evidence tabs only. CSP was not changed or bypassed. No unexpected page errors occurred.

Stopped own live-server with `impeccable live-server stop --keep-inject`; output `Stopped live server on port8481.`; independent `ss` check found no8481 listener. The app8031 belongs to the root agent and was left running.

## Rendered facts and critique implications

### Mobile text has too little useful width

At320, the queue row is268px wide but title/metadata receive121px. The four permanent controls/gutters (checkbox, unread, star, ellipsis) and gaps take the rest. The first title uses3lines; `Share your favourite keyboard shortcuts` uses4lines. Their metadata uses4 separate lines at10.4px, with a10px row gap. First/second rows are205.83/219.5px tall. At393, main text gets194px and the same rows are159.75/117.75px. Desktop rows are94.73px.

This is cramped allocation and poor scan density rather than literal text clipping. Separate the title/content allocation from trailing actions and use a smaller vertical metadata gap; retain the readable title size and all-serif system. Source: `app.css:15429`, `15451`, `15546`, narrow row rules around16153; partial: `thread_row.php:110`, `129`.

Evidence: `mobile320-light-closed.png`, `mobile393-light-closed.png`, corresponding dark captures.

### Header heights and paired row controls actually match; control grammar still varies

All three Inbox toolbar triggers are44px high with7px radius in every viewport/theme. Desktop widths: scope196,sort165,overflow44. At393:150.03,48,44. At320:77.03,48,44. Overflow's border is transparent while scope/sort have the normal visible hairline. Scope becomes a two-line content block and Sort becomes `Sort:` over `Activity`; chevrons disappear on narrow layouts. The scope block expands into available mobile width, producing noticeably unequal proportions.

Star and row ellipsis match each other:28×28desktop,34×34mobile,4px radius. A critique should not claim these adjacent pairs have unequal heights or shapes. Bulk buttons use30px height/4px radius/11.2px type; row menu commands use32px/12.48px; toolbar commands use44px. The meaningful consistency issue is different control scales, border treatments and label layouts across surfaces, not broken geometry within a pair. Source: `app.css:15360`, `15389`, `15580`, `15614`, `16638`, `16674`, `16694`.

### Selection inserts a large wrapped command strip

Selecting one row inserts a48px-high strip on desktop and86px on both mobile widths; the first row shifts down64px on desktop and102px on mobile. At393, `Snooze until Monday` alone occupies the second line. At320, `Star` and Snooze move to the second line. There is no cropped label; the full labels cause an imbalanced action arrangement and list jump. Bulk control type is11.2px and height30px. Shortening the snooze entry and putting its two requested choices in a disclosure can remove the especially long command. Source: `inbox.php:97`, `app.css:15372`.

Evidence: `desktop-light-selected.png`, `mobile393-dark-selected.png`, `mobile320-dark-selected.png`.

### Snooze choices disagree with the requested two-option model everywhere

First row action panel shows five commands: Mark read plus four repeated-prefix snoozes: `Snooze · Later today`, `Snooze · Tomorrow`, `Snooze · Monday`, `Snooze · Next week`. It is196×174px and each command32px high. Bulk and Help fix their snooze to Monday; the `#` shortcut also finds the Monday form. The requested `Til tomorrow` and `Til I turn it back on` are absent from this captured Inbox.

Reducing only the row menu leaves bulk/help/shortcut contradictory. Audit all three entry points plus the topic surface/backing service before changing this behavior. This batch did not execute snooze or prove indefinite snooze support. Source: `thread_row.php:197`, `inbox.php:85`, `102`, `108`, `app.js:1296`.

Evidence: `desktop-dark-row-menu.png`, `mobile320-light-row-menu.png`, Help and selected screenshots.

### Help and scope menus are physically legible, but information is hidden in layers

Overflow's closed Help state is280×106px; expanding Help grows it to280×351.58px. Text fits at320 and393, and JS repositions the panel after Help toggles. Help is nested under the same ellipsis as a bulk mutation, adding another discovery step for shortcuts. Its Monday explanation is outdated relative to the user's chosen snooze model.

Scope menu has12 choices in3 groups; its content is628px tall in a420px panel. In the top-scroll screenshot the third `Topic state` group sits below the fold. This is functional scroll containment, not text clipping, but it makes the least familiar choices harder to discover. All captured open panel boxes fit the viewport coordinates; at320 the scope panel's nominal right edge312 extends2px past the310px layout width reserved for the desktop-style scrollbar, and its screenshot right rule is partly clipped. Treat this as a small edge issue, not severe menu failure without a follow-up reproduction.

Source: `inbox.php:31`, `74`; `app.css:15310`, `15365`; `app.js:1053`.

### Two copy details make scope harder to understand

`Select all on screen` actually targets every loaded row on the current page, including rows below the scroll viewport (20 rows in this fixture). `Select all on this page` matches its scope. The reading placeholder includes `Without JavaScript, topics open as their own page` in normal member copy; a shorter task instruction can preserve this technical explanation in Help instead. Source: `inbox.php:111`, `151`, `app.js:1310`.

## What worked in the captured states

- Light and dark tokens preserve clear surface ownership and the same geometry.
- Native disclosures opened, JS moved them into usable coordinates, Escape dismissed them, and expanding nested Help refreshed placement.
- Queue titles wrap rather than truncate; why-this-topic chips and Pinned/Locked text preserve meaning beyond color.

## Source fingerprints

`templates/inbox.php` a21a29cbd032c32df02be91e1341736bff3f75f6cc7af22b0681f77ef543282f

`templates/partials/thread_row.php` 5c30d7b0e800e607f88c795f3510d34de302a43cd1f41bc09f6f1a77f7d361c8

`public/assets/app.css` e5a63e69e5cdfbf2e815d62d5c2f4cdffdd6233192dcea93d9783372e923009a

`public/assets/app.js` c2143dc6dfc3bf7236af11864d3ed35f5a480c11ff2f4284e4cf8ad9431daf92
