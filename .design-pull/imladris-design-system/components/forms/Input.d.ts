import * as React from 'react';

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  /** Rounded search-bar style on the page ground. */
  pill?: boolean;
  /** Renders a labelled field wrapper above the input. */
  label?: string;
}

/** Serif text field with a gold focus halo. */
export function Input(props: InputProps): JSX.Element;
