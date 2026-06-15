/* eslint-disable */
import axios from "axios";
import {baseConfig} from "@/store/config";

export const kvnModule = {
    state: () => ({
        kvnEventData: {},

        baseUrl: baseConfig.BASE_URL
    }),
    getters: {
        getGames(state){
            return state.kvnEventData.games

        },
        getEventInfo(state){
            return state.kvnEventData.event_active
        }
    },
    mutations: {
        setEventData(state, kvnEventData){
            state.kvnEventData = kvnEventData
        }
    },
    actions: {
        async fetchAnswer({state, commit}) {
            try {
                const response = await axios.get(state.baseUrl+'kvn/',{})
                commit('setEventData', response.data)
                console.log(response.data)
            }  catch (e) {
                console.log('error', e)
            }
        }
    },
    namespaced: true

}