/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const humorModule = {
    state: () => ({

        prank: {},

        setToken: {},

        errors: {},

        likeData: {},

        newPrank: {}

    }),

    getters: {},
    mutations: {

        setPrank(state, data) {
            state.prank = data
        },

        setErrors(state, data){
            state.errors = data
        }
    },
    actions: {
        async getOnePrank({state, commit}) {

            try {
                const response = await axios.post(baseConfig.BASE_URL + 'humor/one/', state.setToken,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {
                    commit('setPrank', response.data.info)
                } else {
                    commit('setErrors', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async setLikesToPrank({state, commit}) {

            try {
                const response = await axios.post(baseConfig.BASE_URL + 'humor/likes/', state.likeData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                } else {
                    commit('setErrors', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async sendNewPrank({state, commit}) {

            console.log('sendNewPrank')

            try {
                const response = await axios.post(baseConfig.BASE_URL + 'humor/send/', state.newPrank,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {

                } else {
                    commit('setErrors', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },
    },


    namespaced: true

}