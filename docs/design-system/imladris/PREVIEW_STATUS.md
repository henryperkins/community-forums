# Imladris preview status

The checked-in CSS, assets, HTML, JSX, and declaration files are design references. The PHP product consumes only the allowlisted CSS and font output built by `ImladrisAssetBuilder`; it does not load a React design-system bundle.

`_ds_bundle.js` was retired on 2026-08-27. It was generated output with no compiler in this repository, and its compiled code no longer matched the source hashes of the checked-in JSX. Git history remains the recovery path if an upstream compiler is later imported.

What still runs here:

- Static specimens and handoffs that use `styles.css`, `components.css`, and ordinary HTML/CSS/JS.
- The production-generated `public/assets/imladris.css` and self-hosted fonts.

What is source-only here:

- React component cards, feature previews, UI kits, and `.dc.html` loaders that refer to `window.ImladrisDesignSystem_c3e027` or `_ds_bundle.js`.
- Those references are retained as imported authoring provenance. They require the upstream design compiler and must not be described as current executable previews in this mirror.

Do not hand-edit or reconstruct a compiled bundle. Either create static HTML from the documented class names, compile the JSX in the consuming application, or bring in a reproducible upstream compiler with its own freshness test.
