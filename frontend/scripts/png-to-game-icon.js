const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const src = process.argv[2];
const dst = process.argv[3];
const size = Number(process.argv[4] || 256);

if (!src || !dst) {
  console.error('Usage: node png-to-game-icon.js <input.png> <output.png> [size=256]');
  process.exit(1);
}

async function convert() {
  const png = await sharp(src)
    .resize(size, size, {
      fit: 'contain',
      background: { r: 0, g: 0, b: 0, alpha: 0 },
    })
    .png()
    .toBuffer();

  fs.mkdirSync(path.dirname(dst), { recursive: true });
  fs.writeFileSync(dst, png);
  console.log(`OK ${size}x${size} -> ${dst} (${(png.length / 1024).toFixed(1)} KiB)`);
}

convert().catch((err) => {
  console.error(err);
  process.exit(1);
});
