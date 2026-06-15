/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

export const catalogModule = {
    state: () => ({
        catalogData: {},
        ratingEvents: {},

        queryData: {
            type: ''
        },

    }),

    getters: {},
    mutations: {
        setCatalogData(state, data) {
            state.catalogData = data
        },
        setRatingEvents(state, data) {
            state.ratingEvents = data
        },
    },
    actions: {

        async getEventsInfo({state, commit}) {
            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.catalog.getEvents(state.queryData.type);
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'events/', state.queryData,
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
                    if(state.queryData.type === 'catalog') {
                        commit('setCatalogData', responseData.result)
                    } else {
                        commit('setRatingEvents', responseData.result)
                    }

                } else {
                    commit('setError', responseData.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },

    },
    namespaced: true
}
