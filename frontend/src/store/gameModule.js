/* eslint-disable */
import { apiActions } from '@/api/bitrixClient';

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
    state: () => ({}),
    getters: {},
    mutations: {},
    actions: {
        async claimXp({ rootState }, matchId) {
            const userToken = rootState.auth?.authData?.token;

            if (!userToken) {
                throw new Error('Требуется авторизация');
            }

            return apiActions.game.claimXp(userToken, matchId);
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
