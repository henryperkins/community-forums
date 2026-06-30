import * as React from 'react';

/**
 * @startingPoint section="Core" subtitle="Evergreen / gold / outline action button" viewport="700x180"
 */
export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /**
   * primary  = evergreen fill (default action)
   * secondary= parchment outline
   * ghost    = transparent, wash on hover
   * accent   = mallorn-gold fill — reserve for the single most-wanted action
   * danger   = rust fill (destructive)
   */
  variant?: 'primary' | 'secondary' | 'ghost' | 'accent' | 'danger';
  /** 'md' (default) or 'sm'. */
  size?: 'md' | 'sm';
  /** Render as an anchor to this URL instead of a <button>. */
  href?: string;
  /** Optional leading icon element (e.g. a Lucide/SVG node). */
  icon?: React.ReactNode;
  /** Optional trailing icon element. */
  iconAfter?: React.ReactNode;
  disabled?: boolean;
  children?: React.ReactNode;
}

/**
 * The Imladris button.
 */
export function Button(props: ButtonProps): JSX.Element;
