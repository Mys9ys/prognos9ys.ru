/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

export const profileModule = {
    state: () => ({
        profileRequest: {},
        profileData: {
            load: false
        },
    }),

    getters: {},
    mutations: {
        setProfileData(state, data) {
            state.profileData = data
            state.profileData.load = true
        },
        setError(state, message) {
            state.profileData.error = message
        },
    },
    actions: {

        async getProfileData({state, commit}) {

            if(!state.profileData.load){
                try {
                    let responseData;

                    if (baseConfig.USE_BITRIX_API) {
                        if (state.profileRequest.userToken) {
                            responseData = await apiActions.profile.getMy(state.profileRequest.userToken);
                        } else if (state.profileRequest.userId) {
                            responseData = await apiActions.profile.getPublic(Number(state.profileRequest.userId));
                        } else {
                            commit('setError', 'Не указан пользователь');
                            return;
                        }
                    } else {
                        const response = await axios.post(baseConfig.BASE_URL + 'profile/', state.profileRequest,
                            {
                                headers: {
                                    'Content-Type': 'multipart/form-data'
                                }
                            }
                        );
                        responseData = response.data;
                    }

                    if (responseData.status === 'ok') {
                        commit('setProfileData', responseData.profile)
                    } else {
                        commit('setError', responseData.mes || 'что то не так')
                    }

                } catch (e) {
                    console.log('error', e)
                    commit('setError', e.message || 'что то не так')
                }
            }
        },

    },
    namespaced: true
}
