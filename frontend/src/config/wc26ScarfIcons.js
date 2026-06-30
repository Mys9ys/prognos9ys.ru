/** Иконки шарфов сборных ЧМ-26 (после Photoshop, прозрачный фон). */
const ctx = require.context('@/assets/collectibles/scarfs', false, /^\.\/scarf_wc26_[a-z0-9]+\.png$/);

const ICONS = {};
ctx.keys().forEach((key) => {
  const match = key.match(/scarf_wc26_([a-z0-9]+)\.png$/);
  if (match) {
    ICONS[match[1]] = ctx(key);
  }
});

/**
 * @param {string} code item code, e.g. scarf_wc26_fra
 * @returns {string|null}
 */
export function getWc26ScarfIconSrc(code) {
  const match = String(code || '').match(/^scarf_wc26_([a-z0-9]+)$/);
  if (!match) {
    return null;
  }
  return ICONS[match[1]] || null;
}

export const WC26_SCARF_ICON_SLUGS = Object.keys(ICONS).sort();
