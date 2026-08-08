# Slice 2 console chrome design QA

The runtime evidence copies the Imladris console anatomy: the compact identity row, full-bleed horizontal area tier, area heading, underline tab strip, and centered content pane. The tier order is Overview, Moderation, Content, People, Members, Appearance, Notifications, Integrations, Packages, Features, Settings. On narrow screens the same tier remains visible and scrolls horizontally; there is no alternate drawer.

Sanctioned deviations are limited to the adopted production decisions and constraints:

- `feature-added`: Moderation is the eleventh production area, inserted at tier index 1 by ADR 0024.
- `feature-changed`: the identity row retains search, notifications, the signed-in monogram, and sign-out in the design's right-cluster idiom.
- `constraint`: navigation remains server-rendered and usable without JavaScript; authorization, CSRF, progressive enhancement, and the strict external-only CSP remain intact.
- `constraint`: disabled flag-gated destinations remain non-interactive and expose their existing availability note to assistive technology.

The light, twilight, system-dark accessibility, and no-JavaScript captures in this directory are production runtime evidence. No deterministic design-reference renderer was available for a trustworthy pixel overlay, so this note does not claim pixel-equivalence proof.
