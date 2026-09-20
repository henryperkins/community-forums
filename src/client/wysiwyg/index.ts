import './styles.css';

// composer.js calls this only for a real composer input and owns rejection
// handling for both this entry and the heavier editor dependency.
export async function loadWysiwygAdapter() {
  const module = await import('./milkdown-adapter');
  return module.createMilkdownComposerAdapter;
}
