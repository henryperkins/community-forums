import * as React from 'react';

export interface ComposerProps extends React.FormHTMLAttributes<HTMLFormElement> {
  /** Name shown in the "Posting as …" strip. Omit for none. */
  postingAs?: string;
  placeholder?: string;
  /** Show the Markdown formatting toolbar. Default true. */
  toolbar?: boolean;
  /** Send button label. Default "Reply" (use "Post topic" for new topics). */
  sendLabel?: string;
  /** Optional "n / max" counter string. */
  count?: string;
  value?: string;
  defaultValue?: string;
  onChange?: React.ChangeEventHandler<HTMLTextAreaElement>;
}

/** The reply / new-topic composer card. */
export function Composer(props: ComposerProps): JSX.Element;
