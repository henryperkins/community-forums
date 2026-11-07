---
target: "https://forum.candidary.online/inbox"
total_score: 24
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 2
target_identity: "file:/home/azureuser/community-forums/templates/inbox.php"
target_fingerprint: "sha256:73f68bd4a61af42a15b397ec448b7b2190e757fe3049141947557502f6ed6870"
target_path: /home/azureuser/community-forums/templates/inbox.php
timestamp: 2026-11-07T10-59-44Z
slug: templates-inbox-php
---
Method: dual-agent (A: ses_e4a840dbfffeS7CNbWk2YPcJ52 · B: ses_e4a83ba04ffegbZyFaJ3ypt4cB)

#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Unread counts reconcile live across topbar, subheader, and rail; however, the onboarding tour highlights `.forum-bar-brand` at top-left while its modal card floats detached over rows 6–7 at the bottom. |
| 2 | Match System / Real World | 3 | Literary council voice lands well, but personal hiding splits across four terms (`Til tomorrow`, `Show in Inbox`, `Hide from Inbox`, `Snoozed`) and the mobile `For You` menu opens with `SORT` above `YOUR QUEUE`. |
| 3 | User Control and Freedom | 1 | `.inbox-reading.is-open { overflow: hidden; }` locks the reading pane's scrollbar so wheel/touch scrolling drags the entire `.app-shell` off-screen and clips replies; mobile `Minimize reply` is a dead button in `/inbox`. |
| 4 | Consistency and Standards | 2 | CSS `:last-child` specificity bug shrinks unselected sort options (`Newest first`, `Most commended`) to `10.56px` mono in the compact menu; composer chrome uses a color OS emoji (`😊`); Twilight checkboxes glare `#FFFFFF`; `/register` drops `?next=%2Finbox`. |
| 5 | Error Prevention | 3 | Bulk actions validate and preserve selections on 422 and shortcuts pause inside open menus/dialogs, but `Mark this page read` commits immediately with no undo. |
| 6 | Recognition Rather Than Recall | 2 | `Show:` exposes 12 scopes (9 at `0` topics) with equal weight; keyboard shortcuts (`j`/`k`/`e`/`s`/`#`) lack visible hints and silently fail unless `.thread-title` or `[data-inbox-list]` holds focus. |
| 7 | Flexibility and Efficiency | 2 | Shift-click range selection and shortcuts exist, but `1280px–1440px` split-pane geometry gives `660px` to the queue list and crushes the reading column (`h2`/lede/composer) to `248px–258px`. |
| 8 | Aesthetic and Minimalist Design | 2 | Every row in `For You` repeats a gold `Watched topic` pill, permanent checkboxes claim a 54px gutter, the preview reply composer never collapses to its 1-line resting dock, and `320px` rows stack to `208px` tall. |
| 9 | Error Recovery | 4 | Preview failure/timeout recovery offers `Try again`, `Open full topic`, and `Return to previous topic` while preserving any unsent reply draft in the reading pane. |
| 10 | Help and Documentation | 2 | `Inbox help` clearly explains views, snoozing, and shortcuts, but is buried under the unlabeled `...` menu with no `?` shortcut trigger, while the `Welcome` tour floats unanchored over the queue. |
| **Total** | | **24/40** | **Acceptable** |

#### Design Specificity Verdict

**LLM assessment**: The typographic and chromatic world ("The Councillor's Hall" — Cormorant Garamond headings, EB Garamond prose, Marcellus lapidary caps, JetBrains Mono tabular figures, warm parchment/evergreen/mallorn-gold in Day and slate-blue in Twilight) is unmistakably authored for this product. The reading pane's editorial attribution bar and un-avataired manuscript lede (`partials/inbox_preview.php`) honor the "Discourse bones + Outlook discipline" lineage. However, on `/inbox`, that identity is undermined by generic issue-tracker triage chrome (a 12-option scope dropdown, permanent bulk checkboxes on every row, and an unanchored bottom-floating `Welcome (1 of 5)` SaaS tour widget), repetitive gold `Watched topic` chips on every row that turn Mallorn Gold into wallpaper, a color OS emoji (`😊`) inside the reply toolbar, and inverted split-pane proportions that starve the reading hall of horizontal room.

**Deterministic scan**:
- **PHP template source scan** (`templates/inbox.php`, `templates/partials/inbox_*.php`, `templates/partials/thread_row.php`): **0 findings** (exit code `0`).
- **Rendered authenticated HTML scan** (`/inbox` and `/inbox` with active preview pane, stylesheets resolved): **13 warnings** on the preview view (`11` on the list-only view):
  - **`11× undersized-ui-text` (True positives — real CSS specificity & token bugs):**
    1. **2× sort labels (`"Newest first"`, `"Most commended"`, `10.56px`):** In `templates/partials/inbox_view_controls.php:34–37`, `.inbox-compact-menu` uses `.inbox-scope-menu-panel` and renders Sort options above Scope options. In `templates/partials/inbox_choices.php:5–8`, the active sort option (`Latest activity`) has a trailing `<svg>` checkmark, so its text `<span>` is not `:last-child` (`13.76px` Marcellus). The two unselected sort options have no `<svg>`, so their label `<span>` is `:last-child` and gets hit by `public/assets/app.css:16296` (`.inbox-scope-menu-panel a > span:last-child { color: var(--text-faint); font-family: var(--font-mono); font-size: .66rem; }`), shrinking two primary sort labels to `10.56px` in faint monospace.
    2. **8× scope counts (`"27"`, `"13"`, `10.56px` across desktop and compact menus):** `public/assets/app.css:17757` (ADR 0044) attempted to raise `.inbox-scope-menu-panel a > .inbox-choice-state` (`(0,2,1)`) to `var(--text-chip)` (`0.7rem` / `11.2px`), but the earlier selector `.inbox-scope-menu-panel a > span:last-child` (`(0,2,2)` at line 16296) wins by specificity, holding every count at `0.66rem` (`10.56px`) and `--text-faint`.
    3. **1× reply eyebrow (`"Add your counsel"`, `10.56px`):** `public/assets/app.css:16753` hardcodes `.inbox-preview-reply > .eyebrow { font-size: .66rem; }` (`10.56px`), below `--text-eyebrow` (`0.72rem` / `11.52px`) and `--text-chip` (`0.7rem` / `11.2px`).
  - **`1× tiny-text` (`11.84px` on `<p class="inbox-shown-count">Showing 20 of 27 topics</p>`) — False positive:** Detector flags `<p>` elements below `12px` as body prose; in `DESIGN.md`, `.inbox-shown-count` (`0.74rem` JetBrains Mono) is a tabular count footer.
  - **`1× nested-cards` (`Card inside card (div)` on `.inbox-preview`) — False positive:** Detector flags `.composer-box` inside `.inbox-reading` (`background: var(--surface-raised)`); `.inbox-reading` is a full-height layout pane and `.composer-box` is the bordered form field container.

**Visual overlays**: No user-visible `[Human]` browser overlay is available. Mutable script injection preflight (`http://localhost:8400/detect.js` from `impeccable live-server`, stopped cleanly via `live-server stop --keep-inject`) was blocked on both `https://forum.candidary.online/inbox` and `http://localhost:8089/inbox` by the application's strict Content Security Policy (`Content-Security-Policy: default-src 'self'; ... script-src 'self'; style-src 'self'`), which rejects cross-port `<script>` tags and blocks WebAssembly instantiation without `'wasm-unsafe-eval'`, in addition to running in headless Playwright.

#### Overall Impression

The Imladris visual language and progressive-enhancement architecture give `/inbox` a calm, literary foundation — optimistic unread reconciliation, draft-preserving failure recovery, and the manuscript-style reading header are genuinely well-crafted. Right now, however, three mechanical regressions break the core split-pane reading experience on `https://forum.candidary.online/inbox`: opening any topic locks the reading pane's scrollbar (`overflow: hidden`) so wheel/touch scrolling drags the entire application shell into a blank void; the desktop grid gives `660px` to the topic list and squeezes the reading column to `248px–258px`; and the inline reply composer never collapses into its resting single-line dock. Fixing the reading pane's scroll/grid mechanics and distilling the noisy `For You` queue and menus is the single biggest opportunity.

#### What's Working

1. **Manuscript-grade reading pane header and lede (`templates/partials/inbox_preview.php`, `public/assets/app.css:16645–16745`):** Setting the opening post (`is_op = 1`) as an un-avataired editorial lede directly beneath a single ruled attribution bar (`#Board / 18 minutes ago`, Cormorant `h2`, author monogram + standing + reply count + `Open full topic`) gives the preview pane the poise of a book rather than a chat widget.
2. **Draft-safe preview recovery and live unread reconciliation (`public/assets/app.js:1706–1915`):** Selecting a topic updates unread indicators and `aria-label`s across the row, subheader, `Show:` menu, topbar `Inbox` pill, and sidebar board row in real time; if a preview fetch fails or times out (`15s`), `showPreviewFailure()` preserves the previous topic and any unsent reply draft with a one-click `Return to previous topic` recovery button.
3. **Resilient no-JS progressive-enhancement floor (`templates/inbox.php`, `templates/partials/thread_row.php`):** Every triage action — scope/sort navigation, pane toggles (`/settings/member-surfaces`), per-row star/read/snooze/visibility toggles, and bulk sweep actions (`/inbox/bulk`) — works end-to-end as server-rendered HTML forms and native `<details>` disclosures.

#### Priority Issues

- **[P0] What**: Reading pane cannot scroll when open; wheel/touch scrolling drags the entire 3-pane shell into a blank void and clips replies (`public/assets/app.css:1966`, `public/assets/app.js:1689`).
  - **Why it matters**: Clicking any topic calls `setMobileReading(true)` at all viewport widths (`1440×900`, `1280×800`, `900×800`, `390×844`, `320×568`), adding `.is-open` to `<section class="inbox-reading">`. Stale rules at `public/assets/app.css:1966–1985` set `.inbox-reading.is-open { overflow: hidden; }` and `.inbox-reading.is-open [data-inbox-reading-content] { height: 100%; min-height: 0; }`, overriding `.inbox-reading { overflow-y: auto; }` (line 16577). The overflowing `<article class="inbox-preview">` inflates `document.documentElement.scrollHeight` (`1438px` at `1440×900`); wheel or touch scrolling leaves `.inbox-reading.scrollTop` at `0` and scrolls `window.scrollY` instead, pulling `.app-shell` upward off-screen while `.inbox-reading`'s `overflow: hidden` clips all lower replies and the reply box into a blank void.
  - **Fix**: Remove the stale `.inbox-reading.is-open { overflow: hidden; }` and fixed-height `[data-inbox-reading-content]` rules at `public/assets/app.css:1966–1985` so `.inbox-reading` retains `overflow-y: auto` and scrolls its own content at all widths; guard mobile pane-hiding classes (`is-hidden` / `is-open`) to `< 1280px`.
  - **Suggested command**: `/impeccable harden`

- **[P1] What**: Split-pane desktop proportions are inverted (`660px` topic list vs `248px–258px` reading column at `1280px–1440px`), and the modal onboarding tour floats detached over the queue (`public/assets/app.css:16971`, `public/assets/tour.js:46`).
  - **Why it matters**: `DESIGN.md` specifies a `272px` sidebar rail and a `410px` topic list inside the `1280px` `.app-shell`. Instead, `body.is-reading-open .inbox-shell` uses `grid-template-columns: minmax(430px, 660px) minmax(0, 1fr)`. Inside `.main` (`1008px` at `1440×900`, `998px` at `1280×800`), `.inbox-list` claims the full `660px` (`65.5%`), leaving `.inbox-reading` only `338px–348px` outer width and — after `padding: 26px 40px 72px` — crushing `.inbox-preview` (`h2`, lede, composer) to **`248px–258px`** and reply prose (`.formatted-content`) to **`208px–218px`** (narrower than on a `390px` phone!). Meanwhile, `.tour-popover` (`role="dialog" aria-modal="true"`) floats at the bottom of the screen over rows 6–7 trapping `Tab` focus while pointing to `.forum-bar-brand` at top-left.
  - **Fix**: Change `body.is-reading-open .inbox-shell` at `min-width: 1280px` to `grid-template-columns: minmax(360px, 410px) minmax(0, 1fr)` (and reduce `[data-inbox-reading-content]` horizontal padding from `40px` to `clamp(20px, 3vw, 40px)`) so the reading pane gets ~598px (`~520px` content measure). Anchor `.tour-popover` beside its target element and name the target explicitly in Step 1 copy.
  - **Suggested command**: `/impeccable layout`

- **[P1] What**: Reply composer inside `/inbox` never collapses to its resting 1-line dock, its mobile `Minimize reply` button is non-functional, and composer/reading controls fall below `44×44px` (`public/assets/app.css:1954–2045`, `16748`).
  - **Why it matters**: `composer.js:3451` wires `.reply-composer` inside `.inbox-preview-reply` as a dock (`data-composer-dock="1"`) and unhides `<button class="composer-minimize" data-composer-minimize>` (`"Minimize reply"`) at `<= 860px`. Because all resting-dock CSS in `public/assets/app.css:1992–2044` is scoped to `.thread-dock .reply-composer:not(.is-expanded)`, the `/inbox` composer is permanently expanded to full height (`10` visible buttons + textarea) and tapping `Minimize reply` on mobile does nothing. Furthermore, inside `.inbox-preview`, `.btn.composer-send` (`Reply`) is `36×36px`, toolbar buttons are `28×28px`, `.inbox-preview-open` (`Open full topic`) is `84×19.4px`, and `.inbox-preview-reply > .eyebrow` (`Add your counsel`) is `10.56px` (`0.66rem`).
  - **Fix**: Scope the resting/expanded reply-dock rules in `public/assets/app.css:1954–2045` to `:is(.thread-dock, .inbox-preview-reply) .reply-composer`, hide `.inbox-preview-reply > .eyebrow` while resting and raise it to `var(--text-eyebrow)` (`0.72rem`) when expanded, and apply the `44×44px` target floor to `.inbox-preview .composer-send` and `.inbox-preview-open`.
  - **Suggested command**: `/impeccable polish`

- **[P2] What**: Scope/Sort menu CSS specificity bug (`10.56px` mono on unselected sort labels), inverted mobile compact menu order, and high visual noise in `For You` rows (`public/assets/app.css:16296`, `templates/partials/inbox_view_controls.php:34–37`, `templates/partials/thread_row.php`).
  - **Why it matters**:
    1. `.inbox-scope-menu-panel a > span:last-child` (`(0,2,2)` at `app.css:16296`) beats `.inbox-scope-menu-panel a > .inbox-choice-state` (`(0,2,1)` at `app.css:17757`), forcing scope counts to `10.56px` (`0.66rem`) and unintentionally shrinking `"Newest first"` and `"Most commended"` in `.inbox-compact-menu` to `10.56px` faint monospace because they lack the active `<svg>` checkmark.
    2. `.inbox-compact-menu` triggers with `For You · 27 topics · Activity` (Scope first) but renders `SORT` at the top of the panel above `YOUR QUEUE` (15 items total).
    3. Every watched-board row in `For You` wears a gold `Watched topic` chip (`--gold-soft`), violating the spirit of the One Gold Rule by painting 20 rows in gold, and `320×568` mobile rows stack metadata across 6–7 vertical lines (`207.9px` tall, fitting 1.5 topics per screen).
  - **Fix**: Replace `.inbox-scope-menu-panel a > span:last-child` at `app.css:16296` with `.inbox-scope-menu-panel a > .inbox-choice-state` at `font-size: var(--text-chip)` (`0.7rem` / `11.2px`) and `color: var(--text-muted)`; render Scope groups above `Sort` in `inbox_view_controls.php`; mute zero-count scopes and move `Snoozed` to the bottom of `Your queue`; style passive `Watched topic` chips in sunken parchment (`--surface-sunken`) so gold is reserved for personal signals (`Mentioned`, `Assigned`, `Unread`); and keep mobile `.thread-meta` inline-wrapped to compact `320px` row height.
  - **Suggested command**: `/impeccable typeset`

- **[P2] What**: Brand and entry-gate rough edges: color OS emoji (`😊`) in composer chrome, glaring `#FFFFFF` Twilight checkboxes, four-way snooze/hide terminology split, and context-free `/login?next=%2Finbox` redirect (`templates/partials/composer.php`, `templates/auth/login.php`, `public/assets/app.css`).
  - **Why it matters**:
    1. The composer emoji toggle prints a color OS emoji `😊`, breaking `PRODUCT.md` (*"Emoji: Not in UI chrome"*).
    2. Unchecked checkboxes in `.inbox-select-all` and `.thread-row-select` render as stark `#FFFFFF` squares in Twilight mode.
    3. Hiding a topic splits across four phrases (`Til tomorrow`, `Show in Inbox` switch, `Hide from Inbox` bulk action, and `Snoozed` scope).
    4. Visiting `https://forum.candidary.online/inbox` signed out redirects (`302`) to `/login?next=%2Finbox` with no explanation that `/inbox` is a personal member surface, drops `?next=%2Finbox` on the `/register` link, and renders `.passkey-signin` (`Sign in with a passkey`) flush against the bottom of the full-width `Log in` button.
  - **Fix**: Replace `😊` in `.composer-emoji-toggle` with a 16px stroked monochrome SVG icon; style checkboxes with `color-scheme: dark` / `--surface-sunken` in Twilight; unify snooze/hide copy (`Snooze until tomorrow` / `Show in Inbox`); and on `/login?next=%2Finbox`, show a calm notice with a link to public boards, preserve `?next=` on `/register`, and space `.passkey-signin` with `margin-top: var(--space-3)`.
  - **Suggested command**: `/impeccable clarify`

#### Persona Red Flags

**Alex (Power User)**:
- Presses `j` immediately on `/inbox` load to step through topics — nothing happens because `public/assets/app.js:1933–1937` ignores `j`/`k`/`e`/`s`/`#` unless DOM focus is already on `.thread-title`, `[data-inbox-list]`, or the preview `<h2>`.
- Presses `?` to view keyboard shortcuts — nothing happens; shortcuts are buried two clicks deep inside the unlabeled `...` (`Inbox actions`) menu → `Help`, and `j` conflicts with `data-shortcut="j"` on the topbar `Reading pane` button.
- Clicks a topic at `1440×900` or `1280×800` and scrolls the reading pane (`248px–258px` content width) with a mouse wheel — `.inbox-reading.is-open { overflow: hidden; }` blocks scrolling in the pane and drags the entire `.app-shell` off-screen into a blank void.

**Sam (Accessibility-Dependent User)**:
- Lands on `/inbox` with `data-tour="1"`: focus is trapped inside `.tour-popover` (`role="dialog" aria-modal="true"`) floating over rows 6–7 while its copy says *"This is your community home. Select it any time to come back here"* without naming what *"it"* is (`.forum-bar-brand`).
- Tabs to `Minimize reply` (`[data-composer-minimize]`) inside the mobile `/inbox` reading pane and activates it: the button announces `"Minimize reply"` and is focusable, but activating it does nothing at all.
- Opens the `Show:` menu and must tab through 12 scope links (9 of which have `0` topics) every time.

**Casey (Distracted Mobile User)**:
- Opens `/inbox` on a `320×568` phone in Twilight mode: each `.thread-row-inbox` is `207.9px` tall (fitting barely 1.5 topics in the `399px` list viewport) with glaring `#FFFFFF` unchecked checkboxes down the left gutter.
- Taps `For You · 27 topics · Activity` (`<= 460px` compact menu): `SORT` options appear at the top instead of `YOUR QUEUE`, and `"Newest first"` / `"Most commended"` shrink to `10.56px` faint monospace.
- Opens a topic on a `390×844` phone and swipes down to read replies: the page scrolls into an empty beige void and clips the conversation.

**Returning Council Member (`PRODUCT.md` Primary Member Audience)**:
- Opens `For You` for daily triage: all 20 rows wear the same gold `Watched topic` pill (`--gold-soft`), drowning out which topics actually have unread replies or direct mentions.
- Tries to mute an uninteresting watched thread: encounters `"Til tomorrow"` and `"Show in Inbox"` in the row menu, `"Hide from Inbox"` in the bulk bar, and has to guess that permanently muted threads live under `"Snoozed"`.

**Guest / Signed-Out Visitor Arriving via `https://forum.candidary.online/inbox` (`PRODUCT.md` Guest Audience)**:
- Follows a shared `https://forum.candidary.online/inbox` link and lands on `/login?next=%2Finbox` (`"WELCOME BACK / Log in"`) with zero context that `/inbox` is a personal member queue or that the community's boards can be read without an account at `/`, and loses `?next=%2Finbox` if they click `"Create an account"`.

#### Minor Observations

- **Row click area is limited to the title text (`templates/partials/thread_row.php`):** Clicking the snippet excerpt or whitespace inside `.thread-row-inbox` does not load the preview; only clicking the `a.thread-title` text (`22px` tall) triggers `loadThread()`.
- **Pager target size (`templates/partials/pager.php`):** `.pagination a.page` measures `30×39.2px` (`Next ›` is `64×39.2px`), falling just short of the `44×44px` touch floor.
- **One Marcellus weight violation in hidden DOM:** `<h2 class="composer-emoji-title">Emoji</h2>` inside the closed emoji picker uses `font-weight: 500` on `Marcellus` (which ships only `400`).
- **Triplicated unread counter in an 80px radius:** Unread count appears in the topbar `Inbox` pill, again right below it as `.badge` beside the `Inbox` `h1`, and again inside the `Show:` trigger/menu.

#### Questions to Consider

- *"If Mallorn Gold is the system's one bright thread marking where to look, why does every passive watched-board row in `For You` wear a gold `Watched topic` badge?"*
- *"On a 1280px–1440px laptop, why is the topic queue 660px wide while the reading hall is squeezed to a 248px–258px text column?"*
- *"Do members use bulk checkboxes often enough to justify a permanent 54px checkbox column and `Select all on this page` bar on every visit — especially on a 320px phone?"*
- *"Why do temporary timers ('Til tomorrow') and permanent topic mutes ('Turn off Show in Inbox') share a single bucket named 'Snoozed'?"*
