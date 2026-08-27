import * as React from 'react';

export interface ParticipantStackProps extends React.HTMLAttributes<HTMLSpanElement> {
  /** Members as bare names, or objects: `username` seeds the monogram swatch,
   *  `href` makes the avatar a link, `title` names it for hover and AT, and
   *  `gilt` gives the topic's opener the gold ring. */
  members: Array<{ name: string; username?: string; href?: string; title?: string; gilt?: boolean } | string>;
  /** Max avatars to show before "+N". Default 5. */
  max?: number;
  /** Override the overflow count. */
  extra?: number;
}

/** Overlapping participant avatars with a "+N" overflow (topic header). */
export function ParticipantStack(props: ParticipantStackProps): JSX.Element;
