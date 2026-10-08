---
target: Inbox controls, bulk actions and hidden menus; two-choice snooze
total_score: 25
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 2
target_identity: "file:/home/ubuntu/community-forums/templates/inbox.php"
target_fingerprint: "sha256:a21a29cbd032c32df02be91e1341736bff3f75f6cc7af22b0681f77ef543282f"
target_path: /home/ubuntu/community-forums/templates/inbox.php
timestamp: 2026-10-07T22-07-10Z
slug: templates-inbox-php
---
Method: dual-agent (A: `/root/critique_design` · B: `/root/critique_evidence`)

My previous pass consolidated the row and missed the inconsistent control sizing and styling. Your screenshots expose those gaps clearly.

**Design specificity:** Imladris has a clear identity: literary serif type, parchment/twilight surfaces, evergreen and restrained gold. The everyday controls lack that same coherence. Keep the established visual language and give disclosures, buttons and menus one consistent grammar.

**Design health: 25/40 — significant focused improvements needed.** These are design judgments, rather than a functional certification.

| Heuristic | Score /4 | Main finding |
| --- | ---: | --- |
| System status | 3 | Counts and selection are visible; snooze feedback omits the return condition. |
| Real-world language | 3 | Familiar actions; unnecessary date comparisons. |
| Control and freedom | 2 | No manual-only snooze; reversal needs clearer wording. |
| Consistency | 2 | Different target sizes, label layouts and menu treatments. |
| Error prevention | 2 | Small adjacent actions and conflicting snooze defaults. |
| Recognition | 3 | Current choices visible; mobile dropdown arrows disappear. |
| Efficiency | 3 | Bulk actions and shortcuts exist, with different snooze behavior. |
| Aesthetic restraint | 2 | Stretched Scope, cramped Sort and irregular bulk wrapping. |
| Error recovery | 2 | Source review shows generic recovery without restored selection. |
| Help | 3 | Useful explanations; oversized nested panel and Monday-only shortcut copy. |
| **Total** | **25/40** | **Acceptable foundation; substantial control refinement needed.** |

**What works:** The visual identity is distinctive, Show and Sort remain independent with useful counts, and native disclosures/forms provide a sound interaction foundation.

**Priority issues**

1. **[P1] Snooze does not express the two requested intentions.** Row actions offer four dates, while bulk actions and `#` use Monday. Use one **Snooze** group with exactly **“Til tomorrow”** and **“Til I turn it back on”**, plus **“Turn back on”** for recovery. Apply the contract to topic tools, rows, bulk actions, shortcuts and Help. Manual snooze needs persistent state: today the server supports dates, and an unknown choice silently clears snooze. Update validation, visibility/counts and recovery together. Suggested commands: **clarify + harden**.

2. **[P1] Triage actions are undersized.** The main toolbar is 44px high; mobile row triggers are 34px, row-menu actions 32px and bulk buttons 30px, with 11.2px bulk labels. These differences make one-handed triage harder and look inconsistent. Give actions a 44px touch floor and a shared readable label style. Suggested command: **adapt**.

3. **[P2] Space allocation makes the controls look poorly fitted.** At 393px, Scope is 150px wide and left-aligned; Sort is 48px wide and centred over two lines. Both are 44px high, so the problem is proportion, inset and text hierarchy. Reserve useful space for Sort, match the label/value rhythm, and retain disclosure cues. The browser pass also found only 121px for topic text at 320px; move secondary row actions out of that narrow text column. Suggested command: **layout**.

4. **[P2] Menus use competing visual rules.** Toolbar panels use 280px width, 12px corners and 8px insets; row menus use 196px width, 7px corners, 6px insets and smaller text. Expanded Help grows the overflow panel from 106px to about 352px. Use one menu family with consistent type, padding, rows, corners and anchor spacing; size it for its content. Separate Help visually from the page action and remove repeated “Snooze ·” prefixes. Suggested commands: **polish + typeset**.

5. **[P2] Selection and list borders create awkward silhouettes.** Selection introduces an 86px, two-line command box on mobile; at 393px, Snooze sits alone on its second line. Rounded row corners bend their bottom rules upward into incomplete card edges. Use a deliberate contextual action layout and straight inset separators independent of the selection wash. Keep the bulk boundary quiet in both themes. Suggested command: **polish**.

**Cognitive load and emotional journey:** Three checklist failures—hierarchy, one decision at a time and minimal choices—produce moderate unnecessary load. The row menu presents five actions. The calm initial queue becomes busier on selection, then asks the member to compare nearly identical dates. Two clear return conditions and specific completion feedback would make postponement feel resolved.

**Persona red flags:** Casey, a mobile member, faces small adjacent targets. Alex, a habitual triager, gets different snooze behavior from rows, bulk actions and `#`. Jordan, a first-time member, sees abbreviated controls without arrows and Help explaining a hidden Show label.

**Minor observations:** “Select all on screen” selects all loaded rows on the page, including those below the viewport; use **“Select all on this page.”** Scope's final group is below its initial scroll window, with little continuation cue. A small right-edge clipping observation at 320px warrants checking alongside menu spacing. Move the JavaScript implementation explanation out of the ordinary reading placeholder.

**Evidence and limits:** Both markup detector scans returned zero findings; their PHP regex mode does not verify the rendered CSS cascade. Independent Chromium inspections covered desktop and phones, light/dark, selection and open menus, alongside your actual photos. The injected overlay was blocked by the existing CSP; no user-visible overlay was established. This critique did not execute topic actions, no-JS flows, enlarged text, assistive technology or a new native-Safari pass. Application source remains unchanged.

**Recommended repair direction:** Unify toolbar and menu geometry, simplify selection actions, implement the shared two-choice snooze contract, then finish with polish. Preserve the single physical header row and Imladris identity.
