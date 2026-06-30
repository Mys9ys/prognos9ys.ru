/**
 * Генерация вымпелов сборных ЧМ-26: флаг-полотно + упрощённый кубок + рамка из референса.
 *
 *   node local/tools/generate_wc26_pennants.mjs
 *   node local/tools/generate_wc26_pennants.mjs --slug=fra --slug=bra
 *   node local/tools/generate_wc26_pennants.mjs --all
 */

import { createRequire } from 'module';
import { mkdirSync, writeFileSync, existsSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const sharp = require('../../frontend/node_modules/sharp');

const __dir = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dir, '../..');
const REFERENCE = join(ROOT, 'frontend/src/assets/icons/pennants/pennant_chm2026.png');
const TROPHY = join(__dir, 'assets/pennants/trophy_wc26_simple.png');
const OUT_DIR = join(ROOT, 'frontend/src/assets/collectibles/pennants');
const PREVIEW_DIR = join(__dir, 'output/pennants/pilot');

const SIZE = 1024;
const ICON_SIZE = 256;

/** slug => flagcdn code */
const SLUG_TO_FLAG = {
  aus: 'au', aut: 'at', alg: 'dz', eng: 'gb-eng', arg: 'ar', bel: 'be', bih: 'ba',
  bra: 'br', hai: 'ht', gha: 'gh', ger: 'de', cod: 'cd', egy: 'eg', jor: 'jo',
  irq: 'iq', irn: 'ir', esp: 'es', cpv: 'cv', can: 'ca', qat: 'qa', col: 'co',
  civ: 'ci', cuw: 'cw', mar: 'ma', mex: 'mx', ned: 'nl', nzl: 'nz', nor: 'no',
  pan: 'pa', par: 'py', por: 'pt', ksa: 'sa', usa: 'us', sen: 'sn', tun: 'tn',
  tur: 'tr', uzb: 'uz', uru: 'uy', fra: 'fr', cro: 'hr', cze: 'cz', sui: 'ch',
  swe: 'se', sco: 'gb-sct', ecu: 'ec', kor: 'kr', rsa: 'za', jpn: 'jp',
};

const PILOT_SLUGS = ['bra', 'fra', 'usa', 'jpn', 'sco'];

function parseArgs(argv) {
  const slugs = [];
  let all = false;
  for (let i = 2; i < argv.length; i++) {
    if (argv[i] === '--all') all = true;
    else if (argv[i].startsWith('--slug=')) slugs.push(argv[i].slice(7));
  }
  if (all) return Object.keys(SLUG_TO_FLAG);
  if (slugs.length) return slugs;
  return PILOT_SLUGS;
}

function isGold(r, g, b, a) {
  if (a < 20) return false;
  return r > 145 && g > 105 && b < 125 && r >= b + 40 && g >= b;
}

function isFabric(r, g, b, a) {
  if (a < 20) return false;
  if (isGold(r, g, b, a)) return false;
  // основное зелёное полотно референса (без тёмных декоративных штрихов)
  return g >= 68 && g <= 125 && r <= 75 && b <= 85 && g > r + 18 && g > b + 18;
}

function isRod(r, g, b, a) {
  if (a < 20) return false;
  if (isGold(r, g, b, a)) return true;
  return g > 40 && g > r && b < 90 && r < 120;
}

async function loadRgba(path, size = SIZE) {
  const { data, info } = await sharp(path)
    .resize(size, size, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });
  return { data: Buffer.from(data), info };
}

function idx(info, x, y) {
  return (y * info.width + x) * 4;
}

function buildMasks(ref) {
  const { data, info } = ref;
  const w = info.width;
  const h = info.height;
  const fabric = new Uint8Array(w * h);

  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = idx(info, x, y);
      const r = data[i];
      const g = data[i + 1];
      const b = data[i + 2];
      const a = data[i + 3];
      const p = y * w + x;

      if (a < 10) continue;
      if (isFabric(r, g, b, a)) fabric[p] = 1;
    }
  }

  let minX = w; let minY = h; let maxX = 0; let maxY = 0;
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = idx(info, x, y);
      if (data[i + 3] < 20) continue;
      if (x < minX) minX = x;
      if (y < minY) minY = y;
      if (x > maxX) maxX = x;
      if (y > maxY) maxY = y;
    }
  }

  return { fabric, bbox: { minX, minY, maxX, maxY, w, h } };
}

function expandFabricToInnerField(fabric, ref, info, bbox) {
  const { data } = ref;
  const w = info.width;
  const h = info.height;
  const out = new Uint8Array(fabric);
  const rodBottom = Math.round(h * 0.115);

  for (let y = bbox.minY; y <= bbox.maxY; y++) {
    for (let x = bbox.minX; x <= bbox.maxX; x++) {
      const p = y * w + x;
      const i = idx(info, x, y);
      const a = data[i + 3];
      if (a < 20) continue;
      if (y < rodBottom) continue;
      if (isGold(data[i], data[i + 1], data[i + 2], a)) continue;
      out[p] = 1;
    }
  }

  return out;
}

async function fetchFlag(flagCode) {
  const url = `https://flagcdn.com/w640/${flagCode}.png`;
  const res = await fetch(url);
  if (!res.ok) throw new Error(`flag ${flagCode}: HTTP ${res.status}`);
  return Buffer.from(await res.arrayBuffer());
}

function fabricNoise(w, h) {
  const buf = Buffer.alloc(w * h * 4);
  for (let i = 0; i < w * h; i++) {
    const n = 200 + Math.floor(Math.random() * 55);
    const a = 18 + Math.floor(Math.random() * 22);
    const o = i * 4;
    buf[o] = n;
    buf[o + 1] = n;
    buf[o + 2] = n;
    buf[o + 3] = a;
  }
  return buf;
}

async function buildFlagLayer(flagBuf, bbox, fabricMask, info) {
  const fw = bbox.maxX - bbox.minX + 1;
  const fh = bbox.maxY - bbox.minY + 1;

  const flagResized = await sharp(flagBuf)
    .resize(fw, fh, { fit: 'cover', position: 'centre' })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const canvas = Buffer.alloc(info.width * info.height * 4, 0);
  const noise = fabricNoise(fw, fh);

  for (let y = 0; y < fh; y++) {
    for (let x = 0; x < fw; x++) {
      const px = bbox.minX + x;
      const py = bbox.minY + y;
      if (px < 0 || py < 0 || px >= info.width || py >= info.height) continue;
      if (!fabricMask[py * info.width + px]) continue;

      const fi = (y * fw + x) * 4;
      const ni = fi;
      const ci = idx(info, px, py);

      let r = flagResized.data[fi];
      let g = flagResized.data[fi + 1];
      let b = flagResized.data[fi + 2];
      const na = noise[ni + 3] / 255;
      r = Math.round(r * (1 - na * 0.15) + noise[ni] * na * 0.15);
      g = Math.round(g * (1 - na * 0.15) + noise[ni + 1] * na * 0.15);
      b = Math.round(b * (1 - na * 0.15) + noise[ni + 2] * na * 0.15);

      canvas[ci] = r;
      canvas[ci + 1] = g;
      canvas[ci + 2] = b;
      canvas[ci + 3] = 255;
    }
  }

  return canvas;
}

function distToTransparent(ref, info) {
  const { data } = ref;
  const w = info.width;
  const h = info.height;
  const dist = new Int32Array(w * h);
  dist.fill(1_000_000);
  const queue = [];

  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const p = y * w + x;
      const a = data[idx(info, x, y) + 3];
      if (a >= 20) continue;
      dist[p] = 0;
      queue.push(p);
    }
  }

  for (let qi = 0; qi < queue.length; qi++) {
    const p = queue[qi];
    const x = p % w;
    const y = (p - x) / w;
    const d = dist[p];
    for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
      const nx = x + dx;
      const ny = y + dy;
      if (nx < 0 || ny < 0 || nx >= w || ny >= h) continue;
      const np = ny * w + nx;
      if (dist[np] > d + 1) {
        dist[np] = d + 1;
        queue.push(np);
      }
    }
  }

  return dist;
}

function buildOverlay(ref, fabricMask, info) {
  const { data } = ref;
  const w = info.width;
  const h = info.height;
  const out = Buffer.alloc(data.length, 0);
  const distTransparent = distToTransparent(ref, info);

  const rodBottom = Math.round(h * 0.115);
  const borderDepth = 20;

  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const p = y * w + x;
      const i = idx(info, x, y);
      const r = data[i];
      const g = data[i + 1];
      const b = data[i + 2];
      const a = data[i + 3];
      if (a < 10) continue;

      const inRod = y < rodBottom && (isRod(r, g, b, a) || isGold(r, g, b, a));
      const edgeGold = isGold(r, g, b, a) && !fabricMask[p] && distTransparent[p] <= borderDepth;

      if (!inRod && !edgeGold) continue;

      out[i] = r;
      out[i + 1] = g;
      out[i + 2] = b;
      out[i + 3] = a;
    }
  }

  return out;
}

async function loadTrophyPrepared(trophyH, trophyW) {
  const trimmed = await sharp(TROPHY).trim().toBuffer();
  const { data, info } = await sharp(trimmed)
    .resize(trophyW, trophyH, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .ensureAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  const pixels = Buffer.from(data);
  for (let i = 0; i < pixels.length; i += 4) {
    const r = pixels[i];
    const g = pixels[i + 1];
    const b = pixels[i + 2];
    const goldish = r > 95 && g > 70 && b < 130 && r >= b + 20;
    const bright = r + g + b > 380;
    if (!goldish && !bright) {
      pixels[i + 3] = 0;
    }
  }

  return { data: pixels, info };
}

async function placeTrophy(bbox, fabricMask, info) {
  const fh = bbox.maxY - bbox.minY + 1;
  const trophyH = Math.round(fh * 0.33);
  const trophyW = Math.round(trophyH * 0.72);

  const cx = Math.round((bbox.minX + bbox.maxX) / 2);
  const cy = Math.round(bbox.minY + fh * 0.48);

  const trophyBuf = await loadTrophyPrepared(trophyH, trophyW);

  const layer = Buffer.alloc(info.width * info.height * 4, 0);
  const tw = trophyBuf.info.width;
  const th = trophyBuf.info.height;
  const left = cx - Math.floor(tw / 2);
  const top = cy - Math.floor(th / 2);

  for (let y = 0; y < th; y++) {
    for (let x = 0; x < tw; x++) {
      const px = left + x;
      const py = top + y;
      if (px < 0 || py < 0 || px >= info.width || py >= info.height) continue;
      if (!fabricMask[py * info.width + px]) continue;

      const ti = (y * tw + x) * 4;
      const oi = idx(info, px, py);
      const a = trophyBuf.data[ti + 3] / 255;
      if (a <= 0) continue;

      layer[oi] = trophyBuf.data[ti];
      layer[oi + 1] = trophyBuf.data[ti + 1];
      layer[oi + 2] = trophyBuf.data[ti + 2];
      layer[oi + 3] = Math.round(trophyBuf.data[ti + 3]);
    }
  }

  return layer;
}

function composite(base, layer, info) {
  const out = Buffer.from(base);
  for (let y = 0; y < info.height; y++) {
    for (let x = 0; x < info.width; x++) {
      const i = idx(info, x, y);
      const a = layer[i + 3] / 255;
      if (a <= 0) continue;
      out[i] = Math.round(layer[i] * a + out[i] * (1 - a));
      out[i + 1] = Math.round(layer[i + 1] * a + out[i + 1] * (1 - a));
      out[i + 2] = Math.round(layer[i + 2] * a + out[i + 2] * (1 - a));
      out[i + 3] = Math.max(out[i + 3], layer[i + 3]);
    }
  }
  return out;
}

async function renderPennant(slug) {
  const flagCode = SLUG_TO_FLAG[slug];
  if (!flagCode) throw new Error(`unknown slug: ${slug}`);

  const ref = await loadRgba(REFERENCE, SIZE);
  let { fabric, bbox } = buildMasks(ref);
  fabric = expandFabricToInnerField(fabric, ref, ref.info, bbox);
  const flagBuf = await fetchFlag(flagCode);

  let canvas = await buildFlagLayer(flagBuf, bbox, fabric, ref.info);
  const trophyLayer = await placeTrophy(bbox, fabric, ref.info);
  canvas = composite(canvas, trophyLayer, ref.info);

  const overlay = buildOverlay(ref, fabric, ref.info);
  canvas = composite(canvas, overlay, ref.info);

  const png = await sharp(canvas, {
    raw: { width: ref.info.width, height: ref.info.height, channels: 4 },
  }).png({ compressionLevel: 9 }).toBuffer();

  return png;
}

async function saveOutputs(slug, png1024) {
  mkdirSync(OUT_DIR, { recursive: true });
  mkdirSync(PREVIEW_DIR, { recursive: true });

  const outPreview = join(PREVIEW_DIR, `pennant_wc26_${slug}.png`);
  const outIcon = join(OUT_DIR, `pennant_wc26_${slug}_256.png`);
  const outBundled = join(OUT_DIR, `pennant_wc26_${slug}.png`);

  writeFileSync(outPreview, png1024);

  const icon = await sharp(png1024)
    .resize(ICON_SIZE, ICON_SIZE, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
    .png({ compressionLevel: 9 })
    .toBuffer();
  writeFileSync(outIcon, icon);
  writeFileSync(outBundled, icon);

  console.log(`OK ${slug} -> ${outIcon} (${(icon.length / 1024).toFixed(0)} KiB), preview ${(png1024.length / 1024).toFixed(0)} KiB`);
}

async function main() {
  if (!existsSync(REFERENCE)) throw new Error(`reference not found: ${REFERENCE}`);
  if (!existsSync(TROPHY)) throw new Error(`trophy not found: ${TROPHY}`);

  const slugs = parseArgs(process.argv);
  for (const slug of slugs) {
    const png = await renderPennant(slug);
    await saveOutputs(slug, png);
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
