/**
 * Копирует сырые AI-вымпелы (с шахматкой, без обрезки) в assets фронта.
 *
 *   node local/tools/import_pennant_ai_raw.mjs
 *   node local/tools/import_pennant_ai_raw.mjs --slug=ger
 */

import { copyFileSync, existsSync, mkdirSync, readdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';
import { WC26_PENNANT_FLAG_PROMPTS } from './pennant_ai_prompts.js';

const __dir = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dir, '../..');
const AI_DIR = join(__dir, 'assets/pennants/ai');
const CURSOR_ASSETS = join(ROOT, '.cursor/projects/d-OSPanel-home-prognos9ys/assets');
const OUT_DIR = join(ROOT, 'frontend/src/assets/collectibles/pennants');

function resolveSrc(slug) {
  const names = [
    join(AI_DIR, `pennant_wc26_${slug}_ai.png`),
    join(CURSOR_ASSETS, `pennant_wc26_${slug}_ai.png`),
  ];
  return names.find((p) => existsSync(p)) || null;
}

function parseSlugs(argv) {
  const picked = [];
  for (let i = 2; i < argv.length; i++) {
    if (argv[i].startsWith('--slug=')) picked.push(argv[i].slice(7));
  }
  return picked.length ? picked : Object.keys(WC26_PENNANT_FLAG_PROMPTS);
}

mkdirSync(OUT_DIR, { recursive: true });

let ok = 0;
let miss = 0;
for (const slug of parseSlugs(process.argv)) {
  const src = resolveSrc(slug);
  const dst = join(OUT_DIR, `pennant_wc26_${slug}.png`);
  if (!src) {
    console.log('MISS', slug);
    miss++;
    continue;
  }
  copyFileSync(src, dst);
  console.log('OK', slug, '->', dst);
  ok++;
}

console.log(`done: ${ok} copied, ${miss} missing`);
