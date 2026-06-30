import React from 'react';

/**
 * Reaction — a lightweight appreciation chip that reads "✦ Name · count".
 * The Imladris set: Commend (the gold star, default), Kindled (flame),
 * Seconded (check), Illuminating (sparkle) — pass the glyph via `icon` for the
 * non-Commend ones. `active` = the viewer reacted (warms to gold).
 */
export function Reaction({
  name = 'Commend',
  count,
  active = false,
  icon,
  onClick,
  className = '',
  ...rest
}) {
  const cls = ['reaction', active ? 'reaction-on' : '', className].filter(Boolean).join(' ');
  const glyph = icon != null ? icon : (
    <svg viewBox="0 0 100 100" width="12" height="12" aria-hidden="true" style={{ display: 'inline-block', flex: '0 0 auto' }}>
      <path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" />
    </svg>
  );
  return (
    <button type="button" className={cls} aria-pressed={active} onClick={onClick} {...rest}>
      <span className="reaction-glyph" style={{ display: 'inline-flex' }}>{glyph}</span>
      <span>{name}</span>
      {count != null ? <span className="reaction-n">{count}</span> : null}
    </button>
  );
}
