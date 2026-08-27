import * as React from 'react';

export interface ReactionProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Reaction name: Commend (default), Kindled, Seconded, Illuminating. Pass
   *  `null` for the raw-emoji form — production has no named set, so a post's
   *  own reactions are glyph + count and the label span is dropped. */
  name?: string | null;
  /** Count shown after a lapidary "·". */
  count?: number | string;
  /** Whether the viewer added this reaction (warms to gold). */
  active?: boolean;
  /** Glyph node; defaults to the gold commend star. Pass a Lucide icon for others. */
  icon?: React.ReactNode;
}

/** A "✦ Name · count" appreciation chip — or `icon` + `count` with `name={null}`
 *  for the raw-emoji reactions a post carries. */
export function Reaction(props: ReactionProps): JSX.Element;
