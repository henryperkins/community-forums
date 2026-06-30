import React from 'react';

/**
 * Card — the base raised surface: parchment ground, hairline border, soft
 * shadow, large radius. The container most Imladris content sits on.
 */
export function Card({ as: Tag = 'div', className = '', children, ...rest }) {
  return <Tag className={['card', className].filter(Boolean).join(' ')} {...rest}>{children}</Tag>;
}
