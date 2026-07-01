import * as React from 'react';

export interface ParticipantStackProps extends React.HTMLAttributes<HTMLSpanElement> {
  /** Members as {name, username} or bare names. */
  members: Array<{ name: string; username?: string } | string>;
  /** Max avatars to show before "+N". Default 5. */
  max?: number;
  /** Override the overflow count. */
  extra?: number;
}

/** Overlapping participant avatars with a "+N" overflow (topic header). */
export function ParticipantStack(props: ParticipantStackProps): JSX.Element;
