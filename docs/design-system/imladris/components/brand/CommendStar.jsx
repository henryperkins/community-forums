import React from 'react';

/**
 * CommendStar — the filled four-point star used for "Commends" (reputation /
 * esteem). It is the glyph on reaction chips, reputation counts, the star
 * button, and the accepted-answer mark. Inherits `currentColor` (gold by
 * convention).
 */
export function CommendStar({ size = 14, title, className = '', style, ...rest }) {
  return (
    <svg
      viewBox="0 0 100 100"
      width={size}
      height={size}
      role={title ? 'img' : undefined}
      aria-hidden={title ? undefined : 'true'}
      aria-label={title}
      className={className}
      style={{ display: 'inline-block', verticalAlign: 'middle', flex: '0 0 auto', ...style }}
      {...rest}
    >
      {title ? <title>{title}</title> : null}
      <path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" />
    </svg>
  );
}
