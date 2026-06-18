export function getLevelBannerStorageKey(userId) {
    if (!userId) {
        return '';
    }

    return `lk_level_banner_state_${userId}`;
}

export function readLevelBannerState(userId) {
    const key = getLevelBannerStorageKey(userId);

    if (!key) {
        return null;
    }

    try {
        const raw = localStorage.getItem(key);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);

        return {
            seenLevel: Number(parsed.seenLevel || 0),
            dismissedLevel: Number(parsed.dismissedLevel || 0),
        };
    } catch (e) {
        return null;
    }
}

export function saveLevelBannerState(userId, state) {
    const key = getLevelBannerStorageKey(userId);

    if (!key) {
        return;
    }

    localStorage.setItem(key, JSON.stringify({
        seenLevel: Number(state.seenLevel || 0),
        dismissedLevel: Number(state.dismissedLevel || 0),
    }));
}
