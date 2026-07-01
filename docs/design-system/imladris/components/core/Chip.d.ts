import * as React from 'react';

export interface ChipProps extends React.HTMLAttributes<HTMLSpanElement> {
  /** Topic status. 'needs' and 'needs_answer' are equivalent. */
  status?: 'solved' | 'needs' | 'needs_answer' | 'decision_made' | 'pinned' | 'locked' | 'archived';
  /** Optional leading glyph (e.g. a Lucide icon node). Inbox rows show text only. */
  icon?: React.ReactNode;
  /** Override the default label. */
  children?: React.ReactNode;
}

/** A topic-status pill — always carries a word, never colour alone. */
export function Chip(props: ChipProps): JSX.Element;
