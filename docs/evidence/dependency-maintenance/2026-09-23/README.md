# Dependency maintenance evidence — 2026-09-23

CommonMark advances from 2.8.3 to 2.10.3, PostCSS from 8.5.16 to 8.5.28, and PostCSS's nested Nano ID from 3.3.15 to 3.3.19. Both original dependency PR tips are retained in the merge ancestry.

The original CommonMark 2.9.0 proposal still had four reported advisories. A targeted update selected 2.10.3 within the existing Composer constraint; the final Composer audit reports no advisories. PostCSS was refreshed beyond the original 8.5.25 proposal. No dependency constraints or unrelated packages changed.

Upstream context: [CommonMark 2.9.1 parser advisory](https://github.com/thephpleague/commonmark/security/advisories/GHSA-j8pm-gj4c-rq4x), [CommonMark 2.10.0 security release](https://github.com/thephpleague/commonmark/releases/tag/2.10.0), and [PostCSS changelog](https://github.com/postcss/postcss/blob/main/CHANGELOG.md).

## Verification

- Baseline and final `composer test`: exit 0; 2,979 tests and 22,482 assertions. Both have six pre-existing PHP 8.5 deprecations and one skipped test. Final output uses `--display-deprecations` to identify them.
- `composer validate --no-check-publish`: valid.
- `composer audit --locked --format=json`: zero advisories and no abandoned packages.
- `npm ci`, `npm run build`, and `npm run check:assets`: passed. Every generated delivery asset and `config/assets.json` remains byte-identical to the base commit.
- `npm run test:assets`: 15 passed.
- `npx playwright test rich-content.spec.ts --project=desktop`: 3 passed.
- `npx playwright test rich-content.spec.ts --project=mobile`: 2 passed, 1 intentional skip. The no-JavaScript case already runs at 390px in the desktop project.

The browser flow is login → rich-content topic → verify rendered Markdown, table and code-block keyboard scrolling, overflow containment, and accessibility. The desktop run includes a 390px no-JavaScript context. Screenshots for both viewports are included. The Browser plugin was not available; the repository's Playwright harness provided the checks.

Verification used an isolated worktree, separate local PHPUnit/browser databases, deterministic non-production credentials, and `tests/browser/prepare.sh` before each browser project. The existing Messages worktrees and root untracked work were preserved separately from this change.

See `validation.json` for versions and lockfile hashes, the accompanying logs for exact results, and the viewport folders for screenshots.
