import { defineConfig } from 'tsup';

export default defineConfig({
  entry: ['src/wp-standalone.tsx'],
  format: ['iife'],
  globalName: 'TimelineHistoryBuilder',
  minify: true,
  // Bundle production React and replace process.env so the IIFE runs in WP admin.
  env: {
    NODE_ENV: 'production',
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  noExternal: [/./],
  outDir: 'dist',
  outExtension: () => ({ js: '.wp.js' }),
});
