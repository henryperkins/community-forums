import React from 'react';

/**
 * EightPointStar — the Imladris elven star, the house mark.
 * An eight-pointed star (outer + faint inner star + a center dot), drawn from
 * the brand's authoritative path data. Inherits `currentColor`, so colour it
 * with a wrapping `color` (evergreen for the wordmark, gold for esteem, faint
 * gold for a watermark).
 */
export function EightPointStar({
  size = 26,
  strokeWidth = 3.4,
  variant = 'mark',          // 'mark' | 'watermark'
  title,
  className = '',
  style,
  ...rest
}) {
  const isWatermark = variant === 'watermark';
  return (
    <svg
      viewBox="0 0 100 100"
      width={size}
      height={size}
      role={title ? 'img' : undefined}
      aria-hidden={title ? undefined : 'true'}
      aria-label={title}
      className={className}
      style={{ display: 'block', flex: '0 0 auto', opacity: isWatermark ? 0.12 : 1, ...style }}
      {...rest}
    >
      {title ? <title>{title}</title> : null}
      <g
        fill="none"
        stroke="currentColor"
        strokeWidth={isWatermark ? 1.3 : strokeWidth}
        strokeLinejoin="round"
        strokeLinecap="round"
      >
        <path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z" />
        <path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5" />
        <circle cx="50" cy="50" r="5" fill="currentColor" stroke="none" />
      </g>
    </svg>
  );
}
