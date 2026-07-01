import React from 'react';

/**
 * JoinBar — the guest's place at the table. Replaces the composer when signed
 * out: a brand-subtle card reading "You're browsing as a guest — log in to add
 * your counsel." with a primary Log in button. Use `archived` for the
 * locked/archived-topic variant.
 */
export function JoinBar({
  message,
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
      <span>{text}</span>
      <a className="btn" href={href}>{cta}</a>
    </div>
  );
}
