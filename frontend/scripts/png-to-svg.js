const fs = require('fs');
const path = require('path');
const sharp = require('sharp');

const src = process.argv[2];
const dst = process.argv[3];
const size = Number(process.argv[4] || 256);

if (!src || !dst) {
  console.error('Usage: node png-to-svg.js <input.png> <output.svg> [size=256]');
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

  const b64 = png.toString('base64');
  const svg = [
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" fill="none">`,
    `  <image width="${size}" height="${size}" href="data:image/png;base64,${b64}"/>`,
    '</svg>',
  ].join('\n');

  fs.mkdirSync(path.dirname(dst), { recursive: true });
  fs.writeFileSync(dst, svg);
  console.log(`OK ${size}x${size} -> ${dst} (${(Buffer.byteLength(svg) / 1024).toFixed(1)} KiB)`);
}

convert().catch((err) => {
  console.error(err);
  process.exit(1);
});
