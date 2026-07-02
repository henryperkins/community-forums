import './styles.css';

const w = window as unknown as {
  RetroBoardsComposer?: {
    registerWysiwygAdapter(factory: unknown): void;
  };
};

if (document.body.getAttribute('data-wysiwyg-composer') === '1' && w.RetroBoardsComposer) {
  import('./milkdown-adapter').then((module) => {
    w.RetroBoardsComposer?.registerWysiwygAdapter(module.createMilkdownComposerAdapter);
  }).catch(() => {
    // Textarea adapter remains active.
  });
}
