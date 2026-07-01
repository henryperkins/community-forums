import React from 'react';

const SLOT_ICON = (
  <svg viewBox="0 0 24 24" aria-hidden="true">
    <rect x="3" y="3" width="18" height="18" rx="2" />
    <circle cx="8.5" cy="8.5" r="1.6" />
    <path d="M21 15l-5-5L5 21" />
  </svg>
);

/**
 * Figure — a framed image with a mono caption ("FIG 3 — …"). Supply the media
 * three ways: pass `src` for an <img>, pass children (e.g. an <image-slot> the
 * reader fills, or a strip of device frames), or pass nothing and it renders a
 * drop-in slot. `plain` removes the frame for edge-to-edge art.
 */
export function Figure({
  src,
  alt = '',
  label,
  caption,
  plain = false,
  slotHint = 'Drop a screenshot here',
  className = '',
  children,
  ...rest
}) {
  let media;
  if (children != null) {
    media = children;
  } else if (src) {
    media = <img className="doc-figure-img" src={src} alt={alt} />;
  } else {
    media = (
      <div className="doc-figure-slot" role="img" aria-label={alt || (typeof slotHint === 'string' ? slotHint : 'Image')}>
        {SLOT_ICON}
        <span>{slotHint}</span>
      </div>
    );
  }
  const cls = ['doc-figure', plain ? 'is-plain' : '', className].filter(Boolean).join(' ');
  return (
    <figure className={cls} {...rest}>
      <div className="doc-figure-frame">{media}</div>
      {(label || caption) ? (
        <figcaption className="doc-figure-cap">
          {label ? <span className="doc-figure-cap-label">{label}</span> : null}
          {caption ? <span className="doc-figure-cap-text">{caption}</span> : null}
        </figcaption>
      ) : null}
    </figure>
  );
}
