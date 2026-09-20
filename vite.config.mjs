import { defineConfig } from 'vite';

export default defineConfig({
  base: '/assets/dist/',
  publicDir: false,
  build: {
    // Source assets stay editable; this directory belongs entirely to the build.
    emptyOutDir: true,
    outDir: 'public/assets/dist',
    assetsDir: '.',
    assetsInlineLimit: 0,
    manifest: true,
    rolldownOptions: {
      // The optional editor entry is imported by URL from the classic bridge.
      preserveEntrySignatures: 'exports-only',
      input: {
        'app-style': 'public/assets/app.css',
        'imladris-style': 'public/assets/imladris.css',
        'wysiwyg-composer': 'src/client/wysiwyg/index.ts',
      },
      output: {
        entryFileNames: '[name]-[hash].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name]-[hash][extname]',
      },
    },
  },
});
