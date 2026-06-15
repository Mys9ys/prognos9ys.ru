import {createStore} from "vuex";
import {kvnModule} from "@/store/kvnModule";
import {authModule} from "@/store/authModule";
import {regModule} from "@/store/regModule";
import {footballModule} from "@/store/footballModule";
import {catalogModule} from "@/store/catalogModule";
import {ratingModule} from "@/store/ratingModule";
import {recoverModule} from "@/store/recoverModule";
import {profileModule} from "@/store/profileModule";
import {iconModule} from "@/store/iconModule";
import {mainPageModule} from "@/store/mainPageModule";
import {humorModule} from "@/store/humorModule";
import {raceModule} from "@/store/raceModule";
import {adminModule} from "@/store/adminModule";
import {newsModule} from "@/store/newsModule";
import {championshipModule} from "@/store/championshipModule";

export default createStore({
    state: {
        mainLoader: false,
    },

    mutations: {
        setMainLoading(state, data){
            console.log('loading main', data)
            state.mainLoader = data
        }
    },
    modules: {

        auth: authModule,
        reg: regModule,
        admin: adminModule,
        recover: recoverModule,
        profile: profileModule,

        football: footballModule,
        race: raceModule,
        kvn: kvnModule,
        humor: humorModule,
        news: newsModule,

        catalog: catalogModule,
        rating: ratingModule,
        mainPage: mainPageModule,

        icons: iconModule,

        championship: championshipModule
    },
    root: true
})
