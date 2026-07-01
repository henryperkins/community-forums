import * as React from 'react';

export interface ChoiceCardProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'title'> {
  /** Radio group name. */
  name?: string;
  /** Heading line (lapidary caps). */
  title?: string;
  /** Supporting description line. */
  desc?: string;
  /** Optional preview node above the title (e.g. a theme swatch). */
  swatch?: React.ReactNode;
}

/** A large radio "card" for theme/density-style single choice. */
export function ChoiceCard(props: ChoiceCardProps): JSX.Element;
