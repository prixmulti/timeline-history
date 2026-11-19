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
/** Zip folder name / wp.org slug – must not collide with existing directory plugins (e.g. timeline-history). */
/** Must match wordpress.org plugin slug (no hyphens). */
const DISTRIBUTION_SLUG = 'arkivtimeline';
const outDir = join(root, 'dist');
const staging = join(outDir, DISTRIBUTION_SLUG);
const zipPath = join(outDir, `${DISTRIBUTION_SLUG}.zip`);
const skipBuild = process.argv.includes('--no-build');
const skipBump = process.argv.includes('--no-bump');
const setVersionArg = process.argv.find((a) => a.startsWith('--version='));
const setVersion = setVersionArg ? setVersionArg.slice('--version='.length).trim() : '';

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
    join(pluginSrc, 'timeline-history.php'),
    join(pluginSrc, 'includes/class-plugin.php'),
    join(pluginSrc, 'includes/class-assets.php'),
    join(pluginSrc, 'includes/class-shortcode.php'),
    join(pluginSrc, 'includes/rest/class-stories-controller.php'),
    join(pluginSrc, 'readme.txt'),
    join(pluginSrc, 'assets/dist/view.js'),
    join(pluginSrc, 'assets/dist/view.asset.php'),
    join(pluginSrc, 'assets/dist/timeline.css'),
    join(pluginSrc, 'assets/dist/builder.js'),
    join(pluginSrc, 'assets/dist/builder.asset.php'),
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
  const zipCmd = spawnSync('zip', ['-r', '-q', zipFile, DISTRIBUTION_SLUG], {
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
      archive.directory(sourceDir, DISTRIBUTION_SLUG);
      archive.finalize();
    });
  } catch {
    fail(
      'Could not create zip. Install system `zip`, or add the `archiver` npm package.'
    );
  }
}

function readPluginVersion() {
  const main = join(pluginSrc, 'timeline-history.php');
  const content = readFileSync(main, 'utf8');
  const match = content.match(
    /define\s*\(\s*['"]TIMELINE_HISTORY_VERSION['"],\s*['"]([^'"]+)['"]\s*\)/
  );
  if (!match) {
    fail('Could not find TIMELINE_HISTORY_VERSION in timeline-history.php');
  }
  return { main, content, version: match[1] };
}

function parseSemver(version) {
  const m = version.match(/^(\d+)\.(\d+)\.(\d+)(.*)$/);
  if (!m) return null;
  return {
    major: Number(m[1]),
    minor: Number(m[2]),
    patch: Number(m[3]),
    suffix: m[4] || '',
  };
}

function bumpPatchVersion(version) {
  const p = parseSemver(version);
  if (!p) {
    fail(`Version is not semver (expected x.y.z): ${version}`);
  }
  return `${p.major}.${p.minor}.${p.patch + 1}${p.suffix}`;
}

/** Opdater define + plugin header (WordPress plugins-liste + cache-bust på assets). */
function writePluginVersion(version) {
  const { main, content } = readPluginVersion();
  let updated = content.replace(
    /define\s*\(\s*['"]TIMELINE_HISTORY_VERSION['"],\s*['"][^'"]+['"]\s*\)/,
    `define( 'TIMELINE_HISTORY_VERSION', '${version}' )`
  );
  updated = updated.replace(/(\*\s*Version:\s*)\S+/, `$1${version}`);
  if (updated === content) {
    fail(`Could not write plugin version ${version} to timeline-history.php`);
  }
  writeFileSync(main, updated);
}

function resolvePackVersion() {
  const { version: current } = readPluginVersion();
  if (setVersion) {
    if (!parseSemver(setVersion)) {
      fail(`Invalid --version= value (use x.y.z): ${setVersion}`);
    }
    return { from: current, next: setVersion, bumped: current !== setVersion };
  }
  if (skipBump) {
    return { from: current, next: current, bumped: false };
  }
  const next = bumpPatchVersion(current);
  return { from: current, next, bumped: true };
}

const packVersion = resolvePackVersion();
if (packVersion.bumped) {
  writePluginVersion(packVersion.next);
  console.log(`Plugin version: ${packVersion.from} → ${packVersion.next}`);
} else {
  console.log(`Plugin version: ${packVersion.next} (unchanged)`);
}

if (!skipBuild) {
  runBuild();
}
assertBuiltAssets();

rmSync(staging, { recursive: true, force: true });
rmSync(zipPath, { force: true });
mkdirSync(outDir, { recursive: true });
copyTree(pluginSrc, staging);

await zipDirectory(staging, zipPath);

console.log(`Packed ArkivTimeline v${packVersion.next}: ${relative(root, zipPath)}`);
console.log('Upload this zip in WordPress → Plugins → Add New → Upload Plugin.');
console.log('Tip: --no-bump skips version increment; --version=1.2.3 sets an explicit version.');
