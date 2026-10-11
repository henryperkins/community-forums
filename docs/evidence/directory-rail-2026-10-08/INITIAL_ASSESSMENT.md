# Directory rail — initial independent layout assessment

This assessment uses source inspection and rendered Chromium evidence on application build `e26e139ee9899a8f`. It uses no detector output or score. The app ran on owned port 8484 against `retroboards_e2e_directory_rail_20261008`, with isolated temporary stores. The fixture includes a personal Reading list folder and a Saved feeds group so the navigation hierarchy is assessed under realistic rail density.

## What the current layout shows

The desktop rail starts with personal folders, then saved feeds, category boards and the presence footer. The home pane links remain in a separate 61px shared row. Boards is also a topbar route; Notifications is also available through the bell. The home row mixes a place directory, topic taxonomy, account notification history and social relationships at equal visual weight.

At 393px, Boards / Tags / Notifications / Connections fit at the current 13px secondary-label size, but they spend the full band ahead of a separate Connections heading. At 320px, the measured link strip runs from x=16 to x=288.8 while the creation button begins at x=250: **the link strip and creation overlap by 38.8px**. Document overflow detection remains quiet, which would miss this defect. The screenshots and bounding boxes establish it directly.

The phone drawer already provides 44px board rows, a 44px close cross, a contained scroll region, main-page inertness, focus to its close control and restored opener focus on Escape. The rail is 322px wide at a 393px viewport and approximately 275px wide at 320px. The first useful stop after Close currently goes to a personal folder's board. The existing navigation family is suitable for the new global group.

## Recommended final structure

1. Add a distinct **Explore** group immediately after the drawer Close control and before personal folders, saved feeds and ordinary board categories. Its fixed order should be Boards, Tags, Connections. Use the existing rail row treatment and a 44px target floor, with one quiet boundary below the group. This establishes broad destinations before personal shortcuts and specific boards.
2. Give exactly the resolved home pane one `aria-current="page"` link. A disabled, invalid or legacy pane must use the server's normalized state. Non-home routes must not inherit a stale query parameter's current state. Keep feature-gated Tags and Connections links absent when their subsystem is unavailable.
3. Remove Notifications from this directory group and retain the bell as its entry. The existing notification surface and old links need their normal route behavior; moving navigation does not require deleting that surface.
4. For authenticated Tags and Connections, put their existing heading in the shared creation row. The Connections profile identity should appear once, remain linked, and wrap naturally. Give the leading heading and identity `min-width: 0` / `overflow-wrap: anywhere` so a maximum-length username cannot intrude into the plus control's 44px allocation. Keep the row's height driven by content rather than clipping large text. Preserve the Boards directory hero and use a quiet Boards context in the shared row.
5. Guests should keep meaningful content headings and usable directory navigation without an empty creation band. No-JS phone users should continue to get the bounded rail above the content rather than a script-dependent drawer.
6. On Compose, new directory links stay links; existing board destinations stay real GET buttons with their current selected state and disabled-board behavior. Keep directory navigation and compose-target selection as separate intents in the same rail.

This retains the parchment, serif and quiet selected-state vocabulary while removing the crowded repeated navigation band. The three 44px global rows add vertical content to the drawer, so the final check should verify scrolling to the category/presence footer and keyboard containment, not compress those rows to recover space.

## Evidence provenance / limits

`chromium-initial.json` contains Boards, Tags and Connections at 1440, 393 and 320px, plus a 320px text-expansion probe and DOM-order information. Initial `*-drawer.png` captures sampled the entrance transition before it settled; they are explicitly superseded for drawer geometry by `chromium-initial-drawer-settled.json` and the two `*-drawer-settled.png` captures. This was a timing repair in the evidence helper, not an application defect or redesign iteration.

The initial assessment is Linux Chromium only. The final bounded confirmation should use Chromium and WebKit, native/no-JS routes, normalized current states, feature gates, phone focus, extreme names/text, overlays and retained board compose picking. Root font scaling exercises text expansion, not a physical browser zoom test. No physical Safari/iPhone, Firefox or assistive-technology coverage is claimed.
