/* eslint-disable */
import { apiActions } from '@/api/bitrixClient';

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
    },
    namespaced: true,
};
