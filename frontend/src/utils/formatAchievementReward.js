import { formatAmount } from '@/utils/formatLevelRewards';
import { getAchievementPennantIconSrc } from '@/config/achievementPennantIcons';

const PENNANT_LABELS = {
  site: 'Вымпел Прогносяус',
  chm2026: 'Вымпел ЧМ-2026',
};

const PENNANT_ICONS = {
  site: 'pennant_site',
  chm2026: 'pennant_chm2026',
};

/**
 * @param {{ rublius?: number, chests?: number, pennant?: string|null }} reward
 * @returns {Array<{ key: string, amount?: number|string, label?: string, icon: string }>}
 */
export function buildAchievementRewardBits(reward) {
  if (!reward || typeof reward !== 'object') {
    return [];
  }

  const bits = [];

  if (Number(reward.rublius) > 0) {
    bits.push({
      key: 'rublius',
      amount: formatAmount(reward.rublius),
      icon: 'rublius',
    });
  }

  if (Number(reward.chests) > 0) {
    const chestType = String(reward.chest_type || '');
    const isProfessionChest = chestType === 'profession'
      || chestType.startsWith('profession_tier_');
    const chestIcon = isProfessionChest ? 'chest_xp' : 'chest_achievement';
    bits.push({
      key: 'chests',
      amount: reward.chests,
      icon: chestIcon,
      label: isProfessionChest ? 'сундук проф.' : undefined,
    });
  }

  const materials = reward.materials;
  if (Array.isArray(materials)) {
    materials.forEach((mat, index) => {
      const qty = Number(mat?.qty) || 0;
      if (qty <= 0) {
        return;
      }
      bits.push({
        key: `material_${index}`,
        amount: qty,
        label: mat?.code || 'ресурс',
        icon: 'total_all',
      });
    });
  }

  const pennant = String(reward.pennant || '').trim();
  if (pennant) {
    const iconSrc = getAchievementPennantIconSrc(pennant);
    bits.push({
      key: `pennant_${pennant}`,
      label: PENNANT_LABELS[pennant] || `Вымпел ${pennant}`,
      icon: PENNANT_ICONS[pennant] || (iconSrc ? `pennant:${pennant}` : 'pennant_site'),
      imageSrc: iconSrc || undefined,
    });
  }

  return bits;
}

export function hasAchievementReward(reward) {
  return buildAchievementRewardBits(reward).length > 0;
}

function chestLabel(count) {
  const n = Number(count) || 0;
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) {
    return 'сундук';
  }
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return 'сундука';
  }
  return 'сундуков';
}

/** Краткая подпись награды для тултипа уровня ачивки. */
export function formatAchievementRewardText(reward) {
  const bits = buildAchievementRewardBits(reward);
  if (!bits.length) {
    return '';
  }

  return bits.map((bit) => {
    if (bit.key === 'rublius') {
      return `+${bit.amount} руб.`;
    }
    if (bit.key === 'chests') {
      return `+${bit.amount} ${chestLabel(bit.amount)}`;
    }
    if (bit.label) {
      return bit.label;
    }
    return '';
  }).filter(Boolean).join(', ');
}
