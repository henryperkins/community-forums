Ceiling: proxy (automated_test, desktop_emulation, static_review); limits: field_data not_checked, os_simulator unavailable, physical_device not_checked, no available simulator or physical device; assessment incomplete; verification incomplete.

# Mobile UX audit — thread-mobile-fixes-20261022

## 1. Verdict

Mode: audit_and_fix. Principal task: Apply all six thread critique fixes with compact mobile byline and wider prose..
Open P0/P1: F-01.
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
        "description": "Isolated seeded local MariaDB instead of live community data",
        "behavior": "replaced",
        "configured_response": "tests/browser/seed.php plus thread-view-fixture.php; topic 30 ratified-decisions",
        "real_components": "Real PHP kernel, templates, services and MariaDB",
        "untested": "Production data distribution and deployment"
      },
      {
        "boundary": "mail_delivery",
        "description": "ArrayMailer for test safety",
        "behavior": "replaced",
        "configured_response": "In-memory mail transport",
        "real_components": "App notification and write logic",
        "untested": "External email delivery"
      },
      {
        "boundary": "ai_generation",
        "description": "Dummy provider credential and seeded Living Brief",
        "behavior": "replaced",
        "configured_response": "Existing fixture summary; no provider generation invoked",
        "real_components": "Rendered Living Brief and source navigation",
        "untested": "Real AI provider and generation latency"
      }
    ],
    "unknown_boundaries": [],
    "evidence": [
      "original-audit/context.json",
      "tests/browser/seed.php",
      "tests/browser/thread-view-fixture.php"
    ],
    "reason": "",
    "id": "S-LOCAL-FIXTURE",
    "environments": [
      "desktop_emulation",
      "automated_test"
    ],
    "build_ids": [
      "B-LOCAL",
      "B-FIX-LOCAL"
    ]
  }
]
```

| Area | Disposition | Environments used | Highest level | What remains |
| --- | --- | --- | --- | --- |
| 1. Reach and placement | defects_found | desktop_emulation | proxy | Actual grip/reach needs C-PHONE-ANDROID or C-PHONE-SAFARI-BOTTOM. |
| 2. Viewport and safe areas | defects_found | desktop_emulation | proxy | Real browser chrome, safe areas, landscape and text scaling need device entries. |
| 3. Touch targets | no_defect_found | desktop_emulation | proxy | Only sampled geometry/names reviewed. Coarse emulation succeeded, but physical touch, cancellation and full spacing exceptions remain unverified. |
| 4. Forms and keyboard | defects_found | automated_test, desktop_emulation, static_review | proxy | Native keyboard/browser-bar checks remain NOT READY; Markdown and rich modes passed local regressions. |
| 5. Gestures and scrolling | no_defect_found | desktop_emulation, static_review | proxy | Pinch/edge gestures, pull-to-refresh and iOS scroll chaining need device entries. |
| 6. Overlays, navigation and dense data | no_defect_found | desktop_emulation | proxy | Android Back and iOS background scroll need phone checks; F-02 navigation friction recorded separately. |
| 7. Content priority | defects_found | automated_test, desktop_emulation | proxy | F-05/F-06 are design recommendations, not additional confirmed defects. |
| 8. Performance | not_tested |  | none | Field performance and hardware timing remain untested. |
| 9. Errors and recovery | defects_found | automated_test, desktop_emulation | proxy | Real-phone interruption, unknown successful-send response and rich-mode recovery remain untested. |
| 10. Accessibility on mobile | defects_found | desktop_emulation, static_review | proxy | No formal WCAG verdict; VoiceOver/TalkBack and real text-size/zoom checks pending. |

## 3. Findings by severity

### F-01: Failed-send recovery can lose reply text

P1 · observed · **implemented**

Where: public/assets/composer.js:912-955; :3306; served composer-5aeaedfd8f662ec5.js. Expected: An unsuccessful send keeps recoverable text and restores a usable Reply control; only confirmed success or explicit discard clears drafts.. Actual: Edge offline submit failed. Back restored the exact text while Reply stayed disabled after focus. Visiting the board and reopening the topic showed an empty textarea; no discard warning was observed. Reply count stayed 5. A later independent online submit succeeded..
Cause: Likely mechanism: clearCompletedDraftSubmits removes pending local drafts and attempts remote discard on a later loaded page without a successful submission receipt. Latest pre-offline save acknowledgement, storage contents and successful remote deletion were not established.. Disposition reason:

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "F-01-ORIGIN",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Microsoft Edge 155.0.4283.62",
        "device": "Windows desktop browser with explicit viewport override",
        "os": "Windows",
        "viewport": "390x844",
        "state": "Edge offline submit failed. Back restored the exact text while Reply stayed disabled after focus. Visiting the board and reopening the topic showed an empty textarea; no discard warning was observed. Reply count stayed 5. A later independent online submit succeeded.",
        "account_data": "Seeded Elladan member; topic 30",
        "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
        "steps": [
          "Fill a unique reply sentinel in the fixture Markdown composer.",
          "Enable CDP offline immediately, submit; observe ERR_INTERNET_DISCONNECTED.",
          "Restore network, navigate Back; inspect restored text and disabled Reply.",
          "Visit The Archive and reopen the same thread; inspect empty composer."
        ],
        "timing": "As recorded in the independent assessment; no general timing/performance claim",
        "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "original-audit/assessment-b/technical-report.md",
      "level": "proxy",
      "environment": "desktop_emulation",
      "driver": "agent",
      "observation": "Edge offline submit failed. Back restored the exact text while Reply stayed disabled after focus. Visiting the board and reopening the topic showed an empty textarea; no discard warning was observed. Reply count stayed 5. A later independent online submit succeeded.",
      "scope_ids": [
        "F-01-ORIGIN"
      ]
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": true,
    "build_id": "B-FIX-LOCAL",
    "evidence": [
      "ADR0050",
      "independent-review.md",
      "build-identity.json"
    ],
    "required_check_ids": [
      "C-FIX-PHONE-SAFARI-BOTTOM",
      "C-FIX-PHONE-SAFARI-TOP",
      "C-FIX-PHONE-SAFARI-COMPACT",
      "C-FIX-PHONE-ANDROID"
    ],
    "device_regressions": [
      {
        "id": "DR-F-01-SAFARI-BOTTOM",
        "source_scope_ids": [
          "F-01-ORIGIN"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Safari, Bottom tabs",
          "device": "iPhone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      },
      {
        "id": "DR-F-01-SAFARI-TOP",
        "source_scope_ids": [
          "F-01-ORIGIN"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Safari, Top tabs",
          "device": "iPhone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      },
      {
        "id": "DR-F-01-SAFARI-COMPACT",
        "source_scope_ids": [
          "F-01-ORIGIN"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Safari, Compact tabs if offered by installed version",
          "device": "iPhone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      },
      {
        "id": "DR-F-01-ANDROID",
        "source_scope_ids": [
          "F-01-ORIGIN"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Chrome; native Android gesture navigation",
          "device": "Android phone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      }
    ]
  },
  "contrary_evidence": null,
  "status_history": [
    {
      "status": "open",
      "reason": "Read-only audit; no fix applied."
    }
  ]
}
```

Device regression requirements (independent of observed scope):
```json
{
  "requirements": [
    {
      "id": "DR-F-01-SAFARI-BOTTOM",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Safari, Bottom tabs",
        "device": "iPhone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    },
    {
      "id": "DR-F-01-SAFARI-TOP",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Safari, Top tabs",
        "device": "iPhone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    },
    {
      "id": "DR-F-01-SAFARI-COMPACT",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Safari, Compact tabs if offered by installed version",
        "device": "iPhone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    },
    {
      "id": "DR-F-01-ANDROID",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Chrome; native Android gesture navigation",
        "device": "Android phone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    }
  ],
  "qualification": [
    {
      "finding_id": "F-01",
      "regression_id": "DR-F-01-SAFARI-BOTTOM",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "check_ids": [
        "C-FIX-PHONE-SAFARI-BOTTOM"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-01",
      "regression_id": "DR-F-01-SAFARI-TOP",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "check_ids": [
        "C-FIX-PHONE-SAFARI-TOP"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-01",
      "regression_id": "DR-F-01-SAFARI-COMPACT",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "check_ids": [
        "C-FIX-PHONE-SAFARI-COMPACT"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-01",
      "regression_id": "DR-F-01-ANDROID",
      "source_scope_ids": [
        "F-01-ORIGIN"
      ],
      "check_ids": [
        "C-FIX-PHONE-ANDROID"
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
    "id": "C-FIX-PHONE-SAFARI-BOTTOM",
    "module": 3,
    "required": true,
    "scenario": "Read at narrow portrait, enlarge text, expand/collapse browser bars; rotate to short landscape; edit with keyboard; perform offline-send/Back/reopen recovery using unique test text.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "iPhone",
      "browser": "Safari, Bottom tabs",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Safari, Bottom tabs",
      "device": "iPhone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-01",
      "F-02",
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-SAFARI-BOTTOM"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-SAFARI-BOTTOM"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  },
  {
    "id": "C-FIX-PHONE-SAFARI-TOP",
    "module": 3,
    "required": true,
    "scenario": "Record viewport; expand/collapse browser bars, enlarge text, rotate; open topic tools and keyboard composer; minimize and return focus.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "iPhone",
      "browser": "Safari, Top tabs",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Safari, Top tabs",
      "device": "iPhone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-SAFARI-TOP"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-SAFARI-TOP"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  },
  {
    "id": "C-FIX-PHONE-SAFARI-COMPACT",
    "module": 3,
    "required": true,
    "scenario": "Record viewport; exercise expanded/collapsed browser bars, portrait/landscape, enlarged text and keyboard composer.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "iPhone",
      "browser": "Safari, Compact tabs if offered by installed version",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Safari, Compact tabs if offered by installed version",
      "device": "iPhone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-SAFARI-COMPACT"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-SAFARI-COMPACT"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  },
  {
    "id": "C-FIX-PHONE-ANDROID",
    "module": 3,
    "required": true,
    "scenario": "Read/find answer; enlarge text; rotate; open/close tools with Android Back; edit with keyboard; offline-send/restore-network/Back/reopen recovery.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "Android phone",
      "browser": "Chrome; native Android gesture navigation",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Chrome; native Android gesture navigation",
      "device": "Android phone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-01",
      "F-02",
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-ANDROID"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-ANDROID"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  }
]
```

### F-02: Solved has no persistent route to the accepted answer

P2 · observed · **implemented**

Where: templates/thread.php:72; :233-235; templates/partials/post.php:53-54. Expected: Solved readers can follow an always-available, correctly paginated answer permalink.. Actual: Solved is static. Catch me up linked to the answer for an unread member but disappeared after reading/reload; guests lacked it. Accepted answer begins about 3966px down at 320px in the five-reply fixture..
Cause: The state chip is a span; the answer shortcut is incidental to conditional unread context.. Disposition reason:

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "F-02-ORIGIN",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Chromium 155.0.0.0",
        "device": "Windows desktop browser with explicit viewport override",
        "os": "Windows",
        "viewport": "320x844 and 390x844",
        "state": "Solved is static. Catch me up linked to the answer for an unread member but disappeared after reading/reload; guests lacked it. Accepted answer begins about 3966px down at 320px in the five-reply fixture.",
        "account_data": "Seeded Elladan member; topic 30",
        "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
        "steps": [
          "Open topic as a guest or already-read member.",
          "Look for an answer link beside Solved.",
          "Compare the unread-member catch-up link, then reload after reading."
        ],
        "timing": "As recorded in the independent assessment; no general timing/performance claim",
        "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "original-audit/assessment-a/assessment-a.md",
      "level": "proxy",
      "environment": "desktop_emulation",
      "driver": "agent",
      "observation": "Solved is static. Catch me up linked to the answer for an unread member but disappeared after reading/reload; guests lacked it. Accepted answer begins about 3966px down at 320px in the five-reply fixture.",
      "scope_ids": [
        "F-02-ORIGIN"
      ]
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": false,
    "build_id": "B-FIX-LOCAL",
    "evidence": [
      "ADR0050",
      "independent-review.md",
      "build-identity.json"
    ],
    "required_check_ids": [
      "C-FIX-ANSWER"
    ]
  },
  "contrary_evidence": null,
  "status_history": [
    {
      "status": "open",
      "reason": "Read-only audit; no fix applied."
    }
  ]
}
```

Checks (environment, level, substitutions, expected and actual):
```json
[]
```

### F-03: Topic header actions do not reflow with enlarged text

P2 · observed · **implemented**

Where: public/assets/app.css:10696; :11463-11467; templates/thread.php:120-130. Expected: Header controls, labels and focus outlines remain within the available narrow width as text grows.. Actual: The enlarged-root-font stress test produced 504px document scroll width at 320px and 390px. At ordinary 320px, A separately recorded client width310px versus scroll width311px (small P3 geometry). The action group measured x14,width489.796875,right503.796875; Topic tools width345.53125..
Cause: Nonwrapping topic action group retains intrinsic widths while its labels enlarge. Enlarged-root-font proxy is distinct from OS text size or browser page zoom.. Disposition reason:

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "F-03-ORIGIN",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Chromium 155.0.0.0",
        "device": "Windows desktop browser with explicit viewport override",
        "os": "Windows",
        "viewport": "320x720 and390x844",
        "state": "The enlarged-root-font stress test produced 504px document scroll width at 320px and 390px. At ordinary 320px, A separately recorded client width310px versus scroll width311px (small P3 geometry).",
        "account_data": "Seeded Elladan member; topic 30",
        "setup": "IAB desktop Chromium; HTML font-size32px via CDP; computed body/reply34px and title56px; no OS font or browser page zoom claim.",
        "steps": [
          "Open the member thread with Starred and Topic tools watch label.",
          "Using CDP CSS.setEffectivePropertyValueForNode, set HTML font-size to32px from baseline16px; this is a root-font proxy.",
          "Measure at320x720 and390x844. Restore16px afterward."
        ],
        "timing": "As recorded in the independent assessment; no general timing/performance claim",
        "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "original-audit/assessment-b/technical-report.md",
      "level": "proxy",
      "environment": "desktop_emulation",
      "driver": "agent",
      "observation": "The enlarged-root-font stress test produced 504px document scroll width at 320px and 390px. At ordinary 320px, A separately recorded client width310px versus scroll width311px (small P3 geometry).",
      "scope_ids": [
        "F-03-ORIGIN"
      ]
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": false,
    "build_id": "B-FIX-LOCAL",
    "evidence": [
      "ADR0050",
      "independent-review.md",
      "build-identity.json"
    ],
    "required_check_ids": [
      "C-FIX-LAYOUT"
    ]
  },
  "contrary_evidence": null,
  "status_history": [
    {
      "status": "open",
      "reason": "Read-only audit; no fix applied."
    }
  ]
}
```

Checks (environment, level, substitutions, expected and actual):
```json
[]
```

### F-04: Expanded landscape composer hides its minimize control under chrome

P2 · observed · **implemented**

Where: public/assets/app.css:1906-1909; :993-1000; :1328-1337; public/assets/app.js:1054-1061. Expected: Expanded composer has a reachable minimize/close path below sticky member chrome in short landscape.. Actual: At844x390 IAB coarse proxy, Minimize y47.109375-91.109375 was wholly under header y0-108. Edge fine-pointer confirmation hit Search the council at the Minimize center. No real keyboard was involved; separate keyboard focus was not exercised..
Cause: The whole sticky dock, including preview/status rows, exceeds vertical room remaining below chrome; only the textarea is height-capped. Phone keyboard and safe-area behavior are untested.. Disposition reason:

Affected scope, retained evidence, fix and history:
```json
{
  "affects": [
    {
      "id": "F-04-ORIGIN",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Chromium 155.0.0.0",
        "device": "Windows desktop browser with explicit viewport override",
        "os": "Windows",
        "viewport": "844x390",
        "state": "At844x390 IAB coarse proxy, Minimize y47.109375-91.109375 was wholly under header y0-108. Edge fine-pointer confirmation hit Search the council at the Minimize center. No real keyboard was involved; separate keyboard focus was not exercised.",
        "account_data": "Seeded Elladan member; topic 30",
        "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
        "steps": [
          "Open member topic at844x390 with preview on.",
          "Focus the Markdown reply field to expand the dock.",
          "Inspect Minimize geometry and header hit testing."
        ],
        "timing": "As recorded in the independent assessment; no general timing/performance claim",
        "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
      }
    },
    {
      "id": "F-04-EDGE",
      "conditions": {
        "build_id": "B-LOCAL",
        "browser": "Microsoft Edge155.0.4283.62",
        "device": "Windows desktop browser with explicit viewport override",
        "os": "Windows",
        "viewport": "844x390",
        "state": "Fine pointer; loaded server-draft status row; preview on; dock y-8.9375,h398.9375; Minimize x705,y15.0625,w26,h26; center hits Search the council",
        "account_data": "Seeded Elladan member; topic 30",
        "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
        "steps": [
          "Open member topic at844x390 with preview on.",
          "Focus the Markdown reply field to expand the dock.",
          "Inspect Minimize geometry and header hit testing."
        ],
        "timing": "As recorded in the independent assessment; no general timing/performance claim",
        "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
      }
    }
  ],
  "refuted_scopes": null,
  "evidence": [
    {
      "ref": "original-audit/assessment-b/technical-report.md",
      "level": "proxy",
      "environment": "desktop_emulation",
      "driver": "agent",
      "observation": "At844x390 IAB coarse proxy, Minimize y47.109375-91.109375 was wholly under header y0-108. Edge fine-pointer confirmation hit Search the council at the Minimize center. No real keyboard was involved; separate keyboard focus was not exercised.",
      "scope_ids": [
        "F-04-ORIGIN"
      ]
    },
    {
      "ref": "original-audit/assessment-b/technical-report.md",
      "level": "proxy",
      "environment": "desktop_emulation",
      "driver": "agent",
      "observation": "Edge center hit on Minimize returned A.forum-bar-search / Search the council; keyboard-focus check unrun.",
      "scope_ids": [
        "F-04-EDGE"
      ]
    }
  ],
  "fix": {
    "applied": true,
    "device_dependent": true,
    "build_id": "B-FIX-LOCAL",
    "evidence": [
      "ADR0050",
      "independent-review.md",
      "build-identity.json"
    ],
    "required_check_ids": [
      "C-FIX-PHONE-SAFARI-BOTTOM",
      "C-FIX-PHONE-SAFARI-TOP",
      "C-FIX-PHONE-SAFARI-COMPACT",
      "C-FIX-PHONE-ANDROID"
    ],
    "device_regressions": [
      {
        "id": "DR-F-04-SAFARI-BOTTOM",
        "source_scope_ids": [
          "F-04-ORIGIN",
          "F-04-EDGE"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Safari, Bottom tabs",
          "device": "iPhone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      },
      {
        "id": "DR-F-04-SAFARI-TOP",
        "source_scope_ids": [
          "F-04-ORIGIN",
          "F-04-EDGE"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Safari, Top tabs",
          "device": "iPhone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      },
      {
        "id": "DR-F-04-SAFARI-COMPACT",
        "source_scope_ids": [
          "F-04-ORIGIN",
          "F-04-EDGE"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Safari, Compact tabs if offered by installed version",
          "device": "iPhone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      },
      {
        "id": "DR-F-04-ANDROID",
        "source_scope_ids": [
          "F-04-ORIGIN",
          "F-04-EDGE"
        ],
        "environment": "physical_device",
        "behavior": "interaction",
        "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
        "conditions": {
          "build_id": "B-FIX-PHONE-PENDING",
          "browser": "Chrome; native Android gesture navigation",
          "device": "Android phone",
          "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
          "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
          "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
          "steps": [
            "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
            "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
            "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
            "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
            "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
            "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
            "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
          ],
          "timing": "After save acknowledgements and each state transition; record network timing",
          "substitutions": "Unknown until build and setup are independently confirmed"
        },
        "depends_on": []
      }
    ]
  },
  "contrary_evidence": null,
  "status_history": [
    {
      "status": "open",
      "reason": "Read-only audit; no fix applied."
    }
  ]
}
```

Device regression requirements (independent of observed scope):
```json
{
  "requirements": [
    {
      "id": "DR-F-04-SAFARI-BOTTOM",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Safari, Bottom tabs",
        "device": "iPhone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    },
    {
      "id": "DR-F-04-SAFARI-TOP",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Safari, Top tabs",
        "device": "iPhone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    },
    {
      "id": "DR-F-04-SAFARI-COMPACT",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Safari, Compact tabs if offered by installed version",
        "device": "iPhone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    },
    {
      "id": "DR-F-04-ANDROID",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "environment": "physical_device",
      "behavior": "interaction",
      "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
      "conditions": {
        "build_id": "B-FIX-PHONE-PENDING",
        "browser": "Chrome; native Android gesture navigation",
        "device": "Android phone",
        "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
        "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
        "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
        "steps": [
          "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
          "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
          "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
          "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
          "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
          "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
          "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
        ],
        "timing": "After save acknowledgements and each state transition; record network timing",
        "substitutions": "Unknown until build and setup are independently confirmed"
      },
      "depends_on": []
    }
  ],
  "qualification": [
    {
      "finding_id": "F-04",
      "regression_id": "DR-F-04-SAFARI-BOTTOM",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "check_ids": [
        "C-FIX-PHONE-SAFARI-BOTTOM"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-04",
      "regression_id": "DR-F-04-SAFARI-TOP",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "check_ids": [
        "C-FIX-PHONE-SAFARI-TOP"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-04",
      "regression_id": "DR-F-04-SAFARI-COMPACT",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "check_ids": [
        "C-FIX-PHONE-SAFARI-COMPACT"
      ],
      "status": "not_run"
    },
    {
      "finding_id": "F-04",
      "regression_id": "DR-F-04-ANDROID",
      "source_scope_ids": [
        "F-04-ORIGIN",
        "F-04-EDGE"
      ],
      "check_ids": [
        "C-FIX-PHONE-ANDROID"
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
    "id": "C-FIX-PHONE-SAFARI-BOTTOM",
    "module": 3,
    "required": true,
    "scenario": "Read at narrow portrait, enlarge text, expand/collapse browser bars; rotate to short landscape; edit with keyboard; perform offline-send/Back/reopen recovery using unique test text.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "iPhone",
      "browser": "Safari, Bottom tabs",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Safari, Bottom tabs",
      "device": "iPhone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-01",
      "F-02",
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-SAFARI-BOTTOM"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-SAFARI-BOTTOM"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  },
  {
    "id": "C-FIX-PHONE-SAFARI-TOP",
    "module": 3,
    "required": true,
    "scenario": "Record viewport; expand/collapse browser bars, enlarge text, rotate; open topic tools and keyboard composer; minimize and return focus.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "iPhone",
      "browser": "Safari, Top tabs",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Safari, Top tabs",
      "device": "iPhone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-SAFARI-TOP"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-SAFARI-TOP"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  },
  {
    "id": "C-FIX-PHONE-SAFARI-COMPACT",
    "module": 3,
    "required": true,
    "scenario": "Record viewport; exercise expanded/collapsed browser bars, portrait/landscape, enlarged text and keyboard composer.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "iPhone",
      "browser": "Safari, Compact tabs if offered by installed version",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Safari, Compact tabs if offered by installed version",
      "device": "iPhone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-SAFARI-COMPACT"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-SAFARI-COMPACT"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  },
  {
    "id": "C-FIX-PHONE-ANDROID",
    "module": 3,
    "required": true,
    "scenario": "Read/find answer; enlarge text; rotate; open/close tools with Android Back; edit with keyboard; offline-send/restore-network/Back/reopen recovery.",
    "expected": "Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.",
    "actual": "",
    "evidence": [],
    "reason": "Not run: physical device and phone-reachable matching preview unprovisioned.",
    "status": "not_run",
    "environment": "physical_device",
    "driver": "person",
    "evidence_type": "physical_device",
    "level": "target",
    "behavior": "interaction",
    "capability_id": "CAP-PHONE",
    "build_id": "B-FIX-PHONE-PENDING",
    "configuration": {
      "device": "Android phone",
      "browser": "Chrome; native Android gesture navigation",
      "os": "Record installed version; not assumed",
      "viewport": "Record actual CSS dimensions"
    },
    "conditions": {
      "build_id": "B-FIX-PHONE-PENDING",
      "browser": "Chrome; native Android gesture navigation",
      "device": "Android phone",
      "state": "Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery",
      "account_data": "Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic",
      "setup": "Independently confirm isolated matching preview and real PHP/DB; record substitutions",
      "steps": [
        "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
        "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
        "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
        "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
        "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
        "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
        "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
      ],
      "timing": "After save acknowledgements and each state transition; record network timing",
      "substitutions": "Unknown until build and setup are independently confirmed"
    },
    "setup": "Phone-reachable isolated fixed preview required; no physical result supplied.",
    "steps": [
      "Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.",
      "Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.",
      "In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.",
      "Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.",
      "At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.",
      "Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.",
      "Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run."
    ],
    "depends_on": [],
    "substitutions": {
      "build": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      },
      "setup": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Device/test-backend/account/network setup not confirmed."
      },
      "additional": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
      }
    },
    "covers": [],
    "related_findings": [
      "F-01",
      "F-02",
      "F-03",
      "F-04"
    ],
    "coverage_note": "Prospective phone validation related to findings; not original Windows proxy-scope coverage or fix verification.",
    "regression_covers": [
      {
        "finding_id": "F-01",
        "regression_id": "DR-F-01-ANDROID"
      },
      {
        "finding_id": "F-04",
        "regression_id": "DR-F-04-ANDROID"
      }
    ],
    "handoff": {
      "status": "blocked",
      "url": "",
      "reason": "NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.",
      "prerequisites": [
        "Provision an isolated matching test build and disposable fixture account.",
        "Confirm build fingerprint, actual device/browser and real backend setup separately."
      ],
      "stop_conditions": [
        "Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run."
      ]
    }
  }
]
```

## 4. Recommendations

```json
[
  {
    "id": "F-05",
    "title": "Give phone prose the width used by the permanent identity rail",
    "severity": "P2",
    "evidence_class": "recommendation",
    "status": "implemented",
    "module": 1,
    "area_ids": [
      7
    ],
    "location": "templates/partials/post.php:28-52; public/assets/app.css:10753-10758; :11524-11531",
    "revision": "468dd5b44a37bfcafacf2649223ef2c548ed9bd5",
    "expected": "Compare a compact byline/header with prose spanning beneath it at narrow widths.",
    "actual": "At320px, prose spans roughly x81-285, about204px; the six-post fixture is almost5000px long. All text remains readable; this is a reading-efficiency recommendation.",
    "reproduction": [
      "Read the same fixture at320px and390px with avatars enabled."
    ],
    "cause": "A persistent48px identity rail plus gap/padding reduces the phone reading measure.",
    "determinism": "unknown",
    "affects": [
      {
        "id": "F-05-ORIGIN",
        "conditions": {
          "build_id": "B-LOCAL",
          "browser": "Chromium 155.0.0.0",
          "device": "Windows desktop browser with explicit viewport override",
          "os": "Windows",
          "viewport": "320x844 and390x844",
          "state": "At320px, prose spans roughly x81-285, about204px; the six-post fixture is almost5000px long. All text remains readable; this is a reading-efficiency recommendation.",
          "account_data": "Seeded Elladan member; topic 30",
          "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
          "steps": [
            "Read the same fixture at320px and390px with avatars enabled."
          ],
          "timing": "As recorded in the independent assessment; no general timing/performance claim",
          "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
        }
      }
    ],
    "evidence": [
      {
        "ref": "original-audit/assessment-a/assessment-a.md",
        "level": "proxy",
        "environment": "desktop_emulation",
        "driver": "agent",
        "observation": "At320px, prose spans roughly x81-285, about204px; the six-post fixture is almost5000px long. All text remains readable; this is a reading-efficiency recommendation.",
        "scope_ids": [
          "F-05-ORIGIN"
        ]
      }
    ],
    "depends_on": [],
    "fix": {
      "applied": true,
      "device_dependent": false,
      "build_id": "B-FIX-LOCAL",
      "evidence": [
        "ADR0050",
        "independent-review.md",
        "build-identity.json"
      ],
      "required_check_ids": [
        "C-FIX-LAYOUT"
      ]
    },
    "verification_ids": [
      "C-FIX-LAYOUT"
    ],
    "status_history": [
      {
        "status": "open",
        "reason": "Read-only audit; no fix applied."
      }
    ],
    "history": [
      {
        "event": "implementation",
        "description": "Authorized source fix applied in B-FIX-LOCAL; local checks do not certify physical devices."
      }
    ]
  },
  {
    "id": "F-06",
    "title": "Make unusual reaction meanings visible to touch users",
    "severity": "P3",
    "evidence_class": "recommendation",
    "status": "implemented",
    "module": 4,
    "area_ids": [
      3,
      7,
      10
    ],
    "location": "DESIGN.md:482; templates/partials/post.php reaction picker; public/assets/app.css:11428-11429",
    "revision": "468dd5b44a37bfcafacf2649223ef2c548ed9bd5",
    "expected": "Users can learn a gesture before activating it without relying on hover.",
    "actual": "Post More presents nine symbols in a3x3 picker. Accessible names/title hints exist; phone hover behavior was not tested.",
    "reproduction": [
      "Open More on an authored post and inspect its reaction picker."
    ],
    "cause": "The documented icon treatment favors density; uncommon reaction meanings require learning.",
    "determinism": "unknown",
    "affects": [
      {
        "id": "F-06-ORIGIN",
        "conditions": {
          "build_id": "B-LOCAL",
          "browser": "Chromium 155.0.0.0",
          "device": "Windows desktop browser with explicit viewport override",
          "os": "Windows",
          "viewport": "390x844",
          "state": "Post More presents nine symbols in a3x3 picker. Accessible names/title hints exist; phone hover behavior was not tested.",
          "account_data": "Seeded Elladan member; topic 30",
          "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
          "steps": [
            "Open More on an authored post and inspect its reaction picker."
          ],
          "timing": "As recorded in the independent assessment; no general timing/performance claim",
          "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
        }
      }
    ],
    "evidence": [
      {
        "ref": "original-audit/assessment-a/assessment-a.md",
        "level": "proxy",
        "environment": "desktop_emulation",
        "driver": "agent",
        "observation": "Post More presents nine symbols in a3x3 picker. Accessible names/title hints exist; phone hover behavior was not tested.",
        "scope_ids": [
          "F-06-ORIGIN"
        ]
      }
    ],
    "depends_on": [],
    "fix": {
      "applied": true,
      "device_dependent": false,
      "build_id": "B-FIX-LOCAL",
      "evidence": [
        "ADR0050",
        "independent-review.md",
        "build-identity.json"
      ],
      "required_check_ids": [
        "C-FIX-REACTIONS"
      ]
    },
    "verification_ids": [
      "C-FIX-REACTIONS"
    ],
    "status_history": [
      {
        "status": "open",
        "reason": "Read-only audit; no fix applied."
      }
    ],
    "history": [
      {
        "event": "implementation",
        "description": "Authorized source fix applied in B-FIX-LOCAL; local checks do not certify physical devices."
      }
    ]
  }
]
```

## 5. Changes made

```json
[
  {
    "path": "public/assets/app.css",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "public/assets/app.js",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "public/assets/composer.js",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "src/Security/Session.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "src/Controller/Controller.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "src/Controller/ThreadController.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "src/Repository/ServerDraftRepository.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "templates/thread.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "templates/layout.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "templates/partials/post_toolbar.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "src/Core/App.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "tests/Integration/Core/AppThreadMobileRecoveryTest.php",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  },
  {
    "path": "tests/browser/thread-mobile-recovery.spec.ts",
    "summary": "Authorized thread-view remediation and regression coverage (ADR0050).",
    "finding_ids": [
      "F-01",
      "F-02",
      "F-03",
      "F-04",
      "F-05",
      "F-06"
    ]
  }
]
```

## 6. Device check script

Budget: 10 entries. Applied device-fix reservation: 4/5.

### C-FIX-PHONE-SAFARI-BOTTOM [F-01, F-04] {'device': 'iPhone', 'browser': 'Safari, Bottom tabs', 'os': 'Record installed version; not assumed', 'viewport': 'Record actual CSS dimensions'}

NOT READY — NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-FIX-PHONE-PENDING · source revision: not reported
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "No phone-reachable build provisioned or inspected."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Device/test-backend/account/network setup not confirmed."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
  }
}
```

Prerequisites:
- Provision an isolated matching test build and disposable fixture account.
- Confirm build fingerprint, actual device/browser and real backend setup separately.
Stop conditions:
- Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run.

Setup: Phone-reachable isolated fixed preview required; no physical result supplied.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-01/DR-F-01-SAFARI-BOTTOM — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

#### F-04/DR-F-04-SAFARI-BOTTOM — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
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

### C-FIX-PHONE-SAFARI-TOP [F-01, F-04] {'device': 'iPhone', 'browser': 'Safari, Top tabs', 'os': 'Record installed version; not assumed', 'viewport': 'Record actual CSS dimensions'}

NOT READY — NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-FIX-PHONE-PENDING · source revision: not reported
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "No phone-reachable build provisioned or inspected."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Device/test-backend/account/network setup not confirmed."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
  }
}
```

Prerequisites:
- Provision an isolated matching test build and disposable fixture account.
- Confirm build fingerprint, actual device/browser and real backend setup separately.
Stop conditions:
- Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run.

Setup: Phone-reachable isolated fixed preview required; no physical result supplied.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-01/DR-F-01-SAFARI-TOP — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

#### F-04/DR-F-04-SAFARI-TOP — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
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

### C-FIX-PHONE-SAFARI-COMPACT [F-01, F-04] {'device': 'iPhone', 'browser': 'Safari, Compact tabs if offered by installed version', 'os': 'Record installed version; not assumed', 'viewport': 'Record actual CSS dimensions'}

NOT READY — NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-FIX-PHONE-PENDING · source revision: not reported
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "No phone-reachable build provisioned or inspected."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Device/test-backend/account/network setup not confirmed."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
  }
}
```

Prerequisites:
- Provision an isolated matching test build and disposable fixture account.
- Confirm build fingerprint, actual device/browser and real backend setup separately.
Stop conditions:
- Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run.

Setup: Phone-reachable isolated fixed preview required; no physical result supplied.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-01/DR-F-01-SAFARI-COMPACT — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

#### F-04/DR-F-04-SAFARI-COMPACT — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
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

### C-FIX-PHONE-ANDROID [F-01, F-04] {'device': 'Android phone', 'browser': 'Chrome; native Android gesture navigation', 'os': 'Record installed version; not assumed', 'viewport': 'Record actual CSS dimensions'}

NOT READY — NOT READY: matching isolated phone-reachable preview and physical device are unprovisioned.
URL: Not supplied — provision a phone-reachable test URL
Build: B-FIX-PHONE-PENDING · source revision: not reported
Confirm the exact build and disposable test account before any write.
Substitutions active in this check (build/setup/additional):
```json
{
  "build": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "No phone-reachable build provisioned or inspected."
  },
  "setup": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Device/test-backend/account/network setup not confirmed."
  },
  "additional": {
    "status": "unknown",
    "items": [],
    "unknown_boundaries": [
      "*"
    ],
    "evidence": [],
    "reason": "Tester must report additional substitutions; local CDP offline/overrides do not transfer."
  }
}
```

Prerequisites:
- Provision an isolated matching test build and disposable fixture account.
- Confirm build fingerprint, actual device/browser and real backend setup separately.
Stop conditions:
- Stop on wrong build, production account/data, unexpected write destination or unconfirmed setup; record Not run.

Setup: Phone-reachable isolated fixed preview required; no physical result supplied.
Record actual CSS width, orientation, text size/zoom, keyboard state, browser/OS version, and input method.

#### F-01/DR-F-01-ANDROID — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
Result [pass/fail/not run]: ___
Actual behavior: ___
Evidence/screenshot reference: ___

#### F-04/DR-F-04-ANDROID — device regression, not original-scope coverage
State: Portrait/landscape, expanded/collapsed browser bars, real keyboard, Markdown/rich failed-send and history recovery
Account/data: Dedicated seeded Elladan-equivalent test account, fixture topic with accepted answer and later-page topic
Setup: Independently confirm isolated matching preview and real PHP/DB; record substitutions
Timing: After save acknowledgements and each state transition; record network timing
Steps:
1. Provision an isolated phone-reachable build matching build-identity.json. Confirm build and real backend/account setup separately; do not use production data.
2. Record actual device, OS/browser versions, Safari tab layout or Android Chrome, CSS viewport and text scaling.
3. In Markdown and rich modes type unique multiline text; wait for save acknowledgement; disconnect, Send, reconnect, Back, navigate to board, reopen and reload. Text must remain and Send must work without automatic retry. Retry unchanged once and confirm one persisted post.
4. Repeat with delivery lost after commit where controllable; change text before retry and confirm the new version persists. After confirmed sends, make identical-word and newer sibling drafts, revisit older history entries, and verify neither newer draft is discarded.
5. At narrow portrait and enlarged text read compact bylines/full prose; follow View answer and inspect visible reaction names before activation.
6. Expand a multiline reply with Preview and sync/status text; rotate to short landscape; expand/collapse browser bars; open/close the real software keyboard. Reach Minimize and Reply, return to the reading position, and retain all text.
7. Record recovery and dock portions separately, with blank result/build/setup confirmation, notes and screenshot fields. Leave unexecuted states Not run.
Expected after these steps: Text and controls remain reachable; no page-level overflow for ordinary prose; source and answer navigation work; failed sends preserve recoverable text and a usable submit path; overlays/focus remain controllable. Performance entry reports measured values without inventing thresholds/results.
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

- No iOS/Android target, OS simulator, real software keyboard, browser chrome, safe-area, screen-reader or hardware performance result.
- No phone-reachable preview has been provisioned; B-PHONE-PENDING is a prerequisite plan, not an executable local URL.
- Field LCP, INP and CLS not measured; exact supported mobile browser versions are not declared in project source.
- Read-only asset check failed with a retained delivery-asset digest mismatch; this audit did not rebuild or repair assets.
- No source change was made; a full PHPUnit regression run is outside this read-only critique and has not been claimed.
- Latest offline sentinel was not separately acknowledged saved before offline; Back did restore it and no discard warning was observed. Actual localStorage/remote-draft deletion was not inspected.
- Rich composer was not exercised because the fixture pins wysiwyg_composer=false.
- Screenshots were captured in CUA transcripts; no unsupported filesystem screenshot-save operation or local PNG artifact is claimed.
- No physical device or OS simulator results. Four reserved device entries jointly cover applied recovery/dock changes within floor(10/2).
- Original audit remains unchanged under original-audit/. Current fixes are implemented, not device-verified.
- Full local suites retain environment/baseline failures separately; no publication, commit or deployment requested.
- Complete four reserved physical-device checks on a matching isolated reachable preview.
- Keep full-suite Windows/runtime environment failures distinct from affected-surface regression results.
- F-01: confirmed finding is implemented
- F-01/DR-F-01-SAFARI-BOTTOM: device regression target verification not_run
- F-01/DR-F-01-SAFARI-TOP: device regression target verification not_run
- F-01/DR-F-01-SAFARI-COMPACT: device regression target verification not_run
- F-01/DR-F-01-ANDROID: device regression target verification not_run
- F-02: confirmed finding is implemented
- F-03: confirmed finding is implemented
- F-04: confirmed finding is implemented
- F-04/DR-F-04-SAFARI-BOTTOM: device regression target verification not_run
- F-04/DR-F-04-SAFARI-TOP: device regression target verification not_run
- F-04/DR-F-04-SAFARI-COMPACT: device regression target verification not_run
- F-04/DR-F-04-ANDROID: device regression target verification not_run
- area 8: not tested
- Required checks remain unrun or no required checks were recorded
- C-FIX-PHONE-SAFARI-BOTTOM: required verification incomplete
- C-FIX-PHONE-SAFARI-TOP: required verification incomplete
- C-FIX-PHONE-SAFARI-COMPACT: required verification incomplete
- C-FIX-PHONE-ANDROID: required verification incomplete

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
        "AGENTS.md",
        "templates/thread.php",
        "original-audit/working-source-fingerprints.json"
      ],
      "reason": ""
    },
    {
      "id": "CAP-BROWSER",
      "environment": "desktop_emulation",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "CUA in-app browser successfully loaded /t/30-ratified-decisions and exposed rendered topic/answer/poll DOM"
      ],
      "reason": ""
    },
    {
      "id": "CAP-CLI",
      "environment": "automated_test",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "asset-check.txt: npm run check:assets executed and reported a retained asset digest mismatch"
      ],
      "reason": ""
    },
    {
      "id": "CAP-SIMULATOR",
      "environment": "os_simulator",
      "driver": "agent",
      "status": "unavailable",
      "evidence": [
        "Tool inventory and CUA surface inventory"
      ],
      "reason": "No OS simulator tool exposed in the session."
    },
    {
      "id": "CAP-PHONE",
      "environment": "physical_device",
      "driver": "person",
      "status": "not_checked",
      "evidence": [],
      "reason": "No person-driven device results supplied; no phone configuration confirmed."
    },
    {
      "id": "CAP-FIELD",
      "environment": "field_data",
      "driver": "agent",
      "status": "not_checked",
      "evidence": [],
      "reason": "No RUM or CrUX dataset for this working build supplied."
    },
    {
      "id": "CAP-EDGE",
      "environment": "desktop_emulation",
      "driver": "agent",
      "status": "available",
      "evidence": [
        "original-audit/assessment-b/technical-report.md",
        "original-audit/successful-reply-db-proof.txt"
      ],
      "reason": ""
    }
  ],
  "builds": [
    {
      "id": "B-LOCAL",
      "description": "HEAD 468dd5b44a37bfcafacf2649223ef2c548ed9bd5 plus preserved dirty working files; fingerprints recorded. Real local PHP serves /t/30-ratified-decisions. Asset integrity check failed; actual loaded URLs are the rendered identity.",
      "contains_fixes": [],
      "substitutions": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
        ],
        "reason": ""
      }
    },
    {
      "id": "B-PHONE-PENDING",
      "description": "Unprovisioned phone-reachable test preview matching B-LOCAL working-source fingerprints. Must establish reachability and independently confirm build/setup before any phone result.",
      "contains_fixes": [],
      "substitutions": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      }
    },
    {
      "id": "B-FIX-LOCAL",
      "description": "HEAD468dd5b plus preserved working baseline and reviewed six-fix patch; exact source hashes and asset manifest in build-identity.json.",
      "contains_fixes": [
        "F-01",
        "F-02",
        "F-03",
        "F-04",
        "F-05",
        "F-06"
      ],
      "substitutions": {
        "status": "none_checked",
        "items": [],
        "unknown_boundaries": [],
        "evidence": [
          "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
        ],
        "reason": ""
      }
    },
    {
      "id": "B-FIX-PHONE-PENDING",
      "description": "Unprovisioned phone-reachable isolated preview of B-FIX-LOCAL. Match source and asset fingerprints before testing; no deployment requested.",
      "contains_fixes": [
        "F-01",
        "F-02",
        "F-03",
        "F-04",
        "F-05",
        "F-06"
      ],
      "substitutions": {
        "status": "unknown",
        "items": [],
        "unknown_boundaries": [
          "*"
        ],
        "evidence": [],
        "reason": "No phone-reachable build provisioned or inspected."
      }
    }
  ],
  "modules": [
    {
      "id": 1,
      "name": "Principal journey and mobile UX",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    },
    {
      "id": 2,
      "name": "Viewport, browser chrome and safe areas",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    },
    {
      "id": 3,
      "name": "Virtual keyboard and forms",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    },
    {
      "id": 4,
      "name": "Touch targets, reach and feedback",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    },
    {
      "id": 5,
      "name": "Overlays, navigation and data",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    },
    {
      "id": 6,
      "name": "Gestures and scrolling",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    },
    {
      "id": 7,
      "name": "Performance, pending states and recovery",
      "status": "reviewed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Applicability reviewed; unavailable target checks and unmeasured performance remain explicit."
    }
  ],
  "commands": [
    {
      "command": "impeccable.cmd context --target templates/thread/show.php",
      "target": "templates/thread/show.php",
      "status": "completed",
      "evidence": [
        "Tool output loaded existing PRODUCT.md and DESIGN.md"
      ],
      "reason": "Initial guessed path was corrected to existing templates/thread.php before assessments; no thread-specific surface brief exists.",
      "runtime_status": "executed",
      "kind": "workflow"
    },
    {
      "command": "impeccable.cmd critique-storage slug templates/thread.php",
      "target": "templates/thread.php",
      "status": "completed",
      "evidence": [
        "templates-thread-php"
      ],
      "reason": "",
      "runtime_status": "executed",
      "kind": "workflow"
    },
    {
      "command": "npm run check:assets",
      "target": "Current working assets",
      "status": "failed",
      "exit_code": 1,
      "evidence": [
        "original-audit/asset-check.txt"
      ],
      "reason": "Retained asset digest mismatch /assets/dist/app-c89b55420ad2231f.js; no rebuild attempted.",
      "runtime_status": "failed",
      "kind": "shell",
      "resolved_by": "C-FIX-ASSETS"
    },
    {
      "command": "Background Start-Process server restore",
      "status": "not_run",
      "evidence": [
        "Tool approval rejection; no execution"
      ],
      "reason": "A supervised exec server was started successfully instead and later cleaned up.",
      "runtime_status": "rejected",
      "kind": "shell",
      "resolved_by": "Supervised exec PHP server session86340/PID18760; healthz200; bounded check completed and resource-cleanup.json confirms removal."
    },
    {
      "command": "Owned test database SELECT for successful post128",
      "status": "completed",
      "evidence": [
        "original-audit/successful-reply-db-proof.txt"
      ],
      "reason": "Local fixture persistence only; no deployment claim.",
      "runtime_status": "executed",
      "kind": "shell"
    },
    {
      "command": "impeccable.cmd detect --json templates/thread.php templates/partials/post.php templates/partials/composer.php templates/partials/composer_shell.php templates/partials/thread_tools.php templates/partials/thread_memory_slot.php templates/partials/living_brief.php templates/partials/living_brief_empty.php templates/partials/thread_restructure.php templates/partials/pagination.php",
      "kind": "workflow",
      "status": "completed",
      "evidence": [
        "original-audit/assessment-b/detector-raw.json",
        "original-audit/assessment-b/detector-exit.txt"
      ],
      "reason": "One invocation; ten named markup targets; zero hits; browser manual fallback."
    },
    {
      "command": "Independent critique assessment A and assessment B",
      "kind": "workflow",
      "status": "completed",
      "evidence": [
        "original-audit/assessment-a/assessment-a.md",
        "original-audit/assessment-b/technical-report.md"
      ],
      "reason": "Assessment A finished before detector findings entered synthesis."
    },
    {
      "command": "impeccable.cmd critique-storage write templates/thread.php [owned temporary body]",
      "kind": "workflow",
      "status": "completed",
      "evidence": [
        "original-audit/critique-storage-write.txt"
      ],
      "reason": "Saved snapshot; temporary body removed."
    },
    {
      "command": "impeccable.cmd critique-storage trend templates/thread.php 5",
      "kind": "workflow",
      "status": "completed",
      "evidence": [
        "original-audit/critique-storage-trend.json"
      ],
      "reason": "24/40 ->24/40 across different builds; no historical finding refutation claimed."
    }
  ],
  "cleanup": {
    "status": "done",
    "evidence": [
      "verification.json"
    ],
    "reason": "Owned PHP preview stopped and labelled disposable MariaDB removed after tests; unrelated services untouched."
  },
  "history": [
    {
      "event": "User Resume",
      "effect": "Preserved earlier IAB evidence; restarted only the owned PHP runtime; remaining recovery/send checks used a fresh Edge tab. Source fingerprints and HEAD remained unchanged."
    },
    {
      "event": "authorized_remediation",
      "description": "User: Fix all six items and use the compact byline with wider mobile prose. Original audit and source preserved; isolated worktree used."
    },
    {
      "event": "original_failed_checks_preserved",
      "checks": [
        {
          "id": "C-ANSWER",
          "module": 1,
          "required": false,
          "scenario": "Find accepted answer after guest/read-member entry",
          "expected": "Persistent answer permalink",
          "actual": "Static Solved has no answer link; catch-up link disappears after reading.",
          "status": "fail",
          "evidence": [
            "original-audit/assessment-a/assessment-a.md"
          ],
          "reason": "Historical original-audit check; continuation checks have new IDs.",
          "environment": "desktop_emulation",
          "driver": "agent",
          "evidence_type": "emulation",
          "level": "proxy",
          "behavior": "layout",
          "capability_id": "CAP-BROWSER",
          "build_id": "B-LOCAL",
          "configuration": {
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop",
            "os": "Windows",
            "viewport": "320x844 and390x844"
          },
          "conditions": {
            "build_id": "B-LOCAL",
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop browser with explicit viewport override",
            "os": "Windows",
            "viewport": "320x844 and390x844",
            "state": "Find accepted answer after guest/read-member entry",
            "account_data": "Seeded Elladan member; topic 30",
            "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
            "steps": [
              "Find accepted answer after guest/read-member entry"
            ],
            "timing": "As recorded in the independent assessment; no general timing/performance claim",
            "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
          },
          "depends_on": [],
          "substitutions": {
            "build": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
              ],
              "reason": ""
            },
            "setup": {
              "status": "known",
              "items": [
                {
                  "boundary": "account_data",
                  "description": "Isolated seeded local MariaDB instead of live community data",
                  "behavior": "replaced",
                  "configured_response": "tests/browser/seed.php plus thread-view-fixture.php; topic 30 ratified-decisions",
                  "real_components": "Real PHP kernel, templates, services and MariaDB",
                  "untested": "Production data distribution and deployment"
                },
                {
                  "boundary": "mail_delivery",
                  "description": "ArrayMailer for test safety",
                  "behavior": "replaced",
                  "configured_response": "In-memory mail transport",
                  "real_components": "App notification and write logic",
                  "untested": "External email delivery"
                },
                {
                  "boundary": "ai_generation",
                  "description": "Dummy provider credential and seeded Living Brief",
                  "behavior": "replaced",
                  "configured_response": "Existing fixture summary; no provider generation invoked",
                  "real_components": "Rendered Living Brief and source navigation",
                  "untested": "Real AI provider and generation latency"
                }
              ],
              "unknown_boundaries": [],
              "evidence": [
                "original-audit/context.json",
                "tests/browser/seed.php",
                "tests/browser/thread-view-fixture.php"
              ],
              "reason": "",
              "id": "S-LOCAL-FIXTURE",
              "environments": [
                "desktop_emulation",
                "automated_test"
              ],
              "build_ids": [
                "B-LOCAL"
              ]
            },
            "additional": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "Assessment browser steps added no response interception; offline is scenario state."
              ],
              "reason": ""
            }
          },
          "covers": [
            {
              "finding_id": "F-02",
              "scope_id": "F-02-ORIGIN"
            }
          ]
        },
        {
          "id": "C-HEADER",
          "module": 2,
          "required": false,
          "scenario": "Root-font enlargement at narrow viewport",
          "expected": "Header reflows without page overflow",
          "actual": "504px document scroll width at320/390px; ordinary320px also had1px overflow in A.",
          "status": "fail",
          "evidence": [
            "original-audit/assessment-b/technical-report.md"
          ],
          "reason": "Historical original-audit check; continuation checks have new IDs.",
          "environment": "desktop_emulation",
          "driver": "agent",
          "evidence_type": "emulation",
          "level": "proxy",
          "behavior": "layout",
          "capability_id": "CAP-BROWSER",
          "build_id": "B-LOCAL",
          "configuration": {
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop",
            "os": "Windows",
            "viewport": "320x844 and390x844; root-font stress"
          },
          "conditions": {
            "build_id": "B-LOCAL",
            "browser": "Chromium 155.0.0.0",
            "device": "Windows desktop browser with explicit viewport override",
            "os": "Windows",
            "viewport": "320x720 and390x844",
            "state": "The enlarged-root-font stress test produced 504px document scroll width at 320px and 390px. At ordinary 320px, A separately recorded client width310px versus scroll width311px (small P3 geometry).",
            "account_data": "Seeded Elladan member; topic 30",
            "setup": "IAB desktop Chromium; HTML font-size32px via CDP; computed body/reply34px and title56px; no OS font or browser page zoom claim.",
            "steps": [
              "Open the member thread with Starred and Topic tools watch label.",
              "Using CDP CSS.setEffectivePropertyValueForNode, set HTML font-size to32px from baseline16px; this is a root-font proxy.",
              "Measure at320x720 and390x844. Restore16px afterward."
            ],
            "timing": "As recorded in the independent assessment; no general timing/performance claim",
            "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
          },
          "depends_on": [],
          "substitutions": {
            "build": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
              ],
              "reason": ""
            },
            "setup": {
              "status": "known",
              "items": [
                {
                  "boundary": "account_data",
                  "description": "Isolated seeded local MariaDB instead of live community data",
                  "behavior": "replaced",
                  "configured_response": "tests/browser/seed.php plus thread-view-fixture.php; topic 30 ratified-decisions",
                  "real_components": "Real PHP kernel, templates, services and MariaDB",
                  "untested": "Production data distribution and deployment"
                },
                {
                  "boundary": "mail_delivery",
                  "description": "ArrayMailer for test safety",
                  "behavior": "replaced",
                  "configured_response": "In-memory mail transport",
                  "real_components": "App notification and write logic",
                  "untested": "External email delivery"
                },
                {
                  "boundary": "ai_generation",
                  "description": "Dummy provider credential and seeded Living Brief",
                  "behavior": "replaced",
                  "configured_response": "Existing fixture summary; no provider generation invoked",
                  "real_components": "Rendered Living Brief and source navigation",
                  "untested": "Real AI provider and generation latency"
                }
              ],
              "unknown_boundaries": [],
              "evidence": [
                "original-audit/context.json",
                "tests/browser/seed.php",
                "tests/browser/thread-view-fixture.php"
              ],
              "reason": "",
              "id": "S-LOCAL-FIXTURE",
              "environments": [
                "desktop_emulation",
                "automated_test"
              ],
              "build_ids": [
                "B-LOCAL"
              ]
            },
            "additional": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "Assessment browser steps added no response interception; offline is scenario state."
              ],
              "reason": ""
            }
          },
          "covers": [
            {
              "finding_id": "F-03",
              "scope_id": "F-03-ORIGIN"
            }
          ]
        },
        {
          "id": "C-LANDSCAPE",
          "module": 3,
          "required": false,
          "scenario": "Expand composer in short landscape",
          "expected": "Minimize stays reachable below header",
          "actual": "Minimize is underneath sticky chrome at844x390.",
          "status": "fail",
          "evidence": [
            "original-audit/assessment-b/technical-report.md"
          ],
          "reason": "Historical original-audit check; continuation checks have new IDs.",
          "environment": "desktop_emulation",
          "driver": "agent",
          "evidence_type": "emulation",
          "level": "proxy",
          "behavior": "interaction",
          "capability_id": "CAP-BROWSER",
          "build_id": "B-LOCAL",
          "configuration": {
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop",
            "os": "Windows",
            "viewport": "844x390"
          },
          "conditions": {
            "build_id": "B-LOCAL",
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop browser with explicit viewport override",
            "os": "Windows",
            "viewport": "844x390",
            "state": "Expand composer in short landscape",
            "account_data": "Seeded Elladan member; topic 30",
            "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
            "steps": [
              "Expand composer in short landscape"
            ],
            "timing": "As recorded in the independent assessment; no general timing/performance claim",
            "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
          },
          "depends_on": [],
          "substitutions": {
            "build": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
              ],
              "reason": ""
            },
            "setup": {
              "status": "known",
              "items": [
                {
                  "boundary": "account_data",
                  "description": "Isolated seeded local MariaDB instead of live community data",
                  "behavior": "replaced",
                  "configured_response": "tests/browser/seed.php plus thread-view-fixture.php; topic 30 ratified-decisions",
                  "real_components": "Real PHP kernel, templates, services and MariaDB",
                  "untested": "Production data distribution and deployment"
                },
                {
                  "boundary": "mail_delivery",
                  "description": "ArrayMailer for test safety",
                  "behavior": "replaced",
                  "configured_response": "In-memory mail transport",
                  "real_components": "App notification and write logic",
                  "untested": "External email delivery"
                },
                {
                  "boundary": "ai_generation",
                  "description": "Dummy provider credential and seeded Living Brief",
                  "behavior": "replaced",
                  "configured_response": "Existing fixture summary; no provider generation invoked",
                  "real_components": "Rendered Living Brief and source navigation",
                  "untested": "Real AI provider and generation latency"
                }
              ],
              "unknown_boundaries": [],
              "evidence": [
                "original-audit/context.json",
                "tests/browser/seed.php",
                "tests/browser/thread-view-fixture.php"
              ],
              "reason": "",
              "id": "S-LOCAL-FIXTURE",
              "environments": [
                "desktop_emulation",
                "automated_test"
              ],
              "build_ids": [
                "B-LOCAL"
              ]
            },
            "additional": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "Assessment browser steps added no response interception; offline is scenario state."
              ],
              "reason": ""
            }
          },
          "covers": [
            {
              "finding_id": "F-04",
              "scope_id": "F-04-ORIGIN"
            },
            {
              "finding_id": "F-04",
              "scope_id": "F-04-EDGE"
            }
          ]
        },
        {
          "id": "C-OFFLINE",
          "module": 7,
          "required": false,
          "scenario": "Offline submit then Back, board, reopen",
          "expected": "Recover text and usable submit control after unsuccessful send",
          "actual": "ERR_INTERNET_DISCONNECTED; Back retained exact sentinel but Reply stayed disabled after focus; board/reopen had empty textarea and reply_count5, no discard warning observed. New value save acknowledgement before offline was unconfirmed; no remote draft deletion claim.",
          "status": "fail",
          "evidence": [
            "original-audit/assessment-b/technical-report.md"
          ],
          "reason": "Historical original-audit check; continuation checks have new IDs.",
          "environment": "desktop_emulation",
          "driver": "agent",
          "evidence_type": "emulation",
          "level": "proxy",
          "behavior": "interaction",
          "capability_id": "CAP-EDGE",
          "build_id": "B-LOCAL",
          "configuration": {
            "browser": "Microsoft Edge155.0.4283.62",
            "device": "Windows desktop",
            "os": "Windows",
            "viewport": "390x844"
          },
          "conditions": {
            "build_id": "B-LOCAL",
            "browser": "Microsoft Edge155.0.4283.62",
            "device": "Windows desktop browser with explicit viewport override",
            "os": "Windows",
            "viewport": "390x844",
            "state": "Offline submit then Back, board, reopen",
            "account_data": "Seeded Elladan member; topic 30",
            "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
            "steps": [
              "Offline submit then Back, board, reopen"
            ],
            "timing": "As recorded in the independent assessment; no general timing/performance claim",
            "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
          },
          "depends_on": [],
          "substitutions": {
            "build": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
              ],
              "reason": ""
            },
            "setup": {
              "status": "known",
              "items": [
                {
                  "boundary": "account_data",
                  "description": "Isolated seeded local MariaDB instead of live community data",
                  "behavior": "replaced",
                  "configured_response": "tests/browser/seed.php plus thread-view-fixture.php; topic 30 ratified-decisions",
                  "real_components": "Real PHP kernel, templates, services and MariaDB",
                  "untested": "Production data distribution and deployment"
                },
                {
                  "boundary": "mail_delivery",
                  "description": "ArrayMailer for test safety",
                  "behavior": "replaced",
                  "configured_response": "In-memory mail transport",
                  "real_components": "App notification and write logic",
                  "untested": "External email delivery"
                },
                {
                  "boundary": "ai_generation",
                  "description": "Dummy provider credential and seeded Living Brief",
                  "behavior": "replaced",
                  "configured_response": "Existing fixture summary; no provider generation invoked",
                  "real_components": "Rendered Living Brief and source navigation",
                  "untested": "Real AI provider and generation latency"
                }
              ],
              "unknown_boundaries": [],
              "evidence": [
                "original-audit/context.json",
                "tests/browser/seed.php",
                "tests/browser/thread-view-fixture.php"
              ],
              "reason": "",
              "id": "S-LOCAL-FIXTURE",
              "environments": [
                "desktop_emulation",
                "automated_test"
              ],
              "build_ids": [
                "B-LOCAL"
              ]
            },
            "additional": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "Assessment browser steps added no response interception; offline is scenario state."
              ],
              "reason": ""
            }
          },
          "covers": [
            {
              "finding_id": "F-01",
              "scope_id": "F-01-ORIGIN"
            }
          ]
        },
        {
          "id": "C-ASSETS",
          "module": 7,
          "required": false,
          "scenario": "Read-only asset manifest qualification",
          "expected": "Retained asset hashes match manifest",
          "actual": "Exit1: retained asset digest mismatch/assets/dist/app-c89b55420ad2231f.js. No rebuild or repair attempted.",
          "status": "fail",
          "evidence": [
            "original-audit/asset-check.txt"
          ],
          "reason": "Historical original-audit check; continuation checks have new IDs.",
          "environment": "automated_test",
          "driver": "agent",
          "evidence_type": "automated",
          "level": "mechanism",
          "behavior": "interaction",
          "capability_id": "CAP-CLI",
          "build_id": "B-LOCAL",
          "configuration": {
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop",
            "os": "Windows",
            "viewport": "390x844"
          },
          "conditions": {
            "build_id": "B-LOCAL",
            "browser": "Chromium155.0.0.0",
            "device": "Windows desktop browser with explicit viewport override",
            "os": "Windows",
            "viewport": "390x844",
            "state": "Read-only asset manifest qualification",
            "account_data": "Seeded Elladan member; topic 30",
            "setup": "Real local PHP/MariaDB; fixture seed; Markdown composer; test integrations from S-LOCAL-FIXTURE",
            "steps": [
              "Read-only asset manifest qualification"
            ],
            "timing": "As recorded in the independent assessment; no general timing/performance claim",
            "substitutions": "S-LOCAL-FIXTURE; no request interception added. Offline sequence changes network scenario state."
          },
          "depends_on": [],
          "substitutions": {
            "build": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "No source code changed or stubbed by this audit; context.json and working-source-fingerprints.json"
              ],
              "reason": ""
            },
            "setup": {
              "status": "known",
              "items": [
                {
                  "boundary": "account_data",
                  "description": "Isolated seeded local MariaDB instead of live community data",
                  "behavior": "replaced",
                  "configured_response": "tests/browser/seed.php plus thread-view-fixture.php; topic 30 ratified-decisions",
                  "real_components": "Real PHP kernel, templates, services and MariaDB",
                  "untested": "Production data distribution and deployment"
                },
                {
                  "boundary": "mail_delivery",
                  "description": "ArrayMailer for test safety",
                  "behavior": "replaced",
                  "configured_response": "In-memory mail transport",
                  "real_components": "App notification and write logic",
                  "untested": "External email delivery"
                },
                {
                  "boundary": "ai_generation",
                  "description": "Dummy provider credential and seeded Living Brief",
                  "behavior": "replaced",
                  "configured_response": "Existing fixture summary; no provider generation invoked",
                  "real_components": "Rendered Living Brief and source navigation",
                  "untested": "Real AI provider and generation latency"
                }
              ],
              "unknown_boundaries": [],
              "evidence": [
                "original-audit/context.json",
                "tests/browser/seed.php",
                "tests/browser/thread-view-fixture.php"
              ],
              "reason": "",
              "id": "S-LOCAL-FIXTURE",
              "environments": [
                "desktop_emulation",
                "automated_test"
              ],
              "build_ids": [
                "B-LOCAL"
              ]
            },
            "additional": {
              "status": "none_checked",
              "items": [],
              "unknown_boundaries": [],
              "evidence": [
                "Assessment browser steps added no response interception; offline is scenario state."
              ],
              "reason": ""
            }
          },
          "covers": []
        }
      ],
      "reason": "Original-build failures are retained here and in original-audit/run.json; new-build checks have new IDs."
    },
    {
      "event": "user_authorized_commit_push",
      "description": "Finish remaining checks, then commit/push only thread-view fixes; preserve unrelated edits."
    }
  ]
}
```
