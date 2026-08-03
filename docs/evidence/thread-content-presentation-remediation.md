# Thread Content Presentation Remediation Evidence

**Verified:** 2026-08-03
**Base revision:** `3d317c7`
**Design:** `docs/superpowers/specs/2026-08-03-thread-content-presentation-remediation-design.md`

## Result

The shared user-authored-content presentation now preserves its intended hierarchy across posts, composer previews, rich Markdown, and profile bios:

- consecutive same-author replies keep `padding-top: 0px` at desktop and mobile widths;
- staff-visible removed replies retain a dashed, non-transparent frame and their own sunken surface after the final flat-stream reset;
- the composer preview's border-box maximum leaves the full published `66ch` content measure inside its padding and border;
- preview code uses a different fill from the preview container and a stronger shared boundary;
- the final paragraph inside a blockquote contributes `0px` bottom margin, leaving the blockquote's outer rhythm as the single following-space rule; and
- the profile fixture now proves headings, lists, blockquotes, inline code, and fenced code through the real public-profile route.

The code fill itself remains deliberately quiet: approximately `1.10:1` against the light page and `1.34:1` against the dark page. The visible boundary now comes from `--border-strong`; its resolved border-to-code-fill contrast is approximately `2.15:1` in light and `1.96:1` in dark. These are component-boundary measurements, not text-contrast claims.

## Regression proof

The new browser assertions were run before the stylesheet change and failed on the exact reviewed defects:

| Contract | Before |
| --- | ---: |
| preview content maximum vs `66ch` probe | `25.99px` too narrow |
| profile inline-code border vs fill, light | `1.18:1` |
| blockquote final paragraph bottom margin | `17px` |
| removed-post background alpha | `0` |
| grouped reply mobile top padding | `16px` |

After the final-layer CSS repair, the same eight desktop/mobile regression cases passed. The broader clean-database browser run then passed all applicable affected-surface cases:

```text
npx playwright test composer-shell.spec.ts profile-surface.spec.ts rich-content.spec.ts thread-content-presentation.spec.ts thread-view-study.spec.ts
51 passed, 19 project-specific skips, 0 failed (3.7m)
```

That run includes the suites' existing no-JavaScript, Axe serious/critical, keyboard, interaction, console/network-error, overflow, reduced-motion, and light/dark checks.

## Visual evidence

The following final captures were inspected at `1280×800` desktop and `390×844` mobile:

- `docs/evidence/browser/{desktop,mobile}/85-thread-content-states-light.png`
- `docs/evidence/browser/{desktop,mobile}/86-thread-content-states-dark.png`
- `docs/evidence/browser/{desktop,mobile}/87-composer-preview-light.png`
- `docs/evidence/browser/{desktop,mobile}/88-composer-preview-dark.png`
- `docs/evidence/browser/{desktop,mobile}/83-rich-content.png`
- `docs/evidence/browser/{desktop,mobile}/84-rich-content-table.png`
- `docs/evidence/imladris-profile-production/{desktop,mobile}/guest-populated-{light,dark}.png`

The grouped continuation remains visually attached to its preceding post, the removed state is bounded without becoming a heavy card, code edges stay visible in both registers, and no inspected viewport introduces horizontal overflow or clipping.

## Final gates

```text
php vendor/bin/phpunit
OK, but some tests were skipped!
Tests: 2432, Assertions: 17325, Skipped: 2. (14:42.526)

composer verify:imladris
Imladris runtime assets are current.
OK (11 tests, 66 assertions)

npm run check:wysiwyg
vite build completed; generated WYSIWYG assets have no diff

git diff --check
passed (line-ending conversion warnings only)
```

`composer test` was also attempted, but Composer terminated its `phpunit` child at the repository's fixed 300-second process timeout while the suite was at 60%. The direct PHPUnit invocation above ran the same configured suite to completion and is the reported full-suite result.

Only the isolated `retroboards_e2e` database was reset and seeded for browser evidence. The existing development database and the already-running development server were left untouched.
