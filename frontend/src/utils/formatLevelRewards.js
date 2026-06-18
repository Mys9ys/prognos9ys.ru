function formatAmount(value) {
    return Number(value ?? 0).toFixed(1).replace(/\.0$/, '');
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
