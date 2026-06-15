/* eslint-disable */
import {createRouter, createWebHistory} from "vue-router";
import routes from "@/router/routes";
import store from "@/store";

const router = createRouter({
    routes,
    history: createWebHistory(process.env.BASE_URL)
})

router.beforeEach((to) => {
    const token = store.state.auth.authData.token;
    const isPublic = to.matched.some((record) => record.meta.public);

    if (!token && !isPublic) {
        return '/';
    }
});

export default router
