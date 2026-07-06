/* eslint-disable */
import {createRouter, createWebHistory} from "vue-router";
import routes from "@/router/routes";
import store from "@/store";
import { resolveLegacyProfileRedirect } from "@/router/legacyRedirects";
import { authRoute } from "@/utils/authRedirect";
import { logScreenVisit } from "@/utils/screenVisitLogger";

const router = createRouter({
    routes,
    history: createWebHistory(process.env.BASE_URL)
})

router.beforeEach((to) => {
    const legacy = resolveLegacyProfileRedirect(to);
    if (legacy) {
        return legacy;
    }

    const token = store.state.auth.authData.token;
    const isPublic = to.matched.some((record) => record.meta.public);
    const requiresAuth = to.matched.some((record) => record.meta.auth);

    if (requiresAuth && !token) {
        return authRoute(to.fullPath);
    }

    if (!token && !isPublic) {
        return '/catalog';
    }
});

router.afterEach((to) => {
    logScreenVisit(to);
});

export default router
