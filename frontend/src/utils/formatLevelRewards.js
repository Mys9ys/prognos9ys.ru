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

/** Зеркало ProfessionEconomyConfig::getProfessionLevelReward (PHP). */
export function getProfessionLevelRewardConfig(level) {
    const lvl = Number(level || 0);
    if (lvl <= 0) {
        return {
            prognobaks: 0,
            rublius: 0,
            material_qty: 0,
            chests: 0,
            title: null,
        };
    }

    const player = getLevelUpRewardConfig(lvl);
    let chests = 0;
    if (lvl === 5) {
        chests = 1;
    } else if (lvl === 10) {
        chests = 2;
    }

    return {
        prognobaks: Math.round(player.prognobaks * 0.4 * 10) / 10,
        rublius: Math.round(player.rublius * 0.4 * 10) / 10,
        material_qty: lvl % 5 === 0 ? 5 : 3,
        chests,
        title: lvl === 10 ? 'Мастер' : null,
    };
}

/** Зеркало GameEconomyConfig::defaultLevelThresholds (PHP). */
export function defaultLevelThresholds() {
    const tiers = {
        0: 0,
        1: 100,
        2: 250,
        3: 500,
        4: 1000,
    };

    for (let level = 5; level <= 50; level += 1) {
        tiers[level] = 1000 * (level - 3);
    }

    return tiers;
}

function formatRewardBits({ prognobaks, rublius, chests, chestLabel = 'сунд.' }) {
    const bits = [];
    if (Number(prognobaks) > 0) {
        bits.push(`+${formatAmount(prognobaks)} 🪙`);
    }
    if (Number(rublius) > 0) {
        bits.push(`+${formatAmount(rublius)} 💎`);
    }
    if (Number(chests) > 0) {
        bits.push(`+${chests} ${chestLabel}`);
    }
    return bits.join(', ');
}

/** Строки для гайда: уровень игрока. */
export function buildPlayerLevelGuideRows(maxLevel = 15) {
    const tiers = defaultLevelThresholds();
    const rows = [];

    for (let level = 1; level <= maxLevel; level += 1) {
        const reward = getLevelUpRewardConfig(level);
        const xpFrom = tiers[level] ?? 0;
        const xpTo = tiers[level + 1];
        const xpLabel = xpTo != null ? `${xpFrom}–${xpTo - 1} XP` : `от ${xpFrom} XP`;
        const rewardLabel = formatRewardBits({
            ...reward,
            chests: 1,
            chestLabel: '⭐сунд.',
        });
        const milestone = level % 5 === 0 ? ' ★' : '';
        rows.push([`Ур. ${level}${milestone}`, xpLabel, rewardLabel]);
    }

    return rows;
}

/** Строки для гайда: уровень профессии (ресурс — пример для дровосека). */
export function buildProfessionLevelGuideRows(maxLevel = 10) {
    const tiers = defaultLevelThresholds();
    const rows = [];

    for (let level = 1; level <= maxLevel; level += 1) {
        const reward = getProfessionLevelRewardConfig(level);
        const xpFrom = tiers[level] ?? 0;
        const xpTo = tiers[level + 1];
        const xpLabel = xpTo != null ? `${xpFrom}–${xpTo - 1} XP` : `от ${xpFrom} XP`;
        const bits = [formatRewardBits({ ...reward, chestLabel: 'сунд. проф.' })];
        if (reward.material_qty > 0) {
            bits.push(`+${reward.material_qty} рес.`);
        }
        if (reward.title) {
            bits.push(reward.title);
        }
        const milestone = level % 5 === 0 ? ' ★' : '';
        rows.push([`Ур. ${level}${milestone}`, xpLabel, bits.filter(Boolean).join(', ')]);
    }

    return rows;
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

    if (chestType === 'achievement') {
        return 'chest_achievement';
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
