import { getIconSrc } from '@/config/iconPool';

/** @type {Record<string, string>} */
const STATIC_ICON_KEYS = {
  site: 'pennant_site',
  chm2026: 'pennant_chm2026',
};

/** @type {Record<string, string>} */
const DYNAMIC_SRC = {};

try {
  const context = require.context('@/assets/icons/pennants', false, /\.png$/);
  context.keys().forEach((key) => {
    const fileName = key.replace('./', '').replace(/\.png$/, '');
    if (fileName === 'pennant_site' || fileName === 'pennant_chm2026') {
      return;
    }
    if (!fileName.startsWith('pennant_')) {
      return;
    }
    const pennantCode = fileName.slice('pennant_'.length);
    if (pennantCode) {
      DYNAMIC_SRC[pennantCode] = context(key);
    }
  });
} catch (e) {
  // ignore — только статические вымпелы
}

/**
 * @param {string} pennantCode site | chm2026 | prof_{code} | prof_{code}_premium
 * @returns {string|null}
 */
export function getAchievementPennantIconSrc(pennantCode) {
  const code = String(pennantCode || '').trim();
  if (!code) {
    return null;
  }

  const staticKey = STATIC_ICON_KEYS[code];
  if (staticKey) {
    return getIconSrc(staticKey);
  }

  if (DYNAMIC_SRC[code]) {
    return DYNAMIC_SRC[code];
  }

  // Пока нет pennant_prof_*.png — мастерство / премиум на базе уже нарисованных вымпелов.
  if (code.endsWith('_premium')) {
    return getIconSrc('pennant_chm2026');
  }
  if (code.startsWith('prof_')) {
    return getIconSrc('pennant_site');
  }

  return null;
}

export function isAchievementPennantCode(code) {
  const value = String(code || '').trim();
  if (!value) {
    return false;
  }

  return value === 'site'
    || value === 'chm2026'
    || value.startsWith('prof_');
}
