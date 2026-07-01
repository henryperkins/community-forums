import * as React from 'react';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  /**
   * op     = green "OP" (original poster)
   * staff  = gold "Staff"
   * wiki   = green "Wiki"
   * muted  = neutral (pass children for the label)
   * solved = outlined accepted-answer marker
   */
  variant?: 'op' | 'staff' | 'wiki' | 'muted' | 'solved';
  /** Override the default label for the variant. */
  children?: React.ReactNode;
}

/** A role/author marker shown inline with a name. */
export function Badge(props: BadgeProps): JSX.Element;
