import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false,
  build: {
    emptyOutDir: false,
    outDir: 'public/assets',
    assetsDir: '.',
    rollupOptions: {
      input: 'src/client/wysiwyg/index.ts',
      output: {
        entryFileNames: 'wysiwyg-composer.js',
        codeSplitting: false,
        assetFileNames: (assetInfo) => {
          return assetInfo.name && assetInfo.name.endsWith('.css')
            ? 'wysiwyg-composer.css'
            : '[name][extname]';
        },
      },
    },
  },
});
