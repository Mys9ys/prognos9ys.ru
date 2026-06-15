import KvnEvent from "@/pages/kvn/KvnEvent";
import KvnGame from "@/pages/kvn/KvnGame";

export const kvnEvent = {
    path: '/kvn/:event',
    component: KvnEvent,
    meta: {
        requiresAuth: true,
    }
}

export const kvnGame = {
    path: '/kvn/:event/:number',
    component: KvnGame
}
