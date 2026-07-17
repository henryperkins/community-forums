import * as React from 'react';

export interface ComposerUpload {
  name: string;
  /** Thumbnail URL; omitted renders the empty 48px well. */
  thumb?: string;
  /** e.g. "Uploading forge.png..." · "Uploaded image 1280x854." */
  status?: string;
  /** 0–100 shows the progress bar. */
  progress?: number;
  failed?: boolean;
  /** Present (even "") renders the alt-text input. */
  alt?: string;
}

export interface ComposerProps extends React.FormHTMLAttributes<HTMLFormElement> {
  /** The mount. One shell, identical feature surface everywhere. Default 'reply'. */
  context?: 'reply' | 'new_thread' | 'dm' | 'edit';
  placeholder?: string;
  /** Post ~20000 · DM ~4000. Default 20000. */
  maxLength?: number;
  value?: string;
  defaultValue?: string;
  onChange?: React.ChangeEventHandler<HTMLTextAreaElement>;
  /** aria-label of the circular quill send. 'Reply' | 'Post' | 'Send' | 'Save changes'. Default 'Reply'. */
  submitLabel?: string;
  /** Display name for the "as …" identity line. Omit to hide. */
  identity?: string;
  identitySeed?: string;
  /** Honors the user's show_avatars preference. Default true. */
  showAvatar?: boolean;
  /** Board allows masked-identity posting — renders the Anonymous chip + disclosure. */
  allowAnonymous?: boolean;
  anonymousChecked?: boolean;
  anonymousDisclosure?: string;
  /** The Aa formatting-row state (production default: open). */
  toolbarOpen?: boolean;
  /** Toolbar keys shown active: 'bold' | 'italic' | 'strike' | 'code' | 'quote' | 'h2' | 'list' | 'orderedList' | 'codeblock' | 'spoiler' | 'link'. */
  activeFormats?: string[];
  /** Validation error, shown inside the box above the input. */
  error?: string;
  uploads?: ComposerUpload[];
  /** "Draft saved · Discard" in the meta row. */
  draftSaved?: boolean;
  /** "18,204 / 20,000" — production shows it from 90% of the limit. */
  count?: string;
  countOver?: boolean;
  previewOpen?: boolean;
  /** Server-rendered preview (same pipeline + .formatted-content contract as posts). */
  previewContent?: React.ReactNode;
  /** is-submitting: quill spins, send disabled, SR announces "Sending…". */
  submitting?: boolean;
  disabled?: boolean;
  /** e.g. the locked-mid-compose notice; the draft is kept. */
  disabledNotice?: string;
  /** Wrapper slot above the box — Title + board picker (new_thread), recipients (dm). */
  header?: React.ReactNode;
  actionsStart?: React.ReactNode;
  actionsEnd?: React.ReactNode;
}

/**
 * The shared composer shell (COMPOSER.md v0.8; composer_shell.php): one
 * contained box for all four mounts — engraved icon formatting row (Aa
 * toggles it), attach ＋ / emoji 😊, "as Name" identity, Anonymous chip,
 * Preview, the circular ✒ send — with draft state, disclosure, and the
 * near-limit counter below. In production the textarea is canonical
 * Markdown with WYSIWYG mounted over it, forms carry CSRF + a fresh
 * idempotency key, and send navigates (no optimistic send — ADR 0020).
 */
export declare function Composer(props: ComposerProps): React.ReactElement;
