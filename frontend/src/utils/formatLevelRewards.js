function formatAmount(value) {
    return Number(value ?? 0).toFixed(1).replace(/\.0$/, '');
}

/** Зеркало GameEconomyConfig::getLevelUpReward (PHP). */
export function getLevelUpRewardConfig(level) {
    const lvl = Number(level || 0);

    if (lvl <= 0) {
        return { prognobaks: 0, rublius: 0 };
    }

    let baseP;
    let baseR;

    if (lvl <= 5) {
        baseP = 50;
        baseR = 5;
    } else if (lvl <= 10) {
        baseP = 100;
        baseR = 10;
    } else {
        baseP = 150;
        baseR = 15;
    }

    if (lvl % 5 === 0) {
        baseP *= 4;
        baseR *= 4;
    }

    return { prognobaks: baseP, rublius: baseR };
}

/** Награды за диапазон уровней для баннера (без начисления). */
export function buildLevelRewardsPreview(fromLevel, toLevel) {
    const from = Number(fromLevel || 0);
    const to = Number(toLevel || 0);

    if (to <= 0 || from > to) {
        return [];
    }

    const rewards = [];

    for (let level = from; level <= to; level += 1) {
        const amounts = getLevelUpRewardConfig(level);

        rewards.push({
            level,
            prognobaks: amounts.prognobaks,
            rublius: amounts.rublius,
            chests: 1,
            chest_type: 'level',
        });
    }

    return rewards;
}

export function getChestIconName(chestType) {
    if (chestType === 'level') {
        return 'chest_xp';
    }

    return 'chest_wc2026';
}

export function formatLevelRewardItem(reward) {
    const bits = [`ур. ${reward.level}`];

    if (Number(reward.prognobaks) > 0) {
        bits.push(`+${formatAmount(reward.prognobaks)} 💵`);
    }

    if (Number(reward.rublius) > 0) {
        bits.push(`+${formatAmount(reward.rublius)} 💎`);
    }

    if (Number(reward.chests) > 0) {
        const chestLabel = reward.chest_type === 'level' ? '⭐сунд.' : 'сунд.';
        bits.push(`+${reward.chests} ${chestLabel}`);
    }

    return bits.join(' ');
}

export function formatLevelRewardsSummary(levelRewards) {
    if (!Array.isArray(levelRewards) || !levelRewards.length) {
        return '';
    }

    return levelRewards.map(formatLevelRewardItem).join('; ');
}

export { formatAmount };
