# Physical-device handoff — NOT READY

These ten checks are reserved and have not been executed. They target the integrated local candidate in [candidate-integrated.json](candidate-integrated.json). No phone-reachable instance, exact device configuration or safe test-account setup is provisioned. The former loopback server has been stopped.

Before any check, record the actual build/source and asset hashes, URL, device model, OS/browser version, browser toolbar layout, text scaling, account role, feature flags, network and backend wiring. Confirm that writes affect disposable fixtures. Record setup substitutions separately; do not assume desktop interceptions, CSSOM scaling or safe-area behavior apply on the phone.

The two menu entries reserve the device-dependent F-04 regression. The other entries cover recovery, keyboard, assistive technology and performance within the total ten-entry budget.

## 1. Account menu, native pointer-to-keyboard entry and Create topic target

Check: `C-MOBILE-PHONE-MENU-IOS`. **NOT READY / NOT RUN.**

Configuration: Safari; iPhone; exact model/OS/browser to be supplied.

1. Record model, OS/browser versions, browser toolbar layout and actual CSS viewport. Use the disposable moderator and then admin account.
2. Open Account by tapping the avatar in portrait and short landscape near 844x390; expand/collapse browser bars, then repeat at enlarged text. Scroll the menu to Log out and verify its full label/hit area remains reachable.
3. With an attached keyboard, tap the avatar then Tab through every action, reverse direction and dismiss. Repeat the native disclosure with JavaScript disabled; reopen it through the summary to close it.
4. At 320/360/390/430 CSS widths where available, tap Create topic with a filled disposable form; record target dimensions, neighboring actions and no page overflow.

**Expected:** All role actions remain reachable; internal scrolling exposes Log out; native Tab enters and leaves the menu; Create topic retains at least a 44x44 CSS-pixel target without overlap.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 2. Account menu, native pointer-to-keyboard entry and Create topic target

Check: `C-MOBILE-PHONE-MENU-ANDROID`. **NOT READY / NOT RUN.**

Configuration: Chrome Android; Android phone; exact model/OS/browser to be supplied.

1. Record model, OS/browser versions, browser toolbar layout and actual CSS viewport. Use the disposable moderator and then admin account.
2. Open Account by tapping the avatar in portrait and short landscape near 844x390; expand/collapse browser bars, then repeat at enlarged text. Scroll the menu to Log out and verify its full label/hit area remains reachable.
3. With an attached keyboard, tap the avatar then Tab through every action, reverse direction and dismiss. Repeat the native disclosure with JavaScript disabled; reopen it through the summary to close it.
4. At 320/360/390/430 CSS widths where available, tap Create topic with a filled disposable form; record target dimensions, neighboring actions and no page overflow.

**Expected:** All role actions remain reachable; internal scrolling exposes Log out; native Tab enters and leaves the menu; Create topic retains at least a 44x44 CSS-pixel target without overlap.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 3. Complete draft recovery, explicit comparison and Resume

Check: `C-MOBILE-PHONE-RECOVERY-IOS`. **NOT READY / NOT RUN.**

Configuration: Safari; iPhone; exact model/OS/browser to be supplied.

1. In source and rich modes, save a topic with title, body, board and Anonymous; reload, navigate away/back and background/reopen the tab. Record each value independently.
2. From a second client create equal, title-only, anonymity-only, combined and body conflicts. Inspect both named copies, then exercise Keep local, Keep server and Save local on separate fixtures.
3. Resume browser and server drafts into topics/replies/messages, including server Resume without JavaScript; verify destination and every permitted saved field.
4. Repeat with the software keyboard open; keep newer same-body wrapper changes in the second client.

**Expected:** All permitted values survive and each explicit choice uses the displayed copy and current edits.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 4. Complete draft recovery, explicit comparison and Resume

Check: `C-MOBILE-PHONE-RECOVERY-ANDROID`. **NOT READY / NOT RUN.**

Configuration: Chrome Android; Android phone; exact model/OS/browser to be supplied.

1. In source and rich modes, save a topic with title, body, board and Anonymous; reload, navigate away/back and background/reopen the tab. Record each value independently.
2. From a second client create equal, title-only, anonymity-only, combined and body conflicts. Inspect both named copies, then exercise Keep local, Keep server and Save local on separate fixtures.
3. Resume browser and server drafts into topics/replies/messages, including server Resume without JavaScript; verify destination and every permitted saved field.
4. Repeat with the software keyboard open; keep newer same-body wrapper changes in the second client.

**Expected:** All permitted values survive and each explicit choice uses the displayed copy and current edits.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 5. Unchanged resumed send and uncertain-response recovery

Check: `C-MOBILE-PHONE-COMPLETION-IOS`. **NOT READY / NOT RUN.**

Configuration: Safari; iPhone; exact model/OS/browser to be supplied.

1. Save an unchanged draft in each composer context; use local and server Resume in source/rich modes. Send once and check one created result, an empty completed draft and no conflict on revisit.
2. After Resume, change the saved copy from a second client, including an identical body at a newer revision. Submit the first client and verify that the newer copy survives.
3. On a disposable fixture only, interrupt the network after submission. Record whether a server commit occurred before retrying. Restore the connection, reload or return with native Back, and verify retained text and a usable Send control.
4. If completion remains uncertain, retry the unchanged content once and verify the original result is replayed with no duplicate. Record native failure/history states separately; do not infer them from the local route-abort test.

**Expected:** An exact completed copy clears; unrelated/newer copies survive; unconfirmed drafts recover and unchanged retry creates no duplicate.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 6. Unchanged resumed send and uncertain-response recovery

Check: `C-MOBILE-PHONE-COMPLETION-ANDROID`. **NOT READY / NOT RUN.**

Configuration: Chrome Android; Android phone; exact model/OS/browser to be supplied.

1. Save an unchanged draft in each composer context; use local and server Resume in source/rich modes. Send once and check one created result, an empty completed draft and no conflict on revisit.
2. After Resume, change the saved copy from a second client, including an identical body at a newer revision. Submit the first client and verify that the newer copy survives.
3. On a disposable fixture only, interrupt the network after submission. Record whether a server commit occurred before retrying. Restore the connection, reload or return with native Back, and verify retained text and a usable Send control.
4. If completion remains uncertain, retry the unchanged content once and verify the original result is replayed with no duplicate. Record native failure/history states separately; do not infer them from the local route-abort test.

**Expected:** An exact completed copy clears; unrelated/newer copies survive; unconfirmed drafts recover and unchanged retry creates no duplicate.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 7. Software keyboard, form zoom and safe-area states

Check: `C-MOBILE-PHONE-KEYBOARD-IOS`. **NOT READY / NOT RUN.**

Configuration: Safari; iPhone; exact model/OS/browser to be supplied.

1. Record Safari tab layout, text scale and viewport; open the reply keyboard in portrait and landscape.
2. Exercise source/rich input, selection, autofill where applicable, toolbar expansion and validation errors. Expand/collapse browser bars and dismiss/reopen the keyboard.
3. Verify focused fields, error text, caret, Reply and Minimize remain reachable without horizontal overflow or loss of text.

**Expected:** Keyboard and browser bars do not obscure required actions or lose draft state.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 8. VoiceOver member journey

Check: `C-MOBILE-PHONE-ASSISTIVE-IOS`. **NOT READY / NOT RUN.**

Configuration: Safari; iPhone; exact model/OS/browser to be supplied.

1. With VoiceOver/TalkBack enabled, enter through Boards, read a topic and locate Reply, Drafts and the account menu.
2. Read the named local/server draft copies and their differences; make a deliberate recovery choice on a disposable draft.
3. Use native focus navigation and touch exploration through the scrolled account menu and composer validation. Record names, roles, order, state announcements and retained focus.

**Expected:** Controls and comparison regions have usable names/order/state and recovery retains user input.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 9. TalkBack member journey

Check: `C-MOBILE-PHONE-ASSISTIVE-ANDROID`. **NOT READY / NOT RUN.**

Configuration: Chrome Android; Android phone; exact model/OS/browser to be supplied.

1. With VoiceOver/TalkBack enabled, enter through Boards, read a topic and locate Reply, Drafts and the account menu.
2. Read the named local/server draft copies and their differences; make a deliberate recovery choice on a disposable draft.
3. Use native focus navigation and touch exploration through the scrolled account menu and composer validation. Record names, roles, order, state announcements and retained focus.

**Expected:** Controls and comparison regions have usable names/order/state and recovery retains user input.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

## 10. Cold reading and editor readiness on the intended compressed release profile

Check: `C-MOBILE-PHONE-PERFORMANCE-ANDROID`. **NOT READY / NOT RUN.**

Configuration: Chrome Android; Android phone; exact model/OS/browser to be supplied.

1. Record phone/OS/Chrome, source/asset hashes, release-like edge/backend URL, cache state and network. Confirm CSS Content-Encoding and response sizes.
2. Use Chrome remote debugging Performance recording on the physical Android phone. Perform three cold navigations with a specified repeatable connection; capture LCP, layout shifts, requests/TTFB and rich-editor-ready timing separately.
3. Record a real interaction trace after loading; do not treat click duration as field INP. Compare the reading and composing timings with the local transport experiment and retain each trace.

**Expected:** Reading meets the agreed budget on the intended release profile; any delay is attributed to a measured request/render step. Editor startup is reported separately.

**Record:** observed result, exact setup, screenshots/trace, timestamps, each tested state, discrepancies, and whether this check passed, failed or could not run. Do not infer untested configurations.

**Measurement tool:** Chrome DevTools remote Performance panel and Network panel on the physical Android device; three cold navigation traces, exported trace/HAR with secrets removed, LCP/CLS/TTFB/encoded sizes and editor-ready marks.

## Beyond this pass

- Additional Safari tab layouts and physical browser-chrome combinations beyond the selected configuration.
- Separate full Android software-keyboard/autofill matrix; related keyboard states remain in the recovery entries.
- Native Android System Back/edge-gesture matrix beyond the recovery path.
- Field Core Web Vitals populations and release monitoring are unknown, not a phone-script result.

Desktop emulation remains proxy evidence. A schema-valid handoff is not a physical-device result.
