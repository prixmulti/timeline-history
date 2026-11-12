/**
 * Build (optional via --no-build) and zip a clean WordPress plugin folder
 * that always includes assets/dist and blocks/timeline/build.
 */
import { spawnSync } from 'node:child_process';
import {
  cpSync,
  existsSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  statSync,
  createWriteStream,
  writeFileSync,
} from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const pluginSrc = join(root, 'wordpress-plugin/timeline-history');
const outDir = join(root, 'dist');
const staging = join(outDir, 'timeline-history');
const zipPath = join(outDir, 'timeline-history.zip');
const skipBuild = process.argv.includes('--no-build');

const SKIP_DIRS = new Set([
  'node_modules',
  '.cache',
  '.git',
  '.DS_Store',
]);

const SKIP_FILES = new Set(['.DS_Store', 'package-lock.json']);

function fail(message) {
  console.error(message);
  process.exit(1);
}

function runBuild() {
  console.log('Running npm run build…');
  const result = spawnSync('npm', ['run', 'build'], {
    cwd: root,
    stdio: 'inherit',
    shell: process.platform === 'win32',
  });
  if (result.status !== 0) {
    fail('Build failed; aborting pack.');
  }
}

function assertBuiltAssets() {
  const required = [
    join(pluginSrc, 'assets/dist/view.js'),
    join(pluginSrc, 'assets/dist/timeline.css'),
    join(pluginSrc, 'assets/dist/builder.js'),
    join(pluginSrc, 'assets/dist/builder.css'),
    join(pluginSrc, 'blocks/timeline/build/index.js'),
    join(pluginSrc, 'blocks/timeline/build/index.asset.php'),
  ];
  for (const file of required) {
    if (!existsSync(file)) {
      fail(`Missing built asset: ${relative(root, file)}. Run npm run build first.`);
    }
  }
}

function shouldSkip(name) {
  return SKIP_DIRS.has(name) || SKIP_FILES.has(name);
}

function copyTree(src, dest) {
  mkdirSync(dest, { recursive: true });
  for (const entry of readdirSync(src)) {
    if (shouldSkip(entry)) {
      continue;
    }
    const from = join(src, entry);
    const to = join(dest, entry);
    const st = statSync(from);
    if (st.isDirectory()) {
      copyTree(from, to);
    } else {
      cpSync(from, to);
    }
  }
}

async function zipDirectory(sourceDir, zipFile) {
  // Prefer system zip when available (fast, no deps).
  const zipCmd = spawnSync('zip', ['-r', '-q', zipFile, 'timeline-history'], {
    cwd: dirname(sourceDir),
    stdio: 'inherit',
  });
  if (zipCmd.status === 0) {
    return;
  }

  // Fallback: archiver if installed, else fail with clear message.
  try {
    const require = createRequire(import.meta.url);
    const archiver = require('archiver');
    await new Promise((resolve, reject) => {
      const output = createWriteStream(zipFile);
      const archive = archiver('zip', { zlib: { level: 9 } });
      output.on('close', resolve);
      archive.on('error', reject);
      archive.pipe(output);
      archive.directory(sourceDir, 'timeline-history');
      archive.finalize();
    });
  } catch {
    fail(
      'Could not create zip. Install system `zip`, or add the `archiver` npm package.'
    );
  }
}

/** WordPress reads Version from the plugin header — keep in sync with TIMELINE_HISTORY_VERSION. */
function syncPluginHeaderVersion() {
  const main = join(pluginSrc, 'timeline-history.php');
  const content = readFileSync(main, 'utf8');
  const match = content.match(
    /define\s*\(\s*['"]TIMELINE_HISTORY_VERSION['"],\s*['"]([^'"]+)['"]\s*\)/
  );
  if (!match) {
    fail('Could not find TIMELINE_HISTORY_VERSION in timeline-history.php');
  }
  const version = match[1];
  const updated = content.replace(
    /(\*\s*Version:\s*)\S+/,
    `$1${version}`
  );
  if (updated === content) {
    if (!content.includes(`Version:           ${version}`)) {
      fail(
        `Plugin header Version does not match TIMELINE_HISTORY_VERSION (${version}).`
      );
    }
    return;
  }
  writeFileSync(main, updated);
  console.log(`Synced plugin header Version → ${version}`);
}

if (!skipBuild) {
  runBuild();
}

syncPluginHeaderVersion();
assertBuiltAssets();

rmSync(staging, { recursive: true, force: true });
rmSync(zipPath, { force: true });
mkdirSync(outDir, { recursive: true });
copyTree(pluginSrc, staging);

await zipDirectory(staging, zipPath);

console.log(`Packed plugin: ${relative(root, zipPath)}`);
console.log('Upload this zip in WordPress → Plugins → Add New → Upload Plugin.');
