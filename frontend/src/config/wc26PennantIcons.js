/** Иконки вымпелов сборных ЧМ-26 (сырые AI, фон — шахматка; обрезка в Photoshop). */
const ctx = require.context('@/assets/collectibles/pennants', false, /^\.\/pennant_wc26_[a-z0-9]+\.png$/);

const ICONS = {};
ctx.keys().forEach((key) => {
  const match = key.match(/pennant_wc26_([a-z0-9]+)\.png$/);
  if (match) {
    ICONS[match[1]] = ctx(key);
  }
});

/**
 * @param {string} code item code, e.g. pennant_wc26_fra
 * @returns {string|null}
 */
export function getWc26PennantIconSrc(code) {
  const match = String(code || '').match(/^pennant_wc26_([a-z0-9]+)$/);
  if (!match) {
    return null;
  }
  return ICONS[match[1]] || null;
}

export const WC26_PENNANT_ICON_SLUGS = Object.keys(ICONS).sort();
