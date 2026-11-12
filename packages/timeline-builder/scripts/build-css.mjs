import { cpSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = dirname(fileURLToPath(import.meta.url));
cpSync(join(dir, '../src/styles.css'), join(dir, '../dist/builder.css'));
console.log('Built builder.css');
