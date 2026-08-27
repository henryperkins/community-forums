import * as React from 'react';

/**
 * @startingPoint section="Identity" subtitle="Monogram avatar with presence & gilt" viewport="700x150"
 */
export interface MonogramProps extends React.HTMLAttributes<HTMLElement> {
  /** Display name — drives the initials. */
  name?: string;
  /** Seed for the colour (defaults to name). Same seed → same colour. */
  username?: string;
  /**
   * Named rung — 'sm' 28px · 'md' 36px (default) · 'lg' 44px · 'xl' 64px — or a
   * number for an exact pixel box (ink scales with it). The app uses `32` in the
   * board topic row, where density is pinned at 32px/.6rem.
   */
  size?: 'sm' | 'md' | 'lg' | 'xl' | number;
  /** Add the gold "gilt" ring — for OP, accepted answer, profile, top-3. */
  gilt?: boolean;
  /** Presence dot: true/'online' (leaf), 'away' (amber), 'offline' (grey). */
  presence?: boolean | 'online' | 'away' | 'offline';
  /** Real avatar image URL (replaces the initials). */
  src?: string;
}

/**
 * The brand avatar — tinted-ground initials, colour hashed from the username.
 */
export function Monogram(props: MonogramProps): JSX.Element;
