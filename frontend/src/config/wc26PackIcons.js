/** Иконки паков ЧМ-26 (после Photoshop, прозрачный фон). */
const ctx = require.context('@/assets/collectibles/packs', false, /^\.\/pack_[a-z0-9_]+\.png$/);

const ICONS = {};
ctx.keys().forEach((key) => {
  const match = key.match(/(pack_[a-z0-9_]+)\.png$/);
  if (match) {
    ICONS[match[1]] = ctx(key);
  }
});

/**
 * @param {string} code item code, e.g. pack_pennant_wc26
 * @returns {string|null}
 */
export function getWc26PackIconSrc(code) {
  return ICONS[String(code || '')] || null;
}

export const WC26_PACK_ICON_CODES = Object.keys(ICONS).sort();
