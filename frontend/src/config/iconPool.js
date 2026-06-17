import { getStoredIconStyle } from '@/config/iconStyles';

/** UI-иконки (SVG, из mob_app PNG). */
const GAME_ICONS = {
  prognobak: require('@/assets/icons/game/prognobak.svg'),
  rublius: require('@/assets/icons/game/rublius.svg'),
  chest_wc2026: require('@/assets/icons/game/chest-wc2026.svg'),
  bank: require('@/assets/icons/game/bank.svg'),
  football: require('@/assets/icons/game/football.svg'),
  prognosis: require('@/assets/icons/game/prognosis.svg'),
  f1_race: require('@/assets/icons/game/f1_race.svg'),
  achievement: require('@/assets/icons/game/achievement.svg'),
  trophy: require('@/assets/icons/game/achievement.svg'),
  profile_info: require('@/assets/icons/game/profile_info.svg'),
  settings: require('@/assets/icons/game/settings.svg'),
  exit_door: require('@/assets/icons/game/exit_door.svg'),
  wealth: require('@/assets/icons/game/wealth.svg'),
  poverty: require('@/assets/icons/game/poverty.svg'),
  xp: require('@/assets/icons/game/xp.svg'),
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
