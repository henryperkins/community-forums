import React from 'react';

// The Imladris eight-point mark (Eärendil's star), used as the cover device.
const DEFAULT_MARK = (
  <svg viewBox="0 0 100 100" fill="none" aria-hidden="true">
    <g stroke="currentColor" strokeWidth="3.2" strokeLinejoin="round" strokeLinecap="round">
      <path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z" />
      <path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5" />
      <circle cx="50" cy="50" r="5" fill="currentColor" stroke="none" />
    </g>
  </svg>
);

/**
 * DocCover — the title page of a long-form Imladris document. A tracked-caps
 * kicker beside the eight-point mark, a large display title, an italic dek, a
 * gold rule, the lede, a two-column meta grid, and a contents rail of section
 * pills. Anything passed as children renders between the lede and the meta grid.
 */
export function DocCover({
  kicker,
  title,
  subtitle,
  lede,
  meta = [],
  contents = [],
  mark,                 // pass null to hide the device
  className = '',
  children,
  ...rest
}) {
  const showMark = mark !== null;
  return (
    <header className={['doc-cover', className].filter(Boolean).join(' ')} {...rest}>
      {(kicker || showMark) ? (
        <div className="doc-cover-brand">
          {showMark ? <span className="doc-cover-mark">{mark || DEFAULT_MARK}</span> : null}
          {kicker ? <span className="doc-cover-kicker">{kicker}</span> : null}
        </div>
      ) : null}
      {title ? <h1 className="doc-cover-title">{title}</h1> : null}
      {subtitle ? <div className="doc-cover-dek">{subtitle}</div> : null}
      <div className="doc-cover-rule" aria-hidden="true" />
      {lede ? <p className="doc-cover-lede">{lede}</p> : null}
      {children}
      {meta.length ? (
        <div className="doc-cover-meta">
          {meta.map((m, i) => (
            <div className="doc-cover-meta-cell" key={i}>
              <div className="doc-cover-meta-label">{m.label}</div>
              <div className="doc-cover-meta-value">{m.value}</div>
            </div>
          ))}
        </div>
      ) : null}
      {contents.length ? (
        <nav className="doc-cover-toc" aria-label="Contents">
          {contents.map((c, i) => <span className="doc-cover-toc-item" key={i}>{c}</span>)}
        </nav>
      ) : null}
    </header>
  );
}
