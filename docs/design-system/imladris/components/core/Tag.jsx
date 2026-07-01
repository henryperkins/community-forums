import React from 'react';

/**
 * Tag — the smallest meta token: board visibility, a topic tag, a quiet label.
 * Lapidary micro-caps on a sunken pill.
 */
export function Tag({ className = '', children, ...rest }) {
  return <span className={['tag', className].filter(Boolean).join(' ')} {...rest}>{children}</span>;
}
