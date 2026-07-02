import { defaultValueCtx, Editor, editorViewCtx, rootCtx } from '@milkdown/core';
import { listener, listenerCtx } from '@milkdown/plugin-listener';
import { history } from '@milkdown/plugin-history';
import { commonmark } from '@milkdown/preset-commonmark';
import { gfm } from '@milkdown/preset-gfm';
import { closeHistory } from '@milkdown/prose/history';
import type { Node as ProseMirrorNode } from '@milkdown/prose/model';
import { Plugin, PluginKey } from '@milkdown/prose/state';
import { Decoration, DecorationSet, type EditorView } from '@milkdown/prose/view';
import { $prose, getMarkdown, insert, markdownToSlice, replaceAll } from '@milkdown/utils';

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

type ReferenceState = {
  trigger: '@' | '#';
  query: string;
  start: number;
  end: number;
};

type ComposerSuggestionItem = {
  type?: string;
  label?: string;
  token?: string;
  url?: string;
  markdown?: string;
};

type InternalPasteTarget = {
  path: string;
  query: string;
  fallbackMarkdown: string;
};

const MAX_NOTIFYING_MENTIONS = 10;
const chipPluginKey = 'retroboards-composer-chips';

function isCodeText(node: ProseMirrorNode, parent: ProseMirrorNode | null): boolean {
  if (parent?.type.spec.code) {
    return true;
  }
  return node.marks.some((mark) => mark.type.name === 'inlineCode' || mark.type.name === 'code');
}

function sameOriginPath(value: string): string {
  try {
    const url = new URL(value, window.location.origin);
    if (url.origin !== window.location.origin) {
      return '';
    }
    return url.pathname + url.hash;
  } catch {
    return '';
  }
}

function isInternalReferencePath(path: string): boolean {
  return /^\/c\/[A-Za-z0-9_-]+$/.test(path)
    || /^\/tags\/[A-Za-z0-9_-]+$/.test(path)
    || /^\/t\/\d+-[A-Za-z0-9-]+(?:#p\d+)?$/.test(path);
}

function markdownLabel(label: string): string {
  return label.replace(/\\/g, '\\\\').replace(/\[/g, '\\[').replace(/\]/g, '\\]');
}

function normalizeSerializedMarkdown(markdown: string): string {
  return markdown.replace(/\n+$/, '');
}

function buildChipDecorations(doc: ProseMirrorNode): DecorationSet {
  const decorations: Decoration[] = [];
  let mentionCount = 0;

  doc.descendants((node, pos, parent) => {
    if (!node.isText || !node.text) {
      return true;
    }

    const text = node.text;
    const link = node.marks.find((mark) => mark.type.name === 'link');
    if (link) {
      const path = sameOriginPath(String(link.attrs.href || ''));
      if (isInternalReferencePath(path)) {
        decorations.push(Decoration.inline(pos, pos + text.length, { class: 'composer-chip' }));
      }
      return true;
    }

    if (isCodeText(node, parent)) {
      return true;
    }

    const mentionPattern = /(^|[^\w@])@([A-Za-z0-9_]{1,32})\b/g;
    let match: RegExpExecArray | null;
    while ((match = mentionPattern.exec(text)) !== null) {
      const prefixLength = match[1]?.length ?? 0;
      const from = pos + match.index + prefixLength;
      const to = from + match[2].length + 1;
      mentionCount += 1;
      decorations.push(Decoration.inline(from, to, {
        class: mentionCount > MAX_NOTIFYING_MENTIONS ? 'composer-chip is-muted' : 'composer-chip',
      }));
    }

    return true;
  });

  return DecorationSet.create(doc, decorations);
}

function richComposerPlugin(adapter: MilkdownComposerAdapter) {
  return $prose(() => new Plugin({
    key: new PluginKey(chipPluginKey),
    props: {
      decorations(state) {
        return buildChipDecorations(state.doc);
      },
      handlePaste(view, event) {
        return adapter.handleInternalPaste(view, event);
      },
    },
  }));
}

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
  private pasteSeq = 0;

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
      return this.editor ? normalizeSerializedMarkdown(this.editor.action(getMarkdown())) : this.textarea.value;
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

  referenceTargets(): HTMLElement[] {
    return [this.host, this.textarea];
  }

  referenceState(): ReferenceState | null {
    if (!this.richMode || this.failed || this.destroyed) {
      return this.textareaReferenceState();
    }
    const view = this.currentView();
    if (!view) {
      return null;
    }
    const { selection } = view.state;
    if (!selection.empty) {
      return null;
    }
    const pos = selection.from;
    const from = Math.max(0, pos - 90);
    const before = view.state.doc.textBetween(from, pos, '\n', '\n');
    const match = before.match(/(^|[\s(])([@#])([A-Za-z0-9_-]{1,80})$/);
    if (!match) {
      return null;
    }
    const trigger = match[2] as '@' | '#';
    const query = match[3];
    const line = before.slice(before.lastIndexOf('\n') + 1);
    if (trigger === '#' && /^\s*#{1,3}\s?$/.test(line)) {
      return null;
    }
    const parent = selection.$from.parent;
    if (parent.type.spec.code) {
      return null;
    }
    return {
      trigger,
      query,
      start: pos - trigger.length - query.length,
      end: pos,
    };
  }

  replaceReferenceSelection(state: ReferenceState, item: ComposerSuggestionItem): void {
    const markdown = item.markdown || item.token || item.label || '';
    if (markdown === '') {
      return;
    }
    if (!this.richMode || this.failed || this.destroyed) {
      this.textarea.selectionStart = state.start;
      this.textarea.selectionEnd = state.end;
      this.fallback.replaceSelection(markdown);
      return;
    }
    this.replaceEditorRangeWithMarkdown({ from: state.start, to: state.end }, markdown, true);
  }

  handleInternalPaste(view: EditorView, event: ClipboardEvent): boolean {
    if (!this.richMode || this.failed || this.destroyed) {
      return false;
    }
    const raw = event.clipboardData?.getData('text/plain')?.trim() || '';
    if (raw === '') {
      return false;
    }
    const target = this.internalPasteTarget(raw);
    if (!target) {
      return false;
    }
    const seq = ++this.pasteSeq;
    window.setTimeout(() => {
      this.rewriteInternalPaste(view, raw, target, seq);
    }, 0);
    return false;
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
      .use(richComposerPlugin(this))
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
    markdown = normalizeSerializedMarkdown(markdown);
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
      const markdown = normalizeSerializedMarkdown(this.editor.action(getMarkdown()));
      this.writeTextarea(markdown);
      this.emit(markdown);
    }).catch(() => {});
  }

  private currentView(): EditorView | null {
    try {
      return this.editor ? this.editor.action((ctx) => ctx.get(editorViewCtx)) : null;
    } catch {
      return null;
    }
  }

  private textareaReferenceState(): ReferenceState | null {
    if (this.textarea.selectionStart !== this.textarea.selectionEnd) {
      return null;
    }
    const pos = this.textarea.selectionStart || 0;
    const before = this.textarea.value.slice(0, pos);
    const match = before.match(/(^|[\s(])([@#])([A-Za-z0-9_-]{1,80})$/);
    if (!match) {
      return null;
    }
    const trigger = match[2] as '@' | '#';
    const query = match[3];
    const line = before.slice(before.lastIndexOf('\n') + 1);
    if (trigger === '#' && /^\s*#{1,3}\s?$/.test(line)) {
      return null;
    }
    if (((before.match(/^```/gm) || []).length % 2) === 1) {
      return null;
    }
    return {
      trigger,
      query,
      start: pos - trigger.length - query.length,
      end: pos,
    };
  }

  private replaceEditorRangeWithMarkdown(range: { from: number; to: number }, markdown: string, refocus = false): void {
    this.dirty = true;
    this.ready.then(() => {
      if (this.destroyed || this.failed || !this.editor) {
        return;
      }
      this.editor.action((ctx) => {
        const view = ctx.get(editorViewCtx);
        const slice = markdownToSlice(markdown)(ctx);
        view.dispatch(closeHistory(view.state.tr.replace(range.from, range.to, slice).scrollIntoView()));
      });
      this.syncRichMarkdown();
      if (refocus) {
        this.focus();
      }
    }).catch(() => {
      this.fallback.replaceSelection(markdown);
    });
  }

  private internalPasteTarget(raw: string): InternalPasteTarget | null {
    let url: URL;
    try {
      url = new URL(raw, window.location.origin);
    } catch {
      return null;
    }
    if (url.origin !== window.location.origin) {
      return null;
    }

    const path = url.pathname.replace(/\/+$/, '') || '/';
    const hash = /^#p\d+$/.test(url.hash) ? url.hash : '';
    const board = path.match(/^\/c\/([A-Za-z0-9_-]+)$/);
    if (board) {
      const slug = board[1];
      return {
        path,
        query: slug,
        fallbackMarkdown: `[#${slug}](${path})`,
      };
    }

    const tag = path.match(/^\/tags\/([A-Za-z0-9_-]+)$/);
    if (tag) {
      const slug = tag[1];
      return {
        path,
        query: slug,
        fallbackMarkdown: `[#${slug}](${path})`,
      };
    }

    const topic = path.match(/^\/t\/(\d+)-([A-Za-z0-9-]+)$/);
    if (topic) {
      const slug = topic[2];
      const label = slug.split('-').filter(Boolean).join(' ') || `Topic ${topic[1]}`;
      return {
        path: path + hash,
        query: slug,
        fallbackMarkdown: `[${markdownLabel(label)}](${path + hash})`,
      };
    }

    return null;
  }

  private async markdownForInternalPaste(target: InternalPasteTarget): Promise<string> {
    const params = new URLSearchParams({
      trigger: '#',
      q: target.query,
      context: this.form.getAttribute('data-composer-context') || '',
      target_id: this.form.getAttribute('data-composer-target-id') || '0',
    });

    try {
      const response = await fetch(`/composer/suggest?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) {
        return target.fallbackMarkdown;
      }
      const json = await response.json() as { ok?: boolean; items?: ComposerSuggestionItem[] };
      const items = Array.isArray(json.items) ? json.items : [];
      const targetWithoutHash = target.path.replace(/#p\d+$/, '');
      const match = items.find((item) => {
        const itemPath = sameOriginPath(item.url || '');
        return itemPath === target.path || itemPath === targetWithoutHash;
      });
      return match?.markdown || target.fallbackMarkdown;
    } catch {
      return target.fallbackMarkdown;
    }
  }

  private async rewriteInternalPaste(view: EditorView, raw: string, target: InternalPasteTarget, seq: number): Promise<void> {
    const markdown = await this.markdownForInternalPaste(target);
    if (seq !== this.pasteSeq || this.destroyed || this.failed) {
      return;
    }
    this.ready.then(() => {
      const activeView = this.currentView() || view;
      const range = this.findRecentTextRange(activeView, raw);
      if (!range) {
        return;
      }
      this.replaceEditorRangeWithMarkdown(range, markdown);
    }).catch(() => {});
  }

  private findRecentTextRange(view: EditorView, raw: string): { from: number; to: number } | null {
    const end = view.state.selection.from;
    const start = Math.max(0, end - raw.length);
    if (start < end && view.state.doc.textBetween(start, end, '\n', '\n') === raw) {
      return { from: start, to: end };
    }

    let found: { from: number; to: number } | null = null;
    view.state.doc.descendants((node, pos) => {
      if (!node.isText || !node.text) {
        return true;
      }
      const index = node.text.indexOf(raw);
      if (index >= 0) {
        found = { from: pos + index, to: pos + index + raw.length };
        return false;
      }
      return true;
    });
    return found;
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
