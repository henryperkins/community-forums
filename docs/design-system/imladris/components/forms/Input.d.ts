import * as React from 'react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  /** Rounded search-bar style on the page ground. */
  pill?: boolean;
  /** Renders a labelled field wrapper above the input. */
  label?: string;
  /** Leading glyph inside the field, decorative. 'search' draws the system
   *  lens; or pass any node. The input still needs its own label or aria-label. */
  icon?: 'search' | React.ReactNode;
}

/** Serif text field with a gold focus halo, optionally with a leading icon. */
export function Input(props: InputProps): JSX.Element;
