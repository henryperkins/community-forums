# Forum accessibility and reading baseline

**Reviewed:** 2026-11-07. **Target:** WCAG 2.2 Level AA, including applicable Level A criteria.
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
| Type family | System sans serif for interface and prose; system monospace for code | Familiar rendering without required font downloads. A serif font is not inherently inaccessible. |
| Base sizing | Root `100%`; interface `1rem`; prose `1.125rem` | Approximately 16px and 18px at the browser's usual default; retain the reader's font preference. |
| Reading rhythm | Prose line height `1.65`, unitless; readable column up to `65ch` | USWDS recommends at least 1.5 for longer text and identifies about 66 characters as a useful extended-reading target. `ch` is an approximation, so inspect real content. |
| Metadata | At least `0.875rem`; normal, legible weights | Smaller secondary text still needs ordinary-text contrast. Avoid all-caps or widely tracked text for continuous reading. |
| Alignment | Start-aligned text and clear paragraph/heading separation | Avoid full justification. Keep headings meaningful and visually distinguishable. |
| Links | Blue link tokens with persistent underlines within prose | Color alone does not identify an action. Navigation may use other clear visual affordances. |
| Color modes | Light, dark, and system preference | Validate each resolved mode and meaningful interaction state independently. Dark mode does not establish accessibility by itself. |
| Controls | Aim for an actual 44 × 44 CSS pixel hit area for key standalone controls | The icon can be smaller. This exceeds the AA minimum; inline prose links are handled separately. |
| Surfaces and images | Solid reading surfaces; no decorative photography behind text | Keep content imagery responsive. Provide meaningful alternatives for informative images and empty `alt` for purely decorative images. |
| Focus | Clearly visible outline or border, with adequate offset and contrast | A shadow alone disappears in forced-colors mode. Aim to keep the entire focused control visible. |

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

The 44 × 44 target in this project is a design goal; WCAG 2.5.5 specifies that
size at **AAA**, with exceptions. Likewise, the quantitative focus-area rule in
2.4.13 is **AAA**, not AA. AA 2.4.11 permits partial focus obstruction; this
project should aim for full visibility. Use the [normative standard][wcag] when
assessing exceptions rather than treating this summary as a replacement.

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
