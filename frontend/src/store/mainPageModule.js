/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const mainPageModule = {
    state: () => ({

        arrNearest: {},

        setToken: {},

        errors: {},

        loading: false,

    }),

    getters: {},
    mutations: {

        setNearest(state, data) {
            state.arrNearest = data
        },

        setErrors(state, data){
            state.errors = data
        },

        setLoading(state, value) {
            state.loading = !!value
        }
    },
    actions: {
        async getNearest({state, commit}) {
            commit('setLoading', true)

            try {
                const response = await axios.post(baseConfig.BASE_URL + 'main_page/', state.setToken,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {
                    commit('setNearest', response.data.result)
                } else {
                    commit('setErrors', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            } finally {
                commit('setLoading', false)
            }
        },
    },


    namespaced: true

}