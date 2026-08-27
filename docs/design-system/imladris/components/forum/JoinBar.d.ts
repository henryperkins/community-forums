import * as React from 'react';

export interface JoinBarProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Override the default guest message. */
  message?: React.ReactNode;
  /** A leading glyph for the neutral variants (lock, info). */
  icon?: React.ReactNode;
  /** Button label. Default "Log in". Pass null to render no button — for the
   *  states that report a fact rather than invite an act. */
  cta?: string | null;
  href?: string;
  /** The locked/archived-topic variant (neutral, not brand-subtle). */
  archived?: boolean;
}

/** The guest "log in to add your counsel" bar that replaces the composer. */
export function JoinBar(props: JoinBarProps): JSX.Element;
