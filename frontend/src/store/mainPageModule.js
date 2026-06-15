/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const mainPageModule = {
    state: () => ({

        arrNearest: {},

        setToken: {},

        errors: {}

    }),

    getters: {},
    mutations: {

        setNearest(state, data) {
            state.arrNearest = data
        },

        setErrors(state, data){
            state.errors = data
        }
    },
    actions: {
        async getNearest({state, commit}) {

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
            }
        },
    },


    namespaced: true

}