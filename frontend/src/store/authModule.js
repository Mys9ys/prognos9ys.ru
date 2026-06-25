/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

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

        impersonation: {
            active: localStorage.getItem('lk_impersonating') === 'true',
            originalToken: localStorage.getItem('lk_token_original') || '',
        },

    }),
    getters: {
        canImpersonate(state) {
            if (state.impersonation.active && state.impersonation.originalToken) {
                return true;
            }

            const role = state.userInfo?.role;

            return !!state.userInfo?.can_impersonate
                || role === 'admin'
                || role === 'super_moder';
        },
    },
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
        },

        setImpersonation(state, payload) {
            state.impersonation = {
                ...state.impersonation,
                ...payload,
            }
            localStorage.setItem('lk_impersonating', payload.active ? 'true' : 'false')
            if (payload.originalToken !== undefined) {
                if (payload.originalToken) {
                    localStorage.setItem('lk_token_original', payload.originalToken)
                } else {
                    localStorage.removeItem('lk_token_original')
                }
            }
        }
    },
    actions: {

        logoutVue({commit}) {
            commit('setAuth', false)
            commit('setToken', '')
            commit('setUserInfo', [])
            commit('setImpersonation', { active: false, originalToken: '' })
            commit('mainPage/setNearest', {}, { root: true })
        },

        applyAuthUser({ commit }, userInfo) {
            commit('setUserInfo', userInfo)
            commit('setAuth', true)
            commit('setToken', userInfo.UF_TOKEN)
            commit('setLoginError', '')
        },

        async impersonateStart({ state, commit, dispatch }, targetUserId) {
            const targetId = Number(targetUserId);
            if (!targetId) {
                throw new Error('Не указан пользователь');
            }

            const actorToken = state.impersonation.active
                ? state.impersonation.originalToken
                : state.authData.token

            if (!actorToken) {
                return
            }

            const originalToken = state.impersonation.active
                ? state.impersonation.originalToken
                : state.authData.token

            const data = await apiActions.impersonation.start(actorToken, targetId)

            commit('setImpersonation', {
                active: true,
                originalToken,
            })
            dispatch('applyAuthUser', data.user)
            await dispatch('refreshGameInfo')
        },

        async impersonateStop({ state, commit, dispatch }) {
            const moderatorToken = state.impersonation.originalToken
            if (!moderatorToken) {
                return
            }

            const data = await apiActions.impersonation.stop(moderatorToken)

            commit('setImpersonation', {
                active: false,
                originalToken: '',
            })
            dispatch('applyAuthUser', data.user)
            await dispatch('refreshGameInfo')
        },

        async searchImpersonationUsers({ state }, query) {
            const actorToken = state.impersonation.active
                ? state.impersonation.originalToken
                : state.authData.token

            if (!actorToken) {
                return []
            }

            const data = await apiActions.impersonation.searchUsers(actorToken, query)

            return data.users || []
        },

        async authRequest({state, commit, dispatch}) {
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
                    commit('setImpersonation', { active: false, originalToken: '' })
                    await dispatch('refreshGameInfo')
                } else {
                    commit('setLoginError', response.data.mes)
                    commit('setAuth', false)
                }

                // console.log(response.data)
            } catch (e) {
                console.log('error', e)
            }
        },

        async loginRequest({state, commit, dispatch}) {

            commit('setTypeRequest', 'tokenLogin')
            try {
                const response = await axios.post(baseConfig.BASE_URL + 'auth/', state.authData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })

                if (response.data.status == 'ok') {
                    console.log('axios response', response.data)
                    commit('setUserInfo', response.data.info)
                    commit('setAuth', true)
                    await dispatch('refreshGameInfo')
                } else {
                    commit('setAuth', false)
                }

            } catch (e) {
                console.log('error', e)
            }
        },

        async refreshGameInfo({ state, commit }) {
            if (!state.authData.token) {
                return;
            }

            try {
                const data = await apiActions.game.getState(state.authData.token);
                if (data?.game) {
                    commit('setUserInfo', {
                        ...state.userInfo,
                        game_info: data.game,
                    });
                }
            } catch (e) {
                console.log('refreshGameInfo error', e);
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