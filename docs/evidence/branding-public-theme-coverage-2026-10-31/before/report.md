**Admin settings to public theme propagation — 2026-10-31**

Saved branding settings reach the real public application, but the colour
controls do not cover every public component state. The latest approved dashboard
fixes remain uncommitted in fix/admin-branding-recovery and have not been deployed
by this work. The deployed public CSS was fetched read-only and byte-matched the
main checkout's app-style--jg7dl7V.css; it differs from the candidate's rebuilt CSS.

| Control / behavior | Evidence and result |
|---|---|
| Save community name and primary/accent | A real local admin form submission (422 contrast review, then acknowledged 303) changed the name in the guest shell and the primary colour in the public Log in button after reload. |
| Brand stylesheet identity | A fresh guest response gained a versioned /brand.css URL containing the newly generated brand version; this is a functioning dynamic-settings path, independent of a code rebuild. |
| Open pages | The already-open guest page kept its original colours until reload. This is current behavior, not a cache-invalidation failure after reload. |
| Default mode | Changing signed-out default to Dark stamped the guest document Dark. The signed-in member remained System on a light device, as specified. |
| Colour coverage | Ordinary buttons and thread-title hover followed blue. Active header/rail navigation, the rail's gold selection border, and an opened feed menu's selected option retained separate default brand/gold tokens. |
| Production snapshot | The public site showed WordPress AI Forums, no operator-brand stylesheet link, no favicon override, and /brand.css had no root colour declarations. No authenticated production setting was changed or inspected. |

The prior five dashboard fixes address form safety, default rendering, mobile
navigation and guidance. This follow-up tested the next boundary: saving through
the actual admin form and then reading fresh guest/member views. It found no
failure of the save or next-request stylesheet loading; it exposed incomplete
component token coverage.

**Confirmed coverage gap (Code + Render).** In the isolated forum, set Primary
to #005fcc and Accent to #a33300 with Classic/System, then acknowledge contrast
and save. The sample button became rgb(0,95,204), and the sample marker became
rgb(163,51,0). A fresh public Log in button also became blue. Active top navigation
remained #edf3ed with #24402f ink. In a fresh member session, opening Feed view
showed the selected Following entry still #2e4a3a. The public board-rail selection
kept the #c29a44 gold border rather than the configured accent. The feed menu was
explicitly opened for the final capture, avoiding treating a closed-menu computed
style as rendered proof.

Cause: src/Support/BrandPalette.php:91 maps primary to --accent and its ink, and
secondary colour to --accent-2 and its ink. It does not resolve the --brand,
--brand-hover, --brand-press, --brand-subtle, --on-brand-subtle, or gold family.
Runtime components consume those other roles: resources/imladris/components.css
lines 493, 497, 555, 565, 567; app.css's inbox-tab active styles also use --brand.
The normal render order in templates/layout.php correctly loads package and
operator CSS after the application styles. The missing coverage is which semantic
roles receive resolved values and which roles components consume.

The current sample is accurate for its own button/link/marker/field, but it does
not demonstrate header navigation, rail selections, menu selection or unread
badges. A colour review on normal content surfaces also does not certify all
those specialized states. This warrants expanding implementation and proof for
existing controls.

**Bounded recommended update, in order:**

1. Define primary brand roles for actions, selected states, hover, press, soft
   backgrounds and readable foregrounds in the shared resolved palette.
2. Define which accent highlights/indicators follow the secondary colour and
   bind those components to semantic roles. Preserve independent warning,
   success and moderation meanings rather than replacing every green/gold token.
3. Include actual public components in the preview: navigation, rail, menu
   selection, unread badge, primary button and a content/link sample, with both
   appearance modes and active-package precedence represented.
4. Extend regression/contrast coverage to admin save → fresh guest/member
   views across those actual components, including hover/focus/selected states,
   both modes, package overlays and no JavaScript.
5. State when and where a choice applies: next page load, visitor default versus
   member preference, and built-in preset versus active package palette.

[Guest colour split](guest-blue-primary-green-nav-gold-rail.png) and
[opened feed menu](open-feed-menu-brand-colour-gap.png) are the representative
captures. Exact computed values, the no-reload/reload comparison and public asset
identity are retained in observations.json.

This was a source review plus browser work on explicitly isolated fixtures and
read-only production HTTP checks. No source, deployment or production settings
were changed. The temporary browser/server/database were removed after evidence
capture. The evidence is desktop Chromium, not physical-device or screen-reader
qualification.
