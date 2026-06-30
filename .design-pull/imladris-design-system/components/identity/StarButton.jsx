import React from 'react';

/**
 * StarButton — the "Star this topic" pill (a personal bookmark). Off = quiet
 * parchment outline; on = warm gold. Uses the four-point commend star glyph.
 */
export function StarButton({
  active = false,
  label,
  count,
  onClick,
  className = '',
  ...rest
}) {
  const cls = ['star-btn', active ? 'star-on' : '', className].filter(Boolean).join(' ');
  const text = label != null ? label : (active ? 'Starred' : 'Star');
  return (
    <button type="button" className={cls} aria-pressed={active} onClick={onClick} {...rest}>
      <svg viewBox="0 0 100 100" width="13" height="13" aria-hidden="true" style={{ display: 'inline-block', flex: '0 0 auto', color: 'var(--star)' }}>
        <path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" />
      </svg>
      <span>{text}</span>
      {count != null ? <span className="reaction-n" style={{ marginLeft: 2 }}>{count}</span> : null}
    </button>
  );
}
