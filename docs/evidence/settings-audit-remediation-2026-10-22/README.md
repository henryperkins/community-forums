# Settings audit remediation — local evidence

All six supplied findings are addressed in the current working tree. These
changes preserve the existing settings work and Imladris tokens. They are not
committed, published, or deployed. Broader verification is still **not green**.

## Findings and confirmation

| Finding | Change | Current evidence |
| --- | --- | --- |
| Subscription controls collapse | The narrow container rule now matches the base selector's specificity. Editing controls stretch across the single column; opt-out follows the form. | At 320px with a 32px root, the row, form, select, and Save button are all 184px wide. Save is 128px high with three text lines, rather than the reported 36 × 596px collapse. Native frequency changes persist. |
| Empty Security fields have weak boundaries | Security and the shared set-password form use the existing `input-engraved` treatment. | Unfocused borders measure 3.92:1 against the light panel and 4.71:1 against the dark panel; against their own fills, 3.69:1 and 5.30:1. Passwordless forms and forced colors also pass. |
| Account-row content becomes unreadable | Narrow panes keep the provider mark next to its name, then put state and actions on separate rows. Nested session chips also stack. | Connections and populated Sessions pass at 390px and at 320px with doubled root text, in both themes. Text retains at least 70% of row width; state/actions follow the main text. |
| Security heading escapes its panel | Settings panel captions permit long words to wrap, preserving their heading elements and typography. | Heading fragments remain inside their panels and document overflow is at most 1px at 320px/doubled text. |
| Replay tour is a dead native control | Both controls render hidden, with an author-style hidden guard, and are revealed after the tour handler is installed. | Hidden with JavaScript disabled, the tour script blocked, or `fetch` unavailable. With enhancement, replay opens the tour; Escape closes it and restores focus. |
| Connections promises an unset password | Introductory copy follows the existing `has_password` state. | Native passwordless → set-password → password-available transition passes; rejected password creation retains the appropriate explanation. |

The contract is unchanged: DECISIONS §2 progressive enhancement, USER §§2.4,
3.3–3.4, DESIGN's pane-responsive settings rules, ADR 0039's field tokens, and
PRODUCT_DESIGN §13's browser-evidence requirement.

## Verification

- **9 focused browser tests passed**, covering all six findings, native form
  persistence, both themes, doubled root text, forced colors, and enhancement
  availability. See [browser-final.log](browser-final.log) and [desktop](desktop/).
- Existing account-console regression: **17 passed, 1 failed, 12 skipped** in
  the complete run. The failure counted the intentionally hidden Replay button
  as a zero-height touch target. The test now measures rendered controls and
  explicitly checks that Replay is hidden; its focused rerun **passed**. Thus
  all 18 applicable console cases have passing evidence, composed across those
  two runs. The 12 skips are explicit project/viewport exclusions. The suite
  includes all 14 native settings destinations, validation, session revocation,
  responsive navigation, and serious/critical axe checks in light, dark, and
  system-dark themes. See [initial console run](browser-console.log) and
  [corrected case](browser-console-confirmation.log).
- Existing native TOTP enrollment, second-factor login, and disabling:
  **2 passed** (desktop and mobile), [log](browser-totp.log).
- Full `composer test`: **3,353 tests, 29,427 assertions, 25 failures,
  2 warnings, 1 skipped**, in 4m34s with a 1 GiB PHP limit and Composer's
  process timeout disabled. See [full log](phpunit-full.log) and
  [JUnit](phpunit-full.xml).
- **12 of those failures were stale test parsers introduced by the extra field
  class.** Four authentication test classes matched the exact old
  `class="input"` string when extracting the authenticator secret. They now
  locate the labeled readonly input through DOM XPath, require exactly one
  match, and retain strict Base32 validation. Those classes plus the new
  enhancement/copy tests pass: **39 tests, 492 assertions**,
  [focused log](phpunit-focused.log). The full suite was not rerun after these
  test-only repairs; the remaining 13 failures below are not a fresh full-suite
  result.
- Asset build and final freshness check **passed**:
  [build](build-confirmation.log), [check](assets-final-check.log).
  `npm run test:assets` remains **34 passed, 1 failed**: the real Workers Assets
  rehearsal fails Miniflare option validation (`modules`, `script`, and
  `scriptPath` absent), [log](assets-test.log). No Worker/runtime fix is claimed.
- PHP lint, JavaScript syntax checks, and scoped whitespace checks passed.
  Independent source and screenshot review found no additional defect within
  these six fixes.

### Remaining full-run failures

The full run's unresolved failures are in these unchanged contract families:

| Class | Failures | Diagnostic |
| --- | ---: | --- |
| `CloudRunBrowserCiContractTest` | 6 | Windows/Bash paths, missing `/workspace` context, archive paths, and runner assertions |
| `CloudRunBrowserCiPackageContractTest` | 1 | Packaged missing-artifact diagnostics differ |
| `CloudRunBrowserCiUploaderContractTest` | 1 | Python subprocess contracts rely on unavailable Windows `os.O_NOFOLLOW` |
| `CloudRunDeploymentContractTest` | 4 | CRLF-tainted values and failed subprocess creation |
| `ImladrisRuntimeAssetTest` | 1 | Application presentation digest differs from the reconciled baseline |

The two warnings are denied symlink creation and failed `proc_open()`.
The Imladris baseline was not refreshed merely to clear its gate; ADRs 0024/0039
reserve that reconciliation for the publication/design-sync workflow. Existing
historical failures are not erased or described as a green release.

## Retained attempts and isolation

- [Initial focused run](browser-initial.log): 5 passed, 2 harness failures.
  Chromium's computed `line-height: normal` produced NaN, and the script-block
  matcher missed `/assets/dist/`. The corrected tests count actual text lines
  and match both source and fingerprinted script URLs.
- [Second focused run](browser-confirmation.log): 8 passed, 1 width failure. A
  temporary asset publication `EPERM` rolled back to the previous built CSS,
  leaving the preceding form-sizing rule served. The failure is retained in
  [build-initial-failure.log](build-initial-failure.log). A successful rebuild
  and freshness check preceded the final 9-test passing run.
- [First PHPUnit attempt](phpunit-timeout.log) hit Composer's 300-second
  wrapper timeout. The complete run used a fresh private test schema and no
  wrapper timeout.
- Browser database: `retroboards_unified_e2e_auditfix_20261022`; PHPUnit database:
  `retroboards_test_auditfix_20261022`. Browser origin: `http://localhost:8052`.
  Only disposable local accounts were used. The task's PHP server was stopped
  after verification. Disposable schemas and temp stores remain available.
- Existing files were backed up before editing under
  `C:/Users/htper/.codex/backups/settings-audit-remediation-20261022/`. Original
  hashed build outputs are also retained there. No unrelated cleanup, staging,
  commit, push, baseline reconciliation, or deployment was performed.
- [verification.json](verification.json) separates historical and composed
  results. [run-manifest.json](run-manifest.json) records the base commit,
  versions, and final file hashes; this is a dirty working-tree result.

Screenshots use Chromium emulation. Doubled root text is not a physical-device
or actual browser-zoom test. No new performance/CWV measurement or no-JavaScript
axe certification is claimed; the original audit's limits remain applicable.
