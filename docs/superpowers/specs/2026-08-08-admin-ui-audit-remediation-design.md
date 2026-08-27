# Admin UI Audit Remediation Design

**Date:** 2026-08-08

**Status:** Approved by the implementation brief in this task

**Scope:** Correct the responsive console chrome and the Members directory issues identified by the 2026-08-08 Admin UI audit.

## Outcome

The Admin Console remains a server-rendered, no-JavaScript-capable operations surface at every viewport. Its identity row becomes compact before the horizontal area tier becomes cramped; the Members directory keeps its common filters immediately reachable on a phone, places infrequent constraints behind a native disclosure, and makes its wide results table's horizontal scroll affordance explicit.

This is a focused remediation, not a redesign. It preserves every existing route, query parameter, filter, bulk-action control, authorization rule, CSRF contract, and desktop information architecture.

## Sources and boundaries

Repository authority remains `DECISIONS.md` → `PRODUCT_DESIGN.md` → `SCHEMA.md` → `ADMIN.md`. `ADMIN.md` §9.2 requires the two-row console bar and real-route navigation; §9.4 requires progressive disclosure, a visible horizontal-tier scrollbar, 44px touch targets below 860px, and no JavaScript dependency. `PRODUCT_DESIGN.md` §13 requires browser proof as well as server-side tests for visible work.

ADR 0024 remains binding:

- `.admin-bar` and `.admin-tier` rules are owned by `docs/design-system/imladris/components.css` and compiled through `composer build:imladris`.
- `resources/imladris/` and `public/assets/imladris.css` are generated outputs, never hand-edited.
- `config/imladris-runtime-baseline.json` is not changed on this slice branch.
- The existing app-wide 860px content layout and touch-target rules are retained exactly.

The source-owned console adjustment and the app-owned Members-directory changes deliberately remain separate. No product policy, data model, endpoint, feature flag, or generated design baseline changes.

## Responsive console chrome

At viewport widths of 900px and below, the console identity row enters a compact state while the wider page content remains in its desktop layout until the existing 860px breakpoint:

- The header continues to show the community mark/name, exit route, Admin mode, notifications, monogram, and sign-out control.
- The header search input, signed-in username label, and sign-out text label are visually hidden. Their controls retain accessible names through the existing markup.
- The identity row uses compact padding and a stable minimum height rather than wrapping the sign-out control beneath a truncated username.
- The horizontal area tier retains its overflow behavior. Its thin scrollbar receives a transparent track and a visible token-colored thumb so the off-edge content is signaled without creating a pale visual bar.

At 860px and below, the pre-existing one-column console layout, wrapped section tabs, and 44px tier targets continue to apply. The compact-header rules must not change that breakpoint's ownership or alter non-console application navigation.

## Members directory filters

The Members directory's GET form is reorganized into two groups without changing any field names, submitted values, sort/direction hidden inputs, result count, or bulk controls:

1. The always-visible group contains the username/email search, Role, and State controls.
2. A native `<details>` disclosure labeled `More filters` contains Last seen, joined-date range, and post-count range controls.

The disclosure works without JavaScript. When any of its fields is active, the server renders it open after the GET round trip so active constraints are never hidden from the operator. When no advanced criterion is selected, it begins closed to keep mobile results and actions close to the first viewport. The same structure is used on desktop; it is intentionally a progressive-disclosure improvement rather than a viewport-dependent duplicate form.

## Members table scroll affordance

The directory table remains semantic and stays in its labelled, keyboard-focusable horizontal scroll region. Its surrounding card gains a concise mobile cue, `Scroll for state, activity, and dates`, with an arrow and right-edge fade. The cue is present in server-rendered HTML so it is useful without JavaScript.

Existing progressive enhancement may update the cue/fade based on the region's actual horizontal overflow and scroll position, but it must never be required to access table data. At desktop widths the cue and fade do not display.

## Accessibility and compatibility

- Every console area and section remains an ordinary server-rendered route/link with JavaScript disabled.
- The native disclosure keeps its summary keyboard-operable and its controls in the same GET form.
- The table retains its `role="region"`, accessible label, keyboard focusability, and table semantics.
- No inline script, inline style, event handler attribute, or CSP exception is introduced.
- New visual signals supplement rather than replace text, focus, form labels, and existing accessible names.
- At all examined breakpoints, the document must not develop horizontal page overflow.

## Verification and completion evidence

Tests are added before production changes and prove the observable contract:

- a 900px console header fits without wrapping the sign-out control and preserves the tier's scroll signal;
- the 860px console layout remains the established compact one-column experience;
- the Members page renders common filters outside the native advanced disclosure;
- an active advanced query re-renders its disclosure open with its submitted value preserved;
- the wide Members table retains its labelled scroll region and exposes its mobile scroll cue;
- JavaScript-disabled navigation, filtering, disclosure interaction, and table access continue to work.

Final evidence includes focused PHPUnit coverage, `composer verify:imladris`, a CSP scan, desktop and mobile Playwright coverage, a JavaScript-disabled browser pass, screenshots under `docs/evidence/`, the full `php vendor/bin/phpunit` suite, and `git diff --check`.

## Non-goals

This remediation does not:

- change any console route, authorization check, role/state behavior, filter semantics, bulk action, or audit behavior;
- add JavaScript-only filter controls, a drawer, a client router, a migration, or a dependency;
- alter the existing 860px application-shell breakpoint;
- hand-edit generated Imladris assets or refresh the runtime baseline; or
- deploy, push, merge, or mutate production data.
