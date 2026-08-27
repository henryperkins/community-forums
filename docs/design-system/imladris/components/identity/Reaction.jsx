import React from 'react';

/**
 * Reaction — a lightweight appreciation chip that reads "✦ Name · count".
 * The Imladris set: Commend (the gold star, default), Kindled (flame),
 * Seconded (check), Illuminating (sparkle) — pass the glyph via `icon` for the
 * non-Commend ones. `active` = the viewer reacted (warms to gold).
 *
 * Raw-emoji form: production's ReactionService::ALLOWED is a bare emoji list
 * with no named set, so a post's reactions render as glyph + count. Pass
 * `icon={emoji}` and `name={null}` — the label span is dropped entirely rather
 * than rendered empty, so the chip is "😀 4" with no hole where a name was.
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
  const cls = ['reaction', active ? 'reaction-on' : '', name ? '' : 'reaction-bare', className].filter(Boolean).join(' ');
  const glyph = icon != null ? icon : (
    <svg viewBox="0 0 100 100" width="12" height="12" aria-hidden="true" style={{ display: 'inline-block', flex: '0 0 auto' }}>
      <path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" />
    </svg>
  );
  return (
    <button type="button" className={cls} aria-pressed={active} onClick={onClick} {...rest}>
      <span className="reaction-glyph" style={{ display: 'inline-flex' }}>{glyph}</span>
      {name ? <span>{name}</span> : null}
      {count != null ? <span className="reaction-n">{count}</span> : null}
    </button>
  );
}
