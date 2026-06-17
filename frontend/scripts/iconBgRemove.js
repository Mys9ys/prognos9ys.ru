function isGreyCheckerboard(r, g, b) {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  const spread = max - min;
  if (spread > 20) {
    return false;
  }
  const avg = (r + g + b) / 3;
  // светлые и тёмные клетки шахматки (в т.ч. ~#808080)
  return (min >= 165 && max <= 245) || (avg >= 95 && avg < 170);
}

function isDarkNeutral(r, g, b) {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  return max - min <= 25 && max <= 45;
}

function hasForegroundNeighbor(pixels, width, height, x, y) {
  const offsets = [[-1, 0], [1, 0], [0, -1], [0, 1], [-1, -1], [1, -1], [-1, 1], [1, 1]];
  for (const [dx, dy] of offsets) {
    const nx = x + dx;
    const ny = y + dy;
    if (nx < 0 || ny < 0 || nx >= width || ny >= height) {
      continue;
    }
    const idx = (ny * width + nx) * 4;
    if (pixels[idx + 3] < 10) {
      continue;
    }
    const r = pixels[idx];
    const g = pixels[idx + 1];
    const b = pixels[idx + 2];
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const spread = max - min;
    if (min < 195 || spread > 28) {
      return true;
    }
  }
  return false;
}

function isRemovableWhite(pixels, width, height, x, y) {
  const idx = (y * width + x) * 4;
  const r = pixels[idx];
  const g = pixels[idx + 1];
  const b = pixels[idx + 2];
  const spread = Math.max(r, g, b) - Math.min(r, g, b);
  if (spread > 15 || r < 232 || g < 232 || b < 232) {
    return false;
  }
  return !hasForegroundNeighbor(pixels, width, height, x, y);
}

function isFloodBackground(r, g, b, pixels, width, height, x, y) {
  if (isGreyCheckerboard(r, g, b) || isDarkNeutral(r, g, b)) {
    return true;
  }
  return isRemovableWhite(pixels, width, height, x, y);
}

function removeGreyCheckerboardGlobally(pixels) {
  for (let i = 0; i < pixels.length; i += 4) {
    if (isGreyCheckerboard(pixels[i], pixels[i + 1], pixels[i + 2])) {
      pixels[i + 3] = 0;
    }
  }
}

function floodRemove(pixels, width, height, predicate) {
  const visited = new Uint8Array(width * height);
  const queue = [];

  const push = (x, y) => {
    const idx = y * width + x;
    if (visited[idx]) {
      return;
    }
    const p = idx * 4;
    if (pixels[p + 3] < 10) {
      return;
    }
    if (!predicate(pixels[p], pixels[p + 1], pixels[p + 2], x, y)) {
      return;
    }
    visited[idx] = 1;
    queue.push(idx);
  };

  for (let x = 0; x < width; x++) {
    push(x, 0);
    push(x, height - 1);
  }
  for (let y = 0; y < height; y++) {
    push(0, y);
    push(width - 1, y);
  }

  while (queue.length) {
    const idx = queue.pop();
    const p = idx * 4;
    pixels[p + 3] = 0;

    const x = idx % width;
    const y = (idx - x) / width;
    if (x > 0) push(x - 1, y);
    if (x < width - 1) push(x + 1, y);
    if (y > 0) push(x, y - 1);
    if (y < height - 1) push(x, y + 1);
  }
}

function removeNeutralIslands(pixels, width, height) {
  for (let y = 1; y < height - 1; y++) {
    for (let x = 1; x < width - 1; x++) {
      const idx = (y * width + x) * 4;
      if (pixels[idx + 3] < 10) {
        continue;
      }
      const r = pixels[idx];
      const g = pixels[idx + 1];
      const b = pixels[idx + 2];
      const min = Math.min(r, g, b);
      const spread = Math.max(r, g, b) - min;
      if (min < 200 || spread > 18) {
        continue;
      }

      let allNeutral = true;
      for (let dy = -1; dy <= 1; dy++) {
        for (let dx = -1; dx <= 1; dx++) {
          if (dx === 0 && dy === 0) {
            continue;
          }
          const ni = ((y + dy) * width + (x + dx)) * 4;
          if (pixels[ni + 3] < 10) {
            continue;
          }
          const nr = pixels[ni];
          const ng = pixels[ni + 1];
          const nb = pixels[ni + 2];
          const nmin = Math.min(nr, ng, nb);
          const nspread = Math.max(nr, ng, nb) - nmin;
          if (nmin < 170 || nspread > 25) {
            allNeutral = false;
            break;
          }
        }
        if (!allNeutral) {
          break;
        }
      }

      if (allNeutral) {
        pixels[idx + 3] = 0;
      }
    }
  }
}

function binarizeFootball(pixels) {
  for (let i = 0; i < pixels.length; i += 4) {
    if (pixels[i + 3] < 10) {
      continue;
    }
    const r = pixels[i];
    const g = pixels[i + 1];
    const b = pixels[i + 2];
    const lum = 0.299 * r + 0.587 * g + 0.114 * b;
    if (lum > 150) {
      const shade = Math.min(255, Math.round(205 + (lum - 150) * 0.35));
      pixels[i] = shade;
      pixels[i + 1] = shade;
      pixels[i + 2] = shade;
    } else {
      const dark = Math.max(6, Math.min(72, Math.round(lum * 0.42)));
      pixels[i] = dark;
      pixels[i + 1] = dark;
      pixels[i + 2] = dark;
    }
  }
}

function convertFootballToBw(pixels) {
  for (let i = 0; i < pixels.length; i += 4) {
    const r = pixels[i];
    const g = pixels[i + 1];
    const b = pixels[i + 2];

    if (g > r + 18 && g > b + 18 && g > 80) {
      const lum = Math.round(0.2 * r + 0.55 * g + 0.25 * b);
      const dark = Math.max(8, Math.min(70, 120 - Math.round(lum * 0.45)));
      pixels[i] = dark;
      pixels[i + 1] = dark;
      pixels[i + 2] = dark;
      continue;
    }

    if (r > 200 && g > 200 && b > 200) {
      const avg = Math.round((r + g + b) / 3);
      pixels[i] = avg;
      pixels[i + 1] = avg;
      pixels[i + 2] = avg;
    }
  }
}

function removeBackground(pixels, width, height, options = {}) {
  removeGreyCheckerboardGlobally(pixels);
  floodRemove(pixels, width, height, (r, g, b, x, y) => isFloodBackground(r, g, b, pixels, width, height, x, y));

  if (options.football) {
    binarizeFootball(pixels);
  }

  removeNeutralIslands(pixels, width, height);
}

function squareCrop(pixels, width, height) {
  let minX = width;
  let minY = height;
  let maxX = 0;
  let maxY = 0;

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      const idx = (y * width + x) * 4;
      if (pixels[idx + 3] > 10) {
        if (x < minX) minX = x;
        if (y < minY) minY = y;
        if (x > maxX) maxX = x;
        if (y > maxY) maxY = y;
      }
    }
  }

  if (maxX < minX || maxY < minY) {
    return { pixels, width, height };
  }

  const cropW = maxX - minX + 1;
  const cropH = maxY - minY + 1;
  const side = Math.max(cropW, cropH);
  const padX = Math.floor((side - cropW) / 2);
  const padY = Math.floor((side - cropH) / 2);
  const square = Buffer.alloc(side * side * 4, 0);

  for (let y = 0; y < cropH; y++) {
    for (let x = 0; x < cropW; x++) {
      const srcIdx = ((minY + y) * width + (minX + x)) * 4;
      const dstIdx = ((padY + y) * side + (padX + x)) * 4;
      square[dstIdx] = pixels[srcIdx];
      square[dstIdx + 1] = pixels[srcIdx + 1];
      square[dstIdx + 2] = pixels[srcIdx + 2];
      square[dstIdx + 3] = pixels[srcIdx + 3];
    }
  }

  return { pixels: square, width: side, height: side };
}

module.exports = {
  convertFootballToBw,
  removeBackground,
  squareCrop,
};
