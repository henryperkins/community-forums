// Loads this design system into the template. In a consuming project, point
// base at the bound DS folder relative to this file (e.g. '_ds/<folder>' at
// the project root, '../_ds/<folder>' one level down) — one line to edit.
//
// IDEMPOTENT ON PURPOSE. This runs from a <helmet> script, and a helmet can
// mount more than once in a component's life. Appending the stylesheets and the
// bundle unconditionally makes each mount re-fetch and re-execute the bundle,
// which re-registers components, which remounts — a self-feeding loop that
// buries the page under thousands of <link> elements. Every append below is
// guarded by "is it already in the document".
(() => {
  const base = '../..';
  const abs = (p) => new URL(base + '/' + p, document.baseURI).href;

  for (const p of ["tokens/fonts.css","tokens/colors.css","tokens/typography.css","tokens/spacing.css","components.css","components/doc.css","styles.css"]) {
    const href = abs(p);
    if (document.querySelector('link[rel="stylesheet"][href="' + href + '"]')) continue;
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = href;
    document.head.appendChild(l);
  }

  const src = abs('_ds_bundle.js');
  if (!document.querySelector('script[src="' + src + '"]')) {
    const s = document.createElement('script');
    s.src = src;
    s.onerror = () => console.error('ds-base.js: failed to load ' + src + ' — if this is a consuming project, point the base line in ds-base.js at the bound _ds/<folder> tree relative to this page (e.g. _ds/<folder> at the project root, ../_ds/<folder> one level down); in a fresh design system this can just mean the bundle is not compiled yet');
    document.head.appendChild(s);
  }
})();
