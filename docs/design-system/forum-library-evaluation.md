# Forum UI library evaluation

Reviewed: **2026-11-07**. Scope: replacing the deprecated Imladris presentation with a conventional, accessible forum interface.

## Decision

Keep PHP server rendering and progressive enhancement. Build the replacement from semantic HTML, a compact set of owned CSS tokens and reusable presentation classes, and the existing JavaScript enhancement boundary. **Do not add a global UI runtime for this migration.**

Use the accessibility and interaction patterns documented by USWDS, GOV.UK, and WAI as design references. Adopting their guidance does not require installing their entire component libraries or copying their visual identities. If maintaining the owned CSS becomes a demonstrated burden, evaluate a deliberately selected Bootstrap Sass subset first.

This is a research and architecture decision, not a statement that every replacement screen meets WCAG or that every library has been tested in this application. No candidate package was installed as part of this evaluation. Package sizes, application-specific CSP compatibility, assistive technology behavior, and production performance were not benchmarked here. Implementation and test evidence belong to the migration's change record.

## Repository constraints

The repository uses PHP 8.2+ server rendering, HTML forms, and progressively enhanced interaction. The checked manifests do not describe a React application. Milkdown 7.21.2 supplies the rich composer rather than the application shell. Existing JSX design prototypes are not evidence of a React production runtime. See [AGENTS.md](../../AGENTS.md), [composer.json](../../composer.json), and [package.json](../../package.json).

The existing Content Security Policy uses `script-src 'self'` and `style-src 'self'`; this migration should preserve that boundary. External local CSS and JavaScript fit this policy directly. A component's server-rendered inline styles, injected style elements, dynamically loaded assets, and initialization examples require separate examination. A library name alone does not establish compatibility.

The interface must continue to expose useful navigation, content, and form submission when JavaScript is unavailable. Accessibility depends on the rendered page and complete task flow: labels, heading structure, error handling, keyboard behavior, focus visibility, reading order, contrast, and responsive layout. A library's accessibility claims cannot establish conformance for the application.

## Candidate comparison

Versions below are observations from official documentation on the review date, not a guarantee that a particular package registry has no newer release.

| Candidate | Fit for PHP and progressive enhancement | Accessibility and operational considerations | Decision |
| --- | --- | --- | --- |
| Owned HTML and CSS primitives | Direct fit for existing templates and ordinary links/forms. No additional framework runtime or hydration boundary. | The project owns semantics, focus, contrast, and regression work. Keeping the component set small makes this responsibility practical. | **Use for this migration.** |
| Bootstrap 5.3 | CSS works with PHP-rendered markup. Sass and JavaScript can be imported selectively; interactive plugins remain optional. Official documentation shows 5.3.8. | Mature documentation and broad component coverage. Authors still own accessibility. Bootstrap warns that some default color combinations fail text or non-text contrast requirements. Popper-backed controls need a focused integration review. [1–4] | **Best conditional alternative:** evaluate a small CSS subset if there is a concrete maintenance benefit. Do not install the whole bundle by default. |
| Pico CSS | CSS-first, dependency-free styling is architecturally compatible with server-rendered semantic HTML. | The official maintenance notice says **2.1.1 is the final release** and the project is no longer maintained; repositories are archived and no fixes are planned. MIT permits a maintained fork, but that transfers maintenance responsibility. [5] | **Do not introduce as a new dependency.** Its conceptual simplicity does not resolve its maintenance status. |
| USWDS | Its progressive enhancement approach and modular Sass are compatible with this architecture. Components can be evaluated individually. Official documentation shows 3.14.0. | Strong accessibility guidance and documented testing practices. Its federal service patterns and component conventions still require adaptation and product testing. Licensing includes public-domain work and third-party notices. [6–9] | **Adopt relevant patterns now; defer package adoption.** |
| GOV.UK Frontend | Server-rendered HTML and progressive enhancement are a good fit. The official browser support model includes operation without JavaScript. | Strong service design and accessibility guidance. Current 6.x documentation provides non-GOV.UK branding support from 6.3.0 onward, with alternative fonts and replacement identity assets required. A library alone does not make a service accessible. [10–12] | **Adopt interaction and content patterns now.** Whole-library adoption would need a dedicated branding and integration decision. |
| Web Awesome | Framework-independent custom elements can coexist with PHP. Self-hosting and individual imports are available. Official documentation shows 3.14.0; Core is MIT, with separate Pro terms. | Components require JavaScript for their full behavior. Its experimental SSR offers an approximation that reduces layout shift; the maintainers explicitly say it is not intended to make components work without JavaScript. SSR has additional limitations. [13–16] | **Defer broad adoption.** Consider a bounded enhanced component only after proving fallback, CSP, keyboard, and assistive technology behavior. |
| Shoelace | Its web component model could coexist with PHP, but it has the same enhancement-boundary questions. | Officially sunset and no longer actively developed; Web Awesome is its successor. [17] | **Do not add a new dependency.** |
| shadcn/ui | Provides component source the application owns. Its React-oriented workflow would introduce an application architecture absent from this forum. | Source ownership permits customization but also leaves integration and updates with the application. It does not make a PHP page accessible merely by supplying visual components. [18] | **Defer until a separately justified React feature exists.** |
| Radix Primitives | React primitives, rather than PHP template components. Useful for a future React island, not a direct replacement for the current shell. | Documents ARIA patterns, keyboard navigation, and focus management; authors remain responsible for meaningful labels and application behavior. [19] | **Defer for this migration.** |
| React Aria | React hooks/components add a runtime and integration model the current shell does not need. | Particularly strong documented internationalization, interaction, and assistive technology testing practices. The application still owns labels, contrast, target sizes, and visible focus. [20] | **Consider for a future independently justified React feature.** |
| Base UI | React component library; current accessibility documentation shows 1.9.0. Not a drop-in PHP theme. | Provides accessibility behavior, but focus styling remains an author responsibility. Its CSP documentation explicitly distinguishes injected style/script elements from server-rendered style attributes. [21–22] | **Defer.** Its CSP facilities would require a component-specific design, not an assumption of compatibility. |

## Why the native path wins here

The requested improvement concerns reading and using the forum: recognizable navigation, clear hierarchy, dependable controls, readable typography, and measured contrast. These improvements can be made directly in the current PHP templates and CSS without replacing how the application renders or submits forms.

A framework conversion would introduce additional work in client/server state, hydration, build output, component ownership, and failure behavior. No requirement identified in this migration needs those costs. This is an architectural inference from the repository's existing rendering model and the candidates' documented integration models, rather than a claim that React or web components cannot be accessible.

The owned layer should remain deliberately small: typography, spacing, surfaces, links, buttons, fields, feedback messages, navigation, forum lists, post reading, and responsive behavior. Complex interactions should be introduced only for concrete product needs. Where possible, use native elements whose expected behavior already exists in the browser.

Bootstrap is the strongest fallback because it can improve a server-rendered interface without requiring an application framework. Its documented selective imports offer a way to limit scope. Its default visual palette is not an acceptance test: the Bootstrap maintainers explicitly call out contrast failures in some default combinations. The forum must measure its actual themed states, including hover, focus, validation, disabled controls, and any supported alternate palette. [1–3]

## CSP and no-JavaScript boundaries

These checks must be done per component and per rendering path:

1. **Static HTML and external CSS:** generally a direct fit for the current policy when assets are served from approved local paths. Confirm generated output rather than relying on source examples.
2. **Interactive JavaScript:** self-hosting solves the asset origin question, but does not prove style injection, positioning, focus handling, or fallback behavior is suitable. Bootstrap documents optional plugins and identifies components with a Popper dependency. [3]
3. **Custom elements:** PHP can emit their tags without supplying their enhanced behavior. Web Awesome documents experimental SSR limitations, including icons that remain blank until client initialization, client-rendered canvas features, and limitations around language, direction, and slot detection. Its support documentation explicitly separates SSR from no-JavaScript functionality. [15–16]
4. **React libraries:** server rendering is not sufficient evidence of policy compatibility. Base UI's `CSPProvider` can apply nonces to certain injected elements, and `disableStyleElements` shifts particular rules into application CSS. Its documentation separately discusses server-rendered `style` attributes, which a nonce does not authorize. Adding a nonce or relaxing the policy is not part of this migration. [22]

Do not equate all dynamic styling with forbidden inline HTML. CSP distinguishes inline markup and style elements from some JavaScript CSSOM property operations; Base UI documents this distinction. Conversely, do not assume that a library using CSSOM is compatible in every rendering mode. Verify the exact component in the application's browser response and inspect CSP violations. [22]

The acceptable fallback is a usable task, not just visible placeholder markup. Reading a thread, following pagination, searching through an ordinary form, and submitting the supported plain-text composer path must remain assessable without client enhancement. A future sophisticated widget needs an explicit fallback or a separately reviewed requirement for JavaScript.

## Adopt standards and patterns without importing an identity

USWDS and GOV.UK are valuable references for form labels, hint text, error summaries, navigation, content structure, focus, and progressive enhancement. They also document that application teams must test the services they build. Their evidence informs the design; it does not certify this forum. [6, 10]

Use the relevant interaction pattern and its semantic structure while preserving the forum's own product language and identity. Full GOV.UK package adoption is possible outside government, but the official guidance requires a generic branding approach, alternative typography, and replacement brand assets. That is a migration decision with more scope than consulting its patterns. [11]

The same distinction applies to licensing. Bootstrap's code is MIT, while its documentation has separate terms. Web Awesome Core is MIT and Pro has separate terms. USWDS combines public-domain work with third-party licenses. Code or asset reuse should retain applicable notices; consulting a pattern does not imply copying licensed assets. [4, 9, 14]

## Incremental adoption path

1. Replace Imladris presentation within the existing rendering boundary. Establish shared tokens and semantic template primitives, preserve ordinary links and forms, and remove obsolete visual requirements.
2. Verify complete reading and contribution flows: keyboard traversal, focus visibility and order, text resizing, narrow viewport reflow, text spacing, contrast, form errors, and reduced-motion behavior. Include representative long titles, long posts, code, quotes, links, and images.
3. Record automated checks and manual browser evidence separately. Include JavaScript-disabled flows and the actual production CSP. Automated accessibility results are useful evidence but do not replace keyboard or assistive technology review.
4. Consider a third-party component only when a recurring implementation problem or required interaction justifies it. Document the replaced code, dependencies, fallback, maintenance ownership, licensing, and measured production output before adoption.
5. If broad CSS maintenance becomes the problem, prototype a custom Bootstrap Sass subset on one representative forum screen. If one complex widget becomes the problem, compare a native solution with a narrowly imported Web Awesome component. Introduce a React library only after an independent decision to build a React feature.

These steps are acceptance and adoption criteria, not a report of checks completed by this research document. No transfer-size figures are asserted: the relevant comparison is the built and compressed output of the exact selected components, including their transitive dependencies and required assets.

## Official sources

All sources were reviewed on 2026-11-07. Maintenance status and documentation version observations should be rechecked before a later dependency adoption.

1. [Bootstrap accessibility](https://getbootstrap.com/docs/5.3/getting-started/accessibility/)
2. [Bootstrap optimization and selective imports](https://getbootstrap.com/docs/5.3/customize/optimize/)
3. [Bootstrap JavaScript integration](https://getbootstrap.com/docs/5.3/getting-started/javascript/)
4. [Bootstrap license](https://getbootstrap.com/docs/5.3/about/license/)
5. [Pico CSS maintenance and retirement notice](https://picocss.com/docs/maintenance)
6. [USWDS accessibility](https://designsystem.digital.gov/documentation/accessibility/)
7. [USWDS developer getting started guide](https://designsystem.digital.gov/documentation/getting-started-for-developers/)
8. [USWDS component packages](https://designsystem.digital.gov/components/packages/)
9. [USWDS code and licensing guidelines](https://designsystem.digital.gov/documentation/code-guidelines/)
10. [GOV.UK Design System accessibility](https://design-system.service.gov.uk/accessibility/)
11. [Using GOV.UK Frontend without GOV.UK branding](https://frontend.design-system.service.gov.uk/using-govuk-frontend-without-govuk-branding/)
12. [GOV.UK Frontend browser support](https://frontend.design-system.service.gov.uk/browser-support/)
13. [Web Awesome documentation and installation](https://webawesome.com/docs/)
14. [Web Awesome licensing](https://webawesome.com/license)
15. [Web Awesome support and SSR expectations](https://webawesome.com/support)
16. [Web Awesome server-side rendering](https://webawesome.com/docs/ssr/)
17. [Shoelace sunset notice](https://shoelace.style/)
18. [shadcn/ui introduction](https://ui.shadcn.com/docs)
19. [Radix Primitives accessibility](https://www.radix-ui.com/primitives/docs/overview/accessibility)
20. [React Aria quality and accessibility](https://react-aria.adobe.com/quality)
21. [Base UI accessibility](https://base-ui.com/react/overview/accessibility)
22. [Base UI CSP Provider and style attribute behavior](https://base-ui.com/react/utils/csp-provider)
