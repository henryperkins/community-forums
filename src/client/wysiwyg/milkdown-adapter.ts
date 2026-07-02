import { defaultValueCtx, Editor, rootCtx } from '@milkdown/core';
import { listener, listenerCtx } from '@milkdown/plugin-listener';
import { history } from '@milkdown/plugin-history';
import { commonmark } from '@milkdown/preset-commonmark';
import { gfm } from '@milkdown/preset-gfm';
import { getMarkdown, insert, replaceAll } from '@milkdown/utils';

type FallbackAdapter = {
  getMarkdown(): string;
  setMarkdown(markdown: string): void;
  insertMarkdown(markdown: string): void;
  replaceSelection(markdown: string): void;
  replacePendingUpload(token: string, markdown: string): boolean;
  focus(): void;
  onChange(callback: (markdown: string) => void): void;
  setDisabled(disabled: boolean): void;
};

class MilkdownComposerAdapter {
  readonly ta: HTMLTextAreaElement;

  private editor: Editor | null = null;
  private ready: Promise<void>;
  private changeHandlers: Array<(markdown: string) => void> = [];
  private richMode = true;
  private dirty = false;
  private failed = false;
  private destroyed = false;
  private sourceInput: () => void;
  private richInput: () => void;
  private host: HTMLDivElement;
  private toggle: HTMLButtonElement;
  private wasRequired: boolean;

  constructor(
    private form: HTMLFormElement,
    private textarea: HTMLTextAreaElement,
    private fallback: FallbackAdapter,
  ) {
    this.ta = textarea;
    this.wasRequired = textarea.required;
    this.host = document.createElement('div');
    this.host.className = 'wysiwyg-composer';

    this.toggle = document.createElement('button');
    this.toggle.type = 'button';
    this.toggle.className = 'btn btn-secondary btn-small wysiwyg-source-toggle';
    this.toggle.textContent = 'Source';

    textarea.parentNode?.insertBefore(this.host, textarea);
    textarea.parentNode?.insertBefore(this.toggle, textarea.nextSibling);
    textarea.classList.add('is-wysiwyg-source-hidden');
    textarea.required = false;
    this.toggle.addEventListener('click', () => this.toggleSourceMode());

    this.richInput = () => {
      if (!this.richMode || this.failed || this.destroyed) {
        return;
      }
      this.dirty = true;
      this.syncRichMarkdown();
    };
    this.sourceInput = () => {
      if (this.richMode) {
        return;
      }
      this.dirty = true;
      this.emit(this.textarea.value);
    };
    this.host.addEventListener('input', this.richInput, true);
    textarea.addEventListener('input', this.sourceInput);

    this.ready = this.createEditor();
  }

  getMarkdown(): string {
    if (this.failed || this.destroyed) {
      return this.fallback.getMarkdown();
    }
    if (!this.richMode || !this.dirty) {
      return this.textarea.value;
    }
    try {
      return this.editor ? this.editor.action(getMarkdown()) : this.textarea.value;
    } catch {
      return this.textarea.value;
    }
  }

  setMarkdown(markdown: string): void {
    this.dirty = true;
    this.writeTextarea(markdown || '');
    this.emit(this.textarea.value);
    this.ready.then(() => {
      this.editor?.action(replaceAll(this.textarea.value, true));
    }).catch(() => {});
  }

  insertMarkdown(markdown: string): void {
    this.replaceSelection(markdown);
  }

  replaceSelection(markdown: string): void {
    if (!this.richMode || this.failed || this.destroyed) {
      this.fallback.replaceSelection(markdown);
      return;
    }
    this.dirty = true;
    this.ready.then(() => {
      this.editor?.action(insert(markdown));
    }).catch(() => {
      this.fallback.replaceSelection(markdown);
    });
  }

  replacePendingUpload(token: string, markdown: string): boolean {
    const replaced = this.fallback.replacePendingUpload(token, markdown);
    if (!replaced) {
      return false;
    }
    this.dirty = true;
    this.emit(this.textarea.value);
    this.ready.then(() => {
      this.editor?.action(replaceAll(this.textarea.value, true));
    }).catch(() => {});
    return true;
  }

  focus(): void {
    if (!this.richMode || this.failed || this.destroyed) {
      this.textarea.focus();
      return;
    }
    const editor = this.host.querySelector<HTMLElement>('.ProseMirror');
    (editor || this.host).focus();
  }

  onChange(callback: (markdown: string) => void): void {
    this.changeHandlers.push(callback);
  }

  setDisabled(disabled: boolean): void {
    this.fallback.setDisabled(disabled);
    const editor = this.host.querySelector<HTMLElement>('.ProseMirror');
    if (editor) {
      editor.setAttribute('contenteditable', disabled ? 'false' : 'true');
    }
    this.toggle.disabled = disabled;
  }

  destroy(): void {
    if (this.destroyed) {
      return;
    }
    this.destroyed = true;
    this.textarea.removeEventListener('input', this.sourceInput);
    this.host.removeEventListener('input', this.richInput, true);
    this.textarea.classList.remove('is-wysiwyg-source-hidden');
    this.textarea.required = this.wasRequired;
    this.host.remove();
    this.toggle.remove();
    this.editor?.destroy().catch(() => {});
  }

  private createEditor(): Promise<void> {
    const initialMarkdown = this.textarea.value;
    this.editor = Editor.make()
      .config((ctx) => {
        ctx.set(rootCtx, this.host);
        ctx.set(defaultValueCtx, initialMarkdown);
        ctx.get(listenerCtx).markdownUpdated((_ctx, markdown) => {
          this.handleRichMarkdown(markdown);
        });
      })
      .use(commonmark)
      .use(gfm)
      .use(history)
      .use(listener);

    return this.editor.create().then(() => undefined).catch((error) => {
      this.failed = true;
      this.destroy();
      throw error;
    });
  }

  private handleRichMarkdown(markdown: string): void {
    if (this.destroyed || !this.richMode) {
      return;
    }
    this.dirty = true;
    this.writeTextarea(markdown);
    this.emit(markdown);
  }

  private toggleSourceMode(): void {
    if (this.failed || this.destroyed) {
      return;
    }
    if (this.richMode) {
      this.writeTextarea(this.getMarkdown(), false);
      this.richMode = false;
      this.host.hidden = true;
      this.textarea.classList.remove('is-wysiwyg-source-hidden');
      this.textarea.required = this.wasRequired;
      this.toggle.textContent = 'Rich text';
      this.textarea.focus();
      return;
    }

    const markdown = this.textarea.value;
    this.dirty = true;
    this.richMode = true;
    this.textarea.classList.add('is-wysiwyg-source-hidden');
    this.textarea.required = false;
    this.host.hidden = false;
    this.toggle.textContent = 'Source';
    this.ready.then(() => {
      this.editor?.action(replaceAll(markdown, true));
      this.focus();
    }).catch(() => {});
  }

  private writeTextarea(markdown: string, dispatch = true): void {
    this.textarea.value = markdown;
    if (dispatch) {
      this.textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }

  private emit(markdown: string): void {
    this.changeHandlers.forEach((callback) => callback(markdown));
  }

  private syncRichMarkdown(): void {
    this.ready.then(() => {
      if (this.destroyed || this.failed || !this.richMode || !this.editor) {
        return;
      }
      const markdown = this.editor.action(getMarkdown());
      this.writeTextarea(markdown);
      this.emit(markdown);
    }).catch(() => {});
  }
}

export function createMilkdownComposerAdapter(
  form: HTMLFormElement,
  textarea: HTMLTextAreaElement,
  fallback: FallbackAdapter,
): MilkdownComposerAdapter | null {
  try {
    return new MilkdownComposerAdapter(form, textarea, fallback);
  } catch {
    return null;
  }
}
