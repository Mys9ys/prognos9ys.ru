/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const recoverModule = {
    state: () => ({
        recoverCodeData: {
            type: null,
            mail: null,
            phone: null,
        },

        recoverPassData: {
            type: 'recoverPass',
            userId: '',
            pass: ''
        },

        recoverCodeError: {
            error: false,
            mes: ''
        },

        recoverPassError:{
            error: false,
            mes: ''
        },

        userID: '',

        sendCodeSuccess: false,
        sendPassSuccess: false
    }),
    getters: {},
    mutations: {
        setCodeError(state, mes){
            state.recoverCodeError.error = true
            state.recoverCodeError.mes = mes
        },
        setPassError(state, mes){
            state.recoverPassError.error = true
            state.recoverPassError.mes = mes
        },
        setCodeSuccess(state){
            state.sendCodeSuccess = true
        },
        setPassSuccess(state){
            state.sendPassSuccess = true
        },
        setUserId(state, id){
            state.userID = id
        },
    },
    actions: {

        async recoverCodeRequest({state, commit}) {
            try {

                const response = await axios.post(baseConfig.BASE_URL + 'send_code/', state.recoverCodeData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })

                if (response.data.status == 'ok') {
                    commit('setCodeSuccess')
                    commit('setUserId',response.data.id)
                } else {
                    commit('setCodeError', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },
        async recoverPassRequest({state, commit}) {
            try {

                const response = await axios.post(baseConfig.BASE_URL + 'recover_pass/', state.recoverPassData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })

                if (response.data.status == 'ok') {
                    console.log('axios data', response.data)
                    commit('setPassSuccess')
                } else {
                    commit('setPassError', response.data.mes)
                }

            } catch (e) {
                console.log('error', e)
            }
        },
    },
    namespaced: true
}