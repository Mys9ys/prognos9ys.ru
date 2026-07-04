import MainPage from "@/pages/MainPage";
import AuthPage from "@/pages/auth/AuthPage";
import RegisterPage from "@/pages/auth/RegisterPage";
import {kvnEvent, kvnGame} from "@/pages/kvn/routes";
import CatalogPage from "@/pages/CatalogPage";
import RatingPage from "@/pages/RatingPage";
import ProfilePage from "@/pages/ProfilePage";
import RecoverMail from "@/pages/auth/RecoverMail";
import RecoverSuccess from "@/pages/auth/RecoverSuccess";
import HumorPage from "@/pages/HumorPage";
import NewsPage from "@/pages/NewsPage";
import RaceEvent from "@/pages/race/RaceEvent";
import RacePage from "@/pages/race/RacePage";
import FootballPage from "@/pages/football/FootballPage";
import FootballEvent from "@/pages/football/FootballEvent";
import Cs2Page from "@/pages/cs2/Cs2Page";
import Cs2Event from "@/pages/cs2/Cs2Event";
import ChampionshipPage from "@/pages/ChampionshipPage";
import FaqPage from "@/pages/FaqPage";
import FaqArticlePage from "@/pages/FaqArticlePage";

const authRoute = { meta: { auth: true } };

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
        component: () => import('@/pages/profile/ProfileOverviewPage.vue'),
        ...authRoute,
    },

    {
        path: '/prognosis',
        component: () => import('@/pages/profile/ProfilePrognosisPage.vue'),
        ...authRoute,
    },

    {
        path: '/inventory',
        component: () => import('@/pages/game/InventoryPage.vue'),
        ...authRoute,
    },

    {
        path: '/equipment',
        component: () => import('@/pages/game/EquipmentPage.vue'),
        ...authRoute,
    },

    {
        path: '/collection',
        component: () => import('@/pages/game/CollectionPage.vue'),
        ...authRoute,
    },

    {
        path: '/achievements',
        component: () => import('@/pages/game/AchievementsPage.vue'),
        ...authRoute,
    },

    {
        path: '/settings',
        component: () => import('@/pages/SettingsPage.vue'),
        ...authRoute,
    },

    {
        path: '/bank',
        component: () => import('@/pages/game/BankPage.vue'),
        ...authRoute,
    },

    {
        path: '/treasury',
        component: () => import('@/pages/game/TreasuryPage.vue'),
        ...authRoute,
    },

    {
        path: '/market',
        component: () => import('@/pages/game/MarketPage.vue'),
        ...authRoute,
    },

    {
        path: '/work',
        component: () => import('@/pages/game/WorkPage.vue'),
        ...authRoute,
    },

    {
        path: '/estate',
        component: () => import('@/pages/game/EstatePage.vue'),
        ...authRoute,
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
        path: '/cs2/:event',
        component: Cs2Event,
        props: true,
        meta: { public: true },
    },

    {
        path: '/cs2/:event/:number',
        component: Cs2Page,
        props: true,
        meta: { public: true },
    },

    {
        path: '/faq',
        component: FaqPage,
        meta: { public: true },
    },
    {
        path: '/faq/:slug',
        component: FaqArticlePage,
        meta: { public: true },
    },
    // квновские роуты -->
    kvnEvent,
    kvnGame
    // квновские роуты /-->
]

export default routes
