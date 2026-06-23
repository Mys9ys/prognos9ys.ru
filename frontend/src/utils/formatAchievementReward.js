import { formatAmount } from '@/utils/formatLevelRewards';

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
    bits.push({
      key: 'chests',
      amount: reward.chests,
      icon: 'chest_achievement',
    });
  }

  const pennant = String(reward.pennant || '').trim();
  if (pennant) {
    bits.push({
      key: `pennant_${pennant}`,
      label: PENNANT_LABELS[pennant] || `Вымпел ${pennant}`,
      icon: PENNANT_ICONS[pennant] || 'pennant_site',
    });
  }

  return bits;
}

export function hasAchievementReward(reward) {
  return buildAchievementRewardBits(reward).length > 0;
}
