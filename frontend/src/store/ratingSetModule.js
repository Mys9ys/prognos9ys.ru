/* eslint-disable */
import { apiActions } from '@/api/bitrixClient';

export const ratingSetModule = {
    state: () => ({
        mySets: [],
        publicSets: [],
        activeSet: null,
        errors: null,
    }),

    mutations: {
        setMySets(state, data) {
            state.mySets = data;
        },
        setPublicSets(state, data) {
            state.publicSets = data;
        },
        setActiveSet(state, data) {
            state.activeSet = data;
        },
        setError(state, data) {
            state.errors = data;
        },
    },

    actions: {
        async loadSets({ commit }, { sport = 'football', eventId = null, userToken = '' } = {}) {
            try {
                const [myResponse, publicResponse] = await Promise.all([
                    userToken
                        ? apiActions.ratingSet.listMy(userToken, sport, eventId)
                        : Promise.resolve({ status: 'ok', sets: [] }),
                    apiActions.ratingSet.listPublic(sport, eventId),
                ]);

                if (myResponse.status === 'ok') {
                    commit('setMySets', myResponse.sets || []);
                }
                if (publicResponse.status === 'ok') {
                    commit('setPublicSets', publicResponse.sets || []);
                }
                commit('setError', null);
            } catch (e) {
                console.log('ratingSet load error', e);
                commit('setError', e?.message || 'Ошибка загрузки сборников');
            }
        },

        async createSet({ commit, dispatch }, { userToken, payload, sport, eventId }) {
            const response = await apiActions.ratingSet.create(userToken, payload);
            if (response.set) {
                commit('setActiveSet', response.set);
            }
            await dispatch('loadSets', { sport, eventId, userToken });
            return response;
        },

        async updateSet({ dispatch }, { userToken, setId, payload, sport, eventId }) {
            const response = await apiActions.ratingSet.update(userToken, setId, payload);
            await dispatch('loadSets', { sport, eventId, userToken });
            return response;
        },

        async deleteSet({ commit, dispatch }, { userToken, setId, sport, eventId }) {
            const response = await apiActions.ratingSet.delete(userToken, setId);
            commit('setActiveSet', null);
            await dispatch('loadSets', { sport, eventId, userToken });
            return response;
        },
    },

    namespaced: true,
};
