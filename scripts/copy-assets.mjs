import { cpSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, 'wordpress-plugin/timeline-history/assets/dist');

mkdirSync(dist, { recursive: true });

const copies = [
  [join(root, 'packages/timeline-core/dist/view.js'), join(dist, 'view.js')],
  [join(root, 'packages/timeline-core/dist/timeline.css'), join(dist, 'timeline.css')],
  [join(root, 'packages/timeline-builder/dist/wp-standalone.wp.js'), join(dist, 'builder.js')],
  [join(root, 'packages/timeline-builder/dist/builder.css'), join(dist, 'builder.css')],
];

for (const [src, dest] of copies) {
  if (existsSync(src)) {
    cpSync(src, dest);
    console.log(`Copied ${src} -> ${dest}`);
  } else {
    console.warn(`Skip (missing): ${src}`);
  }
}
