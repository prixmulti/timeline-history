import { cpSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = dirname(fileURLToPath(import.meta.url));
const src = join(dir, '../src/styles/timeline.css');
const dest = join(dir, '../dist/timeline.css');
cpSync(src, dest);
console.log('Built timeline.css');
