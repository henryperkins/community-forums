import * as React from 'react';

export interface CalloutProps extends React.HTMLAttributes<HTMLElement> {
  /** Recolours the rule + wash. Default 'note'. */
  tone?: 'note' | 'info' | 'warn' | 'danger' | 'quiet';
  /** 'rule' (gold left-rule, default) or 'panel' (full hairline box). */
  variant?: 'rule' | 'panel';
  /** Tracked-caps eyebrow. */
  label?: React.ReactNode;
  /** Optional display title. */
  title?: React.ReactNode;
  children?: React.ReactNode;
}

/** An aside for notes, acceptance criteria, flows, and warnings. */
export function Callout(props: CalloutProps): JSX.Element;
