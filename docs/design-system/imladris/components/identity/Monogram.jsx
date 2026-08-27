import React from 'react';

// Deterministic avatar colour from a seed (username), 0–9 → .mono-0..9.
function monogramClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}

// 1–2 letter initials: first letters of the first two words, else first two
// letters of a single word. Uppercased.
function monogramInitials(label) {
  const parts = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}

const SIZE_CLASS = { sm: 'monogram-sm', md: '', lg: 'monogram-lg', xl: 'monogram-xl' };

// The named scale has four rungs (28/36/44/64). A numeric `size` asks for an
// exact pixel box instead — the app does this in the board topic row, which
// pins 32px/.6rem (app.css `.board-view .thread-row-board > .monogram`). Ink
// scales with the box at the app's own 0.3 ratio.
function pixelSize(size) {
  const n = typeof size === 'number' ? size : (/^\d+$/.test(String(size)) ? Number(size) : NaN);
  return Number.isFinite(n) && n > 0 ? n : null;
}

/**
 * Monogram — the brand avatar. A tinted ground + dark ink initials, with the
 * colour chosen deterministically from `username`. Add `gilt` for "precious"
 * avatars (OP, accepted answer, profile, leaderboard top-3). Pass `presence`
 * for a leaf/away/offline dot, or `src` for a real image.
 */
export function Monogram({
  name,
  username,
  size = 'md',
  gilt = false,
  presence,             // true | 'online' | 'away' | 'offline'
  src,
  className = '',
  style,
  ...rest
}) {
  const px = pixelSize(size);
  const sizeCls = px === null ? (SIZE_CLASS[size] || '') : '';
  const sizeStyle = px === null ? style : {
    width: px + 'px', height: px + 'px', fontSize: (px * 0.3) + 'px', flexShrink: 0, ...style,
  };
  const seed = username || name;

  const avatar = src ? (
    <img
      className={['monogram', 'avatar-img', sizeCls, gilt ? 'monogram-gilt' : '', className].filter(Boolean).join(' ')}
      src={src}
      alt=""
      aria-hidden="true"
      style={sizeStyle}
      {...rest}
    />
  ) : (
    <span
      className={['monogram', monogramClass(seed), sizeCls, gilt ? 'monogram-gilt' : '', className].filter(Boolean).join(' ')}
      aria-hidden="true"
      style={sizeStyle}
      {...rest}
    >
      {monogramInitials(name || username)}
    </span>
  );

  if (presence) {
    const dotColor = presence === 'away' ? 'var(--amber)'
      : presence === 'offline' ? 'var(--ink-300)'
      : 'var(--presence)';
    return (
      <span className="avatar-wrap">
        {avatar}
        <span className="presence-dot" style={{ background: dotColor }} aria-hidden="true" />
      </span>
    );
  }
  return avatar;
}
