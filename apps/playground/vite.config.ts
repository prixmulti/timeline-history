import path from 'node:path';
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const root = path.dirname(fileURLToPath(import.meta.url));
const coreSrc = path.resolve(root, '../../packages/timeline-core/src');
const builderSrc = path.resolve(root, '../../packages/timeline-builder/src');

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    strictPort: false,
  },
  resolve: {
    alias: [
      {
        find: '@timeline-history/timeline-core/styles.css',
        replacement: path.join(coreSrc, 'styles/timeline.css'),
      },
      {
        find: '@timeline-history/timeline-core',
        replacement: path.join(coreSrc, 'index.ts'),
      },
      {
        find: '@timeline-history/timeline-builder',
        replacement: path.join(builderSrc, 'index.tsx'),
      },
    ],
  },
});
