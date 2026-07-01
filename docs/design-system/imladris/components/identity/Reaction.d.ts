import * as React from 'react';

export interface ReactionProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Reaction name: Commend (default), Kindled, Seconded, Illuminating. */
  name?: string;
  /** Count shown after a lapidary "·". */
  count?: number | string;
  /** Whether the viewer added this reaction (warms to gold). */
  active?: boolean;
  /** Glyph node; defaults to the gold commend star. Pass a Lucide icon for others. */
  icon?: React.ReactNode;
}

/** A "✦ Name · count" appreciation chip. */
export function Reaction(props: ReactionProps): JSX.Element;
