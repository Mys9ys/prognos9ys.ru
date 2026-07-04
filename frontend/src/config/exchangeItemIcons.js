import { getIconSrc } from '@/config/iconPool';
import { getWc26PennantIconSrc } from '@/config/wc26PennantIcons';
import { getWc26ScarfIconSrc } from '@/config/wc26ScarfIcons';
import { getWc26PackIconSrc } from '@/config/wc26PackIcons';
import { getCraftProductIconSrc, getCraftRecipeIconSrc } from '@/config/craftIcons';
import { getMaterialIconSrc } from '@/config/materialIcons';

const MATERIAL_EMOJI = {
  log: '🪵',
  stone: '🪨',
  ore: '⛏️',
  sand: '🏖️',
  cotton: '🧵',
  amber: '🟠',
  marble: '⚪',
  gold_nugget: '🥇',
  quartz: '🔮',
  silk: '🎀',
  plank: '🪚',
  block: '🧱',
  ingot: '🔩',
  glass: '🫙',
  cloth: '🧶',
  fine_plank: '🌲',
  fine_block: '🏛️',
  fine_ingot: '✨',
  fine_glass: '🥂',
  fine_cloth: '👑',
  craft_resin: '🟤',
  craft_sealstone: '⚪',
  craft_gilded_ore: '🟡',
  craft_prism_sand: '🔮',
  craft_golden_thread: '🧵',
};

const LOOT_CATEGORY_EMOJI = {
  cert: '📜',
  pack: '📦',
  album: '📔',
  recipe: '📋',
  xp_bank: '🧪',
};

const WC26_CHEST_CODES = new Set(['wc26', 'match', 'shop_wc26', 'wc26_achievement']);

/**
 * @param {{ kind?: string, code?: string, category?: string, team_code?: string }} item
 * @returns {{ src: string|null, emoji: string|null }}
 */
export function getExchangeItemThumb(item) {
  const kind = String(item?.kind || '');
  const code = String(item?.code || '');
  const category = String(item?.category || '');
  const teamCode = String(item?.team_code || '');

  if (kind === 'chest') {
    if (code === 'level') {
      return { src: getIconSrc('chest_xp'), emoji: null };
    }
    if (code === 'achievement') {
      return { src: getIconSrc('chest_achievement'), emoji: null };
    }
    if (WC26_CHEST_CODES.has(code) || code.includes('wc26')) {
      return { src: getIconSrc('chest_wc2026'), emoji: null };
    }

    return { src: getIconSrc('chest_wc2026'), emoji: null };
  }

  if (kind === 'premium_scroll') {
    return { src: getIconSrc('premium_scroll_1d'), emoji: null };
  }

  if (kind === 'pennant') {
    if (code === 'site') {
      return { src: getIconSrc('pennant_site'), emoji: null };
    }
    if (code === 'chm2026') {
      return { src: getIconSrc('pennant_chm2026'), emoji: null };
    }
  }

  if (kind === 'loot') {
    if (category === 'pennant') {
      const pennantCode = code.startsWith('pennant_wc26_')
        ? code
        : (teamCode ? `pennant_wc26_${teamCode}` : code);
      const src = getWc26PennantIconSrc(pennantCode);
      if (src) {
        return { src, emoji: null };
      }
    }

    if (category === 'scarf') {
      const scarfCode = code.startsWith('scarf_wc26_')
        ? code
        : (teamCode ? `scarf_wc26_${teamCode}` : code);
      const src = getWc26ScarfIconSrc(scarfCode);
      if (src) {
        return { src, emoji: null };
      }
    }

    if (category === 'xp_bank') {
      return { src: getIconSrc('xp'), emoji: null };
    }

    if (category === 'recipe') {
      const src = getCraftRecipeIconSrc(code);
      if (src) {
        return { src, emoji: null };
      }
    }

    if (category === 'equipment' || category === 'material') {
      const materialSrc = getMaterialIconSrc(code);
      if (materialSrc) {
        return { src: materialSrc, emoji: null };
      }
      const src = getCraftProductIconSrc(code);
      if (src) {
        return { src, emoji: null };
      }
    }

    if (LOOT_CATEGORY_EMOJI[category]) {
      return { src: null, emoji: LOOT_CATEGORY_EMOJI[category] };
    }

    if (category === 'pack') {
      const packSrc = getWc26PackIconSrc(code);
      if (packSrc) {
        return { src: packSrc, emoji: null };
      }
      if (code.includes('pennant')) {
        return { src: getIconSrc('pennant_chm2026'), emoji: null };
      }
      if (code.includes('scarf')) {
        return { src: null, emoji: '🧣' };
      }
    }
  }

  if (kind === 'material') {
    const materialSrc = getMaterialIconSrc(code);
    if (materialSrc) {
      return { src: materialSrc, emoji: null };
    }
    const src = getCraftProductIconSrc(code);
    if (src) {
      return { src, emoji: null };
    }
    return { src: null, emoji: MATERIAL_EMOJI[code] || '📦' };
  }

  return { src: null, emoji: '📦' };
}
