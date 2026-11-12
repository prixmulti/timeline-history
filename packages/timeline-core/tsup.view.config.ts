import { defineConfig } from 'tsup';

export default defineConfig({
  entry: ['src/view.ts'],
  format: ['iife'],
  globalName: 'TimelineHistoryView',
  minify: true,
  // Bundle production React and replace process.env so the IIFE runs on the WP frontend.
  env: {
    NODE_ENV: 'production',
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  noExternal: [/./],
  outDir: 'dist',
  outExtension: () => ({ js: '.js' }),
});
