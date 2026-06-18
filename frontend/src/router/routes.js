import MainPage from "@/pages/MainPage";
import AuthPage from "@/pages/auth/AuthPage";
import RegisterPage from "@/pages/auth/RegisterPage";
import {kvnEvent, kvnGame} from "@/pages/kvn/routes";
import CatalogPage from "@/pages/CatalogPage";
import RatingPage from "@/pages/RatingPage";
import MyProfilePage from "@/pages/MyProfilePage";
import ProfilePage from "@/pages/ProfilePage";
import RecoverMail from "@/pages/auth/RecoverMail";
import RecoverSuccess from "@/pages/auth/RecoverSuccess";
import HumorPage from "@/pages/HumorPage";
import NewsPage from "@/pages/NewsPage";
import RaceEvent from "@/pages/race/RaceEvent";
import RacePage from "@/pages/race/RacePage";
import FootballPage from "@/pages/football/FootballPage";
import FootballEvent from "@/pages/football/FootballEvent";
import ChampionshipPage from "@/pages/ChampionshipPage";
import FaqPage from "@/pages/FaqPage";


const routes = [
    {
        path: '/',
        redirect: '/catalog',
    },
    {
        path: '/main',
        component: MainPage,
        meta: { public: true },
    },

    {
        path: '/auth',
        component: AuthPage,
        meta: { public: true },
    },
    {
        path: '/register',
        component: RegisterPage,
        meta: { public: true },
    },

    {
        path: '/catalog',
        component: CatalogPage,
        meta: { public: true },
    },

    {
        path: '/ratings',
        component: RatingPage,
        meta: { public: true },
    },

    {
        path: '/race/:event',
        component: RaceEvent,
    },

    {
        path: '/race/:event/:number',
        component: RacePage,
    },

    {
        path: '/humor',
        component: HumorPage,
        meta: { public: true },
    },

    {
        path: '/news',
        component: NewsPage,
        meta: { public: true },
    },

    {
        path: '/profile',
        component: MyProfilePage,
    },

    {
        path: '/profile/:id',
        component: ProfilePage,
        meta: { public: true },
    },

    {
        path: '/championship/:type/:event',
        component: ChampionshipPage,
        meta: { public: true },
    },

    {
        path: '/recover',
        component: RecoverMail,
        meta: { public: true },
    },
    {
        path: '/recover_success',
        component: RecoverSuccess,
        meta: { public: true },
    },

    {
        path: '/football/:event',
        component: FootballEvent,
        props: true,
        meta: { public: true },
    },

    {
        path: '/football/:event/:number',
        component: FootballPage,
        props: true,
        meta: { public: true },
    },

    {
        path: '/faq',
        component: FaqPage,
        meta: { public: true },
    },
    // квновские роуты -->
    kvnEvent,
    kvnGame
    // квновские роуты /-->
]

export default routes