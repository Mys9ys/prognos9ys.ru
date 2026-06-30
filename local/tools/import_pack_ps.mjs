/**
 * Паки коллекционок (после Photoshop — прозрачный фон).
 *
 *   node local/tools/import_pack_ps.mjs
 *   node local/tools/import_pack_ps.mjs --code=pack_pennant_wc26
 *
 * Источник (по приоритету):
 *   local/tools/output/packs/ps/{code}.png
 *   local/tools/assets/packs/{code}_foil.png
 */

import { copyFileSync, existsSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dir = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dir, '../..');
const PS_DIR = join(__dir, 'output/packs/ps');
const ASSETS_DIR = join(__dir, 'assets/packs');
const OUT_DIR = join(ROOT, 'frontend/src/assets/collectibles/packs');

const PACK_CODES = ['pack_pennant_wc26'];

function parseCodes(argv) {
  const picked = [];
  for (let i = 2; i < argv.length; i++) {
    if (argv[i].startsWith('--code=')) {
      picked.push(argv[i].slice(7));
    }
  }
  return picked.length ? picked : PACK_CODES;
}

function resolveSrc(code) {
  const names = [
    join(PS_DIR, `${code}.png`),
    join(PS_DIR, `${code}_foil.png`),
    join(ASSETS_DIR, `${code}_foil.png`),
  ];
  return names.find((p) => existsSync(p)) || null;
}

mkdirSync(OUT_DIR, { recursive: true });
mkdirSync(PS_DIR, { recursive: true });

let ok = 0;
let miss = 0;

for (const code of parseCodes(process.argv)) {
  const src = resolveSrc(code);
  const dst = join(OUT_DIR, `${code}.png`);

  if (!src) {
    console.log('MISS', code);
    miss += 1;
    continue;
  }

  copyFileSync(src, dst);
  console.log('OK', code, '<-', src);
  console.log('   ->', dst);
  ok += 1;
}

console.log(`done: ${ok} copied, ${miss} missing`);
