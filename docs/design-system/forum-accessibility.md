# Forum accessibility and reading baseline

**Reviewed:** 2026-11-07 (ADR 0056); refined the same day (ADR 0057). **Target:** WCAG 2.2 Level AA, including applicable Level A criteria.
**Scope:** the conventional forum theme replacing Imladris, including member,
authentication, composer, messaging, moderation, and administration surfaces.

This document defines acceptance requirements, project design choices, and the
bounded local browser validation completed for the replacement. Completed and
pending checks appear in §5; this is not a conformance claim or a deployment record.
The normative authority is [WCAG 2.2][wcag]; W3C Understanding documents and ARIA
Authoring Practices explain implementation and are not additional normative rules.
The criteria below are a focused baseline, not an exhaustive conformance audit.

## 1. Reading and visual design decisions

These are project choices, informed by [USWDS typography guidance][type]. WCAG AA
does not prescribe a font family, a default font size, or a specific line length.

| Area | Project decision | Reason and boundary |
| --- | --- | --- |
| Type family | `system-ui` for interface text and metadata; the generic `sans-serif` family for posts, messages and the composer; system monospace only for code and exact identifiers | No font downloads. MDN and CSS Fonts 4 describe `system-ui` as meant for interface text rather than long paragraphs; some systems do not let readers change it, while browsers let readers choose their `sans-serif` face. Reading studies show no single best face for everyone (§1a). A serif font is not inherently inaccessible. |
| Base sizing | Root `100%`; interface `1rem`; prose `1.125rem` | Approximately 16px and 18px at the browser's usual default; retain the reader's font preference. |
| Reading rhythm | Prose line height `1.65`, unitless; readable column up to `65ch` | USWDS recommends at least 1.5 for longer text and identifies about 66 characters as a useful extended-reading target. `ch` is an approximation, so inspect real content. |
| Metadata and labels | At least `0.875rem` (`--text-floor`); interface face; sentence case; normal letter spacing; tabular numerals for counts and times | Enforced at the source in the retained layers and pinned by `forum-refinement.spec.ts` (ADR 0057). Smaller secondary text still needs ordinary-text contrast. |
| Alignment | Start-aligned text and clear paragraph/heading separation | Avoid full justification. Keep headings meaningful and visually distinguishable. |
| Links | Blue link tokens with persistent underlines within prose | Color alone does not identify an action. Navigation may use other clear visual affordances. |
| Color modes | Light, dark, and system preference | Validate each resolved mode and meaningful interaction state independently. Dark mode does not establish accessibility by itself. |
| Controls | Aim for an actual 44 × 44 CSS pixel hit area for key standalone controls | The icon can be smaller. This exceeds the AA minimum; inline prose links are handled separately. |
| Surfaces and images | Solid reading surfaces; no decorative photography behind text | Keep content imagery responsive. Provide meaningful alternatives for informative images and empty `alt` for purely decorative images. |
| Focus | Clearly visible outline or border, with adequate offset and contrast | A shadow alone disappears in forced-colors mode. Aim to keep the entire focused control visible. |

### 1a. What the reading research supports

These findings inform the choices above; none is a WCAG requirement. They are
summarised from the primary sources listed, reviewed 2026-11-07.

| Question | Evidence | What it supports here |
| --- | --- | --- |
| Text size | Rello, Pielot & Marcos (CHI 2016, 104 eye-tracked readers): readability rose with size and comprehension was best at the larger sizes tested; line-spacing effects were small. Legge & Bigelow (2011 review): reading speed plateaus across a wide range of sizes above a critical print size, which is larger for people with low vision. | A generous `1.125rem` prose size that scales with the reader's browser and member settings. It does not establish 18px as a threshold. |
| Line length | Dyson & Haselgrove (2001) found about 55 characters per line best for comprehension, while longer lines were often read faster; Dyson's 2004 review reports readers prefer moderate lengths. | A bounded measure (`65ch` maximum; about 49 characters in the desktop topic column). WCAG 1.4.8 (AAA) sets 80 characters as an upper bound. |
| Line spacing | Little direct evidence beyond avoiding extremes (Rello et al. 2016). | `1.65` is a convention, compatible with WCAG 1.4.8's 1.5 and with 1.4.12 overrides. |
| Typeface | Arditi & Cho (2005): serifs had no effect on reading speed. Wallace et al. (ACM TOCHI 2022, 352 readers, 16 fonts): each reader's fastest font differed, by up to 35%, with no comprehension loss. Rello & Baeza-Yates (2016): sans-serif and roman styles read better than italic for readers with and without dyslexia. | No single house reading face. Prose follows the reader's own `sans-serif` setting; long italic runs are avoided. |
| "Dyslexia fonts" | Controlled studies (Kuster et al. 2018; Wery & Diliberto 2017) found no speed or accuracy benefit, and a 2026 meta-analysis in *Annals of Dyslexia* addressed the same question; one 2023 doctoral study reported gains with OpenDyslexic. | Do not ship a dyslexia font; leave font choice to the reader. |
| Dark mode | With display luminance controlled, polarity itself did not affect readability (Buchner et al. 2009); dark-on-light helped acuity and proofreading for younger and older adults (Piepenbrock et al. 2013); light-on-dark raised workload in some lighting conditions (Sethi & Ziat 2023). Claims about "halation" from very high dark-mode contrast are opinion, not established findings. | Keep light as the default and offer dark and system modes; measure both independently. |

Apply the reading width to prose, not indiscriminately to every table or the
whole forum shell. Long usernames, URLs, translations, quotations, and attachment
names must wrap without hiding essential information. Wide code and data tables
may have a local horizontal scroll area; ordinary paragraphs must still reflow.

## 2. Applicable WCAG requirements

All ratios below are minimums, measured using final rendered foreground and
background colors. Do not round a failing ratio up to a pass. Thin glyphs can
remain difficult to read despite a numerically passing color pair.

| Requirement | Level and criterion | Acceptance condition |
| --- | --- | --- |
| Page titles | A, 2.4.2 | Each page title names the page, then the community (`Inbox · RetroBoards`, ADR 0057); the home page is the community name. |
| Language | A, 3.1.1; AA, 3.1.2 | The document declares `lang`. Passages in another language need their own `lang`; posts have no language field yet, so a post written in another language is a known gap under 3.1.2. |
| Text contrast | AA, 1.4.3 | Ordinary text at least 4.5:1. Large text at least 3:1: 18pt regular or 14pt bold, approximately 24px or 18.67px. Metadata, labels, placeholders, and help text are included; inactive controls have an exception. See [contrast][contrast]. |
| Non-text contrast | AA, 1.4.11 | Necessary visual information identifying controls, states, or meaningful graphics has at least 3:1 against adjacent colors, subject to the criterion's exceptions. Decorative separators do not all need 3:1. See [non-text contrast][nontext]. |
| Non-color cues | A, 1.4.1 | Errors, unread/current/selected states, and links are understandable without color alone. Use text, shape, decoration, or another visible cue as appropriate. |
| Text alternatives | A, 1.1.1 | Informative imagery and functional icons have appropriate text alternatives; decorative imagery is ignored by assistive technology. |
| Relationships and reading order | A, 1.3.1–1.3.2 | Headings, lists, labels, tables, and content order retain programmatic meaning when CSS is removed or the layout collapses. |
| Text resize | AA, 1.4.4 | Text can reach 200% without lost content or functionality, subject to the criterion's exceptions. See [resize text][resize]. |
| Reflow | AA, 1.4.10 | At a width equivalent to 320 CSS pixels, content needs no two-dimensional scrolling, except content whose meaning or use requires it. A 1280 CSS pixel viewport at 400% browser zoom is a representative test. See [reflow][reflow]. |
| Text spacing | AA, 1.4.12 | Simultaneous user overrides to the four spacing values below cause no loss of content or functionality. They are override tolerances, not required default styles. See [text spacing][spacing]. |
| Keyboard operation | A, 2.1.1–2.1.2 | Operate applicable functionality by keyboard without a keyboard trap. Support normal editing keys in inputs. |
| Navigation and focus order | A, 2.4.1–2.4.3; AA, 2.4.6 | Provide a bypass mechanism, meaningful page titles, a sensible focus sequence, and descriptive headings/labels. |
| Visible focus | AA, 2.4.7 and 2.4.11 | Keyboard focus is visible and the focused component is not entirely hidden by author-created content. Sticky headers, drawers, and composer bars must not swallow focus. |
| Pointer targets | AA, 2.5.8 | Targets are at least 24 × 24 CSS pixels, or satisfy a specified exception such as sufficient spacing or inline placement. See [target size][targets]. |
| Visible labels and names | A, 2.5.3 and 4.1.2 | Accessible names include visible label text; controls expose correct names, roles, and values/states. |
| Errors and instructions | A, 3.3.1–3.3.2; AA, 3.3.3 | Identify detected errors in text, provide needed labels/instructions, and offer known correction suggestions where appropriate. |
| Accessible authentication | AA, 3.3.8 | Do not require an unsupported cognitive-function test. Preserve password-manager/autofill and paste support; allow a complete verification code to be pasted. See [authentication][auth]. |
| Status messages | AA, 4.1.3 | Relevant result, progress, and error-status updates can be announced without receiving focus. Not every DOM mutation is a status message. See [status messages][status]. |
| Hover/focus content | AA, 1.4.13 | Applicable tooltips/popovers are dismissible, hoverable, and persistent under the criterion's conditions. |
| Moving or updating content | A, 2.2.2 | Provide required pause, stop, or hide controls for applicable moving and auto-updating content. Avoid unnecessary animation. |

**Tracked AAA criteria (information, not targets).** 1.4.6 Contrast (Enhanced)
needs 7:1 for ordinary text: body and muted text pass in both modes, but the
light-mode link and action colour measures 6.70:1. 1.4.8 Visual Presentation
asks for a mechanism to choose colours, keep lines to 80 characters or fewer
(40 for CJK), avoid justification, use at least 1.5 line spacing with larger
paragraph spacing, and resize to 200% without horizontal scrolling. The
defaults meet the measure, justification and line-spacing items; browser and
user settings supply the rest.

The 44 × 44 target in this project is a design goal; WCAG 2.5.5 specifies that
size at **AAA**, with exceptions. Likewise, the quantitative focus-area rule in
2.4.13 is **AAA**, not AA. AA 2.4.11 permits partial focus obstruction; this
project should aim for full visibility. Use the [normative standard][wcag] when
assessing exceptions rather than treating this summary as a replacement.

### 2a. Contrast method status

WCAG 2.2's relative-luminance ratio remains the acceptance test. WCAG 3 is
still a Working Draft; its April 2026 Editor's Draft notes that its contrast
algorithm is "yet to be determined", and the APCA method, present in the first
public draft (2021), was pulled from the July 2023 Working Draft.
APCA lightness contrast (Lc) is recorded below only as information: it rates
the dark palette's link and faint text lower than WCAG 2 does, so those are the
first candidates if the dark palette is revised.

| Token pair | Light | Dark |
| --- | --- | --- |
| Body text on page | 14.10:1 (Lc 100.6) | 16.96:1 (Lc −103.2) |
| Muted text on page | 7.58:1 (Lc 86.3) | 11.95:1 (Lc −79.2) |
| Faint text on page | 6.31:1 (Lc 81.4) | 9.82:1 (Lc −67.9) |
| Link and action text on page | 6.70:1 (Lc 82.2) | 9.84:1 (Lc −68.0) |
| Primary button label on its fill | 6.70:1 (Lc −87.2) | 9.84:1 (Lc 68.9) |
| Field boundary on raised surface | 4.76:1 | 5.72:1 |
| Focus outline on page | 6.39:1 | 12.48:1 |
| Link colour against body text | 2.10:1 | 1.72:1 |

The last row is below the 3:1 of technique G183, so links inside text rely on
their underline, not colour, to satisfy 1.4.1. Prose and help links are
underlined; links standing alone in navigation, lists and metadata are
identified by position and layout.

## 3. Forum interaction requirements

- Use native links, buttons, form controls, headings, lists, and landmarks before
  adding ARIA. Label multiple navigation regions and provide a visible-on-focus
  skip link to the main content.
- Give each page a clear primary heading. Identify posts with semantic structure,
  authorship, timestamps, and stable permalinks. Repeated action names must be
  understandable in their post context.
- Express toggle state programmatically, such as `aria-pressed` for a bookmark
  button. A normal navigation disclosure does not need application-menu roles.
- Prefer explicit pagination or a discoverable “Load more” action for long
  discussions. Appending posts must preserve the reader's focus and position.
  Do not focus new posts or announce the entire discussion on every update.
- Do not add `role="feed"` merely because posts form a list. The [APG feed
  pattern][feed] establishes a focus/loading contract, article metadata, and
  keyboard expectations. Virtualization must not remove the focused post or
  make later page content unreachable.
- A modal dialog needs an accessible name, appropriate initial focus, contained
  Tab navigation, a usable close control, Escape dismissal where appropriate,
  an inert background, and focus restoration. For complex structured content,
  initial focus on a heading can preserve reading order. Follow the [APG dialog
  pattern][dialog]; do not flatten a long dialog into one `aria-describedby` string.
- Associate field labels, help, and errors. Keep invalid input visible and
  preserve the draft after server validation. Provide an error summary where
  useful; a temporary toast is insufficient as the only error explanation.
- Use short, targeted status announcements for results such as a posted reply,
  saved preference, or empty search. Reserve assertive announcements for urgent
  cases and avoid repeated announcements from polling.
- Reading, navigation, search, authentication, and posting retain server-rendered
  fallbacks under the existing [stack decision](../../DECISIONS.md). No-JavaScript
  support is this project's robustness contract, not a standalone WCAG criterion.

## 4. Spacing, platform preferences, and content stress cases

Test all four WCAG text-spacing overrides together using a test stylesheet or
browser accessibility tooling. A representative override is:

```css
* {
  line-height: 1.5 !important;
  letter-spacing: 0.12em !important;
  word-spacing: 0.16em !important;
}
p {
  margin-block-end: 2em !important;
}
```

Apply the values to actual paragraph equivalents as well if markup uses other
elements. Inspect clipped text, fixed-height controls, truncated labels, overlap,
and hidden errors. Keep the test override outside production assets.

Honor `prefers-reduced-motion: reduce`: suppress decorative movement and smooth
scrolling while retaining necessary feedback. [Reduced-motion preference][motion]
support is a project requirement; animation from interaction is addressed by
WCAG 2.3.3 at AAA, so do not label every animation an AA failure.

In [forced-colors mode][forced], preserve native controls and semantic outlines
or borders. System colors may replace authored colors; `box-shadow` and many
background images are suppressed. Avoid a global `forced-color-adjust: none`.
Check selected states, links, focus, validation, and icon-only controls in the
actual forced palette, not only in a dark screenshot.

Stress content includes a long discussion, nested quotes, a long unbroken URL,
long display names, an empty state, validation errors, images with captions,
code, tables, and translated or enlarged labels. Distinguish meaningful imagery
from decorative assets; photographs need context-specific alternatives rather
than a filename or a generic “image” label.

## 5. Validation record

**Recorded 2026-11-07:** all 14 focused cases in `forum-theme.spec.ts` and
`forum-controls.spec.ts` passed across the desktop and mobile Chromium projects
(seven cases per project). Another 17 Inbox checks and 12 composer checks passed;
three desktop-only Inbox cases were skipped on mobile. The latest 52 axe scans cover 13 routes × two color
modes × two viewports and report zero automated violations. They also report
32 `color-contrast` and eight `aria-valid-attr-value` incomplete rule occurrences
with documented manual-review dispositions; these counts are not themselves
passes. The latter involve existing IDs for hidden controls, whose opened states
and keyboard behavior are covered by the focused tests.

The [evidence record](../evidence/forum-theme/README.md) owns the tested candidate,
browser/runtime provenance, logs, captures, scan results, and remaining review.
Results below apply only to the recorded fixtures and states. “Not yet run” is
not a pass. Follow [PRODUCT_DESIGN §13](../../PRODUCT_DESIGN.md#13-roadmap--phasing)
for completion evidence.

| Check | Required evidence | Status for replacement |
| --- | --- | --- |
| Contrast | Actual text, UI, and focus pairs in light/dark, including operator branding and hover/selected/error states | **Partial:** recorded baseline token pairs pass their thresholds and scans report no violations; 32 contrast incomplete occurrences have bounded manual-review dispositions in the evidence record. All operator palettes and interaction/error states are not fully audited. |
| Typography and content | Desktop/mobile captures of discussion, list, composer, auth, and administration; stress content above | **Partial:** 13 representative routes captured in both modes/viewports; composer text and shared messaging prose checked. Comprehensive feature, error-state, and stress-content coverage remains unverified. |
| Resize and reflow | 200% text resize; 400% browser zoom; 320 CSS pixel layout; only justified local two-dimensional overflow | **Partial:** topic layout at 320 CSS pixels and directory at 200% CSS text enlargement passed. Real 400% browser zoom has **not yet run**; CSS enlargement is not browser zoom evidence. |
| Text spacing | All four overrides simultaneously; no clipped content or lost controls | **Passed for the recorded topic fixture:** combined overrides at 320 CSS pixels, visible content, and page overflow checked. Other states are not exhaustively covered. |
| Keyboard | Browse, search, filter, compose, submit, edit, cancel, open/close overlays; sensible order and visible focus | **Partial:** skip link reaches main; mobile drawer, account disclosure, and emoji dialog keyboard paths and focus return passed. Full feature/action traversal remains unverified. |
| Touch targets | Computed clickable rectangles and spacing for compact controls; distinguish 44px project goal from AA minimum | **Partial:** topic submit control meets the 44px height check; Inbox pagination meets 44px width and height, and the compact reply fits normal/Large text. A complete target width/height/spacing audit has **not yet run**. |
| Dialogs and menus | Initial/contained/returned focus, accessible names, Escape and close action, background behavior | **Passed for the recorded controls:** drawer Tab containment, main-content inertness, Escape/close return; native account-menu Escape return; emoji initial/contained focus, dismissal return, and insertion return to the editor. Other overlays and assistive-technology behavior remain unverified. |
| Dynamic updates | Loading/append/validation/polling preserves focus and reading position; targeted announcements | **Not yet validated comprehensively:** the emoji insertion path preserves the local draft and returns focus; long-thread updates, polling, and actual spoken announcements remain unverified. |
| Forms and authentication | Label/error associations, preserved draft, password-manager/autofill/paste, complete-code paste | **Partial:** native sign-in, appearance persistence, and direct-message submission passed. Native-textarea ARIA correction and all 12 focused native/rich suggestion regressions passed, including insertion, ownership, Escape and focus. Validation/error states and password-manager/code-paste workflows remain unverified. |
| Preferences | Light/dark/system, reduced motion, and forced colors, including focus and selected states | **Passed for recorded states:** light/dark routes, system dark, emulated reduced motion, and forced-colors focus/native checkbox behavior. Physical platform palettes and every selected/error state remain unverified. |
| No JavaScript | Core reading, navigation, search, sign-in, and posting flows; graceful disclosure fallback | **Passed for recorded native flows:** sign-in, appearance change/persistence, and direct-message sending in both projects. Complete search/posting/disclosure coverage remains unverified. |
| Automated accessibility | axe or equivalent on representative routes and opened/error states; include WCAG 2.2 rules where supported | **52 scans completed; zero violations.** The 32 contrast and eight hidden-control-ID incomplete occurrences have documented review dispositions; these are not comprehensive passes. Opened/error states and all feature combinations are not fully scanned. |
| Assistive technology | NVDA with Firefox or Chrome on Windows; VoiceOver with Safari on Apple platforms; complete reading/posting tasks | **Not yet run on physical assistive-technology setups.** Browser automation and emulation do not establish these results. |

Automated checks find a subset of failures. They do not establish usable reading
order, appropriate alternative text, understandable announcements, or complete
WCAG conformance. Screen-reader behavior remains **unverified** until actual
assistive-technology sessions are recorded; DOM inspection and browser emulation
do not substitute for them. Include disabled users in usability evaluation when
available. Record unavailable platforms and remaining risks explicitly.

### Refinement record (ADR 0057)

**Recorded 2026-11-07 and re-measured 2026-11-08, local fixtures, Chromium
153.** An audit of the merged
theme across 22 routes, both themes and both viewports, plus keyboard
traversal, 320px reflow, text-spacing, forced colours and a 320 × 256
viewport, found the contract gaps listed in ADR 0057. After the changes:

- no visible text below 14px at the default text size (including
  `aria-hidden` and generated text; initials and glyphs exempt), no capitals,
  positive tracking or lower-case labels, and no code face outside code and
  identifiers on the 20 routes swept by `forum-refinement.spec.ts` in both
  themes at desktop and phone widths;
- zero axe violations with WCAG 2.0–2.2 A/AA and best-practice rules on those
  routes and states, including landmark, heading-order and region rules; the
  incomplete results remain in the saved JSON;
- link-style controls, including the phone order sheet and the search scope
  and order options, centre their labels within 1.5px;
- at 320 × 256 the reply dock is static, scroll padding is 12px, and focused
  post links are not covered (2.4.11) for a guest with and without scripting
  and for a member with the reply composer, where previously the dock covered
  57% of the viewport;
- no undersized target on the 22 audited routes, at either viewport, fails
  2.5.8's spacing exception (`tests/browser/forum-type-audit/targets.mjs`).

The 320 × 256 viewport approximates 1280 × 1024 at 400% zoom; real browser
zoom, physical devices, Firefox and Safari were not exercised. Screen-reader
testing remains **not yet run**.

## 6. Primary references

The links below were reviewed on 2026-11-07. WCAG requirements come from W3C;
USWDS informs reading choices, and MDN documents browser preference behavior.

[wcag]: https://www.w3.org/TR/WCAG22/
[type]: https://designsystem.digital.gov/components/typography/
[contrast]: https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html
[nontext]: https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html
[resize]: https://www.w3.org/WAI/WCAG22/Understanding/resize-text.html
[reflow]: https://www.w3.org/WAI/WCAG22/Understanding/reflow.html
[spacing]: https://www.w3.org/WAI/WCAG22/Understanding/text-spacing.html
[targets]: https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html
[auth]: https://www.w3.org/WAI/WCAG22/Understanding/accessible-authentication-minimum.html
[status]: https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html
[feed]: https://www.w3.org/WAI/ARIA/apg/patterns/feed/
[dialog]: https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
[motion]: https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/prefers-reduced-motion
[forced]: https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@media/forced-colors

Reading research and contrast status (reviewed 2026-11-07):

- Rello, Pielot & Marcos, [Make It Big! (CHI 2016)](https://doi.org/10.1145/2858036.2858204)
- Wallace et al., [Towards Individuated Reading Experiences (ACM TOCHI 2022)](https://doi.org/10.1145/3502222)
- Dyson & Haselgrove, [line length and reading speed (2001)](https://doi.org/10.1006/ijhc.2001.0458); Dyson, [how physical text layout affects reading from screen (2004)](https://doi.org/10.1080/01449290410001715714)
- Legge & Bigelow, [Does print size matter for reading? (2011)](https://doi.org/10.1167/11.5.8)
- Arditi & Cho, [Serifs and font legibility (2005)](https://doi.org/10.1016/j.visres.2005.06.013)
- Rello & Baeza-Yates, [font type and readability for people with dyslexia (2016)](https://doi.org/10.1145/2897736)
- Kuster et al., [Dyslexie font (2018)](https://doi.org/10.1007/s11881-017-0154-6); Wery & Diliberto, [OpenDyslexic (2017)](https://doi.org/10.1007/s11881-016-0127-1); Broadbent, [OpenDyslexic and Arial with Key Stage 2 readers (UCL EdD thesis, 2023)](https://discovery-pp.ucl.ac.uk/id/eprint/10173561)
- Buchner, Mayr & Brandt, [display polarity and luminance (2009)](https://doi.org/10.1080/00140130802641635); Piepenbrock et al., [positive polarity for younger and older adults (2013)](https://doi.org/10.1080/00140139.2013.790485); Sethi & Ziat, [dark mode and workload (2023)](https://doi.org/10.1080/00140139.2022.2160879)
- [MDN: `font-family` and `system-ui`](https://developer.mozilla.org/en-US/docs/Web/CSS/font-family); [CSS Fonts Module Level 4](https://www.w3.org/TR/css-fonts-4/)
- [WCAG 3.0 Working Draft](https://www.w3.org/TR/wcag-3.0/); Roselli, [WCAG3 contrast as of April 2026](https://adrianroselli.com/2026/04/wcag3-contrast-as-of-april-2026.html); [Understanding SC 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html); [technique G183](https://www.w3.org/WAI/WCAG22/Techniques/general/G183)

## 7. Standards context for operators

This is orientation, not legal advice. Whether a particular community is in
scope of a law depends on who runs it and for whom.

| Instrument | Status on 2026-11-07 |
| --- | --- |
| WCAG 2.2 | W3C Recommendation; also [ISO/IEC 40500:2025](https://www.w3.org/WAI/news/2025-10-21/wcag22-iso). |
| EN 301 549 | V4.1.1 aligns its web clauses with WCAG 2.2 AA. |
| European Accessibility Act | Applies since 28 June 2025 to listed consumer services; general forum software is not a listed category, third-party content outside the operator's control is excluded, and microenterprise service providers are exempt. |
| US ADA Title II | The DOJ's web rule keeps WCAG 2.1 AA; an [April 2026 interim final rule](https://www.federalregister.gov/documents/2026/04/20/2026-07663/extension-of-compliance-dates-for-nondiscrimination-on-the-basis-of-disability-accessibility-of-web) moved compliance to 26 April 2027 (populations of 50,000 or more) and 26 April 2028 (smaller entities). |
| Section 508 | Still references WCAG 2.0 A and AA. |

Member posts are user-contributed content. Several instruments treat content
outside the operator's control differently; the forum's own templates,
controls and defaults are what this baseline governs.
