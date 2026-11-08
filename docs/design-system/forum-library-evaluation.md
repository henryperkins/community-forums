# Forum UI library evaluation

Reviewed: **2026-11-07** for ADR 0056; re-reviewed the same day for ADR 0057, which corrected several facts and added the candidates and platform primitives below. Scope: the conventional, accessible Forum interface that replaced the deprecated Imladris presentation.

## Decision

Keep PHP server rendering and progressive enhancement. Build the interface from semantic HTML, a compact set of owned CSS tokens and presentation classes, and the existing JavaScript enhancement boundary. **Do not add a global UI runtime.**

Reach for **Baseline platform primitives** before a component library: `<dialog>`, the Popover API, invoker commands (`command`/`commandfor`) and exclusive `<details name>` now cover most of what libraries were adopted for, work with server-rendered markup and need no inline script. Use USWDS, GOV.UK and WAI patterns as design references without installing their packages or copying their identities.

ADR 0056 named a selective Bootstrap 5.3 Sass subset as the fallback. That is **withdrawn**: Bootstrap 6.0.0-alpha.1 shipped on 2026-10-08 with a rewritten Sass API, class syntax and module-only JavaScript, and v5 is to receive 5.4 before an end-of-life date is set. Re-evaluate a library only for a demonstrated maintenance or interaction problem, against the criteria in this document.

This is a research and architecture decision. No candidate package was installed into the application; package contents were inspected from published tarballs where noted. Package sizes, rendered contrast inside this forum, assistive technology behaviour and production performance were not benchmarked.

## Repository constraints

The repository uses PHP 8.2+ server rendering, HTML forms and progressively enhanced interaction. The checked manifests do not describe a React application; Milkdown 7.21.2 supplies the optional rich composer rather than the application shell. See [AGENTS.md](../../AGENTS.md), [composer.json](../../composer.json) and [package.json](../../package.json).

The Content Security Policy is `script-src 'self'; style-src 'self'` with no `'unsafe-inline'`, so inline `<script>` and `<style>` elements, `style="…"` attributes and inline event handlers (`onclick="…"`) are blocked. JavaScript CSSOM property writes are allowed. Self-hosted assets only. A library name alone does not establish compatibility: each component's server output, injected styles, dynamic loading and documented examples need checking.

Reading, navigation, search, sign-in and the plain composer must remain usable with JavaScript unavailable. Accessibility depends on the assembled page and task flow; a library's claims cannot establish conformance for the application.

## Corrections to the first review

| Earlier statement | Finding on re-review |
| --- | --- |
| Bootstrap 5.3.8 as the current documentation version | Still npm `latest`, but getbootstrap.com now leads with **6.0.0-alpha.1** (2026-10-08): Chrome/Edge 130, Firefox 132 and Safari 18 minimums; Sass modules (`@use`/`@forward`); CSS-variable customisation with `@layer`, `oklch()`, `color-mix()` and `light-dark()`; a native-`<details>` accordion and `<dialog>`; ES-module-only JavaScript; and breakpoint-prefixed classes. Bootstrap warns the alpha can change meaningfully before beta. [1–3] |
| GOV.UK Frontend fits a strict CSP | Its page template uses an inline script to add `js-enabled`, which this CSP blocks; GOV.UK recommends a hash or a nonce. Current version 6.6.0 (2026-10-15). [9–11] |
| GOV.UK browser-support page documents operation without JavaScript | That page does not say so. The GOV.UK Service Manual's progressive-enhancement guidance does. [12] |
| Base UI 1.9.0 | Now published as `@base-ui/react`; the old package name is deprecated. Its CSP helper does not cover inline style attributes. [19] |

Confirmed unchanged: Pico CSS 2.1.1 is the final release and its repository was archived on 2026-10-04; Shoelace is sunset (repository archived 2026-05-14) in favour of Web Awesome; USWDS is at 3.14.0; Web Awesome 3.14.0 says progressive enhancement is not currently a goal and its server rendering relies on Lit SSR, which Lit labels pre-release. [4–8, 13–15]

## Candidate comparison

Versions and maintenance status were observed from official documentation and the npm registry on 2026-11-07. Contrast figures for library defaults are WCAG 2 calculations from shipped colour tokens, not rendered measurements.

| Candidate | Fit for PHP, strict CSP and no-JS | Accessibility and operational notes | Decision |
| --- | --- | --- | --- |
| **Owned HTML and CSS + platform primitives** | Direct fit for templates and forms; no runtime, hydration or inline script. | The project owns semantics, focus, contrast and regression evidence; `forum-refinement.spec.ts` pins the typographic contract. | **Use.** |
| Bootstrap 5.3 / 6 alpha | CSS works with server-rendered markup; JavaScript plugins optional. v6 moves to Sass modules and ES modules. | Bootstrap warns that some default colour combinations fail 4.5:1 text or 3:1 non-text contrast. Popper-backed controls need integration review. [1–3] | **Withdrawn as named fallback.** Revisit v6 after beta only for a demonstrated need. |
| USWDS 3.14.0 | Progressive enhancement and modular Sass fit this architecture. | Strong accessibility guidance and testing practice; federal patterns need adaptation. Public-domain code with third-party notices. [6–8] | **Adopt patterns, not the package.** |
| GOV.UK Frontend 6.6.0 | Server-rendered HTML fits, but the template's inline `js-enabled` script needs a CSP hash or nonce. Non-GOV.UK branding is supported from 6.3.0 with the generic header, replacement fonts, colours and brand assets. [9–11] | Strong service-design guidance; a library alone does not make a service accessible. | **Adopt interaction and content patterns.** Package adoption would need a CSP change. |
| Bulma 1.0.4 | "Bulma does not include any JavaScript"; pure CSS fits the CSP. | Its modal is a plain `<div>` rather than `<dialog>`; no reduced-motion or forced-colours rules. Infrequent releases (last 2025-04-19). [16] | **Not adopted.** No gap it fills. |
| daisyUI 5 | CSS component classes on Tailwind 4. Its modal examples use inline `onclick`, which the CSP blocks. | Checkbox- and link-based modals do not close with Escape or make the background inert; the drawer relies on a hidden checkbox. Some defaults calculate below AA (secondary button 3.05:1; a 12px error hint 2.87:1; dark primary link 3.40:1). [17] | **Not adopted.** |
| Basecoat UI 1.1.0 | shadcn-style components for plain HTML; mostly CSS, but interactive parts need JS and examples use inline handlers; requires Tailwind. Uses native `<dialog>` and `<details>`. [18] | Claims to follow accessibility practice; not tested here. | **Watch.** A useful reference for native-element patterns. |
| Open Props 1.7 | Pure custom-property tokens. | Tokens only; overlaps the forum's own token set. | **Not needed.** |
| Tailwind CSS 4.3 | Generates static CSS at build time; compatible with the CSP. | Utilities only; adds a build step and its own cascade layers. | **Not adopted.** Would replace, not simplify, the owned layer. |
| Simple.css 2.3.7 (classless) | Pure CSS. | Defaults calculate well above AA; no forced-colours rules; follows OS colour scheme only. | **Reference only.** |
| Primer CSS 22.3 (GitHub) | Pure CSS. | README states the project is in maintenance-only ("KTLO") mode. [20] | **Do not adopt.** |
| Web Awesome 3.14 | Custom elements; full behaviour needs JavaScript; SSR approximates layout only. [13–15] | Bounded use would need fallback, CSP, keyboard and AT evidence per component. | **Defer.** |
| Spectrum, Fluent UI and Carbon web components | Render nothing useful without JavaScript. Fluent applies themes from JS and its tooltip injects a `<style>` element where anchor positioning is missing; Carbon's date picker creates `<style>` elements and its install script sends telemetry unless disabled. [21–23] | Strong vendor accessibility programmes, but the integration model conflicts with the no-JS floor and CSP. | **Do not adopt.** |
| Shoelace, Pico CSS | Archived. [4, 5] | Maintenance would transfer to this project. | **Do not adopt.** |
| shadcn/ui, Radix, React Aria, Base UI | React components or primitives. | Good documented accessibility behaviour, but they introduce an application architecture this forum does not have. [19, 24–26] | **Defer** until a separately justified React feature exists. |

## Platform primitives

Baseline status from the web-features data set (3.45.0) as of 2026-11-07; spot checks against webstatus.dev agreed. "Newly available" means every core browser supports the feature; "widely available" follows 30 months later. [27]

| Feature | Baseline | Use in the forum |
| --- | --- | --- |
| `<dialog>` with `showModal()` | Widely available | Modal dialogs with an inert background, Escape and focus handling from the browser. `closedby` is not yet in Safari; Escape still closes a modal dialog. |
| Popover API (`popover`, `popovertarget`) | Newly available (2025-01) | Menus and disclosures that need light dismiss. Declarative invocation exposes `aria-expanded`/`aria-details` automatically. |
| Invoker commands (`command`, `commandfor`) | Newly available (2025-12) | Open dialogs and popovers without inline handlers, satisfying the CSP. Use `type="button"`; `show-modal` and `close` add no ARIA mappings. |
| Exclusive accordions (`<details name>`) | Newly available (2024-09) | One-open-at-a-time disclosure groups with no script. |
| `:user-invalid` | Widely available (2026-05) | Visual field errors after interaction; server-side validation stays authoritative. |
| `field-sizing: content` | Newly available (2026-06) | Auto-growing textareas with explicit minimum and maximum sizes. |
| `light-dark()`, `color-mix()`, `@layer` | Newly available (2024-05), widely available (2025-11), widely available | Token arithmetic and cascade ordering already used by the Forum layer; check contrast for any mixed or operator-derived colour. |
| `prefers-contrast`, `forced-colors` | Widely available | Contrast preferences and Windows high-contrast support; focus must not rely on `box-shadow`, which forced colours remove. |
| `@starting-style`, `scrollbar-gutter`, `text-wrap: balance` | Newly available | Optional polish; respect reduced motion. |
| Customisable `<select>`, CSS anchor positioning, `text-wrap: pretty`, `interpolate-size` | Limited | Progressive enhancement only, with a usable fallback. |

## CSP and no-JavaScript boundaries

1. **Static HTML and external CSS** fit the current policy when served from approved local paths. Confirm generated output rather than relying on examples.
2. **Inline handlers and inline scripts** in library examples (daisyUI, Basecoat, GOV.UK's template) are blocked. Prefer invoker commands or external modules; do not add `'unsafe-inline'`.
3. **Custom elements** can be emitted by PHP without their behaviour. Web Awesome's SSR is explicitly not a no-JavaScript mode, and several web-component systems inject `<style>` elements in some browsers. [14, 22, 23]
4. **React libraries:** server rendering does not establish policy compatibility. Base UI's `CSPProvider` covers injected elements but not server-rendered `style` attributes. [19]
5. **JavaScript styling:** MDN states that `element.style.x = …` is allowed under `style-src 'self'` while `setAttribute('style', …)` is blocked; its guidance on `style.cssText` is inconsistent, so test before relying on it. [28]

The acceptable fallback is a usable task, not visible placeholder markup.

## Adoption criteria

1. Start from native HTML and Baseline primitives; record why they are insufficient.
2. Name the recurring implementation problem, the code it replaces, its dependencies, licence, maintenance status and fallback.
3. Verify the exact component under the production CSP with JavaScript on and off, keyboard and focus behaviour, forced colours, both themes and operator branding.
4. Measure built and compressed output of the selected components and their transitive dependencies.
5. Record automated and manual browser evidence separately; library claims are not conformance evidence.

## Official sources

All sources reviewed on 2026-11-07. Recheck versions and maintenance status before any later adoption.

1. [Bootstrap](https://getbootstrap.com/) and [5.3 colour modes](https://getbootstrap.com/docs/5.3/customize/color-modes/)
2. [Bootstrap 5.3 accessibility](https://getbootstrap.com/docs/5.3/getting-started/accessibility/)
3. [Bootstrap 6 alpha announcement](https://blog.getbootstrap.com/2026/10/08/bootstrap-6-alpha/)
4. [Pico CSS maintenance notice](https://picocss.com/docs/maintenance) and [repository](https://github.com/picocss/pico)
5. [Shoelace](https://shoelace.style/) and [repository](https://github.com/shoelace-style/shoelace)
6. [USWDS](https://designsystem.digital.gov/) and [accessibility](https://designsystem.digital.gov/documentation/accessibility/)
7. [USWDS component packages](https://designsystem.digital.gov/components/packages/)
8. [USWDS code and licensing guidelines](https://designsystem.digital.gov/documentation/code-guidelines/)
9. [Using GOV.UK Frontend without GOV.UK branding](https://frontend.design-system.service.gov.uk/using-govuk-frontend-without-govuk-branding/)
10. [GOV.UK Frontend: if the inline JavaScript snippet is blocked by a CSP](https://frontend.design-system.service.gov.uk/import-javascript/#if-our-inline-javascript-snippet-is-blocked-by-a-content-security-policy)
11. [GOV.UK Frontend browser support](https://frontend.design-system.service.gov.uk/browser-support/)
12. [GOV.UK Service Manual: using progressive enhancement](https://www.gov.uk/service-manual/technology/using-progressive-enhancement)
13. [Web Awesome licensing](https://webawesome.com/license)
14. [Web Awesome server-side rendering](https://webawesome.com/docs/ssr/) and [support](https://webawesome.com/support)
15. [npm registry](https://registry.npmjs.org/) entries for each package named above
16. [Bulma modal](https://bulma.io/documentation/components/modal/) and [dark mode](https://bulma.io/documentation/features/dark-mode/)
17. [daisyUI modal](https://daisyui.com/components/modal/), [drawer](https://daisyui.com/components/drawer/) and [themes](https://daisyui.com/docs/themes/)
18. [Basecoat UI introduction](https://basecoatui.com/introduction/) and [dialog](https://basecoatui.com/components/dialog/)
19. [Base UI CSP Provider](https://base-ui.com/react/utils/csp-provider) and [accessibility](https://base-ui.com/react/overview/accessibility)
20. [Primer CSS](https://github.com/primer/css)
21. [Spectrum Web Components](https://opensource.adobe.com/spectrum-web-components/)
22. [Fluent 2 accessibility](https://fluent2.microsoft.design/accessibility) and the `@fluentui/web-components` 3.1.4 package
23. [Carbon accessibility](https://www.carbondesignsystem.com/building-blocks/foundations/accessibility/overview) and the `@carbon/web-components` 2.67.0 package
24. [shadcn/ui](https://ui.shadcn.com/docs)
25. [Radix Primitives accessibility](https://www.radix-ui.com/primitives/docs/overview/accessibility)
26. [React Aria quality and accessibility](https://react-aria.adobe.com/quality)
27. [web.dev Baseline](https://web.dev/baseline), [web-features](https://www.npmjs.com/package/web-features) and MDN pages for [`<dialog>`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/dialog), [Popover API](https://developer.mozilla.org/en-US/docs/Web/API/Popover_API/Using), [invoker commands](https://developer.mozilla.org/en-US/docs/Web/API/Invoker_Commands_API) and [`<details>`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/details)
28. [MDN: CSP `style-src`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/style-src)
