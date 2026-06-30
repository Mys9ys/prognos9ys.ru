/**
 * Постобработка AI-вымпелов: шахматка → прозрачность, trim, 256px.
 *
 *   node local/tools/process_pennant_ai_png.mjs <input.png> <output.png> [size=256]
 *   node local/tools/process_pennant_ai_png.mjs --pilot
 */

import { createRequire } from 'module';
import { existsSync, mkdirSync, readdirSync, copyFileSync } from 'fs';
import { dirname, join, basename } from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const sharp = require('../../frontend/node_modules/sharp');

const __dir = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dir, '../..');
const AI_DIR = join(ROOT, '.cursor/projects/d-OSPanel-home-prognos9ys/assets');
const OUT_DIR = join(ROOT, 'frontend/src/assets/collectibles/pennants');
const PREVIEW_DIR = join(__dir, 'output/pennants/pilot-ai');

const PILOT = ['bra', 'fra', 'usa', 'jpn', 'sco'];

function isBgTraversable(r, g, b, a) {
  if (a < 12) return true;
  const spread = Math.max(r, g, b) - Math.min(r, g, b);
  if (spread > 40) return false;
  const avg = (r + g + b) / 3;
  return avg > 145;
}

function floodBackgroundAlpha(pixels, width, height) {
  const visited = new Uint8Array(width * height);
  const queue = [];

  const trySeed = (x, y) => {
    const p = y * width + x;
    const i = p * 4;
    if (!isBgTraversable(pixels[i], pixels[i + 1], pixels[i + 2], pixels[i + 3])) return;
    if (visited[p]) return;
    visited[p] = 1;
    queue.push(p);
  };

  for (let x = 0; x < width; x++) {
    trySeed(x, 0);
    trySeed(x, height - 1);
  }
  for (let y = 0; y < height; y++) {
    trySeed(0, y);
    trySeed(width - 1, y);
  }

  for (let qi = 0; qi < queue.length; qi++) {
    const p = queue[qi];
    const x = p % width;
    const y = (p - x) / width;
    pixels[p * 4 + 3] = 0;
    for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
      const nx = x + dx;
      const ny = y + dy;
      if (nx < 0 || ny < 0 || nx >= width || ny >= height) continue;
      const np = ny * width + nx;
      if (visited[np]) continue;
      const i = np * 4;
      if (!isBgTraversable(pixels[i], pixels[i + 1], pixels[i + 2], pixels[i + 3])) continue;
      visited[np] = 1;
      queue.push(np);
    }
  }
}

async function processOne(src, dst, size = 256) {
  const { data, info } = await sharp(src)
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const pixels = Buffer.from(data);
  floodBackgroundAlpha(pixels, info.width, info.height);

  let minX = info.width;
  let minY = info.height;
  let maxX = 0;
  let maxY = 0;
  for (let y = 0; y < info.height; y++) {
    for (let x = 0; x < info.width; x++) {
      const i = (y * info.width + x) * 4;
      if (pixels[i + 3] < 10) continue;
      if (x < minX) minX = x;
      if (y < minY) minY = y;
      if (x > maxX) maxX = x;
      if (y > maxY) maxY = y;
    }
  }

  const cropW = maxX - minX + 1;
  const cropH = maxY - minY + 1;
  const side = Math.max(cropW, cropH);
  const padX = Math.floor((side - cropW) / 2);
  const padY = Math.floor((side - cropH) / 2);
  const square = Buffer.alloc(side * side * 4, 0);

  for (let y = 0; y < cropH; y++) {
    for (let x = 0; x < cropW; x++) {
      const srcIdx = ((minY + y) * info.width + (minX + x)) * 4;
      const dstIdx = ((padY + y) * side + (padX + x)) * 4;
      square[dstIdx] = pixels[srcIdx];
      square[dstIdx + 1] = pixels[srcIdx + 1];
      square[dstIdx + 2] = pixels[srcIdx + 2];
      square[dstIdx + 3] = pixels[srcIdx + 3];
    }
  }

  mkdirSync(dirname(dst), { recursive: true });
  await sharp(square, { raw: { width: side, height: side, channels: 4 } })
    .resize(size, size, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png({ compressionLevel: 9 })
    .toFile(dst);

  const stat = await sharp(dst).metadata();
  console.log(`OK ${basename(src)} -> ${dst} (${stat.width}x${stat.height})`);
}

function resolveAiPath(slug) {
  const candidates = [
    join(AI_DIR, `pennant_wc26_${slug}_ai.png`),
    join(ROOT, 'assets', `pennant_wc26_${slug}_ai.png`),
    join(__dir, 'assets/pennants/ai', `pennant_wc26_${slug}_ai.png`),
  ];
  return candidates.find((p) => existsSync(p)) || null;
}

async function processPilot() {
  mkdirSync(PREVIEW_DIR, { recursive: true });
  for (const slug of PILOT) {
    const src = resolveAiPath(slug);
    if (!src) {
      console.log('SKIP no AI file for', slug);
      continue;
    }
    const preview = join(PREVIEW_DIR, `pennant_wc26_${slug}.png`);
    const icon256 = join(OUT_DIR, `pennant_wc26_${slug}_256.png`);
    const bundled = join(OUT_DIR, `pennant_wc26_${slug}.png`);
    copyFileSync(src, preview);
    await processOne(src, icon256, 256);
    copyFileSync(icon256, bundled);
  }
}

async function main() {
  if (process.argv[2] === '--pilot') {
    await processPilot();
    return;
  }
  const src = process.argv[2];
  const dst = process.argv[3];
  const size = Number(process.argv[4] || 256);
  if (!src || !dst) {
    console.error('Usage: node process_pennant_ai_png.mjs <in.png> <out.png> [size]');
    console.error('       node process_pennant_ai_png.mjs --pilot');
    process.exit(1);
  }
  await processOne(src, dst, size);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
