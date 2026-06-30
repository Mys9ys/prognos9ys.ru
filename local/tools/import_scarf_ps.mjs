/**
 * Копирует шарфы ЧМ-26 после Photoshop (прозрачный фон) в assets фронта.
 *
 *   node local/tools/import_scarf_ps.mjs
 *   node local/tools/import_scarf_ps.mjs --slug=fra
 */

import { copyFileSync, existsSync, mkdirSync, readdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dir = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dir, '../..');
const PS_DIR = join(__dir, 'output/scarfs/ps');
const OUT_DIR = join(ROOT, 'frontend/src/assets/collectibles/scarfs');

function parseSlugs(argv) {
  const picked = [];
  for (let i = 2; i < argv.length; i++) {
    if (argv[i].startsWith('--slug=')) {
      picked.push(argv[i].slice(7));
    }
  }
  return picked;
}

function resolveSrc(slug) {
  const names = [
    join(PS_DIR, `scarf_wc26_${slug}_v2.png`),
    join(PS_DIR, `scarf_wc26_${slug}.png`),
  ];
  return names.find((p) => existsSync(p)) || null;
}

function listSlugsFromDir() {
  return readdirSync(PS_DIR)
    .map((name) => name.match(/^scarf_wc26_([a-z0-9]+)(?:_v2)?\.png$/))
    .filter(Boolean)
    .map((match) => match[1]);
}

mkdirSync(OUT_DIR, { recursive: true });

const slugs = parseSlugs(process.argv);
const targets = slugs.length ? slugs : [...new Set(listSlugsFromDir())].sort();

let ok = 0;
let miss = 0;

for (const slug of targets) {
  const src = resolveSrc(slug);
  const dst = join(OUT_DIR, `scarf_wc26_${slug}.png`);

  if (!src) {
    console.log('MISS', slug);
    miss += 1;
    continue;
  }

  copyFileSync(src, dst);
  console.log('OK', slug, '->', dst);
  ok += 1;
}

console.log(`done: ${ok} copied, ${miss} missing`);
