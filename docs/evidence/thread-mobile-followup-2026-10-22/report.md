Ceiling: proxy (desktop_emulation); limits: field_data not_checked, os_simulator unavailable, physical_device unavailable, no available simulator or physical device; assessment complete; verification failed.

# Mobile UX audit — 20261022-mobile-fix-followup-61589551

## 1. Verdict

Mode: audit_and_fix. Principal task: Open a topic, read the conversation, reply, and return to the topic list on mobile..
Open P0/P1: F-09.
Evidence declarations are not independently authenticated by the helper.

## 2. Coverage

Standing substitutions (scoped to environment/build):
```json
[
  {
    "status": "known",
    "items": [
      {
        "boundary": "account_data",
        "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
        "behavior": "replaced",
        "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
        "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
        "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
      }
    ],
    "unknown_boundaries": [],
    "evidence": [
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
    ],
    "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
    "id": "S-LOCAL",
    "environments": [
      "desktop_emulation",
      "automated_test"
    ],
    "build_ids": [
      "B-LOCAL"
    ]
  },
  {
    "status": "known",
    "items": [
      {
        "boundary": "account_data",
        "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
        "behavior": "replaced",
        "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
        "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
        "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
      }
    ],
    "unknown_boundaries": [],
    "evidence": [
      "seed.txt",
      "thread-fixture.txt"
    ],
    "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
    "id": "S-FIXTURE",
    "environments": [
      "desktop_emulation",
      "automated_test"
    ],
    "build_ids": [
      "B-FIX",
      "B-DIAG"
    ]
  }
]
```

| Area | Disposition | Environments used | Highest level | What remains |
| --- | --- | --- | --- | --- |
| 1. Reach and placement | no_defect_found | desktop_emulation | proxy | One-handed physical reach |
| 2. Viewport and safe areas | no_defect_found | desktop_emulation | proxy | Real browser bars, notches/safe areas, native zoom and keyboard |
| 3. Touch targets | no_defect_found | desktop_emulation | proxy | Physical target acquisition and dense inline links |
| 4. Forms and keyboard | no_defect_found | desktop_emulation | proxy | iOS/Android software keyboards and native autofill |
| 5. Gestures and scrolling | defects_found | desktop_emulation | proxy | Native gestures, momentum and platform keyboard/AT |
| 6. Overlays, navigation and dense data | no_defect_found | desktop_emulation | proxy | Physical browser back/edge gestures and narrow overlay bars |
| 7. Content priority | no_defect_found | desktop_emulation | proxy | Whole-app content architecture excluded |
| 8. Performance | no_defect_found | desktop_emulation | proxy | Production compression/cache, real-device traces, field metrics; lab LCP needs investigation |
| 9. Errors and recovery | defects_found | desktop_emulation | proxy | Physical network loss, mobile browser history and software-keyboard recovery; upload flow excluded |
| 10. Accessibility on mobile | defects_found | desktop_emulation | proxy | VoiceOver/TalkBack, native text scaling; axe incomplete checks need manual review |

## 3. Findings by severity

### F-09: Mixed conflict/history rich recovery exposed an older draft

P1 · observed · **implemented**

Where: public/assets/composer.js: rememberHistorySubmission, enhance, renderConflict. Expected: A newly typed rich reply must not be replaced by an older local revision during failure/Back recovery.. Actual: Native failure retained the latest local/journal body while showing the older textarea/rich body and rotating its key. Controlled stale-snapshot reproduction also failed before the fix..
Cause: Native Back restoration can put an older value into the hidden textarea before lazy rich initialization. Nonempty text skips the local draft restore, and key comparison then treats it as a new edit. Separately, Save local previously closed over stale text.. Disposition reason: 

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "mixed-rich-history",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Microsoft Edge 155.0.4283.62 on Windows",
        "device": "Desktop mobile emulation, 390x844 CSS unless stated",
        "state": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
        "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
        "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
        "steps": [
          "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back"
        ],
        "timing": "State/event waits in retained probe; no real software keyboard",
        "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
      }
    },
    {
      "id": "native-back-reproduced",
      "conditions": {
        "build_id": "B-DIAG",
        "browser": "Microsoft Edge 155.0.4283.62 on Windows",
        "device": "Desktop mobile emulation, 390x844 CSS unless stated",
        "state": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
        "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
        "setup": "Real PHP on port18037 and isolated MariaDB3317; seeded Elladan topic30; rich composer enabled.",
        "steps": [
          "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back"
        ],
        "timing": "Rapid edit/send after Save local; native offline error entry and Back; capture DOMContentLoaded/pageshow and editor appearance.",
        "substitutions": "B-DIAG / S-FIXTURE; network disabled for failed POST; capture listeners only."
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\rich-recovery.json",
      "level": "proxy",
      "scope_ids": [
        "mixed-rich-history"
      ],
      "observation": "Original mixed sequence did not preserve intended body/key."
    },
    {
      "ref": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-post-rich.json",
      "level": "proxy",
      "scope_ids": [
        "mixed-rich-history"
      ],
      "observation": "Prior revision and later intended revision both appear in local fixture history."
    },
    {
      "ref": "recovery-diagnostic/rich-recovery-trace-mobile-1.json",
      "level": "proxy",
      "scope_ids": [
        "native-back-reproduced"
      ],
      "observation": "Latest local/journal text survived, but pageshow restored old textarea before rich initialization; old visible text and changed key reproduced."
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": true,
    "proposal": "Isolate the original conflict-to-edit-to-submit timing and capture canonical/local/history state at each boundary before proposing a code fix.",
    "build_id": "B-FIX",
    "evidence": [
      "implementation.diff",
      "build-identity-final.json"
    ],
    "description": "Read live body/title when saving a conflict; initialize a matching unconfirmed history draft before rich adapter creation, preserving typing during imports.",
    "device_regressions": [
      {
        "id": "DR-IOS-RECOVERY",
        "source_scope_ids": [
          "mixed-rich-history",
          "native-back-reproduced"
        ],
        "environment": "physical_device",
        "behavior": "recovery",
        "expected": "Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.",
        "conditions": {
          "build_id": "B-PHONE-FIX",
          "browser": "Safari",
          "device": "Same provisioned iPhone; disposable draft and reply data",
          "state": "Fresh settled rich draft, then separate mixed conflict/history case; local and server revisions distinct",
          "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
          "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
          "steps": [
            "Run equal-data control and each enabled optional-only field control from the actual provisioned form.",
            "Disable network before submit, return Back, inspect body/key and send state; re-enable and inspect saved status before retry.",
            "Separately recreate two-tab conflict, keep local/save next revision, type a new body and immediately submit/offline/Back; record every state.",
            "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
            "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
            "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
            "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
          ],
          "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
          "substitutions": "Not yet established; record build, setup and additional fault injection separately."
        },
        "depends_on": []
      },
      {
        "id": "DR-ANDROID-RECOVERY",
        "source_scope_ids": [
          "mixed-rich-history",
          "native-back-reproduced"
        ],
        "environment": "physical_device",
        "behavior": "recovery",
        "expected": "Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.",
        "conditions": {
          "build_id": "B-PHONE-FIX",
          "browser": "Chrome",
          "device": "Same provisioned Pixel8; disposable draft and reply data",
          "state": "Settled rich draft; controlled lost acknowledgement after real test-server commit",
          "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
          "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
          "steps": [
            "Confirm permitted response-delivery fault harness and record its boundary; do not fake a successful persistence response.",
            "Submit synthetic body while suppressing only acknowledgement, then inspect saved status through a separate read.",
            "Retry only after status inspection; count saved copies and confirm recovery state clears.",
            "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
            "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
            "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
            "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
          ],
          "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
          "substitutions": "Not yet established; record build, setup and additional fault injection separately."
        },
        "depends_on": []
      }
    ],
    "required_check_ids": [
      "C-PHONE-IOS-RECOVERY-FIX",
      "C-PHONE-ANDROID-RECOVERY-FIX"
    ]
  },
  "contrary_evidence": null,
  "status_history": null
}
```

Device regression requirements (independent of observed scope):
```json
{
  "requirements": [
    {
      "id": "DR-IOS-RECOVERY",
      "source_scope_ids": [
        "mixed-rich-history",
        "native-back-reproduced"
      ],
      "environment": "physical_device",
      "behavior": "recovery",
      "expected": "Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.",
      "conditions": {
        "build_id": "B-PHONE-FIX",
        "browser": "Safari",
        "device": "Same provisioned iPhone; disposable draft and reply data",
        "state": "Fresh settled rich draft, then separate mixed conflict/history case; local and server revisions distinct",
        "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
        "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
        "steps": [
          "Run equal-data control and each enabled optional-only field control from the actual provisioned form.",
          "Disable network before submit, return Back, inspect body/key and send state; re-enable and inspect saved status before retry.",
          "Separately recreate two-tab conflict, keep local/save next revision, type a new body and immediately submit/offline/Back; record every state.",
          "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
          "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
          "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
          "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
        ],
        "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
        "substitutions": "Not yet established; record build, setup and additional fault injection separately."
      },
      "depends_on": []
    },
    {
      "id": "DR-ANDROID-RECOVERY",
      "source_scope_ids": [
        "mixed-rich-history",
        "native-back-reproduced"
      ],
      "environment": "physical_device",
      "behavior": "recovery",
      "expected": "Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.",
      "conditions": {
        "build_id": "B-PHONE-FIX",
        "browser": "Chrome",
        "device": "Same provisioned Pixel8; disposable draft and reply data",
        "state": "Settled rich draft; controlled lost acknowledgement after real test-server commit",
        "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
        "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
        "steps": [
          "Confirm permitted response-delivery fault harness and record its boundary; do not fake a successful persistence response.",
          "Submit synthetic body while suppressing only acknowledgement, then inspect saved status through a separate read.",
          "Retry only after status inspection; count saved copies and confirm recovery state clears.",
          "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
          "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
          "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
          "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
        ],
        "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
        "substitutions": "Not yet established; record build, setup and additional fault injection separately."
      },
      "depends_on": []
    }
  ],
  "qualification": [
    {
      "finding_id": "F-09",
      "regression_id": "DR-IOS-RECOVERY",
      "source_scope_ids": [
        "mixed-rich-history",
        "native-back-reproduced"
      ],
      "check_ids": [
        "C-PHONE-IOS-RECOVERY-FIX"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-09",
      "regression_id": "DR-ANDROID-RECOVERY",
      "source_scope_ids": [
        "mixed-rich-history",
        "native-back-reproduced"
      ],
      "check_ids": [
        "C-PHONE-ANDROID-RECOVERY-FIX"
      ],
      "status": "not_run"
    }
  ],
  "note": "Regression results do not verify or refute the original observed scopes."
}
```

Checks (environment, level, substitutions, expected and actual):
```json
[
  {
    "id": "C-RICH-MIXED",
    "module": 7,
    "scenario": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
    "expected": "Newest intended body and idempotency identity survive recovery",
    "actual": "OfflineBack bodyPreserved=false and sameKey=false; screenshot and DB showed the prior local revision. A later retry saved intended rich text. Exact timing/root cause remain unestablished.",
    "evidence": [
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\rich-recovery.json",
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\evidence\\rich-offline-back.png",
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-post-rich.json"
    ],
    "status": "partial",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "recovery",
    "capability_id": "CAP-EDGE",
    "build_id": "B-LOCAL",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-LOCAL",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
      "steps": [
        "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Application source and existing built assets served without injected application wiring."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\rich-recovery.json"
        ],
        "reason": "No additional response or dependency replacement in this check."
      }
    },
    "covers": [
      {
        "finding_id": "F-09",
        "scope_id": "mixed-rich-history"
      }
    ],
    "reason": "Mixed history/conflict timing was not isolated. This is an unresolved hypothesis, not confirmed permanent data loss."
  },
  {
    "id": "C-DIAG-NATIVE-BACK",
    "module": 7,
    "scenario": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
    "expected": "Newest intended body and idempotency identity survive recovery",
    "actual": "One of three captured native runs showed stale rich/textarea body and rotated key despite intact latest local/journal text.",
    "evidence": [
      "recovery-diagnostic.txt",
      "recovery-diagnostic/rich-recovery-trace-mobile-1.json"
    ],
    "status": "fail",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "recovery",
    "capability_id": "CAP-EDGE",
    "build_id": "B-DIAG",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-DIAG",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Real PHP on port18037 and isolated MariaDB3317; seeded Elladan topic30; rich composer enabled.",
      "steps": [
        "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back"
      ],
      "timing": "Rapid edit/send after Save local; native offline error entry and Back; capture DOMContentLoaded/pageshow and editor appearance.",
      "substitutions": "B-DIAG / S-FIXTURE; network disabled for failed POST; capture listeners only."
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "seed.txt",
          "thread-fixture.txt"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "known",
        "items": [
          {
            "boundary": "network_transport",
            "description": "Playwright offline mode blocks the native POST before delivery.",
            "behavior": "replaced",
            "configured_response": "Failed native POST navigation; then online and Back.",
            "real_components": "Real form, local/session storage, browser history, lazy editor and server-rendered page; actual PHP/SQL for other writes.",
            "untested": "Real radio loss, mobile browser error history and hardware keyboards."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "recovery-controlled-after.txt"
        ],
        "reason": "Explicit network-failure injection."
      }
    },
    "covers": [
      {
        "finding_id": "F-09",
        "scope_id": "native-back-reproduced"
      }
    ]
  },
  {
    "id": "C-FIX-RICH-BACK",
    "module": 7,
    "scenario": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
    "expected": "Newest intended body and idempotency identity survive recovery",
    "actual": "Native offline Back preserves latest Unicode body and key, including three repeated native runs.",
    "evidence": [
      "browser-edge-verified.txt",
      "recovery-controlled-after.txt"
    ],
    "status": "pass",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "recovery",
    "capability_id": "CAP-EDGE",
    "build_id": "B-FIX",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-FIX",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Real PHP at18037 and isolated MariaDB3317; seeded topic30 and Elladan test account.",
      "steps": [
        "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-FIX / S-FIXTURE; see individual test fault boundaries."
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "seed.txt",
          "thread-fixture.txt"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "known",
        "items": [
          {
            "boundary": "network_transport",
            "description": "Playwright offline mode blocks the native POST before delivery.",
            "behavior": "replaced",
            "configured_response": "Failed native POST navigation; then online and Back.",
            "real_components": "Real form, local/session storage, browser history, lazy editor and server-rendered page; actual PHP/SQL for other writes.",
            "untested": "Real radio loss, mobile browser error history and hardware keyboards."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "recovery-controlled-after.txt"
        ],
        "reason": "Explicit network-failure injection."
      }
    },
    "covers": [
      {
        "finding_id": "F-09",
        "scope_id": "mixed-rich-history"
      }
    ]
  },
  {
    "id": "C-FIX-CONTROLLED-RECOVERY",
    "module": 7,
    "scenario": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
    "expected": "Newest intended body and idempotency identity survive recovery",
    "actual": "Stale-snapshot reproduction failed before the fix; both stale-snapshot and new-typing controls passed three repetitions after it.",
    "evidence": [
      "recovery-controlled-before.txt",
      "recovery-controlled-after.txt"
    ],
    "status": "pass",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "recovery",
    "capability_id": "CAP-EDGE",
    "build_id": "B-FIX",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-FIX",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Real PHP at18037 and isolated MariaDB3317; seeded topic30 and Elladan test account.",
      "steps": [
        "Rich editor after cross-tab conflict, Save local as next revision, rapid edit/send and offline Back"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-FIX / S-FIXTURE; see individual test fault boundaries."
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "seed.txt",
          "thread-fixture.txt"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "known",
        "items": [
          {
            "boundary": "network_transport",
            "description": "Playwright offline mode blocks the native POST before delivery.",
            "behavior": "replaced",
            "configured_response": "Failed native POST navigation; then online and Back.",
            "real_components": "Real form, local/session storage, browser history, lazy editor and server-rendered page; actual PHP/SQL for other writes.",
            "untested": "Real radio loss, mobile browser error history and hardware keyboards."
          },
          {
            "boundary": "native_form_restoration",
            "description": "Hold the observed lazy module request and set only the native textarea to the older value captured in the failing trace.",
            "behavior": "replaced",
            "configured_response": "Release the real module after stale restoration, with a separate case typing newer text before release.",
            "real_components": "Real offline submission journal, local draft, history, actual rich module and app; no successful persistence response is fabricated.",
            "untested": "Timing of native mobile form restoration and module loading."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "recovery-controlled-after.txt"
        ],
        "reason": "Explicit network-failure injection."
      }
    },
    "covers": [
      {
        "finding_id": "F-09",
        "scope_id": "mixed-rich-history"
      }
    ]
  },
  {
    "id": "C-PHONE-IOS-RECOVERY-FIX",
    "module": 7,
    "scenario": "Fresh settled rich draft, then separate mixed conflict/history case; local and server revisions distinct",
    "expected": "Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.",
    "actual": "",
    "evidence": [],
    "status": "not_run",
    "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
    "required": true,
    "environment": "physical_device",
    "evidence_type": "physical_device",
    "driver": "person",
    "level": "target",
    "behavior": "recovery",
    "capability_id": "CAP-PHONE",
    "build_id": "B-PHONE-FIX",
    "build_confirmed": false,
    "setup_confirmed": false,
    "configuration": {
      "browser": "Safari",
      "device": "Same provisioned iPhone; disposable draft and reply data"
    },
    "conditions": {
      "build_id": "B-PHONE-FIX",
      "browser": "Safari",
      "device": "Same provisioned iPhone; disposable draft and reply data",
      "state": "Fresh settled rich draft, then separate mixed conflict/history case; local and server revisions distinct",
      "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
      "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
      "steps": [
        "Run equal-data control and each enabled optional-only field control from the actual provisioned form.",
        "Disable network before submit, return Back, inspect body/key and send state; re-enable and inspect saved status before retry.",
        "Separately recreate two-tab conflict, keep local/save next revision, type a new body and immediately submit/offline/Back; record every state.",
        "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
        "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
        "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
        "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
      ],
      "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
      "substitutions": "Not yet established; record build, setup and additional fault injection separately."
    },
    "steps": [
      "Run equal-data control and each enabled optional-only field control from the actual provisioned form.",
      "Disable network before submit, return Back, inspect body/key and send state; re-enable and inspect saved status before retry.",
      "Separately recreate two-tab conflict, keep local/save next revision, type a new body and immediately submit/offline/Back; record every state.",
      "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
      "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
      "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
      "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
    ],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      }
    },
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
      "prerequisites": [
        "Provision the exact planned build and an HTTPS URL; prove reachability from the named device.",
        "Confirm disposable account/topic and feature mode; share access outside artifacts.",
        "Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID."
      ],
      "stop_conditions": [
        "Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.",
        "Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable."
      ]
    },
    "replaces_check_id": "C-PHONE-IOS-RECOVERY",
    "regression_covers": [
      {
        "finding_id": "F-09",
        "regression_id": "DR-IOS-RECOVERY"
      }
    ]
  },
  {
    "id": "C-PHONE-ANDROID-RECOVERY-FIX",
    "module": 7,
    "scenario": "Settled rich draft; controlled lost acknowledgement after real test-server commit",
    "expected": "Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.",
    "actual": "",
    "evidence": [],
    "status": "not_run",
    "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
    "required": true,
    "environment": "physical_device",
    "evidence_type": "physical_device",
    "driver": "person",
    "level": "target",
    "behavior": "recovery",
    "capability_id": "CAP-PHONE",
    "build_id": "B-PHONE-FIX",
    "build_confirmed": false,
    "setup_confirmed": false,
    "configuration": {
      "browser": "Chrome",
      "device": "Same provisioned Pixel8; disposable draft and reply data"
    },
    "conditions": {
      "build_id": "B-PHONE-FIX",
      "browser": "Chrome",
      "device": "Same provisioned Pixel8; disposable draft and reply data",
      "state": "Settled rich draft; controlled lost acknowledgement after real test-server commit",
      "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
      "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
      "steps": [
        "Confirm permitted response-delivery fault harness and record its boundary; do not fake a successful persistence response.",
        "Submit synthetic body while suppressing only acknowledgement, then inspect saved status through a separate read.",
        "Retry only after status inspection; count saved copies and confirm recovery state clears.",
        "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
        "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
        "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
        "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
      ],
      "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
      "substitutions": "Not yet established; record build, setup and additional fault injection separately."
    },
    "steps": [
      "Confirm permitted response-delivery fault harness and record its boundary; do not fake a successful persistence response.",
      "Submit synthetic body while suppressing only acknowledgement, then inspect saved status through a separate read.",
      "Retry only after status inspection; count saved copies and confirm recovery state clears.",
      "Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.",
      "Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.",
      "Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.",
      "Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry."
    ],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      }
    },
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
      "prerequisites": [
        "Provision the exact planned build and an HTTPS URL; prove reachability from the named device.",
        "Confirm disposable account/topic and feature mode; share access outside artifacts.",
        "Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID."
      ],
      "stop_conditions": [
        "Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.",
        "Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable."
      ]
    },
    "replaces_check_id": "C-PHONE-ANDROID-RECOVERY",
    "regression_covers": [
      {
        "finding_id": "F-09",
        "regression_id": "DR-ANDROID-RECOVERY"
      }
    ]
  }
]
```

### F-07: Active gold labels fall below normal-text contrast

P2 · observed · **implemented**

Where: resources/imladris/components.css:160,174,184; served imladris CSS recorded in build-identity.json. Expected: Normal active label/count text meets WCAG1.4.3 4.5:1 in day and twilight.. Actual: Starred3.55:1; active count3.55:1 day/2.52:1 twilight..
Cause: Active small text uses fixed --gold-700 primitive.. Disposition reason: 

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "day",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Microsoft Edge 155.0.4283.62 on Windows",
        "device": "Desktop mobile emulation, 390x844 CSS unless stated",
        "state": "Active Starred and Commend count in day theme",
        "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
        "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
        "steps": [
          "Active Starred and Commend count in day theme"
        ],
        "timing": "State/event waits in retained probe; no real software keyboard",
        "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
      }
    },
    {
      "id": "twilight",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Microsoft Edge 155.0.4283.62 on Windows",
        "device": "Desktop mobile emulation, 390x844 CSS unless stated",
        "state": "Same active controls under emulated dark system/twilight theme",
        "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
        "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
        "steps": [
          "Same active controls under emulated dark system/twilight theme"
        ],
        "timing": "State/event waits in retained probe; no real software keyboard",
        "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-day.json",
      "level": "proxy",
      "scope_ids": [
        "day"
      ],
      "observation": "Two active small-text nodes3.55:1"
    },
    {
      "ref": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-twilight.json",
      "level": "proxy",
      "scope_ids": [
        "twilight"
      ],
      "observation": "Starred3.55:1; active count2.52:1"
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": false,
    "proposal": "Use a suitable existing semantic text token such as --gold-ink with matching theme background; regenerate owned CSS and verify day/twilight normal, hover and focus states.",
    "build_id": "B-FIX",
    "evidence": [
      "implementation.diff",
      "build-identity-final.json"
    ],
    "description": "Use semantic gold ink/soft tokens; remove conflicting unlayered primitive overrides with revert-layer; regenerate CSS."
  },
  "contrary_evidence": null,
  "status_history": null
}
```

Checks (environment, level, substitutions, expected and actual):
```json
[
  {
    "id": "C-CONTRAST-DAY",
    "module": 4,
    "scenario": "Active Starred and Commend count in day theme",
    "expected": "Normal active text meets 4.5:1 contrast",
    "actual": "Starred12.48px and active count13.6px measured3.55:1; both are active text, not disabled controls.",
    "evidence": [
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-day.json"
    ],
    "status": "fail",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "contrast",
    "capability_id": "CAP-EDGE",
    "build_id": "B-LOCAL",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-LOCAL",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Active Starred and Commend count in day theme",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
      "steps": [
        "Active Starred and Commend count in day theme"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Application source and existing built assets served without injected application wiring."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-day.json"
        ],
        "reason": "No additional response or dependency replacement in this check."
      }
    },
    "covers": [
      {
        "finding_id": "F-07",
        "scope_id": "day"
      }
    ]
  },
  {
    "id": "C-CONTRAST-TWILIGHT",
    "module": 4,
    "scenario": "Same active controls under emulated dark system/twilight theme",
    "expected": "Normal active text meets 4.5:1 contrast",
    "actual": "Starred remains3.55:1; active Commend count measures2.52:1.",
    "evidence": [
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-twilight.json",
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\twilight-setup.json"
    ],
    "status": "fail",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "contrast",
    "capability_id": "CAP-EDGE",
    "build_id": "B-LOCAL",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-LOCAL",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Same active controls under emulated dark system/twilight theme",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
      "steps": [
        "Same active controls under emulated dark system/twilight theme"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Application source and existing built assets served without injected application wiring."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-twilight.json"
        ],
        "reason": "No additional response or dependency replacement in this check."
      }
    },
    "covers": [
      {
        "finding_id": "F-07",
        "scope_id": "twilight"
      }
    ]
  },
  {
    "id": "C-FIX-CONTRAST-DAY",
    "module": 4,
    "scenario": "Active Starred and Commend count in day theme",
    "expected": "Normal active text meets 4.5:1 contrast",
    "actual": "Scoped axe contrast: no violations or incomplete checks in saved light appearance; desktop/mobile Edge and mobile WebKit.",
    "evidence": [
      "browser-edge-appearance.txt",
      "browser-webkit-appearance.txt"
    ],
    "status": "pass",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "contrast",
    "capability_id": "CAP-EDGE",
    "build_id": "B-FIX",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-FIX",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Active Starred and Commend count in day theme",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Real PHP at18037 and isolated MariaDB3317; seeded topic30 and Elladan test account.",
      "steps": [
        "Active Starred and Commend count in day theme"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-FIX / S-FIXTURE; see individual test fault boundaries."
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "seed.txt",
          "thread-fixture.txt"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      }
    },
    "covers": [
      {
        "finding_id": "F-07",
        "scope_id": "day"
      }
    ]
  },
  {
    "id": "C-FIX-CONTRAST-TWILIGHT",
    "module": 4,
    "scenario": "Same active controls under emulated dark system/twilight theme",
    "expected": "Normal active text meets 4.5:1 contrast",
    "actual": "Scoped axe contrast: no violations or incomplete checks in saved dark appearance; desktop/mobile Edge and mobile WebKit.",
    "evidence": [
      "browser-edge-appearance.txt",
      "browser-webkit-appearance.txt"
    ],
    "status": "pass",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "contrast",
    "capability_id": "CAP-EDGE",
    "build_id": "B-FIX",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-FIX",
      "browser": "Microsoft Edge 155.0.4283.62 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Same active controls under emulated dark system/twilight theme",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Real PHP at18037 and isolated MariaDB3317; seeded topic30 and Elladan test account.",
      "steps": [
        "Same active controls under emulated dark system/twilight theme"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-FIX / S-FIXTURE; see individual test fault boundaries."
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "seed.txt",
          "thread-fixture.txt"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      }
    },
    "covers": [
      {
        "finding_id": "F-07",
        "scope_id": "twilight"
      }
    ]
  }
]
```

### F-08: Plain long preview has no keyboard path into its scroll area

P2 · observed · **implemented**

Where: templates/partials/composer_shell.php:149; public/assets/app.css:1928; public/assets/composer.js preview construction. Expected: Keyboard users can reach and scroll the complete rendered preview.. Actual: Tab skips the128px preview scroll region containing1412px content; independent PageDown leaves it unchanged..
Cause: Overflow region has no keyboard focus entry when its content has no focusable descendants.. Disposition reason: 

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "webkit-plain-preview",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Playwright WebKit 26.5 on Windows",
        "device": "Desktop mobile emulation, 390x844 CSS unless stated",
        "state": "Plain long Markdown preview in WebKit390x844 without links or conflict buttons",
        "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
        "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
        "steps": [
          "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants"
        ],
        "timing": "State/event waits in retained probe; no real software keyboard",
        "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json",
      "level": "proxy",
      "scope_ids": [
        "webkit-plain-preview"
      ],
      "observation": "128px viewport clips1412px of rendered content; Tab skips it."
    },
    {
      "ref": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.json",
      "level": "proxy",
      "scope_ids": [
        "webkit-plain-preview"
      ],
      "observation": "Independent keyboard replay, zero focusable descendants; PageDown cannot move preview."
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": true,
    "proposal": "Give the overflow region a meaningful accessible name and keyboard focus/scroll path with visible focus. Recheck long preview with and without links/conflict controls; confirm focus and software-keyboard behavior on phones before calling it device verified.",
    "build_id": "B-FIX",
    "evidence": [
      "implementation.diff",
      "build-identity-final.json"
    ],
    "description": "Name/focus the populated open preview scroll region and draw a token-based visible focus ring.",
    "device_regressions": [
      {
        "id": "DR-IOS-PREVIEW",
        "source_scope_ids": [
          "webkit-plain-preview"
        ],
        "environment": "physical_device",
        "behavior": "focus",
        "expected": "Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.",
        "conditions": {
          "build_id": "B-PHONE-FIX",
          "browser": "Safari",
          "device": "Provisioned iPhone plus paired hardware keyboard",
          "state": "390-or375 CSS px portrait; Markdown mode;18-paragraph preview with no links and no conflict controls",
          "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
          "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
          "steps": [
            "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
            "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
            "Repeat with VoiceOver; keep input method and focus/scroll measurements separate.",
            "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
            "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
          ],
          "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
          "substitutions": "Not yet established; record build, setup and additional fault injection separately."
        },
        "depends_on": []
      },
      {
        "id": "DR-ANDROID-PREVIEW",
        "source_scope_ids": [
          "webkit-plain-preview"
        ],
        "environment": "physical_device",
        "behavior": "focus",
        "expected": "Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.",
        "conditions": {
          "build_id": "B-PHONE-FIX",
          "browser": "Chrome",
          "device": "Provisioned Android phone plus paired hardware keyboard; record actual model/version",
          "state": "390-or412 CSS px portrait and landscape; 18-paragraph Markdown preview with/without links and conflict controls; real keyboard and browser bars.",
          "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
          "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
          "steps": [
            "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
            "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
            "Repeat with TalkBack; keep input method and focus/scroll measurements separate.",
            "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
            "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
          ],
          "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
          "substitutions": "Not yet established; record build, setup and additional fault injection separately."
        },
        "depends_on": []
      }
    ],
    "required_check_ids": [
      "C-PHONE-IOS-PREVIEW-FIX",
      "C-PHONE-ANDROID-PREVIEW-FIX"
    ]
  },
  "contrary_evidence": null,
  "status_history": null
}
```

Device regression requirements (independent of observed scope):
```json
{
  "requirements": [
    {
      "id": "DR-IOS-PREVIEW",
      "source_scope_ids": [
        "webkit-plain-preview"
      ],
      "environment": "physical_device",
      "behavior": "focus",
      "expected": "Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.",
      "conditions": {
        "build_id": "B-PHONE-FIX",
        "browser": "Safari",
        "device": "Provisioned iPhone plus paired hardware keyboard",
        "state": "390-or375 CSS px portrait; Markdown mode;18-paragraph preview with no links and no conflict controls",
        "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
        "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
        "steps": [
          "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
          "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
          "Repeat with VoiceOver; keep input method and focus/scroll measurements separate.",
          "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
          "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
        ],
        "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
        "substitutions": "Not yet established; record build, setup and additional fault injection separately."
      },
      "depends_on": []
    },
    {
      "id": "DR-ANDROID-PREVIEW",
      "source_scope_ids": [
        "webkit-plain-preview"
      ],
      "environment": "physical_device",
      "behavior": "focus",
      "expected": "Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.",
      "conditions": {
        "build_id": "B-PHONE-FIX",
        "browser": "Chrome",
        "device": "Provisioned Android phone plus paired hardware keyboard; record actual model/version",
        "state": "390-or412 CSS px portrait and landscape; 18-paragraph Markdown preview with/without links and conflict controls; real keyboard and browser bars.",
        "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
        "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
        "steps": [
          "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
          "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
          "Repeat with TalkBack; keep input method and focus/scroll measurements separate.",
          "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
          "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
        ],
        "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
        "substitutions": "Not yet established; record build, setup and additional fault injection separately."
      },
      "depends_on": []
    }
  ],
  "qualification": [
    {
      "finding_id": "F-08",
      "regression_id": "DR-IOS-PREVIEW",
      "source_scope_ids": [
        "webkit-plain-preview"
      ],
      "check_ids": [
        "C-PHONE-IOS-PREVIEW-FIX"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-08",
      "regression_id": "DR-ANDROID-PREVIEW",
      "source_scope_ids": [
        "webkit-plain-preview"
      ],
      "check_ids": [
        "C-PHONE-ANDROID-PREVIEW-FIX"
      ],
      "status": "not_run"
    }
  ],
  "note": "Regression results do not verify or refute the original observed scopes."
}
```

Checks (environment, level, substitutions, expected and actual):
```json
[
  {
    "id": "C-WK-PREVIEW",
    "module": 6,
    "scenario": "Plain long Markdown preview, no links or conflict buttons, WebKit390x844",
    "expected": "Keyboard can reach and scroll all preview content",
    "actual": "Preview scrollHeight1412/clientHeight128, tabIndex-1. Fifteen Tabs did not enter preview; scrollTop stayed0.",
    "evidence": [
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json",
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\evidence\\webkit-long-preview-keyboard.png"
    ],
    "status": "fail",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "focus",
    "capability_id": "CAP-WEBKIT",
    "build_id": "B-LOCAL",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Playwright WebKit 26.5 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-LOCAL",
      "browser": "Playwright WebKit 26.5 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Plain long Markdown preview, no links or conflict buttons, WebKit390x844",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
      "steps": [
        "Plain long Markdown preview, no links or conflict buttons, WebKit390x844"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Application source and existing built assets served without injected application wiring."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json"
        ],
        "reason": "No additional response or dependency replacement in this check."
      }
    },
    "covers": [
      {
        "finding_id": "F-08",
        "scope_id": "webkit-plain-preview"
      }
    ],
    "input_probe": {
      "method": "keyboard",
      "relation": "defect_reproduced",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json"
      ],
      "viewport": {
        "width": 390,
        "height": 844
      }
    }
  },
  {
    "id": "C-WK-KEYBOARD",
    "module": 6,
    "scenario": "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants",
    "expected": "Tab can enter preview and PageDown can scroll remaining rendered paragraphs",
    "actual": "Thirty-four Tab samples skipped the preview. After Discard, focus became BODY; PageDown left preview at102px, with128px client height/1412px scroll height and zero focusable descendants.",
    "evidence": [
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.json",
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\evidence\\keyboard-confirm-v2-before.png",
      "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\evidence\\keyboard-confirm-v2-after.png"
    ],
    "status": "fail",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "focus",
    "capability_id": "CAP-WEBKIT",
    "build_id": "B-LOCAL",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Playwright WebKit 26.5 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-LOCAL",
      "browser": "Playwright WebKit 26.5 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Local PHP plus isolated MariaDB; Markdown unless scenario explicitly names rich editor",
      "steps": [
        "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-LOCAL and S-LOCAL; additional listed separately"
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Application source and existing built assets served without injected application wiring."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.json"
        ],
        "reason": "No additional response or dependency replacement in this check."
      }
    },
    "covers": [
      {
        "finding_id": "F-08",
        "scope_id": "webkit-plain-preview"
      }
    ],
    "input_probe": {
      "method": "keyboard",
      "relation": "defect_reproduced",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.json"
      ]
    }
  },
  {
    "id": "C-FIX-WK-KEYBOARD",
    "module": 6,
    "scenario": "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants",
    "expected": "Tab can enter preview and PageDown can scroll remaining rendered paragraphs",
    "actual": "Tab reaches the named region; PageDown scrolls it; visible outline >=2px; closing Preview removes the extra tab stop.",
    "evidence": [
      "browser-webkit-appearance.txt",
      "verified-webkit-appearance/keyboard-preview-webkit.png"
    ],
    "status": "pass",
    "required": true,
    "environment": "desktop_emulation",
    "evidence_type": "emulation",
    "driver": "agent",
    "level": "proxy",
    "behavior": "focus",
    "capability_id": "CAP-WEBKIT",
    "build_id": "B-FIX",
    "build_confirmed": true,
    "setup_confirmed": true,
    "configuration": {
      "browser": "Playwright WebKit 26.5 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated"
    },
    "conditions": {
      "build_id": "B-FIX",
      "browser": "Playwright WebKit 26.5 on Windows",
      "device": "Desktop mobile emulation, 390x844 CSS unless stated",
      "state": "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants",
      "account_data": "Disposable Elladan fixture; The Archive board, topic 30; no production data",
      "setup": "Real PHP at18037 and isolated MariaDB3317; seeded topic30 and Elladan test account.",
      "steps": [
        "Independent keyboard replay after resolving shared-account conflict; plain long preview with no focusable descendants"
      ],
      "timing": "State/event waits in retained probe; no real software keyboard",
      "substitutions": "B-FIX / S-FIXTURE; see individual test fault boundaries."
    },
    "substitutions": {
      "build": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      },
      "setup": {
        "status": "known",
        "items": [
          {
            "boundary": "account_data",
            "description": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled.",
            "behavior": "replaced",
            "configured_response": "Local seeded data, real PHP services and SQL; Markdown first, rich-editor feature enabled for named rich checks.",
            "real_components": "Real kernel, CSRF, sessions, draft repository, transaction/idempotency and MariaDB persistence.",
            "untested": "Production data and deployment, email delivery, AI generation, uploads, production network and real-device behavior."
          }
        ],
        "unknown_boundaries": [],
        "evidence": [
          "seed.txt",
          "thread-fixture.txt"
        ],
        "reason": "Disposable local account, seeded topic and isolated database; email is an array driver with delivery disabled."
      },
      "additional": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      }
    },
    "covers": [
      {
        "finding_id": "F-08",
        "scope_id": "webkit-plain-preview"
      }
    ]
  },
  {
    "id": "C-PHONE-IOS-PREVIEW-FIX",
    "module": 6,
    "scenario": "390-or375 CSS px portrait; Markdown mode;18-paragraph preview with no links and no conflict controls",
    "expected": "Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.",
    "actual": "",
    "evidence": [],
    "status": "not_run",
    "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
    "required": true,
    "environment": "physical_device",
    "evidence_type": "physical_device",
    "driver": "person",
    "level": "target",
    "behavior": "focus",
    "capability_id": "CAP-PHONE",
    "build_id": "B-PHONE-FIX",
    "build_confirmed": false,
    "setup_confirmed": false,
    "configuration": {
      "browser": "Safari",
      "device": "Provisioned iPhone plus paired hardware keyboard"
    },
    "conditions": {
      "build_id": "B-PHONE-FIX",
      "browser": "Safari",
      "device": "Provisioned iPhone plus paired hardware keyboard",
      "state": "390-or375 CSS px portrait; Markdown mode;18-paragraph preview with no links and no conflict controls",
      "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
      "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
      "steps": [
        "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
        "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
        "Repeat with VoiceOver; keep input method and focus/scroll measurements separate.",
        "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
        "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
      ],
      "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
      "substitutions": "Not yet established; record build, setup and additional fault injection separately."
    },
    "steps": [
      "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
      "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
      "Repeat with VoiceOver; keep input method and focus/scroll measurements separate.",
      "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
      "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
    ],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      }
    },
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
      "prerequisites": [
        "Provision the exact planned build and an HTTPS URL; prove reachability from the named device.",
        "Confirm disposable account/topic and feature mode; share access outside artifacts.",
        "Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID."
      ],
      "stop_conditions": [
        "Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.",
        "Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable."
      ]
    },
    "replaces_check_id": "C-PHONE-IOS-PREVIEW",
    "regression_covers": [
      {
        "finding_id": "F-08",
        "regression_id": "DR-IOS-PREVIEW"
      }
    ]
  },
  {
    "id": "C-PHONE-ANDROID-PREVIEW-FIX",
    "module": 6,
    "scenario": "390-or375 CSS px portrait; Markdown mode;18-paragraph preview with no links and no conflict controls",
    "expected": "Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.",
    "actual": "",
    "evidence": [],
    "status": "not_run",
    "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
    "required": true,
    "environment": "physical_device",
    "evidence_type": "physical_device",
    "driver": "person",
    "level": "target",
    "behavior": "focus",
    "capability_id": "CAP-PHONE",
    "build_id": "B-PHONE-FIX",
    "build_confirmed": false,
    "setup_confirmed": false,
    "configuration": {
      "browser": "Chrome",
      "device": "Provisioned Android phone plus paired hardware keyboard; record actual model/version"
    },
    "conditions": {
      "build_id": "B-PHONE-FIX",
      "browser": "Chrome",
      "device": "Provisioned Android phone plus paired hardware keyboard; record actual model/version",
      "state": "390-or412 CSS px portrait and landscape; 18-paragraph Markdown preview with/without links and conflict controls; real keyboard and browser bars.",
      "account_data": "Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.",
      "setup": "Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.",
      "steps": [
        "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
        "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
        "Repeat with TalkBack; keep input method and focus/scroll measurements separate.",
        "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
        "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
      ],
      "timing": "Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.",
      "substitutions": "Not yet established; record build, setup and additional fault injection separately."
    },
    "steps": [
      "Provision Markdown mode and synthetic18-paragraph body; wait for server preview.",
      "Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.",
      "Repeat with TalkBack; keep input method and focus/scroll measurements separate.",
      "Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.",
      "Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap."
    ],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      }
    },
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.",
      "prerequisites": [
        "Provision the exact planned build and an HTTPS URL; prove reachability from the named device.",
        "Confirm disposable account/topic and feature mode; share access outside artifacts.",
        "Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID."
      ],
      "stop_conditions": [
        "Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.",
        "Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable."
      ]
    },
    "replaces_check_id": "C-PHONE-IOS-PREVIEW",
    "regression_covers": [
      {
        "finding_id": "F-08",
        "regression_id": "DR-ANDROID-PREVIEW"
      }
    ]
  }
]
```

## 4. Recommendations

```json
[]
```

## 5. Changes made

```json
[
  {
    "path": "docs/design-system/imladris/components.css",
    "finding_ids": [
      "F-07"
    ],
    "summary": "Use semantic gold ink/soft tokens; remove conflicting unlayered primitive overrides with revert-layer; regenerate CSS.",
    "evidence": [
      "implementation.diff"
    ]
  },
  {
    "path": "public/assets/composer.js",
    "finding_ids": [
      "F-08"
    ],
    "summary": "Name/focus the populated open preview scroll region and draw a token-based visible focus ring.",
    "evidence": [
      "implementation.diff"
    ]
  },
  {
    "path": "public/assets/composer.js",
    "finding_ids": [
      "F-09"
    ],
    "summary": "Read live body/title when saving a conflict; initialize a matching unconfirmed history draft before rich adapter creation, preserving typing during imports.",
    "evidence": [
      "implementation.diff"
    ]
  }
]
```

## 6. Device check script

Budget: 10 entries. Applied device-fix reservation: 4/5.

### C-PHONE-IOS-PREVIEW-FIX [F-08] {'browser': 'Safari', 'device': 'Provisioned iPhone plus paired hardware keyboard'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-08/DR-IOS-PREVIEW — device regression, not original-scope coverage
State: 390-or375 CSS px portrait; Markdown mode;18-paragraph preview with no links and no conflict controls
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Provision Markdown mode and synthetic18-paragraph body; wait for server preview.
2. Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.
3. Repeat with VoiceOver; keep input method and focus/scroll measurements separate.
4. Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.
5. Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap.
Expected after these steps: Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-ANDROID-PREVIEW-FIX [F-08] {'browser': 'Chrome', 'device': 'Provisioned Android phone plus paired hardware keyboard; record actual model/version'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-08/DR-ANDROID-PREVIEW — device regression, not original-scope coverage
State: 390-or412 CSS px portrait and landscape; 18-paragraph Markdown preview with/without links and conflict controls; real keyboard and browser bars.
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Provision Markdown mode and synthetic18-paragraph body; wait for server preview.
2. Use only Tab/Shift+Tab and page/arrow scrolling to enter the preview and reach its last paragraph.
3. Repeat with TalkBack; keep input method and focus/scroll measurements separate.
4. Repeat with links and a server conflict panel, rotate with software keyboard open/closed, and inspect reachability of Reply/Minimize.
5. Confirm visible focus, meaningful preview name, no hidden extra tab stop after closing Preview, and no focus/reading trap.
Expected after these steps: Complete preview is keyboard/AT reachable and scrollable with visible named focus; controls remain usable across keyboard/rotation states.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-IOS-RECOVERY-FIX [F-09] {'browser': 'Safari', 'device': 'Same provisioned iPhone; disposable draft and reply data'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-09/DR-IOS-RECOVERY — device regression, not original-scope coverage
State: Fresh settled rich draft, then separate mixed conflict/history case; local and server revisions distinct
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Run equal-data control and each enabled optional-only field control from the actual provisioned form.
2. Disable network before submit, return Back, inspect body/key and send state; re-enable and inspect saved status before retry.
3. Separately recreate two-tab conflict, keep local/save next revision, type a new body and immediately submit/offline/Back; record every state.
4. Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.
5. Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.
6. Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.
7. Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry.
Expected after these steps: Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-ANDROID-RECOVERY-FIX [F-09] {'browser': 'Chrome', 'device': 'Same provisioned Pixel8; disposable draft and reply data'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-09/DR-ANDROID-RECOVERY — device regression, not original-scope coverage
State: Settled rich draft; controlled lost acknowledgement after real test-server commit
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Confirm permitted response-delivery fault harness and record its boundary; do not fake a successful persistence response.
2. Submit synthetic body while suppressing only acknowledgement, then inspect saved status through a separate read.
3. Retry only after status inspection; count saved copies and confirm recovery state clears.
4. Create a two-tab conflict; type newer text after the conflict appears, then Save local as next revision and read back the actual test backend body.
5. Type a newer Unicode rich body immediately after resolving the conflict; fail the POST before commit, return Back, and compare visible body, local body and submission key.
6. Repeat with delayed rich loading and type into the native textarea while it loads; preserve this new text and rotate the key only for a real edit.
7. Keep confirmed cleanup, newer sibling drafts, lost acknowledgement and deliberate repeated-body controls separate; inspect actual persisted status before retry.
Expected after these steps: Save local writes current edits; failed-history recovery shows the latest unconfirmed body with the same key; new typing and newer sibling drafts survive; confirmed cleanup remains exact.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-IOS-FORMS-FIX [] {'browser': 'Safari', 'device': 'iPhone SE (3rd generation), if available; otherwise record exact replacement and revise plan'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### Whole check
State: Portrait375 CSS px at100% text; long rich reply; browser bars expanded/collapsed; rotate to667x375 landscape while editing
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Log in to the disposable fixture on the confirmed build; open topic30.
2. Focus the rich reply, enter Unicode and a long body, show and hide the software keyboard.
3. Keep the keyboard open while rotating; reach Reply and Minimize, dismiss keyboard and resume reading.
Expected after these steps: No focus zoom, clipped send/minimize, hidden caret or keyboard-covered action; body preserved.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-ANDROID-FORMS-FIX [] {'browser': 'Chrome', 'device': 'Pixel8, if available; otherwise record exact replacement and revise plan'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### Whole check
State: Portrait and landscape; OS default plus enlarged text; rich reply; software keyboard open
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Open topic30 on the confirmed fixture.
2. Enter long Unicode text, rotate with keyboard open, dismiss/reopen it and use Minimize.
3. Repeat at enlarged OS text and browser text scaling; report actual CSS dimensions.
Expected after these steps: Body, caret and controls remain reachable and legible without page-width overflow.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-IOS-OVERLAYS-FIX [] {'browser': 'Safari', 'device': 'iPhone SE (3rd generation), same provisioned device as C-PHONE-IOS-FORMS'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### Whole check
State: Portrait375 CSS px; mid-topic reading anchor; browser bars expanded/collapsed; reduced motion on
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Record reading anchor and scroll position; tap topic tools directly, dismiss and inspect focus/position.
2. Open rail and account menu with direct taps; dismiss by their supported close paths; use Back.
3. Repeat with VoiceOver enabled and report focus order, labels and dismissal.
Expected after these steps: No reading-position jump or focus escape; overlays fit bars/safe areas; Back follows expected history.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-ANDROID-GESTURES-FIX [] {'browser': 'Chrome', 'device': 'Pixel8, same provisioned device as C-PHONE-ANDROID-FORMS'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### Whole check
State: Long topic and expanded long preview; portrait/landscape, browser bars expanded/collapsed
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Scroll topic using finger drags, including from nested preview.
2. Tap View answer; verify accepted content clears header.
3. Open/close rail and tools while retaining a reading anchor; exercise Android back navigation.
Expected after these steps: Normal document scrolling, no trapped/competing gesture, safe overlay dismissal and visible answer.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

### C-PHONE-ANDROID-PERF-FIX [] {'browser': 'Chrome', 'device': 'Same provisioned Pixel8 connected to a desktop for Chrome remote debugging'}

NOT READY — NOT READY: exact phone-reachable fixed build, account and devices have not been provisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-PHONE-FIX · source revision: 35cacebb7b07fde20c4b5d59f462a0d9b7c00dae
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "phone_build",
      "phone_setup"
    ],
    "evidence": [],
    "reason": "Phone build and access have not been provisioned."
  }
}
```

Prerequisites:
- Provision the exact planned build and an HTTPS URL; prove reachability from the named device.
- Confirm disposable account/topic and feature mode; share access outside artifacts.
- Record actual device/OS/browser, CSS width, orientation, text scale, keyboard and substitutions; a different build/scenario gets a new linked C-ID.
Stop conditions:
- Stop if build/account/fixture differs, if the URL is production, or a write would affect real user data.
- Stop and record not run if device, software keyboard, fault harness or measurement tool is unavailable.
Measurement: Chrome DevTools Performance recording over Android remote debugging (chrome://inspect); retain traces and Event Timing/LCP/layout-shift entries for each cold/warm run. Tool access is not provisioned yet.

Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### Whole check
State: Cold and warm topic entry; no debug CPU shaping unless recorded; real cellular or Wi-Fi specified
Account/data: Provision disposable Elladan-equivalent fixture/topic30 and fresh drafts; anonymous remains disabled unless actual approved fixture differs.
Setup: Provision phone-reachable HTTPS B-FIX bytes from build-identity-final.json and a disposable seeded account; real PHP/SQL; share access privately.
Timing: Record each state transition and wait for explicit settled controls where instructed; keep rapid mixed-history case separate.
Steps:
1. Record device/OS/browser/build and actual network; clear site cache for a cold run.
2. Capture Chrome DevTools Performance trace through remote debugging while opening topic, expanding composer and submitting a disposable reply.
3. Repeat warm and retain traces, LCP/layout-shift/events, long tasks and stalled/pending feedback.
Expected after these steps: Measured device timings and pending feedback; report traces and individual runs, not an invented field percentile.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

Build as tested: ___
Build confirmed separately [yes/no]: ___
Setup as tested: ___
Setup confirmed separately [yes/no]: ___
Browser/OS/device as tested: ___
CSS width/orientation/text size/keyboard/input as tested: ___
Substitutions and unexpected differences: ___
Notes: ___
Screenshot: ___

Beyond budget (titles only):

None.

## 7. Open questions

- Evidence ceiling is proxy, not physical target.
- Installed runner reference packaging gap and local r2 reference fallback are disclosed.
- WebKit immediate resize and first keyboard confirmation were state-contaminated controls; retained, not silently promoted or replaced.
- No field performance data. Single local throttled sample is not a release performance verdict.
- No physical phone, real software keyboard or assistive-technology execution; all phone checks are NOT READY.
- Full composer test invocation: 3337 tests, 12 Windows deployment/tooling failures, 2 warnings, 2 skipped; first attempt exhausted the default128MB PHP allocation. Focused final161 tests pass.
- Root node_modules differs from lockfile; primary asset tests34/35 passed, while isolated matching-toolchain35/35 passed. Package files and primary dependencies preserved.
- Final Edge38 and WebKit5 browser checks use local synthetic data and generated assets; no production deployment.
- Provision the fixed build and disposable account for the nine physical checks; four protect these applied fixes.
- Resolve the unrelated Windows deployment-test portability issues separately.
- F-07: confirmed finding is implemented
- F-08: confirmed finding is implemented
- F-08/DR-IOS-PREVIEW: device regression target verification not_run
- F-08/DR-ANDROID-PREVIEW: device regression target verification not_run
- F-09: confirmed finding is implemented
- F-09/DR-IOS-RECOVERY: device regression target verification not_run
- F-09/DR-ANDROID-RECOVERY: device regression target verification not_run
- C-CONTRAST-DAY: current recorded failure
- C-CONTRAST-TWILIGHT: current recorded failure
- C-WK-PREVIEW: current recorded failure
- C-WK-KEYBOARD: current recorded failure
- C-DIAG-NATIVE-BACK: current recorded failure
- C-CONTRAST-DAY: required verification incomplete
- C-CONTRAST-TWILIGHT: required verification incomplete
- C-WK-RESIZE: required verification incomplete
- C-WK-PREVIEW: required verification incomplete
- C-WK-KEYBOARD: required verification incomplete
- C-CONFLICT: required verification incomplete
- C-RICH-MIXED: required verification incomplete
- C-PHONE-IOS-FORMS: required verification incomplete
- C-PHONE-ANDROID-FORMS: required verification incomplete
- C-PHONE-IOS-OVERLAYS: required verification incomplete
- C-PHONE-ANDROID-GESTURES: required verification incomplete
- C-PHONE-IOS-PREVIEW: required verification incomplete
- C-PHONE-IOS-RECOVERY: required verification incomplete
- C-PHONE-ANDROID-RECOVERY: required verification incomplete
- C-PHONE-ANDROID-PERF: required verification incomplete
- C-DIAG-NATIVE-BACK: required verification incomplete
- C-PHONE-IOS-FORMS-FIX: required verification incomplete
- C-PHONE-ANDROID-FORMS-FIX: required verification incomplete
- C-PHONE-IOS-OVERLAYS-FIX: required verification incomplete
- C-PHONE-ANDROID-GESTURES-FIX: required verification incomplete
- C-PHONE-IOS-PREVIEW-FIX: required verification incomplete
- C-PHONE-IOS-RECOVERY-FIX: required verification incomplete
- C-PHONE-ANDROID-RECOVERY-FIX: required verification incomplete
- C-PHONE-ANDROID-PERF-FIX: required verification incomplete
- C-PHONE-ANDROID-PREVIEW-FIX: required verification incomplete
- Unresolved failed command: composer test (PHP memory_limit1024M)

## Run provenance

```json
{
  "capabilities": [
    {
      "id": "CAP-SOURCE",
      "environment": "static_review",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
      ],
      "reason": "Source and style ownership inspected."
    },
    {
      "id": "CAP-EDGE",
      "environment": "desktop_emulation",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\layout-results.txt"
      ],
      "reason": "Edge 155.0.4283.62 exercised through Playwright CLI."
    },
    {
      "id": "CAP-WEBKIT",
      "environment": "desktop_emulation",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json"
      ],
      "reason": "Windows Playwright WebKit 26.5; spoofed mobile UA does not establish iPhone capability."
    },
    {
      "id": "CAP-DB",
      "environment": "automated_test",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
      ],
      "reason": "Real isolated SQL fixture and PHP persistence."
    },
    {
      "id": "CAP-PHONE",
      "environment": "physical_device",
      "driver": "person",
      "status": "unavailable",
      "evidence": [],
      "reason": "No phone or phone-reachable test URL supplied."
    },
    {
      "id": "CAP-SIMULATOR",
      "environment": "os_simulator",
      "driver": "agent",
      "status": "unavailable",
      "evidence": [],
      "reason": "No iOS/Android OS simulator available."
    },
    {
      "id": "CAP-FIELD",
      "environment": "field_data",
      "driver": "agent",
      "status": "not_checked",
      "evidence": [],
      "reason": "No production RUM source or representative population supplied."
    }
  ],
  "builds": [
    {
      "id": "B-LOCAL",
      "source_revision": "35cacebb7b07fde20c4b5d59f462a0d9b7c00dae",
      "manifest_version": "cd261fb5782f8107",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\source-preservation.json"
      ],
      "contains_fixes": [],
      "substitutions": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Application source and existing built assets served without injected application wiring."
      }
    },
    {
      "id": "B-PHONE-PLANNED",
      "status": "planned_not_provisioned",
      "source_revision": "35cacebb7b07fde20c4b5d59f462a0d9b7c00dae",
      "manifest_version": "cd261fb5782f8107",
      "expected_identity": "Reproduce B-LOCAL assets and approved source snapshot; compare every recorded SHA-256 before testing. A changed source/build requires a new B-ID and C-IDs.",
      "contains_fixes": [],
      "substitutions": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      }
    },
    {
      "id": "B-DIAG",
      "source_revision": "35cacebb7b07fde20c4b5d59f462a0d9b7c00dae",
      "manifest_version": "05c1529dd2638ac0",
      "evidence": [
        "asset-validation-inputs.json",
        "recovery-diagnostic.spec.ts"
      ],
      "contains_fixes": [
        "F-07",
        "F-08"
      ],
      "substitutions": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      }
    },
    {
      "id": "B-FIX",
      "source_revision": "35cacebb7b07fde20c4b5d59f462a0d9b7c00dae",
      "manifest_version": "1c24a3765cdd43f9",
      "evidence": [
        "build-identity-final.json",
        "implementation.diff"
      ],
      "contains_fixes": [
        "F-07",
        "F-08",
        "F-09"
      ],
      "substitutions": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "build-identity-final.json"
        ],
        "reason": "Real PHP/SQL app and generated assets; no substituted application implementation."
      }
    },
    {
      "id": "B-PHONE-FIX",
      "status": "planned_not_provisioned",
      "source_revision": "35cacebb7b07fde20c4b5d59f462a0d9b7c00dae",
      "manifest_version": "1c24a3765cdd43f9",
      "expected_identity": "Provision B-FIX source and all SHA-256 bytes in build-identity-final.json; this is a planned build, not a reachable deployment.",
      "contains_fixes": [
        "F-07",
        "F-08",
        "F-09"
      ],
      "substitutions": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "phone_build",
          "phone_setup"
        ],
        "evidence": [],
        "reason": "Phone build and access have not been provisioned."
      }
    }
  ],
  "modules": [
    {
      "id": 1,
      "name": "Principal journey and mobile UX",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\evidence\\inbox.png",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\nojs-results.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    },
    {
      "id": 2,
      "name": "Viewport, browser chrome and safe areas",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\layout-results.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\layout-results.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-settled.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    },
    {
      "id": 3,
      "name": "Virtual keyboard and forms",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\landscape-expanded.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    },
    {
      "id": 4,
      "name": "Touch targets, reach and feedback",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\layout-results.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-day.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\axe-twilight.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    },
    {
      "id": 5,
      "name": "Overlays, navigation and data",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\overlay-results.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    },
    {
      "id": 6,
      "name": "Gestures and scrolling",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\touch-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\overlay-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    },
    {
      "id": 7,
      "name": "Performance, pending states and recovery",
      "status": "reviewed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\recovery-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\recovery-equal.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\conflict-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\rich-recovery.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\rich-settled-control.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\lost-ack-control.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\performance-results.json"
      ],
      "reason": "Reviewed available source/emulation portions; physical-device limits remain."
    }
  ],
  "commands": [
    {
      "kind": "shell",
      "command": "impeccable context --target templates/thread.php (installed Windows CLI)",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\audit.md"
      ],
      "reason": "Executed once in tool transcript; large context output was truncated there. PRODUCT.md and DESIGN.md were read directly. No fabricated stdout artifact."
    },
    {
      "kind": "shell",
      "command": "impeccable detector for thread/shared chrome/composer assets",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\detector.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\detector.exit.txt"
      ],
      "reason": "Actual detector output and exit retained; triaged in audit.md."
    },
    {
      "kind": "shell",
      "command": "Local isolated migration and fixture seeding",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\migrate.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\seed.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\thread-fixture.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\fixture-final.json"
      ]
    },
    {
      "kind": "browser",
      "command": "Playwright CLI named Edge/WebKit sessions; scripts retained alongside result JSON",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\layout-probe.js",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\layout-results.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.js",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm-v2.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\lost-ack-control.js",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\lost-ack-control.json"
      ],
      "reason": "Returned product failures remain check failures; these are not labelled command crashes."
    },
    {
      "kind": "browser",
      "command": "Initial navigation before server startup",
      "status": "failed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\audit.md"
      ],
      "reason": "ERR_CONNECTION_REFUSED during initial setup; no product verdict drawn.",
      "resolved_by": "Subsequent successful preview navigation shown in layout-results.txt."
    },
    {
      "kind": "browser",
      "command": "conflict-probe.js initial server draft mutation callback",
      "status": "failed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\conflict-results.json"
      ],
      "reason": "Response.status() is not a function after the real fixture mutation. Subsequent reads established state; the original equal case is not passed.",
      "resolved_by": "Separate recovery-equal.json control and retained server-state assertions."
    },
    {
      "kind": "shell",
      "command": "Close named Playwright sessions, verify PHP PID and Docker identity, stop owned PHP preview, docker rm -f -v rb-mobile-audit-61589551",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\cleanup.json"
      ],
      "reason": "Actual shell exits0; unrelated processes/containers untouched."
    },
    {
      "kind": "shell",
      "command": "C:\\Users\\htper\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe C:\\Users\\htper\\.codex\\plugins\\cache\\created-by-me-remote\\mobile-web-ux-runner\\2.0.3\\skills\\mobile-web-ux-runner\\scripts\\run_state.py check C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\run.json",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\initial-check.stdout.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\initial-check.stderr.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\initial-check.exit.txt"
      ],
      "reason": "Actual exit3: valid ledger, assessment complete within scope, verification failed/incomplete and device work remains."
    },
    {
      "kind": "shell",
      "command": "C:\\Users\\htper\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe C:\\Users\\htper\\.codex\\plugins\\cache\\created-by-me-remote\\mobile-web-ux-runner\\2.0.3\\skills\\mobile-web-ux-runner\\scripts\\run_state.py report C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\run.json",
      "status": "completed",
      "evidence": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\initial-report.stdout.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\initial-report.stderr.txt",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\initial-report.exit.txt"
      ],
      "reason": "Actual exit3: valid ledger, assessment complete within scope, verification failed/incomplete and device work remains."
    },
    {
      "kind": "shell",
      "command": "composer test (PHP memory_limit1024M)",
      "status": "failed",
      "exit_code": 1,
      "evidence": [
        "phpunit-full-1g.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "phpunit focused composer/draft/reaction/Imladris tests",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "phpunit-focused.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "locked-toolchain npm run check:assets",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "check-assets-locked.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "locked-toolchain npm run test:assets",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "test-assets-locked.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "playwright thread-mobile-recovery desktop and mobile Edge",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "browser-edge-verified.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "playwright affected WebKit checks",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "browser-webkit-appearance.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "playwright final saved-appearance contrast desktop/mobile Edge",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "browser-edge-appearance.txt"
      ],
      "reason": "Actual captured process exit."
    },
    {
      "kind": "shell",
      "command": "playwright native/controlled recovery repeated three times",
      "status": "completed",
      "exit_code": 0,
      "evidence": [
        "recovery-controlled-after.txt"
      ],
      "reason": "Actual captured process exit."
    }
  ],
  "cleanup": {
    "status": "done",
    "evidence": [
      "cleanup-final.json"
    ],
    "reason": "Only owned preview resources are removed after evidence collection."
  },
  "history": [
    {
      "kind": "prior_audit",
      "reference": "docs/evidence/thread-mobile-ux-2026-10-22/",
      "note": "Historical F-01..F-06 not overwritten or reclassified by this audit."
    },
    {
      "kind": "retained_controls",
      "references": [
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\conflict-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\keyboard-confirm.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\webkit-results.json",
        "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\rich-recovery.json"
      ],
      "note": "Initial unequal control, conflict-containing keyboard control, immediate resize sample and mixed rich failure retained alongside differently scoped follow-ups."
    },
    {
      "kind": "parent_audit",
      "reference": "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\run.json",
      "note": "Original observations and check IDs retained. User explicitly requested both confirmed fixes, recovery investigation and relevant tests. Changed builds receive new IDs."
    },
    {
      "kind": "parent_run_context",
      "assumptions": [
        "A plugin-only invocation requests its default read-only audit. No source remediation was authorized.",
        "The principal task is the topic/reply journey, informed by the current thread-specific work and prior audit. This is not a whole-app audit.",
        "Historical F-01 through F-06 remain in docs/evidence/thread-mobile-ux-2026-10-22; this run uses new IDs and does not change their history.",
        "The installed 2.0.3 plugin lacks required r2 reference files. Its installed SKILL, additive audit-controls and helper were combined with complete local r2 references from pixel-perfect-view-757/tools/mobile-web-ux-runner (package 2.0.1)."
      ],
      "limitations": [
        "Evidence ceiling is proxy, not physical target.",
        "No PHPUnit/full-suite, full Cloud Build browser lane, deployment or production verification run for this read-only audit.",
        "Installed runner reference packaging gap and local r2 reference fallback are disclosed.",
        "WebKit immediate resize and first keyboard confirmation were state-contaminated controls; retained, not silently promoted or replaced.",
        "No field performance data. Single local throttled sample is not a release performance verdict.",
        "F-09 remains open; passing settled controls do not refute its mixed-history scope."
      ],
      "launch": {
        "status": "started",
        "url": "http://127.0.0.1:18037",
        "evidence": [
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\server.json",
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\migrate.txt",
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\seed.txt",
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\thread-fixture.txt",
          "C:\\Users\\htper\\community-forums\\output\\playwright\\mobile-web-audit-2026-10-22\\20261022T203208Z-61589551\\build-identity.json"
        ],
        "reason": "Owned isolated preview; removed after evidence collection."
      }
    },
    {
      "kind": "test_harness_diagnosis",
      "references": [
        "browser-webkit-verified.txt",
        "browser-webkit-settled.txt",
        "browser-animation-wait.spec.ts",
        "browser-webkit-appearance.txt"
      ],
      "note": "Direct DOM theme switching sampled mismatched foreground/background in offscreen WebKit controls; waiting for that transition timed out. Final contrast tests use actual saved appearance and navigation. No extra product style change was made."
    }
  ]
}
```
