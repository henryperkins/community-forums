import React from 'react';

function monoClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}

function initials(label) {
  const parts = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
}

// The dot is decorative everywhere — the state is always carried as text on the
// sub-line, so a screen reader reads the member once, not a leaf and a name.
const DOT_CLASS = { away: 'is-away', offline: 'is-off' };
const STATE_LABEL = { online: 'Here now', away: 'Away', offline: 'Not shown' };

/**
 * PresenceRow — one member in the presence widget. Exported so a page can build
 * its own list (a directory grid, a "seen recently" strip) with the same row.
 *
 * One anatomy, deliberately: the rail, the directory grid and anything a live
 * poller rebuilds must render the same row, or the rail reflows under the
 * reader a second after load.
 */
export function PresenceRow({
  name,
  username,
  presence = 'online',
  title,
  where,
  href,
  src,
  gilt = false,
  staff = false,
  self = false,
  avatar = true,
  className = '',
  ...rest
}) {
  const label = name || username;
  const Tag = href ? 'a' : 'span';
  const dotClass = DOT_CLASS[presence] || '';
  const trailing = where ? <span className="presence-where">{where}</span> : title || null;
  return (
    <li className={['presence-row', className].filter(Boolean).join(' ')} data-presence-row={username} {...rest}>
      <Tag className="presence-person" href={href} data-presence-state={presence}>
        {avatar ? (
          <span className="avatar-wrap">
            {src ? (
              <img className={['monogram', 'monogram-sm', 'avatar-img', gilt ? 'monogram-gilt' : ''].filter(Boolean).join(' ')} src={src} alt="" aria-hidden="true" />
            ) : (
              <span className={['monogram', 'monogram-sm', monoClass(username || label), gilt ? 'monogram-gilt' : ''].filter(Boolean).join(' ')} aria-hidden="true">{initials(label)}</span>
            )}
            <span className={['presence-dot', dotClass].filter(Boolean).join(' ')} aria-hidden="true" />
          </span>
        ) : (
          // Avatars off is a reading preference, and the row is on the rail of
          // every route. The dot stands alone rather than going with the picture.
          <span className={['presence-dot', 'presence-dot-bare', dotClass].filter(Boolean).join(' ')} aria-hidden="true" />
        )}
        <span className="presence-person-id">
          <span className="presence-name">
            {label}
            {self ? <span className="presence-you">you</span> : null}
            {staff ? <span className="presence-staff">Staff</span> : null}
          </span>
          <span className="presence-sub">
            {username ? `@${username} · ` : ''}
            {STATE_LABEL[presence] || STATE_LABEL.online}
            {trailing ? <> · {trailing}</> : null}
          </span>
        </span>
      </Tag>
    </li>
  );
}

/**
 * PresenceList — the "Online" widget. A live roster of the members currently at
 * the council: a leaf dot per person, the here-now count beside the heading, and
 * a "+n more" tail when the roll runs past `max`.
 *
 * `here` is the badge (members here *now*); `total` is the whole roll, and it is
 * what decides emptiness and "+n more". A rail holding five away members and
 * nobody here is not empty.
 */
export function PresenceList({
  title = 'Online',
  people = [],
  here,
  count,
  total,
  max = 5,
  showCount = true,
  loading = false,
  layout = 'list',
  avatars = true,
  emptyLabel = 'No one is showing as online.',
  footer,
  className = '',
  ...rest
}) {
  const listClass = layout === 'grid' ? 'presence-grid' : 'presence-list';
  const shown = max > 0 ? people.slice(0, max) : people;
  // `count` is the old name for `here`, kept as an alias so existing callers
  // keep working; `here` wins where both are given.
  const hereCount = here != null ? here : count != null ? count : people.filter((p) => (p.presence || 'online') === 'online').length;
  const rollTotal = total != null ? total : Math.max(people.length, hereCount);
  const more = Math.max(0, rollTotal - shown.length);
  const awayCount = Math.max(0, rollTotal - hereCount);

  return (
    <section className={['presence-widget', className].filter(Boolean).join(' ')} data-presence-limit={max > 0 ? max : undefined} {...rest}>
      {/* A list with no title and no count (the directory grid under its own
          page heading) gets no heading at all — not an empty <h2>. */}
      {title || showCount ? (
        <h2 className="presence-title">
          {title}
          {showCount ? <span className="presence-count">{loading ? '·' : hereCount}</span> : null}
        </h2>
      ) : null}
      {/* Exactly one live region, and it announces a summary. When the list
          itself is live, a poller replacing rows makes a screen reader re-read
          the whole roster on every cycle, changed or not. */}
      {loading ? null : (
        <p className="sr-only" aria-live="polite">
          {hereCount} member{hereCount === 1 ? '' : 's'} here now{awayCount > 0 ? `, ${awayCount} away` : ''}.
        </p>
      )}
      {loading ? (
        <ul className={listClass} aria-hidden="true">
          {[0, 1, 2].map((i) => (
            <li className="presence-row is-loading" key={i}>
              <span className="presence-person">
                <span className="presence-skeleton-dot" />
                <span className="presence-skeleton-bar" style={{ width: `${68 - i * 12}%` }} />
              </span>
            </li>
          ))}
        </ul>
      ) : shown.length === 0 ? (
        <p className="presence-empty">{emptyLabel}</p>
      ) : (
        <ul className={listClass}>
          {shown.map((p, i) => (
            <PresenceRow key={p.username || p.name || i} avatar={avatars} {...p} />
          ))}
        </ul>
      )}
      {more > 0 && !loading ? <p className="presence-more">+{more} more</p> : null}
      {footer ? <div className="presence-foot">{footer}</div> : null}
    </section>
  );
}
