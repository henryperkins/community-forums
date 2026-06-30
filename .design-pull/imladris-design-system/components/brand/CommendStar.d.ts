import * as React from 'react';

export interface CommendStarProps extends React.SVGProps<SVGSVGElement> {
  /** Square size in px. Default 14. */
  size?: number;
  /** Accessible label; omit for decorative (aria-hidden). */
  title?: string;
  className?: string;
}

/**
 * The filled four-point "Commend" star — the esteem/reputation glyph.
 * Inherits currentColor (gold by convention: var(--star)).
 */
export function CommendStar(props: CommendStarProps): JSX.Element;
