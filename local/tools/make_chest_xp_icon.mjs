/**
 * Сундук ЧМ + звезда опыта вместо эмблемы кубка.
 * node local/tools/make_chest_xp_icon.mjs
 */
import path from 'path';
import { createRequire } from 'module';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const sharp = require('../../frontend/node_modules/sharp');

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const chestPath = path.join(root, 'mob_app/img/chest_ps.png');
const starPath = path.join(root, 'mob_app/img/xp_ps.png');
const outPath = path.join(root, 'frontend/src/assets/icons/game/chest_xp.png');

const OUT = 256;
const starSize = Math.round(OUT * 0.36);
const left = Math.round(OUT * 0.5 - starSize / 2);
const top = Math.round(OUT * 0.52 - starSize / 2);

const chest = await sharp(chestPath)
    .resize(OUT, OUT, {
        fit: 'contain',
        background: { r: 0, g: 0, b: 0, alpha: 0 },
    })
    .png()
    .toBuffer();

const star = await sharp(starPath)
    .resize(starSize, starSize, {
        fit: 'contain',
        background: { r: 0, g: 0, b: 0, alpha: 0 },
    })
    .png()
    .toBuffer();

await sharp(chest)
    .composite([{ input: star, left, top }])
    .png({ compressionLevel: 9, palette: true })
    .toFile(outPath);

console.log(`OK ${outPath}`);
