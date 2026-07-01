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
  ...rest
}) {
  const sizeCls = SIZE_CLASS[size] || '';
  const seed = username || name;

  const avatar = src ? (
    <img
      className={['monogram', 'avatar-img', sizeCls, gilt ? 'monogram-gilt' : '', className].filter(Boolean).join(' ')}
      src={src}
      alt=""
      aria-hidden="true"
      {...rest}
    />
  ) : (
    <span
      className={['monogram', monogramClass(seed), sizeCls, gilt ? 'monogram-gilt' : '', className].filter(Boolean).join(' ')}
      aria-hidden="true"
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
