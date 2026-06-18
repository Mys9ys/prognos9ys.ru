import { getStoredIconStyle } from '@/config/iconStyles';

/** UI-иконки (PNG 256×256, из Photoshop-источников). */
const GAME_ICONS = {
  prognobak: require('@/assets/icons/game/prognobak.png'),
  rublius: require('@/assets/icons/game/rublius.png'),
  chest_wc2026: require('@/assets/icons/game/chest-wc2026.png'),
  chest_xp: require('@/assets/icons/game/chest_xp.png'),
  bank: require('@/assets/icons/game/bank.png'),
  football: require('@/assets/icons/game/football.png'),
  prognosis: require('@/assets/icons/game/prognosis.png'),
  f1_race: require('@/assets/icons/game/f1_race.png'),
  achievement: require('@/assets/icons/game/achievement.png'),
  trophy: require('@/assets/icons/game/achievement.png'),
  profile_info: require('@/assets/icons/game/profile_info.png'),
  settings: require('@/assets/icons/game/settings.png'),
  exit_door: require('@/assets/icons/game/exit_door.png'),
  wealth: require('@/assets/icons/game/wealth.png'),
  poverty: require('@/assets/icons/game/poverty.png'),
  xp: require('@/assets/icons/game/xp.png'),
};

const POOLS = {
  soft: GAME_ICONS,
  flat: GAME_ICONS,
  pixel: GAME_ICONS,
};

/**
 * @param {string} name
 * @param {string|null} style
 * @returns {string|null}
 */
export function getIconSrc(name, style = null) {
  const styleId = style || getStoredIconStyle();
  const pool = POOLS[styleId] || GAME_ICONS;
  return pool[name] || GAME_ICONS[name] || null;
}

export const GAME_ICON_NAMES = {
  PROGNOBAK: 'prognobak',
  RUBLIUS: 'rublius',
  CHEST_WC2026: 'chest_wc2026',
  CHEST_XP: 'chest_xp',
  BANK: 'bank',
  FOOTBALL: 'football',
  PROGNOSIS: 'prognosis',
  F1_RACE: 'f1_race',
  ACHIEVEMENT: 'achievement',
  PROFILE_INFO: 'profile_info',
  SETTINGS: 'settings',
  EXIT_DOOR: 'exit_door',
  WEALTH: 'wealth',
  POVERTY: 'poverty',
  XP: 'xp',
  /** @deprecated use achievement */
  TROPHY: 'achievement',
};
