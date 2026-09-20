# A3 review: avatar draft preservation

Verdict: **APPROVED** for the A3 task gate. No actionable regressions or spec-compliance defects found in `a4231a85..3a25b7af`. The final broad browser/release gate remains parent-owned.

## Scope and evidence

Read A3-brief.md and A3-report.md before the supplied review diff. Reviewed the complete supplied package; recovered the initially truncated controller/template segment separately. Unrelated parent documentation changes were excluded. No runtime, index, branch, or application source changes were made.

The supplied evidence records the initial expected failures, the native-submit regression failure, and the final PHP result of 130 tests / 1321 assertions passing. The parent reports six desktop/mobile avatar and accessibility browser cases passing. These are supplied results, not independently rerun results. No additional test run was necessary to resolve a concrete doubt.

## Spec compliance

- `src/Controller/AccountController.php:42`: the shared account renderer starts from persisted user and custom-field data and overlays only the six ordinary profile fields and enabled custom label/value pairs. Submitted avatar paths, email, roles, and user IDs cannot replace server-owned account data. Scalar drafts are preserved without trimming or truncation; malformed non-scalar fields are safely normalized for rendering.
- `src/Controller/AccountController.php:96`: normal profile save still delegates to AccountService, retains its successful redirect, and re-renders failed validation through the same trusted renderer.
- `src/Controller/AccountController.php:188`: upload failure returns 422 with the submitted draft and a normalized avatar error explaining file reselection. Success returns 200 with the persisted avatar and an explicit message that other edits remain unsaved. Neither upload nor removal calls updateProfile.
- `src/Controller/AccountController.php:207`: removal preserves the same draft, refreshes the trusted stored avatar state, and returns the requested 200 contract.
- `templates/account/settings.php:24`: one multipart form carries ordinary fields and avatar controls. Its CSRF token covers each POST destination. The optional file input, explicit avatar formactions, and formnovalidate allow upload/removal while unrelated draft fields are incomplete. The first hidden submit targets profile save to preserve implicit Enter behavior.
- `templates/account/settings.php:31`: status copy and user-controlled field values remain escaped. Avatar rejection uses existing linked field-error semantics at lines 42–44. No inline scripts/styles, new JavaScript dependency, or nested forms were introduced; native form submission supports no-JavaScript operation.
- `tests/Integration/Core/AppProfileMediaTest.php:12`: regression coverage includes upload/removal/error draft preservation, all custom pairs, no incidental profile writes, malicious identity/avatar overrides, final save, missing/oversized/invalid files, disabled features, restricted accounts, CSRF, and temporary upload/generated-file cleanup. Existing redirect assertions now verify the intended render contract and messages.

## Code quality and concrete risk checks

The renderer extraction removes duplicated profile-page assembly and makes the trust boundary explicit. Controller responsibilities remain limited to request handling, service invocation, and response construction. Error normalization at the controller boundary avoids an unnecessary service API change.

Inspected outside the diff only to resolve named risks: whether the unchanged service still enforces WriteGate before mutation (`src/Service/ProfileMediaService.php:28`, `:44`); whether normal profile validation returns the original draft rather than normalized/truncated values (`src/Service/AccountService.php:86`); whether request draft collection is an array (`src/Core/Request.php:134`); and whether the existing profile-media availability gate remains active (`src/Controller/AccountController.php:355`). All checks support the implementation. Existing attachment finalization/removal behavior is unchanged.

## Findings

None requiring changes. The PHP-level inability to recover a request body discarded before dispatch by post_max_size remains the implementation report's stated boundary; it does not invalidate application-level validation recovery. Release approval still depends on the parent's remaining broad verification gates.
