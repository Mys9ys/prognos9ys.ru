import {createStore} from "vuex";
import {kvnModule} from "@/store/kvnModule";
import {authModule} from "@/store/authModule";
import {regModule} from "@/store/regModule";
import {footballModule} from "@/store/footballModule";
import {cs2Module} from "@/store/cs2Module";
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
import {ratingSetModule} from "@/store/ratingSetModule";
import {gameModule} from "@/store/gameModule";

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
        cs2: cs2Module,
        race: raceModule,
        kvn: kvnModule,
        humor: humorModule,
        news: newsModule,

        catalog: catalogModule,
        rating: ratingModule,
        ratingSet: ratingSetModule,
        game: gameModule,
        mainPage: mainPageModule,

        icons: iconModule,

        championship: championshipModule
    },
    root: true
})
