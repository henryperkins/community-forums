import * as React from 'react';

export interface EightPointStarProps extends React.SVGProps<SVGSVGElement> {
  /** Square size in px. Default 26 (wordmark size). */
  size?: number;
  /** Outer-path stroke width. Default 3.4. Ignored for the watermark variant. */
  strokeWidth?: number;
  /** 'mark' = the solid house mark; 'watermark' = faint, thin, decorative. */
  variant?: 'mark' | 'watermark';
  /** Accessible label. When omitted the star is aria-hidden (decorative). */
  title?: string;
  className?: string;
}

/**
 * The Imladris eight-pointed elven star — the house mark. Inherits currentColor.
 */
export function EightPointStar(props: EightPointStarProps): JSX.Element;
