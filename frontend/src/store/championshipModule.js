/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

export const championshipModule = {
    state: () => ({

        raceData: {},
        footballData: {},
        queryData: {},
        errors: {},
    }),

    getters: {},
    mutations: {
        setRaceData(state, data){
            state.raceData = data
        },
        setFootballData(state, data){
            state.footballData = data
        },
        setQueryData(state, data) {
            state.queryData = data
        },
        setError(state, data) {
            state.errors = data
        },
    },
    actions: {
        async getRaceTable({state, commit}) {

            try {
                const response = await axios.post(baseConfig.BASE_URL + '/championship/race/', state.queryData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {
                    commit('setRaceData', response.data.result)
                } else {
                    commit('setError', response.data.mes || 'Не удалось загрузить таблицу')
                }

            } catch (e) {
                console.log('error', e)
                commit('setError', 'Ошибка загрузки таблицы')
            }
        },

        async getFootballTable({state, commit}) {

            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.championship.getFootballTable(
                        state.queryData.events,
                        state.queryData.token || ''
                    );
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'championship/football/', state.queryData,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        }
                    );
                    responseData = response.data;
                }

                if (responseData.status == 'ok') {
                    const tablePayload = responseData.result || responseData;
                    commit('setFootballData', {
                        groups: tablePayload.groups || {},
                        thirdPlaces: tablePayload.thirdPlaces || [],
                        groupMatches: tablePayload.groupMatches || {},
                        info: tablePayload.info || {},
                    });
                    commit('setError', null)
                } else {
                    commit('setError', responseData.mes || 'Не удалось загрузить таблицу')
                }

            } catch (e) {
                console.log('error', e)
                commit('setError', e?.message || 'Ошибка загрузки таблицы')
            }
        },


    },


    namespaced: true

}