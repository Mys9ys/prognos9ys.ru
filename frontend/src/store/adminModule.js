/* eslint-disable */
import axios from "axios";
import {baseConfig} from "@/store/config";

export const adminModule = {
    state: () => ({

        queryEvent: {},

        errors: {},

        sendSuccess: false

    }),

    getters: {},
    mutations: {
        setElementsData(state, data) {
            state.elements = data
        },
        setError(state, data) {
            state.errors = data
        },

    },

    actions: {

        async setRaceResult({state, commit}) {
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'admin/race/result/', state.queryEvent.info,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                    commit('setElementsData', response.data.info)
                } else {
                    commit('setError', 'что то пошло не так')
                    if (response.data.status == 'error') {
                        commit('setError', response.data.mes)
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async calcRaceResult({state, commit}) {
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'admin/race/calc/', state.queryEvent,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                    commit('setElementsData', response.data.info)
                } else {
                    commit('setError', 'что то пошло не так')
                    if (response.data.status == 'error') {
                        commit('setError', response.data.mes)
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        //////////////////////////////////////////////////////

        async setFootballResult({state, commit}) {
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'admin/football/result/', state.queryEvent,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                    commit('setElementsData', response.data.info)
                } else {
                    commit('setError', 'что то пошло не так')
                    if (response.data.status == 'error') {
                        commit('setError', response.data.mes)
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async calcFootballResult({state, commit}) {
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'admin/football/calc/', state.queryEvent,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                    commit('setElementsData', response.data.info)
                } else {
                    commit('setError', 'что то пошло не так')
                    if (response.data.status == 'error') {
                        commit('setError', response.data.mes)
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },

    },

    namespaced: true

}