# Thread Content Presentation Remediation Design

**Date:** 2026-08-03

**Status:** Approved in conversation

**Scope:** Correct the remaining readability and presentation defects in thread bodies and other user-authored Markdown surfaces without changing forum behavior, content, or data contracts.

## Context

The shared `.formatted-content` contract now gives posts, direct messages, composer previews, living briefs, and profile bios one typographic baseline. Review of that work found four cascade and containment defects plus one evidence gap:

- the mobile `.post` shorthand restores top padding on consecutive same-author replies;
- the final thread-layer `.post` rule overrides the earlier deleted-post frame, while the separator selector also excludes deleted posts;
- the composer preview's border and padding consume part of its declared `66ch` measure, and its sunken outer fill hides the nested code fill;
- a blockquote's final paragraph contributes an extra inner bottom margin; and
- profile evidence uses a plain-text bio, so the shared contract is not exercised there with structural Markdown.

The prior contrast report also described only the dark-theme measurements. Light-theme code containers need a visibly stronger boundary rather than a misleading cross-theme number.

## Approved direction

Keep `.formatted-content` as the single prose contract and repair each contextual state at the final cascade layer. Do not introduce a second post-body typography system or change the established `66ch` published-content measure.

### Thread state hierarchy

- A normal later post retains the existing hairline separator.
- A consecutive same-author post suppresses its own top padding on every viewport, including after the mobile `.post` shorthand.
- A deleted post regains an explicit muted frame and surface at the final thread layer, so its boundary does not depend on the normal-post separator.
- Deleted content remains semantically present and restorable under the existing disclosure and moderation controls.

### Prose rhythm and code

- The final direct child inside a formatted blockquote has no bottom margin; the blockquote's own outer margin remains the sole following-space rule.
- Inline and fenced code retain the shared monospaced scale and sunken fill.
- Their border uses the stronger existing border token so the container edge remains legible in light and dark themes and inside contextual surfaces such as previews and message bubbles.

### Composer preview

The preview remains one progressively enhanced `.composer-preview.formatted-content` element. Its border-box maximum includes the two `12px` inline paddings and two `1px` borders in addition to the prose's `66ch` content measure. The preview background uses the page surface, allowing nested sunken code blocks to remain distinct. On narrow screens the preview continues to shrink to its containing form without horizontal overflow.

### Profile bio coverage

The production profile markup already opts into `.formatted-content`. The deterministic browser fixture will use a short, representative Markdown bio containing a heading, list, blockquote, inline code, and fenced code. Server and browser assertions will prove that the profile route renders those structures and inherits the same computed typography, rhythm, and code boundary as a thread post.

## Implementation boundaries

Expected production changes are limited to `public/assets/app.css`. Test fixture, PHPUnit, Playwright, evidence, runtime-baseline, and planning files may change to prove the result.

Do not change Markdown parsing or sanitization, templates, controllers, services, repositories, routes, authorization, feature flags, migrations, schema, JavaScript behavior, or persisted development data. Do not stop the existing development server or remove user-created database scaffolding.

## Verification and completion evidence

Focused browser assertions must measure real computed behavior in both configured desktop and mobile projects:

- grouped reply top padding is `0px`;
- a deleted reply has a visible final-layer frame and distinct surface;
- preview content width matches a published `66ch` post body at desktop and remains contained on mobile;
- code boundaries are materially stronger than the surrounding surface in both themes;
- a blockquote's last child has zero bottom margin; and
- the profile bio renders and styles representative Markdown structures.

Refresh relevant screenshots only after these assertions pass and visually inspect them. Completion also requires the focused PHPUnit tests, full PHPUnit suite, relevant Playwright suites, Imladris runtime verification, WYSIWYG asset verification, and `git diff --check` on the final working tree.

## Non-goals

This remediation does not redesign the thread, composer, profile, direct messages, shell, mobile avatar rail, or Markdown language. It does not alter post grouping/deletion semantics, the accepted-answer treatment, the `66ch` published measure, database contents outside the isolated browser fixture database, or deployment state.
