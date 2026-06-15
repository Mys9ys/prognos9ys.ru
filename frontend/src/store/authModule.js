/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";

export const authModule = {
    state: () => ({
        loginData: {
            type: null,
            mail: null,
            pass: null,
        },

        loginError: '',

        authData: {
            type: null,
            token: localStorage.getItem('lk_token') || '',
        },

        avaData: {
            type: 'ava',
            file: ''
        },

        userInfo: [],

    }),
    getters: {},
    mutations: {
        setAuthData(state, authData) {
            state.authData = authData
        },

        setLoginData(state, loginData) {
            state.loginData = loginData
        },

        setUserInfo(state, userInfo) {
            state.userInfo = userInfo
        },

        setLoginError(state, loginError) {
            state.loginError = loginError
        },

        setAvaFile(state, avaFile) {
            state.avaData.file = avaFile
        },

        setAuth(state, value) {
            state.isAuth = value
            localStorage.setItem('lk_auth', value)
        },

        setToken(state, token) {
            state.authData.token = token
            localStorage.setItem('lk_token', token)
        },

        setTypeRequest(state, type) {
            state.authData.type = type
        }
    },
    actions: {

        logoutVue({commit}) {
            commit('setAuth', false)
            commit('setToken', '')
        },

        async authRequest({state, commit}) {
            console.log('axios data', state.loginData)

            try {
                const response = await axios.post(baseConfig.BASE_URL + 'auth/', state.loginData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )

                if (response.data.status == 'ok') {
                    console.log('axios response', response.data)
                    commit('setUserInfo', response.data.info)
                    commit('setAuth', true)
                    commit('setToken', response.data.info.UF_TOKEN)
                    commit('setLoginError', '')
                } else {
                    commit('setLoginError', response.data.mes)
                    commit('setAuth', false)
                }

                // console.log(response.data)
            } catch (e) {
                console.log('error', e)
            }
        },

        async loginRequest({state, commit}) {

            commit('setTypeRequest', 'tokenLogin')
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'auth/', state.authData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })

                if (response.data.status == 'ok') {
                    console.log('response.data', response.data)
                    commit('setUserInfo', response.data.info)
                    commit('setAuth', true)
                } else {
                    commit('setAuth', false)
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async avaSetRequest({state, commit}) {
            try {

                state.avaData.token = state.authData.token

                const response = await axios.post(baseConfig.BASE_URL + 'auth/', state.avaData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })

                if (response.data.status == 'ok') {
                    console.log('response.data', response.data)
                    commit('setUserInfo', response.data.info)
                }

            } catch (e) {
                console.log('error', e)
            }
        }
    },
    namespaced: true
}