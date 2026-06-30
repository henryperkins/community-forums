import React from 'react';

/**
 * SectionHeader — a numbered section (or sub-section) header. A tracked kicker
 * ("§5 · Screens & flows") over a display title, with an optional italic
 * standfirst. `level="section"` is the gold, h2 register that opens a chapter;
 * `level="sub"` is the quieter ink, h3 register for a sub-section.
 */
export function SectionHeader({
  number,
  kicker,
  title,
  dek,
  level = 'section',
  as,
  className = '',
  ...rest
}) {
  const Tag = as || (level === 'sub' ? 'h3' : 'h2');
  const label = [number, kicker].filter(Boolean).join(' · ');
  const cls = ['doc-section', level === 'sub' ? 'is-sub' : '', className].filter(Boolean).join(' ');
  return (
    <header className={cls} {...rest}>
      {label ? <div className="doc-section-kicker">{label}</div> : null}
      {title ? <Tag className="doc-section-title">{title}</Tag> : null}
      {dek ? <p className="doc-section-dek">{dek}</p> : null}
    </header>
  );
}
