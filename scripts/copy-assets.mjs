import { cpSync, mkdirSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const dist = join(root, 'wordpress-plugin/timeline-history/assets/dist');

mkdirSync(dist, { recursive: true });

const copies = [
  [join(root, 'packages/timeline-core/dist/timeline.css'), join(dist, 'timeline.css')],
  // view.js, builder.js, builder.css, *.asset.php — built in-place by timeline-history-wp-assets
];

for (const [src, dest] of copies) {
  if (existsSync(src)) {
    cpSync(src, dest);
    console.log(`Copied ${src} -> ${dest}`);
  } else {
    console.warn(`Skip (missing): ${src}`);
  }
}
