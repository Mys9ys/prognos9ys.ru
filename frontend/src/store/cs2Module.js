/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

export const cs2Module = {
    state: () => ({
        matches: [],
        match: [],
        prognosis: [],
        queryEvent: {
            events: '',
            userToken: '',
        },
        errors: {},
        queryMatch: {
            eventId: '',
            userToken: '',
            number: '',
        },
        queryPrognosis: {
            userToken: '',
            fields: [],
            map_scores_json: '',
            withBet: false,
        },
    }),

    mutations: {
        setMatchesData(state, data) {
            state.matches = data;
        },
        setMatchData(state, data) {
            state.match = data;
        },
        setPrognosisData(state, prognosis) {
            state.prognosis = prognosis;
        },
        setError(state, data) {
            state.errors = data;
        },
    },

    actions: {
        async getEventMatchesRequest({ state, commit }) {
            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.cs2.getEventMatches(
                        state.queryEvent.events,
                        state.queryEvent.userToken || ''
                    );
                } else {
                    const response = await axios.post(
                        baseConfig.BASE_URL + 'cs2/many/',
                        state.queryEvent,
                        { headers: { 'Content-Type': 'multipart/form-data' } }
                    );
                    responseData = response.data;
                }

                if (responseData.status === 'ok') {
                    commit('setMatchesData', responseData.info);
                    if (responseData.res?.prognosis) {
                        commit('setPrognosisData', responseData.res.prognosis);
                    }
                } else {
                    commit('setError', responseData.mes || 'что то не так');
                }
            } catch (e) {
                commit('setError', e.message || 'Ошибка загрузки');
            }
        },

        async getMatchRequest({ state, commit }) {
            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.cs2.getMatch(
                        state.queryMatch.eventId,
                        state.queryMatch.number,
                        state.queryMatch.userToken || ''
                    );
                } else {
                    const response = await axios.post(
                        baseConfig.BASE_URL + 'cs2/one/',
                        state.queryMatch,
                        { headers: { 'Content-Type': 'multipart/form-data' } }
                    );
                    responseData = response.data;
                }

                if (responseData.status === 'ok') {
                    commit('setMatchData', responseData.result);
                } else {
                    commit('setError', responseData.mes || 'что то не так');
                }
            } catch (e) {
                commit('setError', e.message || 'Ошибка загрузки матча');
            }
        },

        async sendUserPrognosis({ state, commit }) {
            commit('setError', {});

            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.cs2.sendPrognosis(
                        state.queryPrognosis.userToken,
                        state.queryPrognosis.fields,
                        state.queryPrognosis.map_scores_json,
                        state.queryPrognosis.withBet
                    );
                } else {
                    const response = await axios.post(
                        baseConfig.BASE_URL + 'cs2/send/',
                        state.queryPrognosis,
                        { headers: { 'Content-Type': 'multipart/form-data' } }
                    );
                    responseData = response.data;
                }

                if (responseData.status === 'ok') {
                    return { ok: true };
                }

                commit('setError', { mes: responseData.mes || 'Не удалось сохранить прогноз' });
                return { ok: false };
            } catch (e) {
                commit('setError', { mes: e.message || 'Ошибка при отправке прогноза' });
                return { ok: false };
            }
        },
    },
    namespaced: true,
};
