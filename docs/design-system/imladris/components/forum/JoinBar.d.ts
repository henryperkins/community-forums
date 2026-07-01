import * as React from 'react';

export interface JoinBarProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Override the default guest message. */
  message?: React.ReactNode;
  /** Button label. Default "Log in". */
  cta?: string;
  href?: string;
  /** The locked/archived-topic variant (neutral, not brand-subtle). */
  archived?: boolean;
}

/** The guest "log in to add your counsel" bar that replaces the composer. */
export function JoinBar(props: JoinBarProps): JSX.Element;
