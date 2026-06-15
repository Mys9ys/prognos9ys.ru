/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

export const footballModule = {
    state: () => ({
        matches: [],

        match: [],

        prognosis: [],

        queryEvent: {
            eventId:'',
            userToken: ''
        },

        errors: {},

        queryMatch: {
            eventId:'',
            userToken: '',
            number: ''
        },

        queryPrognosis: {
            userToken: '',
            fields: []
        },

    }),

    getters: {},
    mutations: {
        setMatchesData(state, data) {
            state.matches = data
        },

        setMatchData(state, data) {
            state.match = data
        },

        setPrognosisData(state, prognosis){
            state.prognosis = prognosis
        },

        setError(state, data){
            state.errors = data
        }

    },
    actions: {

        async getEventMatchesRequest({state, commit}) {

            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.football.getEventMatches(
                        state.queryEvent.events,
                        state.queryEvent.userToken || ''
                    );
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'football/many/', state.queryEvent,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        }
                    );
                    responseData = response.data;
                }

                if (responseData.status === 'ok') {
                    console.log('axios data', responseData)
                    commit('setMatchesData', responseData.info)
                    if (responseData.res?.prognosis) {
                        commit('setPrognosisData', responseData.res.prognosis)
                    }
                } else {
                    if(responseData.mes) {
                        commit('setError', responseData.mes)
                    } else {
                        commit('setError', 'что то не так')
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async getMatchRequest({state, commit}) {

            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.football.getMatch(
                        state.queryMatch.eventId,
                        state.queryMatch.number,
                        state.queryMatch.userToken || ''
                    );
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'football/one/', state.queryMatch,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        }
                    );
                    responseData = response.data;
                }

                if (responseData.status == 'ok') {
                    console.log('axios data', responseData)
                    commit('setMatchData', responseData.result)
                } else {
                    if(responseData.mes) {
                        commit('setError', responseData.mes)
                    } else {
                        commit('setError', 'что то не так')
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async sendUserPrognosis({state, commit}) {
            commit('setError', {});

            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.football.sendPrognosis(
                        state.queryPrognosis.userToken,
                        state.queryPrognosis.fields
                    );
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'football/send/', state.queryPrognosis,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        }
                    );
                    responseData = response.data;
                }

                if (responseData.status === 'ok') {
                    console.log('axios data', responseData);
                    return { ok: true };
                }

                commit('setError', { mes: responseData.mes || 'Не удалось сохранить прогноз' });
                return { ok: false };

            } catch (e) {
                console.log('error', e);
                commit('setError', { mes: e.message || 'Ошибка при отправке прогноза' });
                return { ok: false };
            }
        },

    },
    namespaced: true
}
