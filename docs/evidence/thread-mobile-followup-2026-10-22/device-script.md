# Physical device checks

**NOT READY — no fixed phone build or device access is provisioned.**

Nine of ten entries reserved; four protect applied device-dependent fixes (cap five). Original audit plans/results remain in the ledger.

Build B-PHONE-FIX must match B-FIX manifest 1c24a3765cdd43f9 and every recorded source/asset digest. Record actual device/OS/browser and data; stop on mismatches. No production writes.

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

