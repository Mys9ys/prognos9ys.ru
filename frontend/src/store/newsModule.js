/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const newsModule = {
    state: () => ({

        last: {},

        errors: {},

        likeData: {},
        seenData: {},

    }),

    getters: {},
    mutations: {

        setLast(state, data) {
            state.last = data
        },

        setErrors(state, data){
            state.errors = data
        }
    },
    actions: {
        async getOneNews({state, commit}) {

            try {
                const response = await axios.post(baseConfig.BASE_URL + 'news/one/', state.setToken,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {
                    commit('setLast', response.data.info)
                } else {
                    commit('setErrors', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async setSeenToNews({state, commit}) {

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

        async setLikesToNews({state, commit}) {

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


    },


    namespaced: true

}