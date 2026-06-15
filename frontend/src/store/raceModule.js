/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const raceModule = {
    state: () => ({

        queryEvent: {},

        elements: {},

        oneRace: {},

        errors: {},

        sendSuccess: false

    }),
    getters: {},
    mutations: {
        setElementsData(state, data) {
            state.elements = data
        },

        setOneRace(state, data){
            state.oneRace = data
        },

        setSendSuccess(state, data){
            state.sendSuccess = data
        },

        setError(state, data) {
            state.errors = data
        },
    },
    actions: {

        async getEventElements({state, commit}) {
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'race/many/', state.queryEvent,
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

        async getOneElement({state, commit}) {
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'race/one/', state.queryEvent,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                    commit('setOneRace', response.data.info)
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

        async sendPognosisData({state, commit}) {
            console.log('state.queryEvent', state.queryEvent)
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'race/send/', state.queryEvent.info,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                    commit('setSendSuccess', true)
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