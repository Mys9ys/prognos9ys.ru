/* eslint-disable */
import axios from "axios";

import {baseConfig} from "@/store/config";
import {apiActions} from "@/api/bitrixClient";

export const ratingModule = {
    state: () => ({
        footballRating: {},

        raceRating: [],

        ratingData: {
            event: '',
            setId: null,
            selector: 'all',
            limit: 50,
        },

    }),
    getters: {},
    mutations: {
        setFootballRatings(state, data) {
            if (data && typeof data === 'object' && data.selector && data.ratings) {
                state.footballRating = {
                    ...(state.footballRating || {}),
                    ...data.ratings,
                };
                return;
            }

            state.footballRating = data;
        },

        clearFootballRatings(state) {
            state.footballRating = {};
        },

        setRaceRatings(state, data){
            state.raceRating = data
        }

    },
    actions: {
        async getFootballRatings({state, commit, rootState}) {
            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    const userToken = rootState.auth?.authData?.token || '';
                    responseData = await apiActions.rating.getFootball(
                        state.ratingData.event,
                        state.ratingData.setId || null,
                        userToken,
                        {
                            selector: state.ratingData.selector || 'all',
                            limit: state.ratingData.limit || 50,
                        }
                    );
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'football/ratings/', state.ratingData,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        }
                    );
                    responseData = response.data;
                }

                if (responseData.status == 'ok') {
                    commit('setFootballRatings', {
                        selector: state.ratingData.selector || 'all',
                        ratings: responseData.ratings,
                    })
                } else {
                    if (responseData.status == 'error') {
                        commit('setError', responseData.mes)
                    } else {
                        commit('setError', 'Что то пошло не так')
                    }
                }


            } catch (e) {
                console.log('error', e)
            }
        },

        async getRaceRatings({state, commit}) {
            try {
                let responseData;

                if (baseConfig.USE_BITRIX_API) {
                    responseData = await apiActions.rating.getRace(state.ratingData.events);
                } else {
                    const response = await axios.post(baseConfig.BASE_URL + 'race/ratings/', state.ratingData,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data'
                            }
                        }
                    );
                    responseData = response.data;
                }

                if (responseData.status == 'ok') {
                    console.log('response.data.ratings',responseData.ratings)
                    commit('setRaceRatings', responseData.ratings)
                } else {
                    if (responseData.status == 'error') {
                        commit('setError', responseData.mes)
                    } else {
                        commit('setError', 'Что то пошло не так')
                    }
                }

            } catch (e) {
                console.log('error', e)
            }
        },
    },
    namespaced: true

}