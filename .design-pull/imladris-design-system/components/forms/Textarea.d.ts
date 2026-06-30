import * as React from 'react';

export interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  /** Renders a labelled field wrapper above the textarea. */
  label?: string;
}

/** Serif multi-line field (composer / forms) with a gold focus halo. */
export function Textarea(props: TextareaProps): JSX.Element;
