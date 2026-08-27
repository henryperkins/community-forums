import React from 'react';

/**
 * JoinBar — the guest's place at the table. Replaces the composer when signed
 * out: a brand-subtle card reading "You're browsing as a guest — log in to add
 * your counsel." with a primary Log in button. Use `archived` for the
 * locked/archived-topic variant.
 *
 * The neutral variants state a fact rather than invite an act — a locked topic,
 * a suspended account, a board the reader cannot post in — so they take an
 * `icon` and pass `cta={null}` to drop the button entirely.
 */
export function JoinBar({
  message,
  icon,
  cta = 'Log in',
  href = '/login',
  archived = false,
  className = '',
  ...rest
}) {
  const text = message || (
    <>You're browsing as a guest — <em>log in to add your counsel.</em></>
  );
  return (
    <div className={['joinbar', archived ? 'joinbar-archived' : '', className].filter(Boolean).join(' ')} {...rest}>
      {icon ? <span className="joinbar-icon" aria-hidden="true">{icon}</span> : null}
      <span>{text}</span>
      {cta ? <a className="btn" href={href}>{cta}</a> : null}
    </div>
  );
}
