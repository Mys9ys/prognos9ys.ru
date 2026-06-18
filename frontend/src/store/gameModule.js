/* eslint-disable */
import { apiActions } from '@/api/bitrixClient';
import { readLevelBannerState, saveLevelBannerState } from '@/utils/levelBannerStorage';
import { buildLevelRewardsPreview } from '@/utils/formatLevelRewards';

function applyGamePayload({ rootState, commit }, res) {
    if (res?.game && rootState.auth?.userInfo) {
        commit('auth/setUserInfo', {
            ...rootState.auth.userInfo,
            game_info: res.game,
        }, { root: true });
    }
    return res;
}

export const gameModule = {
    state: () => ({
        levelBanner: {
            visible: false,
            level: 0,
            from: 0,
            rewards: [],
        },
    }),
    getters: {},
    mutations: {
        setLevelBanner(state, payload) {
            state.levelBanner = {
                ...state.levelBanner,
                ...payload,
            };
        },
    },
    actions: {
        evaluateLevelBanner({ rootState, commit }) {
            const userId = Number(rootState.auth?.userInfo?.ID || 0);
            const currentLevel = Number(rootState.auth?.userInfo?.game_info?.progress?.level || 0);

            if (!userId || currentLevel <= 0) {
                commit('setLevelBanner', { visible: false, level: 0, from: 0, rewards: [] });
                return;
            }

            const state = readLevelBannerState(userId);

            if (!state) {
                saveLevelBannerState(userId, {
                    seenLevel: currentLevel,
                    dismissedLevel: currentLevel,
                });
                commit('setLevelBanner', { visible: false, level: currentLevel, from: 0, rewards: [] });
                return;
            }

            let seenLevel = state.seenLevel;
            const dismissedLevel = state.dismissedLevel;

            if (currentLevel > seenLevel) {
                seenLevel = currentLevel;
            }

            const fromLevel = dismissedLevel + 1;
            const showBanner = currentLevel > dismissedLevel;
            const rewards = showBanner && fromLevel <= currentLevel
                ? buildLevelRewardsPreview(fromLevel, currentLevel)
                : [];

            commit('setLevelBanner', {
                visible: showBanner,
                level: currentLevel,
                from: fromLevel < currentLevel ? fromLevel : 0,
                rewards,
            });

            saveLevelBannerState(userId, { seenLevel, dismissedLevel });
        },

        closeLevelBanner({ rootState, commit }) {
            const userId = Number(rootState.auth?.userInfo?.ID || 0);
            const currentLevel = Number(rootState.auth?.userInfo?.game_info?.progress?.level || 0);
            const state = readLevelBannerState(userId) || { seenLevel: currentLevel, dismissedLevel: 0 };

            state.seenLevel = Math.max(Number(state.seenLevel || 0), currentLevel);
            state.dismissedLevel = Math.max(Number(state.dismissedLevel || 0), currentLevel);
            saveLevelBannerState(userId, state);

            commit('setLevelBanner', { visible: false, from: 0, rewards: [] });
        },

        showBulkLevelBanner({ dispatch, commit, rootState }, { oldLevel, newLevel, levelRewards = [] }) {
            const previousLevel = Number(oldLevel ?? 0);
            const nextLevel = Number(newLevel ?? 0);
            const userId = Number(rootState.auth?.userInfo?.ID || 0);

            if (nextLevel <= previousLevel) {
                dispatch('evaluateLevelBanner');
                return;
            }

            const state = readLevelBannerState(userId) || { seenLevel: 0, dismissedLevel: 0 };
            state.seenLevel = Math.max(Number(state.seenLevel || 0), nextLevel);
            saveLevelBannerState(userId, state);

            commit('setLevelBanner', {
                visible: true,
                level: nextLevel,
                from: previousLevel + 1,
                rewards: Array.isArray(levelRewards) ? levelRewards : [],
            });
        },

        async claimXp({ rootState }, matchId) {
            const userToken = rootState.auth?.authData?.token;

            if (!userToken) {
                throw new Error('Требуется авторизация');
            }

            return apiActions.game.claimXp(userToken, matchId);
        },
        async claimAllXp(ctx) {
            const userToken = ctx.rootState.auth?.authData?.token;

            if (!userToken) {
                throw new Error('Требуется авторизация');
            }

            const res = await apiActions.game.claimAllXp(userToken);
            return applyGamePayload(ctx, res);
        },
        async listBanks({ rootState }, limit = 30) {
            const userToken = rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            return apiActions.game.listBanks(userToken, limit);
        },
        async getMyContracts({ rootState }) {
            const userToken = rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            return apiActions.game.getMyContracts(userToken);
        },
        async getBankOperations({ rootState }, limit = 100) {
            const userToken = rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            return apiActions.game.getBankOperations(userToken, limit);
        },
        async openBank(ctx) {
            const userToken = ctx.rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            const res = await apiActions.game.openBank(userToken);
            return applyGamePayload(ctx, res);
        },
        async createDeposit(ctx, { bankId, amount }) {
            const userToken = ctx.rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            const res = await apiActions.game.createDeposit(userToken, bankId, amount);
            return applyGamePayload(ctx, res);
        },
        async takeLoan(ctx, { bankId, amount }) {
            const userToken = ctx.rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            const res = await apiActions.game.takeLoan(userToken, bankId, amount);
            return applyGamePayload(ctx, res);
        },
        async closeBank(ctx) {
            const userToken = ctx.rootState.auth?.authData?.token;
            if (!userToken) {
                throw new Error('Требуется авторизация');
            }
            const res = await apiActions.game.closeBank(userToken);
            return applyGamePayload(ctx, res);
        },
    },
    namespaced: true,
};
