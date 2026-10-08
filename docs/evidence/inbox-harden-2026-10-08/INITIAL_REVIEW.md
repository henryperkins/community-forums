# Inbox hardening: initial inspection

Inspected application build `9cd60f8b2665c07a` against the private `retroboards_e2e_inbox_harden_20261008` database on owned port 8484. The main developer server and tunnel were left running. The PHP fixture reset refuses other database names.

## Confirmed issues

- A preview HTTP 500, rejected network request, or malformed HTTP 200 response sends the browser to the canonical topic, losing the queue despite a transient enhancement failure. Both engines reproduce this. Preserve the queue and provide retry / a deliberate canonical link; keep intentional authentication and access handling.
- A held preview fetch remains `aria-busy=true` with the unchanged “Choose a topic” content. Observed for 1.5 seconds; source inspection confirms no deadline or visible loading/recovery path.
- Reading the sole Unread topic and returning leaves a master checkbox, an empty list and “Showing 0 of 0 topics.” The server renders a useful caught-up state for the same data. Both engines reproduce this. Preserve meaningful recovery if a page drains while later topics remain.
- At 320×180 with 200% root text sizing, the creation panel measures 304×186 at y=8, extending past the viewport. Its max-height is `none` and overflow is `visible`, unlike the Inbox panels. Both engines reproduce this.
- Chromium forced-colors maps both switch track and thumb backgrounds to white, and the thumb has zero border width. The thumb becomes invisible even though its semantic state remains correct. The reveal animation also briefly permits underlying content to show through. WebKit advertises the media query but does not apply Chromium's forced palette on this Linux runner; its colors cannot qualify Windows high-contrast rendering.

## Working behavior / limits

- Latest-wins preview request reconciliation survives a delayed first response in both engines. Focus goes to the latest preview heading.
- The seeded long Arabic/Hebrew/CJK/emoji topic title wraps within its row. Existing native forms, visible canonical links and the documented two hiding intents remain intact.
- Setting the entire document to `dir=rtl` causes horizontal overflow (1309/1319px document scroll width at 320px) and a blank viewport after automatic row scrolling. Full localized shell RTL is a generic hardening proposal, not an existing product commitment. Mixed-direction member content should remain safe without claiming the whole shell is localized.
- At the intentionally extreme 180px height, opening the combined view disclosure was observed to close during scroll/auto-placement; this needs attribution before being called a distinct source regression. Help remains within the short viewport.

## Evidence provenance

`chromium-initial.json` retains nine initial cases, including three harness failures. The strict preview heading selector also matched a nested composer emoji heading; the mixed-script fixture was initially below the first page; and the short-view positioning wait assumed a panel would remain open. `chromium-initial-affected.json` repairs only those three measurements. `webkit-initial.json` uses the corrected harness and a fixture with four starred sample topics and one unread topic. The fixture's onboarding flag was completed to remove the unrelated product-tour overlay. Earlier valid 500/network/hung/empty-state evidence is retained.

Rendered checks use Linux Chromium and WebKit, desktop and phone dimensions. Root font scaling exercises text expansion but is not a physical browser zoom run. There is no physical iPhone/Safari, Windows forced-colors, Firefox or assistive-technology qualification. UI inspection was batched; subsequent confirmation should be limited to the root's correction batch.
